<?php
/**
 * Umumiy yordamchi funksiyalar va sessiya / autentifikatsiya.
 */

require_once __DIR__ . '/../config/db.php';

/* ============================================================
 *  XAVFSIZLIK (Security) — sarlavhalar va sessiya himoyasi
 * ============================================================ */

/**
 * Xavfsizlik sarlavhalari — XSS, clickjacking, MIME-sniffing va
 * boshqa hujumlardan himoya. Har bir sahifada (CLI'dan tashqari) o'rnatiladi.
 */
function security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    // MIME turini majburlamaslik
    header('X-Content-Type-Options: nosniff');
    // Iframe ichida ochilishni taqiqlash (clickjacking)
    header('X-Frame-Options: DENY');
    // Referrer siyosati
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Brauzer imkoniyatlarini cheklash
    header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    // Eski brauzerlar uchun XSS filtri
    header('X-XSS-Protection: 1; mode=block');
    // Server signaturasini yashirish
    header_remove('X-Powered-By');

    // Content-Security-Policy: faqat ishonchli manbalar (Leaflet/OSM + o'zimiz)
    $csp = "default-src 'self'; "
         . "img-src 'self' data: https:; "
         . "style-src 'self' 'unsafe-inline' https://unpkg.com; "
         . "script-src 'self' 'unsafe-inline' https://unpkg.com; "
         . "connect-src 'self' https://nominatim.openstreetmap.org https://*.tile.openstreetmap.org https://routing.openstreetmap.de; "
         . "font-src 'self' data:; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self'; "
         . "frame-ancestors 'none'";
    header('Content-Security-Policy: ' . $csp);

    // HTTPS ostida ishlayotgan bo'lsa — HSTS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Xavfsiz sessiyani boshlash:
 *  - HttpOnly (JS orqali cookie o'qib bo'lmaydi)
 *  - SameSite=Lax (CSRF himoyasiga yordam)
 *  - HTTPS ostida Secure
 *  - vaqti-vaqti bilan session ID yangilanadi (session fixation himoyasi)
 */
function secure_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (function_exists('ini_set')) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
    }
    session_start();

    // Har 30 daqiqada session ID ni yangilash (fixation himoyasi)
    if (empty($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif (time() - $_SESSION['__created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}

security_headers();
secure_session_start();

/* ---------- Xavfsizlik / chiqarish ---------- */

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Narxni chiroyli formatda chiqarish: 28000 -> "28 000 so'm" */
function money($v): string
{
    return number_format((float)$v, 0, '.', ' ') . " so'm";
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Sessiya / foydalanuvchi ---------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

/** Faqat ko'rsatilgan rol(lar)ga ruxsat */
function require_role(string ...$roles): void
{
    require_login();
    $u = current_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('Ruxsat yo\'q (403).');
    }
}

/** Login bo'lgandan keyin rolga qarab yo'naltirish */
function role_home(string $role): string
{
    return match ($role) {
        'admin'   => '/admin/index.php',
        'courier' => '/courier/index.php',
        'store'   => '/store/index.php',
        default   => '/index.php',
    };
}

/* ---------- Do'kon egasi (store owner) ---------- */

/** Joriy foydalanuvchining do'koni (role='store' uchun). Topilmasa null. */
function current_store(): ?array
{
    $u = current_user();
    if (!$u || $u['role'] !== 'store') {
        return null;
    }
    static $store = null;
    if ($store === null) {
        $stmt = db()->prepare('SELECT * FROM stores WHERE owner_id = ? LIMIT 1');
        $stmt->execute([$u['id']]);
        $store = $stmt->fetch() ?: null;
    }
    return $store;
}

/**
 * Do'kon egasi panellari uchun himoya. Do'kon biriktirilmagan bo'lsa
 * ogohlantirish sahifasiga yo'naltiradi.
 */
function require_store_owner(): array
{
    require_role('store');
    $store = current_store();
    if (!$store) {
        http_response_code(403);
        die('Sizga hali do\'kon biriktirilmagan. Iltimos admin bilan bog\'laning.');
    }
    return $store;
}

/* ---------- CSRF himoyasi ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Sessiya muddati tugadi. Sahifani yangilang.');
    }
}

/* ---------- Login: sessiya yangilash + urinishlarni cheklash ---------- */

/**
 * Foydalanuvchini xavfsiz tarzda tizimga kiritish.
 * Session fixation hujumiga qarshi session ID yangilanadi.
 */
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['__created'] = time();
    unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
}

/**
 * Login urinishlarini cheklash (brute-force himoyasi).
 * Bloklangan bo'lsa qolgan soniyani qaytaradi, aks holda 0.
 */
function login_lock_seconds(): int
{
    $until = (int)($_SESSION['login_lock_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}

/** Muvaffaqiyatsiz urinishni qayd etish (5 marta = 60s blok) */
function login_register_failure(): void
{
    $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_lock_until'] = time() + 60;
        $_SESSION['login_attempts']   = 0;
    }
}

/**
 * API uchun bir xil manba (same-origin) tekshiruvi.
 * Cross-site so'rovlarni rad etadi (CSRF/abuse himoyasi).
 * JSON javob qaytaruvchi POST endpointlarda chaqiriladi.
 */
function api_require_same_origin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $ref    = $_SERVER['HTTP_REFERER'] ?? '';

    $ok = false;
    if ($origin !== '') {
        $ok = (parse_url($origin, PHP_URL_HOST) === $host);
    } elseif ($ref !== '') {
        $ok = (parse_url($ref, PHP_URL_HOST) === $host);
    } else {
        // Origin/Referer yo'q — sodda fetch; CSRF tokenga tayanamiz
        $ok = true;
    }
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'cross_origin']);
        exit;
    }
}

/* ---------- Savatcha (sessiyada) ---------- */

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart());
}

function role_label(string $r): string
{
    return [
        'admin'    => 'Admin',
        'courier'  => 'Kuryer',
        'customer' => 'Mijoz',
        'store'    => 'Do\'kon',
    ][$r] ?? $r;
}

/* ---------- Buyurtma statuslari ---------- */

function status_label(string $s): string
{
    return [
        'new'       => 'Yangi',
        'accepted'  => 'Qabul qilindi',
        'picked_up' => 'Mahsulot olindi',
        'on_way'    => "Yo'lda",
        'delivered' => 'Yetkazildi',
        'cancelled' => 'Bekor qilindi',
    ][$s] ?? $s;
}

function status_color(string $s): string
{
    return [
        'new'       => '#f59e0b',
        'accepted'  => '#3b82f6',
        'picked_up' => '#0ea5e9',
        'on_way'    => '#8b5cf6',
        'delivered' => '#22c55e',
        'cancelled' => '#ef4444',
    ][$s] ?? '#6b7280';
}

/* ---------- Sozlamalar (settings) ---------- */

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, $default = null)
{
    return settings()[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
    );
    $stmt->execute([$key, $value]);
}

/* ---------- Masofa va yetkazib berish haqi ---------- */

/** Ikki nuqta orasidagi masofa (km) - Haversine formulasi */
function haversine_km($lat1, $lng1, $lat2, $lng2): float
{
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return 0.0;
    }
    $R = 6371; // Yer radiusi, km
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLng = deg2rad((float)$lng2 - (float)$lng1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($R * $c, 2);
}

/**
 * Velosiped (bike) yo'l harakatiga ko'ra haqiqiy yo'l masofasi (km).
 * OSRM ochiq xizmatidan foydalanadi (kalit talab qilmaydi).
 * Server xato bersa yoki internet bo'lmasa — Haversine (to'g'ri chiziq) ga qaytadi.
 *
 * Qaytaradi: ['km' => float, 'source' => 'route'|'straight']
 */
function route_distance_km($lat1, $lng1, $lat2, $lng2): array
{
    $straight = haversine_km($lat1, $lng1, $lat2, $lng2);
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return ['km' => 0.0, 'source' => 'straight'];
    }

    // OSRM velosiped profili: lon,lat;lon,lat
    $url = sprintf(
        'https://routing.openstreetmap.de/routed-bike/route/v1/driving/%F,%F;%F,%F?overview=false',
        (float)$lng1, (float)$lat1, (float)$lng2, (float)$lat2
    );

    $json = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 4, 'header' => "User-Agent: DostavkaApp/1.0\r\n"],
    ]));

    if ($json !== false) {
        $data = json_decode($json, true);
        if (isset($data['routes'][0]['distance'])) {
            $km = round((float)$data['routes'][0]['distance'] / 1000, 2);
            if ($km > 0) {
                return ['km' => $km, 'source' => 'route'];
            }
        }
    }
    // Yo'l xizmati ishlamasa — to'g'ri chiziq masofasiga 1.3 koeffitsient
    // (real yo'l odatda to'g'ri chiziqdan ~30% uzun bo'ladi)
    return ['km' => round($straight * 1.3, 2), 'source' => 'straight'];
}

/**
 * Nuqta poligon (shahar markazi chizig'i) ichida yoki tashqarisida ekanligini aniqlash.
 * Ray-casting algoritmi. $polygon = [[lat,lng], [lat,lng], ...]
 * Bo'sh yoki uchburchakdan kam nuqta bo'lsa — har doim "shahar ichi" (true) deb hisoblaymiz.
 */
function point_in_polygon(?float $lat, ?float $lng, array $polygon): bool
{
    if ($lat === null || $lng === null) {
        return true; // koordinata yo'q — default shahar ichi
    }
    $n = count($polygon);
    if ($n < 3) {
        return true; // poligon belgilanmagan — hammasi shahar ichi
    }
    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $yi = (float)($polygon[$i][0] ?? 0); // lat
        $xi = (float)($polygon[$i][1] ?? 0); // lng
        $yj = (float)($polygon[$j][0] ?? 0);
        $xj = (float)($polygon[$j][1] ?? 0);

        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
        if ($intersect) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/** Sozlamalardagi shahar poligonini massiv ko'rinishida olish */
function city_polygon(): array
{
    $raw = setting('city_polygon', '[]');
    $arr = json_decode((string)$raw, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Manzil koordinatasiga qarab zona aniqlash: 'in' (shahar ichi) yoki 'out' (tashqarisi).
 */
function delivery_zone(?float $lat, ?float $lng): string
{
    return point_in_polygon($lat, $lng, city_polygon()) ? 'in' : 'out';
}

/**
 * Masofa va zonaga (shahar ichi / tashqarisi) qarab yetkazib berish haqi.
 * - shahar ichi  -> price_in_city
 * - shahar tashqari -> price_out_city
 * Eski 'price_per_km' bilan ham mos: agar in/out belgilanmagan bo'lsa, undan foydalanadi.
 */
function delivery_fee(float $distanceKm, string $zone = 'in'): float
{
    $fallback = (float)setting('price_per_km', 8000);
    $perKm = $zone === 'out'
        ? (float)setting('price_out_city', $fallback)
        : (float)setting('price_in_city', $fallback);
    if ($perKm <= 0) {
        $perKm = $fallback;
    }
    $min = (float)setting('min_fee', 0);
    $fee = $distanceKm * $perKm;
    return round(max($fee, $min), -2); // 100 gacha yaxlitlash
}

/**
 * Savatdagi mahsulotlar bo'yicha "olish nuqtasi" (pickup) ni aniqlash.
 * Mahsulotlar do'konga bog'langan bo'lsa — o'sha do'kon manzili/koordinatasi.
 * Aks holda sozlamalardagi umumiy ombor ishlatiladi.
 *
 * $productIds - savatdagi mahsulot id lari.
 * Qaytaradi: ['lat'=>?, 'lng'=>?, 'name'=>?, 'address'=>?]
 */
function resolve_pickup(array $productIds): array
{
    $fallback = [
        'lat'     => setting('store_lat', null) !== null ? (float)setting('store_lat') : null,
        'lng'     => setting('store_lng', null) !== null ? (float)setting('store_lng') : null,
        'name'    => setting('store_name', 'Ombor'),
        'address' => setting('store_name', 'Ombor'),
    ];
    $ids = array_values(array_filter(array_map('intval', $productIds)));
    if (!$ids) {
        return $fallback;
    }
    $in = implode(',', $ids);
    // Savatdagi birinchi (koordinatasi bor) do'konni olish nuqtasi sifatida olamiz
    $row = db()->query(
        "SELECT s.name, s.address, s.lat, s.lng
         FROM products p JOIN stores s ON s.id = p.store_id
         WHERE p.id IN ($in) AND s.lat IS NOT NULL AND s.lng IS NOT NULL
         ORDER BY p.id LIMIT 1"
    )->fetch();

    if ($row) {
        return [
            'lat'     => (float)$row['lat'],
            'lng'     => (float)$row['lng'],
            'name'    => $row['name'],
            'address' => $row['address'] ?: $row['name'],
        ];
    }
    return $fallback;
}

/** Mahsulot uchun yakuniy chegirma foizi (mahsulot yoki do'kon — kattasi) */
function product_discount(array $product): float
{
    return max(
        (float)($product['discount_percent'] ?? 0),
        (float)($product['store_discount'] ?? 0)
    );
}

/** Mahsulotning chegirmadan keyingi yakuniy narxi (do'kon chegirmasi bilan) */
function product_final_price(array $product): float
{
    return discounted_price($product['price'] ?? 0, product_discount($product));
}

/** Zona nomi: 'in' -> shahar ichi, 'out' -> tashqari */
function zone_label(string $zone): string
{
    return $zone === 'out' ? 'Shahar tashqarisi' : 'Shahar ichi';
}

/* ---------- Do'konlar va chegirmalar ---------- */

/** Chegirmadan keyingi narx (mahsulot uchun) */
function discounted_price($price, $discountPercent): float
{
    $price = (float)$price;
    $pct   = max(0, min(100, (float)$discountPercent));
    return round($price * (1 - $pct / 100), -2);
}

/**
 * Do'kon hozir ish vaqtida ochiqligini tekshirish.
 * $store - stores jadvalidan satr (open_time, close_time, work_days, is_active).
 */
function store_is_open(array $store, ?int $ts = null): bool
{
    if (empty($store['is_active'])) {
        return false;
    }
    $ts   = $ts ?? time();
    $dow  = (int)date('N', $ts); // 1=Dushanba ... 7=Yakshanba
    $days = array_filter(array_map('intval', explode(',', (string)($store['work_days'] ?? '1,2,3,4,5,6,7'))));
    if ($days && !in_array($dow, $days, true)) {
        return false;
    }
    $open  = $store['open_time']  ?? null;
    $close = $store['close_time'] ?? null;
    if (!$open || !$close) {
        return true; // vaqt belgilanmagan — doim ochiq
    }
    $now = date('H:i:s', $ts);
    // Yarim tundan oshadigan ish vaqti (masalan 18:00 - 02:00)
    if ($close > $open) {
        return $now >= $open && $now <= $close;
    }
    return $now >= $open || $now <= $close;
}

/** Ish vaqtini chiroyli ko'rsatish: "09:00 - 23:00" */
function store_hours_label(array $store): string
{
    $o = substr((string)($store['open_time']  ?? ''), 0, 5);
    $c = substr((string)($store['close_time'] ?? ''), 0, 5);
    if ($o === '' || $c === '') {
        return '24 soat';
    }
    return $o . ' - ' . $c;
}

/**
 * Buyurtma yetkazilganda hisob-kitob (idempotent).
 *  - admin komissiyasini hisoblaydi (commission_percent)
 *  - kuryer balansiga (yetkazish haqi - komissiya) qo'shadi
 *  - paid_to_courier flag bilan takror to'lashdan saqlaydi
 * $pdo ochiq tranzaksiya ichida chaqirilishi kerak.
 */
function settle_delivery(PDO $pdo, array $order): void
{
    if (!empty($order['paid_to_courier'])) {
        return; // allaqachon hisoblangan
    }
    $pct        = (float)setting('commission_percent', 0);
    $fee        = (float)$order['delivery_fee'];
    $commission = round($fee * $pct / 100, -2);          // admin ulushi
    $courierEarn = max(0, $fee - $commission);            // kuryer ulushi

    $pdo->prepare('UPDATE orders SET paid_to_courier = 1, commission = ? WHERE id = ?')
        ->execute([$commission, $order['id']]);

    if (!empty($order['courier_id'])) {
        $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
            ->execute([$courierEarn, $order['courier_id']]);
    }
}

/* ---------- Ikonkalar (inline SVG, zamonaviy) ---------- */

function icon(string $name, int $size = 22): string
{
    $paths = [
        'home'     => '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h14V10"/>',
        'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.4 12.5a2 2 0 0 0 2 1.5h7.7a2 2 0 0 0 2-1.6L22 7H6"/>',
        'package'  => '<path d="m7.5 4.3 9 5.2M3 7l9 5 9-5-9-5-9 5Z"/><path d="M3 7v10l9 5 9-5V7"/><path d="M12 12v10"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'pin'      => '<path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'truck'    => '<path d="M1 5h14v11H1z"/><path d="M15 8h4l3 3v5h-7"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 15H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 5V5a2 2 0 1 1 4 0v.1A1.6 1.6 0 0 0 17 6.6l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 21 12.6"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'minus'    => '<path d="M5 12h14"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'check'    => '<path d="m20 6-11 11-5-5"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'wallet'   => '<path d="M3 7a2 2 0 0 1 2-2h14v4"/><path d="M3 7v10a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-3"/><path d="M21 12h-5a2 2 0 1 0 0 4h5z"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'menu'     => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'nav'      => '<path d="m3 11 19-9-9 19-2-8-8-2Z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'star'     => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.5 6.8 19.2l1-5.8-4.3-4.1 5.9-.9Z"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'dashboard'=> '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/>',
        'route'    => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7a4 4 0 0 0 0-8H9a4 4 0 0 1 0-8h7"/>',
        'store'    => '<path d="M3 9 4.5 4h15L21 9"/><path d="M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0Z"/><path d="M5 13v7h14v-7"/><path d="M9 20v-4h6v4"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'layers'   => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
        'palette'  => '<path d="M12 3a9 9 0 1 0 0 18 2 2 0 0 0 2-2 2 2 0 0 1 2-2h1a4 4 0 0 0 4-4 9 9 0 0 0-9-8Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="10.5" r="1"/>',
        'chart'    => '<path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 8v4l3 2"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
        'trophy'   => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/>',
        'flame'    => '<path d="M12 2s4 4 4 9a4 4 0 0 1-8 0c0-1 .5-2 .5-2S6 11 6 14a6 6 0 0 0 12 0c0-5-6-12-6-12Z"/>',
        'phone-call'=> '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
         . 'stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/* ============================================================
 *  KURYER yordamchilari (panel, tarix, hisobot uchun umumiy)
 * ============================================================ */

/**
 * Kuryer uchun buyurtmalar ro'yxati elementlarini (order_items) bittada olish.
 * $orders - orders satrlari; qaytaradi: [order_id => [items...]]
 */
function load_order_items(array $orders): array
{
    $byOrder = [];
    $ids = array_values(array_filter(array_map(fn($o) => (int)$o['id'], $orders)));
    if (!$ids) {
        return $byOrder;
    }
    $in = implode(',', $ids);
    foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($in)")->fetchAll() as $r) {
        $byOrder[$r['order_id']][] = $r;
    }
    return $byOrder;
}

/** Kuryerning bitta yetkazilgan buyurtmadan sof daromadi (komissiyasiz) */
function courier_earn(array $order): float
{
    return max(0, (float)$order['delivery_fee'] - (float)($order['commission'] ?? 0));
}

/**
 * Kuryer statistikasi (umumiy + bugun + hafta + oy).
 * Qaytaradi: assoc massiv (delivered, today_*, week_*, month_*, total_*, avg_*).
 */
function courier_stats(int $courierId): array
{
    $stmt = db()->prepare(
        "SELECT
            COUNT(*) AS delivered,
            COALESCE(SUM(delivery_fee - commission),0) AS total_earn,
            COALESCE(SUM(distance_km),0) AS total_km,
            COALESCE(SUM(CASE WHEN DATE(updated_at)=CURDATE() THEN 1 ELSE 0 END),0) AS today_cnt,
            COALESCE(SUM(CASE WHEN DATE(updated_at)=CURDATE() THEN delivery_fee - commission ELSE 0 END),0) AS today_earn,
            COALESCE(SUM(CASE WHEN YEARWEEK(updated_at,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END),0) AS week_cnt,
            COALESCE(SUM(CASE WHEN YEARWEEK(updated_at,1)=YEARWEEK(CURDATE(),1) THEN delivery_fee - commission ELSE 0 END),0) AS week_earn,
            COALESCE(SUM(CASE WHEN YEAR(updated_at)=YEAR(CURDATE()) AND MONTH(updated_at)=MONTH(CURDATE()) THEN 1 ELSE 0 END),0) AS month_cnt,
            COALESCE(SUM(CASE WHEN YEAR(updated_at)=YEAR(CURDATE()) AND MONTH(updated_at)=MONTH(CURDATE()) THEN delivery_fee - commission ELSE 0 END),0) AS month_earn
         FROM orders WHERE courier_id=? AND status='delivered'"
    );
    $stmt->execute([$courierId]);
    $s = $stmt->fetch() ?: [];
    $s['avg_earn'] = ((int)($s['delivered'] ?? 0) > 0)
        ? (float)$s['total_earn'] / (int)$s['delivered'] : 0;
    return $s;
}

/**
 * Oxirgi 7 kun bo'yicha kunlik daromad va buyurtmalar (grafik uchun).
 * Qaytaradi: [['label'=>'Du','date'=>'2026-06-08','count'=>3,'earn'=>45000], ...]
 */
function courier_daily_series(int $courierId, int $days = 7): array
{
    $stmt = db()->prepare(
        "SELECT DATE(updated_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(delivery_fee - commission),0) AS earn
         FROM orders
         WHERE courier_id=? AND status='delivered' AND updated_at >= (CURDATE() - INTERVAL ? DAY)
         GROUP BY DATE(updated_at)"
    );
    $stmt->execute([$courierId, $days - 1]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[$r['d']] = ['count' => (int)$r['cnt'], 'earn' => (float)$r['earn']];
    }
    $dow = ['Yak','Du','Se','Cho','Pa','Ju','Sha']; // 0=yak ... 6=sha (date('w'))
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $ts = strtotime("-$i day");
        $key = date('Y-m-d', $ts);
        $out[] = [
            'label' => $dow[(int)date('w', $ts)],
            'date'  => $key,
            'count' => $map[$key]['count'] ?? 0,
            'earn'  => $map[$key]['earn'] ?? 0,
        ];
    }
    return $out;
}
