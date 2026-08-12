<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$ref = trim($_GET['ref'] ?? '');

$stmt = $pdo->prepare('SELECT l.*, u.username, u.email FROM loans l JOIN users u ON u.id = l.user_id WHERE l.loan_ref = ?');
$stmt->execute([$ref]);
$loan = $stmt->fetch();

if (!$loan) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/admin-header.php';
    echo '<div class="card empty-state">Loan not found.</div>';
    require __DIR__ . '/includes/admin-footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM loan_payments WHERE loan_id = ? ORDER BY paid_at DESC');
$stmt->execute([$loan['id']]);
$payments = $stmt->fetchAll();

$pageTitle = $loan['loan_ref'];
require __DIR__ . '/includes/admin-header.php';
?>

<h1><?= e($loan['loan_ref']) ?> <span class="text-faint">— <?= e($loan['username']) ?></span></h1>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card">
        <h3>Loan Details</h3>
        <table>
            <tr><td class="text-faint">Borrower</td><td><?= e($loan['username']) ?> (<?= e($loan['email']) ?>)</td></tr>
            <tr><td class="text-faint">Principal</td><td><?= e(format_money($loan['principal'])) ?></td></tr>
            <tr><td class="text-faint">Rate</td><td><?= e((string)$loan['interest_rate']) ?>%</td></tr>
            <tr><td class="text-faint">Term</td><td><?= (int)$loan['term_months'] ?> months</td></tr>
            <tr><td class="text-faint">Monthly Payment</td><td><?= e(format_money($loan['monthly_payment'])) ?></td></tr>
            <tr><td class="text-faint">Total Repayment</td><td><?= e(format_money($loan['total_repayment'])) ?></td></tr>
            <tr><td class="text-faint">Remaining</td><td><?= e(format_money($loan['remaining_balance'])) ?></td></tr>
            <tr><td class="text-faint">Purpose</td><td><?= e($loan['purpose']) ?></td></tr>
            <tr><td class="text-faint">Status</td><td><span class="badge badge-neutral"><?= e(ucwords(str_replace('_',' ',$loan['status']))) ?></span></td></tr>
        </table>
    </div>
    <div class="card">
        <h3>Payment History</h3>
        <?php if (empty($payments)): ?>
            <div class="empty-state">No payments yet.</div>
        <?php else: ?>
            <?php foreach ($payments as $p): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title"><?= e(format_money($p['amount'])) ?></div>
                        <div class="txn-meta"><?= e(format_date($p['paid_at'])) ?></div>
                    </div>
                    <div class="text-faint">Bal: <?= e(format_money($p['remaining_balance'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
