<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

$s      = courier_stats((int)$me['id']);
$series = courier_daily_series((int)$me['id'], 7);
$maxEarn = max(1, max(array_map(fn($d) => $d['earn'], $series)));

$pageTitle = 'Hisobot';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('chart',22) ?> Statistika va hisobot</h1>

<!-- Davr bo'yicha kartalar -->
<div class="report-grid">
    <div class="rcard">
        <div class="rc-ic blue"><?= icon('calendar',18) ?></div>
        <div class="rc-v"><?= money($s['today_earn'] ?? 0) ?></div>
        <div class="rc-l">Bugun · <?= (int)($s['today_cnt'] ?? 0) ?> ta</div>
    </div>
    <div class="rcard">
        <div class="rc-ic purple"><?= icon('calendar',18) ?></div>
        <div class="rc-v"><?= money($s['week_earn'] ?? 0) ?></div>
        <div class="rc-l">Bu hafta · <?= (int)($s['week_cnt'] ?? 0) ?> ta</div>
    </div>
    <div class="rcard">
        <div class="rc-ic green"><?= icon('calendar',18) ?></div>
        <div class="rc-v"><?= money($s['month_earn'] ?? 0) ?></div>
        <div class="rc-l">Bu oy · <?= (int)($s['month_cnt'] ?? 0) ?> ta</div>
    </div>
    <div class="rcard">
        <div class="rc-ic amber"><?= icon('wallet',18) ?></div>
        <div class="rc-v"><?= money($s['total_earn'] ?? 0) ?></div>
        <div class="rc-l">Umumiy daromad</div>
    </div>
</div>

<!-- 7 kunlik grafik -->
<div class="card chart-card">
    <div class="cr-section-head" style="margin-top:0">
        <h2><?= icon('chart',18) ?> So'nggi 7 kun daromadi</h2>
    </div>
    <div class="bars">
        <?php foreach ($series as $d):
            $h = (int)round(($d['earn'] / $maxEarn) * 100);
            if ($d['earn'] > 0 && $h < 6) $h = 6; ?>
            <div class="bar-col">
                <div class="bar-val"><?= $d['earn'] > 0 ? round($d['earn']/1000).'k' : '' ?></div>
                <div class="bar-track">
                    <div class="bar-fill" style="height:<?= $h ?>%" title="<?= money($d['earn']) ?> · <?= $d['count'] ?> ta"></div>
                </div>
                <div class="bar-lbl"><?= e($d['label']) ?></div>
                <div class="bar-cnt"><?= $d['count'] ?> ta</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Umumiy ko'rsatkichlar -->
<div class="stat-rows card">
    <div class="srow"><span><?= icon('check',16) ?> Jami yetkazilgan</span><strong><?= (int)($s['delivered'] ?? 0) ?> ta</strong></div>
    <div class="srow"><span><?= icon('route',16) ?> Bosib o'tilgan masofa</span><strong><?= number_format((float)($s['total_km'] ?? 0),1) ?> km</strong></div>
    <div class="srow"><span><?= icon('wallet',16) ?> O'rtacha daromad / buyurtma</span><strong><?= money($s['avg_earn'] ?? 0) ?></strong></div>
    <div class="srow"><span><?= icon('flame',16) ?> Joriy balans</span><strong style="color:var(--green)"><?= money($me['balance']) ?></strong></div>
</div>

<a class="btn block" href="/courier/balance.php" style="margin-top:14px"><?= icon('wallet',16) ?> Balans va to'lovlar tarixi</a>

<?php require __DIR__ . '/../includes/footer.php'; ?>
