-- Vehicle Maintenance Quotation System Database Schema
-- Created for om-engineers by Claude Code

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: om_requestor
CREATE DATABASE IF NOT EXISTS `om_requestor` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `om_requestor`;

-- Organizations table (for future multi-org support)
CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','requestor','approver') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_users_organization` (`organization_id`),
  CONSTRAINT `fk_users_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vehicles table
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `registration_number` varchar(20) NOT NULL UNIQUE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vehicles_user` (`user_id`),
  CONSTRAINT `fk_vehicles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Service requests table
CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `problem_description` text NOT NULL,
  `status` enum('pending','quoted','approved','completed','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_requests_vehicle` (`vehicle_id`),
  KEY `fk_requests_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_requests_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quotations table
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `work_description` text NOT NULL,
  `status` enum('sent','approved','rejected') NOT NULL DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_quotations_request` (`request_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_quotations_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Approvals table
CREATE TABLE `approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_approvals_quotation` (`quotation_id`),
  KEY `fk_approvals_approver` (`approver_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_approvals_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_approvals_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work orders and billing table
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `work_completed_at` timestamp NULL DEFAULT NULL,
  `bill_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_workorders_quotation` (`quotation_id`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_workorders_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default organization
INSERT INTO `organizations` (`name`) VALUES ('Sammaan Foundation');

-- Insert default admin user
-- Password: admin123 (hashed with password_hash())
INSERT INTO `users` (`organization_id`, `username`, `email`, `password`, `role`, `full_name`)
VALUES (1, 'admin', 'admin@om-engineers.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator');

COMMIT;