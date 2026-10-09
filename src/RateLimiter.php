<?php
declare(strict_types=1);

namespace LoganX;

use PDO;

class RateLimiter
{
    /**
     * Check and record rate limit attempt.
     * Returns array ['allowed' => bool, 'retry_after' => int, 'reason' => string]
     */
    public static function check(string $identifier, string $action = 'request_download'): array
    {
        $pdo = Database::getConnection();
        $identifier = strtolower(trim($identifier));
        $cooldownSec = (int)Config::get('RATE_LIMIT_COOLDOWN_SECONDS', 60);
        $maxPerHour = (int)Config::get('RATE_LIMIT_MAX_PER_HOUR', 10);
        $now = time();

        $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE identifier = :ident AND action = :act LIMIT 1");
        $stmt->execute([':ident' => $identifier, ':act' => $action]);
        $row = $stmt->fetch();

        if ($row) {
            $lastHit = strtotime($row['last_hit_at']);
            $windowStart = strtotime($row['window_start_at']);
            $hitCount = (int)$row['hit_count'];

            // Check 60-second cooldown
            $elapsedSinceLast = $now - $lastHit;
            if ($elapsedSinceLast < $cooldownSec) {
                $retryAfter = $cooldownSec - $elapsedSinceLast;
                return [
                    'allowed'     => false,
                    'retry_after' => $retryAfter,
                    'reason'      => "Please wait {$retryAfter} seconds before requesting another verification email."
                ];
            }

            // Check 1-hour window
            $elapsedWindow = $now - $windowStart;
            if ($elapsedWindow < 3600) {
                if ($hitCount >= $maxPerHour) {
                    $retryAfter = 3600 - $elapsedWindow;
                    $retryMinutes = ceil($retryAfter / 60);
                    return [
                        'allowed'     => false,
                        'retry_after' => $retryAfter,
                        'reason'      => "Hourly request limit reached. Please try again in approximately {$retryMinutes} minute(s)."
                    ];
                }

                // Increment hit count in existing window
                $upd = $pdo->prepare(
                    "UPDATE rate_limits 
                     SET hit_count = hit_count + 1, last_hit_at = NOW() 
                     WHERE id = :id"
                );
                $upd->execute([':id' => $row['id']]);
            } else {
                // Reset window
                $upd = $pdo->prepare(
                    "UPDATE rate_limits 
                     SET hit_count = 1, last_hit_at = NOW(), window_start_at = NOW() 
                     WHERE id = :id"
                );
                $upd->execute([':id' => $row['id']]);
            }
        } else {
            // First hit: insert record
            $ins = $pdo->prepare(
                "INSERT INTO rate_limits (identifier, action, hit_count, last_hit_at, window_start_at)
                 VALUES (:ident, :act, 1, NOW(), NOW())"
            );
            $ins->execute([':ident' => $identifier, ':act' => $action]);
        }

        return ['allowed' => true, 'retry_after' => 0, 'reason' => ''];
    }

    /**
     * Clear rate limit for an identifier (useful for tests)
     */
    public static function clear(string $identifier, string $action = 'request_download'): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = :ident AND action = :act");
        $stmt->execute([':ident' => strtolower(trim($identifier)), ':act' => $action]);
    }
}
