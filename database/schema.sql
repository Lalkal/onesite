-- LOGANX Automated Email Verification & Download Delivery System
-- Database Schema (MySQL 8+ / MariaDB 10.4+)

CREATE TABLE IF NOT EXISTS `download_resources` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `stored_file_path` VARCHAR(500) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
  `file_size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `version` VARCHAR(50) NOT NULL DEFAULT '1.0.0',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `download_limit` INT UNSIGNED NOT NULL DEFAULT 5,
  `token_expiry_hours` INT UNSIGNED NOT NULL DEFAULT 24,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_resources_slug` (`slug`),
  INDEX `idx_resources_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `resource_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_evt_email` (`email`),
  INDEX `idx_evt_token_hash` (`token_hash`),
  INDEX `idx_evt_expires_at` (`expires_at`),
  INDEX `idx_evt_resource` (`resource_id`),
  CONSTRAINT `fk_evt_resource` FOREIGN KEY (`resource_id`) REFERENCES `download_resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `verified_download_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `resource_id` INT UNSIGNED NOT NULL,
  `verification_token_id` INT UNSIGNED NOT NULL,
  `verification_status` ENUM('verified', 'revoked') NOT NULL DEFAULT 'verified',
  `delivery_status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `delivery_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_delivery_attempt_at` DATETIME NULL,
  `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vdr_email` (`email`),
  INDEX `idx_vdr_resource` (`resource_id`),
  INDEX `idx_vdr_delivery` (`delivery_status`),
  CONSTRAINT `fk_vdr_resource` FOREIGN KEY (`resource_id`) REFERENCES `download_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vdr_token` FOREIGN KEY (`verification_token_id`) REFERENCES `email_verification_tokens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `download_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `verified_download_request_id` INT UNSIGNED NOT NULL,
  `resource_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `max_downloads` INT UNSIGNED NOT NULL DEFAULT 5,
  `successful_download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_downloaded_at` DATETIME NULL,
  INDEX `idx_dt_token_hash` (`token_hash`),
  INDEX `idx_dt_expires_at` (`expires_at`),
  INDEX `idx_dt_resource` (`resource_id`),
  CONSTRAINT `fk_dt_request` FOREIGN KEY (`verified_download_request_id`) REFERENCES `verified_download_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dt_resource` FOREIGN KEY (`resource_id`) REFERENCES `download_resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_delivery_queue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `verified_download_request_id` INT UNSIGNED NOT NULL,
  `email_type` VARCHAR(50) NOT NULL DEFAULT 'download_link',
  `recipient_email` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `next_attempt_at` DATETIME NOT NULL,
  `last_error_code` VARCHAR(50) NULL,
  `last_error_message` TEXT NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_edq_status_next` (`status`, `next_attempt_at`),
  INDEX `idx_edq_recipient` (`recipient_email`),
  CONSTRAINT `fk_edq_vdr` FOREIGN KEY (`verified_download_request_id`) REFERENCES `verified_download_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `download_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `resource_id` INT UNSIGNED NOT NULL,
  `verified_download_request_id` INT UNSIGNED NULL,
  `download_token_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `download_status` VARCHAR(50) NOT NULL,
  `bytes_sent` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_dl_resource` (`resource_id`),
  INDEX `idx_dl_status` (`download_status`),
  INDEX `idx_dl_date` (`downloaded_at`),
  CONSTRAINT `fk_dl_resource` FOREIGN KEY (`resource_id`) REFERENCES `download_resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(255) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `hit_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_hit_at` DATETIME NOT NULL,
  `window_start_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_rate_ident_action` (`identifier`, `action`),
  INDEX `idx_rate_last_hit` (`last_hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
