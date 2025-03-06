<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($username, $password, $email) {
        // Validate input
        if (empty($username) || empty($password) || empty($email)) {
            throw new AppException("All fields are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppException("Invalid email format");
        }
        
        // Check if username or email already exists
        $existingUser = $this->db->select("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email])->fetch();
        
        if ($existingUser) {
            throw new AppException("Username or email already exists");
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Create the user
        $userId = $this->db->insert('users', ['username', 'password', 'email'], [$username, $hashedPassword, $email]);
        
        return [
            'id' => $userId,
            'username' => $username,
            'email' => $email
        ];
    }
    
    public function authenticate($username, $password) {
        // Find user by username
        $user = $this->db->select("SELECT id, username, password, email FROM users WHERE username = ?", [$username])->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new AppException("Invalid credentials", 401);
        }
        
        // Generate JWT token using lcobucci/jwt
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->getSecretKey())
        );
        
        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedBy($_SERVER['HTTP_HOST'] ?? 'link-shortener')
            ->issuedAt($now)
            ->expiresAt($now->modify('+24 hours'))
            ->withClaim('user_id', $user['id'])
            ->withClaim('username', $user['username'])
            ->getToken($config->signer(), $config->signingKey());
        
        return [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'token' => $token->toString()
        ];
    }
    
    public function verifyToken($tokenString) {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->getSecretKey())
            );
            
            $token = $config->parser()->parse($tokenString);
            
            if($token->isExpired(new \DateTimeImmutable())) {
                throw new AppException("Token expired", 401);
            }
            
            return [
                'user_id' => $token->claims()->get('user_id'),
                'username' => $token->claims()->get('username')
            ];
        } catch (\Exception $e) {
            throw new AppException("Invalid token", 401);
        }
    }
    
    private function getSecretKey() {
        // Load from environment variable or secure configuration
        $secretKey = JWT_SECRET_KEY;
        if (empty($secretKey)) {
            throw new AppException("JWT secret key not configured", 500);
        }
        return $secretKey;
    }
}
