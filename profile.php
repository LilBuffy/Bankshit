<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $mobile = trim($_POST['mobile_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!preg_match('/^\+?[0-9\-\s]{7,20}$/', $mobile)) {
        $errors[] = 'Enter a valid mobile number.';
    }
    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = 'Enter a valid address.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('UPDATE users SET mobile_number = ?, address = ? WHERE id = ?');
        $stmt->execute([$mobile, $address, $user['id']]);
        log_security_event((int)$user['id'], 'profile_updated', '');
        flash_set('success', 'Profile updated successfully.');
        redirect('/profile.php');
    }
}

$pageTitle = t('profile_title');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('profile_title')) ?></h1>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr; align-items:start;">
    <div class="card">
        <h3>Account Information</h3>
        <table>
            <tr><td class="text-faint">Name</td><td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td></tr>
            <tr><td class="text-faint">Username</td><td><?= e($user['username']) ?></td></tr>
            <tr><td class="text-faint">Email</td><td><?= e($user['email']) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('dashboard_account_number')) ?></td><td><?= e($user['account_number']) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('profile_member_since')) ?></td><td><?= e(format_date($user['created_at'], 'M j, Y')) ?></td></tr>
        </table>
        <p class="text-faint mt-16">To change your name, username, or email, please contact support. This demo restricts changes to identity fields for data integrity.</p>
    </div>

    <div class="card">
        <h3><?= e(t('profile_update')) ?></h3>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" data-validate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="mobile_number"><?= e(t('register_mobile')) ?></label>
                <input type="tel" id="mobile_number" name="mobile_number" required value="<?= e($user['mobile_number']) ?>">
            </div>
            <div class="form-group">
                <label for="address"><?= e(t('register_address')) ?></label>
                <textarea id="address" name="address" required><?= e($user['address']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('profile_update')) ?></button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
