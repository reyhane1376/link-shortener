<?php
namespace App\Controllers;

use App\Exceptions\AppException;
use App\Models\Link;
use App\Helpers\helpers;
use App\Utils\Cache;

class LinkController {
    private $linkModel;
    private $cache;
    
    public function __construct() {
        $this->linkModel = new Link();
        $this->cache = new Cache();
    }
    
    private function authenticate() {
        return authenticate();
    }
    
    public function getLinks() {
        try {
            $userId = $this->authenticate();

            $cacheKey = "links_{$userId}";
            $links = $this->cache->get($cacheKey);
            
            if ($links === null) {
                $links = $this->linkModel->getAll($userId);
                
                $this->cache->set($cacheKey, $links, 3600);
            }
            
            // Format the response
            $response = array_map(function($link) {
                return [
                    'id'            => $link['id'],
                    'original_url'  => $link['original_url'],
                    'short_code'    => $link['short_code'],
                    'custom_domain' => $link['custom_domain'],
                    'short_url'     => $this->formatShortUrl($link['short_code'], $link['custom_domain']),
                    'clicks'        => $link['clicks'],
                    'created_at'    => $link['created_at']
                ];
            }, $links);
            
            echo json_encode(['links' => $response]);
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function getLink($id) {
        try {
            $userId = $this->authenticate();

            $cacheKey = "link_{$userId}_{$id}";
            $link = $this->cache->get($cacheKey);
            
            if ($link === null) {
                $link = $this->linkModel->getById($id, $userId);
                
                $this->cache->set($cacheKey, $link, 3600);
            }
            
            // Format the response
            $response = [
                'id'            => $link['id'],
                'original_url'  => $link['original_url'],
                'short_code'    => $link['short_code'],
                'custom_domain' => $link['custom_domain'],
                'short_url'     => $this->formatShortUrl($link['short_code'], $link['custom_domain']),
                'clicks'        => $link['clicks'],
                'created_at'    => $link['created_at']
            ];
            
            echo json_encode(['link' => $response]);
            
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function createLink() {
        try {
            $userId = $this->authenticate();
            
            // Get input data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['original_url'])) {
                throw new AppException("Original URL is required");
            }
            
            $originalUrl = $data['original_url'];

            // Validate URL more strictly
            if (!filter_var($originalUrl, FILTER_VALIDATE_URL) || 
                !preg_match('/^https?:\/\//', $originalUrl)) {
                    throw new AppException("Invalid URL format. URL must start with http:// or https://");
            }

            // Validate custom domain if provided
            $customDomain = null;
            if (isset($data['custom_domain']) && !empty($data['custom_domain'])) {
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/', $data['custom_domain'])) {
                    throw new AppException("Invalid custom domain format");
                }
                $customDomain = htmlspecialchars($data['custom_domain'], ENT_QUOTES, 'UTF-8');
            }
            
            // Create the link
            $link = $this->linkModel->create($userId, $originalUrl, $customDomain);

            $cacheKey = "link_{$userId}_{$link['id']}";
            $this->cache->set($cacheKey, $link);

            $cacheKeyLinks = "links_{$userId}";
            $this->cache->delete($cacheKeyLinks);
            
            // Format the response
            $response = [
                'id'            => $link['id'],
                'original_url'  => $link['original_url'],
                'short_code'    => $link['short_code'],
                'custom_domain' => $link['custom_domain'],
                'short_url'     => $this->formatShortUrl($link['short_code'], $link['custom_domain']),
                'clicks'        => $link['clicks'],
                'created_at'    => $link['created_at']
            ];
            
            http_response_code(201);
            echo json_encode(['link' => $response]);
            
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function updateLink($id) {
        try {
            $userId = $this->authenticate();
            
            // Get input data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data)) {
                throw new AppException("No data provided");
            }
            
            // Update the link
            $link = $this->linkModel->update($id, $userId, $data);

            $cacheKey = "link_{$userId}_{$link['id']}";
            $this->cache->set($cacheKey, $link);

            $cacheKeyLinks = "links_{$userId}";
            $this->cache->delete($cacheKeyLinks);
            
            // Format the response
            $response = [
                'id'            => $link['id'],
                'original_url'  => $link['original_url'],
                'short_code'    => $link['short_code'],
                'custom_domain' => $link['custom_domain'],
                'short_url'     => $this->formatShortUrl($link['short_code'], $link['custom_domain']),
                'clicks'        => $link['clicks'],
                'created_at'    => $link['created_at']
            ];
            
            echo json_encode(['link' => $response]);
            
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function deleteLink($id) {
        try {
            $userId = $this->authenticate();

            $this->linkModel->delete($id, $userId);

            $cacheKey = "link_{$userId}_{$id}";
            $this->cache->delete($cacheKey);

            $cacheKeyLinks = "links_{$userId}";
            $this->cache->delete($cacheKeyLinks);

            $response = [
                'message' => 'Link successfully deleted.',
            ];
            
            http_response_code(201);
            echo json_encode(['messages' => $response]);
            
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function redirect($shortCode) {
        try {
            $cacheKey = "short_code_{$shortCode}";
            $link = $this->cache->get($cacheKey);

                    
            if ($link === null) {
                $link = $this->linkModel->getByCode($shortCode);
                $this->cache->set($cacheKey, $link, 3600);
            }

            $url = $link['original_url'];
            if (!filter_var($url, FILTER_VALIDATE_URL) || 
                !preg_match('/^https?:\/\//', $url)) {
                throw new AppException("Invalid redirect URL", 400);
            }
            
            // Increment click count (but don't wait for it)
            $this->linkModel->incrementClicks($link['id']);


            // Add security headers
            header("X-Frame-Options: DENY");
            header("Content-Security-Policy: frame-ancestors 'none'");
            
            // Redirect to the original URL
            header("Location: " . $url);
            exit;
            
        } catch (AppException $e) {
            http_response_code(404);
            echo json_encode(['error' => 'Link not found']);
        }
    }
    
    private function formatShortUrl($shortCode, $customDomain = null) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $customDomain ?: ($_SERVER['HTTP_HOST'] ?? 'short-link.com');
        
        return "{$protocol}://{$host}/{$shortCode}";
    }
}
