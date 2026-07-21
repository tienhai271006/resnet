<?php
require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/FollowModel.php";

class AuthController {
    public static function register(array $body): void {
        $name = trim($body["name"] ?? "");
        $email = strtolower(trim($body["email"] ?? ""));
        $password = $body["password"] ?? "";
        $role = in_array($body["role"] ?? "", ["researcher", "reader"]) ? $body["role"] : "reader";
        $institution = trim($body["institution"] ?? "") ?: null;

        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = "Họ tên phải có ít nhất 2 ký tự.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email không hợp lệ.";
        if (strlen($password) < 8) $errors[] = "Mật khẩu phải có ít nhất 8 ký tự.";
        if (!preg_match("/[A-Z]/", $password)) $errors[] = "Mật khẩu phải có chữ hoa.";
        if (!preg_match("/[0-9]/", $password)) $errors[] = "Mật khẩu phải có chữ số.";
        if ($role === "researcher" && !$institution) $errors[] = "Nhà nghiên cứu cần khai báo đơn vị công tác.";
        if ($errors) Response::err(implode(" ", $errors));

        if (UserModel::findByEmail($email)) Response::err("Email này đã được sử dụng.", 409);

        $hash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
        // researcher moi dang ky mac dinh is_verified=0, admin xac minh thu cong truoc khi cho dang bai
        $id = UserModel::create($name, $email, $hash, $role, $institution, $role === "reader");

        Response::ok(["id" => $id, "name" => $name, "email" => $email, "role" => $role],
            $role === "researcher"
                ? "Đăng ký thành công! Tài khoản nhà nghiên cứu cần được quản trị viên xác minh trước khi đăng tải."
                : "Đăng ký thành công!"
        );
    }

    public static function login(array $body): void {
        $email = strtolower(trim($body["email"] ?? ""));
        $password = $body["password"] ?? "";
        if (!$email || !$password) Response::err("Vui lòng nhập email và mật khẩu.");

        $user = UserModel::findActiveByEmail($email);

        if (!$user || !password_verify($password, $user["password"])) {
            Response::err("Email hoặc mật khẩu không đúng.", 401);
        }

        if (password_needs_rehash($user["password"], PASSWORD_BCRYPT, ["cost" => 12])) {
            UserModel::updatePassword((int)$user["id"], password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]));
        }

        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_role"] = $user["role"];

        UserModel::touchLastLogin((int)$user["id"]);

        Response::ok([
            "id" => $user["id"], "name" => $user["name"], "email" => $user["email"],
            "role" => $user["role"], "avatar" => $user["avatar"], "is_verified" => (bool)$user["is_verified"],
        ], "Đăng nhập thành công!");
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
        Response::ok(null, "Đã đăng xuất.");
    }

    public static function me(): void {
        Auth::required();
        $user = UserModel::findProfileById((int)$_SESSION["user_id"]);
        if (!$user) Response::err("Không tìm thấy người dùng.", 404);

        // So lieu tong quan ho so
        $user["published_count"] = DocumentModel::countPublishedByOwner((int)$user["id"]);
        $user["followers_count"] = FollowModel::countFollowers((int)$user["id"]);

        Response::ok($user);
    }

    public static function updateProfile(array $body): void {
        Auth::required();
        $name = trim($body["name"] ?? "");
        $bio = trim($body["bio"] ?? "");
        $institution = trim($body["institution"] ?? "");
        $orcid = trim($body["orcid_id"] ?? "");
        if (mb_strlen($name) < 2) Response::err("Họ tên phải có ít nhất 2 ký tự.");

        $avatarPath = null;
        if (!empty($_FILES["avatar"]["name"])) {
            try {
                require_once __DIR__ . "/../utils/FileUpload.php";
                $res = FileUpload::image($_FILES["avatar"], "avatars");
                $avatarPath = $res["path"];
            } catch (RuntimeException $e) { Response::err($e->getMessage()); }
        }

        UserModel::updateProfile((int)$_SESSION["user_id"], $name, $bio, $institution, $orcid, $avatarPath);

        $_SESSION["user_name"] = $name;
        Response::ok(null, "Cập nhật hồ sơ thành công!");
    }

    /** Admin xac minh tai khoan nha nghien cuu truoc khi cho phep dang tai */
    public static function verifyResearcher(int $userId): void {
        Auth::role("admin");
        UserModel::verifyResearcher($userId);
        Response::ok(null, "Đã xác minh nhà nghiên cứu.");
    }
}
