<?php
require_once __DIR__ . "/../utils/FileUpload.php";
require_once __DIR__ . "/../utils/Copyright.php";
require_once __DIR__ . "/../utils/Pagination.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/InteractionModel.php";
require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../models/CopyrightModel.php";

class DocumentController {

    /**
     * GET /api/documents?page=&q=&category_id=&visibility=&status=&sort=&order=
     * Danh sach cong khai: chi tra ve tai lieu approved + (public|institution neu da dang nhap).
     * Neu chinh chu goi voi ?mine=1 -> tra ve toan bo tai lieu cua ho ke ca pending/rejected.
     */
    public static function index(): void {
        $page   = max(1, (int)($_GET["page"] ?? 1));
        $limit  = max(1, min(50, (int)($_GET["limit"] ?? 12)));
        $q      = trim($_GET["q"] ?? "");
        $catId  = (int)($_GET["category_id"] ?? 0);
        $sort   = in_array($_GET["sort"] ?? "", ["created_at", "view_count", "like_count", "citation_count"])
                    ? $_GET["sort"] : "created_at";
        $order  = strtoupper($_GET["order"] ?? "") === "ASC" ? "ASC" : "DESC";
        $mine   = ($_GET["mine"] ?? "") === "1";

        $uid = (int)($_SESSION["user_id"] ?? 0);
        $where = [];
        $params = [];

        if ($mine) {
            Auth::required();
            $where[] = "d.owner_id = ?";
            $params[] = $_SESSION["user_id"];
        } else {
            // Nguoi ngoai chi thay tai lieu da duyet va public; nguoi da dang nhap thay them 'institution'
            $visList = $uid ? "'public','institution'" : "'public'";
            $where[] = "d.status = 'approved'";
            $where[] = "d.visibility IN ({$visList})";
        }

        if ($q !== "") {
            $where[] = "(d.title LIKE ? OR d.abstract LIKE ? OR d.keywords LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like);
        }
        if ($catId > 0) { $where[] = "d.category_id = ?"; $params[] = $catId; }

        $result = DocumentModel::paginateSearch($where, $params, $sort, $order, $page, $limit);
        Response::paged($result["data"], $result["meta"]);
    }

    /** GET /api/documents/:id - chi tiet + kiem tra phan quyen */
    public static function show(int $id): void {
        $doc = DocumentModel::findWithOwner($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);

        $access = Auth::checkDocumentAccess($doc);
        if (!$access["canView"]) {
            Response::err("Tài liệu bị hạn chế truy cập. (" . $access["reason"] . ")", 403);
        }

        // Khong bao gio tra thang duong dan file goc ra ngoai - chi tra co the tai hay khong
        unset($doc["file_path"], $doc["file_hash"]);
        $doc["can_download"] = $access["canDownload"];
        $doc["access_reason"] = $access["reason"];
        $doc["license_label"] = Copyright::moTaGiayPhep($doc["license_type"]);

        // Tang view_count (khong tang khi chinh chu xem)
        $uid = (int)($_SESSION["user_id"] ?? 0);
        if ($uid !== (int)$doc["owner_id"]) {
            DocumentModel::incrementView($id);
        }

        // Trang thai like/bookmark cua nguoi dung hien tai
        if ($uid) {
            $doc["liked_by_me"] = InteractionModel::isLiked($id, $uid);
            $doc["bookmarked_by_me"] = InteractionModel::isBookmarked($id, $uid);
        } else {
            $doc["liked_by_me"] = false;
            $doc["bookmarked_by_me"] = false;
        }

        Response::ok($doc);
    }

    /** POST /api/documents - dang tai cong trinh moi (chi researcher da xac minh) */
    public static function store(array $body): void {
        Auth::role("admin", "researcher");
        if (($_SESSION["user_role"] === "researcher") && empty($_SESSION["is_verified"])) {
            // double-check tu DB phong khi session cu
            if (!UserModel::getVerifiedFlag((int)$_SESSION["user_id"])) {
                Response::err("Tài khoản của bạn chưa được quản trị viên xác minh là nhà nghiên cứu.", 403);
            }
        }

        $title = Sanitize::text($body["title"] ?? "", 300);
        $abstract = trim($body["abstract"] ?? "");
        $keywords = Sanitize::text($body["keywords"] ?? "", 400);
        $authorsText = Sanitize::text($body["authors_text"] ?? "", 500);
        $categoryId = (int)($body["category_id"] ?? 0) ?: null;
        $visibility = Sanitize::enum($body["visibility"] ?? "", ["public", "institution", "restricted", "private"]) ?: "restricted";
        $allowDownload = !empty($body["allow_download"]) ? 1 : 0;
        $license = Sanitize::enum($body["license_type"] ?? "", ["all_rights_reserved", "cc_by", "cc_by_nc", "cc_by_nc_nd", "cc0"]) ?: "all_rights_reserved";

        if (mb_strlen($title) < 5) Response::err("Tiêu đề phải có ít nhất 5 ký tự.");
        if (mb_strlen($abstract) < 50) Response::err("Tóm tắt phải có ít nhất 50 ký tự để đảm bảo chất lượng học thuật.");
        if (empty($_FILES["file"]["name"])) Response::err("Vui lòng đính kèm file PDF công trình.");

        try {
            $uploaded = FileUpload::document($_FILES["file"], "papers");
        } catch (RuntimeException $e) { Response::err($e->getMessage()); }

        // Chong dang trung lap / dao van nguyen file da co tren he thong
        $dup = Copyright::kiemTraTrungLap($uploaded["hash"]);
        if ($dup) {
            FileUpload::delete($uploaded["path"]);
            Response::err("File nay trung khop voi tai lieu \"{$dup['title']}\" da co tren he thong (ID #{$dup['id']}). Khong the dang trung lap.", 409);
        }

        $coverPath = null;
        if (!empty($_FILES["cover"]["name"])) {
            try {
                $cov = FileUpload::image($_FILES["cover"], "covers");
                $coverPath = $cov["path"];
            } catch (RuntimeException $e) { /* anh bia khong bat buoc, bo qua loi nhe */ }
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $docId = DocumentModel::create([
                "owner_id" => $_SESSION["user_id"], "category_id" => $categoryId, "title" => $title,
                "abstract" => $abstract, "keywords" => $keywords, "authors_text" => $authorsText,
                "file_path" => $uploaded["path"], "file_hash" => $uploaded["hash"], "file_size" => $uploaded["size"],
                "cover_image" => $coverPath, "visibility" => $visibility, "allow_download" => $allowDownload,
                "license_type" => $license,
            ]);

            DocumentModel::insertVersion($docId, 1, $uploaded["path"], $uploaded["hash"], "Phien ban dau", $_SESSION["user_id"]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            FileUpload::delete($uploaded["path"]);
            Response::err("Lỗi lưu tài liệu vào hệ thống.", 500);
        }

        Response::ok(["id" => $docId, "title" => $title, "status" => "pending"],
            "Đăng tải thành công! Công trình đang chờ quản trị viên kiểm duyệt trước khi công khai.");
    }

    /** PUT /api/documents/:id - chinh sua sieu du lieu (khong doi file) */
    public static function update(int $id, array $body): void {
        Auth::required();
        $doc = DocumentModel::find($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        $title = Sanitize::text($body["title"] ?? $doc["title"], 300);
        $abstract = trim($body["abstract"] ?? $doc["abstract"]);
        $keywords = Sanitize::text($body["keywords"] ?? $doc["keywords"], 400);
        $visibility = Sanitize::enum($body["visibility"] ?? "", ["public", "institution", "restricted", "private"]) ?: $doc["visibility"];
        $allowDownload = array_key_exists("allow_download", $body) ? (!empty($body["allow_download"]) ? 1 : 0) : $doc["allow_download"];
        $license = Sanitize::enum($body["license_type"] ?? "", ["all_rights_reserved", "cc_by", "cc_by_nc", "cc_by_nc_nd", "cc0"]) ?: $doc["license_type"];

        if (mb_strlen($title) < 5) Response::err("Tiêu đề phải có ít nhất 5 ký tự.");

        DocumentModel::update($id, $title, $abstract, $keywords, $visibility, $allowDownload, $license);

        Response::ok(["id" => $id], "Cập nhật thành công!");
    }

    /** DELETE /api/documents/:id */
    public static function destroy(int $id): void {
        Auth::required();
        $doc = DocumentModel::find($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        FileUpload::delete($doc["file_path"]);
        if ($doc["cover_image"]) FileUpload::delete($doc["cover_image"]);
        DocumentModel::delete($id);

        Response::ok(null, "Đã xóa tài liệu.");
    }

    /**
     * GET /api/documents/:id/download
     * Kiem tra quyen -> ghi log + tao watermark token -> stream file thuc te.
     */
    public static function download(int $id): void {
        $doc = DocumentModel::find($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        if ($doc["status"] !== "approved") Response::err("Tài liệu chưa được duyệt.", 403);

        Auth::required(); // bat buoc dang nhap de co the truy vet nguoi tai
        $access = Auth::checkDocumentAccess($doc);
        if (!$access["canDownload"]) {
            Response::err("Bạn không có quyền tải file này. Vui lòng gửi yêu cầu cấp quyền.", 403);
        }

        $filePath = __DIR__ . "/../uploads/" . $doc["file_path"];
        if (!file_exists($filePath)) Response::err("File không tồn tại trên máy chủ.", 404);

        // === BAO VE BAN QUYEN: ghi nhat ky + token watermark cho MOI luot tai ===
        $token = Copyright::taoWatermarkToken($id, (int)$_SESSION["user_id"]);
        CopyrightModel::logDownload($id, (int)$_SESSION["user_id"], $token, $_SERVER["REMOTE_ADDR"] ?? null, substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 250));
        DocumentModel::incrementDownload($id);

        // Phuc vu file (trong trien khai thuc te: dong dau watermark token vao footer PDF truoc khi stream)
        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"" . basename($doc["file_path"]) . "\"");
        header("X-Watermark-Token: {$token}");
        header("Content-Length: " . filesize($filePath));
        readfile($filePath);
        exit;
    }

    /** GET /api/documents/:id/versions */
    public static function versions(int $id): void {
        Auth::required();
        $doc = DocumentModel::findOwnerId($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        Response::ok(DocumentModel::listVersions($id));
    }

    /** POST /api/documents/:id/versions - tai len ban cap nhat, giu lai lich su */
    public static function addVersion(int $id, array $body): void {
        Auth::required();
        $doc = DocumentModel::find($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);
        Auth::owns((int)$doc["owner_id"]);

        if (empty($_FILES["file"]["name"])) Response::err("Vui lòng đính kèm file phiên bản mới.");
        try { $uploaded = FileUpload::document($_FILES["file"], "papers"); }
        catch (RuntimeException $e) { Response::err($e->getMessage()); }

        $nextVersion = DocumentModel::nextVersionNo($id);

        $db = getDB();
        $db->beginTransaction();
        try {
            DocumentModel::insertVersion($id, $nextVersion, $uploaded["path"], $uploaded["hash"], Sanitize::text($body["changelog"] ?? "", 300), $_SESSION["user_id"]);

            // Ban moi phai duoc kiem duyet lai truoc khi thay the ban cong khai
            DocumentModel::replaceActiveFile($id, $uploaded["path"], $uploaded["hash"], $uploaded["size"]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            FileUpload::delete($uploaded["path"]);
            Response::err("Lỗi lưu phiên bản mới.", 500);
        }

        Response::ok(["version" => $nextVersion], "Da tai len phien ban {$nextVersion}, cho kiem duyet lai.");
    }

    // ==================== KIEM DUYET (ADMIN) ====================

    /** GET /api/documents/pending - hang cho duyet (admin) */
    public static function pending(): void {
        Auth::role("admin");
        $page = max(1, (int)($_GET["page"] ?? 1));
        $result = DocumentModel::paginatePending($page, 10);
        Response::paged($result["data"], $result["meta"]);
    }

    /** POST /api/documents/:id/approve - admin duyet + cap DOI */
    public static function approve(int $id): void {
        Auth::role("admin");
        $doc = DocumentModel::findOwnerAndDoi($id);
        if (!$doc) Response::err("Không tìm thấy tài liệu.", 404);

        $doi = $doc["doi"] ?: Copyright::sinhDOI($id);
        DocumentModel::approve($id, $doi);

        Response::ok(["doi" => $doi], "Da duyet cong trinh va cap DOI: {$doi}");
    }

    /** POST /api/documents/:id/reject */
    public static function reject(int $id, array $body): void {
        Auth::role("admin");
        $reason = Sanitize::text($body["reason"] ?? "Không đạt yêu cầu kiểm duyệt.", 300);
        DocumentModel::reject($id, $reason);
        Response::ok(null, "Đã từ chối công trình.");
    }

    /** POST /api/documents/:id/takedown - go bai vi vi pham ban quyen da bi to cao va xac minh */
    public static function takedown(int $id, array $body): void {
        Auth::role("admin");
        $reason = Sanitize::text($body["reason"] ?? "Vi phạm bản quyền.", 300);
        DocumentModel::takedown($id, $reason);
        Response::ok(null, "Đã gỡ bỏ tài liệu khỏi hệ thống.");
    }
}
