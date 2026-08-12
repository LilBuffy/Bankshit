<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ratelimit.php';
require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if ($user['account_status'] !== 'active') {
        $errors[] = 'Your account is locked. Unlock it in the Security Center to continue.';
    } elseif (is_action_rate_limited((int)$user['id'], 'withdrawal', 10, 10)) {
        $errors[] = 'Too many withdrawal attempts. Please wait a few minutes.';
    } else {
        $amount = $_POST['amount'] ?? '';
        if (!is_valid_amount($amount)) {
            $errors[] = 'Enter a valid withdrawal amount.';
        } else {
            $amount = round((float)$amount, 2);
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('SELECT balance, status FROM accounts WHERE id = ? FOR UPDATE');
                $stmt->execute([$user['account_id']]);
                $account = $stmt->fetch();

                if (!$account || $account['status'] !== 'active') {
                    throw new RuntimeException('Account not active.');
                }
                if ($amount > (float)$account['balance']) {
                    $pdo->rollBack();
                    $errors[] = t('withdraw_insufficient');
                } else {
                    $newBalance = round((float)$account['balance'] - $amount, 2);
                    $upd = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
                    $upd->execute([$newBalance, $user['account_id']]);

                    $ref = generate_reference('TXN');
                    $ins = $pdo->prepare(
                        'INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, description, status)
                         VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = "withdrawal"), ?, ?, ?, "completed")'
                    );
                    $ins->execute([$ref, $user['account_id'], $amount, $newBalance, 'Demo funds withdrawal']);

                    create_notification($pdo, (int)$user['id'], 'withdrawal', 'Withdrawal completed', 'You withdrew ' . format_money($amount) . ' (demo funds).');
                    $pdo->commit();

                    log_security_event((int)$user['id'], 'withdrawal', $ref);
                    flash_set('success', t('withdraw_success') . ' (' . format_money($amount) . ')');
                    redirect('/transactions.php');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Withdrawal failed: ' . $e->getMessage());
                $errors[] = 'Withdrawal could not be completed. Please try again.';
            }
        }
    }
}

$pageTitle = t('withdraw_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('withdraw_title')) ?></h1>
<p class="text-faint"><?= e(t('demo_notice')) ?></p>

<div class="card" style="max-width:480px;">
    <div class="mb-16">
        <span class="text-faint"><?= e(t('dashboard_available_balance')) ?>:</span>
        <strong><?= e(format_money($user['balance'])) ?></strong>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" data-validate data-confirm="Confirm this demo withdrawal?">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="amount"><?= e(t('withdraw_amount')) ?></label>
            <div class="input-prefix-group">
                <span class="input-prefix"><?= e(APP_CURRENCY) ?></span>
                <input type="number" id="amount" name="amount" step="0.01" min="<?= MIN_TRANSACTION_AMOUNT ?>" max="<?= e($user['balance']) ?>" required data-min-amount="<?= MIN_TRANSACTION_AMOUNT ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= e(t('withdraw_button')) ?></button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
