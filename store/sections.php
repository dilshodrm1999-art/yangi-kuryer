<?php
require_once __DIR__ . '/../includes/functions.php';
$store = require_store_owner();
$sid = (int)$store['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($name !== '') {
            if ($id) {
                // Faqat o'z do'koniga tegishli bo'limni yangilash (xavfsizlik)
                db()->prepare('UPDATE store_sections SET name=?, sort_order=? WHERE id=? AND store_id=?')
                    ->execute([$name, $order, $id, $sid]);
                $msg = 'Bo\'lim yangilandi.';
            } else {
                db()->prepare('INSERT INTO store_sections (store_id, name, sort_order) VALUES (?,?,?)')
                    ->execute([$sid, $name, $order]);
                $msg = 'Bo\'lim qo\'shildi.';
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM store_sections WHERE id=? AND store_id=?')->execute([(int)$_POST['id'], $sid]);
        $msg = 'Bo\'lim o\'chirildi.';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM store_sections WHERE id=? AND store_id=?');
    $stmt->execute([$editId, $sid]);
    $edit = $stmt->fetch() ?: null;
}

$stmt = db()->prepare(
    'SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.section_id=s.id) AS product_count
     FROM store_sections s WHERE s.store_id=? ORDER BY s.sort_order, s.name'
);
$stmt->execute([$sid]);
$sections = $stmt->fetchAll();

$pageTitle = 'Bo\'limlar — ' . $store['name'];
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Do'kon bo'limlari <?= icon('layers',22) ?></h1>
<p class="page-sub">Menyuni bo'limlarga ajrating (masalan: Lavashlar, Ichimliklar, Shirinliklar).</p>

<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2><?= $edit ? icon('edit',18).' Tahrirlash' : icon('plus',18).' Yangi bo\'lim' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <label class="field"><span>Bo'lim nomi</span><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required placeholder="Masalan: Lavashlar"></label>
            <label class="field"><span>Tartib raqami (kichik birinchi)</span><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>" step="1"></label>
            <button class="btn primary block" style="margin-top:8px"><?= $edit ? 'Saqlash' : 'Qo\'shish' ?></button>
            <?php if ($edit): ?><a class="btn ghost block" href="/store/sections.php">Bekor qilish</a><?php endif; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tartib</th><th>Nomi</th><th>Mahsulot</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sections as $s): ?>
                <tr>
                    <td><?= (int)$s['sort_order'] ?></td>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= (int)$s['product_count'] ?></td>
                    <td class="row-actions">
                        <a class="btn sm" href="?edit=<?= $s['id'] ?>"><?= icon('edit',15) ?></a>
                        <form method="post" data-confirm="Bo'lim o'chirilsinmi? Mahsulotlar bo'limsiz qoladi.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn sm danger"><?= icon('trash',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sections): ?><tr><td colspan="4" class="muted">Hali bo'lim yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
