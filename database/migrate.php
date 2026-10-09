<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Config;
use LoganX\Database;
use LoganX\Security;

echo "=== LOGANX Automated Email Verification & Download Delivery Migration ===\n";

try {
    // 1. Connect and ensure database exists
    $host = Config::get('DB_HOST', '127.0.0.1');
    $port = Config::get('DB_PORT', '3306');
    $dbname = Config::get('DB_DATABASE', 'loganx_downloads');
    $user = Config::get('DB_USERNAME', 'root');
    $pass = Config::get('DB_PASSWORD', '');

    echo "Connecting to MySQL server at {$host}:{$port}...\n";
    $rootPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `{$dbname}` verified/created.\n";

    // 2. Connect to specific database and run schema.sql
    $pdo = Database::getConnection();
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found at {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    echo "Database tables, foreign keys, and indexes created successfully.\n";

    // 3. Ensure storage directory exists
    $storageDir = Config::storagePath();
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    // 4. Create sample downloadable ZIP archives if they don't exist
    $sampleFile1 = $storageDir . '/loganx_windows_studio_v1_2.zip';
    if (!file_exists($sampleFile1)) {
        $zip1 = new ZipArchive();
        if ($zip1->open($sampleFile1, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip1->addFromString('LOGANX-Studio.exe', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFFLOGANX Desktop Studio Windows Executable Build 1.2.0 (Official Build)\r\n");
            $zip1->addFromString('README.txt', "LOGANX Desktop Studio for Windows\r\nVersion 1.2.0\r\n\r\nThank you for downloading LOGANX Desktop Studio!\r\nFor documentation and guides, visit https://loganx.io\r\n");
            $zip1->addFromString('license.txt', "LOGANX Studio License Agreement\r\nCopyright (C) 2026 LOGANX. All rights reserved.\r\n");
            $zip1->addFromString('config.json', json_encode([
                'appName' => 'LOGANX Desktop Studio',
                'version' => '1.2.0',
                'channel' => 'stable-x64',
                'build'   => 20261009
            ], JSON_PRETTY_PRINT));
            $zip1->close();
            echo "Sample Windows app archive created at: {$sampleFile1}\n";
        }
    }

    $sampleFile2 = $storageDir . '/loganx_starter_kit.zip';
    if (!file_exists($sampleFile2)) {
        $zip2 = new ZipArchive();
        if ($zip2->open($sampleFile2, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip2->addFromString('README.txt', "LOGANX Website Starter Kit Bundle\r\nIncludes templates, CSS tokens, and marketing workflows.\r\n");
            $zip2->addFromString('template-guide.md', "# LOGANX Starter Guide\r\nGet started with responsive web projects.\r\n");
            $zip2->close();
            echo "Sample Starter Kit archive created at: {$sampleFile2}\n";
        }
    }

    // 5. Seed Resources in download_resources
    $resources = [
        [
            'slug'               => 'windows-app',
            'title'              => 'LOGANX Desktop Studio (Windows App)',
            'description'        => 'Official LOGANX Desktop application for Windows x64. Includes business management, local POS, appointment tracking, and automation tools.',
            'stored_file_path'   => 'loganx_windows_studio_v1_2.zip',
            'original_filename'  => 'LOGANX-Studio-v1.2-Windows-x64.zip',
            'mime_type'          => 'application/zip',
            'file_size_bytes'    => file_exists($sampleFile1) ? filesize($sampleFile1) : 1024,
            'version'            => '1.2.0',
            'is_active'          => 1,
            'download_limit'     => 5,
            'token_expiry_hours' => 24
        ],
        [
            'slug'               => 'website-starter-kit',
            'title'              => 'LOGANX Business Website Starter Kit',
            'description'        => 'Complete package of responsive website templates, UI assets, and conversion components for local businesses.',
            'stored_file_path'   => 'loganx_starter_kit.zip',
            'original_filename'  => 'LOGANX-Starter-Kit-Bundle.zip',
            'mime_type'          => 'application/zip',
            'file_size_bytes'    => file_exists($sampleFile2) ? filesize($sampleFile2) : 1024,
            'version'            => '2.0.0',
            'is_active'          => 1,
            'download_limit'     => 5,
            'token_expiry_hours' => 24
        ]
    ];

    foreach ($resources as $res) {
        $stmt = $pdo->prepare("SELECT id FROM download_resources WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $res['slug']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $pdo->prepare(
                "UPDATE download_resources 
                 SET title = :title, description = :description, stored_file_path = :stored_file_path, 
                     original_filename = :original_filename, mime_type = :mime_type, file_size_bytes = :file_size_bytes, 
                     version = :version, is_active = :is_active, download_limit = :download_limit, 
                     token_expiry_hours = :token_expiry_hours 
                 WHERE id = :id"
            );
            $upd->execute(array_merge($res, [':id' => $existing['id']]));
            echo "Updated existing resource: {$res['title']} (ID: {$existing['id']})\n";
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO download_resources 
                 (slug, title, description, stored_file_path, original_filename, mime_type, file_size_bytes, version, is_active, download_limit, token_expiry_hours, created_at)
                 VALUES (:slug, :title, :description, :stored_file_path, :original_filename, :mime_type, :file_size_bytes, :version, :is_active, :download_limit, :token_expiry_hours, NOW())"
            );
            $ins->execute($res);
            $newId = $pdo->lastInsertId();
            echo "Created new resource: {$res['title']} (ID: {$newId})\n";
        }
    }

    // 6. Seed Admin User
    $adminUsername = (string)Config::get('ADMIN_USERNAME', 'admin');
    $adminHash = (string)Config::get('ADMIN_PASSWORD_HASH', '');
    if ($adminHash === '') {
        $adminHash = password_hash('Admin@LoganX2026!', PASSWORD_BCRYPT);
    }

    $chkAdmin = $pdo->prepare("SELECT id FROM admin_users WHERE username = :u LIMIT 1");
    $chkAdmin->execute([':u' => $adminUsername]);
    $existingAdmin = $chkAdmin->fetch();

    if ($existingAdmin) {
        $updAdmin = $pdo->prepare("UPDATE admin_users SET password_hash = :hash WHERE id = :id");
        $updAdmin->execute([':hash' => $adminHash, ':id' => $existingAdmin['id']]);
        echo "Admin user '{$adminUsername}' updated.\n";
    } else {
        $insAdmin = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, created_at) VALUES (:u, :p, 'admin', NOW())");
        $insAdmin->execute([':u' => $adminUsername, ':p' => $adminHash]);
        echo "Admin user '{$adminUsername}' created.\n";
    }

    echo "=== Migration completed successfully! ===\n";

} catch (\Throwable $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}
