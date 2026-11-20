<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();
$page = 'admin_audit';
$pageTitle = 'Журнал действий';
$pageScript = 'admin_audit.js';
require __DIR__ . '/partials/head.php';
?>
<div class="page-grid">
  <section class="panel span-12">
    <div class="panel-head slim"><div><h2 class="panel-title">Журнал действий</h2><p class="panel-sub">Полная история изменений записей, закрытий и удалений</p></div></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Дата и время</th><th>Пользователь</th><th>Действие</th><th>Запись</th><th>Изменения</th></tr></thead><tbody id="st-audit"><tr><td colspan="5" class="empty">Загружаем…</td></tr></tbody></table></div>
  </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
