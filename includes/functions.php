<?php
/**
 * Umumiy yordamchi funksiyalar va sessiya / autentifikatsiya.
 */

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        default   => '/index.php',
    };
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

/** Masofa bo'yicha yetkazib berish haqini hisoblash */
function delivery_fee(float $distanceKm): float
{
    $perKm = (float)setting('price_per_km', 8000);
    $min   = (float)setting('min_fee', 0);
    $fee   = $distanceKm * $perKm;
    return round(max($fee, $min), -2); // 100 gacha yaxlitlash
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
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
         . 'stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
