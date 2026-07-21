-- =====================================================================
-- DU LIEU MAU - ResNet
-- Tai khoan demo (mat khau chung: Admin@123, bcrypt cost=12):
--   Admin   : admin@system.com
--   Teacher : teacher@system.com   (vai tro he thong: researcher)
--   Student : student@system.com  (vai tro he thong: reader)
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO `users` (`name`,`email`,`password`,`role`,`institution`,`is_verified`) VALUES
('Quan tri he thong','admin@system.com','$2b$12$LCB89CQgKqhr/F4PW/0DUu/uJoj0MtK26urZ/t5alXSbL1YnFlhnS','admin','Truong CNTT - DH Phenikaa',1),
('Giao vien Demo','teacher@system.com','$2b$12$LCB89CQgKqhr/F4PW/0DUu/uJoj0MtK26urZ/t5alXSbL1YnFlhnS','researcher','Truong CNTT - DH Phenikaa',1),
('Sinh vien Demo','student@system.com','$2b$12$LCB89CQgKqhr/F4PW/0DUu/uJoj0MtK26urZ/t5alXSbL1YnFlhnS','reader',NULL,0);

INSERT INTO `categories` (`name`,`slug`,`icon`) VALUES
('Cong nghe thong tin','cong-nghe-thong-tin','💻'),
('Tri tue nhan tao','tri-tue-nhan-tao','🤖'),
('Y sinh hoc','y-sinh-hoc','🧬'),
('Khoa hoc vat lieu','khoa-hoc-vat-lieu','🔬'),
('Kinh te - Quan ly','kinh-te-quan-ly','📊'),
('Giao duc hoc','giao-duc-hoc','🎓');

-- ---------------------------------------------------------------------
-- BAI DANG MAU (documents) - dang boi tai khoan Teacher (id=2)
-- Ghi chu: file_path/file_hash la du lieu gia lap cho muc dich demo,
-- khong tuong ung file PDF thuc te tren dia.
-- ---------------------------------------------------------------------
INSERT INTO `documents`
  (`owner_id`,`category_id`,`title`,`abstract`,`keywords`,`authors_text`,
   `file_path`,`file_hash`,`file_size`,`visibility`,`allow_download`,
   `status`,`license_type`,`view_count`,`download_count`,`like_count`,`comment_count`) VALUES
(2, 2,
 'Ung dung hoc sau trong nhan dien anh y te',
 'Nghien cuu de xuat mot kien truc mang no-ron tich chap (CNN) cai tien nham nang cao do chinh xac trong phan loai anh chup X-quang phoi, ho tro chan doan som cac benh ly ve duong ho hap. Ket qua thu nghiem tren tap du lieu cong khai cho thay do chinh xac dat tren 94%.',
 'hoc sau, CNN, anh y te, chan doan benh, tri tue nhan tao',
 'Giao vien Demo, Sinh vien Demo',
 'uploads/papers/demo-1.pdf',
 '5d34cf1cbca72d91572b4a4afa2168de51feabf8be752f43d89e72039696150c',
 2150000, 'public', 1, 'approved', 'cc_by', 128, 34, 21, 0),

(2, 1,
 'Toi uu hieu nang he thong phan tan bang thuat toan can bang tai dong',
 'Bai bao trinh bay mot phuong phap can bang tai dong (dynamic load balancing) cho he thong microservices, giup giam do tre trung binh va tang thong luong xu ly trong dieu kien luu luong truy cap bien dong manh. De xuat duoc kiem chung qua mo phong tren cluster gom 20 node.',
 'he thong phan tan, can bang tai, microservices, hieu nang',
 'Giao vien Demo',
 'uploads/papers/demo-2.pdf',
 '49156cd19206b5c376d9252528e738ca9916aad8958a7d46c8fbdb270a4de0ea',
 1870000, 'public', 1, 'approved', 'cc_by_nc', 96, 18, 15, 0),

(2, 4,
 'Vat lieu composite sinh hoc: huong tiep can moi trong bao ve moi truong',
 'Nghien cuu tong hop va danh gia dac tinh co-ly cua mot loai vat lieu composite sinh hoc co kha nang phan huy, huong toi ung dung thay the nhua dung mot lan trong bao bi thuc pham. Cac chi so do ben keo va do hut nuoc duoc trinh bay chi tiet.',
 'composite sinh hoc, vat lieu phan huy, bao ve moi truong',
 'Giao vien Demo, ThS. Le Thi Hoa',
 'uploads/papers/demo-3.pdf',
 'c70eb84f30a049cb81ca0d4818561c52e0c4b7bfa8b09bc5cecd789ccd755418',
 3040000, 'institution', 1, 'approved', 'cc_by_nc_nd', 54, 9, 7, 0),

(2, 6,
 'Danh gia hieu qua hoc tap truc tuyen ket hop (blended learning) o bac dai hoc',
 'Bai viet khao sat tren 500 sinh vien tai 3 truong dai hoc de danh gia muc do hieu qua cua mo hinh hoc tap truc tuyen ket hop so voi hoc truyen thong, tu do de xuat khung tham chieu trien khai phu hop voi dieu kien Viet Nam.',
 'hoc tap truc tuyen, blended learning, giao duc dai hoc',
 'Giao vien Demo',
 'uploads/papers/demo-4.pdf',
 '323930789128da547cdae31d605e8b7b19cc55595b3261e22cb7ed9003827e2c',
 1420000, 'public', 1, 'pending', 'all_rights_reserved', 12, 2, 3, 0);
