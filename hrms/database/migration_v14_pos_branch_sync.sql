-- v14: POS branch mapping support for HRMS recruitment
USE `hrms`;
ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `pos_branch_id` INT NULL AFTER `id`;
ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10,7) NULL AFTER `city`;
ALTER TABLE `branches` ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
ALTER TABLE `job_postings` ADD COLUMN IF NOT EXISTS `branch_id` INT NULL AFTER `department_id`;
ALTER TABLE `applicants` ADD COLUMN IF NOT EXISTS `branch_id` INT NULL AFTER `job_id`;
