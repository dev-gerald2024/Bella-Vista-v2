-- ============================================================================
-- BELLA VISTA OASIS SPA - AUTHORITATIVE DATABASE SCHEMA
-- Version: 6.0 (Complete System Schema)
-- Environment: MySQL 8.0+ / MariaDB 10.4+ / XAMPP
-- Description: Complete, pure DDL structure without data.
--              Includes all 24 tables, indexes, constraints, view, and triggers.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `bellavista` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bellavista`;

/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bellavista
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity`
--

DROP TABLE IF EXISTS `activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `text` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_act_user` (`user_id`),
  CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_log`
--

DROP TABLE IF EXISTS `admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `admin_name` varchar(100) NOT NULL DEFAULT '',
  `action` varchar(60) NOT NULL,
  `target` varchar(60) NOT NULL DEFAULT '',
  `detail` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adminlog_admin` (`admin_id`),
  KEY `idx_adminlog_time` (`created_at`),
  CONSTRAINT `fk_adminlog_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appointment_room_assignments`
--

DROP TABLE IF EXISTS `appointment_room_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_room_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL,
  `assigned_date` varchar(10) NOT NULL,
  `start_time` varchar(5) NOT NULL,
  `end_time` varchar(5) NOT NULL,
  `cleaning_end_time` varchar(5) NOT NULL,
  `status` enum('ASSIGNED','RELEASED','CANCELLED') NOT NULL DEFAULT 'ASSIGNED',
  `assigned_by` varchar(100) NOT NULL DEFAULT 'Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_room_assign_time` (`room_id`,`assigned_date`,`start_time`,`cleaning_end_time`),
  KEY `idx_assign_booking` (`booking_id`),
  CONSTRAINT `fk_assign_room_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_assign_room_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appointment_therapist_assignments`
--

DROP TABLE IF EXISTS `appointment_therapist_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_therapist_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `therapist_id` int(10) unsigned NOT NULL,
  `assigned_date` varchar(10) NOT NULL,
  `start_time` varchar(5) NOT NULL,
  `end_time` varchar(5) NOT NULL,
  `status` enum('ASSIGNED','RELEASED','CANCELLED') NOT NULL DEFAULT 'ASSIGNED',
  `assigned_by` varchar(100) NOT NULL DEFAULT 'Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_therapist_assign_time` (`therapist_id`,`assigned_date`,`start_time`,`end_time`),
  KEY `idx_assign_therapist_bk` (`booking_id`),
  CONSTRAINT `fk_assign_therapist_bk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_assign_therapist_th` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(254) NOT NULL DEFAULT '',
  `service` varchar(150) NOT NULL,
  `preferred_date` varchar(10) NOT NULL DEFAULT '',
  `time` varchar(5) NOT NULL DEFAULT '',
  `duration` varchar(30) NOT NULL DEFAULT '',
  `guests` int(10) unsigned NOT NULL DEFAULT 1,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('UNPAID','PARTIALLY_PAID','PAID','REFUNDED','FORFEITED') NOT NULL DEFAULT 'UNPAID',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `downpayment_required` decimal(10,2) NOT NULL DEFAULT 0.00,
  `phone` varchar(30) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `status` enum('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NO-SHOW','REJECTED') NOT NULL DEFAULT 'PENDING',
  `cancel_reason` varchar(100) NOT NULL DEFAULT '',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` varchar(50) DEFAULT NULL,
  `cancellation_reason` varchar(300) NOT NULL DEFAULT '',
  `original_date` varchar(10) DEFAULT NULL,
  `original_time` varchar(5) DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `rescheduled_by` varchar(50) DEFAULT NULL,
  `room_id` varchar(20) DEFAULT NULL,
  `therapist_id` int(10) unsigned DEFAULT NULL,
  `package_id` int(10) unsigned DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `hidden_for_customer` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_date` (`preferred_date`),
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_slot` (`preferred_date`,`status`,`archived`),
  KEY `idx_bookings_date_status` (`preferred_date`,`status`),
  KEY `idx_bookings_user_status` (`user_id`,`status`),
  KEY `idx_bookings_user_date` (`user_id`,`preferred_date`),
  KEY `idx_bookings_room_code` (`room_id`),
  KEY `idx_bookings_therapist` (`therapist_id`),
  KEY `idx_bookings_package` (`package_id`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10086 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `content`
--

DROP TABLE IF EXISTS `content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content` (
  `content_key` varchar(50) NOT NULL,
  `json` longtext NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`content_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `user_id` int(10) unsigned NOT NULL,
  `item_key` varchar(190) NOT NULL,
  PRIMARY KEY (`user_id`,`item_key`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `text` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `package_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `min_guests` int(10) unsigned NOT NULL DEFAULT 1,
  `standard_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `max_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `default_duration` int(10) unsigned NOT NULL DEFAULT 60,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `additional_guest_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `package_status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `description` varchar(500) NOT NULL DEFAULT '',
  `management_notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_code` (`package_code`),
  KEY `idx_packages_category` (`category_code`),
  KEY `idx_packages_status` (`package_status`),
  CONSTRAINT `fk_packages_category` FOREIGN KEY (`category_code`) REFERENCES `room_categories` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_settings`
--

DROP TABLE IF EXISTS `payment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reschedule_requests`
--

DROP TABLE IF EXISTS `reschedule_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reschedule_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `new_date` varchar(10) NOT NULL,
  `new_time` varchar(5) NOT NULL,
  `original_date` varchar(10) DEFAULT NULL,
  `original_time` varchar(5) DEFAULT NULL,
  `reason` varchar(300) NOT NULL DEFAULT '',
  `status` enum('PENDING','APPROVED','DECLINED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
  `admin_note` varchar(300) NOT NULL DEFAULT '',
  `decided_by` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resched_booking` (`booking_id`,`status`),
  KEY `idx_resched_user` (`user_id`),
  CONSTRAINT `fk_resched_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_resched_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` varchar(1000) NOT NULL DEFAULT '',
  `hidden` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_booking` (`booking_id`),
  KEY `idx_review_user` (`user_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `room_categories`
--

DROP TABLE IF EXISTS `room_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_categories` (
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `min_guests` int(10) unsigned NOT NULL DEFAULT 1,
  `standard_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `max_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `default_duration` int(10) unsigned NOT NULL DEFAULT 60,
  `documented_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `room_cleaning`
--

DROP TABLE IF EXISTS `room_cleaning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_cleaning` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `cleaning_date` varchar(10) NOT NULL,
  `start_time` varchar(5) NOT NULL,
  `end_time` varchar(5) NOT NULL,
  `status` enum('SCHEDULED','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'SCHEDULED',
  `cleaned_by` varchar(100) NOT NULL DEFAULT '',
  `completed_at` datetime DEFAULT NULL,
  `notes` varchar(300) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cleaning_schedule` (`room_id`,`cleaning_date`,`start_time`),
  KEY `fk_clean_booking` (`booking_id`),
  CONSTRAINT `fk_clean_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clean_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `room_maintenance`
--

DROP TABLE IF EXISTS `room_maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_maintenance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(10) unsigned NOT NULL,
  `start_date` varchar(10) NOT NULL,
  `start_time` varchar(5) NOT NULL DEFAULT '00:00',
  `end_date` varchar(10) NOT NULL,
  `end_time` varchar(5) NOT NULL DEFAULT '23:59',
  `reason` varchar(300) NOT NULL DEFAULT 'Scheduled Maintenance',
  `status` enum('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  `created_by` varchar(100) NOT NULL DEFAULT 'Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maint_dates` (`room_id`,`start_date`,`end_date`),
  CONSTRAINT `fk_maint_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_code` varchar(20) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `min_guests` int(10) unsigned NOT NULL DEFAULT 1,
  `standard_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `max_capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `room_status` enum('AVAILABLE','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
  `notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`room_code`),
  KEY `idx_rooms_category` (`category_code`),
  KEY `idx_rooms_status` (`room_status`),
  CONSTRAINT `fk_rooms_category` FOREIGN KEY (`category_code`) REFERENCES `room_categories` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `token_hash` char(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` bigint(20) NOT NULL,
  `expires_at` bigint(20) NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_exp` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `therapist_breaks`
--

DROP TABLE IF EXISTS `therapist_breaks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapist_breaks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `therapist_id` int(10) unsigned NOT NULL,
  `break_date` varchar(10) DEFAULT NULL,
  `start_time` varchar(5) NOT NULL,
  `end_time` varchar(5) NOT NULL,
  `break_type` varchar(50) NOT NULL DEFAULT 'Meal Break',
  `notes` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_break_lookup` (`therapist_id`,`break_date`,`start_time`),
  CONSTRAINT `fk_break_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `therapist_leaves`
--

DROP TABLE IF EXISTS `therapist_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapist_leaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `therapist_id` int(10) unsigned NOT NULL,
  `start_date` varchar(10) NOT NULL,
  `end_date` varchar(10) NOT NULL,
  `leave_type` varchar(50) NOT NULL DEFAULT 'Annual Leave',
  `status` enum('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'APPROVED',
  `notes` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_dates` (`therapist_id`,`start_date`,`end_date`),
  CONSTRAINT `fk_leave_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `therapist_schedules`
--

DROP TABLE IF EXISTS `therapist_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapist_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `therapist_id` int(10) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `shift_start` varchar(5) NOT NULL DEFAULT '13:00',
  `shift_end` varchar(5) NOT NULL DEFAULT '21:00',
  `is_working_day` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_therapist_day` (`therapist_id`,`day_of_week`),
  CONSTRAINT `fk_sched_therapist` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `therapists`
--

DROP TABLE IF EXISTS `therapists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(30) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(254) NOT NULL DEFAULT '',
  `specialization` varchar(300) NOT NULL DEFAULT 'ALL',
  `employment_status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `availability_status` enum('AVAILABLE','ON_BREAK','OFF_DUTY','ON_LEAVE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
  `notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_therapist_code` (`employee_code`),
  KEY `idx_therapist_status` (`employment_status`,`availability_status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tokens` (
  `token_hash` char(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `purpose` varchar(20) NOT NULL,
  `expires_at` bigint(20) NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `idx_tokens_user` (`user_id`,`purpose`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_reference` varchar(40) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `description` varchar(150) NOT NULL,
  `payment_method` enum('GCASH_DEMO','MAYA_DEMO','CASH_AT_SPA','BANK_TRANSFER_DEMO') NOT NULL DEFAULT 'CASH_AT_SPA',
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('DOWNPAYMENT','BALANCE_PAYMENT','FULL_PAYMENT','REFUND','FORFEITURE','ADJUSTMENT') NOT NULL DEFAULT 'DOWNPAYMENT',
  `receipt_number` varchar(40) DEFAULT NULL,
  `demo_mode` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(500) DEFAULT NULL,
  `refund_reason` varchar(500) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `status` enum('UNPAID','PENDING','PROCESSING','PAID','PARTIALLY_PAID','FAILED','CANCELLED','REFUNDED','PARTIALLY_REFUNDED','FORFEITED') NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_reference` (`transaction_reference`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `idx_txn_user` (`user_id`),
  KEY `idx_txn_booking` (`booking_id`),
  CONSTRAINT `fk_txn_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_txn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `userdata`
--

DROP TABLE IF EXISTS `userdata`;
/*!50001 DROP VIEW IF EXISTS `userdata`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `userdata` AS SELECT
 1 AS `id`,
  1 AS `username`,
  1 AS `email`,
  1 AS `password_hash`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('CUSTOMER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `phone` varchar(30) NOT NULL DEFAULT '',
  `birthday` varchar(10) NOT NULL DEFAULT '',
  `contact` varchar(20) NOT NULL DEFAULT '',
  `prefs` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
DROP TRIGGER IF EXISTS `trg_users_role_log`;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_users_role_log` AFTER UPDATE ON `users` FOR EACH ROW
  INSERT INTO `admin_log` (`admin_id`, `admin_name`, `action`, `target`, `detail`, `created_at`)
  SELECT NULL, 'Database', 'Role changed', NEW.`email`, CONCAT(OLD.`role`, ' -> ', NEW.`role`), UTC_TIMESTAMP()
  FROM DUAL WHERE OLD.`role` <> NEW.`role` */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Dumping routines for database 'bellavista'
--

--
-- Final view structure for view `userdata`
--

/*!50001 DROP VIEW IF EXISTS `userdata`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `userdata` AS select `users`.`id` AS `id`,`users`.`full_name` AS `username`,`users`.`email` AS `email`,`users`.`password_hash` AS `password_hash`,`users`.`created_at` AS `created_at`,`users`.`updated_at` AS `updated_at` from `users` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-13 14:56:14
