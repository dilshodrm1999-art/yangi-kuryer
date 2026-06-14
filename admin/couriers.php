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

        // Rasm va pasport rasmini yuklash (local fayl yoki URL)
        $upErr = null;
        $photo    = resolve_image_input('photo_file', 'photo_url', 'couriers', '', $upErr);
        if ($upErr) $errors[] = 'Rasm: ' . $upErr;
        $passport = resolve_image_input('passport_file', 'passport_url', 'couriers', '', $upErr);
        if ($upErr) $errors[] = 'Pasport: ' . $upErr;

        if (!$errors) {
            $chk = db()->prepare('SELECT id FROM users WHERE phone=?');
            $chk->execute([$phone]);
            if ($chk->fetch()) {
                $errors[] = 'Bu telefon band.';
            } else {
                db()->prepare('INSERT INTO users (name, phone, password, role, photo, passport) VALUES (?,?,?,"courier",?,?)')
                    ->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT), $photo, $passport]);
                $msg = 'Kuryer qo\'shildi.';
            }
        }
    } elseif ($action === 'update_photos') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = db()->prepare('SELECT photo, passport FROM users WHERE id=? AND role="courier"');
        $cur->execute([$id]);
        $row = $cur->fetch() ?: ['photo' => '', 'passport' => ''];
        $upErr = null;
        $photo    = resolve_image_input('photo_file', 'photo_url', 'couriers', $row['photo'] ?? '', $upErr);
        if ($upErr) $errors[] = 'Rasm: ' . $upErr;
        $passport = resolve_image_input('passport_file', 'passport_url', 'couriers', $row['passport'] ?? '', $upErr);
        if ($upErr) $errors[] = 'Pasport: ' . $upErr;
        if (!$errors) {
            db()->prepare('UPDATE users SET photo=?, passport=? WHERE id=? AND role="courier"')
                ->execute([$photo, $passport, $id]);
            $msg = 'Kuryer rasmlari yangilandi.';
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
    } elseif ($action === 'topup') {
        // Balansni to'ldirish (admin qo'lda pul qo'shadi)
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount > 0) {
            db()->prepare('UPDATE users SET balance = balance + ? WHERE id=? AND role="courier"')
                ->execute([$amount, (int)$_POST['id']]);
            $msg = 'Balans to\'ldirildi: +' . money($amount);
        }
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
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label class="field"><span>Ism</span><input type="text" name="name" required></label>
            <label class="field"><span>Telefon</span><input type="tel" name="phone" placeholder="+998..." required></label>
            <label class="field"><span>Parol</span><input type="text" name="password" required></label>

            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Kuryer rasmi</span>
                <input type="file" name="photo_file" accept="image/*" class="js-file" data-preview="prevPhoto">
                <div class="img-preview" id="prevPhoto"></div>
                <input type="text" name="photo_url" placeholder="yoki rasm URL (ixtiyoriy)">
            </div>

            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Pasport rasmi</span>
                <input type="file" name="passport_file" accept="image/*" class="js-file" data-preview="prevPass">
                <div class="img-preview" id="prevPass"></div>
                <input type="text" name="passport_url" placeholder="yoki rasm URL (ixtiyoriy)">
            </div>

            <button class="btn primary block"><?= icon('truck',16) ?> Kuryer qo'shish</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Rasm</th><th>Ism / ID</th><th>Telefon</th><th>Aktiv</th><th>Yetkazgan</th><th>Balans</th><th>Hujjat</th><th>Holat</th><th>Amal</th></tr></thead>
            <tbody>
            <?php foreach ($couriers as $c): ?>
                <tr>
                    <td>
                        <div class="thumb round" style="background-image:url('<?= e($c['photo'] ?: '') ?>')">
                            <?= $c['photo'] ? '' : icon('user',18) ?>
                        </div>
                    </td>
                    <td><strong><?= e($c['name']) ?></strong><div class="muted small">ID: K<?= str_pad((string)$c['id'], 3, '0', STR_PAD_LEFT) ?></div></td>
                    <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></td>
                    <td><?= $c['active_orders'] ?></td>
                    <td><?= $c['done_orders'] ?></td>
                    <td style="font-weight:700;color:var(--green)"><?= money($c['balance']) ?></td>
                    <td>
                        <?php if (!empty($c['passport'])): ?>
                            <a class="btn sm ghost" href="<?= e($c['passport']) ?>" target="_blank" title="Pasportni ko'rish"><?= icon('image',15) ?></a>
                        <?php else: ?><span class="muted small">—</span><?php endif; ?>
                    </td>
                    <td><?= $c['is_active'] ? '🟢 Faol' : '🔴 Blok' ?></td>
                    <td class="row-actions">
                        <button type="button" class="btn sm js-edit-photos"
                            data-id="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>"
                            data-photo="<?= e($c['photo'] ?? '') ?>" data-passport="<?= e($c['passport'] ?? '') ?>"
                            title="Rasmlarni tahrirlash"><?= icon('image',15) ?></button>
                        <form method="post" class="inline-form" style="gap:4px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="topup">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="number" name="amount" placeholder="summa" step="1000" min="0" style="width:88px;padding:7px 9px" required>
                            <button class="btn sm primary" title="Balansni to'ldirish"><?= icon('plus',15) ?></button>
                        </form>
                        <form method="post" data-confirm="<?= e($c['name']) ?> ga <?= money($c['balance']) ?> to'lansinmi? Balans nollanadi.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="payout">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn sm" title="To'lab, nollash" <?= $c['balance'] <= 0 ? 'disabled' : '' ?>><?= icon('wallet',15) ?></button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn sm" title="Faollik"><?= $c['is_active'] ? icon('x',15) : icon('check',15) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$couriers): ?><tr><td colspan="9" class="muted">Kuryerlar yo'q.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Kuryer rasmlarini tahrirlash (modal) -->
<div class="modal-overlay" id="photoModal" style="display:none">
    <div class="modal">
        <div class="modal-head">
            <h3><?= icon('image',18) ?> Rasmlar — <span id="pmName"></span></h3>
            <button type="button" class="icon-btn" id="pmClose"><?= icon('x',18) ?></button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_photos">
            <input type="hidden" name="id" id="pmId">
            <div class="upload-field">
                <span class="upload-label"><?= icon('user',15) ?> Kuryer rasmi</span>
                <div class="img-preview" id="pmPhotoCur"></div>
                <input type="file" name="photo_file" accept="image/*" class="js-file" data-preview="pmPhotoCur">
                <input type="text" name="photo_url" id="pmPhotoUrl" placeholder="yoki rasm URL">
            </div>
            <div class="upload-field">
                <span class="upload-label"><?= icon('image',15) ?> Pasport rasmi</span>
                <div class="img-preview" id="pmPassCur"></div>
                <input type="file" name="passport_file" accept="image/*" class="js-file" data-preview="pmPassCur">
                <input type="text" name="passport_url" id="pmPassUrl" placeholder="yoki rasm URL">
            </div>
            <button class="btn primary block"><?= icon('check',16) ?> Saqlash</button>
        </form>
    </div>
</div>

<script src="/assets/js/admin-uploads.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
