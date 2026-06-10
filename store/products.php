<?php
require_once __DIR__ . '/../includes/functions.php';
$store = require_store_owner();
$sid = (int)$store['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $price     = (float)($_POST['price'] ?? 0);
        $discount  = max(0, min(100, (float)($_POST['discount_percent'] ?? 0)));
        $catId     = (int)($_POST['category_id'] ?? 0) ?: null;
        $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
        $image     = trim($_POST['image'] ?? '');
        $available = isset($_POST['is_available']) ? 1 : 0;

        // Bo'lim shu do'konga tegishli ekanini tekshiramiz (xavfsizlik)
        if ($sectionId) {
            $chk = db()->prepare('SELECT id FROM store_sections WHERE id=? AND store_id=?');
            $chk->execute([$sectionId, $sid]);
            if (!$chk->fetch()) { $sectionId = null; }
        }

        if ($name !== '') {
            if ($id) {
                // Faqat o'z do'koni mahsulotini yangilash mumkin
                db()->prepare(
                    'UPDATE products SET name=?, description=?, price=?, discount_percent=?, category_id=?, section_id=?, image=?, is_available=?
                     WHERE id=? AND store_id=?'
                )->execute([$name, $desc, $price, $discount, $catId, $sectionId, $image, $available, $id, $sid]);
                $msg = 'Mahsulot yangilandi.';
            } else {
                db()->prepare(
                    'INSERT INTO products (name, description, price, discount_percent, category_id, store_id, section_id, image, is_available)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$name, $desc, $price, $discount, $catId, $sid, $sectionId, $image, $available]);
                $msg = 'Mahsulot qo\'shildi.';
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM products WHERE id=? AND store_id=?')->execute([(int)$_POST['id'], $sid]);
        $msg = 'Mahsulot o\'chirildi.';
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE products SET is_available = 1 - is_available WHERE id=? AND store_id=?')->execute([(int)$_POST['id'], $sid]);
        $msg = 'Holat o\'zgartirildi.';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id=? AND store_id=?');
    $stmt->execute([$editId, $sid]);
    $edit = $stmt->fetch() ?: null;
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$secStmt = db()->prepare('SELECT * FROM store_sections WHERE store_id=? ORDER BY sort_order, name');
$secStmt->execute([$sid]);
$sections = $secStmt->fetchAll();

$prodStmt = db()->prepare(
    'SELECT p.*, c.name AS category, ss.name AS section_name
     FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     LEFT JOIN store_sections ss ON ss.id=p.section_id
     WHERE p.store_id=? ORDER BY p.created_at DESC'
);
$prodStmt->execute([$sid]);
$products = $prodStmt->fetchAll();

$pageTitle = 'Mahsulotlar — ' . $store['name'];
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Mening mahsulotlarim 🍔</h1>
<p class="page-sub"><?= e($store['name']) ?> uchun mahsulotlarni boshqaring.</p>

<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>
<?php if (!$sections): ?>
    <div class="alert info"><?= icon('layers',16) ?> Avval <a href="/store/sections.php">bo'lim qo'shing</a> (masalan "Lavashlar"), keyin mahsulotni bo'limga joylang.</div>
<?php endif; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2><?= $edit ? icon('edit',18).' Tahrirlash' : icon('plus',18).' Yangi mahsulot' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <label class="field"><span>Nomi</span><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
            <label class="field"><span>Tavsif</span><textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></label>
            <label class="field"><span>Narxi (so'm)</span><input type="number" step="100" name="price" value="<?= e($edit['price'] ?? '') ?>" required></label>
            <label class="field"><span>Chegirma (%)</span><input type="number" min="0" max="100" step="1" name="discount_percent" value="<?= e($edit['discount_percent'] ?? 0) ?>"></label>
            <label class="field"><span>Bo'lim</span>
                <select name="section_id">
                    <option value="0">— bo'limsiz —</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($edit['section_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Kategoriya</span>
                <select name="category_id">
                    <option value="0">— tanlanmagan —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Rasm URL</span><input type="text" name="image" value="<?= e($edit['image'] ?? '') ?>" placeholder="https://..."></label>
            <label class="check"><input type="checkbox" name="is_available" <?= ($edit['is_available'] ?? 1) ? 'checked' : '' ?>> Sotuvda mavjud</label>
            <button class="btn primary block" style="margin-top:12px"><?= $edit ? 'Saqlash' : 'Qo\'shish' ?></button>
            <?php if ($edit): ?><a class="btn ghost block" href="/store/products.php">Bekor qilish</a><?php endif; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Rasm</th><th>Nomi</th><th>Bo'lim</th><th>Narx</th><th>Holat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><div class="thumb" style="background-image:url('<?= e($p['image']) ?>')"></div></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['section_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['discount_percent'] > 0): ?>
                            <span class="old-price"><?= money($p['price']) ?></span>
                            <strong><?= money(discounted_price($p['price'], $p['discount_percent'])) ?></strong>
                            <span class="tag dist">-<?= (float)$p['discount_percent'] ?>%</span>
                        <?php else: ?>
                            <?= money($p['price']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $p['is_available'] ? '🟢' : '🔴' ?></td>
                    <td class="row-actions">
                        <a class="btn sm" href="?edit=<?= $p['id'] ?>"><?= icon('edit',15) ?></a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn sm" title="Sotuvda/yashirish"><?= $p['is_available'] ? icon('x',15) : icon('check',15) ?></button>
                        </form>
                        <form method="post" data-confirm="O'chirilsinmi?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn sm danger"><?= icon('trash',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="6" class="muted">Hali mahsulot yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
