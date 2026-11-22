<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/GroupController.php';

class GroupJoinTest extends TestCase
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

    private function invokeJoinGroup($pdo): HttpResponseException
    {
        try {
            GroupController::joinGroup($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testJoinGroupFailsWhenCodeFormatInvalid(): void
    {
        // Arrange (TC-01: Kode grup bukan 6 karakter alfanumerik)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['code' => 'AB12'];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeJoinGroup($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Kode grup harus terdiri dari 6 karakter alfanumerik', $res->payload['message']);
    }

    public function testJoinGroupFailsWhenGroupCodeNotFound(): void
    {
        // Arrange (TC-02: Stub fetch() mengembalikan false / kode grup tidak ditemukan)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['code' => 'XYZ999'];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Act
        $res = $this->invokeJoinGroup($pdoStub);

        // Assert
        $this->assertSame(404, $res->statusCode);
        $this->assertSame('Kode grup tidak ditemukan', $res->payload['message']);
    }

    public function testJoinGroupFailsWhenUserAlreadyMember(): void
    {
        // Arrange (TC-03: Grup ditemukan, tetapi user sudah terdaftar di group_members)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['code' => 'ABC123'];

        $groupStmtStub = $this->createStub(PDOStatement::class);
        $groupStmtStub->method('execute')->willReturn(true);
        $groupStmtStub->method('fetch')->willReturn(['id' => 10, 'name' => 'Kelompok PKPL A']);

        $memberStmtStub = $this->createStub(PDOStatement::class);
        $memberStmtStub->method('execute')->willReturn(true);
        $memberStmtStub->method('fetch')->willReturn(['id' => 55]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')
            ->willReturnOnConsecutiveCalls($groupStmtStub, $memberStmtStub);

        // Act
        $res = $this->invokeJoinGroup($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Anda sudah bergabung di grup ini', $res->payload['message']);
    }

    public function testJoinGroupSuccessWithValidCode(): void
    {
        // Arrange (TC-04: Kode valid & belum bergabung -> Mock PDO memverifikasi INSERT anggota, log, & commit)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['code' => 'abc123'];

        $groupStmtStub = $this->createStub(PDOStatement::class);
        $groupStmtStub->method('execute')->willReturn(true);
        $groupStmtStub->method('fetch')->willReturn(['id' => 10, 'name' => 'Kelompok PKPL A']);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetch')->willReturn(false);

        $insertMemberMock = $this->createMock(PDOStatement::class);
        $insertMemberMock->expects($this->once())
            ->method('execute')
            ->with([10, 2])
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction');
        $pdoMock->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($groupStmtStub, $memberCheckStub, $insertMemberMock, $logStmtStub);
        $pdoMock->expects($this->once())->method('commit');

        // Act
        $res = $this->invokeJoinGroup($pdoMock);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Berhasil bergabung ke grup', $res->payload['message']);
    }

    public function testJoinGroupRollsBackOnDatabaseError(): void
    {
        // Arrange (TC-05: Error saat INSERT anggota memicu rollBack transaksi & HTTP 500)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['code' => 'ABC123'];

        $groupStmtStub = $this->createStub(PDOStatement::class);
        $groupStmtStub->method('execute')->willReturn(true);
        $groupStmtStub->method('fetch')->willReturn(['id' => 10, 'name' => 'Kelompok PKPL A']);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetch')->willReturn(false);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction');
        $pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($groupStmtStub, $memberCheckStub) {
                if (str_starts_with($sql, 'SELECT id, name')) return $groupStmtStub;
                if (str_starts_with($sql, 'SELECT id FROM group_members')) return $memberCheckStub;
                throw new Exception('Insert member failed');
            });
        $pdoMock->expects($this->once())->method('rollBack');

        // Act
        $res = $this->invokeJoinGroup($pdoMock);

        // Assert
        $this->assertSame(500, $res->statusCode);
        $this->assertSame('Insert member failed', $res->payload['message']);
    }
}