  </main>
</div><!-- /.shell -->

<div class="toasts" id="toasts" aria-live="polite"></div>
<script>window.APP = { base: '<?= e(BASE) ?>', hasYandex: <?= YANDEX_GEOCODER_KEY !== '' ? 'true' : 'false' ?> };</script>
<script src="<?= BASE ?>/assets/js/common.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/common.js') ?>"></script>
<?php if ($pageScript !== ''): ?>
<script src="<?= BASE ?>/assets/js/<?= e($pageScript) ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/js/' . $pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
