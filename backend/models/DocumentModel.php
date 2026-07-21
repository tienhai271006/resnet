<?php
/** Truy van bang `documents` va `document_versions` */
class DocumentModel {

    /** Danh sach cong khai / cua chinh minh, co loc + phan trang */
    public static function paginateSearch(array $where, array $params, string $sort, string $order, int $page, int $limit): array {
        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT d.id,d.title,d.abstract,d.keywords,d.authors_text,d.cover_image,d.visibility,d.status,
                       d.license_type,d.doi,d.view_count,d.download_count,d.like_count,d.citation_count,d.comment_count,
                       d.created_at, u.id AS owner_id, u.name AS owner_name, u.institution AS owner_institution,
                       u.is_verified AS owner_verified, c.name AS category_name
                FROM documents d
                JOIN users u ON u.id = d.owner_id
                LEFT JOIN categories c ON c.id = d.category_id
                {$whereSql}
                ORDER BY d.{$sort} {$order}";
        return Pagination::run($sql, $params, $page, $limit);
    }

    /** Chi tiet 1 tai lieu kem thong tin chu so huu */
    public static function findWithOwner(int $id): ?array {
        $stmt = getDB()->prepare(
            "SELECT d.*, u.name AS owner_name, u.institution AS owner_institution, u.is_verified AS owner_verified
             FROM documents d JOIN users u ON u.id = d.owner_id WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Ban ghi tho, dung cho update/delete/download/versions */
    public static function find(int $id): ?array {
        $stmt = getDB()->prepare("SELECT * FROM documents WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findOwnerAndDoi(int $id): ?array {
        $stmt = getDB()->prepare("SELECT id,doi FROM documents WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findOwnerId(int $id): ?array {
        $stmt = getDB()->prepare("SELECT owner_id FROM documents WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Dung cho luong xin quyen truy cap: can biet owner/visibility/status */
    public static function findAccessInfo(int $id): ?array {
        $stmt = getDB()->prepare("SELECT id,owner_id,visibility,status FROM documents WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Dung cho like/bookmark/cite - chi cho phep tuong tac voi tai lieu da duoc duyet */
    public static function findApproved(int $id): ?array {
        $stmt = getDB()->prepare("SELECT id,owner_id FROM documents WHERE id=? AND status='approved'");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function incrementView(int $id): void {
        getDB()->prepare("UPDATE documents SET view_count = view_count + 1 WHERE id=?")->execute([$id]);
    }

    public static function incrementDownload(int $id): void {
        getDB()->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id=?")->execute([$id]);
    }

    public static function incrementLikeCount(int $id): void {
        getDB()->prepare("UPDATE documents SET like_count = like_count + 1 WHERE id=?")->execute([$id]);
    }

    public static function decrementLikeCount(int $id): void {
        getDB()->prepare("UPDATE documents SET like_count = GREATEST(like_count - 1, 0) WHERE id=?")->execute([$id]);
    }

    public static function incrementCommentCount(int $id): void {
        getDB()->prepare("UPDATE documents SET comment_count = comment_count + 1 WHERE id=?")->execute([$id]);
    }

    public static function decrementCommentCount(int $id): void {
        getDB()->prepare("UPDATE documents SET comment_count = GREATEST(comment_count - 1, 0) WHERE id=?")->execute([$id]);
    }

    public static function incrementCitationCount(int $id): void {
        getDB()->prepare("UPDATE documents SET citation_count = citation_count + 1 WHERE id=?")->execute([$id]);
    }

    /** Tim tai lieu trung file (SHA-256) - chong dang trung lap / dao van */
    public static function findByHash(string $fileHash, ?int $excludeId = null): ?array {
        $sql = "SELECT id, title, owner_id FROM documents WHERE file_hash = ?";
        $params = [$fileHash];
        if ($excludeId !== null) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): int {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO documents
             (owner_id,category_id,title,abstract,keywords,authors_text,file_path,file_hash,file_size,
              cover_image,visibility,allow_download,status,license_type)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'pending', ?)"
        );
        $stmt->execute([
            $d["owner_id"], $d["category_id"], $d["title"], $d["abstract"], $d["keywords"], $d["authors_text"],
            $d["file_path"], $d["file_hash"], $d["file_size"], $d["cover_image"],
            $d["visibility"], $d["allow_download"], $d["license_type"],
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, string $title, string $abstract, string $keywords, string $visibility, int $allowDownload, string $license): void {
        getDB()->prepare(
            "UPDATE documents SET title=?,abstract=?,keywords=?,visibility=?,allow_download=?,license_type=?,updated_at=NOW() WHERE id=?"
        )->execute([$title, $abstract, $keywords, $visibility, $allowDownload, $license, $id]);
    }

    public static function delete(int $id): void {
        getDB()->prepare("DELETE FROM documents WHERE id=?")->execute([$id]);
    }

    // ---------- Phien ban tai lieu ----------

    public static function nextVersionNo(int $documentId): int {
        $stmt = getDB()->prepare("SELECT MAX(version_no) FROM document_versions WHERE document_id=?");
        $stmt->execute([$documentId]);
        return (int)$stmt->fetchColumn() + 1;
    }

    public static function insertVersion(int $documentId, int $versionNo, string $filePath, string $fileHash, string $changelog, int $uploadedBy): void {
        getDB()->prepare(
            "INSERT INTO document_versions (document_id,version_no,file_path,file_hash,changelog,uploaded_by) VALUES (?,?,?,?,?,?)"
        )->execute([$documentId, $versionNo, $filePath, $fileHash, $changelog, $uploadedBy]);
    }

    public static function listVersions(int $documentId): array {
        $stmt = getDB()->prepare(
            "SELECT id,version_no,changelog,created_at FROM document_versions WHERE document_id=? ORDER BY version_no DESC"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function replaceActiveFile(int $id, string $filePath, string $fileHash, int $fileSize): void {
        getDB()->prepare(
            "UPDATE documents SET file_path=?, file_hash=?, file_size=?, status='pending', updated_at=NOW() WHERE id=?"
        )->execute([$filePath, $fileHash, $fileSize, $id]);
    }

    // ---------- Kiem duyet (admin) ----------

    public static function paginatePending(int $page, int $limit = 10): array {
        $sql = "SELECT d.id,d.title,d.abstract,d.visibility,d.created_at,u.name AS owner_name,u.institution
                FROM documents d JOIN users u ON u.id=d.owner_id
                WHERE d.status='pending' ORDER BY d.created_at ASC";
        return Pagination::run($sql, [], $page, $limit);
    }

    public static function approve(int $id, string $doi): void {
        getDB()->prepare("UPDATE documents SET status='approved', doi=?, reject_reason=NULL WHERE id=?")->execute([$doi, $id]);
    }

    public static function reject(int $id, string $reason): void {
        getDB()->prepare("UPDATE documents SET status='rejected', reject_reason=? WHERE id=?")->execute([$reason, $id]);
    }

    public static function takedown(int $id, string $reason): void {
        getDB()->prepare("UPDATE documents SET status='takedown', reject_reason=? WHERE id=?")->execute([$reason, $id]);
    }

    // ---------- Ho so ca nhan / thong ke ----------

    public static function countPublishedByOwner(int $ownerId): int {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM documents WHERE owner_id=? AND status='approved'");
        $stmt->execute([$ownerId]);
        return (int)$stmt->fetchColumn();
    }

    public static function paginatePublicByOwner(int $ownerId, int $page, int $limit = 12): array {
        $sql = "SELECT id,title,abstract,cover_image,view_count,like_count,citation_count,created_at
                FROM documents WHERE owner_id = ? AND status='approved' AND visibility='public'
                ORDER BY created_at DESC";
        return Pagination::run($sql, [$ownerId], $page, $limit);
    }

    public static function countTotalApproved(): int {
        return (int)getDB()->query("SELECT COUNT(*) FROM documents WHERE status='approved'")->fetchColumn();
    }

    public static function countPending(): int {
        return (int)getDB()->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn();
    }

    public static function monthlyUploads(): array {
        return getDB()->query(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt
             FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status='approved'
             GROUP BY month ORDER BY month"
        )->fetchAll();
    }

    public static function countByVisibility(): array {
        return getDB()->query(
            "SELECT visibility, COUNT(*) AS cnt FROM documents WHERE status='approved' GROUP BY visibility"
        )->fetchAll();
    }

    public static function topByViews(int $limit = 5): array {
        $stmt = getDB()->prepare(
            "SELECT id,title,view_count,download_count,citation_count FROM documents
             WHERE status='approved' ORDER BY view_count DESC LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function myOverview(int $ownerId): array {
        $stmt = getDB()->prepare(
            "SELECT COUNT(*) AS total, SUM(view_count) AS views, SUM(download_count) AS downloads,
                    SUM(like_count) AS likes, SUM(citation_count) AS citations
             FROM documents WHERE owner_id=? AND status='approved'"
        );
        $stmt->execute([$ownerId]);
        return $stmt->fetch();
    }
}
