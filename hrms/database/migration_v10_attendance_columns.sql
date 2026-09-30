-- =====================================================================
-- HRMS Migration v10 — Add missing attendance columns
-- Adds late_minutes, undertime_minutes, and user_id to the attendance
-- table. Required for Time In / Out and Timekeeping pages to work.
-- Safe to re-run — uses ADD COLUMN IF NOT EXISTS.
-- =====================================================================

ALTER TABLE `attendance`
    ADD COLUMN IF NOT EXISTS `user_id`            INT(11)       NULL DEFAULT NULL
        COMMENT 'HRMS user account that clocked in (nullable)'
        AFTER `id`,
    ADD COLUMN IF NOT EXISTS `late_minutes`        INT(11)       NOT NULL DEFAULT 0
        COMMENT 'Minutes late past 08:00 AM start'
        AFTER `overtime_hours`,
    ADD COLUMN IF NOT EXISTS `undertime_minutes`   INT(11)       NOT NULL DEFAULT 0
        COMMENT 'Minutes left before 05:00 PM end'
        AFTER `late_minutes`;

-- Index for fast per-user lookups
ALTER TABLE `attendance`
    ADD INDEX IF NOT EXISTS `idx_att_user_id` (`user_id`);

-- ── DONE ──────────────────────────────────────────────────────────────────────
