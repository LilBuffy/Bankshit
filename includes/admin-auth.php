<?php
declare(strict_types=1);

function require_admin(): void {
    if (!is_logged_in() || !is_admin()) {
        log_security_event(current_user_id(), 'admin_access_denied', $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(403);
        redirect('/login.php');
    }

    $stmt = db()->prepare('SELECT status FROM users WHERE id = ? AND role_id = 2');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active') {
        logout_user();
        redirect('/login.php');
    }
}
