</main>

<?php if (!empty($navItems)): ?>
<nav class="bottom-nav">
    <?php foreach ($navItems as $it): ?>
        <a href="<?= e($it[0]) ?>" class="<?= str_contains($cur, ltrim($it[0], '/')) ? 'active' : '' ?>">
            <span class="bn-ic">
                <?= icon($it[1], 22) ?>
                <?php if (!empty($it[3])): ?><i class="dot"><?= (int)$it[3] ?></i><?php endif; ?>
            </span>
            <span class="bn-label"><?= e($it[2]) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<footer class="footer">
    <p>© <?= date('Y') ?> Dostavka — yetkazib berish xizmati</p>
</footer>
<script src="/assets/js/app.js"></script>
</body>
</html>
