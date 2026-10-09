<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

use LoganX\Database;
use LoganX\Security;
use LoganX\Config;

$pdo = Database::getConnection();
$message = null;
$error = null;

// Handle POST: Create or Update Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrfToken($csrf)) {
        $error = 'Security session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $id          = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $title       = trim((string)($_POST['title'] ?? ''));
            $slug        = trim((string)($_POST['slug'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $limit       = max(1, (int)($_POST['download_limit'] ?? 5));
            $expiry      = max(1, (int)($_POST['token_expiry_hours'] ?? 24));
            $version     = trim((string)($_POST['version'] ?? '1.0.0'));
            $isActive    = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '' || $slug === '') {
                $error = 'Title and Slug are required fields.';
            } else {
                $storageDir = Config::storagePath();
                if (!is_dir($storageDir)) {
                    @mkdir($storageDir, 0755, true);
                }

                $storedPath = null;
                $origFilename = null;
                $mimeType = 'application/octet-stream';
                $fileSize = 0;

                // Handle optional file upload
                if (!empty($_FILES['resource_file']['name']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $upload = $_FILES['resource_file'];
                    $origFilename = Security::sanitizeFilename($upload['name']);
                    $ext = strtolower(pathinfo($origFilename, PATHINFO_EXTENSION));

                    // Allowed file extensions
                    $allowedExts = ['zip', 'exe', 'msi', 'dmg', 'pkg', 'apk', 'tar', 'gz', 'pdf', 'json', 'txt'];
                    if (!in_array($ext, $allowedExts, true)) {
                        $error = "File extension '.{$ext}' is not permitted for digital downloads.";
                    } else {
                        // Generate randomized storage filename to prevent file collisions or predictable URLs
                        $randomFilename = 'res_' . bin2hex(random_bytes(16)) . '.' . $ext;
                        $destination = $storageDir . '/' . $randomFilename;

                        if (move_uploaded_file($upload['tmp_name'], $destination)) {
                            $storedPath = $randomFilename;
                            $fileSize = filesize($destination) ?: 0;
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $destination) ?: 'application/octet-stream';
                            finfo_close($finfo);
                        } else {
                            $error = 'Failed to store uploaded file on the server.';
                        }
                    }
                }

                if (!$error) {
                    if ($action === 'create') {
                        if (!$storedPath) {
                            $error = 'Please upload a product file for new downloads.';
                        } else {
                            try {
                                $stmt = $pdo->prepare(
                                    "INSERT INTO download_resources 
                                     (slug, title, description, stored_file_path, original_filename, mime_type, file_size_bytes, version, is_active, download_limit, token_expiry_hours, created_at)
                                     VALUES (:slug, :title, :desc, :path, :orig, :mime, :size, :ver, :active, :lim, :exp, NOW())"
                                );
                                $stmt->execute([
                                    ':slug'   => $slug,
                                    ':title'  => $title,
                                    ':desc'   => $description,
                                    ':path'   => $storedPath,
                                    ':orig'   => $origFilename,
                                    ':mime'   => $mimeType,
                                    ':size'   => $fileSize,
                                    ':ver'    => $version,
                                    ':active' => $isActive,
                                    ':lim'    => $limit,
                                    ':exp'    => $expiry
                                ]);
                                $message = 'Resource created successfully!';
                            } catch (\PDOException $e) {
                                $error = 'Failed to create resource (slug must be unique): ' . $e->getMessage();
                            }
                        }
                    } elseif ($action === 'update' && $id) {
                        try {
                            if ($storedPath) {
                                $stmt = $pdo->prepare(
                                    "UPDATE download_resources 
                                     SET slug = :slug, title = :title, description = :desc, 
                                         stored_file_path = :path, original_filename = :orig, mime_type = :mime, file_size_bytes = :size,
                                         version = :ver, is_active = :active, download_limit = :lim, token_expiry_hours = :exp 
                                     WHERE id = :id"
                                );
                                $stmt->execute([
                                    ':slug'   => $slug,
                                    ':title'  => $title,
                                    ':desc'   => $description,
                                    ':path'   => $storedPath,
                                    ':orig'   => $origFilename,
                                    ':mime'   => $mimeType,
                                    ':size'   => $fileSize,
                                    ':ver'    => $version,
                                    ':active' => $isActive,
                                    ':lim'    => $limit,
                                    ':exp'    => $expiry,
                                    ':id'     => $id
                                ]);
                            } else {
                                $stmt = $pdo->prepare(
                                    "UPDATE download_resources 
                                     SET slug = :slug, title = :title, description = :desc, 
                                         version = :ver, is_active = :active, download_limit = :lim, token_expiry_hours = :exp 
                                     WHERE id = :id"
                                );
                                $stmt->execute([
                                    ':slug'   => $slug,
                                    ':title'  => $title,
                                    ':desc'   => $description,
                                    ':ver'    => $version,
                                    ':active' => $isActive,
                                    ':lim'    => $limit,
                                    ':exp'    => $expiry,
                                    ':id'     => $id
                                ]);
                            }
                            $message = 'Resource updated successfully!';
                        } catch (\PDOException $e) {
                            $error = 'Failed to update resource: ' . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE download_resources SET is_active = IF(is_active=1, 0, 1) WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Resource status updated!';
        }
    }
}

// Fetch all resources
$resources = $pdo->query("SELECT * FROM download_resources ORDER BY id ASC")->fetchAll();
$csrf = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Resources — LOGANX Admin</title>
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
    .container { max-width: 1240px; margin: 32px auto; padding: 0 24px; }
    .page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    h1 { font-size: 26px; font-weight: 800; letter-spacing: -1px; }
    .alert-success { background: rgba(140,255,91,.12); border: 1px solid rgba(140,255,91,.3); color: var(--accent); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: rgba(255,88,118,.12); border: 1px solid rgba(255,88,118,.3); color: #ff859d; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; border: 0; cursor: pointer; text-decoration: none; }
    .btn-primary { background: var(--accent); color: #071006; }
    .btn-secondary { background: #1b212f; color: #fff; border: 1px solid var(--line); }
    .section-box { background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 24px; margin-bottom: 32px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; padding: 12px 14px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid var(--line); }
    td { padding: 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #cbd2df; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .badge-active { background: rgba(140,255,91,.12); color: var(--accent); }
    .badge-inactive { background: rgba(255,88,118,.12); color: var(--danger); }
    
    /* Modal Form */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { margin-bottom: 16px; }
    .form-group.full { grid-column: span 2; }
    label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
    input, textarea, select { width: 100%; padding: 12px 14px; background: #0c0f17; border: 1px solid var(--line); border-radius: 10px; color: #fff; font: inherit; font-size: 13px; outline: none; }
    input:focus, textarea:focus { border-color: var(--accent); }
  </style>
</head>
<body>

  <header class="header">
    <div class="logo">LOGAN<span>X</span> <span style="font-size:12px;color:var(--muted);font-weight:500;">· Admin</span></div>
    <nav class="nav-links">
      <a href="index.php">Overview</a>
      <a href="resources.php" class="active">Resources & Files</a>
      <a href="requests.php">Verification Requests</a>
      <a href="logs.php">Download Logs</a>
    </nav>
    <div><a href="logout.php" style="color:var(--danger);text-decoration:none;font-size:13px;font-weight:700;">Logout</a></div>
  </header>

  <main class="container">
    <div class="page-head">
      <div>
        <h1>Downloadable Resources & Applications</h1>
        <p style="color:var(--muted);font-size:14px;margin-top:4px;">Manage digital products, upload application binaries, and configure token limits.</p>
      </div>
      <button class="btn btn-primary" onclick="openCreateModal()">+ Add New Downloadable Product</button>
    </div>

    <?php if ($message): ?>
      <div class="alert-success"><?= Security::e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-danger"><?= Security::e($error) ?></div>
    <?php endif; ?>

    <div class="section-box">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title & Slug</th>
            <th>Original Filename</th>
            <th>Size</th>
            <th>Expiry</th>
            <th>Max DL</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resources as $res): ?>
            <tr>
              <td>#<?= (int)$res['id'] ?></td>
              <td>
                <strong><?= Security::e($res['title']) ?></strong>
                <div style="font-size:11px;color:var(--muted);font-family:monospace;"><?= Security::e($res['slug']) ?></div>
              </td>
              <td><?= Security::e($res['original_filename']) ?></td>
              <td><?= number_format((int)$res['file_size_bytes'] / 1024, 1) ?> KB</td>
              <td><?= (int)$res['token_expiry_hours'] ?> hrs</td>
              <td><?= (int)$res['download_limit'] ?> max</td>
              <td>
                <?php if ((int)$res['is_active'] === 1): ?>
                  <span class="badge badge-active">Active</span>
                <?php else: ?>
                  <span class="badge badge-inactive">Disabled</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" action="resources.php" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                  <button type="submit" class="btn btn-secondary" style="padding:6px 10px;font-size:12px;">
                    <?= (int)$res['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Product Creation Form / Modal Box -->
    <div class="section-box" id="productFormBox">
      <h2 style="font-size:18px;margin-bottom:16px;">Add / Upload New Product</h2>
      <form method="POST" action="resources.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
        <input type="hidden" name="action" value="create">

        <div class="form-grid">
          <div class="form-group">
            <label>Product Title *</label>
            <input type="text" name="title" required placeholder="e.g. LOGANX Desktop Studio (Windows App)">
          </div>
          <div class="form-group">
            <label>Slug (URL Identifier) *</label>
            <input type="text" name="slug" required placeholder="e.g. windows-app">
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="Brief product summary..."></textarea>
          </div>
          <div class="form-group">
            <label>Upload File (ZIP / EXE / PDF / APK) *</label>
            <input type="file" name="resource_file" required>
          </div>
          <div class="form-group">
            <label>Version</label>
            <input type="text" name="version" value="1.0.0">
          </div>
          <div class="form-group">
            <label>Download Limit (Allowed Clicks)</label>
            <input type="number" name="download_limit" value="5" min="1">
          </div>
          <div class="form-group">
            <label>Link Expiration (Hours)</label>
            <input type="number" name="token_expiry_hours" value="24" min="1">
          </div>
          <div class="form-group full">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="is_active" value="1" checked style="width:auto;">
              Make this digital product immediately active for downloads
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:12px;">Save & Upload Digital Product &rarr;</button>
      </form>
    </div>
  </main>

  <script>
    function openCreateModal() {
      document.getElementById('productFormBox').scrollIntoView({ behavior: 'smooth' });
    }
  </script>
</body>
</html>
