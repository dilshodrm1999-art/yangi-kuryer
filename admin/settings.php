<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/regions.php';
require_role('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Narxlar (shahar ichi / tashqarisi)
    set_setting('price_in_city',  (string)max(0, (int)($_POST['price_in_city'] ?? 8000)));
    set_setting('price_out_city', (string)max(0, (int)($_POST['price_out_city'] ?? 15000)));
    // Eski mosligi uchun price_per_km ni ham shahar ichi narxiga tenglaymiz
    set_setting('price_per_km',   (string)max(0, (int)($_POST['price_in_city'] ?? 8000)));
    set_setting('min_fee',        (string)max(0, (int)($_POST['min_fee'] ?? 0)));
    set_setting('commission_percent', (string)min(100, max(0, (int)($_POST['commission_percent'] ?? 0))));
    set_setting('cashback_percent', (string)min(100, max(0, (int)($_POST['cashback_percent'] ?? 0))));

    // Hudud (viloyat / tuman)
    $region   = trim($_POST['region'] ?? '');
    $district = trim($_POST['district'] ?? '');
    if ($region !== '' && in_array($region, uz_region_names(), true)) {
        set_setting('region', $region);
        set_setting('district', $district);
        $info = uz_region_info($region);
        // Xarita markazini hudud markaziga moslash (agar admin alohida belgilamagan bo'lsa)
        if ($info) {
            set_setting('map_lat',  (string)(float)($_POST['map_lat']  ?? $info['lat']));
            set_setting('map_lng',  (string)(float)($_POST['map_lng']  ?? $info['lng']));
            set_setting('map_zoom', (string)(int)($_POST['map_zoom'] ?? $info['zoom']));
        }
    }

    // Ombor nomi va joylashuvi
    set_setting('store_name', trim($_POST['store_name'] ?? 'Ombor'));
    if (($_POST['store_lat'] ?? '') !== '') set_setting('store_lat', (string)(float)$_POST['store_lat']);
    if (($_POST['store_lng'] ?? '') !== '') set_setting('store_lng', (string)(float)$_POST['store_lng']);

    // Shahar markazi poligoni (JSON: [[lat,lng], ...])
    $polyRaw = $_POST['city_polygon'] ?? '[]';
    $poly    = json_decode((string)$polyRaw, true);
    if (is_array($poly)) {
        // Faqat to'g'ri sonli juftliklarni saqlaymiz (validatsiya)
        $clean = [];
        foreach ($poly as $pt) {
            if (is_array($pt) && count($pt) === 2 && is_numeric($pt[0]) && is_numeric($pt[1])) {
                $clean[] = [round((float)$pt[0], 7), round((float)$pt[1], 7)];
            }
        }
        set_setting('city_polygon', json_encode($clean));
    }

    redirect('/admin/settings.php?saved=1');
}

$priceIn   = (float)setting('price_in_city', 8000);
$priceOut  = (float)setting('price_out_city', 15000);
$minFee     = (float)setting('min_fee', 0);
$commission = (float)setting('commission_percent', 20);
$cashbackPct = (float)setting('cashback_percent', 0);
$storeName = setting('store_name', 'Ombor');
$storeLat  = (float)setting('store_lat', 41.311081);
$storeLng  = (float)setting('store_lng', 69.240562);

$region    = setting('region', 'Toshkent shahri');
$district  = setting('district', '');
$mapLat    = (float)setting('map_lat', 41.3111);
$mapLng    = (float)setting('map_lng', 69.2797);
$mapZoom   = (int)setting('map_zoom', 12);
$cityPoly  = setting('city_polygon', '[]');

$regions = uz_regions();

$pageTitle = 'Sozlamalar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Sozlamalar ⚙️</h1>
<p class="page-sub">Hudud, xarita, shahar chegarasi va yetkazib berish narxlarini sozlang.</p>

<?php if (isset($_GET['saved'])): ?><div class="alert success"><?= icon('check',16) ?> Sozlamalar saqlandi.</div><?php endif; ?>

<form method="post" id="settingsForm">
    <?= csrf_field() ?>

    <div class="admin-layout">
        <div class="card form-card">
            <h2><?= icon('pin',18) ?> Hudud (viloyat / tuman)</h2>
            <label class="field"><span>Viloyat / shahar</span>
                <select name="region" id="regionSel">
                    <?php foreach (array_keys($regions) as $rname): ?>
                        <option value="<?= e($rname) ?>" <?= $region === $rname ? 'selected' : '' ?>><?= e($rname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Tuman (ixtiyoriy)</span>
                <select name="district" id="districtSel">
                    <option value="">— barcha tumanlar —</option>
                </select>
            </label>
            <div class="alert info"><?= icon('nav',16) ?> Hudud tanlansa, xarita avtomatik o'sha joyga o'tadi.</div>

            <h2 style="margin-top:18px"><?= icon('wallet',18) ?> Narxlar</h2>
            <label class="field"><span>Shahar ichi — 1 km narxi (so'm)</span>
                <input type="number" name="price_in_city" value="<?= (int)$priceIn ?>" min="0" step="100" required>
            </label>
            <label class="field"><span>Shahar tashqarisi — 1 km narxi (so'm)</span>
                <input type="number" name="price_out_city" value="<?= (int)$priceOut ?>" min="0" step="100" required>
            </label>
            <label class="field"><span>Minimal yetkazib berish haqi (so'm)</span>
                <input type="number" name="min_fee" value="<?= (int)$minFee ?>" min="0" step="500">
            </label>
            <label class="field"><span>Admin komissiyasi (%)</span>
                <input type="number" name="commission_percent" value="<?= (int)$commission ?>" min="0" max="100" step="1">
            </label>
            <label class="field"><span>Keshbek (%) — yangi buyurtmalar uchun standart</span>
                <input type="number" name="cashback_percent" value="<?= (int)$cashbackPct ?>" min="0" max="100" step="1">
            </label>
            <div class="alert info"><?= icon('wallet',16) ?> Bu foiz yangi buyurtmalarga avtomatik qo'llanadi. Har bir buyurtma uchun alohida keshbekni "Buyurtmalar" sahifasidan o'zgartirishingiz mumkin.</div>
            <div class="alert info"><?= icon('route',16) ?> Manzil shahar chizig'i ichida bo'lsa "shahar ichi" narxi, tashqarisida bo'lsa "shahar tashqarisi" narxi qo'llanadi.</div>

            <label class="field" style="margin-top:8px"><span>Ombor nomi</span>
                <input type="text" name="store_name" value="<?= e($storeName) ?>">
            </label>

            <button class="btn primary block" style="margin-top:8px"><?= icon('check',16) ?> Saqlash</button>
        </div>

        <div class="card">
            <h2 style="margin-bottom:12px"><?= icon('pin',18) ?> Xarita: shahar chegarasi va ombor</h2>
            <div class="map-head">
                <span class="muted small">Shahar markazini chiziq bilan belgilang</span>
                <div style="display:flex;gap:6px">
                    <button type="button" class="btn ghost sm" id="drawBtn"><?= icon('edit',15) ?> Chizishni boshlash</button>
                    <button type="button" class="btn ghost sm" id="clearBtn"><?= icon('trash',15) ?> Tozalash</button>
                </div>
            </div>
            <div class="map-head" style="margin-top:6px">
                <span class="muted small">📦 Ombor nuqtasini bosib belgilang (alohida)</span>
                <button type="button" class="btn ghost sm" id="storeBtn"><?= icon('box',15) ?> Ombor rejimi</button>
            </div>
            <div id="map" style="height:420px"></div>

            <input type="hidden" name="city_polygon" id="cityPolygon" value='<?= e($cityPoly) ?>'>
            <input type="hidden" name="map_lat" id="mapLat" value="<?= $mapLat ?>">
            <input type="hidden" name="map_lng" id="mapLng" value="<?= $mapLng ?>">
            <input type="hidden" name="map_zoom" id="mapZoom" value="<?= $mapZoom ?>">
            <input type="hidden" name="store_lat" id="lat" value="<?= $storeLat ?>">
            <input type="hidden" name="store_lng" id="lng" value="<?= $storeLng ?>">

            <p class="coords" id="modeInfo">🟢 Rejim: ko'rish</p>
            <p class="coords" id="coords">📦 Ombor: <?= $storeLat ?>, <?= $storeLng ?></p>
        </div>
    </div>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Viloyat -> tuman ma'lumotlari (markaz koordinatasi bilan)
window.UZ_REGIONS = <?= json_encode($regions, JSON_UNESCAPED_UNICODE) ?>;
window.SETTINGS_INIT = {
  region: <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>,
  district: <?= json_encode($district, JSON_UNESCAPED_UNICODE) ?>,
  mapLat: <?= $mapLat ?>, mapLng: <?= $mapLng ?>, mapZoom: <?= $mapZoom ?>,
  storeLat: <?= $storeLat ?>, storeLng: <?= $storeLng ?>
};
</script>
<script src="/assets/js/admin-settings.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
