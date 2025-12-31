<?php
// Role management helper functions

require_once 'db.php';

class RoleManager {
    private $db;
    
    // Define staff roles that require the ALL role
    private static $staffRoles = ['ADMIN', 'MODERATOR', 'DEVELOPER'];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Add a role to a user and handle automatic role linking
     * @param int $userId User ID
     * @param int $roleId Role ID to add
     * @param int $grantedBy User ID who granted the role
     * @return bool Success status
     */
    public function addRole($userId, $roleId, $grantedBy = null) {
        try {
            // Start transaction
            $this->db->getConnection()->beginTransaction();
            
            // Add the requested role
            $this->db->execute(
                "INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)",
                [$userId, $roleId, $grantedBy]
            );
            
            // Check if this is a staff role
            $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
            if ($role && in_array($role['name'], self::$staffRoles)) {
                // Automatically add the ALL role
                $allRole = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'ALL'");
                if ($allRole) {
                    $this->db->execute(
                        "INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)",
                        [$userId, $allRole['id'], $grantedBy]
                    );
                }
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
     * @param int $roleId Role ID to remove
     * @return bool Success status
     */
    public function removeRole($userId, $roleId) {
        try {
            // Start transaction
            $this->db->getConnection()->beginTransaction();
            
            // Remove the requested role
            $this->db->execute(
                "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
                [$userId, $roleId]
            );
            
            // Check if this is the ALL role being removed
            $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
            if ($role && $role['name'] === 'ALL') {
                // When removing ALL, also remove all staff roles
                $staffRoleIds = $this->db->fetchAll(
                    "SELECT id FROM roles WHERE name IN ('ADMIN', 'MODERATOR', 'DEVELOPER')"
                );
                foreach ($staffRoleIds as $staffRole) {
                    $this->db->execute(
                        "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
                        [$userId, $staffRole['id']]
                    );
                }
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
            $fixed = 0;
            
            // Get all users with staff roles
            $users = $this->db->fetchAll("
                SELECT DISTINCT ur.user_id, ur.granted_by
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE r.name IN ('ADMIN', 'MODERATOR', 'DEVELOPER')
            ");
            
            $allRole = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'ALL'");
            if (!$allRole) {
                return 0;
            }
            
            foreach ($users as $user) {
                // Check if they have the ALL role
                $hasAll = $this->db->fetchOne(
                    "SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?",
                    [$user['user_id'], $allRole['id']]
                );
                
                if (!$hasAll) {
                    // Add ALL role
                    $this->db->execute(
                        "INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)",
                        [$user['user_id'], $allRole['id'], $user['granted_by']]
                    );
                    $fixed++;
                }
            }
            
            return $fixed;
            
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
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name IN ('ADMIN', 'MODERATOR', 'DEVELOPER')
        ", [$userId]);
        
        return $result && $result['count'] > 0;
    }
}
