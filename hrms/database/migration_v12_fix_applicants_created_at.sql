-- =====================================================================
-- HRMS Migration v12 — Fix: Add missing created_at to applicants table
-- 
-- The applicants table was originally created with only applied_at and
-- updated_at. Several modules and queries reference applicants.created_at
-- in WHERE clauses, causing:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'created_at'
--   in 'where clause'
--
-- This migration adds created_at to the applicants table (backfilled
-- from applied_at so existing rows get a sensible value).
-- Safe to re-run — uses ADD COLUMN IF NOT EXISTS.
-- =====================================================================

USE `hrms`;

-- Add created_at column to applicants (backfill from applied_at)
ALTER TABLE `applicants`
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Alias for applied_at — added for module compatibility'
        AFTER `updated_at`;

-- Backfill created_at from applied_at for existing rows
UPDATE `applicants`
SET `created_at` = `applied_at`
WHERE `created_at` = '0000-00-00 00:00:00'
   OR `created_at` IS NULL;

-- ── DONE ──────────────────────────────────────────────────────────────────────
SELECT 'Migration v12 complete: created_at added to applicants table.' AS status;
