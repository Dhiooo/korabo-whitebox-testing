<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/AuthController.php';

class AuthLoginTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__MOCK_JSON_INPUT__']);
    }

    private function invokeLogin($pdo): HttpResponseException
    {
        try {
            AuthController::login($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testLoginFailsWhenNimOrPasswordIsEmpty(): void
    {
        // Arrange (TC-01: Input kosong)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '',
            'password' => ''
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeLogin($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('NIM dan Password wajib diisi', $res->payload['message']);
    }

    public function testLoginFailsWhenNimNotFound(): void
    {
        // Arrange (TC-02: Stub fetch() mengembalikan false / user tidak ditemukan)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '202399999999',
            'password' => 'password12'
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Act
        $res = $this->invokeLogin($pdoStub);

        // Assert
        $this->assertSame(404, $res->statusCode);
        $this->assertSame('NIM tidak ditemukan', $res->payload['message']);
    }

    public function testLoginFailsWhenPasswordIsIncorrect(): void
    {
        // Arrange (TC-03: User ditemukan, tetapi password tidak cocok)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '202310370311',
            'password' => 'salahpassword'
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn([
            'id' => 1,
            'name' => 'Dhio Cho',
            'nim' => '202310370311',
            'password' => password_hash('password12', PASSWORD_BCRYPT)
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Act
        $res = $this->invokeLogin($pdoStub);

        // Assert
        $this->assertSame(401, $res->statusCode);
        $this->assertSame('Password salah', $res->payload['message']);
    }

    public function testLoginSuccessWithValidCredentials(): void
    {
        // Arrange (TC-04: Mock PDO memverifikasi query SELECT & Stub mengembalikan data user valid)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '202310370311',
            'password' => 'password12'
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with(['202310370311'])
            ->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'id' => 1,
            'name' => 'Dhio Cho',
            'nim' => '202310370311',
            'password' => password_hash('password12', PASSWORD_BCRYPT)
        ]);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE nim = ?')
            ->willReturn($stmtMock);

        // Act
        $res = $this->invokeLogin($pdoMock);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Login berhasil', $res->payload['message']);
        $this->assertNotEmpty($res->payload['token']);
        $this->assertSame('202310370311', $res->payload['user']['nim']);
    }
}