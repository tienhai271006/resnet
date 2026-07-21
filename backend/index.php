<?php
// ==================== 1. CORS ====================
$allowed = [
    "http://localhost:5173",
    "http://localhost:3000",
    "https://dautrau.rf.gd", // <- cap nhat URL that khi deploy
];
$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

// ==================== 2. Dependencies ====================
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/utils/Response.php";
require_once __DIR__ . "/utils/Sanitize.php";
require_once __DIR__ . "/middleware/Auth.php";

// ==================== 3. Session bao mat ====================
session_set_cookie_params([
    "lifetime" => 3600 * 8,
    "path"     => "/",
    "secure"   => isset($_SERVER["HTTPS"]),
    "httponly" => true,
    "samesite" => "Lax",
]);
ini_set("session.use_strict_mode", "1");
session_start();

// Fingerprint - phat hien session hijacking
$fingerprint = md5(($_SERVER["HTTP_USER_AGENT"] ?? "") . ($_SERVER["REMOTE_ADDR"] ?? ""));
if (isset($_SESSION["_fp"]) && $_SESSION["_fp"] !== $fingerprint) {
    session_unset(); session_destroy();
    Response::err("Phien lam viec khong hop le. Vui long dang nhap lai.", 401);
}
if (empty($_SESSION["_fp"])) $_SESSION["_fp"] = $fingerprint;

// ==================== 4. Phan tich URI ====================
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER["REQUEST_METHOD"];
$uri = preg_replace("#^(?:/.+)?/backend(?=/api|$)#", "", $uri) ?: "/";
$parts = array_values(array_filter(explode("/", $uri)));
// vi du: /api/documents/12/download -> ["api","documents","12","download"]
$resource = $parts[1] ?? "";
$p2 = $parts[2] ?? "";
$p3 = $parts[3] ?? "";
$p4 = $parts[4] ?? "";

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";
if (str_starts_with($contentType, "application/json")) {
    $body = json_decode(file_get_contents("php://input"), true) ?? [];
} else {
    $body = $_POST;
}
// PHP khong parse PUT multipart tu dong -> doc JSON body cho PUT
if ($method === "PUT" && empty($body)) {
    parse_str(file_get_contents("php://input"), $body);
}

require_once __DIR__ . "/controllers/AuthController.php";
require_once __DIR__ . "/controllers/DocumentController.php";
require_once __DIR__ . "/controllers/PermissionController.php";
require_once __DIR__ . "/controllers/CopyrightController.php";
require_once __DIR__ . "/controllers/InteractionController.php";
require_once __DIR__ . "/controllers/CommentController.php";
require_once __DIR__ . "/controllers/FollowController.php";
require_once __DIR__ . "/controllers/StatsController.php";
require_once __DIR__ . "/controllers/NotificationController.php";
require_once __DIR__ . "/controllers/CategoryController.php";
require_once __DIR__ . "/controllers/UserController.php";

// ==================== 5. Routing Table ====================
switch ($resource) {

    // ---------- AUTH ----------
    case "auth":
        match ("$method:$p2") {
            "POST:register" => AuthController::register($body),
            "POST:login"    => AuthController::login($body),
            "POST:logout"   => AuthController::logout(),
            "GET:me"        => AuthController::me(),
            "PUT:profile", "POST:profile" => AuthController::updateProfile($body),
            default => Response::err("Endpoint khong ton tai.", 404),
        };
        break;

    // ---------- CATEGORIES ----------
    case "categories":
        if ($method === "GET") { CategoryController::index(); }
        Response::err("Method khong hop le.", 405);
        break;

    // ---------- DOCUMENTS ----------
    case "documents":
        // /api/documents/pending
        if ($p2 === "pending" && $method === "GET") { DocumentController::pending(); break; }

        $docId = ctype_digit($p2) ? (int)$p2 : null;

        if ($docId !== null && $p3 !== "") {
            match ("$method:$p3") {
                "GET:download"        => DocumentController::download($docId),
                "GET:versions"        => DocumentController::versions($docId),
                "POST:versions"       => DocumentController::addVersion($docId, $body),
                "POST:approve"        => DocumentController::approve($docId),
                "POST:reject"         => DocumentController::reject($docId, $body),
                "POST:takedown"       => DocumentController::takedown($docId, $body),
                "POST:like"           => InteractionController::toggleLike($docId),
                "POST:bookmark"       => InteractionController::toggleBookmark($docId),
                "GET:comments"        => CommentController::index($docId),
                "POST:comments"       => CommentController::store($docId, $body),
                "GET:citations"       => InteractionController::citationGraph($docId),
                "POST:cite"           => InteractionController::cite($docId, (int)($body["cited_id"] ?? 0), $body),
                "POST:access-requests"=> PermissionController::requestAccess($docId, $body),
                "GET:access-requests" => PermissionController::listRequests($docId),
                "GET:permissions"     => PermissionController::listGranted($docId),
                "POST:report"         => CopyrightController::report($docId, $body),
                "GET:download-logs"   => CopyrightController::downloadLogs($docId),
                default => Response::err("Endpoint khong ton tai.", 404),
            };
            break;
        }

        if ($docId !== null) {
            match ($method) {
                "GET"    => DocumentController::show($docId),
                "PUT"    => DocumentController::update($docId, $body),
                "POST"   => DocumentController::update($docId, $body), // ho tro multipart update
                "DELETE" => DocumentController::destroy($docId),
                default  => Response::err("Method khong hop le.", 405),
            };
        } else {
            match ($method) {
                "GET"  => DocumentController::index(),
                "POST" => DocumentController::store($body),
                default => Response::err("Method khong hop le.", 405),
            };
        }
        break;

    // ---------- ACCESS REQUESTS (thao tac tren chinh request) ----------
    case "access-requests":
        $reqId = ctype_digit($p2) ? (int)$p2 : null;
        if ($reqId !== null) {
            match ("$method:$p3") {
                "POST:approve" => PermissionController::approveRequest($reqId, $body),
                "POST:reject"  => PermissionController::rejectRequest($reqId),
                default => Response::err("Endpoint khong ton tai.", 404),
            };
        } else Response::err("Thieu ma yeu cau.", 400);
        break;

    // ---------- PERMISSIONS ----------
    case "permissions":
        $permId = ctype_digit($p2) ? (int)$p2 : null;
        if ($permId !== null && $method === "DELETE") { PermissionController::revoke($permId); }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- COPYRIGHT REPORTS (admin) ----------
    case "copyright-reports":
        $repId = ctype_digit($p2) ? (int)$p2 : null;
        if ($repId !== null && $p3 === "resolve" && $method === "POST") { CopyrightController::resolve($repId, $body); break; }
        if ($method === "GET") { CopyrightController::index(); break; }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- COMMENTS ----------
    case "comments":
        $cId = ctype_digit($p2) ? (int)$p2 : null;
        if ($cId !== null && $p3 === "hide" && $method === "POST") { CommentController::hide($cId); break; }
        if ($cId !== null && $method === "DELETE") { CommentController::destroy($cId); break; }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- USERS / FOLLOW ----------
    case "users":
        $uId = ctype_digit($p2) ? (int)$p2 : null;
        if ($uId !== null && $p3 !== "") {
            match ("$method:$p3") {
                "POST:follow"    => FollowController::toggle($uId),
                "GET:followers"  => FollowController::followers($uId),
                "GET:following"  => FollowController::following($uId),
                "GET:documents"  => UserController::documents($uId),
                default => Response::err("Endpoint khong ton tai.", 404),
            };
            break;
        }
        if ($uId !== null && $method === "GET") { UserController::show($uId); break; }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- FEED ----------
    case "feed":
        if ($method === "GET") { FollowController::feed(); }
        Response::err("Method khong hop le.", 405);
        break;

    // ---------- ME (thu vien ca nhan) ----------
    case "me":
        if ($p2 === "bookmarks" && $method === "GET") { InteractionController::myBookmarks(); break; }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- NOTIFICATIONS ----------
    case "notifications":
        if ($p2 === "read-all" && $method === "POST") { NotificationController::markAllRead(); break; }
        $nId = ctype_digit($p2) ? (int)$p2 : null;
        if ($nId !== null && $p3 === "read" && $method === "POST") { NotificationController::markRead($nId); break; }
        if ($method === "GET") { NotificationController::index(); break; }
        Response::err("Endpoint khong ton tai.", 404);
        break;

    // ---------- STATS ----------
    case "stats":
        match ("$method:$p2") {
            "GET:summary" => StatsController::summary(),
            "GET:my"      => StatsController::my(),
            default => Response::err("Endpoint thong ke khong ton tai.", 404),
        };
        break;

    // ---------- ADMIN ----------
    case "admin":
        if ($p2 === "users") {
            $auId = ctype_digit($p3) ? (int)$p3 : null;
            if ($auId !== null && $p4 === "toggle-active" && $method === "POST") { UserController::toggleActive($auId); break; }
            if ($auId !== null && $p4 === "verify" && $method === "POST") { AuthController::verifyResearcher($auId); break; }
            if ($method === "GET") { UserController::adminIndex(); break; }
        }
        Response::err("Endpoint admin khong ton tai.", 404);
        break;

    default:
        Response::err("API endpoint [{$method} /api/{$resource}] khong ton tai.", 404);
}
