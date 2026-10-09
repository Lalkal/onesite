<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Database;
use LoganX\Security;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60'); // Cache for 60 seconds

Security::setSecurityHeaders();

function formatBytes(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * (int)$pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

try {
    $pdo = Database::getConnection();

    $slug = trim((string)($_GET['slug'] ?? ''));

    if ($slug !== '') {
        $stmt = $pdo->prepare("SELECT id, slug, title, description, version, file_size_bytes, original_filename, is_active, updated_at FROM download_resources WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $res = $stmt->fetch();

        if (!$res) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Resource not found or inactive.']);
            exit;
        }

        $res['file_size_formatted'] = formatBytes((int)$res['file_size_bytes']);
        $res['is_primary'] = ($res['slug'] === 'windows-app');
        echo json_encode(['success' => true, 'resource' => $res]);
        exit;
    }

    $stmt = $pdo->query("SELECT id, slug, title, description, version, file_size_bytes, original_filename, is_active, updated_at FROM download_resources WHERE is_active = 1 ORDER BY id ASC");
    $items = $stmt->fetchAll();

    $resources = [];
    foreach ($items as $item) {
        $item['file_size_formatted'] = formatBytes((int)$item['file_size_bytes']);
        $item['is_primary'] = ($item['slug'] === 'windows-app');
        $resources[] = $item;
    }

    echo json_encode([
        'success'   => true,
        'count'     => count($resources),
        'resources' => $resources
    ], JSON_UNESCAPED_SLASHES);
    exit;

} catch (\Throwable $e) {
    Security::log('error', "Failed to fetch resources API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve download resources.'
    ]);
    exit;
}
