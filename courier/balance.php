<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

$s = courier_stats((int)$me['id']);

// Yetkazilgan buyurtmalar (daromad tarixi)
$hist = db()->prepare(
    "SELECT o.id, o.address, o.distance_km, o.delivery_fee, o.commission, o.updated_at, u.name AS customer
     FROM orders o JOIN users u ON u.id=o.customer_id
     WHERE o.courier_id=? AND o.status='delivered'
     ORDER BY o.updated_at DESC LIMIT 50"
);
$hist->execute([$me['id']]);
$history = $hist->fetchAll();

$pageTitle = 'Balans';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('wallet',22) ?> Balans</h1>

<div class="balance-hero">
    <div class="label"><?= icon('wallet',18) ?> Joriy balans</div>
    <div class="amount"><?= money($me['balance']) ?></div>
    <div class="bh-sub">
        <span>Bugun: <strong><?= money($s['today_earn'] ?? 0) ?></strong></span>
        <span>Bu oy: <strong><?= money($s['month_earn'] ?? 0) ?></strong></span>
    </div>
</div>

<div class="report-grid two">
    <div class="rcard"><div class="rc-ic green"><?= icon('check',18) ?></div><div class="rc-v"><?= (int)($s['delivered'] ?? 0) ?></div><div class="rc-l">Yetkazilgan</div></div>
    <div class="rcard"><div class="rc-ic blue"><?= icon('route',18) ?></div><div class="rc-v"><?= number_format((float)($s['total_km'] ?? 0),1) ?> km</div><div class="rc-l">Umumiy masofa</div></div>
</div>

<div class="cr-section-head"><h2><?= icon('list',18) ?> Daromad tarixi</h2></div>
<?php if (!$history): ?>
    <div class="empty-box"><?= icon('wallet',30) ?><p>Hali yetkazilgan buyurtmalar yo'q.</p></div>
<?php else: ?>
<div class="cr-list">
    <?php foreach ($history as $h): ?>
        <article class="hcard">
            <div class="hcard-left"><span class="hstatus" style="--c:var(--green)"><?= icon('check',14) ?></span></div>
            <div class="hcard-mid">
                <div class="hcard-top"><strong>#<?= (int)$h['id'] ?></strong><span class="muted small"><?= e(date('d.m H:i', strtotime($h['updated_at']))) ?></span></div>
                <div class="hcard-addr"><?= icon('pin',13) ?> <?= e($h['address']) ?></div>
                <div class="hcard-meta"><span><?= e($h['customer']) ?></span> · <span><?= e($h['distance_km']) ?> km</span></div>
            </div>
            <div class="hcard-earn"><?= money($h['delivery_fee'] - $h['commission']) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
