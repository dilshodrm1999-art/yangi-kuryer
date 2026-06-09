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
        'on_way'    => '#8b5cf6',
        'delivered' => '#22c55e',
        'cancelled' => '#ef4444',
    ][$s] ?? '#6b7280';
}
