<?php
/**
 * Buyurtma bo'yicha kuryer joylashuvini olish (mijoz yoki admin).
 * GET: order_id
 * Javob: kuryer lokatsiyasi, manzil nuqtasi, status.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT o.id, o.status, o.lat, o.lng, o.address, o.customer_id,
            k.id AS courier_id, k.name AS courier_name, k.phone AS courier_phone,
            k.lat AS courier_lat, k.lng AS courier_lng, k.last_seen
     FROM orders o
     LEFT JOIN users k ON k.id = o.courier_id
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$orderId]);
$o = $stmt->fetch();

if (!$o) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

// Faqat egasi (mijoz), tayinlangan kuryer yoki admin ko'ra oladi
$allowed = $u['role'] === 'admin'
        || (int)$o['customer_id'] === (int)$u['id']
        || (int)$o['courier_id']  === (int)$u['id'];
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'status'   => $o['status'],
    'status_label' => status_label($o['status']),
    'dest'     => ($o['lat'] && $o['lng']) ? ['lat' => (float)$o['lat'], 'lng' => (float)$o['lng']] : null,
    'address'  => $o['address'],
    'courier'  => $o['courier_id'] ? [
        'name'      => $o['courier_name'],
        'phone'     => $o['courier_phone'],
        'lat'       => $o['courier_lat'] !== null ? (float)$o['courier_lat'] : null,
        'lng'       => $o['courier_lng'] !== null ? (float)$o['courier_lng'] : null,
        'last_seen' => $o['last_seen'],
    ] : null,
]);
