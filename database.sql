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
    ('rcon_password', ''),
    ('whitelist_agreement', '<p><strong>By requesting whitelist, you agree to the following:</strong></p>
<ul>
    <li>
        <strong>Pilot Communication</strong> - All pilots are expected to communicate in-game via text or voice. You may be asked to switch role if unable to communicate.
    </li>
    <li>
        <strong>Waiting For Passengers</strong> - Transport Helicopters should wait in an orderly fashion on the side of the yellow barriers opposite from spawn, leaving the traffic lane clear for infantry and vehicles.
    </li>
    <li>
        <strong>No CAS on Kavala</strong> - All Close Air Support is forbidden to engage the Priority Mission Kavala. This mission is meant to be close-quarters combat. CAS can ruin the mission if they destroy buildings containing intel. Contact an in-game Zeus or use the vote-kick feature to enforce this rule as needed.
    </li>
</ul>')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;


-- Staff notes table for recording punitive actions and other user notes
CREATE TABLE IF NOT EXISTS `staff_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_text` TEXT NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by_user_id` INT NULL,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stats and Leaderboards System
-- Player statistics tracking

CREATE TABLE IF NOT EXISTS `stat_player` (
    `steam_id` VARCHAR(20) PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
    -- NOTE: If used across multiple games, a player's name can change erratically.
    --       Even on Arma 3, players can change names quickly by switching
    --       between in-game profiles.
    --       Perhaps names should be keyed by server ID or some game ID?
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stat` (
    `stat_id` VARCHAR(32) PRIMARY KEY,
    `score_multiplier` BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stat` VALUES ('deaths',      -1);
INSERT INTO `stat` VALUES ('incaps',      -1);
INSERT INTO `stat` VALUES ('kills',        1);
INSERT INTO `stat` VALUES ('kills_air',    5);
INSERT INTO `stat` VALUES ('kills_cars',   2);
INSERT INTO `stat` VALUES ('kills_ships',  3);
INSERT INTO `stat` VALUES ('kills_tanks',  3);
INSERT INTO `stat` VALUES ('playtime',     0);
INSERT INTO `stat` VALUES ('revives',      2);
INSERT INTO `stat` VALUES ('score',        0);
INSERT INTO `stat` VALUES ('transports',   2);

CREATE TABLE IF NOT EXISTS `stat_server` (
    `server_id` VARCHAR(32) PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stat_server` VALUES ('main', 'Main Server');

CREATE TABLE IF NOT EXISTS `stat_player_daily` (
    `created_at` DATE,
    `steam_id` VARCHAR(20),
    `stat_id` VARCHAR(32),
    `server_id` VARCHAR(32),
    `amount` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`created_at`, `steam_id`, `stat_id`, `server_id`),
    FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
    FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
    FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Would prefer materialized view here, but MariaDB does not support it
CREATE TABLE IF NOT EXISTS `stat_player_weekly` (
    -- created_at DATE CHECK (DAYOFWEEK(created_at) = 1),
    `created_at` INT DEFAULT (YEARWEEK(CURRENT_DATE)),
    `steam_id` VARCHAR(20),
    `stat_id` VARCHAR(32),
    `server_id` VARCHAR(32),
    `amount` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`created_at`, `steam_id`, `stat_id`, `server_id`),
    FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
    FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
    FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Would prefer materialized view here, but MariaDB does not support it
CREATE TABLE IF NOT EXISTS `stat_player_monthly` (
    -- created_at DATE CHECK (DAY(created_at) = 1),
    `created_at` INT DEFAULT (EXTRACT(YEAR_MONTH FROM CURRENT_DATE)),
    `steam_id` VARCHAR(20),
    `stat_id` VARCHAR(32),
    `server_id` VARCHAR(32),
    `amount` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`created_at`, `steam_id`, `stat_id`, `server_id`),
    FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
    FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
    FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stat_player_alltime` (
    `steam_id` VARCHAR(20),
    `stat_id` VARCHAR(32),
    `server_id` VARCHAR(32),
    `amount` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`steam_id`, `stat_id`, `server_id`),
    FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
    FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
    FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- https://mariadb.com/docs/server/server-usage/stored-routines/stored-procedures/stored-procedure-overview
DELIMITER //

CREATE PROCEDURE add_player_stat(
    p_steam_id VARCHAR(20),
    p_name VARCHAR(255), -- nullable
    p_stat_id VARCHAR(32),
    p_server_id VARCHAR(32),
    p_amount BIGINT
)
    MODIFIES SQL DATA
    BEGIN
        -- https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/inserting-loading-data/insert-on-duplicate-key-update
        INSERT INTO stat_player (steam_id, name)
            VALUES (p_steam_id, COALESCE(p_name, 'Unknown Player'))
            ON DUPLICATE KEY UPDATE
                name = COALESCE(p_name, name);
        INSERT INTO stat_player_daily (created_at, steam_id, stat_id, server_id, amount)
            VALUES (CURRENT_DATE, p_steam_id, p_stat_id, p_server_id, p_amount)
            ON DUPLICATE KEY UPDATE
                amount = amount + p_amount;
        CALL add_stat_player_daily_score(CURRENT_DATE, p_steam_id, p_stat_id, p_server_id, p_amount);
    END;
//

CREATE PROCEDURE prune_stat_player()
    MODIFIES SQL DATA
    BEGIN
        DELETE FROM stat_player_daily WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 MONTH);
        DELETE FROM stat_player_weekly WHERE created_at < YEARWEEK(DATE_SUB(NOW(), INTERVAL 2 MONTH));
        DELETE FROM stat_player_monthly WHERE created_at < EXTRACT(YEAR_MONTH FROM DATE_SUB(NOW(), INTERVAL 2 MONTH));
    END;
//

CREATE PROCEDURE add_stat_player_daily_score(
    p_created_at DATE,
    p_steam_id VARCHAR(20),
    p_stat_id VARCHAR(32),
    p_server_id VARCHAR(32),
    p_amount BIGINT
)
    MODIFIES SQL DATA
    BEGIN
        INSERT INTO stat_player_daily (created_at, steam_id, stat_id, server_id, amount)
            VALUES (CURRENT_DATE, p_steam_id, 'score', p_server_id, p_amount * stat_score_multiplier(p_stat_id))
            ON DUPLICATE KEY UPDATE
                amount = amount + p_amount * stat_score_multiplier(p_stat_id);
    END;
//

-- https://mariadb.com/docs/server/server-usage/stored-routines/stored-functions/stored-function-overview
CREATE FUNCTION stat_score_multiplier(
    p_stat_id VARCHAR(32)
)
    RETURNS BIGINT
    READS SQL DATA
    BEGIN
        RETURN (SELECT score_multiplier FROM stat WHERE stat_id = p_stat_id);
    END;
//

DELIMITER ;

-- https://mariadb.com/docs/server/server-usage/triggers-events/triggers/trigger-overview
CREATE TRIGGER tg_add_stat_player_weekly
    AFTER INSERT ON stat_player_daily
    FOR EACH ROW
    INSERT INTO stat_player_weekly (created_at, steam_id, stat_id, server_id, amount)
        VALUES (YEARWEEK(NEW.created_at), NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
        ON DUPLICATE KEY UPDATE
            amount = amount + NEW.amount;

CREATE TRIGGER tg_add_stat_player_monthly
    AFTER INSERT ON stat_player_daily
    FOR EACH ROW
    INSERT INTO stat_player_monthly (created_at, steam_id, stat_id, server_id, amount)
        VALUES (EXTRACT(YEAR_MONTH FROM NEW.created_at), NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
        ON DUPLICATE KEY UPDATE
            amount = amount + NEW.amount;

CREATE TRIGGER tg_add_stat_player_alltime
    AFTER INSERT ON stat_player_daily
    FOR EACH ROW
    INSERT INTO stat_player_alltime (steam_id, stat_id, server_id, amount)
        VALUES (NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
        ON DUPLICATE KEY UPDATE
            amount = amount + NEW.amount;

-- https://mariadb.com/docs/server/server-usage/triggers-events/event-scheduler/events
CREATE EVENT IF NOT EXISTS prune_stat_player_event
    ON SCHEDULE EVERY 1 MONTH
    DO CALL prune_stat_player();
