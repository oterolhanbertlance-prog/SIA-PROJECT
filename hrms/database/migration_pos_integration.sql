-- =============================================================================
-- POS ↔ HRMS Integration — Migration
-- Run this ONCE against your `hrms` database in phpMyAdmin (SQL tab).
-- The `milk_tea_pos` database must already exist separately.
-- =============================================================================

USE hrms;

-- Table 1: Tags an HRMS job_posting as targeting a POS role
CREATE TABLE IF NOT EXISTS `pos_job_postings_meta` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_posting_id`     INT NOT NULL,
  `pos_role_target`    ENUM('super_admin','cashier','inventory_staff') NOT NULL DEFAULT 'cashier',
  `pos_position_label` VARCHAR(100) NOT NULL DEFAULT 'POS Staff',
  `created_by`         INT DEFAULT NULL COMMENT 'HRMS users.id of the HR who tagged it',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_posting` (`job_posting_id`),
  CONSTRAINT `fk_pos_meta_job` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `pos_job_postings_meta`
  MODIFY COLUMN `pos_role_target` ENUM('super_admin','cashier','inventory_staff') NOT NULL DEFAULT 'cashier';

-- Table 2: Audit log of every POS role upgrade (success, failed, or rolled_back)
CREATE TABLE IF NOT EXISTS `pos_role_upgrade_log` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hrms_applicant_id`     INT DEFAULT NULL COMMENT 'applicants.id — null if not linked',
  `hrms_user_id`          INT DEFAULT NULL COMMENT 'employees.id — reserved for future use',
  `pos_user_id`           INT NOT NULL    COMMENT 'milk_tea_pos.users.id',
  `pos_username`          VARCHAR(100) NOT NULL,
  `pos_email`             VARCHAR(150) DEFAULT NULL,
  `old_pos_role`          VARCHAR(50)  DEFAULT NULL,
  `new_pos_role`          VARCHAR(50)  NOT NULL,
  `hrms_role_unchanged`   VARCHAR(50)  NOT NULL DEFAULT 'employee',
  `job_posting_id`        INT DEFAULT NULL,
  `performed_by`          INT DEFAULT NULL COMMENT 'HRMS users.id',
  `performed_by_username` VARCHAR(100) DEFAULT NULL,
  `notes`                 TEXT DEFAULT NULL,
  `status`                ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_applicant` (`hrms_applicant_id`),
  KEY `idx_pos_user`  (`pos_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: POS portal users self-applying for role upgrades
CREATE TABLE IF NOT EXISTS `pos_role_applications` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pos_user_id`    INT NOT NULL    COMMENT 'milk_tea_pos.users.id',
  `pos_username`   VARCHAR(100) NOT NULL,
  `pos_email`      VARCHAR(150) DEFAULT NULL,
  `job_posting_id` INT NOT NULL,
  `cover_note`     TEXT DEFAULT NULL,
  `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`    INT DEFAULT NULL COMMENT 'HRMS users.id',
  `reviewed_at`    DATETIME DEFAULT NULL,
  `hr_notes`       TEXT DEFAULT NULL,
  `applied_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pos_user_job` (`pos_user_id`, `job_posting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `pos_role` ENUM('super_admin','cashier','inventory_staff') NULL
        COMMENT 'POS role for hired staff; HRMS role remains employee'
        AFTER `role`;
ALTER TABLE `users`
    MODIFY COLUMN `pos_role` ENUM('super_admin','cashier','inventory_staff') NULL
        COMMENT 'POS role for hired staff; HRMS role remains employee'
        AFTER `role`;
ALTER TABLE `attendance`
    MODIFY COLUMN `employee_id` INT(11) NULL,
    ADD COLUMN IF NOT EXISTS `pos_user_id` INT(11) NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `source_system` VARCHAR(30) NOT NULL DEFAULT 'hrms' AFTER `pos_user_id`;

-- =============================================================================
-- Done. POS integration tables and shared login/attendance columns are ready.
-- =============================================================================
