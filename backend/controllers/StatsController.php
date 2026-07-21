<?php
/** Thong ke cho Dashboard */
class StatsController {

    /** GET /api/stats/summary */
    public static function summary(): void {
        Auth::required();
        $db = getDB();
        $res = [];

        $res["documents_total"]    = (int)$db->query("SELECT COUNT(*) FROM documents WHERE status='approved'")->fetchColumn();
        $res["documents_pending"]  = (int)$db->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn();
        $res["users_total"]        = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $res["reports_open"]       = (int)$db->query("SELECT COUNT(*) FROM copyright_reports WHERE status='open'")->fetchColumn();
        $res["downloads_total"]    = (int)$db->query("SELECT COUNT(*) FROM download_logs")->fetchColumn();

        $stmt = $db->query(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt
             FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status='approved'
             GROUP BY month ORDER BY month"
        );
        $res["monthly_uploads"] = $stmt->fetchAll();

        $stmt = $db->query(
            "SELECT visibility, COUNT(*) AS cnt FROM documents WHERE status='approved' GROUP BY visibility"
        );
        $res["by_visibility"] = $stmt->fetchAll();

        $stmt = $db->query(
            "SELECT id,title,view_count,download_count,citation_count FROM documents
             WHERE status='approved' ORDER BY view_count DESC LIMIT 5"
        );
        $res["top_documents"] = $stmt->fetchAll();

        Response::ok($res);
    }

    /** GET /api/stats/my - so lieu ca nhan cho nha nghien cuu */
    public static function my(): void {
        Auth::required();
        $db = getDB();
        $uid = $_SESSION["user_id"];
        $res = [];

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total, SUM(view_count) AS views, SUM(download_count) AS downloads,
                    SUM(like_count) AS likes, SUM(citation_count) AS citations
             FROM documents WHERE owner_id=? AND status='approved'"
        );
        $stmt->execute([$uid]);
        $res["overview"] = $stmt->fetch();

        $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?");
        $stmt->execute([$uid]);
        $res["followers_count"] = (int)$stmt->fetchColumn();

        Response::ok($res);
    }
}
