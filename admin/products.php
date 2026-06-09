<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $price     = (float)($_POST['price'] ?? 0);
        $catId     = (int)($_POST['category_id'] ?? 0) ?: null;
        $image     = trim($_POST['image'] ?? '');
        $available = isset($_POST['is_available']) ? 1 : 0;

        if ($name !== '') {
            if ($id) {
                db()->prepare('UPDATE products SET name=?, description=?, price=?, category_id=?, image=?, is_available=? WHERE id=?')
                    ->execute([$name, $desc, $price, $catId, $image, $available, $id]);
                $msg = 'Mahsulot yangilandi.';
            } else {
                db()->prepare('INSERT INTO products (name, description, price, category_id, image, is_available) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $desc, $price, $catId, $image, $available]);
                $msg = 'Mahsulot qo\'shildi.';
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM products WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Mahsulot o\'chirildi.';
    } elseif ($action === 'add_category') {
        $cname = trim($_POST['cat_name'] ?? '');
        if ($cname !== '') {
            db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$cname]);
            $msg = 'Kategoriya qo\'shildi.';
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$products = db()->query(
    'SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC'
)->fetchAll();

$pageTitle = 'Mahsulotlar (admin)';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Mahsulotlar 🍔</h1>
<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>

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
            <?php if ($edit): ?><a class="btn ghost block" href="/admin/products.php">Bekor qilish</a><?php endif; ?>
        </form>
        <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
        <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="cat_name" placeholder="Yangi kategoriya">
            <button class="btn sm"><?= icon('plus',15) ?></button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Rasm</th><th>Nomi</th><th>Kategoriya</th><th>Narx</th><th>Holat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><div class="thumb" style="background-image:url('<?= e($p['image']) ?>')"></div></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td><?= money($p['price']) ?></td>
                    <td><?= $p['is_available'] ? '🟢' : '🔴' ?></td>
                    <td class="row-actions">
                        <a class="btn sm" href="?edit=<?= $p['id'] ?>"><?= icon('edit',15) ?></a>
                        <form method="post" data-confirm="O'chirilsinmi?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn sm danger"><?= icon('trash',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
