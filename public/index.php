<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page       = 'main';
$pageTitle  = 'Главная';
$pageScript = 'main.js';
require __DIR__ . '/partials/head.php';
?>

<section class="stats">
  <article class="stat" style="--c:var(--red)">
    <p class="stat-label">Дома в отключении</p>
    <b class="stat-num" data-stat="outages">–</b>
  </article>
  <article class="stat" style="--c:var(--teal)">
    <p class="stat-label">Звонков за день</p>
    <b class="stat-num" data-stat="calls_today">–</b>
  </article>
  <article class="stat" style="--c:var(--blue)">
    <p class="stat-label">Дома в реестре</p>
    <b class="stat-num" data-stat="houses">–</b>
  </article>
  <article class="stat" style="--c:var(--amber)">
    <p class="stat-label">Обращений всего</p>
    <b class="stat-num" data-stat="appeals">–</b>
  </article>
</section>

<div class="main-grid">
  <section class="panel map-panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">Карта города</h2>
        <p class="panel-sub" id="map-note"></p>
      </div>
      <div class="legend" id="legend" role="group" aria-label="Слои карты">
        <button type="button" class="chip is-on chip-hot"  data-layer="hot_water"><i></i>ГВС</button>
        <button type="button" class="chip is-on chip-heat" data-layer="heating"><i></i>Отопление</button>
        <button type="button" class="chip is-on chip-call" data-layer="appeals"><i></i>Звонки</button>
      </div>
    </div>
    <div class="map-wrap">
      <div id="map" class="map"></div>
      <div class="map-fallback" id="map-fallback" hidden>
        <h3>Карта не подключена</h3>
      </div>
    </div>
  </section>

  <section class="panel side-panel">
    <div class="panel-head slim">
      <div>
        <h2 class="panel-title">Сегодня в поддержке</h2>
        <p class="panel-sub" id="today-note"></p>
      </div>
      <a class="btn ghost sm" href="<?= BASE ?>/appeals.php">Все →</a>
    </div>
    <ol class="call-list" id="today-list"><li class="empty">Загружаем…</li></ol>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
