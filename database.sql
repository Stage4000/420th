-- Database schema for 420th Delta Whitelist System

CREATE DATABASE IF NOT EXISTS `420th_whitelist` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `420th_whitelist`;

-- Users table with boolean role columns for optimized queries
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `steam_id` VARCHAR(20) UNIQUE NOT NULL,
    `steam_name` VARCHAR(255) NOT NULL,
    `avatar_url` VARCHAR(500),
    `last_login` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Role assignments as boolean columns
    `role_s3` TINYINT(1) DEFAULT 0,
    `role_cas` TINYINT(1) DEFAULT 0,
    `role_s1` TINYINT(1) DEFAULT 0,
    `role_opfor` TINYINT(1) DEFAULT 0,
    `role_all` TINYINT(1) DEFAULT 0,
    `role_admin` TINYINT(1) DEFAULT 0,
    `role_moderator` TINYINT(1) DEFAULT 0,
    `role_trusted` TINYINT(1) DEFAULT 0,
    `role_media` TINYINT(1) DEFAULT 0,
    `role_curator` TINYINT(1) DEFAULT 0,
    `role_developer` TINYINT(1) DEFAULT 0,
    `role_panel` TINYINT(1) DEFAULT 0,
    INDEX `idx_steam_id` (`steam_id`),
    INDEX `idx_role_panel` (`role_panel`),
    INDEX `idx_role_admin` (`role_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table (kept for alias management only, roles stored as boolean columns in users table)
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `alias` VARCHAR(100),
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`name`, `display_name`, `description`) VALUES
    ('S3', 'S3', 'S3 personnel'),
    ('CAS', 'CAS', 'Close Air Support personnel'),
    ('S1', 'S1', 'S1 personnel'),
    ('OPFOR', 'OPFOR', 'Opposing Force personnel'),
    ('ALL', 'ALL (Staff)', 'All staff members should have this role'),
    ('ADMIN', 'Administrator', 'Administrator with elevated privileges'),
    ('MODERATOR', 'Moderator', 'Moderator with moderation privileges'),
    ('TRUSTED', 'Trusted', 'Trusted community member'),
    ('MEDIA', 'Media', 'Media team member'),
    ('CURATOR', 'Curator', 'Content curator'),
    ('DEVELOPER', 'Developer', 'Developer team member'),
    ('PANEL', 'Panel Administrator', 'Panel admin with user management rights')
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`), `description` = VALUES(`description`);

-- Whitelist bans table
CREATE TABLE IF NOT EXISTS `whitelist_bans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `banned_by_user_id` INT NOT NULL,
    `ban_type` ENUM('S3', 'CAS', 'BOTH') DEFAULT 'BOTH',
    `server_kick` TINYINT(1) DEFAULT 0,
    `server_ban` TINYINT(1) DEFAULT 0,
    `ban_reason` TEXT,
    `ban_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ban_expires` DATETIME NULL DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `unbanned_by_user_id` INT NULL DEFAULT NULL,
    `unban_date` DATETIME NULL DEFAULT NULL,
    `unban_reason` TEXT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`banned_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`unbanned_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_ban_expires` (`ban_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server settings table for RCON and other configurations
CREATE TABLE IF NOT EXISTS `server_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by_user_id` INT NULL,
    INDEX `idx_setting_key` (`setting_key`),
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default RCON settings
INSERT INTO `server_settings` (`setting_key`, `setting_value`) VALUES
    ('rcon_enabled', '0'),
    ('rcon_host', ''),
    ('rcon_port', '2306'),
    ('rcon_password', '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
