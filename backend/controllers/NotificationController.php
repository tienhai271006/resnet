<?php
require_once __DIR__ . "/../utils/Pagination.php";

class NotificationController {
    /** GET /api/notifications */
    public static function index(): void {
        Auth::required();
        $page = max(1, (int)($_GET["page"] ?? 1));
        $sql = "SELECT n.id,n.type,n.message,n.is_read,n.created_at,n.document_id,
                       a.name AS actor_name, a.avatar AS actor_avatar
                FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
                WHERE n.user_id = ? ORDER BY n.created_at DESC";
        $result = Pagination::run($sql, [$_SESSION["user_id"]], $page, 20);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/notifications/:id/read */
    public static function markRead(int $id): void {
        Auth::required();
        $db = getDB();
        $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")
           ->execute([$id, $_SESSION["user_id"]]);
        Response::ok(null, "Đã đánh dấu đã đọc.");
    }

    /** POST /api/notifications/read-all */
    public static function markAllRead(): void {
        Auth::required();
        $db = getDB();
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION["user_id"]]);
        Response::ok(null, "Đã đánh dấu tất cả đã đọc.");
    }
}
