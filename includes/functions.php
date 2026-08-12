<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_money(float|string $amount): string {
    return APP_CURRENCY . ' ' . number_format((float)$amount, 2);
}

function format_date(string $datetime, string $format = 'M j, Y g:i A'): string {
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : $datetime;
}

function generate_account_number(PDO $pdo): string {
    do {
        $number = 'NOVA-' . random_int(1000, 9999) . '-' . random_int(1000, 9999) . '-' . random_int(1000, 9999);
        $stmt = $pdo->prepare('SELECT id FROM accounts WHERE account_number = ?');
        $stmt->execute([$number]);
    } while ($stmt->fetch());
    return $number;
}

function generate_reference(string $prefix): string {
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(6)));
}

function is_valid_amount(mixed $amount, float $min = MIN_TRANSACTION_AMOUNT, float $max = MAX_TRANSACTION_AMOUNT): bool {
    if (!is_numeric($amount)) return false;
    $amount = (float)$amount;
    if ($amount < $min || $amount > $max) return false;
    // reject more than 2 decimal places
    if (round($amount, 2) !== round($amount, 8)) return false;
    return true;
}

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_username(string $username): bool {
    return (bool)preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username);
}

function is_strong_password(string $password): bool {
    if (strlen($password) < PASSWORD_MIN_LENGTH) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function redirect(string $path): never {
    header('Location: ' . APP_BASE_URL . $path);
    exit;
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message): void {
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $title, $message]);
}

function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $cache[$key] = $row ? $row['setting_value'] : $default;
}

function set_setting(string $key, string $value): void {
    $stmt = db()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function calculate_loan(float $principal, float $ratePercent, int $termMonths): array {
    $totalInterest = $principal * ($ratePercent / 100) * ($termMonths / 12);
    $totalRepayment = round($principal + $totalInterest, 2);
    $monthlyPayment = round($totalRepayment / $termMonths, 2);
    return [
        'principal' => round($principal, 2),
        'interest_rate' => $ratePercent,
        'term_months' => $termMonths,
        'total_interest' => round($totalInterest, 2),
        'total_repayment' => $totalRepayment,
        'monthly_payment' => $monthlyPayment,
    ];
}
