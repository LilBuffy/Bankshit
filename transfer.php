<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ratelimit.php';
require_login();

$user = current_user();
$errors = [];
$old = ['recipient' => '', 'amount' => '', 'description' => ''];

$stmt = db()->prepare('SELECT b.nickname, a.account_number FROM beneficiaries b JOIN accounts a ON a.id = b.beneficiary_account_id WHERE b.user_id = ? ORDER BY b.nickname');
$stmt->execute([$user['id']]);
$beneficiaries = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $old['recipient'] = trim($_POST['recipient_account'] ?? '');
    $old['amount'] = $_POST['amount'] ?? '';
    $old['description'] = trim($_POST['description'] ?? '');

    if ($user['account_status'] !== 'active') {
        $errors[] = 'Your account is locked. Unlock it in the Security Center to continue.';
    } elseif (is_action_rate_limited((int)$user['id'], 'transfer', RATE_LIMIT_TRANSFER_PER_HOUR, 60)) {
        $errors[] = 'Transfer limit reached for this hour. Please try again later.';
    } else {
        if (!is_valid_amount($old['amount'])) {
            $errors[] = 'Enter a valid transfer amount.';
        }
        if ($old['recipient'] === '' || !preg_match('/^NOVA-\d{4}-\d{4}-\d{4}$/', $old['recipient'])) {
            $errors[] = 'Enter a valid recipient account number.';
        }
        if (mb_strlen($old['description']) > 255) {
            $errors[] = 'Description is too long.';
        }

        if (empty($errors)) {
            $amount = round((float)$old['amount'], 2);
            $pdo = db();
            try {
                $recStmt = $pdo->prepare('SELECT id, user_id, status FROM accounts WHERE account_number = ?');
                $recStmt->execute([$old['recipient']]);
                $recipientAccount = $recStmt->fetch();

                if (!$recipientAccount) {
                    $errors[] = t('transfer_recipient_not_found');
                } elseif ((int)$recipientAccount['id'] === (int)$user['account_id']) {
                    $errors[] = t('transfer_self_error');
                } elseif ($recipientAccount['status'] !== 'active') {
                    $errors[] = 'Recipient account is not active.';
                } else {
                    $senderId = (int)$user['account_id'];
                    $recipientId = (int)$recipientAccount['id'];
                    [$firstId, $secondId] = $senderId < $recipientId ? [$senderId, $recipientId] : [$recipientId, $senderId];

                    $pdo->beginTransaction();

                    $lockStmt = $pdo->prepare('SELECT id, balance, status FROM accounts WHERE id = ? FOR UPDATE');
                    $lockStmt->execute([$firstId]);
                    $firstAccount = $lockStmt->fetch();
                    $lockStmt->execute([$secondId]);
                    $secondAccount = $lockStmt->fetch();

                    $senderAccount = $firstAccount['id'] == $senderId ? $firstAccount : $secondAccount;
                    $recipientLocked = $firstAccount['id'] == $recipientId ? $firstAccount : $secondAccount;

                    if ($senderAccount['status'] !== 'active' || $recipientLocked['status'] !== 'active') {
                        throw new RuntimeException('One of the accounts is no longer active.');
                    }
                    if ($amount > (float)$senderAccount['balance']) {
                        $pdo->rollBack();
                        $errors[] = 'Insufficient balance for this transfer.';
                    } else {
                        $senderNewBalance = round((float)$senderAccount['balance'] - $amount, 2);
                        $recipientNewBalance = round((float)$recipientLocked['balance'] + $amount, 2);

                        $upd = $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
                        $upd->execute([$senderNewBalance, $senderId]);
                        $upd->execute([$recipientNewBalance, $recipientId]);

                        $refOut = generate_reference('TXN');
                        $refIn = generate_reference('TXN');

                        $insOut = $pdo->prepare(
                            'INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, counterparty_account_id, description, status)
                             VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = "transfer_out"), ?, ?, ?, ?, "completed")'
                        );
                        $insOut->execute([$refOut, $senderId, $amount, $senderNewBalance, $recipientId, $old['description'] ?: null]);

                        $insIn = $pdo->prepare(
                            'INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, counterparty_account_id, description, status)
                             VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = "transfer_in"), ?, ?, ?, ?, "completed")'
                        );
                        $insIn->execute([$refIn, $recipientId, $amount, $recipientNewBalance, $senderId, $old['description'] ?: null]);

                        create_notification($pdo, (int)$user['id'], 'transfer_sent', 'Transfer sent', 'You sent ' . format_money($amount) . ' to ' . $old['recipient'] . '.');
                        create_notification($pdo, (int)$recipientAccount['user_id'], 'transfer_received', 'Transfer received', 'You received ' . format_money($amount) . ' from ' . $user['account_number'] . '.');

                        $pdo->commit();
                        log_security_event((int)$user['id'], 'transfer', $refOut);

                        flash_set('success', t('transfer_success') . ' (' . format_money($amount) . ')');
                        redirect('/transactions.php');
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Transfer failed: ' . $e->getMessage());
                $errors[] = 'Transfer could not be completed. Please try again.';
            }
        }
    }
}

$pageTitle = t('transfer_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('transfer_title')) ?></h1>
<p class="text-faint"><?= e(t('demo_notice')) ?></p>

<div class="grid grid-2" style="grid-template-columns: 1fr 320px; align-items:start;">
    <div class="card">
        <div class="mb-16">
            <span class="text-faint"><?= e(t('dashboard_available_balance')) ?>:</span>
            <strong><?= e(format_money($user['balance'])) ?></strong>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" data-validate data-confirm="Confirm this demo transfer?">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="recipient_account"><?= e(t('transfer_recipient')) ?></label>
                <input type="text" id="recipient_account" name="recipient_account" required placeholder="NOVA-0000-0000-0000" pattern="NOVA-\d{4}-\d{4}-\d{4}" value="<?= e($old['recipient']) ?>">
            </div>
            <div class="form-group">
                <label for="amount"><?= e(t('transfer_amount')) ?></label>
                <div class="input-prefix-group">
                    <span class="input-prefix"><?= e(APP_CURRENCY) ?></span>
                    <input type="number" id="amount" name="amount" step="0.01" min="<?= MIN_TRANSACTION_AMOUNT ?>" max="<?= e($user['balance']) ?>" required data-min-amount="<?= MIN_TRANSACTION_AMOUNT ?>" value="<?= e((string)$old['amount']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="description"><?= e(t('transfer_description')) ?></label>
                <input type="text" id="description" name="description" maxlength="255" value="<?= e($old['description']) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('transfer_button')) ?></button>
        </form>
    </div>

    <div class="card">
        <h3><?= e(t('beneficiaries_title')) ?></h3>
        <?php if (empty($beneficiaries)): ?>
            <p class="text-faint"><?= e(t('beneficiaries_none')) ?></p>
        <?php else: ?>
            <?php foreach ($beneficiaries as $b): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title"><?= e($b['nickname']) ?></div>
                        <div class="txn-meta"><?= e($b['account_number']) ?></div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('recipient_account').value='<?= e($b['account_number']) ?>'; document.getElementById('amount').focus();"><?= e(t('beneficiaries_transfer')) ?></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?= APP_BASE_URL ?>/beneficiaries.php" class="btn btn-secondary btn-block mt-16"><?= e(t('beneficiaries_add')) ?></a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
