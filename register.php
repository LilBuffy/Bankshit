<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$registrationEnabled = get_setting('registration_enabled', '1') === '1';

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (!$registrationEnabled) {
        $errors[] = 'New registrations are temporarily disabled by the administrator.';
    }

    $old = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'mobile_number' => trim($_POST['mobile_number'] ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 60) $errors[] = 'First name is required.';
    if ($old['last_name'] === '' || mb_strlen($old['last_name']) > 60) $errors[] = 'Last name is required.';
    if (!is_valid_username($old['username'])) $errors[] = 'Username must be 3-30 characters (letters, numbers, dots, underscores only).';
    if (!is_valid_email($old['email'])) $errors[] = 'A valid email address is required.';
    if (!preg_match('/^\+?[0-9\-\s]{7,20}$/', $old['mobile_number'])) $errors[] = 'A valid mobile number is required.';

    $dob = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
    if (!$dob) {
        $errors[] = 'A valid date of birth is required.';
    } else {
        $age = $dob->diff(new DateTime())->y;
        if ($age < 18) $errors[] = 'You must be at least 18 years old to register.';
        if ($age > 120) $errors[] = 'Please enter a valid date of birth.';
    }

    if ($old['address'] === '' || mb_strlen($old['address']) > 255) $errors[] = 'Address is required.';
    if (!is_strong_password($password)) $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$old['username'], $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (empty($errors)) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, mobile_number, date_of_birth, address)
                 VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['username'], $old['email'], $hash,
                $old['first_name'], $old['last_name'],
                $old['mobile_number'], $old['date_of_birth'], $old['address'],
            ]);
            $userId = (int)$pdo->lastInsertId();

            $accountNumber = generate_account_number($pdo);
            $stmt = $pdo->prepare('INSERT INTO accounts (user_id, account_number, balance) VALUES (?, ?, 0.00)');
            $stmt->execute([$userId, $accountNumber]);

            create_notification($pdo, $userId, 'welcome', 'Welcome to NovaBank', 'Your demo account ' . $accountNumber . ' is ready. All funds are fictional.');

            $pdo->commit();
            log_security_event($userId, 'account_registered', '');

            flash_set('success', t('register_success'));
            redirect('/login.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = t('register_title');
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
    <div class="auth-card wide">
        <div class="auth-brand">
            <div class="brand-mark">NB</div>
            <h1 style="margin-bottom:2px;"><?= e(t('register_title')) ?></h1>
            <div class="text-faint"><?= e(t('demo_notice')) ?></div>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>
        <?php if (!$registrationEnabled): ?>
            <div class="alert alert-warning">New registrations are temporarily disabled by the administrator.</div>
        <?php endif; ?>

        <form method="post" data-validate style="<?= $registrationEnabled ? '' : 'opacity:0.5;pointer-events:none;' ?>">
            <?= csrf_field() ?>
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="first_name"><?= e(t('register_first_name')) ?></label>
                    <input type="text" id="first_name" name="first_name" required value="<?= e($old['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="last_name"><?= e(t('register_last_name')) ?></label>
                    <input type="text" id="last_name" name="last_name" required value="<?= e($old['last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="username"><?= e(t('register_username')) ?></label>
                <input type="text" id="username" name="username" required value="<?= e($old['username'] ?? '') ?>" pattern="[a-zA-Z0-9._]{3,30}">
            </div>

            <div class="form-group">
                <label for="email"><?= e(t('register_email')) ?></label>
                <input type="email" id="email" name="email" required value="<?= e($old['email'] ?? '') ?>">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="mobile_number"><?= e(t('register_mobile')) ?></label>
                    <input type="tel" id="mobile_number" name="mobile_number" required value="<?= e($old['mobile_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="date_of_birth"><?= e(t('register_dob')) ?></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required value="<?= e($old['date_of_birth'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address"><?= e(t('register_address')) ?></label>
                <textarea id="address" name="address" required><?= e($old['address'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="password"><?= e(t('register_password')) ?></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <div class="form-hint">Min. 8 characters, uppercase, lowercase, and a number.</div>
                </div>
                <div class="form-group">
                    <label for="confirm_password"><?= e(t('register_confirm_password')) ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(t('register_button')) ?></button>
        </form>

        <div class="auth-footer">
            <?= e(t('register_have_account')) ?> <a href="<?= APP_BASE_URL ?>/login.php"><?= e(t('register_login_link')) ?></a>
        </div>
    </div>
</div>
</body>
</html>
