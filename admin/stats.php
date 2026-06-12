<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$fin     = admin_finance_stats();
$report  = admin_courier_report();
$series  = admin_daily_commission(7);
$maxC    = max(1, max(array_map(fn($d) => $d['commission'], $series)));

// Reyting jami (foiz hisoblash uchun)
$totalFee = array_sum(array_map(fn($r) => (float)$r['total_fee'], $report));

$pageTitle = 'Hisobotlar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('chart',22) ?> Statistika va hisobotlar</h1>
<p class="page-sub">Kuryerlar samaradorligi, daromad va komissiya kesimida.</p>

<!-- Moliyaviy kartalar -->
<div class="report-grid">
    <div class="rcard"><div class="rc-ic blue"><?= icon('wallet',18) ?></div><div class="rc-v"><?= money($fin['revenue'] ?? 0) ?></div><div class="rc-l">Umumiy tushum</div></div>
    <div class="rcard"><div class="rc-ic green"><?= icon('star',18) ?></div><div class="rc-v"><?= money($fin['commission'] ?? 0) ?></div><div class="rc-l">Admin komissiyasi</div></div>
    <div class="rcard"><div class="rc-ic amber"><?= icon('truck',18) ?></div><div class="rc-v"><?= money($fin['fees'] ?? 0) ?></div><div class="rc-l">Yetkazish haqi</div></div>
    <div class="rcard"><div class="rc-ic purple"><?= icon('star',18) ?></div><div class="rc-v"><?= money($fin['cashback'] ?? 0) ?></div><div class="rc-l">Keshbek berildi</div></div>
</div>

<div class="report-grid">
    <div class="rcard"><div class="rc-ic blue"><?= icon('calendar',18) ?></div><div class="rc-v"><?= money($fin['today_commission'] ?? 0) ?></div><div class="rc-l">Bugun (komissiya)</div></div>
    <div class="rcard"><div class="rc-ic purple"><?= icon('calendar',18) ?></div><div class="rc-v"><?= money($fin['week_commission'] ?? 0) ?></div><div class="rc-l">Bu hafta</div></div>
    <div class="rcard"><div class="rc-ic green"><?= icon('calendar',18) ?></div><div class="rc-v"><?= money($fin['month_commission'] ?? 0) ?></div><div class="rc-l">Bu oy</div></div>
    <div class="rcard"><div class="rc-ic amber"><?= icon('check',18) ?></div><div class="rc-v"><?= (int)($fin['delivered'] ?? 0) ?></div><div class="rc-l">Yetkazilgan</div></div>
</div>

<!-- 7 kunlik komissiya grafigi -->
<div class="card chart-card">
    <div class="cr-section-head" style="margin-top:0"><h2><?= icon('chart',18) ?> So'nggi 7 kun — admin komissiyasi</h2></div>
    <div class="bars">
        <?php foreach ($series as $d):
            $h = (int)round(($d['commission'] / $maxC) * 100);
            if ($d['commission'] > 0 && $h < 6) $h = 6; ?>
            <div class="bar-col">
                <div class="bar-val"><?= $d['commission'] > 0 ? round($d['commission']/1000).'k' : '' ?></div>
                <div class="bar-track"><div class="bar-fill" style="height:<?= $h ?>%" title="<?= money($d['commission']) ?> · <?= $d['count'] ?> ta"></div></div>
                <div class="bar-lbl"><?= e($d['label']) ?></div>
                <div class="bar-cnt"><?= $d['count'] ?> ta</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Kuryerlar reytingi -->
<h2 class="sub"><?= icon('trophy',18) ?> Kuryerlar reytingi</h2>
<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>#</th><th>Kuryer</th><th>Yetkazgan</th><th>Masofa</th>
            <th>Yetkazish haqi</th><th>Admin komissiyasi</th><th>Foiz</th><th>Kuryer daromadi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($report as $i => $r):
            $pct = $r['total_fee'] > 0 ? round($r['total_commission'] / $r['total_fee'] * 100) : 0; ?>
            <tr>
                <td>
                    <?php if ($i === 0 && $r['delivered'] > 0): ?>🥇<?php elseif ($i === 1 && $r['delivered'] > 0): ?>🥈<?php elseif ($i === 2 && $r['delivered'] > 0): ?>🥉<?php else: ?><?= $i+1 ?><?php endif; ?>
                </td>
                <td>
                    <strong><?= e($r['name']) ?></strong>
                    <div class="muted small"><?= e($r['phone']) ?> <?= $r['is_active'] ? '' : '· 🔴 blok' ?></div>
                </td>
                <td><?= (int)$r['delivered'] ?> ta</td>
                <td><?= number_format((float)$r['total_km'],1) ?> km</td>
                <td><?= money($r['total_fee']) ?></td>
                <td style="color:var(--green);font-weight:700"><?= money($r['total_commission']) ?></td>
                <td><span class="tag"><?= $pct ?>%</span></td>
                <td><?= money($r['courier_earn']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$report): ?><tr><td colspan="8" class="muted">Kuryerlar yo'q.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($report): ?>
        <tfoot>
            <tr class="t-total">
                <td colspan="4">Jami</td>
                <td><?= money($totalFee) ?></td>
                <td style="color:var(--green)"><?= money($fin['commission'] ?? 0) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
