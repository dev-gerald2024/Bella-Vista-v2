-- ============================================================================
-- Bella Vista Oasis Spa - Migration 003: Double Booking Protection & Concurrency
-- Adds performance composite index for concurrent slot checking.
-- ============================================================================

USE `bellavista`;

-- Add composite index to accelerate slot validation queries under concurrent load
ALTER TABLE `bookings` ADD KEY IF NOT EXISTS `idx_bookings_slot` (`preferred_date`, `status`, `archived`);
