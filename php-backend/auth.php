<?php
require_once 'config.php';
require_once 'jwt_helper.php';

function getAuthenticatedUser() {
    $authHeader = '';
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $decoded = JWTHelper::decode($token, JWT_SECRET);
        
        if ($decoded) {
            return $decoded;
        }
    }
    
    response(['message' => 'Akses ditolak, token tidak valid atau tidak ada'], 401);
}

function isGroupMember($pdo, $groupId, $userId) {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$groupId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function requireGroupMember($pdo, $groupId, $userId) {
    if (!isGroupMember($pdo, $groupId, $userId)) {
        response(['message' => 'Anda bukan anggota grup ini'], 403);
    }
}

function requireGroupAdmin($pdo, $groupId, $userId) {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM `groups` WHERE id = ? AND admin_id = ? LIMIT 1'
    );
    $stmt->execute([$groupId, $userId]);

    if (!$stmt->fetchColumn()) {
        response(['message' => 'Hanya admin grup yang dapat melakukan aksi ini'], 403);
    }
}

function loadTaskForUser($pdo, $taskId, $userId) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.group_id, t.title, t.proof_file, t.assigned_to,
                g.admin_id
         FROM tasks t
         JOIN `groups` g ON g.id = t.group_id
         WHERE t.id = ?'
    );
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        response(['message' => 'Tugas tidak ditemukan'], 404);
    }

    $isOwner = (int)$task['assigned_to'] === (int)$userId;
    $isAdmin = (int)$task['admin_id'] === (int)$userId;

    if (!$isOwner && !$isAdmin) {
        response(['message' => 'Anda tidak memiliki akses ke tugas ini'], 403);
    }

    return $task;
}

