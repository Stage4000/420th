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
            background: #0a0e1a;
            color: #e4e6eb;
            min-height: 100vh;
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
            border-radius: 10px;
            border: 1px solid #2a3142;
            overflow: hidden;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leaderboard-table thead {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .leaderboard-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #667eea;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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
        
        .current-user-label {
            color: #667eea;
            font-weight: 600;
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
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 31, 46, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            z-index: 10;
        }
        
        .loading-spinner {
            border: 4px solid rgba(102, 126, 234, 0.2);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .leaderboard-card {
            position: relative;
        }
        
        <?php include 'footer_styles.php'; ?>
        
        @media (max-width: 768px) {
            .container {
                width: 100%;
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
    <?php 
    $currentPage = 'leaderboards';
    $pageTitle = 'Leaderboards';
    $isPanelAdmin = SteamAuth::isPanelAdmin();
    $canViewBans = $isPanelAdmin || SteamAuth::hasRole('ALL');
    $canViewActivePlayers = SteamAuth::hasRole('ADMIN');
    ?>
    <?php include 'navbar.php'; ?>
    
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
                            <select name="stat" id="stat">
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
                            <select name="period" id="period">
                                <option value="daily" <?php echo $selectedPeriod === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $selectedPeriod === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $selectedPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="alltime" <?php echo $selectedPeriod === 'alltime' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="server">Server</label>
                            <select name="server" id="server">
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
                                            <span class="current-user-label"> (You)</span>
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
    
    <?php include 'footer.php'; ?>
    
    <script>
        // AJAX functionality for leaderboard updates
        document.addEventListener('DOMContentLoaded', function() {
            const statSelect = document.getElementById('stat');
            const periodSelect = document.getElementById('period');
            const serverSelect = document.getElementById('server');
            const leaderboardCard = document.querySelector('.leaderboard-card');
            
            // Add event listeners to all filter selects
            if (statSelect) statSelect.addEventListener('change', updateLeaderboard);
            if (periodSelect) periodSelect.addEventListener('change', updateLeaderboard);
            if (serverSelect) serverSelect.addEventListener('change', updateLeaderboard);
            
            function updateLeaderboard() {
                const stat = statSelect.value;
                const period = periodSelect.value;
                const server = serverSelect.value;
                
                // Show loading overlay
                showLoading();
                
                // Update URL without page refresh
                const url = new URL(window.location);
                url.searchParams.set('stat', stat);
                url.searchParams.set('period', period);
                url.searchParams.set('server', server);
                window.history.pushState({}, '', url);
                
                // Fetch new leaderboard data
                fetch(`api/leaderboard.php?stat=${encodeURIComponent(stat)}&period=${encodeURIComponent(period)}&server=${encodeURIComponent(server)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderLeaderboard(data.leaderboard, data.stat);
                        } else {
                            showError(data.message || 'Failed to load leaderboard data');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching leaderboard:', error);
                        showError('An error occurred while loading the leaderboard');
                    })
                    .finally(() => {
                        hideLoading();
                    });
            }
            
            function renderLeaderboard(leaderboard, statName) {
                const statDisplay = statName.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
                
                if (leaderboard.length === 0) {
                    leaderboardCard.innerHTML = `
                        <div class="no-data">
                            <div class="no-data-icon">📊</div>
                            <h2>No Data Available</h2>
                            <p>There are no statistics recorded for this period yet.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th class="rank">Rank</th>
                                <th>Player</th>
                                <th class="stat">${escapeHtml(statDisplay)}</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                leaderboard.forEach(entry => {
                    const rank = entry.rank;
                    const isCurrentUser = entry.is_current_user;
                    const rowClass = isCurrentUser ? ' class="highlight"' : '';
                    
                    let rankHtml;
                    if (rank <= 3) {
                        const medalClass = rank === 1 ? 'gold' : (rank === 2 ? 'silver' : 'bronze');
                        rankHtml = `<span class="rank-medal ${medalClass}">${rank}</span>`;
                    } else {
                        rankHtml = rank;
                    }
                    
                    const youLabel = isCurrentUser ? '<span class="current-user-label"> (You)</span>' : '';
                    
                    html += `
                        <tr${rowClass}>
                            <td class="rank">${rankHtml}</td>
                            <td>${escapeHtml(entry.name)}${youLabel}</td>
                            <td class="stat">${formatNumber(entry.amount)}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                    </table>
                `;
                
                leaderboardCard.innerHTML = html;
            }
            
            function showLoading() {
                const existingOverlay = leaderboardCard.querySelector('.loading-overlay');
                if (!existingOverlay) {
                    const overlay = document.createElement('div');
                    overlay.className = 'loading-overlay';
                    overlay.innerHTML = '<div class="loading-spinner"></div>';
                    leaderboardCard.appendChild(overlay);
                }
            }
            
            function hideLoading() {
                const overlay = leaderboardCard.querySelector('.loading-overlay');
                if (overlay) {
                    overlay.remove();
                }
            }
            
            function showError(message) {
                leaderboardCard.innerHTML = `
                    <div class="no-data">
                        <div class="no-data-icon">⚠️</div>
                        <h2>Error</h2>
                        <p>${escapeHtml(message)}</p>
                    </div>
                `;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatNumber(num) {
                return new Intl.NumberFormat().format(num);
            }
        });
    </script>
</body>
</html>
