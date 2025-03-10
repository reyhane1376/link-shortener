<?php
namespace App\Controllers;

use App\Exceptions\AppException;
use App\Models\User;

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        // Get input data
        
        if (!isset($data['username']) || !isset($data['password']) || !isset($data['email'])) {
            throw new AppException("Username, password and email are required");
        }
                
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new AppException("Invalid email format");
        }

        // Enforce password complexity
        if (strlen($data['password']) < 8) {
            throw new AppException("Password must be at least 8 characters long");
        }
        
        if (!preg_match('/[A-Z]/', $data['password']) || 
            !preg_match('/[a-z]/', $data['password']) || 
            !preg_match('/[0-9]/', $data['password'])) {
            throw new AppException("Password must include uppercase, lowercase, and numbers");
        }
        
        // Create user
        $user = $this->userModel->create($data['username'], $data['password'], $data['email']);
        
        // Return response
        http_response_code(201);
        echo json_encode(['user' => $user]);
    }
    
    public function login() {
        // Get input data
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            throw new AppException("Username and password are required");
        }
        
        // Authenticate user
        $result = $this->userModel->authenticate($data['username'], $data['password']);
        
        // Return response
        echo json_encode($result);
    }
    
    public function logout() {
        // Get Authorization header
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new AppException("No token provided", 401);
        }
        
        $token = $matches[1];
        
        // Invalidate the token
        $this->userModel->invalidateToken($token);
        
        // Return success response
        http_response_code(200);
        echo json_encode(['message' => 'Successfully logged out']);
    }
}
