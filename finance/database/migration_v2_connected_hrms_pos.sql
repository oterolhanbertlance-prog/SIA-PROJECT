-- Finance v2: HRMS + POS account and branch mapping integration
USE finance_system;
CREATE TABLE IF NOT EXISTS external_identities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 finance_user_id INT UNSIGNED NOT NULL,
 source_system ENUM('hrms','pos') NOT NULL,
 source_user_id INT NOT NULL,
 source_username VARCHAR(120) NULL, source_email VARCHAR(180) NULL,
 source_role VARCHAR(80) NULL, source_branch_id INT NULL, last_synced_at DATETIME NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_external_identity(source_system,source_user_id),
 KEY idx_external_finance_user(finance_user_id)
) ENGINE=InnoDB;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS branch_code VARCHAR(40) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS manager_name VARCHAR(150) NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS hrms_branch_id INT NULL;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS pos_branch_id INT NULL;
ALTER TABLE employee_snapshots ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE employee_snapshots ADD COLUMN IF NOT EXISTS source_branch_id INT NULL;
ALTER TABLE payroll_snapshots ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE payroll_snapshots ADD COLUMN IF NOT EXISTS source_branch_id INT NULL;
ALTER TABLE sales_snapshots ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sales_snapshots ADD COLUMN IF NOT EXISTS source_branch_id INT NULL;
ALTER TABLE journal_entries ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS source_system VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS source_user_id INT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS source_employee_id INT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
