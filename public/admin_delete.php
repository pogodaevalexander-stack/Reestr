<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();
$page = 'admin_delete';
$pageTitle = 'Удаление по периоду';
$pageScript = 'admin_delete.js';
require __DIR__ . '/partials/head.php';
?>
<div class="page-grid">
  <section class="panel span-12">
    <div class="panel-head"><div><h2 class="panel-title">Удаление записей за период</h2><p class="panel-sub">Действие необратимо. Удалённые записи останутся отражены в журнале действий.</p></div></div>
    <form class="form record-form" id="period-delete-form">
      <label class="field"><span class="field-label">Раздел</span><select id="pd-entity" required><option value="appeals">Обращения</option><option value="outages">Отключения</option></select></label>
      <label class="field"><span class="field-label">С</span><input type="datetime-local" id="pd-from" required></label>
      <label class="field"><span class="field-label">По</span><input type="datetime-local" id="pd-to" required></label>
      <div class="field-actions"><button class="btn danger" type="submit">Удалить выбранные записи</button></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
