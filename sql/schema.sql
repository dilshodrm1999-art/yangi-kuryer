-- ============================================================
--  Dostavka (Yetkazib berish) xizmati - TO'LIQ MySQL bazasi
--  ----------------------------------------------------------
--  BITTA UMUMIY FAYL: shu faylning o'zi to'liq tizimni o'rnatadi.
--  Boshqa migratsiya fayllari kerak emas.
--
--  Ishga tushirish (terminal):
--      mysql -u root -p < sql/schema.sql
--
--  Yoki phpMyAdmin -> Import -> shu faylni tanlang.
--
--  DIQQAT: bu fayl mavjud "dostavka" bazasidagi jadvallarni
--  o'chirib qaytadan yaratadi (demo ma'lumot bilan).
--
--  Ichida: foydalanuvchilar (mijoz/kuryer/admin/do'kon egasi),
--  do'konlar+brending, do'kon bo'limlari, mahsulotlar+chegirma,
--  buyurtmalar (masofa, zona, komissiya), sozlamalar (hudud,
--  xarita, shahar poligoni, shahar ichi/tashqarisi narxi).
--
--  Standart parol (barcha demo akkauntlar uchun): 12345
-- ============================================================

CREATE DATABASE IF NOT EXISTS dostavka
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dostavka;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS store_sections;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS stores;
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
    role          ENUM('customer','courier','admin','store') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    balance       DECIMAL(12,2) NOT NULL DEFAULT 0,
    cashback_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    photo         VARCHAR(255)  NULL,
    passport      VARCHAR(255)  NULL,
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
-- Do'konlar / fastfudlar
--   owner_id             - do'konni boshqaradigan foydalanuvchi (role='store')
--   logo / cover         - do'kon logotipi va sarlavha rasmi (brending)
--   theme_color          - do'kon oynasi asosiy rangi
--   open_time/close_time - ish vaqti
--   work_days            - ish kunlari (1=Dush ... 7=Yak)
--   discount_percent     - do'kon bo'yicha umumiy chegirma
--   lat/lng              - do'kon (olish nuqtasi) joylashuvi
-- ------------------------------------------------------------
CREATE TABLE stores (
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
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Do'kon bo'limlari (do'kon ichidagi menyu kategoriyalari)
-- ------------------------------------------------------------
CREATE TABLE store_sections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    store_id   INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Mahsulotlar
--   store_id          - qaysi do'konga tegishli
--   section_id        - do'kon ichidagi bo'lim
--   discount_percent  - mahsulot bo'yicha chegirma (%)
-- ------------------------------------------------------------
CREATE TABLE products (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    category_id      INT NULL,
    store_id         INT NULL,
    section_id       INT NULL,
    name             VARCHAR(150)   NOT NULL,
    description      TEXT           NULL,
    price            DECIMAL(12,2)  NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2)   NOT NULL DEFAULT 0,
    image            VARCHAR(255)   NULL,
    is_available     TINYINT(1)     NOT NULL DEFAULT 1,
    created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)      ON DELETE SET NULL,
    FOREIGN KEY (store_id)    REFERENCES stores(id)          ON DELETE SET NULL,
    FOREIGN KEY (section_id)  REFERENCES store_sections(id)  ON DELETE SET NULL
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
    pickup_name   VARCHAR(150)  NULL,
    pickup_address VARCHAR(255) NULL,
    distance_km   DECIMAL(6,2)  NOT NULL DEFAULT 0,
    delivery_zone VARCHAR(12)   NOT NULL DEFAULT 'in',
    delivery_fee  DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission    DECIMAL(12,2) NOT NULL DEFAULT 0,
    cashback_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    cashback      DECIMAL(12,2) NOT NULL DEFAULT 0,
    cashback_used DECIMAL(12,2) NOT NULL DEFAULT 0,
    cashback_paid TINYINT(1)    NOT NULL DEFAULT 0,
    phone         VARCHAR(20)   NULL,
    note          TEXT          NULL,
    total         DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_to_courier TINYINT(1)  NOT NULL DEFAULT 0,
    cancel_requested TINYINT(1) NOT NULL DEFAULT 0,
    cancel_reason   VARCHAR(255) NULL,
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

-- ------------------------------------------------------------
-- Ovozli xabarlar (ratsiya) — admin <-> kuryer
--   sender_id    - kim yubordi
--   receiver_id  - kimga (NULL bo'lsa: kuryerdan -> barcha adminlarga)
--   audio        - /uploads/voice/... yo'li
--   is_read      - qabul qiluvchi eshitdimi
-- ------------------------------------------------------------
CREATE TABLE voice_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NULL,
    audio       VARCHAR(255) NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DEMO MA'LUMOTLAR
-- ============================================================

-- Sozlamalar: hudud, xarita markazi, shahar poligoni, zonali narxlar
INSERT INTO settings (skey, svalue) VALUES
  ('price_per_km', '8000'),
  ('price_in_city',  '8000'),
  ('price_out_city', '15000'),
  ('store_name',   'Asosiy ombor'),
  ('store_lat',    '39.5098680'),
  ('store_lng',    '63.8538900'),
  ('min_fee',      '5000'),
  ('commission_percent', '20'),
  ('cashback_percent', '0'),
  ('region',       'Buxoro viloyati'),
  ('district',     'Qorako\'l'),
  ('map_lat',      '39.5098680'),
  ('map_lng',      '63.8538900'),
  ('map_zoom',     '13'),
  ('city_polygon', '[]');

-- Parol barchasi uchun: "12345"
SET @pw = '$2y$12$Rhplui5xoPJ30sUK1J28i.Q0avU/nqoE9q7J/41aK2EUrzAtzX.VS';

INSERT INTO users (name, phone, password, role, balance) VALUES
  ('Admin',        '+998900000000', @pw, 'admin',   0),
  ('Kuryer Akmal', '+998901111111', @pw, 'courier', 0),
  ('Kuryer Sardor','+998902222222', @pw, 'courier', 0),
  ('Mijoz Dilnoza','+998903333333', @pw, 'customer',0),
  ('Oqtepa egasi', '+998904444444', @pw, 'store',   0),
  ('Evos egasi',   '+998905555555', @pw, 'store',   0);

INSERT INTO categories (name, icon) VALUES
  ('Fast Food',    'burger'),
  ('Ichimliklar',  'cup'),
  ('Shirinliklar', 'cake'),
  ('Oziq-ovqat',   'basket');

-- Do'konlar / fastfudlar (owner_id: 5=Oqtepa egasi, 6=Evos egasi)
INSERT INTO stores (owner_id, name, description, image, logo, cover, theme_color, address, phone, lat, lng, open_time, close_time, discount_percent) VALUES
  (5, 'Oqtepa Lavash', 'Tez tayyorlanadigan lavash va fastfud',
   'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600',
   'https://images.unsplash.com/photo-1599974579688-8dbdd335c77f?w=200',
   'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1200',
   '#e63946',
   'Chilonzor, Bunyodkor ko''chasi', '+998901234567',
   41.2750000, 69.2040000, '09:00:00', '23:00:00', 10.00),
  (6, 'Evos Burger', 'Burger, hot-dog va ichimliklar',
   'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=600',
   'https://images.unsplash.com/photo-1606131731446-5568d87113aa?w=200',
   'https://images.unsplash.com/photo-1550547660-d9450f859349?w=1200',
   '#f4a261',
   'Yunusobod, Amir Temur shoh ko''chasi', '+998907654321',
   41.3380000, 69.2890000, '10:00:00', '22:00:00', 0.00);

-- Do'kon bo'limlari (1,2 -> Oqtepa; 3,4 -> Evos)
INSERT INTO store_sections (store_id, name, sort_order) VALUES
  (1, 'Lavashlar',   1),
  (1, 'Ichimliklar', 2),
  (2, 'Burgerlar',   1),
  (2, 'Shirinliklar',2);

INSERT INTO products (category_id, store_id, section_id, name, description, price, discount_percent, image) VALUES
  (1, 1, 1, 'Lavash',        'Mol go''shtli mazali lavash',        28000, 10, 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=500'),
  (1, 2, 3, 'Gamburger',     'Ikki qavatli cheeseburger',          32000, 0,  'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500'),
  (1, 2, 3, 'Hot-Dog',       'Klassik hot-dog',                    18000, 0,  'https://images.unsplash.com/photo-1612392062798-2dd9e0d6f0b0?w=500'),
  (2, 1, 2, 'Coca-Cola 1L',  'Sovuq gazli ichimlik',                12000, 0,  'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=500'),
  (2, 1, 2, 'Choy',          'Issiq qora choy',                      8000, 0,  'https://images.unsplash.com/photo-1597318181409-cf64d0b5d8a2?w=500'),
  (3, 2, 4, 'Tort bo''lagi', 'Shokoladli tort',                     22000, 15, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=500'),
  (4, 1, 1, 'Non',           'Yangi yopilgan non',                   5000, 0,  'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500');
