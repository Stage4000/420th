<?php
/**
 * Installation Verification Script
 * Run this file in your browser after setup to verify configuration
 * Delete this file after successful verification for security
 */

// Security: Only allow from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('This script can only be run from localhost. Delete this file after setup.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Verification - 420th Delta</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: white;
            padding: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .check {
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .check.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .check.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .check.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .icon {
            font-size: 1.5rem;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 2rem;
        }
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>420th Delta - Installation Verification</h1>
        <p>Checking system configuration...</p>
    </div>
    <div class="content">
        <?php
        $errors = 0;
        $warnings = 0;
        
        // Check PHP version
        $phpVersion = phpversion();
        if (version_compare($phpVersion, '7.4.0', '>=')) {
            echo '<div class="check success"><span class="icon">✅</span><div><strong>PHP Version:</strong> ' . $phpVersion . ' (OK)</div></div>';
        } else {
            echo '<div class="check error"><span class="icon">❌</span><div><strong>PHP Version:</strong> ' . $phpVersion . ' (Requires 7.4+)</div></div>';
            $errors++;
        }
        
        // Check required extensions
        $required_extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'openssl'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                echo '<div class="check success"><span class="icon">✅</span><div><strong>Extension ' . $ext . ':</strong> Loaded</div></div>';
            } else {
                echo '<div class="check error"><span class="icon">❌</span><div><strong>Extension ' . $ext . ':</strong> Not loaded (Required)</div></div>';
                $errors++;
            }
        }
        
        // Check config file
        if (file_exists('config.php')) {
            echo '<div class="check success"><span class="icon">✅</span><div><strong>Config file:</strong> Found</div></div>';
            
            require_once 'config.php';
            
            // Check Steam API key
            if (defined('STEAM_API_KEY') && STEAM_API_KEY !== 'YOUR_STEAM_API_KEY_HERE') {
                echo '<div class="check success"><span class="icon">✅</span><div><strong>Steam API Key:</strong> Configured</div></div>';
            } else {
                echo '<div class="check warning"><span class="icon">⚠️</span><div><strong>Steam API Key:</strong> Not configured (Update in config.php)</div></div>';
                $warnings++;
            }
            
            // Check database connection
            try {
                require_once 'db.php';
                $db = Database::getInstance();
                echo '<div class="check success"><span class="icon">✅</span><div><strong>Database Connection:</strong> Successful</div></div>';
                
                // Check tables
                $tables = ['users', 'roles', 'user_roles'];
                foreach ($tables as $table) {
                    try {
                        $result = $db->query("SELECT 1 FROM `$table` LIMIT 1");
                        echo '<div class="check success"><span class="icon">✅</span><div><strong>Table `' . $table . '`:</strong> Exists</div></div>';
                    } catch (Exception $e) {
                        echo '<div class="check error"><span class="icon">❌</span><div><strong>Table `' . $table . '`:</strong> Not found (Run database.sql)</div></div>';
                        $errors++;
                    }
                }
                
                // Check roles
                try {
                    $roles = $db->fetchAll("SELECT name FROM roles");
                    $roleCount = count($roles);
                    if ($roleCount >= 12) {
                        echo '<div class="check success"><span class="icon">✅</span><div><strong>Roles:</strong> ' . $roleCount . ' roles configured</div></div>';
                    } else {
                        echo '<div class="check warning"><span class="icon">⚠️</span><div><strong>Roles:</strong> Only ' . $roleCount . ' roles found (Expected 12)</div></div>';
                        $warnings++;
                    }
                } catch (Exception $e) {
                    echo '<div class="check error"><span class="icon">❌</span><div><strong>Roles:</strong> Error checking roles</div></div>';
                    $errors++;
                }
                
            } catch (Exception $e) {
                echo '<div class="check error"><span class="icon">❌</span><div><strong>Database Connection:</strong> Failed<br><small>' . htmlspecialchars($e->getMessage()) . '</small></div></div>';
                $errors++;
            }
            
        } else {
            echo '<div class="check error"><span class="icon">❌</span><div><strong>Config file:</strong> Not found (Copy config.example.php to config.php)</div></div>';
            $errors++;
        }
        
        // Check file permissions
        if (is_writable(session_save_path())) {
            echo '<div class="check success"><span class="icon">✅</span><div><strong>Session Directory:</strong> Writable</div></div>';
        } else {
            echo '<div class="check error"><span class="icon">❌</span><div><strong>Session Directory:</strong> Not writable</div></div>';
            $errors++;
        }
        
        // Summary
        echo '<hr style="margin: 2rem 0;">';
        if ($errors === 0 && $warnings === 0) {
            echo '<div class="check success">';
            echo '<span class="icon">🎉</span>';
            echo '<div><strong>All checks passed!</strong><br>Your installation is ready. Delete this file for security.</div>';
            echo '</div>';
        } elseif ($errors === 0) {
            echo '<div class="check warning">';
            echo '<span class="icon">⚠️</span>';
            echo '<div><strong>Setup complete with warnings</strong><br>' . $warnings . ' warning(s) found. Review them above.</div>';
            echo '</div>';
        } else {
            echo '<div class="check error">';
            echo '<span class="icon">❌</span>';
            echo '<div><strong>Setup incomplete</strong><br>' . $errors . ' error(s) and ' . $warnings . ' warning(s) found. Fix them before proceeding.</div>';
            echo '</div>';
        }
        ?>
        
        <div class="warning-box">
            <strong>⚠️ Security Warning:</strong> Delete this file (verify.php) after confirming your installation is working correctly.
        </div>
        
        <?php if ($errors === 0): ?>
        <div style="margin-top: 2rem; padding: 1rem; background: #e3f2fd; border-radius: 5px;">
            <h3>Next Steps:</h3>
            <ol>
                <li>Delete this verification file (verify.php)</li>
                <li>Navigate to <a href="index.php">index.php</a> to test the login</li>
                <li>After first login, grant yourself the PANEL role using SQL:
                    <pre>-- Find your user ID
SELECT id FROM users WHERE steam_id = 'YOUR_STEAM_ID';

-- Get PANEL role ID  
SELECT id FROM roles WHERE name = 'PANEL';

-- Grant PANEL role (replace X and Y with actual IDs)
INSERT INTO user_roles (user_id, role_id) VALUES (X, Y);</pre>
                </li>
                <li>Access the admin panel to manage other users</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
