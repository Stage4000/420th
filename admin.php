<?php
// Admin panel for managing users and roles

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'role_manager.php';

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
$user = SteamAuth::getCurrentUser();

// Get all available roles and cache them for alias lookups
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");

// Handle role assignment/removal and alias updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $roleName = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';
        
        if ($_POST['action'] === 'add_role' && $userId && $roleName) {
            // Add role to user using RoleManager (handles automatic ALL role)
            try {
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
</body>
</html>
