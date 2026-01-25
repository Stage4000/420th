<?php
// Staff notes management class

require_once 'db.php';

class StaffNotesManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Validate and trim note text
     * 
     * @param string $noteText Note text to validate
     * @return string Trimmed note text
     * @throws Exception if note text is empty
     */
    private function validateNoteText($noteText) {
        $noteText = trim($noteText);
        
        if (empty($noteText)) {
            throw new Exception("Note text cannot be empty");
        }
        
        return $noteText;
    }
    
    /**
     * Add a staff note to a user
     * 
     * @param int $userId User ID to add note to
     * @param int $createdByUserId User ID creating the note
     * @param string $noteText Note content
     * @return int Note ID
     */
    public function addNote($userId, $createdByUserId, $noteText) {
        $noteText = $this->validateNoteText($noteText);
        
        $this->db->query(
            "INSERT INTO staff_notes (user_id, created_by_user_id, note_text, created_at) 
             VALUES (?, ?, ?, NOW())",
            [$userId, $createdByUserId, $noteText]
        );
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update a staff note
     * 
     * @param int $noteId Note ID to update
     * @param int $updatedByUserId User ID updating the note
     * @param string $noteText New note content
     * @return bool Success
     */
    public function updateNote($noteId, $updatedByUserId, $noteText) {
        $noteText = $this->validateNoteText($noteText);
        
        $result = $this->db->query(
            "UPDATE staff_notes 
             SET note_text = ?, updated_by_user_id = ?, updated_at = NOW() 
             WHERE id = ?",
            [$noteText, $updatedByUserId, $noteId]
        );
        
        return $result > 0; // Return true if at least one row was updated
    }
    
    /**
     * Delete a staff note
     * 
     * @param int $noteId Note ID to delete
     * @return bool Success
     */
    public function deleteNote($noteId) {
        $result = $this->db->query("DELETE FROM staff_notes WHERE id = ?", [$noteId]);
        return $result > 0; // Return true if at least one row was deleted
    }
    
    /**
     * Get all notes for a user
     * 
     * @param int $userId User ID
     * @return array Array of notes with creator/updater info
     */
    public function getUserNotes($userId) {
        return $this->db->fetchAll(
            "SELECT sn.*, 
                    created_by.steam_name as created_by_name,
                    created_by.avatar_url as created_by_avatar,
                    updated_by.steam_name as updated_by_name,
                    updated_by.avatar_url as updated_by_avatar
             FROM staff_notes sn
             JOIN users created_by ON sn.created_by_user_id = created_by.id
             LEFT JOIN users updated_by ON sn.updated_by_user_id = updated_by.id
             WHERE sn.user_id = ?
             ORDER BY sn.created_at DESC",
            [$userId]
        );
    }
    
    /**
     * Get a single note by ID
     * 
     * @param int $noteId Note ID
     * @return array|null Note data or null if not found
     */
    public function getNote($noteId) {
        return $this->db->fetchOne(
            "SELECT sn.*, 
                    created_by.steam_name as created_by_name,
                    updated_by.steam_name as updated_by_name
             FROM staff_notes sn
             JOIN users created_by ON sn.created_by_user_id = created_by.id
             LEFT JOIN users updated_by ON sn.updated_by_user_id = updated_by.id
             WHERE sn.id = ?",
            [$noteId]
        );
    }
    
    /**
     * Count notes for a user
     * 
     * @param int $userId User ID
     * @return int Number of notes
     */
    public function countUserNotes($userId) {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM staff_notes WHERE user_id = ?",
            [$userId]
        );
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Check if a user has any notes
     * 
     * @param int $userId User ID
     * @return bool True if user has notes
     */
    public function hasNotes($userId) {
        return $this->countUserNotes($userId) > 0;
    }
    
    /**
     * Get users with notes (for icon display)
     * 
     * @return array Array of user IDs with note counts
     */
    public function getUsersWithNotes() {
        $results = $this->db->fetchAll(
            "SELECT user_id, COUNT(*) as note_count 
             FROM staff_notes 
             GROUP BY user_id"
        );
        
        $noteCounts = [];
        foreach ($results as $row) {
            $noteCounts[$row['user_id']] = (int)$row['note_count'];
        }
        
        return $noteCounts;
    }
}
