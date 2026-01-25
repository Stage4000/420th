<?php
// Ban management page - Display and manage all bans

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'ban_manager.php';
require_once 'staff_notes_manager.php';

// Check if user is logged in and has admin or moderator role
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Allow both PANEL and ALL (staff) to view bans
if (!SteamAuth::hasRole('PANEL') && !SteamAuth::hasRole('ALL')) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$banManager = new BanManager();
$staffNotesManager = new StaffNotesManager();
$currentUser = SteamAuth::getCurrentUser();

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f35 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: #9b59b6;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s;
            border: 1px solid rgba(138, 43, 226, 0.2);
        }
        
        .nav-links a:hover {
            background: rgba(138, 43, 226, 0.2);
            border-color: #9b59b6;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        
        .message.success {
            background: rgba(46, 204, 113, 0.1);
            border-color: #2ecc71;
            color: #2ecc71;
        }
        
        .message.error {
            background: rgba(231, 76, 60, 0.1);
            border-color: #e74c3c;
            color: #e74c3c;
        }
        
        .controls {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 6px;
            color: #e0e0e0;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 6px;
            color: #e0e0e0;
            font-size: 14px;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #9b59b6;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #9b59b6;
        }
        
        .stat-label {
            font-size: 14px;
            color: #888;
            margin-top: 5px;
        }
        
        .bans-table {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: rgba(138, 43, 226, 0.2);
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #9b59b6;
            border-bottom: 1px solid rgba(138, 43, 226, 0.2);
            cursor: pointer;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(138, 43, 226, 0.1);
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
            border: 2px solid #9b59b6;
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
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 6px;
            color: #9b59b6;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: rgba(138, 43, 226, 0.2);
            border-color: #9b59b6;
        }
        
        .pagination .current {
            background: rgba(138, 43, 226, 0.3);
            border-color: #9b59b6;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-direction: column;
                width: 100%;
            }
            
            .nav-links a {
                text-align: center;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .stats {
                flex-direction: column;
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
    <div class="container">
        <div class="header">
            <h1>🚫 Ban Management</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <?php if (SteamAuth::hasRole('ADMIN')): ?>
                    <a href="active_players.php">Active Players</a>
                <?php endif; ?>
                <a href="users.php">Users</a>
                <?php if (SteamAuth::isPanelAdmin()): ?>
                    <a href="admin.php">Admin</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total Bans<?php echo $filterStatus !== 'all' ? ' (' . ucfirst($filterStatus) . ')' : ''; ?></div>
            </div>
        </div>
        
        <div class="controls">
            <div class="search-box">
                <form method="GET" action="">
                    <input type="text" name="search" placeholder="🔍 Search by player name or Steam ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                </form>
            </div>
            <form method="GET" action="">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Bans</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="expired" <?php echo $filterStatus === 'expired' ? 'selected' : ''; ?>>Expired Only</option>
                </select>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
            </form>
        </div>
        
        <div class="bans-table">
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
                                    <span class="ban-type ban-type-<?php echo strtolower($ban['ban_type']); ?>">
                                        <?php echo htmlspecialchars($ban['ban_type']); ?>
                                    </span>
                                    <?php if ($ban['server_kick']): ?>
                                        <span class="badge badge-info" title="Server kick">⚠️</span>
                                    <?php endif; ?>
                                    <?php if ($ban['server_ban']): ?>
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
</body>
</html>
