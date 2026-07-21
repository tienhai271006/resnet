<?php
require_once __DIR__ . "/../utils/Pagination.php";

/** CO CHE TUONG TAC: theo doi nha nghien cuu + bang tin */
class FollowController {

    /** POST /api/users/:id/follow - toggle theo doi */
    public static function toggle(int $targetId): void {
        Auth::required();
        if ($targetId === (int)$_SESSION["user_id"]) Response::err("Không thể tự theo dõi chính mình.");

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
        $stmt->execute([$targetId]);
        if (!$stmt->fetch()) Response::err("Không tìm thấy người dùng.", 404);

        $stmt = $db->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
        $stmt->execute([$_SESSION["user_id"], $targetId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $db->prepare("DELETE FROM follows WHERE id=?")->execute([$existing["id"]]);
            Response::ok(["following" => false], "Đã bỏ theo dõi.");
        }

        $db->prepare("INSERT INTO follows (follower_id,following_id) VALUES (?,?)")->execute([$_SESSION["user_id"], $targetId]);
        $db->prepare(
            "INSERT INTO notifications (user_id,type,actor_id,message) VALUES (?, 'follow', ?, ?)"
        )->execute([$targetId, $_SESSION["user_id"], $_SESSION["user_name"] . " da theo doi ban."]);

        Response::ok(["following" => true], "Đã theo dõi.");
    }

    /** GET /api/users/:id/followers */
    public static function followers(int $userId): void {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT u.id,u.name,u.institution,u.avatar,u.is_verified
             FROM follows f JOIN users u ON u.id = f.follower_id WHERE f.following_id=?"
        );
        $stmt->execute([$userId]);
        Response::ok($stmt->fetchAll());
    }

    /** GET /api/users/:id/following */
    public static function following(int $userId): void {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT u.id,u.name,u.institution,u.avatar,u.is_verified
             FROM follows f JOIN users u ON u.id = f.following_id WHERE f.follower_id=?"
        );
        $stmt->execute([$userId]);
        Response::ok($stmt->fetchAll());
    }

    /** GET /api/feed - bang tin cong trinh moi tu nhung nguoi dang theo doi */
    public static function feed(): void {
        Auth::required();
        $page = max(1, (int)($_GET["page"] ?? 1));
        $sql = "SELECT d.id,d.title,d.abstract,d.cover_image,d.created_at,d.like_count,d.comment_count,
                       u.id AS owner_id, u.name AS owner_name, u.avatar AS owner_avatar
                FROM documents d
                JOIN users u ON u.id = d.owner_id
                JOIN follows f ON f.following_id = d.owner_id
                WHERE f.follower_id = ? AND d.status = 'approved'
                ORDER BY d.created_at DESC";
        $result = Pagination::run($sql, [$_SESSION["user_id"]], $page, 15);
        Response::paged($result["data"], $result["meta"]);
    }
}
