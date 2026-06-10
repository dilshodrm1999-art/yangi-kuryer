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
        // Buyurtmani o'zlashtirish (faqat hali tayinlanmagan bo'lsa)
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

    // Status yangilash (faqat o'ziga tegishli buyurtma)
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
                // Yetkazilganda: admin komissiyasi + kuryer balansi
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

// ---- O'ziga tayinlangan buyurtmalar ----
$stmt = db()->prepare(
    'SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.courier_id = ?
     ORDER BY FIELD(o.status,"accepted","picked_up","on_way","new","delivered","cancelled"), o.created_at DESC'
);
$stmt->execute([$me['id']]);
$mine = $stmt->fetchAll();

$allOrders = array_merge($available, $mine);
$itemsByOrder = [];
if ($allOrders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $allOrders));
    foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll() as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$active = array_filter($mine, fn($o) => in_array($o['status'], ['accepted','picked_up','on_way']));
$done   = array_filter($mine, fn($o) => in_array($o['status'], ['delivered','cancelled']));

$todayEarn = db()->prepare(
    "SELECT COALESCE(SUM(delivery_fee - commission),0) FROM orders
     WHERE courier_id=? AND status='delivered' AND DATE(updated_at)=CURDATE()"
);
$todayEarn->execute([$me['id']]);
$todayEarn = $todayEarn->fetchColumn();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$pageTitle = 'Kuryer paneli';
require __DIR__ . '/../includes/header.php';

/** Buyurtma kartasi. $mode: 'available' yoki 'mine' */
function courier_card(array $o, array $items, string $mode): void { ?>
    <div class="card order">
        <div class="order-head">
            <strong>#<?= $o['id'] ?> · <?= e($o['customer_name']) ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        </div>
        <?php if (!empty($o['pickup_name'])): ?>
            <div class="order-line"><?= icon('store',16) ?><span>Olish: <strong><?= e($o['pickup_name']) ?></strong><?= $o['pickup_address'] ? ' · '.e($o['pickup_address']) : '' ?></span></div>
        <?php endif; ?>
        <div class="order-line"><?= icon('pin',16) ?><span>Manzil: <strong><?= e($o['address']) ?></strong></span></div>
        <?php if ($mode === 'mine'): ?>
            <div class="order-line"><?= icon('phone',16) ?><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></div>
        <?php endif; ?>
        <?php if ($o['note']): ?><div class="order-line"><?= icon('edit',15) ?><span><?= e($o['note']) ?></span></div><?php endif; ?>

        <ul class="order-items">
            <?php foreach ($items as $it): ?>
                <li><span><?= e($it['name']) ?></span><span>× <?= $it['quantity'] ?></span></li>
            <?php endforeach; ?>
        </ul>

        <div class="order-meta">
            <?php if ($o['distance_km'] > 0): ?><span class="tag dist"><?= icon('route',13) ?> <?= e($o['distance_km']) ?> km</span><?php endif; ?>
            <span class="tag <?= ($o['delivery_zone'] ?? 'in') === 'out' ? 'zone-out' : 'zone-in' ?>"><?= e(zone_label($o['delivery_zone'] ?? 'in')) ?></span>
            <span class="tag fee"><?= icon('wallet',13) ?> Daromad: <?= money((float)$o['delivery_fee'] - (float)($o['commission'] ?? 0)) ?></span>
        </div>

        <?php if ($mode === 'available'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <button class="btn primary block"><?= icon('check',16) ?> Qabul qilish</button>
            </form>
        <?php else: ?>
            <?php if ($o['lat'] && $o['lng']): ?>
                <a class="btn block" target="_blank"
                   href="https://www.google.com/maps/dir/?api=1<?= ($o['pickup_lat'] && $o['pickup_lng']) ? '&origin='.e($o['pickup_lat']).','.e($o['pickup_lng']) : '' ?>&destination=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>">
                   <?= icon('nav',16) ?> Yo'l ko'rsatish<?= ($o['pickup_lat'] && $o['pickup_lng']) ? ' (do\'kon → mijoz)' : '' ?>
                </a>
            <?php endif; ?>
            <div class="order-actions">
                <?php if ($o['status'] === 'accepted'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="picked_up">
                        <button class="btn primary sm"><?= icon('package',16) ?> Mahsulotni oldim</button>
                    </form>
                <?php elseif ($o['status'] === 'picked_up'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="on_way">
                        <button class="btn primary sm"><?= icon('truck',16) ?> Yo'lga chiqdim</button>
                    </form>
                <?php elseif ($o['status'] === 'on_way'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="delivered">
                        <button class="btn success sm"><?= icon('check',16) ?> Yetkazdim</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($o['status'], ['accepted','picked_up','on_way'])): ?>
                    <form method="post" data-confirm="Buyurtmani bekor qilasizmi?"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="cancelled">
                        <button class="btn ghost sm"><?= icon('x',16) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php }
?>
<div class="profile-head">
    <div class="avatar"><?= icon('truck', 28) ?></div>
    <div>
        <h1 class="page-title" style="margin:0">Salom, <?= e($me['name']) ?>!</h1>
        <p class="muted">Bugungi daromad: <strong style="color:var(--green)"><?= money($todayEarn) ?></strong></p>
    </div>
</div>

<?php if ($flash): ?><div class="alert info"><?= icon('check',16) ?><?= e($flash) ?></div><?php endif; ?>

<div class="gps-banner"><?= icon('nav',16) ?> <span id="gpsBadge" class="tag">GPS ulanmoqda...</span> <span class="muted small">Joylashuvingiz ko'rsatiladi</span></div>

<!-- Yangi buyurtma bildirishnomasi (signal) -->
<div id="newOrderAlert" class="new-order-alert" style="display:none">
    🔔 <strong>Yangi buyurtma keldi!</strong>
    <button class="btn sm" onclick="location.reload()">Ko'rish</button>
</div>

<h2 class="sub"><?= icon('package',18) ?> Yangi buyurtmalar <span class="count-pill" id="availCount"><?= count($available) ?></span></h2>
<?php if (!$available): ?><div class="card muted" style="text-align:center">Hozircha yangi buyurtma yo'q. Kutib turing 🔔</div><?php endif; ?>
<div class="grid orders">
    <?php foreach ($available as $o) courier_card($o, $itemsByOrder[$o['id']] ?? [], 'available'); ?>
</div>

<h2 class="sub"><?= icon('truck',18) ?> Mening aktiv buyurtmalarim (<?= count($active) ?>)</h2>
<?php if (!$active): ?><div class="card muted" style="text-align:center">Aktiv buyurtmalar yo'q.</div><?php endif; ?>
<div class="grid orders">
    <?php foreach ($active as $o) courier_card($o, $itemsByOrder[$o['id']] ?? [], 'mine'); ?>
</div>

<h2 class="sub"><?= icon('clock',18) ?> Tarix (<?= count($done) ?>)</h2>
<div class="grid orders">
    <?php foreach ($done as $o) courier_card($o, $itemsByOrder[$o['id']] ?? [], 'mine'); ?>
</div>

<script>window.__lastOrderId = <?= $available ? max(array_map(fn($o)=>(int)$o['id'],$available)) : 0 ?>;</script>
<script src="/assets/js/courier-track.js"></script>
<script src="/assets/js/courier-orders.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
