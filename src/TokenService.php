<?php
declare(strict_types=1);

namespace LoganX;

use PDO;
use RuntimeException;

class TokenService
{
    /**
     * Generate at least 32 bytes of secure random token data (64 hex characters)
     */
    public static function generateRawToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Compute SHA-256 hash of token
     */
    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', trim($rawToken));
    }

    /**
     * Create and store an email verification token
     * Returns the RAW token to be included in the email link (never stored in DB).
     */
    public static function createVerificationToken(string $email, int $resourceId, ?string $ip = null, ?string $userAgent = null): string
    {
        $pdo = Database::getConnection();
        $rawToken = self::generateRawToken();
        $tokenHash = self::hashToken($rawToken);

        $ttlMinutes = (int)Config::get('VERIFICATION_TOKEN_TTL_MINUTES', 30);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $stmt = $pdo->prepare(
            "INSERT INTO email_verification_tokens 
             (email, resource_id, token_hash, ip_address, user_agent, expires_at, created_at) 
             VALUES (:email, :resource_id, :token_hash, :ip_address, :user_agent, :expires_at, NOW())"
        );

        $stmt->execute([
            ':email'       => strtolower(trim($email)),
            ':resource_id' => $resourceId,
            ':token_hash'  => $tokenHash,
            ':ip_address'  => $ip,
            ':user_agent'  => $userAgent ? substr($userAgent, 0, 500) : null,
            ':expires_at'  => $expiresAt
        ]);

        return $rawToken;
    }

    /**
     * Validate verification token WITHOUT consuming it (e.g. for GET inspection page)
     */
    public static function validateVerificationToken(string $rawToken): ?array
    {
        $pdo = Database::getConnection();
        $tokenHash = self::hashToken($rawToken);

        $stmt = $pdo->prepare(
            "SELECT evt.*, r.title AS resource_title, r.is_active AS resource_is_active 
             FROM email_verification_tokens evt
             JOIN download_resources r ON r.id = evt.resource_id
             WHERE evt.token_hash = :hash
             LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null; // Invalid token
        }

        // Check if revoked
        if (!empty($row['revoked_at'])) {
            return ['status' => 'revoked', 'record' => $row];
        }

        // Check if already used
        if (!empty($row['used_at'])) {
            return ['status' => 'used', 'record' => $row];
        }

        // Check if expired
        if (strtotime($row['expires_at']) <= time()) {
            return ['status' => 'expired', 'record' => $row];
        }

        // Check if resource is active
        if ((int)$row['resource_is_active'] !== 1) {
            return ['status' => 'inactive_resource', 'record' => $row];
        }

        return ['status' => 'valid', 'record' => $row];
    }

    /**
     * Atomically consume verification token and create verified download request
     * Prevents race conditions and duplicate consumption via row check.
     */
    public static function consumeVerificationToken(string $rawToken): array
    {
        return Database::transaction(function (PDO $pdo) use ($rawToken) {
            $tokenHash = self::hashToken($rawToken);

            // Re-fetch token row with FOR UPDATE locking
            $stmt = $pdo->prepare(
                "SELECT evt.*, r.title AS resource_title, r.download_limit, r.token_expiry_hours, r.is_active AS resource_is_active
                 FROM email_verification_tokens evt
                 JOIN download_resources r ON r.id = evt.resource_id
                 WHERE evt.token_hash = :hash
                 FOR UPDATE"
            );
            $stmt->execute([':hash' => $tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['success' => false, 'error' => 'invalid', 'message' => 'Invalid or unrecognized verification token.'];
            }

            if (!empty($row['revoked_at'])) {
                return ['success' => false, 'error' => 'revoked', 'message' => 'This verification token has been revoked.'];
            }

            if (!empty($row['used_at'])) {
                return ['success' => false, 'error' => 'used', 'message' => 'This verification token has already been confirmed.'];
            }

            if (strtotime($row['expires_at']) <= time()) {
                return ['success' => false, 'error' => 'expired', 'message' => 'This verification token has expired.'];
            }

            if ((int)$row['resource_is_active'] !== 1) {
                return ['success' => false, 'error' => 'inactive_resource', 'message' => 'The requested download resource is no longer active.'];
            }

            // Atomically mark token as used
            $updateStmt = $pdo->prepare(
                "UPDATE email_verification_tokens 
                 SET used_at = NOW() 
                 WHERE id = :id AND used_at IS NULL"
            );
            $updateStmt->execute([':id' => $row['id']]);

            if ($updateStmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'concurrent_use', 'message' => 'Verification token was just consumed by another process.'];
            }

            // Check if there is already a verified download request for this exact verification token
            $checkReq = $pdo->prepare("SELECT id FROM verified_download_requests WHERE verification_token_id = :tid LIMIT 1");
            $checkReq->execute([':tid' => $row['id']]);
            $existingReq = $checkReq->fetch();

            if ($existingReq) {
                $requestId = (int)$existingReq['id'];
            } else {
                // Create verified_download_requests record
                $insReq = $pdo->prepare(
                    "INSERT INTO verified_download_requests 
                     (email, resource_id, verification_token_id, verification_status, delivery_status, verified_at, created_at)
                     VALUES (:email, :resource_id, :token_id, 'verified', 'pending', NOW(), NOW())"
                );
                $insReq->execute([
                    ':email'       => $row['email'],
                    ':resource_id' => $row['resource_id'],
                    ':token_id'    => $row['id']
                ]);
                $requestId = (int)$pdo->lastInsertId();
            }

            // Generate unique secure download token
            $rawDownloadToken = self::generateRawToken();
            $downloadTokenHash = self::hashToken($rawDownloadToken);

            $expiryHours = (int)($row['token_expiry_hours'] ?? Config::get('DOWNLOAD_TOKEN_TTL_HOURS', 24));
            $downloadExpiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
            $maxDownloads = (int)($row['download_limit'] ?? Config::get('DEFAULT_DOWNLOAD_LIMIT', 5));

            $insToken = $pdo->prepare(
                "INSERT INTO download_tokens 
                 (verified_download_request_id, resource_id, token_hash, expires_at, max_downloads, successful_download_count, created_at)
                 VALUES (:req_id, :resource_id, :token_hash, :expires_at, :max_downloads, 0, NOW())"
            );
            $insToken->execute([
                ':req_id'       => $requestId,
                ':resource_id'  => $row['resource_id'],
                ':token_hash'   => $downloadTokenHash,
                ':expires_at'   => $downloadExpiresAt,
                ':max_downloads'=> $maxDownloads
            ]);
            $downloadTokenId = (int)$pdo->lastInsertId();

            return [
                'success'           => true,
                'request_id'        => $requestId,
                'download_token_id' => $downloadTokenId,
                'raw_download_token'=> $rawDownloadToken,
                'email'             => $row['email'],
                'resource_id'       => (int)$row['resource_id'],
                'resource_title'    => $row['resource_title'],
                'expires_at'        => $downloadExpiresAt,
                'max_downloads'     => $maxDownloads
            ];
        });
    }

    /**
     * Validate download token for file delivery
     */
    public static function validateDownloadToken(string $rawToken): array
    {
        $pdo = Database::getConnection();
        $tokenHash = self::hashToken($rawToken);

        $stmt = $pdo->prepare(
            "SELECT dt.*, r.title AS resource_title, r.stored_file_path, r.original_filename, 
                    r.mime_type, r.file_size_bytes, r.is_active AS resource_is_active,
                    vdr.email AS recipient_email
             FROM download_tokens dt
             JOIN download_resources r ON r.id = dt.resource_id
             JOIN verified_download_requests vdr ON vdr.id = dt.verified_download_request_id
             WHERE dt.token_hash = :hash
             LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['status' => 'not_found', 'message' => 'Download link is invalid or does not exist.'];
        }

        if (!empty($row['revoked_at'])) {
            return ['status' => 'revoked', 'record' => $row, 'message' => 'This download link has been revoked by an administrator.'];
        }

        if (strtotime($row['expires_at']) <= time()) {
            return ['status' => 'expired', 'record' => $row, 'message' => 'This download link has expired.'];
        }

        if ((int)$row['successful_download_count'] >= (int)$row['max_downloads']) {
            return ['status' => 'limit_reached', 'record' => $row, 'message' => 'Download limit reached for this security token.'];
        }

        if ((int)$row['resource_is_active'] !== 1) {
            return ['status' => 'resource_inactive', 'record' => $row, 'message' => 'This resource is temporarily unavailable.'];
        }

        return ['status' => 'valid', 'record' => $row];
    }
}
