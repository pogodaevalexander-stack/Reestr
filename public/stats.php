<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page = 'stats';
$pageTitle = 'Статистика';
$pageScript = 'stats.js';
require __DIR__ . '/partials/head.php';
?>
<div class="page-grid">
  <section class="panel span-12">
    <div class="panel-head slim"><div><h2 class="panel-title">Работа операторов</h2><p class="panel-sub">Количество принятых и закрытых обращений за период</p></div></div>
    <div class="toolbar">
      <label class="field period-filter"><span class="field-label">С</span><input type="datetime-local" id="st-from"></label>
      <label class="field period-filter"><span class="field-label">По</span><input type="datetime-local" id="st-to"></label>
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Оператор</th><th>Принято обращений</th><th>Закрыто обращений</th></tr></thead><tbody id="st-operators"><tr><td colspan="3" class="empty">Загружаем…</td></tr></tbody></table></div>
  </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
