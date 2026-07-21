<?php
require_once __DIR__ . "/../utils/Pagination.php";

/** BAO VE BAN QUYEN SO: to cao vi pham + tra cuu nhat ky tai ve */
class CopyrightController {

    /** POST /api/documents/:id/report - nguoi dung to cao vi pham ban quyen */
    public static function report(int $docId, array $body): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        if (!$stmt->fetch()) Response::err("Không tìm thấy tài liệu.", 404);

        $reason = Sanitize::enum($body["reason"] ?? "", ["plagiarism", "unauthorized_reupload", "license_violation", "other"]);
        if (!$reason) Response::err("Vui lòng chọn lý do tố cáo hợp lệ.");
        $description = trim($body["description"] ?? "");
        if (mb_strlen($description) < 20) Response::err("Vui lòng mô tả chi tiết tối thiểu 20 ký tự để admin xử lý.");
        $evidence = trim($body["evidence_url"] ?? "") ?: null;

        $db->prepare(
            "INSERT INTO copyright_reports (document_id,reported_by,reason,description,evidence_url) VALUES (?,?,?,?,?)"
        )->execute([$docId, $_SESSION["user_id"], $reason, $description, $evidence]);

        Response::ok(null, "Đã ghi nhận tố cáo. Đội ngũ quản trị sẽ xem xét trong thời gian sớm nhất.");
    }

    /** GET /api/copyright-reports - admin xem hang doi xu ly */
    public static function index(): void {
        Auth::role("admin");
        $status = Sanitize::enum($_GET["status"] ?? "", ["open", "reviewing", "resolved", "dismissed"]) ?: "open";
        $page = max(1, (int)($_GET["page"] ?? 1));

        $sql = "SELECT r.id,r.reason,r.description,r.evidence_url,r.status,r.created_at,
                       d.id AS document_id, d.title AS document_title,
                       u.name AS reporter_name
                FROM copyright_reports r
                JOIN documents d ON d.id = r.document_id
                JOIN users u ON u.id = r.reported_by
                WHERE r.status = ?
                ORDER BY r.created_at ASC";
        $result = Pagination::run($sql, [$status], $page, 10);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/copyright-reports/:id/resolve - admin xu ly to cao */
    public static function resolve(int $id, array $body): void {
        Auth::role("admin");
        $status = Sanitize::enum($body["status"] ?? "", ["resolved", "dismissed"]);
        if (!$status) Response::err("Trạng thái xử lý không hợp lệ.");
        $note = Sanitize::text($body["resolution_note"] ?? "", 300);

        $db = getDB();
        $stmt = $db->prepare("SELECT document_id FROM copyright_reports WHERE id=?");
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        if (!$report) Response::err("Không tìm thấy tố cáo.", 404);

        $db->prepare(
            "UPDATE copyright_reports SET status=?, resolved_by=?, resolution_note=?, resolved_at=NOW() WHERE id=?"
        )->execute([$status, $_SESSION["user_id"], $note, $id]);

        // Neu xac dinh vi pham that -> tu dong go bai (takedown)
        if ($status === "resolved" && !empty($body["takedown"])) {
            $db->prepare("UPDATE documents SET status='takedown', reject_reason=? WHERE id=?")
               ->execute(["Vi phạm bản quyền: " . $note, $report["document_id"]]);
        }

        Response::ok(null, "Đã cập nhật trạng thái tố cáo.");
    }

    /** GET /api/documents/:id/download-logs - chu so huu tra cuu ai da tai file (truy vet ro ri) */
    public static function downloadLogs(int $docId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT owner_id FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        $stmt = $db->prepare(
            "SELECT l.id,l.watermark_token,l.ip_address,l.created_at,u.name,u.email
             FROM download_logs l JOIN users u ON u.id = l.user_id
             WHERE l.document_id=? ORDER BY l.created_at DESC LIMIT 200"
        );
        $stmt->execute([$docId]);
        Response::ok($stmt->fetchAll());
    }
}
