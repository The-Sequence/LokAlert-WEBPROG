-- =============================================
-- LokAlert Database Schema
-- MySQL Database Export for phpMyAdmin
-- =============================================

-- Create database (optional - InfinityFree assigns one)
-- CREATE DATABASE IF NOT EXISTS lokalert_db;
-- USE lokalert_db;

-- =============================================
-- Users Table
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
-- Download Logs Table
-- =============================================
CREATE TABLE IF NOT EXISTS `download_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `version_id` INT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `download_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`version_id`) REFERENCES `apk_versions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Contact Messages Table (Additional CRUD feature)
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
-- Insert Default Admin User
-- Password: admin123 (hashed with password_hash)
-- =============================================
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`) VALUES 
('admin', 'admin@lokalert.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- =============================================
-- Insert Sample APK Versions
-- =============================================
INSERT INTO `apk_versions` (`version`, `filename`, `file_size`, `release_notes`, `download_count`, `is_latest`) VALUES 
('1.0.0', 'LokAlert-v1.0.0.apk', 15728640, 'Initial release with core features:\n- Location-based alerts\n- Geofencing support\n- Push notifications', 150, 0),
('1.1.0', 'LokAlert-v1.1.0.apk', 16777216, 'Feature update:\n- Improved GPS accuracy\n- Battery optimization\n- Bug fixes', 280, 0),
('1.2.0', 'LokAlert-v1.2.0.apk', 17825792, 'Latest version:\n- New UI design\n- Multiple destination support\n- Enhanced notifications\n- Performance improvements', 425, 1);

-- =============================================
-- Insert Sample Users
-- =============================================
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`) VALUES 
('john_doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0),
('jane_smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0);

-- =============================================
-- Insert Sample Download Logs
-- =============================================
INSERT INTO `download_logs` (`user_id`, `version_id`, `ip_address`, `user_agent`) VALUES 
(2, 3, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(3, 3, '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'),
(NULL, 2, '192.168.1.102', 'Mozilla/5.0 (Linux; Android 13) Mobile'),
(2, 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');

-- =============================================
-- Insert Sample Contact Messages
-- =============================================
INSERT INTO `contact_messages` (`name`, `email`, `subject`, `message`, `is_read`) VALUES 
('Mike Johnson', 'mike@example.com', 'Feature Request', 'Would love to see widget support in the next update!', 0),
('Sarah Williams', 'sarah@example.com', 'Bug Report', 'App crashes when setting multiple destinations on Android 12.', 1),
('David Brown', 'david@example.com', 'General Inquiry', 'Is there an iOS version coming soon?', 0);
