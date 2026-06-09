<?php
/**
 * Kuryer jonli lokatsiyasini yangilash.
 * Kuryer brauzeri har necha soniyada lat/lng yuboradi.
 * POST: lat, lng
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'courier') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_coords']);
    exit;
}

$stmt = db()->prepare('UPDATE users SET lat = ?, lng = ?, last_seen = NOW() WHERE id = ?');
$stmt->execute([$lat, $lng, $u['id']]);

echo json_encode(['ok' => true]);
