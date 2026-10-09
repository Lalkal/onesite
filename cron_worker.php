<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use LoganX\EmailQueueService;
use LoganX\Security;

// CLI only or authorized key access
if (php_sapi_name() !== 'cli' && (!isset($_GET['key']) || $_GET['key'] !== 'loganx_cron_secret')) {
    http_response_code(403);
    echo "Access denied. Run via CLI: php cron_worker.php\n";
    exit(1);
}

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting LOGANX Email Queue Worker...\n";

try {
    $results = EmailQueueService::processEligibleQueue(25);
    $elapsed = round(microtime(true) - $startTime, 3);

    echo sprintf(
        "[%s] Completed queue run in %ss. Processed: %d | Succeeded: %d | Failed: %d\n",
        date('Y-m-d H:i:s'),
        $elapsed,
        $results['processed'],
        $results['succeeded'],
        $results['failed']
    );

    Security::log('info', "Queue worker finished run", $results);
    exit(0);
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR in queue worker: " . $e->getMessage() . "\n";
    Security::log('error', "Cron worker encountered exception: " . $e->getMessage());
    exit(1);
}
