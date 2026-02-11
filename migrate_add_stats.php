<?php
// Migration script to add stats and leaderboards tables

require_once 'config.php';
require_once 'db.php';

$db = Database::getInstance();

try {
    echo "Creating stats tables...\n";
    
    // Create stat_player table
    echo "Creating stat_player table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat_player` (
            `steam_id` VARCHAR(20) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_player table created!\n";
    
    // Create stat table
    echo "Creating stat table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat` (
            `stat_id` VARCHAR(32) PRIMARY KEY,
            `score_multiplier` BIGINT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat table created!\n";
    
    // Insert default stats
    echo "Adding default stats...\n";
    $defaultStats = [
        ['deaths',      -1],
        ['incaps',      -1],
        ['kills',        1],
        ['kills_air',    5],
        ['kills_cars',   2],
        ['kills_ships',  3],
        ['kills_tanks',  3],
        ['playtime',     0],
        ['revives',      2],
        ['score',        0],
        ['transports',   2]
    ];
    
    foreach ($defaultStats as $stat) {
        $db->query(
            "INSERT INTO `stat` VALUES (?, ?) ON DUPLICATE KEY UPDATE score_multiplier = VALUES(score_multiplier)",
            $stat
        );
    }
    echo "✓ Default stats added!\n";
    
    // Create stat_server table
    echo "Creating stat_server table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat_server` (
            `server_id` VARCHAR(32) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_server table created!\n";
    
    // Insert default server
    echo "Adding default server...\n";
    $db->query("INSERT INTO `stat_server` VALUES ('main', 'Main Server') ON DUPLICATE KEY UPDATE name = VALUES(name)");
    echo "✓ Default server added!\n";
    
    // Create stat_player_daily table
    echo "Creating stat_player_daily table...\n";
    $db->query("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_player_daily table created!\n";
    
    // Create stat_player_weekly table
    echo "Creating stat_player_weekly table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat_player_weekly` (
            `created_at` INT DEFAULT (YEARWEEK(CURRENT_DATE)),
            `steam_id` VARCHAR(20),
            `stat_id` VARCHAR(32),
            `server_id` VARCHAR(32),
            `amount` BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (`created_at`, `steam_id`, `stat_id`, `server_id`),
            FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
            FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
            FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_player_weekly table created!\n";
    
    // Create stat_player_monthly table
    echo "Creating stat_player_monthly table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat_player_monthly` (
            `created_at` INT DEFAULT (EXTRACT(YEAR_MONTH FROM CURRENT_DATE)),
            `steam_id` VARCHAR(20),
            `stat_id` VARCHAR(32),
            `server_id` VARCHAR(32),
            `amount` BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (`created_at`, `steam_id`, `stat_id`, `server_id`),
            FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
            FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
            FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_player_monthly table created!\n";
    
    // Create stat_player_alltime table
    echo "Creating stat_player_alltime table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS `stat_player_alltime` (
            `steam_id` VARCHAR(20),
            `stat_id` VARCHAR(32),
            `server_id` VARCHAR(32),
            `amount` BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (`steam_id`, `stat_id`, `server_id`),
            FOREIGN KEY (`steam_id`) REFERENCES `stat_player` (`steam_id`) ON DELETE CASCADE,
            FOREIGN KEY (`stat_id`) REFERENCES `stat` (`stat_id`) ON DELETE CASCADE,
            FOREIGN KEY (`server_id`) REFERENCES `stat_server` (`server_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ stat_player_alltime table created!\n";
    
    // Create stored procedures
    echo "Creating stored procedures and functions...\n";
    
    // Drop existing procedures/functions if they exist
    $db->query("DROP PROCEDURE IF EXISTS add_player_stat");
    $db->query("DROP PROCEDURE IF EXISTS prune_stat_player");
    $db->query("DROP PROCEDURE IF EXISTS add_stat_player_daily_score");
    $db->query("DROP FUNCTION IF EXISTS stat_score_multiplier");
    
    // Create stat_score_multiplier function
    $db->query("
        CREATE FUNCTION stat_score_multiplier(
            p_stat_id VARCHAR(32)
        )
        RETURNS BIGINT
        READS SQL DATA
        BEGIN
            RETURN (SELECT score_multiplier FROM stat WHERE stat_id = p_stat_id);
        END
    ");
    echo "✓ stat_score_multiplier function created!\n";
    
    // Create add_stat_player_daily_score procedure
    $db->query("
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
        END
    ");
    echo "✓ add_stat_player_daily_score procedure created!\n";
    
    // Create add_player_stat procedure
    $db->query("
        CREATE PROCEDURE add_player_stat(
            p_steam_id VARCHAR(20),
            p_name VARCHAR(255),
            p_stat_id VARCHAR(32),
            p_server_id VARCHAR(32),
            p_amount BIGINT
        )
        MODIFIES SQL DATA
        BEGIN
            INSERT INTO stat_player (steam_id, name)
                VALUES (p_steam_id, COALESCE(p_name, 'Unknown Player'))
                ON DUPLICATE KEY UPDATE
                    name = COALESCE(p_name, name);
            INSERT INTO stat_player_daily (created_at, steam_id, stat_id, server_id, amount)
                VALUES (CURRENT_DATE, p_steam_id, p_stat_id, p_server_id, p_amount)
                ON DUPLICATE KEY UPDATE
                    amount = amount + p_amount;
            CALL add_stat_player_daily_score(CURRENT_DATE, p_steam_id, p_stat_id, p_server_id, p_amount);
        END
    ");
    echo "✓ add_player_stat procedure created!\n";
    
    // Create prune_stat_player procedure
    $db->query("
        CREATE PROCEDURE prune_stat_player()
        MODIFIES SQL DATA
        BEGIN
            DELETE FROM stat_player_daily WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 MONTH);
            DELETE FROM stat_player_weekly WHERE created_at < YEARWEEK(DATE_SUB(NOW(), INTERVAL 2 MONTH));
            DELETE FROM stat_player_monthly WHERE created_at < EXTRACT(YEAR_MONTH FROM DATE_SUB(NOW(), INTERVAL 2 MONTH));
        END
    ");
    echo "✓ prune_stat_player procedure created!\n";
    
    // Create triggers
    echo "Creating triggers...\n";
    
    // Drop existing triggers if they exist
    $db->query("DROP TRIGGER IF EXISTS tg_add_stat_player_weekly");
    $db->query("DROP TRIGGER IF EXISTS tg_add_stat_player_monthly");
    $db->query("DROP TRIGGER IF EXISTS tg_add_stat_player_alltime");
    
    // Create trigger for weekly stats
    $db->query("
        CREATE TRIGGER tg_add_stat_player_weekly
            AFTER INSERT ON stat_player_daily
            FOR EACH ROW
            INSERT INTO stat_player_weekly (created_at, steam_id, stat_id, server_id, amount)
                VALUES (YEARWEEK(NEW.created_at), NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
                ON DUPLICATE KEY UPDATE
                    amount = amount + NEW.amount
    ");
    echo "✓ tg_add_stat_player_weekly trigger created!\n";
    
    // Create trigger for monthly stats
    $db->query("
        CREATE TRIGGER tg_add_stat_player_monthly
            AFTER INSERT ON stat_player_daily
            FOR EACH ROW
            INSERT INTO stat_player_monthly (created_at, steam_id, stat_id, server_id, amount)
                VALUES (EXTRACT(YEAR_MONTH FROM NEW.created_at), NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
                ON DUPLICATE KEY UPDATE
                    amount = amount + NEW.amount
    ");
    echo "✓ tg_add_stat_player_monthly trigger created!\n";
    
    // Create trigger for alltime stats
    $db->query("
        CREATE TRIGGER tg_add_stat_player_alltime
            AFTER INSERT ON stat_player_daily
            FOR EACH ROW
            INSERT INTO stat_player_alltime (steam_id, stat_id, server_id, amount)
                VALUES (NEW.steam_id, NEW.stat_id, NEW.server_id, NEW.amount)
                ON DUPLICATE KEY UPDATE
                    amount = amount + NEW.amount
    ");
    echo "✓ tg_add_stat_player_alltime trigger created!\n";
    
    // Create event
    echo "Creating scheduled event...\n";
    $db->query("DROP EVENT IF EXISTS prune_stat_player_event");
    $db->query("
        CREATE EVENT IF NOT EXISTS prune_stat_player_event
            ON SCHEDULE EVERY 1 MONTH
            DO CALL prune_stat_player()
    ");
    echo "✓ prune_stat_player_event created!\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Stats and leaderboards system is now ready to use.\n";
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
