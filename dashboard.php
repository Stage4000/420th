<?php
// Main dashboard page

require_once 'steam_auth.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = SteamAuth::getCurrentUser();
$isPanelAdmin = SteamAuth::isPanelAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - 420th Delta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .welcome-card h1 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        
        .welcome-card p {
            color: #666;
        }
        
        .roles-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .roles-card h2 {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .role-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .role-badge.staff {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .role-badge.admin {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .role-badge.panel {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        .no-roles {
            text-align: center;
            padding: 2rem;
            color: #999;
        }
        
        .no-roles-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .admin-panel-link {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
            transition: transform 0.2s;
            font-weight: 500;
        }
        
        .admin-panel-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .info-card {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .info-card p {
            color: #1565c0;
            margin: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">420th Delta Dashboard</div>
        <div class="navbar-user">
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
            <span><?php echo htmlspecialchars($user['steam_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h1>Welcome, <?php echo htmlspecialchars($user['steam_name']); ?>!</h1>
            <p>Steam ID: <?php echo htmlspecialchars($user['steam_id']); ?></p>
        </div>
        
        <div class="roles-card">
            <h2>Your Whitelist Roles</h2>
            
            <?php if (empty($user['roles'])): ?>
                <div class="no-roles">
                    <div class="no-roles-icon">🔒</div>
                    <p><strong>No roles assigned</strong></p>
                    <p>You currently don't have any whitelist roles assigned. Please contact an administrator if you believe this is an error.</p>
                </div>
            <?php else: ?>
                <div class="roles-grid">
                    <?php foreach ($user['roles'] as $role): ?>
                        <?php
                        $badgeClass = '';
                        if ($role['name'] === 'ALL') {
                            $badgeClass = 'staff';
                        } elseif (in_array($role['name'], ['ADMIN', 'MODERATOR', 'DEVELOPER'])) {
                            $badgeClass = 'admin';
                        } elseif ($role['name'] === 'PANEL') {
                            $badgeClass = 'panel';
                        }
                        ?>
                        <div class="role-badge <?php echo $badgeClass; ?>">
                            <?php echo htmlspecialchars($role['display_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($isPanelAdmin): ?>
                    <div class="info-card">
                        <p><strong>Panel Administrator Access:</strong> You have administrative privileges to manage users and roles.</p>
                    </div>
                    <a href="admin.php" class="admin-panel-link">🔧 Access Admin Panel</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
