<?php
require_once __DIR__ . '/includes/functions.php';

// Admin/kuryer kirsa, o'z paneliga yo'naltiramiz
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
<h1 class="page-title">Mahsulotlar</h1>

<form class="filters" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Qidirish...">
    <select name="cat" onchange="this.form.submit()">
        <option value="0">Barcha kategoriyalar</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Qidirish</button>
</form>

<?php if (!$products): ?>
    <p class="muted">Mahsulot topilmadi.</p>
<?php endif; ?>

<div class="grid">
    <?php foreach ($products as $p): ?>
        <div class="card product">
            <div class="product-img" style="background-image:url('<?= e($p['image'] ?: 'https://via.placeholder.com/400x300?text=Mahsulot') ?>')"></div>
            <div class="product-body">
                <span class="chip"><?= e($p['category'] ?? 'Boshqa') ?></span>
                <h3><?= e($p['name']) ?></h3>
                <p class="muted small"><?= e($p['description']) ?></p>
                <div class="product-foot">
                    <span class="price"><?= money($p['price']) ?></span>
                    <?php if (is_logged_in()): ?>
                        <form method="post" action="/cart.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button class="btn primary" type="submit">Savatga +</button>
                        </form>
                    <?php else: ?>
                        <a class="btn primary" href="/login.php">Savatga +</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
