<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (mb_strlen($name) < 2) $errors[] = 'Ism kiriting.';
        if (!preg_match('/^\+?\d{7,15}$/', $phone)) $errors[] = 'Telefon noto\'g\'ri.';
        if (mb_strlen($pass) < 5) $errors[] = 'Parol kamida 5 belgi.';

        if (!$errors) {
            $chk = db()->prepare('SELECT id FROM users WHERE phone=?');
            $chk->execute([$phone]);
            if ($chk->fetch()) {
                $errors[] = 'Bu telefon band.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO users (name, phone, password, role) VALUES (?,?,?,"courier")'
                );
                $stmt->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
                $msg = 'Kuryer qo\'shildi.';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=? AND role="courier"');
        $stmt->execute([$id]);
        $msg = 'Holat o\'zgartirildi.';
    }
}

// Kuryerlar + statistika
$couriers = db()->query(
    'SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id) AS total_orders,
            (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id AND o.status="delivered") AS done_orders
     FROM users u WHERE u.role="courier" ORDER BY u.created_at DESC'
)->fetchAll();

$pageTitle = 'Kuryerlar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Kuryerlar boshqaruvi</h1>
<?php if ($msg): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2>Yangi kuryer ulash</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Ism <input type="text" name="name" required></label>
            <label>Telefon <input type="text" name="phone" placeholder="+998..." required></label>
            <label>Parol <input type="text" name="password" required></label>
            <button class="btn primary block">+ Kuryer qo'shish</button>
        </form>
    </div>

    <div class="products-list">
        <table class="table">
            <thead><tr><th>Ism</th><th>Telefon</th><th>Buyurtmalar</th><th>Yetkazgan</th><th>Holat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($couriers as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['phone']) ?></td>
                    <td><?= $c['total_orders'] ?></td>
                    <td><?= $c['done_orders'] ?></td>
                    <td><?= $c['is_active'] ? '🟢 Faol' : '🔴 Bloklangan' ?></td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn small"><?= $c['is_active'] ? 'Bloklash' : 'Faollashtirish' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$couriers): ?><tr><td colspan="6" class="muted">Kuryerlar yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
