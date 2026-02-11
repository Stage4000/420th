<?php
// Ban management page - Display and manage all bans

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'ban_manager.php';
require_once 'staff_notes_manager.php';

// Check if user is logged in and has admin or moderator role
if (!SteamAuth::isLoggedIn()) {
    header('Location: index');
    exit;
}

// Allow both PANEL and ALL (staff) to view bans
if (!SteamAuth::hasRole('PANEL') && !SteamAuth::hasRole('ALL')) {
    header('Location: dashboard');
    exit;
}

$db = Database::getInstance();
$banManager = new BanManager();
$staffNotesManager = new StaffNotesManager();
$currentUser = SteamAuth::getCurrentUser();

// Get all available roles for alias lookups
$allRoles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$roleMetadata = [];
foreach ($allRoles as $role) {
    $roleMetadata[$role['name']] = $role['alias'] ?: $role['display_name'];
}

$message = '';
$messageType = '';

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : 'all'; // 'all', 'active', 'expired'

// Handle unban action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'unban' && isset($_POST['user_id'])) {
        try {
            $userId = intval($_POST['user_id']);
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
            
            $result = $banManager->unbanUser($userId, $currentUser['id'], $reason);
            
            $message = implode('. ', $result['messages']);
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error unbanning user: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get bans based on filter
if ($filterStatus === 'expired') {
    // Query for expired bans
    $offset = ($page - 1) * $perPage;
    $whereClause = "WHERE wb.is_active = 0";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (banned_user.steam_name LIKE ? OR banned_user.steam_id LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM whitelist_bans wb
                   JOIN users banned_user ON wb.user_id = banned_user.id
                   $whereClause";
    $totalResult = $db->fetchOne($countQuery, $params);
    $total = $totalResult['total'];
    
    // Get bans
    $query = "SELECT wb.*, 
                     banned_user.steam_name as banned_user_name,
                     banned_user.steam_id as banned_user_steam_id,
                     banned_user.avatar_url as banned_user_avatar,
                     banned_by.steam_name as banned_by_name,
                     unbanned_by.steam_name as unbanned_by_name
              FROM whitelist_bans wb
              JOIN users banned_user ON wb.user_id = banned_user.id
              JOIN users banned_by ON wb.banned_by_user_id = banned_by.id
              LEFT JOIN users unbanned_by ON wb.unbanned_by_user_id = unbanned_by.id
              $whereClause
              ORDER BY wb.ban_date DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $bans = $db->fetchAll($query, $params);
} elseif ($filterStatus === 'active') {
    // Use existing getAllBans method for active bans
    $result = $banManager->getAllBans($page, $perPage, $search);
    $bans = $result['bans'];
    $total = $result['total'];
} else {
    // Get all bans (both active and expired)
    $offset = ($page - 1) * $perPage;
    $params = [];
    
    // Build WHERE clause based on search criteria
    if (!empty($search)) {
        $whereClause = "WHERE (banned_user.steam_name LIKE ? OR banned_user.steam_id LIKE ?)";
        $params = ["%$search%", "%$search%"];
    } else {
        $whereClause = "";
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM whitelist_bans wb
                   JOIN users banned_user ON wb.user_id = banned_user.id
                   $whereClause";
    $totalResult = $db->fetchOne($countQuery, $params);
    $total = $totalResult['total'];
    
    // Get bans
    $query = "SELECT wb.*, 
                     banned_user.steam_name as banned_user_name,
                     banned_user.steam_id as banned_user_steam_id,
                     banned_user.avatar_url as banned_user_avatar,
                     banned_user.id as user_id,
                     banned_by.steam_name as banned_by_name,
                     unbanned_by.steam_name as unbanned_by_name
              FROM whitelist_bans wb
              JOIN users banned_user ON wb.user_id = banned_user.id
              JOIN users banned_by ON wb.banned_by_user_id = banned_by.id
              LEFT JOIN users unbanned_by ON wb.unbanned_by_user_id = unbanned_by.id
              $whereClause
              ORDER BY wb.ban_date DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $bans = $db->fetchAll($query, $params);
}

// Enrich ban data with notes count
foreach ($bans as &$ban) {
    if (isset($ban['user_id'])) {
        $ban['note_count'] = $staffNotesManager->countUserNotes($ban['user_id']);
    }
}

$totalPages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ban Management - 420th Delta</title>
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
            display: flex;
            flex-direction: column;
        }
        
        <?php include 'navbar_styles.php'; ?>
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
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
        
        .page-header {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            border: 1px solid #2a3142;
            text-align: center;
        }
        
        .page-header h1 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #8b92a8;
        }
        
        .filters-card {
            background: #1a1f2e;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            border: 1px solid #2a3142;
        }
        
        .controls {
            background: #1a1f2e;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #2a3142;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem;
            background: #0f1318;
            border: 1px solid #2a3142;
            border-radius: 5px;
            color: #e4e6eb;
            font-size: 1rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-select {
            padding: 0.75rem;
            background: #1a1f2e;
            border: 1px solid #2a3142;
            border-radius: 5px;
            color: #e4e6eb;
            font-size: 1rem;
            cursor: pointer;
        }
        
        .filter-select option {
            background: #1a1f2e;
            color: #e4e6eb;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .bans-table {
            background: #1a1f2e;
            border-radius: 10px;
            border: 1px solid #2a3142;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #2a3142;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .table-header h2 {
            color: #667eea;
            font-weight: 600;
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: rgba(102, 126, 234, 0.1);
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            border-bottom: 1px solid #2a3142;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #667eea;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .badge-active {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .badge-expired {
            background: rgba(149, 165, 166, 0.2);
            color: #95a5a6;
            border: 1px solid #95a5a6;
        }
        
        .badge-info {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .ban-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .ban-type-s3 {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .ban-type-cas {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
            border: 1px solid #f1c40f;
        }
        
        .ban-type-whitelist,
        .ban-type-both {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn-unban {
            background: #27ae60;
            color: white;
        }
        
        .btn-unban:hover {
            background: #229954;
        }
        
        .btn-disabled {
            background: #7f8c8d;
            color: #bdc3c7;
            cursor: not-allowed;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 1.5rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #2a3142;
            border-radius: 5px;
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }
        
        .pagination .current {
            background: rgba(102, 126, 234, 0.2);
            border-color: #667eea;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8b92a8;
        }
        
        footer {
            background: #1a1f2e;
            color: #8b92a8;
            padding: 1.5rem 2rem;
            margin-top: auto;
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
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .bans-table {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 800px;
            }
            
            footer {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
                padding: 1rem;
                font-size: 0.8rem;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'ban_management';
    $pageTitle = 'Ban Management';
    $user = $currentUser;
    $isPanelAdmin = SteamAuth::isPanelAdmin();
    $canViewBans = $isPanelAdmin || SteamAuth::hasRole('ALL');
    $canViewActivePlayers = SteamAuth::hasRole('ADMIN');
    ?>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🚫 Ban Management</h1>
            <p>View and manage all player bans across the platform</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="filters-card">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 250px;">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="🔍 Search by player name or Steam ID..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        style="width: 100%; padding: 0.75rem; background: #0f1318; border: 1px solid #2a3142; border-radius: 5px; color: #e4e6eb; font-size: 1rem;"
                    >
                </div>
                <select 
                    name="status" 
                    style="padding: 0.75rem; background: #0f1318; color: #e4e6eb; border: 1px solid #2a3142; border-radius: 5px; font-size: 1rem; cursor: pointer;"
                >
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Bans</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="expired" <?php echo $filterStatus === 'expired' ? 'selected' : ''; ?>>Expired Only</option>
                </select>
                <button type="submit" style="padding: 0.75rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                    Search
                </button>
                <?php if (!empty($search) || $filterStatus !== 'all'): ?>
                    <a href="ban_management" style="padding: 0.75rem 1.5rem; background: #2a3142; color: #e4e6eb; border-radius: 5px; text-decoration: none; font-weight: 600; white-space: nowrap;">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="bans-table">
            <div class="table-header">
                <h2>All Bans (<?php echo $total; ?>)</h2>
                <span style="color: #8b92a8;">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
            </div>
        
        <?php if (empty($bans)): ?>
                <div class="empty-state">
                    <h3>No bans found</h3>
                    <p>There are no bans matching your criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Banned By</th>
                            <th>Ban Date</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bans as $ban): ?>
                            <?php
                            $isActive = $ban['is_active'] && (empty($ban['ban_expires']) || strtotime($ban['ban_expires']) > time());
                            $hasExpired = !$ban['is_active'] || (!empty($ban['ban_expires']) && strtotime($ban['ban_expires']) <= time());
                            ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <?php if (!empty($ban['banned_user_avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($ban['banned_user_avatar']); ?>" 
                                                 alt="Avatar" class="user-avatar">
                                        <?php endif; ?>
                                        <div>
                                            <div><?php echo htmlspecialchars($ban['banned_user_name']); ?></div>
                                            <small style="color: #888;"><?php echo htmlspecialchars($ban['banned_user_steam_id']); ?></small>
                                            <?php if (isset($ban['note_count']) && $ban['note_count'] > 0): ?>
                                                <span class="badge badge-info" title="<?php echo $ban['note_count']; ?> staff note(s)">
                                                    📝 <?php echo $ban['note_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $displayType = $ban['ban_type'];
                                    if ($ban['ban_type'] === 'S3' && isset($roleMetadata['S3'])) {
                                        $displayType = $roleMetadata['S3'];
                                    } elseif ($ban['ban_type'] === 'CAS' && isset($roleMetadata['CAS'])) {
                                        $displayType = $roleMetadata['CAS'];
                                    }
                                    ?>
                                    <span class="ban-type ban-type-<?php echo strtolower($ban['ban_type']); ?>">
                                        <?php echo htmlspecialchars($displayType); ?>
                                    </span>
                                    <?php if (!empty($ban['server_kick'])): ?>
                                        <span class="badge badge-info" title="Server kick">⚠️</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ban['server_ban'])): ?>
                                        <span class="badge badge-info" title="Server ban">🚫</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" 
                                         title="<?php echo htmlspecialchars($ban['ban_reason']); ?>">
                                        <?php echo htmlspecialchars($ban['ban_reason'] ?: 'No reason provided'); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($ban['banned_by_name']); ?></td>
                                <td>
                                    <small><?php echo date('Y-m-d H:i', strtotime($ban['ban_date'])); ?></small>
                                </td>
                                <td>
                                    <?php if (empty($ban['ban_expires'])): ?>
                                        <span style="color: #e74c3c;">Never</span>
                                    <?php else: ?>
                                        <small><?php echo date('Y-m-d H:i', strtotime($ban['ban_expires'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-expired">Expired</span>
                                        <?php if (!empty($ban['unbanned_by_name'])): ?>
                                            <br><small style="color: #888;">By: <?php echo htmlspecialchars($ban['unbanned_by_name']); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="user_id" value="<?php echo $ban['user_id']; ?>">
                                            <button type="submit" class="btn btn-unban" 
                                                    onclick="return confirm('Are you sure you want to unban this user?')">
                                                Unban
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-disabled" disabled>Already Unbanned</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($search); ?>">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($search); ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div>© 2026 <a href="https://420thdelta.net" target="_blank">420th Delta Gaming Community</a></div>
        <div>Made with ❤️ by <a href="https://sitecritter.com" target="_blank">SiteCritter</a></div>
    </footer>
</body>
</html>
