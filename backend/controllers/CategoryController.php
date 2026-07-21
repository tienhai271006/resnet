<?php
class CategoryController {
    /** GET /api/categories */
    public static function index(): void {
        $db = getDB();
        $stmt = $db->query(
            "SELECT c.id,c.name,c.slug,c.icon, COUNT(d.id) AS document_count
             FROM categories c
             LEFT JOIN documents d ON d.category_id = c.id AND d.status='approved'
             GROUP BY c.id ORDER BY c.name ASC"
        );
        Response::ok($stmt->fetchAll());
    }
}
