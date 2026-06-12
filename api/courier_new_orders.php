<?php
/**
 * Kuryer uchun: mavjud (tayinlanmagan, yangi) buyurtmalar soni va oxirgi ID.
 * Signal (tovush) bildirishnomasi uchun ishlatiladi.
 * Javob: { ok, count, latest_id, busy }
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'courier') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// Kuryer band bo'lsa (aktiv buyurtmasi bor) — yangi buyurtmalar ko'rsatilmaydi
$busyStmt = db()->prepare(
    "SELECT COUNT(*) FROM orders
     WHERE courier_id = ? AND status IN ('accepted','picked_up','on_way')"
);
$busyStmt->execute([$u['id']]);
$busy = (int)$busyStmt->fetchColumn() > 0;

if ($busy) {
    echo json_encode(['ok' => true, 'count' => 0, 'latest_id' => 0, 'busy' => true]);
    exit;
}

// Tayinlanmagan yangi buyurtmalar
$row = db()->query(
    "SELECT COUNT(*) AS cnt, COALESCE(MAX(id),0) AS latest
     FROM orders
     WHERE status = 'new' AND courier_id IS NULL"
)->fetch();

echo json_encode([
    'ok'        => true,
    'count'     => (int)$row['cnt'],
    'latest_id' => (int)$row['latest'],
    'busy'      => false,
]);
