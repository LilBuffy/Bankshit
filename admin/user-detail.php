<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$userId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.mobile_number, u.address,
            u.date_of_birth, u.status AS user_status, u.created_at, u.failed_login_count, u.locked_until,
            a.id AS account_id, a.account_number, a.balance, a.status AS account_status
     FROM users u JOIN accounts a ON a.user_id = u.id
     WHERE u.id = ? AND u.role_id = 1'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/admin-header.php';
    echo '<div class="card empty-state">User not found.</div>';
    require __DIR__ . '/includes/admin-footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, tt.code AS type_code FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id WHERE t.account_id = ? ORDER BY t.created_at DESC LIMIT 15');
$stmt->execute([$user['account_id']]);
$transactions = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY applied_at DESC');
$stmt->execute([$userId]);
$loans = $stmt->fetchAll();

$pageTitle = $user['username'];
require __DIR__ . '/includes/admin-header.php';
?>

<h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?> <span class="text-faint">@<?= e($user['username']) ?></span></h1>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card mb-24">
        <h3>Profile</h3>
        <table>
            <tr><td class="text-faint">Email</td><td><?= e($user['email']) ?></td></tr>
            <tr><td class="text-faint">Mobile</td><td><?= e($user['mobile_number']) ?></td></tr>
            <tr><td class="text-faint">Address</td><td><?= e($user['address']) ?></td></tr>
            <tr><td class="text-faint">Date of Birth</td><td><?= e($user['date_of_birth']) ?></td></tr>
            <tr><td class="text-faint">Registered</td><td><?= e(format_date($user['created_at'])) ?></td></tr>
            <tr><td class="text-faint">Failed Logins</td><td><?= (int)$user['failed_login_count'] ?></td></tr>
        </table>
        <p class="text-faint mt-16">Password hashes are never displayed to administrators.</p>
    </div>

    <div class="card mb-24">
        <h3>Account</h3>
        <table>
            <tr><td class="text-faint">Account #</td><td><?= e($user['account_number']) ?></td></tr>
            <tr><td class="text-faint">Balance</td><td><?= e(format_money($user['balance'])) ?></td></tr>
            <tr><td class="text-faint">Status</td><td>
                <span class="badge <?= $user['account_status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e(ucfirst($user['account_status'])) ?></span>
            </td></tr>
        </table>
        <form method="post" action="<?= APP_BASE_URL ?>/admin/users.php" data-confirm="Are you sure?" class="mt-16">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
            <?php if ($user['account_status'] === 'active'): ?>
                <input type="hidden" name="action" value="lock">
                <button type="submit" class="btn btn-danger btn-block">Lock Account</button>
            <?php else: ?>
                <input type="hidden" name="action" value="unlock">
                <button type="submit" class="btn btn-secondary btn-block">Unlock Account</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card mb-24">
    <h3>Transaction History</h3>
    <?php if (empty($transactions)): ?>
        <div class="empty-state">No transactions.</div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['transaction_ref']) ?></td>
                    <td><?= e(ucwords(str_replace('_',' ',$t['type_code']))) ?></td>
                    <td><?= e(format_money($t['amount'])) ?></td>
                    <td><?= e(format_money($t['balance_after'])) ?></td>
                    <td><?= e(format_date($t['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Loan History</h3>
    <?php if (empty($loans)): ?>
        <div class="empty-state">No loans.</div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Ref</th><th>Principal</th><th>Status</th><th>Remaining</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><?= e($loan['loan_ref']) ?></td>
                    <td><?= e(format_money($loan['principal'])) ?></td>
                    <td><span class="badge badge-neutral"><?= e(ucwords(str_replace('_',' ',$loan['status']))) ?></span></td>
                    <td><?= e(format_money($loan['remaining_balance'])) ?></td>
                    <td><a href="<?= APP_BASE_URL ?>/admin/loan-detail.php?ref=<?= urlencode($loan['loan_ref']) ?>" class="btn btn-ghost btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
