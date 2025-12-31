-- Database schema for 420th Delta Whitelist System

CREATE DATABASE IF NOT EXISTS `420th_whitelist` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `420th_whitelist`;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `steam_id` VARCHAR(20) UNIQUE NOT NULL,
    `steam_name` VARCHAR(255) NOT NULL,
    `avatar_url` VARCHAR(500),
    `last_login` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_steam_id` (`steam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `alias` VARCHAR(100),
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User roles mapping table
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    `granted_by` INT,
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_role_id` (`role_id`)
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
