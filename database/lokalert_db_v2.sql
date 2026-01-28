-- =============================================
-- LokAlert Database Schema v2.0
-- Updated: 2026-01-28
-- Features: User signup with email verification, 
--           password reset, download tracking
-- =============================================

-- Drop existing tables if they exist (for fresh install)
-- Uncomment these lines if you want to reset the database
-- DROP TABLE IF EXISTS `download_logs`;
-- DROP TABLE IF EXISTS `contact_messages`;
-- DROP TABLE IF EXISTS `apk_versions`;
-- DROP TABLE IF EXISTS `users`;

-- =============================================
-- Users Table (Updated with verification & reset)
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) DEFAULT 0,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(6) NULL,
    `verification_expires` TIMESTAMP NULL,
    `reset_token` VARCHAR(64) NULL,
    `reset_expires` TIMESTAMP NULL,
    `last_download_at` TIMESTAMP NULL,
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_verification_code` (`verification_code`),
    INDEX `idx_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- APK Versions Table
-- =============================================
CREATE TABLE IF NOT EXISTS `apk_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(20) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_size` BIGINT DEFAULT 0,
    `release_notes` TEXT,
    `download_count` INT DEFAULT 0,
    `is_latest` TINYINT(1) DEFAULT 0,
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Download Logs Table (Enhanced)
-- =============================================
CREATE TABLE IF NOT EXISTS `download_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `version_id` INT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `download_token` VARCHAR(64) UNIQUE,
    `status` ENUM('started', 'completed', 'failed', 'cancelled') DEFAULT 'started',
    `file_size` BIGINT DEFAULT 0,
    `bytes_downloaded` BIGINT DEFAULT 0,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`version_id`) REFERENCES `apk_versions`(`id`) ON DELETE SET NULL,
    INDEX `idx_download_token` (`download_token`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Contact Messages Table
-- =============================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Email Logs Table (For tracking sent emails)
-- =============================================
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `email_to` VARCHAR(100) NOT NULL,
    `email_type` ENUM('verification', 'password_reset', 'notification') NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Insert Default Admin User
-- Password: admin123 (hashed with password_hash)
-- =============================================
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`, `is_verified`) VALUES 
('admin', 'admin@lokalert.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)
ON DUPLICATE KEY UPDATE `username` = 'admin';

-- =============================================
-- Insert Sample APK Version
-- =============================================
INSERT INTO `apk_versions` (`version`, `filename`, `file_size`, `release_notes`, `download_count`, `is_latest`) VALUES 
('1.0.0', 'LokAlert Demo 1.apk', 17188901, 'Initial release - LokAlert Demo\n• Location-based arrival alerts\n• GPS tracking\n• Customizable radius\n• Background monitoring', 0, 1)
ON DUPLICATE KEY UPDATE `version` = '1.0.0';

-- =============================================
-- Migration Query (If upgrading from v1)
-- Run these if you have existing data
-- =============================================
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `verification_code` VARCHAR(6) NULL;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `verification_expires` TIMESTAMP NULL;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(64) NULL;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_expires` TIMESTAMP NULL;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_download_at` TIMESTAMP NULL;
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `download_count` INT DEFAULT 0;
-- ALTER TABLE `users` MODIFY COLUMN `username` VARCHAR(50) NULL;

-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `download_token` VARCHAR(64) UNIQUE;
-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `status` ENUM('started', 'completed', 'failed', 'cancelled') DEFAULT 'started';
-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `file_size` BIGINT DEFAULT 0;
-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `bytes_downloaded` BIGINT DEFAULT 0;
-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE `download_logs` ADD COLUMN IF NOT EXISTS `completed_at` TIMESTAMP NULL;
