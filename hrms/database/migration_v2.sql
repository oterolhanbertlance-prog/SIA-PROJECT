-- ============================================================
-- HRMS v2 Migration Script
-- Run this ONLY if you already have the hrms database installed.
-- If installing fresh, use hrms.sql instead.
-- ============================================================

USE `hrms`;

-- Add resignation_letters table (v2 feature)
CREATE TABLE IF NOT EXISTS `resignation_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `last_working_day` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `letter_body` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hr_remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_res_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add rejection_reason column to users table if it doesn't already exist
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `rejection_reason` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approved_at` datetime DEFAULT NULL;

-- Ensure employees table has employment_status column (it should already exist)
-- ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `employment_status` enum('Regular','Probationary','Contractual','Resigned','Terminated') DEFAULT 'Probationary';

SELECT 'Migration v2 applied successfully.' AS result;
