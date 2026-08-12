<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$search = trim($_GET['q'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$validTypes = ['deposit', 'withdrawal', 'transfer_out', 'transfer_in', 'loan_disbursement', 'loan_repayment'];
if ($typeFilter !== '' && !in_array($typeFilter, $validTypes, true)) $typeFilter = '';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (t.transaction_ref LIKE ? OR a.account_number LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($typeFilter !== '') {
    $where .= ' AND tt.code = ?';
    $params[] = $typeFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM transactions t JOIN transaction_types tt ON tt.id=t.type_id JOIN accounts a ON a.id=t.account_id WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT t.*, tt.code AS type_code, a.account_number, u.username
        FROM transactions t
        JOIN transaction_types tt ON tt.id = t.type_id
        JOIN accounts a ON a.id = t.account_id
        JOIN users u ON u.id = a.user_id
        WHERE $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = 'Transactions';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>All Transactions</h1>

<div class="card mb-24">
    <form method="get" class="flex-gap" style="align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0;">
            <label for="q">Search reference or account #</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="min-width:200px;margin-bottom:0;">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All Types</option>
                <?php foreach ($validTypes as $vt): ?>
                    <option value="<?= e($vt) ?>" <?= $typeFilter === $vt ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$vt))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">No transactions found.</div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Ref</th><th>User</th><th>Account #</th><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['transaction_ref']) ?></td>
                    <td><?= e($t['username']) ?></td>
                    <td><?= e($t['account_number']) ?></td>
                    <td><?= e(ucwords(str_replace('_',' ',$t['type_code']))) ?></td>
                    <td><?= e(format_money($t['amount'])) ?></td>
                    <td><span class="badge badge-success"><?= e(ucfirst($t['status'])) ?></span></td>
                    <td><?= e(format_date($t['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex-gap mt-16" style="justify-content:center;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
