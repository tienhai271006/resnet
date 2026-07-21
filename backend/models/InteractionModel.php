<?php
/** Truy van bang `likes`, `bookmarks`, `citations` */
class InteractionModel {

    // ---------- Likes ----------

    public static function findLike(int $documentId, int $userId): ?array {
        $stmt = getDB()->prepare("SELECT id FROM likes WHERE document_id=? AND user_id=?");
        $stmt->execute([$documentId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function isLiked(int $documentId, int $userId): bool {
        return self::findLike($documentId, $userId) !== null;
    }

    public static function addLike(int $documentId, int $userId): void {
        getDB()->prepare("INSERT INTO likes (document_id,user_id) VALUES (?,?)")->execute([$documentId, $userId]);
    }

    public static function removeLike(int $id): void {
        getDB()->prepare("DELETE FROM likes WHERE id=?")->execute([$id]);
    }

    // ---------- Bookmarks ----------

    public static function findBookmark(int $documentId, int $userId): ?array {
        $stmt = getDB()->prepare("SELECT id FROM bookmarks WHERE document_id=? AND user_id=?");
        $stmt->execute([$documentId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function isBookmarked(int $documentId, int $userId): bool {
        return self::findBookmark($documentId, $userId) !== null;
    }

    public static function addBookmark(int $documentId, int $userId): void {
        getDB()->prepare("INSERT INTO bookmarks (document_id,user_id) VALUES (?,?)")->execute([$documentId, $userId]);
    }

    public static function removeBookmark(int $id): void {
        getDB()->prepare("DELETE FROM bookmarks WHERE id=?")->execute([$id]);
    }

    public static function paginateBookmarks(int $userId, int $page, int $limit = 12): array {
        $sql = "SELECT d.id,d.title,d.abstract,d.cover_image,d.view_count,d.like_count,d.created_at,
                       u.name AS owner_name, b.created_at AS bookmarked_at
                FROM bookmarks b
                JOIN documents d ON d.id = b.document_id
                JOIN users u ON u.id = d.owner_id
                WHERE b.user_id = ? ORDER BY b.created_at DESC";
        return Pagination::run($sql, [$userId], $page, $limit);
    }

    // ---------- Citations ----------

    /** @throws PDOException neu da trich dan roi (UNIQUE constraint) */
    public static function addCitation(int $citingId, int $citedId, int $createdBy): void {
        getDB()->prepare(
            "INSERT INTO citations (citing_document_id,cited_document_id,created_by) VALUES (?,?,?)"
        )->execute([$citingId, $citedId, $createdBy]);
    }

    public static function citesOf(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT d.id,d.title,u.name AS owner_name FROM citations c
             JOIN documents d ON d.id = c.cited_document_id JOIN users u ON u.id = d.owner_id
             WHERE c.citing_document_id = ?"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function citedByOf(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT d.id,d.title,u.name AS owner_name FROM citations c
             JOIN documents d ON d.id = c.citing_document_id JOIN users u ON u.id = d.owner_id
             WHERE c.cited_document_id = ?"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }
}
