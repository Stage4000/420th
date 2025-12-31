<?php
// User management page with search and pagination

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

// Handle role assignment/removal
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $roleId = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
        
        if ($_POST['action'] === 'add_role' && $userId && $roleId) {
            try {
                $roleManager->addRole($userId, $roleId, $user['id']);
                $message = "Role added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'remove_role' && $userId && $roleId) {
            try {
                $roleManager->removeRole($userId, $roleId);
                $message = "Role removed successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error removing role: " . $e->getMessage();
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

// Get users with pagination
$users = $db->fetchAll("
    SELECT u.*, 
           GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles,
           GROUP_CONCAT(CONCAT(r.id, ':', r.name) SEPARATOR '||') as role_details
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    $whereClause
    GROUP BY u.id
    ORDER BY u.last_login DESC
    LIMIT ? OFFSET ?
", array_merge($params, [$perPage, $offset]));

// Get all available roles
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
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
            transition: background 0.2s;
        }
        
        .navbar-links a:hover {
            background: #2a3142;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
                 alt="Logo" 
                 class="navbar-logo"
                 onerror="this.style.display='none';">
            <span>User Management</span>
        </div>
        <div class="navbar-links">
            <a href="admin.php">Admin Home</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
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
                                    <?php 
                                    if (!empty($u['role_details'])) {
                                        $roleDetails = explode('||', $u['role_details']);
                                        foreach ($roleDetails as $detail) {
                                            list($roleId, $roleName) = explode(':', $detail);
                                            echo '<span class="role-badge">' . htmlspecialchars($roleName) . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #8b92a8;">No roles</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($u['last_login']); ?></td>
                                <td>
                                    <button onclick="openModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['steam_name'])); ?>')" 
                                            class="btn btn-primary btn-small">
                                        Manage Roles
                                    </button>
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
    
    <script>
        let currentUserId = null;
        const allRoles = <?php echo json_encode($allRoles); ?>;
        const usersData = <?php echo json_encode($users); ?>;
        
        function openModal(userId, userName) {
            currentUserId = userId;
            document.getElementById('modalUserName').textContent = userName;
            
            const user = usersData.find(u => u.id == userId);
            const userRoles = user.role_details ? user.role_details.split('||').map(d => d.split(':')[0]) : [];
            
            const roleList = document.getElementById('modalRoleList');
            roleList.innerHTML = '';
            
            allRoles.forEach(role => {
                const hasRole = userRoles.includes(role.id.toString());
                const roleItem = document.createElement('div');
                roleItem.className = 'role-item';
                roleItem.innerHTML = `
                    <span style="color: #e4e6eb;">${role.name}</span>
                    <button 
                        class="btn btn-${hasRole ? 'secondary' : 'primary'} btn-small"
                        onclick="toggleRole(${userId}, ${role.id}, ${hasRole})"
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
        }
        
        function toggleRole(userId, roleId, hasRole) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${hasRole ? 'remove_role' : 'add_role'}">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="role_id" value="${roleId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Close modal on outside click
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
