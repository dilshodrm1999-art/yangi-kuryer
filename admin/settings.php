<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    set_setting('price_per_km', (string)max(0, (int)($_POST['price_per_km'] ?? 8000)));
    set_setting('min_fee',      (string)max(0, (int)($_POST['min_fee'] ?? 0)));
    set_setting('store_name',   trim($_POST['store_name'] ?? 'Ombor'));
    if (($_POST['store_lat'] ?? '') !== '') set_setting('store_lat', (string)(float)$_POST['store_lat']);
    if (($_POST['store_lng'] ?? '') !== '') set_setting('store_lng', (string)(float)$_POST['store_lng']);
    $msg = 'Sozlamalar saqlandi.';
    // keshni yangilash uchun qayta yuklaymiz
    redirect('/admin/settings.php?saved=1');
}

$perKm     = (float)setting('price_per_km', 8000);
$minFee     = (float)setting('min_fee', 0);
$storeName = setting('store_name', 'Ombor');
$storeLat  = (float)setting('store_lat', 41.311081);
$storeLng  = (float)setting('store_lng', 69.240562);

$pageTitle = 'Sozlamalar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Sozlamalar ⚙️</h1>
<p class="page-sub">Yetkazib berish narxi va omborning joylashuvini sozlang.</p>

<?php if (isset($_GET['saved'])): ?><div class="alert success"><?= icon('check',16) ?> Sozlamalar saqlandi.</div><?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="admin-layout">
        <div class="card form-card">
            <h2><?= icon('wallet',18) ?> Narx sozlamalari</h2>
            <label class="field"><span>1 km uchun narx (so'm)</span>
                <input type="number" name="price_per_km" value="<?= (int)$perKm ?>" min="0" step="100" required>
            </label>
            <label class="field"><span>Minimal yetkazib berish haqi (so'm)</span>
                <input type="number" name="min_fee" value="<?= (int)$minFee ?>" min="0" step="500">
            </label>
            <div class="alert info"><?= icon('route',16) ?> Haq = masofa (km) × 1km narxi. Masofa mahsulot olinadigan ombordan mijozgacha hisoblanadi.</div>
            <label class="field"><span>Ombor nomi</span>
                <input type="text" name="store_name" value="<?= e($storeName) ?>">
            </label>
            <button class="btn primary block"><?= icon('check',16) ?> Saqlash</button>
        </div>

        <div class="card">
            <h2 style="margin-bottom:12px"><?= icon('pin',18) ?> Ombor joylashuvi (olish nuqtasi)</h2>
            <div class="map-head">
                <span class="muted small">Xaritaga bosib belgilang</span>
                <button type="button" class="btn ghost sm" id="locBtn"><?= icon('nav',15) ?> Joylashuvim</button>
            </div>
            <div id="map"></div>
            <input type="hidden" name="store_lat" id="lat" value="<?= $storeLat ?>">
            <input type="hidden" name="store_lng" id="lng" value="<?= $storeLng ?>">
            <p class="coords" id="coords">📍 <?= $storeLat ?>, <?= $storeLng ?></p>
        </div>
    </div>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var def=[<?= $storeLat ?>,<?= $storeLng ?>];
  var map=L.map('map').setView(def,13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
  var m=L.marker(def,{draggable:true}).addTo(map);
  function save(lat,lng){document.getElementById('lat').value=lat.toFixed(7);document.getElementById('lng').value=lng.toFixed(7);
    document.getElementById('coords').textContent='📍 '+lat.toFixed(5)+', '+lng.toFixed(5);}
  m.on('dragend',function(e){var p=e.target.getLatLng();save(p.lat,p.lng);});
  map.on('click',function(e){m.setLatLng(e.latlng);save(e.latlng.lat,e.latlng.lng);});
  document.getElementById('locBtn').addEventListener('click',function(){
    if(!navigator.geolocation)return;
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude,lng=pos.coords.longitude;map.setView([lat,lng],16);m.setLatLng([lat,lng]);save(lat,lng);
    });
  });
  setTimeout(function(){map.invalidateSize();},300);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
