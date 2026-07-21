<?php
class Pagination {
    /**
     * @param string $baseSQL Cau SELECT khong co LIMIT/OFFSET
     * @param array  $params  Tham so bind (positional "?")
     */
    public static function run(string $baseSQL, array $params = [], int $page = 1, int $limit = 10): array {
        $db = getDB();

        $countSQL = "SELECT COUNT(*) FROM ({$baseSQL}) AS _cnt";
        $stmt = $db->prepare($countSQL);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $limit = max(1, min(100, $limit));
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("{$baseSQL} LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            "data" => $data,
            "meta" => [
                "total" => $total, "per_page" => $limit, "current_page" => $page,
                "total_pages" => $totalPages,
                "from" => $total > 0 ? $offset + 1 : 0,
                "to" => min($offset + $limit, $total),
                "has_prev" => $page > 1, "has_next" => $page < $totalPages,
            ],
        ];
    }
}
