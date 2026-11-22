<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/AuthController.php';

class AuthRegisterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__MOCK_JSON_INPUT__']);
    }

    private function invokeRegister($pdo): HttpResponseException
    {
        try {
            AuthController::register($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testRegisterFailsWhenNameLengthInvalid(): void
    {
        // Arrange (TC-01)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'A',
            'nim' => '202310370311',
            'email' => 'dhio@gmail.com',
            'password' => 'password12'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeRegister($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Nama harus terdiri dari 2-100 karakter', $res->payload['message']);
    }

    public function testRegisterFailsWhenNimFormatInvalid(): void
    {
        // Arrange (TC-02)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'Dhio Cho',
            'nim' => '123',
            'email' => 'dhio@gmail.com',
            'password' => 'password12'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeRegister($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('NIM harus berupa 5-20 digit angka', $res->payload['message']);
    }

    public function testRegisterFailsWhenEmailFormatInvalid(): void
    {
        // Arrange (TC-03)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'Dhio Cho',
            'nim' => '202310370311',
            'email' => 'dhiogmail.com',
            'password' => 'password12'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeRegister($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Format email tidak valid', $res->payload['message']);
    }

    public function testRegisterFailsWhenPasswordLengthInvalid(): void
    {
        // Arrange (TC-04)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'Dhio Cho',
            'nim' => '202310370311',
            'email' => 'dhio@gmail.com',
            'password' => '123'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeRegister($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Password harus terdiri dari 4-72 karakter', $res->payload['message']);
    }

    public function testRegisterFailsWhenNimOrEmailAlreadyExists(): void
    {
        // Arrange (TC-05 - Stub PDOStatement mengembalikan user yang sudah ada)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'Dhio Cho',
            'nim' => '20230001',
            'email' => 'dhio@gmail.com',
            'password' => 'password12'
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(['id' => 1]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Act
        $res = $this->invokeRegister($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('NIM atau Email sudah terdaftar', $res->payload['message']);
    }

    public function testRegisterSuccessWithValidUniqueData(): void
    {
        // Arrange (TC-06 - Stub untuk SELECT & Mock untuk memverifikasi INSERT)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'name' => 'Dhio Cho',
            'nim' => '202310370311',
            'email' => 'dhio@gmail.com',
            'password' => 'password12'
        ];

        $selectStmtStub = $this->createStub(PDOStatement::class);
        $selectStmtStub->method('execute')->willReturn(true);
        $selectStmtStub->method('fetch')->willReturn(false);

        $insertStmtMock = $this->createMock(PDOStatement::class);
        $insertStmtMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) {
                return $params[0] === 'Dhio Cho'
                    && $params[1] === '202310370311'
                    && $params[2] === 'dhio@gmail.com'
                    && password_verify('password12', $params[3]);
            }))
            ->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmtStub, $insertStmtMock);

        // Act
        $res = $this->invokeRegister($pdoMock);

        // Assert
        $this->assertSame(201, $res->statusCode);
        $this->assertSame('Registrasi berhasil', $res->payload['message']);
    }
}