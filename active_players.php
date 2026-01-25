<?php
// Active players page - Shows online players from RCON server
// Only accessible to users with role_admin flag

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'rcon_manager.php';
require_once 'ban_manager.php';
require_once 'staff_notes_manager.php';

// Check if user is logged in and has admin role
if (!SteamAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (!SteamAuth::hasRole('ADMIN')) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$rconManager = new RconManager();
$banManager = new BanManager();
$staffNotesManager = new StaffNotesManager();
$currentUser = SteamAuth::getCurrentUser();

$message = '';
$messageType = '';
$players = [];
$rconEnabled = $rconManager->isEnabled();

// Handle AJAX requests for ban/kick/notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'kick_player' && isset($_POST['steam_id'])) {
            try {
                $steamId = trim($_POST['steam_id']);
                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Kicked by admin';
                
                $rconManager->kickPlayer($steamId, $reason);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Player kicked successfully']);
                    exit;
                }
                $message = "Player kicked successfully";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error kicking player: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'ban_player' && isset($_POST['steam_id'])) {
            try {
                $steamId = trim($_POST['steam_id']);
                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Banned by admin';
                $banType = isset($_POST['ban_type']) ? trim($_POST['ban_type']) : 'BOTH';
                $serverBan = isset($_POST['server_ban']) && $_POST['server_ban'] === '1';
                $banDuration = isset($_POST['ban_duration']) ? trim($_POST['ban_duration']) : 'indefinite';
                $banExpires = null;
                
                if ($banDuration !== 'indefinite') {
                    $hours = intval($banDuration);
                    if ($hours > 0) {
                        $banExpires = date('Y-m-d H:i:s', strtotime("+$hours hours"));
                    }
                }
                
                // Find or create user record
                $user = $db->fetchOne("SELECT * FROM users WHERE steam_id = ?", [$steamId]);
                if (!$user) {
                    // Create a placeholder user record
                    $db->query(
                        "INSERT INTO users (steam_id, steam_name, created_at) VALUES (?, ?, NOW())",
                        [$steamId, 'Unknown Player']
                    );
                    $userId = $db->getConnection()->lastInsertId();
                } else {
                    $userId = $user['id'];
                }
                
                // Issue ban
                $result = $banManager->banUser($userId, $currentUser['id'], $banType, $reason, $banExpires, false, $serverBan);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => implode('. ', $result['messages'])]);
                    exit;
                }
                $message = implode('. ', $result['messages']);
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error banning player: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'add_note' && isset($_POST['user_id'])) {
            try {
                $userId = intval($_POST['user_id']);
                $noteText = trim($_POST['note_text']);
                
                $staffNotesManager->addNote($userId, $currentUser['id'], $noteText);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Note added successfully']);
                    exit;
                }
                $message = "Note added successfully";
                $messageType = "success";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = "Error adding note: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($_POST['action'] === 'get_notes' && isset($_POST['user_id'])) {
            try {
                $userId = intval($_POST['user_id']);
                $notes = $staffNotesManager->getUserNotes($userId);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'notes' => $notes]);
                exit;
            } catch (Exception $e) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }
}

// Get active players from RCON
if ($rconEnabled) {
    try {
        $players = $rconManager->getPlayers();
        
        // Enrich player data with database info
        foreach ($players as &$player) {
            if (isset($player['guid']) && !empty($player['guid'])) {
                $steamId = $player['guid'];
                $user = $db->fetchOne("SELECT * FROM users WHERE steam_id = ?", [$steamId]);
                
                if ($user) {
                    $player['db_user'] = $user;
                    $player['has_ban'] = $banManager->isUserBanned($user['id']) !== false;
                    
                    // Get ban history count
                    $bans = $banManager->getUserBans($user['id']);
                    $player['ban_count'] = count($bans);
                    
                    // Get notes count
                    $player['note_count'] = $staffNotesManager->countUserNotes($user['id']);
                } else {
                    $player['db_user'] = null;
                    $player['has_ban'] = false;
                    $player['ban_count'] = 0;
                    $player['note_count'] = 0;
                }
            }
        }
    } catch (Exception $e) {
        $message = "Error fetching players: " . $e->getMessage();
        $messageType = "error";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Players - 420th Delta</title>
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
        
        .info-box {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid #3498db;
            color: #3498db;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .warning-box {
            background: rgba(241, 196, 15, 0.1);
            border: 1px solid #f1c40f;
            color: #f1c40f;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .players-table {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(138, 43, 226, 0.2);
            overflow: hidden;
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
        
        .player-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .player-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #9b59b6;
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
        
        .btn-kick {
            background: #f39c12;
            color: white;
        }
        
        .btn-kick:hover {
            background: #e67e22;
        }
        
        .btn-ban {
            background: #e74c3c;
            color: white;
        }
        
        .btn-ban:hover {
            background: #c0392b;
        }
        
        .btn-notes {
            background: #3498db;
            color: white;
        }
        
        .btn-notes:hover {
            background: #2980b9;
        }
        
        .icon {
            font-size: 16px;
            margin-right: 4px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .badge-warning {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .badge-info {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: #1a1f35;
            margin: 5% auto;
            padding: 30px;
            border: 1px solid rgba(138, 43, 226, 0.3);
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(138, 43, 226, 0.2);
        }
        
        .modal-header h2 {
            color: #9b59b6;
            font-size: 24px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #fff;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #9b59b6;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 6px;
            color: #e0e0e0;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #9b59b6;
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.4);
        }
        
        .notes-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .note-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(138, 43, 226, 0.1);
        }
        
        .note-author {
            font-weight: 600;
            color: #9b59b6;
        }
        
        .note-date {
            font-size: 12px;
            color: #888;
        }
        
        .note-text {
            color: #e0e0e0;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .refresh-btn {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .refresh-btn:hover {
            background: #229954;
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
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
            
            .btn {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎮 Active Players</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="ban_management.php">Bans</a>
                <a href="admin.php">Admin</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$rconEnabled): ?>
            <div class="warning-box">
                ⚠️ RCON is not enabled or configured. Please configure RCON in the admin panel to view active players.
            </div>
        <?php else: ?>
            <div class="info-box">
                📡 Showing <?php echo count($players); ?> active player(s) from the game server.
                <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
            </div>
            
            <div class="players-table">
                <?php if (empty($players)): ?>
                    <div class="empty-state">
                        <h3>No players currently online</h3>
                        <p>The server appears to be empty right now.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Player</th>
                                <th>Steam ID</th>
                                <th>Playtime</th>
                                <th>Ping</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($player['num'] ?? '?'); ?></td>
                                    <td>
                                        <div class="player-info">
                                            <?php if (isset($player['db_user']) && $player['db_user']): ?>
                                                <?php if (!empty($player['db_user']['avatar_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($player['db_user']['avatar_url']); ?>" 
                                                         alt="Avatar" class="player-avatar">
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($player['name'] ?? 'Unknown'); ?></span>
                                                <?php if ($player['ban_count'] > 0): ?>
                                                    <span class="badge badge-warning" title="<?php echo $player['ban_count']; ?> previous ban(s)">
                                                        🚫 <?php echo $player['ban_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($player['note_count'] > 0): ?>
                                                    <span class="badge badge-info" title="<?php echo $player['note_count']; ?> staff note(s)">
                                                        📝 <?php echo $player['note_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars($player['name'] ?? 'Unknown'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($player['guid'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($player['time'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($player['ping'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (isset($player['db_user']) && $player['db_user']): ?>
                                            <span style="color: #2ecc71;">✓ Known</span>
                                        <?php else: ?>
                                            <span style="color: #95a5a6;">○ Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-kick" onclick="kickPlayer('<?php echo htmlspecialchars($player['guid'] ?? ''); ?>', '<?php echo htmlspecialchars($player['name'] ?? 'Unknown'); ?>')">
                                            <span class="icon">⚠️</span>Kick
                                        </button>
                                        <button class="btn btn-ban" onclick="banPlayer('<?php echo htmlspecialchars($player['guid'] ?? ''); ?>', '<?php echo htmlspecialchars($player['name'] ?? 'Unknown'); ?>', <?php echo isset($player['db_user']) ? $player['db_user']['id'] : 'null'; ?>)">
                                            <span class="icon">🚫</span>Ban
                                        </button>
                                        <?php if (isset($player['db_user']) && $player['db_user']): ?>
                                            <button class="btn btn-notes" onclick="viewNotes(<?php echo $player['db_user']['id']; ?>, '<?php echo htmlspecialchars($player['name'] ?? 'Unknown'); ?>')">
                                                <span class="icon">📝</span>Notes
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Kick Modal -->
    <div id="kickModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚠️ Kick Player</h2>
                <span class="close" onclick="closeModal('kickModal')">&times;</span>
            </div>
            <form id="kickForm">
                <input type="hidden" id="kick_steam_id" name="steam_id">
                <div class="form-group">
                    <label>Player: <span id="kick_player_name"></span></label>
                </div>
                <div class="form-group">
                    <label for="kick_reason">Reason:</label>
                    <input type="text" id="kick_reason" name="reason" placeholder="Enter kick reason...">
                </div>
                <button type="submit" class="btn-submit">Kick Player</button>
            </form>
        </div>
    </div>
    
    <!-- Ban Modal -->
    <div id="banModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🚫 Ban Player</h2>
                <span class="close" onclick="closeModal('banModal')">&times;</span>
            </div>
            <form id="banForm">
                <input type="hidden" id="ban_steam_id" name="steam_id">
                <input type="hidden" id="ban_user_id" name="user_id">
                <div class="form-group">
                    <label>Player: <span id="ban_player_name"></span></label>
                </div>
                <div class="form-group">
                    <label for="ban_type">Ban Type:</label>
                    <select id="ban_type" name="ban_type">
                        <option value="BOTH">Both (S3 + CAS)</option>
                        <option value="S3">S3 Only</option>
                        <option value="CAS">CAS Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ban_duration">Duration:</label>
                    <select id="ban_duration" name="ban_duration">
                        <option value="indefinite">Indefinite</option>
                        <option value="1">1 Hour</option>
                        <option value="6">6 Hours</option>
                        <option value="24">24 Hours</option>
                        <option value="72">3 Days</option>
                        <option value="168">1 Week</option>
                        <option value="720">30 Days</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ban_reason">Reason:</label>
                    <textarea id="ban_reason" name="reason" placeholder="Enter ban reason..."></textarea>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="server_ban" name="server_ban" value="1" checked>
                    <label for="server_ban">Also ban from game server (via RCON)</label>
                </div>
                <button type="submit" class="btn-submit">Ban Player</button>
            </form>
        </div>
    </div>
    
    <!-- Notes Modal -->
    <div id="notesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📝 Staff Notes - <span id="notes_player_name"></span></h2>
                <span class="close" onclick="closeModal('notesModal')">&times;</span>
            </div>
            <input type="hidden" id="notes_user_id">
            <div class="form-group">
                <label for="new_note">Add New Note:</label>
                <textarea id="new_note" placeholder="Enter note..."></textarea>
            </div>
            <button onclick="addNote()" class="btn-submit">Add Note</button>
            <hr style="margin: 20px 0; border: 1px solid rgba(138, 43, 226, 0.2);">
            <div class="notes-list" id="notes_list">
                <div class="empty-state">Loading notes...</div>
            </div>
        </div>
    </div>
    
    <script>
        function kickPlayer(steamId, playerName) {
            document.getElementById('kick_steam_id').value = steamId;
            document.getElementById('kick_player_name').textContent = playerName;
            document.getElementById('kickModal').style.display = 'block';
        }
        
        function banPlayer(steamId, playerName, userId) {
            document.getElementById('ban_steam_id').value = steamId;
            document.getElementById('ban_user_id').value = userId || '';
            document.getElementById('ban_player_name').textContent = playerName;
            document.getElementById('banModal').style.display = 'block';
        }
        
        function viewNotes(userId, playerName) {
            document.getElementById('notes_user_id').value = userId;
            document.getElementById('notes_player_name').textContent = playerName;
            document.getElementById('new_note').value = '';
            document.getElementById('notesModal').style.display = 'block';
            loadNotes(userId);
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Kick form submission
        document.getElementById('kickForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'kick_player');
            
            fetch('active_players.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('kickModal');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });
        
        // Ban form submission
        document.getElementById('banForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'ban_player');
            
            fetch('active_players.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal('banModal');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });
        
        function loadNotes(userId) {
            const formData = new FormData();
            formData.append('action', 'get_notes');
            formData.append('user_id', userId);
            
            fetch('active_players.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotes(data.notes);
                } else {
                    alert('Error loading notes: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
        
        function displayNotes(notes) {
            const notesList = document.getElementById('notes_list');
            
            if (notes.length === 0) {
                notesList.innerHTML = '<div class="empty-state"><p>No notes yet</p></div>';
                return;
            }
            
            let html = '';
            notes.forEach(note => {
                const createdDate = new Date(note.created_at).toLocaleString();
                html += `
                    <div class="note-item">
                        <div class="note-header">
                            <span class="note-author">${escapeHtml(note.created_by_name)}</span>
                            <span class="note-date">${createdDate}</span>
                        </div>
                        <div class="note-text">${escapeHtml(note.note_text)}</div>
                    </div>
                `;
            });
            
            notesList.innerHTML = html;
        }
        
        function addNote() {
            const userId = document.getElementById('notes_user_id').value;
            const noteText = document.getElementById('new_note').value;
            
            if (!noteText.trim()) {
                alert('Please enter a note');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_note');
            formData.append('user_id', userId);
            formData.append('note_text', noteText);
            
            fetch('active_players.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('new_note').value = '';
                    loadNotes(userId);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
