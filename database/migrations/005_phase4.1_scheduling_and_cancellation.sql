-- ============================================================================
-- Bella Vista Oasis Spa - Migration 005: Phase 4.1 Scheduling & Cancellation
-- Adds guest count, 24-hour cancellation and reschedule audit columns,
-- status ENUM expansion (IN_PROGRESS, REJECTED), and room_id column.
-- ============================================================================

USE `bellavista`;

-- 1. Update bookings status ENUM to support IN_PROGRESS and REJECTED
ALTER TABLE `bookings`
  MODIFY COLUMN `status` ENUM('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NO-SHOW','REJECTED') NOT NULL DEFAULT 'PENDING';

-- 2. Add guest count and audit columns to bookings
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `guests`              INT UNSIGNED NOT NULL DEFAULT 1 AFTER `duration`,
  ADD COLUMN IF NOT EXISTS `cancelled_at`        DATETIME     NULL DEFAULT NULL  AFTER `cancel_reason`,
  ADD COLUMN IF NOT EXISTS `cancelled_by`        VARCHAR(50)  NULL DEFAULT NULL  AFTER `cancelled_at`,
  ADD COLUMN IF NOT EXISTS `cancellation_reason` VARCHAR(300) NOT NULL DEFAULT '' AFTER `cancelled_by`,
  ADD COLUMN IF NOT EXISTS `original_date`       VARCHAR(10)  NULL DEFAULT NULL  AFTER `cancellation_reason`,
  ADD COLUMN IF NOT EXISTS `original_time`       VARCHAR(5)   NULL DEFAULT NULL  AFTER `original_date`,
  ADD COLUMN IF NOT EXISTS `rescheduled_at`      DATETIME     NULL DEFAULT NULL  AFTER `original_time`,
  ADD COLUMN IF NOT EXISTS `rescheduled_by`      VARCHAR(50)  NULL DEFAULT NULL  AFTER `rescheduled_at`,
  ADD COLUMN IF NOT EXISTS `room_id`             VARCHAR(20)  NULL DEFAULT NULL  AFTER `rescheduled_by`;

-- 3. Add audit columns to reschedule_requests
ALTER TABLE `reschedule_requests`
  ADD COLUMN IF NOT EXISTS `original_date` VARCHAR(10)  NULL DEFAULT NULL AFTER `new_time`,
  ADD COLUMN IF NOT EXISTS `original_time` VARCHAR(5)   NULL DEFAULT NULL AFTER `original_date`,
  ADD COLUMN IF NOT EXISTS `decided_by`    VARCHAR(100) NULL DEFAULT NULL AFTER `admin_note`;

-- 4. Sync legacy cancel_reason into cancellation_reason
UPDATE `bookings`
  SET `cancellation_reason` = `cancel_reason`
  WHERE (`cancellation_reason` = '' OR `cancellation_reason` IS NULL)
    AND `cancel_reason` IS NOT NULL
    AND `cancel_reason` <> '';

-- 5. Add performance indexes
CREATE INDEX IF NOT EXISTS `idx_bookings_date_status` ON `bookings` (`preferred_date`, `status`);
CREATE INDEX IF NOT EXISTS `idx_bookings_user_status` ON `bookings` (`user_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_bookings_user_date`   ON `bookings` (`user_id`, `preferred_date`);
