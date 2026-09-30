-- Finance System database for XAMPP / phpMyAdmin
-- Import this file into MariaDB/MySQL before opening the application.
CREATE DATABASE IF NOT EXISTS `finance_system`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `finance_system`;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `account_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `normal_balance` ENUM('debit','credit') NOT NULL,
  `statement_group` ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_groups_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `account_group_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_code` (`code`),
  CONSTRAINT `fk_accounts_group` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fiscal_periods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_name` VARCHAR(40) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('open','soft_close','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_period_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_system` VARCHAR(40) NULL,
  `source_id` VARCHAR(80) NULL,
  `name` VARCHAR(120) NOT NULL,
  `address` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_branch_source` (`source_system`, `source_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cost_centers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `source_system` VARCHAR(40) NULL,
  `source_id` VARCHAR(80) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_centers_code` (`code`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_number` VARCHAR(40) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `source_system` VARCHAR(40) NULL,
  `source_id` VARCHAR(100) NULL,
  `entry_type` ENUM('manual','imported','adjusting','reversing','recurring') NOT NULL DEFAULT 'manual',
  `status` ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
  `total_debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED NULL,
  `posted_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_number` (`journal_number`),
  UNIQUE KEY `uq_journal_source` (`source_system`, `source_id`),
  KEY `idx_journal_date_status` (`transaction_date`, `status`),
  CONSTRAINT `fk_journal_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NULL,
  `debit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `cost_center_id` INT UNSIGNED NULL,
  `branch_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_journal_lines_entry` (`journal_entry_id`),
  CONSTRAINT `fk_journal_lines_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_journal_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_journal_lines_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_journal_lines_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_journal_line_side` CHECK ((`debit` > 0 AND `credit` = 0) OR (`credit` > 0 AND `debit` = 0))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(40) NULL,
  `credit_limit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(40) NULL,
  `payment_terms` VARCHAR(80) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ar_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(40) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `status` ENUM('draft','posted','paid','void') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ar_invoice_number` (`invoice_number`),
  CONSTRAINT `fk_ar_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ap_bills` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_number` VARCHAR(40) NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `bill_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `status` ENUM('draft','posted','paid','void') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ap_bill_number` (`bill_number`),
  CONSTRAINT `fk_ap_bill_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_date` DATE NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'cash',
  `status` ENUM('draft','approved','posted','void') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_expense_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_year` YEAR NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `status` ENUM('draft','approved','closed') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `budget_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `month_number` TINYINT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_line` (`budget_id`, `account_id`, `month_number`),
  CONSTRAINT `fk_budget_line_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_line_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fixed_assets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `acquisition_date` DATE NOT NULL,
  `cost` DECIMAL(14,2) NOT NULL,
  `salvage_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `useful_life_months` INT UNSIGNED NOT NULL,
  `accumulated_depreciation` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','disposed','transferred') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fixed_asset_code` (`asset_code`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tax_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `rate` DECIMAL(7,4) NOT NULL,
  `tax_type` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `integration_mappings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_system` VARCHAR(40) NOT NULL,
  `source_id` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `local_id` BIGINT UNSIGNED NULL,
  `status` ENUM('imported','skipped','failed') NOT NULL DEFAULT 'imported',
  `last_error` TEXT NULL,
  `first_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_integration_mapping` (`source_system`, `source_id`, `entity_type`),
  KEY `idx_integration_status` (`source_system`, `status`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `integration_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_system` VARCHAR(40) NOT NULL,
  `action` VARCHAR(40) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `imported_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `message` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_integration_logs_source_date` (`source_system`, `started_at`),
  CONSTRAINT `fk_integration_log_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `employee_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_id` VARCHAR(100) NOT NULL,
  `employee_code` VARCHAR(40) NULL,
  `full_name` VARCHAR(180) NOT NULL,
  `department_name` VARCHAR(120) NULL,
  `position_title` VARCHAR(120) NULL,
  `employment_status` VARCHAR(50) NULL,
  `basic_salary` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `source_updated_at` DATETIME NULL,
  `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_snapshot_source` (`source_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payroll_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_id` VARCHAR(100) NOT NULL,
  `employee_source_id` VARCHAR(100) NULL,
  `payroll_code` VARCHAR(40) NULL,
  `payroll_month` TINYINT UNSIGNED NOT NULL,
  `payroll_year` YEAR NOT NULL,
  `gross_pay` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `net_pay` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(30) NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `source_updated_at` DATETIME NULL,
  `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_snapshot_source` (`source_id`),
  CONSTRAINT `fk_payroll_snapshot_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sales_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_id` VARCHAR(100) NOT NULL,
  `transaction_date` DATETIME NOT NULL,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `order_type` VARCHAR(30) NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `source_updated_at` DATETIME NULL,
  `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_snapshot_source` (`source_id`),
  KEY `idx_sales_snapshot_date` (`transaction_date`),
  CONSTRAINT `fk_sales_snapshot_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sales_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_snapshot_id` BIGINT UNSIGNED NOT NULL,
  `source_detail_id` VARCHAR(100) NOT NULL,
  `product_name` VARCHAR(150) NULL,
  `category_name` VARCHAR(120) NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_item_source` (`source_detail_id`),
  CONSTRAINT `fk_sales_item_sale` FOREIGN KEY (`sale_snapshot_id`) REFERENCES `sales_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(60) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `record_type` VARCHAR(80) NULL,
  `record_id` VARCHAR(100) NULL,
  `old_data` JSON NULL,
  `new_data` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_date` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient` VARCHAR(180) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `sent_at` DATETIME NULL,
  `error_message` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mail_queue_status` (`status`, `created_at`),
  CONSTRAINT `fk_mail_queue_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `location_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NULL,
  `label` VARCHAR(150) NOT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `purpose` VARCHAR(180) NOT NULL,
  `logged_at` DATETIME NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_location_logs_date` (`logged_at`),
  CONSTRAINT `fk_location_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_location_log_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_snapshot_id` BIGINT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `time_in` DATETIME NULL,
  `time_out` DATETIME NULL,
  `geo_latitude` DECIMAL(10,7) NULL,
  `geo_longitude` DECIMAL(10,7) NULL,
  `status` ENUM('present','late','absent','on_leave','half_day') NOT NULL DEFAULT 'present',
  `notes` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_employee_date` (`employee_snapshot_id`, `attendance_date`),
  KEY `idx_attendance_date_status` (`attendance_date`, `status`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_snapshot_id`) REFERENCES `employee_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO `roles` (`name`, `label`) VALUES
  ('super_admin', 'Super Administrator'),
  ('finance_manager', 'Finance Manager'),
  ('accountant', 'Accountant'),
  ('finance_staff', 'Finance Staff'),
  ('auditor', 'Auditor'),
  ('viewer', 'Viewer');

INSERT IGNORE INTO `permissions` (`name`) VALUES
  ('dashboard.view'), ('journal.view'), ('journal.create'), ('journal.post'),
  ('journal.void'), ('sales.view'), ('sales.sync'), ('payroll.view'),
  ('payroll.sync'), ('ar.manage'), ('ap.manage'), ('cash.manage'),
  ('bank.reconcile'), ('budget.manage'), ('assets.manage'), ('tax.manage'),
  ('reports.view'), ('settings.manage'), ('audit.view'), ('mailer.manage'), ('gps.manage'), ('attendance.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'super_admin';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r JOIN permissions p ON p.name IN ('mailer.manage', 'gps.manage', 'attendance.manage')
WHERE r.name IN ('finance_manager', 'accountant');

INSERT IGNORE INTO `account_groups` (`name`, `normal_balance`, `statement_group`) VALUES
  ('Cash and Cash Equivalents', 'debit', 'asset'),
  ('Bank Accounts', 'debit', 'asset'),
  ('E-Wallets', 'debit', 'asset'),
  ('Accounts Receivable', 'debit', 'asset'),
  ('Inventory', 'debit', 'asset'),
  ('Property and Equipment', 'debit', 'asset'),
  ('Accumulated Depreciation', 'credit', 'asset'),
  ('Accounts Payable', 'credit', 'liability'),
  ('Payroll Liabilities', 'credit', 'liability'),
  ('Tax Liabilities', 'credit', 'liability'),
  ('Owner Equity', 'credit', 'equity'),
  ('Revenue', 'credit', 'revenue'),
  ('Cost of Goods Sold', 'debit', 'expense'),
  ('Operating Expenses', 'debit', 'expense');

INSERT IGNORE INTO `accounts` (`code`, `name`, `account_group_id`)
SELECT v.code, v.name, g.id
FROM (
  SELECT '1000' code, 'Cash' name, 'Cash and Cash Equivalents' group_name
  UNION ALL SELECT '1010', 'Bank Accounts', 'Bank Accounts'
  UNION ALL SELECT '1020', 'E-Wallets', 'E-Wallets'
  UNION ALL SELECT '1100', 'Accounts Receivable', 'Accounts Receivable'
  UNION ALL SELECT '1200', 'Inventory', 'Inventory'
  UNION ALL SELECT '1500', 'Property and Equipment', 'Property and Equipment'
  UNION ALL SELECT '1600', 'Accumulated Depreciation', 'Accumulated Depreciation'
  UNION ALL SELECT '2000', 'Accounts Payable', 'Accounts Payable'
  UNION ALL SELECT '2100', 'Payroll Payable', 'Payroll Liabilities'
  UNION ALL SELECT '2200', 'SSS Payable', 'Payroll Liabilities'
  UNION ALL SELECT '2210', 'PhilHealth Payable', 'Payroll Liabilities'
  UNION ALL SELECT '2220', 'Pag-IBIG Payable', 'Payroll Liabilities'
  UNION ALL SELECT '2230', 'Withholding Tax Payable', 'Tax Liabilities'
  UNION ALL SELECT '2300', 'Other Current Liabilities', 'Tax Liabilities'
  UNION ALL SELECT '3000', 'Owner / Shareholders Equity', 'Owner Equity'
  UNION ALL SELECT '3100', 'Retained Earnings', 'Owner Equity'
  UNION ALL SELECT '4000', 'Sales Revenue', 'Revenue'
  UNION ALL SELECT '4100', 'Other Income', 'Revenue'
  UNION ALL SELECT '5000', 'Cost of Goods Sold', 'Cost of Goods Sold'
  UNION ALL SELECT '5100', 'Payroll Expense', 'Operating Expenses'
  UNION ALL SELECT '5200', 'Rent Expense', 'Operating Expenses'
  UNION ALL SELECT '5300', 'Utilities', 'Operating Expenses'
  UNION ALL SELECT '5400', 'Supplies', 'Operating Expenses'
  UNION ALL SELECT '5500', 'Marketing', 'Operating Expenses'
  UNION ALL SELECT '5600', 'Depreciation', 'Operating Expenses'
  UNION ALL SELECT '5700', 'Repairs and Maintenance', 'Operating Expenses'
  UNION ALL SELECT '5800', 'Transportation', 'Operating Expenses'
  UNION ALL SELECT '5900', 'Other Operating Expenses', 'Operating Expenses'
) v
JOIN account_groups g ON g.name = v.group_name;

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
  ('company_name', 'Finance System'),
  ('currency', 'PHP'),
  ('session_timeout_minutes', '30'),
  ('inventory_costing_method', 'manual');

SET FOREIGN_KEY_CHECKS = 1;

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
