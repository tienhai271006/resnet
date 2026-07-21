<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/FollowModel.php";

class UserController {
    /** GET /api/users/:id - trang ho so cong khai cua nha nghien cuu */
    public static function show(int $id): void {
        $user = UserModel::findPublicById($id);
        if (!$user) Response::err("Không tìm thấy người dùng.", 404);

        $user["published_count"] = DocumentModel::countPublishedByOwner($id);
        $user["followers_count"] = FollowModel::countFollowers($id);
        $user["following_count"] = FollowModel::countFollowing($id);

        $user["followed_by_me"] = !empty($_SESSION["user_id"])
            ? FollowModel::isFollowing((int)$_SESSION["user_id"], $id)
            : false;

        Response::ok($user);
    }

    /** GET /api/users/:id/documents - cong trinh cong khai cua 1 nha nghien cuu */
    public static function documents(int $id): void {
        $page = max(1, (int)($_GET["page"] ?? 1));
        $result = DocumentModel::paginatePublicByOwner($id, $page, 12);
        Response::paged($result["data"], $result["meta"]);
    }

    /** GET /api/admin/users - admin quan ly nguoi dung */
    public static function adminIndex(): void {
        Auth::role("admin");
        $page = max(1, (int)($_GET["page"] ?? 1));
        $role = Sanitize::enum($_GET["role"] ?? "", ["admin", "researcher", "reader"]);
        $result = UserModel::paginateAdmin($role ?: null, $page, 15);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/admin/users/:id/toggle-active - khoa / mo khoa tai khoan */
    public static function toggleActive(int $id): void {
        Auth::role("admin");
        $cur = UserModel::getActiveFlag($id);
        if ($cur === false) Response::err("Không tìm thấy người dùng.", 404);
        UserModel::setActiveFlag($id, !$cur);
        Response::ok(["is_active" => !$cur], !$cur ? "Đã mở khóa tài khoản." : "Đã khóa tài khoản.");
    }
}
