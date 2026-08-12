<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$ref = trim($_GET['ref'] ?? '');

$stmt = db()->prepare('SELECT * FROM loans WHERE loan_ref = ? AND user_id = ?');
$stmt->execute([$ref, $user['id']]);
$loan = $stmt->fetch();

if (!$loan) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card empty-state">Loan not found.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT * FROM loan_payments WHERE loan_id = ? ORDER BY paid_at DESC');
$stmt->execute([$loan['id']]);
$payments = $stmt->fetchAll();

$statusBadge = [
    'pending' => 'badge-warning', 'under_review' => 'badge-warning',
    'approved' => 'badge-success', 'active' => 'badge-success',
    'fully_paid' => 'badge-neutral', 'rejected' => 'badge-danger', 'defaulted' => 'badge-danger',
];

$pageTitle = $loan['loan_ref'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-16">
    <h1><?= e($loan['loan_ref']) ?></h1>
    <span class="badge <?= $statusBadge[$loan['status']] ?? 'badge-neutral' ?>"><?= e(t('status_' . $loan['status'])) ?></span>
</div>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card">
        <h3>Loan Details</h3>
        <table>
            <tr><td class="text-faint">Principal</td><td><?= e(format_money($loan['principal'])) ?></td></tr>
            <tr><td class="text-faint">Interest Rate</td><td><?= e((string)$loan['interest_rate']) ?>%</td></tr>
            <tr><td class="text-faint"><?= e(t('loans_term')) ?></td><td><?= (int)$loan['term_months'] ?> months</td></tr>
            <tr><td class="text-faint"><?= e(t('loans_monthly_payment')) ?></td><td><?= e(format_money($loan['monthly_payment'])) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_total_repayment')) ?></td><td><?= e(format_money($loan['total_repayment'])) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_remaining')) ?></td><td><strong><?= e(format_money($loan['remaining_balance'])) ?></strong></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_purpose')) ?></td><td><?= e($loan['purpose']) ?></td></tr>
            <tr><td class="text-faint">Applied</td><td><?= e(format_date($loan['applied_at'])) ?></td></tr>
        </table>

        <?php if (in_array($loan['status'], ['active'], true) && (float)$loan['remaining_balance'] > 0): ?>
            <a href="<?= APP_BASE_URL ?>/loan-repay.php?ref=<?= urlencode($loan['loan_ref']) ?>" class="btn btn-primary btn-block mt-16"><?= e(t('loans_repay')) ?></a>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Payment History</h3>
        <?php if (empty($payments)): ?>
            <div class="empty-state">No payments made yet.</div>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
