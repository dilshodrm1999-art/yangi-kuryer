<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) >= 2) {
            db()->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name, $u['id']]);
            $msg = 'Ma\'lumotlar yangilandi.';
            $u['name'] = $name;
        } else {
            $err = 'Ism juda qisqa.';
        }
    } elseif ($action === 'password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        if (!password_verify($old, $u['password'])) {
            $err = 'Joriy parol noto\'g\'ri.';
        } elseif (mb_strlen($new) < 5) {
            $err = 'Yangi parol kamida 5 belgi.';
        } else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            $msg = 'Parol o\'zgartirildi.';
        }
    }
}

$pageTitle = 'Profil';
require __DIR__ . '/includes/header.php';
?>
<div class="profile-head">
    <div class="avatar"><?= icon('user', 30) ?></div>
    <div>
        <h1 class="page-title" style="margin:0"><?= e($u['name']) ?></h1>
        <p class="muted"><?= e(role_label($u['role'])) ?> · <?= e($u['phone']) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= icon('x',16) ?><?= e($err) ?></div><?php endif; ?>

<div class="admin-layout">
    <div class="card form-card">
        <h2>Ma'lumotlar</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <label class="field"><span>Ism</span><input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
            <label class="field"><span>Telefon</span><input type="text" value="<?= e($u['phone']) ?>" disabled></label>
            <button class="btn primary block">Saqlash</button>
        </form>
    </div>

    <div class="card form-card">
        <h2>Parolni o'zgartirish</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label class="field"><span>Joriy parol</span><input type="password" name="old" required></label>
            <label class="field"><span>Yangi parol</span><input type="password" name="new" required></label>
            <button class="btn block">O'zgartirish</button>
        </form>
        <a class="btn ghost block" href="/logout.php"><?= icon('logout',16) ?> Hisobdan chiqish</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
