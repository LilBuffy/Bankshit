<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query('SELECT COUNT(*) c FROM security_logs')->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT sl.*, u.username FROM security_logs sl LEFT JOIN users u ON u.id = sl.user_id
     ORDER BY sl.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
$logs = $stmt->fetchAll();

$adminLogsStmt = $pdo->query(
    'SELECT al.*, u.username FROM admin_logs al JOIN users u ON u.id = al.admin_id ORDER BY al.created_at DESC LIMIT 20'
);
$adminLogs = $adminLogsStmt->fetchAll();

$pageTitle = 'Security Logs';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>Security Logs</h1>

<div class="grid grid-2" style="grid-template-columns: 1.4fr 1fr; align-items:start;">
    <div class="card">
        <h3>System Security Events</h3>
        <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Event</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['username'] ?? '—') ?></td>
                    <td><?= e(ucwords(str_replace('_',' ',$log['event_type']))) ?></td>
                    <td><?= e($log['ip_address']) ?></td>
                    <td><?= e(format_date($log['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex-gap mt-16" style="justify-content:center;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Admin Actions</h3>
        <?php if (empty($adminLogs)): ?>
            <div class="empty-state">No admin actions logged yet.</div>
        <?php else: ?>
            <?php foreach ($adminLogs as $al): ?>
                <div class="txn-row">
                    <div class="txn-body">
                        <div class="txn-title"><?= e($al['username']) ?> — <?= e(ucwords(str_replace('_',' ',$al['action']))) ?></div>
                        <div class="txn-meta"><?= e($al['target_type'] ?? '') ?> #<?= e((string)$al['target_id']) ?> · <?= e(format_date($al['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
