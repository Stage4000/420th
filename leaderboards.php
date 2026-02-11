<?php
// Leaderboards page for player statistics

require_once 'steam_auth.php';
require_once 'db.php';
require_once 'stats_manager.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    header('Location: index');
    exit;
}

$user = SteamAuth::getCurrentUser();
$isPanelAdmin = SteamAuth::isPanelAdmin();
$statsManager = new StatsManager();

// Get filter parameters
$selectedStat = isset($_GET['stat']) ? $_GET['stat'] : 'score';
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'alltime';
$selectedServer = isset($_GET['server']) ? $_GET['server'] : 'main';

// Check if stats tables exist
$statsExist = $statsManager->statsTablesExist();

// Get available stats and servers
$allStats = $statsExist ? $statsManager->getAllStats() : [];
$allServers = $statsExist ? $statsManager->getAllServers() : [];

// Get leaderboard data
$leaderboard = $statsExist ? $statsManager->getLeaderboard($selectedStat, $selectedPeriod, $selectedServer, 50) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards - 420th Delta</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f1318 0%, #1a1f2e 100%);
            color: #e4e6eb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background: rgba(26, 31, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid #2a3142;
            position: sticky;
            top: 0;
            z-index: 100;
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
            font-weight: 600;
            color: #667eea;
        }
        
        .navbar-links {
            display: flex;
            align-items: center;
            gap: 1rem;
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
        }
        
        .navbar-links a.active {
            background: rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #667eea;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.2s;
            border: none !important;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
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
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            color: #8b92a8;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .filter-group select {
            background: #0f1318;
            color: #e4e6eb;
            border: 1px solid #2a3142;
            padding: 0.75rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-group select:hover {
            border-color: #667eea;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .leaderboard-card {
            background: #1a1f2e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid #2a3142;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leaderboard-table thead {
            background: #0f1318;
            border-bottom: 2px solid #667eea;
        }
        
        .leaderboard-table th {
            padding: 1rem;
            text-align: left;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .leaderboard-table th.rank {
            width: 80px;
            text-align: center;
        }
        
        .leaderboard-table th.stat {
            width: 120px;
            text-align: right;
        }
        
        .leaderboard-table tbody tr {
            border-bottom: 1px solid #2a3142;
            transition: background 0.2s;
        }
        
        .leaderboard-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .leaderboard-table tbody tr.highlight {
            background: rgba(102, 126, 234, 0.15);
        }
        
        .leaderboard-table td {
            padding: 1rem;
            color: #e4e6eb;
        }
        
        .leaderboard-table td.rank {
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .leaderboard-table td.stat {
            text-align: right;
            font-weight: 600;
            color: #667eea;
        }
        
        .rank-medal {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .rank-medal.gold {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #0f1318;
        }
        
        .rank-medal.silver {
            background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
            color: #0f1318;
        }
        
        .rank-medal.bronze {
            background: linear-gradient(135deg, #cd7f32 0%, #e89e67 100%);
            color: #0f1318;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #8b92a8;
        }
        
        .no-data-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .info-message {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #667eea;
            text-align: center;
        }
        
        footer {
            background: rgba(26, 31, 46, 0.95);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #8b92a8;
            margin-top: auto;
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
            
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
            }
            
            .navbar-links {
                width: 100%;
                margin-top: 1rem;
                justify-content: center;
                gap: 0.5rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .leaderboard-table {
                font-size: 0.9rem;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .rank-medal {
                width: 30px;
                height: 30px;
                font-size: 1rem;
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
            <span class="navbar-title">Leaderboards</span>
        </div>
        <div class="navbar-links">
            <a href="dashboard">Dashboard</a>
            <?php if ($isPanelAdmin): ?>
                <a href="admin">Admin Panel</a>
                <a href="users">Users</a>
                <a href="ban_management">Bans</a>
            <?php endif; ?>
            <?php if (SteamAuth::hasRole('ADMIN')): ?>
                <a href="active_players">Active Players</a>
            <?php endif; ?>
            <a href="leaderboards" class="active">Leaderboards</a>
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
            <span><?php echo htmlspecialchars($user['steam_name']); ?></span>
            <a href="logout" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h1>🏆 Player Leaderboards</h1>
            <p>View top players across various statistics and time periods</p>
        </div>
        
        <?php if (!$statsExist): ?>
            <div class="info-message">
                <strong>Note:</strong> Statistics tables have not been created yet. Please contact an administrator to set up the stats system.
            </div>
        <?php else: ?>
            <div class="filters-card">
                <form method="GET" action="leaderboards">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="stat">Statistic</label>
                            <select name="stat" id="stat" onchange="this.form.submit()">
                                <?php foreach ($allStats as $stat): ?>
                                    <option value="<?php echo htmlspecialchars($stat['stat_id']); ?>" 
                                            <?php echo $selectedStat === $stat['stat_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $stat['stat_id']))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="period">Time Period</label>
                            <select name="period" id="period" onchange="this.form.submit()">
                                <option value="daily" <?php echo $selectedPeriod === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $selectedPeriod === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $selectedPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="alltime" <?php echo $selectedPeriod === 'alltime' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="server">Server</label>
                            <select name="server" id="server" onchange="this.form.submit()">
                                <?php foreach ($allServers as $server): ?>
                                    <option value="<?php echo htmlspecialchars($server['server_id']); ?>" 
                                            <?php echo $selectedServer === $server['server_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($server['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="leaderboard-card">
                <?php if (empty($leaderboard)): ?>
                    <div class="no-data">
                        <div class="no-data-icon">📊</div>
                        <h2>No Data Available</h2>
                        <p>There are no statistics recorded for this period yet.</p>
                    </div>
                <?php else: ?>
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th class="rank">Rank</th>
                                <th>Player</th>
                                <th class="stat"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $selectedStat))); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $entry): ?>
                                <?php 
                                $rank = $index + 1;
                                $isCurrentUser = $entry['steam_id'] === $user['steam_id'];
                                ?>
                                <tr <?php echo $isCurrentUser ? 'class="highlight"' : ''; ?>>
                                    <td class="rank">
                                        <?php if ($rank <= 3): ?>
                                            <span class="rank-medal <?php 
                                                echo $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : 'bronze'); 
                                            ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($entry['name']); ?>
                                        <?php if ($isCurrentUser): ?>
                                            <span style="color: #667eea; font-weight: 600;"> (You)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="stat">
                                        <?php echo number_format($entry['amount']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
