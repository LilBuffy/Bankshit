<?php
$links = [
    ['dashboard.php', 'nav_dashboard', '🏠'],
    ['account.php', 'nav_account', '💳'],
    ['transactions.php', 'nav_transactions', '📜'],
    ['transfer.php', 'nav_transfer', '↗️'],
    ['deposit.php', 'nav_deposit', '⬇️'],
    ['withdraw.php', 'nav_withdraw', '⬆️'],
    ['loans.php', 'nav_loans', '🏦'],
    ['beneficiaries.php', 'nav_beneficiaries', '👥'],
    ['notifications.php', 'nav_notifications', '🔔'],
    ['profile.php', 'nav_profile', '👤'],
    ['security.php', 'nav_security', '🛡️'],
];
?>
<div class="brand">
    <div class="brand-mark">NB</div>
    <div>
        <div class="brand-name"><?= e(t('app_name')) ?></div>
        <div class="brand-tagline"><?= e(t('tagline')) ?></div>
    </div>
</div>

<?php foreach ($links as [$file, $key, $icon]): ?>
    <a href="<?= APP_BASE_URL ?>/<?= $file ?>" class="nav-link <?= $currentPage === $file ? 'active' : '' ?>">
        <span class="nav-icon" aria-hidden="true"><?= $icon ?></span>
        <span><?= e(t($key)) ?></span>
    </a>
<?php endforeach; ?>

<div class="sidebar-footer">
    <form method="post" action="<?= APP_BASE_URL ?>/logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="nav-link" style="width:100%;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;">
            <span class="nav-icon" aria-hidden="true">🚪</span>
            <span><?= e(t('nav_logout')) ?></span>
        </button>
    </form>
</div>
