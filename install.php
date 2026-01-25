<?php
/**
 * 420th Delta Dashboard Installer
 * This file handles the initial setup on first run
 */

session_start();

// Check if already installed
if (file_exists('config.php')) {
    require_once 'config.php';
    // Try to connect to database to verify installation
    try {
        $testConn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        // Check if roles table exists
        $stmt = $testConn->query("SHOW TABLES LIKE 'roles'");
        if ($stmt->rowCount() > 0) {
            // Already installed, redirect to index
            header('Location: index');
            exit;
        }
    } catch (PDOException $e) {
        // Continue with installation
    }
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Database configuration step
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $steamApiKey = trim($_POST['steam_api_key'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        
        // Validate inputs
        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            $error = 'Please fill in all required database fields.';
        } elseif (empty($steamApiKey)) {
            $error = 'Steam API key is required.';
        } elseif (empty($baseUrl)) {
            $error = 'Base URL is required.';
        } else {
            // Test database connection
            try {
                $testConn = new PDO(
                    "mysql:host=" . $dbHost . ";charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Create database if it doesn't exist
                $dbNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName); // Sanitize database name
                $testConn->exec("CREATE DATABASE IF NOT EXISTS `$dbNameSafe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $testConn->exec("USE `$dbNameSafe`");
                
                // Create tables
                $schemaFile = 'database.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("Database schema file not found: $schemaFile");
                }
                $schema = file_get_contents($schemaFile);
                if ($schema === false) {
                    throw new Exception("Failed to read database schema file");
                }
                // Remove CREATE DATABASE and USE statements
                $schema = preg_replace('/CREATE DATABASE.*?;/s', '', $schema);
                $schema = preg_replace('/USE.*?;/s', '', $schema);
                
                // Split into individual statements and execute
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $testConn->exec($statement);
                    }
                }
                
                // Save configuration
                $configContent = "<?php\n";
                $configContent .= "// Configuration file for the 420th Delta Dashboard\n\n";
                $configContent .= "// Database Configuration\n";
                $configContent .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
                $configContent .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
                $configContent .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
                $configContent .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n\n";
                $configContent .= "// Steam OAuth Configuration\n";
                $configContent .= "define('STEAM_API_KEY', " . var_export($steamApiKey, true) . ");\n";
                $configContent .= "define('STEAM_LOGIN_URL', 'https://steamcommunity.com/openid/login');\n";
                $configContent .= "define('STEAM_RETURN_URL', " . var_export(rtrim($baseUrl, '/') . '/callback.php', true) . ");\n\n";
                $configContent .= "// Session Configuration\n";
                $configContent .= "define('SESSION_NAME', '420th_session');\n";
                $configContent .= "define('SESSION_LIFETIME', 3600 * 24); // 24 hours\n\n";
                $configContent .= "// Whitelist Roles\n";
                $configContent .= "define('ROLES', [\n";
                $configContent .= "    'S3' => 'S3',\n";
                $configContent .= "    'CAS' => 'CAS',\n";
                $configContent .= "    'S1' => 'S1',\n";
                $configContent .= "    'OPFOR' => 'OPFOR',\n";
                $configContent .= "    'ALL' => 'ALL (Staff)',\n";
                $configContent .= "    'ADMIN' => 'Administrator',\n";
                $configContent .= "    'MODERATOR' => 'Moderator',\n";
                $configContent .= "    'TRUSTED' => 'Trusted',\n";
                $configContent .= "    'MEDIA' => 'Media',\n";
                $configContent .= "    'CURATOR' => 'Curator',\n";
                $configContent .= "    'DEVELOPER' => 'Developer',\n";
                $configContent .= "    'PANEL' => 'Panel Administrator'\n";
                $configContent .= "]);\n\n";
                $configContent .= "// Start session\n";
                $configContent .= "session_name(SESSION_NAME);\n";
                $configContent .= "session_start();\n";
                
                file_put_contents('config.php', $configContent);
                
                // Store in session for next step
                $_SESSION['installer_db_host'] = $dbHost;
                $_SESSION['installer_db_name'] = $dbName;
                $_SESSION['installer_db_user'] = $dbUser;
                $_SESSION['installer_db_pass'] = $dbPass;
                
                header('Location: install?step=2');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($step === 2) {
        // First admin setup - this happens after Steam login
        if (isset($_SESSION['installer_first_user']) && $_SESSION['installer_first_user']) {
            header('Location: index');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>420th Delta Dashboard - Installation</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0e1a;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .installer-container {
            background: #1a1f2e;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            max-width: 600px;
            width: 100%;
            border: 1px solid #2a3142;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #e4e6eb;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .subtitle {
            color: #8b92a8;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2a3142;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #8b92a8;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        label .required {
            color: #dc3545;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="url"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        input:focus {
            outline: none;
            border-color: #1e3c72;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .btn {
            background: #1e3c72;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2a5298;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .info-box {
            background: #1e2837;
            border-left: 4px solid #4299e1;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        
        .info-box p {
            color: #90cdf4;
            margin: 0;
        }
        
        .steam-login-section {
            text-align: center;
        }
        
        .steam-login-btn {
            display: inline-block;
            background: #000;
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        
        .steam-login-btn:hover {
            background: #333;
        }
        
        footer {
            background: #1a1f2e;
            color: #8b92a8;
            padding: 1.5rem 2rem;
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #2a3142;
            font-size: 0.9rem;
        }
        
        footer a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        footer a:hover {
            color: #8b9cff;
        }
        
        @media (max-width: 768px) {
            footer {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
                padding: 1rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="logo">420th Delta</div>
        <div class="subtitle">Dashboard Installation</div>
        
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <h2 style="margin-bottom: 1rem; color: #1e3c72;">Step 1: Database Configuration</h2>
            
            <div class="info-box">
                <p><strong>Note:</strong> Please have your database credentials and Steam API key ready.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Database Host <span class="required">*</span></label>
                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                    <div class="help-text">Usually "localhost" or "127.0.0.1"</div>
                </div>
                
                <div class="form-group">
                    <label>Database Name <span class="required">*</span></label>
                    <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? '420th_whitelist'); ?>" required>
                    <div class="help-text">Will be created if it doesn't exist</div>
                </div>
                
                <div class="form-group">
                    <label>Database Username <span class="required">*</span></label>
                    <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    <div class="help-text">Leave blank if no password</div>
                </div>
                
                <div class="form-group">
                    <label>Steam API Key <span class="required">*</span></label>
                    <input type="text" name="steam_api_key" value="<?php echo htmlspecialchars($_POST['steam_api_key'] ?? ''); ?>" required>
                    <div class="help-text">Get one at <a href="https://steamcommunity.com/dev/apikey" target="_blank">steamcommunity.com/dev/apikey</a></div>
                </div>
                
                <div class="form-group">
                    <label>Base URL <span class="required">*</span></label>
                    <input type="url" name="base_url" value="<?php echo htmlspecialchars($_POST['base_url'] ?? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])); ?>" required>
                    <div class="help-text">The full URL to your installation (e.g., http://yourdomain.com or http://localhost/420th)</div>
                </div>
                
                <button type="submit" class="btn">Continue</button>
            </form>
        <?php elseif ($step === 2): ?>
            <h2 style="margin-bottom: 1rem; color: #1e3c72;">Step 2: Create Admin Account</h2>
            
            <div class="info-box">
                <p><strong>Almost done!</strong> Log in with Steam to create your administrator account.</p>
            </div>
            
            <div class="steam-login-section">
                <p style="margin-bottom: 1.5rem; color: #666;">
                    Click the button below to sign in with Steam.<br>
                    You will be granted the PANEL administrator role automatically.
                </p>
                <a href="callback?installer=1" class="steam-login-btn">
                    Sign in with Steam to Complete Setup
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div>© 2026 <a href="https://420thdelta.net" target="_blank">420th Delta Gaming Community</a></div>
        <div>Made with ❤️ by <a href="https://sitecritter.com" target="_blank">SiteCritter</a></div>
    </footer>
</body>
</html>
