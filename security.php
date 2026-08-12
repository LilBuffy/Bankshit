<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pdo = db();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([current_user_id()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
            log_security_event(current_user_id(), 'password_change_failed', '');
        } elseif (!is_strong_password($new)) {
            $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, and a number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, current_user_id()]);
            log_security_event(current_user_id(), 'password_changed', '');
            create_notification($pdo, current_user_id(), 'security', 'Password changed', 'Your password was changed successfully.');
            flash_set('success', 'Password changed successfully.');
            redirect('/security.php');
        }
    } elseif ($action === 'lock_account') {
        $pdo->prepare('UPDATE accounts SET status = "locked" WHERE user_id = ?')->execute([current_user_id()]);
        log_security_event(current_user_id(), 'account_self_locked', '');
        create_notification($pdo, current_user_id(), 'security', 'Account locked', 'You locked your own account for security.');
        flash_set('success', 'Your account has been locked.');
        redirect('/security.php');
    } elseif ($action === 'unlock_account') {
        $password = (string)($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([current_user_id()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $errors[] = 'Incorrect password. Cannot unlock account.';
        } else {
            $pdo->prepare('UPDATE accounts SET status = "active" WHERE user_id = ?')->execute([current_user_id()]);
            log_security_event(current_user_id(), 'account_self_unlocked', '');
            create_notification($pdo, current_user_id(), 'security', 'Account unlocked', 'You unlocked your account.');
            flash_set('success', 'Your account has been unlocked.');
            redirect('/security.php');
        }
    }
}

$user = current_user();

$stmt = $pdo->prepare('SELECT event_type, ip_address, created_at FROM security_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 12');
$stmt->execute([$user['id']]);
$logs = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT created_at, ip_address, success FROM login_attempts WHERE username_attempted IN (?, ?) ORDER BY created_at DESC LIMIT 10'
);
$stmt->execute([$user['username'], $user['email']]);
$loginAttempts = $stmt->fetchAll();

$pageTitle = t('security_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('security_title')) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card mb-24">
        <h3><?= e(t('security_change_password')) ?></h3>
        <form method="post" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label for="current_password"><?= e(t('security_current_password')) ?></label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password"><?= e(t('security_new_password')) ?></label>
                <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_new_password"><?= e(t('security_confirm_password')) ?></label>
                <input type="password" id="confirm_new_password" name="confirm_password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('security_change_password')) ?></button>
        </form>
    </div>

    <div class="card mb-24">
        <h3>Account Lock</h3>
        <p class="text-faint"><?= e(t('security_lock_warning')) ?></p>
        <?php if ($user['account_status'] === 'active'): ?>
            <form method="post" data-confirm="Lock your account? You will not be able to transact until you unlock it.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="lock_account">
                <button type="submit" class="btn btn-danger btn-block"><?= e(t('security_lock_account')) ?></button>
            </form>
        <?php else: ?>
            <form method="post" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unlock_account">
                <div class="form-group">
                    <label for="unlock_password"><?= e(t('confirm_password_prompt')) ?></label>
                    <input type="password" id="unlock_password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= e(t('security_unlock_account')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card">
        <h3><?= e(t('security_recent_logins')) ?></h3>
        <?php if (empty($loginAttempts)): ?>
            <div class="empty-state">No recent activity.</div>
        <?php else: ?>
            <?php foreach ($loginAttempts as $la): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title">Login attempt from <?= e($la['ip_address']) ?></div>
                        <div class="txn-meta"><?= e(format_date($la['created_at'])) ?></div>
                    </div>
                    <span class="badge <?= $la['success'] ? 'badge-success' : 'badge-danger' ?>"><?= $la['success'] ? 'Success' : 'Failed' ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Security Log</h3>
        <?php if (empty($logs)): ?>
            <div class="empty-state">No events yet.</div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title"><?= e(ucwords(str_replace('_', ' ', $log['event_type']))) ?></div>
                        <div class="txn-meta"><?= e($log['ip_address']) ?> · <?= e(format_date($log['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
