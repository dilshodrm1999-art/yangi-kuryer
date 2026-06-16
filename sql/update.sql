-- ============================================================
--  QO'SHIMCHA (UPDATE) SQL — mavjud bazaga yangi ustun/jadvallarni
--  qo'shadi. MA'LUMOTNI O'CHIRMAYDI.
--
--  Ishlatish:
--    mysql -u FOYDALANUVCHI -p BAZA_NOMI < sql/update.sql
--  yoki phpMyAdmin -> bazani tanlang -> Import -> shu faylni yuklang.
--
--  Xavfsiz (idempotent): ustun/jadval allaqachon bo'lsa,
--  xato bermay o'tkazib yuboradi. Bir necha marta ishga tushirsa ham zarari yo'q.
--  MySQL 8 va MariaDB ikkalasida ham ishlaydi.
-- ============================================================

-- >>> AGAR BAZA NOMINGIZ BOSHQA BO'LSA, shu qatorni o'zgartiring <<<
USE dostavka;

-- ------------------------------------------------------------
-- Yordamchi protsedura: ustun bo'lmasa qo'shadi
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing(
    IN tbl  VARCHAR(64),
    IN col  VARCHAR(64),
    IN ddl  VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tbl
          AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END //
DELIMITER ;

-- ============================================================
--  1) users — 'store' (do'kon egasi) rolini qo'shish
-- ============================================================
ALTER TABLE users
  MODIFY COLUMN role ENUM('customer','courier','admin','store') NOT NULL DEFAULT 'customer';

-- users — keshbek balansi (mijoz uchun)
CALL add_col_if_missing('users','cashback_balance', "cashback_balance DECIMAL(12,2) NOT NULL DEFAULT 0");

-- users — kuryer rasmi va pasport rasmi
CALL add_col_if_missing('users','photo',    "photo VARCHAR(255) NULL");
CALL add_col_if_missing('users','passport', "passport VARCHAR(255) NULL");

-- ovozli xabarlar (ratsiya) jadvali
CREATE TABLE IF NOT EXISTS voice_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NULL,
    audio       VARCHAR(255) NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  2) stores jadvali (do'konlar/fastfudlar) — bo'lmasa yaratiladi
-- ============================================================
CREATE TABLE IF NOT EXISTS stores (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    owner_id         INT           NULL,
    name             VARCHAR(150)  NOT NULL,
    description      TEXT          NULL,
    image            VARCHAR(255)  NULL,
    logo             VARCHAR(255)  NULL,
    cover            VARCHAR(255)  NULL,
    theme_color      VARCHAR(9)    NOT NULL DEFAULT '#ff6b35',
    address          VARCHAR(255)  NULL,
    phone            VARCHAR(20)   NULL,
    lat              DECIMAL(10,7) NULL,
    lng              DECIMAL(10,7) NULL,
    open_time        TIME          NULL DEFAULT '09:00:00',
    close_time       TIME          NULL DEFAULT '22:00:00',
    work_days        VARCHAR(20)   NOT NULL DEFAULT '1,2,3,4,5,6,7',
    discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0,
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- stores allaqachon bor bo'lsa, yetishmaydigan ustunlarni qo'shamiz
CALL add_col_if_missing('stores','owner_id',    "owner_id INT NULL AFTER id");
CALL add_col_if_missing('stores','logo',        "logo VARCHAR(255) NULL AFTER image");
CALL add_col_if_missing('stores','cover',       "cover VARCHAR(255) NULL AFTER logo");
CALL add_col_if_missing('stores','theme_color', "theme_color VARCHAR(9) NOT NULL DEFAULT '#ff6b35' AFTER cover");

-- ============================================================
--  3) store_sections jadvali (do'kon bo'limlari) — bo'lmasa yaratiladi
-- ============================================================
CREATE TABLE IF NOT EXISTS store_sections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    store_id   INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  4) products — do'kon, bo'lim va chegirma ustunlari
-- ============================================================
CALL add_col_if_missing('products','store_id',         "store_id INT NULL AFTER category_id");
CALL add_col_if_missing('products','section_id',       "section_id INT NULL AFTER store_id");
CALL add_col_if_missing('products','discount_percent', "discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price");

-- ============================================================
--  5) orders — masofa, zona, olish nuqtasi, komissiya, bekor so'rovi
-- ============================================================
CALL add_col_if_missing('orders','pickup_lat',       "pickup_lat DECIMAL(10,7) NULL");
CALL add_col_if_missing('orders','pickup_lng',       "pickup_lng DECIMAL(10,7) NULL");
CALL add_col_if_missing('orders','pickup_name',      "pickup_name VARCHAR(150) NULL");
CALL add_col_if_missing('orders','pickup_address',   "pickup_address VARCHAR(255) NULL");
CALL add_col_if_missing('orders','distance_km',      "distance_km DECIMAL(6,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','delivery_zone',    "delivery_zone VARCHAR(12) NOT NULL DEFAULT 'in'");
CALL add_col_if_missing('orders','delivery_fee',     "delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','commission',       "commission DECIMAL(12,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cashback_percent', "cashback_percent DECIMAL(5,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cashback',         "cashback DECIMAL(12,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cashback_used',    "cashback_used DECIMAL(12,2) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cashback_paid',    "cashback_paid TINYINT(1) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','paid_to_courier',  "paid_to_courier TINYINT(1) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cancel_requested', "cancel_requested TINYINT(1) NOT NULL DEFAULT 0");
CALL add_col_if_missing('orders','cancel_reason',    "cancel_reason VARCHAR(255) NULL");

-- ============================================================
--  6) Yangi sozlamalar (hudud, xarita, shahar zonasi, narxlar)
--     INSERT IGNORE — mavjud bo'lsa tegmaydi, qiymatlaringiz saqlanadi
-- ============================================================
INSERT IGNORE INTO settings (skey, svalue) VALUES
  ('price_in_city',  '8000'),
  ('price_out_city', '15000'),
  ('min_fee',        '5000'),
  ('commission_percent', '20'),
  ('cashback_percent', '0'),
  ('region',         'Buxoro viloyati'),
  ('district',       'Qorako\'l'),
  ('map_lat',        '39.5098680'),
  ('map_lng',        '63.8538900'),
  ('map_zoom',       '13'),
  ('city_polygon',   '[]');

-- ------------------------------------------------------------
-- Xarita markazini Toshkentdan Qorako'l (Buxoro viloyati)ga ko'chirish.
-- FAQAT eski Toshkent qiymati saqlangan bo'lsa o'zgaradi — admin o'zi
-- belgilagan boshqa joy bo'lsa, unga TEGMAYDI.
-- ------------------------------------------------------------
UPDATE settings SET svalue = '39.5098680' WHERE skey = 'map_lat'   AND svalue IN ('41.3111000','41.3111');
UPDATE settings SET svalue = '63.8538900' WHERE skey = 'map_lng'   AND svalue IN ('69.2797000','69.2797');
UPDATE settings SET svalue = '13'         WHERE skey = 'map_zoom'  AND svalue = '12';
UPDATE settings SET svalue = 'Buxoro viloyati' WHERE skey = 'region' AND svalue = 'Toshkent shahri';
UPDATE settings SET svalue = 'Qorako\'l'  WHERE skey = 'district' AND svalue = '';
UPDATE settings SET svalue = '39.5098680' WHERE skey = 'store_lat' AND svalue IN ('41.3110810','41.311081');
UPDATE settings SET svalue = '63.8538900' WHERE skey = 'store_lng' AND svalue IN ('69.2405620','69.240562');

-- ------------------------------------------------------------
-- Tozalash
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_col_if_missing;

-- Tayyor! Mavjud ma'lumotlaringiz saqlandi, faqat yangi ustun/jadvallar qo'shildi.
