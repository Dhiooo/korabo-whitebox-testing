<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/TaskController.php';

class TaskCreateTest extends TestCase
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

    private function invokeCreateTask($pdo): HttpResponseException
    {
        try {
            TaskController::createTask($pdo);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    public function testCreateTaskFailsWhenTitleOrGroupInvalid(): void
    {
        // Arrange (TC-01: Judul kurang dari 3 karakter)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'group_id' => 10,
            'title' => 'UI'
        ];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeCreateTask($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Judul tugas harus terdiri dari 3-150 karakter', $res->payload['message']);
    }

    public function testCreateTaskFailsWhenRequesterIsNotGroupAdmin(): void
    {
        // Arrange (TC-02: Stub fetchColumn() mengembalikan false / bukan admin grup)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'group_id' => 10,
            'title' => 'Membuat Laporan PKPL'
        ];

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($adminCheckStub);

        // Act
        $res = $this->invokeCreateTask($pdoStub);

        // Assert
        $this->assertSame(403, $res->statusCode);
        $this->assertSame('Hanya admin grup yang dapat melakukan aksi ini', $res->payload['message']);
    }

    public function testCreateTaskFailsWhenAssigneeIsNotGroupMember(): void
    {
        // Arrange (TC-03: Pembuat adalah admin, tetapi user tujuan bukan anggota grup)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'group_id' => 10,
            'title' => 'Membuat Laporan PKPL',
            'assigned_to' => 99
        ];

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(1);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetchColumn')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')
            ->willReturnOnConsecutiveCalls($adminCheckStub, $memberCheckStub);

        // Act
        $res = $this->invokeCreateTask($pdoStub);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Anggota tujuan bukan anggota grup', $res->payload['message']);
    }

    public function testCreateTaskSuccessWhenValidAndAssignedToMember(): void
    {
        // Arrange (TC-04: Admin & assignee valid -> Mock memverifikasi INSERT tugas & log aktivitas)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = [
            'group_id' => 10,
            'title' => 'Implementasi Unit Test',
            'description' => 'Membuat test case PHPUnit',
            'deadline' => '2026-11-30 23:59:00',
            'assigned_to' => 3
        ];

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(1);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetchColumn')->willReturn(1);

        $insertTaskMock = $this->createMock(PDOStatement::class);
        $insertTaskMock->expects($this->once())
            ->method('execute')
            ->with([10, 'Implementasi Unit Test', 'Membuat test case PHPUnit', '2026-11-30 23:59:00', 3])
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($adminCheckStub, $memberCheckStub, $insertTaskMock, $logStmtStub);

        // Act
        $res = $this->invokeCreateTask($pdoMock);

        // Assert
        $this->assertSame(201, $res->statusCode);
        $this->assertSame('Tugas berhasil dibuat', $res->payload['message']);
    }
}