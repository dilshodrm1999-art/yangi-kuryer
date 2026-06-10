<?php
require_once __DIR__ . '/includes/functions.php';

// Admin/kuryer/do'kon kirsa, o'z paneliga (faqat mijoz va mehmonlar uchun)
if (is_logged_in() && !in_array(current_user()['role'], ['customer', 'store'], true)) {
    redirect(role_home(current_user()['role']));
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$store = $stmt->fetch();

if (!$store) {
    http_response_code(404);
    $pageTitle = 'Do\'kon topilmadi';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card" style="text-align:center;color:var(--muted)">Bunday do\'kon topilmadi 🔍 <br><a href="/index.php">Bosh sahifaga</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$open = store_is_open($store);
$theme = $store['theme_color'] ?: '#ff6b35';

// Bo'limlar
$secStmt = db()->prepare('SELECT * FROM store_sections WHERE store_id=? ORDER BY sort_order, name');
$secStmt->execute([$id]);
$sections = $secStmt->fetchAll();

// Mahsulotlar (faqat sotuvda)
$prodStmt = db()->prepare(
    'SELECT * FROM products WHERE store_id=? AND is_available=1 ORDER BY created_at DESC'
);
$prodStmt->execute([$id]);
$products = $prodStmt->fetchAll();

// Mahsulotlarni bo'limlarga guruhlash
$bySection = [];
$noSection = [];
foreach ($products as $p) {
    if (!empty($p['section_id'])) {
        $bySection[(int)$p['section_id']][] = $p;
    } else {
        $noSection[] = $p;
    }
}

$storeDisc = (float)$store['discount_percent'];

// Kartani chizadigan yordamchi (DRY)
function render_store_product(array $p, float $storeDisc, bool $open): void
{
    $disc = max((float)$p['discount_percent'], $storeDisc);
    $finalPrice = discounted_price($p['price'], $disc);
    ?>
    <div class="card product">
        <div class="product-img" style="background-image:url('<?= e($p['image'] ?: 'https://via.placeholder.com/400x300?text=Mahsulot') ?>')">
            <?php if ($disc > 0): ?><span class="chip disc">-<?= (float)$disc ?>%</span><?php endif; ?>
        </div>
        <div class="product-body">
            <h3><?= e($p['name']) ?></h3>
            <p class="muted small"><?= e($p['description']) ?></p>
            <div class="product-foot">
                <span class="price">
                    <?php if ($disc > 0): ?><span class="old-price"><?= money($p['price']) ?></span><?php endif; ?>
                    <?= money($finalPrice) ?>
                </span>
                <?php if (is_logged_in() && current_user()['role'] === 'customer'): ?>
                    <button class="add-btn js-add" type="button" data-product-id="<?= $p['id'] ?>" <?= $open ? '' : 'disabled title="Do\'kon yopiq"' ?> title="Savatga"><?= icon('plus', 20) ?></button>
                <?php elseif (!is_logged_in()): ?>
                    <a class="add-btn" href="/login.php" title="Kirish"><?= icon('plus', 20) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

$pageTitle = $store['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="store-window" style="--theme: <?= e($theme) ?>">
    <div class="sw-cover" style="background-image:url('<?= e($store['cover'] ?: $store['image'] ?? '') ?>')">
        <a class="sw-back" href="/index.php"><?= icon('home',18) ?></a>
    </div>
    <div class="sw-head">
        <div class="sw-logo" style="background-image:url('<?= e($store['logo'] ?: $store['image'] ?? '') ?>')">
            <?= ($store['logo'] || $store['image']) ? '' : icon('store',34) ?>
        </div>
        <div class="sw-info">
            <h1><?= e($store['name']) ?></h1>
            <?php if ($store['description']): ?><p class="muted"><?= e($store['description']) ?></p><?php endif; ?>
            <div class="hero-tags">
                <span class="tag <?= $open ? 'zone-in' : 'zone-out' ?>"><?= $open ? '🟢 Ochiq' : '🔴 Yopiq' ?></span>
                <span class="tag"><?= icon('clock',13) ?> <?= e(store_hours_label($store)) ?></span>
                <?php if ($storeDisc > 0): ?><span class="tag dist">Chegirma -<?= (float)$storeDisc ?>%</span><?php endif; ?>
                <?php if ($store['phone']): ?><a class="tag" href="tel:<?= e($store['phone']) ?>"><?= icon('phone',13) ?> <?= e($store['phone']) ?></a><?php endif; ?>
                <?php if ($store['address']): ?><span class="tag"><?= icon('pin',13) ?> <?= e($store['address']) ?></span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$open): ?>
    <div class="alert info"><?= icon('clock',16) ?> Do'kon hozir yopiq. Ish vaqti: <?= e(store_hours_label($store)) ?>. Buyurtma ish vaqtida qabul qilinadi.</div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="card" style="text-align:center;color:var(--muted)">Bu do'konda hozircha mahsulot yo'q 🤷</div>
<?php endif; ?>

<?php if (count($sections) > 1 || (count($sections) >= 1 && $noSection)): ?>
<div class="cat-scroll sw-sections">
    <?php foreach ($sections as $sec): if (empty($bySection[$sec['id']])) continue; ?>
        <a class="cat-pill" href="#sec-<?= $sec['id'] ?>"><?= e($sec['name']) ?></a>
    <?php endforeach; ?>
    <?php if ($noSection): ?><a class="cat-pill" href="#sec-other">Boshqa</a><?php endif; ?>
</div>
<?php endif; ?>

<?php foreach ($sections as $sec):
    if (empty($bySection[$sec['id']])) continue; ?>
    <h2 class="sub" id="sec-<?= $sec['id'] ?>"><?= icon('layers',18) ?> <?= e($sec['name']) ?></h2>
    <div class="grid">
        <?php foreach ($bySection[$sec['id']] as $p) render_store_product($p, $storeDisc, $open); ?>
    </div>
<?php endforeach; ?>

<?php if ($noSection): ?>
    <?php if ($sections): ?><h2 class="sub" id="sec-other"><?= icon('package',18) ?> Boshqa mahsulotlar</h2><?php endif; ?>
    <div class="grid">
        <?php foreach ($noSection as $p) render_store_product($p, $storeDisc, $open); ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
