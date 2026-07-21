<?php
/**
 * Tien ich BAO VE BAN QUYEN SO cho tai lieu nghien cuu.
 * - sinhDOI(): cap ma dinh danh so duy nhat cho moi cong trinh duoc duyet
 * - taoWatermarkToken(): tao token rieng cho MOI luot tai ve, ghi vao download_logs
 *   de truy vet neu file bi phat tan trai phep (moi ban tai ve la duy nhat)
 * - kiemTraTrungLap(): so sanh SHA-256 de chan dang tai lai / phat hien dao van nguyen file
 */
class Copyright {
    /** Sinh DOI dang 10.RESNET/<nam>.<id_ngau_nhien> - duy nhat, khong doan truoc duoc */
    public static function sinhDOI(int $documentId): string {
        return sprintf("10.RESNET/%s.%06d", date("Y"), $documentId);
    }

    /** Token watermark rieng cho tung luot tai - dung de nhung vao metadata/footer file khi phuc vu tai ve */
    public static function taoWatermarkToken(int $documentId, int $userId): string {
        return hash("sha256", $documentId . "|" . $userId . "|" . microtime(true) . "|" . bin2hex(random_bytes(8)));
    }

    /** Kiem tra file trung lap trong toan he thong bang hash SHA-256 */
    public static function kiemTraTrungLap(string $fileHash, ?int $excludeDocId = null): ?array {
        require_once __DIR__ . "/../models/DocumentModel.php";
        return DocumentModel::findByHash($fileHash, $excludeDocId);
    }

    /** Nhan dien license co cho phep tai lai/phat hanh khong, dung khi hien thi UI */
    public static function moTaGiayPhep(string $license): string {
        return match ($license) {
            "cc0"              => "CC0 - Miễn phí bản quyền, sử dụng tự do",
            "cc_by"             => "CC BY - Được phép sử dụng lại nếu ghi nguồn",
            "cc_by_nc"          => "CC BY-NC - Chỉ sử dụng phi thương mại, ghi nguồn",
            "cc_by_nc_nd"       => "CC BY-NC-ND - Không được chỉnh sửa, không thương mại",
            default             => "Giữ toàn bộ bản quyền - Phải xin phép tác giả",
        };
    }
}
