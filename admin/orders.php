<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action  = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'assign') {
        $courierId = (int)($_POST['courier_id'] ?? 0) ?: null;
        if ($courierId) {
            // Tayinlаsh: status "accepted" ga o'tadi
            $stmt = db()->prepare(
                'UPDATE orders SET courier_id=?, status=IF(status="new","accepted",status) WHERE id=?'
            );
            $stmt->execute([$courierId, $orderId]);
            $msg = "Buyurtma #$orderId kuryerga tayinlandi.";
        } else {
            $stmt = db()->prepare('UPDATE orders SET courier_id=NULL WHERE id=?');
            $stmt->execute([$orderId]);
            $msg = "Kuryer olib tashlandi.";
        }
    } elseif ($action === 'status') {
        $status  = $_POST['status'] ?? '';
        $allowed = ['new','accepted','on_way','delivered','cancelled'];
        if (in_array($status, $allowed, true)) {
            $stmt = db()->prepare('UPDATE orders SET status=? WHERE id=?');
            $stmt->execute([$status, $orderId]);
            $msg = "Status yangilandi.";
        }
    }
}

$filter = $_GET['status'] ?? '';
$sql = 'SELECT o.*, c.name AS customer_name, k.name AS courier_name
        FROM orders o
        JOIN users c ON c.id = o.customer_id
        LEFT JOIN users k ON k.id = o.courier_id';
$params = [];
if ($filter && $filter !== 'all') {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$couriers = db()->query("SELECT id, name FROM users WHERE role='courier' AND is_active=1 ORDER BY name")->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    foreach (db()->query("SELECT * FROM order_items WHERE order_id IN ($ids)")->fetchAll() as $r) {
        $itemsByOrder[$r['order_id']][] = $r;
    }
}

$pageTitle = 'Buyurtmalar (admin)';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Buyurtmalar boshqaruvi</h1>
<?php if ($msg): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>

<div class="tabs">
    <?php
    $tabs = ['all'=>'Hammasi','new'=>'Yangi','accepted'=>'Qabul qilindi','on_way'=>"Yo'lda",'delivered'=>'Yetkazildi','cancelled'=>'Bekor'];
    foreach ($tabs as $key => $label):
        $active = ($filter === $key || ($filter === '' && $key === 'all')) ? 'active' : '';
    ?>
        <a class="tab <?= $active ?>" href="?status=<?= $key ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$orders): ?><p class="muted">Buyurtmalar yo'q.</p><?php endif; ?>

<div class="grid">
<?php foreach ($orders as $o): ?>
    <div class="card order">
        <div class="order-head">
            <strong>#<?= $o['id'] ?> · <?= e($o['customer_name']) ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        </div>
        <div class="order-body">
            <p>📍 <?= e($o['address']) ?> · 📞 <?= e($o['phone']) ?></p>
            <?php if ($o['lat'] && $o['lng']): ?>
                <p><a href="https://www.google.com/maps?q=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>" target="_blank">Xaritada ko'rish</a></p>
            <?php endif; ?>
            <ul class="order-items">
                <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
                    <li><?= e($it['name']) ?> × <?= $it['quantity'] ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="order-controls">
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="courier_id" onchange="this.form.submit()">
                    <option value="0">— Kuryer tanlash —</option>
                    <?php foreach ($couriers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $o['courier_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                    <?php foreach (['new','accepted','on_way','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="order-foot">
            <span class="muted small"><?= $o['courier_name'] ? '🛵 '.e($o['courier_name']) : 'Kuryer yo\'q' ?></span>
            <strong><?= money($o['total']) ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
