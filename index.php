<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>420th Delta - Login</title>
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
        }
        
        .login-container {
            background: #1a1f2e;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            text-align: center;
            max-width: 400px;
            width: 90%;
            border: 1px solid #2a3142;
        }
        
        .logo-container {
            margin-bottom: 2rem;
        }
        
        .logo {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        
        .subtitle {
            color: #8b92a8;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .steam-login-btn {
            display: inline-block;
            background: #171a21;
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1rem;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #2a3142;
        }
        
        .steam-login-btn:hover {
            background: #2a475e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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
            color: #8b92a8;
            font-size: 0.9rem;
        }
        
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1a1f2e;
            color: #8b92a8;
            padding: 1rem 2rem;
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
        <div class="logo-container">
            <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
                 alt="420th Delta" 
                 class="logo"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'font-size: 2rem; font-weight: bold; color: #fff; margin-bottom: 1rem;\'>420th Delta</div>';">
        </div>
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
    
    <footer>
        <div>© 2026 <a href="https://420thdelta.net" target="_blank">420th Delta Gaming Community</a></div>
        <div>Made with ❤️ by <a href="https://sitecritter.com" target="_blank">SiteCritter</a></div>
    </footer>
</body>
</html>
