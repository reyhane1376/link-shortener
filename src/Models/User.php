<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class User {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 1800; // 30 minutes in seconds
    
    public function __construct($db = null) {
        $this->db = $db ?? new Database();
    }
    
    public function create($username, $password, $email) {
        
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
        $user = $this->db->select("SELECT id, username, password, email, login_attempts, last_attempt_time FROM users WHERE username = ?", [$username])->fetch();
        
        // Check if account is temporarily locked due to too many failed attempts
        if ($user && $user['login_attempts'] >= $this->maxLoginAttempts) {
            // Check if lockout period has passed
            $lastAttemptTime = $user['last_attempt_time'] ? strtotime($user['last_attempt_time']) : 0;
            $timeSinceLastAttempt = time() - $lastAttemptTime;
            
            if ($timeSinceLastAttempt < $this->lockoutTime) {
                $minutesLeft = ceil(($this->lockoutTime - $timeSinceLastAttempt) / 60);
                throw new AppException("Account temporarily locked. Try again in {$minutesLeft} minutes.", 429);
            }
            
            // If lockout period has passed, reset the attempts counter
            $this->resetLoginAttempts($user['id']);
        }
        
        // Validate credentials
        if (!$user || !password_verify($password, $user['password'])) {
            // Record failed attempt if user exists
            if ($user) {
                $this->incrementLoginAttempts($user['id']);
            }
            throw new AppException("Invalid credentials", 401);
        }
        
        // Reset login attempts on successful login
        $this->resetLoginAttempts($user['id']);
        
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
        
        // Store token in invalidated_tokens table with status 'active'
        $tokenString = $token->toString();
        $this->storeToken($user['id'], $tokenString, $now->modify('+24 hours')->getTimestamp());
        
        return [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'token' => $tokenString
        ];
    }
    
    private function incrementLoginAttempts($userId) {
        $this->db->execute(
            "UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() WHERE id = ?", 
            [$userId]
        );
    }
    
    private function resetLoginAttempts($userId) {
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, last_attempt_time = NULL WHERE id = ?", 
            [$userId]
        );
    }
    
    private function storeToken($userId, $token, $expiresAt) {
        $this->db->insert(
            'token_blacklist', 
            ['user_id', 'token', 'expires_at'], 
            [$userId, $token, date('Y-m-d H:i:s', $expiresAt)]
        );
    }
    
    public function invalidateToken($tokenString) {
        try {
            // First verify the token is valid
            $tokenData = $this->verifyToken($tokenString);
            
            // Then invalidate it in the database
            $updated = $this->db->execute(
                "UPDATE token_blacklist SET invalidated = TRUE, invalidated_at = NOW() WHERE token = ?", 
                [$tokenString]
            );
            
            if (!$updated) {
                throw new AppException("Token not found or already invalidated", 400);
            }
            
            return true;
        } catch (AppException $e) {
            // Re-throw application exceptions
            throw $e;
        } catch (\Exception $e) {
            throw new AppException("Error invalidating token: " . $e->getMessage(), 500);
        }
    }
    
    public function verifyToken($tokenString) {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->getSecretKey())
            );
            
            $token = $config->parser()->parse($tokenString);
            
            if ($token->isExpired(new \DateTimeImmutable())) {
                throw new AppException("Token expired", 401);
            }
            
            // Check if token has been invalidated (logged out)
            $tokenRecord = $this->db->select(
                "SELECT invalidated FROM token_blacklist WHERE token = ?", 
                [$tokenString]
            )->fetch();
            
            if ($tokenRecord && $tokenRecord['invalidated']) {
                throw new AppException("Token has been invalidated", 401);
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