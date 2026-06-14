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

    // GPS majburiy: kuryer joylashuvi yangilanmagan bo'lsa hech qanday amal bajarilmaydi
    if (!courier_gps_fresh($me)) {
        $_SESSION['flash_err'] = "Geolokatsiya o'chiq. Tizimda ishlash uchun GPS'ni yoqing.";
        redirect('/courier/index.php');
    }

    if ($action === 'accept') {
        // Kuryer band bo'lsa (aktiv buyurtmasi bo'lsa) yangi qabul qila olmaydi
        $busy = $pdo->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE courier_id = ? AND status IN ('accepted','picked_up','on_way')"
        );
        $busy->execute([$me['id']]);
        if ((int)$busy->fetchColumn() > 0) {
            $_SESSION['flash'] = 'Avval joriy buyurtmangizni yakunlang, keyin yangisini oling.';
            redirect('/courier/index.php');
        }

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

    // Bekor qilish so'rovi: kuryer o'zi bekor qila olmaydi — admin tasdiqlashi kerak
    if ($action === 'cancel_request') {
        $reason = trim($_POST['reason'] ?? '');
        $stmt = $pdo->prepare(
            "UPDATE orders SET cancel_requested = 1, cancel_reason = ?
             WHERE id = ? AND courier_id = ? AND status IN ('accepted','picked_up','on_way')"
        );
        $stmt->execute([$reason !== '' ? $reason : 'Sabab ko\'rsatilmagan', $orderId, $me['id']]);
        $_SESSION['flash'] = $stmt->rowCount()
            ? "Bekor qilish so'rovi adminga yuborildi. Admin tasdiqlagach buyurtma bekor bo'ladi."
            : 'So\'rov yuborilmadi.';
        redirect('/courier/index.php');
    }

    // Holatni o'zgartirish (kuryer endi cancelled qila olmaydi)
    $status  = $_POST['status'] ?? '';
    $allowed = ['picked_up', 'on_way', 'delivered'];
    if (in_array($status, $allowed, true)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND courier_id=? FOR UPDATE');
            $stmt->execute([$orderId, $me['id']]);
            $order = $stmt->fetch();
            if ($order) {
                // "Yetkazdim" faqat mijoz manziliga 20m yaqinlikda tasdiqlanadi
                if ($status === 'delivered') {
                    $cLat = isset($_POST['cur_lat']) ? (float)$_POST['cur_lat'] : null;
                    $cLng = isset($_POST['cur_lng']) ? (float)$_POST['cur_lng'] : null;
                    if ($cLat === null || $cLng === null || $order['lat'] === null || $order['lng'] === null) {
                        $pdo->rollBack();
                        $_SESSION['flash_err'] = "Joylashuv aniqlanmadi. GPS yoqilganini tekshiring.";
                        redirect('/courier/index.php');
                    }
                    $dist = distance_meters($cLat, $cLng, $order['lat'], $order['lng']);
                    if ($dist > DELIVERY_RADIUS_M) {
                        $pdo->rollBack();
                        $_SESSION['flash_err'] = "Siz mijoz manzilidan " . round($dist) . " m uzoqdasiz. "
                            . "Yetkazishni tasdiqlash uchun manzilga " . DELIVERY_RADIUS_M . " m yaqinlashing.";
                        redirect('/courier/index.php');
                    }
                }
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

// ---- Faqat AKTIV buyurtmalar (tarix alohida sahifada) ----
$stmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.courier_id = ? AND o.status IN ('accepted','picked_up','on_way')
     ORDER BY FIELD(o.status,'on_way','picked_up','accepted'), o.created_at DESC"
);
$stmt->execute([$me['id']]);
$active = $stmt->fetchAll();

$isBusy = count($active) > 0;

// ---- Mavjud (tayinlanmagan) yangi buyurtmalar ----
// Kuryer band bo'lsa yangi buyurtmalar ko'rsatilmaydi.
$available = [];
if (!$isBusy) {
    $available = db()->query(
        "SELECT o.*, u.name AS customer_name
         FROM orders o JOIN users u ON u.id = o.customer_id
         WHERE o.status = 'new' AND o.courier_id IS NULL
         ORDER BY o.created_at ASC"
    )->fetchAll();
}

$itemsByOrder = load_order_items(array_merge($available, $active));

// Bugungi qisqa statistika (sarlavha uchun)
$today = db()->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(delivery_fee - commission),0) AS earn
     FROM orders WHERE courier_id=? AND status='delivered' AND DATE(updated_at)=CURDATE()"
);
$today->execute([$me['id']]);
$t = $today->fetch();

$flash = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_err']);

// GPS holati (server tomonda oxirgi yangilanish)
$gpsFresh = courier_gps_fresh($me);

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
        <span id="gpsBadge" class="gps-chip <?= $gpsFresh ? 'on' : 'off' ?>"><?= $gpsFresh ? 'GPS yoqilgan' : 'GPS...' ?></span>
    </div>
    <div class="cr-hero-stats">
        <div><span class="v"><?= (int)$t['cnt'] ?></span><span class="l">Bugun yetkazildi</span></div>
        <div><span class="v"><?= money($t['earn']) ?></span><span class="l">Bugungi daromad</span></div>
        <div><span class="v"><?= money($me['balance']) ?></span><span class="l">Balans</span></div>
    </div>
</section>

<!-- GPS majburiy ogohlantirishi (geolokatsiya o'chiq bo'lsa) -->
<div id="gpsRequired" class="alert error" style="<?= $gpsFresh ? 'display:none' : '' ?>">
    <?= icon('nav',16) ?> <strong>Geolokatsiya o'chiq.</strong> Tizimda ishlash uchun GPS'ni yoqing — aks holda buyurtma qabul qilish va yetkazishni tasdiqlab bo'lmaydi.
</div>

<?php if ($flashErr): ?><div class="alert error"><?= icon('x',16) ?> <?= e($flashErr) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert info"><?= icon('check',16) ?> <?= e($flash) ?></div><?php endif; ?>

<!-- Ratsiya: admin bilan ovozli aloqa -->
<div class="ratsiya-card">
    <h2><?= icon('mic',18) ?> Ratsiya (admin bilan aloqa)</h2>
    <div class="ptt-wrap">
        <button type="button" class="ptt-btn" id="courierPtt" title="Bosib turing va gapiring"><?= icon('mic',34) ?></button>
        <span class="ptt-hint">Bosib turing va gapiring — admin eshitadi</span>
        <span class="ptt-status" id="courierPttStatus"></span>
    </div>
</div>

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
<?php if ($isBusy): ?>
    <div class="cr-section-head">
        <h2><?= icon('package',18) ?> Yangi buyurtmalar</h2>
    </div>
    <div class="empty-box busy">
        <?= icon('truck',30) ?>
        <p><strong>Siz hozir bandsiz.</strong><br>Joriy buyurtmani yetkazib bo'lgach, yangi buyurtmalar shu yerda ko'rinadi.</p>
    </div>
<?php else: ?>
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
<?php endif; ?>

<script>
window.__lastOrderId = <?= $available ? max(array_map(fn($o)=>(int)$o['id'],$available)) : 0 ?>;
window.__courierBusy = <?= $isBusy ? 'true' : 'false' ?>;
window.__deliveryRadius = <?= DELIVERY_RADIUS_M ?>;
</script>

<!-- Bekor qilish so'rovi (yashirin forma) -->
<form method="post" id="cancelForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="cancel_request">
    <input type="hidden" name="order_id" id="cancelOrderId">
    <input type="hidden" name="reason" id="cancelReason">
</form>

<script src="/assets/js/courier-track.js"></script>
<script src="/assets/js/courier-actions.js"></script>
<script src="/assets/js/courier-orders.js"></script>
<script src="/assets/js/ratsiya.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
