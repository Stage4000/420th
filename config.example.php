<?php
// Configuration Example File for 420th Delta Dashboard
// Copy this to config.php and update with your settings

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '420th_whitelist');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Steam OAuth Configuration
// Get your Steam API key from: https://steamcommunity.com/dev/apikey
define('STEAM_API_KEY', getenv('STEAM_API_KEY') ?: 'YOUR_STEAM_API_KEY_HERE');
define('STEAM_LOGIN_URL', 'https://steamcommunity.com/openid/login');
// Update this to match your domain
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

// RCON Configuration (optional)
// RCON settings are stored in database and can be configured via admin panel
// These settings are for reference only - actual values are managed in admin interface
// define('RCON_ENABLED', false);
// define('RCON_HOST', '127.0.0.1');
// define('RCON_PORT', 2306);
// define('RCON_PASSWORD', 'your_rcon_password');

// Start session
session_name(SESSION_NAME);
session_start();
