<?php
class Auth {
    public static function required(): void {
        if (empty($_SESSION["user_id"])) {
            Response::err("Phiên làm việc hết hạn. Vui lòng đăng nhập lại.", 401);
        }
    }

    /** Auth::role("admin") hoac Auth::role("admin","researcher") */
    public static function role(string ...$roles): void {
        self::required();
        if (!in_array($_SESSION["user_role"] ?? "", $roles, true)) {
            Response::err("Bạn không có quyền thực hiện thao tác này.", 403);
        }
    }

    public static function current(): array {
        return [
            "id"    => (int)($_SESSION["user_id"] ?? 0),
            "name"  => $_SESSION["user_name"] ?? "",
            "email" => $_SESSION["user_email"] ?? "",
            "role"  => $_SESSION["user_role"] ?? "",
        ];
    }

    /** Chi chu so huu tai lieu hoac admin moi duoc sua/xoa */
    public static function owns(int $ownerId): void {
        self::required();
        $uid = (int)$_SESSION["user_id"];
        $role = $_SESSION["user_role"] ?? "";
        if ($uid !== $ownerId && $role !== "admin") {
            Response::err("Bạn không có quyền thực hiện với tài nguyên này.", 403);
        }
    }

    /**
     * PHAN QUYEN TAI LIEU - kiem tra nguoi dung hien tai co duoc XEM tai lieu khong.
     * Tra ve mang ["canView"=>bool,"canDownload"=>bool,"reason"=>string]
     */
    public static function checkDocumentAccess(array $doc): array {
        $uid = (int)($_SESSION["user_id"] ?? 0);
        $role = $_SESSION["user_role"] ?? "";

        // Chu so huu va admin luon toan quyen
        if ($uid && ($uid === (int)$doc["owner_id"] || $role === "admin")) {
            return ["canView" => true, "canDownload" => true, "reason" => "owner_or_admin"];
        }

        // Tai lieu chua duoc duyet - chi chu so huu/admin xem duoc (da xu ly o tren)
        if ($doc["status"] !== "approved") {
            return ["canView" => false, "canDownload" => false, "reason" => "not_approved"];
        }

        switch ($doc["visibility"]) {
            case "public":
                return ["canView" => true, "canDownload" => (bool)$doc["allow_download"], "reason" => "public"];

            case "institution":
                if (!$uid) return ["canView" => false, "canDownload" => false, "reason" => "login_required"];
                return ["canView" => true, "canDownload" => (bool)$doc["allow_download"], "reason" => "institution"];

            case "restricted":
                if (!$uid) return ["canView" => true, "canDownload" => false, "reason" => "login_required_for_download"];
                $db = getDB();
                $stmt = $db->prepare(
                    "SELECT permission_type FROM document_permissions
                     WHERE document_id = ? AND user_id = ? AND (expires_at IS NULL OR expires_at > NOW())"
                );
                $stmt->execute([$doc["id"], $uid]);
                $perms = array_column($stmt->fetchAll(), "permission_type");
                return [
                    "canView"     => true, // abstract luon xem duoc
                    "canDownload" => in_array("download", $perms, true) && (bool)$doc["allow_download"],
                    "reason"      => in_array("download", $perms, true) ? "granted" : "permission_required",
                ];

            case "private":
            default:
                return ["canView" => false, "canDownload" => false, "reason" => "private"];
        }
    }
}
