<?php
// Whitelist ban management class

require_once 'db.php';
require_once 'role_manager.php';

class BanManager {
    private $db;
    private $roleManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->roleManager = new RoleManager();
    }
    
    /**
     * Issue a whitelist ban to a user
     * Removes S3 and/or CAS roles based on ban type
     * 
     * @param int $userId User ID to ban
     * @param int $bannedByUserId User ID issuing the ban
     * @param string $banType Ban type: 'S3', 'CAS', or 'BOTH'
     * @param string $reason Ban reason
     * @param string|null $expiresAt Ban expiration (null for indefinite)
     * @return bool
     */
    public function banUser($userId, $bannedByUserId, $banType = 'BOTH', $reason = '', $expiresAt = null) {
        try {
            // Validate ban type
            if (!in_array($banType, ['S3', 'CAS', 'BOTH'])) {
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
            
            // Create new ban record
            $this->db->query(
                "INSERT INTO whitelist_bans (user_id, banned_by_user_id, ban_type, ban_reason, ban_date, ban_expires) 
                 VALUES (?, ?, ?, ?, NOW(), ?)",
                [$userId, $bannedByUserId, $banType, $reason, $expiresAt]
            );
            
            // Remove roles based on ban type (don't use RoleManager to avoid nested transactions)
            if ($banType === 'BOTH') {
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
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Unban a user
     * 
     * @param int $userId User ID to unban
     * @param int $unbannedByUserId User ID removing the ban
     * @param string $reason Unban reason
     * @return bool
     */
    public function unbanUser($userId, $unbannedByUserId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Update active ban
            $result = $this->db->query(
                "UPDATE whitelist_bans 
                 SET is_active = 0, unbanned_by_user_id = ?, unban_date = NOW(), unban_reason = ?
                 WHERE user_id = ? AND is_active = 1",
                [$unbannedByUserId, $reason, $userId]
            );
            
            $this->db->commit();
            return true;
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
