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
        <div class="sg-wrap" id="of-address-wrap">
          <input type="text" id="of-address" class="sg-input" placeholder="Введите адрес или выберите подсказку" autocomplete="off" required minlength="5">
          <input type="hidden" class="sg-lat"><input type="hidden" class="sg-lng">
          <span class="sg-badge" hidden>Яндекс</span><ul class="sg-list" hidden></ul>
        </div>
      </label>
      <label class="field"><span class="field-label">Категория</span>
        <select id="of-type" required><option value="shutdown">Отключение</option><option value="damage">Повреждение</option><option value="improvement">Благоустройство</option></select>
      </label>
      <label class="field" id="of-detail-field"><span class="field-label">Вид</span>
        <select id="of-detail-type" required>
          <option value="full_shutdown">Полное отключение</option>
          <option value="supply_without_circulation">Подача без циркуляции</option>
          <option value="damage_without_shutdown">Повреждение без отключения</option>
        </select>
      </label>
      <label class="field" id="of-mkd-field">
        <span class="field-label">Количество МКД</span>
        <input type="number" id="of-mkd-count" min="0" step="1" required>
      </label>
      <label class="field" id="of-social-field">
        <span class="field-label">Количество объектов соц. жилья</span>
        <input type="number" id="of-social-count" min="0" step="1" required>
      </label>
      <label class="field">
        <span class="field-label">Причина</span>
        <input type="text" id="of-reason" placeholder="Причина отключения">
      </label>
      <label class="field" id="of-planned-start-field">
        <span class="field-label">Планируемое время отключения</span>
        <input type="datetime-local" id="of-planned-start">
      </label>
      <label class="field" id="of-actual-start-field">
        <span class="field-label">Фактическое время отключения</span>
        <input type="datetime-local" id="of-actual-start" value="<?= e(date('Y-m-d\\TH:i')) ?>" required>
      </label>
      <label class="field" id="of-planned-end-field">
        <span class="field-label">Плановое восстановление</span>
        <input type="datetime-local" id="of-planned-end">
      </label>
      <div class="field-actions">
        <button type="submit" class="btn primary" id="of-save">Зарегистрировать</button>
        <button type="button" class="btn ghost" id="of-toggle" hidden></button>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?><button type="button" class="btn danger" id="of-delete" hidden>Удалить отключение</button><?php endif; ?>
        <a class="btn ghost" href="<?= BASE ?>/outages.php">Отмена</a>
      </div>
      <input type="hidden" id="of-id" value="<?= $editingId ?>">
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
