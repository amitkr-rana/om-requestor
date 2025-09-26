-- Om Engineers - New Quotation-Centric Database Schema
-- Complete redesign for quotation workflow with global inventory, tracking, and reporting
-- Created: 2024

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Use existing database
USE `om_requestor`;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Organizations table (enhanced)
CREATE TABLE IF NOT EXISTS `organizations_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `settings` text DEFAULT NULL COMMENT 'JSON settings for org preferences',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users table (enhanced with activity tracking)
CREATE TABLE IF NOT EXISTS `users_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','requestor','approver','technician') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `preferences` text DEFAULT NULL COMMENT 'JSON user preferences',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_organization` (`organization_id`),
  KEY `idx_role` (`role`),
  KEY `idx_email` (`email`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_users_organization_new` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- QUOTATION SYSTEM (NEW CENTRAL HUB)
-- ============================================================================

-- Main quotations table - now the central hub
CREATE TABLE IF NOT EXISTS `quotations_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL UNIQUE,
  `organization_id` int(11) NOT NULL,

  -- Customer Information (direct, no more vehicle dependency)
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `vehicle_registration` varchar(20) NOT NULL,
  `vehicle_make_model` varchar(100) DEFAULT NULL,

  -- Service Information
  `problem_description` text NOT NULL,
  `work_description` text DEFAULT NULL,
  `service_notes` text DEFAULT NULL,

  -- Financial Information
  `base_service_charge` decimal(10,2) DEFAULT 0.00,
  `parts_total` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 18.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'INR',

  -- Status and Workflow
  `status` enum('pending','sent','approved','rejected','repair_in_progress','repair_complete','bill_generated','paid','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',

  -- Assignment and Work Tracking
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Assigned technician',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,

  -- Timestamps for complete tracking
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `repair_started_at` timestamp NULL DEFAULT NULL,
  `repair_completed_at` timestamp NULL DEFAULT NULL,
  `bill_generated_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Estimates and Deadlines
  `estimated_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,

  -- Additional metadata
  `approval_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quotation_number` (`quotation_number`),
  KEY `idx_organization` (`organization_id`),
  KEY `idx_status` (`status`),
  KEY `idx_customer_email` (`customer_email`),
  KEY `idx_vehicle_registration` (`vehicle_registration`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_approved_by` (`approved_by`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_priority` (`priority`),

  CONSTRAINT `fk_quotations_organization_new` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quotations_assigned_to_new` FOREIGN KEY (`assigned_to`) REFERENCES `users_new` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotations_approved_by_new` FOREIGN KEY (`approved_by`) REFERENCES `users_new` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotations_created_by_new` FOREIGN KEY (`created_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quotation items/line items
CREATE TABLE IF NOT EXISTS `quotation_items_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `inventory_item_id` int(11) DEFAULT NULL,
  `item_type` enum('service','part','material','misc') NOT NULL DEFAULT 'part',
  `description` varchar(255) NOT NULL,
  `hsn_code` varchar(20) DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(20) DEFAULT 'nos',
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 18.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_quotation_items_quotation_new` (`quotation_id`),
  KEY `fk_quotation_items_inventory_new` (`inventory_item_id`),
  KEY `idx_item_type` (`item_type`),
  CONSTRAINT `fk_quotation_items_quotation_new` FOREIGN KEY (`quotation_id`) REFERENCES `quotations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- COMPREHENSIVE TRACKING SYSTEM
-- ============================================================================

-- Quotation history - tracks all changes
CREATE TABLE IF NOT EXISTS `quotation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `action` enum('created','updated','status_changed','approved','rejected','assigned','completed','billed','paid','cancelled') NOT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_quotation_history_quotation` (`quotation_id`),
  KEY `fk_quotation_history_user` (`changed_by`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_quotation_history_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quotation_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status change log with detailed tracking
CREATE TABLE IF NOT EXISTS `quotation_status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `duration_in_status` int(11) DEFAULT NULL COMMENT 'Minutes spent in previous status',
  `notification_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_status_log_quotation` (`quotation_id`),
  KEY `fk_status_log_user` (`changed_by`),
  KEY `idx_to_status` (`to_status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_status_log_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_status_log_user` FOREIGN KEY (`changed_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User activity log - system-wide activity tracking
CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'quotation, inventory, user, etc',
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'JSON metadata',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_activity_user` (`user_id`),
  KEY `fk_activity_organization` (`organization_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- GLOBAL INVENTORY SYSTEM
-- ============================================================================

-- Inventory categories
CREATE TABLE IF NOT EXISTS `inventory_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_category_parent` (`parent_category_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global inventory items
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `item_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `hsn_code` varchar(20) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'nos',
  `current_stock` decimal(10,3) DEFAULT 0.000,
  `allocated_stock` decimal(10,3) DEFAULT 0.000,
  `available_stock` decimal(10,3) GENERATED ALWAYS AS (`current_stock` - `allocated_stock`) STORED,
  `minimum_stock` decimal(10,3) DEFAULT 0.000,
  `maximum_stock` decimal(10,3) DEFAULT 0.000,
  `reorder_level` decimal(10,3) DEFAULT 0.000,
  `average_cost` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_part_number` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_code_org` (`item_code`, `organization_id`),
  KEY `fk_inventory_organization` (`organization_id`),
  KEY `fk_inventory_category` (`category_id`),
  KEY `fk_inventory_supplier` (`supplier_id`),
  KEY `idx_name` (`name`),
  KEY `idx_current_stock` (`current_stock`),
  KEY `idx_available_stock` (`available_stock`),
  KEY `idx_reorder_level` (`reorder_level`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_inventory_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_suppliers_organization` (`organization_id`),
  KEY `idx_name` (`name`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_suppliers_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key for inventory supplier
ALTER TABLE `inventory` ADD CONSTRAINT `fk_inventory_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
ALTER TABLE `quotation_items_new` ADD CONSTRAINT `fk_quotation_items_inventory_new` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL;

-- Inventory transactions - all movements
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','transfer','allocation','deallocation','return','damage','consumption') NOT NULL,
  `reference_type` enum('quotation','purchase_order','adjustment','transfer','manual') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(12,2) DEFAULT NULL,
  `running_balance` decimal(10,3) NOT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inv_trans_organization` (`organization_id`),
  KEY `fk_inv_trans_inventory` (`inventory_item_id`),
  KEY `fk_inv_trans_user` (`performed_by`),
  KEY `fk_inv_trans_supplier` (`supplier_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_reference` (`reference_type`, `reference_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_inv_trans_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_trans_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_trans_user` FOREIGN KEY (`performed_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inv_trans_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory allocations - track what's allocated to quotations
CREATE TABLE IF NOT EXISTS `inventory_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `allocated_quantity` decimal(10,3) NOT NULL,
  `consumed_quantity` decimal(10,3) DEFAULT 0.000,
  `remaining_quantity` decimal(10,3) GENERATED ALWAYS AS (`allocated_quantity` - `consumed_quantity`) STORED,
  `allocated_by` int(11) NOT NULL,
  `allocation_notes` text DEFAULT NULL,
  `allocated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `consumed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quotation_inventory` (`quotation_id`, `inventory_item_id`),
  KEY `fk_alloc_quotation` (`quotation_id`),
  KEY `fk_alloc_inventory` (`inventory_item_id`),
  KEY `fk_alloc_user` (`allocated_by`),
  CONSTRAINT `fk_alloc_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_user` FOREIGN KEY (`allocated_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ENHANCED BILLING SYSTEM
-- ============================================================================

-- Main billing records
CREATE TABLE IF NOT EXISTS `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL UNIQUE,
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `payment_status` enum('unpaid','partial','paid','overdue') DEFAULT 'unpaid',
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `generated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bill_number` (`bill_number`),
  KEY `fk_billing_quotation` (`quotation_id`),
  KEY `fk_billing_user` (`generated_by`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_bill_date` (`bill_date`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `fk_billing_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_billing_user` FOREIGN KEY (`generated_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment transactions
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL UNIQUE,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','cheque','bank_transfer','credit_card','upi','wallet','other') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_transaction_number` (`transaction_number`),
  KEY `fk_payment_billing` (`billing_id`),
  KEY `fk_payment_user` (`received_by`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_method` (`payment_method`),
  CONSTRAINT `fk_payment_billing` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`received_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- REPORTING AND ANALYTICS SYSTEM
-- ============================================================================

-- Report definitions for custom reports
CREATE TABLE IF NOT EXISTS `report_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `report_type` enum('quotation','inventory','financial','performance','custom') NOT NULL,
  `sql_query` text DEFAULT NULL,
  `parameters` text DEFAULT NULL COMMENT 'JSON parameters definition',
  `chart_config` text DEFAULT NULL COMMENT 'JSON chart configuration',
  `access_roles` varchar(255) DEFAULT 'admin' COMMENT 'Comma-separated roles',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reports_organization` (`organization_id`),
  KEY `fk_reports_user` (`created_by`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_reports_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`created_by`) REFERENCES `users_new` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report cache for performance
CREATE TABLE IF NOT EXISTS `report_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_definition_id` int(11) NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `parameters_hash` varchar(64) NOT NULL,
  `data` longtext NOT NULL COMMENT 'JSON cached data',
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `hit_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cache_key` (`cache_key`),
  KEY `fk_cache_report` (`report_definition_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_cache_report` FOREIGN KEY (`report_definition_id`) REFERENCES `report_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard widgets
CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `widget_type` varchar(50) NOT NULL COMMENT 'chart, table, metric, etc',
  `title` varchar(100) NOT NULL,
  `configuration` text NOT NULL COMMENT 'JSON widget configuration',
  `position_x` int(11) DEFAULT 0,
  `position_y` int(11) DEFAULT 0,
  `width` int(11) DEFAULT 4,
  `height` int(11) DEFAULT 3,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_widgets_user` (`user_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_widgets_user` FOREIGN KEY (`user_id`) REFERENCES `users_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SEQUENCE TABLES FOR NUMBER GENERATION
-- ============================================================================

-- Quotation number sequence
CREATE TABLE IF NOT EXISTS `quotation_sequence` (
  `organization_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `prefix` varchar(10) DEFAULT 'QT',
  PRIMARY KEY (`organization_id`, `year`),
  CONSTRAINT `fk_quot_seq_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bill number sequence
CREATE TABLE IF NOT EXISTS `bill_sequence` (
  `organization_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `prefix` varchar(10) DEFAULT 'INV',
  PRIMARY KEY (`organization_id`, `year`),
  CONSTRAINT `fk_bill_seq_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TRIGGERS FOR AUTOMATION
-- ============================================================================

-- Auto-update inventory allocated stock
DELIMITER $$
CREATE TRIGGER `update_inventory_allocated_stock_after_allocation`
AFTER INSERT ON `inventory_allocations`
FOR EACH ROW
BEGIN
    UPDATE `inventory`
    SET `allocated_stock` = (
        SELECT COALESCE(SUM(`allocated_quantity` - `consumed_quantity`), 0)
        FROM `inventory_allocations`
        WHERE `inventory_item_id` = NEW.`inventory_item_id`
    )
    WHERE `id` = NEW.`inventory_item_id`;
END$$

CREATE TRIGGER `update_inventory_allocated_stock_after_update`
AFTER UPDATE ON `inventory_allocations`
FOR EACH ROW
BEGIN
    UPDATE `inventory`
    SET `allocated_stock` = (
        SELECT COALESCE(SUM(`allocated_quantity` - `consumed_quantity`), 0)
        FROM `inventory_allocations`
        WHERE `inventory_item_id` = NEW.`inventory_item_id`
    )
    WHERE `id` = NEW.`inventory_item_id`;
END$$

CREATE TRIGGER `update_inventory_allocated_stock_after_delete`
AFTER DELETE ON `inventory_allocations`
FOR EACH ROW
BEGIN
    UPDATE `inventory`
    SET `allocated_stock` = (
        SELECT COALESCE(SUM(`allocated_quantity` - `consumed_quantity`), 0)
        FROM `inventory_allocations`
        WHERE `inventory_item_id` = OLD.`inventory_item_id`
    )
    WHERE `id` = OLD.`inventory_item_id`;
END$$
DELIMITER ;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Additional performance indexes
CREATE INDEX `idx_quotations_date_range` ON `quotations_new` (`created_at`, `organization_id`, `status`);
CREATE INDEX `idx_quotations_customer_search` ON `quotations_new` (`customer_name`, `customer_email`, `vehicle_registration`);
CREATE INDEX `idx_inventory_low_stock` ON `inventory` (`organization_id`, `available_stock`, `reorder_level`);
CREATE INDEX `idx_billing_overdue` ON `billing` (`due_date`, `payment_status`);

COMMIT;