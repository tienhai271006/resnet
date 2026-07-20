<?php
/**
 * Ket noi MySQL qua PDO (Singleton). Prepared Statement bat buoc moi truy van.
 *
 * TRIEN KHAI TREN INFINITYFREE:
 *  DB_HOST : hostname tu hPanel -> MySQL Databases (vd: sql304.epizy.com)
 *  DB_NAME : epiz_xxxxxxxx_resnet
 *  DB_USER : epiz_xxxxxxxx
 */
define("DB_HOST", getenv("DB_HOST") ?: "localhost");
define("DB_NAME", getenv("DB_NAME") ?: "resnet_db");
define("DB_USER", getenv("DB_USER") ?: "root");
define("DB_PASS", getenv("DB_PASS") ?: "");
define("DB_CHARSET", "utf8mb4");

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s;port=3306", DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(["success" => false, "message" => "Loi ket noi CSDL"], JSON_UNESCAPED_UNICODE));
    }
    return $pdo;
}
