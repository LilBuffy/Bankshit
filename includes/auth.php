<?php
declare(strict_types=1);

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
    if (!is_admin() && function_exists('get_setting') && get_setting('maintenance_mode', '0') === '1') {
        http_response_code(503);
        die('NovaBank is currently undergoing scheduled maintenance. Please check back later.');
    }
}

function check_session_idle_timeout(): void {
    if (!isset($_SESSION['user_id'])) return;

    $now = time();
    $last = $_SESSION['last_activity'] ?? $now;
    if ($now - $last > SESSION_IDLE_TIMEOUT_MINUTES * 60) {
        $uid = $_SESSION['user_id'] ?? null;
        session_unset();
        session_destroy();
        session_start();
        if ($uid) {
            log_security_event($uid, 'session_timeout', 'Idle session expired');
        }
        return;
    }
    $_SESSION['last_activity'] = $now;
}

function current_user(): ?array {
    static $user = null;
    static $fetched = false;
    if ($fetched) return $user;
    $fetched = true;
    $uid = current_user_id();
    if (!$uid) return null;
    $stmt = db()->prepare('SELECT u.*, a.id AS account_id, a.account_number, a.balance, a.status AS account_status
                            FROM users u JOIN accounts a ON a.user_id = u.id
                            WHERE u.id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role_id'] == 2 ? 'admin' : 'customer';
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_activity'] = time();

    $tokenHash = hash('sha256', session_id());
    $stmt = db()->prepare(
        'INSERT INTO user_sessions (user_id, session_token_hash, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $stmt->execute([
        $user['id'],
        $tokenHash,
        client_ip(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        SESSION_IDLE_TIMEOUT_MINUTES,
    ]);
}

function logout_user(): void {
    $uid = current_user_id();
    if ($uid) {
        $tokenHash = hash('sha256', session_id());
        $stmt = db()->prepare('UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND session_token_hash = ?');
        $stmt->execute([$uid, $tokenHash]);
        log_security_event($uid, 'logout', '');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_account_locked(array $user): bool {
    if ($user['status'] !== 'active') return true;
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) return true;
    return false;
}

function record_login_attempt(string $usernameAttempted, bool $success): void {
    $stmt = db()->prepare('INSERT INTO login_attempts (username_attempted, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$usernameAttempted, client_ip(), $success ? 1 : 0]);
}

function is_rate_limited(string $usernameAttempted): bool {
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE username_attempted = ? AND success = 0
         AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->execute([$usernameAttempted, LOGIN_ATTEMPT_WINDOW_MINUTES]);
    $byUser = (int)$stmt->fetch()['c'] >= MAX_LOGIN_ATTEMPTS;

    $stmt = db()->prepare(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE ip_address = ? AND success = 0
         AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->execute([client_ip(), LOGIN_ATTEMPT_WINDOW_MINUTES]);
    $byIp = (int)$stmt->fetch()['c'] >= (MAX_LOGIN_ATTEMPTS * 3);

    return $byUser || $byIp;
}
