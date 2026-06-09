<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

// ---- Status yangilash (+ yetkazilganda balansga haq qo'shish) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $allowed = ['picked_up', 'on_way', 'delivered', 'cancelled'];

    if (in_array($status, $allowed, true)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Faqat o'ziga tayinlangan buyurtma
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND courier_id=? FOR UPDATE');
            $stmt->execute([$orderId, $me['id']]);
            $order = $stmt->fetch();

            if ($order) {
                $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $orderId]);

                // Yetkazilganda kuryer balansiga yetkazib berish haqini qo'shamiz
                if ($status === 'delivered' && !$order['paid_to_courier']) {
                    $pdo->prepare('UPDATE orders SET paid_to_courier=1 WHERE id=?')->execute([$orderId]);
                    $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id=?')
                        ->execute([$order['delivery_fee'], $me['id']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
        }
    }
    redirect('/courier/index.php');
}

$stmt = db()->prepare(
    'SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.courier_id = ?
     ORDER BY FIELD(o.status,"accepted","picked_up","on_way","new","delivered","cancelled"), o.created_at DESC'
);
$stmt->execute([$me['id']]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll() as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$active = array_filter($orders, fn($o) => in_array($o['status'], ['accepted','picked_up','on_way']));
$done   = array_filter($orders, fn($o) => in_array($o['status'], ['delivered','cancelled']));
$todayEarn = db()->prepare(
    "SELECT COALESCE(SUM(delivery_fee),0) FROM orders WHERE courier_id=? AND status='delivered' AND DATE(updated_at)=CURDATE()"
);
$todayEarn->execute([$me['id']]);
$todayEarn = $todayEarn->fetchColumn();

$pageTitle = 'Kuryer paneli';
require __DIR__ . '/../includes/header.php';

function courier_card(array $o, array $items): void { ?>
    <div class="card order">
        <div class="order-head">
            <strong>#<?= $o['id'] ?> · <?= e($o['customer_name']) ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        </div>
        <div class="order-line"><?= icon('pin',16) ?><span><strong><?= e($o['address']) ?></strong></span></div>
        <div class="order-line"><?= icon('phone',16) ?><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></div>
        <?php if ($o['note']): ?><div class="order-line"><?= icon('edit',15) ?><span><?= e($o['note']) ?></span></div><?php endif; ?>

        <ul class="order-items">
            <?php foreach ($items as $it): ?>
                <li><span><?= e($it['name']) ?></span><span>× <?= $it['quantity'] ?></span></li>
            <?php endforeach; ?>
        </ul>

        <div class="order-meta">
            <?php if ($o['distance_km'] > 0): ?><span class="tag dist"><?= icon('route',13) ?> <?= e($o['distance_km']) ?> km</span><?php endif; ?>
            <span class="tag fee"><?= icon('wallet',13) ?> Daromad: <?= money($o['delivery_fee']) ?></span>
        </div>

        <?php if ($o['lat'] && $o['lng']): ?>
            <a class="btn block" target="_blank"
               href="https://www.google.com/maps/dir/?api=1&destination=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>">
               <?= icon('nav',16) ?> Yo'l ko'rsatish (navigatsiya)
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

<div class="gps-banner"><?= icon('nav',16) ?> <span id="gpsBadge" class="tag">GPS ulanmoqda...</span> <span class="muted small">Joylashuvingiz admin va mijozga ko'rinadi</span></div>

<h2 class="sub"><?= icon('truck',18) ?> Aktiv buyurtmalar (<?= count($active) ?>)</h2>
<?php if (!$active): ?><div class="card muted" style="text-align:center">Aktiv buyurtmalar yo'q.</div><?php endif; ?>
<div class="grid orders">
    <?php foreach ($active as $o) courier_card($o, $itemsByOrder[$o['id']] ?? []); ?>
</div>

<h2 class="sub"><?= icon('clock',18) ?> Tarix (<?= count($done) ?>)</h2>
<div class="grid orders">
    <?php foreach ($done as $o) courier_card($o, $itemsByOrder[$o['id']] ?? []); ?>
</div>

<script src="/assets/js/courier-track.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
