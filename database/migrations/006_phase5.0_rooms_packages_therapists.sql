-- ============================================================================
-- Bella Vista Oasis Spa - Migration 006: Phase 5.0 Rooms, Packages & Therapists
-- Adds room categories, physical rooms, packages, therapists, therapist shifts,
-- breaks, leaves, room maintenance, room cleaning, and appointment assignments.
-- ============================================================================

USE `bellavista`;

CREATE TABLE IF NOT EXISTS `room_categories` (
  `code`              VARCHAR(20)   NOT NULL,
  `name`              VARCHAR(100)  NOT NULL,
  `min_guests`        INT UNSIGNED  NOT NULL DEFAULT 1,
  `standard_capacity` INT UNSIGNED  NOT NULL DEFAULT 1,
  `max_capacity`      INT UNSIGNED  NOT NULL DEFAULT 1,
  `default_duration`  INT UNSIGNED  NOT NULL DEFAULT 60,
  `documented_rate`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `description`       VARCHAR(500)  NOT NULL DEFAULT '',
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `room_code`         VARCHAR(20)   NOT NULL,
  `room_name`         VARCHAR(100)  NOT NULL,
  `category_code`     VARCHAR(20)   NOT NULL,
  `min_guests`        INT UNSIGNED  NOT NULL DEFAULT 1,
  `standard_capacity` INT UNSIGNED  NOT NULL DEFAULT 1,
  `max_capacity`      INT UNSIGNED  NOT NULL DEFAULT 1,
  `room_status`       ENUM('AVAILABLE','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
  `notes`             VARCHAR(500)  NOT NULL DEFAULT '',
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`room_code`),
  KEY `idx_rooms_category` (`category_code`),
  KEY `idx_rooms_status` (`room_status`),
  CONSTRAINT `fk_rooms_category` FOREIGN KEY (`category_code`) REFERENCES `room_categories`(`code`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `packages` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `package_code`           VARCHAR(30)   NOT NULL,
  `name`                   VARCHAR(150)  NOT NULL,
  `category_code`          VARCHAR(20)   NOT NULL,
  `min_guests`             INT UNSIGNED  NOT NULL DEFAULT 1,
  `standard_capacity`      INT UNSIGNED  NOT NULL DEFAULT 1,
  `max_capacity`           INT UNSIGNED  NOT NULL DEFAULT 1,
  `default_duration`       INT UNSIGNED  NOT NULL DEFAULT 60,
  `base_price`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `additional_guest_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `package_status`         ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `description`            VARCHAR(500)  NOT NULL DEFAULT '',
  `management_notes`       VARCHAR(500)  NOT NULL DEFAULT '',
  `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_code` (`package_code`),
  KEY `idx_packages_category` (`category_code`),
  KEY `idx_packages_status` (`package_status`),
  CONSTRAINT `fk_packages_category` FOREIGN KEY (`category_code`) REFERENCES `room_categories`(`code`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `therapists` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_code`       VARCHAR(30)  NOT NULL,
  `full_name`           VARCHAR(100) NOT NULL,
  `contact_number`      VARCHAR(30)  NOT NULL DEFAULT '',
  `email`               VARCHAR(254) NOT NULL DEFAULT '',
  `specialization`      VARCHAR(300) NOT NULL DEFAULT 'ALL',
  `employment_status`   ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `availability_status` ENUM('AVAILABLE','ON_BREAK','OFF_DUTY','ON_LEAVE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
  `notes`               VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_therapist_code` (`employee_code`),
  KEY `idx_therapist_status` (`employment_status`, `availability_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `therapist_schedules` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `therapist_id`   INT UNSIGNED NOT NULL,
  `day_of_week`    TINYINT UNSIGNED NOT NULL,
  `shift_start`    VARCHAR(5)   NOT NULL DEFAULT '13:00',
  `shift_end`      VARCHAR(5)   NOT NULL DEFAULT '21:00',
  `is_working_day` TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_therapist_day` (`therapist_id`, `day_of_week`),
  KEY `idx_schedule_day` (`day_of_week`),
  CONSTRAINT `fk_sched_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `therapist_breaks` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `therapist_id` INT UNSIGNED NOT NULL,
  `break_date`   VARCHAR(10)  NOT NULL,
  `start_time`   VARCHAR(5)   NOT NULL,
  `end_time`     VARCHAR(5)   NOT NULL,
  `reason`       VARCHAR(200) NOT NULL DEFAULT 'Scheduled Break',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_breaks_date_time` (`therapist_id`, `break_date`, `start_time`, `end_time`),
  CONSTRAINT `fk_break_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `therapist_leaves` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `therapist_id` INT UNSIGNED NOT NULL,
  `leave_date`   VARCHAR(10)  NOT NULL,
  `leave_type`   ENUM('SICK','VACATION','EMERGENCY','OTHER') NOT NULL DEFAULT 'VACATION',
  `notes`        VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_therapist_leave_date` (`therapist_id`, `leave_date`),
  KEY `idx_leaves_date` (`leave_date`),
  CONSTRAINT `fk_leave_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_maintenance` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id`      INT UNSIGNED NOT NULL,
  `start_date`   VARCHAR(10)  NOT NULL,
  `end_date`     VARCHAR(10)  NOT NULL,
  `reason`       VARCHAR(300) NOT NULL DEFAULT '',
  `scheduled_by` VARCHAR(100) NOT NULL DEFAULT 'Admin',
  `status`       ENUM('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maint_room_dates` (`room_id`, `start_date`, `end_date`),
  KEY `idx_maint_status` (`status`),
  CONSTRAINT `fk_maint_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_cleaning` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id`           INT UNSIGNED NOT NULL,
  `booking_id`        INT UNSIGNED NULL,
  `cleaning_date`     VARCHAR(10)  NOT NULL,
  `start_time`        VARCHAR(5)   NOT NULL,
  `end_time`          VARCHAR(5)   NOT NULL,
  `cleaning_status`   ENUM('SCHEDULED','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'SCHEDULED',
  `cleaned_by`        VARCHAR(100) NOT NULL DEFAULT '',
  `notes`             VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cleaning_room_time` (`room_id`, `cleaning_date`, `start_time`, `end_time`),
  KEY `idx_cleaning_status` (`cleaning_status`),
  KEY `idx_cleaning_booking` (`booking_id`),
  CONSTRAINT `fk_cleaning_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cleaning_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointment_room_assignments` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`        INT UNSIGNED NOT NULL,
  `room_id`           INT UNSIGNED NOT NULL,
  `assigned_date`     VARCHAR(10)  NOT NULL,
  `start_time`        VARCHAR(5)   NOT NULL,
  `end_time`          VARCHAR(5)   NOT NULL,
  `cleaning_end_time` VARCHAR(5)   NOT NULL,
  `status`            ENUM('ASSIGNED','RELEASED','CANCELLED') NOT NULL DEFAULT 'ASSIGNED',
  `assigned_by`       VARCHAR(100) NOT NULL DEFAULT 'Admin',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_assign_time` (`room_id`, `assigned_date`, `start_time`, `cleaning_end_time`),
  KEY `idx_assign_booking` (`booking_id`),
  CONSTRAINT `fk_assign_room_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_room_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointment_therapist_assignments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`    INT UNSIGNED NOT NULL,
  `therapist_id`  INT UNSIGNED NOT NULL,
  `assigned_date` VARCHAR(10)  NOT NULL,
  `start_time`    VARCHAR(5)   NOT NULL,
  `end_time`      VARCHAR(5)   NOT NULL,
  `status`        ENUM('ASSIGNED','RELEASED','CANCELLED') NOT NULL DEFAULT 'ASSIGNED',
  `assigned_by`   VARCHAR(100) NOT NULL DEFAULT 'Admin',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_therapist_assign_time` (`therapist_id`, `assigned_date`, `start_time`, `end_time`),
  KEY `idx_assign_therapist_bk` (`booking_id`),
  CONSTRAINT `fk_assign_therapist_bk` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_therapist_th` FOREIGN KEY (`therapist_id`) REFERENCES `therapists`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
