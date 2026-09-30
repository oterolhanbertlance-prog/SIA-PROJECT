-- ============================================================
-- HRMS Migration: Two-stage Admin -> HR approval workflow
-- Run this ONCE against an EXISTING hrms database (one that was
-- created before this update). Skip this file if you are doing
-- a fresh install from hrms.sql, which already includes these
-- changes.
-- ============================================================

-- Leave requests: admin recommends, HR gives final approval
ALTER TABLE `leave_requests`
  MODIFY `status` enum('pending','admin_approved','approved','rejected','cancelled') DEFAULT 'pending',
  ADD COLUMN `admin_approved_by` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `admin_approved_at` datetime DEFAULT NULL AFTER `admin_approved_by`;

-- Resignation letters: admin recommends, HR gives final approval
ALTER TABLE `resignation_letters`
  MODIFY `status` enum('pending','admin_approved','approved','rejected') DEFAULT 'pending',
  ADD COLUMN `admin_approved_by` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `admin_approved_at` datetime DEFAULT NULL AFTER `admin_approved_by`;

-- User registration approvals: admin recommends, HR gives final approval
ALTER TABLE `users`
  MODIFY `status` enum('active','inactive','pending','admin_approved','rejected') DEFAULT 'active',
  ADD COLUMN `admin_approved_by` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `admin_approved_at` datetime DEFAULT NULL AFTER `admin_approved_by`;

-- Interviews: admin recommends the schedule, HR gives the official go-ahead
ALTER TABLE `interviews`
  ADD COLUMN `approval_status` enum('pending','admin_approved','approved','rejected') DEFAULT 'pending' AFTER `status`,
  ADD COLUMN `admin_approved_by` int(11) DEFAULT NULL AFTER `approval_status`,
  ADD COLUMN `admin_approved_at` datetime DEFAULT NULL AFTER `admin_approved_by`,
  ADD COLUMN `hr_approved_by` int(11) DEFAULT NULL AFTER `admin_approved_at`,
  ADD COLUMN `hr_approved_at` datetime DEFAULT NULL AFTER `hr_approved_by`;
