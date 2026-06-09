<?php
require_once __DIR__ . '/includes/functions.php';
require_role('customer');

// ---- Savatcha amallari (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['product_id'] ?? 0);
    $_SESSION['cart'] = $_SESSION['cart'] ?? [];

    switch ($action) {
        case 'add':
            $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
            break;
        case 'inc':
            $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
            break;
        case 'dec':
            $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) - 1;
            if ($_SESSION['cart'][$pid] <= 0) unset($_SESSION['cart'][$pid]);
            break;
        case 'remove':
            unset($_SESSION['cart'][$pid]);
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            break;
    }
    redirect('/cart.php');
}

// ---- Savatcha tarkibini bazadan olish ----
$cart  = cart();
$items = [];
$total = 0;
if ($cart) {
    $ids   = implode(',', array_map('intval', array_keys($cart)));
    $rows  = db()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
    foreach ($rows as $r) {
        $qty   = (int)$cart[$r['id']];
        $sub   = $qty * (float)$r['price'];
        $total += $sub;
        $items[] = $r + ['qty' => $qty, 'sub' => $sub];
    }
}

$pageTitle = 'Savatcha';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Savatcha</h1>

<?php if (!$items): ?>
    <p class="muted">Savatchangiz bo'sh. <a href="/index.php">Mahsulotlarga o'tish</a></p>
<?php else: ?>
<div class="cart-layout">
    <div class="cart-items">
        <?php foreach ($items as $it): ?>
            <div class="cart-row">
                <div class="ci-img" style="background-image:url('<?= e($it['image']) ?>')"></div>
                <div class="ci-info">
                    <strong><?= e($it['name']) ?></strong>
                    <span class="muted"><?= money($it['price']) ?></span>
                </div>
                <div class="ci-qty">
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="dec">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <button class="qbtn">−</button>
                    </form>
                    <span><?= $it['qty'] ?></span>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="inc">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <button class="qbtn">+</button>
                    </form>
                </div>
                <div class="ci-sub"><?= money($it['sub']) ?></div>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                    <button class="qbtn danger" title="O'chirish">×</button>
                </form>
            </div>
        <?php endforeach; ?>
        <form method="post" class="clear-form"><?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button class="btn ghost">Savatchani tozalash</button>
        </form>
    </div>

    <!-- Buyurtma berish (manzil + lokatsiya) -->
    <div class="checkout card">
        <h2>Yetkazib berish manzili</h2>
        <form method="post" action="/checkout.php" id="checkoutForm">
            <?= csrf_field() ?>
            <label>Manzil (ko'cha, uy, mo'ljal)
                <input type="text" name="address" id="address" required placeholder="Masalan: Chilonzor 9-kvartal, 12-uy">
            </label>
            <label>Telefon
                <input type="text" name="phone" value="<?= e(current_user()['phone']) ?>" required>
            </label>
            <label>Izoh (ixtiyoriy)
                <textarea name="note" rows="2" placeholder="Qo'shimcha izoh..."></textarea>
            </label>

            <div class="map-box">
                <div class="map-head">
                    <span>📍 Lokatsiyani xaritadan belgilang</span>
                    <button type="button" class="btn small" id="locBtn">Mening joylashuvim</button>
                </div>
                <div id="map"></div>
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <p class="muted small" id="coords">Koordinata tanlanmagan</p>
            </div>

            <div class="checkout-total">
                <span>Jami:</span>
                <strong><?= money($total) ?></strong>
            </div>
            <button class="btn primary block" type="submit">Buyurtma berish</button>
        </form>
    </div>
</div>

<!-- Leaflet (OpenStreetMap) xarita -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/map.js"></script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
