<?php
/**
 * Kuryer uchun: mavjud (tayinlanmagan, yangi) buyurtmalar soni va oxirgi ID.
 * Signal (tovush) bildirishnomasi uchun ishlatiladi.
 * Javob: { ok, count, latest_id }
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'courier') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// Tayinlanmagan yangi buyurtmalar + shu kuryerga yangi tayinlanganlar
$row = db()->query(
    "SELECT COUNT(*) AS cnt, COALESCE(MAX(id),0) AS latest
     FROM orders
     WHERE status = 'new' AND courier_id IS NULL"
)->fetch();

echo json_encode([
    'ok'        => true,
    'count'     => (int)$row['cnt'],
    'latest_id' => (int)$row['latest'],
]);
