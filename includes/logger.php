<?php
declare(strict_types=1);

function log_security_event(?int $userId, string $eventType, string $details = ''): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $eventType,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            substr($details, 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('Failed to write security log: ' . $e->getMessage());
    }
}

function log_admin_action(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, string $details = ''): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$adminId, $action, $targetType, $targetId, substr($details, 0, 255)]);
    } catch (Throwable $e) {
        error_log('Failed to write admin log: ' . $e->getMessage());
    }
}
