<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$isImprovementPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'improvements.php';
$page       = $isImprovementPage ? 'improvements' : 'outages';
$pageTitle  = $isImprovementPage ? 'Благоустройство' : 'Отключения';
$pageScript = 'outages.js';
$newUrl     = BASE . '/outage_form.php' . ($isImprovementPage ? '?type=improvement' : '');
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-12 outages-panel">
    <div class="panel-head slim">
      <div>
        <h2 class="panel-title">Список <?= $isImprovementPage ? 'благоустройств' : 'отключений' ?></h2>
        <p class="panel-sub" id="ot-count">записей: 0</p>
      </div>
      <div class="header-actions">
        <?php if (!$isImprovementPage): ?><a class="btn ghost excel-export" href="<?= BASE ?>/export.php?type=outages" download="outages.xlsx" data-filename="outages.xlsx">Экспорт в Excel</a><?php endif; ?>
        <?php if (($currentUser['role'] ?? '') !== 'user'): ?><a class="btn primary" href="<?= e($newUrl) ?>"><?= $isImprovementPage ? 'Новое благоустройство' : 'Новое отключение' ?></a><?php endif; ?>
      </div>
    </div>
    <div class="toolbar">
      <div class="appeal-search">
        <div class="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" id="ot-q" placeholder="Поиск <?= $isImprovementPage ? 'благоустройств' : 'отключений' ?>">
        </div>
        <div class="filter-picker" id="ot-filter-picker">
          <button type="button" class="btn ghost filter-trigger" id="ot-filter-toggle" aria-expanded="false" aria-controls="ot-filter-popup">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16l-6.5 7.5v5l-3 1.5v-6.5L4 5Z"/></svg>
            <span>Фильтр</span>
          </button>
          <div class="filter-popup outage-filter-popup" id="ot-filter-popup" hidden>
            <strong>Фильтры отключений</strong>
            <span class="filter-group-title">Тип</span>
            <label><input type="radio" name="ot-type" value="all" checked> Все типы</label>
            <label><input type="radio" name="ot-type" value="shutdown"> Отключение</label>
            <label><input type="radio" name="ot-type" value="damage"> Повреждение</label>
            <span class="filter-group-title">Статус</span>
            <label><input type="radio" name="ot-status" value="all" checked> Все статусы</label>
            <label><input type="radio" name="ot-status" value="active"> Активные</label>
            <label><input type="radio" name="ot-status" value="restored"> Восстановленные</label>
            <span class="filter-group-title">Период фактического начала</span>
            <label class="filter-period"><span>С</span><input type="datetime-local" id="ot-from"></label>
            <label class="filter-period"><span>По</span><input type="datetime-local" id="ot-to"></label>
            <div class="header-actions">
              <button type="button" class="btn primary sm" id="ot-filter-search">Поиск</button>
              <button type="button" class="btn ghost sm" id="ot-filter-reset">Сбросить фильтры</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl" id="ot-table">
        <thead><tr><th>Номер</th><th>Статус</th><th>Адрес</th><th>Тип</th><th>Причина</th><th>МКД</th><th>Соц. объекты</th><th>Прочее</th><th>Оператор</th><th>План. начало</th><th>Факт. начало</th><th>План. конец</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>