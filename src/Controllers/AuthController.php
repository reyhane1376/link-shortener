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
}
