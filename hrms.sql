-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 11:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `applicant_account_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `applicant_code` varchar(20) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `highest_education` varchar(100) DEFAULT NULL,
  `school` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `years_experience` int(11) DEFAULT 0,
  `resume` varchar(255) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `status` enum('pending','initial_interview','final_interview','passed','failed','hired','withdrawn') DEFAULT 'pending',
  `interview_date` date DEFAULT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_venue` varchar(255) DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Alias kept for module compatibility',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `applicant_account_id`, `job_id`, `branch_id`, `applicant_code`, `firstname`, `lastname`, `middlename`, `email`, `phone`, `address`, `gender`, `birthdate`, `highest_education`, `school`, `course`, `years_experience`, `resume`, `cover_letter`, `skills`, `status`, `interview_date`, `interview_time`, `interview_venue`, `interview_notes`, `applied_at`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 1, 'APP-2024-0001', 'Ana', 'Cruz', NULL, 'ana.cruz@email.com', '09301234567', NULL, NULL, NULL, 'Bachelor\'s Degree', 'Mapua University', 'Psychology', 2, NULL, NULL, NULL, 'initial_interview', NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31', '2026-09-07 05:47:02'),
(2, NULL, 2, 1, 'APP-2024-0002', 'Ben', 'Torres', NULL, 'ben.torres@email.com', '09311234567', NULL, NULL, NULL, 'Bachelor\'s Degree', 'FEU Tech', 'Computer Science', 4, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31', '2026-09-07 05:47:02'),
(3, NULL, 1, 1, 'APP-2024-0003', 'Clara', 'Lim', NULL, 'clara.lim@email.com', '09321234567', NULL, NULL, NULL, 'Bachelor\'s Degree', 'PLM', 'Human Resources', 1, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31', '2026-09-07 05:47:02'),
(4, NULL, 3, 1, 'APP-2024-0004', 'David', 'Tan', NULL, 'david.tan@email.com', '09331234567', NULL, NULL, NULL, 'Bachelor\'s Degree', 'PUP', 'Accounting', 3, NULL, NULL, NULL, 'final_interview', NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31', '2026-09-07 05:47:02'),
(5, 2, 1, 1, 'APP-2026-0001', 'Vincent', 'Garibay', 'Pacardo', 'sahur@gmail.com', '09319015661', 'BLK 28 LT 9', 'Male', NULL, 'Vocational', 'NCST', 'BSIT', 3, NULL, 'qweqwe', 'Customer Service', 'hired', NULL, NULL, NULL, NULL, '2026-09-08 12:40:34', '2026-09-08 12:40:34', '2026-09-08 12:52:38'),
(6, 3, 2, 1, 'APP-2026-0002', 'lance', 'otero', '', 'lance1@gmail.com', '', '', '', NULL, '', '', '', 0, NULL, '', '', 'hired', NULL, NULL, NULL, NULL, '2026-09-08 13:46:07', '2026-09-08 13:46:07', '2026-09-08 13:47:19'),
(7, 4, 1, 1, 'APP-2026-0003', 'Lawrence', 'Segunial', 'Carreon', 'lawrence@gmail.com', '09858895721', 'Dasma Bayan', 'Male', NULL, 'Vocational', 'NCST', 'Bachelor of Science in Computer Science', 1, NULL, '', 'Cashier', 'hired', NULL, NULL, NULL, NULL, '2026-09-09 06:14:05', '2026-09-09 06:14:05', '2026-09-09 06:15:47'),
(8, 5, 2, 1, 'APP-2026-0004', 'lhanbert', 'otero', 'P', 'lance11@gmail.com', '', '', '', NULL, '', '', '', 0, NULL, '', '', 'hired', NULL, NULL, NULL, NULL, '2026-09-21 14:55:39', '2026-09-21 14:55:39', '2026-09-21 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_accounts`
--

CREATE TABLE `applicant_accounts` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT 'NULL for email-only applicants',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `hired_username` varchar(100) DEFAULT NULL,
  `hired_temp_password` varchar(255) DEFAULT NULL,
  `hired_employee_id` varchar(50) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_accounts`
--

INSERT INTO `applicant_accounts` (`id`, `firstname`, `lastname`, `email`, `phone`, `password`, `status`, `hired_username`, `hired_temp_password`, `hired_employee_id`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Vincent', 'Garibay', 'vincentgaribay988@gmail.com', '09319015661', '$2y$10$mMQmMLyokkq2Tx1Q3cqapeRjNqlZd5bRKj8wcslJ3sujV2gOGVXfW', 'active', NULL, NULL, NULL, NULL, '2026-09-07 13:10:11', '2026-09-07 13:10:11'),
(2, 'Vincent', 'Garibay', 'sahur@gmail.com', '09319015661', '$2y$10$mzQIxDK3J3WgwNRAlAAPeOZU/aBfmR0xKx7KpkaGp/MFQ/iCg1Wu6', 'active', 'garibay', '123456', 'EMP-2026-0001', NULL, '2026-09-08 12:40:34', '2026-09-08 12:52:38'),
(3, 'lance', 'otero', 'lance1@gmail.com', '', '', 'active', 'otero', '123456', 'EMP-2026-0002', NULL, '2026-09-08 13:46:07', '2026-09-08 13:47:19'),
(4, 'Lawrence', 'Segunial', 'lawrence@gmail.com', '09858895721', '$2y$10$wIwlhskiMOdqsZl3EWb0MO7XaIdoU9vIy1vNhGkol6MFXZzpRYvJG', 'active', 'law', '123456', 'EMP-2026-0003', NULL, '2026-09-09 06:14:05', '2026-09-09 06:15:47'),
(5, 'lhanbert', 'otero', 'lance11@gmail.com', '', '', 'active', 'Inventory', '123456', 'EMP-2026-0004', NULL, '2026-09-21 14:55:39', '2026-09-21 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_documents`
--

CREATE TABLE `applicant_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_account_id` int(10) UNSIGNED NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_replies`
--

CREATE TABLE `applicant_replies` (
  `id` int(11) NOT NULL,
  `hr_email_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT 'Re: (no subject)',
  `body` text NOT NULL,
  `is_read_hr` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'HRMS user that clocked in',
  `pos_user_id` int(11) DEFAULT NULL COMMENT 'POS shadow user that clocked in',
  `source_system` varchar(30) NOT NULL DEFAULT 'hrms',
  `employee_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `late_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Minutes late past 08:00',
  `undertime_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Minutes left before 17:00',
  `proof_file` varchar(255) DEFAULT NULL,
  `status` enum('present','absent','late','half_day','leave','holiday') DEFAULT 'present',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_in_lat` decimal(10,7) DEFAULT NULL,
  `time_in_lon` decimal(10,7) DEFAULT NULL,
  `time_out_lat` decimal(10,7) DEFAULT NULL,
  `time_out_lon` decimal(10,7) DEFAULT NULL,
  `time_in_distance` decimal(10,2) DEFAULT NULL COMMENT 'metres from office at clock-in',
  `time_out_distance` decimal(10,2) DEFAULT NULL COMMENT 'metres from office at clock-out',
  `location_status` varchar(20) NOT NULL DEFAULT 'unknown',
  `remote_deduction` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `pos_user_id`, `source_system`, `employee_id`, `date`, `time_in`, `time_out`, `hours_worked`, `overtime_hours`, `late_minutes`, `undertime_minutes`, `proof_file`, `status`, `remarks`, `created_at`, `time_in_lat`, `time_in_lon`, `time_out_lat`, `time_out_lon`, `time_in_distance`, `time_out_distance`, `location_status`, `remote_deduction`) VALUES
(1, NULL, NULL, 'hrms', 1, '2026-08-05', NULL, NULL, 0.00, 0.00, 0, 0, NULL, 'absent', NULL, '2026-08-05 03:50:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(2, NULL, NULL, 'hrms', 2, '2026-08-05', NULL, NULL, 0.00, 0.00, 0, 0, NULL, 'absent', NULL, '2026-08-05 03:50:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(3, NULL, NULL, 'hrms', 3, '2026-08-05', NULL, NULL, 0.00, 0.00, 0, 0, NULL, 'absent', NULL, '2026-08-05 03:50:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(4, NULL, NULL, 'hrms', 4, '2026-08-05', NULL, NULL, 0.00, 0.00, 0, 0, NULL, 'absent', NULL, '2026-08-05 03:50:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(5, NULL, NULL, 'hrms', 5, '2026-08-05', NULL, NULL, 0.00, 0.00, 0, 0, NULL, 'absent', NULL, '2026-08-05 03:50:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(6, 2, NULL, 'hrms', NULL, '2026-08-05', '07:25:48', NULL, 0.00, 0.00, 0, 0, NULL, 'present', NULL, '2026-08-05 05:25:48', 14.3379834, 120.8860463, NULL, NULL, 5550.35, NULL, 'remote', 0.00),
(7, 1, 1, 'pos', 1, '2026-09-07', '23:11:08', NULL, 0.00, 0.00, 0, 0, NULL, 'present', NULL, '2026-09-07 15:11:08', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00),
(8, 5, 17, 'pos', 6, '2026-09-08', '22:06:44', NULL, 0.00, 0.00, 846, 0, NULL, 'late', NULL, '2026-09-08 14:06:44', NULL, NULL, NULL, NULL, NULL, NULL, 'unknown', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_proofs`
--

CREATE TABLE `attendance_proofs` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL DEFAULT 'image',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `module`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '127.0.0.1', NULL, '2026-08-05 02:47:31'),
(2, 1, 'admin', 'CREATE', 'Employee', 'Created employee: EMP-2024-0001', NULL, NULL, '127.0.0.1', NULL, '2026-08-05 02:47:31'),
(3, 2, 'hr', 'LOGIN', 'Authentication', 'HR logged in', NULL, NULL, '127.0.0.1', NULL, '2026-08-05 02:47:31'),
(4, 2, 'hr', 'UPDATE', 'Leave', 'Approved leave request LV-2024-0002', NULL, NULL, '127.0.0.1', NULL, '2026-08-05 02:47:31'),
(5, 1, 'admin', 'CREATE', 'Payroll', 'Processed payroll for June 2025', NULL, NULL, '127.0.0.1', NULL, '2026-08-05 02:47:31'),
(6, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-08-05 05:26:11'),
(7, 3, 'employee', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-08-05 05:26:18'),
(8, 3, 'employee', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-08-05 05:26:47'),
(9, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-07 05:47:20'),
(10, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 05:47:39'),
(11, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-07 05:47:43'),
(12, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 05:47:57'),
(13, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-07 05:48:42'),
(14, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 05:49:20'),
(15, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-07 13:06:02'),
(16, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 13:06:27'),
(17, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-07 13:06:36'),
(18, 1, 'admin', 'CREATE', 'Recruitment', 'Job posting: Cashier', NULL, NULL, '::1', NULL, '2026-09-07 13:11:13'),
(19, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-07 15:08:09'),
(20, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 15:09:05'),
(21, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-07 15:09:12'),
(22, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-07 16:00:27'),
(23, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:03:18'),
(24, 2, 'hr', 'UPDATE', 'Leave', 'Leave #1 rejected from dashboard', NULL, NULL, '::1', NULL, '2026-09-08 12:03:23'),
(25, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:04:16'),
(26, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:05:59'),
(27, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:06:10'),
(28, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:06:36'),
(29, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:07:09'),
(30, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:10:26'),
(31, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:11:19'),
(32, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:22:53'),
(33, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:27:48'),
(34, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:38:13'),
(35, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:39:37'),
(36, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:39:52'),
(37, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:41:34'),
(38, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:43:51'),
(39, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:51:39'),
(40, 2, 'hr', 'UPDATE', 'Recruitment', 'Applicant #5 status updated to passed', NULL, NULL, '::1', NULL, '2026-09-08 12:51:50'),
(41, 2, 'hr', 'CREATE', 'Deployment', 'Deployed applicant Vincent Garibay as employee EMP-2026-0001 and POS cashier user', NULL, NULL, '::1', NULL, '2026-09-08 12:52:38'),
(42, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:53:14'),
(43, 5, 'garibay', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:53:35'),
(44, 5, 'garibay', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:54:03'),
(45, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 12:55:38'),
(46, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 12:55:51'),
(47, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-08 13:26:22'),
(48, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 13:26:30'),
(49, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 13:46:35'),
(50, 2, 'hr', 'UPDATE', 'Recruitment', 'Applicant #6 status updated to passed', NULL, NULL, '::1', NULL, '2026-09-08 13:47:00'),
(51, 2, 'hr', 'CREATE', 'Deployment', 'Deployed applicant lance otero as employee EMP-2026-0002 and POS inventory_staff user', NULL, NULL, '::1', NULL, '2026-09-08 13:47:19'),
(52, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 13:47:26'),
(53, 6, 'otero', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-08 13:47:31'),
(54, 6, 'otero', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 13:47:38'),
(55, 17, 'garibay', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 14:07:20'),
(56, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-08 14:07:33'),
(57, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 14:08:20'),
(58, 5, 'garibay', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-08 14:08:51'),
(59, 5, 'garibay', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-08 14:09:18'),
(60, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 04:59:55'),
(61, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:02:10'),
(62, 6, 'otero', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:02:54'),
(63, 6, 'otero', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:02:58'),
(64, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:04:47'),
(65, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:05:06'),
(66, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:05:40'),
(67, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:06:03'),
(68, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:11:26'),
(69, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:11:51'),
(70, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:14:43'),
(71, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:29:48'),
(72, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:30:49'),
(73, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:31:23'),
(74, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 05:33:29'),
(75, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 05:47:42'),
(76, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 06:14:50'),
(77, 2, 'hr', 'UPDATE', 'Recruitment', 'Applicant #7 status updated to passed', NULL, NULL, '::1', NULL, '2026-09-09 06:15:08'),
(78, 2, 'hr', 'CREATE', 'Deployment', 'Deployed applicant Lawrence Segunial as employee EMP-2026-0003 and POS cashier user', NULL, NULL, '::1', NULL, '2026-09-09 06:15:47'),
(79, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 06:16:15'),
(80, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-09 06:17:35'),
(81, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-09 06:19:40'),
(82, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-14 05:58:00'),
(83, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-14 05:58:33'),
(84, 16, 'customer', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-15 12:47:38'),
(85, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-15 12:47:45'),
(86, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-18 13:58:48'),
(87, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-18 13:59:18'),
(88, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-21 14:55:58'),
(89, 2, 'hr', 'UPDATE', 'Recruitment', 'Applicant #8 status updated to passed', NULL, NULL, '::1', NULL, '2026-09-21 14:56:13'),
(90, 2, 'hr', 'CREATE', 'Deployment', 'Deployed applicant lhanbert otero as employee EMP-2026-0004 and POS inventory_staff user', NULL, NULL, '::1', NULL, '2026-09-21 14:56:35'),
(91, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-21 14:56:41'),
(92, 8, 'Inventory', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-21 14:56:48'),
(93, 8, 'Inventory', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-21 14:57:02'),
(94, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-26 06:24:53'),
(95, 2, 'hr', 'LOGIN', 'Authentication', 'Hr logged in', NULL, NULL, '::1', NULL, '2026-09-29 09:15:08'),
(96, 2, 'hr', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-29 09:15:14'),
(97, 1, 'admin', 'LOGIN', 'Authentication', 'Admin logged in', NULL, NULL, '::1', NULL, '2026-09-29 09:15:19'),
(98, 1, 'admin', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-29 09:15:25'),
(99, 6, 'otero', 'LOGIN', 'Authentication', 'Employee logged in', NULL, NULL, '::1', NULL, '2026-09-29 09:15:31'),
(100, 6, 'otero', 'LOGOUT', 'Authentication', 'User logged out', NULL, NULL, '::1', NULL, '2026-09-29 09:15:37');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `pos_branch_id` int(11) DEFAULT NULL,
  `branch_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `pos_branch_id`, `branch_code`, `name`, `address`, `city`, `latitude`, `longitude`, `phone`, `manager_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 5, 'BR-MAIN-HQ', 'Main Branch / Head Office', '123 Talisay Street, Brgy. Centro', 'Cebu City', 10.3157000, 123.8854000, NULL, NULL, 'active', '2026-08-05 02:47:32', '2026-09-26 06:53:35'),
(2, 6, 'BR-BH-02', 'North Branch', '456 Mandaue Road, Brgy. Looc', 'Mandaue City', 10.3237000, 123.9223000, NULL, NULL, 'active', '2026-08-05 02:47:32', '2026-09-26 06:53:35'),
(3, 3, 'BR-CAV-01', 'Cavite Branch', 'Emilio Aguinaldo Highway, Brgy. Anabu I-A', 'Imus, Cavite', 14.4297000, 120.9367000, NULL, NULL, 'active', '2026-09-07 05:47:02', '2026-09-26 06:53:35'),
(4, 4, 'BR-BTG-01', 'Batangas Branch', 'P. Burgos Street, Brgy. Poblacion', 'Batangas City', 13.7565000, 121.0583000, NULL, NULL, 'active', '2026-09-07 05:47:02', '2026-09-26 06:53:35');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `contract_number` varchar(30) DEFAULT NULL,
  `contract_type` enum('Regular','Probationary','Contractual','Fixed-Term') DEFAULT 'Probationary',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('active','expired','terminated','renewed') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `employee_id`, `contract_number`, `contract_type`, `start_date`, `end_date`, `department_id`, `position_id`, `salary`, `file_path`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'CON-2020-0001', 'Regular', '2020-01-01', NULL, 1, 1, 80000.00, NULL, 'active', NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(2, 2, 'CON-2021-0001', 'Regular', '2021-03-15', NULL, 1, 2, 50000.00, NULL, 'active', NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(3, 3, 'CON-2019-0001', 'Regular', '2019-06-01', NULL, 2, 5, 75000.00, NULL, 'active', NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(4, 4, 'CON-2022-0001', 'Regular', '2022-01-10', NULL, 3, 8, 45000.00, NULL, 'active', NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(5, 5, 'CON-2018-0001', 'Regular', '2018-09-01', NULL, 4, 9, 85000.00, NULL, 'active', NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(6, 6, 'CON-2026-0001', 'Probationary', '2026-09-08', NULL, 4, 15, 10000.00, NULL, 'active', NULL, 2, '2026-09-08 12:52:38', '2026-09-08 12:52:38'),
(7, 7, 'CON-2026-0002', 'Probationary', '2026-09-08', NULL, 2, 16, 500.00, NULL, 'active', NULL, 2, '2026-09-08 13:47:19', '2026-09-08 13:47:19'),
(8, 8, 'CON-2026-0003', 'Probationary', '2026-09-09', '2026-09-30', 4, 15, 500.00, NULL, 'active', NULL, 2, '2026-09-09 06:15:46', '2026-09-09 06:15:46'),
(9, 9, 'CON-2026-0004', 'Probationary', '2026-09-21', NULL, 2, 16, 500.00, NULL, 'active', NULL, 2, '2026-09-21 14:56:35', '2026-09-21 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `head_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Management & Administration', 'Branch leadership, POS administration and workforce coordination', NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(2, 'Inventory & Supply', 'Ingredients, stock, purchasing and inventory control', NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(3, 'Delivery & Logistics', 'Delivery riders, dispatch and branch logistics', NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(4, 'Store Operations', 'Cashier, counter service and day-to-day milk tea store operations', NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(5, 'Marketing', 'Manages marketing and brand', NULL, 'inactive', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(6, 'Sales', 'Manages sales and revenue', NULL, 'inactive', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(7, 'Legal', 'Manages legal and compliance', NULL, 'inactive', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(8, 'Administration', 'Manages administrative tasks', NULL, 'inactive', '2026-08-05 02:47:31', '2026-09-08 12:10:26');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT 'Single',
  `nationality` varchar(50) DEFAULT 'Filipino',
  `religion` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `employment_status` enum('Regular','Probationary','Contractual','Part-Time','Resigned','Terminated') DEFAULT 'Probationary',
  `employment_type` enum('Full-Time','Part-Time','Contract') DEFAULT 'Full-Time',
  `date_hired` date DEFAULT NULL,
  `date_regularized` date DEFAULT NULL,
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `sss_no` varchar(20) DEFAULT NULL,
  `philhealth_no` varchar(20) DEFAULT NULL,
  `pagibig_no` varchar(20) DEFAULT NULL,
  `tin_no` varchar(20) DEFAULT NULL,
  `gsis_no` varchar(30) DEFAULT NULL COMMENT 'Government Service Insurance System number',
  `profile_picture` varchar(255) DEFAULT 'default.png',
  `highest_education` varchar(100) DEFAULT NULL,
  `school` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `grad_year` year(4) DEFAULT NULL,
  `work_experience` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `firstname`, `lastname`, `middlename`, `gender`, `birthdate`, `civil_status`, `nationality`, `religion`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`, `department_id`, `branch_id`, `position_id`, `employment_status`, `employment_type`, `date_hired`, `date_regularized`, `basic_salary`, `sss_no`, `philhealth_no`, `pagibig_no`, `tin_no`, `gsis_no`, `profile_picture`, `highest_education`, `school`, `course`, `grad_year`, `work_experience`, `skills`, `status`, `created_at`, `updated_at`) VALUES
(1, 'EMP-2024-0001', 'John', 'Doe', 'M', 'Male', '1990-05-15', 'Married', 'Filipino', NULL, 'admin@hrms.com', '09171234567', '123 Main St', 'Manila', NULL, NULL, NULL, NULL, NULL, 4, NULL, 1, 'Regular', 'Full-Time', '2020-01-01', NULL, 80000.00, '12-3456789-0', '12-345678901-2', '123456789012', '123-456-789-000', NULL, 'default.png', 'Bachelor\'s Degree', 'University of Manila', 'Business Administration', NULL, NULL, NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(2, 'EMP-2024-0002', 'Jane', 'Smith', 'A', 'Female', '1992-08-20', 'Single', 'Filipino', NULL, 'jane.smith@hrms.com', '09181234567', '456 Oak Ave', 'Quezon City', NULL, NULL, NULL, NULL, NULL, 4, NULL, 2, 'Regular', 'Full-Time', '2021-03-15', NULL, 50000.00, '23-4567890-1', '23-456789012-3', '234567890123', '234-567-890-001', NULL, 'default.png', 'Bachelor\'s Degree', 'Ateneo de Manila', 'Psychology', NULL, NULL, NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(3, 'EMP-2024-0003', 'Carlos', 'Reyes', 'B', 'Male', '1988-11-10', 'Married', 'Filipino', NULL, 'carlos.reyes@hrms.com', '09191234567', '789 Pine Rd', 'Makati', NULL, NULL, NULL, NULL, NULL, 4, NULL, 5, 'Regular', 'Full-Time', '2019-06-01', NULL, 75000.00, '34-5678901-2', '34-567890123-4', '345678901234', '345-678-901-002', NULL, 'default.png', 'Bachelor\'s Degree', 'DLSU', 'Computer Science', NULL, NULL, NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(4, 'EMP-2024-0004', 'Maria', 'Santos', 'C', 'Female', '1995-03-25', 'Single', 'Filipino', NULL, 'maria.santos@hrms.com', '09201234567', '321 Elm St', 'Pasig', NULL, NULL, NULL, NULL, NULL, 4, NULL, 8, 'Regular', 'Full-Time', '2022-01-10', NULL, 45000.00, '45-6789012-3', '45-678901234-5', '456789012345', '456-789-012-003', NULL, 'default.png', 'Bachelor\'s Degree', 'UP Manila', 'Accounting', NULL, NULL, NULL, 'active', '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(5, 'EMP-2024-0005', 'Pedro', 'Garcia', 'D', 'Male', '1985-07-05', 'Married', 'Filipino', NULL, 'pedro.garcia@hrms.com', '09211234567', '654 Maple Dr', 'Taguig', NULL, NULL, NULL, NULL, NULL, 4, NULL, 9, 'Regular', 'Full-Time', '2018-09-01', NULL, 85000.00, '56-7890123-4', '56-789012345-6', '567890123456', '567-890-123-004', NULL, 'default.png', 'Master\'s Degree', 'AIM', 'Business Management', NULL, NULL, NULL, 'active', '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(6, 'EMP-2026-0001', 'Vincent', 'Garibay', 'Pacardo', 'Male', NULL, 'Single', 'Filipino', NULL, 'sahur@gmail.com', '09319015661', 'BLK 28 LT 9', NULL, NULL, NULL, NULL, NULL, NULL, 4, 1, 15, 'Probationary', 'Full-Time', '2026-09-08', NULL, 10000.00, NULL, NULL, NULL, NULL, NULL, 'default.png', 'Vocational', 'NCST', 'BSIT', NULL, NULL, NULL, 'active', '2026-09-08 12:52:38', '2026-09-08 12:52:38'),
(7, 'EMP-2026-0002', 'lance', 'otero', '', '', NULL, 'Single', 'Filipino', NULL, 'lance1@gmail.com', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 2, 1, 16, 'Probationary', 'Full-Time', '2026-09-08', NULL, 500.00, NULL, NULL, NULL, NULL, NULL, 'default.png', '', '', '', NULL, NULL, NULL, 'active', '2026-09-08 13:47:19', '2026-09-08 13:47:19'),
(8, 'EMP-2026-0003', 'Lawrence', 'Segunial', 'Carreon', 'Male', NULL, 'Single', 'Filipino', NULL, 'lawrence@gmail.com', '09858895721', 'Dasma Bayan', NULL, NULL, NULL, NULL, NULL, NULL, 4, 1, 15, 'Probationary', 'Full-Time', '2026-09-09', NULL, 500.00, NULL, NULL, NULL, NULL, NULL, 'default.png', 'Vocational', 'NCST', 'Bachelor of Science in Computer Science', NULL, NULL, NULL, 'active', '2026-09-09 06:15:46', '2026-09-09 06:15:46'),
(9, 'EMP-2026-0004', 'lhanbert', 'otero', 'P', '', NULL, 'Single', 'Filipino', NULL, 'lance11@gmail.com', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 2, 1, 16, 'Probationary', 'Full-Time', '2026-09-21', NULL, 500.00, NULL, NULL, NULL, NULL, NULL, 'default.png', '', '', '', NULL, NULL, NULL, 'active', '2026-09-21 14:56:35', '2026-09-21 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `finance_entries`
--

CREATE TABLE `finance_entries` (
  `id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `description` text DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hr_emails`
--

CREATE TABLE `hr_emails` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `sender_name` varchar(150) NOT NULL DEFAULT 'HR Department',
  `sender_email` varchar(150) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Emails sent from HR Mailer to individual applicants';

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `interview_type` enum('initial','final','technical','hr') NOT NULL DEFAULT 'initial',
  `interview_date` datetime NOT NULL,
  `interviewer_id` int(11) DEFAULT NULL,
  `interviewer_name` varchar(150) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `mode` enum('in-person','online','phone') DEFAULT 'in-person',
  `result` enum('pending','passed','failed','no_show','rescheduled') DEFAULT 'pending',
  `rating` tinyint(1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `approval_status` enum('pending','admin_approved','approved','rejected') DEFAULT 'pending',
  `admin_approved_by` int(11) DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `hr_approved_by` int(11) DEFAULT NULL,
  `hr_approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interviews`
--

INSERT INTO `interviews` (`id`, `applicant_id`, `interview_type`, `interview_date`, `interviewer_id`, `interviewer_name`, `location`, `mode`, `result`, `rating`, `notes`, `status`, `approval_status`, `admin_approved_by`, `admin_approved_at`, `hr_approved_by`, `hr_approved_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'initial', '2025-07-15 10:00:00', NULL, 'Jane Smith', 'Conference Room A', 'in-person', 'pending', NULL, NULL, 'scheduled', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(2, 4, 'final', '2025-07-18 14:00:00', NULL, 'John Doe', 'Conference Room B', 'in-person', 'pending', NULL, NULL, 'scheduled', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `description` text DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `location` varchar(150) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','discontinued') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment','return') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `adjustment` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'which branch this position is hiring for',
  `position_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `qualifications` text DEFAULT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `slots` int(11) DEFAULT 1,
  `job_type` enum('Full-Time','Part-Time','Contract','Internship') DEFAULT 'Full-Time',
  `status` enum('draft','open','closed','filled') DEFAULT 'draft',
  `posted_by` int(11) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`id`, `title`, `department_id`, `branch_id`, `position_id`, `description`, `requirements`, `qualifications`, `salary_min`, `salary_max`, `slots`, `job_type`, `status`, `posted_by`, `deadline`, `created_at`, `updated_at`) VALUES
(1, 'Cashier', 4, 1, 15, 'Serve customers and process milk tea orders at the POS counter.', 'Customer service and basic cash-handling skills.', NULL, 10000.00, 15000.00, 2, 'Full-Time', 'open', NULL, '2026-09-30', '2026-08-05 02:47:31', '2026-09-07 13:11:13'),
(2, 'Inventory Staff', 2, 1, 16, 'Track ingredients, stock levels, and store-room movements.', 'Inventory or stockroom experience preferred.', NULL, NULL, NULL, 1, 'Full-Time', 'open', NULL, NULL, '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(3, 'POS Super Admin', 1, 1, 17, 'Administer the complete milk tea POS system, including users, products, inventory, transactions, and reports.', 'POS administration and security experience preferred.', NULL, NULL, NULL, 1, 'Full-Time', 'open', NULL, NULL, '2026-08-05 02:47:31', '2026-09-08 12:10:26'),
(4, 'Cashier — Cavite Branch', NULL, 3, NULL, 'Front-of-store cashier for our Cavite branch. Takes orders, handles payments on the POS, and assists customers.', 'Friendly and reliable, comfortable handling cash and basic math. No prior experience required — full training is provided.', NULL, 16000.00, 20000.00, 2, 'Full-Time', 'open', NULL, NULL, '2026-09-07 05:47:02', '2026-09-07 05:47:02');

-- --------------------------------------------------------

--
-- Table structure for table `job_posting_branches`
--

CREATE TABLE `job_posting_branches` (
  `job_posting_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_posting_branches`
--

INSERT INTO `job_posting_branches` (`job_posting_id`, `branch_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 3);

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance`
--

CREATE TABLE `leave_balance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_days` decimal(5,1) DEFAULT 0.0,
  `used_days` decimal(5,1) DEFAULT 0.0,
  `remaining_days` decimal(5,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_balance`
--

INSERT INTO `leave_balance` (`id`, `employee_id`, `leave_type_id`, `year`, `total_days`, `used_days`, `remaining_days`) VALUES
(1, 1, 1, '2025', 15.0, 0.0, 15.0),
(2, 1, 2, '2025', 15.0, 0.0, 15.0),
(3, 1, 3, '2025', 3.0, 0.0, 3.0),
(4, 2, 1, '2025', 15.0, 0.0, 15.0),
(5, 2, 2, '2025', 15.0, 0.0, 15.0),
(6, 2, 3, '2025', 3.0, 0.0, 3.0),
(7, 3, 1, '2025', 15.0, 3.0, 12.0),
(8, 3, 2, '2025', 15.0, 0.0, 15.0),
(9, 3, 3, '2025', 3.0, 0.0, 3.0),
(10, 4, 1, '2025', 15.0, 0.0, 15.0),
(11, 4, 2, '2025', 15.0, 5.0, 10.0),
(12, 4, 3, '2025', 3.0, 0.0, 3.0),
(13, 5, 1, '2025', 15.0, 0.0, 15.0),
(14, 5, 2, '2025', 15.0, 0.0, 15.0),
(15, 5, 3, '2025', 3.0, 1.0, 2.0),
(16, 6, 1, '2026', 15.0, 0.0, 15.0),
(17, 6, 2, '2026', 15.0, 0.0, 15.0),
(18, 6, 3, '2026', 3.0, 0.0, 3.0),
(19, 6, 4, '2026', 105.0, 0.0, 105.0),
(20, 6, 5, '2026', 7.0, 0.0, 7.0),
(21, 6, 6, '2026', 3.0, 0.0, 3.0),
(22, 6, 7, '2026', 5.0, 0.0, 5.0),
(23, 7, 1, '2026', 15.0, 0.0, 15.0),
(24, 7, 2, '2026', 15.0, 0.0, 15.0),
(25, 7, 3, '2026', 3.0, 0.0, 3.0),
(26, 7, 4, '2026', 105.0, 0.0, 105.0),
(27, 7, 5, '2026', 7.0, 0.0, 7.0),
(28, 7, 6, '2026', 3.0, 0.0, 3.0),
(29, 7, 7, '2026', 5.0, 0.0, 5.0),
(30, 8, 1, '2026', 15.0, 0.0, 15.0),
(31, 8, 2, '2026', 15.0, 0.0, 15.0),
(32, 8, 3, '2026', 3.0, 0.0, 3.0),
(33, 8, 4, '2026', 105.0, 0.0, 105.0),
(34, 8, 5, '2026', 7.0, 0.0, 7.0),
(35, 8, 6, '2026', 3.0, 0.0, 3.0),
(36, 8, 7, '2026', 5.0, 0.0, 5.0),
(37, 9, 1, '2026', 15.0, 0.0, 15.0),
(38, 9, 2, '2026', 15.0, 0.0, 15.0),
(39, 9, 3, '2026', 3.0, 0.0, 3.0),
(40, 9, 4, '2026', 105.0, 0.0, 105.0),
(41, 9, 5, '2026', 7.0, 0.0, 7.0),
(42, 9, 6, '2026', 3.0, 0.0, 3.0),
(43, 9, 7, '2026', 5.0, 0.0, 5.0);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `leave_code` varchar(30) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(4,1) DEFAULT 0.0,
  `reason` text DEFAULT NULL,
  `status` enum('pending','admin_approved','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_approved_by` int(11) DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `file_attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `leave_code`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `admin_approved_by`, `admin_approved_at`, `approved_by`, `approved_at`, `remarks`, `file_attachment`, `created_at`, `updated_at`) VALUES
(1, 'LV-2024-0001', 3, 1, '2025-07-20', '2025-07-22', 3.0, 'Medical check-up and recovery', 'rejected', NULL, NULL, 2, '2026-09-08 20:03:23', NULL, NULL, '2026-08-05 02:47:31', '2026-09-08 12:03:23'),
(2, 'LV-2024-0002', 4, 2, '2025-08-01', '2025-08-05', 5.0, 'Family vacation', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(3, 'LV-2024-0003', 5, 3, '2025-07-10', '2025-07-10', 1.0, 'Family emergency', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `days_allowed` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `days_allowed`, `description`, `status`) VALUES
(1, 'Sick Leave', 15, 'Leave due to illness or medical appointment', 'active'),
(2, 'Vacation Leave', 15, 'Annual vacation or personal leave', 'active'),
(3, 'Emergency Leave', 3, 'Unforeseeable emergency situations', 'active'),
(4, 'Maternity Leave', 105, 'Maternity leave for female employees', 'active'),
(5, 'Paternity Leave', 7, 'Paternity leave for male employees', 'active'),
(6, 'Bereavement Leave', 3, 'Leave due to death of a family member', 'active'),
(7, 'Service Incentive Leave', 5, 'Mandatory SIL per Labor Code', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `mailer_accounts`
--

CREATE TABLE `mailer_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `fullname` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Standalone accounts for the HR Mailer module (separate from users table)';

--
-- Dumping data for table `mailer_accounts`
--

INSERT INTO `mailer_accounts` (`id`, `username`, `password`, `email`, `fullname`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'hrmailer', '$2y$10$VZQ3p7X1rQnA/FqNvUzS3.HWtkPVbEODWm1kApnTZN7ZSuHjI.9Ni', 'hr@hrms.local', 'HR Mailer Admin', 'active', NULL, '2026-08-05 02:47:32', '2026-08-05 02:47:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `applicant_account_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `applicant_account_id`, `title`, `message`, `type`, `is_read`, `link`, `created_at`) VALUES
(1, 1, NULL, 'New Leave Request', 'Employee Carlos Reyes submitted a leave request', 'info', 1, NULL, '2026-08-05 02:47:31'),
(2, 2, NULL, 'Interview Scheduled', 'Interview scheduled for Ana Cruz on July 15', 'info', 1, NULL, '2026-08-05 02:47:31'),
(3, 1, NULL, 'Payroll Processed', 'June 2025 payroll has been processed successfully', 'success', 1, NULL, '2026-08-05 02:47:31'),
(4, 1, NULL, 'New Job Application', 'Vincent Garibay (sahur@gmail.com) applied for Cashier.', 'info', 1, NULL, '2026-09-08 12:40:34'),
(5, 2, NULL, 'New Job Application', 'Vincent Garibay (sahur@gmail.com) applied for Cashier.', 'info', 1, NULL, '2026-09-08 12:40:34'),
(6, 4, NULL, 'New Job Application', 'Vincent Garibay (sahur@gmail.com) applied for Cashier.', 'info', 0, NULL, '2026-09-08 12:40:34'),
(7, NULL, 2, '🎉 Congratulations — You\'re Hired!', 'Your application has been approved and your HRMS employee account is ready.\n\n  • Employee ID : EMP-2026-0001\n  • Username    : garibay\n  • Password    : 123456 (temporary — please change after first login)\n\nLog in at: /hrms/index.php\n\nWelcome to the team!', 'success', 1, NULL, '2026-09-08 12:52:38'),
(8, 1, NULL, 'New Job Application', 'lance otero (lance1@gmail.com) applied for Inventory Staff.', 'info', 1, NULL, '2026-09-08 13:46:07'),
(9, 2, NULL, 'New Job Application', 'lance otero (lance1@gmail.com) applied for Inventory Staff.', 'info', 1, NULL, '2026-09-08 13:46:07'),
(10, 4, NULL, 'New Job Application', 'lance otero (lance1@gmail.com) applied for Inventory Staff.', 'info', 0, NULL, '2026-09-08 13:46:07'),
(11, NULL, 3, '🎉 Congratulations — You\'re Hired!', 'Your application has been approved and your HRMS employee account is ready.\n\n  • Employee ID : EMP-2026-0002\n  • Username    : otero\n  • Password    : 123456 (temporary — please change after first login)\n\nLog in at: /hrms/index.php\n\nWelcome to the team!', 'success', 0, NULL, '2026-09-08 13:47:19'),
(12, 1, NULL, 'New Job Application', 'Lawrence Segunial (lawrence@gmail.com) applied for Cashier.', 'info', 1, NULL, '2026-09-09 06:14:06'),
(13, 2, NULL, 'New Job Application', 'Lawrence Segunial (lawrence@gmail.com) applied for Cashier.', 'info', 1, NULL, '2026-09-09 06:14:06'),
(14, 4, NULL, 'New Job Application', 'Lawrence Segunial (lawrence@gmail.com) applied for Cashier.', 'info', 0, NULL, '2026-09-09 06:14:06'),
(15, NULL, 4, '🎉 Congratulations — You\'re Hired!', 'Your application has been approved and your HRMS employee account is ready.\n\n  • Employee ID : EMP-2026-0003\n  • Username    : law\n  • Password    : 123456 (temporary — please change after first login)\n\nLog in at: /hrms/index.php\n\nWelcome to the team!', 'success', 1, NULL, '2026-09-09 06:15:47'),
(16, 1, NULL, 'New Job Application', 'lhanbert otero (lance11@gmail.com) applied for Inventory Staff.', 'info', 1, NULL, '2026-09-21 14:55:39'),
(17, 2, NULL, 'New Job Application', 'lhanbert otero (lance11@gmail.com) applied for Inventory Staff.', 'info', 1, NULL, '2026-09-21 14:55:39'),
(18, 4, NULL, 'New Job Application', 'lhanbert otero (lance11@gmail.com) applied for Inventory Staff.', 'info', 0, NULL, '2026-09-21 14:55:39'),
(19, NULL, 5, '🎉 Congratulations — You\'re Hired!', 'Your application has been approved and your HRMS employee account is ready.\n\n  • Employee ID : EMP-2026-0004\n  • Username    : Inventory\n  • Password    : 123456 (temporary — please change after first login)\n\nLog in at: /hrms/index.php\n\nWelcome to the team!', 'success', 0, NULL, '2026-09-21 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `payroll_code` varchar(30) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_month` tinyint(2) NOT NULL,
  `payroll_year` year(4) NOT NULL,
  `pay_period` enum('monthly','semi-monthly','weekly') DEFAULT 'monthly',
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `allowances` decimal(12,2) DEFAULT 0.00,
  `overtime_pay` decimal(12,2) DEFAULT 0.00,
  `bonuses` decimal(12,2) DEFAULT 0.00,
  `gross_pay` decimal(12,2) DEFAULT 0.00,
  `sss_deduction` decimal(10,2) DEFAULT 0.00,
  `philhealth_deduction` decimal(10,2) DEFAULT 0.00,
  `pagibig_deduction` decimal(10,2) DEFAULT 0.00,
  `gsis_deduction` decimal(10,2) DEFAULT 0.00,
  `tax_deduction` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `net_pay` decimal(12,2) DEFAULT 0.00,
  `days_worked` decimal(4,1) DEFAULT 0.0,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('draft','processed','released') DEFAULT 'draft',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `payroll_code`, `employee_id`, `payroll_month`, `payroll_year`, `pay_period`, `basic_salary`, `allowances`, `overtime_pay`, `bonuses`, `gross_pay`, `sss_deduction`, `philhealth_deduction`, `pagibig_deduction`, `gsis_deduction`, `tax_deduction`, `other_deductions`, `total_deductions`, `net_pay`, `days_worked`, `overtime_hours`, `status`, `processed_by`, `processed_at`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'PAY-2025-06-001', 1, 6, '2025', 'monthly', 80000.00, 5000.00, 0.00, 0.00, 85000.00, 1350.00, 900.00, 100.00, 0.00, 12000.00, 0.00, 14350.00, 70650.00, 22.0, 0.00, 'released', NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(2, 'PAY-2025-06-002', 2, 6, '2025', 'monthly', 50000.00, 3000.00, 0.00, 0.00, 53000.00, 900.00, 562.50, 100.00, 0.00, 6000.00, 0.00, 7562.50, 45437.50, 22.0, 0.00, 'released', NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(3, 'PAY-2025-06-003', 3, 6, '2025', 'monthly', 75000.00, 4000.00, 2000.00, 0.00, 81000.00, 1350.00, 843.75, 100.00, 0.00, 10500.00, 0.00, 12793.75, 68206.25, 22.0, 0.00, 'released', NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(4, 'PAY-2025-06-004', 4, 6, '2025', 'monthly', 45000.00, 2500.00, 0.00, 0.00, 47500.00, 900.00, 506.25, 100.00, 0.00, 5000.00, 0.00, 6506.25, 40993.75, 22.0, 0.00, 'released', NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31'),
(5, 'PAY-2025-06-005', 5, 6, '2025', 'monthly', 85000.00, 5000.00, 3000.00, 5000.00, 98000.00, 1350.00, 956.25, 100.00, 0.00, 14000.00, 0.00, 16406.25, 81593.75, 22.0, 0.00, 'released', NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-08-05 02:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `min_salary` decimal(12,2) DEFAULT 0.00,
  `max_salary` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `title`, `department_id`, `description`, `min_salary`, `max_salary`, `status`, `created_at`) VALUES
(1, 'HR Manager', 1, NULL, 50000.00, 80000.00, 'inactive', '2026-08-05 02:47:31'),
(2, 'HR Officer', 1, NULL, 30000.00, 50000.00, 'inactive', '2026-08-05 02:47:31'),
(3, 'HR Assistant', 1, NULL, 20000.00, 30000.00, 'inactive', '2026-08-05 02:47:31'),
(4, 'IT Manager', 2, NULL, 60000.00, 100000.00, 'inactive', '2026-08-05 02:47:31'),
(5, 'Senior Developer', 2, NULL, 45000.00, 80000.00, 'inactive', '2026-08-05 02:47:31'),
(6, 'Junior Developer', 2, NULL, 25000.00, 45000.00, 'inactive', '2026-08-05 02:47:31'),
(7, 'Finance Manager', 3, NULL, 55000.00, 90000.00, 'inactive', '2026-08-05 02:47:31'),
(8, 'Accountant', 3, NULL, 30000.00, 55000.00, 'inactive', '2026-08-05 02:47:31'),
(9, 'Operations Manager', 4, NULL, 50000.00, 85000.00, 'inactive', '2026-08-05 02:47:31'),
(10, 'Marketing Manager', 5, NULL, 45000.00, 75000.00, 'inactive', '2026-08-05 02:47:31'),
(11, 'Sales Manager', 6, NULL, 40000.00, 70000.00, 'inactive', '2026-08-05 02:47:31'),
(12, 'Sales Representative', 6, NULL, 20000.00, 40000.00, 'inactive', '2026-08-05 02:47:31'),
(13, 'Legal Counsel', 7, NULL, 60000.00, 100000.00, 'inactive', '2026-08-05 02:47:31'),
(14, 'Admin Officer', 8, NULL, 20000.00, 35000.00, 'inactive', '2026-08-05 02:47:31'),
(15, 'Cashier', 4, 'POS counter cashier', 14000.00, 22000.00, 'active', '2026-08-05 02:47:31'),
(16, 'Inventory Staff', 2, 'POS inventory and stock control', 14000.00, 22000.00, 'active', '2026-08-05 02:47:31'),
(17, 'POS Super Admin', 1, 'Full POS administration and access control', 20000.00, 35000.00, 'active', '2026-08-05 02:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `pos_job_postings_meta`
--

CREATE TABLE `pos_job_postings_meta` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_posting_id` int(11) NOT NULL,
  `pos_role_target` enum('super_admin','cashier','inventory_staff') NOT NULL DEFAULT 'cashier',
  `pos_position_label` varchar(100) NOT NULL DEFAULT 'POS Staff',
  `created_by` int(11) DEFAULT NULL COMMENT 'HRMS users.id of the HR who tagged it',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_job_postings_meta`
--

INSERT INTO `pos_job_postings_meta` (`id`, `job_posting_id`, `pos_role_target`, `pos_position_label`, `created_by`, `created_at`) VALUES
(1, 4, 'cashier', 'Cashier', NULL, '2026-09-07 05:47:02');

-- --------------------------------------------------------

--
-- Table structure for table `pos_role_applications`
--

CREATE TABLE `pos_role_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `pos_user_id` int(11) NOT NULL COMMENT 'milk_tea_pos.users.id',
  `pos_username` varchar(100) NOT NULL,
  `pos_email` varchar(150) DEFAULT NULL,
  `job_posting_id` int(11) NOT NULL,
  `cover_note` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'HRMS users.id',
  `reviewed_at` datetime DEFAULT NULL,
  `hr_notes` text DEFAULT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_role_upgrade_log`
--

CREATE TABLE `pos_role_upgrade_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `hrms_applicant_id` int(11) DEFAULT NULL COMMENT 'applicants.id — null if not linked',
  `hrms_user_id` int(11) DEFAULT NULL COMMENT 'employees.id — reserved for future use',
  `pos_user_id` int(11) NOT NULL COMMENT 'milk_tea_pos.users.id',
  `pos_username` varchar(100) NOT NULL,
  `pos_email` varchar(150) DEFAULT NULL,
  `old_pos_role` varchar(50) DEFAULT NULL,
  `new_pos_role` varchar(50) NOT NULL,
  `hrms_role_unchanged` varchar(50) NOT NULL DEFAULT 'employee',
  `job_posting_id` int(11) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL COMMENT 'HRMS users.id',
  `performed_by_username` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resignation_letters`
--

CREATE TABLE `resignation_letters` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `last_working_day` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `letter_body` text NOT NULL,
  `status` enum('pending','admin_approved','approved','rejected') DEFAULT 'pending',
  `admin_approved_by` int(11) DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `hr_remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` smallint(6) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL DEFAULT '',
  `smtp_password` varchar(255) NOT NULL DEFAULT '',
  `smtp_from` varchar(255) NOT NULL DEFAULT '',
  `smtp_name` varchar(150) NOT NULL DEFAULT 'BobaHouse HR',
  `smtp_secure` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `smtp_auth` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_from`, `smtp_name`, `smtp_secure`, `smtp_auth`, `updated_at`) VALUES
(1, 'smtp.gmail.com', 587, '', '', 'noreply@bobahouse.com', 'BobaHouse HR', 'tls', 1, '2026-08-05 02:47:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` enum('admin','hr','inventory_staff','employee') NOT NULL DEFAULT 'employee',
  `pos_role` enum('super_admin','cashier','inventory_staff') DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'for hr/admin: the one branch they manage; NULL = all branches (head office)',
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive','pending','admin_approved','rejected') DEFAULT 'active',
  `admin_approved_by` int(11) DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `pos_role`, `branch_id`, `firstname`, `lastname`, `phone`, `employee_id`, `last_login`, `status`, `admin_approved_by`, `admin_approved_at`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2b$10$O0lYwf1x0i14L1In58dOwOMwv2tmljur1vhqh/swKgsVrIwQnCY6m', 'admin@hrms.com', 'admin', NULL, NULL, NULL, NULL, NULL, 1, '2026-09-29 17:15:19', 'active', NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-09-29 09:15:19'),
(2, 'hr', '$2b$10$UsYLP50d6udTSK68rQQUqeXfczzSzswRW4t4y7rj/KkkbP6mu7FLq', 'jane.smith@hrms.com', 'hr', NULL, NULL, NULL, NULL, NULL, 2, '2026-09-29 17:15:08', 'active', NULL, NULL, NULL, NULL, NULL, '2026-08-05 02:47:31', '2026-09-29 09:15:08'),
(4, 'hr_cavite', '$2b$10$UsYLP50d6udTSK68rQQUqeXfczzSzswRW4t4y7rj/KkkbP6mu7FLq', 'cavite.hr@hrms.com', 'hr', NULL, 3, 'Cavite', 'Branch HR', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, NULL, '2026-09-07 05:47:02', '2026-09-07 05:47:02'),
(5, 'garibay', '$2y$10$26HaznTxbBbX9cenG7DPQehtrIAGSijmT1/qvPWBWq9/mthgPkhdu', 'sahur@gmail.com', 'employee', 'cashier', NULL, 'Vincent', 'Garibay', '09319015661', 6, '2026-09-08 22:08:51', 'active', NULL, NULL, 2, '2026-09-08 20:52:38', NULL, '2026-09-08 12:52:38', '2026-09-08 14:08:51'),
(6, 'otero', '$2y$10$0me3wH1iLEvVMpl4RnFj1e8Mvdg9y9aOO/994Jy.x9fWkf0O4oNfK', 'lance1@gmail.com', 'employee', 'inventory_staff', NULL, 'lance', 'otero', '', 7, '2026-09-29 17:15:31', 'active', NULL, NULL, 2, '2026-09-08 21:47:19', NULL, '2026-09-08 13:47:19', '2026-09-29 09:15:31'),
(7, 'law', '$2y$10$Uq8pE.uyAaF.IUOIE04TYOVJve2LcT9OPtpNQdaIMxQQvxWvga3by', 'lawrence@gmail.com', 'employee', 'cashier', NULL, 'Lawrence', 'Segunial', '09858895721', 8, NULL, 'active', NULL, NULL, 2, '2026-09-09 14:15:47', NULL, '2026-09-09 06:15:47', '2026-09-09 06:15:47'),
(8, 'Inventory', '$2y$10$DV6tAcF4k6nSv6rgLo2aS.WsNfZSqbJNaOaBD22lBd9nU6xKKlhl6', 'lance11@gmail.com', 'employee', 'inventory_staff', NULL, 'lhanbert', 'otero', '', 9, '2026-09-21 22:56:48', 'active', NULL, NULL, 2, '2026-09-21 22:56:35', NULL, '2026-09-21 14:56:35', '2026-09-21 14:56:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `idx_applicant_account_id` (`applicant_account_id`);

--
-- Indexes for table `applicant_accounts`
--
ALTER TABLE `applicant_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_applicant_account_email` (`email`);

--
-- Indexes for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_applicant_account` (`applicant_account_id`);

--
-- Indexes for table `applicant_replies`
--
ALTER TABLE `applicant_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hr_email_id` (`hr_email_id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_date` (`employee_id`,`date`),
  ADD UNIQUE KEY `user_date` (`user_id`,`date`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_att_user_id` (`user_id`);

--
-- Indexes for table `attendance_proofs`
--
ALTER TABLE `attendance_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`),
  ADD UNIQUE KEY `uq_hrms_pos_branch` (`pos_branch_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `position_id` (`position_id`);

--
-- Indexes for table `finance_entries`
--
ALTER TABLE `finance_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entry_date` (`entry_date`),
  ADD KEY `type` (`type`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `hr_emails`
--
ALTER TABLE `hr_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `job_posting_branches`
--
ALTER TABLE `job_posting_branches`
  ADD PRIMARY KEY (`job_posting_id`,`branch_id`);

--
-- Indexes for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_type_year` (`employee_id`,`leave_type_id`,`year`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mailer_accounts`
--
ALTER TABLE `mailer_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mailer_username` (`username`),
  ADD UNIQUE KEY `uniq_mailer_email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notif_applicant_account_id` (`applicant_account_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `pos_job_postings_meta`
--
ALTER TABLE `pos_job_postings_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_posting` (`job_posting_id`);

--
-- Indexes for table `pos_role_applications`
--
ALTER TABLE `pos_role_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_user_job` (`pos_user_id`,`job_posting_id`);

--
-- Indexes for table `pos_role_upgrade_log`
--
ALTER TABLE `pos_role_upgrade_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_applicant` (`hrms_applicant_id`),
  ADD KEY `idx_pos_user` (`pos_user_id`);

--
-- Indexes for table `resignation_letters`
--
ALTER TABLE `resignation_letters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `applicant_accounts`
--
ALTER TABLE `applicant_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_replies`
--
ALTER TABLE `applicant_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `attendance_proofs`
--
ALTER TABLE `attendance_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `finance_entries`
--
ALTER TABLE `finance_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hr_emails`
--
ALTER TABLE `hr_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leave_balance`
--
ALTER TABLE `leave_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `mailer_accounts`
--
ALTER TABLE `mailer_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `pos_job_postings_meta`
--
ALTER TABLE `pos_job_postings_meta`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pos_role_applications`
--
ALTER TABLE `pos_role_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_role_upgrade_log`
--
ALTER TABLE `pos_role_upgrade_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resignation_letters`
--
ALTER TABLE `resignation_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_app_job` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applicants_account` FOREIGN KEY (`applicant_account_id`) REFERENCES `applicant_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applicant_replies`
--
ALTER TABLE `applicant_replies`
  ADD CONSTRAINT `fk_reply_app` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reply_hremail` FOREIGN KEY (`hr_email_id`) REFERENCES `hr_emails` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_con_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_doc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_emp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emp_pos` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hr_emails`
--
ALTER TABLE `hr_emails`
  ADD CONSTRAINT `fk_hremail_app` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `fk_int_app` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `fk_job_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD CONSTRAINT `fk_lb_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_lr_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lr_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_applicant` FOREIGN KEY (`applicant_account_id`) REFERENCES `applicant_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_pay_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `fk_pos_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_job_postings_meta`
--
ALTER TABLE `pos_job_postings_meta`
  ADD CONSTRAINT `fk_pos_meta_job` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resignation_letters`
--
ALTER TABLE `resignation_letters`
  ADD CONSTRAINT `fk_res_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
