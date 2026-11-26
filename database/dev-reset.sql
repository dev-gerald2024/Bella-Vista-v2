-- ============================================================================
-- DESTRUCTIVE - DEVELOPMENT ONLY.  DELETES THE WHOLE `bellavista` DATABASE.
-- All customers, bookings, payments and content are permanently lost.
-- Never run this on a database you want to keep. Export a backup first
-- (docs/BACKUP.md). After running it, import database/schema.sql and then
-- database/seed.sql to start again from a clean install.
-- ============================================================================
DROP DATABASE IF EXISTS `bellavista`;
CREATE DATABASE `bellavista` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
