<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$stats = [
    'orders'    => db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'new'       => db()->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'delivered' => db()->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn(),
    'products'  => db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'couriers'  => db()->query("SELECT COUNT(*) FROM users WHERE role='courier'")->fetchColumn(),
    'revenue'   => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='delivered'")->fetchColumn(),
];

$recent = db()->query(
    'SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Boshqaruv paneli';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Boshqaruv paneli</h1>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= $stats['orders'] ?></span>Jami buyurtma</div>
    <div class="stat"><span class="stat-num"><?= $stats['new'] ?></span>Yangi buyurtma</div>
    <div class="stat"><span class="stat-num"><?= $stats['delivered'] ?></span>Yetkazilgan</div>
    <div class="stat"><span class="stat-num"><?= $stats['products'] ?></span>Mahsulot</div>
    <div class="stat"><span class="stat-num"><?= $stats['couriers'] ?></span>Kuryer</div>
    <div class="stat"><span class="stat-num"><?= money($stats['revenue']) ?></span>Tushum</div>
</div>

<div class="quick-links">
    <a class="btn primary" href="/admin/products.php">+ Mahsulot qo'shish</a>
    <a class="btn" href="/admin/couriers.php">Kuryer qo'shish</a>
    <a class="btn" href="/admin/orders.php">Buyurtmalarni boshqarish</a>
</div>

<h2 class="sub">So'nggi buyurtmalar</h2>
<table class="table">
    <thead><tr><th>#</th><th>Mijoz</th><th>Manzil</th><th>Summa</th><th>Status</th><th>Vaqt</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $o): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= e($o['customer_name']) ?></td>
            <td><?= e($o['address']) ?></td>
            <td><?= money($o['total']) ?></td>
            <td><span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td class="muted small"><?= e($o['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="muted">Buyurtmalar yo'q.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../includes/footer.php'; ?>
