<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

$validTypes = ['deposit', 'withdrawal', 'transfer_out', 'transfer_in', 'loan_disbursement', 'loan_repayment'];
if ($typeFilter !== '' && !in_array($typeFilter, $validTypes, true)) {
    $typeFilter = '';
}

$where = 't.account_id = ?';
$params = [$user['account_id']];

if ($search !== '') {
    $where .= ' AND (t.transaction_ref LIKE ? OR t.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($typeFilter !== '') {
    $where .= ' AND tt.code = ?';
    $params[] = $typeFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM transactions t JOIN transaction_types tt ON tt.id = t.type_id WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT t.*, tt.code AS type_code FROM transactions t JOIN transaction_types tt ON tt.id = t.type_id
        WHERE $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = t('transactions_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('transactions_title')) ?></h1>

<div class="card mb-24">
    <form method="get" class="flex-gap" style="align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
            <label for="q"><?= e(t('transactions_search')) ?></label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Reference or description">
        </div>
        <div class="form-group" style="min-width:200px;margin-bottom:0;">
            <label for="type"><?= e(t('transactions_filter_type')) ?></label>
            <select id="type" name="type">
                <option value=""><?= e(t('transactions_all_types')) ?></option>
                <?php foreach ($validTypes as $vt): ?>
                    <option value="<?= e($vt) ?>" <?= $typeFilter === $vt ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $vt))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state"><?= e(t('transactions_no_results')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th><?= e(t('transactions_id')) ?></th>
                <th><?= e(t('transactions_date')) ?></th>
                <th><?= e(t('transactions_type')) ?></th>
                <th><?= e(t('transactions_description')) ?></th>
                <th><?= e(t('transactions_amount')) ?></th>
                <th><?= e(t('transactions_balance_after')) ?></th>
                <th><?= e(t('transactions_status')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <?php $isCredit = in_array($t['type_code'], ['deposit', 'transfer_in', 'loan_disbursement'], true); ?>
                <tr>
                    <td><a href="<?= APP_BASE_URL ?>/transaction-detail.php?ref=<?= urlencode($t['transaction_ref']) ?>"><?= e($t['transaction_ref']) ?></a></td>
                    <td><?= e(format_date($t['created_at'])) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $t['type_code']))) ?></td>
                    <td><?= e($t['description'] ?? '—') ?></td>
                    <td class="<?= $isCredit ? 'txn-amount in' : 'txn-amount out' ?>"><?= $isCredit ? '+' : '-' ?><?= e(format_money($t['amount'])) ?></td>
                    <td><?= e(format_money($t['balance_after'])) ?></td>
                    <td><span class="badge badge-success"><?= e(t('status_' . $t['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex-gap mt-16" style="justify-content:center;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
