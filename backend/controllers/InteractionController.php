<?php
/** CO CHE TUONG TAC: thich, luu (bookmark), trich dan giua cac cong trinh */
class InteractionController {

    /** POST /api/documents/:id/like - toggle thich */
    public static function toggleLike(int $docId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT id,owner_id FROM documents WHERE id=? AND status='approved'");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);

        $stmt = $db->prepare("SELECT id FROM likes WHERE document_id=? AND user_id=?");
        $stmt->execute([$docId, $_SESSION["user_id"]]);
        $existing = $stmt->fetch();

        if ($existing) {
            $db->prepare("DELETE FROM likes WHERE id=?")->execute([$existing["id"]]);
            $db->prepare("UPDATE documents SET like_count = GREATEST(like_count - 1, 0) WHERE id=?")->execute([$docId]);
            Response::ok(["liked" => false], "Đã bỏ thích.");
        }

        $db->prepare("INSERT INTO likes (document_id,user_id) VALUES (?,?)")->execute([$docId, $_SESSION["user_id"]]);
        $db->prepare("UPDATE documents SET like_count = like_count + 1 WHERE id=?")->execute([$docId]);

        if ((int)$doc["owner_id"] !== (int)$_SESSION["user_id"]) {
            $db->prepare(
                "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
                 VALUES (?, 'like', ?, ?, ?)"
            )->execute([$doc["owner_id"], $_SESSION["user_id"], $docId, $_SESSION["user_name"] . " da thich cong trinh cua ban."]);
        }

        Response::ok(["liked" => true], "Đã thích.");
    }

    /** POST /api/documents/:id/bookmark - toggle luu vao thu vien ca nhan */
    public static function toggleBookmark(int $docId): void {
        Auth::required();
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM documents WHERE id=? AND status='approved'");
        $stmt->execute([$docId]);
        if (!$stmt->fetch()) Response::err("Không tìm thấy tài liệu.", 404);

        $stmt = $db->prepare("SELECT id FROM bookmarks WHERE document_id=? AND user_id=?");
        $stmt->execute([$docId, $_SESSION["user_id"]]);
        $existing = $stmt->fetch();

        if ($existing) {
            $db->prepare("DELETE FROM bookmarks WHERE id=?")->execute([$existing["id"]]);
            Response::ok(["bookmarked" => false], "Đã bỏ lưu.");
        }
        $db->prepare("INSERT INTO bookmarks (document_id,user_id) VALUES (?,?)")->execute([$docId, $_SESSION["user_id"]]);
        Response::ok(["bookmarked" => true], "Đã lưu vào thư viện của bạn.");
    }

    /** GET /api/me/bookmarks - thu vien ca nhan */
    public static function myBookmarks(): void {
        Auth::required();
        require_once __DIR__ . "/../utils/Pagination.php";
        $page = max(1, (int)($_GET["page"] ?? 1));
        $sql = "SELECT d.id,d.title,d.abstract,d.cover_image,d.view_count,d.like_count,d.created_at,
                       u.name AS owner_name, b.created_at AS bookmarked_at
                FROM bookmarks b
                JOIN documents d ON d.id = b.document_id
                JOIN users u ON u.id = d.owner_id
                WHERE b.user_id = ? ORDER BY b.created_at DESC";
        $result = Pagination::run($sql, [$_SESSION["user_id"]], $page, 12);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/documents/:citingId/cite/:citedId - trich dan mot cong trinh khac */
    public static function cite(int $citingId, int $citedId, array $body): void {
        Auth::required();
        if ($citingId === $citedId) Response::err("Một công trình không thể tự trích dẫn chính nó.");
        $db = getDB();
        $stmt = $db->prepare("SELECT owner_id FROM documents WHERE id=?");
        $stmt->execute([$citingId]);
        $citing = $stmt->fetch();
        if (!$citing) Response::err("Không tìm thấy tài liệu trích dẫn.", 404);
        Auth::owns((int)$citing["owner_id"]);

        $stmt = $db->prepare("SELECT id,owner_id FROM documents WHERE id=? AND status='approved'");
        $stmt->execute([$citedId]);
        $cited = $stmt->fetch();
        if (!$cited) Response::err("Không tìm thấy công trình được trích dẫn.", 404);

        try {
            $db->prepare(
                "INSERT INTO citations (citing_document_id,cited_document_id,created_by) VALUES (?,?,?)"
            )->execute([$citingId, $citedId, $_SESSION["user_id"]]);
        } catch (PDOException $e) {
            Response::err("Đã trích dẫn công trình này rồi.", 409);
        }
        $db->prepare("UPDATE documents SET citation_count = citation_count + 1 WHERE id=?")->execute([$citedId]);

        if ((int)$cited["owner_id"] !== (int)$_SESSION["user_id"]) {
            $db->prepare(
                "INSERT INTO notifications (user_id,type,actor_id,document_id,message)
                 VALUES (?, 'citation', ?, ?, 'Cong trinh cua ban vua duoc trich dan.')"
            )->execute([$cited["owner_id"], $_SESSION["user_id"], $citedId]);
        }

        Response::ok(null, "Đã thêm trích dẫn.");
    }

    /** GET /api/documents/:id/citations - danh sach cong trinh trich dan / duoc trich dan */
    public static function citationGraph(int $docId): void {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT d.id,d.title,u.name AS owner_name FROM citations c
             JOIN documents d ON d.id = c.cited_document_id JOIN users u ON u.id = d.owner_id
             WHERE c.citing_document_id = ?"
        );
        $stmt->execute([$docId]);
        $cites = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT d.id,d.title,u.name AS owner_name FROM citations c
             JOIN documents d ON d.id = c.citing_document_id JOIN users u ON u.id = d.owner_id
             WHERE c.cited_document_id = ?"
        );
        $stmt->execute([$docId]);
        $citedBy = $stmt->fetchAll();

        Response::ok(["cites" => $cites, "cited_by" => $citedBy]);
    }
}
