<?php
// Stats Manager for handling player statistics

require_once 'db.php';

class StatsManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Add a player stat
     * @param string $steamId Steam ID of the player
     * @param string $name Player name (nullable)
     * @param string $statId Stat ID
     * @param string $serverId Server ID
     * @param int $amount Amount to add
     * @return bool Success status
     */
    public function addPlayerStat($steamId, $name, $statId, $serverId, $amount) {
        try {
            // Call the stored procedure
            $stmt = $this->db->getConnection()->prepare("CALL add_player_stat(?, ?, ?, ?, ?)");
            $stmt->execute([$steamId, $name, $statId, $serverId, $amount]);
            return true;
        } catch (PDOException $e) {
            error_log("Error adding player stat: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get leaderboard for a specific stat and time period
     * @param string $statId Stat ID to get leaderboard for
     * @param string $period Time period (daily, weekly, monthly, alltime)
     * @param string $serverId Server ID (default: 'main')
     * @param int $limit Number of results to return (default: 50)
     * @return array Leaderboard data
     */
    public function getLeaderboard($statId, $period = 'alltime', $serverId = 'main', $limit = 50) {
        $validPeriods = ['daily', 'weekly', 'monthly', 'alltime'];
        if (!in_array($period, $validPeriods)) {
            $period = 'alltime';
        }
        
        $table = 'stat_player_' . $period;
        
        try {
            // For daily, weekly, monthly - get most recent period
            $dateCondition = '';
            if ($period === 'daily') {
                $dateCondition = 'AND created_at = CURRENT_DATE';
            } elseif ($period === 'weekly') {
                $dateCondition = 'AND created_at = YEARWEEK(CURRENT_DATE)';
            } elseif ($period === 'monthly') {
                $dateCondition = 'AND created_at = EXTRACT(YEAR_MONTH FROM CURRENT_DATE)';
            }
            
            $sql = "SELECT sp.steam_id, sp.name, s.amount
                    FROM $table s
                    JOIN stat_player sp ON s.steam_id = sp.steam_id
                    WHERE s.stat_id = ? AND s.server_id = ? $dateCondition
                    ORDER BY s.amount DESC
                    LIMIT ?";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$statId, $serverId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting leaderboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available stats
     * @return array Array of stats
     */
    public function getAllStats() {
        try {
            return $this->db->fetchAll("SELECT * FROM stat ORDER BY stat_id");
        } catch (PDOException $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available servers
     * @return array Array of servers
     */
    public function getAllServers() {
        try {
            return $this->db->fetchAll("SELECT * FROM stat_server ORDER BY server_id");
        } catch (PDOException $e) {
            error_log("Error getting servers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get player stats
     * @param string $steamId Steam ID of the player
     * @param string $period Time period (daily, weekly, monthly, alltime)
     * @param string $serverId Server ID (default: 'main')
     * @return array Player stats
     */
    public function getPlayerStats($steamId, $period = 'alltime', $serverId = 'main') {
        $validPeriods = ['daily', 'weekly', 'monthly', 'alltime'];
        if (!in_array($period, $validPeriods)) {
            $period = 'alltime';
        }
        
        $table = 'stat_player_' . $period;
        
        try {
            // For daily, weekly, monthly - get most recent period
            $dateCondition = '';
            if ($period === 'daily') {
                $dateCondition = 'AND created_at = CURRENT_DATE';
            } elseif ($period === 'weekly') {
                $dateCondition = 'AND created_at = YEARWEEK(CURRENT_DATE)';
            } elseif ($period === 'monthly') {
                $dateCondition = 'AND created_at = EXTRACT(YEAR_MONTH FROM CURRENT_DATE)';
            }
            
            $sql = "SELECT s.stat_id, st.score_multiplier, s.amount
                    FROM $table s
                    JOIN stat st ON s.stat_id = st.stat_id
                    WHERE s.steam_id = ? AND s.server_id = ? $dateCondition
                    ORDER BY s.stat_id";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$steamId, $serverId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting player stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if stats tables exist
     * @return bool True if stats tables exist
     */
    public function statsTablesExist() {
        try {
            $this->db->fetchOne("SELECT 1 FROM stat_player LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
