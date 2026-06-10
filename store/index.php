<?php
require_once __DIR__ . '/../includes/functions.php';
$store = require_store_owner();
$sid = (int)$store['id'];

$stats = [
    'products'  => db()->prepare('SELECT COUNT(*) FROM products WHERE store_id=?'),
    'available' => db()->prepare("SELECT COUNT(*) FROM products WHERE store_id=? AND is_available=1"),
    'sections'  => db()->prepare('SELECT COUNT(*) FROM store_sections WHERE store_id=?'),
];
foreach ($stats as $k => $st) { $st->execute([$sid]); $stats[$k] = (int)$st->fetchColumn(); }

// Do'kon mahsulotlari sotilgan buyurtmalar soni (taxminiy: order_items orqali)
$soldStmt = db()->prepare(
    'SELECT COUNT(DISTINCT oi.order_id)
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE p.store_id = ?'
);
$soldStmt->execute([$sid]);
$soldOrders = (int)$soldStmt->fetchColumn();

$isOpen = store_is_open($store);
$pageTitle = $store['name'] . ' — panel';
require __DIR__ . '/../includes/header.php';
?>
<div class="store-hero" style="--theme: <?= e($store['theme_color'] ?: '#ff6b35') ?>">
    <?php if (!empty($store['cover'])): ?>
        <div class="store-hero-cover" style="background-image:url('<?= e($store['cover']) ?>')"></div>
    <?php endif; ?>
    <div class="store-hero-body">
        <div class="store-logo" style="background-image:url('<?= e($store['logo'] ?: $store['image'] ?: '') ?>')">
            <?= ($store['logo'] || $store['image']) ? '' : icon('store',30) ?>
        </div>
        <div>
            <h1><?= e($store['name']) ?></h1>
            <p class="muted"><?= e($store['address'] ?? '') ?></p>
            <div class="hero-tags">
                <span class="tag <?= $isOpen ? 'zone-in' : 'zone-out' ?>"><?= $isOpen ? '🟢 Hozir ochiq' : '🔴 Hozir yopiq' ?></span>
                <span class="tag"><?= icon('clock',13) ?> <?= e(store_hours_label($store)) ?></span>
                <?php if ($store['discount_percent'] > 0): ?><span class="tag dist">-<?= (float)$store['discount_percent'] ?>%</span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="stats" style="margin-top:18px">
    <div class="stat"><div class="si"><?= icon('package',20) ?></div><div class="stat-num"><?= $stats['products'] ?></div><div class="stat-label">Mahsulotlar</div></div>
    <div class="stat"><div class="si" style="background:#ecfdf3;color:var(--green)"><?= icon('check',20) ?></div><div class="stat-num"><?= $stats['available'] ?></div><div class="stat-label">Sotuvda</div></div>
    <div class="stat"><div class="si" style="background:#f3e8ff;color:var(--purple)"><?= icon('layers',20) ?></div><div class="stat-num"><?= $stats['sections'] ?></div><div class="stat-label">Bo'limlar</div></div>
    <div class="stat"><div class="si" style="background:#eff6ff;color:var(--blue)"><?= icon('list',20) ?></div><div class="stat-num"><?= $soldOrders ?></div><div class="stat-label">Buyurtmalar</div></div>
</div>

<div class="quick-links">
    <a class="btn primary" href="/store/products.php"><?= icon('plus',16) ?> Mahsulot qo'shish</a>
    <a class="btn" href="/store/sections.php"><?= icon('layers',16) ?> Bo'limlar</a>
    <a class="btn" href="/store/profile.php"><?= icon('palette',16) ?> Brending / ish vaqti</a>
    <a class="btn" href="/store_view.php?id=<?= $sid ?>" target="_blank"><?= icon('store',16) ?> Do'kon oynasi</a>
</div>

<?php if (!$stats['products']): ?>
    <div class="alert info" style="margin-top:18px"><?= icon('package',16) ?> Hali mahsulot qo'shmagansiz. <a href="/store/products.php">Birinchi mahsulotni qo'shing →</a></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
