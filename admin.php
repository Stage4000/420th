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

// Get all users with their roles (from boolean columns)
$users = $db->fetchAll("
    SELECT u.*,
           CONCAT_WS(', ',
               IF(u.role_s3, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='S3'), NULL),
               IF(u.role_cas, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='CAS'), NULL),
               IF(u.role_s1, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='S1'), NULL),
               IF(u.role_opfor, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='OPFOR'), NULL),
               IF(u.role_all, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='ALL'), NULL),
               IF(u.role_admin, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='ADMIN'), NULL),
               IF(u.role_moderator, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='MODERATOR'), NULL),
               IF(u.role_trusted, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='TRUSTED'), NULL),
               IF(u.role_media, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='MEDIA'), NULL),
               IF(u.role_curator, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='CURATOR'), NULL),
               IF(u.role_developer, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='DEVELOPER'), NULL),
               IF(u.role_panel, (SELECT COALESCE(alias, display_name) FROM roles WHERE name='PANEL'), NULL)
           ) as roles
    FROM users u
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
            color: white;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
                 alt="Logo" 
                 class="navbar-logo"
                 onerror="this.style.display='none';">
            <span>Admin Panel</span>
        </div>
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
        
        <!--User Management Link -->
        <div class="users-table" style="margin-bottom: 2rem;">
            <div class="table-header">
                <h2>User Management</h2>
                <p style="margin-top: 0.5rem; color: #8b92a8; font-weight: normal;">Manage whitelist roles for all users</p>
            </div>
            <div style="padding: 2rem; text-align: center;">
                <a href="users.php" class="btn btn-primary" style="display: inline-block; padding: 1rem 2rem; font-size: 1.1rem; text-decoration: none;">
                    👥 Manage Users & Roles
                </a>
                <p style="color: #8b92a8; margin-top: 1rem;">View, search, and manage roles for all users with pagination</p>
            </div>
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
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #e4e6eb;">
                                <?php echo htmlspecialchars($role['name']); ?>
                                <small style="font-weight: normal; color: #8b92a8;">(<?php echo htmlspecialchars($role['display_name']); ?>)</small>
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
                        <?php foreach ($users as $u): 
                            // Build a list of role names from boolean columns
                            $userRoleNames = [];
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
                                if (!empty($u[$column])) {
                                    $userRoleNames[] = $roleName;
                                }
                            }
                            $userRolesData = implode(',', $userRoleNames);
                        ?>
                            <tr data-user-id="<?php echo $u['id']; ?>" data-user-roles="<?php echo htmlspecialchars($userRolesData); ?>">
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
                                            $roles = array_filter(explode(', ', $u['roles']));
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
                                    <button class="btn btn-primary" onclick="openRoleModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['steam_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($userRolesData, ENT_QUOTES); ?>')">
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
                                <br><small style="color: #8b92a8;"><?php echo htmlspecialchars($role['description']); ?></small>
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
                    currentRoles.add(checkbox.getAttribute('data-role-name'));
                }
            });
            
            // Find roles to add and remove
            const toAdd = [...currentRoles].filter(r => !originalRoles.has(r));
            const toRemove = [...originalRoles].filter(r => !currentRoles.has(r));
            
            // Submit each change
            let completed = 0;
            const total = toAdd.length + toRemove.length;
            
            if (total === 0) {
                closeModal();
                return;
            }
            
            // Add roles
            toAdd.forEach(roleName => {
                submitRoleChange('add_role', currentUserId, roleName, () => {
                    completed++;
                    if (completed === total) {
                        location.reload();
                    }
                });
            });
            
            // Remove roles
            toRemove.forEach(roleName => {
                submitRoleChange('remove_role', currentUserId, roleName, () => {
                    completed++;
                    if (completed === total) {
                        location.reload();
                    }
                });
            });
        }
        
        function submitRoleChange(action, userId, roleName, callback) {
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
            roleInput.name = 'role_name';
            roleInput.value = roleName;
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
    </script>
</body>
</html>
