<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nickname = trim($_POST['nickname'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');

        if ($nickname === '' || mb_strlen($nickname) > 60) {
            $errors[] = 'Enter a valid nickname.';
        }
        if (!preg_match('/^NOVA-\d{4}-\d{4}-\d{4}$/', $accountNumber)) {
            $errors[] = 'Enter a valid account number.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM accounts WHERE account_number = ?');
            $stmt->execute([$accountNumber]);
            $target = $stmt->fetch();

            if (!$target) {
                $errors[] = t('transfer_recipient_not_found');
            } elseif ((int)$target['id'] === (int)$user['account_id']) {
                $errors[] = t('transfer_self_error');
            } else {
                try {
                    $ins = $pdo->prepare('INSERT INTO beneficiaries (user_id, beneficiary_account_id, nickname) VALUES (?, ?, ?)');
                    $ins->execute([$user['id'], $target['id'], $nickname]);
                    log_security_event((int)$user['id'], 'beneficiary_added', $accountNumber);
                    flash_set('success', 'Beneficiary added.');
                    redirect('/beneficiaries.php');
                } catch (PDOException $e) {
                    $errors[] = 'That account is already saved as a beneficiary.';
                }
            }
        }
    } elseif ($action === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM beneficiaries WHERE id = ? AND user_id = ?');
        $del->execute([$id, $user['id']]);
        log_security_event((int)$user['id'], 'beneficiary_removed', (string)$id);
        flash_set('success', 'Beneficiary removed.');
        redirect('/beneficiaries.php');
    }
}

$stmt = $pdo->prepare(
    'SELECT b.id, b.nickname, a.account_number
     FROM beneficiaries b JOIN accounts a ON a.id = b.beneficiary_account_id
     WHERE b.user_id = ? ORDER BY b.nickname'
);
$stmt->execute([$user['id']]);
$beneficiaries = $stmt->fetchAll();

$pageTitle = t('beneficiaries_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('beneficiaries_title')) ?></h1>

<div class="grid grid-2" style="grid-template-columns: 1fr 340px; align-items:start;">
    <div class="card">
        <?php if (empty($beneficiaries)): ?>
            <div class="empty-state"><?= e(t('beneficiaries_none')) ?></div>
        <?php else: ?>
            <?php foreach ($beneficiaries as $b): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title"><?= e($b['nickname']) ?></div>
                        <div class="txn-meta"><?= e($b['account_number']) ?></div>
                    </div>
                    <div class="flex-gap">
                        <a href="<?= APP_BASE_URL ?>/transfer.php" class="btn btn-secondary btn-sm"><?= e(t('beneficiaries_transfer')) ?></a>
                        <form method="post" data-confirm="Remove this beneficiary?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--color-danger);"><?= e(t('beneficiaries_remove')) ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3><?= e(t('beneficiaries_add')) ?></h3>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="nickname"><?= e(t('beneficiaries_nickname')) ?></label>
                <input type="text" id="nickname" name="nickname" required maxlength="60">
            </div>
            <div class="form-group">
                <label for="account_number"><?= e(t('beneficiaries_account_number')) ?></label>
                <input type="text" id="account_number" name="account_number" required placeholder="NOVA-0000-0000-0000" pattern="NOVA-\d{4}-\d{4}-\d{4}">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('beneficiaries_add')) ?></button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
