<?php
// User management page with search and pagination

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'role_manager.php';
require_once 'ban_manager.php';

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
$banManager = new BanManager();
$currentUser = SteamAuth::getCurrentUser();

// Check if current user has ALL flag (can manage bans) and PANEL flag (can manage roles)
$hasAllFlag = SteamAuth::hasRole('ALL');
$canManageRoles = SteamAuth::hasRole('PANEL');

// Handle role assignment/removal and ban management
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $roleName = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Only PANEL admins can manage roles
        if ($_POST['action'] === 'add_role' && $userId && $roleName && $canManageRoles) {
            try {
                $roleManager->addRole($userId, $roleName);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Role added successfully']);
                    exit;
                }
                $message = "Role added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error adding role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'remove_role' && $userId && $roleName && $canManageRoles) {
            try {
                $roleManager->removeRole($userId, $roleName);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Role removed successfully']);
                    exit;
                }
                $message = "Role removed successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error removing role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'ban_user' && $userId && $hasAllFlag) {
            // Handle ban action (ALL flag holders can ban)
            try {
                $banReason = isset($_POST['ban_reason']) ? trim($_POST['ban_reason']) : '';
                $banDuration = isset($_POST['ban_duration']) ? trim($_POST['ban_duration']) : 'indefinite';
                $banType = isset($_POST['ban_type']) ? trim($_POST['ban_type']) : 'BOTH';
                $banExpires = null;
                
                // Validate ban type
                if (!in_array($banType, ['S3', 'CAS', 'BOTH'])) {
                    throw new Exception("Invalid ban type");
                }
                
                if ($banDuration !== 'indefinite') {
                    $hours = intval($banDuration);
                    if ($hours > 0) {
                        $banExpires = date('Y-m-d H:i:s', strtotime("+$hours hours"));
                    }
                }
                
                $banManager->banUser($userId, $currentUser['id'], $banType, $banReason, $banExpires);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'User banned successfully']);
                    exit;
                }
                $message = "User banned successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error banning user: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'unban_user' && $userId && $hasAllFlag) {
            // Handle unban action
            try {
                $unbanReason = isset($_POST['unban_reason']) ? trim($_POST['unban_reason']) : '';
                $banManager->unbanUser($userId, $currentUser['id'], $unbanReason);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
                    exit;
                }
                $message = "User unbanned successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error unbanning user: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Pagination and search
$perPage = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = " WHERE u.steam_name LIKE ? OR u.steam_id LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Get total count
$countQuery = "SELECT COUNT(DISTINCT u.id) as total FROM users u" . $whereClause;
$totalResult = $db->fetchOne($countQuery, $params);
$totalUsers = $totalResult['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get all available roles and cache them for alias lookups
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");

// Create a map of role names to their aliases/display names for efficient lookup
$roleMetadata = [];
foreach ($allRoles as $role) {
    $roleMetadata[$role['name']] = $role['alias'] ?: $role['display_name'];
}

// Get users with pagination and ban information
$users = $db->fetchAll("
    SELECT u.*,
           wb.id as ban_id,
           wb.ban_reason,
           wb.ban_date,
           wb.ban_expires,
           wb.is_active as is_banned
    FROM users u
    LEFT JOIN whitelist_bans wb ON u.id = wb.user_id AND wb.is_active = 1
    $whereClause
    ORDER BY u.last_login DESC
    LIMIT ? OFFSET ?
", array_merge($params, [$perPage, $offset]));

// Add formatted roles to each user
foreach ($users as &$user) {
    $roleNames = [];
    $roleColumnMap = [
        'role_s3' => 'S3',
        'role_cas' => 'CAS',
        'role_s1' => 'S1',
        'role_opfor' => 'OPFOR',
        'role_all' => 'ALL',
        'role_admin' => 'ADMIN',
        'role_moderator' => 'MODERATOR',
        'role_trusted' => 'TRUSTED',
        'role_media' => 'MEDIA',
        'role_curator' => 'CURATOR',
        'role_developer' => 'DEVELOPER',
        'role_panel' => 'PANEL',
    ];
    foreach ($roleColumnMap as $column => $roleName) {
        if (!empty($user[$column])) {
            $roleNames[] = $roleMetadata[$roleName];
        }
    }
    $user['roles'] = implode(', ', $roleNames);
    
    // Check if ban is expired (additional safety check)
    if ($user['is_banned'] && $user['ban_expires'] && strtotime($user['ban_expires']) < time()) {
        $user['is_banned'] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
        
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .message.success {
            background: #1e3a2e;
            border-color: #48bb78;
            color: #9ae6b4;
        }
        
        .message.error {
            background: #3a1e1e;
            border-color: #f56565;
            color: #fc8181;
        }
        
        .search-bar {
            background: #1a1f2e;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #2a3142;
            background: #0f1318;
            color: #e4e6eb;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
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
        
        .btn-secondary {
            background: #2a3142;
            color: #e4e6eb;
        }
        
        .btn-secondary:hover {
            background: #3a4152;
        }
        
        .users-table {
            background: #1a1f2e;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 2px solid #2a3142;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            border-bottom: 1px solid #2a3142;
        }
        
        th {
            font-weight: 600;
            color: #e4e6eb;
        }
        
        td {
            color: #8b92a8;
        }
        
        tr:hover {
            background: #1e2837;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 0.5rem;
            background: #667eea;
            color: white;
        }
        
        .roles-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .actions button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem;
            background: #1a1f2e;
            margin-top: 2rem;
            border-radius: 10px;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            color: #e4e6eb;
            background: #2a3142;
            transition: background 0.2s;
        }
        
        .pagination a:hover {
            background: #3a4152;
        }
        
        .pagination .active {
            background: #667eea;
            font-weight: bold;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #8b92a8;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
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
        
        .modal-header h3 {
            color: #e4e6eb;
        }
        
        .role-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .role-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #0f1318;
            border-radius: 5px;
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
        
        /* Tooltip Styles for Role Modal */
        .info-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .info-icon .tooltip {
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
            z-index: 10000;
            font-weight: normal;
        }
        
        .info-icon:hover .tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-12px);
        }
        
        /* Tooltip arrow */
        .info-icon .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #1a1f2e;
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
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 0.5rem;
                white-space: nowrap;
            }
            
            /* Make tables horizontally scrollable on mobile */
            .users-table {
                overflow-x: auto;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-box input {
                width: 100% !important;
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
            
            /* Adjust modal for small screens */
            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
            
            /* Hide actions column on very small screens */
            table th:last-child,
            table td:last-child {
                min-width: 100px;
            }
        }
        
        /* Animation keyframes for info messages */
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
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
            <span class="navbar-title">User Management</span>
        </div>
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
        <div class="navbar-links" id="navbarLinks">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin.php">Admin Panel</a>
            <img src="<?php echo htmlspecialchars($currentUser['avatar_url']); ?>" alt="Avatar" class="user-avatar">
            <span><?php echo htmlspecialchars($currentUser['steam_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" class="search-form">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search by Steam name or Steam ID..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <button type="submit" class="btn btn-primary">🔍 Search</button>
                <?php if (!empty($search)): ?>
                    <a href="users.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <div class="table-header">
                <h2>All Users (<?php echo $totalUsers; ?>)</h2>
                <span style="color: #8b92a8;">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <p><strong>No users found</strong></p>
                    <?php if (!empty($search)): ?>
                        <p>Try a different search term.</p>
                    <?php else: ?>
                        <p>Users will appear here after they log in for the first time.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Steam ID</th>
                            <th>Roles</th>
                            <?php if ($hasAllFlag): ?>
                            <th>Ban Status</th>
                            <?php endif; ?>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($u['avatar_url']); ?>" alt="Avatar" class="user-avatar">
                                        <strong style="color: #e4e6eb;"><?php echo htmlspecialchars($u['steam_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['steam_id']); ?></td>
                                <td>
                                    <?php if ($u['roles']): ?>
                                        <div class="roles-list">
                                            <?php
                                            $roles = array_filter(explode(', ', $u['roles']));
                                            foreach ($roles as $role):
                                            ?>
                                                <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #8b92a8;">No roles</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($hasAllFlag): ?>
                                <td>
                                    <?php if ($u['is_banned']): ?>
                                        <div style="color: #ff6b6b;">
                                            <strong>🚫 BANNED</strong><br>
                                            <small style="color: #8b92a8;">
                                                <?php if ($u['ban_expires']): ?>
                                                    Expires: <?php echo date('M j, Y g:i A', strtotime($u['ban_expires'])); ?>
                                                <?php else: ?>
                                                    Indefinite
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #51cf66;">✓ Active</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($u['last_login']); ?></td>
                                <td>
                                    <?php if ($canManageRoles): ?>
                                        <button onclick="openModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['steam_name'])); ?>')" 
                                                class="btn btn-primary btn-small">
                                            Manage Roles
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($hasAllFlag && !$u['role_all']): // Can't ban users with ALL flag ?>
                                        <?php if ($u['is_banned']): ?>
                                            <button onclick="openUnbanModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['steam_name'])); ?>')" 
                                                    class="btn btn-success btn-small" style="background: #51cf66; margin-top: 0.5rem;">
                                                Unban
                                            </button>
                                        <?php else: ?>
                                            <button onclick="openBanModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['steam_name'])); ?>')" 
                                                    class="btn btn-danger btn-small" style="background: #ff6b6b; margin-top: 0.5rem;">
                                                Ban
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">« Previous</a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Role Management Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Roles for <span id="modalUserName"></span></h3>
            </div>
            <div id="modalRoleList" class="role-list">
                <!-- Populated by JavaScript -->
            </div>
            <button onclick="closeModal()" class="btn btn-secondary" style="width: 100%;">Close</button>
        </div>
    </div>
    
    <!-- Ban Modal -->
    <div id="banModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ban User: <span id="banModalUserName"></span></h3>
            </div>
            <form id="banForm">
                <input type="hidden" id="banUserId" name="user_id">
                <input type="hidden" name="action" value="ban_user">
                
                <div style="margin-bottom: 1rem;">
                    <label style="color: #e4e6eb; display: block; margin-bottom: 0.5rem;">Ban Type:</label>
                    <select name="ban_type" id="banTypeSelect" style="width: 100%; padding: 0.75rem; background: #2a3142; border: 1px solid #3a4152; border-radius: 5px; color: #e4e6eb;">
                        <option value="BOTH">Both <?php echo htmlspecialchars($roleMetadata['S3'] ?? 'S3'); ?> and <?php echo htmlspecialchars($roleMetadata['CAS'] ?? 'CAS'); ?></option>
                        <option value="S3"><?php echo htmlspecialchars($roleMetadata['S3'] ?? 'S3'); ?> Only</option>
                        <option value="CAS"><?php echo htmlspecialchars($roleMetadata['CAS'] ?? 'CAS'); ?> Only</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="color: #e4e6eb; display: block; margin-bottom: 0.5rem;">Ban Duration:</label>
                    <select name="ban_duration" style="width: 100%; padding: 0.75rem; background: #2a3142; border: 1px solid #3a4152; border-radius: 5px; color: #e4e6eb;">
                        <option value="24">24 Hours</option>
                        <option value="48">48 Hours</option>
                        <option value="72">72 Hours (3 Days)</option>
                        <option value="168">1 Week</option>
                        <option value="336">2 Weeks</option>
                        <option value="720">30 Days</option>
                        <option value="indefinite" selected>Indefinite</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="color: #e4e6eb; display: block; margin-bottom: 0.5rem;">Ban Reason:</label>
                    <textarea name="ban_reason" rows="3" style="width: 100%; padding: 0.75rem; background: #2a3142; border: 1px solid #3a4152; border-radius: 5px; color: #e4e6eb; resize: vertical;" placeholder="Enter reason for ban..."></textarea>
                </div>
                
                <div id="banWarningMessage" style="color: #ff6b6b; margin-bottom: 1rem; padding: 0.75rem; background: rgba(255, 107, 107, 0.1); border-radius: 5px; border: 1px solid rgba(255, 107, 107, 0.3);">
                    <strong>⚠️ Warning:</strong> <span id="banWarningText">This will remove <?php echo htmlspecialchars($roleMetadata['S3'] ?? 'S3'); ?> and <?php echo htmlspecialchars($roleMetadata['CAS'] ?? 'CAS'); ?> roles and prevent the user from using "Whitelist Me!" button until the ban expires.</span>
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-danger" style="flex: 1; background: #ff6b6b;">
                        Issue Ban
                    </button>
                    <button type="button" onclick="closeBanModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Unban Modal -->
    <div id="unbanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Unban User: <span id="unbanModalUserName"></span></h3>
            </div>
            <form id="unbanForm">
                <input type="hidden" id="unbanUserId" name="user_id">
                <input type="hidden" name="action" value="unban_user">
                
                <div style="margin-bottom: 1rem;">
                    <label style="color: #e4e6eb; display: block; margin-bottom: 0.5rem;">Unban Reason (Optional):</label>
                    <textarea name="unban_reason" rows="3" style="width: 100%; padding: 0.75rem; background: #2a3142; border: 1px solid #3a4152; border-radius: 5px; color: #e4e6eb; resize: vertical;" placeholder="Enter reason for unban..."></textarea>
                </div>
                
                <div style="color: #51cf66; margin-bottom: 1rem; padding: 0.75rem; background: rgba(81, 207, 102, 0.1); border-radius: 5px; border: 1px solid rgba(81, 207, 102, 0.3);">
                    <strong>✓ Note:</strong> This will allow the user to whitelist themselves again using the "Whitelist Me!" button.
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-success" style="flex: 1; background: #51cf66;">
                        Remove Ban
                    </button>
                    <button type="button" onclick="closeUnbanModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentUserId = null;
        const allRoles = <?php echo json_encode($allRoles); ?>;
        const usersData = <?php echo json_encode($users); ?>;
        const roleAliases = <?php echo json_encode($roleMetadata); ?>;
        
        // Reusable toast notification function
        function showToast(message, type = 'info') {
            const colors = {
                'info': '#3a7ca5',
                'success': '#51cf66',
                'error': '#ff6b6b',
                'warning': '#ffa94d'
            };
            const icons = {
                'info': 'ℹ️',
                'success': '✓',
                'error': '✗',
                'warning': '⚠️'
            };
            
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type] || colors.info};
                color: #e4e6eb;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                z-index: 10000;
                max-width: 400px;
                animation: slideIn 0.3s ease-out;
            `;
            messageDiv.innerHTML = `<strong>${icons[type] || icons.info} ${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}`;
            document.body.appendChild(messageDiv);
            
            // Remove message after 5 seconds
            setTimeout(() => {
                messageDiv.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => messageDiv.remove(), 300);
            }, 5000);
        }
        
        // Centralized role column mapping to avoid duplication
        const ROLE_COLUMN_MAP = {
            'S3': 'role_s3',
            'CAS': 'role_cas',
            'S1': 'role_s1',
            'OPFOR': 'role_opfor',
            'ALL': 'role_all',
            'ADMIN': 'role_admin',
            'MODERATOR': 'role_moderator',
            'TRUSTED': 'role_trusted',
            'MEDIA': 'role_media',
            'CURATOR': 'role_curator',
            'DEVELOPER': 'role_developer',
            'PANEL': 'role_panel',
        };
        
        const STAFF_ROLES = ['ADMIN', 'MODERATOR', 'DEVELOPER', 'CURATOR'];
        
        function openModal(userId, userName) {
            currentUserId = userId;
            document.getElementById('modalUserName').textContent = userName;
            
            const user = usersData.find(u => u.id == userId);
            
            const roleList = document.getElementById('modalRoleList');
            roleList.innerHTML = '';
            
            allRoles.forEach(role => {
                const column = ROLE_COLUMN_MAP[role.name];
                const hasRole = user && column && user[column] == 1;
                const roleItem = document.createElement('div');
                roleItem.className = 'role-item';
                // Use alias if available, otherwise fall back to display_name
                const displayName = role.alias || role.display_name;
                const description = role.description || 'No description available';
                roleItem.innerHTML = `
                    <span style="color: #e4e6eb; display: flex; align-items: center; gap: 0.5rem;">
                        ${displayName}
                        <span class="info-icon" style="cursor: help; color: #667eea; font-size: 1rem;">ℹ️
                            <span class="tooltip">${description}</span>
                        </span>
                    </span>
                    <button 
                        class="btn btn-${hasRole ? 'secondary' : 'primary'} btn-small"
                        onclick="toggleRole(event, ${userId}, '${role.name}', ${hasRole})"
                    >
                        ${hasRole ? 'Remove' : 'Add'}
                    </button>
                `;
                roleList.appendChild(roleItem);
            });
            
            document.getElementById('roleModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('roleModal').classList.remove('active');
            // Refresh page to update role badges
            window.location.reload();
        }
        
        function toggleRole(event, userId, roleName, hasRole) {
            // Check for ADMIN/MODERATOR mutual exclusivity and show info message
            let mutualExclusivityMessage = null;
            if (!hasRole && (roleName === 'ADMIN' || roleName === 'MODERATOR')) {
                const user = usersData.find(u => u.id == userId);
                const otherRole = roleName === 'ADMIN' ? 'MODERATOR' : 'ADMIN';
                const otherRoleColumn = ROLE_COLUMN_MAP[otherRole];
                
                if (user && user[otherRoleColumn] == 1) {
                    const otherRoleAlias = roleAliases[otherRole] || otherRole;
                    const adminAlias = roleAliases['ADMIN'] || 'admin';
                    const modAlias = roleAliases['MODERATOR'] || 'mod';
                    
                    // Prepare message to show after successful update
                    mutualExclusivityMessage = `A user may only be ${adminAlias.toLowerCase()} or ${modAlias.toLowerCase()}. ${otherRoleAlias} was removed.`;
                }
            }
            
            // Use AJAX to avoid page refresh and keep modal open
            const formData = new FormData();
            formData.append('action', hasRole ? 'remove_role' : 'add_role');
            formData.append('user_id', userId);
            formData.append('role_name', roleName);
            
            // Disable the button while processing
            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = '...';
            
            fetch('users.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Unknown error');
                }
                
                // Update the user data in memory to match server state
                const user = usersData.find(u => u.id == userId);
                const column = ROLE_COLUMN_MAP[roleName];
                if (user && column) {
                    // Toggle the requested role
                    user[column] = hasRole ? 0 : 1;
                    
                    // Handle ADMIN/MODERATOR mutual exclusivity
                    if (roleName === 'ADMIN' && !hasRole) {
                        user.role_moderator = 0;  // Remove MODERATOR when adding ADMIN
                    } else if (roleName === 'MODERATOR' && !hasRole) {
                        user.role_admin = 0;  // Remove ADMIN when adding MODERATOR
                    }
                    
                    // Server handles automatic linking, but we need to sync our local state
                    // Staff roles (ADMIN, MOD, DEV, CURATOR) automatically get ALL role
                    if (STAFF_ROLES.includes(roleName) && !hasRole) {
                        user.role_all = 1;
                    } else if (roleName === 'ALL' && hasRole) {
                        // Removing ALL removes all staff roles
                        user.role_admin = 0;
                        user.role_moderator = 0;
                        user.role_developer = 0;
                        user.role_curator = 0;
                    } else if (STAFF_ROLES.includes(roleName) && hasRole) {
                        // Check if user still has other staff roles
                        if (!user.role_admin && !user.role_moderator && !user.role_developer && !user.role_curator) {
                            user.role_all = 0;
                        }
                    }
                }
                
                // Refresh the modal to show updated roles
                const userName = document.getElementById('modalUserName').textContent;
                openModal(userId, userName);
                
                // Show mutual exclusivity message if applicable
                if (mutualExclusivityMessage) {
                    showToast(mutualExclusivityMessage, 'info');
                }
            })
            .catch(error => {
                console.error('Error toggling role:', error);
                showToast('Error updating role: ' + error.message + '. Please try again.', 'error');
                button.disabled = false;
                button.textContent = originalText;
            });
        }
        
        // Close modal on outside click
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Ban/Unban Modal Functions
        function openBanModal(userId, userName) {
            document.getElementById('banModalUserName').textContent = userName;
            document.getElementById('banUserId').value = userId;
            document.getElementById('banModal').classList.add('active');
            updateBanWarning(); // Update warning on open
        }
        
        function closeBanModal() {
            document.getElementById('banModal').classList.remove('active');
            document.getElementById('banForm').reset();
        }
        
        // Update ban warning message based on selected ban type
        function updateBanWarning() {
            const banType = document.getElementById('banTypeSelect').value;
            const warningText = document.getElementById('banWarningText');
            const s3Alias = <?php echo json_encode($roleMetadata['S3'] ?? 'S3'); ?>;
            const casAlias = <?php echo json_encode($roleMetadata['CAS'] ?? 'CAS'); ?>;
            
            let message = '';
            if (banType === 'BOTH') {
                message = `This will remove ${s3Alias} and ${casAlias} roles and prevent the user from using "Whitelist Me!" button until the ban expires.`;
            } else if (banType === 'S3') {
                message = `This will remove the ${s3Alias} role and prevent the user from requesting ${s3Alias} whitelist until the ban expires.`;
            } else if (banType === 'CAS') {
                message = `This will remove the ${casAlias} role and prevent the user from requesting ${casAlias} whitelist until the ban expires.`;
            }
            
            warningText.textContent = message;
        }
        
        // Listen for ban type changes
        document.getElementById('banTypeSelect').addEventListener('change', updateBanWarning);
        
        function openUnbanModal(userId, userName) {
            document.getElementById('unbanModalUserName').textContent = userName;
            document.getElementById('unbanUserId').value = userId;
            document.getElementById('unbanModal').classList.add('active');
        }
        
        function closeUnbanModal() {
            document.getElementById('unbanModal').classList.remove('active');
            document.getElementById('unbanForm').reset();
        }
        
        // Handle ban form submission
        document.getElementById('banForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1500); // Reload after showing message
                } else {
                    showToast(data.error || 'Failed to ban user', 'error');
                }
            } catch (error) {
                console.error('Error banning user:', error);
                showToast('Failed to ban user', 'error');
            }
        });
        
        // Handle unban form submission
        document.getElementById('unbanForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1500); // Reload after showing message
                } else {
                    showToast(data.error || 'Failed to unban user', 'error');
                }
            } catch (error) {
                console.error('Error unbanning user:', error);
                showToast('Failed to unban user', 'error');
            }
        });
        
        // Mobile menu toggle function
        function toggleMobileMenu() {
            const navbarLinks = document.getElementById('navbarLinks');
            navbarLinks.classList.toggle('active');
        }
    </script>
</body>
</html>
