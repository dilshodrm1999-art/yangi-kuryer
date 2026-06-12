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
            db()->prepare('UPDATE orders SET courier_id=?, status=IF(status="new","accepted",status) WHERE id=?')
                ->execute([$courierId, $orderId]);
            $msg = "Buyurtma #$orderId kuryerga tayinlandi.";
        } else {
            db()->prepare('UPDATE orders SET courier_id=NULL WHERE id=?')->execute([$orderId]);
            $msg = "Kuryer olib tashlandi.";
        }
    } elseif ($action === 'status') {
        $status  = $_POST['status'] ?? '';
        $allowed = ['new','accepted','picked_up','on_way','delivered','cancelled'];
        if (in_array($status, $allowed, true)) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();
                if ($order) {
                    $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $orderId]);
                    if ($status === 'delivered') {
                        settle_delivery($pdo, $order);
                    }
                    if ($status === 'cancelled') {
                        refund_cashback_if_used($pdo, $orderId);
                    }
                }
                $pdo->commit();
                $msg = "Status yangilandi.";
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $msg = "Xatolik yuz berdi.";
            }
        }
    } elseif ($action === 'approve_cancel') {
        // Kuryerning bekor qilish so'rovini tasdiqlash -> buyurtma bekor bo'ladi
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE orders SET status='cancelled', cancel_requested=0 WHERE id=?")
                ->execute([$orderId]);
            refund_cashback_if_used($pdo, $orderId);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
        }
        $msg = "Buyurtma #$orderId bekor qilindi (kuryer so'rovi tasdiqlandi).";
    } elseif ($action === 'reject_cancel') {
        // So'rovni rad etish -> buyurtma davom etadi
        db()->prepare("UPDATE orders SET cancel_requested=0, cancel_reason=NULL WHERE id=?")
            ->execute([$orderId]);
        $msg = "Bekor so'rovi rad etildi. Buyurtma #$orderId davom etadi.";
    }
}

$filter = $_GET['status'] ?? '';
$sql = 'SELECT o.*, c.name AS customer_name, k.name AS courier_name
        FROM orders o JOIN users c ON c.id=o.customer_id
        LEFT JOIN users k ON k.id=o.courier_id';
$params = [];
if ($filter && $filter !== 'all') { $sql .= ' WHERE o.status = ?'; $params[] = $filter; }
$sql .= ' ORDER BY o.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$couriers = db()->query("SELECT id, name FROM users WHERE role='courier' AND is_active=1 ORDER BY name")->fetchAll();

// Bekor qilish so'rovlari soni (admin tasdig'i kutilayotgan)
$pendingCancels = (int)db()->query("SELECT COUNT(*) FROM orders WHERE cancel_requested=1")->fetchColumn();

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
<h1 class="page-title">Buyurtmalar 📋</h1>
<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>
<?php if ($pendingCancels > 0): ?>
    <div class="alert error"><?= icon('x',16) ?> <strong><?= $pendingCancels ?> ta</strong> bekor qilish so'rovi admin tasdig'ini kutmoqda.</div>
<?php endif; ?>

<div class="tabs">
    <?php
    $tabs = ['all'=>'Hammasi','new'=>'Yangi','accepted'=>'Qabul','picked_up'=>'Olindi','on_way'=>"Yo'lda",'delivered'=>'Yetkazildi','cancelled'=>'Bekor'];
    foreach ($tabs as $key => $label):
        $a = ($filter === $key || ($filter === '' && $key === 'all')) ? 'active' : '';
    ?>
        <a class="tab <?= $a ?>" href="?status=<?= $key ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$orders): ?><div class="card muted" style="text-align:center">Buyurtmalar yo'q.</div><?php endif; ?>

<div class="grid orders">
<?php foreach ($orders as $o): ?>
    <div class="card order">
        <div class="order-head">
            <strong>#<?= $o['id'] ?> · <?= e($o['customer_name']) ?></strong>
            <span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
        </div>
        <?php if (!empty($o['cancel_requested'])): ?>
            <div class="cancel-req-box">
                <div class="crb-head"><?= icon('x',15) ?> <strong>Kuryer bekor qilishni so'radi</strong></div>
                <?php if (!empty($o['cancel_reason'])): ?><p class="crb-reason">Sabab: <?= e($o['cancel_reason']) ?></p><?php endif; ?>
                <div class="crb-actions">
                    <form method="post" data-confirm="Buyurtma bekor qilinsinmi?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve_cancel">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button class="btn danger sm"><?= icon('check',14) ?> Tasdiqlash (bekor qilish)</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject_cancel">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button class="btn ghost sm"><?= icon('x',14) ?> Rad etish</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="order-line"><?= icon('pin',16) ?><span><?= e($o['address']) ?></span></div>
        <div class="order-line"><?= icon('phone',16) ?><span><?= e($o['phone']) ?></span></div>
        <?php if ($o['lat'] && $o['lng']): ?>
            <a class="btn ghost sm" target="_blank" href="https://www.google.com/maps?q=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>"><?= icon('nav',15) ?> Xaritada</a>
        <?php endif; ?>

        <ul class="order-items">
            <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
                <li><span><?= e($it['name']) ?></span><span>× <?= $it['quantity'] ?></span></li>
            <?php endforeach; ?>
        </ul>

        <div class="order-meta">
            <?php if ($o['distance_km'] > 0): ?><span class="tag dist"><?= icon('route',13) ?> <?= e($o['distance_km']) ?> km</span><?php endif; ?>
            <span class="tag <?= ($o['delivery_zone'] ?? 'in') === 'out' ? 'zone-out' : 'zone-in' ?>"><?= icon('pin',13) ?> <?= e(zone_label($o['delivery_zone'] ?? 'in')) ?></span>
            <span class="tag fee"><?= icon('truck',13) ?> <?= money($o['delivery_fee']) ?></span>
            <?php if (($o['cashback'] ?? 0) > 0): ?><span class="tag cashback"><?= icon('wallet',13) ?> Keshbek: +<?= money($o['cashback']) ?></span><?php endif; ?>
            <?php if (($o['cashback_used'] ?? 0) > 0): ?><span class="tag cashback"><?= icon('wallet',13) ?> Ishlatilgan: −<?= money($o['cashback_used']) ?></span><?php endif; ?>
        </div>

        <div class="order-controls">
            <form method="post" class="inline-form js-confirm2" data-confirm1="Buyurtma #<?= $o['id'] ?> kuryerini o'zgartirmoqchimisiz?" data-confirm2="Ishonchingiz komilmi? Bu o'zgarish kuryerga darhol ta'sir qiladi.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="courier_id" class="js-confirm-control">
                    <option value="0">— Kuryer tanlash —</option>
                    <?php foreach ($couriers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $o['courier_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="post" class="inline-form js-confirm2" data-confirm1="Buyurtma #<?= $o['id'] ?> holatini o'zgartirmoqchimisiz?" data-confirm2="Ishonchingiz komilmi? Tasdiqlasangiz holat darhol o'zgaradi.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="status" class="js-confirm-control">
                    <?php foreach (['new','accepted','picked_up','on_way','delivered','cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="order-foot">
            <span class="muted small"><?= $o['courier_name'] ? '🛵 '.e($o['courier_name']) : 'Kuryersiz' ?></span>
            <strong><?= money($o['total']) ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>
<script>
// Admin buyurtma o'zgartirishlari: 2 marta tasdiqlash
(function(){
  document.querySelectorAll('.js-confirm2').forEach(function(form){
    var ctrl = form.querySelector('.js-confirm-control');
    if(!ctrl) return;
    var prev = ctrl.value;
    ctrl.addEventListener('change', function(){
      var c1 = form.dataset.confirm1 || "O'zgartirishni tasdiqlaysizmi?";
      var c2 = form.dataset.confirm2 || "Ishonchingiz komilmi?";
      if (confirm(c1) && confirm(c2)) {
        form.submit();
      } else {
        ctrl.value = prev; // bekor qilindi — eski qiymatga qaytaramiz
      }
    });
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
