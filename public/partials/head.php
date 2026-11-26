<?php
require_once __DIR__ . '/../../src/bootstrap.php';
$currentUser = auth_user();
$page        = $page ?? 'main';
$pageTitle   = $pageTitle ?? 'Главная';
$pageScript  = $pageScript ?? '';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · Реестр АОТК</title>
<link rel="icon" href="<?= BASE ?>/logo/Итоговый_ImgID1.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;800&family=JetBrains+Mono:wght@500;700&family=Unbounded:wght@500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/app.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/app.css') ?>">

<!-- === ДОБАВЛЕНО: общий объект window.APP для JS === -->
<script>
window.APP = {
  base: <?= json_encode(BASE) ?>,
  hasYandex: <?= (YANDEX_GEOCODER_KEY !== '') ? 'true' : 'false' ?>,
  readOnly: <?= ($currentUser['role'] ?? '') === 'user' ? 'true' : 'false' ?>
};
</script>

<script>window.YMAPS = { mapKey: '<?= e(YANDEX_MAPS_KEY) ?>', center: <?= json_encode(MAP_CENTER) ?>, bounds: <?= json_encode(MAP_BOUNDS) ?>, zoom: <?= (int)MAP_ZOOM ?> };</script>
<?php if (YANDEX_MAPS_KEY !== ''): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= e(YANDEX_MAPS_KEY) ?>&lang=ru_RU"></script>
<?php endif; ?>
</head>
<body data-page="<?= e($page) ?>" data-read-only="<?= ($currentUser['role'] ?? '') === 'user' ? '1' : '0' ?>">

<aside class="side">
  <div class="brand">
    <div class="brand-mark" aria-label="АОТК"></div>
    <div class="brand-text">
      <b>РЕЕСТР АОТК</b>
    </div>
  </div>

  <nav class="nav" aria-label="Разделы">
    <a class="nav-item<?= $page === 'main' ? ' is-active' : '' ?>" href="<?= BASE ?>/index.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/></svg>
      <span>Главная</span>
    </a>
    <a class="nav-item<?= $page === 'outages' ? ' is-active' : '' ?>" href="<?= BASE ?>/outages.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/></svg>
      <span>Отключения</span><i class="nav-badge orange" id="badge-outages" hidden></i>
    </a>
    <a class="nav-item<?= $page === 'improvements' ? ' is-active' : '' ?>" href="<?= BASE ?>/improvements.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M18.4 5.6 5.6 18.4"/></svg>
      <span>Благоустройство</span><i class="nav-badge teal" id="badge-improvements" hidden></i>
    </a>
    <a class="nav-item<?= $page === 'appeals' ? ' is-active' : '' ?>" href="<?= BASE ?>/appeals.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
      <span>Обращения</span><i class="nav-badge" id="badge-calls" hidden></i>
    </a>
    <a class="nav-item<?= $page === 'stats' ? ' is-active' : '' ?>" href="<?= BASE ?>/stats.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h17M8 16v-4M13 16V8M18 16V4"/></svg>
      <span>Статистика</span>
    </a>
    <a class="nav-item<?= $page === 'archive' ? ' is-active' : '' ?>" href="<?= BASE ?>/archive.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v4H4zM5 8v12h14V8M9 12h6M9 16h6"/></svg>
      <span>Архив</span>
    </a>
    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
    <a class="nav-item<?= $page === 'admin_audit' ? ' is-active' : '' ?>" href="<?= BASE ?>/admin_audit.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>
      <span>Журнал действий</span>
    </a>
    <a class="nav-item<?= $page === 'admin_delete' ? ' is-active' : '' ?>" href="<?= BASE ?>/admin_delete.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 10v6m4-6v6"/></svg>
      <span>Удаление по периоду</span>
    </a>
    <a class="nav-item<?= $page === 'accounts' ? ' is-active' : '' ?>" href="<?= BASE ?>/accounts.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6"/></svg>
      <span>Пользователи</span>
    </a>
    <?php endif; ?>
  </nav>

</aside>

<div class="shell">
  <header class="topbar">
    <div>
      <p class="crumb">Реестр АОТК / <?= e($pageTitle) ?></p>
      <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    </div>
    <div class="topbar-right">
      <?php if ($currentUser): ?>
        <span class="user-chip"><?= e($currentUser['full_name'] !== '' ? $currentUser['full_name'] : $currentUser['login']) ?> · <?= e($currentUser['login']) ?> · <?= ['admin' => 'Администратор', 'user' => 'Пользователь'][$currentUser['role'] ?? ''] ?? 'Оператор' ?></span>
        <a class="logout-link" href="<?= BASE ?>/logout.php">Выход</a>
      <?php endif; ?>
      <div class="refresh-controls" aria-label="Обновление страницы">
        <label class="sr-only" for="auto-refresh">Автообновление страницы</label>
        <select id="auto-refresh" title="Интервал автообновления">
          <option value="0">Авто: выкл.</option>
          <option value="30000">Авто: 30 сек.</option>
          <option value="60000">Авто: 1 мин.</option>
          <option value="300000">Авто: 5 мин.</option>
          <option value="600000">Авто: 10 мин.</option>
        </select>
        <button type="button" class="btn ghost sm" id="refresh-now" title="Обновить страницу сейчас">Обновить</button>
      </div>
      <time class="clock mono" id="clock">--:--:--</time>
    </div>
  </header>

  <main class="content">