-- ============================================================
--  v4 migratsiyasi - MAVJUD bazaga qo'shimchalar (ma'lumotni o'chirmaydi)
--  Ishga tushirish:  mysql -u root -p dostavka < sql/migrate_v4.sql
--
--  YANGI v4:
--   * stores  - do'konlar / fastfudlar (ish vaqti, chegirma, joylashuv)
--   * products.store_id, products.discount_percent
--   * Hudud (viloyat/tuman) + xarita markazi sozlamalari
--   * Shahar markazi poligoni (chiziqlar) + shahar ichi/tashqarisi narxi
-- ============================================================

USE dostavka;

-- ------------------------------------------------------------
-- Do'konlar / fastfudlar
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stores (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(150)  NOT NULL,
    description      TEXT          NULL,
    image            VARCHAR(255)  NULL,
    address          VARCHAR(255)  NULL,
    phone            VARCHAR(20)   NULL,
    lat              DECIMAL(10,7) NULL,
    lng              DECIMAL(10,7) NULL,
    open_time        TIME          NULL DEFAULT '09:00:00',
    close_time       TIME          NULL DEFAULT '22:00:00',
    work_days        VARCHAR(20)   NOT NULL DEFAULT '1,2,3,4,5,6,7', -- 1=Dushanba ... 7=Yakshanba
    discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0,
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Mahsulotga do'kon va chegirma ustunlari
-- ------------------------------------------------------------
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS store_id INT NULL AFTER category_id;
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price;

-- store_id uchun bog'lanish (mavjud bo'lmasa qo'shamiz)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'products'
              AND CONSTRAINT_NAME = 'fk_products_store');
SET @sql := IF(@fk = 0,
  'ALTER TABLE products ADD CONSTRAINT fk_products_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- Buyurtmaga shahar zonasi (ichi/tashqari) ustuni
-- ------------------------------------------------------------
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS delivery_zone VARCHAR(12) NOT NULL DEFAULT 'in' AFTER distance_km;

-- ------------------------------------------------------------
-- Yangi sozlamalar (hudud, xarita markazi, poligon, zonali narx)
-- ------------------------------------------------------------
INSERT INTO settings (skey, svalue) VALUES
  ('region',          'Toshkent shahri'),
  ('district',        ''),
  ('map_lat',         '41.3111000'),
  ('map_lng',         '69.2797000'),
  ('map_zoom',        '12'),
  ('city_polygon',    '[]'),
  ('price_in_city',   '8000'),
  ('price_out_city',  '15000')
ON DUPLICATE KEY UPDATE svalue = svalue;

-- Demo do'konlar
INSERT INTO stores (name, description, image, address, phone, lat, lng, open_time, close_time, discount_percent)
SELECT * FROM (SELECT
  'Oqtepa Lavash' AS name,
  'Tez tayyorlanadigan lavash va fastfud' AS description,
  'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600' AS image,
  'Chilonzor, Bunyodkor ko''chasi' AS address,
  '+998901234567' AS phone,
  41.2750000 AS lat, 69.2040000 AS lng,
  '09:00:00' AS open_time, '23:00:00' AS close_time,
  10.00 AS discount_percent) AS t
WHERE NOT EXISTS (SELECT 1 FROM stores);
