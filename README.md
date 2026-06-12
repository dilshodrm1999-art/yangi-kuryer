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

---

## 🆕 v4 yangiliklari

### 🗺 Hudud va xarita
- **Viloyat / tuman tanlash** — admin O'zbekistonning istalgan viloyati yoki tumanini tanlaydi; xarita avtomatik o'sha hududning markaziga o'tadi (`config/regions.php` — 14 ta hudud, koordinatalari bilan).
- **Shahar chegarasini chizish** — admin xaritada shahar markazini chiziqlar (poligon) bilan belgilaydi.
- **Zonali narx** — manzil shahar chizig'i **ichida** bo'lsa "shahar ichi" 1 km narxi, **tashqarisida** bo'lsa "shahar tashqarisi" narxi qo'llanadi. Hisob-kitob nuqta-poligon (ray-casting) algoritmi orqali serverda va mijozda jonli amalga oshiriladi.
- Buyurtmada zona (`delivery_zone`) saqlanadi va admin panelda ko'rsatiladi.

### 🏪 Do'konlar / Fastfudlar
- Yangi **`admin/stores.php`** — do'kon qo'shish/tahrirlash/o'chirish.
- Har bir do'kon: nomi, rasm, manzil, telefon, **xaritada joylashuvi**, **ish vaqti** (ochilish/yopilish + ish kunlari), **chegirma %**, faollik holati.
- Do'kon **yopiq** bo'lsa, mijoz undan buyurtma bera olmaydi (server tomonda ham tekshiriladi).
- Mahsulotlar do'konga biriktiriladi; bosh sahifada do'kon karuseli (ochiq/yopiq holati va chegirma bilan).

### 🏷 Chegirmalar
- Mahsulot bo'yicha va/yoki do'kon bo'yicha chegirma. Mijozga eng katta chegirma qo'llanadi; eski narx chizilgan holda ko'rsatiladi.
- Chegirmali narx buyurtma yaratilganda serverda qayta hisoblanadi (mijoz narxni o'zgartira olmaydi).

### 🔐 Xavfsizlik (yuqori e'tibor)
- **Xavfsiz sessiya**: `HttpOnly`, `SameSite=Lax`, HTTPS ostida `Secure`, `use_strict_mode`, login/registratsiyada `session_regenerate_id` (session fixation himoyasi), 30 daqiqada ID yangilanishi.
- **Xavfsizlik sarlavhalari**: Content-Security-Policy, X-Frame-Options (DENY), X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (HTTPS ostida). `X-Powered-By` olib tashlanadi.
- **CSRF himoyasi** barcha formalarda; AJAX (savatga qo'shish, kuryer GPS) endpointlarida CSRF token + **same-origin** tekshiruvi (cross-site so'rovlar rad etiladi).
- **Brute-force himoyasi** — 5 marta xato login = 60 soniya blok.
- **Ma'lumot sizib chiqishining oldini olish** — baza/buyurtma xatolari foydalanuvchiga ko'rsatilmaydi, faqat log'ga yoziladi.
- **Apache himoyasi** (`.htaccess`) — katalog ro'yxati o'chirilgan; `config/` va `sql/` kataloglari va `.sql/.md/.env` kabi maxfiy fayllarga to'g'ridan-to'g'ri kirish taqiqlangan.
- Barcha SQL so'rovlari **PDO prepared statements** orqali (SQL injection himoyasi), chiqishlar `htmlspecialchars` bilan ekranlanadi (XSS himoyasi).

### Yangi / o'zgargan fayllar
```
config/regions.php          — O'zbekiston viloyat/tumanlari + koordinatalar
admin/stores.php            — do'konlar boshqaruvi (CRUD)
admin/settings.php          — hudud, shahar poligoni, zonali narxlar
assets/js/admin-settings.js — xarita: hudud markazi + poligon chizish
.htaccess, config/.htaccess, sql/.htaccess — xavfsizlik
```


---

## 🆕 v5 yangiliklari — Do'kon egasi paneli

### 🏪 Do'kon o'z oynasi va paneliga ega bo'ldi
Endi har bir do'kon/fastfud **alohida egasi (akkaunti)** orqali mustaqil boshqariladi.

- **Yangi rol: `store`** (do'kon egasi). Admin do'kon yaratganda egasini biriktiradi yoki to'g'ridan-to'g'ri yangi egasi akkauntini yaratadi (`admin/stores.php`).
- **Do'kon paneli** (`/store/`):
  - `store/index.php` — dashboard (mahsulot/bo'lim/buyurtma statistikasi, holat).
  - `store/products.php` — do'kon **o'z mahsulotlarini** qo'shadi/tahrirlaydi (faqat o'ziniki bilan ishlaydi).
  - `store/sections.php` — do'kon ichidagi **bo'limlar** (menyu kategoriyalari, masalan "Lavashlar", "Ichimliklar").
  - `store/profile.php` — **brending** (logotip, banner, asosiy rang), **ish vaqti + ish kunlari**, xaritada joylashuv.

### 🎨 Brending / bezatish
- Do'kon **logotip**, **banner (cover)** va **asosiy rang (theme)** belgilaydi — do'kon oynasi shu ranglarda bezatiladi.
- Jonli "oldindan ko'rish" (preview) profil sahifasida.

### 🗂 Bo'limlar (sections)
- Do'kon menyusini bo'limlarga ajratadi; mahsulot bo'limga biriktiriladi.
- Ommaviy oynada mahsulotlar bo'limlar bo'yicha guruhlanib ko'rsatiladi.

### 🪟 Ommaviy do'kon oynasi (`store_view.php`)
- Logotip, banner, ish vaqti (ochiq/yopiq), telefon, manzil va chegirma ko'rsatiladi.
- Mahsulotlar bo'limlar bo'yicha; bo'lim havolalari (anchor) bilan tez o'tish.
- Bosh sahifadagi do'kon kartalari shu oynaga olib boradi.

### 🔐 Xavfsizlik (do'kon paneli)
- Do'kon egasi **faqat o'z do'koni** ma'lumotlari bilan ishlaydi — barcha so'rovlar `owner_id` / `store_id` bo'yicha cheklangan (boshqa do'kon mahsulotini tahrirlab/o'chirib bo'lmaydi).
- Bo'lim biriktirishda ham bo'lim shu do'konga tegishliligi tekshiriladi.

### Yangi / o'zgargan fayllar (v5)
```
store/index.php, store/products.php, store/sections.php, store/profile.php — do'kon paneli
store_view.php              — ommaviy do'kon oynasi (brending bilan)
admin/stores.php            — egasi biriktirish + logo/banner/rang
```

### ⚙️ O'rnatish — BITTA umumiy SQL fayl
Endi alohida migratsiya fayllari yo'q. **`sql/schema.sql`** ning o'zi to'liq bazani (jadvallar + demo ma'lumot) o'rnatadi:

```bash
mysql -u root -p < sql/schema.sql
```
Yoki **phpMyAdmin → Import** orqali shu faylni yuklang.

> Diqqat: bu fayl mavjud `dostavka` bazasidagi jadvallarni o'chirib qaytadan yaratadi.

### Demo akkauntlar (parol: `12345`)
```
+998900000000  — Admin (super admin)
+998901111111  — Kuryer (Akmal)
+998903333333  — Mijoz (Dilnoza)
+998904444444  — Do'kon egasi (Oqtepa Lavash)
+998905555555  — Do'kon egasi (Evos Burger)
```


---

## 🆕 v6 yangiliklari — Yo'l haqi do'kon manzilidan hisoblanadi

Endi yetkazib berish narxi **umumiy ombordan emas, balki aniq do'kon manzilidan** mijoz manzcornergacha hisoblanadi va narx **oldindan** ko'rinadi.

### 🛣️ Mijoz uchun (savatcha)
- Savatda **"Olish nuqtasi: do'kon nomi + manzili"** ko'rsatiladi.
- Xaritada 🏪 do'kon nuqtasi va 🔵 do'kon→manzil yo'l chizig'i chiziladi.
- Mijoz manzilni belgilashi bilan: **masofa (km)**, **zona** (shahar ichi/tashqarisi), **1 km narxi** va **jami yo'l haqi** jonli ko'rinadi.

### 🛵 Kuryer uchun
- Buyurtma kartasida **olish nuqtasi (do'kon)** va **yetkazish manzili** alohida ko'rinadi.
- "Yo'l ko'rsatish" tugmasi endi **do'kon → mijoz** marshrutini ochadi.
- Daromad (yo'l haqi − komissiya) aniq ko'rsatiladi.

### Texnik o'zgarishlar
- `resolve_pickup()` — savatdagi mahsulot do'koniga qarab olish nuqtasini aniqlaydi (do'kon koordinatasi bo'lmasa umumiy omborga qaytadi).
- `orders` jadvaliga `pickup_name`, `pickup_address` ustunlari qo'shildi.
- Masofa har doim **olish nuqtasidan** (do'kon yoki ombor) hisoblanadi — mijozga ham, kuryerga ham bir xil narx ko'rinadi.


---

## 🆕 v7 yangiliklari — Kuryer paneli qayta ishlandi (mobil / APK webview)

Kuryer sahifasi soddalashtirildi va telefon ekraniga moslandi. APK ichida WebView orqali ochishga tayyor.

### 📱 Mobilga moslik
- Yagona ustunli, katta tugmali, kam yozuvli ixcham dizayn.
- Pastki navigatsiya (bottom-nav) telefon uchun qulay: **Buyurtma · Tarix · Hisobot · Balans**.
- `env(safe-area-inset-bottom)` — telefon pastki "notch"iga moslashadi.

### 🚚 Buyurtma sahifasi (`courier/index.php`)
- Yuqorida: salom + **bugungi natija** (yetkazilgan soni, daromad, balans) + **GPS holati**.
- Faqat **aktiv** va **yangi** buyurtmalar ko'rsatiladi (tarix alohida bo'limga ko'chdi).
- Har karta: do'kon → manzil marshruti, masofa, mahsulot soni, zona va **sof daromad**.
- Tugmalar: Qabul → Oldim → Yo'ldaman → Yetkazdim; tezkor **Qo'ng'iroq** va **Yo'l ko'rsatish**.
- Yangi buyurtma kelganda **signal (tovush + tebranish)** saqlanib qoldi.

### 🕘 Buyurtma tarixi (`courier/history.php`) — alohida bo'lim
- Sana bo'yicha guruhlangan (Bugun / Kecha / sana).
- Filtr: Hammasi · Yetkazilgan · Bekor qilingan.

### 📊 Statistika / hisobot (`courier/stats.php`)
- Davr kartalari: **Bugun · Bu hafta · Bu oy · Umumiy** daromad.
- **So'nggi 7 kun** daromadi ustunli grafikda.
- Ko'rsatkichlar: jami yetkazilgan, bosib o'tilgan masofa, o'rtacha daromad/buyurtma, balans.

### 💰 Balans (`courier/balance.php`)
- Joriy balans + bugun/bu oy; ixcham daromad tarixi (kartalar ko'rinishida).

### Texnik
- Yangi yordamchilar: `courier_stats()`, `courier_daily_series()`, `courier_earn()`, `load_order_items()`.
- Umumiy karta: `courier/_card.php` (barcha kuryer sahifalarida bir xil ko'rinish).
- Hech qanday tashqi grafik kutubxonasi yo'q — grafik sof CSS bilan (webview'da yengil).


---

## 🆕 v8 yangiliklari — Yo'l bo'yicha masofa + kuryer bitta zakas qoidasi

### 🚲 Haqiqiy yo'l masofasi (velosiped marshruti)
- Masofa endi **to'g'ri chiziq** bilan emas, **velosiped yo'l harakatiga ko'ra** (haqiqiy ko'chalar bo'yicha) hisoblanadi.
- OSRM ochiq xizmati (`routing.openstreetmap.de/routed-bike`) ishlatiladi — **API kalit talab qilmaydi**.
- Xaritada yo'l chizig'i ko'chalar bo'ylab egri chiziladi (to'g'ri chiziq emas).
- Mijoz manzilni belgilashi bilan yo'l masofasi va narx jonli yangilanadi.
- **Zaxira:** internet/xizmat ishlamasa, to'g'ri chiziq × 1.3 koeffitsient bilan taxminlanadi (xatolik bermaydi).
- Server tomonda ham xuddi shu yo'l masofasi saqlanadi (`route_distance_km()`), shuning uchun mijoz va kuryer bir xil narxni ko'radi.
- CSP'ga OSRM domeni qo'shildi.

### 🛵 Kuryer bir vaqtda bitta zakas
- Kuryerda aktiv buyurtma bo'lsa (qabul qilingan / olingan / yo'lda) — unga **yangi buyurtmalar ko'rsatilmaydi** va qabul qila olmaydi.
- Joriy buyurtmani **"Yetkazdim"** (yoki bekor) qilgach, yangi buyurtmalar avtomatik qaytadi (sahifa o'zi yangilanadi, signal qayta ishlaydi).
- Bu qoida ham serverda (`accept` da tekshiruv), ham API'da (`busy` flag), ham frontendda qo'llanadi — chetlab o'tib bo'lmaydi.


---

## 🆕 v9 — Haqiqiy yo'lda eng qisqa masofa

- Xarita endi bir nechta yo'l variantini so'rab (`alternatives=true`), **eng qisqa haqiqiy yo'l** masofasini tanlaydi.
- **Ikkita routing serveri**: biri ishlamasa, ikkinchisi (`router.project-osrm.org`) avtomatik sinaladi.
- Xaritada yo'l chizig'i ustida **masofa + taxminiy vaqt** yorlig'i (🚲 km · ~daq) ko'rsatiladi.
- Xarita do'kon va manzil ikkalasini ko'rsatadigan qilib avtomatik moslashadi (fit-bounds).
- Server (`route_distance_km`) va xarita bir xil mantiqdan foydalanadi — narx mos keladi.


---

## 🆕 v10 — Kuryer nazorati: bekor so'rovi, GPS majburiy, 20m yetkazish

### 🚫 Kuryer o'zi bekor qila olmaydi (admin tasdig'i kerak)
- Qabul qilingan buyurtmani kuryer **o'z xohishiga ko'ra bekor qila olmaydi**.
- "Bekor" tugmasi bosilganda **sabab so'raladi** va **adminga so'rov** boradi (`cancel_requested`).
- Admin buyurtmalar sahifasida so'rovni ko'radi: **Tasdiqlash** (buyurtma bekor bo'ladi) yoki **Rad etish** (davom etadi).
- Tasdiqlanmaguncha buyurtma aktiv qoladi.

### 📍 Geolokatsiya majburiy
- Kuryer GPS'i o'chiq (yoki so'nggi 2 daqiqada yangilanmagan) bo'lsa — **hech qanday amal bajara olmaydi** (qabul, "oldim", "yetkazdim").
- Server tomonda `courier_gps_fresh()` tekshiruvi + frontendda ogohlantirish banneri.

### ✅ Yetkazishni 20m aniqlikda tasdiqlash
- Kuryer mijoz manziliga **yetib bormasdan** "Yetkazdim" deya buyurtmani yopa **olmaydi**.
- Tasdiqlash uchun kuryerning joriy GPS koordinatasi mijoz belgilagan manzilga **20 metr ichida** bo'lishi shart.
- Tekshiruv ikki bosqichda: frontend (darhol ogohlantiradi) + server (`DELIVERY_RADIUS_M = 20`, qayta tekshiradi — chetlab o'tib bo'lmaydi).

### Texnik
- `orders` jadvaliga `cancel_requested`, `cancel_reason` ustunlari qo'shildi.
- Yangi: `courier_gps_fresh()`, `distance_meters()`, `assets/js/courier-actions.js`.
