<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// Kuryerlar ro'yxati (tanlash uchun)
$couriers = db()->query(
    "SELECT id, name, phone FROM users WHERE role='courier' ORDER BY name"
)->fetchAll();

// Tanlangan kuryer (ixtiyoriy)
$selId = (int)($_GET['courier_id'] ?? 0);

$pageTitle = 'Ratsiya';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title"><?= icon('mic',22) ?> Ratsiya — ovozli aloqa</h1>
<p class="page-sub">Kuryerni tanlang, mikrofonni bosib turib gapiring. Barcha yozishmalar shu yerda saqlanadi.</p>

<div class="rts-layout">
    <!-- Chap: kuryerlar ro'yxati -->
    <aside class="rts-side">
        <div class="rts-side-head"><?= icon('truck',16) ?> Kuryerlar</div>
        <a href="/admin/ratsiya.php" class="rts-courier <?= $selId === 0 ? 'active' : '' ?>">
            <span class="rts-ava all"><?= icon('list',16) ?></span>
            <span class="rts-ci"><strong>Barcha yozishmalar</strong><small class="muted">hammasi</small></span>
        </a>
        <?php foreach ($couriers as $c): ?>
            <a href="?courier_id=<?= $c['id'] ?>" class="rts-courier <?= $selId === (int)$c['id'] ? 'active' : '' ?>" data-id="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>">
                <span class="rts-ava">K<?= str_pad((string)$c['id'],3,'0',STR_PAD_LEFT) ?></span>
                <span class="rts-ci"><strong><?= e($c['name']) ?></strong><small class="muted"><?= e($c['phone']) ?></small></span>
                <span class="rts-online" data-online="<?= $c['id'] ?>"></span>
            </a>
        <?php endforeach; ?>
        <?php if (!$couriers): ?><p class="muted small" style="padding:12px">Kuryerlar yo'q.</p><?php endif; ?>
    </aside>

    <!-- O'ng: yozishmalar + gapirish -->
    <section class="rts-main">
        <div class="rts-chat-head">
            <div>
                <strong id="rtsTitle"><?= $selId ? 'Kuryer bilan yozishma' : 'Barcha yozishmalar' ?></strong>
                <div class="muted small" id="rtsSub">Tarix avtomatik yangilanadi</div>
            </div>
            <span class="rts-live"><span class="dot-live"></span> jonli</span>
        </div>

        <div class="rts-log" id="rtsLog">
            <div class="rts-empty muted">Yuklanmoqda...</div>
        </div>

        <div class="rts-talk">
            <button type="button" class="ptt-btn big" id="adminPtt" <?= $selId ? '' : 'disabled' ?>>
                <?= icon('mic',30) ?>
            </button>
            <div class="rts-talk-info">
                <strong id="pttLabel"><?= $selId ? 'Bosib turib gapiring' : 'Avval kuryer tanlang' ?></strong>
                <span class="ptt-status" id="adminPttStatus"></span>
            </div>
        </div>
    </section>
</div>

<script>
window.__rtsCourierId = <?= $selId ?>;
window.__rtsCourierName = <?= json_encode($selId ? '' : '') ?>;
<?php if ($selId):
    foreach ($couriers as $c) { if ((int)$c['id'] === $selId) { echo "window.__rtsCourierName = ".json_encode($c['name']).";"; } }
endif; ?>
</script>
<script src="/assets/js/ratsiya.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
