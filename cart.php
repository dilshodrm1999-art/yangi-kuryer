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
    $rows  = db()->query(
        "SELECT p.*, s.discount_percent AS store_discount
         FROM products p LEFT JOIN stores s ON s.id = p.store_id
         WHERE p.id IN ($ids)"
    )->fetchAll();
    foreach ($rows as $r) {
        $qty   = (int)$cart[$r['id']];
        $unit  = product_final_price($r);
        $sub   = $qty * $unit;
        $total += $sub;
        $items[] = $r + ['qty' => $qty, 'unit' => $unit, 'sub' => $sub, 'disc' => product_discount($r)];
    }
}

$storeLat = (float)setting('store_lat', 41.311081);
$storeLng = (float)setting('store_lng', 69.240562);
$priceIn  = (float)setting('price_in_city', setting('price_per_km', 8000));
$priceOut = (float)setting('price_out_city', 15000);
$minFee   = (float)setting('min_fee', 0);
$cityPoly = setting('city_polygon', '[]');

// Olish nuqtasi (do'kon manzili) — savatdagi do'konga qarab aniqlanadi
$pickup    = $cart ? resolve_pickup(array_keys($cart)) : ['lat'=>$storeLat,'lng'=>$storeLng,'name'=>setting('store_name','Ombor'),'address'=>''];
$pickupLat = $pickup['lat'] ?? $storeLat;
$pickupLng = $pickup['lng'] ?? $storeLng;

$pageTitle = 'Savatcha';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Savatcha 🛒</h1>

<?php if (isset($_GET['closed'])): ?>
    <div class="alert error"><?= icon('clock',16) ?> "<?= e($_GET['closed']) ?>" do'koni hozir yopiq. Ish vaqtida qayta urinib ko'ring.</div>
<?php endif; ?>

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
                    <?php if ($it['disc'] > 0): ?>
                        <span class="muted small"><span class="old-price"><?= money($it['price']) ?></span> <?= money($it['unit']) ?> <span class="tag dist">-<?= (float)$it['disc'] ?>%</span></span>
                    <?php else: ?>
                        <span class="muted small"><?= money($it['unit']) ?></span>
                    <?php endif; ?>
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

        <?php if (!empty($pickup['name'])): ?>
        <div class="pickup-line">
            <?= icon('store',16) ?>
            <span>Olish nuqtasi: <strong><?= e($pickup['name']) ?></strong><?= $pickup['address'] ? ' · '.e($pickup['address']) : '' ?></span>
        </div>
        <?php endif; ?>

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
            <div class="summary-row"><span>Yo'l (do'kondan manzilgacha) <small id="distTxt"></small></span><strong id="feeTxt">—</strong></div>

            <?php $cbBal = (float)(current_user()['cashback_balance'] ?? 0); ?>
            <?php if ($cbBal > 0): ?>
                <label class="cb-use">
                    <input type="checkbox" name="use_cashback" id="useCashback" value="1">
                    <span><?= icon('wallet',15) ?> Keshbekni ishlatish (<strong><?= money($cbBal) ?></strong>)</span>
                </label>
                <div class="summary-row cb-row" id="cbRow" style="display:none"><span>Keshbek chegirmasi</span><strong id="cbTxt">−0</strong></div>
            <?php endif; ?>

            <div class="summary-total"><span>Jami</span><strong id="grandTxt"><?= money($total) ?></strong></div>
            <p class="muted small" id="feeHint" style="margin-top:6px"><?= icon('route',13) ?> Manzilni xaritada belgilang — yo'l haqi avtomatik hisoblanadi.</p>

            <button class="btn primary block" type="submit"><?= icon('check',18) ?> Buyurtma berish</button>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Yetkazib berish zonasi (shahar ichi/tashqarisi) — xaritada poligon ko'rsatiladi
window.CITY_POLYGON = <?= $cityPoly ?: '[]' ?>;
// Olish nuqtasi (do'kon) koordinatasi va nomi — xaritada ko'rsatiladi
window.PICKUP = {lat: <?= $pickupLat ?>, lng: <?= $pickupLng ?>, name: <?= json_encode($pickup['name'] ?? 'Do\'kon', JSON_UNESCAPED_UNICODE) ?>};
</script>
<script src="/assets/js/map.js"></script>
<script>
// Yetkazib berish haqini jonli hisoblash (do'kon -> mijoz, VELOSIPED yo'li bo'yicha)
(function(){
  var PRICE_IN=<?= (int)$priceIn ?>, PRICE_OUT=<?= (int)$priceOut ?>, MIN_FEE=<?= (int)$minFee ?>;
  var GOODS=<?= (int)$total ?>;
  var CB_BAL=<?= (int)(current_user()['cashback_balance'] ?? 0) ?>;
  var POLY=window.CITY_POLYGON||[];
  var lastFee=0;
  function fmt(n){return new Intl.NumberFormat('ru-RU').format(Math.round(n))+" so'm";}
  // Ray-casting: nuqta poligon ichidami?
  function inPoly(lat,lng){
    if(!POLY||POLY.length<3) return true;
    var inside=false;
    for(var i=0,j=POLY.length-1;i<POLY.length;j=i++){
      var yi=POLY[i][0],xi=POLY[i][1],yj=POLY[j][0],xj=POLY[j][1];
      var hit=((yi>lat)!==(yj>lat))&&(lng<(xj-xi)*(lat-yi)/((yj-yi)||1e-12)+xi);
      if(hit) inside=!inside;
    }
    return inside;
  }
  function recalc(){
    var total = GOODS + lastFee;
    var useCb = document.getElementById('useCashback');
    var cbRow = document.getElementById('cbRow');
    var used = 0;
    if (useCb && useCb.checked) {
      used = Math.min(CB_BAL, total);
      if (cbRow) { cbRow.style.display=''; document.getElementById('cbTxt').textContent='−'+fmt(used); }
    } else if (cbRow) {
      cbRow.style.display='none';
    }
    document.getElementById('grandTxt').textContent = fmt(total - used);
  }
  // map.js real yo'l masofasini hisoblaganda chaqiradi (km = yo'l masofasi)
  window.onRouteResult=function(km,lat,lng){
    if(km===null||isNaN(km)) return;
    var inCity=inPoly(lat,lng);
    var perKm=inCity?PRICE_IN:PRICE_OUT;
    lastFee=Math.max(Math.round(km*perKm/100)*100, MIN_FEE);
    var zoneTxt=inCity?'🏙️ shahar ichi':'🛣️ shahar tashqarisi';
    document.getElementById('distTxt').textContent='('+km.toFixed(1)+' km · '+zoneTxt+')';
    document.getElementById('feeTxt').textContent=fmt(lastFee);
    var hint=document.getElementById('feeHint');
    if(hint) hint.innerHTML='🚲 '+window.PICKUP.name+' → manzil: <strong>'+km.toFixed(1)+' km</strong> (yo\'l bo\'yicha), '+zoneTxt+'. 1 km = '+fmt(perKm);
    recalc();
  };
  var cbToggle=document.getElementById('useCashback');
  if(cbToggle) cbToggle.addEventListener('change', recalc);
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
