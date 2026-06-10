<?php
/**
 * Savatga AJAX orqali qo'shish (sahifani yangilamasdan).
 * POST: product_id, csrf
 * Javob: { ok, count }
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden', 'msg' => 'Avval mijoz sifatida tizimga kiring']);
    exit;
}

// CSRF tekshiruvi (token mavjud bo'lsa). Same-origin himoyasi: cookie SameSite=Lax.
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf', 'msg' => 'Sahifani yangilang (F5) va qayta urinib ko\'ring']);
    exit;
}

$pid = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));

// Mahsulot mavjudligini tekshirish
$stmt = db()->prepare('SELECT id, name FROM products WHERE id = ? AND is_available = 1');
$stmt->execute([$pid]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;

echo json_encode([
    'ok'    => true,
    'count' => array_sum($_SESSION['cart']),
    'name'  => $product['name'],
]);
