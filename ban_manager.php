<?php
// Whitelist ban management class

require_once 'db.php';
require_once 'role_manager.php';
require_once 'rcon_manager.php';

class BanManager {
    private $db;
    private $roleManager;
    private $rconManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->roleManager = new RoleManager();
        $this->rconManager = new RconManager();
    }
    
    /**
     * Issue a whitelist ban to a user
     * Removes S3 and/or CAS roles based on ban type
     * Optionally kicks or bans from game server via RCON
     * 
     * @param int $userId User ID to ban
     * @param int $bannedByUserId User ID issuing the ban
     * @param string $banType Ban type: 'S3', 'CAS', or 'Whitelist'
     * @param string $reason Ban reason
     * @param string|null $expiresAt Ban expiration (null for indefinite)
     * @param bool $serverKick Whether to kick from game server
     * @param bool $serverBan Whether to ban from game server
     * @return array Result with success status and messages
     */
    public function banUser($userId, $bannedByUserId, $banType = 'Whitelist', $reason = '', $expiresAt = null, $serverKick = false, $serverBan = false) {
        $messages = [];
        
        try {
            // Validate ban type
            if (!in_array($banType, ['S3', 'CAS', 'Whitelist'])) {
                throw new Exception("Invalid ban type");
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Check if user exists
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Check if user has ALL flag (shouldn't be able to ban staff)
            if ($user['role_all']) {
                throw new Exception("Cannot ban users with ALL (Staff) role");
            }
            
            // Deactivate any existing active bans
            $this->db->query(
                "UPDATE whitelist_bans SET is_active = 0 WHERE user_id = ? AND is_active = 1",
                [$userId]
            );
            
            // Create new ban record with server action flags
            $this->db->query(
                "INSERT INTO whitelist_bans (user_id, banned_by_user_id, ban_type, server_kick, server_ban, ban_reason, ban_date, ban_expires) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
                [$userId, $bannedByUserId, $banType, $serverKick ? 1 : 0, $serverBan ? 1 : 0, $reason, $expiresAt]
            );
            
            // Remove roles based on ban type (don't use RoleManager to avoid nested transactions)
            if ($banType === 'Whitelist') {
                $this->db->query(
                    "UPDATE users SET role_s3 = 0, role_cas = 0 WHERE id = ?",
                    [$userId]
                );
            } elseif ($banType === 'S3') {
                $this->db->query(
                    "UPDATE users SET role_s3 = 0 WHERE id = ?",
                    [$userId]
                );
            } elseif ($banType === 'CAS') {
                $this->db->query(
                    "UPDATE users SET role_cas = 0 WHERE id = ?",
                    [$userId]
                );
            }
            
            $this->db->commit();
            $messages[] = "Whitelist ban issued successfully";
            
            // Handle server actions via RCON
            if (($serverKick || $serverBan) && $this->rconManager->isEnabled()) {
                $steamId = $user['steam_id'];
                
                try {
                    if ($serverBan) {
                        // Ban from server (also kicks)
                        $this->rconManager->banPlayer($steamId, $reason);
                        $messages[] = "Player banned from game server";
                    } elseif ($serverKick) {
                        // Just kick from server
                        $this->rconManager->kickPlayer($steamId, $reason);
                        $messages[] = "Player kicked from game server";
                    }
                } catch (Exception $e) {
                    $messages[] = "Warning: Server action failed - " . $e->getMessage();
                }
            } elseif (($serverKick || $serverBan) && !$this->rconManager->isEnabled()) {
                $messages[] = "Warning: RCON is not enabled, server action skipped";
            }
            
            return [
                'success' => true,
                'messages' => $messages
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Unban a user
     * Removes whitelist ban and optionally removes server ban via RCON
     * 
     * @param int $userId User ID to unban
     * @param int $unbannedByUserId User ID removing the ban
     * @param string $reason Unban reason
     * @return array Result with success status and messages
     */
    public function unbanUser($userId, $unbannedByUserId, $reason = '') {
        $messages = [];
        
        try {
            $this->db->beginTransaction();
            
            // Get the active ban info to check if server ban was issued
            $activeBan = $this->db->fetchOne(
                "SELECT wb.*, u.steam_id 
                 FROM whitelist_bans wb
                 JOIN users u ON wb.user_id = u.id
                 WHERE wb.user_id = ? AND wb.is_active = 1
                 LIMIT 1",
                [$userId]
            );
            
            // Update active ban
            $result = $this->db->query(
                "UPDATE whitelist_bans 
                 SET is_active = 0, unbanned_by_user_id = ?, unban_date = NOW(), unban_reason = ?
                 WHERE user_id = ? AND is_active = 1",
                [$unbannedByUserId, $reason, $userId]
            );
            
            $this->db->commit();
            $messages[] = "Whitelist unban successful";
            
            // If the ban included a server ban, remove it via RCON
            if ($activeBan && !empty($activeBan['server_ban']) && $this->rconManager->isEnabled()) {
                $steamId = $activeBan['steam_id'];
                
                try {
                    $this->rconManager->unbanPlayer($steamId);
                    $messages[] = "Player unbanned from game server";
                } catch (Exception $e) {
                    $messages[] = "Warning: Server unban failed - " . $e->getMessage();
                }
            } elseif ($activeBan && !empty($activeBan['server_ban']) && !$this->rconManager->isEnabled()) {
                $messages[] = "Warning: RCON is not enabled, server unban skipped";
            }
            
            return [
                'success' => true,
                'messages' => $messages
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Check if a user is currently banned
     * Automatically expires old bans
     * 
     * @param int $userId User ID to check
     * @return array|bool Ban info array if banned, false if not banned
     */
    public function isUserBanned($userId) {
        // First, expire any old bans
        $this->expireOldBans();
        
        // Check for active ban
        $ban = $this->db->fetchOne(
            "SELECT wb.*, 
                    banned_by.steam_name as banned_by_name,
                    banned_user.steam_name as banned_user_name
             FROM whitelist_bans wb
             JOIN users banned_by ON wb.banned_by_user_id = banned_by.id
             JOIN users banned_user ON wb.user_id = banned_user.id
             WHERE wb.user_id = ? AND wb.is_active = 1
             LIMIT 1",
            [$userId]
        );
        
        return $ban ? $ban : false;
    }
    
    /**
     * Get active ban info by steam ID
     * 
     * @param string $steamId Steam ID to check
     * @return array|bool Ban info array if banned, false if not banned
     */
    public function isUserBannedBySteamId($steamId) {
        // First, expire any old bans
        $this->expireOldBans();
        
        $user = $this->db->fetchOne("SELECT id FROM users WHERE steam_id = ?", [$steamId]);
        if (!$user) {
            return false;
        }
        
        return $this->isUserBanned($user['id']);
    }
    
    /**
     * Get all bans for a user (active and inactive)
     * 
     * @param int $userId User ID
     * @return array Array of ban records
     */
    public function getUserBans($userId) {
        return $this->db->fetchAll(
            "SELECT wb.*, 
                    banned_by.steam_name as banned_by_name,
                    unbanned_by.steam_name as unbanned_by_name
             FROM whitelist_bans wb
             JOIN users banned_by ON wb.banned_by_user_id = banned_by.id
             LEFT JOIN users unbanned_by ON wb.unbanned_by_user_id = unbanned_by.id
             WHERE wb.user_id = ?
             ORDER BY wb.ban_date DESC",
            [$userId]
        );
    }
    
    /**
     * Expire old bans automatically
     * Called before ban checks
     * 
     * @return int Number of bans expired
     */
    private function expireOldBans() {
        $result = $this->db->query(
            "UPDATE whitelist_bans 
             SET is_active = 0 
             WHERE is_active = 1 
             AND ban_expires IS NOT NULL 
             AND ban_expires < NOW()"
        );
        
        return $result;
    }
    
    /**
     * Get all active bans with pagination
     * 
     * @param int $page Page number
     * @param int $perPage Results per page
     * @param string $search Optional search term
     * @return array Array with 'bans' and 'total' keys
     */
    public function getAllBans($page = 1, $perPage = 20, $search = '') {
        $this->expireOldBans();
        
        $offset = ($page - 1) * $perPage;
        $whereClause = "WHERE wb.is_active = 1";
        $params = [];
        
        if (!empty($search)) {
            $whereClause .= " AND (banned_user.steam_name LIKE ? OR banned_user.steam_id LIKE ?)";
            $params = ["%$search%", "%$search%"];
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM whitelist_bans wb
                       JOIN users banned_user ON wb.user_id = banned_user.id
                       $whereClause";
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['total'];
        
        // Get bans
        $query = "SELECT wb.*, 
                         banned_user.steam_name as banned_user_name,
                         banned_user.steam_id as banned_user_steam_id,
                         banned_user.avatar_url as banned_user_avatar,
                         banned_by.steam_name as banned_by_name
                  FROM whitelist_bans wb
                  JOIN users banned_user ON wb.user_id = banned_user.id
                  JOIN users banned_by ON wb.banned_by_user_id = banned_by.id
                  $whereClause
                  ORDER BY wb.ban_date DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $bans = $this->db->fetchAll($query, $params);
        
        return [
            'bans' => $bans,
            'total' => $total
        ];
    }
}
