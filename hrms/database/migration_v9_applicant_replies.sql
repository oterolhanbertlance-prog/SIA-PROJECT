-- =====================================================================
-- HRMS Migration v9 — Applicant Replies Table
-- Allows applicants to reply to HR emails from the portal.
-- Replies appear in HR > Recruitment > Applicant Replies.
-- Run once against the hrms database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `applicant_replies` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `hr_email_id`  INT(11)      NOT NULL COMMENT 'Which HR email this is a reply to',
    `applicant_id` INT(11)      NOT NULL COMMENT 'The applicant row (in applicants table)',
    `subject`      VARCHAR(255) NOT NULL DEFAULT 'Re: (no subject)',
    `body`         TEXT         NOT NULL,
    `is_read_hr`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = HR has read this reply',
    `sent_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `hr_email_id`  (`hr_email_id`),
    KEY `applicant_id` (`applicant_id`),
    KEY `is_read_hr`   (`is_read_hr`),
    CONSTRAINT `fk_reply_hremail` FOREIGN KEY (`hr_email_id`)  REFERENCES `hr_emails`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reply_app`     FOREIGN KEY (`applicant_id`) REFERENCES `applicants`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Applicant replies to HR emails — visible in HR > Recruitment > Applicant Replies';

-- ── DONE ──────────────────────────────────────────────────────────────────────
