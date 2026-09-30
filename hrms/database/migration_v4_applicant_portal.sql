-- Migration v4: Applicant self-service portal (separate login from HR/admin/employee users)
-- Additive only — does not touch or remove any existing table/column.

CREATE TABLE IF NOT EXISTS applicant_accounts (
  id INT(11) NOT NULL AUTO_INCREMENT,
  firstname VARCHAR(100) NOT NULL,
  lastname VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uniq_applicant_account_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link each application record to the applicant's account (nullable, so
-- existing rows and any future HR-entered applicant with no self-service
-- account keep working exactly as before).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'applicant_account_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE applicants ADD COLUMN applicant_account_id INT(11) DEFAULT NULL AFTER id, ADD KEY idx_applicant_account_id (applicant_account_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND CONSTRAINT_NAME = 'fk_applicants_account');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE applicants ADD CONSTRAINT fk_applicants_account FOREIGN KEY (applicant_account_id) REFERENCES applicant_accounts(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Allow notifications to target an applicant account instead of a staff user.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'applicant_account_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE notifications ADD COLUMN applicant_account_id INT(11) DEFAULT NULL AFTER user_id, MODIFY COLUMN user_id INT(11) DEFAULT NULL, ADD KEY idx_notif_applicant_account_id (applicant_account_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND CONSTRAINT_NAME = 'fk_notifications_applicant');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_applicant FOREIGN KEY (applicant_account_id) REFERENCES applicant_accounts(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
