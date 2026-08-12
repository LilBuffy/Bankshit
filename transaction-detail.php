<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$ref = trim($_GET['ref'] ?? '');

$stmt = db()->prepare(
    'SELECT t.*, tt.code AS type_code, ca.account_number AS counterparty_number
     FROM transactions t
     JOIN transaction_types tt ON tt.id = t.type_id
     LEFT JOIN accounts ca ON ca.id = t.counterparty_account_id
     WHERE t.transaction_ref = ? AND t.account_id = ?'
);
$stmt->execute([$ref, $user['account_id']]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card empty-state">Transaction not found.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isCredit = in_array($transaction['type_code'], ['deposit', 'transfer_in', 'loan_disbursement'], true);
$pageTitle = t('transactions_id') . ': ' . $transaction['transaction_ref'];
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('transactions_id')) ?></h1>

<div class="card" style="max-width:520px;">
    <div class="flex-between mb-16">
        <div class="txn-icon <?= $isCredit ? 'in' : 'out' ?>" style="width:52px;height:52px;font-size:20px;"><?= $isCredit ? '↓' : '↑' ?></div>
        <div class="<?= $isCredit ? 'txn-amount in' : 'txn-amount out' ?>" style="font-size:26px;">
            <?= $isCredit ? '+' : '-' ?><?= e(format_money($transaction['amount'])) ?>
        </div>
    </div>

    <table>
        <tr><td class="text-faint"><?= e(t('transactions_id')) ?></td><td><?= e($transaction['transaction_ref']) ?></td></tr>
        <tr><td class="text-faint"><?= e(t('transactions_date')) ?></td><td><?= e(format_date($transaction['created_at'])) ?></td></tr>
        <tr><td class="text-faint"><?= e(t('transactions_type')) ?></td><td><?= e(ucwords(str_replace('_', ' ', $transaction['type_code']))) ?></td></tr>
        <?php if ($transaction['counterparty_number']): ?>
        <tr><td class="text-faint">Counterparty</td><td><?= e($transaction['counterparty_number']) ?></td></tr>
        <?php endif; ?>
        <tr><td class="text-faint"><?= e(t('transactions_description')) ?></td><td><?= e($transaction['description'] ?? '—') ?></td></tr>
        <tr><td class="text-faint"><?= e(t('transactions_balance_after')) ?></td><td><?= e(format_money($transaction['balance_after'])) ?></td></tr>
        <tr><td class="text-faint"><?= e(t('transactions_status')) ?></td><td><span class="badge badge-success"><?= e(t('status_' . $transaction['status'])) ?></span></td></tr>
    </table>

    <a href="<?= APP_BASE_URL ?>/transactions.php" class="btn btn-secondary btn-block mt-16">Back to History</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
