-- ============================================================
-- Migration v12 — GPS Location Tracking for Attendance
-- Run this on your MySQL database if the columns don't exist yet.
-- The timekeeping.php page also auto-adds these columns safely.
-- ============================================================

ALTER TABLE attendance
    ADD COLUMN IF NOT EXISTS time_in_lat       DECIMAL(10,7)  NULL          COMMENT 'Latitude at clock-in',
    ADD COLUMN IF NOT EXISTS time_in_lon       DECIMAL(10,7)  NULL          COMMENT 'Longitude at clock-in',
    ADD COLUMN IF NOT EXISTS time_out_lat      DECIMAL(10,7)  NULL          COMMENT 'Latitude at clock-out',
    ADD COLUMN IF NOT EXISTS time_out_lon      DECIMAL(10,7)  NULL          COMMENT 'Longitude at clock-out',
    ADD COLUMN IF NOT EXISTS time_in_distance  DECIMAL(10,2)  NULL          COMMENT 'Distance from office in metres at clock-in',
    ADD COLUMN IF NOT EXISTS time_out_distance DECIMAL(10,2)  NULL          COMMENT 'Distance from office in metres at clock-out',
    ADD COLUMN IF NOT EXISTS location_status   VARCHAR(20)    NOT NULL DEFAULT 'unknown'
                                                                       COMMENT 'on_site | remote | unknown',
    ADD COLUMN IF NOT EXISTS remote_deduction  DECIMAL(10,2)  NOT NULL DEFAULT 0
                                                                       COMMENT 'Extra deduction when clocked from outside office';

-- Company location constants (for reference)
-- NCST Dasmarinas, Cavite, Philippines
-- Latitude:  14.329444
-- Longitude: 120.936806
-- Allowed radius: 100 metres
