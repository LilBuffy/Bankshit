<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_login();
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT u.id, u.role_id, a.id AS account_id FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if ($target && (int)$target['role_id'] !== 2) {
        if ($action === 'lock') {
            $pdo->prepare('UPDATE accounts SET status = "locked" WHERE user_id = ?')->execute([$userId]);
            log_admin_action((int)current_user_id(), 'lock_user', 'user', $userId);
            flash_set('success', 'User account locked.');
        } elseif ($action === 'unlock') {
            $pdo->prepare('UPDATE accounts SET status = "active" WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?')->execute([$userId]);
            log_admin_action((int)current_user_id(), 'unlock_user', 'user', $userId);
            flash_set('success', 'User account unlocked.');
        }
    }
    redirect('/admin/users.php');
}

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = 'u.role_id = 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.username LIKE ? OR u.email LIKE ? OR a.account_number LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM users u JOIN accounts a ON a.user_id=u.id WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.status AS user_status, u.created_at,
               a.account_number, a.balance, a.status AS account_status
        FROM users u JOIN accounts a ON a.user_id = u.id
        WHERE $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/includes/admin-header.php';
?>

<h1>User Management</h1>

<div class="card mb-24">
    <form method="get" class="flex-gap">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search username, email, or account number" style="flex:1;min-width:240px;">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead>
        <tr><th>Name</th><th>Username</th><th>Account #</th><th>Balance</th><th>Status</th><th>Joined</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['account_number']) ?></td>
                <td><?= e(format_money($u['balance'])) ?></td>
                <td><span class="badge <?= $u['account_status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e(ucfirst($u['account_status'])) ?></span></td>
                <td><?= e(format_date($u['created_at'], 'M j, Y')) ?></td>
                <td class="flex-gap">
                    <a href="<?= APP_BASE_URL ?>/admin/user-detail.php?id=<?= (int)$u['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                    <form method="post" data-confirm="Are you sure?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?php if ($u['account_status'] === 'active'): ?>
                            <input type="hidden" name="action" value="lock">
                            <button type="submit" class="btn btn-danger btn-sm">Lock</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="unlock">
                            <button type="submit" class="btn btn-secondary btn-sm">Unlock</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex-gap mt-16" style="justify-content:center;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
