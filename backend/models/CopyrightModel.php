<?php
/** Truy van bang `copyright_reports` va `download_logs` */
class CopyrightModel {

    public static function createReport(int $documentId, int $reportedBy, string $reason, string $description, ?string $evidenceUrl): void {
        getDB()->prepare(
            "INSERT INTO copyright_reports (document_id,reported_by,reason,description,evidence_url) VALUES (?,?,?,?,?)"
        )->execute([$documentId, $reportedBy, $reason, $description, $evidenceUrl]);
    }

    public static function paginateByStatus(string $status, int $page, int $limit = 10): array {
        $sql = "SELECT r.id,r.reason,r.description,r.evidence_url,r.status,r.created_at,
                       d.id AS document_id, d.title AS document_title,
                       u.name AS reporter_name
                FROM copyright_reports r
                JOIN documents d ON d.id = r.document_id
                JOIN users u ON u.id = r.reported_by
                WHERE r.status = ?
                ORDER BY r.created_at ASC";
        return Pagination::run($sql, [$status], $page, $limit);
    }

    public static function find(int $id): ?array {
        $stmt = getDB()->prepare("SELECT document_id FROM copyright_reports WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function resolve(int $id, string $status, int $resolvedBy, string $note): void {
        getDB()->prepare(
            "UPDATE copyright_reports SET status=?, resolved_by=?, resolution_note=?, resolved_at=NOW() WHERE id=?"
        )->execute([$status, $resolvedBy, $note, $id]);
    }

    // ---------- Nhat ky tai ve ----------

    public static function logDownload(int $documentId, int $userId, string $token, ?string $ip, string $userAgent): void {
        getDB()->prepare(
            "INSERT INTO download_logs (document_id,user_id,watermark_token,ip_address,user_agent) VALUES (?,?,?,?,?)"
        )->execute([$documentId, $userId, $token, $ip, $userAgent]);
    }

    public static function listDownloadLogs(int $documentId, int $limit = 200): array {
        $stmt = getDB()->prepare(
            "SELECT l.id,l.watermark_token,l.ip_address,l.created_at,u.name,u.email
             FROM download_logs l JOIN users u ON u.id = l.user_id
             WHERE l.document_id=? ORDER BY l.created_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    public static function countDownloadsTotal(): int {
        return (int)getDB()->query("SELECT COUNT(*) FROM download_logs")->fetchColumn();
    }

    public static function countOpenReports(): int {
        return (int)getDB()->query("SELECT COUNT(*) FROM copyright_reports WHERE status='open'")->fetchColumn();
    }
}
