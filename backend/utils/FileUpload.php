<?php
class FileUpload {
    private static string $uploadDir = __DIR__ . "/../uploads/";
    private static int $maxDocSize = 25 * 1024 * 1024;  // 25MB cho file nghien cuu
    private static int $maxImgSize = 5 * 1024 * 1024;   // 5MB cho anh
    private static array $docTypes = ["application/pdf"];
    private static array $imgTypes = ["image/jpeg", "image/png", "image/webp"];

    /** Upload file nghien cuu (PDF). Tra ve ["path"=>..,"hash"=>..,"size"=>..] */
    public static function document(array $file, string $subDir = "papers"): array {
        return self::handle($file, $subDir, self::$docTypes, self::$maxDocSize, ["pdf" => "pdf"]);
    }

    public static function image(array $file, string $subDir = "images"): array {
        return self::handle($file, $subDir, self::$imgTypes, self::$maxImgSize, [
            "jpg" => "jpg", "png" => "png", "webp" => "webp",
        ]);
    }

    private static function handle(array $file, string $subDir, array $allowed, int $maxSize, array $extMap): array {
        if ($file["error"] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => "File vượt quá giới hạn php.ini.",
                UPLOAD_ERR_FORM_SIZE => "File vượt quá giới hạn form.",
                UPLOAD_ERR_PARTIAL => "File chỉ upload một phần.",
                UPLOAD_ERR_NO_FILE => "Không có file nào được chọn.",
            ];
            throw new RuntimeException($messages[$file["error"]] ?? "Lỗi upload không xác định.");
        }
        if ($file["size"] > $maxSize) {
            throw new RuntimeException("File quá lớn. Tối đa " . (int)($maxSize / 1024 / 1024) . "MB.");
        }

        // Khong tin $_FILES["type"] - doc mime that bang finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file["tmp_name"]);
        if (!in_array($mimeType, $allowed, true)) {
            throw new RuntimeException("Loai file khong duoc phep: {$mimeType}.");
        }

        $hash = hash_file("sha256", $file["tmp_name"]);

        $ext = match ($mimeType) {
            "application/pdf" => "pdf",
            "image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp",
            default => "bin",
        };
        $filename = sprintf("%s_%s.%s", uniqid(), bin2hex(random_bytes(4)), $ext);

        $destDir = self::$uploadDir . trim($subDir, "/") . "/";
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        if (!move_uploaded_file($file["tmp_name"], $destDir . $filename)) {
            throw new RuntimeException("Không thể lưu file lên máy chủ.");
        }

        return [
            "path" => $subDir . "/" . $filename,
            "hash" => $hash,
            "size" => (int)$file["size"],
        ];
    }

    public static function delete(string $relativePath): bool {
        $full = self::$uploadDir . ltrim($relativePath, "/");
        return file_exists($full) && unlink($full);
    }

    public static function url(string $relativePath): string {
        $base = rtrim($_ENV["APP_URL"] ?? ("http://" . $_SERVER["HTTP_HOST"]), "/");
        return $base . "/backend/uploads/" . ltrim($relativePath, "/");
    }
}
