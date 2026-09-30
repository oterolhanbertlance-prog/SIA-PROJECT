-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 05:00 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `finance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `account_group_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `code`, `name`, `account_group_id`, `is_active`, `created_at`) VALUES
(1, '1000', 'Cash', 1, 1, '2026-09-07 05:46:56'),
(2, '1010', 'Bank Accounts', 2, 1, '2026-09-07 05:46:56'),
(3, '1020', 'E-Wallets', 3, 1, '2026-09-07 05:46:56'),
(4, '1100', 'Accounts Receivable', 4, 1, '2026-09-07 05:46:56'),
(5, '1200', 'Inventory', 5, 1, '2026-09-07 05:46:56'),
(6, '1500', 'Property and Equipment', 6, 1, '2026-09-07 05:46:56'),
(7, '1600', 'Accumulated Depreciation', 7, 1, '2026-09-07 05:46:56'),
(8, '2000', 'Accounts Payable', 8, 1, '2026-09-07 05:46:56'),
(9, '2100', 'Payroll Payable', 9, 1, '2026-09-07 05:46:56'),
(10, '2200', 'SSS Payable', 9, 1, '2026-09-07 05:46:56'),
(11, '2210', 'PhilHealth Payable', 9, 1, '2026-09-07 05:46:56'),
(12, '2220', 'Pag-IBIG Payable', 9, 1, '2026-09-07 05:46:56'),
(13, '2230', 'Withholding Tax Payable', 10, 1, '2026-09-07 05:46:56'),
(14, '2300', 'Other Current Liabilities', 10, 1, '2026-09-07 05:46:56'),
(15, '3000', 'Owner / Shareholders Equity', 11, 1, '2026-09-07 05:46:56'),
(16, '3100', 'Retained Earnings', 11, 1, '2026-09-07 05:46:56'),
(17, '4000', 'Sales Revenue', 12, 1, '2026-09-07 05:46:56'),
(18, '4100', 'Other Income', 12, 1, '2026-09-07 05:46:56'),
(19, '5000', 'Cost of Goods Sold', 13, 1, '2026-09-07 05:46:56'),
(20, '5100', 'Payroll Expense', 14, 1, '2026-09-07 05:46:56'),
(21, '5200', 'Rent Expense', 14, 1, '2026-09-07 05:46:56'),
(22, '5300', 'Utilities', 14, 1, '2026-09-07 05:46:56'),
(23, '5400', 'Supplies', 14, 1, '2026-09-07 05:46:56'),
(24, '5500', 'Marketing', 14, 1, '2026-09-07 05:46:56'),
(25, '5600', 'Depreciation', 14, 1, '2026-09-07 05:46:56'),
(26, '5700', 'Repairs and Maintenance', 14, 1, '2026-09-07 05:46:56'),
(27, '5800', 'Transportation', 14, 1, '2026-09-07 05:46:56'),
(28, '5900', 'Other Operating Expenses', 14, 1, '2026-09-07 05:46:56');

-- --------------------------------------------------------

--
-- Table structure for table `account_groups`
--

CREATE TABLE `account_groups` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `normal_balance` enum('debit','credit') NOT NULL,
  `statement_group` enum('asset','liability','equity','revenue','expense') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_groups`
--

INSERT INTO `account_groups` (`id`, `name`, `normal_balance`, `statement_group`) VALUES
(1, 'Cash and Cash Equivalents', 'debit', 'asset'),
(2, 'Bank Accounts', 'debit', 'asset'),
(3, 'E-Wallets', 'debit', 'asset'),
(4, 'Accounts Receivable', 'debit', 'asset'),
(5, 'Inventory', 'debit', 'asset'),
(6, 'Property and Equipment', 'debit', 'asset'),
(7, 'Accumulated Depreciation', 'credit', 'asset'),
(8, 'Accounts Payable', 'credit', 'liability'),
(9, 'Payroll Liabilities', 'credit', 'liability'),
(10, 'Tax Liabilities', 'credit', 'liability'),
(11, 'Owner Equity', 'credit', 'equity'),
(12, 'Revenue', 'credit', 'revenue'),
(13, 'Cost of Goods Sold', 'debit', 'expense'),
(14, 'Operating Expenses', 'debit', 'expense');

-- --------------------------------------------------------

--
-- Table structure for table `ap_bills`
--

CREATE TABLE `ap_bills` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bill_number` varchar(40) NOT NULL,
  `vendor_id` int(10) UNSIGNED NOT NULL,
  `bill_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` enum('draft','posted','paid','void') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ar_invoices`
--

CREATE TABLE `ar_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(40) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` enum('draft','posted','paid','void') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_snapshot_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `geo_latitude` decimal(10,7) DEFAULT NULL,
  `geo_longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('present','late','absent','on_leave','half_day') NOT NULL DEFAULT 'present',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `module` varchar(60) NOT NULL,
  `record_type` varchar(80) DEFAULT NULL,
  `record_id` varchar(100) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `record_type`, `record_id`, `old_data`, `new_data`, `ip_address`, `created_at`) VALUES
(1, NULL, 'create', 'auth', 'user', '1', NULL, '{\"username\":\"admin\"}', '::1', '2026-09-21 13:02:12'),
(2, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-21 13:02:20'),
(3, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-21 13:02:21'),
(4, 1, 'logout', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-21 13:22:36'),
(5, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-21 13:22:50'),
(6, 1, 'import', 'integration', 'journal_entry', '1', NULL, '{\"source_system\":\"hrms\",\"source_id\":\"payroll:5\"}', '::1', '2026-09-21 13:22:57'),
(7, 1, 'import', 'integration', 'journal_entry', '2', NULL, '{\"source_system\":\"hrms\",\"source_id\":\"payroll:4\"}', '::1', '2026-09-21 13:22:57'),
(8, 1, 'import', 'integration', 'journal_entry', '3', NULL, '{\"source_system\":\"hrms\",\"source_id\":\"payroll:3\"}', '::1', '2026-09-21 13:22:57'),
(9, 1, 'import', 'integration', 'journal_entry', '4', NULL, '{\"source_system\":\"hrms\",\"source_id\":\"payroll:2\"}', '::1', '2026-09-21 13:22:57'),
(10, 1, 'import', 'integration', 'journal_entry', '5', NULL, '{\"source_system\":\"hrms\",\"source_id\":\"payroll:1\"}', '::1', '2026-09-21 13:22:57'),
(11, 1, 'import', 'integration', 'journal_entry', '6', NULL, '{\"source_system\":\"pos\",\"source_id\":\"transaction:4\"}', '::1', '2026-09-21 13:22:57'),
(12, 1, 'sync', 'integration', 'sync', 'sync_everything', NULL, '{\"branches\":{\"imported\":0,\"updated\":8,\"failed\":0},\"accounts\":{\"imported\":2,\"skipped\":0,\"failed\":2},\"hrms\":{\"employees\":{\"imported\":6,\"skipped\":0,\"failed\":0},\"payroll\":{\"imported\":5,\"skipped\":0,\"failed\":0}},\"pos\":{\"sales\":{\"imported\":1,\"skipped\":0,\"failed\":0}}}', '::1', '2026-09-21 13:22:57'),
(13, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-22 05:04:12'),
(14, 1, 'sync', 'integration', 'sync', 'sync_everything', NULL, '{\"branches\":{\"imported\":0,\"updated\":8,\"failed\":0},\"accounts\":{\"imported\":0,\"skipped\":2,\"failed\":2},\"hrms\":{\"employees\":{\"imported\":0,\"skipped\":6,\"failed\":0},\"payroll\":{\"imported\":0,\"skipped\":5,\"failed\":0}},\"pos\":{\"sales\":{\"imported\":0,\"skipped\":1,\"failed\":0}}}', '::1', '2026-09-22 05:06:45'),
(15, 1, 'logout', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-22 05:07:03'),
(16, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-22 05:12:48'),
(17, 1, 'logout', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-22 05:14:26'),
(18, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-29 14:47:48'),
(19, 1, 'logout', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-29 14:51:11'),
(20, NULL, 'login', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-29 14:55:30'),
(21, 1, 'logout', 'auth', 'user', '1', NULL, NULL, '::1', '2026-09-29 14:59:40');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_system` varchar(40) DEFAULT NULL,
  `source_id` varchar(80) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `branch_code` varchar(40) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `manager_name` varchar(150) DEFAULT NULL,
  `hrms_branch_id` int(11) DEFAULT NULL,
  `pos_branch_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `source_system`, `source_id`, `name`, `address`, `is_active`, `branch_code`, `city`, `latitude`, `longitude`, `phone`, `manager_name`, `hrms_branch_id`, `pos_branch_id`, `updated_at`) VALUES
(1, 'hrms', 'branch:1', 'Main Branch / Head Office', '123 Talisay Street, Brgy. Centro', 1, 'BR-MAIN-HQ', 'Cebu City', '10.3157000', '123.8854000', NULL, NULL, 1, 5, '2026-09-21 13:02:30'),
(2, 'hrms', 'branch:2', 'North Branch', '456 Mandaue Road, Brgy. Looc', 1, 'BR-BH-02', 'Mandaue City', '10.3237000', '123.9223000', NULL, NULL, 2, 6, '2026-09-21 13:02:30'),
(3, 'hrms', 'branch:3', 'Cavite Branch', 'Emilio Aguinaldo Highway, Brgy. Anabu I-A', 1, 'BR-CAV-01', 'Imus, Cavite', '14.4297000', '120.9367000', NULL, NULL, 3, 3, '2026-09-21 13:02:30'),
(4, 'hrms', 'branch:4', 'Batangas Branch', 'P. Burgos Street, Brgy. Poblacion', 1, 'BR-BTG-01', 'Batangas City', '13.7565000', '121.0583000', NULL, NULL, 4, 4, '2026-09-21 13:02:30');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `budget_year` year(4) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('draft','approved','closed') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_lines`
--

CREATE TABLE `budget_lines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `budget_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `month_number` tinyint(3) UNSIGNED NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_centers`
--

CREATE TABLE `cost_centers` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `source_system` varchar(40) DEFAULT NULL,
  `source_id` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_snapshots`
--

CREATE TABLE `employee_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` varchar(100) NOT NULL,
  `employee_code` varchar(40) DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `department_name` varchar(120) DEFAULT NULL,
  `position_title` varchar(120) DEFAULT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `basic_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
  `source_updated_at` datetime DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `source_branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_snapshots`
--

INSERT INTO `employee_snapshots` (`id`, `source_id`, `employee_code`, `full_name`, `department_name`, `position_title`, `employment_status`, `basic_salary`, `source_updated_at`, `synced_at`, `branch_id`, `source_branch_id`) VALUES
(1, 'employee:1', 'EMP-2024-0001', 'John M Doe', 'Store Operations', 'HR Manager', 'Regular', '80000.00', '2026-09-08 20:10:26', '2026-09-22 05:06:45', NULL, NULL),
(2, 'employee:5', 'EMP-2024-0005', 'Pedro D Garcia', 'Store Operations', 'Operations Manager', 'Regular', '85000.00', '2026-08-05 10:47:31', '2026-09-22 05:06:45', NULL, NULL),
(3, 'employee:6', 'EMP-2026-0001', 'Vincent Pacardo Garibay', 'Store Operations', 'Cashier', 'Probationary', '10000.00', '2026-09-08 20:52:38', '2026-09-22 05:06:45', NULL, NULL),
(4, 'employee:3', 'EMP-2024-0003', 'Carlos B Reyes', 'Store Operations', 'Senior Developer', 'Regular', '75000.00', '2026-09-08 20:10:26', '2026-09-22 05:06:45', NULL, NULL),
(5, 'employee:4', 'EMP-2024-0004', 'Maria C Santos', 'Store Operations', 'Accountant', 'Regular', '45000.00', '2026-09-08 20:10:26', '2026-09-22 05:06:45', NULL, NULL),
(6, 'employee:2', 'EMP-2024-0002', 'Jane A Smith', 'Store Operations', 'HR Officer', 'Regular', '50000.00', '2026-09-08 20:10:26', '2026-09-22 05:06:45', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(40) NOT NULL DEFAULT 'cash',
  `status` enum('draft','approved','posted','void') NOT NULL DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_identities`
--

CREATE TABLE `external_identities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `finance_user_id` int(10) UNSIGNED NOT NULL,
  `source_system` enum('hrms','pos') NOT NULL,
  `source_user_id` int(11) NOT NULL,
  `source_username` varchar(120) DEFAULT NULL,
  `source_email` varchar(180) DEFAULT NULL,
  `source_role` varchar(80) DEFAULT NULL,
  `source_branch_id` int(11) DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `external_identities`
--

INSERT INTO `external_identities` (`id`, `finance_user_id`, `source_system`, `source_user_id`, `source_username`, `source_email`, `source_role`, `source_branch_id`, `last_synced_at`) VALUES
(1, 1, 'hrms', 1, 'admin', 'admin@hrms.com', 'admin', NULL, '2026-09-22 13:06:44'),
(2, 1, 'pos', 1, 'admin', 'admin@hrms.com', 'super_admin', NULL, '2026-09-22 13:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `fiscal_periods`
--

CREATE TABLE `fiscal_periods` (
  `id` int(10) UNSIGNED NOT NULL,
  `period_name` varchar(40) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','soft_close','closed') NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixed_assets`
--

CREATE TABLE `fixed_assets` (
  `id` int(10) UNSIGNED NOT NULL,
  `asset_code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `acquisition_date` date NOT NULL,
  `cost` decimal(14,2) NOT NULL,
  `salvage_value` decimal(14,2) NOT NULL DEFAULT 0.00,
  `useful_life_months` int(10) UNSIGNED NOT NULL,
  `accumulated_depreciation` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','disposed','transferred') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_logs`
--

CREATE TABLE `integration_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_system` varchar(40) NOT NULL,
  `action` varchar(40) NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `imported_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `integration_logs`
--

INSERT INTO `integration_logs` (`id`, `source_system`, `action`, `started_at`, `finished_at`, `imported_count`, `skipped_count`, `failed_count`, `message`, `created_by`) VALUES
(1, 'hrms', 'employees', '2026-09-21 21:22:57', '2026-09-21 21:22:57', 6, 0, 0, 'Employee snapshot synchronized.', 1),
(2, 'hrms', 'payroll', '2026-09-21 21:22:57', '2026-09-21 21:22:57', 5, 0, 0, 'Payroll snapshot and balanced journals synchronized.', 1),
(3, 'pos', 'sales', '2026-09-21 21:22:57', '2026-09-21 21:22:57', 1, 0, 0, 'POS sales and balanced journals synchronized.', 1),
(4, 'hrms', 'employees', '2026-09-22 13:06:45', '2026-09-22 13:06:45', 0, 6, 0, 'Employee snapshot synchronized.', 1),
(5, 'hrms', 'payroll', '2026-09-22 13:06:45', '2026-09-22 13:06:45', 0, 5, 0, 'Payroll snapshot and balanced journals synchronized.', 1),
(6, 'pos', 'sales', '2026-09-22 13:06:45', '2026-09-22 13:06:45', 0, 1, 0, 'POS sales and balanced journals synchronized.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `integration_mappings`
--

CREATE TABLE `integration_mappings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_system` varchar(40) NOT NULL,
  `source_id` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `local_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('imported','skipped','failed') NOT NULL DEFAULT 'imported',
  `last_error` text DEFAULT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_synced_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `integration_mappings`
--

INSERT INTO `integration_mappings` (`id`, `source_system`, `source_id`, `entity_type`, `local_id`, `status`, `last_error`, `first_seen_at`, `last_synced_at`) VALUES
(1, 'hrms', 'employee:1', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(2, 'hrms', 'employee:5', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(3, 'hrms', 'employee:6', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(4, 'hrms', 'employee:3', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(5, 'hrms', 'employee:4', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(6, 'hrms', 'employee:2', 'employee', NULL, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-22 05:06:45'),
(7, 'hrms', 'payroll:5', 'payroll', 1, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57'),
(8, 'hrms', 'payroll:4', 'payroll', 2, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57'),
(9, 'hrms', 'payroll:3', 'payroll', 3, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57'),
(10, 'hrms', 'payroll:2', 'payroll', 4, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57'),
(11, 'hrms', 'payroll:1', 'payroll', 5, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57'),
(12, 'pos', 'transaction:4', 'sale', 6, 'imported', NULL, '2026-09-21 13:22:57', '2026-09-21 13:22:57');

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `journal_number` varchar(40) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `source_system` varchar(40) DEFAULT NULL,
  `source_id` varchar(100) DEFAULT NULL,
  `entry_type` enum('manual','imported','adjusting','reversing','recurring') NOT NULL DEFAULT 'manual',
  `status` enum('draft','posted','void') NOT NULL DEFAULT 'draft',
  `total_debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `journal_entries`
--

INSERT INTO `journal_entries` (`id`, `journal_number`, `transaction_date`, `description`, `source_system`, `source_id`, `entry_type`, `status`, `total_debit`, `total_credit`, `created_by`, `posted_at`, `created_at`, `branch_id`) VALUES
(1, 'HRM-20260921212257-303', '2025-06-01', 'HRMS payroll · PAY-2025-06-005 · Pedro Garcia', 'hrms', 'payroll:5', 'imported', 'posted', '98000.00', '98000.00', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL),
(2, 'HRM-20260921212257-844', '2025-06-01', 'HRMS payroll · PAY-2025-06-004 · Maria Santos', 'hrms', 'payroll:4', 'imported', 'posted', '47500.00', '47500.00', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL),
(3, 'HRM-20260921212257-413', '2025-06-01', 'HRMS payroll · PAY-2025-06-003 · Carlos Reyes', 'hrms', 'payroll:3', 'imported', 'posted', '81000.00', '81000.00', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL),
(4, 'HRM-20260921212257-479', '2025-06-01', 'HRMS payroll · PAY-2025-06-002 · Jane Smith', 'hrms', 'payroll:2', 'imported', 'posted', '53000.00', '53000.00', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL),
(5, 'HRM-20260921212257-463', '2025-06-01', 'HRMS payroll · PAY-2025-06-001 · John Doe', 'hrms', 'payroll:1', 'imported', 'posted', '85000.00', '85000.00', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL),
(6, 'POS-20260921212257-144', '2026-07-14', 'Milk Tea POS sale · #4', 'pos', 'transaction:4', 'imported', 'posted', '4.99', '4.99', 1, '2026-09-21 21:22:57', '2026-09-21 13:22:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `journal_lines`
--

CREATE TABLE `journal_lines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `journal_entry_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cost_center_id` int(10) UNSIGNED DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL
) ;

--
-- Dumping data for table `journal_lines`
--

INSERT INTO `journal_lines` (`id`, `journal_entry_id`, `account_id`, `description`, `debit`, `credit`, `cost_center_id`, `branch_id`) VALUES
(1, 1, 20, 'Payroll expense', '98000.00', '0.00', NULL, NULL),
(2, 1, 10, 'Payroll deduction', '0.00', '1350.00', NULL, NULL),
(3, 1, 11, 'Payroll deduction', '0.00', '956.25', NULL, NULL),
(4, 1, 12, 'Payroll deduction', '0.00', '100.00', NULL, NULL),
(5, 1, 13, 'Payroll deduction', '0.00', '14000.00', NULL, NULL),
(6, 1, 9, 'Net payroll payable', '0.00', '81593.75', NULL, NULL),
(7, 2, 20, 'Payroll expense', '47500.00', '0.00', NULL, NULL),
(8, 2, 10, 'Payroll deduction', '0.00', '900.00', NULL, NULL),
(9, 2, 11, 'Payroll deduction', '0.00', '506.25', NULL, NULL),
(10, 2, 12, 'Payroll deduction', '0.00', '100.00', NULL, NULL),
(11, 2, 13, 'Payroll deduction', '0.00', '5000.00', NULL, NULL),
(12, 2, 9, 'Net payroll payable', '0.00', '40993.75', NULL, NULL),
(13, 3, 20, 'Payroll expense', '81000.00', '0.00', NULL, NULL),
(14, 3, 10, 'Payroll deduction', '0.00', '1350.00', NULL, NULL),
(15, 3, 11, 'Payroll deduction', '0.00', '843.75', NULL, NULL),
(16, 3, 12, 'Payroll deduction', '0.00', '100.00', NULL, NULL),
(17, 3, 13, 'Payroll deduction', '0.00', '10500.00', NULL, NULL),
(18, 3, 9, 'Net payroll payable', '0.00', '68206.25', NULL, NULL),
(19, 4, 20, 'Payroll expense', '53000.00', '0.00', NULL, NULL),
(20, 4, 10, 'Payroll deduction', '0.00', '900.00', NULL, NULL),
(21, 4, 11, 'Payroll deduction', '0.00', '562.50', NULL, NULL),
(22, 4, 12, 'Payroll deduction', '0.00', '100.00', NULL, NULL),
(23, 4, 13, 'Payroll deduction', '0.00', '6000.00', NULL, NULL),
(24, 4, 9, 'Net payroll payable', '0.00', '45437.50', NULL, NULL),
(25, 5, 20, 'Payroll expense', '85000.00', '0.00', NULL, NULL),
(26, 5, 10, 'Payroll deduction', '0.00', '1350.00', NULL, NULL),
(27, 5, 11, 'Payroll deduction', '0.00', '900.00', NULL, NULL),
(28, 5, 12, 'Payroll deduction', '0.00', '100.00', NULL, NULL),
(29, 5, 13, 'Payroll deduction', '0.00', '12000.00', NULL, NULL),
(30, 5, 9, 'Net payroll payable', '0.00', '70650.00', NULL, NULL),
(31, 6, 1, 'Cash receipt', '4.99', '0.00', NULL, NULL),
(32, 6, 17, 'Sales revenue', '0.00', '4.99', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `location_logs`
--

CREATE TABLE `location_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `label` varchar(150) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `purpose` varchar(180) NOT NULL,
  `logged_at` datetime NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_queue`
--

CREATE TABLE `mail_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient` varchar(180) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_snapshots`
--

CREATE TABLE `payroll_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` varchar(100) NOT NULL,
  `employee_source_id` varchar(100) DEFAULT NULL,
  `payroll_code` varchar(40) DEFAULT NULL,
  `payroll_month` tinyint(3) UNSIGNED NOT NULL,
  `payroll_year` year(4) NOT NULL,
  `gross_pay` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) DEFAULT NULL,
  `journal_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `source_branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_snapshots`
--

INSERT INTO `payroll_snapshots` (`id`, `source_id`, `employee_source_id`, `payroll_code`, `payroll_month`, `payroll_year`, `gross_pay`, `total_deductions`, `net_pay`, `status`, `journal_entry_id`, `source_updated_at`, `synced_at`, `branch_id`, `source_branch_id`) VALUES
(1, 'payroll:5', 'employee:5', 'PAY-2025-06-005', 6, 2025, '98000.00', '16406.25', '81593.75', 'released', 1, '2026-08-05 10:47:31', '2026-09-21 13:22:57', NULL, NULL),
(2, 'payroll:4', 'employee:4', 'PAY-2025-06-004', 6, 2025, '47500.00', '6506.25', '40993.75', 'released', 2, '2026-08-05 10:47:31', '2026-09-21 13:22:57', NULL, NULL),
(3, 'payroll:3', 'employee:3', 'PAY-2025-06-003', 6, 2025, '81000.00', '12793.75', '68206.25', 'released', 3, '2026-08-05 10:47:31', '2026-09-21 13:22:57', NULL, NULL),
(4, 'payroll:2', 'employee:2', 'PAY-2025-06-002', 6, 2025, '53000.00', '7562.50', '45437.50', 'released', 4, '2026-08-05 10:47:31', '2026-09-21 13:22:57', NULL, NULL),
(5, 'payroll:1', 'employee:1', 'PAY-2025-06-001', 6, 2025, '85000.00', '14350.00', '70650.00', 'released', 5, '2026-08-05 10:47:31', '2026-09-21 13:22:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`) VALUES
(11, 'ap.manage'),
(10, 'ar.manage'),
(15, 'assets.manage'),
(22, 'attendance.manage'),
(19, 'audit.view'),
(13, 'bank.reconcile'),
(24, 'branches.view'),
(14, 'budget.manage'),
(12, 'cash.manage'),
(1, 'dashboard.view'),
(21, 'gps.manage'),
(23, 'integration.manage'),
(3, 'journal.create'),
(4, 'journal.post'),
(2, 'journal.view'),
(5, 'journal.void'),
(20, 'mailer.manage'),
(9, 'payroll.sync'),
(8, 'payroll.view'),
(17, 'reports.view'),
(7, 'sales.sync'),
(6, 'sales.view'),
(18, 'settings.manage'),
(16, 'tax.manage');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`) VALUES
(1, 'super_admin', 'Super Administrator'),
(2, 'finance_manager', 'Finance Manager'),
(3, 'accountant', 'Accountant'),
(4, 'finance_staff', 'Finance Staff'),
(5, 'auditor', 'Auditor'),
(6, 'viewer', 'Viewer');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(2, 1),
(2, 2),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 17),
(2, 20),
(2, 21),
(2, 22),
(2, 23),
(2, 24),
(3, 20),
(3, 21),
(3, 22),
(4, 1),
(4, 2),
(4, 6),
(4, 8),
(4, 17),
(4, 24),
(6, 1),
(6, 2),
(6, 6),
(6, 8),
(6, 17),
(6, 24);

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_snapshot_id` bigint(20) UNSIGNED NOT NULL,
  `source_detail_id` varchar(100) NOT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `category_name` varchar(120) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_items`
--

INSERT INTO `sales_items` (`id`, `sale_snapshot_id`, `source_detail_id`, `product_name`, `category_name`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 'transaction_detail:4', 'Classic Brown Sugar Milk Tea', 'Milk Tea', 1, '4.99', '4.99');

-- --------------------------------------------------------

--
-- Table structure for table `sales_snapshots`
--

CREATE TABLE `sales_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` varchar(100) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL,
  `status` varchar(30) NOT NULL,
  `order_type` varchar(30) DEFAULT NULL,
  `journal_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `source_branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_snapshots`
--

INSERT INTO `sales_snapshots` (`id`, `source_id`, `transaction_date`, `total_amount`, `payment_method`, `status`, `order_type`, `journal_entry_id`, `source_updated_at`, `synced_at`, `branch_id`, `source_branch_id`) VALUES
(1, 'transaction:4', '2026-07-14 22:45:18', '4.99', 'cash', 'completed', 'delivery', 6, '2026-07-14 22:45:18', '2026-09-21 13:22:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Finance System'),
('currency', 'PHP'),
('inventory_costing_method', 'manual'),
('session_timeout_minutes', '30');

-- --------------------------------------------------------

--
-- Table structure for table `tax_rates`
--

CREATE TABLE `tax_rates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate` decimal(7,4) NOT NULL,
  `tax_type` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source_system` varchar(20) DEFAULT NULL,
  `source_user_id` int(11) DEFAULT NULL,
  `source_employee_id` int(11) DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role_id`, `is_active`, `failed_attempts`, `locked_until`, `last_login_at`, `created_at`, `source_system`, `source_user_id`, `source_employee_id`, `branch_id`) VALUES
(1, 'admin', 'admin', '$2y$10$z0bHHRiHsNOOTa8bigG5QuwE5aotj8P.BEE.3EqaXCe9b3Qp99fqe', 2, 1, 0, NULL, '2026-09-29 22:55:30', '2026-09-21 13:02:12', 'hrms', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `payment_terms` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accounts_code` (`code`),
  ADD KEY `fk_accounts_group` (`account_group_id`);

--
-- Indexes for table `account_groups`
--
ALTER TABLE `account_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_groups_name` (`name`);

--
-- Indexes for table `ap_bills`
--
ALTER TABLE `ap_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ap_bill_number` (`bill_number`),
  ADD KEY `fk_ap_bill_vendor` (`vendor_id`);

--
-- Indexes for table `ar_invoices`
--
ALTER TABLE `ar_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ar_invoice_number` (`invoice_number`),
  ADD KEY `fk_ar_invoice_customer` (`customer_id`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attendance_employee_date` (`employee_snapshot_id`,`attendance_date`),
  ADD KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  ADD KEY `fk_attendance_user` (`created_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_date` (`created_at`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_finance_branch_source` (`source_system`,`source_id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_lines`
--
ALTER TABLE `budget_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_budget_line` (`budget_id`,`account_id`,`month_number`),
  ADD KEY `fk_budget_line_account` (`account_id`);

--
-- Indexes for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cost_centers_code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_snapshots`
--
ALTER TABLE `employee_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_snapshot_source` (`source_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_expense_user` (`created_by`);

--
-- Indexes for table `external_identities`
--
ALTER TABLE `external_identities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_external_identity` (`source_system`,`source_user_id`),
  ADD KEY `idx_external_finance_user` (`finance_user_id`);

--
-- Indexes for table `fiscal_periods`
--
ALTER TABLE `fiscal_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fiscal_period_dates` (`start_date`,`end_date`);

--
-- Indexes for table `fixed_assets`
--
ALTER TABLE `fixed_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fixed_asset_code` (`asset_code`);

--
-- Indexes for table `integration_logs`
--
ALTER TABLE `integration_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_integration_logs_source_date` (`source_system`,`started_at`),
  ADD KEY `fk_integration_log_user` (`created_by`);

--
-- Indexes for table `integration_mappings`
--
ALTER TABLE `integration_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_integration_mapping` (`source_system`,`source_id`,`entity_type`),
  ADD KEY `idx_integration_status` (`source_system`,`status`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_journal_number` (`journal_number`),
  ADD UNIQUE KEY `uq_journal_source` (`source_system`,`source_id`),
  ADD KEY `idx_journal_date_status` (`transaction_date`,`status`),
  ADD KEY `fk_journal_user` (`created_by`);

--
-- Indexes for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_journal_lines_entry` (`journal_entry_id`),
  ADD KEY `fk_journal_lines_account` (`account_id`),
  ADD KEY `fk_journal_lines_cost_center` (`cost_center_id`),
  ADD KEY `fk_journal_lines_branch` (`branch_id`);

--
-- Indexes for table `location_logs`
--
ALTER TABLE `location_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location_logs_date` (`logged_at`),
  ADD KEY `fk_location_log_branch` (`branch_id`),
  ADD KEY `fk_location_log_user` (`created_by`);

--
-- Indexes for table `mail_queue`
--
ALTER TABLE `mail_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_queue_status` (`status`,`created_at`),
  ADD KEY `fk_mail_queue_user` (`created_by`);

--
-- Indexes for table `payroll_snapshots`
--
ALTER TABLE `payroll_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payroll_snapshot_source` (`source_id`),
  ADD KEY `fk_payroll_snapshot_journal` (`journal_entry_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_name` (`name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_item_source` (`source_detail_id`),
  ADD KEY `fk_sales_item_sale` (`sale_snapshot_id`);

--
-- Indexes for table `sales_snapshots`
--
ALTER TABLE `sales_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_snapshot_source` (`source_id`),
  ADD KEY `idx_sales_snapshot_date` (`transaction_date`),
  ADD KEY `fk_sales_snapshot_journal` (`journal_entry_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `tax_rates`
--
ALTER TABLE `tax_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `account_groups`
--
ALTER TABLE `account_groups`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `ap_bills`
--
ALTER TABLE `ap_bills`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ar_invoices`
--
ALTER TABLE `ar_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_lines`
--
ALTER TABLE `budget_lines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_centers`
--
ALTER TABLE `cost_centers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_snapshots`
--
ALTER TABLE `employee_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_identities`
--
ALTER TABLE `external_identities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `fiscal_periods`
--
ALTER TABLE `fiscal_periods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fixed_assets`
--
ALTER TABLE `fixed_assets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_logs`
--
ALTER TABLE `integration_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `integration_mappings`
--
ALTER TABLE `integration_mappings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `journal_lines`
--
ALTER TABLE `journal_lines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `location_logs`
--
ALTER TABLE `location_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mail_queue`
--
ALTER TABLE `mail_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_snapshots`
--
ALTER TABLE `payroll_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales_snapshots`
--
ALTER TABLE `sales_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tax_rates`
--
ALTER TABLE `tax_rates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_group` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`);

--
-- Constraints for table `ap_bills`
--
ALTER TABLE `ap_bills`
  ADD CONSTRAINT `fk_ap_bill_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `ar_invoices`
--
ALTER TABLE `ar_invoices`
  ADD CONSTRAINT `fk_ar_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_snapshot_id`) REFERENCES `employee_snapshots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `budget_lines`
--
ALTER TABLE `budget_lines`
  ADD CONSTRAINT `fk_budget_line_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_budget_line_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expense_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `integration_logs`
--
ALTER TABLE `integration_logs`
  ADD CONSTRAINT `fk_integration_log_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `fk_journal_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD CONSTRAINT `fk_journal_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_journal_lines_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_journal_lines_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_journal_lines_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `location_logs`
--
ALTER TABLE `location_logs`
  ADD CONSTRAINT `fk_location_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_location_log_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mail_queue`
--
ALTER TABLE `mail_queue`
  ADD CONSTRAINT `fk_mail_queue_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_snapshots`
--
ALTER TABLE `payroll_snapshots`
  ADD CONSTRAINT `fk_payroll_snapshot_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `fk_sales_item_sale` FOREIGN KEY (`sale_snapshot_id`) REFERENCES `sales_snapshots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_snapshots`
--
ALTER TABLE `sales_snapshots`
  ADD CONSTRAINT `fk_sales_snapshot_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
