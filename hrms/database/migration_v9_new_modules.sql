-- ============================================================
-- HRMS Migration v9 — New modules: branches, inventory,
--   finance, SMTP settings, attendance proofs, & role updates.
-- Run ONCE against the `hrms` database.
-- Safe to run multiple times (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
-- ============================================================

USE `hrms`;

-- ─────────────────────────────────────────────────────────────
-- 1. Branches
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `branches` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `branch_code`  VARCHAR(30)  NOT NULL UNIQUE,
    `name`         VARCHAR(150) NOT NULL,
    `address`      VARCHAR(255) NOT NULL,
    `city`         VARCHAR(100) DEFAULT NULL,
    `phone`        VARCHAR(30)  DEFAULT NULL,
    `manager_id`   INT          DEFAULT NULL,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `branches` (`id`,`branch_code`,`name`,`address`,`city`,`status`) VALUES
(1,'BR-MAIN-HQ','Main Branch / Head Office','123 Talisay Street, Brgy. Centro','Cebu City','active'),
(2,'BR-BH-02','North Branch','456 Mandaue Road, Brgy. Looc','Mandaue City','active');

-- Add branch_id FK to employees (does nothing if already present)
ALTER TABLE `employees`
    ADD COLUMN IF NOT EXISTS `branch_id` INT DEFAULT NULL AFTER `department_id`;

-- ─────────────────────────────────────────────────────────────
-- 2. Users role ENUM — expand to include inventory_staff
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('admin','hr','inventory_staff','employee')
        NOT NULL DEFAULT 'employee';

-- ─────────────────────────────────────────────────────────────
-- 3. SMTP settings (single-row config)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `smtp_settings` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `smtp_host`     VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    `smtp_port`     SMALLINT     NOT NULL DEFAULT 587,
    `smtp_username` VARCHAR(255) NOT NULL DEFAULT '',
    `smtp_password` VARCHAR(255) NOT NULL DEFAULT '',
    `smtp_from`     VARCHAR(255) NOT NULL DEFAULT '',
    `smtp_name`     VARCHAR(150) NOT NULL DEFAULT 'BobaHouse HR',
    `smtp_secure`   ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    `smtp_auth`     TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `smtp_settings` (`id`,`smtp_host`,`smtp_port`,`smtp_from`,`smtp_name`) VALUES
(1,'smtp.gmail.com',587,'noreply@bobahouse.com','BobaHouse HR');

-- ─────────────────────────────────────────────────────────────
-- 4. Finance entries
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `finance_entries` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `type`         ENUM('income','expense') NOT NULL,
    `category`     VARCHAR(100) NOT NULL DEFAULT 'General',
    `description`  TEXT DEFAULT NULL,
    `amount`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `entry_date`   DATE NOT NULL,
    `reference`    VARCHAR(100) DEFAULT NULL,
    `attachment`   VARCHAR(255) DEFAULT NULL,
    `branch_id`    INT DEFAULT NULL,
    `created_by`   INT DEFAULT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY (`entry_date`),
    KEY (`type`),
    KEY (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. Inventory items
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `sku`           VARCHAR(60) NOT NULL UNIQUE,
    `name`          VARCHAR(150) NOT NULL,
    `category`      VARCHAR(100) DEFAULT 'General',
    `description`   TEXT DEFAULT NULL,
    `unit`          VARCHAR(30)  NOT NULL DEFAULT 'pcs',
    `current_stock` INT          NOT NULL DEFAULT 0,
    `reorder_level` INT          NOT NULL DEFAULT 5,
    `unit_cost`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `location`      VARCHAR(150) DEFAULT NULL,
    `branch_id`     INT          DEFAULT NULL,
    `status`        ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
    `created_by`    INT          DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY (`branch_id`),
    KEY (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. Inventory movements (stock in / out)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventory_movements` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `item_id`       INT NOT NULL,
    `type`          ENUM('in','out','adjustment','return') NOT NULL,
    `quantity`      INT NOT NULL,
    `unit_cost`     DECIMAL(12,2) DEFAULT NULL,
    `reference`     VARCHAR(100) DEFAULT NULL,
    `remarks`       TEXT DEFAULT NULL,
    `performed_by`  INT DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY (`item_id`),
    KEY (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 7. Attendance proofs (uploaded files for time-in/out evidence)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance_proofs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `attendance_id` INT NOT NULL,
    `file_path`     VARCHAR(255) NOT NULL,
    `file_type`     VARCHAR(50)  NOT NULL DEFAULT 'image',
    `uploaded_by`   INT DEFAULT NULL,
    `uploaded_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY (`attendance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add proof_file shortcut column to attendance table
ALTER TABLE `attendance`
    ADD COLUMN IF NOT EXISTS `proof_file` VARCHAR(255) NULL AFTER `undertime_minutes`;

-- ─────────────────────────────────────────────────────────────
-- 8. Payroll — add missing columns used by new process.php
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `payroll`
    ADD COLUMN IF NOT EXISTS `allowances`          DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `basic_salary`,
    ADD COLUMN IF NOT EXISTS `overtime_pay`        DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `allowances`,
    ADD COLUMN IF NOT EXISTS `bonuses`             DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `overtime_pay`,
    ADD COLUMN IF NOT EXISTS `gross_pay`           DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `bonuses`,
    ADD COLUMN IF NOT EXISTS `sss_deduction`       DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `gross_pay`,
    ADD COLUMN IF NOT EXISTS `philhealth_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0   AFTER `sss_deduction`,
    ADD COLUMN IF NOT EXISTS `pagibig_deduction`   DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `philhealth_deduction`,
    ADD COLUMN IF NOT EXISTS `tax_deduction`       DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `pagibig_deduction`,
    ADD COLUMN IF NOT EXISTS `other_deductions`    DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `tax_deduction`,
    ADD COLUMN IF NOT EXISTS `total_deductions`    DECIMAL(12,2) NOT NULL DEFAULT 0    AFTER `other_deductions`,
    ADD COLUMN IF NOT EXISTS `days_worked`         TINYINT       NOT NULL DEFAULT 22   AFTER `net_pay`,
    ADD COLUMN IF NOT EXISTS `processed_by`        INT           DEFAULT NULL          AFTER `days_worked`,
    ADD COLUMN IF NOT EXISTS `processed_at`        DATETIME      DEFAULT NULL          AFTER `processed_by`;

-- ─────────────────────────────────────────────────────────────
-- 9. Contracts — add interview_date if missing
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `applicants`
    ADD COLUMN IF NOT EXISTS `interview_date`   DATE     DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `interview_time`   TIME     DEFAULT NULL AFTER `interview_date`,
    ADD COLUMN IF NOT EXISTS `interview_venue`  VARCHAR(255) DEFAULT NULL AFTER `interview_time`,
    ADD COLUMN IF NOT EXISTS `interview_notes`  TEXT     DEFAULT NULL AFTER `interview_venue`;

ALTER TABLE `contracts`
    ADD COLUMN IF NOT EXISTS `contract_number`  VARCHAR(30) DEFAULT NULL AFTER `id`;

SELECT 'Migration v9 complete.' AS status;
