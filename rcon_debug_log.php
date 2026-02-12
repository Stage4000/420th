<?php
// RCON Debug Log Viewer
// This page allows viewing and clearing the RCON debug log

require_once 'steam_auth.php';
require_once 'rcon_manager.php';

// Check if user is logged in and has admin role
if (!SteamAuth::isLoggedIn()) {
    header('Location: index');
    exit;
}

$hasAdminRole = SteamAuth::hasRole('ADMIN');
if (!$hasAdminRole) {
    header('Location: dashboard');
    exit;
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'clear') {
        if (RconManager::clearLog()) {
            $message = 'Log cleared successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to clear log';
            $messageType = 'error';
        }
    }
}

$logContent = RconManager::getLog();
$logPath = RconManager::getLogFilePath();
$logExists = file_exists($logPath);
$logSize = $logExists ? filesize($logPath) : 0;
$logSizeFormatted = $logExists ? number_format($logSize / 1024, 2) . ' KB' : '0 KB';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCON Debug Log - 420th Delta</title>
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
        
        <?php include 'navbar_styles.php'; ?>
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .header {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            border: 1px solid #2a3142;
        }
        
        .header h1 {
            color: #e4e6eb;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #8b92a8;
        }
        
        .info-box {
            background: #1e3a45;
            border: 1px solid #3498db;
            color: #90cdf4;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
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
        
        .actions {
            margin-bottom: 1.5rem;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 0.5rem;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .log-container {
            background: #1a1f2e;
            border: 1px solid #2a3142;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .log-content {
            background: #0a0e1a;
            border: 1px solid #2a3142;
            border-radius: 5px;
            padding: 1rem;
            max-height: 600px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #e4e6eb;
        }
        
        .log-content::-webkit-scrollbar {
            width: 10px;
        }
        
        .log-content::-webkit-scrollbar-track {
            background: #1a1f2e;
        }
        
        .log-content::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 5px;
        }
        
        .empty-log {
            text-align: center;
            color: #8b92a8;
            padding: 3rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>RCON Debug Log</h1>
            <p>View detailed RCON communication logs for debugging</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <div class="info-row">
                <strong>Log File:</strong>
                <span><?php echo htmlspecialchars($logPath); ?></span>
            </div>
            <div class="info-row">
                <strong>Status:</strong>
                <span><?php echo $logExists ? 'Exists' : 'Not created yet'; ?></span>
            </div>
            <div class="info-row">
                <strong>Size:</strong>
                <span><?php echo $logSizeFormatted; ?></span>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn" onclick="location.reload()">🔄 Refresh</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear the log?')">🗑️ Clear Log</button>
            </form>
        </div>
        
        <div class="log-container">
            <h2 style="margin-bottom: 1rem;">Log Content</h2>
            <?php if ($logExists && $logSize > 0): ?>
                <div class="log-content"><?php echo htmlspecialchars($logContent); ?></div>
            <?php else: ?>
                <div class="empty-log">
                    <h3>No log entries yet</h3>
                    <p>Visit the Active Players page to generate RCON traffic</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>
