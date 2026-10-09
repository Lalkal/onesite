<?php
declare(strict_types=1);

namespace LoganX;

use RuntimeException;

class SupabaseStorage
{
    public static function isConfigured(): bool
    {
        $url = trim((string)Config::get('SUPABASE_URL', ''));
        $key = trim((string)Config::get('SUPABASE_SERVICE_ROLE_KEY', Config::get('SUPABASE_KEY', '')));
        return $url !== '' && $key !== '';
    }

    public static function getBucket(): string
    {
        return trim((string)Config::get('SUPABASE_BUCKET', 'supabase-coral-bucket'));
    }

    public static function getUrl(): string
    {
        return rtrim((string)Config::get('SUPABASE_URL', ''), '/');
    }

    public static function getKey(): string
    {
        return trim((string)Config::get('SUPABASE_SERVICE_ROLE_KEY', Config::get('SUPABASE_KEY', '')));
    }

    /**
     * Upload a local file to Supabase Storage bucket
     */
    public static function uploadFile(string $localFilePath, string $destinationPath, string $mimeType = 'application/octet-stream'): array
    {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'message' => 'Supabase credentials are not configured in environment variables.'
            ];
        }

        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            return [
                'success' => false,
                'message' => 'Local source file does not exist or cannot be read.'
            ];
        }

        $bucket = self::getBucket();
        $cleanPath = ltrim($destinationPath, '/');
        $endpoint = self::getUrl() . "/storage/v1/object/{$bucket}/{$cleanPath}";

        $fileContent = file_get_contents($localFilePath);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Failed to read file contents for upload.'];
        }

        $headers = [
            'Authorization: Bearer ' . self::getKey(),
            'apikey: ' . self::getKey(),
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fileContent,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Security::log('error', "Supabase upload cURL error: {$curlError}");
            return ['success' => false, 'message' => "Upload connection error: {$curlError}"];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            Security::log('info', "File uploaded to Supabase bucket '{$bucket}': {$cleanPath}");
            return [
                'success'  => true,
                'path'     => $cleanPath,
                'bucket'   => $bucket,
                'full_uri' => "supabase://{$bucket}/{$cleanPath}"
            ];
        }

        $decoded = json_decode((string)$response, true);
        $errMsg = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode} error from Supabase";
        Security::log('error', "Supabase upload failed: {$errMsg} (HTTP {$httpCode})");

        return [
            'success' => false,
            'message' => "Supabase Storage error: {$errMsg}"
        ];
    }

    /**
     * Create a secure, time-limited signed download URL from Supabase Storage
     */
    public static function createSignedUrl(string $path, int $expiresInSeconds = 3600): ?string
    {
        if (!self::isConfigured()) {
            return null;
        }

        $bucket = self::getBucket();
        $cleanPath = self::extractCleanPath($path);
        $endpoint = self::getUrl() . "/storage/v1/object/sign/{$bucket}/{$cleanPath}";

        $headers = [
            'Authorization: Bearer ' . self::getKey(),
            'apikey: ' . self::getKey(),
            'Content-Type: application/json'
        ];

        $payload = json_encode(['expiresIn' => $expiresInSeconds]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode((string)$response, true);
            if (!empty($data['signedURL'])) {
                // If signedURL is relative, prefix with Supabase URL
                $signed = $data['signedURL'];
                if (str_starts_with($signed, '/')) {
                    return self::getUrl() . '/storage/v1' . $signed;
                }
                return $signed;
            }
        }

        return null;
    }

    /**
     * Stream file bytes from Supabase Storage directly to client without exposing storage URL
     */
    public static function streamFile(string $path, string $downloadFilename, string $mimeType = 'application/octet-stream', int $expectedSize = 0): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $bucket = self::getBucket();
        $cleanPath = self::extractCleanPath($path);
        $endpoint = self::getUrl() . "/storage/v1/object/authenticated/{$bucket}/{$cleanPath}";

        $headers = [
            'Authorization: Bearer ' . self::getKey(),
            'apikey: ' . self::getKey()
        ];

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . Security::sanitizeFilename($downloadFilename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        if ($expectedSize > 0) {
            header('Content-Length: ' . $expectedSize);
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return strlen($chunk);
            }
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $success !== false && $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * List files in Supabase Storage bucket
     */
    public static function listFiles(string $prefix = '', int $limit = 50): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        $bucket = self::getBucket();
        $endpoint = self::getUrl() . "/storage/v1/object/list/{$bucket}";

        $headers = [
            'Authorization: Bearer ' . self::getKey(),
            'apikey: ' . self::getKey(),
            'Content-Type: application/json'
        ];

        $payload = json_encode([
            'prefix' => $prefix,
            'limit'  => $limit,
            'offset' => 0,
            'sortBy' => ['column' => 'created_at', 'order' => 'desc']
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode((string)$response, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Delete a file from Supabase Storage
     */
    public static function deleteFile(string $path): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $bucket = self::getBucket();
        $cleanPath = self::extractCleanPath($path);
        $endpoint = self::getUrl() . "/storage/v1/object/{$bucket}";

        $headers = [
            'Authorization: Bearer ' . self::getKey(),
            'apikey: ' . self::getKey(),
            'Content-Type: application/json'
        ];

        $payload = json_encode(['prefixes' => [$cleanPath]]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Normalize path input (handles supabase://bucket/path, bucket/path, or path)
     */
    public static function extractCleanPath(string $rawPath): string
    {
        $path = trim($rawPath);
        if (str_starts_with($path, 'supabase://')) {
            $path = substr($path, 11);
            $parts = explode('/', $path, 2);
            return $parts[1] ?? $parts[0];
        }

        $bucket = self::getBucket();
        if (str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket) + 1);
        }

        return ltrim($path, '/');
    }
}
