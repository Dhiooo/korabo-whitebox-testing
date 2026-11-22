<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/TaskController.php';
require_once __DIR__ . '/../controllers/GroupController.php';

class TaskAndGroupCrudTest extends TestCase
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

    public function testGetGroupTasksReturnsListForGroupMember(): void
    {
        // Arrange (TC-01: Read daftar tugas oleh anggota grup yang sah)
        $this->authenticateAs(2);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetchColumn')->willReturn(1);

        $tasksStmtStub = $this->createStub(PDOStatement::class);
        $tasksStmtStub->method('execute')->willReturn(true);
        $tasksStmtStub->method('fetchAll')->willReturn([
            ['id' => 12, 'title' => 'Laporan Akhir', 'status' => 1],
            ['id' => 11, 'title' => 'Setup PHPUnit', 'status' => 2]
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')
            ->willReturnOnConsecutiveCalls($memberCheckStub, $tasksStmtStub);

        // Act
        try {
            TaskController::getGroupTasks($pdoStub, 10);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            // Assert
            $this->assertSame(200, $res->statusCode);
            $this->assertCount(2, $res->payload);
            $this->assertSame('Laporan Akhir', $res->payload[0]['title']);
        }
    }

    public function testAssignTaskSuccessWhenAdminAssignsToGroupMember(): void
    {
        // Arrange (TC-02: Update penugasan tugas oleh admin ke anggota grup valid)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['userId' => 3];

        $taskStmtStub = $this->createStub(PDOStatement::class);
        $taskStmtStub->method('execute')->willReturn(true);
        $taskStmtStub->method('fetch')->willReturn(['group_id' => 10, 'title' => 'Desain UI']);

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(1);

        $memberCheckStub = $this->createStub(PDOStatement::class);
        $memberCheckStub->method('execute')->willReturn(true);
        $memberCheckStub->method('fetchColumn')->willReturn(1);

        $updateAssignMock = $this->createMock(PDOStatement::class);
        $updateAssignMock->expects($this->once())
            ->method('execute')
            ->with([3, 15])
            ->willReturn(true);

        $userNameStub = $this->createStub(PDOStatement::class);
        $userNameStub->method('execute')->willReturn(true);
        $userNameStub->method('fetch')->willReturn(['name' => 'Abel']);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(6))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $taskStmtStub,
                $adminCheckStub,
                $memberCheckStub,
                $updateAssignMock,
                $userNameStub,
                $logStmtStub
            );

        // Act
        try {
            TaskController::assignTask($pdoMock, 15);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            // Assert
            $this->assertSame(200, $res->statusCode);
            $this->assertSame('Tugas berhasil ditugaskan', $res->payload['message']);
        }
    }

    public function testDeleteTaskFailsWhenTaskNotFound(): void
    {
        // Arrange (TC-03: Delete tugas gagal jika ID tugas tidak ditemukan)
        $this->authenticateAs(1);

        $taskStmtStub = $this->createStub(PDOStatement::class);
        $taskStmtStub->method('execute')->willReturn(true);
        $taskStmtStub->method('fetch')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($taskStmtStub);

        // Act
        try {
            TaskController::deleteTask($pdoStub, 999);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            // Assert
            $this->assertSame(404, $res->statusCode);
            $this->assertSame('Tugas tidak ditemukan', $res->payload['message']);
        }
    }

    public function testDeleteTaskSuccessWhenRequestedByGroupAdmin(): void
    {
        // Arrange (TC-04: Delete tugas berhasil oleh admin grup -> verifikasi DELETE dipanggil)
        $this->authenticateAs(1);

        $taskStmtStub = $this->createStub(PDOStatement::class);
        $taskStmtStub->method('execute')->willReturn(true);
        $taskStmtStub->method('fetch')->willReturn(['group_id' => 10, 'title' => 'Tugas Lama']);

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(1);

        $deleteStmtMock = $this->createMock(PDOStatement::class);
        $deleteStmtMock->expects($this->once())
            ->method('execute')
            ->with([15])
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($taskStmtStub, $adminCheckStub, $deleteStmtMock, $logStmtStub);

        // Act
        try {
            TaskController::deleteTask($pdoMock, 15);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            // Assert
            $this->assertSame(200, $res->statusCode);
            $this->assertSame('Tugas berhasil dihapus', $res->payload['message']);
        }
    }

    public function testUpdateAndDeleteGroupSuccessForAdmin(): void
    {
        // Arrange (TC-05: Update nama grup & Delete grup oleh admin)
        $this->authenticateAs(1);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['name' => 'Kelompok PKPL Revisi'];

        $adminCheckStub = $this->createStub(PDOStatement::class);
        $adminCheckStub->method('execute')->willReturn(true);
        $adminCheckStub->method('fetchColumn')->willReturn(1);

        $updateGroupMock = $this->createMock(PDOStatement::class);
        $updateGroupMock->expects($this->once())
            ->method('execute')
            ->with(['Kelompok PKPL Revisi', 10])
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoUpdateMock = $this->createMock(PDO::class);
        $pdoUpdateMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($adminCheckStub, $updateGroupMock, $logStmtStub);

        // Act & Assert Update
        try {
            GroupController::updateGroup($pdoUpdateMock, 10);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            $this->assertSame(200, $res->statusCode);
            $this->assertSame('Grup berhasil diperbarui', $res->payload['message']);
        }

        // Arrange & Act Delete Group
        $deleteGroupMock = $this->createMock(PDOStatement::class);
        $deleteGroupMock->expects($this->once())
            ->method('execute')
            ->with([10])
            ->willReturn(true);

        $pdoDeleteMock = $this->createMock(PDO::class);
        $pdoDeleteMock->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($adminCheckStub, $deleteGroupMock);

        try {
            GroupController::deleteGroup($pdoDeleteMock, 10);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            $this->assertSame(200, $res->statusCode);
            $this->assertSame('Grup berhasil dihapus', $res->payload['message']);
        }
    }

    public function testLeaveGroupFailsWhenAdminTriesToLeave(): void
    {
        // Arrange (TC-06: Admin grup tidak diperbolehkan keluar dari grup sendiri)
        $this->authenticateAs(1);

        $groupStmtStub = $this->createStub(PDOStatement::class);
        $groupStmtStub->method('execute')->willReturn(true);
        $groupStmtStub->method('fetch')->willReturn(['admin_id' => 1]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($groupStmtStub);

        // Act
        try {
            GroupController::leaveGroup($pdoStub, 10);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $res) {
            // Assert
            $this->assertSame(400, $res->statusCode);
            $this->assertSame('Admin tidak bisa keluar grup, hapus grup saja', $res->payload['message']);
        }
    }
}