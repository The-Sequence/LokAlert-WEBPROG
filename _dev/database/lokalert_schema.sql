-- ============================================================
-- LokAlert Database Schema
-- DBMS: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (full Unicode support)
-- Host: InfinityFree (sql300.infinityfree.com)
-- Note: Do NOT use CREATE DATABASE or USE statements on
--       InfinityFree. The database is pre-created for you.
-- ============================================================

-- ============================================================
-- 1. ROLES TABLE
-- Stores user role definitions and their permissions.
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `role_name`   VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `permissions` TEXT NULL COMMENT 'JSON-encoded permission list',
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default roles
INSERT IGNORE INTO `roles` (`id`, `role_name`, `description`, `permissions`) VALUES
(1, 'admin', 'Full system access – manage users, versions, messages, and settings',
    '["users.read","users.create","users.update","users.delete","versions.read","versions.create","versions.update","versions.delete","messages.read","messages.delete","downloads.read","settings.manage","backup.create"]'),
(2, 'user', 'Standard authenticated user – can download APK and submit contact messages',
    '["versions.read","downloads.create","messages.create","profile.read","profile.update"]');

-- ============================================================
-- 2. USERS TABLE
-- Stores registered user accounts with authentication data.
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `username`              VARCHAR(50) NULL,
    `email`                 VARCHAR(100) NOT NULL UNIQUE,
    `password`              VARCHAR(255) NOT NULL,
    `role_id`               INT NOT NULL DEFAULT 2,
    `is_admin`              TINYINT(1) DEFAULT 0,
    `is_verified`           TINYINT(1) DEFAULT 0,
    `verification_code`     VARCHAR(10) NULL,
    `verification_expires`  DATETIME NULL,
    `reset_token`           VARCHAR(100) NULL,
    `reset_expires`         DATETIME NULL,
    `download_count`        INT DEFAULT 0,
    `last_download_at`      DATETIME NULL,
    `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_email`               (`email`),
    INDEX `idx_verification_code`   (`verification_code`),
    INDEX `idx_reset_token`         (`reset_token`),
    INDEX `idx_role_id`             (`role_id`),

    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. APK VERSIONS TABLE
-- Stores each released APK version and its metadata.
-- ============================================================
CREATE TABLE IF NOT EXISTS `apk_versions` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `version`         VARCHAR(20) NOT NULL,
    `filename`        VARCHAR(255) NOT NULL,
    `file_size`       BIGINT DEFAULT 0,
    `download_url`    VARCHAR(500) NULL,
    `release_notes`   TEXT NULL,
    `is_latest`       TINYINT(1) DEFAULT 0,
    `download_count`  INT DEFAULT 0,
    `stored_in_db`    TINYINT(1) DEFAULT 0 COMMENT '1 if APK binary is stored in apk_file_chunks',
    `upload_date`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_is_latest` (`is_latest`),
    INDEX `idx_version`   (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. DOWNLOAD LOGS TABLE
-- Tracks every download attempt with status and progress.
-- FK references: users.id, apk_versions.id
-- ============================================================
CREATE TABLE IF NOT EXISTS `download_logs` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`           INT NOT NULL,
    `version_id`        INT NULL,
    `ip_address`        VARCHAR(45) NULL,
    `user_agent`        TEXT NULL,
    `download_token`    VARCHAR(64) NULL,
    `status`            ENUM('started','completed','failed','cancelled') DEFAULT 'started',
    `file_size`         BIGINT DEFAULT 0,
    `bytes_downloaded`  BIGINT DEFAULT 0,
    `started_at`        DATETIME NULL,
    `completed_at`      DATETIME NULL,

    INDEX `idx_user_id`         (`user_id`),
    INDEX `idx_version_id`      (`version_id`),
    INDEX `idx_download_token`  (`download_token`),
    INDEX `idx_status`          (`status`),

    CONSTRAINT `fk_downloads_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_downloads_version`
        FOREIGN KEY (`version_id`) REFERENCES `apk_versions`(`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. CONTACT MESSAGES TABLE
-- Stores messages submitted through the public contact form.
-- ============================================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `email`       VARCHAR(100) NOT NULL,
    `subject`     VARCHAR(200) NOT NULL,
    `message`     TEXT NOT NULL,
    `is_read`     TINYINT(1) DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_is_read`     (`is_read`),
    INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. EMAIL LOGS TABLE
-- Records every email sent by the system for auditing.
-- FK reference: users.id
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT NULL,
    `email_to`         VARCHAR(255) NOT NULL,
    `email_type`       VARCHAR(50) NOT NULL,
    `subject`          VARCHAR(255) NULL,
    `status`           ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `error_message`    TEXT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_email_user`    (`user_id`),
    INDEX `idx_email_status`  (`status`),

    CONSTRAINT `fk_email_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. LOGIN ATTEMPTS TABLE
-- Tracks failed login attempts for brute-force protection.
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(100) NOT NULL,
    `ip_address`    VARCHAR(45) NOT NULL,
    `attempted_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `success`       TINYINT(1) DEFAULT 0,

    INDEX `idx_login_email`    (`email`),
    INDEX `idx_login_ip`       (`ip_address`),
    INDEX `idx_login_time`     (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. AUDIT LOGS TABLE
-- Records all significant database changes for accountability.
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT NULL,
    `action`       VARCHAR(100) NOT NULL COMMENT 'e.g. CREATE_USER, DELETE_VERSION',
    `table_name`   VARCHAR(100) NULL,
    `record_id`    INT NULL,
    `old_values`   TEXT NULL COMMENT 'JSON snapshot before change',
    `new_values`   TEXT NULL COMMENT 'JSON snapshot after change',
    `ip_address`   VARCHAR(45) NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_audit_user`    (`user_id`),
    INDEX `idx_audit_action`  (`action`),
    INDEX `idx_audit_table`   (`table_name`),
    INDEX `idx_audit_time`    (`created_at`),

    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. SETTINGS TABLE
-- Stores admin-configurable system settings.
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key`    VARCHAR(100) NOT NULL UNIQUE,
    `setting_value`  TEXT NULL,
    `description`    VARCHAR(255) NULL,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('session_timeout_minutes', '5', 'Auto-logout after N minutes of inactivity'),
('download_cooldown_minutes', '5', 'Cooldown between downloads in minutes'),
('max_login_attempts', '5', 'Max failed login attempts before lockout'),
('login_lockout_minutes', '15', 'Lockout duration after max failed attempts'),
('site_maintenance_mode', '0', 'Enable maintenance mode (0=off, 1=on)'),
('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.', 'Message shown during maintenance mode'),
('invite_expiry_hours', '48', 'Admin invite link expiry in hours'),
('apk_db_storage_enabled', '0', 'Allow storing APK files in database (0=off, 1=on)');

-- ============================================================
-- 10. ADMIN INVITES TABLE
-- Stores admin invitation tokens for inviting new admins.
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_invites` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NULL,
    `email`           VARCHAR(100) NOT NULL,
    `token`           VARCHAR(100) NOT NULL UNIQUE,
    `password_hash`   VARCHAR(255) NULL COMMENT 'Pre-set password hash (optional)',
    `created_by`      INT NULL COMMENT 'Admin user who created the invite',
    `expires_at`      DATETIME NOT NULL,
    `used_at`         DATETIME NULL COMMENT 'When the invite was accepted',
    `invalidated_at`  DATETIME NULL COMMENT 'When the invite was revoked',
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_invite_token` (`token`),
    INDEX `idx_invite_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. APK FILE CHUNKS TABLE
-- Stores APK binary data in 512KB chunks to work within
-- MySQL max_allowed_packet limits on InfinityFree.
-- ============================================================
CREATE TABLE IF NOT EXISTS `apk_file_chunks` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `version_id`      INT NOT NULL,
    `chunk_index`     INT NOT NULL DEFAULT 0,
    `chunk_data`      LONGBLOB NOT NULL,
    `chunk_size`      INT NOT NULL DEFAULT 0,
    `total_chunks`    INT NOT NULL DEFAULT 1,
    `filename`        VARCHAR(255) NOT NULL,
    `total_size`      BIGINT NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_version_chunk` (`version_id`, `chunk_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DEFAULT ADMIN USER
-- Password: lokalert2024  (hashed with PASSWORD_DEFAULT)
-- ============================================================
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role_id`, `is_admin`, `is_verified`, `created_at`)
VALUES (
    'admin',
    'admin',
    '$2y$12$Bok1BnYTNR/X8bIVXpTsWe9NGVuWLlaf.875kXXAZV6.VGTridaQe',
    1,
    1,
    1,
    NOW()
);

-- ============================================================
-- SAMPLE INSERT STATEMENTS
-- ============================================================

-- Insert a sample APK version
INSERT IGNORE INTO `apk_versions` (`version`, `filename`, `file_size`, `download_url`, `release_notes`, `is_latest`)
VALUES (
    '2.0.1',
    'LokAlert-v2.0.1.apk',
    18500000,
    'https://github.com/The-Sequence/LokAlert-WEBPROG/releases/download/v2.0.1/LokAlert-v2.0.1.apk',
    'Initial public release with location-based arrival alerts.',
    1
);

-- Insert a sample contact message
INSERT IGNORE INTO `contact_messages` (`name`, `email`, `subject`, `message`)
VALUES (
    'Juan Dela Cruz',
    'juan@example.com',
    'Feature Request',
    'I would love to see an iOS version of LokAlert! Great app for commuters.'
);

-- ============================================================
-- SAMPLE SELECT QUERIES
-- ============================================================

-- Get all verified users with their roles
-- SELECT u.id, u.username, u.email, r.role_name, u.download_count, u.created_at
-- FROM users u
-- JOIN roles r ON u.role_id = r.id
-- WHERE u.is_verified = 1
-- ORDER BY u.created_at DESC;

-- Get download statistics per version
-- SELECT av.version, av.download_count, COUNT(dl.id) AS log_entries
-- FROM apk_versions av
-- LEFT JOIN download_logs dl ON av.id = dl.version_id AND dl.status = 'completed'
-- GROUP BY av.id
-- ORDER BY av.upload_date DESC;

-- Get recent audit trail
-- SELECT al.action, al.table_name, al.record_id, u.username, al.created_at
-- FROM audit_logs al
-- LEFT JOIN users u ON al.user_id = u.id
-- ORDER BY al.created_at DESC
-- LIMIT 20;
