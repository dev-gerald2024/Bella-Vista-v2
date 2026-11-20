-- ============================================================================
-- Bella Vista Oasis Spa - Migration 007: Phase 6.0 Demo Payments & Refunds
-- Adds payment settings table, enhances transactions with demo payment fields,
-- receipt numbers, and refund tracking, and adds payment tracking to bookings.
-- ============================================================================

USE `bellavista`;

-- 1. Create payment_settings table for configurable downpayment & simulation rules
CREATE TABLE IF NOT EXISTS `payment_settings` (
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description`   VARCHAR(255) NULL,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_settings` (`setting_key`, `setting_value`, `description`) VALUES
('DOWNPAYMENT_PERCENTAGE', '25', 'Default demo downpayment percentage (25%, management configurable)'),
('MIN_DOWNPAYMENT_AMOUNT', '500.00', 'Minimum required demo downpayment in PHP'),
('ALLOW_FULL_PAYMENT', '1', 'Allow customers to simulate paying the full remaining balance'),
('DEMO_MODE_ACTIVE', '1', 'Simulated demo mode enabled: NO REAL MONEY TRANSFERRED'),
('FORFEIT_LATE_CANCELLATION', '1', 'Late cancellations (< 24h) forfeit simulated downpayment')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

-- 2. Enhance transactions table with complete Phase 6 demo payment fields
ALTER TABLE `transactions` MODIFY COLUMN `status` 
  ENUM('UNPAID','PENDING','PROCESSING','PAID','PARTIALLY_PAID','FAILED','CANCELLED','REFUNDED','PARTIALLY_REFUNDED','FORFEITED') 
  NOT NULL DEFAULT 'PENDING';

ALTER TABLE `transactions`
  ADD COLUMN IF NOT EXISTS `transaction_reference` VARCHAR(40)  NULL UNIQUE AFTER `id`,
  ADD COLUMN IF NOT EXISTS `payment_method`        ENUM('GCASH_DEMO','MAYA_DEMO','CASH_AT_SPA','BANK_TRANSFER_DEMO') NOT NULL DEFAULT 'CASH_AT_SPA' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `transaction_type`      ENUM('DOWNPAYMENT','BALANCE_PAYMENT','FULL_PAYMENT','REFUND','FORFEITURE','ADJUSTMENT') NOT NULL DEFAULT 'DOWNPAYMENT' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `receipt_number`         VARCHAR(40)  NULL UNIQUE AFTER `transaction_type`,
  ADD COLUMN IF NOT EXISTS `demo_mode`              TINYINT(1)   NOT NULL DEFAULT 1 AFTER `receipt_number`,
  ADD COLUMN IF NOT EXISTS `notes`                  VARCHAR(500) NULL AFTER `demo_mode`,
  ADD COLUMN IF NOT EXISTS `refund_reason`          VARCHAR(500) NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `processed_at`           DATETIME     NULL AFTER `refund_reason`,
  ADD COLUMN IF NOT EXISTS `updated_at`             DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- 3. Enhance bookings table with payment status & downpayment tracking columns
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `payment_status`        ENUM('UNPAID','PARTIALLY_PAID','PAID','REFUNDED','FORFEITED') NOT NULL DEFAULT 'UNPAID' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `paid_amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `downpayment_required`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `paid_amount`;
