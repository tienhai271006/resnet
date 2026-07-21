<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/CommentModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/NotificationModel.php";

/** CO CHE TUONG TAC: binh luan / thao luan khoa hoc theo cay */
class CommentController {

    /** GET /api/documents/:id/comments */
    public static function index(int $docId): void {
        $flat = CommentModel::listFlatByDocument($docId);

        // Dung cay tra loi tu danh sach phang
        $byParent = [];
        foreach ($flat as $row) {
            $byParent[$row["parent_id"] ?? 0][] = $row;
        }
        $build = function ($parentId) use (&$build, &$byParent) {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $node) {
                $node["replies"] = $build($node["id"]);
                $out[] = $node;
            }
            return $out;
        };

        Response::ok($build(0));
    }

    /** POST /api/documents/:id/comments */
    public static function store(int $docId, array $body): void {
        Auth::required();
        $doc = DocumentModel::find($docId);
        if (!$doc || $doc["status"] !== "approved") Response::err("Không thể bình luận tài liệu này.", 404);

        $content = trim($body["content"] ?? "");
        if (mb_strlen($content) < 2) Response::err("Nội dung bình luận quá ngắn.");
        if (mb_strlen($content) > 2000) Response::err("Nội dung bình luận quá dài (tối đa 2000 ký tự).");
        $parentId = (int)($body["parent_id"] ?? 0) ?: null;

        if ($parentId && !CommentModel::findParentInDocument($parentId, $docId)) {
            Response::err("Bình luận gốc không tồn tại.", 404);
        }

        $commentId = CommentModel::create($docId, $_SESSION["user_id"], $parentId, $content);
        DocumentModel::incrementCommentCount($docId);

        if ((int)$doc["owner_id"] !== (int)$_SESSION["user_id"]) {
            NotificationModel::create(
                (int)$doc["owner_id"], "comment", (int)$_SESSION["user_id"], $docId,
                $_SESSION["user_name"] . " da binh luan ve cong trinh cua ban."
            );
        }

        Response::ok(["id" => $commentId], "Đã gửi bình luận.");
    }

    /** DELETE /api/comments/:id - chu binh luan hoac admin */
    public static function destroy(int $id): void {
        Auth::required();
        $c = CommentModel::find($id);
        if (!$c) Response::err("Không tìm thấy bình luận.", 404);
        Auth::owns((int)$c["user_id"]);

        CommentModel::delete($id);
        DocumentModel::decrementCommentCount((int)$c["document_id"]);

        Response::ok(null, "Đã xóa bình luận.");
    }

    /** POST /api/comments/:id/hide - admin an binh luan vi pham */
    public static function hide(int $id): void {
        Auth::role("admin");
        CommentModel::hide($id);
        Response::ok(null, "Đã ẩn bình luận.");
    }
}
