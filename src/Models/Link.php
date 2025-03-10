<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use App\Utils\HashFunc;
use App\Utils\Cache;
use PDOException;

class Link
{
    private Database $db;
    private Cache $cache;
    private const DEFAULT_CODE_LENGTH = 6;
    private const MAX_CODE_LENGTH = 10;
    private const MAX_ATTEMPTS = 5;
    private const CACHE_TTL = 86400; // 24 hours in seconds

    public function __construct()
    {
        $this->db = new Database();
        $this->cache = new Cache();
    }

    public function create(int $userId, string $originalUrl, ?string $customDomain = null): array
    {
        $conn = $this->db->getConnection();
    
        $length = self::DEFAULT_CODE_LENGTH;
        $shortCode = $this->createShortCode($originalUrl, $length);
    
        if (!$shortCode) {
            while (!$shortCode && $length < self::MAX_CODE_LENGTH) {
                $length++;
                $shortCode = $this->createShortCode($originalUrl, $length);
            }
    
            if (!$shortCode) {
                throw new AppException("Failed to generate a unique code after multiple attempts");
            }
        }
    
        try {
            $sql = "INSERT INTO links (user_id, original_url, short_code, custom_domain, created_at) 
                    VALUES (:user_id, :original_url, :short_code, :custom_domain, NOW()) 
                    RETURNING id";
    
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':original_url' => $originalUrl,
                ':short_code' => $shortCode,
                ':custom_domain' => $customDomain
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $linkId = $result['id'];
    
            $cacheKey = "short_code_{$shortCode}";
            $link = $this->getById($linkId);
            $this->cache->set($cacheKey, $link, self::CACHE_TTL);
    
            return $link;
        } catch (PDOException $e) {
            throw new AppException("Database error: " . $e->getMessage());
        }
    }

    public function getById(int $id, ?int $userId = null): array
    {
        $sql = "SELECT * FROM links WHERE id = :id";
        $params = [':id' => $id];

        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $link = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$link) {
            throw new AppException("Link not found", 404);
        }

        return $link;
    }

    public function getByCode(string $shortCode): array
    {
        $sql = "SELECT * FROM links WHERE short_code = :short_code";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':short_code' => $shortCode]);
        $link = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$link) {
            throw new AppException("Link not found", 404);
        }

        return $link;
    }

    public function getAll(int $userId): array
    {
        $sql = "SELECT * FROM links WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function update(int $id, int $userId, array $data): array
    {
        $link = $this->getById($id, $userId);
        
        $updateFields = [];
        $params = [];

        if (isset($data['original_url'])) {
            if (!filter_var($data['original_url'], FILTER_VALIDATE_URL)) {
                throw new AppException("Invalid URL format");
            }
            $updateFields[] = "original_url = :original_url";
            $params[':original_url'] = $data['original_url'];
        }

        if (isset($data['custom_domain'])) {
            $updateFields[] = "custom_domain = :custom_domain";
            $params[':custom_domain'] = $data['custom_domain'];
        }

        if (empty($updateFields)) {
            return $link;
        }

        $updateFields[] = "updated_at = NOW()";
        $params[':id'] = $id;
        $params[':user_id'] = $userId;

        $sql = "UPDATE links SET " . implode(", ", $updateFields) . 
               " WHERE id = :id AND user_id = :user_id RETURNING *";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $updatedLink = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$updatedLink) {
            throw new AppException("Failed to update link", 500);
        }

        return $updatedLink;
    }

    public function delete(int $id, int $userId): bool
    {
        $link = $this->getById($id, $userId);
        $shortCode = $link['short_code'];

        $sql = "DELETE FROM links WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new AppException("Failed to delete link", 500);
        }

        $cacheKey = "short_code_{$shortCode}";
        $this->cache->delete($cacheKey);

        return true;
    }

    public function incrementClicks(int $id): void
    {
        $sql = "UPDATE links SET clicks = clicks + 1 WHERE id = :id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':id' => $id]);
    }

    private function createShortCode(string $originalUrl, int $length): ?string
    {
        $attempts = 0;
        $shortCode = null;

        while ($attempts < self::MAX_ATTEMPTS) {
            $potentialCode = HashFunc::generateShortCode($originalUrl . $attempts, $length);
            $attempts++;

            $cacheKey = "short_code_{$potentialCode}";
            $existsInCache = $this->cache->get($cacheKey);

            if ($existsInCache === null) {
                $sql = "SELECT id FROM links WHERE short_code = :short_code";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute([':short_code' => $potentialCode]);
                $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$exists) {
                    $shortCode = $potentialCode;
                    $this->cache->set($cacheKey, true, self::CACHE_TTL);
                    break;
                }
                $this->cache->set($cacheKey, true, self::CACHE_TTL);
            }
        }

        return $shortCode;
    }
}