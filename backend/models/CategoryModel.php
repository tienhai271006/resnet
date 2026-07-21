<?php
/** Truy van bang `categories` */
class CategoryModel {
    public static function allWithCounts(): array {
        $stmt = getDB()->query(
            "SELECT c.id,c.name,c.slug,c.icon, COUNT(d.id) AS document_count
             FROM categories c
             LEFT JOIN documents d ON d.category_id = c.id AND d.status='approved'
             GROUP BY c.id ORDER BY c.name ASC"
        );
        return $stmt->fetchAll();
    }
}
