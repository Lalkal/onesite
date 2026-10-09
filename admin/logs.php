<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

use LoganX\Database;
use LoganX\Security;

$pdo = Database::getConnection();

$filterStatus = !empty($_GET['status']) ? trim($_GET['status']) : null;
$filterResource = !empty($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;

$sql = "SELECT dl.*, r.title AS resource_title, vdr.email AS recipient_email
        FROM download_logs dl
        JOIN download_resources r ON r.id = dl.resource_id
        LEFT JOIN verified_download_requests vdr ON vdr.id = dl.verified_download_request_id
        WHERE 1=1";
$params = [];

if ($filterStatus && $filterStatus !== 'all') {
    $sql .= " AND dl.download_status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterResource) {
    $sql .= " AND dl.resource_id = :res_id";
    $params[':res_id'] = $filterResource;
}

$sql .= " ORDER BY dl.downloaded_at DESC LIMIT 150";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$resources = $pdo->query("SELECT id, title FROM download_resources ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Download Audit Logs — LOGANX Admin</title>
  <style>
    :root {
      --bg: #090c13;
      --panel: #111520;
      --text: #f6f7fb;
      --muted: #959fad;
      --line: rgba(255,255,255,.08);
      --accent: #8cff5b;
      --danger: #ff5876;
      --warning: #ffb84d;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
    .header { background: rgba(17,21,32,.9); backdrop-filter: blur(14px); border-bottom: 1px solid var(--line); padding: 16px 28px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
    .logo { font-size: 20px; font-weight: 900; letter-spacing: -1px; }
    .logo span { color: var(--accent); }
    .nav-links { display: flex; align-items: center; gap: 20px; }
    .nav-links a { color: var(--muted); text-decoration: none; font-size: 14px; font-weight: 600; }
    .nav-links a:hover, .nav-links a.active { color: #fff; }
    .container { max-width: 1280px; margin: 32px auto; padding: 0 24px; }
    .page-head { margin-bottom: 24px; }
    h1 { font-size: 26px; font-weight: 800; letter-spacing: -1px; }
    .filter-bar { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .filter-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-group label { font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--muted); letter-spacing: .5px; }
    input, select { background: #0c0f17; border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; color: #fff; font: inherit; font-size: 13px; outline: none; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; border: 0; cursor: pointer; text-decoration: none; }
    .btn-primary { background: var(--accent); color: #071006; }
    .btn-secondary { background: #1c2230; color: #fff; border: 1px solid var(--line); }
    .section-box { background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; padding: 12px 14px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid var(--line); }
    td { padding: 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #cbd2df; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .badge-success { background: rgba(140,255,91,.12); color: var(--accent); }
    .badge-danger { background: rgba(255,88,118,.12); color: var(--danger); }
  </style>
</head>
<body>

  <header class="header">
    <div class="logo">LOGAN<span>X</span> <span style="font-size:12px;color:var(--muted);font-weight:500;">· Admin</span></div>
    <nav class="nav-links">
      <a href="index.php">Overview</a>
      <a href="resources.php">Resources & Files</a>
      <a href="requests.php">Verification Requests</a>
      <a href="logs.php" class="active">Download Logs</a>
    </nav>
    <div><a href="logout.php" style="color:var(--danger);text-decoration:none;font-size:13px;font-weight:700;">Logout</a></div>
  </header>

  <main class="container">
    <div class="page-head">
      <h1>Download Audit Logs</h1>
      <p style="color:var(--muted);font-size:14px;margin-top:4px;">Security and delivery logs for all file stream attempts, IP tracking, and token enforcement.</p>
    </div>

    <form method="GET" action="logs.php" class="filter-bar">
      <div class="filter-group">
        <label>Product</label>
        <select name="resource_id">
          <option value="">All Products</option>
          <?php foreach ($resources as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $filterResource === (int)$r['id'] ? 'selected' : '' ?>>
              <?= Security::e($r['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select name="status">
          <option value="all">All Statuses</option>
          <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
          <option value="failed_expired" <?= $filterStatus === 'failed_expired' ? 'selected' : '' ?>>Expired Token</option>
          <option value="failed_limit_reached" <?= $filterStatus === 'failed_limit_reached' ? 'selected' : '' ?>>Limit Reached</option>
          <option value="failed_revoked" <?= $filterStatus === 'failed_revoked' ? 'selected' : '' ?>>Revoked Link</option>
          <option value="failed_not_found" <?= $filterStatus === 'failed_not_found' ? 'selected' : '' ?>>Invalid Token</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="height:39px;">Filter Logs</button>
      <a href="logs.php" class="btn btn-secondary" style="height:39px;">Reset</a>
    </form>

    <div class="section-box">
      <?php if (empty($logs)): ?>
        <p style="color:var(--muted);padding:20px 0;text-align:center;">No download logs found.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Product</th>
              <th>Associated Email</th>
              <th>Status</th>
              <th>IP Address</th>
              <th>Bytes Streamed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= Security::e($l['downloaded_at']) ?></td>
                <td><?= Security::e($l['resource_title']) ?></td>
                <td><?= Security::e($l['recipient_email'] ?? '—') ?></td>
                <td>
                  <?php if ($l['download_status'] === 'success'): ?>
                    <span class="badge badge-success">Success</span>
                  <?php else: ?>
                    <span class="badge badge-danger"><?= Security::e($l['download_status']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= Security::e($l['ip_address'] ?? '—') ?></td>
                <td><?= number_format((int)$l['bytes_sent']) ?> B</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
