<?php
declare(strict_types=1);

namespace LoganX;

use PDO;
use RuntimeException;

class EmailQueueService
{
    /**
     * Enqueue a download delivery email job and optionally attempt immediate delivery
     */
    public static function enqueueDownloadEmail(
        int $verifiedRequestId,
        string $recipientEmail,
        string $resourceTitle,
        string $rawDownloadToken,
        int $expiryHours,
        int $maxDownloads,
        bool $attemptImmediate = true
    ): array {
        $pdo = Database::getConnection();

        // Idempotency check: don't enqueue if already sent for this request
        $chk = $pdo->prepare("SELECT id, status FROM email_delivery_queue WHERE verified_download_request_id = :req_id AND status = 'sent' LIMIT 1");
        $chk->execute([':req_id' => $verifiedRequestId]);
        if ($chk->fetch()) {
            return ['success' => true, 'queued' => false, 'already_sent' => true, 'message' => 'Email was already sent for this verified request.'];
        }

        // Insert pending job into queue
        $ins = $pdo->prepare(
            "INSERT INTO email_delivery_queue 
             (verified_download_request_id, email_type, recipient_email, status, attempts, next_attempt_at, created_at)
             VALUES (:req_id, 'download_ready', :email, 'pending', 0, NOW(), NOW())"
        );
        $ins->execute([
            ':req_id' => $verifiedRequestId,
            ':email'  => $recipientEmail
        ]);
        $queueId = (int)$pdo->lastInsertId();

        if ($attemptImmediate) {
            $dispatchResult = self::dispatchJob(
                $queueId,
                $verifiedRequestId,
                $recipientEmail,
                $resourceTitle,
                $rawDownloadToken,
                $expiryHours,
                $maxDownloads
            );
            return $dispatchResult;
        }

        return ['success' => true, 'queued' => true, 'queue_id' => $queueId];
    }

    /**
     * Dispatch an individual queue job
     */
    public static function dispatchJob(
        int $queueId,
        int $verifiedRequestId,
        string $recipientEmail,
        string $resourceTitle,
        string $rawDownloadToken,
        int $expiryHours,
        int $maxDownloads
    ): array {
        $pdo = Database::getConnection();

        // Mark as processing
        $upd = $pdo->prepare("UPDATE email_delivery_queue SET status = 'processing', attempts = attempts + 1, updated_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $queueId]);

        $sendResult = Mailer::sendDownloadEmail(
            $recipientEmail,
            $resourceTitle,
            $rawDownloadToken,
            $expiryHours,
            $maxDownloads
        );

        if ($sendResult['success']) {
            // Update queue item to sent
            $successStmt = $pdo->prepare(
                "UPDATE email_delivery_queue 
                 SET status = 'sent', sent_at = NOW(), last_error_code = NULL, last_error_message = NULL, updated_at = NOW() 
                 WHERE id = :id"
            );
            $successStmt->execute([':id' => $queueId]);

            // Update verified request status
            $vdrStmt = $pdo->prepare(
                "UPDATE verified_download_requests 
                 SET delivery_status = 'sent', delivery_attempts = delivery_attempts + 1, last_delivery_attempt_at = NOW(), updated_at = NOW() 
                 WHERE id = :rid"
            );
            $vdrStmt->execute([':rid' => $verifiedRequestId]);

            return ['success' => true, 'sent' => true, 'queue_id' => $queueId];
        } else {
            // Capture failure with exponential backoff
            $stmtAtt = $pdo->prepare("SELECT attempts FROM email_delivery_queue WHERE id = :id");
            $stmtAtt->execute([':id' => $queueId]);
            $attempts = (int)($stmtAtt->fetchColumn() ?: 1);

            $maxAttempts = 5;
            $newStatus = ($attempts >= $maxAttempts) ? 'failed' : 'pending';

            // Exponential backoff: 2^attempts * 60 seconds (capped at 3600 seconds)
            $backoffSec = min(3600, (int)(pow(2, $attempts) * 60));
            $nextAttemptAt = date('Y-m-d H:i:s', time() + $backoffSec);

            $failStmt = $pdo->prepare(
                "UPDATE email_delivery_queue 
                 SET status = :status, next_attempt_at = :next_attempt, 
                     last_error_code = :err_code, last_error_message = :err_msg, updated_at = NOW() 
                 WHERE id = :id"
            );
            $failStmt->execute([
                ':status'       => $newStatus,
                ':next_attempt' => $nextAttemptAt,
                ':err_code'     => $sendResult['error_code'] ?? 'DELIVERY_ERROR',
                ':err_msg'      => substr($sendResult['message'] ?? 'Unknown delivery failure', 0, 500),
                ':id'           => $queueId
            ]);

            // Update request delivery status
            $vdrFail = $pdo->prepare(
                "UPDATE verified_download_requests 
                 SET delivery_status = 'failed', delivery_attempts = delivery_attempts + 1, last_delivery_attempt_at = NOW(), updated_at = NOW() 
                 WHERE id = :rid"
            );
            $vdrFail->execute([':rid' => $verifiedRequestId]);

            return [
                'success'       => false,
                'sent'          => false,
                'error'         => $sendResult['message'] ?? 'Delivery failed',
                'queue_id'      => $queueId,
                'retry_at'      => $nextAttemptAt,
                'attempts'      => $attempts
            ];
        }
    }

    /**
     * Process all eligible pending jobs in the queue (CLI cron worker)
     */
    public static function processEligibleQueue(int $limit = 25): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT edq.id AS queue_id, edq.verified_download_request_id, edq.recipient_email, edq.attempts,
                    r.title AS resource_title, r.token_expiry_hours, r.download_limit,
                    dt.id AS token_id, dt.token_hash
             FROM email_delivery_queue edq
             JOIN verified_download_requests vdr ON vdr.id = edq.verified_download_request_id
             JOIN download_resources r ON r.id = vdr.resource_id
             LEFT JOIN download_tokens dt ON dt.verified_download_request_id = vdr.id
             WHERE edq.status IN ('pending', 'processing')
               AND edq.next_attempt_at <= NOW()
               AND edq.attempts < 5
             ORDER BY edq.created_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $processed++;

            // If a new download token needs to be generated because raw token isn't stored in DB:
            // Notice: Download tokens store only hash in DB!
            // When retrying via queue, if we don't have the raw token, we generate a fresh valid download token
            // bound to this verified request and resource, ensuring seamless delivery!
            $rawDownloadToken = TokenService::generateRawToken();
            $tokenHash = TokenService::hashToken($rawDownloadToken);

            $expiryHours = (int)($job['token_expiry_hours'] ?? 24);
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
            $maxDownloads = (int)($job['download_limit'] ?? 5);

            // Revoke any previous un-downloaded token for this request and insert fresh token
            $pdo->prepare("UPDATE download_tokens SET revoked_at = NOW() WHERE verified_download_request_id = :req_id AND successful_download_count = 0")
                ->execute([':req_id' => $job['verified_download_request_id']]);

            $ins = $pdo->prepare(
                "INSERT INTO download_tokens (verified_download_request_id, resource_id, token_hash, expires_at, max_downloads, successful_download_count, created_at)
                 VALUES (:req_id, (SELECT resource_id FROM verified_download_requests WHERE id = :req_id2), :hash, :exp, :max_d, 0, NOW())"
            );
            $ins->execute([
                ':req_id'  => $job['verified_download_request_id'],
                ':req_id2' => $job['verified_download_request_id'],
                ':hash'    => $tokenHash,
                ':exp'     => $expiresAt,
                ':max_d'   => $maxDownloads
            ]);

            $res = self::dispatchJob(
                (int)$job['queue_id'],
                (int)$job['verified_download_request_id'],
                $job['recipient_email'],
                $job['resource_title'],
                $rawDownloadToken,
                $expiryHours,
                $maxDownloads
            );

            if ($res['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed'    => $failed
        ];
    }

    /**
     * Admin action: Manually reset a failed queue item for immediate re-delivery
     */
    public static function retryQueueItem(int $queueId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE email_delivery_queue 
             SET status = 'pending', attempts = 0, next_attempt_at = NOW(), updated_at = NOW() 
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $queueId]);
    }
}
