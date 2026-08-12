<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = t('nav_account');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('nav_account')) ?></h1>

<div class="grid grid-2" style="grid-template-columns: 1fr 1fr;">
    <div class="balance-card">
        <div class="balance-label"><?= e(t('dashboard_available_balance')) ?></div>
        <div class="balance-amount"><?= e(format_money($user['balance'])) ?></div>
        <div class="balance-account"><?= e($user['account_number']) ?></div>
    </div>

    <div class="card">
        <h3><?= e(t('nav_account')) ?> <?= e(t('profile_title')) ?></h3>
        <table>
            <tr><td class="text-faint"><?= e(t('dashboard_account_number')) ?></td><td><?= e($user['account_number']) ?></td></tr>
            <tr><td class="text-faint">Currency</td><td><?= e($user['currency']) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_status')) ?></td><td>
                <span class="badge <?= $user['account_status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                    <?= $user['account_status'] === 'active' ? e(t('status_active')) : e(t('status_locked')) ?>
                </span>
            </td></tr>
            <tr><td class="text-faint"><?= e(t('profile_member_since')) ?></td><td><?= e(format_date($user['created_at'], 'M j, Y')) ?></td></tr>
        </table>
    </div>
</div>

<div class="mt-24 flex-gap">
    <a href="<?= APP_BASE_URL ?>/deposit.php" class="btn btn-primary"><?= e(t('action_deposit')) ?></a>
    <a href="<?= APP_BASE_URL ?>/withdraw.php" class="btn btn-secondary"><?= e(t('action_withdraw')) ?></a>
    <a href="<?= APP_BASE_URL ?>/transfer.php" class="btn btn-secondary"><?= e(t('action_transfer')) ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
