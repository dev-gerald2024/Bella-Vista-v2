-- ============================================================================
-- Bella Vista Oasis Spa - Migration 008: Dedicated Application Database User
-- Optional operational security script for production deployment.
-- Run in MySQL root console to create dedicated unprivileged web application user.
-- ============================================================================

-- Replace 'CHANGE-THIS-STRONG-PASSWORD' with your production database password:
CREATE USER IF NOT EXISTS 'bellavista_app'@'localhost' IDENTIFIED BY 'CHANGE-THIS-STRONG-PASSWORD';
ALTER USER 'bellavista_app'@'localhost' IDENTIFIED BY 'CHANGE-THIS-STRONG-PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON `bellavista`.* TO 'bellavista_app'@'localhost';
FLUSH PRIVILEGES;
