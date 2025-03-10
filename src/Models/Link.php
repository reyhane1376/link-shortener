<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use App\Utils\HashFunc;
use App\Utils\Cache;
use PDOException;

class Link {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = new Database();
        $this->cache = new Cache();
    }
    
    public function create($userId, $originalUrl, $customDomain = null) {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();

            $length = 6;
            $shortCode = $this->createShortCode($originalUrl, $length);

            if (!$shortCode) {
                // If we failed to create a short code with the default length,
                // try with an increased length
                $maxLength = 10;
                
                while (!$shortCode && $length < $maxLength) {
                    $length++;
                    $shortCode = $this->createShortCode($originalUrl, $length);
                }
                
                if (!$shortCode) {
                    $conn->rollBack();
                    throw new AppException("Failed to generate a unique code after multiple attempts");
                }
            }
            
            $linkId = $this->db->insert('links', 
                ['user_id', 'original_url', 'short_code', 'custom_domain'], 
                [$userId, $originalUrl, $shortCode, $customDomain]
            );
            
            $conn->commit();
            
            // Store the short code mapping in cache
            $cacheKeyShortCode = "short_code_{$shortCode}";
            $link = $this->getById($linkId);
            $this->cache->set($cacheKeyShortCode, $link, 86400);
            
            return $link;
            
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
        // Get the link first to get the short code
        $link = $this->getById($id, $userId);
        $shortCode = $link['short_code'];
        
        // Delete the link
        $this->db->execute("DELETE FROM links WHERE id = ? AND user_id = ?", [$id, $userId]);
        
        // Delete from cache
        $cacheKeyShortCode = "short_code_{$shortCode}";
        $this->cache->delete($cacheKeyShortCode);
        
        // Keep the short_code_exists entry to prevent reuse for some time
        
        return true;
    }
    
    public function incrementClicks($id) {
        $this->db->execute("UPDATE links SET clicks = clicks + 1 WHERE id = ?", [$id]);
    }

    private function createShortCode($originalUrl, $length = 6)
    {
        $maxAttempts = 5;
        $attempts = 0;
        $shortCode = null;
        
        while ($attempts < $maxAttempts) {
            // Generate a potential short code
            $potentialCode = HashFunc::generateShortCode($originalUrl . $attempts, $length);
            $attempts++;
            
            // Check if code exists in Redis first
            $cacheKey = "short_code_{$potentialCode}";
            $existsInCache = $this->cache->get($cacheKey);
            
            if ($existsInCache === null) {
                // Not in cache, check database
                $exists = $this->db->select("SELECT id FROM links WHERE short_code = ?", [$potentialCode])->fetch();
                
                if (!$exists) {
                    // Code is unique, we can use it
                    $shortCode = $potentialCode;
                    // Add to cache to mark it as used
                    $this->cache->set($cacheKey, true, 86400);
                    break;
                } else {
                    // Mark as existing in cache for future checks
                    $this->cache->set($cacheKey, true, 86400);
                }
            }
            // If exists in cache, just continue to next attempt
        }
        
        return $shortCode;
    }
}
