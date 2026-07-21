<?php
/** Truy van bang `users` */
class UserModel {

    public static function findByEmail(string $email): ?array {
        $stmt = getDB()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /** Dung cho dang nhap - chi lay tai khoan dang hoat dong */
    public static function findActiveByEmail(string $email): ?array {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /** Ho so day du cua chinh minh (GET /auth/me) */
    public static function findProfileById(int $id): ?array {
        $stmt = getDB()->prepare(
            "SELECT id,name,email,role,institution,orcid_id,bio,avatar,is_verified,created_at FROM users WHERE id=?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Ho so cong khai (GET /users/:id) - chi tai khoan dang hoat dong */
    public static function findPublicById(int $id): ?array {
        $stmt = getDB()->prepare(
            "SELECT id,name,institution,orcid_id,bio,avatar,role,is_verified,created_at FROM users WHERE id=? AND is_active=1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $hash, string $role, ?string $institution, bool $isVerified): int {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO users (name,email,password,role,institution,is_verified) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([$name, $email, $hash, $role, $institution, $isVerified ? 1 : 0]);
        return (int)$db->lastInsertId();
    }

    public static function updatePassword(int $id, string $hash): void {
        getDB()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
    }

    public static function touchLastLogin(int $id): void {
        getDB()->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$id]);
    }

    public static function updateProfile(int $id, string $name, string $bio, string $institution, string $orcid, ?string $avatarPath): void {
        $sql = "UPDATE users SET name=?, bio=?, institution=?, orcid_id=?" . ($avatarPath ? ", avatar=?" : "") . " WHERE id=?";
        $params = [$name, $bio, $institution, $orcid];
        if ($avatarPath) $params[] = $avatarPath;
        $params[] = $id;
        getDB()->prepare($sql)->execute($params);
    }

    public static function verifyResearcher(int $id): void {
        getDB()->prepare("UPDATE users SET is_verified=1 WHERE id=? AND role='researcher'")->execute([$id]);
    }

    public static function exists(int $id): bool {
        $stmt = getDB()->prepare("SELECT id FROM users WHERE id=?");
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }

    public static function countAll(): int {
        return (int)getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public static function getVerifiedFlag(int $id): bool {
        $stmt = getDB()->prepare("SELECT is_verified FROM users WHERE id=?");
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    /** Admin: danh sach nguoi dung co phan trang + loc theo role */
    public static function paginateAdmin(?string $role, int $page, int $limit = 15): array {
        $where = "1=1"; $params = [];
        if ($role) { $where = "role = ?"; $params[] = $role; }
        $sql = "SELECT id,name,email,role,institution,is_verified,is_active,created_at FROM users WHERE {$where} ORDER BY created_at DESC";
        return Pagination::run($sql, $params, $page, $limit);
    }

    public static function getActiveFlag(int $id): int|false {
        $stmt = getDB()->prepare("SELECT is_active FROM users WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public static function setActiveFlag(int $id, bool $active): void {
        getDB()->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$active ? 1 : 0, $id]);
    }
}
