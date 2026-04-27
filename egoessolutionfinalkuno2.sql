-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.42 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for egoessolution
CREATE DATABASE IF NOT EXISTS `egoessolution` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `egoessolution`;

-- Dumping structure for table egoessolution.app_settings
CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  UNIQUE KEY `uq_app_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.app_settings: ~2 rows (approximately)
INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`) VALUES
	(1, 'deduction_per_minute', '2.00'),
	(2, 'hourly_rate_default', '68.00');

-- Dumping structure for table egoessolution.attendance_logs
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `office_id` int NOT NULL,
  `log_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` enum('present','absent','late','on_leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'present',
  `late_minutes` int DEFAULT NULL,
  `deduction_amount` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.attendance_logs: ~0 rows (approximately)

-- Dumping structure for table egoessolution.cash_advances
CREATE TABLE IF NOT EXISTS `cash_advances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `requested_by_user_id` int DEFAULT NULL,
  `request_source` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `advance_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cash_adv_employee` (`employee_id`),
  KEY `idx_cash_adv_status_date` (`status`,`advance_date`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.cash_advances: ~0 rows (approximately)

-- Dumping structure for table egoessolution.deductions
CREATE TABLE IF NOT EXISTS `deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_item_id` int NOT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `payroll_item_id` (`payroll_item_id`),
  CONSTRAINT `deductions_ibfk_1` FOREIGN KEY (`payroll_item_id`) REFERENCES `payroll_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.deductions: ~0 rows (approximately)

-- Dumping structure for table egoessolution.employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rate_type` enum('hourly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rate_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `date_hired` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=268 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.employees: ~101 rows (approximately)
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `position`, `rate_type`, `rate_amount`, `date_hired`) VALUES
	(137, 215, 'E-00215', NULL, 'hourly', 0.00, NULL),
	(138, 200, 'E-00200', NULL, 'hourly', 0.00, NULL),
	(139, 197, 'E-00197', NULL, 'hourly', 0.00, NULL),
	(140, 159, 'E-00159', NULL, 'hourly', 0.00, NULL),
	(141, 202, 'E-00202', NULL, 'hourly', 0.00, NULL),
	(142, 242, 'E-00242', NULL, 'hourly', 0.00, NULL),
	(143, 168, 'E-00168', NULL, 'hourly', 0.00, NULL),
	(144, 207, 'E-00207', NULL, 'hourly', 0.00, NULL),
	(145, 201, 'E-00201', NULL, 'hourly', 0.00, NULL),
	(146, 142, 'E-00142', NULL, 'hourly', 125.00, NULL),
	(147, 158, 'E-00158', NULL, 'hourly', 0.00, NULL),
	(148, 183, 'E-00183', NULL, 'hourly', 0.00, NULL),
	(149, 155, 'E-00155', NULL, 'hourly', 0.00, NULL),
	(150, 171, 'E-00171', NULL, 'hourly', 0.00, NULL),
	(151, 222, 'E-00222', NULL, 'hourly', 0.00, NULL),
	(152, 165, 'E-00165', NULL, 'hourly', 0.00, NULL),
	(153, 205, 'E-00205', NULL, 'hourly', 0.00, NULL),
	(154, 153, 'E-00153', NULL, 'hourly', 0.00, NULL),
	(155, 206, 'E-00206', NULL, 'hourly', 0.00, NULL),
	(156, 227, 'E-00227', NULL, 'hourly', 0.00, NULL),
	(157, 188, 'E-00188', NULL, 'hourly', 0.00, NULL),
	(158, 176, 'E-00176', NULL, 'hourly', 0.00, NULL),
	(159, 229, 'E-00229', NULL, 'hourly', 0.00, NULL),
	(160, 169, 'E-00169', NULL, 'hourly', 0.00, NULL),
	(161, 182, 'E-00182', NULL, 'hourly', 0.00, NULL),
	(162, 150, 'E-00150', NULL, 'hourly', 0.00, NULL),
	(163, 186, 'E-00186', NULL, 'hourly', 0.00, NULL),
	(164, 156, 'E-00156', NULL, 'hourly', 0.00, NULL),
	(165, 175, 'E-00175', NULL, 'hourly', 0.00, NULL),
	(166, 237, 'E-00237', NULL, 'hourly', 0.00, NULL),
	(167, 224, 'E-00224', NULL, 'hourly', 0.00, NULL),
	(168, 178, 'E-00178', NULL, 'hourly', 0.00, NULL),
	(169, 241, 'E-00241', NULL, 'hourly', 0.00, NULL),
	(170, 216, 'E-00216', NULL, 'hourly', 0.00, NULL),
	(171, 174, 'E-00174', NULL, 'hourly', 0.00, NULL),
	(172, 177, 'E-00177', NULL, 'hourly', 0.00, NULL),
	(173, 230, 'E-00230', NULL, 'hourly', 0.00, NULL),
	(174, 163, 'E-00163', NULL, 'hourly', 0.00, NULL),
	(175, 162, 'E-00162', NULL, 'hourly', 0.00, NULL),
	(176, 164, 'E-00164', NULL, 'hourly', 0.00, NULL),
	(177, 198, 'E-00198', NULL, 'hourly', 0.00, NULL),
	(178, 144, 'E-00144', NULL, 'hourly', 0.00, NULL),
	(179, 173, 'E-00173', NULL, 'hourly', 0.00, NULL),
	(180, 234, 'E-00234', NULL, 'hourly', 0.00, NULL),
	(181, 195, 'E-00195', NULL, 'hourly', 0.00, NULL),
	(182, 203, 'E-00203', NULL, 'hourly', 0.00, NULL),
	(183, 240, 'E-00240', NULL, 'hourly', 0.00, NULL),
	(184, 235, 'E-00235', NULL, 'hourly', 0.00, NULL),
	(185, 194, 'E-00194', NULL, 'hourly', 0.00, NULL),
	(186, 219, 'E-00219', NULL, 'hourly', 0.00, NULL),
	(187, 211, 'E-00211', NULL, 'hourly', 0.00, NULL),
	(188, 190, 'E-00190', NULL, 'hourly', 0.00, NULL),
	(189, 166, 'E-00166', NULL, 'hourly', 0.00, NULL),
	(190, 214, 'E-00214', NULL, 'hourly', 0.00, NULL),
	(191, 231, 'E-00231', NULL, 'hourly', 0.00, NULL),
	(192, 239, 'E-00239', NULL, 'hourly', 0.00, NULL),
	(193, 170, 'E-00170', NULL, 'hourly', 0.00, NULL),
	(194, 204, 'E-00204', NULL, 'hourly', 0.00, NULL),
	(195, 187, 'E-00187', NULL, 'hourly', 0.00, NULL),
	(196, 218, 'E-00218', NULL, 'hourly', 0.00, NULL),
	(197, 199, 'E-00199', NULL, 'hourly', 0.00, NULL),
	(198, 233, 'E-00233', NULL, 'hourly', 0.00, NULL),
	(199, 180, 'E-00180', NULL, 'hourly', 0.00, NULL),
	(200, 143, 'E-00143', NULL, 'hourly', 0.00, NULL),
	(201, 167, 'E-00167', NULL, 'hourly', 0.00, NULL),
	(202, 191, 'E-00191', NULL, 'hourly', 0.00, NULL),
	(203, 161, 'E-00161', NULL, 'hourly', 0.00, NULL),
	(204, 210, 'E-00210', NULL, 'hourly', 0.00, NULL),
	(205, 181, 'E-00181', NULL, 'hourly', 0.00, NULL),
	(206, 228, 'E-00228', NULL, 'hourly', 0.00, NULL),
	(207, 179, 'E-00179', NULL, 'hourly', 0.00, NULL),
	(208, 238, 'E-00238', NULL, 'hourly', 0.00, NULL),
	(209, 145, 'E-00145', NULL, 'hourly', 0.00, NULL),
	(210, 172, 'E-00172', NULL, 'hourly', 0.00, NULL),
	(211, 232, 'E-00232', NULL, 'hourly', 0.00, NULL),
	(212, 226, 'E-00226', NULL, 'hourly', 0.00, NULL),
	(213, 148, 'E-00148', NULL, 'hourly', 0.00, NULL),
	(214, 160, 'E-00160', NULL, 'hourly', 0.00, NULL),
	(215, 223, 'E-00223', NULL, 'hourly', 0.00, NULL),
	(216, 209, 'E-00209', NULL, 'hourly', 0.00, NULL),
	(217, 193, 'E-00193', NULL, 'hourly', 0.00, NULL),
	(218, 146, 'E-00146', NULL, 'hourly', 0.00, NULL),
	(219, 185, 'E-00185', NULL, 'hourly', 0.00, NULL),
	(220, 213, 'E-00213', NULL, 'hourly', 0.00, NULL),
	(221, 221, 'E-00221', NULL, 'hourly', 0.00, NULL),
	(222, 220, 'E-00220', NULL, 'hourly', 0.00, NULL),
	(223, 208, 'E-00208', NULL, 'hourly', 0.00, NULL),
	(224, 192, 'E-00192', NULL, 'hourly', 0.00, NULL),
	(225, 151, 'E-00151', NULL, 'hourly', 0.00, NULL),
	(226, 184, 'E-00184', NULL, 'hourly', 0.00, NULL),
	(227, 189, 'E-00189', NULL, 'hourly', 0.00, NULL),
	(228, 212, 'E-00212', NULL, 'hourly', 0.00, NULL),
	(229, 225, 'E-00225', NULL, 'hourly', 0.00, NULL),
	(230, 149, 'E-00149', NULL, 'hourly', 0.00, NULL),
	(231, 147, 'E-00147', NULL, 'hourly', 0.00, NULL),
	(232, 217, 'E-00217', NULL, 'hourly', 0.00, NULL),
	(233, 152, 'E-00152', NULL, 'hourly', 0.00, NULL),
	(234, 196, 'E-00196', NULL, 'hourly', 0.00, NULL),
	(235, 154, 'E-00154', NULL, 'hourly', 0.00, NULL),
	(236, 157, 'E-00157', NULL, 'hourly', 0.00, NULL),
	(237, 236, 'E-00236', NULL, 'hourly', 0.00, NULL);

-- Dumping structure for table egoessolution.employee_memos
CREATE TABLE IF NOT EXISTS `employee_memos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `office_id` int NOT NULL,
  `issued_by` int NOT NULL,
  `violation_code` varchar(50) NOT NULL,
  `violation_name` varchar(255) NOT NULL,
  `offense_number` int NOT NULL,
  `consequence` varchar(255) NOT NULL,
  `consequence_type` varchar(30) NOT NULL,
  `suspension_days` int DEFAULT NULL,
  `suspension_start` date DEFAULT NULL,
  `suspension_end` date DEFAULT NULL,
  `memo_notes` text,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_memos_user` (`user_id`),
  KEY `idx_employee_memos_office` (`office_id`),
  KEY `idx_employee_memos_violation` (`violation_code`),
  KEY `idx_employee_memos_status` (`status`),
  KEY `idx_employee_memos_office_created` (`office_id`,`created_at`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.employee_memos: ~0 rows (approximately)

-- Dumping structure for table egoessolution.leave_requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `leave_type` varchar(50) NOT NULL,
  `leave_other_specify` varchar(150) DEFAULT NULL,
  `employment_status` varchar(30) DEFAULT NULL,
  `campaign` varchar(120) DEFAULT NULL,
  `supervisor_name` varchar(120) DEFAULT NULL,
  `filing_date` date NOT NULL DEFAULT '1970-01-01',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int NOT NULL DEFAULT '0',
  `day_types` varchar(60) DEFAULT NULL,
  `shift_schedule` varchar(80) DEFAULT NULL,
  `half_day_option` varchar(20) DEFAULT NULL,
  `supporting_documents` varchar(255) DEFAULT NULL,
  `supporting_document_image` varchar(255) DEFAULT NULL,
  `supporting_other_text` varchar(150) DEFAULT NULL,
  `coverage_arrangement` varchar(80) DEFAULT NULL,
  `coverage_other_text` varchar(150) DEFAULT NULL,
  `covering_employee` varchar(120) DEFAULT NULL,
  `contact_during_leave` varchar(80) DEFAULT NULL,
  `reason` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_requests_user` (`user_id`),
  KEY `idx_leave_requests_status` (`status`),
  KEY `idx_leave_requests_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.leave_requests: ~0 rows (approximately)

-- Dumping structure for table egoessolution.memos
CREATE TABLE IF NOT EXISTS `memos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.memos: ~0 rows (approximately)

-- Dumping structure for table egoessolution.offices
CREATE TABLE IF NOT EXISTS `offices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `working_start_time` time DEFAULT NULL,
  `working_end_time` time DEFAULT NULL,
  `team_leader` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `team_leader_user_id` int DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_offices_team_leader_user` (`team_leader_user_id`),
  CONSTRAINT `fk_offices_team_leader_user` FOREIGN KEY (`team_leader_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.offices: ~6 rows (approximately)
INSERT INTO `offices` (`id`, `name`, `address`, `working_start_time`, `working_end_time`, `team_leader`, `is_active`, `team_leader_user_id`, `time_in`, `time_out`) VALUES
	(5, 'Team Hanes', 'Tirad Pass', NULL, NULL, 'Reponte, Hanes Michael', 1, 144, '20:00:00', '05:00:00'),
	(6, 'Team Lester', 'Tirad Pass', NULL, NULL, 'Paragile, Lester', 1, 145, '20:00:00', '05:00:00'),
	(7, 'Team Lourince', 'Tirad Pass', NULL, NULL, 'Daging, Lourince', 1, 148, '20:00:00', '05:00:00'),
	(8, 'Team Ronnie C', 'Terminal', NULL, NULL, 'Cañar Jr., Ronnie', 1, 149, '21:00:00', '06:00:00'),
	(9, 'Team Marianyl', 'Terminal', NULL, NULL, 'Lampitao, Marianyl', 1, 146, '21:00:00', '06:00:00'),
	(10, 'Team Ronnie D', 'Terminal', NULL, NULL, 'Dimas Jr., Ronnie', 1, 147, '21:00:00', '06:00:00');

-- Dumping structure for table egoessolution.payroll_deduction_types
CREATE TABLE IF NOT EXISTS `payroll_deduction_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(128) NOT NULL,
  `default_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.payroll_deduction_types: ~3 rows (approximately)
INSERT INTO `payroll_deduction_types` (`id`, `label`, `default_amount`, `created_at`) VALUES
	(1, 'SSS Contribution', 150.00, '2026-03-30 19:00:58'),
	(2, 'PhilHealth', 60.00, '2026-03-30 19:00:58'),
	(3, 'Pag-IBIG', 100.00, '2026-03-30 19:00:58');

-- Dumping structure for table egoessolution.payroll_items
CREATE TABLE IF NOT EXISTS `payroll_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_period_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `total_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `payroll_period_id` (`payroll_period_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payroll_items_ibfk_1` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`),
  CONSTRAINT `payroll_items_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.payroll_items: ~0 rows (approximately)

-- Dumping structure for table egoessolution.payroll_periods
CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `office_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','processing','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `payroll_periods_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.payroll_periods: ~0 rows (approximately)

-- Dumping structure for table egoessolution.payroll_receipts
CREATE TABLE IF NOT EXISTS `payroll_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `period_type` varchar(10) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_receipt` (`employee_id`,`period_type`,`period_start`,`period_end`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.payroll_receipts: ~0 rows (approximately)

-- Dumping structure for table egoessolution.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `office_id` int DEFAULT NULL,
  `role` enum('superadmin','admin','employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `profile_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.users: ~102 rows (approximately)
INSERT INTO `users` (`id`, `office_id`, `role`, `full_name`, `email`, `password_hash`, `profile_image`, `is_active`, `created_at`) VALUES
	(1, 1, 'superadmin', 'admin, super', 'superadmin@egoes.com', '$2y$10$gd2ix9tBZaYxk5gYgNcz1O7.mM4FiZmQe0tuBwXGMbLCN8BPqaYr2', '../assets/images/profile/profile_1_1776684203.png', 1, '2026-03-27 17:35:46'),
	(142, 5, 'employee', 'Villanueva, Anna Marie', 'annamarievillanueva@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(143, 10, 'employee', 'Barbadillo, Kim Lloyd', 'kimlloydbarbadillo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(144, 5, 'admin', 'Reponte, Hanes Michael', 'hanesmichaelreponte@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(145, 6, 'admin', 'Paragile, Lester', 'lesterparagile@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(146, 9, 'admin', 'Lampitao, Marianyl', 'marianyllampitao@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(147, 10, 'admin', 'Dimas Jr., Ronnie', 'ronniedimas@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(148, 7, 'admin', 'Daging, Lourince', 'lourincedaging@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(149, 8, 'admin', 'Cañar Jr., Ronnie', 'ronniecanar@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(150, 5, 'employee', 'Altamera, CJ', 'cjaltamera@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(151, 5, 'employee', 'Badillo, Princess Hope', 'princesshopebadillo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(152, 5, 'employee', 'Basmayon, Sherwin', 'sherwinbasmayon@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(153, 5, 'employee', 'Brillo, Carl Amyd', 'carlamydbrillo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(154, 5, 'employee', 'Buno, Vincent Fel', 'vincentfelbuno@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(155, 5, 'employee', 'Dawing, Bernadeth', 'bernadethdawing@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(156, 5, 'employee', 'Del Rosario, Clifford', 'clifforddelrosario@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(157, 5, 'employee', 'Dela Pena, Yna Kristine', 'ynakristinedelapena@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(158, 5, 'employee', 'Engbino, Ara', 'araengbino@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(159, 5, 'employee', 'Flores, Angel Maxine', 'angelmaxineflores@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(160, 5, 'employee', 'Gabuya, Lourince', 'lourincegabuya@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(161, 5, 'employee', 'Hagonos, Kristel', 'kristelhagonos@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(162, 5, 'employee', 'Ganad, Gena', 'genaganad@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(163, 5, 'employee', 'Javellana Jr., Florencio', 'florenciojavellana@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(164, 5, 'employee', 'Labiton, Gerald', 'geraldlabiton@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(165, 5, 'employee', 'Labuni, Bonn Enrico', 'bonnenricolabuni@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(166, 5, 'employee', 'Mentino, Johnrey', 'johnreymentino@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(167, 5, 'employee', 'Mirafuentes, Kissie', 'kissiemirafuentes@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(168, 5, 'employee', 'Mindo, Angelyn', 'angelynmindo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(169, 5, 'employee', 'Sultan, Christine', 'christinesultan@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(170, 6, 'employee', 'Barera, Juan Paolo', 'juanpaolobarera@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(171, 6, 'employee', 'Bitazar, Bernie', 'berniebitazar@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(172, 6, 'employee', 'Cala, Loreen', 'loreencala@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(173, 6, 'employee', 'Castillo, Haniyah', 'haniyahcastillo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(174, 6, 'employee', 'Ebreo, Elaine', 'elaineebreo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(175, 6, 'employee', 'Gargar, Cyd', 'cydgargar@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(176, 6, 'employee', 'Inot, Christian', 'christianinot@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(177, 6, 'employee', 'Felipe, Emerald', 'emeraldfelipe@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(178, 6, 'employee', 'Liray, Dee Lawrence', 'deelawrenceliray@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(179, 7, 'employee', 'Morata, Lea', 'leamorata@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(180, 7, 'employee', 'Alberca, Kian', 'kianalberca@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(181, 7, 'employee', 'Amatong, Lady Joy', 'ladyjoyamatong@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(182, 7, 'employee', 'Abatayo, Chylle', 'chylleabatayo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(183, 7, 'employee', 'Austria, Arvie', 'arvieaustria@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(184, 7, 'employee', 'Catane, Rachell', 'rachellcatane@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(185, 7, 'employee', 'Damsid, Mariella', 'marielladamsid@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(186, 7, 'employee', 'Talledo, Claire', 'clairetalledo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(187, 7, 'employee', 'Ramos, Jyceneth', 'jycenethramos@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(188, 7, 'employee', 'Adormeo, Christian', 'christianadormeo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(189, 7, 'employee', 'Enghog, Ranerv', 'ranervenghog@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(190, 7, 'employee', 'Abadiez, Jhony Roy', 'jhonyroyabadiez@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(191, 7, 'employee', 'Celin, Krissa Jane', 'krissajanecelin@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(192, 7, 'employee', 'Leyson, Princess Diane', 'princessdianeleyson@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(193, 7, 'employee', 'Solis, Marc Nataniel', 'marcnatanielsolis@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(194, 7, 'employee', 'Rebucas, Jemylyn', 'jemylynrebucas@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(195, 7, 'employee', 'Gonzaga, Jake', 'jakegonzaga@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(196, 7, 'employee', 'Lisondra, Shiela Marie', 'shielamarielisondra@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(197, 7, 'employee', 'Hinoguin, Ana Rose', 'anarosehinoguin@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(198, 7, 'employee', 'Tungal, Grechel', 'grecheltungal@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(199, 7, 'employee', 'Quimada, Karen', 'karenquimada@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(200, 8, 'employee', 'Abalayan, Alce Mae', 'alcemaeabalayan@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(201, 8, 'employee', 'Arreza, Anjayla', 'anjaylaarreza@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(202, 8, 'employee', 'Amacio, Angelo', 'angeloamacio@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(203, 8, 'employee', 'Anas, Jan', 'jananas@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(204, 8, 'employee', 'Bautista, Judy', 'judybautista@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(205, 8, 'employee', 'Bunod, Brazell', 'brazellbunod@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(206, 8, 'employee', 'Cuadra, Cassiopia', 'cassiopiacuadra@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(207, 8, 'employee', 'Donayre, Anikka', 'anikkadonayre@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(208, 8, 'employee', 'Flores, Princess Abegail', 'princessabegailflores@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(209, 8, 'employee', 'Torres, Lux', 'luxtorres@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(210, 8, 'employee', 'Malinao, Kurt', 'kurtmalinao@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(211, 8, 'employee', 'Sialonggo, Jessie', 'jessiesialonggo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(212, 8, 'employee', 'Tabasa, Ressamae', 'ressamaetabasa@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(213, 8, 'employee', 'Yecyec, Mekyla', 'mekylayecyec@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(214, 9, 'employee', 'Adona, Jolia Denise', 'joliadeniseadona@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(215, 9, 'employee', 'Alberca, Aireen', 'aireenalberca@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(216, 9, 'employee', 'Balboa, Edmund John', 'edmundjohnbalboa@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(217, 9, 'employee', 'Binoya, Rose', 'rosebinoya@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(218, 9, 'employee', 'Bughaw, Karen', 'karenbughaw@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(219, 9, 'employee', 'Caballero, Jenny', 'jennycaballero@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(220, 9, 'employee', 'Dag-uman, Precious', 'preciousdaguman@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(221, 9, 'employee', 'Duhilag, Novie Jean', 'noviejeanduhilag@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(222, 9, 'employee', 'Gambong, Blessie', 'blessiegambong@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(223, 9, 'employee', 'Gapor, Lovely Kate', 'lovelykategapor@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(224, 9, 'employee', 'Jamad, Davey', 'daveyjamad@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(225, 9, 'employee', 'Martizano, Reymark', 'reymarkmartizano@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(226, 9, 'employee', 'Matab, Louise Mikaella', 'louisemikaellamatab@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(227, 9, 'employee', 'Mentino, Cherie Ann', 'cherieannmentino@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(228, 9, 'employee', 'Pacheco, Leah', 'leahpacheco@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(229, 9, 'employee', 'Robledo, Christine', 'christinerobledo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(230, 9, 'employee', 'Sabay, Faith', 'faithsabay@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(231, 9, 'employee', 'Sombilon, Joriz', 'jorizsombilon@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(232, 9, 'employee', 'Superales, Lorive', 'lorivesuperales@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(233, 9, 'employee', 'Tawi, Khenrich', 'khenrichtawi@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(234, 9, 'employee', 'Velchez, Irish', 'irishvelchez@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(235, 10, 'employee', 'Estoconing, Jasmin Beth', 'jasminbethestoconing@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(236, 10, 'employee', 'Abapo, Zein', 'zeinabapo@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(237, 10, 'employee', 'Alquizar, Dante', 'dantealquizar@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(238, 10, 'employee', 'Amad, Lee Arthur', 'leearthuramad@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(239, 10, 'employee', 'Madrid, Joy Love', 'joylovemadrid@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(240, 10, 'employee', 'Serrano, Jan', 'janserrano@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(241, 10, 'employee', 'Parantar, Deza', 'dezaparantar@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30'),
	(242, 10, 'employee', 'Ymas, Angelo', 'angeloymas@egoes.com', '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2', NULL, 1, '2026-03-27 17:32:30');

-- Dumping structure for table egoessolution.user_profiles
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.user_profiles: ~3 rows (approximately)
INSERT INTO `user_profiles` (`id`, `user_id`, `nickname`, `first_name`, `last_name`, `avatar`, `date_of_birth`, `gender`, `address`, `phone`, `email`, `created_at`, `updated_at`) VALUES
	(6, 142, NULL, 'Villanueva,', 'Anna Marie', NULL, NULL, NULL, NULL, NULL, 'annamarievillanueva@egoes.com', '2026-04-13 16:57:16', '2026-04-13 16:57:16'),
	(12, 144, NULL, 'Hanes Michael', 'Reponte', NULL, NULL, NULL, NULL, NULL, 'hanesmichaelreponte@egoes.com', '2026-04-15 08:55:52', '2026-04-15 08:56:25'),
	(26, 1, NULL, 'super', 'admin', NULL, NULL, NULL, NULL, NULL, 'superadmin@egoes.com', '2026-04-20 11:23:23', '2026-04-20 11:23:23');

-- Dumping structure for table egoessolution.violation_letter_templates
CREATE TABLE IF NOT EXISTS `violation_letter_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `subject_template` varchar(255) NOT NULL,
  `body_template` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=1378 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.violation_letter_templates: ~59 rows (approximately)
INSERT INTO `violation_letter_templates` (`id`, `code`, `subject_template`, `body_template`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'A-01', 'Subject: Formal Notice: Late Login/Arrival (Code A-01)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notice of your violation of Code A-01. On [Date], you reported to work at [Time], which is past your scheduled shift start. Punctuality is a requirement of your role, and this incident has been documented in your 201 file.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(2, 'A-02', 'Subject: Formal Notice: Accumulated Tardiness (Code A-02)', 'Dear [Employee Name],\\n\\nRecords indicate you have been late three times within the current month. This is a violation of Code A-02. Frequent tardiness disrupts operations and is a breach of company policy. This notice serves as a formal disciplinary record of this occurrence.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(3, 'A-03', 'Subject: Formal Notice: Unexcused Absence (Code A-03)', 'Dear [Employee Name],\\n\\nThis letter documents your unexcused absence on [Date]. You failed to report for your shift without prior authorization or a valid excuse. This violation of Code A-03 has been recorded, and immediate improvement in your attendance reliability is required.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(4, 'A-04', 'Subject: Formal Notice: AWOL (Code A-04)', 'Dear [Employee Name],\\n\\nOn [Date], you were absent from work without filing an official leave or receiving authorization. This is a violation of Code A-04. Unauthorized absence is a serious breach of your employment contract and protocol.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(5, 'A-05', 'Subject: Formal Notice: No-Call, No-Show (Code A-05)', 'Dear [Employee Name],\\n\\nYou failed to report to work and did not notify management of your absence on [Date]. This No-Call, No-Show status is a violation of Code A-05. Failure to communicate your status during working hours is unacceptable.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(6, 'A-06', 'Subject: Formal Notice: Excessive Absences (Code A-06)', 'Dear [Employee Name],\\n\\nYour attendance has dropped below the required 95% threshold, violating Code A-06. Consistent attendance is a core requirement of your position. This notice serves as a formal warning regarding your attendance reliability.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(7, 'UG-01', 'Subject: Formal Notice: Uniform Violation (Code UG-01)', 'Dear [Employee Name],\\n\\nOn [Date], you were observed on duty without the prescribed company uniform. This is a violation of Code UG-01. You are required to wear the full uniform at all times while on the production floor.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(8, 'UG-02', 'Subject: Formal Notice: Unauthorized Attire (Code UG-02)', 'Dear [Employee Name],\\n\\nYou reported to work on [Date] wearing unauthorized attire, which is a violation of Code UG-02. All employees must adhere to the specific dress code standards outlined in the employee handbook.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(9, 'UG-03', 'Subject: Formal Notice: Grooming Standards (Code UG-03)', 'Dear [Employee Name],\\n\\nOn [Date], your physical presentation did not meet the company’s grooming standards. This is a violation of Code UG-03. Maintaining a neat and professional appearance is mandatory during work hours.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(10, 'MI-01', 'Subject: Formal Notice: Unprofessional Behavior (Code MI-01)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notice regarding your conduct on [Date]. Your behavior was found to be unprofessional and inconsistent with company standards. This violation of Code MI-01 has been documented in your personnel file.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(11, 'MI-02', 'Subject: Formal Notice: Disruptive Behavior (Code MI-02)', 'Dear [Employee Name],\\n\\nThis notice is issued for a violation of Code MI-02. On [Date], you engaged in boisterous or disruptive behavior that interfered with the workplace environment. We expect all employees to maintain a professional demeanor that respects the productivity of others.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(12, 'MI-03', 'Subject: Formal Notice: Failure to Follow Instructions (Code MI-03)', 'Dear [Employee Name],\\n\\nOn [Date], you failed to comply with specific instructions provided by leadership. This is a violation of Code MI-03. Following management directives is a fundamental requirement of your position.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(13, 'MA-10', 'Subject: Formal Notice: Disrespectful Conduct (Code MA-10)', 'Dear [Employee Name],\\n\\nThis letter documents a formal violation of Code MA-10. On [Date], you displayed disrespectful behavior toward a [Colleague/TL/OM/Client]. The company maintains a strict policy regarding mutual respect in all professional interactions.', 1, '2026-04-16 16:19:02', '2026-04-16 16:19:02'),
	(14, 'MA-11', 'Subject: Formal Notice: Abusive Language (Code MA-11)', 'Dear [Employee Name],\\n\\nYou are being cited for violation of Code MA-11. On [Date], you used abusive language within the workplace. Such language is prohibited and creates a hostile environment. This notice serves as a serious warning regarding your conduct.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(15, 'MA-12', 'Subject: Disciplinary Notice: Harassment/Bullying (Code MA-12)', 'Dear [Employee Name],\\n\\nThis notice is issued for a violation of Code MA-12. Investigation has confirmed your involvement in [Harassment/Bullying/Discrimination] on [Date]. The company has a zero-tolerance policy for this behavior.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(16, 'MA-13', 'Subject: Formal Notice: Insubordination (Code MA-13)', 'Dear [Employee Name],\\n\\nThis letter documents a violation of Code MA-13. On [Date], you intentionally refused to follow a lawful order or directive. Insubordination is a major offense that undermines operational structure and will not be tolerated.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(17, 'MA-14', 'Subject: Formal Notice: Sleeping During Shift (Code MA-14)', 'Dear [Employee Name],\\n\\nOn [Date], you were observed sleeping during your scheduled work hours. This is a violation of Code MA-14. Employees are expected to remain alert and productive throughout their assigned shift.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(18, 'B-01', 'Subject: Formal Notice: Overbreak (Code B-01)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notice of a violation of Code B-01. On [Date], you exceeded your allotted break time. Proper schedule adherence is necessary to ensure adequate coverage. This incident has been documented in your personnel file.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(19, 'B-02', 'Subject: Formal Notice: Unauthorized Break/Loitering (Code B-02)', 'Dear [Employee Name],\\n\\nYou are being cited for a violation of Code B-02. On [Date], you were observed taking an unauthorized break or loitering during production hours. You are expected to be at your workstation and ready for duty outside of your scheduled break times.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(20, 'B-03', 'Subject: Formal Notice: Early Logout (Code B-03)', 'Dear [Employee Name],\\n\\nThis notice is issued for a violation of Code B-03. Records show that on [Date], you logged out of your station and ended your shift prior to your scheduled logout time without prior authorization.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(21, 'B-04', 'Subject: Formal Notice: Low Schedule Adherence (Code B-04)', 'Dear [Employee Name],\\n\\nA review of your performance logs shows that your schedule adherence has fallen below the acceptable company standard. This violation of Code B-04 impacts our ability to meet service levels. Immediate improvement in following your assigned schedule is required.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(22, 'P-01', 'Subject: Formal Notice: Performance Deficiency (Code P-01)', 'Dear [Employee Name],\\n\\nDespite previous coaching sessions, you have failed to meet the required Key Performance Indicators (KPIs). This is a violation of Code P-01. As a result, you are being placed on a Performance Improvement Plan (PIP) to monitor your progress.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(23, 'P-02', 'Subject: Formal Notice: Repeated Quality Failures (Code P-02)', 'Dear [Employee Name],\\n\\nThis letter documents a violation of Code P-02. Due to repeated failure to meet quality standards during audits, you are being issued this notice. Continued failure to improve quality will result in further disciplinary action or extension of your PIP.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(24, 'P-03', 'Subject: Disciplinary Notice: Intentional Call Avoidance (Code P-03)', 'Dear [Employee Name],\\n\\nInvestigation has confirmed intentional call avoidance or call dropping on [Date]. This is a violation of Code P-03. This behavior is a serious breach of duty that directly affects our customers and operations.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(25, 'P-04', 'Subject: Formal Notice: Mishandling of Data (Code P-04)', 'Dear [Employee Name],\\n\\nOn [Date], it was determined that you mishandled customer data, resulting in a negative impact on service quality. This violation of Code P-04 is a serious matter. We expect all data to be handled with the highest level of accuracy and care.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(26, 'P-05', 'Subject: Formal Notice: Failure to Follow Call Flow (Code P-05)', 'Dear [Employee Name],\\n\\nQuality monitoring has identified that you failed to follow the prescribed call flow on [Date]. This is a violation of Code P-05. Adhering to the established call flow is mandatory to ensure service consistency and compliance.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(27, 'DP-01', 'Subject: Notice of Immediate Termination (Code DP-01)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notification that your employment is terminated effective immediately. An investigation has confirmed that you violated Code DP-01 by sharing confidential company or client information. This breach of our data privacy agreement warrants immediate dismissal for cause.', 1, '2026-04-16 16:19:03', '2026-04-16 16:19:03'),
	(28, 'DP-02', 'Subject: Notice of Immediate Termination (Code DP-02)', 'Dear [Employee Name],\\n\\nEffective immediately, your employment is terminated. Investigation confirmed that on [Date], you mishandled sensitive customer data in violation of Code DP-02. This action represents a critical security risk and a failure to uphold our data handling standards.', 1, '2026-04-16 16:19:04', '2026-04-16 16:19:04'),
	(29, 'DP-03', 'Subject: Notice of Immediate Termination (Code DP-03)', 'Dear [Employee Name],\\n\\nYou are hereby notified that your employment is terminated effective immediately. Our security logs indicate that you accessed unauthorized systems or accounts, violating Code DP-03. This unauthorized access is a major security breach and results in summary dismissal.', 1, '2026-04-16 16:19:04', '2026-04-16 16:19:04'),
	(30, 'DP-04', 'Subject: Notice of Immediate Termination (Code DP-04)', 'Dear [Employee Name],\\n\\nThis letter is to inform you that your employment is terminated effective immediately. You were found to have taken unauthorized screenshots, photos, or recordings within a secure work area, which is a violation of Code DP-04. This compromise of workplace security warrants immediate termination.', 1, '2026-04-16 16:19:04', '2026-04-16 16:19:04'),
	(31, 'DP-05', 'Subject: Notice of Immediate Termination (Code DP-05)', 'Dear [Employee Name],\\n\\nEffective immediately, your employment is terminated. You violated Code DP-05 by allowing another individual to use your personal log-in credentials. Sharing credentials undermines our security protocols and is a grounds for immediate dismissal.', 1, '2026-04-16 16:19:04', '2026-04-16 16:19:04'),
	(32, 'DP-06', 'Subject: Notice of Immediate Termination and Legal Action (Code DP-06)', 'Dear [Employee Name],\\n\\nYour employment is terminated effective immediately due to a severe breach of compliance standards under Code DP-06. This violation involves a failure to adhere to mandatory regulatory requirements (GDPR/HIPAA). Please be advised that the company reserves the right to pursue further legal action relative to this breach.', 1, '2026-04-16 16:19:04', '2026-04-16 16:19:04'),
	(289, 'UB-10', 'Subject: Formal Notice: Unauthorized Browsing (Code UB-10)', 'Dear [Employee Name],\\n\\nMonitoring indicates that on [Date], you accessed non-work-related websites while on active duty. This is a violation of Code UB-10. Company resources must be dedicated to work tasks only.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(290, 'UB-11', 'Subject: Formal Notice: Unauthorized Media Usage (Code UB-11)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notification of a violation of Code UB-11. Monitoring of your workstation on [Date] confirmed that you were streaming videos or accessing social media platforms during your active work hours. Company systems and internet bandwidth are strictly reserved for business-related tasks. This incident has been documented in your personnel file.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(291, 'UB-12', 'Subject: Formal Notice: Unauthorized Tool Usage (Code UB-12)', 'Dear [Employee Name],\\n\\nOn [Date], you were observed using a mobile phone or unauthorized messaging app during work hours. This is a violation of Code UB-12. Please adhere to the company\'s policy regarding personal device usage.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(292, 'SC-01', 'Subject: Formal Notice: Refusal of Supervisor Call (Code SC-01)', 'Dear [Employee Name],\\n\\nOn [Date], you refused to accept a required supervisor call or escalation as directed. This is a violation of Code SC-01. Handling escalated calls is a core responsibility of your role to ensure customer satisfaction and operational flow.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(293, 'SC-02', 'Subject: Formal Notice: Escalation Avoidance (Code SC-02)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notice regarding your conduct on [Date]. Records indicate that you intentionally logged out of your station or went on a break specifically to avoid a pending supervisor escalation or a required call.\\n\\nThis behavior is a violation of Code SC-02. Avoiding escalations disrupts our service levels and places an unfair burden on your teammates and leadership. Consistent handling of all assigned tasks, including escalations, is a requirement of your role.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(294, 'SC-03', 'Subject: Formal Notice: Defiance of Escalation Directive (Code SC-03)', 'Dear [Employee Name],\\n\\nThis notice is issued for a violation of Code SC-03. On [Date], you demonstrated intentional defiance of a direct instruction to handle an escalated call. This level of refusal is a serious breach of duty and will result in immediate disciplinary action as per the company matrix.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(295, 'FT-01', 'Subject: Notice of Immediate Termination (Code FT-01)', 'Dear [Employee Name],\\n\\nThis letter serves as notice of your immediate termination for violation of Code FT-01. It has been determined that you engaged in fraud, falsification, or the unauthorized tampering of company records. This represents a total breach of professional integrity.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(296, 'FT-02', 'Subject: Notice of Immediate Termination (Code FT-02)', 'Dear [Employee Name],\\n\\nEffective immediately, your employment is terminated for the theft of company property under Code FT-02. The unauthorized removal of company assets is a criminal matter and results in immediate summary dismissal.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(297, 'FT-03', 'Subject: Notice of Immediate Termination (Code FT-03)', 'Dear [Employee Name],\\n\\nYou are hereby notified that your employment is terminated effective immediately. Investigation has confirmed that you intentionally manipulated productivity metrics to misrepresent your performance. This violation of Code FT-03 constitutes workplace fraud.', 1, '2026-04-16 16:58:28', '2026-04-16 16:58:28'),
	(298, 'FT-04', 'Subject: Notice of Immediate Termination (Code FT-04)', 'Dear [Employee Name],\\n\\nThis letter informs you of your immediate termination for violation of Code FT-04. You were found to have misused company systems or data for unauthorized personal gain. This is a severe violation of the company’s trust and policy.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(299, 'FT-05', 'Subject: Notice of Immediate Termination (Code FT-05)', 'Dear [Employee Name],\\n\\nYour employment is terminated effective immediately. You violated Code FT-05 by accepting bribes or unauthorized benefits in connection with your professional duties. This unethical conduct results in immediate termination for cause.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(300, 'SA-01', 'Subject: Notice of Immediate Termination (Code SA-01)', 'Dear [Employee Name],\\n\\nEffective immediately, your employment is terminated for violation of Code SA-01. On [Date], it was determined that you reported to work under the influence of prohibited substances. This represents an unacceptable safety risk to yourself and others in the workplace.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(301, 'SA-02', 'Subject: Notice of Immediate Termination (Code SA-02)', 'Dear [Employee Name],\\n\\nThis letter serves as formal notification that your employment is terminated effective immediately. On [Date], you were found to be in possession of alcohol or illegal substances within the workplace premises. This is a direct violation of Code SA-02. Given the severity of this safety breach and our zero-tolerance policy regarding controlled substances, summary dismissal is applied.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(302, 'SA-03', 'Subject: Formal Notice: Smoking Violation (Code SA-03)', 'Dear [Employee Name],\\n\\nThis notice is issued regarding a violation of Code SA-03. On [Date], you were observed smoking in an unauthorized area of the building. This pose a significant safety and fire risk to the facility and its occupants. Per the company matrix, this offense warrants [Suspension/Termination]. Immediate compliance with designated smoking area policies is mandatory.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(303, 'SA-04', 'Subject: Formal Notice: Safety Violation (Code SA-04)', 'Dear [Employee Name],\\n\\nOn [Date], you engaged in an act that endangered the health or safety of yourself or others in the workplace. This is a violation of Code SA-04. Maintaining a safe work environment is a priority, and your actions have been deemed a serious breach of this standard. Per the disciplinary matrix, you are issued a [Suspension/Termination].', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(304, 'CP-01', 'Subject: Formal Notice: Misuse of Equipment (Code CP-01)', 'Dear [Employee Name],\\n\\nThis letter serves as a formal notice for violation of Code CP-01. On [Date], it was determined that you misused company equipment for purposes other than their intended business use. Company assets must be handled with care and used strictly for authorized tasks. This incident has been recorded as your [1st/2nd/3rd] offense.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(305, 'CP-02', 'Subject: Formal Notice: Unauthorized Software Installation (Code CP-02)', 'Dear [Employee Name],\\n\\nMonitoring has identified that on [Date], you installed unauthorized software on your company-issued device. This is a violation of Code CP-02 and poses a high security risk to our network. As per the matrix, you are issued a [Final Warning/Suspension]. You are reminded that all software installations must be pre-approved by the IT department.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(306, 'CP-03', 'Subject: Formal Notice: Equipment Neglect (Code CP-03)', 'Dear [Employee Name],\\n\\nThis letter documents a violation of Code CP-03. On [Date], your neglect in handling company equipment resulted in physical damage to [List Equipment]. All employees are responsible for the proper care and maintenance of the tools provided. Based on the offense level, you are issued a [Final Warning/Suspension/Termination].', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(307, 'CP-04', 'Subject: Formal Notice: Loss of Company Asset (Code CP-04)', 'Dear [Employee Name],\\n\\nOn [Date], it was reported that a company asset assigned to you was lost due to negligence. This is a violation of Code CP-04. Per company policy, you are held liable for the value of the lost asset. Additionally, you are issued a [Suspension/Termination] based on the circumstances of the loss.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(308, 'CB-01', 'Subject: Notice of Immediate Termination - Critical Misconduct', 'Dear [Employee Name],\\n\\nThis letter serves as formal notification that your employment is terminated effective immediately, [Date]. An investigation has confirmed that you engaged in harassment, discrimination, or threats within the workplace. The company maintains a zero-tolerance policy for this behavior. Please return all company property to the Human Resources department immediately.', 1, '2026-04-16 16:58:29', '2026-04-16 17:09:01'),
	(309, 'CV-02', 'Subject: Notice of Immediate Termination - Workplace Violence', 'Dear [Employee Name],\\n\\nEffective immediately, your employment with the company is terminated for cause. On [Date], you were involved in a physical assault on company premises. Acts of violence are a fundamental breach of our safety protocols and Code of Conduct. You are prohibited from entering company premises effective immediately.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(310, 'CV-03', 'Subject: Notice of Immediate Termination - Fraud and Theft', 'Dear [Employee Name],\\n\\nYour employment is terminated effective immediately following the discovery of fraud or theft involving company assets. This violation of company trust and misappropriation of property warrants summary dismissal. The company reserves the right to pursue further legal action and restitution for the lost assets or funds.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(311, 'CV-04', 'Subject: Notice of Immediate Termination - Data Security Breach', 'Dear [Employee Name],\\n\\nThis letter informs you of your immediate termination due to a confirmed data breach. It was determined that your actions compromised sensitive information, violating our data privacy obligations to our clients and regulatory bodies. Your employment is ended effective today.', 1, '2026-04-16 16:58:29', '2026-04-16 16:58:29'),
	(312, 'CV-05', 'Subject: Notice of Immediate Termination - Malicious Property Damage', 'Dear [Employee Name],\\n\\nEffective immediately, your employment is terminated for the intentional damage of company property. Malicious destruction of company assets is a severe violation of policy. You will be held financially liable for the repair or replacement costs of the damaged equipment.', 1, '2026-04-16 16:58:30', '2026-04-16 16:58:30'),
	(313, 'CV-06', 'Subject: Notice of Termination - Job Abandonment', 'Dear [Employee Name],\\n\\nFollowing your repeated failure to report for work without official leave or notification, your employment is terminated effective immediately. This pattern of being Absent Without Official Leave (AWOL) constitutes job abandonment and a failure to meet the requirements of your employment contract.', 1, '2026-04-16 16:58:30', '2026-04-16 16:58:30'),
	(314, 'CV-07', 'Subject: Notice of Immediate Termination - Major Insubordination', 'Dear [Employee Name],\\n\\nThis letter serves as notice of your immediate termination for serious insubordination. On [Date], you demonstrated a willful and flagrant refusal to follow a lawful and reasonable directive from leadership. Such defiance undermines the operational integrity of the company and warrants immediate dismissal.', 1, '2026-04-16 16:58:30', '2026-04-16 16:58:30'),
	(315, 'CV-08', 'Subject: Notice of Immediate Termination - Critical Risk Violation', 'Dear [Employee Name],\\n\\nYour employment is terminated effective immediately due to actions that placed the company, our clients, or our customers at significant risk. Due to the gravity of the potential impact and the liability caused by these actions, we have determined that your continued employment is no longer possible.', 1, '2026-04-16 16:58:30', '2026-04-16 16:58:30');

-- Dumping structure for table egoessolution.violation_policies
CREATE TABLE IF NOT EXISTS `violation_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `violation_name` varchar(255) NOT NULL,
  `refresh_months` int NOT NULL DEFAULT '0',
  `offense_1` varchar(255) NOT NULL,
  `offense_1_type` varchar(50) DEFAULT 'warning',
  `offense_1_days` int DEFAULT '0',
  `offense_2` varchar(255) NOT NULL,
  `offense_2_type` varchar(50) DEFAULT 'warning',
  `offense_2_days` int DEFAULT '0',
  `offense_3` varchar(255) NOT NULL,
  `offense_3_type` varchar(50) DEFAULT 'warning',
  `offense_3_days` int DEFAULT '0',
  `offense_4` varchar(255) NOT NULL,
  `offense_4_type` varchar(50) DEFAULT 'warning',
  `offense_4_days` int DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.violation_policies: ~51 rows (approximately)
INSERT INTO `violation_policies` (`id`, `code`, `violation_name`, `refresh_months`, `offense_1`, `offense_1_type`, `offense_1_days`, `offense_2`, `offense_2_type`, `offense_2_days`, `offense_3`, `offense_3_type`, `offense_3_days`, `offense_4`, `offense_4_type`, `offense_4_days`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'A-01', 'Late login / late arrival', 3, 'Verbal warning', 'warning', 0, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension up to 3 days', 'suspension', 3, 1, '2026-04-15 19:43:27', '2026-04-16 19:09:17'),
	(22, 'A-02', 'Accumulated tardiness (3x in 1 month)', 1, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:12:14', '2026-04-16 16:45:10'),
	(23, 'A-03', 'Unexcused absence', 6, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:13:33', '2026-04-16 16:53:30'),
	(24, 'A-04', 'AWOL (Absent Without Official Leave)', 0, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:14:24', '2026-04-16 11:14:24'),
	(25, 'A-05', 'No-Call, No-Show', 6, 'Suspension', 'suspension', 3, 'Final warning', 'warning', 0, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:16:20', '2026-04-16 16:53:36'),
	(26, 'A-06', 'Excessive absences (below 95% attendance)', 1, 'Warning', 'warning', 0, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:17:15', '2026-04-16 16:45:19'),
	(27, 'UG-01', 'Not wearing prescribed uniform', 1, 'Verbal warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:18:05', '2026-04-16 16:46:07'),
	(28, 'UG-02', 'Wearing unauthorized attire(slippers, shorts, revealing clothing, etc.)', 0, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:18:49', '2026-04-16 11:18:49'),
	(29, 'UG-03', 'Poor grooming / unkempt appearance', 1, 'Verbal warning', 'warning', 0, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 1, '2026-04-16 11:20:18', '2026-04-16 16:51:01'),
	(30, 'MI-01', 'Unprofessional Behaviour', 3, 'Verbal warning', 'warning', 0, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 1, '2026-04-16 11:21:04', '2026-04-16 16:52:19'),
	(31, 'MI-02', 'Boisterous or disruptive behaviour', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:22:03', '2026-04-16 16:52:27'),
	(32, 'MI-03', 'Failure to follow instructions', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:22:32', '2026-04-16 16:52:35'),
	(33, 'MA-10', 'Disrespect toward colleagues, TL, OM, or clients', 6, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'warning', 3, '', 'warning', 0, 1, '2026-04-16 11:23:08', '2026-04-16 16:53:48'),
	(34, 'MA-11', 'Abusive language inside the workplace', 6, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:23:34', '2026-04-16 16:53:54'),
	(35, 'MA-12', 'Harassment, bullying, or discrimination', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:24:07', '2026-04-16 11:24:07'),
	(36, 'MA-13', 'Insubordination (refusal to follow lawful orders)', 0, 'Final Warning', 'warning', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:26:19', '2026-04-16 11:26:19'),
	(37, 'MA-14', 'Sleeping during shift', 6, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:26:49', '2026-04-16 16:54:01'),
	(38, 'UB-10', 'Browsing non-work-related sites while on active duty or while calls are in queue', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:27:32', '2026-04-16 16:52:45'),
	(39, 'UB-11', 'Streaming videos or accessing social media during work hours', 0, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:28:08', '2026-04-16 11:28:08'),
	(40, 'UB-12', 'Using mobile phone, messaging apps, or unauthorized communication tools', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:29:05', '2026-04-16 16:52:52'),
	(41, 'SC-01', 'Refusal to take a required supervisor call', 6, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:29:57', '2026-04-16 16:54:14'),
	(42, 'SC-02', 'Avoiding escalations by going on break/logging out', 6, 'Suspension', 'suspension', 3, 'Final warning', 'warning', 0, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:30:35', '2026-04-16 16:54:21'),
	(43, 'SC-03', 'Intentional refusal or defiance of a directive to take escalated calls', 6, 'Immediate suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:31:18', '2026-04-16 16:54:34'),
	(44, 'B-01', 'Overbreak', 1, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:31:57', '2026-04-16 16:51:36'),
	(45, 'B-02', 'Unauthorized break / loitering', 1, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:32:20', '2026-04-16 16:51:54'),
	(46, 'B-03', 'Early logout', 1, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:32:45', '2026-04-16 16:52:00'),
	(47, 'B-04', 'Low adherence', 1, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, 1, '2026-04-16 11:33:06', '2026-04-16 16:52:06'),
	(48, 'P-01', 'Failure to meet KPIs despite coaching', 3, 'Written warning', 'warning', 0, 'PIP', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:34:22', '2026-04-16 16:52:58'),
	(49, 'P-02', 'Repeated quality fails', 3, 'Final Warning', 'warning', 0, 'PIP', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:34:54', '2026-04-16 16:53:06'),
	(50, 'P-03', 'Intentional call avoidance / call drops', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:35:15', '2026-04-16 11:35:15'),
	(51, 'P-04', 'Mishandling customer data affecting service quality', 0, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:35:35', '2026-04-16 11:35:35'),
	(52, 'P-05', 'Failure to follow call flow,Coaching', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:35:56', '2026-04-16 16:53:13'),
	(53, 'DP-01', 'Sharing confidential info', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:36:29', '2026-04-16 11:36:29'),
	(54, 'DP-02', 'Mishandling customer data', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:36:49', '2026-04-16 11:36:49'),
	(55, 'DP-03', 'Accessing unauthorized systems or accounts', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:37:04', '2026-04-16 11:37:04'),
	(56, 'DP-04', 'Unauthorized screenshots/photos/recordings', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:37:21', '2026-04-16 11:37:21'),
	(57, 'DP-05', 'Allowing others to use your log-in credentials', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:37:44', '2026-04-16 11:37:44'),
	(58, 'DP-06', 'Breach of compliance (GDPR/HIPAA/etc.)', 0, 'Termination + legal action Code,Violation,Action', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:38:36', '2026-04-16 11:38:36'),
	(59, 'FT-01', 'Fraud, falsification, or tampering with records', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:38:55', '2026-04-16 11:38:55'),
	(60, 'FT-02', 'Theft of company property', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:39:19', '2026-04-16 11:39:19'),
	(61, 'FT-03', 'Manipulating productivity metrics', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:39:35', '2026-04-16 11:39:35'),
	(62, 'FT-04', 'Misuse of systems for personal gain', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:39:51', '2026-04-16 11:39:51'),
	(63, 'FT-05', 'Accepting bribes or unauthorized benefits', 0, 'Termination\nCode,Violation,Action', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:40:04', '2026-04-16 11:40:48'),
	(64, 'SA-01', 'Reporting to work under the influence', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:41:09', '2026-04-16 11:41:09'),
	(65, 'SA-02', 'Possession of alcohol or illegal drugs in workplace', 0, 'Termination', 'termination', 0, '', 'warning', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:41:53', '2026-04-16 11:41:53'),
	(66, 'SA-03', 'Smoking inside unauthorized areas', 6, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:42:15', '2026-04-16 16:54:58'),
	(67, 'SA-04', 'Acts endangering health or safety', 6, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:42:39', '2026-04-16 16:55:06'),
	(68, 'CP-01', 'Misuse of company equipment', 3, 'Written warning', 'warning', 0, 'Final warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'termination', 0, 1, '2026-04-16 11:43:14', '2026-04-16 16:53:19'),
	(69, 'CP-02', 'Unauthorized installation of software', 6, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, '', 'warning', 0, '', 'termination', 0, 1, '2026-04-16 11:43:42', '2026-04-16 16:54:41'),
	(70, 'CP-03', 'Neglect resulting in damage to equipment', 6, 'Final Warning', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:44:19', '2026-04-16 16:54:49'),
	(71, 'CP-04', 'Loss of company asset due to negligence', 0, 'Employee Liable', 'warning', 0, 'Suspension', 'suspension', 3, 'Termination', 'termination', 0, '', 'termination', 0, 1, '2026-04-16 11:44:52', '2026-04-16 11:44:52');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
