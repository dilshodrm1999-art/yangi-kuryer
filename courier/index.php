<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

// ---- Amallar (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $pdo = db();

    if ($action === 'accept') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET courier_id = ?, status = 'accepted'
             WHERE id = ? AND courier_id IS NULL AND status = 'new'"
        );
        $stmt->execute([$me['id'], $orderId]);
        $_SESSION['flash'] = $stmt->rowCount()
            ? 'Buyurtma qabul qilindi.'
            : 'Kechirasiz, bu buyurtmani boshqa kuryer oldi.';
        redirect('/courier/index.php');
    }

    $status  = $_POST['status'] ?? '';
    $allowed = ['picked_up', 'on_way', 'delivered', 'cancelled'];
    if (in_array($status, $allowed, true)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND courier_id=? FOR UPDATE');
            $stmt->execute([$orderId, $me['id']]);
            $order = $stmt->fetch();
            if ($order) {
                $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $orderId]);
                if ($status === 'delivered') {
                    settle_delivery($pdo, $order);
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
        }
    }
    redirect('/courier/index.php');
}

// ---- Mavjud (tayinlanmagan) yangi buyurtmalar ----
$available = db()->query(
    "SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.status = 'new' AND o.courier_id IS NULL
     ORDER BY o.created_at ASC"
)->fetchAll();

// ---- Faqat AKTIV buyurtmalar (tarix alohida sahifada) ----
$stmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.courier_id = ? AND o.status IN ('accepted','picked_up','on_way')
     ORDER BY FIELD(o.status,'on_way','picked_up','accepted'), o.created_at DESC"
);
$stmt->execute([$me['id']]);
$active = $stmt->fetchAll();

$itemsByOrder = load_order_items(array_merge($available, $active));

// Bugungi qisqa statistika (sarlavha uchun)
$today = db()->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(delivery_fee - commission),0) AS earn
     FROM orders WHERE courier_id=? AND status='delivered' AND DATE(updated_at)=CURDATE()"
);
$today->execute([$me['id']]);
$t = $today->fetch();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$pageTitle = 'Kuryer';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_card.php';
?>
<!-- Yuqori panel: salom + bugungi natija + GPS -->
<section class="cr-hero">
    <div class="cr-hero-row">
        <div class="cr-ava"><?= icon('truck', 24) ?></div>
        <div class="cr-hi">
            <span class="muted small">Assalomu alaykum,</span>
            <strong><?= e($me['name']) ?></strong>
        </div>
        <span id="gpsBadge" class="gps-chip off">GPS...</span>
    </div>
    <div class="cr-hero-stats">
        <div><span class="v"><?= (int)$t['cnt'] ?></span><span class="l">Bugun yetkazildi</span></div>
        <div><span class="v"><?= money($t['earn']) ?></span><span class="l">Bugungi daromad</span></div>
        <div><span class="v"><?= money($me['balance']) ?></span><span class="l">Balans</span></div>
    </div>
</section>

<?php if ($flash): ?><div class="alert info"><?= icon('check',16) ?> <?= e($flash) ?></div><?php endif; ?>

<!-- Yangi buyurtma signali -->
<div id="newOrderAlert" class="new-order-alert" style="display:none">
    🔔 <strong>Yangi buyurtma!</strong>
    <button class="btn sm" onclick="location.reload()">Yangilash</button>
</div>

<!-- Aktiv buyurtmalar -->
<div class="cr-section-head">
    <h2><?= icon('truck',18) ?> Aktiv buyurtmalar</h2>
    <span class="count-pill"><?= count($active) ?></span>
</div>
<?php if (!$active): ?>
    <div class="empty-box"><?= icon('truck',30) ?><p>Aktiv buyurtmangiz yo'q.</p></div>
<?php else: ?>
    <div class="cr-list">
        <?php foreach ($active as $o) courier_card($o, $itemsByOrder[$o['id']] ?? [], 'active'); ?>
    </div>
<?php endif; ?>

<!-- Yangi (bo'sh) buyurtmalar -->
<div class="cr-section-head">
    <h2><?= icon('package',18) ?> Yangi buyurtmalar</h2>
    <span class="count-pill" id="availCount"><?= count($available) ?></span>
</div>
<?php if (!$available): ?>
    <div class="empty-box"><?= icon('package',30) ?><p>Hozircha yangi buyurtma yo'q. Kutib turing 🔔</p></div>
<?php else: ?>
    <div class="cr-list">
        <?php foreach ($available as $o) courier_card($o, $itemsByOrder[$o['id']] ?? [], 'available'); ?>
    </div>
<?php endif; ?>

<script>window.__lastOrderId = <?= $available ? max(array_map(fn($o)=>(int)$o['id'],$available)) : 0 ?>;</script>
<script src="/assets/js/courier-track.js"></script>
<script src="/assets/js/courier-orders.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
