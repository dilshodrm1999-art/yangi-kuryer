<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('courier');
$me = current_user();

// Filtr: hammasi | delivered | cancelled
$filter = $_GET['f'] ?? 'all';
$where  = "o.courier_id = ? AND o.status IN ('delivered','cancelled')";
$params = [$me['id']];
if ($filter === 'delivered') { $where = "o.courier_id=? AND o.status='delivered'"; }
elseif ($filter === 'cancelled') { $where = "o.courier_id=? AND o.status='cancelled'"; }

$stmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name
     FROM orders o JOIN users u ON u.id=o.customer_id
     WHERE $where
     ORDER BY o.updated_at DESC LIMIT 100"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Sanaga qarab guruhlash (bugun / kecha / sana)
function day_label(string $dt): string
{
    $d = date('Y-m-d', strtotime($dt));
    if ($d === date('Y-m-d')) return 'Bugun';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Kecha';
    return date('d.m.Y', strtotime($dt));
}
$groups = [];
foreach ($orders as $o) {
    $groups[day_label($o['updated_at'])][] = $o;
}

$pageTitle = 'Buyurtma tarixi';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('history',22) ?> Buyurtma tarixi</h1>

<div class="seg">
    <a class="seg-btn <?= $filter==='all'?'active':'' ?>" href="?f=all">Hammasi</a>
    <a class="seg-btn <?= $filter==='delivered'?'active':'' ?>" href="?f=delivered">Yetkazilgan</a>
    <a class="seg-btn <?= $filter==='cancelled'?'active':'' ?>" href="?f=cancelled">Bekor</a>
</div>

<?php if (!$orders): ?>
    <div class="empty-box"><?= icon('history',30) ?><p>Bu bo'limda buyurtma yo'q.</p></div>
<?php else: ?>
    <?php foreach ($groups as $label => $list): ?>
        <div class="day-group"><?= icon('calendar',14) ?> <?= e($label) ?> · <span class="muted"><?= count($list) ?> ta</span></div>
        <div class="cr-list">
            <?php foreach ($list as $o):
                $delivered = $o['status'] === 'delivered'; ?>
                <article class="hcard">
                    <div class="hcard-left">
                        <span class="hstatus" style="--c:<?= status_color($o['status']) ?>"><?= $delivered ? icon('check',14) : icon('x',14) ?></span>
                    </div>
                    <div class="hcard-mid">
                        <div class="hcard-top">
                            <strong>#<?= (int)$o['id'] ?></strong>
                            <span class="muted small"><?= e(date('H:i', strtotime($o['updated_at']))) ?></span>
                        </div>
                        <div class="hcard-addr"><?= icon('pin',13) ?> <?= e($o['address']) ?></div>
                        <div class="hcard-meta">
                            <?php if ($o['distance_km']>0): ?><span><?= e($o['distance_km']) ?> km</span> · <?php endif; ?>
                            <span><?= e(status_label($o['status'])) ?></span>
                        </div>
                    </div>
                    <div class="hcard-earn <?= $delivered ? '' : 'muted' ?>">
                        <?= $delivered ? money(courier_earn($o)) : '—' ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
