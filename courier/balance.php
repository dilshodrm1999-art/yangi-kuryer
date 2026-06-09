<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

// Daromad statistikasi
$stats = db()->prepare(
    "SELECT
        COUNT(*) AS delivered_count,
        COALESCE(SUM(delivery_fee),0) AS total_earned,
        COALESCE(SUM(CASE WHEN DATE(updated_at)=CURDATE() THEN delivery_fee ELSE 0 END),0) AS today_earned,
        COALESCE(SUM(distance_km),0) AS total_km
     FROM orders WHERE courier_id=? AND status='delivered'"
);
$stats->execute([$me['id']]);
$s = $stats->fetch();

// Yetkazilgan buyurtmalar (daromad tarixi)
$hist = db()->prepare(
    "SELECT o.id, o.address, o.distance_km, o.delivery_fee, o.updated_at, u.name AS customer
     FROM orders o JOIN users u ON u.id=o.customer_id
     WHERE o.courier_id=? AND o.status='delivered'
     ORDER BY o.updated_at DESC LIMIT 50"
);
$hist->execute([$me['id']]);
$history = $hist->fetchAll();

$pageTitle = 'Balans';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Balans va daromad 💰</h1>

<div class="balance-hero">
    <div class="label"><?= icon('wallet',18) ?> Joriy balans</div>
    <div class="amount"><?= money($me['balance']) ?></div>
    <div class="small" style="opacity:.9;margin-top:6px">Bugun: <?= money($s['today_earned']) ?></div>
</div>

<div class="stats">
    <div class="stat"><div class="si"><?= icon('check',20) ?></div><div class="stat-num"><?= (int)$s['delivered_count'] ?></div><div class="stat-label">Yetkazilgan</div></div>
    <div class="stat"><div class="si"><?= icon('route',20) ?></div><div class="stat-num"><?= number_format((float)$s['total_km'],1) ?> km</div><div class="stat-label">Umumiy masofa</div></div>
    <div class="stat"><div class="si"><?= icon('wallet',20) ?></div><div class="stat-num" style="font-size:18px"><?= money($s['total_earned']) ?></div><div class="stat-label">Umumiy daromad</div></div>
</div>

<h2 class="sub"><?= icon('list',18) ?> Daromad tarixi</h2>
<?php if (!$history): ?>
    <div class="card muted" style="text-align:center">Hali yetkazilgan buyurtmalar yo'q.</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>#</th><th>Mijoz</th><th>Manzil</th><th>Masofa</th><th>Daromad</th><th>Sana</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td>#<?= $h['id'] ?></td>
                <td><?= e($h['customer']) ?></td>
                <td><?= e($h['address']) ?></td>
                <td><?= e($h['distance_km']) ?> km</td>
                <td style="color:var(--green);font-weight:700"><?= money($h['delivery_fee']) ?></td>
                <td class="muted small"><?= e(date('d.m.Y H:i', strtotime($h['updated_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
