<?php
/** Truy van bang `comments` */
class CommentModel {

    /** Danh sach phang (chua dung cay), dung de FE/Controller tu dung cay tra loi */
    public static function listFlatByDocument(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT c.id,c.parent_id,c.content,c.created_at,u.id AS user_id,u.name,u.avatar,u.is_verified
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.document_id=? AND c.is_hidden=0 ORDER BY c.created_at ASC"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function findParentInDocument(int $parentId, int $documentId): ?array {
        $stmt = getDB()->prepare("SELECT id FROM comments WHERE id=? AND document_id=?");
        $stmt->execute([$parentId, $documentId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $documentId, int $userId, ?int $parentId, string $content): int {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO comments (document_id,user_id,parent_id,content) VALUES (?,?,?,?)");
        $stmt->execute([$documentId, $userId, $parentId, $content]);
        return (int)$db->lastInsertId();
    }

    public static function find(int $id): ?array {
        $stmt = getDB()->prepare("SELECT * FROM comments WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function delete(int $id): void {
        getDB()->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
    }

    public static function hide(int $id): void {
        getDB()->prepare("UPDATE comments SET is_hidden=1 WHERE id=?")->execute([$id]);
    }
}
