<?php
// Role management helper functions

require_once 'db.php';

class RoleManager {
    private $db;
    
    // Define staff roles that require the ALL role
    private static $staffRoles = ['ADMIN', 'MODERATOR', 'DEVELOPER', 'CURATOR'];
    
    // Role name to column mapping
    private static $roleColumnMap = [
        'S3' => 'role_s3',
        'CAS' => 'role_cas',
        'S1' => 'role_s1',
        'OPFOR' => 'role_opfor',
        'ALL' => 'role_all',
        'ADMIN' => 'role_admin',
        'MODERATOR' => 'role_moderator',
        'TRUSTED' => 'role_trusted',
        'MEDIA' => 'role_media',
        'CURATOR' => 'role_curator',
        'DEVELOPER' => 'role_developer',
        'PANEL' => 'role_panel',
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Add a role to a user and handle automatic role linking
     * @param int $userId User ID
     * @param string $roleName Role name (e.g., 'ADMIN', 'S3')
     * @return bool Success status
     */
    public function addRole($userId, $roleName) {
        try {
            if (!isset(self::$roleColumnMap[$roleName])) {
                throw new Exception("Invalid role name: $roleName");
            }
            
            $column = self::$roleColumnMap[$roleName];
            
            // Start transaction
            $this->db->getConnection()->beginTransaction();
            
            // ADMIN and MODERATOR are mutually exclusive
            if ($roleName === 'ADMIN') {
                // Remove MODERATOR if adding ADMIN
                $this->db->execute(
                    "UPDATE users SET role_moderator = 0 WHERE id = ?",
                    [$userId]
                );
            } elseif ($roleName === 'MODERATOR') {
                // Remove ADMIN if adding MODERATOR
                $this->db->execute(
                    "UPDATE users SET role_admin = 0 WHERE id = ?",
                    [$userId]
                );
            }
            
            // Add the requested role
            $this->db->execute(
                "UPDATE users SET $column = 1 WHERE id = ?",
                [$userId]
            );
            
            // Check if this is a staff role - automatically add ALL role
            if (in_array($roleName, self::$staffRoles)) {
                $this->db->execute(
                    "UPDATE users SET role_all = 1 WHERE id = ?",
                    [$userId]
                );
            }
            
            // Commit transaction
            $this->db->getConnection()->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->getConnection()->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Remove a role from a user and handle automatic role unlinking
     * @param int $userId User ID
     * @param string $roleName Role name to remove
     * @return bool Success status
     */
    public function removeRole($userId, $roleName) {
        try {
            if (!isset(self::$roleColumnMap[$roleName])) {
                throw new Exception("Invalid role name: $roleName");
            }
            
            $column = self::$roleColumnMap[$roleName];
            
            // Start transaction
            $this->db->getConnection()->beginTransaction();
            
            // Remove the requested role
            $this->db->execute(
                "UPDATE users SET $column = 0 WHERE id = ?",
                [$userId]
            );
            
            // Check if this is a staff role - remove ALL if no other staff roles remain
            if (in_array($roleName, self::$staffRoles)) {
                if (!$this->hasStaffRole($userId)) {
                    $this->db->execute(
                        "UPDATE users SET role_all = 0 WHERE id = ?",
                        [$userId]
                    );
                }
            }
            
            // Check if this is the ALL role being removed - remove all staff roles too
            if ($roleName === 'ALL') {
                $this->db->execute(
                    "UPDATE users SET role_admin = 0, role_moderator = 0, role_developer = 0, role_curator = 0 WHERE id = ?",
                    [$userId]
                );
            }
            
            // Commit transaction
            $this->db->getConnection()->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->getConnection()->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Ensure all staff roles have the ALL role
     * This can be run to fix any existing data
     * @return int Number of users fixed
     */
    public function syncStaffRoles() {
        try {
            // Get all users with at least one staff role but missing ALL
            $result = $this->db->execute("
                UPDATE users 
                SET role_all = 1 
                WHERE (role_admin = 1 OR role_moderator = 1 OR role_developer = 1 OR role_curator = 1) 
                AND role_all = 0
            ");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error syncing staff roles: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if a user has any staff roles
     * @param int $userId User ID
     * @return bool
     */
    public function hasStaffRole($userId) {
        $result = $this->db->fetchOne("
            SELECT (role_admin + role_moderator + role_developer + role_curator) as staff_count
            FROM users 
            WHERE id = ?
        ", [$userId]);
        
        return $result && $result['staff_count'] > 0;
    }
    
    /**
     * Get role name by ID (for backward compatibility)
     * @param int $roleId
     * @return string|null
     */
    public function getRoleName($roleId) {
        $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
        return $role ? $role['name'] : null;
    }
    
    /**
     * Get role ID by name (for backward compatibility)
     * @param string $roleName
     * @return int|null
     */
    public function getRoleId($roleName) {
        $role = $this->db->fetchOne("SELECT id FROM roles WHERE name = ?", [$roleName]);
        return $role ? $role['id'] : null;
    }
}
