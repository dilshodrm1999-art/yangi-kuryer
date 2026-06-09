<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');

$me = current_user();

// ---- Status yangilash ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $allowed = ['accepted', 'on_way', 'delivered', 'cancelled'];

    if (in_array($status, $allowed, true)) {
        // Faqat o'ziga tayinlangan buyurtma
        $stmt = db()->prepare(
            'UPDATE orders SET status = ? WHERE id = ? AND courier_id = ?'
        );
        $stmt->execute([$status, $orderId, $me['id']]);
    }
    redirect('/courier/index.php');
}

// ---- O'ziga tayinlangan buyurtmalar ----
$stmt = db()->prepare(
    'SELECT o.*, u.name AS customer_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     WHERE o.courier_id = ?
     ORDER BY FIELD(o.status, "accepted","on_way","new","delivered","cancelled"), o.created_at DESC'
);
$stmt->execute([$me['id']]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    $rows = db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$active = array_filter($orders, fn($o) => in_array($o['status'], ['accepted', 'on_way']));
$done   = array_filter($orders, fn($o) => in_array($o['status'], ['delivered', 'cancelled']));

$pageTitle = 'Kuryer paneli';
require __DIR__ . '/../includes/header.php';

/** Bitta buyurtma kartasini chizish */
function courier_order_card(array $o, array $items): void { ?>
    <div class="card order">
        <div class="order-head">
            <strong>Buyurtma #<?= $o['id'] ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>">
                <?= e(status_label($o['status'])) ?>
            </span>
        </div>
        <div class="order-body">
            <p>👤 <?= e($o['customer_name']) ?> · 📞 <?= e($o['phone']) ?></p>
            <p>📍 <strong><?= e($o['address']) ?></strong></p>
            <?php if ($o['lat'] && $o['lng']): ?>
                <p>
                    <a class="btn small" href="https://www.google.com/maps?q=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>" target="_blank">🗺️ Yo'l ko'rsatish</a>
                </p>
            <?php endif; ?>
            <?php if ($o['note']): ?><p class="muted">📝 <?= e($o['note']) ?></p><?php endif; ?>
            <ul class="order-items">
                <?php foreach ($items as $it): ?>
                    <li><?= e($it['name']) ?> × <?= $it['quantity'] ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="order-foot">
            <strong><?= money($o['total']) ?></strong>
            <div class="order-actions">
                <?php if ($o['status'] === 'accepted'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="on_way">
                        <button class="btn primary small">Yo'lga chiqdim</button>
                    </form>
                <?php elseif ($o['status'] === 'on_way'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="delivered">
                        <button class="btn success small">Yetkazdim ✅</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($o['status'], ['accepted', 'on_way'])): ?>
                    <form method="post" onsubmit="return confirm('Bekor qilasizmi?')"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn ghost small">Bekor qilish</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php }
?>
<h1 class="page-title">Mening buyurtmalarim</h1>

<h2 class="sub">Aktiv (<?= count($active) ?>)</h2>
<?php if (!$active): ?>
    <p class="muted">Aktiv buyurtmalar yo'q.</p>
<?php endif; ?>
<div class="grid">
    <?php foreach ($active as $o) courier_order_card($o, $itemsByOrder[$o['id']] ?? []); ?>
</div>

<h2 class="sub">Tarix (<?= count($done) ?>)</h2>
<div class="grid">
    <?php foreach ($done as $o) courier_order_card($o, $itemsByOrder[$o['id']] ?? []); ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
