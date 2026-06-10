<?php
require_once __DIR__ . '/includes/functions.php';

// Admin/kuryer kirsa, o'z paneliga
if (is_logged_in() && current_user()['role'] !== 'customer') {
    redirect(role_home(current_user()['role']));
}

$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$storeId = isset($_GET['store']) ? (int)$_GET['store'] : 0;
$q     = trim($_GET['q'] ?? '');

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$stores = db()->query('SELECT * FROM stores WHERE is_active = 1 ORDER BY name')->fetchAll();

$sql    = 'SELECT p.*, c.name AS category, s.name AS store_name, s.discount_percent AS store_discount,
                  s.open_time, s.close_time, s.work_days, s.is_active AS store_active
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
           LEFT JOIN stores s ON s.id = p.store_id
           WHERE p.is_available = 1';
$params = [];
if ($catId)   { $sql .= ' AND p.category_id = ?'; $params[] = $catId; }
if ($storeId) { $sql .= ' AND p.store_id = ?';    $params[] = $storeId; }
if ($q)       { $sql .= ' AND p.name LIKE ?';     $params[] = "%$q%"; }
$sql .= ' ORDER BY p.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'Mahsulotlar';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Nimani buyurtma qilamiz? 🍔</h1>
<p class="page-sub">Tanlang, savatga soling va manzilingizga yetkazib beramiz.</p>

<form class="searchbar" method="get">
    <?= icon('search', 20) ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Mahsulot qidirish...">
    <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
    <button class="btn primary sm" type="submit">Qidirish</button>
</form>

<div class="cat-scroll">
    <a class="cat-pill <?= $catId === 0 ? 'active' : '' ?>" href="/index.php<?= $q ? '?q='.urlencode($q) : '' ?>">Barchasi</a>
    <?php foreach ($categories as $c): ?>
        <a class="cat-pill <?= $catId === (int)$c['id'] ? 'active' : '' ?>"
           href="?cat=<?= $c['id'] ?><?= $q ? '&q='.urlencode($q) : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($stores): ?>
<h2 class="sub"><?= icon('box',18) ?> Do'konlar / Fastfudlar</h2>
<div class="store-scroll">
    <a class="store-card <?= $storeId === 0 ? 'active' : '' ?>" href="/index.php">
        <div class="store-ic"><?= icon('list',22) ?></div>
        <span>Barchasi</span>
    </a>
    <?php foreach ($stores as $s):
        $open = store_is_open($s); ?>
        <a class="store-card <?= $storeId === (int)$s['id'] ? 'active' : '' ?>" href="?store=<?= $s['id'] ?>">
            <div class="store-img" style="background-image:url('<?= e($s['image'] ?: 'https://via.placeholder.com/120?text=Do%27kon') ?>')">
                <span class="store-status <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'Ochiq' : 'Yopiq' ?></span>
                <?php if ($s['discount_percent'] > 0): ?><span class="store-disc">-<?= (float)$s['discount_percent'] ?>%</span><?php endif; ?>
            </div>
            <strong><?= e($s['name']) ?></strong>
            <span class="muted small"><?= icon('clock',12) ?> <?= e(store_hours_label($s)) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="card" style="text-align:center;color:var(--muted)">Mahsulot topilmadi 🔍</div>
<?php endif; ?>

<div class="grid">
    <?php foreach ($products as $p):
        // Eng yaxshi chegirma: mahsulot yoki do'kon chegirmasi (kattasi)
        $disc = max((float)($p['discount_percent'] ?? 0), (float)($p['store_discount'] ?? 0));
        $finalPrice = discounted_price($p['price'], $disc);
        $storeOpen = $p['store_id'] ? store_is_open([
            'is_active'  => $p['store_active'] ?? 1,
            'open_time'  => $p['open_time'] ?? null,
            'close_time' => $p['close_time'] ?? null,
            'work_days'  => $p['work_days'] ?? '1,2,3,4,5,6,7',
        ]) : true;
    ?>
        <div class="card product">
            <div class="product-img" style="background-image:url('<?= e($p['image'] ?: 'https://via.placeholder.com/400x300?text=Mahsulot') ?>')">
                <span class="chip"><?= e($p['category'] ?? 'Boshqa') ?></span>
                <?php if ($disc > 0): ?><span class="chip disc">-<?= (float)$disc ?>%</span><?php endif; ?>
            </div>
            <div class="product-body">
                <h3><?= e($p['name']) ?></h3>
                <?php if ($p['store_name']): ?>
                    <p class="store-tag"><?= icon('box',13) ?> <?= e($p['store_name']) ?>
                        <?php if (!$storeOpen): ?><span class="tag closed-tag">Yopiq</span><?php endif; ?>
                    </p>
                <?php endif; ?>
                <p class="muted small"><?= e($p['description']) ?></p>
                <div class="product-foot">
                    <span class="price">
                        <?php if ($disc > 0): ?>
                            <span class="old-price"><?= money($p['price']) ?></span>
                        <?php endif; ?>
                        <?= money($finalPrice) ?>
                    </span>
                    <?php if (is_logged_in()): ?>
                        <button class="add-btn js-add" type="button" data-product-id="<?= $p['id'] ?>" <?= $storeOpen ? '' : 'disabled title="Do\'kon yopiq"' ?> title="Savatga"><?= icon('plus', 20) ?></button>
                    <?php else: ?>
                        <a class="add-btn" href="/login.php" title="Kirish"><?= icon('plus', 20) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
