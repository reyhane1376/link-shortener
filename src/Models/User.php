<?php
namespace App\Models;

use App\Database\Database;
use App\Exceptions\AppException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class User 
{
    private Database $db;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 1800; // 30 minutes in seconds

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function create(string $username, string $password, string $email): array
    {
        // Check if username or email already exists
        $sql = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':email' => $email
        ]);
        $existingUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existingUser) {
            throw new AppException("Username or email already exists");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password, email, created_at) 
                VALUES (:username, :password, :email, NOW()) 
                RETURNING id";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'id' => $result['id'],
            'username' => $username,
            'email' => $email
        ];
    }

    public function authenticate(string $username, string $password): array
    {
        $sql = "SELECT id, username, password, email, login_attempts, last_attempt_time 
                FROM users WHERE username = :username";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && $user['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $lastAttemptTime = $user['last_attempt_time'] ? strtotime($user['last_attempt_time']) : 0;
            $timeSinceLastAttempt = time() - $lastAttemptTime;

            if ($timeSinceLastAttempt < self::LOCKOUT_TIME) {
                $minutesLeft = ceil((self::LOCKOUT_TIME - $timeSinceLastAttempt) / 60);
                throw new AppException("Account temporarily locked. Try again in {$minutesLeft} minutes.", 429);
            }
            $this->resetLoginAttempts($user['id']);
        }

        if (!$user || !password_verify($password, $user['password'])) {
            if ($user) {
                $this->incrementLoginAttempts($user['id']);
            }
            throw new AppException("Invalid credentials", 401);
        }

        $this->resetLoginAttempts($user['id']);

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

    private function incrementLoginAttempts(int $userId): void
    {
        $sql = "UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() 
                WHERE id = :user_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    }

    private function resetLoginAttempts(int $userId): void
    {
        $sql = "UPDATE users SET login_attempts = 0, last_attempt_time = NULL 
                WHERE id = :user_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    }

    private function storeToken(int $userId, string $token, int $expiresAt): void
    {
        $sql = "INSERT INTO token_blacklist (user_id, token, expires_at, created_at) 
                VALUES (:user_id, :token, TO_TIMESTAMP(:expires_at), NOW())";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':expires_at' => $expiresAt
        ]);
    }

    public function invalidateToken(string $tokenString): bool
    {
        try {
            $tokenData = $this->verifyToken($tokenString);
            
            $sql = "UPDATE token_blacklist SET invalidated = TRUE, invalidated_at = NOW() 
                    WHERE token = :token";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':token' => $tokenString]);
            
            if ($stmt->rowCount() === 0) {
                throw new AppException("Token not found or already invalidated", 400);
            }
            
            return true;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AppException("Error invalidating token: " . $e->getMessage(), 500);
        }
    }

    public function verifyToken(string $tokenString): array
    {
        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->getSecretKey())
            );

            $token = $config->parser()->parse($tokenString);

            if ($token->isExpired(new \DateTimeImmutable())) {
                throw new AppException("Token expired", 401);
            }

            $sql = "SELECT invalidated FROM token_blacklist WHERE token = :token";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':token' => $tokenString]);
            $tokenRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

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

    private function getSecretKey(): string
    {
        $secretKey = getenv('JWT_SECRET_KEY') ?: JWT_SECRET_KEY;
        if (empty($secretKey)) {
            throw new AppException("JWT secret key not configured", 500);
        }
        return $secretKey;
    }
}