<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>420th Delta - Login</title>
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
        
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
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
            cursor: pointer;
        }
        
        .steam-login-btn:hover {
            background: #333;
        }
        
        .steam-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .description {
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php
    // Check if installation is needed
    if (!file_exists('config.php')) {
        header('Location: install.php');
        exit;
    }
    
    require_once 'steam_auth.php';
    
    // Redirect if already logged in
    if (SteamAuth::isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
    
    $loginUrl = SteamAuth::getLoginUrl();
    ?>
    
    <div class="login-container">
        <div class="logo">420th Delta</div>
        <div class="subtitle">Whitelist Dashboard</div>
        
        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="steam-login-btn">
            <svg class="steam-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white">
                <path d="M12 2a10 10 0 0 1 10 10 10 10 0 0 1-10 10c-4.6 0-8.45-3.08-9.64-7.27l3.83 1.58a2.84 2.84 0 0 0 2.78 2.27c1.56 0 2.83-1.27 2.83-2.83v-.13l3.4-2.43h.08c2.08 0 3.77-1.69 3.77-3.77s-1.69-3.77-3.77-3.77-3.78 1.69-3.78 3.77v.05l-2.37 3.46-.16-.01c-.31 0-.61.04-.89.13L2.15 11.1A10 10 0 0 1 12 2m4.25 5.43c1.38 0 2.51 1.12 2.51 2.51 0 1.38-1.13 2.51-2.51 2.51s-2.51-1.13-2.51-2.51c0-1.39 1.13-2.51 2.51-2.51m-8.46 9.31l-.89-.37c.19.58.76 1.04 1.47 1.04.83 0 1.51-.68 1.51-1.51s-.68-1.51-1.51-1.51h-.08l.96-.7c.03.1.05.2.05.31 0 1.04-.84 1.88-1.88 1.88-.37 0-.72-.11-1.01-.3z"/>
            </svg>
            Sign in with Steam
        </a>
        
        <div class="description">
            Sign in with your Steam account to view your whitelist roles and access the dashboard.
        </div>
    </div>
</body>
</html>
