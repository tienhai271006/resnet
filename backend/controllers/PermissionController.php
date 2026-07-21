<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/PermissionModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/NotificationModel.php";

/**
 * Quan ly PHAN QUYEN TAI LIEU cho tai lieu 'restricted':
 * luong xin quyen -> chu so huu/admin duyet -> cap document_permissions.
 */
class PermissionController {

    /** POST /api/documents/:id/access-requests - doc gia xin quyen tai file restricted */
    public static function requestAccess(int $docId, array $body): void {
        Auth::required();
        $doc = DocumentModel::findAccessInfo($docId);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        if ($doc["visibility"] !== "restricted") Response::err("Tài liệu này không yêu cầu xin quyền.", 400);
        if ((int)$doc["owner_id"] === (int)$_SESSION["user_id"]) Response::err("Bạn là chủ sở hữu tài liệu này.", 400);

        $message = Sanitize::text($body["message"] ?? "", 500);

        try {
            PermissionModel::createRequest($docId, $_SESSION["user_id"], $message);
        } catch (PDOException $e) {
            Response::err("Bạn đã gửi yêu cầu cho tài liệu này rồi.", 409);
        }

        NotificationModel::create(
            (int)$doc["owner_id"], "access_request", (int)$_SESSION["user_id"], $docId,
            $_SESSION["user_name"] . " da yeu cau quyen truy cap tai lieu cua ban."
        );

        Response::ok(null, "Đã gửi yêu cầu. Bạn sẽ nhận thông báo khi tác giả phản hồi.");
    }

    /** GET /api/documents/:id/access-requests - chu so huu xem danh sach yeu cau */
    public static function listRequests(int $docId): void {
        Auth::required();
        $doc = DocumentModel::findOwnerId($docId);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        Response::ok(PermissionModel::listRequestsForDocument($docId));
    }

    /** POST /api/access-requests/:id/approve */
    public static function approveRequest(int $requestId, array $body): void {
        Auth::required();
        $req = PermissionModel::findRequestWithOwner($requestId);
        if (!$req) Response::err("Không tìm thấy yêu cầu.", 404);
        Auth::owns((int)$req["owner_id"]);

        $permType = Sanitize::enum($body["permission_type"] ?? "download", ["view", "download"]) ?: "download";

        $db = getDB();
        $db->beginTransaction();
        try {
            PermissionModel::approveRequest($requestId, $_SESSION["user_id"]);
            PermissionModel::grant((int)$req["document_id"], (int)$req["requester_id"], $permType, $_SESSION["user_id"]);
            NotificationModel::create(
                (int)$req["requester_id"], "access_approved", (int)$_SESSION["user_id"], (int)$req["document_id"],
                "Yeu cau truy cap tai lieu cua ban da duoc chap thuan."
            );
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::err("Lỗi cấp quyền truy cập.", 500);
        }

        Response::ok(null, "Đã cấp quyền truy cập.");
    }

    /** POST /api/access-requests/:id/reject */
    public static function rejectRequest(int $requestId): void {
        Auth::required();
        $req = PermissionModel::findRequestWithOwner($requestId);
        if (!$req) Response::err("Không tìm thấy yêu cầu.", 404);
        Auth::owns((int)$req["owner_id"]);

        PermissionModel::rejectRequest($requestId, $_SESSION["user_id"]);
        NotificationModel::create(
            (int)$req["requester_id"], "access_rejected", (int)$_SESSION["user_id"], (int)$req["document_id"],
            "Yeu cau truy cap tai lieu cua ban da bi tu choi."
        );

        Response::ok(null, "Đã từ chối yêu cầu.");
    }

    /** GET /api/documents/:id/permissions - danh sach nguoi da duoc cap quyen */
    public static function listGranted(int $docId): void {
        Auth::required();
        $doc = DocumentModel::findOwnerId($docId);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        Response::ok(PermissionModel::listGranted($docId));
    }

    /** DELETE /api/permissions/:id - thu hoi quyen truy cap */
    public static function revoke(int $permId): void {
        Auth::required();
        $row = PermissionModel::findGrantedWithOwner($permId);
        if (!$row) Response::err("Không tìm thấy bản ghi.", 404);
        Auth::owns((int)$row["owner_id"]);

        PermissionModel::revoke($permId);
        Response::ok(null, "Đã thu hồi quyền truy cập.");
    }
}
