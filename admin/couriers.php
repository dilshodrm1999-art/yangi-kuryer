<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = ''; $errors = [];

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
                db()->prepare('INSERT INTO users (name, phone, password, role) VALUES (?,?,?,"courier")')
                    ->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
                $msg = 'Kuryer qo\'shildi.';
            }
        }
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=? AND role="courier"')
            ->execute([(int)$_POST['id']]);
        $msg = 'Holat o\'zgartirildi.';
    } elseif ($action === 'payout') {
        // Balansni to'lab, nolga tushiramiz
        db()->prepare('UPDATE users SET balance=0 WHERE id=? AND role="courier"')
            ->execute([(int)$_POST['id']]);
        $msg = 'To\'lov amalga oshirildi (balans nollandi).';
    }
}

$couriers = db()->query(
    'SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id) AS total_orders,
            (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id AND o.status="delivered") AS done_orders,
            (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id AND o.status IN ("accepted","picked_up","on_way")) AS active_orders
     FROM users u WHERE u.role="courier" ORDER BY u.created_at DESC'
)->fetchAll();

$pageTitle = 'Kuryerlar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Kuryerlar 🛵</h1>
<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= icon('x',16) ?><?= e($er) ?></div><?php endforeach; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2><?= icon('plus',18) ?> Yangi kuryer ulash</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label class="field"><span>Ism</span><input type="text" name="name" required></label>
            <label class="field"><span>Telefon</span><input type="tel" name="phone" placeholder="+998..." required></label>
            <label class="field"><span>Parol</span><input type="text" name="password" required></label>
            <button class="btn primary block"><?= icon('truck',16) ?> Kuryer qo'shish</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Ism</th><th>Telefon</th><th>Aktiv</th><th>Yetkazgan</th><th>Balans</th><th>Holat</th><th>Amal</th></tr></thead>
            <tbody>
            <?php foreach ($couriers as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></td>
                    <td><?= $c['active_orders'] ?></td>
                    <td><?= $c['done_orders'] ?></td>
                    <td style="font-weight:700;color:var(--green)"><?= money($c['balance']) ?></td>
                    <td><?= $c['is_active'] ? '🟢 Faol' : '🔴 Blok' ?></td>
                    <td class="row-actions">
                        <form method="post" data-confirm="<?= e($c['name']) ?> ga <?= money($c['balance']) ?> to'lansinmi? Balans nollanadi.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="payout">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn sm" title="To'lash" <?= $c['balance'] <= 0 ? 'disabled' : '' ?>><?= icon('wallet',15) ?></button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn sm"><?= $c['is_active'] ? icon('x',15) : icon('check',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$couriers): ?><tr><td colspan="7" class="muted">Kuryerlar yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
