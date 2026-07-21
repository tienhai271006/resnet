<?php
/** Truy van bang `access_requests` va `document_permissions` */
class PermissionModel {

    // ---------- Yeu cau xin quyen ----------

    /** @throws PDOException neu da gui yeu cau roi (UNIQUE constraint) */
    public static function createRequest(int $documentId, int $requesterId, string $message): void {
        getDB()->prepare(
            "INSERT INTO access_requests (document_id,requester_id,message) VALUES (?,?,?)"
        )->execute([$documentId, $requesterId, $message]);
    }

    public static function listRequestsForDocument(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT ar.id,ar.message,ar.status,ar.created_at,u.id AS user_id,u.name,u.institution,u.avatar
             FROM access_requests ar JOIN users u ON u.id = ar.requester_id
             WHERE ar.document_id = ? ORDER BY ar.created_at DESC"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /** Lay yeu cau kem owner_id cua tai lieu lien quan (de kiem tra quyen) */
    public static function findRequestWithOwner(int $requestId): ?array {
        $stmt = getDB()->prepare(
            "SELECT ar.*, d.owner_id FROM access_requests ar JOIN documents d ON d.id = ar.document_id WHERE ar.id=?"
        );
        $stmt->execute([$requestId]);
        return $stmt->fetch() ?: null;
    }

    public static function approveRequest(int $requestId, int $reviewedBy): void {
        getDB()->prepare("UPDATE access_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$reviewedBy, $requestId]);
    }

    public static function rejectRequest(int $requestId, int $reviewedBy): void {
        getDB()->prepare("UPDATE access_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$reviewedBy, $requestId]);
    }

    // ---------- Quyen da cap ----------

    public static function grant(int $documentId, int $userId, string $permType, int $grantedBy): void {
        getDB()->prepare(
            "INSERT INTO document_permissions (document_id,user_id,permission_type,granted_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), created_at = NOW()"
        )->execute([$documentId, $userId, $permType, $grantedBy]);
    }

    public static function listGranted(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT p.id,p.permission_type,p.expires_at,p.created_at,u.name,u.email
             FROM document_permissions p JOIN users u ON u.id = p.user_id
             WHERE p.document_id=? ORDER BY p.created_at DESC"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function findGrantedWithOwner(int $permId): ?array {
        $stmt = getDB()->prepare(
            "SELECT p.id, d.owner_id FROM document_permissions p JOIN documents d ON d.id = p.document_id WHERE p.id=?"
        );
        $stmt->execute([$permId]);
        return $stmt->fetch() ?: null;
    }

    public static function revoke(int $permId): void {
        getDB()->prepare("DELETE FROM document_permissions WHERE id=?")->execute([$permId]);
    }

    /** Cac loai quyen con hieu luc cua 1 nguoi dung tren 1 tai lieu - dung boi Auth::checkDocumentAccess */
    public static function activeTypesForUser(int $documentId, int $userId): array {
        $stmt = getDB()->prepare(
            "SELECT permission_type FROM document_permissions
             WHERE document_id = ? AND user_id = ? AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([$documentId, $userId]);
        return array_column($stmt->fetchAll(), "permission_type");
    }
}
