<?php
// Steam OAuth Authentication Handler

require_once 'config.php';
require_once 'db.php';

class SteamAuth {
    
    /**
     * Get Steam login URL
     */
    public static function getLoginUrl(): string {
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
     * @return string|false
     */
    public static function validate() {
        $requiredKeys = [
            'openid_assoc_handle',
            'openid_signed',
            'openid_sig',
            'openid_claimed_id',
        ];

        foreach ($requiredKeys as $key) {
            if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
                return false;
            }
        }

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
            $paramKey = 'openid_' . str_replace('.', '_', $item);
            if (!isset($_GET[$paramKey]) || !is_string($_GET[$paramKey])) {
                return false;
            }

            $val = $_GET[$paramKey];
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

        $result = @file_get_contents(STEAM_LOGIN_URL, false, $context);
        if ($result === false) {
            error_log("Failed to validate Steam OpenID response");
            return false;
        }

        preg_match("#^https?://steamcommunity.com/openid/id/([0-9]{17,25})#", $_GET['openid_claimed_id'], $matches);
        if (!isset($matches[1])) {
            return false;
        }

        $steamId = $matches[1];

        return preg_match("#is_valid\s*:\s*true#i", $result) == 1 ? $steamId : false;
    }

    /**
     * Get Steam user info
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function getUserInfo(string $steamId): ?array {
        $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=" . STEAM_API_KEY . "&steamids=" . $steamId;
        
        // Use error handling for API call
        $json = @file_get_contents($url);
        if ($json === false) {
            error_log("Failed to fetch Steam user info for Steam ID: " . $steamId);
            return null;
        }
        
        $data = json_decode($json, true);
        
        if (isset($data['response']['players'][0])) {
            return $data['response']['players'][0];
        }
        
        return null;
    }

    /**
     * Login user and create session
     */
    public static function login(string $steamId): bool {
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
            $userId = (int) $db->lastInsertId();
        } else {
            // Update existing user
            $userId = (int) $user['id'];
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
    public static function logout(): void {
        session_destroy();
        session_start();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user
     */
    /**
     * @return array{id: int, steam_id: string, steam_name: string, avatar_url: string, roles: array<int, array{name: string, display_name: string, alias: string|null}>}|null
     */
    public static function getCurrentUser(): ?array {
        if (!self::isLoggedIn()) {
            return null;
        }

        $requiredKeys = ['user_id', 'steam_id', 'steam_name', 'avatar_url', 'roles'];
        foreach ($requiredKeys as $key) {
            if (!isset($_SESSION[$key])) {
                return null;
            }
        }

        if (!is_array($_SESSION['roles'])) {
            return null;
        }
        
        return [
            'id' => (int) $_SESSION['user_id'],
            'steam_id' => (string) $_SESSION['steam_id'],
            'steam_name' => (string) $_SESSION['steam_name'],
            'avatar_url' => (string) $_SESSION['avatar_url'],
            'roles' => $_SESSION['roles']
        ];
    }

    /**
     * Get user roles
     */
    /**
     * @return array<int, array{name: string, display_name: string, alias: string|null}>
     */
    public static function getUserRoles(int $userId): array {
        $db = Database::getInstance();
        
        // Get user's role columns
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return [];
        }
        
        // Get role metadata (aliases, display names)
        $rolesMetadata = $db->fetchAll("SELECT name, display_name, alias FROM roles");
        $metadataMap = [];
        foreach ($rolesMetadata as $meta) {
            $metadataMap[strtolower($meta['name'])] = $meta;
        }
        
        // Build roles array from boolean columns
        $roles = [];
        $roleColumns = [
            'role_s3' => 'S3',
            'role_cas' => 'CAS',
            'role_s1' => 'S1',
            'role_opfor' => 'OPFOR',
            'role_all' => 'ALL',
            'role_admin' => 'ADMIN',
            'role_moderator' => 'MODERATOR',
            'role_trusted' => 'TRUSTED',
            'role_media' => 'MEDIA',
            'role_curator' => 'CURATOR',
            'role_developer' => 'DEVELOPER',
            'role_panel' => 'PANEL',
        ];
        
        foreach ($roleColumns as $column => $roleName) {
            if (!empty($user[$column])) {
                $key = strtolower($roleName);
                $roles[] = [
                    'name' => $roleName,
                    'display_name' => isset($metadataMap[$key]) ? $metadataMap[$key]['display_name'] : $roleName,
                    'alias' => isset($metadataMap[$key]) ? $metadataMap[$key]['alias'] : null,
                ];
            }
        }
        
        return $roles;
    }

    /**
     * Check if user has role
     */
    public static function hasRole(string $roleName): bool {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (!isset($_SESSION['roles']) || !is_array($_SESSION['roles'])) {
            return false;
        }

        foreach ($_SESSION['roles'] as $role) {
            if (is_array($role) && isset($role['name']) && $role['name'] === $roleName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is panel admin
     */
    public static function isPanelAdmin(): bool {
        return self::hasRole('PANEL');
    }

    /**
     * Get base URL
     */
    private static function getBaseUrl(): string {
        $httpsEnabled = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
        $protocol = ($httpsEnabled || $serverPort === 443) ? "https://" : "http://";
        $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        return $protocol . $host;
    }
}
