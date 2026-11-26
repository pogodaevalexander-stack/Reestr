<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

if ((auth_user()['role'] ?? '') === 'user') {
    header('Location: ' . BASE . '/outages.php');
    exit;
}

$editingId         = max(0, (int)($_GET['id'] ?? 0));
$isImprovementPage = (string)($_GET['type'] ?? '') === 'improvement';

// id закрытого отключения для предзаполнения адреса (данные подтянет JS через API)
$fromOutageId = max(0, (int)($_GET['from_outage'] ?? 0));
if ($editingId > 0) $fromOutageId = 0;

$page       = $isImprovementPage ? 'improvements' : 'outages';
$pageTitle  = $isImprovementPage
    ? ($editingId > 0 ? 'Изменить благоустройство' : 'Новое благоустройство')
    : ($editingId > 0 ? 'Изменить отключение' : 'Новое отключение');
$formTitle  = $isImprovementPage
    ? ($editingId > 0 ? 'Изменить благоустройство' : 'Регистрация благоустройства')
    : ($editingId > 0 ? 'Изменить отключение' : 'Регистрация отключения');
$listUrl    = BASE . '/' . ($isImprovementPage ? 'improvements.php' : 'outages.php');
$pageScript = 'outage_form.js';
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-12 record-form-panel">
    <div class="panel-head slim">
      <h2 class="panel-title" id="of-form-title"><?= e($formTitle) ?></h2>
      <a class="btn ghost sm" href="<?= e($listUrl) ?>">К списку</a>
    </div>
    <form class="form record-form" id="outage-form" novalidate data-list-url="<?= e($listUrl) ?>">
      <label class="field">
        <span class="field-label">Адрес дома</span>
        <div class="sg-wrap" id="of-address-wrap">
          <input type="text" id="of-address" class="sg-input" placeholder="Введите адрес или выберите подсказку" autocomplete="off" required minlength="5">
          <input type="hidden" class="sg-lat"><input type="hidden" class="sg-lng">
          <span class="sg-badge" hidden>Яндекс</span><ul class="sg-list" hidden></ul>
        </div>
      </label>
      <label class="field"><span class="field-label">Категория</span>
        <select id="of-type" required <?= $isImprovementPage ? 'disabled' : '' ?>>
          <option value="shutdown">Отключение</option>
          <option value="damage">Повреждение</option>
          <option value="improvement" <?= $isImprovementPage ? 'selected' : '' ?>>Благоустройство</option>
        </select>
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
        <span class="field-label">Количество соц. объектов</span>
        <input type="number" id="of-social-count" min="0" step="1" required>
      </label>
      <label class="field" id="of-other-field">
        <span class="field-label">Количество прочего</span>
        <input type="number" id="of-other-count" min="0" step="1" required>
      </label>
      <label class="field">
        <span class="field-label">Причина</span>
        <textarea id="of-reason" class="reason-input" placeholder="Причина" rows="5"></textarea>
      </label>
      <label class="field" id="of-planned-start-field">
        <span class="field-label">Планируемое время отключения</span>
        <input type="datetime-local" id="of-planned-start">
      </label>
      <label class="field" id="of-actual-start-field">
        <span class="field-label">Фактическое время отключения</span>
        <input type="datetime-local" id="of-actual-start" value="<?= $editingId > 0 ? '' : e(date('Y-m-d\TH:i')) ?>" required>
      </label>
      <label class="field" id="of-planned-end-field">
        <span class="field-label">Плановое восстановление</span>
        <input type="datetime-local" id="of-planned-end">
      </label>
      <div class="field-actions">
        <button type="submit" class="btn primary" id="of-save"><?= $editingId > 0 ? 'Сохранить' : 'Зарегистрировать' ?></button>
        <button type="button" class="btn ghost" id="of-toggle" hidden></button>
        <?php if ((auth_user()['role'] ?? '') === 'admin'): ?><button type="button" class="btn danger" id="of-delete" hidden>Удалить запись</button><?php endif; ?>
        <a class="btn ghost" href="<?= e($listUrl) ?>">Отмена</a>
      </div>
      <input type="hidden" id="of-id" value="<?= $editingId ?>">
      <?php if ($fromOutageId > 0): ?>
        <input type="hidden" id="of-from-outage" value="<?= (int)$fromOutageId ?>">
        <!-- Скрытый флаг: после сохранения закрыть исходное отключение -->
        <input type="hidden" id="of-close-source" value="1">
      <?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>