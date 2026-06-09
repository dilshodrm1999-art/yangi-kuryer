-- ============================================================
--  Dostavka (Yetkazib berish) xizmati - MySQL ma'lumotlar bazasi
--  Ishga tushirish:  mysql -u root -p < sql/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS dostavka
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dostavka;

-- Eski jadvallarni o'chirish (qayta o'rnatishda)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Foydalanuvchilar (mijoz / kuryer / admin)
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    phone         VARCHAR(20)   NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    role          ENUM('customer','courier','admin') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Kategoriyalar
-- ------------------------------------------------------------
CREATE TABLE categories (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(100) NOT NULL
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
--   status: new -> accepted -> on_way -> delivered (yoki cancelled)
-- ------------------------------------------------------------
CREATE TABLE orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT NOT NULL,
    courier_id   INT NULL,
    status       ENUM('new','accepted','on_way','delivered','cancelled') NOT NULL DEFAULT 'new',
    address      VARCHAR(255)  NOT NULL,
    lat          DECIMAL(10,7) NULL,
    lng          DECIMAL(10,7) NULL,
    phone        VARCHAR(20)   NULL,
    note         TEXT          NULL,
    total        DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (courier_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buyurtma tarkibi (har bir mahsulot)
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT NULL,
    name        VARCHAR(150)  NOT NULL,   -- buyurtma paytidagi nom (tarix uchun)
    price       DECIMAL(12,2) NOT NULL,   -- buyurtma paytidagi narx
    quantity    INT           NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DEMO MA'LUMOTLAR
-- ============================================================

-- Parol barchasi uchun: "12345"  (PHP password_hash, PASSWORD_DEFAULT)
SET @pw = '$2y$12$Rhplui5xoPJ30sUK1J28i.Q0avU/nqoE9q7J/41aK2EUrzAtzX.VS';

INSERT INTO users (name, phone, password, role) VALUES
  ('Admin',        '+998900000000', @pw, 'admin'),
  ('Kuryer Akmal', '+998901111111', @pw, 'courier'),
  ('Kuryer Sardor','+998902222222', @pw, 'courier'),
  ('Mijoz Dilnoza','+998903333333', @pw, 'customer');

INSERT INTO categories (name) VALUES
  ('Fast Food'),
  ('Ichimliklar'),
  ('Shirinliklar'),
  ('Oziq-ovqat');

INSERT INTO products (category_id, name, description, price, image) VALUES
  (1, 'Lavash',        'Mol go''shtli mazali lavash',        28000, 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=400'),
  (1, 'Gamburger',     'Ikki qavatli cheeseburger',          32000, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400'),
  (1, 'Hot-Dog',       'Klassik hot-dog',                    18000, 'https://images.unsplash.com/photo-1612392062798-2dd9e0d6f0b0?w=400'),
  (2, 'Coca-Cola 1L',  'Sovuq gazli ichimlik',                12000, 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=400'),
  (2, 'Choy',          'Issiq qora choy',                      8000, 'https://images.unsplash.com/photo-1597318181409-cf64d0b5d8a2?w=400'),
  (3, 'Tort bo''lagi', 'Shokoladli tort',                     22000, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400'),
  (4, 'Non',           'Yangi yopilgan non',                   5000, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400');
