-- =====================================================================
-- CSDL: Mang xa hoi Chia se Nghien cuu Khoa hoc (ResNet)
-- Tuan thu HD-CFIT.CSE702051 (>=4 bang, FK, Index, du lieu mau, PDO+Prepared Stmt)
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. USERS - nguoi dung he thong (3 vai tro)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(200) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL COMMENT "bcrypt hash, KHONG luu plaintext",
  `role`          ENUM('admin','researcher','reader') NOT NULL DEFAULT 'reader',
  `institution`   VARCHAR(200) NULL COMMENT "Truong / Vien nghien cuu",
  `orcid_id`      VARCHAR(30)  NULL COMMENT "Ma dinh danh nha khoa hoc ORCID (neu co)",
  `bio`           TEXT NULL,
  `avatar`        VARCHAR(255) NULL,
  `is_verified`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT "Da xac minh la nha nghien cuu",
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT "0 = bi khoa tai khoan",
  `last_login`    DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT="Nguoi dung: admin quan tri | researcher dang bai | reader doc/tuong tac";

-- ---------------------------------------------------------------------
-- 2. CATEGORIES - linh vuc nghien cuu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(120) NOT NULL,
  `slug`  VARCHAR(120) NOT NULL UNIQUE,
  `icon`  VARCHAR(20)  NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. DOCUMENTS - bai bao / cong trinh nghien cuu (thuc the trung tam)
--    Chua toan bo co che PHAN QUYEN + BAO VE BAN QUYEN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id`         INT UNSIGNED NOT NULL COMMENT "Tac gia dang tai",
  `category_id`      INT UNSIGNED NULL,
  `title`            VARCHAR(300) NOT NULL,
  `abstract`         TEXT NOT NULL COMMENT "Tom tat - LUON hien thi cong khai",
  `keywords`         VARCHAR(400) NULL,
  `authors_text`     VARCHAR(500) NULL COMMENT "Danh sach dong tac gia (chuoi hien thi)",
  `file_path`        VARCHAR(255) NOT NULL COMMENT "PDF goc, KHONG public truc tiep - phai qua downloadWatermark()",
  `file_hash`        CHAR(64) NOT NULL COMMENT "SHA-256 file, phat hien trung lap / dao van",
  `file_size`        INT UNSIGNED NULL COMMENT "Bytes",
  `cover_image`      VARCHAR(255) NULL,
  `visibility`       ENUM('public','institution','restricted','private') NOT NULL DEFAULT 'restricted'
                      COMMENT "public=ai cung xem+tai | institution=cung don vi | restricted=phai duoc cap quyen | private=chi tac gia",
  `allow_download`   TINYINT(1) NOT NULL DEFAULT 1 COMMENT "Tac gia co the chan tai file, chi cho xem abstract",
  `status`           ENUM('pending','approved','rejected','takedown') NOT NULL DEFAULT 'pending'
                      COMMENT "Kiem duyet: cho duyet / da duyet / tu choi / go do vi pham ban quyen",
  `reject_reason`    VARCHAR(300) NULL,
  `license_type`     ENUM('all_rights_reserved','cc_by','cc_by_nc','cc_by_nc_nd','cc0') NOT NULL DEFAULT 'all_rights_reserved',
  `doi`              VARCHAR(80) NULL UNIQUE COMMENT "Digital Object Identifier cap boi he thong",
  `copyright_owner`  VARCHAR(200) NULL COMMENT "Chu so huu ban quyen neu khac tac gia",
  `view_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `download_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `like_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `citation_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `comment_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_owner` (`owner_id`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_visibility_status` (`visibility`,`status`),
  INDEX `idx_hash` (`file_hash`),
  FULLTEXT INDEX `ft_search` (`title`,`abstract`,`keywords`),
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. DOCUMENT_VERSIONS - lich su phien ban (bao ve toan ven noi dung)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_versions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `version_no`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `file_path`     VARCHAR(255) NOT NULL,
  `file_hash`     CHAR(64) NOT NULL,
  `changelog`     VARCHAR(300) NULL,
  `uploaded_by`   INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_version` (`document_id`,`version_no`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. DOCUMENT_PERMISSIONS - cap quyen truy cap tai lieu 'restricted'
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_permissions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`      INT UNSIGNED NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `permission_type`  ENUM('view','download') NOT NULL DEFAULT 'view',
  `granted_by`       INT UNSIGNED NOT NULL,
  `expires_at`       DATETIME NULL COMMENT "NULL = vinh vien",
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm` (`document_id`,`user_id`,`permission_type`),
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. ACCESS_REQUESTS - luong xin cap quyen truy cap
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `requester_id`  INT UNSIGNED NOT NULL,
  `message`       VARCHAR(500) NULL COMMENT "Ly do xin truy cap",
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED NULL,
  `reviewed_at`   DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pending_request` (`document_id`,`requester_id`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. DOWNLOAD_LOGS - nhat ky tai file (truy vet ban quyen / watermark)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `download_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`    INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `watermark_token`VARCHAR(64) NOT NULL COMMENT "Token nhung vao ban tai ve, doi chieu khi phat hien ro ri",
  `ip_address`     VARCHAR(45) NULL,
  `user_agent`     VARCHAR(255) NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_doc_time` (`document_id`,`created_at`),
  INDEX `idx_token` (`watermark_token`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. COPYRIGHT_REPORTS - to cao vi pham ban quyen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `copyright_reports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `reported_by`   INT UNSIGNED NOT NULL,
  `reason`        ENUM('plagiarism','unauthorized_reupload','license_violation','other') NOT NULL,
  `description`   TEXT NULL,
  `evidence_url`  VARCHAR(300) NULL,
  `status`        ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  `resolved_by`   INT UNSIGNED NULL,
  `resolution_note` VARCHAR(300) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. LIKES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_like` (`document_id`,`user_id`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. BOOKMARKS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookmark` (`document_id`,`user_id`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. COMMENTS - co cay tra loi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `parent_id`     INT UNSIGNED NULL COMMENT "NULL = binh luan goc",
  `content`       TEXT NOT NULL,
  `is_hidden`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_doc` (`document_id`),
  INDEX `idx_parent` (`parent_id`),
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. CITATIONS - trich dan giua cac cong trinh
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `citations` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `citing_document_id` INT UNSIGNED NOT NULL,
  `cited_document_id`  INT UNSIGNED NOT NULL,
  `created_by`         INT UNSIGNED NOT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_citation` (`citing_document_id`,`cited_document_id`),
  FOREIGN KEY (`citing_document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cited_document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. FOLLOWS - theo doi nha nghien cuu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `follows` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `follower_id`    INT UNSIGNED NOT NULL,
  `following_id`   INT UNSIGNED NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_follow` (`follower_id`,`following_id`),
  FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. NOTIFICATIONS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `type`          ENUM('like','comment','follow','access_request','access_approved',
                        'access_rejected','doc_approved','doc_rejected','citation','report') NOT NULL,
  `actor_id`      INT UNSIGNED NULL,
  `document_id`   INT UNSIGNED NULL,
  `message`       VARCHAR(300) NOT NULL,
  `is_read`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_read` (`user_id`,`is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
