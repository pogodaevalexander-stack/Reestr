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
<title><?= e($pageTitle) ?> · Реестр отключений</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23f59f0a' d='M12 2s7 7.6 7 12.4A7 7 0 0 1 5 14.4C5 9.6 12 2 12 2Z'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;800&family=JetBrains+Mono:wght@500;700&family=Unbounded:wght@500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/app.css">
<script>window.YMAPS = { mapKey: '<?= e(YANDEX_MAPS_KEY) ?>', center: <?= json_encode(MAP_CENTER) ?>, bounds: <?= json_encode(MAP_BOUNDS) ?>, zoom: <?= (int)MAP_ZOOM ?> };</script>
<?php if (YANDEX_MAPS_KEY !== ''): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= e(YANDEX_MAPS_KEY) ?>&lang=ru_RU"></script>
<?php endif; ?>
</head>
<body data-page="<?= e($page) ?>">

<aside class="side">
  <div class="brand">
    <div class="brand-mark">Р</div>
    <div class="brand-text">
      <b>РЕЕСТР</b>
    </div>
  </div>

  <nav class="nav" aria-label="Разделы">
    <a class="nav-item<?= $page === 'main' ? ' is-active' : '' ?>" href="<?= BASE ?>/index.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/></svg>
      <span>Главная</span>
    </a>
    <a class="nav-item<?= $page === 'outages' ? ' is-active' : '' ?>" href="<?= BASE ?>/outages.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/></svg>
      <span>Отключения</span><i class="nav-badge" id="badge-outages" hidden></i>
    </a>
    <a class="nav-item<?= $page === 'appeals' ? ' is-active' : '' ?>" href="<?= BASE ?>/appeals.php">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
      <span>Обращения</span><i class="nav-badge teal" id="badge-calls" hidden></i>
    </a>
  </nav>

</aside>

<div class="shell">
  <header class="topbar">
    <div>
      <p class="crumb">Реестр / <?= e($pageTitle) ?></p>
      <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    </div>
    <div class="topbar-right">
      <?php if ($currentUser): ?>
        <span class="user-chip">Пользователь: <?= e($currentUser['login']) ?></span>
        <a class="logout-link" href="<?= BASE ?>/logout.php">Выход</a>
      <?php endif; ?>
      <span class="live"><i></i>онлайн</span>
      <time class="clock mono" id="clock">--:--:--</time>
    </div>
  </header>

  <main class="content">
