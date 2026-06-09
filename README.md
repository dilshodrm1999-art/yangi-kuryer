# 🛵 Dostavka — Mahsulot yetkazib berish xizmati

HTML + CSS + JS + PHP + MySQL asosida qurilgan to'liq dostavka tizimi.
Uch xil rol: **Mijoz**, **Kuryer**, **Admin**.

## Imkoniyatlar

### 👤 Mijoz oynasi
- Mahsulotlarni kategoriya va qidiruv bo'yicha ko'rish
- Savatchaga qo'shish, sonini o'zgartirish
- Manzil yozish + **xaritadan lokatsiya** tanlash (Leaflet / OpenStreetMap)
- Buyurtma berish va holatini kuzatish

### 🛵 Kuryer oynasi
- Faqat o'ziga tayinlangan buyurtmalar
- Qayerga (manzil + xarita yo'li) va nimalar olib borish
- Status yangilash: Qabul qildim → Yo'lda → Yetkazdim

### 🛠 Admin panel
- Statistika (buyurtma, tushum, kuryerlar)
- Mahsulot qo'shish/tahrirlash/o'chirish (narx, rasm, kategoriya)
- Kuryer ulash, bloklash/faollashtirish
- Buyurtmalarni kuryerga tayinlash va statusini boshqarish

## O'rnatish

1. **Bazani yaratish:**
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

2. **Ulanishni sozlash** — `config/db.php` ichidagi `DB_USER`, `DB_PASS` ni o'zgartiring
   (yoki muhit o'zgaruvchilari: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

3. **Serverni ishga tushirish:**
   ```bash
   php -S localhost:8000
   ```
   So'ng brauzerda oching: http://localhost:8000

## Demo hisoblar (parol: `12345`)

| Rol    | Telefon         |
|--------|-----------------|
| Admin  | `+998900000000` |
| Kuryer | `+998901111111` |
| Mijoz  | `+998903333333` |

## Tuzilma

```
config/db.php        — MySQL ulanish (PDO)
includes/            — funksiyalar, header, footer
sql/schema.sql       — baza sxemasi + demo ma'lumotlar
index.php            — mijoz: mahsulotlar
cart.php, checkout.php, orders.php
courier/index.php    — kuryer paneli
admin/               — index, products, couriers, orders
assets/css, assets/js
```

## Buyurtma jarayoni (status)
`new` (yangi) → `accepted` (kuryerga tayinlandi) → `on_way` (yo'lda) → `delivered` (yetkazildi) / `cancelled` (bekor)


---

## 🆕 v2 yangiliklari

- **Zamonaviy mobil-birinchi dizayn** — inline SVG ikonkalar, pastki navigatsiya (bottom-nav), telefon uchun qulay.
- **Avto-geolokatsiya** — mijoz buyurtma berishda joylashuvi telefondan avtomatik olinadi (GPS + Nominatim reverse geocoding bilan manzil avto to'ladi).
- **Jonli kuzatuv (live tracking):**
  - Mijoz buyurtmasi qabul qilingach, kuryerni xaritada real vaqtda kuzatadi.
  - Admin barcha faol kuryerlarni jonli xaritada va ularning harakatini ko'radi.
- **Kuryer balansi** — har bir yetkazilgan buyurtma uchun haq avtomatik kuryer balansiga qo'shiladi. Balans va daromad tarixi sahifasi.
- **Masofaviy narx tizimi** — admin 1 km uchun narxni (default 8 000 so'm) va minimal haqni sozlaydi. Haq = (ombordan mijozgacha masofa) × km-narx, avtomatik hisoblanadi.
- **Admin sozlamalari** (`admin/settings.php`) — km-narx, minimal haq, ombor (olish nuqtasi) joylashuvi xaritadan.
- **Buyurtma bosqichlari** — `new → accepted → picked_up → on_way → delivered`, mijozda bosqichli tracker.
- **Admin payout** — kuryerga to'lov qilib balansni nollash.

### Yangi fayllar
```
api/location_update.php   — kuryer GPS lokatsiyasini yuboradi
api/order_track.php       — mijoz/admin buyurtma bo'yicha kuryer joyini oladi
api/couriers_live.php     — admin barcha kuryerlar joylashuvi
admin/settings.php        — narx va ombor sozlamalari
courier/balance.php       — kuryer balansi va daromad tarixi
profile.php               — profil va parol
assets/js/courier-track.js, track-order.js, admin-map.js
```

> Eslatma: jonli xarita uchun OpenStreetMap (Leaflet) ishlatiladi — API kalit talab qilmaydi.
