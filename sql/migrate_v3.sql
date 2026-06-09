-- ============================================================
--  v3 migratsiyasi - MAVJUD bazaga qo'shimchalar
--  (ma'lumotlarni o'chirmaydi)
--  Ishga tushirish:  mysql -u root -p dostavka < sql/migrate_v3.sql
-- ============================================================

USE dostavka;

-- Buyurtmaga admin komissiyasi ustuni
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS commission DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER delivery_fee;

-- Admin komissiya foizi sozlamasi (agar yo'q bo'lsa)
INSERT INTO settings (skey, svalue) VALUES ('commission_percent', '20')
  ON DUPLICATE KEY UPDATE svalue = svalue;
