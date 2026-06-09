<?php
require_once __DIR__ . '/includes/functions.php';
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cart.php');
}
check_csrf();

$cart = cart();
if (!$cart) {
    redirect('/cart.php');
}

$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$note    = trim($_POST['note'] ?? '');
$lat     = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
$lng     = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;

if ($address === '') {
    redirect('/cart.php');
}

// Do'kon (olish nuqtasi) sozlamalardan
$pickupLat = (float)setting('store_lat', 41.311081);
$pickupLng = (float)setting('store_lng', 69.240562);

// Masofa va yetkazib berish haqi
$distance = ($lat !== null && $lng !== null)
    ? haversine_km($pickupLat, $pickupLng, $lat, $lng)
    : 0.0;
$fee = delivery_fee($distance);

$ids  = implode(',', array_map('intval', array_keys($cart)));
$rows = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();

$pdo = db();
$pdo->beginTransaction();
try {
    $goods = 0;
    foreach ($rows as $r) {
        $goods += (int)$cart[$r['id']] * (float)$r['price'];
    }
    $total = $goods + $fee;

    $stmt = $pdo->prepare(
        'INSERT INTO orders
            (customer_id, status, address, lat, lng, pickup_lat, pickup_lng,
             distance_km, delivery_fee, phone, note, total)
         VALUES (?, "new", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        current_user()['id'], $address, $lat, $lng, $pickupLat, $pickupLng,
        $distance, $fee, $phone, $note, $total,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, price, quantity)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) {
        $itemStmt->execute([$orderId, $r['id'], $r['name'], $r['price'], (int)$cart[$r['id']]]);
    }

    $pdo->commit();
    $_SESSION['cart'] = [];
    redirect('/orders.php?ok=' . $orderId);
} catch (Throwable $ex) {
    $pdo->rollBack();
    http_response_code(500);
    die('Buyurtma yaratishda xatolik: ' . e($ex->getMessage()));
}
