<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$stats = [
    'orders'    => db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'new'       => db()->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'active'    => db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('accepted','picked_up','on_way')")->fetchColumn(),
    'delivered' => db()->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn(),
    'couriers'  => db()->query("SELECT COUNT(*) FROM users WHERE role='courier'")->fetchColumn(),
    'revenue'   => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='delivered'")->fetchColumn(),
    'fees'      => db()->query("SELECT COALESCE(SUM(delivery_fee),0) FROM orders WHERE status='delivered'")->fetchColumn(),
    'commission'=> db()->query("SELECT COALESCE(SUM(commission),0) FROM orders WHERE status='delivered'")->fetchColumn(),
];

$recent = db()->query(
    'SELECT o.*, u.name AS customer_name, k.name AS courier_name
     FROM orders o JOIN users u ON u.id=o.customer_id
     LEFT JOIN users k ON k.id=o.courier_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Boshqaruv paneli';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Boshqaruv paneli 📊</h1>
<p class="page-sub">Tizim holati va jonli kuzatuv.</p>

<div class="stats">
    <div class="stat"><div class="si"><?= icon('list',20) ?></div><div class="stat-num"><?= $stats['orders'] ?></div><div class="stat-label">Jami buyurtma</div></div>
    <div class="stat"><div class="si" style="background:#fff7ed;color:var(--amber)"><?= icon('clock',20) ?></div><div class="stat-num"><?= $stats['new'] ?></div><div class="stat-label">Yangi</div></div>
    <div class="stat"><div class="si" style="background:#f3e8ff;color:var(--purple)"><?= icon('truck',20) ?></div><div class="stat-num"><?= $stats['active'] ?></div><div class="stat-label">Yo'lda</div></div>
    <div class="stat"><div class="si" style="background:#ecfdf3;color:var(--green)"><?= icon('check',20) ?></div><div class="stat-num"><?= $stats['delivered'] ?></div><div class="stat-label">Yetkazilgan</div></div>
    <div class="stat"><div class="si" style="background:#eff6ff;color:var(--blue)"><?= icon('wallet',20) ?></div><div class="stat-num" style="font-size:17px"><?= money($stats['revenue']) ?></div><div class="stat-label">Tushum</div></div>
    <div class="stat"><div class="si" style="background:#ecfdf3;color:var(--green)"><?= icon('star',20) ?></div><div class="stat-num" style="font-size:17px"><?= money($stats['commission']) ?></div><div class="stat-label">Admin komissiyasi</div></div>
    <div class="stat"><div class="si"><?= icon('route',20) ?></div><div class="stat-num" style="font-size:17px"><?= money($stats['fees']) ?></div><div class="stat-label">Yetkazish haqi</div></div>
</div>

<div class="quick-links">
    <a class="btn primary" href="/admin/products.php"><?= icon('plus',16) ?> Mahsulot qo'shish</a>
    <a class="btn" href="/admin/stats.php"><?= icon('chart',16) ?> Hisobotlar</a>
    <a class="btn" href="/admin/customers.php"><?= icon('user',16) ?> Mijozlar</a>
    <a class="btn" href="/admin/couriers.php"><?= icon('truck',16) ?> Kuryerlar</a>
    <a class="btn" href="/admin/orders.php"><?= icon('list',16) ?> Buyurtmalar</a>
    <a class="btn" href="/admin/settings.php"><?= icon('settings',16) ?> Sozlamalar</a>
</div>

<h2 class="sub"><?= icon('pin',18) ?> Kuryerlar jonli xaritada (<span id="liveCount">0</span>)</h2>
<div id="admin-map" class="live-map" style="height:340px"></div>
<p class="muted small" style="margin-top:8px">Xarita har 5 soniyada yangilanadi. Faqat GPS yoqilgan kuryerlar ko'rinadi.</p>

<h2 class="sub"><?= icon('clock',18) ?> So'nggi buyurtmalar</h2>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>#</th><th>Mijoz</th><th>Manzil</th><th>Kuryer</th><th>Summa</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $o): ?>
            <tr>
                <td>#<?= $o['id'] ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= e($o['address']) ?></td>
                <td><?= e($o['courier_name'] ?? '—') ?></td>
                <td><?= money($o['total']) ?></td>
                <td><span class="status" style="background:<?= status_color($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="6" class="muted">Buyurtmalar yo'q.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/admin-map.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
