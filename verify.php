<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use LoganX\Config;
use LoganX\Security;
use LoganX\TokenService;
use LoganX\EmailQueueService;

$rawToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$appUrl = Config::appUrl();

// Email masking helper for privacy
function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

// Handle POST Confirmation Action (Consumes token and queues download email)
$postSuccess = false;
$postError = null;
$verifiedData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrfToken($csrfToken)) {
        $postError = 'Your session has expired. Please refresh the page and try again.';
    } elseif ($rawToken === '') {
        $postError = 'Missing verification token.';
    } else {
        // Atomically consume token
        $result = TokenService::consumeVerificationToken($rawToken);
        if (!$result['success']) {
            $postError = $result['message'];
        } else {
            $postSuccess = true;
            $verifiedData = $result;

            // Automatically queue and dispatch download delivery email
            $ttlHours = (int)Config::get('DOWNLOAD_TOKEN_TTL_HOURS', 24);
            $maxDownloads = (int)$result['max_downloads'];

            EmailQueueService::enqueueDownloadEmail(
                $result['request_id'],
                $result['email'],
                $result['resource_title'],
                $result['raw_download_token'],
                $ttlHours,
                $maxDownloads,
                true // attempt immediate dispatch
            );
        }
    }
}

// Initial GET evaluation or re-evaluation
$tokenState = null;
if (!$postSuccess && $rawToken !== '') {
    $tokenState = TokenService::validateVerificationToken($rawToken);
}

$csrf = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification — LOGANX Digital Downloads</title>
  <link rel="stylesheet" href="assets/css/download-system.css">
  <style>
    :root {
      --bg: #080a0f;
      --panel: #10131b;
      --panel2: #151923;
      --text: #f6f7fb;
      --muted: #9da5b5;
      --line: rgba(255,255,255,.09);
      --accent: #8cff5b;
      --accent2: #5ee7ff;
      --danger: #ff5876;
      --warning: #ffb84d;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: radial-gradient(circle at 80% 0%, rgba(94,231,255,.09), transparent 30%),
                  radial-gradient(circle at 10% 10%, rgba(140,255,91,.07), transparent 25%),
                  var(--bg);
      color: var(--text);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .topbar {
      border-bottom: 1px solid var(--line);
      background: rgba(8,10,15,.85);
      backdrop-filter: blur(18px);
      padding: 18px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .logo {
      font-weight: 900;
      font-size: 22px;
      letter-spacing: -1px;
      text-decoration: none;
      color: var(--text);
    }
    .logo span { color: var(--accent); }
    .back-link {
      color: var(--muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: color .2s;
    }
    .back-link:hover { color: var(--accent); }
    .container {
      width: min(calc(100% - 32px), 640px);
      margin: 60px auto 40px;
      flex: 1;
    }
    .card {
      background: linear-gradient(145deg, #121622, #0d1017);
      border: 1px solid var(--line);
      border-radius: 24px;
      padding: 40px 36px;
      box-shadow: 0 30px 100px rgba(0,0,0,.5);
      text-align: center;
    }
    .badge {
      display: inline-flex;
      padding: 6px 12px;
      border: 1px solid rgba(140,255,91,.25);
      background: rgba(140,255,91,.08);
      border-radius: 999px;
      color: var(--accent);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 20px;
    }
    .badge.danger {
      border-color: rgba(255,88,118,.3);
      background: rgba(255,88,118,.08);
      color: var(--danger);
    }
    .badge.warning {
      border-color: rgba(255,184,77,.3);
      background: rgba(255,184,77,.08);
      color: var(--warning);
    }
    .icon-wrap {
      width: 72px;
      height: 72px;
      margin: 0 auto 24px;
      border-radius: 20px;
      display: grid;
      place-items: center;
      font-size: 32px;
      background: #171d28;
      border: 1px solid var(--line);
    }
    .icon-success { color: var(--accent); border-color: rgba(140,255,91,.35); background: rgba(140,255,91,.08); }
    .icon-danger { color: var(--danger); border-color: rgba(255,88,118,.35); background: rgba(255,88,118,.08); }
    .icon-warning { color: var(--warning); border-color: rgba(255,184,77,.35); background: rgba(255,184,77,.08); }
    h1 {
      font-size: clamp(26px, 4vw, 36px);
      letter-spacing: -1.5px;
      line-height: 1.15;
      margin-bottom: 14px;
    }
    p {
      color: var(--muted);
      font-size: 15px;
      margin-bottom: 24px;
    }
    .info-box {
      background: #161b26;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 20px;
      text-align: left;
      margin-bottom: 28px;
    }
    .info-label {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--accent);
      margin-bottom: 6px;
    }
    .info-title {
      font-size: 17px;
      font-weight: 700;
      color: #fff;
    }
    .info-email {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 16px 24px;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 800;
      border: 0;
      cursor: pointer;
      text-decoration: none;
      transition: transform .2s, box-shadow .2s;
    }
    .btn-primary {
      background: var(--accent);
      color: #071006;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(140,255,91,.25);
    }
    .btn-secondary {
      background: #171d28;
      color: #fff;
      border: 1px solid var(--line);
    }
    .btn-secondary:hover {
      border-color: rgba(255,255,255,.2);
      transform: translateY(-2px);
    }
    .note {
      font-size: 12px;
      color: #727a8c;
      margin-top: 18px;
    }
    footer {
      text-align: center;
      padding: 24px;
      color: #636b7c;
      font-size: 12px;
      border-top: 1px solid var(--line);
    }
  </style>
</head>
<body>

  <header class="topbar">
    <a class="logo" href="<?= Security::e($appUrl) ?>/">Logan<span>X</span></a>
    <a class="back-link" href="<?= Security::e($appUrl) ?>/">← Back to Website</a>
  </header>

  <main class="container">
    <div class="card">

      <?php if ($postSuccess && $verifiedData): ?>
        <!-- MODULE 9: VERIFICATION SUCCESS PAGE -->
        <div class="icon-wrap icon-success">✓</div>
        <span class="badge">Verification Complete</span>
        <h1>Your Download Link Has Been Sent!</h1>
        <p>Thank you for verifying your email address. Your secure, personalized download link has just been dispatched to:</p>

        <div class="info-box">
          <div class="info-label">Product Ready</div>
          <div class="info-title"><?= Security::e($verifiedData['resource_title']) ?></div>
          <div class="info-email">Delivered to: <strong><?= Security::e($verifiedData['email']) ?></strong></div>
        </div>

        <p style="font-size: 14px; color: #a4adc1; margin-bottom: 24px;">
          📬 Please check your email inbox (and spam/junk folder). Click the secure link inside the email to begin downloading your file.
          <br><br>
          <em>For security reasons, download links are delivered exclusively via email. You do not need to take further action on this page.</em>
        </p>

        <a class="btn btn-secondary" href="<?= Security::e($appUrl) ?>/">Return to LOGANX Home</a>

      <?php elseif ($postError): ?>
        <!-- ERROR UPON SUBMISSION -->
        <div class="icon-wrap icon-danger">✕</div>
        <span class="badge danger">Verification Failed</span>
        <h1>Unable to Complete Verification</h1>
        <p><?= Security::e($postError) ?></p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request a New Link</a>

      <?php elseif ($tokenState === null || $rawToken === ''): ?>
        <!-- MISSING OR INVALID TOKEN -->
        <div class="icon-wrap icon-danger">✕</div>
        <span class="badge danger">Invalid Token</span>
        <h1>Invalid Verification Link</h1>
        <p>This verification link is missing, malformed, or does not exist in our system.</p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/">Go to Homepage</a>

      <?php elseif ($tokenState['status'] === 'expired'): ?>
        <!-- EXPIRED TOKEN -->
        <div class="icon-wrap icon-warning">⏳</div>
        <span class="badge warning">Link Expired</span>
        <h1>Verification Link Expired</h1>
        <p>For your security, verification links expire after 30 minutes. You can request a fresh verification link anytime.</p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request New Download Link</a>

      <?php elseif ($tokenState['status'] === 'used'): ?>
        <!-- ALREADY USED TOKEN -->
        <div class="icon-wrap icon-success">✓</div>
        <span class="badge">Already Verified</span>
        <h1>Link Already Confirmed</h1>
        <p>This verification token has already been confirmed and your download link was sent to your email.</p>
        <p style="font-size: 13px; color: #8792a6;">Please check your inbox for the email titled <strong>"Your Download Is Ready — LOGANX"</strong>.</p>
        <a class="btn btn-secondary" href="<?= Security::e($appUrl) ?>/">Return to Website</a>

      <?php elseif ($tokenState['status'] === 'revoked'): ?>
        <!-- REVOKED TOKEN -->
        <div class="icon-wrap icon-danger">✕</div>
        <span class="badge danger">Revoked</span>
        <h1>Token Revoked</h1>
        <p>This download verification request was cancelled or revoked by an administrator.</p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request New Link</a>

      <?php elseif ($tokenState['status'] === 'inactive_resource'): ?>
        <!-- INACTIVE RESOURCE -->
        <div class="icon-wrap icon-warning">⚠️</div>
        <span class="badge warning">Resource Unavailable</span>
        <h1>Download Unavailable</h1>
        <p>The requested digital product is currently inactive or under maintenance.</p>
        <a class="btn btn-secondary" href="<?= Security::e($appUrl) ?>/">Return to Homepage</a>

      <?php elseif ($tokenState['status'] === 'valid'): ?>
        <!-- MODULE 3: CONFIRMATION ACTION REQUIRED (Protects against email bot pre-fetch scanners) -->
        <?php $rec = $tokenState['record']; ?>
        <div class="icon-wrap icon-success">🔒</div>
        <span class="badge">Step 2 of 2 · Confirmation</span>
        <h1>Confirm Your Download Request</h1>
        <p>Please confirm that you would like to receive the secure download link for this product.</p>

        <div class="info-box">
          <div class="info-label">Requested Item</div>
          <div class="info-title"><?= Security::e($rec['resource_title']) ?></div>
          <div class="info-email">Recipient: <?= Security::e(maskEmail($rec['email'])) ?></div>
        </div>

        <form method="POST" action="verify.php">
          <input type="hidden" name="token" value="<?= Security::e($rawToken) ?>">
          <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
          <input type="hidden" name="confirm_action" value="1">

          <button type="submit" class="btn btn-primary">
            Confirm & Send Download Link &rarr;
          </button>
        </form>

        <div class="note">
          🔒 By confirming, we will email your single-use, time-limited download link to your address.
        </div>

      <?php endif; ?>

    </div>
  </main>

  <footer>
    &copy; <?= date('Y') ?> LOGANX Website Studio. All rights reserved.
  </footer>

</body>
</html>
