<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

class TaskController {
    private static function logActivity($pdo, $groupId, $userId, $message) {
        $stmt = $pdo->prepare('INSERT INTO activities (group_id, user_id, message) VALUES (?, ?, ?)');
        $stmt->execute([$groupId, $userId, $message]);
    }

    private static function storeUploadedFile(string $tmpName, string $destination): bool {
        if (APP_ENV === 'testing') {
            return copy($tmpName, $destination);
        }
        return move_uploaded_file($tmpName, $destination);
    }

    public static function createTask($pdo) {
        $user = getAuthenticatedUser();
        $data = getJsonInput();
        
        $groupId = $data['group_id'] ?? null;
        $title = trim($data['title'] ?? '');
        $description = $data['description'] ?? '';
        $deadline = $data['deadline'] ?? null;
        $assignedTo = $data['assigned_to'] ?? null;

        if (!$groupId || strlen($title) < 3 || strlen($title) > 150) {
            response(['message' => 'Judul tugas harus terdiri dari 3-150 karakter'], 400);
        }

        requireGroupAdmin($pdo, $groupId, $user['id']);
        if ($assignedTo !== null && $assignedTo !== '' &&
            !isGroupMember($pdo, $groupId, $assignedTo)) {
            response(['message' => 'Anggota tujuan bukan anggota grup'], 400);
        }
        if ($assignedTo === '') $assignedTo = null;

        $stmt = $pdo->prepare('INSERT INTO tasks (group_id, title, description, deadline, assigned_to) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$groupId, $title, $description, $deadline, $assignedTo]);

        self::logActivity($pdo, $groupId, $user['id'], "menambahkan tugas baru: $title");

        response(['message' => 'Tugas berhasil dibuat'], 201);
    }

    public static function getGroupTasks($pdo, $groupId) {
        $user = getAuthenticatedUser();
        requireGroupMember($pdo, $groupId, $user['id']);
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE group_id = ? ORDER BY id DESC');
        $stmt->execute([$groupId]);
        response($stmt->fetchAll());
    }

    public static function updateTaskStatus($pdo, $taskId) {
        $user = getAuthenticatedUser();
        
        // Handle Multipart/form-data for proof file
        $status = $_POST['status'] ?? null;

        if ($status === null) {
            $data = getJsonInput();
            $status = $data['status'] ?? null;
        }

        $parsedStatus = filter_var($status, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 2]
        ]);

        // TC-EP-24: Status Task Invalid
        if ($status === null || $parsedStatus === false) {
            response(['message' => 'Status task tidak valid'], 400);
        }
        $status = $parsedStatus;
        $task = loadTaskForUser($pdo, $taskId, $user['id']);

        $updateSql = 'UPDATE tasks SET status = ?';
        $params = [$status];

        if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
            if ($status !== 2) {
                response(['message' => 'PDF proof hanya boleh dikirim saat status Done'], 400);
            }
            if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
                response(['message' => 'Ukuran PDF maksimal 5 MB'], 400);
            }

            // TC-EP-27: Format File Bukan PDF
            $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) !== 'pdf') response(['message' => 'File bukti harus berformat PDF'], 400);

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['proof']['tmp_name']);
            $signature = file_get_contents($_FILES['proof']['tmp_name'], false, null, 0, 4);
            if ($mime !== 'application/pdf' || $signature !== '%PDF') {
                response(['message' => 'File bukti harus berformat PDF'], 400);
            }

            $filename = time() . '_' . uniqid() . '.pdf';
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            
            if (!self::storeUploadedFile($_FILES['proof']['tmp_name'], UPLOAD_DIR . $filename)) {
                response(['message' => 'Upload PDF gagal'], 400);
            }
            $updateSql .= ', proof_file = ?, completed_at = NOW()';
            $params[] = $filename;
        } elseif ($status === 2 && empty($task['proof_file'])) {
            // TC-EP-26: Status Done Tanpa File PDF
            response(['message' => 'Wajib melampirkan file bukti PDF untuk menyelesaikan tugas'], 400);
        } elseif ($status === 1) {
             $updateSql .= ', completed_at = NULL, proof_file = NULL';
        }

        $updateSql .= ' WHERE id = ?';
        $params[] = $taskId;

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($params);

        $statusText = $status == 1 ? "memulai" : ($status == 2 ? "menyelesaikan" : "memperbarui");
        self::logActivity($pdo, $task['group_id'], $user['id'], "$statusText tugas: " . $task['title']);

        response(['message' => 'Status tugas diperbarui']);
    }

    public static function assignTask($pdo, $taskId) {
        $user = getAuthenticatedUser();
        $data = getJsonInput();
        $userId = $data['userId'] ?? null;

        $stmt = $pdo->prepare('SELECT group_id, title FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) response(['message' => 'Tugas tidak ditemukan'], 404);

        requireGroupAdmin($pdo, $task['group_id'], $user['id']);
        if ($userId !== null && $userId !== '' &&
            !isGroupMember($pdo, $task['group_id'], $userId)) {
            response(['message' => 'Anggota tujuan bukan anggota grup'], 400);
        }
        if ($userId === '') $userId = null;

        $stmt = $pdo->prepare('UPDATE tasks SET assigned_to = ? WHERE id = ?');
        $stmt->execute([$userId, $taskId]);

        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();
        $targetName = $targetUser ? $targetUser['name'] : 'seseorang';

        self::logActivity($pdo, $task['group_id'], $user['id'], "menugaskan '" . $task['title'] . "' kepada $targetName");

        response(['message' => 'Tugas berhasil ditugaskan']);
    }

    public static function deleteTask($pdo, $taskId) {
        $user = getAuthenticatedUser();

        $stmt = $pdo->prepare('SELECT group_id, title FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) response(['message' => 'Tugas tidak ditemukan'], 404);

        requireGroupAdmin($pdo, $task['group_id'], $user['id']);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);

        self::logActivity($pdo, $task['group_id'], $user['id'], "menghapus tugas: " . $task['title']);

        response(['message' => 'Tugas berhasil dihapus']);
    }
}