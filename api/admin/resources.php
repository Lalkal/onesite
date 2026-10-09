<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

use LoganX\Database;
use LoganX\Security;
use LoganX\Config;
use LoganX\SupabaseStorage;

$pdo = null;
$dbError = null;
try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
    Security::log('warning', "Database connection unavailable in CMS resources: " . $dbError);
}
$message = null;
$error = null;

$isSupabaseReady = SupabaseStorage::isConfigured();
$supabaseBucket = SupabaseStorage::getBucket();

function formatBytes(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * (int)$pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Handle POST actions: Create, Update, Toggle, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrfToken($csrf)) {
        $error = 'Security session expired. Please reload and try again.';
        $action = $_POST['action'] ?? '';

        if ($pdo === null) {
            $error = 'Database is currently unreachable (' . ($dbError ?: 'verify DB credentials in Vercel') . '). Cannot save changes.';
        } elseif ($action === 'create' || $action === 'update') {
            $id          = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $title       = trim((string)($_POST['title'] ?? ''));
            $slug        = trim((string)($_POST['slug'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $limit       = max(1, (int)($_POST['download_limit'] ?? 5));
            $expiry      = max(1, (int)($_POST['token_expiry_hours'] ?? 24));
            $version     = trim((string)($_POST['version'] ?? '1.0.0'));
            $isActive    = isset($_POST['is_active']) ? 1 : 0;
            $customPath  = trim((string)($_POST['custom_storage_path'] ?? ''));

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

                // 1. Process File Upload if provided
                if (!empty($_FILES['resource_file']['name']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $upload = $_FILES['resource_file'];
                    $origFilename = Security::sanitizeFilename($upload['name']);
                    $ext = strtolower(pathinfo($origFilename, PATHINFO_EXTENSION));

                    $allowedExts = ['zip', 'exe', 'msi', 'dmg', 'pkg', 'apk', 'tar', 'gz', 'pdf', 'json', 'txt'];
                    if (!in_array($ext, $allowedExts, true)) {
                        $error = "File extension '.{$ext}' is not permitted for digital downloads.";
                    } else {
                        $randomFilename = 'res_' . bin2hex(random_bytes(12)) . '.' . $ext;
                        $localDestination = $storageDir . '/' . $randomFilename;

                        if (move_uploaded_file($upload['tmp_name'], $localDestination)) {
                            $fileSize = filesize($localDestination) ?: 0;
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $localDestination) ?: 'application/octet-stream';
                            finfo_close($finfo);

                            // Auto-sync to Supabase Storage (supabase-coral-bucket) if configured
                            if ($isSupabaseReady) {
                                $uploadRes = SupabaseStorage::uploadFile($localDestination, "downloads/{$randomFilename}", $mimeType);
                                if ($uploadRes['success']) {
                                    $storedPath = $uploadRes['full_uri'];
                                } else {
                                    // Fallback to local storage path with notice
                                    $storedPath = $randomFilename;
                                    $message = "File stored locally (Supabase notice: {$uploadRes['message']}).";
                                }
                            } else {
                                $storedPath = $randomFilename;
                            }
                        } else {
                            $error = 'Failed to process uploaded file on the server.';
                        }
                    }
                } elseif ($customPath !== '') {
                    // 2. Custom storage path or Supabase Bucket key specified directly
                    $storedPath = $customPath;
                    $origFilename = basename($customPath);
                    $fileSize = (int)($_POST['custom_file_size'] ?? 0);
                }

                if (!$error) {
                    if ($action === 'create') {
                        if (!$storedPath) {
                            $error = 'Please upload a downloadable file or specify a cloud storage path.';
                        } else {
                            try {
                                $stmt = $pdo->prepare(
                                    "INSERT INTO download_resources 
                                     (slug, title, description, stored_file_path, original_filename, mime_type, file_size_bytes, version, is_active, download_limit, token_expiry_hours, created_at, updated_at)
                                     VALUES (:slug, :title, :desc, :path, :orig, :mime, :size, :ver, :active, :lim, :exp, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                                );
                                $stmt->execute([
                                    ':slug'   => $slug,
                                    ':title'  => $title,
                                    ':desc'   => $description,
                                    ':path'   => $storedPath,
                                    ':orig'   => $origFilename ?: $slug,
                                    ':mime'   => $mimeType,
                                    ':size'   => $fileSize,
                                    ':ver'    => $version,
                                    ':active' => $isActive,
                                    ':lim'    => $limit,
                                    ':exp'    => $expiry
                                ]);
                                $message = "Resource '{$title}' added successfully! Website downloads are updated.";
                            } catch (\PDOException $e) {
                                $error = 'Failed to create resource (slug must be unique): ' . $e->getMessage();
                            }
                        }
                    } elseif ($action === 'update' && $id) {
                        try {
                            if ($storedPath) {
                                // Trigger cleanup of replaced file in Supabase & local storage
                                $oldStmt = $pdo->prepare("SELECT stored_file_path FROM download_resources WHERE id = :id LIMIT 1");
                                $oldStmt->execute([':id' => $id]);
                                $oldFile = (string)$oldStmt->fetchColumn();

                                if ($oldFile !== '' && $oldFile !== $storedPath) {
                                    if ($isSupabaseReady && (str_starts_with($oldFile, 'supabase://') || str_starts_with($oldFile, 'downloads/'))) {
                                        SupabaseStorage::deleteFile($oldFile);
                                    }
                                    $localOld = Config::storagePath() . '/' . basename($oldFile);
                                    if (file_exists($localOld) && is_file($localOld)) {
                                        @unlink($localOld);
                                    }
                                }

                                $stmt = $pdo->prepare(
                                    "UPDATE download_resources 
                                     SET slug = :slug, title = :title, description = :desc, 
                                         stored_file_path = :path, original_filename = :orig, mime_type = :mime, file_size_bytes = :size,
                                         version = :ver, is_active = :active, download_limit = :lim, token_expiry_hours = :exp,
                                         updated_at = CURRENT_TIMESTAMP
                                     WHERE id = :id"
                                );
                                $stmt->execute([
                                    ':slug'   => $slug,
                                    ':title'  => $title,
                                    ':desc'   => $description,
                                    ':path'   => $storedPath,
                                    ':orig'   => $origFilename ?: $slug,
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
                                         version = :ver, is_active = :active, download_limit = :lim, token_expiry_hours = :exp,
                                         updated_at = CURRENT_TIMESTAMP
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
                            $message = "Resource '{$title}' updated successfully! File synchronized with {$supabaseBucket}.";
                        } catch (\PDOException $e) {
                            $error = 'Failed to update resource: ' . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE download_resources SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Resource status updated!';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Fetch resource details to trigger file deletion from Supabase Storage
                $fetchStmt = $pdo->prepare("SELECT stored_file_path, title FROM download_resources WHERE id = :id LIMIT 1");
                $fetchStmt->execute([':id' => $id]);
                $targetRes = $fetchStmt->fetch();

                if ($targetRes) {
                    $targetPath = (string)($targetRes['stored_file_path'] ?? '');

                    // 1. Trigger deletion in Supabase Coral Bucket if applicable
                    if ($isSupabaseReady && !empty($targetPath) && (str_starts_with($targetPath, 'supabase://') || str_starts_with($targetPath, 'downloads/'))) {
                        SupabaseStorage::deleteFile($targetPath);
                    }

                    // 2. Trigger deletion of local disk copy if present
                    $localTarget = Config::storagePath() . '/' . basename($targetPath);
                    if (file_exists($localTarget) && is_file($localTarget)) {
                        @unlink($localTarget);
                    }

                    // 3. Delete database record
                    $stmt = $pdo->prepare("DELETE FROM download_resources WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $message = "Resource '{$targetRes['title']}' and its file in {$supabaseBucket} deleted successfully.";
                } else {
                    $error = 'Resource not found.';
                }
            }
        }
    }
}

// Fetch all resources safely
$resources = [];
if ($pdo !== null) {
    try {
        $resources = $pdo->query("SELECT * FROM download_resources ORDER BY id ASC")->fetchAll();
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }
}
if (empty($resources)) {
    $resources = [
        [
            'id'                  => 1,
            'slug'                => 'windows-app',
            'title'               => 'LOGANX Desktop Studio (Windows App)',
            'description'         => 'Official desktop client for LOGANX web studio, offline editor, asset manager, and site synchronizer.',
            'version'             => '1.2.0',
            'file_size_bytes'     => 805,
            'original_filename'   => 'loganx_windows_studio_v1_2.zip',
            'stored_file_path'    => 'downloads/loganx_windows_studio_v1_2.zip',
            'is_active'           => 1,
            'download_limit'      => 5,
            'token_expiry_hours'  => 24,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s')
        ],
        [
            'id'                  => 2,
            'slug'                => 'starter-kit',
            'title'               => 'LOGANX Business Website Starter Kit',
            'description'         => 'Ready-to-deploy multi-page responsive HTML5/CSS3 commercial template with lead capture forms and analytics.',
            'version'             => '2.0.0',
            'file_size_bytes'     => 387,
            'original_filename'   => 'loganx_starter_kit.zip',
            'stored_file_path'    => 'downloads/loganx_starter_kit.zip',
            'is_active'           => 1,
            'download_limit'      => 5,
            'token_expiry_hours'  => 24,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s')
        ]
    ];
}
$csrf = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMS — Manage Digital Downloads — LOGANX Admin</title>
  <style>
    :root {
      --bg: #090c13;
      --panel: #111520;
      --panel2: #161b29;
      --text: #f6f7fb;
      --muted: #959fad;
      --line: rgba(255,255,255,.08);
      --accent: #8cff5b;
      --accent-dim: rgba(140,255,91,.15);
      --cyan: #5ee7ff;
      --danger: #ff5876;
      --warning: #ffb84d;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; min-height: 100vh; }
    
    .header { background: rgba(17,21,32,.9); backdrop-filter: blur(14px); border-bottom: 1px solid var(--line); padding: 16px 28px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
    .logo { font-size: 20px; font-weight: 900; letter-spacing: -1px; text-decoration: none; color: #fff; }
    .logo span { color: var(--accent); }
    .nav-links { display: flex; align-items: center; gap: 20px; }
    .nav-links a { color: var(--muted); text-decoration: none; font-size: 14px; font-weight: 600; transition: color .2s; }
    .nav-links a:hover, .nav-links a.active { color: #fff; }
    
    .container { max-width: 1240px; margin: 32px auto; padding: 0 24px; }
    .page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    h1 { font-size: 26px; font-weight: 800; letter-spacing: -1px; }
    
    /* Storage status banner */
    .storage-banner {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 20px;
      border-radius: 14px;
      margin-bottom: 24px;
      font-size: 13px;
      background: rgba(94,231,255,.06);
      border: 1px solid rgba(94,231,255,.2);
      color: #bcefff;
    }
    .storage-banner.active {
      background: rgba(140,255,91,.08);
      border-color: rgba(140,255,91,.25);
      color: var(--accent);
    }
    .storage-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .5px;
      text-transform: uppercase;
      background: rgba(140,255,91,.15);
      border: 1px solid rgba(140,255,91,.3);
      color: var(--accent);
    }
    
    .alert-success { background: rgba(140,255,91,.12); border: 1px solid rgba(140,255,91,.3); color: var(--accent); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: rgba(255,88,118,.12); border: 1px solid rgba(255,88,118,.3); color: #ff859d; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; border: 0; cursor: pointer; text-decoration: none; transition: all .2s; }
    .btn-primary { background: var(--accent); color: #071006; }
    .btn-primary:hover { opacity: .9; transform: translateY(-1px); }
    .btn-secondary { background: #1b212f; color: #fff; border: 1px solid var(--line); }
    .btn-secondary:hover { border-color: rgba(255,255,255,.2); }
    .btn-danger { background: rgba(255,88,118,.15); color: var(--danger); border: 1px solid rgba(255,88,118,.3); }
    .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
    
    .section-box { background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 24px; margin-bottom: 32px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
    
    /* Table styles */
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; padding: 12px 14px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid var(--line); }
    td { padding: 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #cbd2df; }
    tr:hover td { background: rgba(255,255,255,.015); }
    
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-active { background: rgba(140,255,91,.12); color: var(--accent); border: 1px solid rgba(140,255,91,.25); }
    .status-inactive { background: rgba(255,255,255,.06); color: var(--muted); border: 1px solid var(--line); }
    
    .storage-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; border-radius: 6px; font-family: monospace; background: #181d2a; border: 1px solid var(--line); color: var(--cyan); }
    
    /* Form styles */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
    input[type="text"], input[type="number"], textarea, select {
      background: #0b0e16; border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color .2s;
    }
    input:focus, textarea:focus, select:focus { border-color: var(--accent); }
    .file-dropzone { border: 2px dashed rgba(255,255,255,.15); border-radius: 12px; padding: 20px; text-align: center; background: #0c0f18; cursor: pointer; transition: border-color .2s; }
    .file-dropzone:hover { border-color: var(--accent); }
    
    .sync-note { display: flex; align-items: center; gap: 10px; margin-top: 14px; font-size: 12px; color: var(--muted); }
  </style>
</head>
<body>

  <header class="header">
    <a class="logo" href="/admin/">Logan<span>X</span> CMS</a>
    <nav class="nav-links">
      <a href="/admin/">Dashboard</a>
      <a class="active" href="/admin/resources.php">Digital Downloads (CMS)</a>
      <a href="/admin/requests.php">Requests</a>
      <a href="/admin/logs.php">Logs</a>
      <a href="/" target="_blank">View Live Website ↗</a>
      <a href="/admin/logout.php" style="color:var(--danger);">Sign Out</a>
    </nav>
  </header>

  <main class="container">

    <div class="page-head">
      <div>
        <h1>Digital Downloads & Products CMS</h1>
        <p style="color:var(--muted);font-size:14px;margin-top:4px;">
          Manage digital downloads delivered via automated email verification. Updates here reflect instantly on your live website.
        </p>
      </div>
      <div>
        <a href="#new-product-section" class="btn btn-primary">+ Add New Download</a>
      </div>
    </div>

    <!-- Storage Bucket Status Banner -->
    <div class="storage-banner <?= $isSupabaseReady ? 'active' : '' ?>">
      <div>
        <?php if ($isSupabaseReady): ?>
          <strong>⚡ Supabase Storage Connected:</strong> Files automatically upload and stream from bucket <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;color:#fff;"><?= Security::e($supabaseBucket) ?></code>.
        <?php else: ?>
          <strong>📁 Local Storage Active:</strong> Files are stored on server disk. To use cloud storage, add <code style="color:#fff;">SUPABASE_URL</code> & <code style="color:#fff;">SUPABASE_KEY</code> to your environment variables (bucket: <code style="color:#fff;"><?= Security::e($supabaseBucket) ?></code>).
        <?php endif; ?>
      </div>
      <div>
        <span class="storage-pill">
          <?= $isSupabaseReady ? '✓ Cloud Bucket Active' : '● Local Disk Mode' ?>
        </span>
      </div>
    </div>

    <?php if ($dbError): ?>
      <div style="background:rgba(255,184,77,.1);border:1px solid rgba(255,184,77,.35);color:#ffdc99;padding:16px 20px;border-radius:14px;margin-bottom:24px;font-size:13px;line-height:1.6;">
        <div style="font-size:14px;font-weight:800;color:#ffb84d;margin-bottom:4px;">⚠️ Live Database Connection Notice</div>
        <div>The database is currently unreachable: <code><?= Security::e($dbError) ?></code></div>
        <div style="margin-top:8px;">
          Showing fallback resource catalog. To enable live database synchronization on Vercel:
          Open <strong>Vercel Settings &rarr; Environment Variables</strong> and add:
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_CONNECTION=pgsql</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_HOST=...</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_PORT=5432</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_DATABASE=postgres</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_USERNAME=postgres</code>, 
          <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;">DB_PASSWORD=...</code>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert-success">✓ <?= Security::e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert-danger">✕ <?= Security::e($error) ?></div>
    <?php endif; ?>

    <!-- Active Products Table -->
    <div class="section-box">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h2 style="font-size:18px;font-weight:800;">Catalog Downloads (<?= count($resources) ?>)</h2>
        <a href="/api/resources.php" target="_blank" style="color:var(--cyan);font-size:12px;text-decoration:none;font-weight:600;">
          Inspect Public JSON API ↗
        </a>
      </div>

      <?php if (empty($resources)): ?>
        <p style="color:var(--muted);text-align:center;padding:40px 0;">No downloadable products found. Add your first download below.</p>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Product & Slug</th>
                <th>Storage Path / Bucket</th>
                <th>Version</th>
                <th>File Size</th>
                <th>Download Limit</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($resources as $res): ?>
                <tr>
                  <td>
                    <div style="font-weight:700;color:#fff;font-size:14px;"><?= Security::e($res['title']) ?></div>
                    <div style="color:var(--muted);font-size:12px;margin-top:2px;">
                      Slug: <code style="color:var(--accent);"><?= Security::e($res['slug']) ?></code>
                      <?php if ($res['slug'] === 'windows-app'): ?>
                        <span style="background:rgba(94,231,255,.15);color:var(--cyan);padding:2px 6px;border-radius:4px;font-size:10px;margin-left:4px;font-weight:700;">HERO CTA</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <span class="storage-tag" title="<?= Security::e($res['stored_file_path']) ?>">
                      <?php 
                        $path = $res['stored_file_path'];
                        if (str_starts_with($path, 'supabase://')) {
                          echo '☁️ ' . Security::e(substr($path, 11));
                        } else {
                          echo '💾 ' . Security::e(basename($path));
                        }
                      ?>
                    </span>
                  </td>
                  <td><span style="font-family:monospace;font-size:12px;color:#fff;"><?= Security::e($res['version'] ?: '1.0.0') ?></span></td>
                  <td><?= formatBytes((int)$res['file_size_bytes']) ?></td>
                  <td>
                    <?= (int)$res['download_limit'] ?> max / <?= (int)$res['token_expiry_hours'] ?>h
                  </td>
                  <td>
                    <span class="status-badge <?= $res['is_active'] ? 'status-active' : 'status-inactive' ?>">
                      <?= $res['is_active'] ? '● Live on Site' : '○ Inactive' ?>
                    </span>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Toggle active status on live website">
                          <?= $res['is_active'] ? 'Pause' : 'Activate' ?>
                        </button>
                      </form>

                      <button type="button" class="btn btn-secondary btn-sm" onclick='editResource(<?= json_encode($res, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        Edit
                      </button>

                      <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this resource? It will be removed from the website immediately.');">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete product">
                          Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Add / Edit Digital Download Section -->
    <div class="section-box" id="new-product-section">
      <h2 id="form-title" style="font-size:18px;font-weight:800;margin-bottom:6px;">+ Add New Digital Download</h2>
      <p style="color:var(--muted);font-size:13px;margin-bottom:20px;">
        Upload files directly (stored in <?= $isSupabaseReady ? '<code>supabase-coral-bucket</code>' : 'local protected storage' ?>) or specify cloud storage paths.
      </p>

      <form method="POST" enctype="multipart/form-data" id="resource-form">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrf) ?>">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="id" id="resource-id" value="">

        <div class="form-grid">
          <div class="form-group">
            <label>Product Title *</label>
            <input type="text" name="title" id="res-title" placeholder="e.g. LOGANX Desktop Studio (Windows App)" required oninput="autoSlug(this.value)">
          </div>

          <div class="form-group">
            <label>Product Slug (URL Identifier) *</label>
            <input type="text" name="slug" id="res-slug" placeholder="e.g. windows-app" required>
          </div>

          <div class="form-group">
            <label>Version</label>
            <input type="text" name="version" id="res-version" placeholder="1.2.0" value="1.0.0">
          </div>

          <div class="form-group">
            <label>Max Downloads Allowed per Link</label>
            <input type="number" name="download_limit" id="res-limit" min="1" max="100" value="5">
          </div>

          <div class="form-group">
            <label>Link Expiry Lifespan (Hours)</label>
            <input type="number" name="token_expiry_hours" id="res-expiry" min="1" max="720" value="24">
          </div>

          <div class="form-group">
            <label>Publication Status</label>
            <select name="is_active" id="res-active">
              <option value="1">Active (Visible & Downloadable on Website)</option>
              <option value="0">Inactive (Hidden Draft / Maintenance)</option>
            </select>
          </div>

          <div class="form-group full">
            <label>Product Description & Features (Displayed to Visitors)</label>
            <textarea name="description" id="res-desc" rows="3" placeholder="Explain what visitors will receive after email verification..."></textarea>
          </div>

          <div class="form-group full">
            <label>Product File Upload (.zip, .exe, .msi, .dmg, .pdf, .tar.gz)</label>
            <div class="file-dropzone" onclick="document.getElementById('file-input').click()">
              <input type="file" name="resource_file" id="file-input" style="display:none;" onchange="updateFileName(this)">
              <div id="file-label" style="font-size:14px;color:#cbd2df;">
                📁 Click to choose file or drag and drop here
                <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                  Files automatically upload to <?= $isSupabaseReady ? '<strong>supabase-coral-bucket</strong>' : 'local protected storage' ?>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group full">
            <label>Or Cloud Storage / Supabase Path (Optional)</label>
            <input type="text" name="custom_storage_path" id="res-custom-path" placeholder="e.g. supabase-coral-bucket/releases/app-v1.2.exe or storage/downloads/file.zip">
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">
              Leave blank if uploading a file above. Useful when linking files already present inside your Supabase Storage bucket.
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;align-items:center;margin-top:10px;">
          <button type="submit" class="btn btn-primary" id="submit-btn">
            Save & Publish to Website
          </button>
          <button type="button" class="btn btn-secondary" id="cancel-btn" style="display:none;" onclick="resetForm()">
            Cancel Edit
          </button>
        </div>

        <div class="sync-note">
          <span>🔄</span>
          <span>Saving updates the database immediately. The website's <code>#downloads</code> section and modal triggers will instantly reflect changes.</span>
        </div>
      </form>
    </div>

  </main>

  <script>
    function autoSlug(text) {
      const slugInput = document.getElementById('res-slug');
      if (document.getElementById('form-action').value === 'create') {
        slugInput.value = text.toLowerCase()
          .replace(/[^\w\s-]/g, '')
          .replace(/\s+/g, '-')
          .replace(/-+/g, '-');
      }
    }

    function updateFileName(input) {
      if (input.files && input.files[0]) {
        document.getElementById('file-label').innerHTML = `
          <strong style="color:var(--accent);">✓ Selected:</strong> ${input.files[0].name} 
          <span style="color:var(--muted);">(${(input.files[0].size / 1024 / 1024).toFixed(2)} MB)</span>
        `;
      }
    }

    function editResource(res) {
      document.getElementById('form-title').innerText = '✏️ Edit Digital Download: ' + res.title;
      document.getElementById('form-action').value = 'update';
      document.getElementById('resource-id').value = res.id;
      document.getElementById('res-title').value = res.title;
      document.getElementById('res-slug').value = res.slug;
      document.getElementById('res-version').value = res.version || '1.0.0';
      document.getElementById('res-limit').value = res.download_limit || 5;
      document.getElementById('res-expiry').value = res.token_expiry_hours || 24;
      document.getElementById('res-active').value = res.is_active ? '1' : '0';
      document.getElementById('res-desc').value = res.description || '';
      document.getElementById('res-custom-path').value = res.stored_file_path || '';
      document.getElementById('submit-btn').innerText = 'Update Product Details';
      document.getElementById('cancel-btn').style.display = 'inline-flex';

      document.getElementById('new-product-section').scrollIntoView({ behavior: 'smooth' });
    }

    function resetForm() {
      document.getElementById('resource-form').reset();
      document.getElementById('form-title').innerText = '+ Add New Digital Download';
      document.getElementById('form-action').value = 'create';
      document.getElementById('resource-id').value = '';
      document.getElementById('submit-btn').innerText = 'Save & Publish to Website';
      document.getElementById('cancel-btn').style.display = 'none';
      document.getElementById('file-label').innerHTML = '📁 Click to choose file or drag and drop here';
    }
  </script>

</body>
</html>
