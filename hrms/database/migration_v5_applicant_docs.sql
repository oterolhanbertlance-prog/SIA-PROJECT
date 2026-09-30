-- =====================================================================
-- HRMS Migration v5 — Applicant Self-Service Documents
-- Run once against the hrms database.
-- =====================================================================

-- Table for documents uploaded by applicants through the applicant portal.
CREATE TABLE IF NOT EXISTS `applicant_documents` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `applicant_account_id` INT UNSIGNED NOT NULL,
    `document_name`        VARCHAR(255)  NOT NULL,
    `file_path`            VARCHAR(500)  NOT NULL,
    `file_size`            INT UNSIGNED  NOT NULL DEFAULT 0,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_applicant_account` (`applicant_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the uploads directory for applicant documents (handled in PHP via mkdir(),
-- but listed here as a reminder: uploads/applicant_docs/ must be writable).
