<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/TaskController.php';

class TaskUpdateStatusTest extends TestCase
{
    private array $tempFiles = [];
    private array $existingUploads = [];

    protected function setUp(): void
    {
        if (is_dir(UPLOAD_DIR)) {
            $this->existingUploads = scandir(UPLOAD_DIR) ?: [];
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__MOCK_JSON_INPUT__'], $_SERVER['HTTP_AUTHORIZATION']);
        $_POST = [];
        $_FILES = [];

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        if (is_dir(UPLOAD_DIR)) {
            $currentFiles = scandir(UPLOAD_DIR) ?: [];
            foreach (array_diff($currentFiles, $this->existingUploads) as $newFile) {
                $fullPath = UPLOAD_DIR . $newFile;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
    }

    private function authenticateAs(int $userId): void
    {
        $token = JWTHelper::encode(['id' => $userId], JWT_SECRET);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    private function invokeUpdateStatus($pdo, int $taskId): HttpResponseException
    {
        try {
            TaskController::updateTaskStatus($pdo, $taskId);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            return $e;
        }
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'korabo_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testUpdateStatusFailsWhenStatusInvalid(): void
    {
        // Arrange (TC-01: Nilai status di luar rentang 0-2)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['status' => 5];
        $pdoStub = $this->createStub(PDO::class);

        // Act
        $res = $this->invokeUpdateStatus($pdoStub, 12);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Status task tidak valid', $res->payload['message']);
    }

    public function testUpdateStatusFailsWhenDoneWithoutPdfProof(): void
    {
        // Arrange (TC-02: Status Done (2) tanpa lampiran PDF dan tugas belum punya proof_file)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['status' => 2];

        $loadTaskStub = $this->createStub(PDOStatement::class);
        $loadTaskStub->method('execute')->willReturn(true);
        $loadTaskStub->method('fetch')->willReturn([
            'id' => 12,
            'group_id' => 10,
            'title' => 'Laporan Akhir',
            'proof_file' => null,
            'assigned_to' => 2,
            'admin_id' => 1
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($loadTaskStub);

        // Act
        $res = $this->invokeUpdateStatus($pdoStub, 12);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('Wajib melampirkan file bukti PDF untuk menyelesaikan tugas', $res->payload['message']);
    }

    public function testUpdateStatusFailsWhenProofFileIsNotPdf(): void
    {
        // Arrange (TC-03: Status Done (2) dengan file berekstensi .txt)
        $this->authenticateAs(2);
        $_POST['status'] = '2';
        $tmpTxt = $this->createTempFile('Ini bukan file PDF');

        $_FILES['proof'] = [
            'name' => 'bukti.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmpTxt,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpTxt)
        ];

        $loadTaskStub = $this->createStub(PDOStatement::class);
        $loadTaskStub->method('execute')->willReturn(true);
        $loadTaskStub->method('fetch')->willReturn([
            'id' => 12,
            'group_id' => 10,
            'title' => 'Laporan Akhir',
            'proof_file' => null,
            'assigned_to' => 2,
            'admin_id' => 1
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($loadTaskStub);

        // Act
        $res = $this->invokeUpdateStatus($pdoStub, 12);

        // Assert
        $this->assertSame(400, $res->statusCode);
        $this->assertSame('File bukti harus berformat PDF', $res->payload['message']);
    }

    public function testUpdateStatusSuccessToInProgress(): void
    {
        // Arrange (TC-04: Ubah status ke In Progress (1) -> Mock memverifikasi query UPDATE & log)
        $this->authenticateAs(2);
        $GLOBALS['__MOCK_JSON_INPUT__'] = ['status' => 1];

        $loadTaskStub = $this->createStub(PDOStatement::class);
        $loadTaskStub->method('execute')->willReturn(true);
        $loadTaskStub->method('fetch')->willReturn([
            'id' => 12,
            'group_id' => 10,
            'title' => 'Laporan Akhir',
            'proof_file' => null,
            'assigned_to' => 2,
            'admin_id' => 1
        ]);

        $updateStmtMock = $this->createMock(PDOStatement::class);
        $updateStmtMock->expects($this->once())
            ->method('execute')
            ->with([1, 12])
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($loadTaskStub, $updateStmtMock, $logStmtStub);

        // Act
        $res = $this->invokeUpdateStatus($pdoMock, 12);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Status tugas diperbarui', $res->payload['message']);
    }

    public function testUpdateStatusSuccessToDoneWithValidPdfProof(): void
    {
        // Arrange (TC-05: Status Done (2) dengan file PDF valid (%PDF-1.4) -> Mock memverifikasi UPDATE proof_file)
        $this->authenticateAs(2);
        $_POST['status'] = '2';
        $validPdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $tmpPdf = $this->createTempFile($validPdfContent);

        $_FILES['proof'] = [
            'name' => 'bukti_selesai.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpPdf,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($validPdfContent)
        ];

        $loadTaskStub = $this->createStub(PDOStatement::class);
        $loadTaskStub->method('execute')->willReturn(true);
        $loadTaskStub->method('fetch')->willReturn([
            'id' => 12,
            'group_id' => 10,
            'title' => 'Laporan Akhir',
            'proof_file' => null,
            'assigned_to' => 2,
            'admin_id' => 1
        ]);

        $updateStmtMock = $this->createMock(PDOStatement::class);
        $updateStmtMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) {
                return $params[0] === 2
                    && str_ends_with($params[1], '.pdf')
                    && $params[2] === 12;
            }))
            ->willReturn(true);

        $logStmtStub = $this->createStub(PDOStatement::class);
        $logStmtStub->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($loadTaskStub, $updateStmtMock, $logStmtStub);

        // Act
        $res = $this->invokeUpdateStatus($pdoMock, 12);

        // Assert
        $this->assertSame(200, $res->statusCode);
        $this->assertSame('Status tugas diperbarui', $res->payload['message']);
    }
}