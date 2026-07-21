<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/FollowModel.php";
require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../models/NotificationModel.php";

/** CO CHE TUONG TAC: theo doi nha nghien cuu + bang tin */
class FollowController {

    /** POST /api/users/:id/follow - toggle theo doi */
    public static function toggle(int $targetId): void {
        Auth::required();
        if ($targetId === (int)$_SESSION["user_id"]) Response::err("Không thể tự theo dõi chính mình.");

        if (!UserModel::exists($targetId)) {
            Response::err("Không tìm thấy người dùng.", 404);
        }

        $existing = FollowModel::find((int)$_SESSION["user_id"], $targetId);

        if ($existing) {
            FollowModel::delete((int)$existing["id"]);
            Response::ok(["following" => false], "Đã bỏ theo dõi.");
        }

        FollowModel::create((int)$_SESSION["user_id"], $targetId);
        NotificationModel::create($targetId, "follow", (int)$_SESSION["user_id"], null, $_SESSION["user_name"] . " da theo doi ban.");

        Response::ok(["following" => true], "Đã theo dõi.");
    }

    /** GET /api/users/:id/followers */
    public static function followers(int $userId): void {
        Response::ok(FollowModel::listFollowers($userId));
    }

    /** GET /api/users/:id/following */
    public static function following(int $userId): void {
        Response::ok(FollowModel::listFollowing($userId));
    }

    /** GET /api/feed - bang tin cong trinh moi tu nhung nguoi dang theo doi */
    public static function feed(): void {
        Auth::required();
        $page = max(1, (int)($_GET["page"] ?? 1));
        $result = FollowModel::paginateFeed((int)$_SESSION["user_id"], $page, 15);
        Response::paged($result["data"], $result["meta"]);
    }
}
