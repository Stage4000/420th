<?php
// Migration script to add RCON settings table

require_once 'config.php';
require_once 'db.php';

$db = Database::getInstance();

try {
    echo "Creating server_settings table...\n";
    
    // Create server_settings table for RCON configuration
    $db->query("
        CREATE TABLE IF NOT EXISTS `server_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) UNIQUE NOT NULL,
            `setting_value` TEXT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_by_user_id` INT NULL,
            INDEX `idx_setting_key` (`setting_key`),
            FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ server_settings table created successfully!\n";
    
    // Add default RCON settings
    echo "Adding default RCON settings...\n";
    
    $defaultSettings = [
        ['rcon_enabled', '0'],
        ['rcon_host', ''],
        ['rcon_port', '2306'],
        ['rcon_password', '']
    ];
    
    foreach ($defaultSettings as $setting) {
        $db->query(
            "INSERT INTO server_settings (setting_key, setting_value) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_key = setting_key",
            $setting
        );
    }
    
    echo "✓ Default RCON settings added successfully!\n";
    
    // Add server_kick column to whitelist_bans table
    echo "Updating whitelist_bans table...\n";
    
    $db->query("
        ALTER TABLE `whitelist_bans` 
        ADD COLUMN `server_kick` TINYINT(1) DEFAULT 0 AFTER `ban_type`,
        ADD COLUMN `server_ban` TINYINT(1) DEFAULT 0 AFTER `server_kick`
    ");
    
    echo "✓ whitelist_bans table updated successfully!\n";
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    // Check if columns already exist
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ Columns already exist, skipping...\n";
    } else {
        echo "❌ Error during migration: " . $e->getMessage() . "\n";
        exit(1);
    }
}
