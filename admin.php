<?php
// Admin panel for managing users and roles

require_once 'steam_auth.php';
require_once 'db.php';

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
$user = SteamAuth::getCurrentUser();

// Handle role assignment/removal and alias updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $roleId = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
        
        if ($_POST['action'] === 'add_role' && $userId && $roleId) {
            // Add role to user
            try {
                $db->execute(
                    "INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)",
                    [$userId, $roleId, $user['id']]
                );
                $message = "Role added successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error adding role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'remove_role' && $userId && $roleId) {
            // Remove role from user
            try {
                $db->execute(
                    "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
                    [$userId, $roleId]
                );
                $message = "Role removed successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error removing role: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'update_alias' && $roleId) {
            // Update role alias
            $alias = trim($_POST['alias'] ?? '');
            try {
                $db->execute(
                    "UPDATE roles SET alias = ? WHERE id = ?",
                    [$alias, $roleId]
                );
                $message = "Role alias updated successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error updating alias: " . $e->getMessage();
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

// Get all users with their roles
$users = $db->fetchAll("
    SELECT u.*, 
           GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles,
           GROUP_CONCAT(CONCAT(r.id, ':', r.name) SEPARATOR '||') as role_details
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    GROUP BY u.id
    ORDER BY u.last_login DESC
");

// Get all available roles
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - 420th Delta</title>
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
        
        .navbar-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .navbar-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .navbar-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .header-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .header-card h1 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .message.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table-header h2 {
            color: #1e3c72;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
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
            background: white;
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
            color: #1e3c72;
        }
        
        .role-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }
        
        .role-checkbox:hover {
            background: #f8f9fa;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">420th Delta - Admin Panel</div>
        <div class="navbar-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header-card">
            <h1>User Management</h1>
            <p>Manage whitelist roles for all users</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Role Aliases Management -->
        <div class="users-table" style="margin-bottom: 2rem;">
            <div class="table-header">
                <h2>Role Aliases</h2>
                <p style="margin-top: 0.5rem; color: #666; font-weight: normal;">Customize display names for roles. Leave blank to use default display name.</p>
            </div>
            <div style="padding: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($allRoles as $role): ?>
                        <form method="POST" style="border: 1px solid #e0e0e0; padding: 1rem; border-radius: 5px;">
                            <input type="hidden" name="action" value="update_alias">
                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #1e3c72;">
                                <?php echo htmlspecialchars($role['name']); ?>
                                <small style="font-weight: normal; color: #666;">(<?php echo htmlspecialchars($role['display_name']); ?>)</small>
                            </label>
                            <input 
                                type="text" 
                                name="alias" 
                                value="<?php echo htmlspecialchars($role['alias'] ?? ''); ?>" 
                                placeholder="Enter custom alias..."
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 0.5rem;"
                            >
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.5rem;">
                                Update Alias
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="users-table">
            <div class="table-header">
                <h2>All Users</h2>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <p><strong>No users yet</strong></p>
                    <p>Users will appear here after they log in for the first time.</p>
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
                                        <span><?php echo htmlspecialchars($u['steam_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['steam_id']); ?></td>
                                <td>
                                    <?php if ($u['roles']): ?>
                                        <div class="roles-list">
                                            <?php
                                            $roles = explode(', ', $u['roles']);
                                            foreach ($roles as $role):
                                            ?>
                                                <span class="role-tag"><?php echo htmlspecialchars($role); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">No roles</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($u['last_login'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="openManageRoles(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['steam_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['role_details'], ENT_QUOTES); ?>')">
                                        Manage Roles
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Manage Roles Modal -->
    <div id="manageRolesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Roles</h2>
                <p id="modalUserName"></p>
            </div>
            
            <div id="rolesContainer">
                <?php foreach ($allRoles as $role): ?>
                    <label class="role-checkbox">
                        <input 
                            type="checkbox" 
                            class="role-check" 
                            data-role-id="<?php echo $role['id']; ?>"
                            data-role-name="<?php echo htmlspecialchars($role['name']); ?>"
                        >
                        <div>
                            <strong><?php echo htmlspecialchars($role['display_name']); ?></strong>
                            <?php if ($role['description']): ?>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($role['description']); ?></small>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="saveRoles()">Save Changes</button>
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentUserId = null;
        let originalRoles = new Set();
        
        function openManageRoles(userId, userName, roleDetails) {
            currentUserId = userId;
            document.getElementById('modalUserName').textContent = userName;
            
            // Parse current roles
            originalRoles = new Set();
            if (roleDetails) {
                const roles = roleDetails.split('||');
                roles.forEach(role => {
                    const [roleId, roleName] = role.split(':');
                    if (roleId) {
                        originalRoles.add(roleId);
                    }
                });
            }
            
            // Update checkboxes
            document.querySelectorAll('.role-check').forEach(checkbox => {
                const roleId = checkbox.getAttribute('data-role-id');
                checkbox.checked = originalRoles.has(roleId);
            });
            
            document.getElementById('manageRolesModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('manageRolesModal').classList.remove('active');
        }
        
        function saveRoles() {
            const checkboxes = document.querySelectorAll('.role-check');
            const currentRoles = new Set();
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    currentRoles.add(checkbox.getAttribute('data-role-id'));
                }
            });
            
            // Find roles to add and remove
            const toAdd = [...currentRoles].filter(r => !originalRoles.has(r));
            const toRemove = [...originalRoles].filter(r => !currentRoles.has(r));
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Add roles
            toAdd.forEach(roleId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'add_role[]';
                input.value = roleId;
                form.appendChild(input);
            });
            
            // Remove roles
            toRemove.forEach(roleId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_role[]';
                input.value = roleId;
                form.appendChild(input);
            });
            
            // Add user ID
            const userInput = document.createElement('input');
            userInput.type = 'hidden';
            userInput.name = 'user_id';
            userInput.value = currentUserId;
            form.appendChild(userInput);
            
            // Add action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_update';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            
            // Submit each change
            let completed = 0;
            const total = toAdd.length + toRemove.length;
            
            if (total === 0) {
                closeModal();
                return;
            }
            
            // Add roles
            toAdd.forEach(roleId => {
                submitRoleChange('add_role', currentUserId, roleId, () => {
                    completed++;
                    if (completed === total) {
                        location.reload();
                    }
                });
            });
            
            // Remove roles
            toRemove.forEach(roleId => {
                submitRoleChange('remove_role', currentUserId, roleId, () => {
                    completed++;
                    if (completed === total) {
                        location.reload();
                    }
                });
            });
        }
        
        function submitRoleChange(action, userId, roleId, callback) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);
            
            const userInput = document.createElement('input');
            userInput.name = 'user_id';
            userInput.value = userId;
            form.appendChild(userInput);
            
            const roleInput = document.createElement('input');
            roleInput.name = 'role_id';
            roleInput.value = roleId;
            form.appendChild(roleInput);
            
            document.body.appendChild(form);
            
            const formData = new FormData(form);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                document.body.removeChild(form);
                callback();
            });
        }
        
        // Close modal on outside click
        document.getElementById('manageRolesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
