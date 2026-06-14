<?php
/**
 * Admin uchun: barcha faol kuryerlarning jonli joylashuvi.
 * Faqat oxirgi 30 daqiqada lokatsiya yuborganlar.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$rows = db()->query(
    "SELECT u.id, u.name, u.phone, u.lat, u.lng, u.last_seen,
            (SELECT COUNT(*) FROM orders o
              WHERE o.courier_id = u.id AND o.status IN ('accepted','picked_up','on_way')) AS active_orders
     FROM users u
     WHERE u.role = 'courier' AND u.is_active = 1
       AND u.lat IS NOT NULL AND u.lng IS NOT NULL
       AND u.last_seen >= (NOW() - INTERVAL 30 MINUTE)"
)->fetchAll();

$couriers = array_map(fn($r) => [
    'id'            => (int)$r['id'],
    'short_id'      => 'K' . str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT),
    'name'          => $r['name'],
    'phone'         => $r['phone'],
    'lat'           => (float)$r['lat'],
    'lng'           => (float)$r['lng'],
    'last_seen'     => $r['last_seen'],
    'active_orders' => (int)$r['active_orders'],
], $rows);

echo json_encode(['ok' => true, 'couriers' => $couriers]);
