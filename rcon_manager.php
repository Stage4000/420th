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
    
    // RCON debug log file
    const LOG_FILE = __DIR__ . '/rcon_debug.log';
    
    private $db;
    private $rcon;
    private $enabled;
    private $host;
    private $port;
    private $password;
    private $libraryAvailable;
    
    public function __construct() {
        // Check if RCON library is available
        $this->libraryAvailable = class_exists('Nizarii\\ARC');
        $this->db = Database::getInstance();
        $this->loadSettings();
    }
    
    /**
     * Log RCON debug information
     * @param string $message Log message
     * @param mixed $data Optional data to log
     */
    private function logDebug($message, $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";
        
        if ($data !== null) {
            $logEntry .= "\n" . print_r($data, true);
        }
        
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";
        
        // Append to log file
        file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND);
    }
    
    /**
     * Load RCON settings from database
     */
    private function loadSettings() {
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
    public function isEnabled() {
        return $this->libraryAvailable &&
               $this->enabled && 
               !empty($this->host) && 
               !empty($this->password) && 
               $this->port > 0;
    }
    
    /**
     * Update RCON settings
     * @param array $settings Associative array of settings to update
     * @param int $userId User ID making the change
     * @return bool
     */
    public function updateSettings($settings, $userId) {
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
     * @return array
     */
    public function getSettings() {
        return [
            'rcon_enabled' => $this->enabled,
            'rcon_host' => $this->host,
            'rcon_port' => $this->port,
            'rcon_password_set' => !empty($this->password)
        ];
    }
    
    /**
     * Establish RCON connection
     * @return bool
     * @throws Exception
     */
    private function connect() {
        if (!$this->libraryAvailable) {
            $this->logDebug("RCON library not available");
            throw new Exception("RCON library not installed. Run 'composer install' to enable RCON features.");
        }
        
        if (!$this->isEnabled()) {
            $this->logDebug("RCON not enabled or not configured");
            throw new Exception("RCON is not enabled or not configured");
        }
        
        if (!$this->rcon) {
            try {
                $this->logDebug("Attempting RCON connection", [
                    'host' => $this->host,
                    'port' => $this->port,
                    'password_set' => !empty($this->password)
                ]);
                
                $this->rcon = new \Nizarii\ARC($this->host, $this->password, $this->port);
                
                $this->logDebug("RCON connection established successfully");
            } catch (Exception $e) {
                $this->logDebug("RCON connection failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new Exception("Failed to connect to RCON server: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Test RCON connection
     * @return array Result with success status and message
     */
    public function testConnection() {
        try {
            $this->logDebug("testConnection() called");
            
            $this->connect();
            
            // Try to get player list as connection test
            $players = $this->rcon->getPlayersArray();
            
            $this->logDebug("testConnection() successful", [
                'player_count' => count($players)
            ]);
            
            return [
                'success' => true,
                'message' => 'Connected successfully to RCON server',
                'player_count' => count($players)
            ];
        } catch (Exception $e) {
            $this->logDebug("testConnection() failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of online players
     * @return array Array of players with enhanced fields
     */
    public function getPlayers() {
        try {
            $this->logDebug("getPlayers() called");
            
            $this->connect();
            
            $this->logDebug("Calling RCON getPlayersArray()");
            $players = $this->rcon->getPlayersArray();
            
            $this->logDebug("Raw RCON response from getPlayersArray()", [
                'player_count' => count($players),
                'raw_data' => $players
            ]);
            
            // Add missing 'time' field that RCON doesn't provide
            // RCON library already returns: num, name, id, ip, ipport, ping, guid
            foreach ($players as &$player) {
                if (!isset($player['time'])) {
                    $player['time'] = 'N/A'; // RCON doesn't track session time
                }
            }
            unset($player); // Break the reference to avoid unexpected behavior
            
            $this->logDebug("Processed player data", [
                'player_count' => count($players),
                'processed_data' => $players
            ]);
            
            return $players;
        } catch (Exception $e) {
            $this->logDebug("getPlayers() failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception("Failed to get player list: " . $e->getMessage());
        }
    }
    
    /**
     * Kick a player from the server
     * @param string $identifier Player name, ID, or Steam ID
     * @param string $reason Kick reason
     * @return bool
     */
    public function kickPlayer($identifier, $reason = '') {
        try {
            $this->connect();
            
            // Try to find player by Steam ID if 17-digit number
            if (preg_match('/^\d{' . self::STEAM_ID64_LENGTH . '}$/', $identifier)) {
                $players = $this->rcon->getPlayersArray();
                foreach ($players as $player) {
                    if (isset($player['guid']) && $player['guid'] === $identifier) {
                        $identifier = $player['num'];
                        break;
                    }
                }
            }
            
            // Execute kick command
            $fullReason = !empty($reason) ? $reason : 'Kicked by admin';
            $result = $this->rcon->kickPlayer($identifier, $fullReason);
            
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
    public function banPlayer($identifier, $reason = '', $duration = 0) {
        try {
            $this->connect();
            
            // For BattlEye, we need the GUID (Steam ID)
            // If identifier looks like a Steam ID, use it directly
            if (preg_match('/^\d{' . self::STEAM_ID64_LENGTH . '}$/', $identifier)) {
                $guid = $identifier;
            } else {
                // Try to find player's GUID from player list
                $players = $this->rcon->getPlayersArray();
                $guid = null;
                
                foreach ($players as $player) {
                    if ($player['num'] == $identifier || 
                        stripos($player['name'], $identifier) !== false) {
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
                $result = $this->rcon->sendCommand("ban {$identifier} {$duration} {$fullReason}");
            } else {
                $result = $this->rcon->sendCommand("ban {$identifier} 0 {$fullReason}");
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
    public function unbanPlayer($steamId) {
        try {
            $this->connect();
            
            // BattlEye unban command uses the GUID (Steam ID)
            // Command format: removeBan <GUID>
            $result = $this->rcon->sendCommand("removeBan {$steamId}");
            
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
    public function sendGlobalMessage($message) {
        try {
            $this->connect();
            $this->rcon->sayGlobal($message);
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
    public function executeCommand($command) {
        try {
            $this->connect();
            return $this->rcon->sendCommand($command);
        } catch (Exception $e) {
            throw new Exception("Failed to execute command: " . $e->getMessage());
        }
    }
    
    /**
     * Get the path to the RCON debug log file
     * @return string Log file path
     */
    public static function getLogFilePath() {
        return self::LOG_FILE;
    }
    
    /**
     * Clear the RCON debug log file
     * @return bool Success status
     */
    public static function clearLog() {
        if (file_exists(self::LOG_FILE)) {
            return unlink(self::LOG_FILE);
        }
        return true;
    }
    
    /**
     * Get the contents of the RCON debug log file
     * @param int $lines Number of lines to retrieve from the end (0 for all)
     * @return string Log contents
     */
    public static function getLog($lines = 0) {
        if (!file_exists(self::LOG_FILE)) {
            return "Log file does not exist yet.";
        }
        
        $content = file_get_contents(self::LOG_FILE);
        
        if ($lines > 0) {
            $logLines = explode("\n", $content);
            $logLines = array_slice($logLines, -$lines);
            return implode("\n", $logLines);
        }
        
        return $content;
    }
}
