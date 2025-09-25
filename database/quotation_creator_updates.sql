  -- Quotation Creator Database Updates
  -- Updates to support detailed quotation creation with line items and tax calculations

  -- Create quotation_items table for line items
  CREATE TABLE IF NOT EXISTS `quotation_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quotation_id` int(11) NOT NULL,
    `item_type` enum('base_service','parts','misc') NOT NULL,
    `description` varchar(255) NOT NULL,
    `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
    `rate` decimal(10,2) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_quotation_items_quotation` (`quotation_id`),
    CONSTRAINT `fk_quotation_items_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  -- Add new columns to quotations table
  ALTER TABLE `quotations`
  ADD COLUMN `quotation_number` varchar(50) UNIQUE AFTER `id`,
  ADD COLUMN `base_service_charge` decimal(10,2) DEFAULT 0.00 AFTER `work_description`,
  ADD COLUMN `subtotal` decimal(10,2) DEFAULT 0.00 AFTER `base_service_charge`,
  ADD COLUMN `sgst_rate` decimal(5,2) DEFAULT 9.00 AFTER `subtotal`,
  ADD COLUMN `cgst_rate` decimal(5,2) DEFAULT 9.00 AFTER `sgst_rate`,
  ADD COLUMN `sgst_amount` decimal(10,2) DEFAULT 0.00 AFTER `cgst_rate`,
  ADD COLUMN `cgst_amount` decimal(10,2) DEFAULT 0.00 AFTER `sgst_amount`,
  ADD COLUMN `total_amount` decimal(10,2) DEFAULT 0.00 AFTER `cgst_amount`;

  -- Create index for quotation_number
  CREATE INDEX `idx_quotation_number` ON `quotations` (`quotation_number`);