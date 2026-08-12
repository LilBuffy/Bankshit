<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT t.*, tt.code AS type_code
     FROM transactions t JOIN transaction_types tt ON tt.id = t.type_id
     WHERE t.account_id = ?
     ORDER BY t.created_at DESC LIMIT 6'
);
$stmt->execute([$user['account_id']]);
$recentTransactions = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT tt.code AS type_code, SUM(t.amount) AS total
     FROM transactions t JOIN transaction_types tt ON tt.id = t.type_id
     WHERE t.account_id = ? AND MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())
     GROUP BY tt.code"
);
$stmt->execute([$user['account_id']]);
$monthly = ['deposit' => 0, 'withdrawal' => 0, 'transfer_out' => 0, 'transfer_in' => 0];
foreach ($stmt->fetchAll() as $row) {
    $monthly[$row['type_code']] = (float)$row['total'];
}
$monthlySpending = $monthly['withdrawal'] + $monthly['transfer_out'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(remaining_balance),0) AS total FROM loans WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user['id']]);
$loanSummary = $stmt->fetch();

$pageTitle = t('dashboard_welcome');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('dashboard_welcome')) ?>, <?= e($user['first_name']) ?> 👋</h1>

<div class="grid grid-2 mb-24" style="grid-template-columns: 1.3fr 1fr;">
    <div class="balance-card">
        <div class="balance-label"><?= e(t('dashboard_available_balance')) ?></div>
        <div class="balance-amount"><?= e(format_money($user['balance'])) ?></div>
        <div class="balance-account"><?= e(t('dashboard_account_number')) ?>: <?= e($user['account_number']) ?></div>
        <?php if ($user['account_status'] !== 'active'): ?>
            <div class="mt-16"><span class="badge badge-danger"><?= e(t('status_locked')) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3><?= e(t('dashboard_quick_actions')) ?></h3></div>
        <div class="grid grid-2">
            <a href="<?= APP_BASE_URL ?>/deposit.php" class="btn btn-secondary"><?= e(t('action_deposit')) ?></a>
            <a href="<?= APP_BASE_URL ?>/withdraw.php" class="btn btn-secondary"><?= e(t('action_withdraw')) ?></a>
            <a href="<?= APP_BASE_URL ?>/transfer.php" class="btn btn-secondary"><?= e(t('action_transfer')) ?></a>
            <a href="<?= APP_BASE_URL ?>/loans.php" class="btn btn-secondary"><?= e(t('action_pay_loan')) ?></a>
        </div>
    </div>
</div>

<div class="grid grid-3 mb-24">
    <div class="card stat-card">
        <span class="stat-label"><?= e(t('dashboard_deposits')) ?></span>
        <span class="stat-value positive"><?= e(format_money($monthly['deposit'] + $monthly['transfer_in'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(t('dashboard_spending')) ?></span>
        <span class="stat-value negative"><?= e(format_money($monthlySpending)) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(t('dashboard_active_loans')) ?></span>
        <span class="stat-value"><?= (int)$loanSummary['c'] ?> · <?= e(format_money($loanSummary['total'])) ?></span>
    </div>
</div>

<div class="grid grid-2" style="grid-template-columns: 1.3fr 1fr; align-items:start;">
    <div class="card">
        <div class="card-header">
            <h3><?= e(t('dashboard_recent_transactions')) ?></h3>
            <a href="<?= APP_BASE_URL ?>/transactions.php" class="btn btn-ghost btn-sm"><?= e(t('dashboard_view_all')) ?></a>
        </div>

        <?php if (empty($recentTransactions)): ?>
            <div class="empty-state"><?= e(t('dashboard_no_transactions')) ?></div>
        <?php else: ?>
            <?php foreach ($recentTransactions as $t): ?>
                <?php $isCredit = in_array($t['type_code'], ['deposit', 'transfer_in', 'loan_disbursement'], true); ?>
                <div class="txn-row">
                    <div class="txn-icon <?= $isCredit ? 'in' : 'out' ?>"><?= $isCredit ? '↓' : '↑' ?></div>
                    <div class="txn-body">
                        <div class="txn-title"><?= e(ucwords(str_replace('_', ' ', $t['type_code']))) ?></div>
                        <div class="txn-meta"><?= e(format_date($t['created_at'])) ?></div>
                    </div>
                    <div class="txn-amount <?= $isCredit ? 'in' : 'out' ?>"><?= $isCredit ? '+' : '-' ?><?= e(format_money($t['amount'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3><?= e(t('dashboard_monthly_overview')) ?></h3></div>
        <canvas id="monthlyChart" style="width:100%;height:180px;"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('monthlyChart');
    if (canvas && window.NovaBankCharts) {
        window.NovaBankCharts.drawBarChart(canvas, ['Deposits', 'Withdrawals', 'Transfers Out'], [
            { value: <?= (float)($monthly['deposit'] + $monthly['transfer_in']) ?>, color: 'rgb(45,212,167)' },
            { value: <?= (float)$monthly['withdrawal'] ?>, color: 'rgb(242,109,109)' },
            { value: <?= (float)$monthly['transfer_out'] ?>, color: 'rgb(61,139,253)' }
        ]);
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
