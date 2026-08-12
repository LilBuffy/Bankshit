<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $registrationEnabled = isset($_POST['registration_enabled']) ? '1' : '0';
    $defaultLanguage = in_array($_POST['default_language'] ?? '', ['en', 'fil'], true) ? $_POST['default_language'] : 'en';
    $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';

    set_setting('registration_enabled', $registrationEnabled);
    set_setting('default_language', $defaultLanguage);
    set_setting('maintenance_mode', $maintenanceMode);

    log_admin_action((int)current_user_id(), 'update_settings', 'system_settings', null,
        "registration=$registrationEnabled, lang=$defaultLanguage, maintenance=$maintenanceMode");

    flash_set('success', 'Settings updated successfully.');
    redirect('/admin/settings.php');
}

$registrationEnabled = get_setting('registration_enabled', '1') === '1';
$defaultLanguage = get_setting('default_language', 'en');
$maintenanceMode = get_setting('maintenance_mode', '0') === '1';

$pageTitle = 'Settings';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>System Settings</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:520px;">
    <form method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
                <input type="checkbox" name="registration_enabled" value="1" style="width:auto;min-height:auto;" <?= $registrationEnabled ? 'checked' : '' ?>>
                Allow New User Registrations
            </label>
            <div class="form-hint">When disabled, the registration page will reject new sign-ups.</div>
        </div>

        <div class="form-group">
            <label for="default_language">Default Language for New Sessions</label>
            <select id="default_language" name="default_language">
                <option value="en" <?= $defaultLanguage === 'en' ? 'selected' : '' ?>>English</option>
                <option value="fil" <?= $defaultLanguage === 'fil' ? 'selected' : '' ?>>Filipino</option>
            </select>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
                <input type="checkbox" name="maintenance_mode" value="1" style="width:auto;min-height:auto;" <?= $maintenanceMode ? 'checked' : '' ?>>
                Maintenance Mode
            </label>
            <div class="form-hint">Displays a maintenance notice site-wide (admin access is unaffected).</div>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
