<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page = 'archive';
$pageTitle = 'Архив';
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'archive';
$files = glob($directory . DIRECTORY_SEPARATOR . '20??-??.jsonl') ?: [];
rsort($files, SORT_STRING);
$selected = (string)($_GET['month'] ?? '');
$allowedMonths = array_map(static fn(string $path): string => basename($path, '.jsonl'), $files);
if ($selected === '' || !in_array($selected, $allowedMonths, true)) $selected = $allowedMonths[0] ?? '';
$records = [];
if ($selected !== '') {
    $path = $directory . DIRECTORY_SEPARATOR . $selected . '.jsonl';
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry) && isset($entry['entity'], $entry['record'])) $records[] = $entry;
    }
    $records = array_reverse($records);
}
require __DIR__ . '/partials/head.php';
?>
<section class="panel span-12">
  <div class="panel-head slim"><div><h2 class="panel-title">Архив записей</h2><p class="panel-sub">Архивные снимки обращений и отключений</p></div>
    <?php if ($allowedMonths !== []): ?><form method="get"><label class="field-label" for="archive-month">Месяц</label> <select id="archive-month" name="month" onchange="this.form.submit()"><?php foreach ($allowedMonths as $month): ?><option value="<?= e($month) ?>" <?= $month === $selected ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></form><?php endif; ?>
  </div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Дата архива</th><th>Раздел</th><th>Номер</th><th>Дата записи</th><th>Адрес</th><th>Описание</th><th>Статус</th></tr></thead><tbody>
  <?php foreach ($records as $entry): $record = $entry['record']; $appeal = $entry['entity'] === 'appeals'; ?>
    <tr>
      <td class="mono sm"><?= e((string)($entry['archived_at'] ?? '')) ?></td>
      <td><?= $appeal ? 'Обращение' : 'Отключение' ?></td>
      <td class="mono"><?= (int)($entry['record_id'] ?? 0) ?></td>
      <td class="mono sm"><?= e((string)($appeal ? ($record['call_at'] ?? '') : ($record['actual_start'] ?? ''))) ?></td>
      <td><?= e((string)($record['address'] ?? '')) ?></td>
      <td><?= e((string)($appeal ? ($record['subject'] ?? '') : ($record['reason'] ?? ''))) ?></td>
      <td><?= $appeal ? (($record['status'] ?? '') === 'completed' ? 'Завершено' : 'В работе') : ((int)($record['is_active'] ?? 0) === 1 ? 'Активно' : 'Восстановлено') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($records === []): ?><tr><td colspan="7" class="empty">В выбранном месяце архивных записей нет.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>