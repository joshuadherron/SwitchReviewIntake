-- NEW PLAN: run only in the NEW staging swform database (u809002269_swform_test).
-- Select that database in phpMyAdmin first; do NOT run in sales_app.
-- Create the database and its database-scoped user in Hostinger separately.
-- No grants or cross-database foreign keys are required here.

CREATE TABLE IF NOT EXISTS switch_review_leads (
  id CHAR(36) PRIMARY KEY,
  contact_name VARCHAR(120) NOT NULL,
  business_name VARCHAR(160) NOT NULL,
  email VARCHAR(200) NOT NULL,
  phone VARCHAR(40) NULL,
  employee_range VARCHAR(16) NOT NULL,
  renewal_window VARCHAR(32) NOT NULL,
  concerns JSON NOT NULL,
  other_concern VARCHAR(300) NULL,
  consent_at DATETIME(3) NOT NULL,
  utm_source VARCHAR(2000) NULL,
  utm_medium VARCHAR(2000) NULL,
  utm_campaign VARCHAR(2000) NULL,
  utm_content VARCHAR(2000) NULL,
  fbclid VARCHAR(2000) NULL,
  outside_target_size TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('new','imported') NOT NULL DEFAULT 'new',
  prospect_id CHAR(36) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  imported_at DATETIME(3) NULL,
  email_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  email_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_email_error VARCHAR(160) NULL,
  last_email_at DATETIME(3) NULL,
  next_email_at DATETIME(3) NULL,
  email_claim_token CHAR(36) NULL,
  email_claim_until DATETIME(3) NULL,
  INDEX idx_switch_new (status, created_at),
  INDEX idx_switch_email (status, email_status, next_email_at),
  UNIQUE KEY uq_switch_prospect (prospect_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
