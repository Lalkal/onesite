<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Config;
use LoganX\Security;
use LoganX\DownloadService;

$rawToken = trim((string)($_GET['token'] ?? ''));
$appUrl = Config::appUrl();
$clientIp = Security::getClientIp();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($rawToken === '') {
    $result = [
        'status'  => 'not_found',
        'message' => 'No download token was provided in the request.'
    ];
} else {
    // Attempt download and stream if valid (stream=true terminates and flushes binary file)
    $result = DownloadService::serveDownload($rawToken, $clientIp, $userAgent, true);
}

// If code reaches here, streaming did not happen (e.g. token expired, limit reached, invalid)
$status = $result['status'] ?? 'not_found';
$message = $result['message'] ?? 'An error occurred while accessing the download.';
$record = $result['record'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Download Status — LOGANX Digital Downloads</title>
  <link rel="stylesheet" href="/assets/css/download-system.css">
  <style>
    :root {
      --bg: #080a0f;
      --panel: #10131b;
      --text: #f6f7fb;
      --muted: #9da5b5;
      --line: rgba(255,255,255,.09);
      --accent: #8cff5b;
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
    .container {
      width: min(calc(100% - 32px), 620px);
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
      border: 1px solid rgba(255,88,118,.3);
      background: rgba(255,88,118,.08);
      border-radius: 999px;
      color: var(--danger);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 20px;
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
    .icon-danger { color: var(--danger); border-color: rgba(255,88,118,.35); background: rgba(255,88,118,.08); }
    .icon-warning { color: var(--warning); border-color: rgba(255,184,77,.35); background: rgba(255,184,77,.08); }
    h1 {
      font-size: clamp(26px, 4vw, 34px);
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
      margin-top: 12px;
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
    <a style="color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;" href="<?= Security::e($appUrl) ?>/">← Back to Website</a>
  </header>

  <main class="container">
    <div class="card">

      <?php if ($status === 'expired'): ?>
        <!-- MODULE 9: DOWNLOAD LINK EXPIRED PAGE -->
        <div class="icon-wrap icon-warning">⏳</div>
        <span class="badge warning">Link Expired</span>
        <h1>Download Link Has Expired</h1>
        <p>This download link has passed its authorized time limit (24 hours). For your security, download links expire automatically.</p>

        <?php if ($record): ?>
        <div class="info-box">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--accent);">Product</div>
          <div style="font-size:17px;font-weight:700;color:#fff;margin-top:4px;"><?= Security::e($record['resource_title']) ?></div>
          <div style="font-size:13px;color:var(--muted);margin-top:4px;">Expired at: <?= Security::e($record['expires_at']) ?></div>
        </div>
        <?php endif; ?>

        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request a New Link &rarr;</a>
        <a class="btn btn-secondary" href="<?= Security::e($appUrl) ?>/">Return to Homepage</a>

      <?php elseif ($status === 'limit_reached'): ?>
        <!-- MODULE 9: DOWNLOAD LIMIT REACHED PAGE -->
        <div class="icon-wrap icon-warning">🛑</div>
        <span class="badge warning">Limit Reached</span>
        <h1>Maximum Downloads Reached</h1>
        <p>This secure link has reached its maximum download allowance (<?= Security::e((string)($record['max_downloads'] ?? 5)) ?> downloads).</p>

        <div class="info-box">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--accent);">Security Notice</div>
          <div style="font-size:14px;color:var(--muted);margin-top:4px;">
            Personal links allow up to 5 downloads to protect your files. If you need to re-download this product, please request a fresh link using your verified email.
          </div>
        </div>

        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request New Download Link &rarr;</a>
        <a class="btn btn-secondary" href="<?= Security::e($appUrl) ?>/">Return to Homepage</a>

      <?php elseif ($status === 'revoked'): ?>
        <!-- REVOKED LINK -->
        <div class="icon-wrap icon-danger">✕</div>
        <span class="badge">Revoked</span>
        <h1>Download Link Revoked</h1>
        <p>This download link was revoked by a security administrator and can no longer be used.</p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request a New Link &rarr;</a>

      <?php elseif ($status === 'missing_file' || $status === 'resource_inactive'): ?>
        <!-- MODULE 9: FRIENDLY FILE UNAVAILABLE PAGE -->
        <div class="icon-wrap icon-warning">⚠️</div>
        <span class="badge warning">Temporarily Unavailable</span>
        <h1>File Temporarily Unavailable</h1>
        <p>The requested file is currently undergoing maintenance or is temporarily inaccessible on our delivery network.</p>
        <div class="info-box">
          <div style="font-size:14px;color:var(--muted);">
            Our technical team has been notified. Please try again shortly or contact support at <strong style="color:#fff;">vikneshb@zoho.com</strong>.
          </div>
        </div>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/">Return to LOGANX Home</a>

      <?php else: ?>
        <!-- INVALID / NOT FOUND -->
        <div class="icon-wrap icon-danger">✕</div>
        <span class="badge">Invalid Link</span>
        <h1>Invalid Download Link</h1>
        <p>The download token provided in the URL is invalid, truncated, or does not exist.</p>
        <a class="btn btn-primary" href="<?= Security::e($appUrl) ?>/#downloads">Request a Download Link &rarr;</a>

      <?php endif; ?>

    </div>
  </main>

  <footer>
    &copy; <?= date('Y') ?> LOGANX Website Studio. All rights reserved.
  </footer>

</body>
</html>
