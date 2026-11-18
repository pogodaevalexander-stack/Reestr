<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$editingId = max(0, (int)($_GET['id'] ?? 0));
$page       = 'outages';
$pageTitle  = $editingId > 0 ? 'Изменить отключение' : 'Новое отключение';
$pageScript = 'outage_form.js';
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-12 record-form-panel">
    <div class="panel-head slim">
      <h2 class="panel-title" id="of-form-title"><?= $editingId > 0 ? 'Изменить отключение' : 'Регистрация отключения' ?></h2>
      <a class="btn ghost sm" href="<?= BASE ?>/outages.php">К списку</a>
    </div>
    <form class="form record-form" id="outage-form" novalidate>
      <label class="field">
        <span class="field-label">Адрес дома</span>
        <select id="of-house" required><option value="">Загрузка адресов</option></select>
      </label>
      <fieldset class="field type-radio">
        <span class="field-label">Тип отключения</span>
        <label class="tr tr-hot"><input type="radio" name="of-type" value="hot_water" checked><span>Горячая вода (ГВС)</span></label>
        <label class="tr tr-heat"><input type="radio" name="of-type" value="heating"><span>Отопление</span></label>
      </fieldset>
      <label class="field">
        <span class="field-label">Причина</span>
        <input type="text" id="of-reason" placeholder="Причина отключения">
      </label>
      <label class="field">
        <span class="field-label">Планируемое время отключения</span>
        <input type="datetime-local" id="of-planned-start">
      </label>
      <label class="field">
        <span class="field-label">Фактическое время отключения</span>
        <input type="datetime-local" id="of-actual-start" value="<?= e(date('Y-m-d\\TH:i')) ?>" required>
      </label>
      <label class="field">
        <span class="field-label">Плановое восстановление</span>
        <input type="datetime-local" id="of-planned-end">
      </label>
      <div class="field-actions">
        <button type="submit" class="btn primary" id="of-save">Зарегистрировать</button>
        <button type="button" class="btn ghost" id="of-toggle" hidden></button>
        <button type="button" class="btn danger" id="of-delete" hidden>Удалить отключение</button>
        <a class="btn ghost" href="<?= BASE ?>/outages.php">Отмена</a>
      </div>
      <input type="hidden" id="of-id" value="<?= $editingId ?>">
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
