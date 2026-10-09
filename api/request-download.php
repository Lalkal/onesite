<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Config;
use LoganX\Database;
use LoganX\Security;
use LoganX\TokenService;
use LoganX\RateLimiter;
use LoganX\Mailer;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Read JSON or POST form data
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?? $_POST;

$csrfToken  = $data['csrf_token'] ?? '';
$email      = strtolower(trim((string)($data['email'] ?? '')));
$resourceId = $data['resource_id'] ?? null;
$slug       = trim((string)($data['slug'] ?? ''));

// 1. Validate CSRF token
if (!Security::validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your security session has expired. Please refresh the page and try again.'
    ]);
    exit;
}

// 2. Validate Email Address
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

// 3. Resolve Resource
$pdo = Database::getConnection();
$resource = null;

if (!empty($resourceId) && is_numeric($resourceId)) {
    $stmt = $pdo->prepare("SELECT * FROM download_resources WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => (int)$resourceId]);
    $resource = $stmt->fetch();
} elseif (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM download_resources WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $resource = $stmt->fetch();
} else {
    // Default to the first active resource (e.g. windows-app)
    $stmt = $pdo->query("SELECT * FROM download_resources WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    $resource = $stmt->fetch();
}

if (!$resource) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'The requested download product is currently unavailable or inactive.'
    ]);
    exit;
}

// 4. Rate Limiting Check (by IP and by normalized email)
$clientIp = Security::getClientIp();

$ipLimit = RateLimiter::check("ip:{$clientIp}", 'request_download');
if (!$ipLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $ipLimit['reason'],
        'retry_after' => $ipLimit['retry_after']
    ]);
    exit;
}

$emailLimit = RateLimiter::check("email:{$email}", 'request_download');
if (!$emailLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $emailLimit['reason'],
        'retry_after' => $emailLimit['retry_after']
    ]);
    exit;
}

// 5. Generate Verification Token
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
try {
    $rawToken = TokenService::createVerificationToken(
        $email,
        (int)$resource['id'],
        $clientIp,
        $userAgent
    );
} catch (\Throwable $e) {
    Security::log('error', "Failed to create verification token: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while preparing your request. Please try again later.'
    ]);
    exit;
}

// 6. Deliver Verification Email via PHPMailer SMTP
$sendResult = Mailer::sendVerificationEmail($email, $resource['title'], $rawToken);

if (!$sendResult['success']) {
    // Critical: Do NOT simulate successful delivery if SMTP fails!
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'We could not dispatch the verification email due to an email delivery error. Please try again later or contact support.'
    ]);
    exit;
}

// 7. Generic Confirmation Message to reduce email enumeration
echo json_encode([
    'success' => true,
    'message' => 'If this email address is eligible, a verification link has been sent to your inbox. Please check your email (and spam folder) within 30 minutes to complete your request.'
]);
exit;
