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

// Buyurtma tarkibini olish
$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    $rows = db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$pageTitle = 'Buyurtmalarim';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Buyurtmalarim</h1>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert success">✅ Buyurtmangiz qabul qilindi! Raqami: #<?= (int)$_GET['ok'] ?></div>
<?php endif; ?>

<?php if (!$orders): ?>
    <p class="muted">Hali buyurtmalaringiz yo'q.</p>
<?php endif; ?>

<?php foreach ($orders as $o): ?>
    <div class="card order">
        <div class="order-head">
            <strong>Buyurtma #<?= $o['id'] ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>">
                <?= e(status_label($o['status'])) ?>
            </span>
        </div>
        <div class="order-body">
            <p>📍 <?= e($o['address']) ?></p>
            <?php if ($o['lat'] && $o['lng']): ?>
                <p><a href="https://www.google.com/maps?q=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>" target="_blank">Xaritada ko'rish</a></p>
            <?php endif; ?>
            <ul class="order-items">
                <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
                    <li><?= e($it['name']) ?> × <?= $it['quantity'] ?> — <?= money($it['price'] * $it['quantity']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($o['courier_name']): ?>
                <p class="muted">🛵 Kuryer: <?= e($o['courier_name']) ?> (<?= e($o['courier_phone']) ?>)</p>
            <?php endif; ?>
        </div>
        <div class="order-foot">
            <span class="muted small"><?= e($o['created_at']) ?></span>
            <strong><?= money($o['total']) ?></strong>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
