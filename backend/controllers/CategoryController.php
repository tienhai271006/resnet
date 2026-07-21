<?php
require_once __DIR__ . "/../models/CategoryModel.php";

class CategoryController {
    /** GET /api/categories */
    public static function index(): void {
        Response::ok(CategoryModel::allWithCounts());
    }
}
