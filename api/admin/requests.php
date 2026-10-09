<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

use LoganX\Database;
use LoganX\Security;
use LoganX\EmailQueueService;

$pdo = null;
$dbError = null;
try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
    Security::log('warning', "Database connection unavailable in admin requests: " . $dbError);
}
$message = null;
$error = null;

// Handle Actions (Retry delivery, Revoke download links)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrfToken($csrf)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'retry_delivery') {
            $queueId = (int)($_POST['queue_id'] ?? 0);
            if ($queueId > 0 && EmailQueueService::retryQueueItem($queueId)) {
                $message = "Email delivery job #{$queueId} was reset to pending for retry.";
            } else {
                $error = "Failed to retry email delivery job #{$queueId}.";
            }
        } elseif ($action === 'revoke_link') {
            $reqId = (int)($_POST['request_id'] ?? 0);
            if ($reqId > 0) {
                // Revoke all download tokens for this verified request
                $stmt = $pdo->prepare("UPDATE download_tokens SET revoked_at = NOW() WHERE verified_download_request_id = :rid");
                $stmt->execute([':rid' => $reqId]);
                $count = $stmt->rowCount();
                $message = "Revoked {$count} active download link(s) for request #{$reqId}.";
            }
        }
    }
}

// Filtering params
$filterResource = !empty($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;
$filterStatus   = !empty($_GET['status']) ? trim($_GET['status']) : null;
$filterSearch   = !empty($_GET['search']) ? trim($_GET['search']) : null;
$filterDateFrom = !empty($_GET['date_from']) ? trim($_GET['date_from']) : null;
$filterDateTo   = !empty($_GET['date_to']) ? trim($_GET['date_to']) : null;

// Build query
$sql = "SELECT vdr.*, r.title AS resource_title, 
               edq.id AS queue_id, edq.status AS queue_status, edq.attempts AS queue_attempts, 
               edq.next_attempt_at, edq.last_error_message,
               COUNT(dt.id) AS token_count,
               SUM(dt.successful_download_count) AS total_downloads,
               MAX(dt.revoked_at) AS latest_revoked_at
        FROM verified_download_requests vdr
        JOIN download_resources r ON r.id = vdr.resource_id
        LEFT JOIN email_delivery_queue edq ON edq.verified_download_request_id = vdr.id
        LEFT JOIN download_tokens dt ON dt.verified_download_request_id = vdr.id
        WHERE 1=1";
$params = [];

if ($filterResource) {
    $sql .= " AND vdr.resource_id = :res_id";
    $params[':res_id'] = $filterResource;
}
if ($filterStatus && $filterStatus !== 'all') {
    $sql .= " AND vdr.delivery_status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterSearch) {
    $sql .= " AND vdr.email LIKE :search";
    $params[':search'] = '%' . $filterSearch . '%';
}
if ($filterDateFrom) {
    $sql .= " AND vdr.verified_at >= :date_from";
    $params[':date_from'] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo) {
    $sql .= " AND vdr.verified_at <= :date_to";
    $params[':date_to'] = $filterDateTo . ' 23:59:59';
}

$sql .= " GROUP BY vdr.id ORDER BY vdr.verified_at DESC LIMIT 100";

$requests = [];
$resources = [];
if ($pdo !== null) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();
        $resources = $pdo->query("SELECT id, title FROM download_resources ORDER BY title ASC")->fetchAll();
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
  <title>Verification & Delivery Requests — LOGANX Admin</title>
  <style>
    :root {
      --bg: #090c13;
      --panel: #111520;
      --panel2: #161b29;
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
    .alert-success { background: rgba(140,255,91,.12); border: 1px solid rgba(140,255,91,.3); color: var(--accent); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: rgba(255,88,118,.12); border: 1px solid rgba(255,88,118,.3); color: #ff859d; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    
    .filter-bar { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .filter-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-group label { font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--muted); letter-spacing: .5px; }
    input, select { background: #0c0f17; border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; color: #fff; font: inherit; font-size: 13px; outline: none; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; border: 0; cursor: pointer; text-decoration: none; }
    .btn-primary { background: var(--accent); color: #071006; }
    .btn-secondary { background: #1c2230; color: #fff; border: 1px solid var(--line); }
    .btn-danger { background: rgba(255,88,118,.15); color: #ff859d; border: 1px solid rgba(255,88,118,.3); }

    .section-box { background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; padding: 12px 14px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid var(--line); }
    td { padding: 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #cbd2df; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .badge-sent { background: rgba(140,255,91,.12); color: var(--accent); }
    .badge-failed { background: rgba(255,88,118,.12); color: var(--danger); }
    .badge-pending { background: rgba(255,184,77,.12); color: var(--warning); }
  </style>
</head>
<body>

  <header class="header">
    <div class="logo">LOGAN<span>X</span> <span style="font-size:12px;color:var(--muted);font-weight:500;">· Admin</span></div>
    <nav class="nav-links">
      <a href="index.php">Overview</a>
      <a href="resources.php">Resources & Files</a>
      <a href="requests.php" class="active">Verification Requests</a>
      <a href="logs.php">Download Logs</a>
    </nav>
    <div><a href="logout.php" style="color:var(--danger);text-decoration:none;font-size:13px;font-weight:700;">Logout</a></div>
  </header>

  <main class="container">
    <div class="page-head">
      <h1>Verified Requests & Email Deliveries</h1>
      <p style="color:var(--muted);font-size:14px;margin-top:4px;">Filter verification events, monitor email dispatch status, retry transient SMTP failures, and revoke links.</p>
    </div>

    <?php if ($dbError): ?>
      <div style="background:rgba(255,184,77,.1);border:1px solid rgba(255,184,77,.35);color:#ffdc99;padding:16px 20px;border-radius:14px;margin-bottom:24px;font-size:13px;line-height:1.6;">
        <div style="font-size:14px;font-weight:800;color:#ffb84d;margin-bottom:4px;">⚠️ Live Database Connection Notice</div>
        <div>The database is currently unreachable: <code><?= Security::e($dbError) ?></code>. Verification requests and email deliveries will record to database once connected.</div>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert-success"><?= Security::e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-danger"><?= Security::e($error) ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <form method="GET" action="requests.php" class="filter-bar">
      <div class="filter-group">
        <label>Search Email</label>
        <input type="text" name="search" value="<?= Security::e($filterSearch ?? '') ?>" placeholder="user@domain.com">
      </div>
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
        <label>Delivery Status</label>
        <select name="status">
          <option value="all">All Statuses</option>
          <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>Sent</option>
          <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending / Retry</option>
          <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Date From</label>
        <input type="date" name="date_from" value="<?= Security::e($filterDateFrom ?? '') ?>">
      </div>
      <div class="filter-group">
        <label>Date To</label>
        <input type="date" name="date_to" value="<?= Security::e($filterDateTo ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="height:39px;">Apply Filters</button>
      <a href="requests.php" class="btn btn-secondary" style="height:39px;">Reset</a>
    </form>

    <div class="section-box">
      <?php if (empty($requests)): ?>
        <p style="color:var(--muted);padding:20px 0;text-align:center;">No requests match the selected filters.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Recipient</th>
              <th>Product</th>
              <th>Verified At</th>
              <th>Delivery Status</th>
              <th>Downloads</th>
              <th>Revoked?</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $req): ?>
              <tr>
                <td>#<?= (int)$req['id'] ?></td>
                <td>
                  <strong><?= Security::e($req['email']) ?></strong>
                  <?php if (!empty($req['last_error_message'])): ?>
                    <div style="font-size:11px;color:#ff859d;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= Security::e($req['last_error_message']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= Security::e($req['resource_title']) ?></td>
                <td><?= Security::e($req['verified_at']) ?></td>
                <td>
                  <?php if ($req['delivery_status'] === 'sent'): ?>
                    <span class="badge badge-sent">Sent (<?= (int)$req['delivery_attempts'] ?>)</span>
                  <?php elseif ($req['delivery_status'] === 'failed'): ?>
                    <span class="badge badge-failed">Failed (<?= (int)$req['delivery_attempts'] ?>)</span>
                  <?php else: ?>
                    <span class="badge badge-pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td><?= (int)$req['total_downloads'] ?> completed</td>
                <td>
                  <?= !empty($req['latest_revoked_at']) ? '<span style="color:var(--danger);font-weight:700;">Revoked</span>' : '<span style="color:var(--muted);">Active</span>' ?>
                </td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <?php if ($req['delivery_status'] === 'failed' && !empty($req['queue_id'])): ?>
                      <form method="POST" action="requests.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
                        <input type="hidden" name="action" value="retry_delivery">
                        <input type="hidden" name="queue_id" value="<?= (int)$req['queue_id'] ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:5px 8px;font-size:11px;">Retry Email</button>
                      </form>
                    <?php endif; ?>

                    <?php if (empty($req['latest_revoked_at'])): ?>
                      <form method="POST" action="requests.php" style="display:inline;" onsubmit="return confirm('Revoke all download links for this request?');">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
                        <input type="hidden" name="action" value="revoke_link">
                        <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:5px 8px;font-size:11px;">Revoke Links</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
