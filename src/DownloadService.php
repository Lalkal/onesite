<?php
declare(strict_types=1);

namespace LoganX;

use PDO;
use RuntimeException;

class DownloadService
{
    /**
     * Process an authorized download request and stream file content
     * If validation fails, returns array with status and error message
     */
    public static function serveDownload(string $rawToken, ?string $ip = null, ?string $userAgent = null, bool $stream = true): array
    {
        $pdo = Database::getConnection();

        // Validate token record
        $validation = TokenService::validateDownloadToken($rawToken);
        if ($validation['status'] !== 'valid') {
            $record = $validation['record'] ?? null;
            self::logAttempt(
                $record['resource_id'] ?? null,
                $record['verified_download_request_id'] ?? null,
                $record['id'] ?? null,
                $ip,
                $userAgent,
                'failed_' . $validation['status']
            );
            return $validation;
        }

        $record = $validation['record'];
        $storageDir = Config::storagePath();
        $storedRelOrAbs = $record['stored_file_path'];

        // Resolve absolute file path
        if (str_starts_with($storedRelOrAbs, '/') || (strlen($storedRelOrAbs) > 2 && $storedRelOrAbs[1] === ':')) {
            $targetPath = $storedRelOrAbs;
        } else {
            $targetPath = $storageDir . '/' . ltrim($storedRelOrAbs, '\\/');
        }

        // Verify path safety and prevent path traversal
        if (!Security::isPathSafe($storageDir, $targetPath) || !file_exists($targetPath)) {
            self::logAttempt(
                (int)$record['resource_id'],
                (int)$record['verified_download_request_id'],
                (int)$record['id'],
                $ip,
                $userAgent,
                'failed_missing_file'
            );
            return [
                'status'  => 'missing_file',
                'record'  => $record,
                'message' => 'The requested file could not be located on the server. Please contact support.'
            ];
        }

        // Concurrency-safe atomic increment of download count
        $updated = false;
        Database::transaction(function (PDO $pdo) use ($record, &$updated) {
            $stmt = $pdo->prepare(
                "UPDATE download_tokens 
                 SET successful_download_count = successful_download_count + 1, 
                     last_downloaded_at = NOW() 
                 WHERE id = :id 
                   AND successful_download_count < max_downloads"
            );
            $stmt->execute([':id' => $record['id']]);
            $updated = ($stmt->rowCount() > 0);
        });

        if (!$updated) {
            self::logAttempt(
                (int)$record['resource_id'],
                (int)$record['verified_download_request_id'],
                (int)$record['id'],
                $ip,
                $userAgent,
                'failed_limit_reached'
            );
            return [
                'status'  => 'limit_reached',
                'record'  => $record,
                'message' => 'The download limit for this link has just been reached.'
            ];
        }

        $fileSize = filesize($targetPath) ?: 0;
        $originalFilename = Security::sanitizeFilename($record['original_filename'] ?: basename($targetPath));
        $mimeType = $record['mime_type'] ?: 'application/octet-stream';

        // Log successful download
        self::logAttempt(
            (int)$record['resource_id'],
            (int)$record['verified_download_request_id'],
            (int)$record['id'],
            $ip,
            $userAgent,
            'success',
            $fileSize
        );

        if ($stream) {
            self::streamFile($targetPath, $originalFilename, $mimeType, $fileSize);
        }

        return [
            'status'            => 'success',
            'file_path'         => $targetPath,
            'original_filename' => $originalFilename,
            'mime_type'         => $mimeType,
            'file_size'         => $fileSize,
            'record'            => $record
        ];
    }

    /**
     * Stream file to client with security headers and chunking
     */
    public static function streamFile(string $filePath, string $filename, string $mimeType, int $fileSize): void
    {
        // Clean any active output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        Security::setSecurityHeaders();

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        if ($fileSize > 0) {
            header('Content-Length: ' . $fileSize);
        }

        // Stream file in 8KB chunks to avoid memory bottlenecks
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            echo "Failed to open protected resource file.";
            exit;
        }

        while (!feof($handle)) {
            $buffer = fread($handle, 8192);
            echo $buffer;
            flush();
        }
        fclose($handle);
        exit;
    }

    /**
     * Record download attempt in download_logs
     */
    private static function logAttempt(
        ?int $resourceId,
        ?int $requestId,
        ?int $tokenId,
        ?string $ip,
        ?string $userAgent,
        string $status,
        int $bytesSent = 0
    ): void {
        try {
            if ($resourceId === null) {
                return;
            }
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO download_logs 
                 (resource_id, verified_download_request_id, download_token_id, ip_address, user_agent, download_status, bytes_sent, downloaded_at)
                 VALUES (:res_id, :req_id, :tok_id, :ip, :ua, :status, :bytes, NOW())"
            );
            $stmt->execute([
                ':res_id'  => $resourceId,
                ':req_id'  => $requestId,
                ':tok_id'  => $tokenId,
                ':ip'      => $ip,
                ':ua'      => $userAgent ? substr($userAgent, 0, 500) : null,
                ':status'  => $status,
                ':bytes'   => $bytesSent
            ]);
        } catch (\Throwable $e) {
            Security::log('error', "Failed to write download log: " . $e->getMessage());
        }
    }
}
