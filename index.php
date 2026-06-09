<?php
require_once __DIR__ . '/includes/functions.php';

// Admin/kuryer kirsa, o'z paneliga
if (is_logged_in() && current_user()['role'] !== 'customer') {
    redirect(role_home(current_user()['role']));
}

$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$q     = trim($_GET['q'] ?? '');

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$sql    = 'SELECT p.*, c.name AS category FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
           WHERE p.is_available = 1';
$params = [];
if ($catId) { $sql .= ' AND p.category_id = ?'; $params[] = $catId; }
if ($q)     { $sql .= ' AND p.name LIKE ?';     $params[] = "%$q%"; }
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

<?php if (!$products): ?>
    <div class="card" style="text-align:center;color:var(--muted)">Mahsulot topilmadi 🔍</div>
<?php endif; ?>

<div class="grid">
    <?php foreach ($products as $p): ?>
        <div class="card product">
            <div class="product-img" style="background-image:url('<?= e($p['image'] ?: 'https://via.placeholder.com/400x300?text=Mahsulot') ?>')">
                <span class="chip"><?= e($p['category'] ?? 'Boshqa') ?></span>
            </div>
            <div class="product-body">
                <h3><?= e($p['name']) ?></h3>
                <p class="muted small"><?= e($p['description']) ?></p>
                <div class="product-foot">
                    <span class="price"><?= money($p['price']) ?></span>
                    <?php if (is_logged_in()): ?>
                        <button class="add-btn js-add" type="button" data-product-id="<?= $p['id'] ?>" title="Savatga"><?= icon('plus', 20) ?></button>
                    <?php else: ?>
                        <a class="add-btn" href="/login.php" title="Kirish"><?= icon('plus', 20) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
