<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_logged_in()) {
    json_response(['error' => 'unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    json_response(['error' => 'invalid_request'], 403);
}

$id = (int)($_POST['id'] ?? 0);
$stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([$id, current_user_id()]);

json_response(['success' => true]);
