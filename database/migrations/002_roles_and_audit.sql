-- ============================================================================
-- Bella Vista Oasis Spa - Migration 002: User Roles, Admin Logs & Role Triggers
-- Adds role and active flag to users, archive flags to bookings,
-- creates admin_log table, and establishes trg_users_role_log trigger.
-- ============================================================================

USE `bellavista`;

-- 1. Add roles and active status to users
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role` ENUM('CUSTOMER','ADMIN') NOT NULL DEFAULT 'CUSTOMER' AFTER `email_verified`,
  ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
  ADD KEY IF NOT EXISTS `idx_users_role` (`role`);

-- 2. Add archive and visibility flags to bookings
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cancel_reason`,
  ADD COLUMN IF NOT EXISTS `hidden_for_customer` TINYINT(1) NOT NULL DEFAULT 0 AFTER `archived`;

-- 3. Payment history safety constraint
ALTER TABLE `transactions` DROP FOREIGN KEY IF EXISTS `fk_txn_booking`;
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_txn_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT;

-- 4. Create admin_log table
CREATE TABLE IF NOT EXISTS `admin_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED NULL,
  `admin_name` VARCHAR(100) NOT NULL DEFAULT '',
  `action`     VARCHAR(60)  NOT NULL,
  `target`     VARCHAR(60)  NOT NULL DEFAULT '',
  `detail`     VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adminlog_admin` (`admin_id`),
  KEY `idx_adminlog_time` (`created_at`),
  CONSTRAINT `fk_adminlog_user` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Audit trigger for role changes
DROP TRIGGER IF EXISTS `trg_users_role_log`;
CREATE TRIGGER `trg_users_role_log` AFTER UPDATE ON `users` FOR EACH ROW
  INSERT INTO `admin_log` (`admin_id`, `admin_name`, `action`, `target`, `detail`, `created_at`)
  SELECT NULL, 'Database', 'Role changed', NEW.`email`, CONCAT(OLD.`role`, ' -> ', NEW.`role`), UTC_TIMESTAMP()
  FROM DUAL WHERE OLD.`role` <> NEW.`role`;
