<?php
require_once __DIR__ . "/../utils/Pagination.php";

class UserController {
    /** GET /api/users/:id - trang ho so cong khai cua nha nghien cuu */
    public static function show(int $id): void {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT id,name,institution,orcid_id,bio,avatar,role,is_verified,created_at FROM users WHERE id=? AND is_active=1"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::err("Không tìm thấy người dùng.", 404);

        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE owner_id=? AND status='approved'");
        $stmt->execute([$id]);
        $user["published_count"] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?");
        $stmt->execute([$id]);
        $user["followers_count"] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE follower_id=?");
        $stmt->execute([$id]);
        $user["following_count"] = (int)$stmt->fetchColumn();

        if (!empty($_SESSION["user_id"])) {
            $stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id=? AND following_id=?");
            $stmt->execute([$_SESSION["user_id"], $id]);
            $user["followed_by_me"] = (bool)$stmt->fetch();
        } else {
            $user["followed_by_me"] = false;
        }

        Response::ok($user);
    }

    /** GET /api/users/:id/documents - cong trinh cong khai cua 1 nha nghien cuu */
    public static function documents(int $id): void {
        $page = max(1, (int)($_GET["page"] ?? 1));
        $sql = "SELECT id,title,abstract,cover_image,view_count,like_count,citation_count,created_at
                FROM documents WHERE owner_id = ? AND status='approved' AND visibility='public'
                ORDER BY created_at DESC";
        $result = Pagination::run($sql, [$id], $page, 12);
        Response::paged($result["data"], $result["meta"]);
    }

    /** GET /api/admin/users - admin quan ly nguoi dung */
    public static function adminIndex(): void {
        Auth::role("admin");
        $page = max(1, (int)($_GET["page"] ?? 1));
        $role = Sanitize::enum($_GET["role"] ?? "", ["admin", "researcher", "reader"]);
        $where = "1=1"; $params = [];
        if ($role) { $where = "role = ?"; $params[] = $role; }
        $sql = "SELECT id,name,email,role,institution,is_verified,is_active,created_at FROM users WHERE {$where} ORDER BY created_at DESC";
        $result = Pagination::run($sql, $params, $page, 15);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/admin/users/:id/toggle-active - khoa / mo khoa tai khoan */
    public static function toggleActive(int $id): void {
        Auth::role("admin");
        $db = getDB();
        $stmt = $db->prepare("SELECT is_active FROM users WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetchColumn();
        if ($cur === false) Response::err("Không tìm thấy người dùng.", 404);
        $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$cur ? 0 : 1, $id]);
        Response::ok(["is_active" => !$cur], !$cur ? "Đã mở khóa tài khoản." : "Đã khóa tài khoản.");
    }
}
