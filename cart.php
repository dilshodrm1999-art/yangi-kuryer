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

// ---- Savatcha tarkibi ----
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

$storeLat = (float)setting('store_lat', 41.311081);
$storeLng = (float)setting('store_lng', 69.240562);
$perKm    = (float)setting('price_per_km', 8000);

$pageTitle = 'Savatcha';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Savatcha 🛒</h1>

<?php if (!$items): ?>
    <div class="card" style="text-align:center;color:var(--muted)">
        Savatchangiz bo'sh.<br><a class="btn primary sm" style="margin-top:10px" href="/index.php">Mahsulotlarga o'tish</a>
    </div>
<?php else: ?>
<div class="cart-layout">
    <div class="cart-items">
        <?php foreach ($items as $it): ?>
            <div class="cart-row">
                <div class="ci-img" style="background-image:url('<?= e($it['image']) ?>')"></div>
                <div class="ci-info">
                    <strong><?= e($it['name']) ?></strong>
                    <span class="muted small"><?= money($it['price']) ?></span>
                </div>
                <div class="ci-qty">
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="dec">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <button class="qbtn"><?= icon('minus',16) ?></button>
                    </form>
                    <span><?= $it['qty'] ?></span>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="inc">
                        <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                        <button class="qbtn"><?= icon('plus',16) ?></button>
                    </form>
                </div>
                <div class="ci-sub"><?= money($it['sub']) ?></div>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= $it['id'] ?>">
                    <button class="qbtn danger" title="O'chirish"><?= icon('trash',15) ?></button>
                </form>
            </div>
        <?php endforeach; ?>
        <form method="post" style="margin-top:8px"><?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button class="btn ghost sm"><?= icon('trash',15) ?> Savatchani tozalash</button>
        </form>
    </div>

    <!-- Buyurtma berish -->
    <div class="checkout card">
        <h2 style="margin-bottom:14px"><?= icon('pin',18) ?> Yetkazib berish</h2>
        <form method="post" action="/checkout.php" id="checkoutForm">
            <?= csrf_field() ?>

            <div class="gps-banner" id="geoStatus"><?= icon('nav',16) ?> Joylashuv aniqlanmoqda...</div>

            <label class="field"><span>Manzil</span>
                <input type="text" name="address" id="address" required placeholder="Manzil avtomatik to'ldiriladi...">
            </label>
            <label class="field"><span>Telefon</span>
                <input type="tel" name="phone" value="<?= e(current_user()['phone']) ?>" required>
            </label>
            <label class="field"><span>Izoh (ixtiyoriy)</span>
                <textarea name="note" rows="2" placeholder="Eshik kodi, qavat va h.k."></textarea>
            </label>

            <div class="map-box">
                <div class="map-head">
                    <span><?= icon('pin',16) ?> Xaritadan belgilang</span>
                    <button type="button" class="btn ghost sm" id="locBtn"><?= icon('nav',15) ?> Joylashuvim</button>
                </div>
                <div id="map"></div>
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <p class="coords" id="coords">Koordinata tanlanmagan</p>
            </div>

            <div class="summary-row"><span>Mahsulotlar</span><strong><?= money($total) ?></strong></div>
            <div class="summary-row"><span>Yetkazib berish <small id="distTxt"></small></span><strong id="feeTxt">—</strong></div>
            <div class="summary-total"><span>Jami</span><strong id="grandTxt"><?= money($total) ?></strong></div>

            <button class="btn primary block" type="submit"><?= icon('check',18) ?> Buyurtma berish</button>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/map.js"></script>
<script>
// Yetkazib berish haqini jonli hisoblash (do'kondan masofa * km-narx)
(function(){
  var STORE=[<?= $storeLat ?>,<?= $storeLng ?>], PER_KM=<?= $perKm ?>, GOODS=<?= (int)$total ?>;
  function fmt(n){return new Intl.NumberFormat('ru-RU').format(Math.round(n))+" so'm";}
  function hav(a,b,c,d){var R=6371,p=Math.PI/180;
    var x=Math.sin((c-a)*p/2)**2+Math.cos(a*p)*Math.cos(c*p)*Math.sin((d-b)*p/2)**2;
    return R*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));}
  window.onLocationChange=function(lat,lng){
    var km=hav(STORE[0],STORE[1],lat,lng);
    var fee=Math.round(km*PER_KM/100)*100;
    document.getElementById('distTxt').textContent='('+km.toFixed(1)+' km)';
    document.getElementById('feeTxt').textContent=fmt(fee);
    document.getElementById('grandTxt').textContent=fmt(GOODS+fee);
  };
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
