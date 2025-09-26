-- Database Cleanup for Nomenclature Changes
-- This script addresses the critical database inconsistencies identified during
-- the transition from request-based to quotation-based nomenclature

-- Select the database
USE `u391156245_om_engineers`;

-- ============================================================================
-- PHASE 1: Create Modern Sequence Tables
-- ============================================================================

-- Create direct_quotation_sequence for D-YEAR numbering
-- Replaces the obsolete standalone_request_sequence table
CREATE TABLE IF NOT EXISTS `direct_quotation_sequence` (
  `year` int(4) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sequence tracking for direct quotations (D-YEAR format)';

-- ============================================================================
-- PHASE 2: Schema Cleanup
-- ============================================================================

-- Remove obsolete prefix column from quotation_sequence (no longer needed)
-- The new system uses organization prefixes dynamically
ALTER TABLE `quotation_sequence` DROP COLUMN IF EXISTS `prefix`;

-- ============================================================================
-- PHASE 3: Data Migration (if needed)
-- ============================================================================

-- Migrate existing sequence data from standalone_request_sequence to direct_quotation_sequence
INSERT IGNORE INTO `direct_quotation_sequence` (`year`, `last_number`)
SELECT `year`, `last_number`
FROM `standalone_request_sequence`
WHERE `standalone_request_sequence`.`year` IS NOT NULL;

-- ============================================================================
-- PHASE 4: Verification Queries
-- ============================================================================

-- Verify tables exist
SELECT 'direct_quotation_sequence' as table_name, COUNT(*) as record_count
FROM `direct_quotation_sequence`
UNION ALL
SELECT 'quotation_sequence' as table_name, COUNT(*) as record_count
FROM `quotation_sequence`;

-- ============================================================================
-- CLEANUP NOTES
-- ============================================================================
-- After successful migration and testing:
-- 1. DROP TABLE `standalone_request_sequence` (after verification)
-- 2. DROP TABLE `quotation_items` (after quotation_items_new is fully operational)
-- 3. DROP TABLE `standalone_customers` (data now stored directly in quotations_new)
--
-- These DROP statements are commented out for safety and should be executed
-- manually after thorough testing and data verification.
--
-- -- DROP TABLE IF EXISTS `standalone_request_sequence`;
-- -- DROP TABLE IF EXISTS `quotation_items`;
-- -- DROP TABLE IF EXISTS `standalone_customers`;