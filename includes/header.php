<?php
require_once __DIR__ . '/functions.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'Dostavka';

// Joriy sahifa (bottom-nav aktivligi uchun)
$cur = $_SERVER['PHP_SELF'] ?? '';

// Rolga qarab navigatsiya elementlari
$navItems = [];
if ($u) {
    if ($u['role'] === 'customer') {
        $navItems = [
            ['/index.php',  'home',    'Bosh'],
            ['/cart.php',   'cart',    'Savat',  cart_count()],
            ['/orders.php', 'package', 'Buyurtma'],
            ['/profile.php','user',    'Profil'],
        ];
    } elseif ($u['role'] === 'courier') {
        $navItems = [
            ['/courier/index.php',   'truck',  'Buyurtma'],
            ['/courier/balance.php', 'wallet', 'Balans'],
            ['/profile.php',         'user',   'Profil'],
        ];
    } elseif ($u['role'] === 'admin') {
        $navItems = [
            ['/admin/index.php',    'dashboard','Panel'],
            ['/admin/orders.php',   'list',     'Buyurtma'],
            ['/admin/stores.php',   'box',      'Do\'kon'],
            ['/admin/products.php', 'package',  'Mahsulot'],
            ['/admin/couriers.php', 'truck',    'Kuryer'],
            ['/admin/settings.php', 'settings', 'Sozlama'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ff6b35">
    <?php if ($u): ?><meta name="csrf" content="<?= csrf_token() ?>"><?php endif; ?>
    <title><?= e($pageTitle) ?> · Dostavka</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= $u ? 'role-' . e($u['role']) : 'guest' ?>">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $u ? role_home($u['role']) : '/index.php' ?>">
            <span class="brand-logo"><?= icon('truck', 20) ?></span>
            <span>Dostavka</span>
        </a>

        <?php if ($u): ?>
            <nav class="nav-desktop">
                <?php foreach ($navItems as $it): ?>
                    <a href="<?= e($it[0]) ?>" class="<?= str_contains($cur, ltrim($it[0], '/')) ? 'active' : '' ?>">
                        <?= icon($it[1], 18) ?><span><?= e($it[2]) ?></span>
                        <?php if ($it[1] === 'cart'): ?>
                            <i class="dot cart-badge" <?= empty($it[3]) ? 'style="display:none"' : '' ?>><?= (int)($it[3] ?? 0) ?></i>
                        <?php elseif (!empty($it[3])): ?><i class="dot"><?= (int)$it[3] ?></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="topbar-right">
                <?php if ($u['role'] === 'courier'): ?>
                    <a class="balance-pill" href="/courier/balance.php" title="Balans">
                        <?= icon('wallet', 16) ?> <?= number_format((float)$u['balance'], 0, '.', ' ') ?>
                    </a>
                <?php endif; ?>
                <span class="user-chip"><?= icon('user', 15) ?> <?= e($u['name']) ?></span>
                <a class="icon-btn" href="/logout.php" title="Chiqish"><?= icon('logout', 18) ?></a>
            </div>
        <?php else: ?>
            <div class="topbar-right">
                <a class="btn ghost sm" href="/login.php">Kirish</a>
                <a class="btn primary sm" href="/register.php">Ro'yxatdan o'tish</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<main class="container">
