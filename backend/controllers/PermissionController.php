<?php
require_once __DIR__ . "/../utils/Pagination.php";

/**
 * Quan ly PHAN QUYEN TAI LIEU cho tai lieu 'restricted':
 * luong xin quyen -> chu so huu/admin duyet -> cap document_permissions.
 */
class PermissionController {

    /** POST /api/documents/:id/access-requests - doc gia xin quyen tai file restricted */
    public static function requestAccess(int $docId, array $body): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT id,owner_id,visibility,status FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        if ($doc["visibility"] !== "restricted") Response::err("Tài liệu này không yêu cầu xin quyền.", 400);
        if ((int)$doc["owner_id"] === (int)$_SESSION["user_id"]) Response::err("Bạn là chủ sở hữu tài liệu này.", 400);

        $message = Sanitize::text($body["message"] ?? "", 500);

        try {
            $stmt = $db->prepare(
                "INSERT INTO access_requests (document_id,requester_id,message) VALUES (?,?,?)"
            );
            $stmt->execute([$docId, $_SESSION["user_id"], $message]);
        } catch (PDOException $e) {
            Response::err("Bạn đã gửi yêu cầu cho tài liệu này rồi.", 409);
        }

        $db->prepare(
            "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
             VALUES (?, 'access_request', ?, ?, ?)"
        )->execute([$doc["owner_id"], $_SESSION["user_id"], $docId, $_SESSION["user_name"] . " da yeu cau quyen truy cap tai lieu cua ban."]);

        Response::ok(null, "Đã gửi yêu cầu. Bạn sẽ nhận thông báo khi tác giả phản hồi.");
    }

    /** GET /api/documents/:id/access-requests - chu so huu xem danh sach yeu cau */
    public static function listRequests(int $docId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT owner_id FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        $stmt = $db->prepare(
            "SELECT ar.id,ar.message,ar.status,ar.created_at,u.id AS user_id,u.name,u.institution,u.avatar
             FROM access_requests ar JOIN users u ON u.id = ar.requester_id
             WHERE ar.document_id = ? ORDER BY ar.created_at DESC"
        );
        $stmt->execute([$docId]);
        Response::ok($stmt->fetchAll());
    }

    /** POST /api/access-requests/:id/approve */
    public static function approveRequest(int $requestId, array $body): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT ar.*, d.owner_id FROM access_requests ar JOIN documents d ON d.id = ar.document_id WHERE ar.id=?"
        );
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req) Response::err("Không tìm thấy yêu cầu.", 404);
        Auth::owns((int)$req["owner_id"]);

        $permType = Sanitize::enum($body["permission_type"] ?? "download", ["view", "download"]) ?: "download";

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE access_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
               ->execute([$_SESSION["user_id"], $requestId]);

            $db->prepare(
                "INSERT INTO document_permissions (document_id,user_id,permission_type,granted_by)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), created_at = NOW()"
            )->execute([$req["document_id"], $req["requester_id"], $permType, $_SESSION["user_id"]]);

            $db->prepare(
                "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
                 VALUES (?, 'access_approved', ?, ?, 'Yeu cau truy cap tai lieu cua ban da duoc chap thuan.')"
            )->execute([$req["requester_id"], $_SESSION["user_id"], $req["document_id"]]);

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
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT ar.*, d.owner_id FROM access_requests ar JOIN documents d ON d.id = ar.document_id WHERE ar.id=?"
        );
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req) Response::err("Không tìm thấy yêu cầu.", 404);
        Auth::owns((int)$req["owner_id"]);

        $db->prepare("UPDATE access_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$_SESSION["user_id"], $requestId]);

        $db->prepare(
            "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
             VALUES (?, 'access_rejected', ?, ?, 'Yeu cau truy cap tai lieu cua ban da bi tu choi.')"
        )->execute([$req["requester_id"], $_SESSION["user_id"], $req["document_id"]]);

        Response::ok(null, "Đã từ chối yêu cầu.");
    }

    /** GET /api/documents/:id/permissions - danh sach nguoi da duoc cap quyen */
    public static function listGranted(int $docId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT owner_id FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        $stmt = $db->prepare(
            "SELECT p.id,p.permission_type,p.expires_at,p.created_at,u.name,u.email
             FROM document_permissions p JOIN users u ON u.id = p.user_id
             WHERE p.document_id=? ORDER BY p.created_at DESC"
        );
        $stmt->execute([$docId]);
        Response::ok($stmt->fetchAll());
    }

    /** DELETE /api/permissions/:id - thu hoi quyen truy cap */
    public static function revoke(int $permId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT p.id, d.owner_id FROM document_permissions p JOIN documents d ON d.id = p.document_id WHERE p.id=?"
        );
        $stmt->execute([$permId]);
        $row = $stmt->fetch();
        if (!$row) Response::err("Không tìm thấy bản ghi.", 404);
        Auth::owns((int)$row["owner_id"]);

        $db->prepare("DELETE FROM document_permissions WHERE id=?")->execute([$permId]);
        Response::ok(null, "Đã thu hồi quyền truy cập.");
    }
}
