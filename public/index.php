<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page       = 'main';
$pageTitle  = 'Главная';
$pageScript = 'main.js';
require __DIR__ . '/partials/head.php';
?>

<section class="stats">
  <a class="stat stat-link" style="--c:var(--red)" href="<?= BASE ?>/outages.php"><p class="stat-label">МКД в отключении</p><b class="stat-num" data-stat="outages_mkd">–</b></a>
  <a class="stat stat-link" style="--c:var(--orange)" href="<?= BASE ?>/outages.php"><p class="stat-label">Соц. объекты в отключении</p><b class="stat-num" data-stat="outages_social">–</b></a>
  <a class="stat stat-link" style="--c:var(--ink)" href="<?= BASE ?>/outages.php"><p class="stat-label">Прочее в отключении</p><b class="stat-num" data-stat="outages_other">–</b></a>
  <a class="stat stat-link" style="--c:var(--amber)" href="<?= BASE ?>/appeals.php">
    <p class="stat-label">Обращений в работе</p>
    <b class="stat-num" data-stat="appeals_in_work">–</b>
  </a>
</section>

<div class="dashboard-layout">
<section class="panel map-panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Карта города</h2>
        <p class="panel-sub" id="map-note"></p>
      </div>
    </div>
    <div class="map-wrap">
      <div id="map" class="map"></div>
      <div class="map-fallback" id="map-fallback" hidden>
        <h3>Карта не подключена</h3>
      </div>
    </div>
  </section>

<section class="panel dashboard-charts">
  <div class="panel-head slim monthly-chart-head">
    <div><h2 class="panel-title" id="monthly-chart-title">Обращения за месяц</h2><p class="panel-sub" id="monthly-chart-total">Загрузка…</p></div>
    <a class="btn ghost sm" id="monthly-chart-link" href="<?= BASE ?>/appeals.php">Все →</a>
  </div>
  <div class="chart-switch" role="tablist" aria-label="Тип графика">
    <button class="chart-switch-btn is-active" type="button" role="tab" aria-selected="true" data-chart="appeals">Обращения</button>
    <button class="chart-switch-btn" type="button" role="tab" aria-selected="false" data-chart="outages">Отключения</button>
  </div>
  <div class="chart-wrap"><canvas id="monthly-chart" aria-label="Количество записей по дням текущего месяца"></canvas></div>
</section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
