<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/NotificationModel.php";

class NotificationController {
    /** GET /api/notifications */
    public static function index(): void {
        Auth::required();
        $page = max(1, (int)($_GET["page"] ?? 1));
        $result = NotificationModel::paginateByUser((int)$_SESSION["user_id"], $page, 20);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/notifications/:id/read */
    public static function markRead(int $id): void {
        Auth::required();
        NotificationModel::markRead($id, (int)$_SESSION["user_id"]);
        Response::ok(null, "Đã đánh dấu đã đọc.");
    }

    /** POST /api/notifications/read-all */
    public static function markAllRead(): void {
        Auth::required();
        NotificationModel::markAllRead((int)$_SESSION["user_id"]);
        Response::ok(null, "Đã đánh dấu tất cả đã đọc.");
    }
}
