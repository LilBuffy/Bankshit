<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $loanId = (int)($_POST['loan_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM loans WHERE id = ?');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan || !in_array($loan['status'], ['pending', 'under_review'], true)) {
        $errors[] = 'Loan not found or already decided.';
    } elseif ($action === 'reject') {
        $pdo->prepare('UPDATE loans SET status = "rejected", decided_at = NOW(), decided_by = ? WHERE id = ?')
            ->execute([current_user_id(), $loanId]);
        create_notification($pdo, (int)$loan['user_id'], 'loan_status', 'Loan rejected', 'Your loan application ' . $loan['loan_ref'] . ' was rejected.');
        log_admin_action((int)current_user_id(), 'reject_loan', 'loan', $loanId);
        flash_set('success', 'Loan rejected.');
        redirect('/admin/loans.php');
    } elseif ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT a.id AS account_id, a.balance, a.status FROM accounts a WHERE a.user_id = ? FOR UPDATE');
            $stmt->execute([$loan['user_id']]);
            $account = $stmt->fetch();

            if (!$account || $account['status'] !== 'active') {
                throw new RuntimeException('Borrower account is not active.');
            }

            $newBalance = round((float)$account['balance'] + (float)$loan['principal'], 2);
            $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?')->execute([$newBalance, $account['account_id']]);

            $ref = generate_reference('TXN');
            $ins = $pdo->prepare(
                'INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, description, status)
                 VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = "loan_disbursement"), ?, ?, ?, "completed")'
            );
            $ins->execute([$ref, $account['account_id'], $loan['principal'], $newBalance, 'Loan disbursement ' . $loan['loan_ref']]);

            $pdo->prepare('UPDATE loans SET status = "active", decided_at = NOW(), decided_by = ? WHERE id = ?')
                ->execute([current_user_id(), $loanId]);

            create_notification($pdo, (int)$loan['user_id'], 'loan_status', 'Loan approved', 'Your loan ' . $loan['loan_ref'] . ' was approved and disbursed.');
            log_admin_action((int)current_user_id(), 'approve_loan', 'loan', $loanId, $ref);

            $pdo->commit();
            flash_set('success', 'Loan approved and disbursed.');
            redirect('/admin/loans.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Loan approval failed: ' . $e->getMessage());
            $errors[] = 'Could not approve loan. Please try again.';
        }
    }
}

$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['pending', 'under_review', 'approved', 'rejected', 'active', 'fully_paid', 'defaulted'];
$where = '1=1';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $where = 'l.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT l.*, u.username FROM loans l JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.applied_at DESC LIMIT 50");
$stmt->execute($params);
$loans = $stmt->fetchAll();

$statusBadge = [
    'pending' => 'badge-warning', 'under_review' => 'badge-warning',
    'approved' => 'badge-success', 'active' => 'badge-success',
    'fully_paid' => 'badge-neutral', 'rejected' => 'badge-danger', 'defaulted' => 'badge-danger',
];

$pageTitle = 'Loans';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>Loan Management</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card mb-24">
    <form method="get" class="flex-gap">
        <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($validStatuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <?php if (empty($loans)): ?>
        <div class="empty-state">No loans found.</div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Ref</th><th>User</th><th>Principal</th><th>Term</th><th>Status</th><th>Applied</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><?= e($loan['loan_ref']) ?></td>
                    <td><?= e($loan['username']) ?></td>
                    <td><?= e(format_money($loan['principal'])) ?></td>
                    <td><?= (int)$loan['term_months'] ?> mo</td>
                    <td><span class="badge <?= $statusBadge[$loan['status']] ?? 'badge-neutral' ?>"><?= e(ucwords(str_replace('_',' ',$loan['status']))) ?></span></td>
                    <td><?= e(format_date($loan['applied_at'])) ?></td>
                    <td class="flex-gap">
                        <a href="<?= APP_BASE_URL ?>/admin/loan-detail.php?ref=<?= urlencode($loan['loan_ref']) ?>" class="btn btn-ghost btn-sm">View</a>
                        <?php if (in_array($loan['status'], ['pending', 'under_review'], true)): ?>
                        <form method="post" data-confirm="Approve this loan and disburse funds?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="loan_id" value="<?= (int)$loan['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                        </form>
                        <form method="post" data-confirm="Reject this loan?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="loan_id" value="<?= (int)$loan['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
