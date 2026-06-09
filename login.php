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

    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password'])) {
        $errors[] = 'Telefon yoki parol noto\'g\'ri.';
    } elseif (!$user['is_active']) {
        $errors[] = 'Hisobingiz bloklangan. Admin bilan bog\'laning.';
    } else {
        $_SESSION['user_id'] = (int)$user['id'];
        redirect(role_home($user['role']));
    }
}

$pageTitle = 'Kirish';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>Kirish</h1>
    <p class="muted">Hisobingizga kiring.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Telefon
            <input type="text" name="phone" value="<?= e($phone) ?>" placeholder="+998901234567" required>
        </label>
        <label>Parol
            <input type="password" name="password" required>
        </label>
        <button class="btn primary" type="submit">Kirish</button>
    </form>
    <p class="muted">Hisobingiz yo'qmi? <a href="/register.php">Ro'yxatdan o'tish</a></p>

    <div class="demo-box">
        <strong>Demo hisoblar (parol: 12345):</strong>
        <ul>
            <li>Admin: <code>+998900000000</code></li>
            <li>Kuryer: <code>+998901111111</code></li>
            <li>Mijoz: <code>+998903333333</code></li>
        </ul>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
