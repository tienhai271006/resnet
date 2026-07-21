<?php
/** Truy van bang `notifications` - dung chung boi nhieu controller (comment, like, follow, permission...) */
class NotificationModel {

    public static function create(int $userId, string $type, ?int $actorId, ?int $documentId, string $message): void {
        getDB()->prepare(
            "INSERT INTO notifications (user_id,type,actor_id,document_id,message) VALUES (?,?,?,?,?)"
        )->execute([$userId, $type, $actorId, $documentId, $message]);
    }

    public static function paginateByUser(int $userId, int $page, int $limit = 20): array {
        $sql = "SELECT n.id,n.type,n.message,n.is_read,n.created_at,n.document_id,
                       a.name AS actor_name, a.avatar AS actor_avatar
                FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
                WHERE n.user_id = ? ORDER BY n.created_at DESC";
        return Pagination::run($sql, [$userId], $page, $limit);
    }

    public static function markRead(int $id, int $userId): void {
        getDB()->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $userId]);
    }

    public static function markAllRead(int $userId): void {
        getDB()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    }
}
