<?php
require __DIR__ . '/../src/bootstrap.php';
require_admin();

$repo = new AccountRepository();
$actor = auth_user();
$error = '';
$notice = '';
$editValues = null;
$openEditDialog = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('Сессия формы устарела. Обновите страницу и повторите действие.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $repo->create(
                trim((string)($_POST['login'] ?? '')),
                trim((string)($_POST['full_name'] ?? '')),
                (string)($_POST['password'] ?? ''),
                (string)($_POST['role'] ?? 'operator')
            );
            $notice = 'Учётная запись создана.';
        } elseif ($action === 'update') {
            $editValues = [
                'id' => (int)($_POST['id'] ?? 0),
                'login' => trim((string)($_POST['login'] ?? '')),
                'full_name' => trim((string)($_POST['full_name'] ?? '')),
                'role' => (string)($_POST['role'] ?? ''),
                'is_active' => isset($_POST['is_active']) && $_POST['is_active'] === '1',
            ];
            $repo->update(
                $editValues['id'],
                $editValues['login'],
                $editValues['full_name'],
                $editValues['role'],
                $editValues['is_active'],
                (string)($_POST['password'] ?? ''),
                (int)$actor['id']
            );
            $notice = 'Изменения сохранены.';
            $editValues = null;
        } elseif ($action === 'delete') {
            $repo->delete((int)($_POST['id'] ?? 0), (int)$actor['id']);
            $notice = 'Учётная запись удалена.';
        } else {
            throw new InvalidArgumentException('Неизвестное действие.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
        $openEditDialog = $editValues !== null;
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000'
            ? 'Такой логин уже используется.'
            : 'Не удалось сохранить изменения. Проверьте подключение к базе данных.';
        $openEditDialog = $editValues !== null;
    } catch (Throwable $exception) {
        $error = 'Не удалось выполнить операцию. Проверьте подключение к базе данных.';
        $openEditDialog = $editValues !== null;
    }
}

$accounts = $repo->all();
$page = 'accounts';
$pageTitle = 'Учётные записи';
$pageScript = 'accounts.js';
require __DIR__ . '/partials/head.php';
?>

<section class="panel account-create">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Новый пользователь</h2>
      <p class="panel-sub">Пароль должен содержать не менее 10 символов.</p>
    </div>
  </div>
  <form class="account-create-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <label class="field">
      <span class="field-label">Логин</span>
      <input type="text" name="login" minlength="3" maxlength="64" autocomplete="off" required>
    </label>
    <label class="field">
      <span class="field-label">ФИО</span>
      <input type="text" name="full_name" maxlength="255" autocomplete="name" required>
    </label>
    <label class="field">
      <span class="field-label">Пароль</span>
      <input type="password" name="password" minlength="10" autocomplete="new-password" required>
    </label>
    <label class="field">
      <span class="field-label">Роль</span>
      <select name="role">
        <option value="operator">Оператор</option>
        <option value="admin">Администратор</option>
      </select>
    </label>
    <button class="btn primary account-create-submit" type="submit">Добавить пользователя</button>
  </form>
</section>

<?php if ($error !== ''): ?>
  <div class="account-message account-message-error" role="alert"><?= e($error) ?></div>
<?php elseif ($notice !== ''): ?>
  <div class="account-message account-message-ok" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<section class="panel account-list-panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">Пользователи</h2>
      <p class="panel-sub">Нажмите «Изменить», чтобы обновить логин, ФИО или пароль.</p>
    </div>
    <span class="idchip"><?= count($accounts) ?> аккаунт(ов)</span>
  </div>
  <div class="account-table-wrap">
    <table class="account-table">
      <thead>
        <tr><th>Логин</th><th>ФИО</th><th>Роль</th><th>Действия</th></tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $account): ?>
        <?php $isSelf = (int)$account['id'] === (int)$actor['id']; ?>
        <tr>
          <td data-label="Логин"><?= e($account['login']) ?><?= $isSelf ? ' · вы' : '' ?></td>
          <td data-label="ФИО"><?= e($account['full_name']) ?></td>
          <td data-label="Роль"><?= $account['role'] === 'admin' ? 'Администратор' : 'Оператор' ?></td>
          <td class="account-actions" data-label="Действия">
            <div class="account-buttons">
              <button
                class="btn ghost sm account-edit-button"
                type="button"
                data-id="<?= (int)$account['id'] ?>"
                data-login="<?= e($account['login']) ?>"
                data-full-name="<?= e($account['full_name']) ?>"
                data-role="<?= e($account['role']) ?>"
                data-active="<?= (int)$account['is_active'] ?>"
                data-self="<?= $isSelf ? '1' : '0' ?>"
              >Изменить</button>
              <?php if (!$isSelf): ?>
                <button class="btn danger sm" type="submit" form="delete-account-<?= (int)$account['id'] ?>">Удалить</button>
                <form id="delete-account-<?= (int)$account['id'] ?>" method="post" onsubmit="return confirm('Удалить эту учётную запись?')">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($accounts === []): ?>
        <tr><td class="account-empty" colspan="4">Нет учётных записей.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog class="account-dialog" id="account-edit-dialog" aria-labelledby="account-edit-title" data-actor-id="<?= (int)$actor['id'] ?>" <?= $openEditDialog ? 'data-reopen="1"' : '' ?>>
  <form class="account-edit-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" id="edit-account-id" value="<?= (int)($editValues['id'] ?? 0) ?>">
    <div class="account-dialog-head">
      <div>
        <h2 class="panel-title" id="account-edit-title">Изменить пользователя</h2>
        <p class="panel-sub">Новый пароль можно не указывать, если менять его не нужно.</p>
      </div>
      <button class="account-dialog-close" type="button" data-close-edit aria-label="Закрыть">×</button>
    </div>
    <div class="account-edit-fields">
      <label class="field">
        <span class="field-label">Логин</span>
        <input type="text" name="login" id="edit-account-login" minlength="3" maxlength="64" value="<?= e($editValues['login'] ?? '') ?>" required>
      </label>
      <label class="field">
        <span class="field-label">ФИО</span>
        <input type="text" name="full_name" id="edit-account-full-name" maxlength="255" value="<?= e($editValues['full_name'] ?? '') ?>" required>
      </label>
      <label class="field">
        <span class="field-label">Новый пароль</span>
        <input type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Оставьте пустым без изменений">
      </label>
      <label class="field">
        <span class="field-label">Роль</span>
        <select name="role" id="edit-account-role">
          <option value="operator">Оператор</option>
          <option value="admin">Администратор</option>
        </select>
      </label>
      <label class="account-check account-edit-active">
        <input type="checkbox" name="is_active" id="edit-account-active" value="1">
        Активен
      </label>
    </div>
    <?php if ($openEditDialog && $editValues !== null): ?>
      <script>window.ACCOUNT_EDIT_VALUES = <?= json_encode($editValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php endif; ?>
    <div class="account-dialog-actions">
      <button class="btn ghost" type="button" data-close-edit>Отмена</button>
      <button class="btn primary" type="submit">Сохранить</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/partials/footer.php'; ?>