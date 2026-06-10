<?php
require_once __DIR__ . '/includes/functions.php';
require_role('customer');

$uid = current_user()['id'];
$stmt = db()->prepare(
    'SELECT o.*, u.name AS courier_name, u.phone AS courier_phone
     FROM orders o
     LEFT JOIN users u ON u.id = o.courier_id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll() as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$flow = ['new','accepted','picked_up','on_way','delivered'];
$hasActive = false;

$pageTitle = 'Buyurtmalarim';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Buyurtmalarim 📦</h1>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert success"><?= icon('check',16) ?> Buyurtmangiz qabul qilindi! Raqami: #<?= (int)$_GET['ok'] ?></div>
<?php endif; ?>

<?php if (!$orders): ?>
    <div class="card" style="text-align:center;color:var(--muted)">Hali buyurtmalaringiz yo'q.</div>
<?php endif; ?>

<div class="grid orders">
<?php foreach ($orders as $o):
    $active = in_array($o['status'], ['accepted','picked_up','on_way']);
    if ($active) $hasActive = true;
    $curIdx = array_search($o['status'], $flow, true);
?>
    <div class="card order">
        <div class="order-head">
            <strong>Buyurtma #<?= $o['id'] ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        </div>

        <?php if ($o['status'] !== 'cancelled'): ?>
        <div class="steps">
            <?php foreach (['new'=>'Yangi','accepted'=>'Qabul','picked_up'=>'Olindi','on_way'=>"Yo'lda",'delivered'=>'Yetdi'] as $st => $lbl):
                $i = array_search($st, $flow, true);
                $cls = $i < $curIdx ? 'done' : ($i === $curIdx ? 'cur' : '');
            ?>
                <div class="step <?= $cls ?>"><span class="sdot"><?= $i <= $curIdx ? '✓' : '' ?></span><?= $lbl ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($o['pickup_name'])): ?>
            <div class="order-line"><?= icon('store',16) ?><span><?= e($o['pickup_name']) ?></span></div>
        <?php endif; ?>
        <div class="order-line"><?= icon('pin',16) ?><span><?= e($o['address']) ?></span></div>

        <ul class="order-items">
            <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
                <li><span><?= e($it['name']) ?> × <?= $it['quantity'] ?></span><span><?= money($it['price']*$it['quantity']) ?></span></li>
            <?php endforeach; ?>
        </ul>

        <div class="order-meta">
            <?php if ($o['distance_km'] > 0): ?><span class="tag dist"><?= icon('route',13) ?> <?= e($o['distance_km']) ?> km</span><?php endif; ?>
            <span class="tag <?= ($o['delivery_zone'] ?? 'in') === 'out' ? 'zone-out' : 'zone-in' ?>"><?= e(zone_label($o['delivery_zone'] ?? 'in')) ?></span>
            <span class="tag fee"><?= icon('truck',13) ?> Yo'l haqi: <?= money($o['delivery_fee']) ?></span>
        </div>

        <?php if ($active && $o['courier_name']): ?>
            <div class="order-line"><?= icon('truck',16) ?><span><?= e($o['courier_name']) ?> · <a href="tel:<?= e($o['courier_phone']) ?>"><?= e($o['courier_phone']) ?></a></span></div>
            <div class="live-map" data-order-id="<?= $o['id'] ?>" style="height:220px"></div>
            <div class="live-info" id="trackInfo-<?= $o['id'] ?>">Kuzatuv yuklanmoqda...</div>
        <?php elseif ($active): ?>
            <div class="order-line muted"><?= icon('clock',16) ?><span>Kuryer tayinlanishini kuting...</span></div>
        <?php endif; ?>

        <div class="order-foot">
            <span class="muted small"><?= e(date('d.m.Y H:i', strtotime($o['created_at']))) ?></span>
            <strong><?= money($o['total']) ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($hasActive): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/track-order.js"></script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
