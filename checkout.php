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

// Zona (shahar ichi / tashqarisi) va masofa
$zone = delivery_zone($lat, $lng);
$distance = ($lat !== null && $lng !== null)
    ? haversine_km($pickupLat, $pickupLng, $lat, $lng)
    : 0.0;
$fee = delivery_fee($distance, $zone);

$ids  = implode(',', array_map('intval', array_keys($cart)));
$rows = db()->query(
    "SELECT p.*, s.discount_percent AS store_discount, s.name AS store_name,
            s.open_time, s.close_time, s.work_days, s.is_active AS store_active
     FROM products p LEFT JOIN stores s ON s.id = p.store_id
     WHERE p.id IN ($ids)"
)->fetchAll();

// Do'kon ish vaqti tekshiruvi — yopiq do'kondan buyurtma qabul qilinmaydi
foreach ($rows as $r) {
    if (!empty($r['store_id'])) {
        $open = store_is_open([
            'is_active'  => $r['store_active'] ?? 1,
            'open_time'  => $r['open_time'] ?? null,
            'close_time' => $r['close_time'] ?? null,
            'work_days'  => $r['work_days'] ?? '1,2,3,4,5,6,7',
        ]);
        if (!$open) {
            redirect('/cart.php?closed=' . urlencode($r['store_name'] ?? ''));
        }
    }
}

$pdo = db();
$pdo->beginTransaction();
try {
    $goods = 0;
    $priceById = [];
    foreach ($rows as $r) {
        $unit = product_final_price($r); // chegirma qo'llangan narx
        $priceById[$r['id']] = ['name' => $r['name'], 'price' => $unit];
        $goods += (int)$cart[$r['id']] * $unit;
    }
    $total = $goods + $fee;

    $stmt = $pdo->prepare(
        'INSERT INTO orders
            (customer_id, status, address, lat, lng, pickup_lat, pickup_lng,
             distance_km, delivery_zone, delivery_fee, phone, note, total)
         VALUES (?, "new", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        current_user()['id'], $address, $lat, $lng, $pickupLat, $pickupLng,
        $distance, $zone, $fee, $phone, $note, $total,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, price, quantity)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($priceById as $pid => $info) {
        $itemStmt->execute([$orderId, $pid, $info['name'], $info['price'], (int)$cart[$pid]]);
    }

    $pdo->commit();
    $_SESSION['cart'] = [];
    redirect('/orders.php?ok=' . $orderId);
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('Order error: ' . $ex->getMessage());
    http_response_code(500);
    die('Buyurtma yaratishda xatolik. Iltimos qaytadan urinib ko\'ring.');
}
