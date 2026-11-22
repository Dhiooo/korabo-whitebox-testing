<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/AuthController.php';

class AuthPasswordResetTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__MOCK_JSON_INPUT__']);
    }

    private function invokeRequestReset($pdo): HttpResponseException
    {
        try {
            AuthController::requestPasswordReset($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    private function invokeResetPassword($pdo): HttpResponseException
    {
        try {
            AuthController::resetPassword($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testRequestResetFailsWhenNimOrEmailInvalid(): void
    {
        // Arrange (TC-01: Format NIM atau Email tidak valid)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '12',
            'email' => 'emailsalah'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeRequestReset($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('NIM dan email wajib diisi dengan format yang valid', $res->payload['message']);
    }

    public function testRequestResetReturnsGenericMessageWhenUserNotFound(): void
    {
        // Arrange (TC-02: Stub fetch() mengembalikan false agar tidak membocorkan eksistensi akun)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '202310370311',
            'email' => 'dhio@gmail.com'
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtStub);

        // Act
        $res = $this->invokeRequestReset($pdoMock);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Jika data terdaftar, instruksi reset password sudah dibuat.', $res->payload['message']);
    }

    public function testRequestResetCreatesTokenWhenUserExists(): void
    {
        // Arrange (TC-03: User ditemukan -> Mock PDO memverifikasi INSERT token reset)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'nim' => '202310370311',
            'email' => 'dhio@gmail.com'
        ];

        $selectStmtStub = $this->createStub(PDOStatement::class);
        $selectStmtStub->method('execute')->willReturn(true);
        $selectStmtStub->method('fetch')->willReturn(['id' => 7]);

        $insertStmtMock = $this->createMock(PDOStatement::class);
        $insertStmtMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn(array $params) => $params[0] === 7 && strlen($params[1]) === 64))
            ->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmtStub, $insertStmtMock);

        // Act
        $res = $this->invokeRequestReset($pdoMock);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Jika data terdaftar, instruksi reset password sudah dibuat.', $res->payload['message']);
    }

    public function testResetPasswordFailsWhenTokenTooShort(): void
    {
        // Arrange (TC-04: Panjang token kurang dari 32 karakter)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'token' => 'short-token',
            'password' => 'passwordbaru12'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeResetPassword($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Token reset tidak valid atau kedaluwarsa', $res->payload['message']);
    }

    public function testResetPasswordFailsWhenTokenNotFoundOrExpired(): void
    {
        // Arrange (TC-05: Stub fetch() mengembalikan false untuk token kedaluwarsa/sudah dipakai)
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'token' => str_repeat('a', 64),
            'password' => 'passwordbaru12'
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Act
        $res = $this->invokeResetPassword($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Token reset tidak valid atau kedaluwarsa', $res->payload['message']);
    }

    public function testResetPasswordSuccessWithValidToken(): void
    {
        // Arrange (TC-06: Token valid -> Mock memverifikasi UPDATE password user & penandaan used_at token)
        $rawToken = str_repeat('b', 64);
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'token' => $rawToken,
            'password' => 'passwordbaru12'
        ];

        $selectStmtStub = $this->createStub(PDOStatement::class);
        $selectStmtStub->method('execute')->willReturn(true);
        $selectStmtStub->method('fetch')->willReturn(['id' => 15, 'user_id' => 7]);

        $updateUserMock = $this->createMock(PDOStatement::class);
        $updateUserMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn(array $p) => password_verify('passwordbaru12', $p[0]) && $p[1] === 7))
            ->willReturn(true);

        $updateTokenMock = $this->createMock(PDOStatement::class);
        $updateTokenMock->expects($this->once())
            ->method('execute')
            ->with([15])
            ->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmtStub, $updateUserMock, $updateTokenMock);

        // Act
        $res = $this->invokeResetPassword($pdoMock);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Password berhasil diubah. Silakan login.', $res->payload['message']);
    }
}