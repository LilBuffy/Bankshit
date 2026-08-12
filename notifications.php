<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    csrf_require();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    redirect('/notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = t('notifications_title');
require __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-16">
    <h1><?= e(t('notifications_title')) ?></h1>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-secondary btn-sm">Mark all as read</button>
    </form>
</div>

<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="empty-state"><?= e(t('notifications_none')) ?></div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="txn-row" data-notification="<?= (int)$n['id'] ?>" style="<?= $n['is_read'] ? '' : 'background:var(--color-brand-glow);border-radius:10px;' ?>">
                <div class="txn-icon in">🔔</div>
                <div class="txn-body">
                    <div class="txn-title"><?= e($n['title']) ?></div>
                    <div class="txn-meta"><?= e($n['message']) ?></div>
                    <div class="txn-meta"><?= e(format_date($n['created_at'])) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                    <button type="button" class="btn btn-ghost btn-sm" data-mark-read="<?= (int)$n['id'] ?>"><?= e(t('notifications_mark_read')) ?></button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
