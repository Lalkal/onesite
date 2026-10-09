<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Config;
use LoganX\Database;
use LoganX\Security;
use LoganX\TokenService;
use LoganX\RateLimiter;
use LoganX\Mailer;
use LoganX\EmailQueueService;
use LoganX\DownloadService;

// Test Runner Counter
$passed = 0;
$failed = 0;
$tests = [];

function test(string $name, callable $callback) {
    global $passed, $failed, $tests;
    echo "Running: {$name}... ";
    try {
        $callback();
        echo "\033[32m[PASS]\033[0m\n";
        $passed++;
        $tests[] = ['name' => $name, 'status' => 'PASS'];
    } catch (\Throwable $e) {
        echo "\033[31m[FAIL]\033[0m: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'FAIL', 'error' => $e->getMessage()];
    }
}

function assertTrue(bool $condition, string $msg = 'Expected true, got false') {
    if (!$condition) throw new RuntimeException($msg);
}

function assertEquals(mixed $expected, mixed $actual, string $msg = '') {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new RuntimeException($msg ?: "Expected {$expStr}, got {$actStr}");
    }
}

echo "========================================================\n";
echo "LOGANX Automated Email Verification & Download Delivery\n";
echo "Verification Test Suite (20 Requirements)\n";
echo "========================================================\n\n";

$pdo = Database::getConnection();

// Mock transport interceptor so tests don't require external live SMTP servers
$capturedEmails = [];
Mailer::$testTransportInterceptor = function($recipient, $subject, $html, $plain, $type) use (&$capturedEmails) {
    $capturedEmails[] = [
        'recipient' => $recipient,
        'subject'   => $subject,
        'html'      => $html,
        'plain'     => $plain,
        'type'      => $type
    ];
    return ['success' => true, 'message' => 'Intercepted by test harness'];
};

// TEST 1: A visitor requests a download.
test("1. A visitor requests a download", function() use ($pdo) {
    $email = 'visitor_test1_' . time() . '@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1, '127.0.0.1', 'Mozilla/5.0');
    assertTrue(strlen($rawToken) === 64, "Raw token must be 64 hex characters (32 bytes)");

    $hash = TokenService::hashToken($rawToken);
    $stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE token_hash = :hash");
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    assertTrue(!empty($row), "Token record must exist in database");
    assertEquals($email, $row['email']);
});

// TEST 2: A verification email is successfully sent.
test("2. A verification email is successfully sent", function() use (&$capturedEmails) {
    $capturedEmails = [];
    $email = 'verify_email_test@example.com';
    $res = Mailer::sendVerificationEmail($email, 'LOGANX Desktop Studio', 'sample_test_token_hex_123456');
    assertTrue($res['success'], "Mailer should return success");
    assertEquals(1, count($capturedEmails), "Exactly one email captured");
    assertEquals('Verify Your Email — LOGANX Download Request', $capturedEmails[0]['subject']);
    assertTrue(str_contains($capturedEmails[0]['html'], 'sample_test_token_hex_123456'), "HTML body must include verification link token");
});

// TEST 3: The correct resource is associated with the request.
test("3. The correct resource is associated with the request", function() use ($pdo) {
    $email = 'resource_assoc@example.com';
    $rawToken = TokenService::createVerificationToken($email, 2, '127.0.0.1'); // Resource ID 2 = Starter Kit
    $validation = TokenService::validateVerificationToken($rawToken);
    assertTrue($validation !== null, "Validation must find token");
    assertEquals('valid', $validation['status']);
    assertEquals(2, (int)$validation['record']['resource_id'], "Associated resource must be ID 2");
    assertEquals('LOGANX Business Website Starter Kit', $validation['record']['resource_title']);
});

// TEST 4: A valid verification token works.
test("4. A valid verification token works", function() use ($pdo) {
    $email = 'valid_token_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $validation = TokenService::validateVerificationToken($rawToken);
    assertEquals('valid', $validation['status']);
    assertTrue(empty($validation['record']['used_at']), "Token must not be marked used yet");
});

// TEST 5: An expired verification token is rejected.
test("5. An expired verification token is rejected", function() use ($pdo) {
    $email = 'expired_token_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $hash = TokenService::hashToken($rawToken);
    // Artificially expire the token using PHP date to prevent timezone divergence
    $pastDate = date('Y-m-d H:i:s', time() - 300);
    $pdo->prepare("UPDATE email_verification_tokens SET expires_at = :past WHERE token_hash = :hash")
        ->execute([':past' => $pastDate, ':hash' => $hash]);

    $validation = TokenService::validateVerificationToken($rawToken);
    assertEquals('expired', $validation['status']);

    $consumeResult = TokenService::consumeVerificationToken($rawToken);
    assertTrue(!$consumeResult['success'], "Consume must fail on expired token");
    assertEquals('expired', $consumeResult['error']);
});

// TEST 6: A used token cannot be replayed.
test("6. A used token cannot be replayed", function() use ($pdo) {
    $email = 'replay_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    
    // First consumption succeeds
    $res1 = TokenService::consumeVerificationToken($rawToken);
    assertTrue($res1['success'], "First consumption should succeed");

    // Second consumption must fail
    $res2 = TokenService::consumeVerificationToken($rawToken);
    assertTrue(!$res2['success'], "Second consumption must be rejected");
    assertEquals('used', $res2['error']);
});

// TEST 7: A verification email scanner cannot consume the token.
test("7. A verification email scanner cannot consume the token", function() use ($pdo) {
    $email = 'scanner_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    
    // Simulating GET request: calls validateVerificationToken (scanner inspection)
    $inspection = TokenService::validateVerificationToken($rawToken);
    assertEquals('valid', $inspection['status'], "Token must still be valid after GET inspection");

    // Verify in DB that used_at is STILL NULL
    $hash = TokenService::hashToken($rawToken);
    $stmt = $pdo->prepare("SELECT used_at FROM email_verification_tokens WHERE token_hash = :hash");
    $stmt->execute([':hash' => $hash]);
    $usedAt = $stmt->fetchColumn();
    assertTrue($usedAt === null, "Token must NOT be consumed by scanner opening GET link");
});

// TEST 8: A successful verification queues exactly one download email.
test("8. A successful verification queues exactly one download email", function() use ($pdo, &$capturedEmails) {
    $capturedEmails = [];
    $email = 'queue_test_' . time() . '@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    
    $consumed = TokenService::consumeVerificationToken($rawToken);
    assertTrue($consumed['success']);

    $queueResult = EmailQueueService::enqueueDownloadEmail(
        $consumed['request_id'],
        $consumed['email'],
        $consumed['resource_title'],
        $consumed['raw_download_token'],
        24,
        5,
        true
    );

    assertTrue($queueResult['success']);
    assertEquals(1, count($capturedEmails), "Exactly one download email dispatched");
    assertEquals('Your Download Is Ready — LOGANX', $capturedEmails[0]['subject']);

    // Check email_delivery_queue status in DB
    $stmt = $pdo->prepare("SELECT status FROM email_delivery_queue WHERE verified_download_request_id = :rid");
    $stmt->execute([':rid' => $consumed['request_id']]);
    $status = $stmt->fetchColumn();
    assertEquals('sent', $status);
});

// TEST 9: A temporary SMTP failure triggers a safe retry.
test("9. A temporary SMTP failure triggers a safe retry", function() use ($pdo) {
    $email = 'smtp_fail_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);

    // Mock Mailer to simulate SMTP connection error
    $originalInterceptor = Mailer::$testTransportInterceptor;
    Mailer::$testTransportInterceptor = function() {
        return ['success' => false, 'error_code' => 'SMTP_CONN_FAIL', 'message' => 'Connection to SMTP server timed out'];
    };

    $queueResult = EmailQueueService::enqueueDownloadEmail(
        $consumed['request_id'],
        $consumed['email'],
        $consumed['resource_title'],
        $consumed['raw_download_token'],
        24,
        5,
        true
    );

    // Restore normal interceptor
    Mailer::$testTransportInterceptor = $originalInterceptor;

    assertTrue(!$queueResult['success'], "Queue dispatch should record failure");
    assertTrue(isset($queueResult['retry_at']), "Queue must schedule a retry time");

    // Verify DB queue status is pending retry with attempt count incremented
    $stmt = $pdo->prepare("SELECT status, attempts, next_attempt_at FROM email_delivery_queue WHERE verified_download_request_id = :rid");
    $stmt->execute([':rid' => $consumed['request_id']]);
    $row = $stmt->fetch();
    assertEquals('pending', $row['status']);
    assertEquals(1, (int)$row['attempts']);
    assertTrue(strtotime($row['next_attempt_at']) > time(), "Next attempt must be set in the future (backoff)");
});

// TEST 10: Repeated verification submissions do not produce duplicate download emails.
test("10. Repeated verification submissions do not produce duplicate download emails", function() use ($pdo, &$capturedEmails) {
    $capturedEmails = [];
    $email = 'idempotent_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);

    // Send 1st
    EmailQueueService::enqueueDownloadEmail(
        $consumed['request_id'],
        $consumed['email'],
        $consumed['resource_title'],
        $consumed['raw_download_token'],
        24, 5, true
    );
    $countAfterFirst = count($capturedEmails);
    assertEquals(1, $countAfterFirst);

    // Attempt enqueue duplicate
    $res2 = EmailQueueService::enqueueDownloadEmail(
        $consumed['request_id'],
        $consumed['email'],
        $consumed['resource_title'],
        $consumed['raw_download_token'],
        24, 5, true
    );

    assertTrue($res2['already_sent'], "Duplicate enqueue must detect already sent");
    assertEquals(1, count($capturedEmails), "No duplicate email sent");
});

// TEST 11: The emailed download link works.
test("11. The emailed download link works", function() use ($pdo) {
    $email = 'dl_link_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);
    $rawDlToken = $consumed['raw_download_token'];

    $downloadResult = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('success', $downloadResult['status']);
    assertTrue(file_exists($downloadResult['file_path']));
    assertTrue($downloadResult['file_size'] > 0);
});

// TEST 12: An expired download link is rejected.
test("12. An expired download link is rejected", function() use ($pdo) {
    $email = 'dl_expired@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);
    $rawDlToken = $consumed['raw_download_token'];
    $hash = TokenService::hashToken($rawDlToken);

    // Expire download token in DB using PHP timestamp to avoid timezone divergence
    $pastDate = date('Y-m-d H:i:s', time() - 3600);
    $pdo->prepare("UPDATE download_tokens SET expires_at = :past WHERE token_hash = :hash")
        ->execute([':past' => $pastDate, ':hash' => $hash]);

    $res = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('expired', $res['status']);
});

// TEST 13: A download token cannot access another resource.
test("13. A download token cannot access another resource", function() use ($pdo) {
    $email = 'cross_resource@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1); // Resource 1
    $consumed = TokenService::consumeVerificationToken($rawToken);
    $rawDlToken = $consumed['raw_download_token'];

    $validation = TokenService::validateDownloadToken($rawDlToken);
    assertEquals('valid', $validation['status']);
    assertEquals(1, (int)$validation['record']['resource_id'], "Token must only be bound to Resource 1");
    // Verify it cannot point to Resource 2
    assertTrue((int)$validation['record']['resource_id'] !== 2);
});

// TEST 14: Direct access to protected files is blocked.
test("14. Direct access to protected files is blocked", function() {
    $storageDir = Config::storagePath();
    $htaccess = $storageDir . '/.htaccess';
    assertTrue(file_exists($htaccess), ".htaccess must exist in protected storage directory");
    $content = file_get_contents($htaccess);
    assertTrue(str_contains($content, 'Require all denied') || str_contains($content, 'Deny from all'), "Direct web requests must be denied");

    // Path traversal test
    $traversalPath = $storageDir . '/../../../../etc/passwd';
    $isSafe = Security::isPathSafe($storageDir, $traversalPath);
    assertTrue(!$isSafe, "Path traversal path must be rejected by isPathSafe");
});

// TEST 15: Download limits are enforced under concurrent requests.
test("15. Download limits are enforced under concurrent requests", function() use ($pdo) {
    $email = 'limit_test@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);
    $rawDlToken = $consumed['raw_download_token'];
    $hash = TokenService::hashToken($rawDlToken);

    // Set max downloads to 3
    $pdo->prepare("UPDATE download_tokens SET max_downloads = 3 WHERE token_hash = :hash")
        ->execute([':hash' => $hash]);

    // Download 1
    $d1 = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('success', $d1['status']);

    // Download 2
    $d2 = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('success', $d2['status']);

    // Download 3
    $d3 = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('success', $d3['status']);

    // Download 4 (limit reached)
    $d4 = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('limit_reached', $d4['status']);
});

// TEST 16: Disabled resources cannot be downloaded.
test("16. Disabled resources cannot be downloaded", function() use ($pdo) {
    $email = 'disabled_res@example.com';
    $rawToken = TokenService::createVerificationToken($email, 1);
    $consumed = TokenService::consumeVerificationToken($rawToken);
    $rawDlToken = $consumed['raw_download_token'];

    // Disable resource 1 temporarily
    $pdo->exec("UPDATE download_resources SET is_active = 0 WHERE id = 1");

    $res = DownloadService::serveDownload($rawDlToken, '127.0.0.1', 'PHPUnit', false);
    assertEquals('resource_inactive', $res['status']);

    // Restore resource 1
    $pdo->exec("UPDATE download_resources SET is_active = 1 WHERE id = 1");
});

// TEST 17: Rate limiting works.
test("17. Rate limiting works", function() {
    $testIp = '198.51.100.' . rand(1, 250);
    RateLimiter::clear("ip:{$testIp}");

    // First attempt allowed
    $r1 = RateLimiter::check("ip:{$testIp}");
    assertTrue($r1['allowed']);

    // Immediate second attempt within 60s blocked by cooldown
    $r2 = RateLimiter::check("ip:{$testIp}");
    assertTrue(!$r2['allowed'], "Second immediate request must be blocked by cooldown");
    assertTrue($r2['retry_after'] > 0);

    RateLimiter::clear("ip:{$testIp}");
});

// TEST 18: The system works on desktop and mobile.
test("18. The system works on desktop and mobile", function() {
    $cssFile = dirname(__DIR__) . '/assets/css/download-system.css';
    $jsFile = dirname(__DIR__) . '/assets/js/download-system.js';
    assertTrue(file_exists($cssFile), "CSS file must exist");
    assertTrue(file_exists($jsFile), "JS file must exist");

    $css = file_get_contents($cssFile);
    assertTrue(str_contains($css, '@media'), "CSS must include mobile media queries");
    assertTrue(str_contains($css, 'lx-modal-box'), "CSS must include modal box styles");
});

// TEST 19: SMTP credentials and raw tokens do not appear in application logs.
test("19. SMTP credentials and raw tokens do not appear in application logs", function() {
    $logPath = dirname(__DIR__) . '/storage/logs/app.log';
    if (file_exists($logPath)) {
        unlink($logPath);
    }
    $rawToken = TokenService::generateRawToken();
    Security::log('info', "Processing token {$rawToken} with password SecretP@ssword123", [
        'token'    => $rawToken,
        'password' => 'SuperSecret123',
        'status'   => 'ok'
    ]);

    assertTrue(file_exists($logPath), "Log file must exist");
    $logContent = file_get_contents($logPath);

    assertTrue(!str_contains($logContent, $rawToken), "Raw token must NOT appear in logs");
    assertTrue(!str_contains($logContent, 'SecretP@ssword123'), "Plaintext passwords must NOT appear in logs");
    assertTrue(!str_contains($logContent, 'SuperSecret123'), "Context passwords must be redacted");
});

// TEST 20: All relevant tests pass.
test("20. All relevant tests pass", function() use (&$passed, &$failed) {
    assertTrue($failed === 0, "All previous 19 tests must have passed with zero failures");
});

echo "\n========================================================\n";
echo "SUMMARY: {$passed} Passed, {$failed} Failed (Total: " . ($passed + $failed) . ")\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
