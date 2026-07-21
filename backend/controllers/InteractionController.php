<?php
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/InteractionModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/NotificationModel.php";

/** CO CHE TUONG TAC: thich, luu (bookmark), trich dan giua cac cong trinh */
class InteractionController {

    /** POST /api/documents/:id/like - toggle thich */
    public static function toggleLike(int $docId): void {
        Auth::required();
        $doc = DocumentModel::findApproved($docId);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);

        $existing = InteractionModel::findLike($docId, $_SESSION["user_id"]);

        if ($existing) {
            InteractionModel::removeLike((int)$existing["id"]);
            DocumentModel::decrementLikeCount($docId);
            Response::ok(["liked" => false], "Đã bỏ thích.");
        }

        InteractionModel::addLike($docId, $_SESSION["user_id"]);
        DocumentModel::incrementLikeCount($docId);

        if ((int)$doc["owner_id"] !== (int)$_SESSION["user_id"]) {
            NotificationModel::create(
                (int)$doc["owner_id"], "like", (int)$_SESSION["user_id"], $docId,
                $_SESSION["user_name"] . " da thich cong trinh cua ban."
            );
        }

        Response::ok(["liked" => true], "Đã thích.");
    }

    /** POST /api/documents/:id/bookmark - toggle luu vao thu vien ca nhan */
    public static function toggleBookmark(int $docId): void {
        Auth::required();
        if (!DocumentModel::findApproved($docId)) Response::err("Không tìm thấy tài liệu.", 404);

        $existing = InteractionModel::findBookmark($docId, $_SESSION["user_id"]);

        if ($existing) {
            InteractionModel::removeBookmark((int)$existing["id"]);
            Response::ok(["bookmarked" => false], "Đã bỏ lưu.");
        }
        InteractionModel::addBookmark($docId, $_SESSION["user_id"]);
        Response::ok(["bookmarked" => true], "Đã lưu vào thư viện của bạn.");
    }

    /** GET /api/me/bookmarks - thu vien ca nhan */
    public static function myBookmarks(): void {
        Auth::required();
        $page = max(1, (int)($_GET["page"] ?? 1));
        $result = InteractionModel::paginateBookmarks((int)$_SESSION["user_id"], $page, 12);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/documents/:citingId/cite/:citedId - trich dan mot cong trinh khac */
    public static function cite(int $citingId, int $citedId, array $body): void {
        Auth::required();
        if ($citingId === $citedId) Response::err("Một công trình không thể tự trích dẫn chính nó.");

        $citing = DocumentModel::findOwnerId($citingId);
        if (!$citing) Response::err("Không tìm thấy tài liệu trích dẫn.", 404);
        Auth::owns((int)$citing["owner_id"]);

        $cited = DocumentModel::findApproved($citedId);
        if (!$cited) Response::err("Không tìm thấy công trình được trích dẫn.", 404);

        try {
            InteractionModel::addCitation($citingId, $citedId, $_SESSION["user_id"]);
        } catch (PDOException $e) {
            Response::err("Đã trích dẫn công trình này rồi.", 409);
        }
        DocumentModel::incrementCitationCount($citedId);

        if ((int)$cited["owner_id"] !== (int)$_SESSION["user_id"]) {
            NotificationModel::create(
                (int)$cited["owner_id"], "citation", (int)$_SESSION["user_id"], $citedId,
                "Cong trinh cua ban vua duoc trich dan."
            );
        }

        Response::ok(null, "Đã thêm trích dẫn.");
    }

    /** GET /api/documents/:id/citations - danh sach cong trinh trich dan / duoc trich dan */
    public static function citationGraph(int $docId): void {
        Response::ok([
            "cites" => InteractionModel::citesOf($docId),
            "cited_by" => InteractionModel::citedByOf($docId),
        ]);
    }
}
