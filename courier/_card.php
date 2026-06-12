<?php
/**
 * Kuryer buyurtma kartasi (umumiy).
 * Sodda, mobil ekranga mos, kam yozuvli ko'rinish.
 *
 * Foydalanish:
 *   courier_card($order, $items, 'available' | 'active' | 'done');
 */
if (!function_exists('courier_card')) {
    function courier_card(array $o, array $items, string $mode): void
    {
        $statusName  = status_label($o['status']);
        $statusColor = status_color($o['status']);
        $itemCount   = array_sum(array_map(fn($i) => (int)$i['quantity'], $items));
        ?>
        <article class="ocard">
            <div class="ocard-top">
                <div class="ocard-id">#<?= (int)$o['id'] ?></div>
                <span class="ostatus" style="--c:<?= $statusColor ?>"><?= e($statusName) ?></span>
                <span class="ocard-earn"><?= money(courier_earn($o)) ?></span>
            </div>

            <div class="ocard-route">
                <div class="rt-row">
                    <span class="rt-dot pickup"></span>
                    <span class="rt-text"><?= e($o['pickup_name'] ?: 'Do\'kon') ?></span>
                </div>
                <div class="rt-row">
                    <span class="rt-dot drop"></span>
                    <span class="rt-text"><?= e($o['address']) ?></span>
                </div>
            </div>

            <div class="ocard-tags">
                <?php if ($o['distance_km'] > 0): ?><span class="otag"><?= icon('route',13) ?> <?= e($o['distance_km']) ?> km</span><?php endif; ?>
                <span class="otag"><?= icon('package',13) ?> <?= (int)$itemCount ?> dona</span>
                <span class="otag <?= ($o['delivery_zone'] ?? 'in') === 'out' ? 'warn' : 'ok' ?>"><?= e(zone_label($o['delivery_zone'] ?? 'in')) ?></span>
            </div>

            <?php if ($mode !== 'available' && !empty($o['note'])): ?>
                <p class="ocard-note"><?= icon('edit',13) ?> <?= e($o['note']) ?></p>
            <?php endif; ?>

            <?php if ($mode === 'available'): ?>
                <form method="post" class="ocard-act">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="accept">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <button class="btn primary block"><?= icon('check',18) ?> Qabul qilish</button>
                </form>

            <?php elseif ($mode === 'active'): ?>
                <div class="ocard-act">
                    <a class="btn ghost" href="tel:<?= e($o['phone']) ?>"><?= icon('phone',16) ?></a>
                    <?php if ($o['lat'] && $o['lng']): ?>
                        <a class="btn ghost" target="_blank"
                           href="https://www.google.com/maps/dir/?api=1<?= ($o['pickup_lat'] && $o['pickup_lng']) ? '&origin='.e($o['pickup_lat']).','.e($o['pickup_lng']) : '' ?>&destination=<?= e($o['lat']) ?>,<?= e($o['lng']) ?>">
                           <?= icon('nav',16) ?> Yo'l
                        </a>
                    <?php endif; ?>
                    <?php if ($o['status'] === 'accepted'): ?>
                        <form method="post" class="grow"><?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="picked_up">
                            <button class="btn primary block"><?= icon('package',16) ?> Oldim</button>
                        </form>
                    <?php elseif ($o['status'] === 'picked_up'): ?>
                        <form method="post" class="grow"><?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="on_way">
                            <button class="btn primary block"><?= icon('truck',16) ?> Yo'ldaman</button>
                        </form>
                    <?php elseif ($o['status'] === 'on_way'): ?>
                        <form method="post" class="grow"><?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="delivered">
                            <button class="btn success block"><?= icon('check',16) ?> Yetkazdim</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" data-confirm="Buyurtmani bekor qilasizmi?"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="cancelled">
                        <button class="btn danger-ghost"><?= icon('x',16) ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }
}
