<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$id = max(0, (int)($_GET['id'] ?? 0));
$outage = (new OutageRepository())->find($id);
if ($outage === null) {
    http_response_code(404);
    exit('Запись не найдена.');
}
$date = static function ($value): string {
    if (!$value) return 'не указано';
    try { return (new DateTimeImmutable((string)$value))->format('d.m.Y H:i'); }
    catch (Throwable) { return (string)$value; }
};
$isImprovement = $outage['type'] === 'improvement';
$type = $isImprovement ? 'Благоустройство' : (['shutdown' => 'Отключение', 'damage' => 'Повреждение'][$outage['type']] ?? 'Отключение');
$counts = [
    'МКД' => (int)$outage['mkd_count'],
    'Социальные объекты' => (int)$outage['social_count'],
    'Прочее' => (int)($outage['other_count'] ?? 0),
];
$filename = 'faxogram_' . $id . '.doc';
header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode('Факсограмма_' . $id . '.doc'));
header('Cache-Control: private, no-store, max-age=0');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Факсограмма №<?= $id ?></title>
<style>
@page { size: A4 portrait; margin: 20mm 15mm 20mm 30mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #111; font: 12pt 'Times New Roman', serif; }
.sheet { width: 165mm; min-height: 257mm; margin: 0 auto; position: relative; }
.top-line { display: flex; justify-content: space-between; align-items: center; margin: 0 0 18mm; }
.doc-no { white-space: nowrap; }
.doc-date { margin-left: 8mm; }
.recipient { margin: 0 0 14mm 8mm; line-height: 1.25; }
.title { text-align: center; margin: 0 0 8mm; font-size: 16pt; font-weight: 700; letter-spacing: 2pt; }
.subject { text-align: center; margin: 0 0 9mm; line-height: 1.25; }
.address-line { text-align: center; margin: 0 0 5mm; }
.period { text-align: center; margin: 0 0 8mm; }
.objects-title { margin: 0 0 3mm 14mm; }
.object-list { margin: 0 0 0 14mm; line-height: 1.35; }
.object-list p { margin: 0 0 2mm; }
.totals { margin: 10mm 0 4mm 14mm; }
.notice { margin: 0 0 18mm 14mm; }
.signature-block { position: absolute; left: 0; right: 0; bottom: 8mm; }
.sign-head { text-align: right; margin: 0 0 16mm; }
.sign-main { display: flex; justify-content: space-between; align-items: flex-end; }
.sign-name { margin: 0; }
.sign-phone { margin: 0; }
.print-hide { display: none; }
@media print {
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .sheet { width: 100%; }
}
</style>
</head>
<body>
<div class="sheet">
  <div class="top-line">
    <div class="doc-no">№ <?= $id ?></div>
    <div class="doc-date">от <?= e(date('d.m.Y')) ?> г.</div>
  </div>

  <div class="recipient">
    Начальнику УЕДДС<br>
    Администрации г. Омска
  </div>

  <h1 class="title">Ф А К С О Г Р А М М А</h1>

  <p class="subject">Для проведения <?= $isImprovement ? 'благоустройства' : ($outage['type'] === 'damage' ? 'ремонтных работ' : 'ремонтных работ') ?> на сетях<br>
  по адресу <?= e((string)$outage['address']) ?> будет произведено <?= $isImprovement ? 'благоустройство' : 'отключение' ?>:</p>

  <p class="address-line"><b><?= e($type) ?></b></p>
  <p class="period">
    с <?= e($outage['actual_start'] ? $date($outage['actual_start']) : 'не указано') ?><br>
    до <?= e($outage['planned_end'] ? $date($outage['planned_end']) : 'не указано') ?>
  </p>

  <p class="objects-title"><b>Отключаемые объекты:</b></p>
  <div class="object-list">
    <p>— <?= e((string)$outage['address']) ?></p>
  </div>

  <p class="totals"><b>Всего: <?= (int)($outage['mkd_count'] ?? 0) + (int)($outage['social_count'] ?? 0) + (int)($outage['other_count'] ?? 0) ?> объектов
    (<?= (int)$outage['mkd_count'] ?> — МКД, <?= (int)$outage['social_count'] ?> — соц., <?= (int)($outage['other_count'] ?? 0) ?> — прочие)</b></p>
  <p class="notice">Причина: <?= e((string)($outage['reason'] ?? 'не указана')) ?></p>
  <p class="notice">Статус: <?= (int)$outage['is_active'] === 1 ? 'Активно' : 'Восстановлено' ?></p>

  <div class="signature-block">
    <p class="sign-head">Зам. Генерального директора<br>по технической политике — главный инженер</p>
    <div class="sign-main">
      <p class="sign-name"><?= e((string)($outage['operator_name'] ?? '')) ?></p>
      <p class="sign-phone">Подпись: __________________</p>
    </div>
  </div>
</div>
</body>
</html>