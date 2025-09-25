-- Standalone Quotations Database Updates
-- Support for walk-in customers and direct quotation creation

-- Create standalone_customers table for walk-in customers
CREATE TABLE IF NOT EXISTS `standalone_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(50) NOT NULL UNIQUE,
  `vehicle_registration` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100),
  `problem_description` text NOT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `fk_standalone_customers_org` (`organization_id`),
  CONSTRAINT `fk_standalone_customers_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns to quotations table to support standalone quotations
ALTER TABLE `quotations`
ADD COLUMN `is_standalone` tinyint(1) DEFAULT 0 AFTER `request_id`,
ADD COLUMN `standalone_customer_id` int(11) NULL AFTER `is_standalone`;

-- Add index and foreign key for standalone customer reference
ALTER TABLE `quotations`
ADD KEY `fk_quotations_standalone` (`standalone_customer_id`),
ADD CONSTRAINT `fk_quotations_standalone` FOREIGN KEY (`standalone_customer_id`) REFERENCES `standalone_customers` (`id`) ON DELETE SET NULL;

-- Modify the existing foreign key constraint to be optional for standalone quotations
-- Note: We'll handle this in the application logic rather than changing the constraint
-- to maintain backward compatibility

-- Create a function to generate standalone request IDs
-- This will be handled in PHP, but we can create a table to track the sequence
CREATE TABLE IF NOT EXISTS `standalone_request_sequence` (
  `year` int(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;