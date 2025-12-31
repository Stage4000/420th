<?php
// Steam OAuth callback handler

// Check if installation is needed
if (!file_exists('config.php') && !isset($_GET['installer'])) {
    header('Location: install.php');
    exit;
}

require_once 'steam_auth.php';

$installerMode = isset($_GET['installer']) && $_GET['installer'] == '1';

// Validate Steam response
$steamId = SteamAuth::validate();

if ($steamId) {
    // Login user
    if (SteamAuth::login($steamId)) {
        // Check if this is installer mode
        if ($installerMode) {
            require_once 'db.php';
            $db = Database::getInstance();
            
            // Get the user that just logged in
            $user = $db->fetchOne("SELECT id FROM users WHERE steam_id = ?", [$steamId]);
            
            if ($user) {
                // Get PANEL role ID
                $panelRole = $db->fetchOne("SELECT id FROM roles WHERE name = 'PANEL'");
                
                if ($panelRole) {
                    // Grant PANEL role to this first user
                    $db->execute(
                        "INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)",
                        [$user['id'], $panelRole['id']]
                    );
                }
            }
            
            // Mark installation as complete
            session_start();
            $_SESSION['installer_first_user'] = true;
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Failed to retrieve Steam user information.";
    }
} else {
    $error = "Steam authentication failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Error - 420th Delta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .error-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .error-icon {
            font-size: 3rem;
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .back-btn {
            display: inline-block;
            background: #1e3c72;
            color: white;
            padding: 0.8rem 2rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #2a5298;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Authentication Error</h1>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <a href="index.php" class="back-btn">Back to Login</a>
    </div>
</body>
</html>
