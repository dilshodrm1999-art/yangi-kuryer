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
$lat     = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng     = $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

if ($address === '') {
    $_SESSION['flash'] = 'Manzilni kiriting.';
    redirect('/cart.php');
}

$ids  = implode(',', array_map('intval', array_keys($cart)));
$rows = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();

$pdo = db();
$pdo->beginTransaction();
try {
    $total = 0;
    foreach ($rows as $r) {
        $total += (int)$cart[$r['id']] * (float)$r['price'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO orders (customer_id, status, address, lat, lng, phone, note, total)
         VALUES (?, "new", ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        current_user()['id'], $address, $lat, $lng, $phone, $note, $total,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, price, quantity)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) {
        $itemStmt->execute([
            $orderId, $r['id'], $r['name'], $r['price'], (int)$cart[$r['id']],
        ]);
    }

    $pdo->commit();
    $_SESSION['cart'] = [];
    redirect('/orders.php?ok=' . $orderId);
} catch (Throwable $ex) {
    $pdo->rollBack();
    http_response_code(500);
    die('Buyurtma yaratishda xatolik: ' . e($ex->getMessage()));
}
