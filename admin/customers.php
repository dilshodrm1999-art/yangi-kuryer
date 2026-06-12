<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=? AND role="customer"')->execute([$id]);
        $msg = 'Holat o\'zgartirildi.';
    } elseif ($action === 'cashback_add') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount != 0) {
            db()->prepare('UPDATE users SET cashback_balance = GREATEST(0, cashback_balance + ?) WHERE id=? AND role="customer"')
                ->execute([$amount, $id]);
            $msg = 'Keshbek balansi yangilandi.';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id=u.id) AS total_orders,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id=u.id AND o.status='delivered') AS done_orders,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id=u.id AND o.status='delivered') AS spent
        FROM users u WHERE u.role='customer'";
$params = [];
if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR u.phone LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= ' ORDER BY u.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalCustomers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalCashback  = (float)db()->query("SELECT COALESCE(SUM(cashback_balance),0) FROM users WHERE role='customer'")->fetchColumn();

$pageTitle = 'Mijozlar';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('user',22) ?> Mijozlar</h1>
<p class="page-sub">Ro'yxatdan o'tgan mijozlar va keshbek balanslari.</p>

<?php if ($msg): ?><div class="alert success"><?= icon('check',16) ?> <?= e($msg) ?></div><?php endif; ?>

<div class="report-grid two">
    <div class="rcard"><div class="rc-ic blue"><?= icon('user',18) ?></div><div class="rc-v"><?= $totalCustomers ?></div><div class="rc-l">Jami mijozlar</div></div>
    <div class="rcard"><div class="rc-ic green"><?= icon('wallet',18) ?></div><div class="rc-v"><?= money($totalCashback) ?></div><div class="rc-l">Umumiy keshbek</div></div>
</div>

<form method="get" class="search-bar">
    <?= icon('search',16) ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ism yoki telefon bo'yicha qidirish...">
    <button class="btn primary sm">Qidirish</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Mijoz</th><th>Telefon</th><th>Buyurtma</th><th>Sarflagan</th><th>Keshbek</th><th>Holat</th><th>Amal</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong><div class="muted small"><?= e(date('d.m.Y', strtotime($c['created_at']))) ?></div></td>
                <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></td>
                <td><?= (int)$c['done_orders'] ?> / <?= (int)$c['total_orders'] ?></td>
                <td><?= money($c['spent']) ?></td>
                <td style="font-weight:700;color:var(--green)"><?= money($c['cashback_balance'] ?? 0) ?></td>
                <td><?= $c['is_active'] ? '🟢 Faol' : '🔴 Blok' ?></td>
                <td class="row-actions">
                    <form method="post" class="inline-form" style="gap:4px" title="Keshbek qo'shish/ayirish">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cashback_add">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="number" name="amount" placeholder="±summa" step="1000" style="width:92px;padding:7px 9px" required>
                        <button class="btn sm primary"><?= icon('wallet',15) ?></button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn sm" title="Faollik"><?= $c['is_active'] ? icon('x',15) : icon('check',15) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="7" class="muted">Mijozlar topilmadi.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
