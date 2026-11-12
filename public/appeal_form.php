<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$page       = 'appeals';
$pageTitle  = 'Новое обращение';
$pageScript = 'appeal_form.js';
require __DIR__ . '/partials/head.php';
?>

<div class="page-grid">
  <section class="panel span-7">
    <div class="panel-head slim">
      <h2 class="panel-title" id="ap-form-title">Карточка обращения</h2>
      <a class="btn ghost sm" href="<?= BASE ?>/appeals.php">К списку</a>
    </div>
    <form class="form" id="ap-form" novalidate>
      <div class="edit-flag" id="ap-edit-flag" hidden>
        Редактирование <b class="mono" id="ap-edit-id"></b>
      </div>
      <label class="field">
        <span class="field-label">Адрес</span>
        <div class="sg-wrap">
          <input type="text" id="ap-address" class="sg-input" placeholder="Введите адрес" autocomplete="off" required>
          <input type="hidden" class="sg-lat"><input type="hidden" class="sg-lng">
          <span class="sg-badge" hidden>Яндекс</span>
          <ul class="sg-list" hidden></ul>
        </div>
      </label>
      <label class="field">
        <span class="field-label">ФИО заявителя</span>
        <input type="text" id="ap-fio" placeholder="Иванов Иван Иванович" required>
      </label>
      <label class="field">
        <span class="field-label">Телефон</span>
        <input type="tel" id="ap-phone" class="mono" placeholder="+7 900 000-00-00" inputmode="tel" required>
      </label>
      <div class="field-actions">
        <button type="submit" class="btn primary">Сохранить</button>
        <a class="btn ghost" href="<?= BASE ?>/appeals.php">Отмена</a>
      </div>
    </form>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
