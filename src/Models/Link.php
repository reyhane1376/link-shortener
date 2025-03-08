<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use App\Utils\HashTable;
use PDOException;

class Link {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($userId, $originalUrl, $customDomain = null) {

        // Validate URL
        if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            throw new AppException("Invalid URL format");
        }

        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
        
            
            $existingCodes = $this->db->select("SELECT short_code FROM links FOR UPDATE")->fetchAll(\PDO::FETCH_COLUMN);
            
            $shortCode = HashTable::generateUniqueCode($originalUrl, $existingCodes);
            
            $exists = $this->db->select("SELECT id FROM links WHERE short_code = ? FOR UPDATE", [$shortCode])->fetch();
            
            if ($exists) {
                $conn->rollBack();
                throw new AppException("Failed to generate a unique code");
            }
            
            $linkId = $this->db->insert('links', 
                ['user_id', 'original_url', 'short_code', 'custom_domain'], 
                [$userId, $originalUrl, $shortCode, $customDomain]
            );
            
            $conn->commit();
            
            return $this->getById($linkId);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            throw new AppException("Database error: " . $e->getMessage());
        }
    }
    
    public function getById($id, $userId = null) {
        $params = [$id];
        $sql = "SELECT * FROM links WHERE id = ?";
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $link = $this->db->select($sql, $params)->fetch();
        
        if (!$link) {
            throw new AppException("Link not found", 404);
        }
        
        return $link;
    }
    
    public function getByCode($shortCode) {
        $link = $this->db->select("SELECT * FROM links WHERE short_code = ?", [$shortCode])->fetch();
        
    if (!$link) {
            throw new AppException("Link not found", 404);
        }
        
        return $link;
    }
    
    public function getAll($userId) {
        return $this->db->select("SELECT * FROM links WHERE user_id = ? ORDER BY created_at DESC", [$userId])->fetchAll();
    }
    
    public function update($id, $userId, $data) {
        // Verify the link exists and belongs to the user
        $link = $this->getById($id, $userId);
        
        // Prepare update fields
        $updateFields = [];
        $params = [];
        
        if (isset($data['original_url'])) {
            if (!filter_var($data['original_url'], FILTER_VALIDATE_URL)) {
                throw new AppException("Invalid URL format");
            }
            $updateFields[] = "original_url = ?";
            $params[] = $data['original_url'];
        }
        
        if (isset($data['custom_domain'])) {
            $updateFields[] = "custom_domain = ?";
            $params[] = $data['custom_domain'];
        }
        
        if (empty($updateFields)) {
            return $link; // Nothing to update
        }
        
        // Add the ID and user_id to params
        $params[] = $id;
        $params[] = $userId;
        
        // Execute the update
        $sql = "UPDATE links SET " . implode(", ", $updateFields) . " WHERE id = ? AND user_id = ?";
        $this->db->execute($sql, $params);
        
        // Return the updated link
        return $this->getById($id, $userId);
    }
    
    public function delete($id, $userId) {
        // Verify the link exists and belongs to the user
        $this->getById($id, $userId);
        
        // Delete the link
        $this->db->execute("DELETE FROM links WHERE id = ? AND user_id = ?", [$id, $userId]);
        
        return true;
    }
    
    public function incrementClicks($id) {
        $this->db->execute("UPDATE links SET clicks = clicks + 1 WHERE id = ?", [$id]);
    }
}
