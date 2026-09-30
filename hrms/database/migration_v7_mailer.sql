-- =====================================================================
-- HRMS Migration v7 — Mailer Module + GSIS + Payroll Deduction Rules
-- Run once against the hrms database.
-- =====================================================================

-- ── 1. Add GSIS number to employees ──────────────────────────────────────────
-- GSIS (Government Service Insurance System) is applicable to government
-- employees. Like SSS/PhilHealth/Pag-IBIG, if this is empty the GSIS
-- deduction in payroll will be ₱0.
ALTER TABLE `employees`
    ADD COLUMN IF NOT EXISTS `gsis_no` VARCHAR(30) DEFAULT NULL
        COMMENT 'Government Service Insurance System number'
        AFTER `tin_no`;

-- ── 2. Add GSIS deduction column to payroll ───────────────────────────────────
ALTER TABLE `payroll`
    ADD COLUMN IF NOT EXISTS `gsis_deduction` DECIMAL(10,2) DEFAULT 0.00
        AFTER `pagibig_deduction`;

-- Update existing payroll rows so total_deductions is still correct
-- (gsis_deduction defaults to 0 for all existing rows — no change needed).

-- ── 3. Create mailer_accounts table ──────────────────────────────────────────
-- Separate from the main `users` table. Provides an independent login
-- for the HR Mailer module (mailer/login.php) that does NOT give access
-- to the main HRMS dashboard.
CREATE TABLE IF NOT EXISTS `mailer_accounts` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `fullname`   VARCHAR(150) DEFAULT NULL,
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME     DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_mailer_username` (`username`),
    UNIQUE KEY `uniq_mailer_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Standalone accounts for the HR Mailer module (separate from users table)';

-- ── 4. Default mailer account (username: hrmailer / password: hrmailer123) ───
-- Change the password immediately after setup!
INSERT IGNORE INTO `mailer_accounts` (`username`, `password`, `email`, `fullname`, `status`)
VALUES (
    'hrmailer',
    '$2y$10$VZQ3p7X1rQnA/FqNvUzS3.HWtkPVbEODWm1kApnTZN7ZSuHjI.9Ni', -- hrmailer123
    'hr@hrms.local',
    'HR Mailer Admin',
    'active'
);

-- ── 5. Ensure applicant_accounts has all needed columns ──────────────────────
-- (safe re-run — uses ADD COLUMN IF NOT EXISTS)
ALTER TABLE `applicant_accounts`
    ADD COLUMN IF NOT EXISTS `hired_username`      VARCHAR(100) NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `hired_temp_password` VARCHAR(255) NULL DEFAULT NULL AFTER `hired_username`,
    ADD COLUMN IF NOT EXISTS `hired_employee_id`   VARCHAR(50)  NULL DEFAULT NULL AFTER `hired_temp_password`;

-- ── 6. Ensure interviews table has `notes` column ────────────────────────────
ALTER TABLE `interviews`
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL DEFAULT NULL
        COMMENT 'Additional notes sent to the applicant with the interview invitation'
        AFTER `rating`;

-- ── PAYROLL DEDUCTION REMINDER ───────────────────────────────────────────────
-- The payroll module checks employee.sss_no, philhealth_no, pagibig_no,
-- tin_no, and gsis_no before calculating deductions.
-- Any field that is NULL or empty string results in a ₱0.00 deduction
-- for the corresponding contribution.
--
-- Reference function: computePayrollDeductions() in mailer/config.php
-- and the updated payroll processing logic.

-- ── DONE ──────────────────────────────────────────────────────────────────────
