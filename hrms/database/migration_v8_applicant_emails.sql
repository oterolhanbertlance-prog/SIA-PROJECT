-- =====================================================================
-- HRMS Migration v8 — HR Emails to Applicants (Mailer Inbox)
-- Run once against the hrms database.
-- =====================================================================

-- ── 1. Create hr_emails table ─────────────────────────────────────────
-- Stores emails composed by HR via the Mailer module.
-- Each row is one email sent to one applicant.
-- Applicants read these in applicant/inbox.php.
CREATE TABLE IF NOT EXISTS `hr_emails` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `applicant_id` INT(11)      NOT NULL,
    `sender_name`  VARCHAR(150) NOT NULL DEFAULT 'HR Department',
    `sender_email` VARCHAR(150) DEFAULT NULL,
    `subject`      VARCHAR(255) NOT NULL,
    `body`         TEXT         NOT NULL,
    `is_read`      TINYINT(1)   NOT NULL DEFAULT 0,
    `sent_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `applicant_id` (`applicant_id`),
    KEY `is_read` (`is_read`),
    CONSTRAINT `fk_hremail_app` FOREIGN KEY (`applicant_id`)
        REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Emails sent from HR Mailer to individual applicants';

-- ── 2. Ensure applicant_accounts has a password column ──────────────
-- (Added in migration_v4 but included here for safety)
ALTER TABLE `applicant_accounts`
    ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'bcrypt hash for applicant portal login'
        AFTER `email`;

-- ── 3. Ensure notifications supports applicant_account_id ───────────
-- (Added in migration_v4 but safe to re-run)
ALTER TABLE `notifications`
    MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `applicant_account_id` INT(11) NULL DEFAULT NULL
        AFTER `user_id`;

-- ── DONE ─────────────────────────────────────────────────────────────
