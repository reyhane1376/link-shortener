<?php
namespace App\Controllers;

use App\Exceptions\AppException;
use App\Models\Link;
use App\Helpers\helpers;

class LinkController {
    private $linkModel;
    
    public function __construct() {
        $this->linkModel = new Link();
    }
    
    private function authenticate() {
        return authenticate();
    }
    
    public function getLinks() {
        try {
            $userId = $this->authenticate();
            
            $links = $this->linkModel->getAll($userId);
            
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
            
            $link = $this->linkModel->getById($id, $userId);
            
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
            $customDomain = $data['custom_domain'] ?? null;
            
            // Create the link
            $link = $this->linkModel->create($userId, $originalUrl, $customDomain);
            
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

            $response = [
                'message' => 'لینک با موفقیت حذف شد.',
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
            $link = $this->linkModel->getByCode($shortCode);
            
            // Increment click count (but don't wait for it)
            $this->linkModel->incrementClicks($link['id']);
            
            // Redirect to the original URL
            header("Location: {$link['original_url']}");
            exit;
            
        } catch (AppException $e) {
            http_response_code(404);
            echo json_encode(['error' => 'Link not found']);
        }
    }
    
    private function formatShortUrl($shortCode, $customDomain = null) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $customDomain ?: ($_SERVER['HTTP_HOST'] ?? 'example.com');
        
        return "{$protocol}://{$host}/{$shortCode}";
    }
}
