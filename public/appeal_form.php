<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$currentUser = auth_user();
$page       = 'appeals';
$pageTitle  = 'Новое обращение';
$pageScript = 'appeal_form.js';
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-12 record-form-panel">
    <div class="panel-head slim">
      <h2 class="panel-title" id="ap-form-title">Карточка обращения</h2>
      <a class="btn ghost sm" href="<?= BASE ?>/appeals.php">К списку</a>
    </div>
    <form class="form record-form" id="ap-form" novalidate>
      <div class="edit-flag" id="ap-edit-flag" hidden>
        Редактирование <b class="mono" id="ap-edit-id"></b>
      </div>
      <label class="field">
        <span class="field-label">Адрес</span>
        <div class="sg-wrap">
          <input type="text" id="ap-address" class="sg-input" placeholder="Введите адрес или выберите подсказку" autocomplete="off" required minlength="5">
          <input type="hidden" class="sg-lat"><input type="hidden" class="sg-lng">
          <span class="sg-badge" hidden>Яндекс</span>
          <ul class="sg-list" hidden></ul>
        </div>
      </label>
      <label class="field">
        <span class="field-label">ФИО заявителя</span>
        <input type="text" id="ap-fio" placeholder="Иванов Иван Иванович" required minlength="5">
      </label>
      <label class="field">
        <span class="field-label">Состав обращения</span>
        <textarea id="ap-subject" rows="4" maxlength="10000" placeholder="Опишите суть обращения" required></textarea>
      </label>
      <label class="field">
        <span class="field-label">Тип обращения</span>
        <select id="ap-type" required>
          <option value="">Выберите тип</option>
          <option value="no_hot_water">Нет ГВС</option>
          <option value="no_heating">Нет теплоснабжения</option>
          <option value="information">Предоставление информации</option>
          <option value="other">Другое</option>
          <option value="false_call">Ложный вызов</option>
        </select>
      </label>
      <div class="field" id="ap-status-field" hidden>
        <span class="field-label">Статус обращения</span>
        <span class="appeal-current-status" id="ap-status"></span>
      </div>
      <label class="field">
        <span class="field-label">Телефон</span>
        <input type="tel" id="ap-phone" class="mono" placeholder="+7 900 000-00-00" inputmode="tel" required>
      </label>
      <label class="field">
        <span class="field-label">Дата и время звонка</span>
        <input type="datetime-local" id="ap-call-at" value="<?= e(date('Y-m-d\\TH:i')) ?>" required>
      </label>
      <label class="field">
        <span class="field-label">Оператор</span>
        <input type="text" id="ap-operator" value="<?= e($currentUser['full_name'] ?? '') ?>" required readonly aria-readonly="true">
      </label>
      <div class="field-actions">
        <button type="submit" class="btn primary">Сохранить</button>
        <button type="button" class="btn ghost" id="ap-close" hidden>Закрыть обращение</button>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?><button type="button" class="btn danger" id="ap-delete" hidden>Удалить обращение</button><?php endif; ?>
        <a class="btn ghost" href="<?= BASE ?>/appeals.php">Отмена</a>
      </div>
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
