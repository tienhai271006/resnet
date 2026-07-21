<?php
require_once __DIR__ . "/../utils/Pagination.php";

/** CO CHE TUONG TAC: binh luan / thao luan khoa hoc theo cay */
class CommentController {

    /** GET /api/documents/:id/comments */
    public static function index(int $docId): void {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT c.id,c.parent_id,c.content,c.created_at,u.id AS user_id,u.name,u.avatar,u.is_verified
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.document_id=? AND c.is_hidden=0 ORDER BY c.created_at ASC"
        );
        $stmt->execute([$docId]);
        $flat = $stmt->fetchAll();

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
        $db = getDB();
        $stmt = $db->prepare("SELECT id,owner_id,status FROM documents WHERE id=?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc || $doc["status"] !== "approved") Response::err("Không thể bình luận tài liệu này.", 404);

        $content = trim($body["content"] ?? "");
        if (mb_strlen($content) < 2) Response::err("Nội dung bình luận quá ngắn.");
        if (mb_strlen($content) > 2000) Response::err("Nội dung bình luận quá dài (tối đa 2000 ký tự).");
        $parentId = (int)($body["parent_id"] ?? 0) ?: null;

        if ($parentId) {
            $stmt = $db->prepare("SELECT id FROM comments WHERE id=? AND document_id=?");
            $stmt->execute([$parentId, $docId]);
            if (!$stmt->fetch()) Response::err("Bình luận gốc không tồn tại.", 404);
        }

        $stmt = $db->prepare("INSERT INTO comments (document_id,user_id,parent_id,content) VALUES (?,?,?,?)");
        $stmt->execute([$docId, $_SESSION["user_id"], $parentId, $content]);
        $commentId = (int)$db->lastInsertId();

        $db->prepare("UPDATE documents SET comment_count = comment_count + 1 WHERE id=?")->execute([$docId]);

        if ((int)$doc["owner_id"] !== (int)$_SESSION["user_id"]) {
            $db->prepare(
                "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
                 VALUES (?, 'comment', ?, ?, ?)"
            )->execute([$doc["owner_id"], $_SESSION["user_id"], $docId, $_SESSION["user_name"] . " da binh luan ve cong trinh cua ban."]);
        }

        Response::ok(["id" => $commentId], "Đã gửi bình luận.");
    }

    /** DELETE /api/comments/:id - chu binh luan hoac admin */
    public static function destroy(int $id): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id=?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) Response::err("Không tìm thấy bình luận.", 404);
        Auth::owns((int)$c["user_id"]);

        $db->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE documents SET comment_count = GREATEST(comment_count - 1, 0) WHERE id=?")->execute([$c["document_id"]]);

        Response::ok(null, "Đã xóa bình luận.");
    }

    /** POST /api/comments/:id/hide - admin an binh luan vi pham */
    public static function hide(int $id): void {
        Auth::role("admin");
        $db = getDB();
        $db->prepare("UPDATE comments SET is_hidden=1 WHERE id=?")->execute([$id]);
        Response::ok(null, "Đã ẩn bình luận.");
    }
}
