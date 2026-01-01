<?php
// Main dashboard page

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'role_manager.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = SteamAuth::getCurrentUser();
$isPanelAdmin = SteamAuth::isPanelAdmin();
$db = Database::getInstance();
$roleManager = new RoleManager();

// Handle whitelist request
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'whitelist_me') {
        try {
            // Use RoleManager to add roles (handles automatic linking)
            $roleManager->addRole($user['id'], 'S3');
            $roleManager->addRole($user['id'], 'CAS');
            
            // Refresh user roles in session
            $roles = SteamAuth::getUserRoles($user['id']);
            $_SESSION['roles'] = $roles;
            $user['roles'] = $roles;
            
            $message = "You have been successfully whitelisted!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error processing whitelist request: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Check if user has S3 and CAS roles
$hasS3 = false;
$hasCAS = false;
foreach ($user['roles'] as $role) {
    if ($role['name'] === 'S3') $hasS3 = true;
    if ($role['name'] === 'CAS') $hasCAS = true;
}
$isWhitelisted = $hasS3 && $hasCAS;
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
            background: #0a0e1a;
            min-height: 100vh;
            color: #e4e6eb;
        }
        
        .navbar {
            background: #1a1f2e;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2a3142;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
        }
        
        .navbar-title {
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
            border: 2px solid #4a5568;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            border: 1px solid #2a3142;
        }
        
        .welcome-card h1 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
        }
        
        .welcome-card p {
            color: #8b92a8;
        }
        
        .roles-card {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            border: 1px solid #2a3142;
        }
        
        .roles-card h2 {
            color: #e4e6eb;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2a3142;
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
            color: #8b92a8;
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .info-card {
            background: #1e2837;
            border-left: 4px solid #4299e1;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .info-card p {
            color: #90cdf4;
            margin: 0;
        }
        
        .whitelist-card {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid #2a3142;
        }
        
        .whitelist-card h2 {
            color: #e4e6eb;
        }
        
        .whitelist-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.2s;
            font-weight: 500;
        }
        
        .whitelist-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .whitelisted-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success {
            background: #1e3a28;
            border: 1px solid #2d5a3d;
            color: #68d391;
        }
        
        .message.error {
            background: #3a1e1e;
            border: 1px solid #5a2d2d;
            color: #fc8181;
        }
        
        /* Custom Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #0f1318;
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
        
        /* Firefox Scrollbar */
        html, body {
            scrollbar-width: thin;
            scrollbar-color: #667eea #0f1318;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: stretch;
                padding: 1rem;
            }
            
            .navbar-brand {
                justify-content: center;
                margin-bottom: 0.5rem;
            }
            
            .navbar-user {
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .roles-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-logo {
                height: 30px;
            }
            
            .navbar-title {
                font-size: 1.25rem;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
            }
            
            .welcome-card h1 {
                font-size: 1.5rem;
            }
            
            .roles-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)) !important;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
                 alt="420th Delta" 
                 class="navbar-logo"
                 onerror="this.style.display='none';">
            <span class="navbar-title">Dashboard</span>
        </div>
        <div class="navbar-user">
            <?php if ($isPanelAdmin): ?>
                <a href="admin.php" style="color: #e4e6eb; margin-right: 1rem; text-decoration: none;">Admin Panel</a>
                <a href="users.php" style="color: #e4e6eb; margin-right: 1rem; text-decoration: none;">Users</a>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
            <span><?php echo htmlspecialchars($user['steam_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="welcome-card">
            <h1>Welcome, <?php echo htmlspecialchars($user['steam_name']); ?>!</h1>
            <p>Steam ID: <?php echo htmlspecialchars($user['steam_id']); ?></p>
        </div>
        
        <?php if (!$isWhitelisted): ?>
        <div class="whitelist-card">
            <h2 style="margin-bottom: 1rem;">Quick Whitelist</h2>
            <p style="color: #8b92a8; margin-bottom: 1.5rem;">
                Click the button below to automatically add yourself to the 
                <?php 
                    // Get role aliases for display
                    $db = Database::getInstance();
                    $s3Role = $db->fetchOne("SELECT COALESCE(alias, display_name) as name FROM roles WHERE name = 'S3'");
                    $casRole = $db->fetchOne("SELECT COALESCE(alias, display_name) as name FROM roles WHERE name = 'CAS'");
                    echo htmlspecialchars($s3Role ? $s3Role['name'] : 'S3');
                ?> and 
                <?php echo htmlspecialchars($casRole ? $casRole['name'] : 'CAS'); ?> whitelist roles.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="whitelist_me">
                <button type="submit" class="whitelist-btn">🎯 Whitelist Me!</button>
            </form>
        </div>
        <?php else: ?>
        <div class="whitelist-card">
            <h2 style="margin-bottom: 1rem;">Whitelist Status</h2>
            <div class="whitelisted-badge">
                ✅ You are already whitelisted!
            </div>
            <p style="color: #8b92a8; margin-top: 1rem;">
                You have <?php 
                    $s3Alias = '';
                    $casAlias = '';
                    foreach ($user['roles'] as $role) {
                        if ($role['name'] === 'S3') $s3Alias = $role['alias'] ?: $role['display_name'];
                        if ($role['name'] === 'CAS') $casAlias = $role['alias'] ?: $role['display_name'];
                    }
                    echo htmlspecialchars($s3Alias) . ' and ' . htmlspecialchars($casAlias);
                ?> roles assigned.
            </p>
        </div>
        <?php endif; ?>
        
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
                        // Use alias if set, otherwise use display_name
                        $displayText = !empty($role['alias']) ? $role['alias'] : $role['display_name'];
                        ?>
                        <div class="role-badge <?php echo $badgeClass; ?>">
                            <?php echo htmlspecialchars($displayText); ?>
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
