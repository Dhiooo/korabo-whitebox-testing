<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/GroupController.php';

class GroupCreateTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__MOCK_JSON_INPUT__'], $_SERVER['HTTP_AUTHORIZATION']);
    }

    private function authenticateAs(int $userId): void
    {
        $token = JWTHelper::encode(['id' => $userId], JWT_SECRET);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    private function invokeCreateGroup($pdo): HttpResponseException
    {
        try {
            GroupController::createGroup($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testCreateGroupFailsWhenUnauthenticated(): void
    {
        // Arrange (TC-01: Tanpa header Authorization Bearer token)
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeCreateGroup($pdoStub);

        // Assert
        $this->assertSame(401, $res->statusCode);
        $this->assertSame('Akses ditolak, token tidak valid atau tidak ada', $res->payload['message']);
    }

    public function testCreateGroupFailsWhenNameLengthInvalid(): void
    {
        // Arrange (TC-02: Nama grup kurang dari 3 karakter)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['name' => 'AB'];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeCreateGroup($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Nama grup harus terdiri dari 3-100 karakter', $res->payload['message']);
    }

    public function testCreateGroupSuccessWithTransactionAndActivityLog(): void
    {
        // Arrange (TC-03: Mock PDO memverifikasi transaksi, 3 query INSERT, dan commit)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['name' => 'Kelompok PKPL A'];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction');
        $pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($stmtStub);
        $pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('10');
        $pdoMock->expects($this->once())->method('commit');

        // Act
        $res = $this->invokeCreateGroup($pdoMock);

        // Assert
        $this->assertSame(201, $res->statusCode);
        $this->assertSame('Grup berhasil dibuat', $res->payload['message']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $res->payload['code']);
    }

    public function testCreateGroupRollsBackTransactionOnDatabaseError(): void
    {
        // Arrange (TC-04: Simulasi error database memicu rollBack dan HTTP 500)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['name' => 'Kelompok PKPL B'];

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction');
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('Database insert failure'));
        $pdoMock->expects($this->once())->method('rollBack');

        // Act
        $res = $this->invokeCreateGroup($pdoMock);

        // Assert
        $this->assertSame(500, $res->statusCode);
        $this->assertSame('Database insert failure', $res->payload['message']);
    }
}