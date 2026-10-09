<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

use LoganX\Database;
use LoganX\Security;
use LoganX\Config;

$pdo = null;
$dbError = null;
try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
    Security::log('warning', "Database connection unavailable in admin index: " . $dbError);
}

$totalVerifications = 0;
$totalVerified = 0;
$totalSentEmails = 0;
$totalFailedEmails = 0;
$totalPendingEmails = 0;
$totalDownloads = 0;
$totalActiveResources = 2;
$recentRequests = [];
$recentDownloads = [];

if ($pdo !== null) {
    try {
        $totalVerifications = (int)$pdo->query("SELECT COUNT(*) FROM email_verification_tokens")->fetchColumn();
        $totalVerified = (int)$pdo->query("SELECT COUNT(*) FROM verified_download_requests")->fetchColumn();
        $totalSentEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_delivery_queue WHERE status = 'sent'")->fetchColumn();
        $totalFailedEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_delivery_queue WHERE status = 'failed'")->fetchColumn();
        $totalPendingEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_delivery_queue WHERE status IN ('pending', 'processing')")->fetchColumn();
        $totalDownloads = (int)$pdo->query("SELECT COUNT(*) FROM download_logs WHERE download_status = 'success'")->fetchColumn();
        $totalActiveResources = (int)$pdo->query("SELECT COUNT(*) FROM download_resources WHERE is_active = 1")->fetchColumn();

        $stmtRecentReqs = $pdo->query(
            "SELECT vdr.id, vdr.email, vdr.verified_at, vdr.delivery_status, vdr.delivery_attempts,
                    r.title AS resource_title, edq.id AS queue_id, edq.last_error_message
             FROM verified_download_requests vdr
             JOIN download_resources r ON r.id = vdr.resource_id
             LEFT JOIN email_delivery_queue edq ON edq.verified_download_request_id = vdr.id
             ORDER BY vdr.verified_at DESC
             LIMIT 10"
        );
        $recentRequests = $stmtRecentReqs->fetchAll();

        $stmtRecentDownloads = $pdo->query(
            "SELECT dl.*, r.title AS resource_title
             FROM download_logs dl
             JOIN download_resources r ON r.id = dl.resource_id
             ORDER BY dl.downloaded_at DESC
             LIMIT 10"
        );
        $recentDownloads = $stmtRecentDownloads->fetchAll();
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }
}

$csrf = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — LOGANX Downloads</title>
  <style>
    :root {
      --bg: #090c13;
      --panel: #111520;
      --panel2: #161b29;
      --text: #f6f7fb;
      --muted: #959fad;
      --line: rgba(255,255,255,.08);
      --accent: #8cff5b;
      --accent2: #5ee7ff;
      --danger: #ff5876;
      --warning: #ffb84d;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Inter, ui-sans-serif, system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
    }
    .header {
      background: rgba(17,21,32,.9);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--line);
      padding: 16px 28px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .logo {
      font-size: 20px;
      font-weight: 900;
      letter-spacing: -1px;
    }
    .logo span { color: var(--accent); }
    .nav-links {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: color .2s;
    }
    .nav-links a:hover, .nav-links a.active { color: #fff; }
    .user-info {
      font-size: 13px;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logout {
      color: var(--danger);
      text-decoration: none;
      font-weight: 700;
    }
    .container {
      max-width: 1240px;
      margin: 32px auto;
      padding: 0 24px;
    }
    .page-head {
      margin-bottom: 28px;
    }
    h1 {
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -1px;
    }
    .subtitle { color: var(--muted); font-size: 14px; margin-top: 4px; }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
      margin-bottom: 36px;
    }
    .stat-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 22px 20px;
    }
    .stat-label {
      font-size: 11px;
      text-transform: uppercase;
      font-weight: 800;
      letter-spacing: .8px;
      color: var(--muted);
    }
    .stat-val {
      font-size: 30px;
      font-weight: 900;
      letter-spacing: -1px;
      margin-top: 6px;
    }
    .stat-sub { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .val-accent { color: var(--accent); }
    .val-danger { color: var(--danger); }
    .val-warning { color: var(--warning); }
    .val-info { color: var(--accent2); }

    .section-box {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 24px;
      margin-bottom: 32px;
    }
    .section-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }
    .section-head h2 { font-size: 18px; font-weight: 800; letter-spacing: -.5px; }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    th {
      text-align: left;
      padding: 12px 14px;
      color: var(--muted);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .8px;
      border-bottom: 1px solid var(--line);
    }
    td {
      padding: 14px;
      border-bottom: 1px solid rgba(255,255,255,.04);
      color: #cbd2df;
    }
    tr:hover td { background: rgba(255,255,255,.015); }
    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .badge-sent { background: rgba(140,255,91,.12); color: var(--accent); }
    .badge-failed { background: rgba(255,88,118,.12); color: var(--danger); }
    .badge-pending { background: rgba(255,184,77,.12); color: var(--warning); }
    .badge-success { background: rgba(94,231,255,.12); color: var(--accent2); }
    .btn-action {
      background: #1d2333;
      border: 1px solid var(--line);
      color: #fff;
      padding: 6px 10px;
      border-radius: 8px;
      font-size: 12px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .btn-action:hover { border-color: rgba(255,255,255,.2); color: var(--accent); }
  </style>
</head>
<body>

  <header class="header">
    <div class="logo">LOGAN<span>X</span> <span style="font-size:12px;color:var(--muted);font-weight:500;">· Email Downloads</span></div>
    <nav class="nav-links">
      <a href="index.php" class="active">Overview</a>
      <a href="resources.php">Resources & Files</a>
      <a href="requests.php">Verification Requests</a>
      <a href="logs.php">Download Logs</a>
    </nav>
    <div class="user-info">
      <span>Admin: <strong><?= Security::e($adminUsername) ?></strong></span>
      <a href="logout.php" class="logout">Logout</a>
    </div>
  </header>

  <main class="container">
    <div class="page-head">
      <h1>Delivery & Verification Dashboard</h1>
      <p class="subtitle">Real-time metrics for digital downloads, verification token states, and automated SMTP delivery.</p>
    </div>

    <?php if ($dbError): ?>
      <div style="background:rgba(255,184,77,.1);border:1px solid rgba(255,184,77,.35);color:#ffdc99;padding:16px 20px;border-radius:14px;margin-bottom:24px;font-size:13px;line-height:1.6;">
        <div style="font-size:14px;font-weight:800;color:#ffb84d;margin-bottom:4px;">⚠️ Live Database Connection Notice</div>
        <div>The database is currently unreachable: <code><?= Security::e($dbError) ?></code></div>
        <div style="margin-top:8px;">
          Showing default metrics. To connect your live Supabase / PostgreSQL database on Vercel:
          Open <strong>Vercel Settings &rarr; Environment Variables</strong> and add your Supabase credentials:
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_CONNECTION=pgsql</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_HOST=...</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_PORT=5432</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_DATABASE=postgres</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_USERNAME=postgres</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_PASSWORD=...</code>
        </div>
      </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Requests</div>
        <div class="stat-val"><?= $totalVerifications ?></div>
        <div class="stat-sub">Verification emails initiated</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Verified Users</div>
        <div class="stat-val val-accent"><?= $totalVerified ?></div>
        <div class="stat-sub"><?= $totalVerifications > 0 ? round(($totalVerified / $totalVerifications) * 100, 1) : 0 ?>% conversion rate</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Delivered Emails</div>
        <div class="stat-val val-accent"><?= $totalSentEmails ?></div>
        <div class="stat-sub">Download links sent</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Failed Deliveries</div>
        <div class="stat-val val-danger"><?= $totalFailedEmails ?></div>
        <div class="stat-sub"><?= $totalPendingEmails ?> pending queue jobs</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Files Streamed</div>
        <div class="stat-val val-info"><?= $totalDownloads ?></div>
        <div class="stat-sub">Completed downloads</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Products</div>
        <div class="stat-val"><?= $totalActiveResources ?></div>
        <div class="stat-sub">Protected downloads</div>
      </div>
    </div>

    <!-- Recent Verified Requests -->
    <div class="section-box">
      <div class="section-head">
        <h2>Recent Verified Download Requests</h2>
        <a class="btn-action" href="requests.php">View All Requests &rarr;</a>
      </div>
      <?php if (empty($recentRequests)): ?>
        <p style="color:var(--muted);font-size:14px;padding:15px 0;">No verified requests recorded yet. Test the flow from the LOGANX homepage.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Recipient</th>
              <th>Resource</th>
              <th>Verified Time</th>
              <th>Delivery Status</th>
              <th>Attempts</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentRequests as $req): ?>
              <tr>
                <td>#<?= (int)$req['id'] ?></td>
                <td><strong><?= Security::e($req['email']) ?></strong></td>
                <td><?= Security::e($req['resource_title']) ?></td>
                <td><?= Security::e($req['verified_at']) ?></td>
                <td>
                  <?php if ($req['delivery_status'] === 'sent'): ?>
                    <span class="badge badge-sent">Sent</span>
                  <?php elseif ($req['delivery_status'] === 'failed'): ?>
                    <span class="badge badge-failed">Failed</span>
                  <?php else: ?>
                    <span class="badge badge-pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td><?= (int)$req['delivery_attempts'] ?></td>
                <td>
                  <a class="btn-action" href="requests.php?search=<?= urlencode($req['email']) ?>">Inspect</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Recent Downloads Log -->
    <div class="section-box">
      <div class="section-head">
        <h2>Recent Download Logs</h2>
        <a class="btn-action" href="logs.php">View All Logs &rarr;</a>
      </div>
      <?php if (empty($recentDownloads)): ?>
        <p style="color:var(--muted);font-size:14px;padding:15px 0;">No file download attempts logged yet.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Resource</th>
              <th>Status</th>
              <th>IP Address</th>
              <th>Size</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentDownloads as $log): ?>
              <tr>
                <td><?= Security::e($log['downloaded_at']) ?></td>
                <td><?= Security::e($log['resource_title']) ?></td>
                <td>
                  <?php if ($log['download_status'] === 'success'): ?>
                    <span class="badge badge-success">Success</span>
                  <?php else: ?>
                    <span class="badge badge-failed"><?= Security::e($log['download_status']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= Security::e($log['ip_address'] ?? '—') ?></td>
                <td><?= number_format((int)$log['bytes_sent']) ?> bytes</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
