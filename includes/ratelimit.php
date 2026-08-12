<?php
declare(strict_types=1);

function is_action_rate_limited(int $userId, string $eventType, int $maxCount, int $windowMinutes): bool {
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS c FROM security_logs
         WHERE user_id = ? AND event_type = ?
         AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->execute([$userId, $eventType, $windowMinutes]);
    return (int)$stmt->fetch()['c'] >= $maxCount;
}
