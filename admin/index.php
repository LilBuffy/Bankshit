<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();

$stats = [];
$stats['total_users'] = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role_id = 1")->fetch()['c'];
$stats['active_users'] = (int)$pdo->query("SELECT COUNT(*) c FROM users u JOIN accounts a ON a.user_id=u.id WHERE u.role_id=1 AND a.status='active'")->fetch()['c'];
$stats['locked_users'] = (int)$pdo->query("SELECT COUNT(*) c FROM users u JOIN accounts a ON a.user_id=u.id WHERE u.role_id=1 AND a.status='locked'")->fetch()['c'];
$stats['total_balance'] = (float)$pdo->query("SELECT COALESCE(SUM(balance),0) s FROM accounts")->fetch()['s'];

$stats['total_deposits'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id WHERE tt.code='deposit'")->fetch()['s'];
$stats['total_withdrawals'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id WHERE tt.code='withdrawal'")->fetch()['s'];
$stats['total_transfers'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id WHERE tt.code='transfer_out'")->fetch()['s'];

$stats['pending_loans'] = (int)$pdo->query("SELECT COUNT(*) c FROM loans WHERE status IN ('pending','under_review')")->fetch()['c'];
$stats['active_loans'] = (int)$pdo->query("SELECT COUNT(*) c FROM loans WHERE status='active'")->fetch()['c'];
$stats['completed_loans'] = (int)$pdo->query("SELECT COUNT(*) c FROM loans WHERE status='fully_paid'")->fetch()['c'];
$stats['total_loans'] = (int)$pdo->query("SELECT COUNT(*) c FROM loans")->fetch()['c'];

$stats['txn_volume'] = (int)$pdo->query("SELECT COUNT(*) c FROM transactions")->fetch()['c'];

$recentTxns = $pdo->query(
    "SELECT t.transaction_ref, t.amount, t.created_at, tt.code, a.account_number
     FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id JOIN accounts a ON a.id=t.account_id
     ORDER BY t.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Overview';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>System Overview</h1>

<div class="grid grid-4 mb-24">
    <div class="card stat-card"><span class="stat-label">Total Users</span><span class="stat-value"><?= $stats['total_users'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Active Users</span><span class="stat-value positive"><?= $stats['active_users'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Locked Users</span><span class="stat-value negative"><?= $stats['locked_users'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Total Demo Balance</span><span class="stat-value"><?= e(format_money($stats['total_balance'])) ?></span></div>
</div>

<div class="grid grid-3 mb-24">
    <div class="card stat-card"><span class="stat-label">Total Deposits</span><span class="stat-value"><?= e(format_money($stats['total_deposits'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label">Total Withdrawals</span><span class="stat-value"><?= e(format_money($stats['total_withdrawals'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label">Total Transfers</span><span class="stat-value"><?= e(format_money($stats['total_transfers'])) ?></span></div>
</div>

<div class="grid grid-4 mb-24">
    <div class="card stat-card"><span class="stat-label">Pending Loans</span><span class="stat-value"><?= $stats['pending_loans'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Active Loans</span><span class="stat-value"><?= $stats['active_loans'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Completed Loans</span><span class="stat-value"><?= $stats['completed_loans'] ?></span></div>
    <div class="card stat-card"><span class="stat-label">Transaction Volume</span><span class="stat-value"><?= $stats['txn_volume'] ?></span></div>
</div>

<div class="card">
    <h3>Recent Transactions (All Users)</h3>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Ref</th><th>Account</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recentTxns as $t): ?>
            <tr>
                <td><?= e($t['transaction_ref']) ?></td>
                <td><?= e($t['account_number']) ?></td>
                <td><?= e(ucwords(str_replace('_',' ',$t['code']))) ?></td>
                <td><?= e(format_money($t['amount'])) ?></td>
                <td><?= e(format_date($t['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
