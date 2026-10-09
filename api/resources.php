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
    Security::log('warning', "Database connection unavailable in resources API, using fallback defaults: " . $e->getMessage());
    echo json_encode([
        'success'     => true,
        'count'       => 2,
        'is_fallback' => true,
        'resources'   => [
            [
                'id'                  => 1,
                'slug'                => 'windows-app',
                'title'               => 'LOGANX Desktop Studio (Windows App)',
                'description'         => 'Official desktop client for LOGANX web studio, offline editor, asset manager, and site synchronizer.',
                'version'             => '1.2.0',
                'file_size_bytes'     => 805,
                'file_size_formatted' => '805 B',
                'original_filename'   => 'loganx_windows_studio_v1_2.zip',
                'is_active'           => 1,
                'is_primary'          => true
            ],
            [
                'id'                  => 2,
                'slug'                => 'starter-kit',
                'title'               => 'LOGANX Business Website Starter Kit',
                'description'         => 'Ready-to-deploy multi-page responsive HTML5/CSS3 commercial template with lead capture forms and analytics.',
                'version'             => '2.0.0',
                'file_size_bytes'     => 387,
                'file_size_formatted' => '387 B',
                'original_filename'   => 'loganx_starter_kit.zip',
                'is_active'           => 1,
                'is_primary'          => false
            ]
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
