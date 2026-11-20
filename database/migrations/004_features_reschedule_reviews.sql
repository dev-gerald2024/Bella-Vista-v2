-- ============================================================================
-- Bella Vista Oasis Spa - Migration 004: Customer Reschedules & Reviews
-- Creates reschedule_requests and reviews tables.
-- ============================================================================

USE `bellavista`;

-- Reschedule requests: customer asks to move booking; admin approves or declines
CREATE TABLE IF NOT EXISTS `reschedule_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `new_date`    VARCHAR(10)  NOT NULL,                    -- YYYY-MM-DD
  `new_time`    VARCHAR(5)   NOT NULL,                    -- HH:MM
  `reason`      VARCHAR(300) NOT NULL DEFAULT '',
  `status`      ENUM('PENDING','APPROVED','DECLINED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
  `admin_note`  VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at`  DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resched_booking` (`booking_id`, `status`),
  KEY `idx_resched_user` (`user_id`),
  CONSTRAINT `fk_resched_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resched_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews: one per completed booking, written by booking owner
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `rating`     TINYINT UNSIGNED NOT NULL,                 -- 1 to 5
  `comment`    VARCHAR(1000) NOT NULL DEFAULT '',
  `hidden`     TINYINT(1)   NOT NULL DEFAULT 0,           -- 1 = hidden by admin
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_booking` (`booking_id`),
  KEY `idx_review_user` (`user_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_review_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
