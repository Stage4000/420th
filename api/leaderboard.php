<?php
// API endpoint for leaderboard data
header('Content-Type: application/json');

require_once __DIR__ . '/../steam_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../stats_manager.php';

// Check if user is logged in
if (!SteamAuth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = SteamAuth::getCurrentUser();
$statsManager = new StatsManager();

// Get filter parameters
$selectedStat = isset($_GET['stat']) ? $_GET['stat'] : 'score';
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'alltime';
$selectedServer = isset($_GET['server']) ? $_GET['server'] : 'main';

// Check if stats tables exist
$statsExist = $statsManager->statsTablesExist();

if (!$statsExist) {
    echo json_encode([
        'success' => false,
        'message' => 'Statistics tables have not been created yet.'
    ]);
    exit;
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
