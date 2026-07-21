<?php
/** Truy van bang `follows` */
class FollowModel {

    public static function find(int $followerId, int $followingId): ?array {
        $stmt = getDB()->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
        $stmt->execute([$followerId, $followingId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $followerId, int $followingId): void {
        getDB()->prepare("INSERT INTO follows (follower_id,following_id) VALUES (?,?)")->execute([$followerId, $followingId]);
    }

    public static function delete(int $id): void {
        getDB()->prepare("DELETE FROM follows WHERE id=?")->execute([$id]);
    }

    public static function listFollowers(int $userId): array {
        $stmt = getDB()->prepare(
            "SELECT u.id,u.name,u.institution,u.avatar,u.is_verified
             FROM follows f JOIN users u ON u.id = f.follower_id WHERE f.following_id=?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function listFollowing(int $userId): array {
        $stmt = getDB()->prepare(
            "SELECT u.id,u.name,u.institution,u.avatar,u.is_verified
             FROM follows f JOIN users u ON u.id = f.following_id WHERE f.follower_id=?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countFollowers(int $userId): int {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function countFollowing(int $userId): int {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM follows WHERE follower_id=?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function isFollowing(int $followerId, int $followingId): bool {
        $stmt = getDB()->prepare("SELECT 1 FROM follows WHERE follower_id=? AND following_id=?");
        $stmt->execute([$followerId, $followingId]);
        return (bool)$stmt->fetch();
    }

    /** Bang tin: cong trinh moi tu nhung nguoi dang theo doi */
    public static function paginateFeed(int $followerId, int $page, int $limit = 15): array {
        $sql = "SELECT d.id,d.title,d.abstract,d.cover_image,d.created_at,d.like_count,d.comment_count,
                       u.id AS owner_id, u.name AS owner_name, u.avatar AS owner_avatar
                FROM documents d
                JOIN users u ON u.id = d.owner_id
                JOIN follows f ON f.following_id = d.owner_id
                WHERE f.follower_id = ? AND d.status = 'approved'
                ORDER BY d.created_at DESC";
        return Pagination::run($sql, [$followerId], $page, $limit);
    }
}
