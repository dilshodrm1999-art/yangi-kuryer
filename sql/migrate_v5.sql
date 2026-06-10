-- ============================================================
--  v5 migratsiyasi - MAVJUD bazaga qo'shimchalar (ma'lumotni o'chirmaydi)
--  Ishga tushirish:  mysql -u root -p dostavka < sql/migrate_v5.sql
--
--  YANGI v5:  DO'KON EGASI (store owner) paneli
--   * users.role ga 'store' qiymati qo'shiladi
--   * stores.owner_id  - do'konni boshqaradigan foydalanuvchi
--   * stores.logo, stores.cover, stores.theme_color - brending / bezatish
--   * store_sections   - do'kon ichidagi bo'limlar (menyu kategoriyalari)
--   * products.section_id - mahsulot qaysi bo'limga tegishli
-- ============================================================

USE dostavka;

-- ------------------------------------------------------------
-- 'store' rolini qo'shish (ENUM kengaytirish)
-- ------------------------------------------------------------
ALTER TABLE users
  MODIFY COLUMN role ENUM('customer','courier','admin','store') NOT NULL DEFAULT 'customer';

-- ------------------------------------------------------------
-- Do'konga ega (owner) va brending ustunlari
-- ------------------------------------------------------------
ALTER TABLE stores
  ADD COLUMN IF NOT EXISTS owner_id    INT          NULL AFTER id,
  ADD COLUMN IF NOT EXISTS logo        VARCHAR(255) NULL AFTER image,
  ADD COLUMN IF NOT EXISTS cover       VARCHAR(255) NULL AFTER logo,
  ADD COLUMN IF NOT EXISTS theme_color VARCHAR(9)   NOT NULL DEFAULT '#ff6b35' AFTER cover;

-- owner_id uchun bog'lanish (mavjud bo'lmasa qo'shamiz)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'stores'
              AND CONSTRAINT_NAME = 'fk_stores_owner');
SET @sql := IF(@fk = 0,
  'ALTER TABLE stores ADD CONSTRAINT fk_stores_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- Do'kon bo'limlari (ichki menyu kategoriyalari)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS store_sections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    store_id   INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Mahsulotga do'kon bo'limi ustuni
-- ------------------------------------------------------------
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS section_id INT NULL AFTER store_id;

SET @fk2 := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND CONSTRAINT_NAME = 'fk_products_section');
SET @sql2 := IF(@fk2 = 0,
  'ALTER TABLE products ADD CONSTRAINT fk_products_section FOREIGN KEY (section_id) REFERENCES store_sections(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE st2 FROM @sql2; EXECUTE st2; DEALLOCATE PREPARE st2;
