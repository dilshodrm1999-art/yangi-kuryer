<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = ''; $errors = [];
$weekDays = [1 => 'Du', 2 => 'Se', 3 => 'Ch', 4 => 'Pa', 5 => 'Ju', 6 => 'Sh', 7 => 'Ya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $theme    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_color'] ?? '') ? $_POST['theme_color'] : '#ff6b35';
        $address  = trim($_POST['address'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $lat      = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
        $lng      = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
        $open     = preg_match('/^\d{2}:\d{2}$/', $_POST['open_time'] ?? '')  ? $_POST['open_time'].':00'  : '09:00:00';
        $close    = preg_match('/^\d{2}:\d{2}$/', $_POST['close_time'] ?? '') ? $_POST['close_time'].':00' : '22:00:00';
        $discount = max(0, min(100, (float)($_POST['discount_percent'] ?? 0)));
        $active   = isset($_POST['is_active']) ? 1 : 0;
        $ownerId  = (int)($_POST['owner_id'] ?? 0) ?: null;

        // Mavjud rasmlar (tahrirlashda saqlash uchun)
        $curImg = $curLogo = $curCover = '';
        if ($id) {
            $g = db()->prepare('SELECT image, logo, cover FROM stores WHERE id=?');
            $g->execute([$id]); $cur = $g->fetch() ?: [];
            $curImg = $cur['image'] ?? ''; $curLogo = $cur['logo'] ?? ''; $curCover = $cur['cover'] ?? '';
        }
        // Rasm: local fayl yoki URL
        $upErr = null;
        $image = resolve_image_input('image_file', 'image', 'stores', $curImg, $upErr);
        if ($upErr) $errors[] = 'Rasm: ' . $upErr;
        $logo  = resolve_image_input('logo_file',  'logo',  'stores', $curLogo, $upErr);
        if ($upErr) $errors[] = 'Logo: ' . $upErr;
        $cover = resolve_image_input('cover_file', 'cover', 'stores', $curCover, $upErr);
        if ($upErr) $errors[] = 'Banner: ' . $upErr;

        // Ish kunlari (massiv -> "1,2,3")
        $days = array_filter(array_map('intval', (array)($_POST['work_days'] ?? [])), fn($d) => $d >= 1 && $d <= 7);
        $workDays = $days ? implode(',', $days) : '1,2,3,4,5,6,7';

        // --- Yangi do'kon egasi akkaunti yaratish (ixtiyoriy) ---
        $newOwnerName  = trim($_POST['new_owner_name'] ?? '');
        $newOwnerPhone = trim($_POST['new_owner_phone'] ?? '');
        $newOwnerPass  = $_POST['new_owner_pass'] ?? '';
        if ($newOwnerName !== '' && $newOwnerPhone !== '' && $newOwnerPass !== '') {
            if (mb_strlen($newOwnerPass) < 5) {
                $errors[] = 'Egasi paroli kamida 5 belgidan iborat bo\'lsin.';
            } else {
                $chk = db()->prepare('SELECT id FROM users WHERE phone = ?');
                $chk->execute([$newOwnerPhone]);
                if ($chk->fetch()) {
                    $errors[] = 'Bu telefon raqami bilan foydalanuvchi allaqachon mavjud.';
                } else {
                    db()->prepare('INSERT INTO users (name, phone, password, role) VALUES (?,?,?,"store")')
                        ->execute([$newOwnerName, $newOwnerPhone, password_hash($newOwnerPass, PASSWORD_DEFAULT)]);
                    $ownerId = (int)db()->lastInsertId();
                    $msg = 'Do\'kon egasi akkaunti yaratildi. ';
                }
            }
        }

        if (mb_strlen($name) < 2) {
            $errors[] = 'Do\'kon nomini kiriting.';
        }

        if (!$errors) {
            if ($id) {
                db()->prepare(
                    'UPDATE stores SET owner_id=?, name=?, description=?, image=?, logo=?, cover=?, theme_color=?,
                        address=?, phone=?, lat=?, lng=?, open_time=?, close_time=?, work_days=?, discount_percent=?, is_active=?
                     WHERE id=?'
                )->execute([$ownerId, $name, $desc, $image, $logo, $cover, $theme, $address, $phone, $lat, $lng, $open, $close, $workDays, $discount, $active, $id]);
                $msg .= 'Do\'kon yangilandi.';
            } else {
                db()->prepare(
                    'INSERT INTO stores (owner_id, name, description, image, logo, cover, theme_color, address, phone, lat, lng, open_time, close_time, work_days, discount_percent, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$ownerId, $name, $desc, $image, $logo, $cover, $theme, $address, $phone, $lat, $lng, $open, $close, $workDays, $discount, $active]);
                $msg .= 'Do\'kon qo\'shildi.';
            }
            // role='store' egaga aylantirilsa, rolini moslab qo'yamiz
            if ($ownerId) {
                db()->prepare('UPDATE users SET role = "store" WHERE id = ? AND role = "customer"')->execute([$ownerId]);
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM stores WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Do\'kon o\'chirildi.';
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE stores SET is_active = 1 - is_active WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Holat o\'zgartirildi.';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM stores WHERE id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}

$stores = db()->query(
    'SELECT s.*, u.name AS owner_name, u.phone AS owner_phone,
            (SELECT COUNT(*) FROM products p WHERE p.store_id=s.id) AS product_count
     FROM stores s LEFT JOIN users u ON u.id = s.owner_id
     ORDER BY s.created_at DESC'
)->fetchAll();

// Egasi sifatida tanlash mumkin bo'lgan foydalanuvchilar (store yoki band bo'lmagan)
$owners = db()->query(
    "SELECT u.id, u.name, u.phone, u.role,
            (SELECT s2.name FROM stores s2 WHERE s2.owner_id = u.id LIMIT 1) AS owns
     FROM users u WHERE u.role IN ('store','customer') ORDER BY u.role, u.name"
)->fetchAll();

$editDays = $edit ? array_map('intval', explode(',', $edit['work_days'] ?? '1,2,3,4,5,6,7')) : [1,2,3,4,5,6,7];
$mapLat = (float)setting('map_lat', 41.3111);
$mapLng = (float)setting('map_lng', 69.2797);
$mapZoom = (int)setting('map_zoom', 12);
$eLat = $edit && $edit['lat'] !== null ? (float)$edit['lat'] : $mapLat;
$eLng = $edit && $edit['lng'] !== null ? (float)$edit['lng'] : $mapLng;

$pageTitle = 'Do\'konlar (admin)';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Do'konlar / Fastfudlar 🏪</h1>
<p class="page-sub">Do'kon qo'shing, egasini biriktiring, ish vaqti, brending va joylashuvini belgilang.</p>

<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= icon('x',16) ?><?= e($er) ?></div><?php endforeach; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2><?= $edit ? icon('edit',18).' Tahrirlash' : icon('plus',18).' Yangi do\'kon' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

            <label class="field"><span>Nomi</span><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
            <label class="field"><span>Tavsif</span><textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></label>

            <h3 class="form-sub"><?= icon('palette',16) ?> Brending</h3>
            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Logotip</span>
                <div class="img-preview <?= !empty($edit['logo']) ? 'has' : '' ?>" id="prevLogo" style="<?= !empty($edit['logo']) ? "background-image:url('".e($edit['logo'])."')" : '' ?>"></div>
                <input type="file" name="logo_file" accept="image/*" class="js-file" data-preview="prevLogo">
                <input type="text" name="logo" value="<?= e($edit['logo'] ?? '') ?>" placeholder="yoki logo URL">
            </div>
            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Sarlavha (banner)</span>
                <div class="img-preview wide <?= !empty($edit['cover']) ? 'has' : '' ?>" id="prevCover" style="<?= !empty($edit['cover']) ? "background-image:url('".e($edit['cover'])."')" : '' ?>"></div>
                <input type="file" name="cover_file" accept="image/*" class="js-file" data-preview="prevCover">
                <input type="text" name="cover" value="<?= e($edit['cover'] ?? '') ?>" placeholder="yoki banner URL">
            </div>
            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Asosiy rasm</span>
                <div class="img-preview <?= !empty($edit['image']) ? 'has' : '' ?>" id="prevImg" style="<?= !empty($edit['image']) ? "background-image:url('".e($edit['image'])."')" : '' ?>"></div>
                <input type="file" name="image_file" accept="image/*" class="js-file" data-preview="prevImg">
                <input type="text" name="image" value="<?= e($edit['image'] ?? '') ?>" placeholder="yoki rasm URL">
            </div>
            <label class="field"><span>Do'kon rangi (theme)</span><input type="color" name="theme_color" value="<?= e($edit['theme_color'] ?? '#ff6b35') ?>" style="height:44px;padding:4px"></label>

            <h3 class="form-sub"><?= icon('user',16) ?> Egasi (do'kon paneliga kiradi)</h3>
            <label class="field"><span>Mavjud egani tanlash</span>
                <select name="owner_id">
                    <option value="0">— biriktirilmagan —</option>
                    <?php foreach ($owners as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= ($edit['owner_id'] ?? 0) == $o['id'] ? 'selected' : '' ?>>
                            <?= e($o['name']) ?> (<?= e($o['phone']) ?>)<?= $o['owns'] ? ' · '.e($o['owns']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <details class="new-owner">
                <summary>➕ Yangi egasi akkaunti yaratish</summary>
                <label class="field"><span>Egasi ismi</span><input type="text" name="new_owner_name" placeholder="Masalan: Akmal aka"></label>
                <label class="field"><span>Telefon (login)</span><input type="tel" name="new_owner_phone" placeholder="+998..."></label>
                <label class="field"><span>Parol</span><input type="text" name="new_owner_pass" placeholder="kamida 5 belgi"></label>
                <p class="muted small">To'ldirilsa, yangi "do'kon" akkaunti yaratilib shu do'konga biriktiriladi.</p>
            </details>

            <h3 class="form-sub"><?= icon('clock',16) ?> Ish vaqti va aloqa</h3>
            <label class="field"><span>Manzil</span><input type="text" name="address" value="<?= e($edit['address'] ?? '') ?>"></label>
            <label class="field"><span>Telefon</span><input type="tel" name="phone" value="<?= e($edit['phone'] ?? '') ?>" placeholder="+998..."></label>

            <div class="grid-2">
                <label class="field"><span>Ochilish vaqti</span>
                    <input type="time" name="open_time" value="<?= e(substr($edit['open_time'] ?? '09:00:00', 0, 5)) ?>">
                </label>
                <label class="field"><span>Yopilish vaqti</span>
                    <input type="time" name="close_time" value="<?= e(substr($edit['close_time'] ?? '22:00:00', 0, 5)) ?>">
                </label>
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

            <label class="field"><span>Chegirma (%) — do'kon bo'yicha</span>
                <input type="number" name="discount_percent" value="<?= (float)($edit['discount_percent'] ?? 0) ?>" min="0" max="100" step="1">
            </label>
            <label class="check"><input type="checkbox" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Faol (sotuvda)</label>

            <div class="map-box" style="margin-top:12px">
                <div class="map-head">
                    <span><?= icon('pin',16) ?> Do'kon joylashuvi</span>
                </div>
                <div id="map" style="height:240px"></div>
                <input type="hidden" name="lat" id="lat" value="<?= $edit && $edit['lat'] !== null ? $eLat : '' ?>">
                <input type="hidden" name="lng" id="lng" value="<?= $edit && $edit['lng'] !== null ? $eLng : '' ?>">
                <p class="coords" id="coords"><?= ($edit && $edit['lat'] !== null) ? '📍 '.$eLat.', '.$eLng : 'Xaritaga bosib belgilang' ?></p>
            </div>

            <button class="btn primary block" style="margin-top:12px"><?= $edit ? 'Saqlash' : 'Qo\'shish' ?></button>
            <?php if ($edit): ?><a class="btn ghost block" href="/admin/stores.php">Bekor qilish</a><?php endif; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Logo</th><th>Nomi</th><th>Egasi</th><th>Ish vaqti</th><th>Mahsulot</th><th>Holat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($stores as $s):
                $open = store_is_open($s); ?>
                <tr>
                    <td><div class="thumb" style="background-image:url('<?= e($s['logo'] ?: $s['image']) ?>')"></div></td>
                    <td>
                        <strong><?= e($s['name']) ?></strong>
                        <div class="muted small"><?= e($s['address'] ?? '') ?></div>
                        <a class="muted small" href="/store_view.php?id=<?= $s['id'] ?>" target="_blank">🔗 Do'kon oynasi</a>
                    </td>
                    <td>
                        <?php if ($s['owner_name']): ?>
                            <?= e($s['owner_name']) ?><div class="muted small"><?= e($s['owner_phone']) ?></div>
                        <?php else: ?><span class="muted">— yo'q —</span><?php endif; ?>
                    </td>
                    <td>
                        <?= e(store_hours_label($s)) ?>
                        <div class="<?= $open ? 'tag fee' : 'tag' ?>" style="margin-top:4px"><?= $open ? '🟢 Ochiq' : '🔴 Yopiq' ?></div>
                    </td>
                    <td><?= (int)$s['product_count'] ?></td>
                    <td><?= $s['is_active'] ? '🟢 Faol' : '🔴 Yopilgan' ?></td>
                    <td class="row-actions">
                        <a class="btn sm" href="?edit=<?= $s['id'] ?>"><?= icon('edit',15) ?></a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn sm" title="Faollik"><?= $s['is_active'] ? icon('x',15) : icon('check',15) ?></button>
                        </form>
                        <form method="post" data-confirm="Do'kon o'chirilsinmi? Mahsulotlar do'konsiz qoladi.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn sm danger"><?= icon('trash',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$stores): ?><tr><td colspan="7" class="muted">Do'konlar yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var def=[<?= $eLat ?>,<?= $eLng ?>];
  var map=L.map('map').setView(def,<?= $mapZoom ?>);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'\u00a9 OpenStreetMap'}).addTo(map);
  var latEl=document.getElementById('lat'), lngEl=document.getElementById('lng'), coordsEl=document.getElementById('coords');
  var m=null;
  <?php if ($edit && $edit['lat'] !== null): ?>
  m=L.marker(def,{draggable:true}).addTo(map);
  bind(m);
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
<script src="/assets/js/admin-uploads.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
