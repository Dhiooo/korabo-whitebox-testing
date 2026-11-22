<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt_helper.php';

class AuthController {
    public static function register($pdo) {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $nim = trim($data['nim'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (strlen($name) < 2 || strlen($name) > 100) {
            response(['message' => 'Nama harus terdiri dari 2-100 karakter'], 400);
        }
        if (!preg_match('/^\d{5,20}$/', $nim)) {
            response(['message' => 'NIM harus berupa 5-20 digit angka'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            response(['message' => 'Format email tidak valid'], 400);
        }
        if (strlen($password) < 4 || strlen($password) > 72) {
            response(['message' => 'Password harus terdiri dari 4-72 karakter'], 400);
        }

        // Check existing
        $stmt = $pdo->prepare('SELECT id FROM users WHERE nim = ? OR email = ?');
        $stmt->execute([$nim, $email]);
        if ($stmt->fetch()) {
            response(['message' => 'NIM atau Email sudah terdaftar'], 400);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (name, nim, email, password) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $nim, $email, $hashedPassword]);

        response(['message' => 'Registrasi berhasil'], 201);
    }

    public static function login($pdo) {
        $data = json_decode(file_get_contents('php://input'), true);
        $nim = trim($data['nim'] ?? '');
        $password = $data['password'] ?? '';

        // TC-EP-11: Kredensial Kosong
        if ($nim === '' || $password === '') {
            response(['message' => 'NIM dan Password wajib diisi'], 400);
        }

        // TC-EP-09 & TC-EP-10: Validasi Spesifik NIM & Password
        $stmt = $pdo->prepare('SELECT * FROM users WHERE nim = ?');
        $stmt->execute([$nim]);
        $user = $stmt->fetch();

        if (!$user) {
            response(['message' => 'NIM tidak ditemukan'], 404);
        }

        if (!password_verify($password, $user['password'])) {
            response(['message' => 'Password salah'], 401);
        }

        $token = JWTHelper::encode(['id' => $user['id']], JWT_SECRET);

        response([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'nim' => $user['nim']
            ]
        ]);
    }

    public static function requestPasswordReset($pdo) {
        $data = json_decode(file_get_contents('php://input'), true);
        $nim = trim($data['nim'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $genericMessage = 'Jika data terdaftar, instruksi reset password sudah dibuat.';

        if (!preg_match('/^\d{5,20}$/', $nim) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            response(['message' => 'NIM dan email wajib diisi dengan format yang valid'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE nim = ? AND email = ?');
        $stmt->execute([$nim, $email]);
        $user = $stmt->fetch();
        if (!$user) response(['message' => $genericMessage]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->execute([$user['id'], $tokenHash]);

        $result = ['message' => $genericMessage];
        if (APP_ENV === 'development') $result['resetToken'] = $rawToken;
        response($result);
    }

    public static function resetPassword($pdo) {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($token) || strlen($token) < 32) {
            response(['message' => 'Token reset tidak valid atau kedaluwarsa'], 400);
        }
        if (!is_string($password) || strlen($password) < 4 || strlen($password) > 72) {
            response(['message' => 'Password harus terdiri dari 4-72 karakter'], 400);
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare(
            'SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch();
        if (!$reset) response(['message' => 'Token reset tidak valid atau kedaluwarsa'], 400);

        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$reset['id']]);

        response(['message' => 'Password berhasil diubah. Silakan login.']);
    }
}