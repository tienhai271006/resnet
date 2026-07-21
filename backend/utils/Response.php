<?php
/**
 * Moi phan hoi API deu di qua class nay.
 * Dinh dang thong nhat: { success, message, data, meta? }
 */
class Response {
    public static function json(mixed $data, int $status = 200): never {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function ok(mixed $data = null, string $msg = "Thành công"): never {
        self::json(["success" => true, "message" => $msg, "data" => $data]);
    }
    public static function err(string $msg, int $status = 400): never {
        self::json(["success" => false, "message" => $msg], $status);
    }
    public static function paged(array $data, array $meta, string $msg = "Thành công"): never {
        self::json(["success" => true, "message" => $msg, "data" => $data, "meta" => $meta]);
    }
}
