-- =====================================================================
-- HRMS Migration v11 — Allow HR/Admin to clock in without employee record
-- Makes employee_id nullable and adds a user_date unique key so that
-- admin/hr users can log attendance using only their user_id.
-- Safe to re-run.
-- =====================================================================

-- Step 1: Drop the FK so we can modify the column
ALTER TABLE `attendance`
    DROP FOREIGN KEY IF EXISTS `fk_att_emp`;

-- Step 2: Make employee_id nullable
ALTER TABLE `attendance`
    MODIFY COLUMN `employee_id` INT(11) NULL DEFAULT NULL
        COMMENT 'NULL for admin/hr users who have no employee record';

-- Step 3: Re-add FK (now allows NULL)
ALTER TABLE `attendance`
    ADD CONSTRAINT `fk_att_emp`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

-- Step 4: Add unique key for user-only rows (admin/hr without employee_id)
ALTER TABLE `attendance`
    ADD UNIQUE KEY IF NOT EXISTS `user_date` (`user_id`, `date`);

-- ── DONE ──────────────────────────────────────────────────────────────────────
