<?php
require_once __DIR__ . '/functions.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'Dostavka';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Dostavka</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/index.php">🛵 Dostavka</a>
    <nav class="nav">
        <?php if ($u): ?>
            <?php if ($u['role'] === 'customer'): ?>
                <a href="/index.php">Mahsulotlar</a>
                <a href="/cart.php">Savatcha <span class="badge"><?= cart_count() ?></span></a>
                <a href="/orders.php">Buyurtmalarim</a>
            <?php elseif ($u['role'] === 'courier'): ?>
                <a href="/courier/index.php">Buyurtmalar</a>
            <?php elseif ($u['role'] === 'admin'): ?>
                <a href="/admin/index.php">Boshqaruv</a>
                <a href="/admin/products.php">Mahsulotlar</a>
                <a href="/admin/couriers.php">Kuryerlar</a>
                <a href="/admin/orders.php">Buyurtmalar</a>
            <?php endif; ?>
            <span class="user-chip"><?= e($u['name']) ?> · <?= e(role_label($u['role'])) ?></span>
            <a class="btn-logout" href="/logout.php">Chiqish</a>
        <?php else: ?>
            <a href="/login.php">Kirish</a>
            <a href="/register.php">Ro'yxatdan o'tish</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
