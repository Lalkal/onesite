-- LOGANX Automated Email Verification & Download Delivery System
-- Supabase PostgreSQL Schema
-- Run this in the Supabase Dashboard -> SQL Editor

CREATE TABLE IF NOT EXISTS download_resources (
  id BIGSERIAL PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  stored_file_path VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
  file_size_bytes BIGINT NOT NULL DEFAULT 0,
  version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
  is_active SMALLINT NOT NULL DEFAULT 1,
  download_limit INT NOT NULL DEFAULT 5,
  token_expiry_hours INT NOT NULL DEFAULT 24,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_resources_slug ON download_resources (slug);
CREATE INDEX IF NOT EXISTS idx_resources_active ON download_resources (is_active);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  resource_id BIGINT NOT NULL REFERENCES download_resources(id) ON DELETE CASCADE,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  used_at TIMESTAMPTZ NULL,
  revoked_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_evt_email ON email_verification_tokens (email);
CREATE INDEX IF NOT EXISTS idx_evt_token_hash ON email_verification_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_evt_expires_at ON email_verification_tokens (expires_at);

CREATE TABLE IF NOT EXISTS verified_download_requests (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  resource_id BIGINT NOT NULL REFERENCES download_resources(id) ON DELETE CASCADE,
  verification_token_id BIGINT NOT NULL REFERENCES email_verification_tokens(id) ON DELETE CASCADE,
  verification_status VARCHAR(20) NOT NULL DEFAULT 'verified',
  delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  delivery_attempts INT NOT NULL DEFAULT 0,
  last_delivery_attempt_at TIMESTAMPTZ NULL,
  verified_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_vdr_email ON verified_download_requests (email);
CREATE INDEX IF NOT EXISTS idx_vdr_delivery ON verified_download_requests (delivery_status);

CREATE TABLE IF NOT EXISTS download_tokens (
  id BIGSERIAL PRIMARY KEY,
  verified_download_request_id BIGINT NOT NULL REFERENCES verified_download_requests(id) ON DELETE CASCADE,
  resource_id BIGINT NOT NULL REFERENCES download_resources(id) ON DELETE CASCADE,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at TIMESTAMPTZ NOT NULL,
  max_downloads INT NOT NULL DEFAULT 5,
  successful_download_count INT NOT NULL DEFAULT 0,
  revoked_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_downloaded_at TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS idx_dt_token_hash ON download_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_dt_expires_at ON download_tokens (expires_at);

CREATE TABLE IF NOT EXISTS email_delivery_queue (
  id BIGSERIAL PRIMARY KEY,
  verified_download_request_id BIGINT NOT NULL REFERENCES verified_download_requests(id) ON DELETE CASCADE,
  email_type VARCHAR(50) NOT NULL DEFAULT 'download_link',
  recipient_email VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  next_attempt_at TIMESTAMPTZ NOT NULL,
  last_error_code VARCHAR(50) NULL,
  last_error_message TEXT NULL,
  sent_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_edq_status_next ON email_delivery_queue (status, next_attempt_at);

CREATE TABLE IF NOT EXISTS download_logs (
  id BIGSERIAL PRIMARY KEY,
  resource_id BIGINT NOT NULL REFERENCES download_resources(id) ON DELETE CASCADE,
  verified_download_request_id BIGINT NULL REFERENCES verified_download_requests(id) ON DELETE SET NULL,
  download_token_id BIGINT NULL REFERENCES download_tokens(id) ON DELETE SET NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500) NULL,
  download_status VARCHAR(50) NOT NULL,
  failure_reason VARCHAR(255) NULL,
  bytes_served BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGSERIAL PRIMARY KEY,
  identifier VARCHAR(100) NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  last_attempt_at TIMESTAMPTZ NOT NULL,
  attempts_in_window INT NOT NULL DEFAULT 1,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_rate_limit UNIQUE (identifier, action_type)
);

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGSERIAL PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'admin',
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMPTZ NULL
);

-- Seed initial records if not exists
INSERT INTO download_resources (slug, title, description, stored_file_path, original_filename, mime_type, file_size_bytes, version, is_active, download_limit, token_expiry_hours)
VALUES 
  ('windows-app', 'LOGANX Desktop Studio (Windows App)', 'Official desktop client for LOGANX web studio, offline editor, asset manager, and site synchronizer.', 'loganx_windows_studio_v1_2.zip', 'loganx_windows_studio_v1_2.zip', 'application/zip', 805, '1.2.0', 1, 5, 24),
  ('starter-kit', 'LOGANX Business Website Starter Kit', 'Ready-to-deploy multi-page responsive HTML5/CSS3 commercial template with lead capture forms and analytics.', 'loganx_starter_kit.zip', 'loganx_starter_kit.zip', 'application/zip', 387, '2.0.0', 1, 5, 48)
ON CONFLICT (slug) DO NOTHING;

INSERT INTO admin_users (username, password_hash, role)
VALUES ('admin', '$2y$12$K1qg0y0t5YgYy/48aZc2aO7pP.50KzZf6X4tF4qC6F1k0K3A9C8D2', 'admin')
ON CONFLICT (username) DO NOTHING;
