<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use LoganX\Database;
use LoganX\Security;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI execution only.\n";
    exit(1);
}

echo "=== LOGANX Data Retention & Cleanup Worker ===\n";

try {
    $pdo = Database::getConnection();

    // 1. Delete unused expired verification tokens older than 7 days
    $stmt1 = $pdo->prepare("DELETE FROM email_verification_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND used_at IS NULL");
    $stmt1->execute();
    $tokensDeleted = $stmt1->rowCount();
    echo "Cleaned up {$tokensDeleted} expired verification token records.\n";

    // 2. Delete rate limits older than 24 hours
    $stmt2 = $pdo->prepare("DELETE FROM rate_limits WHERE last_hit_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt2->execute();
    $rateLimitsDeleted = $stmt2->rowCount();
    echo "Cleaned up {$rateLimitsDeleted} stale rate limit tracking entries.\n";

    // 3. Delete download logs older than 90 days
    $stmt3 = $pdo->prepare("DELETE FROM download_logs WHERE downloaded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt3->execute();
    $logsDeleted = $stmt3->rowCount();
    echo "Cleaned up {$logsDeleted} download logs older than 90 days.\n";

    Security::log('info', "Data retention cleanup completed", [
        'tokens_deleted'      => $tokensDeleted,
        'rate_limits_deleted' => $rateLimitsDeleted,
        'logs_deleted'        => $logsDeleted
    ]);

    echo "Cleanup finished successfully.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERROR during cleanup: " . $e->getMessage() . "\n";
    Security::log('error', "Cleanup failed: " . $e->getMessage());
    exit(1);
}
