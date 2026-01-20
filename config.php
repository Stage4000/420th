<?php
// Configuration file for the 420th Delta Dashboard

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '420th_whitelist');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Steam OAuth Configuration
define('STEAM_API_KEY', getenv('STEAM_API_KEY') ?: 'YOUR_STEAM_API_KEY_HERE');
define('STEAM_LOGIN_URL', 'https://steamcommunity.com/openid/login');
define('STEAM_RETURN_URL', getenv('STEAM_RETURN_URL') ?: 'http://localhost/callback.php');

// Session Configuration
define('SESSION_NAME', '420th_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 hours

// Whitelist Roles
define('ROLES', [
    'S3' => 'S3',
    'CAS' => 'CAS',
    'S1' => 'S1',
    'OPFOR' => 'OPFOR',
    'ALL' => 'ALL (Staff)',
    'ADMIN' => 'Administrator',
    'MODERATOR' => 'Moderator',
    'TRUSTED' => 'Trusted',
    'MEDIA' => 'Media',
    'CURATOR' => 'Curator',
    'DEVELOPER' => 'Developer',
    'PANEL' => 'Panel Administrator'
]);

// Default Whitelist Agreement
define('DEFAULT_WHITELIST_AGREEMENT', '<p><strong>By requesting whitelist, you agree to the following:</strong></p>
<ul>
    <li>
        <strong>Pilot Communication</strong> - All pilots are expected to communicate in-game via text or voice. You may be asked to switch role if unable to communicate.
    </li>
    <li>
        <strong>Waiting For Passengers</strong> - Transport Helicopters should wait in an orderly fashion on the side of the yellow barriers opposite from spawn, leaving the traffic lane clear for infantry and vehicles.
    </li>
    <li>
        <strong>No CAS on Kavala</strong> - All Close Air Support is forbidden to engage the Priority Mission Kavala. This mission is meant to be close-quarters combat. CAS can ruin the mission if they destroy buildings containing intel. Contact an in-game Zeus or use the vote-kick feature to enforce this rule as needed.
    </li>
</ul>');

// Start session
session_name(SESSION_NAME);
session_start();
