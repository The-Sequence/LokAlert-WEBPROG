-- =============================================
-- LokAlert Database Migration: v1 -> v2
-- Run this to add verification and download tracking columns
-- =============================================

-- Add verification columns to users table
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) DEFAULT 0 AFTER `is_admin`,
    ADD COLUMN IF NOT EXISTS `verification_code` VARCHAR(6) NULL AFTER `is_verified`,
    ADD COLUMN IF NOT EXISTS `verification_expires` TIMESTAMP NULL AFTER `verification_code`,
    ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(64) NULL AFTER `verification_expires`,
    ADD COLUMN IF NOT EXISTS `reset_expires` TIMESTAMP NULL AFTER `reset_token`,
    ADD COLUMN IF NOT EXISTS `last_download_at` TIMESTAMP NULL AFTER `reset_expires`,
    ADD COLUMN IF NOT EXISTS `download_count` INT DEFAULT 0 AFTER `last_download_at`;

-- Make username nullable (email is required, name is optional)
ALTER TABLE `users` MODIFY COLUMN `username` VARCHAR(50) NULL;

-- Add indexes for faster lookups
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_verification_code` (`verification_code`);
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_reset_token` (`reset_token`);

-- Mark existing admin users as verified
UPDATE `users` SET `is_verified` = 1 WHERE `is_admin` = 1;

-- Add download tracking columns to download_logs table
ALTER TABLE `download_logs`
    ADD COLUMN IF NOT EXISTS `download_token` VARCHAR(64) NULL AFTER `user_agent`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('started', 'completed', 'failed', 'cancelled') DEFAULT 'started' AFTER `download_token`,
    ADD COLUMN IF NOT EXISTS `file_size` BIGINT DEFAULT 0 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `bytes_downloaded` BIGINT DEFAULT 0 AFTER `file_size`,
    ADD COLUMN IF NOT EXISTS `started_at` TIMESTAMP NULL AFTER `bytes_downloaded`,
    ADD COLUMN IF NOT EXISTS `completed_at` TIMESTAMP NULL AFTER `started_at`;

-- Add index for download token
ALTER TABLE `download_logs` ADD INDEX IF NOT EXISTS `idx_download_token` (`download_token`);
ALTER TABLE `download_logs` ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Create email_logs table if not exists
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

-- Success message
SELECT 'Migration completed successfully!' AS result;
