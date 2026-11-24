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
<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Факсограмма №<?= $id ?></title>
<style>body{font:12pt "Times New Roman",serif;color:#111}h1{text-align:center;font-size:16pt;margin:0 0 24pt}table{width:100%;border-collapse:collapse;margin:18pt 0}td{border:1px solid #444;padding:7pt;vertical-align:top}.label{width:32%;font-weight:bold}.sign{margin-top:42pt}</style></head><body>
<h1>ФАКСОГРАММА № <?= $id ?></h1>
<p><b>Кому:</b> __________________________________________</p><p><b>От:</b> _____________________________________________</p>
<p><b>Дата составления:</b> <?= e(date('d.m.Y')) ?></p>
<p>Сообщаем о регистрации следующего события по указанному адресу:</p>
<table>
<tr><td class="label">Номер записи</td><td><?= $id ?></td></tr>
<tr><td class="label">Адрес</td><td><?= e((string)$outage['address']) ?></td></tr>
<tr><td class="label">Категория</td><td><?= e($type) ?></td></tr>
<tr><td class="label">Причина</td><td><?= e((string)($outage['reason'] ?? 'не указана')) ?></td></tr>
<tr><td class="label">Планируемое начало</td><td><?= e($date($outage['planned_start'] ?? null)) ?></td></tr>
<tr><td class="label">Фактическое начало</td><td><?= e($date($outage['actual_start'] ?? null)) ?></td></tr>
<tr><td class="label">Плановое восстановление</td><td><?= e($date($outage['planned_end'] ?? null)) ?></td></tr>
<tr><td class="label">Затронуто объектов</td><td><?php foreach ($counts as $label => $count): ?><?= e($label) ?>: <?= $count ?><br><?php endforeach; ?></td></tr>
<tr><td class="label">Статус</td><td><?= (int)$outage['is_active'] === 1 ? 'Активно' : 'Восстановлено' ?></td></tr>
<tr><td class="label">Зарегистрировал</td><td><?= e((string)($outage['operator_name'] ?? '')) ?></td></tr>
</table>
<p>Просим принять информацию к сведению.</p><p class="sign">Ответственное лицо: ____________________ &nbsp;&nbsp; Подпись: ____________</p>
</body></html>