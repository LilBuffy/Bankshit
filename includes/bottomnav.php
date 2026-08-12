<?php
$bottomLinks = [
    ['dashboard.php', 'nav_dashboard', '🏠'],
    ['transfer.php', 'nav_transfer', '↗️'],
    ['transactions.php', 'nav_transactions', '📜'],
    ['notifications.php', 'nav_notifications', '🔔'],
    ['profile.php', 'nav_more', '☰'],
];
?>
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <?php foreach ($bottomLinks as [$file, $key, $icon]): ?>
            <a href="<?= APP_BASE_URL ?>/<?= $file ?>" class="bottom-nav-item <?= $currentPage === $file ? 'active' : '' ?>">
                <span aria-hidden="true"><?= $icon ?></span>
                <span><?= e(t($key)) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
