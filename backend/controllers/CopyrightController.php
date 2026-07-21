<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/CopyrightModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";

/** BAO VE BAN QUYEN SO: to cao vi pham + tra cuu nhat ky tai ve */
class CopyrightController {

    /** POST /api/documents/:id/report - nguoi dung to cao vi pham ban quyen */
    public static function report(int $docId, array $body): void {
        Auth::required();
        if (!DocumentModel::find($docId)) Response::err("Không tìm thấy tài liệu.", 404);

        $reason = Sanitize::enum($body["reason"] ?? "", ["plagiarism", "unauthorized_reupload", "license_violation", "other"]);
        if (!$reason) Response::err("Vui lòng chọn lý do tố cáo hợp lệ.");
        $description = trim($body["description"] ?? "");
        if (mb_strlen($description) < 20) Response::err("Vui lòng mô tả chi tiết tối thiểu 20 ký tự để admin xử lý.");
        $evidence = trim($body["evidence_url"] ?? "") ?: null;

        CopyrightModel::createReport($docId, $_SESSION["user_id"], $reason, $description, $evidence);

        Response::ok(null, "Đã ghi nhận tố cáo. Đội ngũ quản trị sẽ xem xét trong thời gian sớm nhất.");
    }

    /** GET /api/copyright-reports - admin xem hang doi xu ly */
    public static function index(): void {
        Auth::role("admin");
        $status = Sanitize::enum($_GET["status"] ?? "", ["open", "reviewing", "resolved", "dismissed"]) ?: "open";
        $page = max(1, (int)($_GET["page"] ?? 1));

        $result = CopyrightModel::paginateByStatus($status, $page, 10);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/copyright-reports/:id/resolve - admin xu ly to cao */
    public static function resolve(int $id, array $body): void {
        Auth::role("admin");
        $status = Sanitize::enum($body["status"] ?? "", ["resolved", "dismissed"]);
        if (!$status) Response::err("Trạng thái xử lý không hợp lệ.");
        $note = Sanitize::text($body["resolution_note"] ?? "", 300);

        $report = CopyrightModel::find($id);
        if (!$report) Response::err("Không tìm thấy tố cáo.", 404);

        CopyrightModel::resolve($id, $status, $_SESSION["user_id"], $note);

        // Neu xac dinh vi pham that -> tu dong go bai (takedown)
        if ($status === "resolved" && !empty($body["takedown"])) {
            DocumentModel::takedown((int)$report["document_id"], "Vi phạm bản quyền: " . $note);
        }

        Response::ok(null, "Đã cập nhật trạng thái tố cáo.");
    }

    /** GET /api/documents/:id/download-logs - chu so huu tra cuu ai da tai file (truy vet ro ri) */
    public static function downloadLogs(int $docId): void {
        Auth::required();
        $doc = DocumentModel::findOwnerId($docId);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        Response::ok(CopyrightModel::listDownloadLogs($docId, 200));
    }
}
