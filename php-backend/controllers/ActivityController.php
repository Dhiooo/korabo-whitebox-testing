<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

class ActivityController {
    public static function getGroupActivities($pdo, $groupId) {
        $user = getAuthenticatedUser();
        requireGroupMember($pdo, $groupId, $user['id']);
        $stmt = $pdo->prepare('
            SELECT a.*, u.name as user_name 
            FROM activities a
            JOIN users u ON a.user_id = u.id
            WHERE a.group_id = ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ');
        $stmt->execute([$groupId]);
        response($stmt->fetchAll());
    }

    public static function clearGroupActivities($pdo, $groupId) {
        $user = getAuthenticatedUser();
        
        requireGroupAdmin($pdo, $groupId, $user['id']);


        $stmt = $pdo->prepare('DELETE FROM activities WHERE group_id = ?');
        $stmt->execute([$groupId]);
        
        response(['message' => 'Riwayat aktivitas berhasil dihapus']);
    }
}
