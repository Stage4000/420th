<?php
// API endpoint for leaderboard data
header('Content-Type: application/json');

require_once __DIR__ . '/../steam_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../stats_manager.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$user = SteamAuth::getCurrentUser();
$statsManager = new StatsManager();

// Check if stats tables exist first
$statsExist = $statsManager->statsTablesExist();

if (!$statsExist) {
    echo json_encode([
        'success' => false,
        'message' => 'Statistics tables have not been created yet.'
    ]);
    exit;
}

// Get filter parameters with validation
$selectedStat = $_GET['stat'] ?? 'score';
$selectedPeriod = $_GET['period'] ?? 'alltime';
$selectedServer = $_GET['server'] ?? 'main';

// Validate period
$validPeriods = ['daily', 'weekly', 'monthly', 'alltime'];
if (!in_array($selectedPeriod, $validPeriods)) {
    $selectedPeriod = 'alltime';
}

// Validate stat exists
$allStats = $statsManager->getAllStats();
$validStats = array_column($allStats, 'stat_id');
if (!in_array($selectedStat, $validStats)) {
    // Default to first available stat or 'score'
    $selectedStat = !empty($validStats) ? $validStats[0] : 'score';
}

// Validate server exists
$allServers = $statsManager->getAllServers();
$validServers = array_column($allServers, 'server_id');
if (!in_array($selectedServer, $validServers)) {
    // Default to first available server or 'main'
    $selectedServer = !empty($validServers) ? $validServers[0] : 'main';
}

// Get leaderboard data
$leaderboard = $statsManager->getLeaderboard($selectedStat, $selectedPeriod, $selectedServer, 50);

// Add rank and highlight current user
foreach ($leaderboard as $index => &$entry) {
    $entry['rank'] = $index + 1;
    $entry['is_current_user'] = $entry['steam_id'] === $user['steam_id'];
}

echo json_encode([
    'success' => true,
    'leaderboard' => $leaderboard,
    'stat' => $selectedStat,
    'period' => $selectedPeriod,
    'server' => $selectedServer
]);
