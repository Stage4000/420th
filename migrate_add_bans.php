<?php
/**
 * Migration Script: Add Whitelist Bans Table
 * 
 * This script adds the whitelist_bans table to existing installations.
 * Run this once after updating to the version with ban functionality.
 */

// Prevent direct access
if (!isset($_SERVER['HTTP_HOST'])) {
    die('This script must be run through a web browser.');
}

// Check if config exists
if (!file_exists(__DIR__ . '/config.php')) {
    die('Error: config.php not found. Please run the installer first.');
}

require_once __DIR__ . '/config.php';

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// HTML header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bans Table Migration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f2e 100%);
            color: #e4e6eb;
            min-height: 100vh;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: #1a1f2e;
            border: 1px solid #2a3142;
            border-radius: 12px;
            padding: 2rem;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }
        
        .status {
            background: #0a0e1a;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .success {
            border-left-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        
        .error {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .warning {
            border-left-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }
        
        .info {
            border-left-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        button:hover {
            background: #5568d3;
        }
        
        button:disabled {
            background: #4a5568;
            cursor: not-allowed;
        }
        
        pre {
            background: #0a0e1a;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
            margin: 1rem 0;
            font-size: 0.875rem;
        }
        
        .step {
            margin-bottom: 1.5rem;
        }
        
        .step-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Add Whitelist Bans Table</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
            echo '<div class="status info"><strong>Starting migration...</strong></div>';
            
            try {
                // Check if table already exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'whitelist_bans'");
                if ($stmt->rowCount() > 0) {
                    // Table exists, check if columns need to be fixed
                    echo '<div class="step">';
                    echo '<div class="step-title">Checking existing table structure...</div>';
                    
                    $columns = $pdo->query("SHOW COLUMNS FROM whitelist_bans")->fetchAll(PDO::FETCH_ASSOC);
                    $columnNames = array_column($columns, 'Field');
                    
                    $needsFix = false;
                    if (in_array('banned_by', $columnNames) && !in_array('banned_by_user_id', $columnNames)) {
                        $needsFix = true;
                    }
                    
                    if ($needsFix) {
                        echo '<div class="status warning">Found incorrect column names. Fixing...</div>';
                        
                        // Rename columns
                        $pdo->exec("ALTER TABLE whitelist_bans CHANGE COLUMN `banned_by` `banned_by_user_id` int(11) NOT NULL");
                        if (in_array('unbanned_by', $columnNames)) {
                            $pdo->exec("ALTER TABLE whitelist_bans CHANGE COLUMN `unbanned_by` `unbanned_by_user_id` int(11) DEFAULT NULL");
                        }
                        
                        // Reorder columns if needed
                        $pdo->exec("ALTER TABLE whitelist_bans MODIFY COLUMN `unban_date` datetime DEFAULT NULL AFTER `unbanned_by_user_id`");
                        $pdo->exec("ALTER TABLE whitelist_bans MODIFY COLUMN `unban_reason` text DEFAULT NULL AFTER `unban_date`");
                        
                        echo '<div class="status success">✅ Table structure fixed successfully</div>';
                        echo '</div>';
                        
                        echo '<div class="status success">';
                        echo '<strong>✅ Migration Complete!</strong><br>';
                        echo 'The whitelist_bans table has been fixed and is now compatible.<br><br>';
                        echo '<strong>Next Steps:</strong><br>';
                        echo '1. Delete this migration script (migrate_add_bans.php) for security<br>';
                        echo '2. Your panel now supports whitelist bans!<br>';
                        echo '3. Users with the ALL flag can now manage bans from the Users page';
                        echo '</div>';
                    } else {
                        echo '<div class="status success">';
                        echo '<strong>✅ Table Already Exists</strong><br>';
                        echo 'The whitelist_bans table already exists with the correct structure. No migration needed.<br><br>';
                        echo 'You can delete this migration script (migrate_add_bans.php) for security.';
                        echo '</div>';
                    }
                } else {
                    // Create whitelist_bans table
                    echo '<div class="step">';
                    echo '<div class="step-title">Step 1: Creating whitelist_bans table...</div>';
                    
                    $sql = "CREATE TABLE `whitelist_bans` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `banned_by_user_id` int(11) NOT NULL,
                        `ban_reason` text DEFAULT NULL,
                        `ban_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `ban_expires` datetime DEFAULT NULL,
                        `is_active` tinyint(1) NOT NULL DEFAULT 1,
                        `unbanned_by_user_id` int(11) DEFAULT NULL,
                        `unban_date` datetime DEFAULT NULL,
                        `unban_reason` text DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `is_active` (`is_active`),
                        KEY `ban_expires` (`ban_expires`),
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                        FOREIGN KEY (`banned_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                        FOREIGN KEY (`unbanned_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    
                    $pdo->exec($sql);
                    echo '<div class="status success">✅ Table created successfully</div>';
                    echo '</div>';
                    
                    echo '<div class="status success">';
                    echo '<strong>✅ Migration Complete!</strong><br>';
                    echo 'The whitelist_bans table has been successfully added to your database.<br><br>';
                    echo '<strong>Next Steps:</strong><br>';
                    echo '1. Delete this migration script (migrate_add_bans.php) for security<br>';
                    echo '2. Your panel now supports whitelist bans!<br>';
                    echo '3. Users with the ALL flag can now manage bans from the Users page';
                    echo '</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="status error">';
                echo '<strong>❌ Migration Failed</strong><br>';
                echo 'Error: ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            
        } else {
            // Display information and migration button
            ?>
            <div class="status info">
                <strong>ℹ️ About This Migration</strong><br>
                This migration adds the whitelist_bans table to your database, enabling the whitelist ban system.
            </div>
            
            <div class="status warning">
                <strong>⚠️ Before You Start</strong><br>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Backup your database before proceeding</li>
                    <li>This migration is safe and non-destructive</li>
                    <li>Your existing data will not be affected</li>
                </ul>
            </div>
            
            <div class="step">
                <div class="step-title">What will be created:</div>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li><code>whitelist_bans</code> table - Stores ban records with expiration dates</li>
                </ul>
            </div>
            
            <form method="POST" onsubmit="return confirm('Have you backed up your database?');">
                <button type="submit" name="migrate">🚀 Start Migration</button>
            </form>
            
            <?php
        }
        ?>
    </div>
</body>
</html>
