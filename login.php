<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $identifier = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors[] = t('login_error');
    } elseif (is_rate_limited($identifier)) {
        $errors[] = t('login_locked');
        log_security_event(null, 'login_rate_limited', $identifier);
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            record_login_attempt($identifier, false);
            $errors[] = t('login_error');
        } elseif (is_account_locked($user)) {
            record_login_attempt($identifier, false);
            $errors[] = t('login_locked');
            log_security_event((int)$user['id'], 'login_blocked_locked_account', '');
        } elseif (!password_verify($password, $user['password_hash'])) {
            record_login_attempt($identifier, false);
            $newCount = (int)$user['failed_login_count'] + 1;
            $lockUntil = null;
            if ($newCount >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
            }
            $upd = db()->prepare('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?');
            $upd->execute([$newCount, $lockUntil, $user['id']]);
            log_security_event((int)$user['id'], 'login_failed', '');
            $errors[] = t('login_error');
        } else {
            record_login_attempt($identifier, true);
            $resetStmt = db()->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?');
            $resetStmt->execute([$user['id']]);

            login_user($user);
            log_security_event((int)$user['id'], 'login_success', '');
            create_notification(db(), (int)$user['id'], 'login', 'New login', 'A new login to your account was detected just now.');

            redirect($user['role_id'] == 2 ? '/admin/index.php' : '/dashboard.php');
        }
    }
}

$pageTitle = t('login_title');
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(t('app_name')) ?></title>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">NB</div>
            <h1 style="margin-bottom:2px;"><?= e(t('app_name')) ?></h1>
            <div class="text-faint"><?= e(t('tagline')) ?>: <?= e(t('secondary_tagline')) ?></div>
        </div>

        <?php foreach (flash_get_all() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" data-validate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username"><?= e(t('login_username')) ?></label>
                <input type="text" id="username" name="username" required autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><?= e(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('login_button')) ?></button>
        </form>

        <div class="auth-footer">
            <?= e(t('login_no_account')) ?> <a href="<?= APP_BASE_URL ?>/register.php"><?= e(t('login_register_link')) ?></a>
            <br><br>
            <select onchange="location.href='?lang='+this.value" style="min-height:38px;">
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>>English</option>
                <option value="fil" <?= current_lang() === 'fil' ? 'selected' : '' ?>>Filipino</option>
            </select>
        </div>
    </div>
</div>
</body>
</html>
