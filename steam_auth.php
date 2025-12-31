<?php
// Steam OAuth Authentication Handler

require_once 'config.php';
require_once 'db.php';

class SteamAuth {
    
    /**
     * Get Steam login URL
     */
    public static function getLoginUrl() {
        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => STEAM_RETURN_URL,
            'openid.realm' => self::getBaseUrl(),
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];
        
        return STEAM_LOGIN_URL . '?' . http_build_query($params);
    }

    /**
     * Validate Steam OAuth callback
     */
    public static function validate() {
        if (!isset($_GET['openid_assoc_handle'])) {
            return false;
        }

        $params = [
            'openid.assoc_handle' => $_GET['openid_assoc_handle'],
            'openid.signed' => $_GET['openid_signed'],
            'openid.sig' => $_GET['openid_sig'],
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'check_authentication',
        ];

        $signed = explode(',', $_GET['openid_signed']);
        foreach ($signed as $item) {
            $val = $_GET['openid_' . str_replace('.', '_', $item)];
            $params['openid.' . $item] = $val;
        }

        $data = http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\nContent-length: " . strlen($data) . "\r\n",
                'content' => $data,
            ],
        ]);

        $result = file_get_contents(STEAM_LOGIN_URL, false, $context);

        preg_match("#^https?://steamcommunity.com/openid/id/([0-9]{17,25})#", $_GET['openid_claimed_id'], $matches);
        $steamId = is_numeric($matches[1]) ? $matches[1] : 0;

        return preg_match("#is_valid\s*:\s*true#i", $result) == 1 ? $steamId : false;
    }

    /**
     * Get Steam user info
     */
    public static function getUserInfo($steamId) {
        $url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=" . STEAM_API_KEY . "&steamids=" . $steamId;
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        
        if (isset($data['response']['players'][0])) {
            return $data['response']['players'][0];
        }
        
        return null;
    }

    /**
     * Login user and create session
     */
    public static function login($steamId) {
        $db = Database::getInstance();
        
        // Get Steam user info
        $steamInfo = self::getUserInfo($steamId);
        if (!$steamInfo) {
            return false;
        }

        // Check if user exists
        $user = $db->fetchOne("SELECT * FROM users WHERE steam_id = ?", [$steamId]);
        
        if (!$user) {
            // Create new user
            $db->execute(
                "INSERT INTO users (steam_id, steam_name, avatar_url) VALUES (?, ?, ?)",
                [$steamId, $steamInfo['personaname'], $steamInfo['avatarfull']]
            );
            $userId = $db->lastInsertId();
        } else {
            // Update existing user
            $userId = $user['id'];
            $db->execute(
                "UPDATE users SET steam_name = ?, avatar_url = ?, last_login = NOW() WHERE id = ?",
                [$steamInfo['personaname'], $steamInfo['avatarfull'], $userId]
            );
        }

        // Get user roles
        $roles = self::getUserRoles($userId);

        // Set session
        $_SESSION['user_id'] = $userId;
        $_SESSION['steam_id'] = $steamId;
        $_SESSION['steam_name'] = $steamInfo['personaname'];
        $_SESSION['avatar_url'] = $steamInfo['avatarfull'];
        $_SESSION['roles'] = $roles;

        return true;
    }

    /**
     * Logout user
     */
    public static function logout() {
        session_destroy();
        session_start();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'steam_id' => $_SESSION['steam_id'],
            'steam_name' => $_SESSION['steam_name'],
            'avatar_url' => $_SESSION['avatar_url'],
            'roles' => $_SESSION['roles']
        ];
    }

    /**
     * Get user roles
     */
    public static function getUserRoles($userId) {
        $db = Database::getInstance();
        $result = $db->fetchAll(
            "SELECT r.name, r.display_name 
             FROM user_roles ur 
             JOIN roles r ON ur.role_id = r.id 
             WHERE ur.user_id = ?",
            [$userId]
        );
        
        return $result;
    }

    /**
     * Check if user has role
     */
    public static function hasRole($roleName) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        foreach ($_SESSION['roles'] as $role) {
            if ($role['name'] === $roleName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is panel admin
     */
    public static function isPanelAdmin() {
        return self::hasRole('PANEL');
    }

    /**
     * Get base URL
     */
    private static function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $protocol . $_SERVER['HTTP_HOST'];
    }
}
