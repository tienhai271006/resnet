<?php
/** Bo ham lam sach va kiem tra du lieu dau vao. Luon validate o Back-end. */
class Sanitize {
    public static function text(string $str, int $maxLen = 255): string {
        $clean = trim($str);
        $clean = preg_replace("/\s+/u", " ", $clean);
        return mb_substr($clean, 0, $maxLen, "UTF-8");
    }
    public static function email(string $str): string|false {
        $clean = strtolower(trim($str));
        return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : false;
    }
    public static function int(mixed $val, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int|false {
        $int = filter_var($val, FILTER_VALIDATE_INT, ["options" => ["min_range" => $min, "max_range" => $max]]);
        return $int !== false ? (int)$int : false;
    }
    public static function enum(string $val, array $allowed): string|false {
        return in_array($val, $allowed, true) ? $val : false;
    }
    /** Chong XSS khi can echo HTML truc tiep (API JSON thi front-end da tu escape) */
    public static function html(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, "UTF-8");
    }
}
