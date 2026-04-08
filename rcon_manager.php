<?php
// RCON Manager for Arma 3 server control

// Check if Composer autoloader exists before requiring it
// This allows the application to function without RCON support if vendor dependencies aren't installed
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'db.php';

class RconManager {
    // Steam ID64 is always 17 digits
    const STEAM_ID64_LENGTH = 17;
    
    private Database $db;
    /** @var \Nizarii\ARC|null */
    private $rcon = null;
    private bool $enabled = false;
    private string $host = '';
    private int $port = 0;
    private string $password = '';
    private bool $libraryAvailable = false;
    
    public function __construct() {
        // Check if RCON library is available
        $this->libraryAvailable = class_exists('Nizarii\\ARC');
        $this->db = Database::getInstance();
        $this->loadSettings();
    }
    
    /**
     * Load RCON settings from database
     */
    private function loadSettings(): void {
        try {
            $settings = $this->db->fetchAll(
                "SELECT setting_key, setting_value FROM server_settings 
                 WHERE setting_key LIKE 'rcon_%'"
            );
            
            foreach ($settings as $setting) {
                switch ($setting['setting_key']) {
                    case 'rcon_enabled':
                        $this->enabled = (bool)$setting['setting_value'];
                        break;
                    case 'rcon_host':
                        $this->host = $setting['setting_value'];
                        break;
                    case 'rcon_port':
                        $this->port = (int)$setting['setting_value'];
                        break;
                    case 'rcon_password':
                        $this->password = $setting['setting_value'];
                        break;
                }
            }
        } catch (Exception $e) {
            // Settings table might not exist yet
            $this->enabled = false;
        }
    }
    
    /**
     * Check if RCON is enabled and configured
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->libraryAvailable &&
               $this->enabled && 
               !empty($this->host) && 
               !empty($this->password) && 
               $this->port > 0;
    }
    
    /**
     * Update RCON settings
     * @param array<string, scalar|null> $settings Associative array of settings to update
     * @param int $userId User ID making the change
     * @return bool
     */
    public function updateSettings(array $settings, int $userId): bool {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                if (strpos($key, 'rcon_') === 0) {
                    $this->db->query(
                        "INSERT INTO server_settings (setting_key, setting_value, updated_by_user_id) 
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE 
                         setting_value = VALUES(setting_value),
                         updated_by_user_id = VALUES(updated_by_user_id)",
                        [$key, $value, $userId]
                    );
                }
            }
            
            $this->db->commit();
            
            // Reload settings
            $this->loadSettings();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get current RCON settings (password masked)
     * @return array{rcon_enabled: bool, rcon_host: string, rcon_port: int, rcon_password_set: bool}
     */
    public function getSettings(): array {
        return [
            'rcon_enabled' => $this->enabled,
            'rcon_host' => $this->host,
            'rcon_port' => $this->port,
            'rcon_password_set' => !empty($this->password)
        ];
    }
    
    /**
     * Establish RCON connection
     * @return \Nizarii\ARC
     * @throws Exception
     */
    private function connect() {
        if (!$this->libraryAvailable) {
            throw new Exception("RCON library not installed. Run 'composer install' to enable RCON features.");
        }
        
        if (!$this->isEnabled()) {
            throw new Exception("RCON is not enabled or not configured");
        }
        
        if ($this->rcon === null) {
            try {
                $this->rcon = new \Nizarii\ARC($this->host, $this->password, $this->port);
            } catch (Exception $e) {
                throw new Exception("Failed to connect to RCON server: " . $e->getMessage());
            }
        }
        
        return $this->rcon;
    }
    
    /**
     * Test RCON connection
     * @return array{success: bool, message: string, player_count: int}
     */
    public function testConnection(): array {
        try {
            $rcon = $this->connect();
            
            // Try to get player list as connection test
            $players = $rcon->getPlayersArray();
            
            return [
                'success' => true,
                'message' => 'Connected successfully to RCON server',
                'player_count' => count($players)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'player_count' => 0,
            ];
        }
    }
    
    /**
     * Get list of online players
     * @return array Array of players
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlayers(): array {
        try {
            $rcon = $this->connect();
            return $rcon->getPlayersArray();
        } catch (Exception $e) {
            throw new Exception("Failed to get player list: " . $e->getMessage());
        }
    }
    
    /**
     * Kick a player from the server
     * @param string $identifier Player name, ID, or Steam ID
     * @param string $reason Kick reason
     * @return bool
     */
    public function kickPlayer(string $identifier, string $reason = ''): bool {
        try {
            $rcon = $this->connect();
            
            // Try to find player by Steam ID if 17-digit number
            if (preg_match('/^\d{' . self::STEAM_ID64_LENGTH . '}$/', $identifier)) {
                $players = $rcon->getPlayersArray();
                foreach ($players as $player) {
                    if (isset($player['guid']) && $player['guid'] === $identifier) {
                        $identifier = $player['num'];
                        break;
                    }
                }
            }
            
            // Execute kick command
            $fullReason = !empty($reason) ? $reason : 'Kicked by admin';
            $rcon->kickPlayer($identifier, $fullReason);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to kick player: " . $e->getMessage());
        }
    }
    
    /**
     * Ban a player from the server
     * @param string $identifier Player name, ID, or Steam ID (GUID)
     * @param string $reason Ban reason
     * @param int $duration Ban duration in minutes (0 = permanent)
     * @return bool
     */
    public function banPlayer(string $identifier, string $reason = '', int $duration = 0): bool {
        try {
            $rcon = $this->connect();
            
            // For BattlEye, we need the GUID (Steam ID)
            // If identifier looks like a Steam ID, use it directly
            if (preg_match('/^\d{' . self::STEAM_ID64_LENGTH . '}$/', $identifier)) {
                $guid = $identifier;
            } else {
                // Try to find player's GUID from player list
                $players = $rcon->getPlayersArray();
                $guid = null;
                
                foreach ($players as $player) {
                    if ($player['num'] == $identifier || 
                         stripos((string) $player['name'], $identifier) !== false) {
                        $guid = isset($player['guid']) ? $player['guid'] : null;
                        $identifier = $player['num'];
                        break;
                    }
                }
                
                if (!$guid) {
                    throw new Exception("Could not find player GUID for banning");
                }
            }
            
            // Execute ban command with GUID
            $fullReason = !empty($reason) ? $reason : 'Banned by admin';
            
            // BattlEye ban format: #exec ban <player_number> <duration_in_minutes> <reason>
            // Duration: 0 = permanent, or number of minutes
            if ($duration > 0) {
                $rcon->command("ban {$identifier} {$duration} {$fullReason}");
            } else {
                $rcon->command("ban {$identifier} 0 {$fullReason}");
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to ban player: " . $e->getMessage());
        }
    }
    
    /**
     * Unban a player from the server
     * @param string $steamId Player's Steam ID (GUID) to unban
     * @return bool
     */
    public function unbanPlayer(string $steamId): bool {
        try {
            $rcon = $this->connect();
            
            // BattlEye unban command uses the GUID (Steam ID)
            // Command format: removeBan <GUID>
            $rcon->command("removeBan {$steamId}");
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to unban player: " . $e->getMessage());
        }
    }
    
    /**
     * Send a global message to all players
     * @param string $message Message to send
     * @return bool
     */
    public function sendGlobalMessage(string $message): bool {
        try {
            $rcon = $this->connect();
            $rcon->sayGlobal($message);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to send message: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a raw RCON command
     * @param string $command Command to execute
     * @return string Command response
     */
    public function executeCommand(string $command): string {
        try {
            $rcon = $this->connect();
            return $rcon->command($command);
        } catch (Exception $e) {
            throw new Exception("Failed to execute command: " . $e->getMessage());
        }
    }
}
