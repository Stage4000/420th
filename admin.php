<?php
// Admin panel for managing users and roles

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'role_manager.php';
require_once 'rcon_manager.php';
require_once 'html_sanitizer.php';

// Check if user is logged in and is a panel admin
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (!SteamAuth::isPanelAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$roleManager = new RoleManager();
$rconManager = new RconManager();
$user = SteamAuth::getCurrentUser();

// Get all available roles and cache them for alias lookups
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");

// Get current RCON settings
$rconSettings = $rconManager->getSettings();

// Get whitelist agreement setting
// Create server_settings table if it doesn't exist
try {
    $whitelistAgreementSetting = $db->fetchOne("SELECT setting_value FROM server_settings WHERE setting_key = 'whitelist_agreement'");
    $whitelistAgreement = $whitelistAgreementSetting ? $whitelistAgreementSetting['setting_value'] : '';
} catch (PDOException $e) {
    // If server_settings table doesn't exist (SQLSTATE 42S02), create it
    if (Database::isTableNotFoundError($e)) {
        $db->createServerSettingsTable();
        // Retry fetching the setting after creating the table
        $whitelistAgreementSetting = $db->fetchOne("SELECT setting_value FROM server_settings WHERE setting_key = 'whitelist_agreement'");
        $whitelistAgreement = $whitelistAgreementSetting ? $whitelistAgreementSetting['setting_value'] : '';
    } else {
        // Re-throw other database errors
        throw $e;
    }
}

// Handle role assignment/removal and alias updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $roleName = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';
        
        if ($_POST['action'] === 'add_role' && $userId && $roleName) {
            // Add role to user using RoleManager (handles automatic ALL role)
            try {
                // Check if target user is the owner
                $targetUser = $db->fetchOne("SELECT steam_id FROM users WHERE id = ?", [$userId]);
                $isOwner = defined('OWNER_STEAM_ID') && !empty(OWNER_STEAM_ID) && 
                          $targetUser && $targetUser['steam_id'] === OWNER_STEAM_ID;
                
                // If target is owner, only the owner themselves can modify their roles
                if ($isOwner && $user['steam_id'] !== OWNER_STEAM_ID) {
                    throw new Exception("Only the owner can modify the owner's roles");
                }
                
                $roleManager->addRole($userId, $roleName);
                $message = "Role added successfully! (Staff roles automatically get ALL role)";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'remove_role' && $userId && $roleName) {
            // Remove role from user using RoleManager
            try {
                // Check if target user is the owner
                $targetUser = $db->fetchOne("SELECT steam_id FROM users WHERE id = ?", [$userId]);
                $isOwner = defined('OWNER_STEAM_ID') && !empty(OWNER_STEAM_ID) && 
                          $targetUser && $targetUser['steam_id'] === OWNER_STEAM_ID;
                
                // If target is owner, only the owner themselves can modify their roles
                if ($isOwner && $user['steam_id'] !== OWNER_STEAM_ID) {
                    throw new Exception("Only the owner can modify the owner's roles");
                }
                
                $roleManager->removeRole($userId, $roleName);
                $message = "Role removed successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error removing role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'update_aliases') {
            // Update all role aliases at once
            try {
                $db->getConnection()->beginTransaction();
                
                $updated = 0;
                foreach ($allRoles as $role) {
                    if (isset($_POST['alias_' . $role['id']])) {
                        $alias = trim($_POST['alias_' . $role['id']]);
                        $db->execute(
                            "UPDATE roles SET alias = ? WHERE id = ?",
                            [$alias, $role['id']]
                        );
                        $updated++;
                    }
                }
                
                $db->getConnection()->commit();
                $message = "Successfully updated $updated role aliases!";
                $messageType = "success";
            } catch (Exception $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->getConnection()->rollBack();
                }
                $message = "Error updating aliases: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'sync_staff_roles') {
            // Sync staff roles (add ALL role to all staff)
            try {
                $fixed = $roleManager->syncStaffRoles();
                $message = "Staff roles synced! Added ALL role to $fixed user(s).";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error syncing staff roles: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'update_rcon_settings') {
            // Update RCON settings
            try {
                $settings = [
                    'rcon_enabled' => isset($_POST['rcon_enabled']) ? '1' : '0',
                    'rcon_host' => trim($_POST['rcon_host']),
                    'rcon_port' => intval($_POST['rcon_port']),
                ];
                
                // Only update password if provided
                if (!empty($_POST['rcon_password'])) {
                    $settings['rcon_password'] = trim($_POST['rcon_password']);
                }
                
                $rconManager->updateSettings($settings, $user['id']);
                
                // Reload settings
                $rconSettings = $rconManager->getSettings();
                
                $message = "RCON settings updated successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error updating RCON settings: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'test_rcon_connection') {
            // Test RCON connection
            try {
                $result = $rconManager->testConnection();
                if ($result['success']) {
                    $message = "RCON connection successful! Player count: " . $result['player_count'];
                    $messageType = "success";
                } else {
                    $message = "RCON connection failed: " . $result['message'];
                    $messageType = "error";
                }
            } catch (Exception $e) {
                $message = "Error testing RCON connection: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'search_steam_id') {
            // Search for user by Steam ID
            $searchSteamId = trim($_POST['steam_id']);
            if (!empty($searchSteamId)) {
                $searchUser = $db->fetchOne("SELECT * FROM users WHERE steam_id = ?", [$searchSteamId]);
                if (!$searchUser) {
                    // User doesn't exist, create placeholder
                    $message = "User not found. They will be added to the database on their first login.";
                    $messageType = "info";
                }
            }
        } elseif ($_POST['action'] === 'update_whitelist_agreement') {
            // Update whitelist agreement
            try {
                $agreementText = $_POST['whitelist_agreement'] ?? '';
                
                // Sanitize HTML to allow only safe formatting tags
                $sanitizedAgreement = HtmlSanitizer::sanitize($agreementText);
                
                $db->execute(
                    "INSERT INTO server_settings (setting_key, setting_value, updated_by_user_id) 
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     setting_value = VALUES(setting_value),
                     updated_by_user_id = VALUES(updated_by_user_id)",
                    ['whitelist_agreement', $sanitizedAgreement, $user['id']]
                );
                
                // Reload the setting
                $whitelistAgreementSetting = $db->fetchOne("SELECT setting_value FROM server_settings WHERE setting_key = 'whitelist_agreement'");
                $whitelistAgreement = $whitelistAgreementSetting ? $whitelistAgreementSetting['setting_value'] : '';
                
                $message = "Whitelist agreement updated successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error updating whitelist agreement: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - 420th Delta</title>
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
        
        .navbar-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .navbar-links a {
            color: #e4e6eb;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        
        .navbar-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .header-card {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .header-card h1 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
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
        
        .message.info {
            background: #1e2f3a;
            border: 1px solid #2d4a5a;
            color: #90cdf4;
        }
        
        .info-card {
            background: #1e2837;
            border-left: 4px solid #4299e1;
            padding: 1.5rem;
            border-radius: 5px;
            color: #e4e6eb;
        }
        
        .info-card strong {
            color: #90cdf4;
        }
        
        .users-table {
            background: #1a1f2e;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* Allow tooltips to show in role alias section */
        .users-table:has(#aliasForm) {
            overflow: visible;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 2px solid #2a3142;
        }
        
        .table-header h2 {
            color: #e4e6eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #1e2837;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            font-weight: 600;
            color: #e4e6eb;
        }
        
        tr:hover {
            background: #1e2837;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .roles-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .role-tag {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            color: #e4e6eb;
        }
        
        .role-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 1px solid #2a3142;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }
        
        .role-checkbox:hover {
            background: #1e2837;
        }
        
        .role-checkbox input {
            cursor: pointer;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
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
        html, body, .modal-content, .users-table {
            scrollbar-width: thin;
            scrollbar-color: #667eea #0f1318;
        }
        
        /* Tooltip Styles */
        .info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 50%;
            cursor: help;
            font-size: 0.875rem;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .info-icon:hover {
            background: rgba(102, 126, 234, 0.4);
            transform: scale(1.1);
        }
        
        .info-icon::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            background: #1a1f2e;
            color: #e4e6eb;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            font-size: 0.875rem;
            line-height: 1.4;
            white-space: normal;
            width: max-content;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid #2a3142;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 9999;
            font-weight: normal;
        }
        
        .info-icon:hover::after,
        .info-icon:active::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-12px);
        }
        
        /* Tooltip arrow */
        .info-icon::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(4px);
            border: 6px solid transparent;
            border-top-color: #1a1f2e;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 10000;
        }
        
        .info-icon:hover::before,
        .info-icon:active::before {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .navbar-links {
                display: none;
                width: 100%;
                flex-direction: column;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #2a3142;
            }
            
            .navbar-links.active {
                display: flex;
            }
            
            .navbar-links a {
                width: 100%;
                text-align: center;
            }
            
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
            
            /* Make tables horizontally scrollable on mobile */
            .users-table {
                overflow-x: auto;
            }
            
            /* Stack form fields vertically on mobile */
            form > div[style*="grid"] {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 1.25rem;
            }
            
            .navbar-logo {
                height: 30px;
            }
            
            .user-avatar {
                display: none;
            }
            
            .header-card h1 {
                font-size: 1.5rem;
            }
            
            /* Hide less important table columns on very small screens */
            table th:nth-child(3),
            table td:nth-child(3) {
                display: none;
            }
        }
        
        footer {
            background: #1a1f2e;
            color: #8b92a8;
            padding: 1.5rem 2rem;
            margin-top: 3rem;
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
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
                 alt="420th Delta" 
                 class="navbar-logo"
                 onerror="this.style.display='none';">
            <span class="navbar-title">Admin Panel</span>
        </div>
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
        <div class="navbar-links" id="navbarLinks">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="ban_management.php">Bans</a>
            <?php if (SteamAuth::hasRole('ADMIN')): ?>
                <a href="active_players.php">Active Players</a>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
            <span><?php echo htmlspecialchars($user['steam_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header-card">
            <h1>Settings</h1>
            <p style="color: #e4e6eb;">Configure role aliases and automatic role linking</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Role Linking Info -->
        <div class="info-card" style="margin-bottom: 2rem;">
            <p style="margin-bottom: 0.5rem;"><strong>ℹ️ Automatic Role Linking:</strong> Staff roles (ADMIN, MODERATOR, DEVELOPER) automatically receive the ALL role when assigned. Removing the ALL role will also remove all staff roles.</p>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="sync_staff_roles">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    🔄 Sync Staff Roles (Fix Existing Users)
                </button>
            </form>
        </div>
        
        <!-- RCON Configuration -->
        <div class="users-table" style="margin-bottom: 2rem;">
            <div class="table-header">
                <h2>🎮 Arma 3 Server RCON Configuration</h2>
                <p style="margin-top: 0.5rem; color: #8b92a8; font-weight: normal;">Configure RCON to enable server kicks and bans from the admin panel</p>
            </div>
            <form method="POST" style="padding: 2rem;">
                <input type="hidden" name="action" value="update_rcon_settings">
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="border: 1px solid #2a3142; padding: 1.5rem; border-radius: 5px; background: #0f1318;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; margin-bottom: 1rem; color: #e4e6eb;">
                            <input 
                                type="checkbox" 
                                name="rcon_enabled" 
                                value="1" 
                                <?php echo $rconSettings['rcon_enabled'] ? 'checked' : ''; ?>
                                style="width: 20px; height: 20px; cursor: pointer;"
                            >
                            <span>Enable RCON</span>
                        </label>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                                RCON Host/IP:
                            </label>
                            <input 
                                type="text" 
                                name="rcon_host" 
                                value="<?php echo htmlspecialchars($rconSettings['rcon_host'] ?? ''); ?>" 
                                placeholder="127.0.0.1 or server.example.com"
                                style="width: 100%; padding: 0.75rem; border: 1px solid #2a3142; background: #1a1f2e; color: #e4e6eb; border-radius: 3px;"
                            >
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                                RCON Port:
                            </label>
                            <input 
                                type="number" 
                                name="rcon_port" 
                                value="<?php echo htmlspecialchars($rconSettings['rcon_port'] ?? '2306'); ?>" 
                                placeholder="2306"
                                min="1"
                                max="65535"
                                style="width: 100%; padding: 0.75rem; border: 1px solid #2a3142; background: #1a1f2e; color: #e4e6eb; border-radius: 3px;"
                            >
                            <small style="color: #8b92a8; display: block; margin-top: 0.25rem;">Default BattlEye RCON port is usually game port + 4</small>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                                RCON Password:
                            </label>
                            <input 
                                type="password" 
                                name="rcon_password" 
                                placeholder="<?php echo $rconSettings['rcon_password_set'] ? '••••••••••••••••' : 'Enter RCON password'; ?>"
                                style="width: 100%; padding: 0.75rem; border: 1px solid #2a3142; background: #1a1f2e; color: #e4e6eb; border-radius: 3px;"
                            >
                            <small style="color: #8b92a8; display: block; margin-top: 0.25rem;">
                                <?php if ($rconSettings['rcon_password_set']): ?>
                                    Leave blank to keep current password
                                <?php else: ?>
                                    Enter the RConPassword from your beserver.cfg file
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div style="background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3); padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <p style="color: #90cdf4; margin-bottom: 0.5rem;"><strong>ℹ️ Configuration Instructions:</strong></p>
                            <ol style="color: #8b92a8; margin-left: 1.5rem; line-height: 1.6;">
                                <li>Ensure BattlEye RCON is enabled in your beserver.cfg or beserver_x64.cfg</li>
                                <li>Set RConPort (typically game port + 4, e.g., 2306 if game port is 2302)</li>
                                <li>Set a strong RConPassword in the config file</li>
                                <li>Restart your Arma 3 server after making changes</li>
                            </ol>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.75rem;">
                                💾 Save RCON Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Test Connection Button (separate form) -->
            <?php if ($rconSettings['rcon_enabled']): ?>
            <form method="POST" style="padding: 0 2rem 2rem 2rem;">
                <input type="hidden" name="action" value="test_rcon_connection">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.75rem;">
                    🔌 Test RCON Connection
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- Whitelist Agreement Management -->
        <div class="users-table" style="margin-bottom: 2rem;">
            <div class="table-header">
                <h2>📋 Whitelist Agreement</h2>
                <p style="margin-top: 0.5rem; color: #8b92a8; font-weight: normal;">Customize the agreement text shown to users when they request whitelist access. HTML is supported.</p>
            </div>
            <form method="POST" id="agreementForm" style="padding: 2rem;">
                <input type="hidden" name="action" value="update_whitelist_agreement">
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                        Agreement Content:
                    </label>
                    <textarea 
                        name="whitelist_agreement" 
                        rows="15" 
                        style="width: 100%; padding: 1rem; border: 1px solid #2a3142; background: #0f1318; color: #e4e6eb; border-radius: 5px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; resize: vertical;"
                        placeholder="Enter the whitelist agreement HTML content..."
                    ><?php echo htmlspecialchars($whitelistAgreement); ?></textarea>
                    <small style="color: #8b92a8; display: block; margin-top: 0.5rem;">
                        💡 Tip: Allowed HTML tags: <?php echo HtmlSanitizer::getAllowedTagsList(); ?>. Script tags and event handlers are automatically removed for security.
                    </small>
                </div>
                
                <div style="background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3); padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                    <p style="color: #90cdf4; margin-bottom: 0.5rem;"><strong>ℹ️ Preview:</strong></p>
                    <div style="color: #e4e6eb; line-height: 1.6;" id="agreementPreview">
                        <?php echo HtmlSanitizer::sanitize($whitelistAgreement); ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem;">
                    💾 Save Whitelist Agreement
                </button>
            </form>
        </div>
        
        <!-- Role Aliases Management -->
        <div class="users-table" style="margin-bottom: 2rem;">
            <div class="table-header">
                <h2>Role Aliases</h2>
                <p style="margin-top: 0.5rem; color: #8b92a8; font-weight: normal;">Customize display names for roles. Leave blank to use default display name.</p>
            </div>
            <form method="POST" id="aliasForm" style="padding: 2rem;">
                <input type="hidden" name="action" value="update_aliases">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <?php foreach ($allRoles as $role): ?>
                        <div style="border: 1px solid #2a3142; padding: 1rem; border-radius: 5px;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                                <span>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                    <small style="font-weight: normal; color: #8b92a8;">(<?php echo htmlspecialchars($role['display_name']); ?>)</small>
                                </span>
                                <?php if (!empty($role['description'])): ?>
                                    <span class="info-icon" data-tooltip="<?php echo htmlspecialchars($role['description']); ?>">
                                        ℹ️
                                    </span>
                                <?php endif; ?>
                            </label>
                            <input 
                                type="text" 
                                name="alias_<?php echo $role['id']; ?>" 
                                value="<?php echo htmlspecialchars($role['alias'] ?? ''); ?>" 
                                placeholder="Enter custom alias..."
                                style="width: 100%; padding: 0.5rem; border: 1px solid #2a3142; background: #0f1318; color: #e4e6eb; border-radius: 3px;"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem;">
                    💾 Save All Aliases
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Simple HTML sanitizer for preview - allows only safe formatting tags
        function sanitizeHtml(html) {
            const allowedTags = ['p', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            
            // Use DOMParser to safely parse HTML without executing scripts
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Remove script tags
            const scripts = doc.querySelectorAll('script');
            scripts.forEach(script => script.remove());
            
            // Process all elements (convert to array to avoid live NodeList issues)
            const allElements = Array.from(doc.body.querySelectorAll('*'));
            allElements.forEach(el => {
                const tagName = el.tagName.toLowerCase();
                if (!allowedTags.includes(tagName)) {
                    // Replace disallowed tags with their text content
                    const textNode = doc.createTextNode(el.textContent);
                    el.parentNode.replaceChild(textNode, el);
                } else {
                    // Remove all event handler attributes from allowed tags
                    Array.from(el.attributes).forEach(attr => {
                        if (attr.name.startsWith('on')) {
                            el.removeAttribute(attr.name);
                        }
                    });
                }
            });
            
            return doc.body.innerHTML;
        }
        
        // Live preview for whitelist agreement
        const agreementTextarea = document.querySelector('textarea[name="whitelist_agreement"]');
        const agreementPreview = document.getElementById('agreementPreview');
        
        if (agreementTextarea && agreementPreview) {
            agreementTextarea.addEventListener('input', function() {
                // Sanitize first, then safely set innerHTML with sanitized content
                const sanitized = sanitizeHtml(this.value);
                agreementPreview.innerHTML = sanitized;
            });
        }
        
        // AJAX form submission for whitelist agreement
        document.getElementById('agreementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳ Saving...';
            
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                button.textContent = '✅ Saved!';
                button.style.background = '#48bb78';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.disabled = false;
                }, 2000);
            })
            .catch(error => {
                button.textContent = '❌ Error';
                button.style.background = '#f56565';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.disabled = false;
                }, 2000);
            });
        });
        
        // AJAX form submission for aliases to reduce page refresh
        document.getElementById('aliasForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳ Saving...';
            
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                button.textContent = '✅ Saved!';
                button.style.background = '#48bb78';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.disabled = false;
                }, 2000);
            })
            .catch(error => {
                button.textContent = '❌ Error';
                button.style.background = '#f56565';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.disabled = false;
                }, 2000);
            });
        });
        
        // Mobile menu toggle function
        function toggleMobileMenu() {
            const navbarLinks = document.getElementById('navbarLinks');
            navbarLinks.classList.toggle('active');
        }
    </script>
    
    <footer>
        <div>© 2026 <a href="https://420thdelta.net" target="_blank">420th Delta Gaming Community</a></div>
        <div>Made with ❤️ by <a href="https://sitecritter.com" target="_blank">SiteCritter</a></div>
    </footer>
</body>
</html>
