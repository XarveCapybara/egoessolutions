-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
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
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.app_settings: ~2 rows (approximately)
INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`) VALUES
	(1, 'deduction_per_minute', '3.00'),
	(2, 'hourly_rate_default', '30.00');

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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.attendance_logs: ~0 rows (approximately)

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.employees: ~5 rows (approximately)
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `position`, `rate_type`, `rate_amount`, `date_hired`) VALUES
	(3, 7, 'EMP000007', NULL, 'hourly', 0.00, NULL),
	(4, 9, 'EMP000009', NULL, 'hourly', 50.00, NULL),
	(5, 10, 'EMP000010', NULL, 'hourly', 30.00, NULL),
	(6, 11, 'EMP000011', NULL, 'hourly', 60.00, NULL),
	(7, 12, 'EMP000012', NULL, 'hourly', 70.00, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.offices: ~3 rows (approximately)
INSERT INTO `offices` (`id`, `name`, `address`, `working_start_time`, `working_end_time`, `team_leader`, `is_active`, `team_leader_user_id`, `time_in`, `time_out`) VALUES
	(1, 'office1', 'Tirad Pass', '21:00:00', '05:00:00', 'starktony', 1, 12, '20:00:00', '05:00:00'),
	(3, 'office2', 'Terminal', '08:00:00', '17:00:00', 'jobert', 1, 8, NULL, NULL),
	(4, 'officetest', 'tirad pass', NULL, NULL, 'Robert', 1, 6, '20:00:00', '05:00:00');

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table egoessolution.users: ~8 rows (approximately)
INSERT INTO `users` (`id`, `office_id`, `role`, `full_name`, `email`, `password_hash`, `profile_image`, `is_active`, `created_at`) VALUES
	(1, 1, 'superadmin', 'super admin', 'superadmin@egoes.com', '$2y$10$gd2ix9tBZaYxk5gYgNcz1O7.mM4FiZmQe0tuBwXGMbLCN8BPqaYr2', NULL, 1, '2026-03-24 14:17:52'),
	(6, 4, 'admin', 'Robert', 'robert@egoes.com', '$2y$10$6uhsYl7klCi/6iJUiTSg6efclQVDYwzwDmLXOMeCA4kGZ/f4JPkte', NULL, 1, '2026-03-24 14:45:37'),
	(7, 1, 'employee', 'Stripesman', 'stripe@egoes.com', '$2y$10$0ny.CEua18xyyxxWLxj4/.9MKgbyJQXaP03L4LvPlr5zo6eb4LKxO', NULL, 1, '2026-03-24 14:46:32'),
	(8, 3, 'admin', 'jobert', 'jobert@egoes.com', '$2y$10$dj6PFGrEAlWUyt2nV.T41.dNpWEKAi69X022MoyaSXyNqpyrNO9om', NULL, 1, '2026-03-24 14:48:36'),
	(9, NULL, 'employee', 'dodong', 'dodong@egoes.com', '$2y$10$bJ7o.r4cULfAGhLInpxRzeBwlC7h0U13NhiPqzCN3oU8M42xlZLM.', NULL, 1, '2026-03-24 15:18:22'),
	(10, 1, 'employee', 'jupeta', 'jupeta@egoes.com', '$2y$10$m/wOWoa0RI5xH.8xOVGTnOLzEtWbeb/oUTch0axKTH5tUfjfMioha', NULL, 1, '2026-03-24 16:20:09'),
	(11, 1, 'employee', 'rainier', 'rainier@egoes.com', '$2y$10$.Y2V3gL0mOlRQcbECQDFTO8ab1OsK8E0ocUtLsd1l2kTXv9LUAwbq', NULL, 1, '2026-03-24 16:24:24'),
	(12, 1, 'admin', 'starktony', 'stark@egoes.com', '$2y$10$x01.WRnHxOnjWych/6h4PebPB7fE/a0Sil9jZW0efObp.FHww8UUy', NULL, 1, '2026-03-24 16:58:37');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table egoessolution.user_profiles: ~1 rows (approximately)
INSERT INTO `user_profiles` (`id`, `user_id`, `nickname`, `first_name`, `last_name`, `avatar`, `date_of_birth`, `gender`, `address`, `phone`, `email`, `created_at`, `updated_at`) VALUES
	(1, 12, 'starky', 'starktony', NULL, NULL, '1999-01-28', 'Male', NULL, NULL, 'stark@egoes.com', '2026-03-27 10:54:09', '2026-03-27 10:57:12');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
