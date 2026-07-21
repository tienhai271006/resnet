<?php
require_once __DIR__ . "/../models/DocumentModel.php";
require_once __DIR__ . "/../models/CopyrightModel.php";
require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../models/FollowModel.php";

/** Thong ke cho Dashboard */
class StatsController {

    /** GET /api/stats/summary */
    public static function summary(): void {
        Auth::required();

        Response::ok([
            "documents_total"   => DocumentModel::countTotalApproved(),
            "documents_pending" => DocumentModel::countPending(),
            "users_total"       => UserModel::countAll(),
            "reports_open"      => CopyrightModel::countOpenReports(),
            "downloads_total"   => CopyrightModel::countDownloadsTotal(),
            "monthly_uploads"   => DocumentModel::monthlyUploads(),
            "by_visibility"     => DocumentModel::countByVisibility(),
            "top_documents"     => DocumentModel::topByViews(5),
        ]);
    }

    /** GET /api/stats/my - so lieu ca nhan cho nha nghien cuu */
    public static function my(): void {
        Auth::required();
        $uid = (int)$_SESSION["user_id"];

        Response::ok([
            "overview"        => DocumentModel::myOverview($uid),
            "followers_count" => FollowModel::countFollowers($uid),
        ]);
    }
}
