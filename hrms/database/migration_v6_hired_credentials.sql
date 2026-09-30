-- =====================================================================
-- HRMS Migration v6 — Store hired employee credentials in applicant_accounts
-- Run once against the hrms database.
-- =====================================================================

-- Add columns to applicant_accounts to persist the plain-text
-- credentials given to the applicant when their account is created.
-- These are ONLY written once (at hire time) and are shown in the
-- applicant portal so the new hire knows how to log into the HRMS.
ALTER TABLE `applicant_accounts`
    ADD COLUMN IF NOT EXISTS `hired_username`      VARCHAR(100)  NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `hired_temp_password` VARCHAR(255)  NULL DEFAULT NULL AFTER `hired_username`,
    ADD COLUMN IF NOT EXISTS `hired_employee_id`   VARCHAR(50)   NULL DEFAULT NULL AFTER `hired_temp_password`;

-- NOTE: hired_temp_password is stored in plain text intentionally
-- so the applicant can see it in the portal. It is a *temporary*
-- credential; the employee is expected to change it on first login.
-- It is not used for authentication — the bcrypt hash in users.password
-- is what actually controls access.
