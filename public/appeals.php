<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page       = 'appeals';
$pageTitle  = 'Обращения';
$pageScript = 'appeals.js';
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-12 appeals-panel">
    <div class="panel-head slim">
      <div>
        <h2 class="panel-title">Обращения граждан</h2>
        <p class="panel-sub" id="ap-count">записей: 0</p>
      </div>
      <div class="header-actions">
        <a class="btn ghost excel-export" href="<?= BASE ?>/export.php?type=appeals" download="appeals.xlsx" data-filename="appeals.xlsx">Экспорт в Excel</a>
        <a class="btn primary" href="<?= BASE ?>/appeal_form.php">Новое обращение</a>
      </div>
    </div>
    <div class="toolbar">
      <div class="appeal-search">
        <div class="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" id="ap-q" placeholder="Поиск обращений">
        </div>
        <div class="filter-picker" id="ap-filter-picker">
          <button type="button" class="btn ghost filter-trigger" id="ap-filter-toggle" aria-expanded="false" aria-controls="ap-filter-popup">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16l-6.5 7.5v5l-3 1.5v-6.5L4 5Z"/></svg>
            <span>Фильтр</span>
          </button>
          <div class="filter-popup appeal-filter-popup" id="ap-filter-popup" hidden>
            <strong>Искать по полям</strong>
            <label><input type="checkbox" value="id" data-search-field> ID</label>
            <label><input type="checkbox" value="address" data-search-field> Адрес</label>
            <label><input type="checkbox" value="fio" data-search-field> ФИО</label>
            <label><input type="checkbox" value="phone" data-search-field> Телефон</label>
            <label><input type="checkbox" value="type" data-search-field> Тип обращения</label>
            <label><input type="checkbox" value="status" data-search-field> Статус</label>
            <label><input type="checkbox" value="call_at" data-search-field> Дата звонка</label>
            <label><input type="checkbox" value="operator_name" data-search-field> Оператор</label>
            <label><input type="checkbox" value="created_at" data-search-field> Дата создания</label>
            <label><input type="checkbox" value="updated_at" data-search-field> Дата изменения</label>
            <span class="filter-group-title">Период звонка</span>
            <label class="filter-period"><span>С</span><input type="datetime-local" id="ap-from"></label>
            <label class="filter-period"><span>По</span><input type="datetime-local" id="ap-to"></label>
            <button type="button" class="btn ghost sm" id="ap-filter-reset">Сбросить выбор</button>
          </div>
        </div>
      </div>
        <label class="appeal-status-filter">
          <span class="field-label">Статус</span>
          <select id="ap-status-filter">
            <option value="all">Все обращения</option>
            <option value="in_work">В работе</option>
            <option value="completed">Завершено</option>
          </select>
        </label>
    </div>
    <div class="tbl-wrap">
      <table class="tbl" id="ap-table">
        <colgroup>
          <col class="ap-col-id">
          <col class="ap-col-date">
          <col class="ap-col-address">
          <col class="ap-col-fio">
          <col class="ap-col-phone">
          <col class="ap-col-type">
          <col class="ap-col-status">
          <col class="ap-col-operator">
          <col class="ap-col-date">
          <col class="ap-col-date">
        </colgroup>
        <thead>
          <tr>
            <th>ID</th>
            <th>Дата звонка</th>
            <th>Адрес</th>
            <th>ФИО заявителя</th>
            <th>Телефон</th>
            <th>Тип</th>
            <th>Статус</th>
            <th>Оператор</th>
            <th>Создано</th>
            <th>Изменено</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
