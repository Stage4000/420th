<?php
// Main dashboard page

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'role_manager.php';
require_once 'ban_manager.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = SteamAuth::getCurrentUser();
$isPanelAdmin = SteamAuth::isPanelAdmin();
$db = Database::getInstance();
$roleManager = new RoleManager();
$banManager = new BanManager();

// Check if user is banned
$banInfo = $banManager->isUserBanned($user['id']);
$isBanned = $banInfo !== false;

// Refresh user roles from database (ensures roles are up-to-date after ban/unban)
$freshRoles = SteamAuth::getUserRoles($user['id']);
$_SESSION['roles'] = $freshRoles;
$user['roles'] = $freshRoles;

// Handle whitelist request
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'whitelist_me') {
        // Check if user is banned
        if ($isBanned) {
            $message = "You cannot whitelist yourself while banned. ";
            if ($banInfo['ban_expires']) {
                $message .= "Your ban expires on " . date('F j, Y g:i A', strtotime($banInfo['ban_expires'])) . ".";
            } else {
                $message .= "Your ban is indefinite.";
            }
            $messageType = "error";
        } else {
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
            text-align: center;
        }
        
        .welcome-card h1 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
        }
        
        .welcome-card p {
            color: #8b92a8;
        }
        
        .user-card-avatar {
            width: 240px;
            height: 240px;
            border-radius: 50%;
            border: 3px solid #667eea;
            margin: 0 auto 1.5rem;
            display: block;
        }
        
        .roles-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }
        
        .role-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 3rem;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal:focus {
            outline: none;
        }
        
        .modal-content {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid #2a3142;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #2a3142;
        }
        
        .modal-header h2 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
        }
        
        .modal-body {
            color: #8b92a8;
            line-height: 1.6;
        }
        
        .modal-body p {
            margin-bottom: 1rem;
        }
        
        .modal-body ul {
            list-style: none;
            padding-left: 0;
            margin: 1rem 0;
        }
        
        .modal-body ul li {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: rgba(102, 126, 234, 0.1);
            border-left: 3px solid #667eea;
            border-radius: 3px;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #2a3142;
        }
        
        .modal-btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #e4e6eb;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .modal-btn-accept {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .modal-btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
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
        
        .navbar-links {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            
            .navbar-links a, .navbar-links span, .navbar-links img {
                width: 100%;
                text-align: center;
            }
            
            /* Hide user avatar in navbar on mobile */
            .navbar-links .user-avatar {
                display: none;
            }
            
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .roles-grid {
                gap: 0.8rem !important;
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
                gap: 0.6rem !important;
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
            <span class="navbar-title">Dashboard</span>
        </div>
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
        <div class="navbar-links" id="navbarLinks">
            <?php if ($isPanelAdmin): ?>
                <a href="admin.php" style="color: #e4e6eb; text-decoration: none;">Admin Panel</a>
                <a href="users.php" style="color: #e4e6eb; text-decoration: none;">Users</a>
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
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-card-avatar">
            <h1>Welcome, <?php echo htmlspecialchars($user['steam_name']); ?>!</h1>
            <p style="margin-bottom: 1.5rem;">Steam ID: <?php echo htmlspecialchars($user['steam_id']); ?></p>
            
            <?php if (!empty($user['roles'])): ?>
                <div class="roles-grid" style="margin-top: 1.5rem;">
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
            <?php else: ?>
                <p style="color: #8b92a8; margin-top: 1rem; font-style: italic;">No roles assigned</p>
            <?php endif; ?>
        </div>
        
        <?php if ($isBanned): ?>
        <!-- Banned User Message -->
        <div class="whitelist-card" style="border: 2px solid #ff6b6b;">
            <h2 style="margin-bottom: 1rem; color: #ff6b6b;">🚫 Whitelist Banned</h2>
            <div style="background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.3); border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                <p style="color: #ff6b6b; margin-bottom: 0.5rem; font-weight: bold;">
                    You have been banned from using the whitelist system.
                </p>
                <?php if ($banInfo['ban_reason']): ?>
                <p style="color: #8b92a8; margin-bottom: 0.5rem;">
                    <strong>Reason:</strong> <?php echo htmlspecialchars($banInfo['ban_reason']); ?>
                </p>
                <?php endif; ?>
                <p style="color: #8b92a8; margin: 0;">
                    <?php if ($banInfo['ban_expires']): ?>
                        <strong>Expires:</strong> <?php echo date('F j, Y g:i A', strtotime($banInfo['ban_expires'])); ?>
                    <?php else: ?>
                        <strong>Duration:</strong> Indefinite
                    <?php endif; ?>
                </p>
            </div>
            <p style="color: #8b92a8; text-align: center;">
                You cannot whitelist yourself while banned.
            </p>
        </div>
        <?php elseif (!$isWhitelisted): ?>
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
            <form method="POST" id="whitelistForm">
                <input type="hidden" name="action" value="whitelist_me">
                <button type="button" class="whitelist-btn" id="whitelistBtn">🎯 Whitelist Me!</button>
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
            <div class="info-card" style="margin-top: 1.5rem;">
                <p style="margin: 0;">ℹ️ You may need to reconnect to the game server for whitelist changes to take effect.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div>© 2026 <a href="https://420thdelta.net" target="_blank">420th Delta Gaming Community</a></div>
        <div>Made with ❤️ by <a href="https://sitecritter.com" target="_blank">SiteCritter</a></div>
    </footer>
    
    <!-- Whitelist Agreement Modal -->
    <div id="agreementModal" class="modal" role="dialog" aria-labelledby="modalTitle" aria-describedby="modalDescription" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Whitelist Rules Agreement</h2>
                <p id="modalDescription" style="color: #8b92a8; margin: 0;">Please read and accept the following rules before proceeding</p>
            </div>
            <div class="modal-body">
                <p><strong>By requesting whitelist, you agree to the following:</strong></p>
                <ul>
                    <li>
                        <strong>Pilot Communication</strong> - All pilots are expected to communicate in-game via text or voice. You may be asked to switch role if unable to communicate.
                    </li>
                    <li>
                        <strong>Waiting For Passengers</strong> - Transport Helicopters should wait in an orderly fashion on the side of the yellow barriers opposite from spawn, leaving the traffic lane clear for infantry and vehicles.
                    </li>
                    <li>
                        <strong>No CAS on Kavala</strong> - All Close Air Support is forbidden to engage the Priority Mission Kavala. This mission is meant to be close-quarters combat. CAS can ruin the mission if they destroy buildings containing intel. Contact an in-game Zeus or use the vote-kick feature to enforce this rule as needed.
                    </li>
                </ul>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" id="modalCancelBtn">
                    Cancel
                </button>
                <button type="button" class="modal-btn modal-btn-accept" id="modalAcceptBtn">
                    I Agree
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleMobileMenu() {
            const navbarLinks = document.getElementById('navbarLinks');
            navbarLinks.classList.toggle('active');
        }
        
        function showAgreementModal() {
            const modal = document.getElementById('agreementModal');
            modal.classList.add('active');
            // Focus the modal for accessibility
            modal.focus();
            // Add ESC key handler when modal opens
            document.addEventListener('keydown', handleEscKey);
        }
        
        function closeAgreementModal() {
            const modal = document.getElementById('agreementModal');
            modal.classList.remove('active');
            // Remove ESC key handler when modal closes
            document.removeEventListener('keydown', handleEscKey);
            // Return focus to the whitelist button
            const whitelistBtn = document.getElementById('whitelistBtn');
            if (whitelistBtn) {
                whitelistBtn.focus();
            }
        }
        
        function handleEscKey(event) {
            if (event.key === 'Escape') {
                closeAgreementModal();
            }
        }
        
        function acceptAgreement() {
            // Close the modal
            closeAgreementModal();
            // Submit the form
            document.getElementById('whitelistForm').submit();
        }
        
        // Set up event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Whitelist button click handler
            const whitelistBtn = document.getElementById('whitelistBtn');
            if (whitelistBtn) {
                whitelistBtn.addEventListener('click', showAgreementModal);
            }
            
            // Modal cancel button
            const modalCancelBtn = document.getElementById('modalCancelBtn');
            if (modalCancelBtn) {
                modalCancelBtn.addEventListener('click', closeAgreementModal);
            }
            
            // Modal accept button
            const modalAcceptBtn = document.getElementById('modalAcceptBtn');
            if (modalAcceptBtn) {
                modalAcceptBtn.addEventListener('click', acceptAgreement);
            }
            
            // Close modal when clicking outside of it
            const modal = document.getElementById('agreementModal');
            if (modal) {
                modal.addEventListener('click', function(event) {
                    if (event.target === modal && modal.classList.contains('active')) {
                        closeAgreementModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
