-- ============================================================
--  Dostavka (Yetkazib berish) xizmati - MySQL bazasi  (v2)
--  Ishga tushirish:  mysql -u root -p < sql/schema.sql
--
--  YANGI v2: kuryer balansi, jonli lokatsiya, km-narx sozlamasi,
--  buyurtma masofasi va yetkazib berish haqi.
-- ============================================================

CREATE DATABASE IF NOT EXISTS dostavka
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dostavka;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Foydalanuvchilar (mijoz / kuryer / admin)
--   balance     - kuryer hisobidagi pul (yetkazganlari uchun)
--   lat/lng     - kuryerning jonli joylashuvi
--   last_seen   - oxirgi lokatsiya yangilangan vaqt
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    phone         VARCHAR(20)   NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    role          ENUM('customer','courier','admin') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    balance       DECIMAL(12,2) NOT NULL DEFAULT 0,
    lat           DECIMAL(10,7) NULL,
    lng           DECIMAL(10,7) NULL,
    last_seen     TIMESTAMP     NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tizim sozlamalari (kalit-qiymat)
-- ------------------------------------------------------------
CREATE TABLE settings (
    skey   VARCHAR(50)  PRIMARY KEY,
    svalue VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Kategoriyalar
-- ------------------------------------------------------------
CREATE TABLE categories (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(100) NOT NULL,
    icon   VARCHAR(30)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Mahsulotlar
-- ------------------------------------------------------------
CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT NULL,
    name         VARCHAR(150)   NOT NULL,
    description  TEXT           NULL,
    price        DECIMAL(12,2)  NOT NULL DEFAULT 0,
    image        VARCHAR(255)   NULL,
    is_available TINYINT(1)     NOT NULL DEFAULT 1,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buyurtmalar
--   status:      new -> accepted -> picked_up -> on_way -> delivered (yoki cancelled)
--   pickup_*     - mahsulot olinadigan nuqta (do'kon)
--   distance_km  - olish nuqtasidan mijozgacha masofa
--   delivery_fee - kuryerga to'lanadigan haq (masofa * km-narx)
-- ------------------------------------------------------------
CREATE TABLE orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT NOT NULL,
    courier_id    INT NULL,
    status        ENUM('new','accepted','picked_up','on_way','delivered','cancelled') NOT NULL DEFAULT 'new',
    address       VARCHAR(255)  NOT NULL,
    lat           DECIMAL(10,7) NULL,
    lng           DECIMAL(10,7) NULL,
    pickup_lat    DECIMAL(10,7) NULL,
    pickup_lng    DECIMAL(10,7) NULL,
    distance_km   DECIMAL(6,2)  NOT NULL DEFAULT 0,
    delivery_fee  DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission    DECIMAL(12,2) NOT NULL DEFAULT 0,
    phone         VARCHAR(20)   NULL,
    note          TEXT          NULL,
    total         DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_to_courier TINYINT(1)  NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (courier_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buyurtma tarkibi
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT NULL,
    name        VARCHAR(150)  NOT NULL,
    price       DECIMAL(12,2) NOT NULL,
    quantity    INT           NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DEMO MA'LUMOTLAR
-- ============================================================

-- Sozlamalar: 1 km uchun narx, do'kon (olish) joylashuvi
INSERT INTO settings (skey, svalue) VALUES
  ('price_per_km', '8000'),
  ('store_name',   'Asosiy ombor'),
  ('store_lat',    '41.3110810'),
  ('store_lng',    '69.2405620'),
  ('min_fee',      '5000'),
  ('commission_percent', '20');

-- Parol barchasi uchun: "12345"
SET @pw = '$2y$12$Rhplui5xoPJ30sUK1J28i.Q0avU/nqoE9q7J/41aK2EUrzAtzX.VS';

INSERT INTO users (name, phone, password, role, balance) VALUES
  ('Admin',        '+998900000000', @pw, 'admin',   0),
  ('Kuryer Akmal', '+998901111111', @pw, 'courier', 0),
  ('Kuryer Sardor','+998902222222', @pw, 'courier', 0),
  ('Mijoz Dilnoza','+998903333333', @pw, 'customer',0);

INSERT INTO categories (name, icon) VALUES
  ('Fast Food',    'burger'),
  ('Ichimliklar',  'cup'),
  ('Shirinliklar', 'cake'),
  ('Oziq-ovqat',   'basket');

INSERT INTO products (category_id, name, description, price, image) VALUES
  (1, 'Lavash',        'Mol go''shtli mazali lavash',        28000, 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=500'),
  (1, 'Gamburger',     'Ikki qavatli cheeseburger',          32000, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500'),
  (1, 'Hot-Dog',       'Klassik hot-dog',                    18000, 'https://images.unsplash.com/photo-1612392062798-2dd9e0d6f0b0?w=500'),
  (2, 'Coca-Cola 1L',  'Sovuq gazli ichimlik',                12000, 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=500'),
  (2, 'Choy',          'Issiq qora choy',                      8000, 'https://images.unsplash.com/photo-1597318181409-cf64d0b5d8a2?w=500'),
  (3, 'Tort bo''lagi', 'Shokoladli tort',                     22000, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=500'),
  (4, 'Non',           'Yangi yopilgan non',                   5000, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500');
