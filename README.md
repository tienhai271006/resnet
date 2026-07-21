# ResNet Backend — Mang xa hoi Chia se Nghien cuu Khoa hoc

Backend PHP 8 (kien truc MVC thuan, khong framework) cho do an CSE702051,
tuan thu Huong dan 01/HD-CFIT.CSE702051 va 02/HD-CFIT.CSE702051.

## 1. Nghiep vu trong tam da cai dat

| Yeu cau | Trien khai |
|---|---|
| **Phan quyen tai lieu** | 4 muc `visibility` (public / institution / restricted / private), luong xin-cap quyen (`access_requests` -> `document_permissions`), middleware `Auth::checkDocumentAccess()` |
| **Bao ve ban quyen so** | SHA-256 hash chong trung lap/dao van, DOI tu sinh khi duyet bai, watermark token rieng cho MOI luot tai + nhat ky tai ve (`download_logs`), luong to cao vi pham (`copyright_reports`), 5 loai giay phep Creative Commons |
| **Co che tuong tac** | Thich, luu (bookmark), binh luan theo cay, trich dan giua cac cong trinh, theo doi nha nghien cuu, bang tin (feed), thong bao real-time-ready |
| **Kiem duyet** | Bai dang o trang thai `pending` -> admin `approve`/`reject`/`takedown` |
| **Bao mat** | bcrypt cost 12, Prepared Statement 100%, session HttpOnly + fingerprint, CORS whitelist, validate ca 2 phia |

## 2. Cau truc thu muc

```
resnet/
├── backend/
│ ├── .htaccess # Route ve index.php
│ ├── index.php # Router trung tam (single entry point)
│ ├── config/database.php # Ket noi PDO (Singleton)
│ ├── controllers/
│ │ ├── AuthController.php # Dang ky / dang nhap / ho so
│ │ ├── DocumentController.php # CRUD + upload + phan quyen + kiem duyet
│ │ ├── PermissionController.php # Xin quyen / cap quyen / thu hoi quyen
│ │ ├── CopyrightController.php # To cao vi pham + nhat ky tai ve
│ │ ├── InteractionController.php # Thich / luu / trich dan
│ │ ├── CommentController.php # Binh luan theo cay
│ │ ├── FollowController.php # Theo doi + bang tin
│ │ ├── NotificationController.php # Thong bao
│ │ ├── CategoryController.php # Linh vuc nghien cuu
│ │ ├── UserController.php # Ho so cong khai + quan tri nguoi dung
│ │ └── StatsController.php # Dashboard
│ ├── models/
│ │ ├── UserModel.php # Truy van bang users
│ │ ├── DocumentModel.php # Truy van bang documents + document_versions
│ │ ├── CategoryModel.php # Truy van bang categories
│ │ ├── CommentModel.php # Truy van bang comments
│ │ ├── CopyrightModel.php # Truy van bang copyright_reports + download_logs
│ │ ├── FollowModel.php # Truy van bang follows
│ │ ├── InteractionModel.php # Truy van bang likes / bookmarks / citations
│ │ ├── NotificationModel.php # Truy van bang notifications
│ │ └── PermissionModel.php # Truy van bang access_requests + document_permissions
│ ├── middleware/Auth.php # required() / role() / owns() / checkDocumentAccess()
│ ├── utils/
│ │ ├── Response.php # Chuan hoa JSON response
│ │ ├── Pagination.php # Phan trang
│ │ ├── FileUpload.php # Upload PDF/anh, kiem tra mime that (finfo)
│ │ ├── Sanitize.php # Lam sach du lieu dau vao
│ │ └── Copyright.php # Sinh DOI, watermark token, kiem tra trung lap (goi DocumentModel)
│ └── uploads/{papers,covers,avatars}/
├── frontend/ # React (Vite) SPA - xem frontend/README.md
└── database/
├── schema.sql # 14 bang, day du FK + Index
└── seed.sql # Du lieu mau (idempotent, dung ON DUPLICATE KEY)
```
## 3. Cai dat nhanh (localhost / XAMPP)

1. Copy thu muc nay vao `C:\xampp\htdocs\resnet\backend`.
2. Tao CSDL `resnet_db` trong phpMyAdmin, import lan luot `database/schema.sql`
   roi `database/seed.sql`.
3. Cau hinh bien moi truong hoac sua truc tiep `config/database.php`
   (DB_HOST=localhost, DB_NAME=resnet_db, DB_USER=root, DB_PASS="").
4. Truy cap thu: `http://localhost/resnet/backend/api/categories`
   -> phai tra ve JSON danh sach 6 linh vuc.
5. Tai khoan demo (mat khau chung: `Admin@123`):
   - Admin: `admin@system.com`
   - Teacher: `teacher@system.com`
   - Student: `student@system.com`

## 4. Danh sach endpoint chinh

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
PUT    /api/auth/profile

GET    /api/categories

GET    /api/documents?q=&category_id=&sort=&page=
GET    /api/documents?mine=1                      (cong trinh cua toi)
GET    /api/documents/pending                      (admin - hang cho duyet)
GET    /api/documents/:id
POST   /api/documents                              (dang tai - researcher)
PUT    /api/documents/:id
DELETE /api/documents/:id
GET    /api/documents/:id/download                 (kiem quyen + ghi log watermark)
GET    /api/documents/:id/versions
POST   /api/documents/:id/versions
POST   /api/documents/:id/approve                  (admin)
POST   /api/documents/:id/reject                   (admin)
POST   /api/documents/:id/takedown                 (admin)

POST   /api/documents/:id/access-requests           (xin quyen truy cap)
GET    /api/documents/:id/access-requests           (chu so huu xem)
POST   /api/access-requests/:id/approve
POST   /api/access-requests/:id/reject
GET    /api/documents/:id/permissions
DELETE /api/permissions/:id

POST   /api/documents/:id/report                   (to cao vi pham ban quyen)
GET    /api/copyright-reports                       (admin)
POST   /api/copyright-reports/:id/resolve           (admin)
GET    /api/documents/:id/download-logs             (chu so huu truy vet)

POST   /api/documents/:id/like
POST   /api/documents/:id/bookmark
GET    /api/me/bookmarks
POST   /api/documents/:id/cite
GET    /api/documents/:id/citations

GET    /api/documents/:id/comments
POST   /api/documents/:id/comments
DELETE /api/comments/:id
POST   /api/comments/:id/hide                        (admin)

POST   /api/users/:id/follow
GET    /api/users/:id/followers
GET    /api/users/:id/following
GET    /api/users/:id
GET    /api/users/:id/documents
GET    /api/feed

GET    /api/notifications
POST   /api/notifications/:id/read
POST   /api/notifications/read-all

GET    /api/stats/summary
GET    /api/stats/my

GET    /api/admin/users
POST   /api/admin/users/:id/toggle-active
POST   /api/admin/users/:id/verify
```
