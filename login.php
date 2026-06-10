<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

$errors = [];
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $lock = login_lock_seconds();
    if ($lock > 0) {
        $errors[] = "Juda ko'p urinish. {$lock} soniyadan so'ng qayta urinib ko'ring.";
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            login_register_failure();
            $errors[] = 'Telefon yoki parol noto\'g\'ri.';
        } elseif (!$user['is_active']) {
            $errors[] = 'Hisobingiz bloklangan. Admin bilan bog\'laning.';
        } else {
            login_user((int)$user['id']);
            redirect(role_home($user['role']));
        }
    }
}

$pageTitle = 'Kirish';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
<div class="auth-card">
    <div class="auth-logo"><?= icon('truck', 26) ?></div>
    <h1>Xush kelibsiz</h1>
    <p class="muted" style="margin-bottom:18px">Hisobingizga kiring</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= icon('x',16) ?><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label class="field"><span>Telefon</span>
            <input type="tel" name="phone" value="<?= e($phone) ?>" inputmode="tel" placeholder="+998901234567" required>
        </label>
        <label class="field"><span>Parol</span>
            <input type="password" name="password" required>
        </label>
        <button class="btn primary block">Kirish</button>
    </form>
    <p class="muted" style="margin-top:14px;text-align:center">Hisobingiz yo'qmi? <a href="/register.php">Ro'yxatdan o'tish</a></p>

    <div class="demo-box">
        <strong>Demo (parol: 12345):</strong>
        <ul>
            <li>Admin: <code>+998900000000</code></li>
            <li>Kuryer: <code>+998901111111</code></li>
            <li>Mijoz: <code>+998903333333</code></li>
        </ul>
    </div>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
