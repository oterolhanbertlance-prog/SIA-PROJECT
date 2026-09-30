-- ============================================================
-- HRMS Complete Schema — Single-File Install
-- Includes base schema + all migrations v2 through v8.
-- 
-- INSTRUCTIONS (fresh install):
--   1. Open phpMyAdmin → SQL tab (or use MySQL CLI)
--   2. Import THIS file (database/hrms_complete.sql)
--   3. That's it — all tables, columns, and seed data are created.
--
-- Do NOT import the individual migration_vX.sql files as well;
-- they are already merged into this file.
-- ============================================================

-- ============================================================
-- HRMS - Human Resource Management System
-- Database: hrms
-- Version: 1.0
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `hrms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hrms`;

-- -----------------------------------------------
-- Table: departments
-- -----------------------------------------------
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `name`, `description`, `status`) VALUES
(1, 'Human Resources', 'Manages all HR activities', 'active'),
(2, 'Information Technology', 'Manages IT infrastructure and software', 'active'),
(3, 'Finance', 'Manages financial operations', 'active'),
(4, 'Operations', 'Manages day-to-day operations', 'active'),
(5, 'Marketing', 'Manages marketing and brand', 'active'),
(6, 'Sales', 'Manages sales and revenue', 'active'),
(7, 'Legal', 'Manages legal and compliance', 'active'),
(8, 'Administration', 'Manages administrative tasks', 'active');

-- -----------------------------------------------
-- Table: positions
-- -----------------------------------------------
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `min_salary` decimal(12,2) DEFAULT 0.00,
  `max_salary` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_pos_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `positions` (`id`, `title`, `department_id`, `min_salary`, `max_salary`, `status`) VALUES
(1, 'HR Manager', 1, 50000, 80000, 'active'),
(2, 'HR Officer', 1, 30000, 50000, 'active'),
(3, 'HR Assistant', 1, 20000, 30000, 'active'),
(4, 'IT Manager', 2, 60000, 100000, 'active'),
(5, 'Senior Developer', 2, 45000, 80000, 'active'),
(6, 'Junior Developer', 2, 25000, 45000, 'active'),
(7, 'Finance Manager', 3, 55000, 90000, 'active'),
(8, 'Accountant', 3, 30000, 55000, 'active'),
(9, 'Operations Manager', 4, 50000, 85000, 'active'),
(10, 'Marketing Manager', 5, 45000, 75000, 'active'),
(11, 'Sales Manager', 6, 40000, 70000, 'active'),
(12, 'Sales Representative', 6, 20000, 40000, 'active'),
(13, 'Legal Counsel', 7, 60000, 100000, 'active'),
(14, 'Admin Officer', 8, 20000, 35000, 'active');

-- -----------------------------------------------
-- Table: employees
-- -----------------------------------------------
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL UNIQUE,
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
  `profile_picture` varchar(255) DEFAULT 'default.png',
  `highest_education` varchar(100) DEFAULT NULL,
  `school` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `grad_year` year DEFAULT NULL,
  `work_experience` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `fk_emp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_pos` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employees` (`id`,`employee_id`,`firstname`,`lastname`,`middlename`,`gender`,`birthdate`,`civil_status`,`email`,`phone`,`address`,`city`,`department_id`,`position_id`,`employment_status`,`date_hired`,`basic_salary`,`sss_no`,`philhealth_no`,`pagibig_no`,`tin_no`,`highest_education`,`school`,`course`,`status`) VALUES
(1,'EMP-2024-0001','John','Doe','M','Male','1990-05-15','Married','admin@hrms.com','09171234567','123 Main St','Manila',1,1,'Regular','2020-01-01',80000,'12-3456789-0','12-345678901-2','123456789012','123-456-789-000','Bachelor\'s Degree','University of Manila','Business Administration','active'),
(2,'EMP-2024-0002','Jane','Smith','A','Female','1992-08-20','Single','jane.smith@hrms.com','09181234567','456 Oak Ave','Quezon City',1,2,'Regular','2021-03-15',50000,'23-4567890-1','23-456789012-3','234567890123','234-567-890-001','Bachelor\'s Degree','Ateneo de Manila','Psychology','active'),
(3,'EMP-2024-0003','Carlos','Reyes','B','Male','1988-11-10','Married','carlos.reyes@hrms.com','09191234567','789 Pine Rd','Makati',2,5,'Regular','2019-06-01',75000,'34-5678901-2','34-567890123-4','345678901234','345-678-901-002','Bachelor\'s Degree','DLSU','Computer Science','active'),
(4,'EMP-2024-0004','Maria','Santos','C','Female','1995-03-25','Single','maria.santos@hrms.com','09201234567','321 Elm St','Pasig',3,8,'Regular','2022-01-10',45000,'45-6789012-3','45-678901234-5','456789012345','456-789-012-003','Bachelor\'s Degree','UP Manila','Accounting','active'),
(5,'EMP-2024-0005','Pedro','Garcia','D','Male','1985-07-05','Married','pedro.garcia@hrms.com','09211234567','654 Maple Dr','Taguig',4,9,'Regular','2018-09-01',85000,'56-7890123-4','56-789012345-6','567890123456','567-890-123-004','Master\'s Degree','AIM','Business Management','active');

-- -----------------------------------------------
-- Table: users
-- -----------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` enum('admin','hr','employee') NOT NULL DEFAULT 'employee',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_user_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Passwords: admin=admin123, hr=hr123, employee=employee123 (bcrypt $2b$ accepted by PHP password_verify)
INSERT INTO `users` (`id`,`username`,`password`,`email`,`role`,`employee_id`,`status`) VALUES
(1,'admin','$2b$10$O0lYwf1x0i14L1In58dOwOMwv2tmljur1vhqh/swKgsVrIwQnCY6m','admin@hrms.com','admin',1,'active'),
(2,'hr','$2b$10$UsYLP50d6udTSK68rQQUqeXfczzSzswRW4t4y7rj/KkkbP6mu7FLq','jane.smith@hrms.com','hr',2,'active'),
(3,'employee','$2b$10$6zM6K.jUOvyqqLAyMnIZnOQyPwPklXKRcRZZfisCfltXfI2XD4dum','carlos.reyes@hrms.com','employee',3,'active');

-- -----------------------------------------------
-- Table: job_postings
-- -----------------------------------------------
CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_job_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `job_postings` (`id`,`title`,`department_id`,`position_id`,`description`,`requirements`,`slots`,`job_type`,`status`,`deadline`) VALUES
(1,'HR Officer',1,2,'Looking for an experienced HR Officer to join our team.','Bachelor\'s degree in HR or related field, 2 years experience',2,'Full-Time','open','2025-12-31'),
(2,'Senior Developer',2,5,'We need a talented Senior Developer for our IT team.','5+ years experience in PHP, MySQL, JavaScript',1,'Full-Time','open','2025-11-30'),
(3,'Accountant',3,8,'Seeking a detail-oriented Accountant.','CPA preferred, 3 years accounting experience',1,'Full-Time','open','2025-12-15');

-- -----------------------------------------------
-- Table: applicants
-- -----------------------------------------------
CREATE TABLE `applicants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) DEFAULT NULL,
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
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Alias kept for module compatibility',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  CONSTRAINT `fk_app_job` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `applicants` (`id`,`applicant_code`,`job_id`,`firstname`,`lastname`,`email`,`phone`,`highest_education`,`school`,`course`,`years_experience`,`status`) VALUES
(1,'APP-2024-0001',1,'Ana','Cruz','ana.cruz@email.com','09301234567','Bachelor\'s Degree','Mapua University','Psychology',2,'initial_interview'),
(2,'APP-2024-0002',2,'Ben','Torres','ben.torres@email.com','09311234567','Bachelor\'s Degree','FEU Tech','Computer Science',4,'pending'),
(3,'APP-2024-0003',1,'Clara','Lim','clara.lim@email.com','09321234567','Bachelor\'s Degree','PLM','Human Resources',1,'pending'),
(4,'APP-2024-0004',3,'David','Tan','david.tan@email.com','09331234567','Bachelor\'s Degree','PUP','Accounting',3,'final_interview');

-- -----------------------------------------------
-- Table: interviews
-- -----------------------------------------------
CREATE TABLE `interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `applicant_id` (`applicant_id`),
  CONSTRAINT `fk_int_app` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `interviews` (`id`,`applicant_id`,`interview_type`,`interview_date`,`interviewer_name`,`location`,`mode`,`result`,`status`) VALUES
(1,1,'initial','2025-07-15 10:00:00','Jane Smith','Conference Room A','in-person','pending','scheduled'),
(2,4,'final','2025-07-18 14:00:00','John Doe','Conference Room B','in-person','pending','scheduled');

-- -----------------------------------------------
-- Table: contracts
-- -----------------------------------------------
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_con_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `contracts` (`employee_id`,`contract_number`,`contract_type`,`start_date`,`end_date`,`department_id`,`position_id`,`salary`,`status`) VALUES
(1,'CON-2020-0001','Regular','2020-01-01',NULL,1,1,80000,'active'),
(2,'CON-2021-0001','Regular','2021-03-15',NULL,1,2,50000,'active'),
(3,'CON-2019-0001','Regular','2019-06-01',NULL,2,5,75000,'active'),
(4,'CON-2022-0001','Regular','2022-01-10',NULL,3,8,45000,'active'),
(5,'CON-2018-0001','Regular','2018-09-01',NULL,4,9,85000,'active');

-- -----------------------------------------------
-- Table: attendance
-- -----------------------------------------------
CREATE TABLE `attendance` (
  `id`                 int(11)       NOT NULL AUTO_INCREMENT,
  `user_id`            int(11)       NULL DEFAULT NULL COMMENT 'HRMS user that clocked in',
  `employee_id`        int(11)       NOT NULL,
  `date`               date          NOT NULL,
  `time_in`            time          DEFAULT NULL,
  `time_out`           time          DEFAULT NULL,
  `hours_worked`       decimal(5,2)  DEFAULT 0.00,
  `overtime_hours`     decimal(5,2)  DEFAULT 0.00,
  `late_minutes`       int(11)       NOT NULL DEFAULT 0 COMMENT 'Minutes late past 08:00',
  `undertime_minutes`  int(11)       NOT NULL DEFAULT 0 COMMENT 'Minutes left before 17:00',
  `status`             enum('present','absent','late','half_day','leave','holiday') DEFAULT 'present',
  `remarks`            varchar(255)  DEFAULT NULL,
  `created_at`         timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id`    (`employee_id`),
  KEY `user_id`        (`user_id`),
  UNIQUE KEY `emp_date` (`employee_id`, `date`),
  CONSTRAINT `fk_att_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Table: leave_types
-- -----------------------------------------------
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `days_allowed` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_types` (`id`,`name`,`days_allowed`,`description`) VALUES
(1,'Sick Leave',15,'Leave due to illness or medical appointment'),
(2,'Vacation Leave',15,'Annual vacation or personal leave'),
(3,'Emergency Leave',3,'Unforeseeable emergency situations'),
(4,'Maternity Leave',105,'Maternity leave for female employees'),
(5,'Paternity Leave',7,'Paternity leave for male employees'),
(6,'Bereavement Leave',3,'Leave due to death of a family member'),
(7,'Service Incentive Leave',5,'Mandatory SIL per Labor Code');

-- -----------------------------------------------
-- Table: leave_requests
-- -----------------------------------------------
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `fk_lr_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lr_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_requests` (`leave_code`,`employee_id`,`leave_type_id`,`start_date`,`end_date`,`total_days`,`reason`,`status`) VALUES
('LV-2024-0001',3,1,'2025-07-20','2025-07-22',3,'Medical check-up and recovery','pending'),
('LV-2024-0002',4,2,'2025-08-01','2025-08-05',5,'Family vacation','approved'),
('LV-2024-0003',5,3,'2025-07-10','2025-07-10',1,'Family emergency','approved');

-- -----------------------------------------------
-- Table: leave_balance
-- -----------------------------------------------
CREATE TABLE `leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year NOT NULL,
  `total_days` decimal(5,1) DEFAULT 0.0,
  `used_days` decimal(5,1) DEFAULT 0.0,
  `remaining_days` decimal(5,1) DEFAULT 0.0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_type_year` (`employee_id`,`leave_type_id`,`year`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_lb_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_balance` (`employee_id`,`leave_type_id`,`year`,`total_days`,`used_days`,`remaining_days`) VALUES
(1,1,2025,15,0,15),(1,2,2025,15,0,15),(1,3,2025,3,0,3),
(2,1,2025,15,0,15),(2,2,2025,15,0,15),(2,3,2025,3,0,3),
(3,1,2025,15,3,12),(3,2,2025,15,0,15),(3,3,2025,3,0,3),
(4,1,2025,15,0,15),(4,2,2025,15,5,10),(4,3,2025,3,0,3),
(5,1,2025,15,0,15),(5,2,2025,15,0,15),(5,3,2025,3,1,2);

-- -----------------------------------------------
-- Table: payroll
-- -----------------------------------------------
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_code` varchar(30) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_month` tinyint(2) NOT NULL,
  `payroll_year` year NOT NULL,
  `pay_period` enum('monthly','semi-monthly','weekly') DEFAULT 'monthly',
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `allowances` decimal(12,2) DEFAULT 0.00,
  `overtime_pay` decimal(12,2) DEFAULT 0.00,
  `bonuses` decimal(12,2) DEFAULT 0.00,
  `gross_pay` decimal(12,2) DEFAULT 0.00,
  `sss_deduction` decimal(10,2) DEFAULT 0.00,
  `philhealth_deduction` decimal(10,2) DEFAULT 0.00,
  `pagibig_deduction` decimal(10,2) DEFAULT 0.00,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_pay_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payroll` (`payroll_code`,`employee_id`,`payroll_month`,`payroll_year`,`basic_salary`,`allowances`,`overtime_pay`,`bonuses`,`gross_pay`,`sss_deduction`,`philhealth_deduction`,`pagibig_deduction`,`tax_deduction`,`total_deductions`,`net_pay`,`days_worked`,`status`) VALUES
('PAY-2025-06-001',1,6,2025,80000,5000,0,0,85000,1350,900,100,12000,14350,70650,22,'released'),
('PAY-2025-06-002',2,6,2025,50000,3000,0,0,53000,900,562.5,100,6000,7562.5,45437.5,22,'released'),
('PAY-2025-06-003',3,6,2025,75000,4000,2000,0,81000,1350,843.75,100,10500,12793.75,68206.25,22,'released'),
('PAY-2025-06-004',4,6,2025,45000,2500,0,0,47500,900,506.25,100,5000,6506.25,40993.75,22,'released'),
('PAY-2025-06-005',5,6,2025,85000,5000,3000,5000,98000,1350,956.25,100,14000,16406.25,81593.75,22,'released');

-- -----------------------------------------------
-- Table: documents
-- -----------------------------------------------
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_doc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Table: audit_logs
-- -----------------------------------------------
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (`user_id`,`username`,`action`,`module`,`description`,`ip_address`) VALUES
(1,'admin','LOGIN','Authentication','Admin logged in','127.0.0.1'),
(1,'admin','CREATE','Employee','Created employee: EMP-2024-0001','127.0.0.1'),
(2,'hr','LOGIN','Authentication','HR logged in','127.0.0.1'),
(2,'hr','UPDATE','Leave','Approved leave request LV-2024-0002','127.0.0.1'),
(1,'admin','CREATE','Payroll','Processed payroll for June 2025','127.0.0.1');

-- -----------------------------------------------
-- Table: notifications
-- -----------------------------------------------
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`,`is_read`) VALUES
(1,'New Leave Request','Employee Carlos Reyes submitted a leave request','info',0),
(2,'Interview Scheduled','Interview scheduled for Ana Cruz on July 15','info',0),
(1,'Payroll Processed','June 2025 payroll has been processed successfully','success',1);

-- -----------------------------------------------
-- Table: resignation_letters
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `resignation_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `fk_res_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Migration v4: Applicant self-service portal (separate login from HR/admin/employee users)
-- Additive only — does not touch or remove any existing table/column.

CREATE TABLE IF NOT EXISTS applicant_accounts (
  id INT(11) NOT NULL AUTO_INCREMENT,
  firstname VARCHAR(100) NOT NULL,
  lastname VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  password VARCHAR(255) NULL DEFAULT NULL COMMENT 'NULL for email-only applicants',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uniq_applicant_account_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link each application record to the applicant's account (nullable, so
-- existing rows and any future HR-entered applicant with no self-service
-- account keep working exactly as before).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'applicant_account_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE applicants ADD COLUMN applicant_account_id INT(11) DEFAULT NULL AFTER id, ADD KEY idx_applicant_account_id (applicant_account_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND CONSTRAINT_NAME = 'fk_applicants_account');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE applicants ADD CONSTRAINT fk_applicants_account FOREIGN KEY (applicant_account_id) REFERENCES applicant_accounts(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Allow notifications to target an applicant account instead of a staff user.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'applicant_account_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE notifications ADD COLUMN applicant_account_id INT(11) DEFAULT NULL AFTER user_id, MODIFY COLUMN user_id INT(11) DEFAULT NULL, ADD KEY idx_notif_applicant_account_id (applicant_account_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND CONSTRAINT_NAME = 'fk_notifications_applicant');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_applicant FOREIGN KEY (applicant_account_id) REFERENCES applicant_accounts(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
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
-- =====================================================================
-- HRMS Migration v7 — Mailer Module + GSIS + Payroll Deduction Rules
-- Run once against the hrms database.
-- =====================================================================

-- ── 1. Add GSIS number to employees ──────────────────────────────────────────
-- GSIS (Government Service Insurance System) is applicable to government
-- employees. Like SSS/PhilHealth/Pag-IBIG, if this is empty the GSIS
-- deduction in payroll will be ₱0.
ALTER TABLE `employees`
    ADD COLUMN IF NOT EXISTS `gsis_no` VARCHAR(30) DEFAULT NULL
        COMMENT 'Government Service Insurance System number'
        AFTER `tin_no`;

-- ── 2. Add GSIS deduction column to payroll ───────────────────────────────────
ALTER TABLE `payroll`
    ADD COLUMN IF NOT EXISTS `gsis_deduction` DECIMAL(10,2) DEFAULT 0.00
        AFTER `pagibig_deduction`;

-- Update existing payroll rows so total_deductions is still correct
-- (gsis_deduction defaults to 0 for all existing rows — no change needed).

-- ── 3. Create mailer_accounts table ──────────────────────────────────────────
-- Separate from the main `users` table. Provides an independent login
-- for the HR Mailer module (mailer/login.php) that does NOT give access
-- to the main HRMS dashboard.
CREATE TABLE IF NOT EXISTS `mailer_accounts` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `fullname`   VARCHAR(150) DEFAULT NULL,
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME     DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_mailer_username` (`username`),
    UNIQUE KEY `uniq_mailer_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Standalone accounts for the HR Mailer module (separate from users table)';

-- ── 4. Default mailer account (username: hrmailer / password: hrmailer123) ───
-- Change the password immediately after setup!
INSERT IGNORE INTO `mailer_accounts` (`username`, `password`, `email`, `fullname`, `status`)
VALUES (
    'hrmailer',
    '$2y$10$VZQ3p7X1rQnA/FqNvUzS3.HWtkPVbEODWm1kApnTZN7ZSuHjI.9Ni', -- hrmailer123
    'hr@hrms.local',
    'HR Mailer Admin',
    'active'
);

-- ── 5. Ensure applicant_accounts has all needed columns ──────────────────────
-- (safe re-run — uses ADD COLUMN IF NOT EXISTS)
ALTER TABLE `applicant_accounts`
    ADD COLUMN IF NOT EXISTS `hired_username`      VARCHAR(100) NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `hired_temp_password` VARCHAR(255) NULL DEFAULT NULL AFTER `hired_username`,
    ADD COLUMN IF NOT EXISTS `hired_employee_id`   VARCHAR(50)  NULL DEFAULT NULL AFTER `hired_temp_password`;

-- ── 6. Ensure interviews table has `notes` column ────────────────────────────
ALTER TABLE `interviews`
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL DEFAULT NULL
        COMMENT 'Additional notes sent to the applicant with the interview invitation'
        AFTER `rating`;

-- ── PAYROLL DEDUCTION REMINDER ───────────────────────────────────────────────
-- The payroll module checks employee.sss_no, philhealth_no, pagibig_no,
-- tin_no, and gsis_no before calculating deductions.
-- Any field that is NULL or empty string results in a ₱0.00 deduction
-- for the corresponding contribution.
--
-- Reference function: computePayrollDeductions() in mailer/config.php
-- and the updated payroll processing logic.

-- ── DONE ──────────────────────────────────────────────────────────────────────
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

-- ── applicant_replies (migration v9) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `applicant_replies` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `hr_email_id`  INT(11)      NOT NULL,
    `applicant_id` INT(11)      NOT NULL,
    `subject`      VARCHAR(255) NOT NULL DEFAULT 'Re: (no subject)',
    `body`         TEXT         NOT NULL,
    `is_read_hr`   TINYINT(1)   NOT NULL DEFAULT 0,
    `sent_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `hr_email_id`  (`hr_email_id`),
    KEY `applicant_id` (`applicant_id`),
    CONSTRAINT `fk_reply_hremail` FOREIGN KEY (`hr_email_id`)  REFERENCES `hr_emails`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reply_app`     FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── POS-linked localhost configuration ──────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `pos_role` ENUM('super_admin','cashier','inventory_staff') NULL
        COMMENT 'POS role for hired staff; HRMS role remains employee'
        AFTER `role`;
ALTER TABLE `users`
    MODIFY COLUMN `pos_role` ENUM('super_admin','cashier','inventory_staff') NULL
        COMMENT 'POS role for hired staff; HRMS role remains employee'
        AFTER `role`;
ALTER TABLE `attendance`
    MODIFY COLUMN `employee_id` INT(11) NULL,
    ADD COLUMN IF NOT EXISTS `pos_user_id` INT(11) NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `source_system` VARCHAR(30) NOT NULL DEFAULT 'hrms' AFTER `pos_user_id`;

INSERT INTO `positions` (`title`,`department_id`,`min_salary`,`max_salary`,`status`)
SELECT 'Cashier', COALESCE((SELECT id FROM departments WHERE name='Operations' LIMIT 1),1), 14000,22000,'active'
WHERE NOT EXISTS (SELECT 1 FROM positions WHERE title='Cashier');
INSERT INTO `positions` (`title`,`department_id`,`min_salary`,`max_salary`,`status`)
SELECT 'Inventory Staff', COALESCE((SELECT id FROM departments WHERE name='Operations' LIMIT 1),1), 14000,22000,'active'
WHERE NOT EXISTS (SELECT 1 FROM positions WHERE title='Inventory Staff');
INSERT INTO `positions` (`title`,`department_id`,`min_salary`,`max_salary`,`status`)
SELECT 'POS Super Admin', COALESCE((SELECT id FROM departments WHERE name='Operations' LIMIT 1),1), 20000,35000,'active'
WHERE NOT EXISTS (SELECT 1 FROM positions WHERE title='POS Super Admin');
INSERT INTO `job_postings` (`title`,`department_id`,`position_id`,`description`,`requirements`,`slots`,`job_type`,`status`)
SELECT 'Cashier', p.department_id, p.id, 'Serve customers and process milk tea orders at the POS counter.','Customer service and basic cash-handling skills.',2,'Full-Time','open'
FROM positions p WHERE p.title='Cashier' AND NOT EXISTS (SELECT 1 FROM job_postings WHERE title='Cashier') LIMIT 1;
INSERT INTO `job_postings` (`title`,`department_id`,`position_id`,`description`,`requirements`,`slots`,`job_type`,`status`)
SELECT 'Inventory Staff', p.department_id, p.id, 'Track ingredients, stock levels, and store-room movements.','Inventory or stockroom experience preferred.',1,'Full-Time','open'
FROM positions p WHERE p.title='Inventory Staff' AND NOT EXISTS (SELECT 1 FROM job_postings WHERE title='Inventory Staff') LIMIT 1;
INSERT INTO `job_postings` (`title`,`department_id`,`position_id`,`description`,`requirements`,`slots`,`job_type`,`status`)
SELECT 'POS Super Admin', p.department_id, p.id, 'Administer the complete milk tea POS system, including users, products, inventory, transactions, and reports.','POS administration and security experience preferred.',1,'Full-Time','open'
FROM positions p WHERE p.title='POS Super Admin' AND NOT EXISTS (SELECT 1 FROM job_postings WHERE title='POS Super Admin') LIMIT 1;

CREATE TABLE IF NOT EXISTS `pos_job_postings_meta` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_posting_id` INT NOT NULL,
  `pos_role_target` ENUM('super_admin','cashier','inventory_staff') NOT NULL DEFAULT 'cashier',
  `pos_position_label` VARCHAR(100) NOT NULL DEFAULT 'POS Staff',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_job_posting` (`job_posting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `pos_job_postings_meta`
  MODIFY COLUMN `pos_role_target` ENUM('super_admin','cashier','inventory_staff') NOT NULL DEFAULT 'cashier';
CREATE TABLE IF NOT EXISTS `pos_role_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pos_user_id` INT NOT NULL, `pos_username` VARCHAR(100) NOT NULL,
  `pos_email` VARCHAR(150) DEFAULT NULL, `job_posting_id` INT NOT NULL,
  `cover_note` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT DEFAULT NULL, `reviewed_at` DATETIME DEFAULT NULL,
  `hr_notes` TEXT DEFAULT NULL, `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_pos_user_job` (`pos_user_id`,`job_posting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `pos_role_upgrade_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `hrms_applicant_id` INT DEFAULT NULL,
  `hrms_user_id` INT DEFAULT NULL, `pos_user_id` INT NOT NULL,
  `pos_username` VARCHAR(100) NOT NULL, `pos_email` VARCHAR(150) DEFAULT NULL,
  `old_pos_role` VARCHAR(50) DEFAULT NULL, `new_pos_role` VARCHAR(50) NOT NULL,
  `job_posting_id` INT DEFAULT NULL, `performed_by` INT DEFAULT NULL,
  `performed_by_username` VARCHAR(100) DEFAULT NULL, `notes` TEXT DEFAULT NULL,
  `status` ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_pos_user` (`pos_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` INT NOT NULL AUTO_INCREMENT, `smtp_host` VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` SMALLINT NOT NULL DEFAULT 587, `smtp_username` VARCHAR(255) NOT NULL DEFAULT '',
  `smtp_password` VARCHAR(255) NOT NULL DEFAULT '', `smtp_from` VARCHAR(255) NOT NULL DEFAULT 'noreply@localhost',
  `smtp_name` VARCHAR(150) NOT NULL DEFAULT 'HRMS', `smtp_secure` ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `smtp_auth` TINYINT(1) NOT NULL DEFAULT 1, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `smtp_settings` (`id`) VALUES (1);

-- ── v13: repair hiring, deployment, contracts, and POS department links ──
UPDATE `job_postings` j
JOIN `positions` p ON p.id = (
    SELECT MIN(p2.id) FROM `positions` p2
    WHERE p2.title=j.title AND p2.status='active'
)
SET j.position_id=p.id, j.department_id=p.department_id
WHERE j.title IN ('POS Super Admin','Cashier','Inventory Staff');

UPDATE `employees` e
JOIN `applicants` a ON LOWER(a.email)=LOWER(e.email) AND a.status='hired'
JOIN `job_postings` j ON j.id=a.job_id
JOIN `positions` p ON p.id=COALESCE(j.position_id, (
    SELECT MIN(p2.id) FROM `positions` p2
    WHERE p2.title=j.title AND p2.status='active'
))
SET e.department_id=p.department_id, e.position_id=p.id
WHERE j.title IN ('POS Super Admin','Cashier','Inventory Staff');

UPDATE `contracts` c
JOIN `employees` e ON e.id=c.employee_id
SET c.department_id=e.department_id,
    c.position_id=e.position_id,
    c.salary=CASE WHEN c.salary=0 THEN e.basic_salary ELSE c.salary END
WHERE c.status IN ('active','renewed')
  AND (c.department_id IS NULL OR c.position_id IS NULL);

INSERT INTO `contracts`
    (employee_id,contract_number,contract_type,start_date,department_id,position_id,salary,status,notes,created_by)
SELECT e.id, CONCAT('CON-AUTO-',LPAD(e.id,6,'0')), 'Probationary',
       COALESCE(e.date_hired,CURDATE()), e.department_id, e.position_id,
       COALESCE(e.basic_salary,0), 'active',
       'Repaired automatically from an existing hired applicant record.', NULL
FROM `employees` e
JOIN `applicants` a ON LOWER(a.email)=LOWER(e.email) AND a.status='hired'
JOIN `job_postings` j ON j.id=a.job_id
WHERE j.title IN ('POS Super Admin','Cashier','Inventory Staff')
  AND e.department_id IS NOT NULL AND e.position_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `contracts` c
      WHERE c.employee_id=e.id AND c.status IN ('active','renewed')
  );

-- ============================================================================
-- v3 UPGRADE — Branch mapping (lat/lng), branch-scoped job postings & HR
-- logins, combined HRMS role/position labels.
-- Append-only: safe to run once on top of everything above.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- HRMS side
-- ---------------------------------------------------------------------------
USE `hrms`;

ALTER TABLE `branches`
  ADD COLUMN `latitude`  DECIMAL(10,7) NULL AFTER `city`,
  ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;

-- Give the two existing branches real coordinates (Cebu City area).
UPDATE `branches` SET `latitude` = 10.3157, `longitude` = 123.8854 WHERE `id` = 1;
UPDATE `branches` SET `latitude` = 10.3237, `longitude` = 123.9223 WHERE `id` = 2;

-- New example branches, incl. Cavite as requested.
INSERT INTO `branches` (`id`, `branch_code`, `name`, `address`, `city`, `latitude`, `longitude`, `phone`, `manager_id`, `status`)
VALUES
  (3, 'BR-CAV-01', 'Cavite Branch', 'Emilio Aguinaldo Highway, Brgy. Anabu I-A', 'Imus, Cavite', 14.4297, 120.9367, NULL, NULL, 'active'),
  (4, 'BR-BTG-01', 'Batangas Branch', 'P. Burgos Street, Brgy. Poblacion', 'Batangas City', 13.7565, 121.0583, NULL, NULL, 'active')
ON DUPLICATE KEY UPDATE `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`);

-- Job postings now belong to a specific branch.
ALTER TABLE `job_postings`
  ADD COLUMN `branch_id` INT NULL COMMENT 'which branch this position is hiring for' AFTER `department_id`;

UPDATE `job_postings` SET `branch_id` = 1 WHERE `branch_id` IS NULL;

-- A second Cavite-specific Cashier posting, so the Careers page shows a
-- real per-branch example out of the box.
INSERT INTO `job_postings`
  (`title`, `branch_id`, `department_id`, `position_id`, `description`, `requirements`,
   `salary_min`, `salary_max`, `slots`, `job_type`, `status`, `deadline`)
VALUES
  ('Cashier — Cavite Branch', 3, NULL, NULL,
   'Front-of-store cashier for our Cavite branch. Takes orders, handles payments on the POS, and assists customers.',
   'Friendly and reliable, comfortable handling cash and basic math. No prior experience required — full training is provided.',
   16000, 20000, 2, 'Full-Time', 'open', NULL);

SET @cavite_job_id = (SELECT id FROM job_postings WHERE title = 'Cashier — Cavite Branch' ORDER BY id DESC LIMIT 1);
INSERT INTO `pos_job_postings_meta` (`job_posting_id`, `pos_role_target`, `pos_position_label`)
SELECT @cavite_job_id, 'cashier', 'Cashier'
WHERE @cavite_job_id NOT IN (SELECT `job_posting_id` FROM `pos_job_postings_meta`);

-- Applicants keep a snapshot of the branch they applied to.
ALTER TABLE `applicants`
  ADD COLUMN `branch_id` INT NULL AFTER `job_id`;

UPDATE `applicants` a
  JOIN `job_postings` j ON j.id = a.job_id
  SET a.branch_id = j.branch_id
  WHERE a.branch_id IS NULL;

-- HR / admin logins can now be scoped to one branch (shown as "HR — Cavite
-- Branch" etc. in the header). NULL branch_id = Head Office / all branches.
ALTER TABLE `users`
  ADD COLUMN `branch_id` INT NULL COMMENT 'for hr/admin: the one branch they manage; NULL = all branches (head office)' AFTER `role`;

-- Example: a second HR account scoped to the Cavite branch, so the
-- "HR — Cavite" style login is there to try immediately.
INSERT INTO `users` (`username`, `password`, `email`, `role`, `branch_id`, `firstname`, `lastname`, `status`, `created_at`, `updated_at`)
SELECT 'hr_cavite', '$2b$10$UsYLP50d6udTSK68rQQUqeXfczzSzswRW4t4y7rj/KkkbP6mu7FLq', 'cavite.hr@hrms.com', 'hr', 3, 'Cavite', 'Branch HR', 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'hr_cavite');
-- Uses the SAME password hash as the seed 'hr' account (i.e. the same
-- password as that account was created with) — check hrms README/notes for
-- the original plaintext password, or reset it from Employees → the user's
-- profile after import.

-- ---------------------------------------------------------------------------
-- Milk Tea POS side
-- ---------------------------------------------------------------------------
USE `milk_tea_pos`;

ALTER TABLE `branches`
  ADD COLUMN `latitude`  DECIMAL(10,7) NULL AFTER `city`,
  ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;

UPDATE `branches` SET `latitude` = 10.3157, `longitude` = 123.8854 WHERE `id` = 1;
UPDATE `branches` SET `latitude` = 10.3237, `longitude` = 123.9223 WHERE `id` = 2;

INSERT INTO `branches` (`id`, `branch_code`, `name`, `address`, `city`, `latitude`, `longitude`, `manager_name`, `status`)
VALUES
  (3, 'BR-CAV-01', 'Cavite Branch', 'Emilio Aguinaldo Highway, Brgy. Anabu I-A', 'Imus, Cavite', 14.4297, 120.9367, NULL, 'active'),
  (4, 'BR-BTG-01', 'Batangas Branch', 'P. Burgos Street, Brgy. Poblacion', 'Batangas City', 13.7565, 121.0583, NULL, 'active')
ON DUPLICATE KEY UPDATE `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`);

-- POS logins are tagged with their branch too (kept in sync with the HRMS
-- branch id — both branches tables share the same ids by design).
ALTER TABLE `users`
  ADD COLUMN `branch_id` INT NULL AFTER `position_label`;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
