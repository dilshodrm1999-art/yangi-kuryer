<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

$errors = [];
$name = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (mb_strlen($name) < 2)      $errors[] = 'Ismni to\'liq kiriting.';
    if (!preg_match('/^\+?\d{7,15}$/', $phone)) $errors[] = 'Telefon raqami noto\'g\'ri.';
    if (mb_strlen($pass) < 5)      $errors[] = 'Parol kamida 5 ta belgidan iborat bo\'lsin.';
    if ($pass !== $pass2)          $errors[] = 'Parollar mos kelmadi.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE phone = ?');
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu telefon raqami allaqachon ro\'yxatdan o\'tgan.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (name, phone, password, role) VALUES (?, ?, ?, "customer")'
        );
        $stmt->execute([$name, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
        redirect('/index.php');
    }
}

$pageTitle = 'Ro\'yxatdan o\'tish';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>Ro'yxatdan o'tish</h1>
    <p class="muted">Yangi mijoz hisobini yarating.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Ism
            <input type="text" name="name" value="<?= e($name) ?>" required>
        </label>
        <label>Telefon
            <input type="text" name="phone" value="<?= e($phone) ?>" placeholder="+998901234567" required>
        </label>
        <label>Parol
            <input type="password" name="password" required>
        </label>
        <label>Parolni takrorlang
            <input type="password" name="password2" required>
        </label>
        <button class="btn primary" type="submit">Ro'yxatdan o'tish</button>
    </form>
    <p class="muted">Hisobingiz bormi? <a href="/login.php">Kirish</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
