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
    <div class="panel-head slim"><div><h2 class="panel-title">Журнал действий</h2><p class="panel-sub" id="audit-count">Полная история изменений записей, закрытий и удалений</p></div></div>
    <div class="toolbar audit-toolbar filter-toolbar">
      <label class="search toolbar-search">
        <input type="search" id="audit-q" placeholder="Поиск по журналу" aria-label="Поиск по журналу">
      </label>
      <div class="filter-picker" id="audit-filter-picker">
        <button type="button" class="btn ghost filter-trigger" id="audit-filter-toggle" aria-expanded="false" aria-controls="audit-filter-popup"><span>Фильтры</span></button>
        <div class="filter-popup audit-filter-popup" id="audit-filter-popup" hidden>
          <strong>Фильтры журнала</strong>
          <label class="filter-period"><span>С</span><input type="datetime-local" id="audit-from"></label>
          <label class="filter-period"><span>По</span><input type="datetime-local" id="audit-to"></label>
          <label>Оператор <select id="audit-actor"><option value="">Все операторы</option></select></label>
          <label>Действие <select id="audit-action"><option value="">Все действия</option></select></label>
          <label>Раздел <select id="audit-entity"><option value="">Все разделы</option></select></label>
          <button type="button" class="btn ghost sm" id="audit-filter-reset">Сбросить фильтры</button>
        </div>
      </div>
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Дата и время</th><th>Пользователь</th><th>Действие</th><th>Запись</th><th>Изменения</th></tr></thead><tbody id="st-audit"><tr><td colspan="5" class="empty">Загружаем…</td></tr></tbody></table></div>
  </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
