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

-- Dumping structure for table egoessolution.attendance_logs
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `office_id` int NOT NULL,
  `log_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` enum('present','absent','late','on_leave') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'present',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table egoessolution.deductions
CREATE TABLE IF NOT EXISTS `deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_item_id` int NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `payroll_item_id` (`payroll_item_id`),
  CONSTRAINT `deductions_ibfk_1` FOREIGN KEY (`payroll_item_id`) REFERENCES `payroll_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table egoessolution.employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rate_type` enum('hourly','monthly') COLLATE utf8mb4_general_ci NOT NULL,
  `rate_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `date_hired` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table egoessolution.offices
CREATE TABLE IF NOT EXISTS `offices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `team_leader` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `team_leader_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_offices_team_leader_user` (`team_leader_user_id`),
  CONSTRAINT `fk_offices_team_leader_user` FOREIGN KEY (`team_leader_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

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

-- Data exporting was unselected.

-- Dumping structure for table egoessolution.payroll_periods
CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `office_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','processing','closed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `payroll_periods_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table egoessolution.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `office_id` int DEFAULT NULL,
  `role` enum('superadmin','admin','employee') COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
