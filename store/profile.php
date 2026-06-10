<?php
require_once __DIR__ . '/../includes/functions.php';
$store = require_store_owner();
$sid = (int)$store['id'];
$weekDays = [1 => 'Du', 2 => 'Se', 3 => 'Ch', 4 => 'Pa', 5 => 'Ju', 6 => 'Sh', 7 => 'Ya'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $logo     = trim($_POST['logo'] ?? '');
    $cover    = trim($_POST['cover'] ?? '');
    $image    = trim($_POST['image'] ?? '');
    $theme    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_color'] ?? '') ? $_POST['theme_color'] : '#ff6b35';
    $address  = trim($_POST['address'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $lat      = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
    $lng      = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
    $open     = preg_match('/^\d{2}:\d{2}$/', $_POST['open_time'] ?? '')  ? $_POST['open_time'].':00'  : '09:00:00';
    $close    = preg_match('/^\d{2}:\d{2}$/', $_POST['close_time'] ?? '') ? $_POST['close_time'].':00' : '22:00:00';
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $days = array_filter(array_map('intval', (array)($_POST['work_days'] ?? [])), fn($d) => $d >= 1 && $d <= 7);
    $workDays = $days ? implode(',', $days) : '1,2,3,4,5,6,7';

    if ($name === '') {
        $msg = 'Do\'kon nomini kiriting.';
    } else {
        // Faqat o'z do'konini yangilaydi (owner_id orqali himoyalangan)
        db()->prepare(
            'UPDATE stores SET name=?, description=?, logo=?, cover=?, image=?, theme_color=?,
                address=?, phone=?, lat=?, lng=?, open_time=?, close_time=?, work_days=?, is_active=?
             WHERE id=? AND owner_id=?'
        )->execute([$name, $desc, $logo, $cover, $image, $theme, $address, $phone, $lat, $lng, $open, $close, $workDays, $active, $sid, (int)$store['owner_id']]);
        redirect('/store/profile.php?saved=1');
    }
}

// Eng so'nggi ma'lumotni qayta o'qiymiz
$stmt = db()->prepare('SELECT * FROM stores WHERE id=?');
$stmt->execute([$sid]);
$store = $stmt->fetch();

$editDays = array_map('intval', explode(',', $store['work_days'] ?? '1,2,3,4,5,6,7'));
$mapLat = $store['lat'] !== null ? (float)$store['lat'] : (float)setting('map_lat', 41.3111);
$mapLng = $store['lng'] !== null ? (float)$store['lng'] : (float)setting('map_lng', 69.2797);
$mapZoom = (int)setting('map_zoom', 12);

$pageTitle = 'Do\'kon sozlamalari';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Do'konni bezatish <?= icon('palette',22) ?></h1>
<p class="page-sub">Logotip, ranglar, ish vaqti va joylashuvni o'zingiz boshqaring.</p>

<?php if (isset($_GET['saved'])): ?><div class="alert success"><?= icon('check',16) ?> Saqlandi.</div><?php endif; ?>
<?php if ($msg): ?><div class="alert error"><?= icon('x',16) ?><?= e($msg) ?></div><?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="admin-layout">
        <div class="card form-card">
            <h2><?= icon('palette',18) ?> Brending</h2>

            <div class="brand-preview" style="--theme: <?= e($store['theme_color'] ?: '#ff6b35') ?>">
                <div class="bp-cover" style="background-image:url('<?= e($store['cover'] ?? '') ?>')">
                    <div class="bp-logo" style="background-image:url('<?= e($store['logo'] ?: $store['image'] ?? '') ?>')"></div>
                </div>
                <div class="bp-name"><?= e($store['name']) ?></div>
            </div>

            <label class="field"><span>Do'kon nomi</span><input type="text" name="name" value="<?= e($store['name']) ?>" required></label>
            <label class="field"><span>Tavsif</span><textarea name="description" rows="2"><?= e($store['description'] ?? '') ?></textarea></label>
            <label class="field"><span><?= icon('image',14) ?> Logotip URL</span><input type="text" name="logo" value="<?= e($store['logo'] ?? '') ?>" placeholder="https://... (logo)"></label>
            <label class="field"><span><?= icon('image',14) ?> Sarlavha (banner) rasmi URL</span><input type="text" name="cover" value="<?= e($store['cover'] ?? '') ?>" placeholder="https://... (banner)"></label>
            <label class="field"><span>Asosiy rasm URL</span><input type="text" name="image" value="<?= e($store['image'] ?? '') ?>" placeholder="https://..."></label>
            <label class="field"><span>Asosiy rang (theme)</span><input type="color" name="theme_color" value="<?= e($store['theme_color'] ?: '#ff6b35') ?>" style="height:44px;padding:4px"></label>

            <h2 style="margin-top:18px"><?= icon('clock',18) ?> Ish vaqti</h2>
            <div class="grid-2">
                <label class="field"><span>Ochilish</span><input type="time" name="open_time" value="<?= e(substr($store['open_time'] ?? '09:00:00',0,5)) ?>"></label>
                <label class="field"><span>Yopilish</span><input type="time" name="close_time" value="<?= e(substr($store['close_time'] ?? '22:00:00',0,5)) ?>"></label>
            </div>
            <div class="field"><span>Ish kunlari</span>
                <div class="day-chips">
                    <?php foreach ($weekDays as $num => $lbl): ?>
                        <label class="day-chip">
                            <input type="checkbox" name="work_days[]" value="<?= $num ?>" <?= in_array($num, $editDays, true) ? 'checked' : '' ?>>
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="field"><span>Manzil</span><input type="text" name="address" value="<?= e($store['address'] ?? '') ?>"></label>
            <label class="field"><span>Telefon</span><input type="tel" name="phone" value="<?= e($store['phone'] ?? '') ?>" placeholder="+998..."></label>
            <label class="check"><input type="checkbox" name="is_active" <?= ($store['is_active'] ?? 1) ? 'checked' : '' ?>> Do'kon faol (mijozlarga ko'rinadi)</label>

            <button class="btn primary block" style="margin-top:12px"><?= icon('check',16) ?> Saqlash</button>
        </div>

        <div class="card">
            <h2 style="margin-bottom:12px"><?= icon('pin',18) ?> Do'kon joylashuvi</h2>
            <div id="map" style="height:360px"></div>
            <input type="hidden" name="lat" id="lat" value="<?= $store['lat'] !== null ? (float)$store['lat'] : '' ?>">
            <input type="hidden" name="lng" id="lng" value="<?= $store['lng'] !== null ? (float)$store['lng'] : '' ?>">
            <p class="coords" id="coords"><?= $store['lat'] !== null ? '📍 '.(float)$store['lat'].', '.(float)$store['lng'] : 'Xaritaga bosib do\'kon joyini belgilang' ?></p>
        </div>
    </div>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var def=[<?= $mapLat ?>,<?= $mapLng ?>];
  var map=L.map('map').setView(def,<?= $mapZoom ?>);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'\u00a9 OpenStreetMap'}).addTo(map);
  var latEl=document.getElementById('lat'),lngEl=document.getElementById('lng'),coordsEl=document.getElementById('coords');
  var m=null;
  <?php if ($store['lat'] !== null): ?>
  m=L.marker(def,{draggable:true}).addTo(map); bind(m);
  <?php endif; ?>
  function bind(mk){mk.on('dragend',function(e){var p=e.target.getLatLng();save(p.lat,p.lng);});}
  function save(lat,lng){latEl.value=lat.toFixed(7);lngEl.value=lng.toFixed(7);coordsEl.textContent='📍 '+lat.toFixed(5)+', '+lng.toFixed(5);}
  map.on('click',function(e){
    if(m){m.setLatLng(e.latlng);}else{m=L.marker(e.latlng,{draggable:true}).addTo(map);bind(m);}
    save(e.latlng.lat,e.latlng.lng);
  });
  setTimeout(function(){map.invalidateSize();},300);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
