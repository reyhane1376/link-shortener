<?php

require_once __DIR__ . '/BaseTest.php';
require_once __DIR__ . '/../src/Helpers/helpers.php';

use App\Exceptions\AppException;
use PHPUnit\Framework\TestCase;

class UserModelTest extends BaseTest {
    private $userModel;
    private $dbMock;

    protected function setUp(): void {
        parent::setUp();

        $this->dbMock = new DatabaseMock();

        $this->userModel = new App\Models\User($this->dbMock);

        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', 'test_secret_key');
        }
    }

    public function testCreateUserSuccess() {
        $this->dbMock->setReturnValue('select', []);
        $this->dbMock->setReturnValue('insert', 123);

        $result = $this->userModel->create(
            'testuser',
            'Password123',
            'test@example.com'
        );

        $this->assertEquals(123, $result['id']);
        $this->assertEquals('testuser', $result['username']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    public function testCreateUserWithExistingUsername() {
        $this->dbMock->setReturnValue('select', [
            ['id' => 123, 'username' => 'testuser', 'email' => 'existing@example.com']
        ]);

        $this->expectException(AppException::class);
        $this->userModel->create(
            'testuser',
            'Password123',
            'test@example.com'
        );
    }

    public function testCreateUserWithInvalidEmail() {
        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->create(
            'testuser',
            'Password123',
            'invalid-email'
        );
    }

    public function testCreateUserWithWeakPassword() {
        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->create(
            'testuser',
            'password',
            'test@example.com'
        );
    }

    public function testAuthenticateSuccess() {
        $this->dbMock->setReturnValue('select', [
            [
                'id'                => 123,
                'username'          => 'testuser',
                'password'          => password_hash('Password123', PASSWORD_DEFAULT),
                'email'             => 'test@example.com',
                'login_attempts'    => 0,
                'last_attempt_time' => null
            ]
        ]);

        $result = $this->userModel->authenticate('testuser', 'Password123');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals(123, $result['user']['id']);
        $this->assertEquals('testuser', $result['user']['username']);
    }

    public function testAuthenticateWithWrongPassword() {
        $this->dbMock->setReturnValue('select', [
            [
                'id' => 123,
                'username' => 'testuser',
                'password' => password_hash('Password123', PASSWORD_DEFAULT),
                'email' => 'test@example.com',
                'login_attempts' => 0,
                'last_attempt_time' => null
            ]
        ]);

        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->authenticate('testuser', 'WrongPassword');
    }

    public function testAuthenticateWithNonExistentUser() {
        $this->dbMock->setReturnValue('select', []);

        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->authenticate('nonexistent', 'Password123');
    }

    public function testAccountLockout() {
        $this->dbMock->setReturnValue('select', [
            [
                'id' => 123,
                'username' => 'testuser',
                'password' => password_hash('Password123', PASSWORD_DEFAULT),
                'email' => 'test@example.com',
                'login_attempts' => 5,
                'last_attempt_time' => date('Y-m-d H:i:s', time() - 60)
            ]
        ]);

        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->authenticate('testuser', 'Password123');
    }

    public function testVerifyTokenSuccess() {
        $token = $this->createTestToken(123, 'testuser');
        $result = $this->userModel->verifyToken($token);

        $this->assertEquals(123, $result['user_id']);
        $this->assertEquals('testuser', $result['username']);
    }

    public function testVerifyInvalidToken() {
        $this->expectException(\App\Exceptions\AppException::class);
        $this->userModel->verifyToken('invalid.token.string');
    }

    private function createTestToken($userId, $username) {
        $config = \Lcobucci\JWT\Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::plainText(JWT_SECRET_KEY)
        );

        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedBy('test')
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('user_id', $userId)
            ->withClaim('username', $username)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}