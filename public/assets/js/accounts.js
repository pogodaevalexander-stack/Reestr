(() => {
  const dialog = document.getElementById('account-edit-dialog');
  if (!dialog) return;

  const form = dialog.querySelector('form');
  const idInput = document.getElementById('edit-account-id');
  const loginInput = document.getElementById('edit-account-login');
  const fullNameInput = document.getElementById('edit-account-full-name');
  const roleInput = document.getElementById('edit-account-role');
  const activeInput = document.getElementById('edit-account-active');

  function setSelfFields(isSelf) {
    form.querySelectorAll('[data-self-field]').forEach((input) => input.remove());
    roleInput.disabled = isSelf;
    activeInput.disabled = isSelf;

    if (isSelf) {
      [['role', 'admin'], ['is_active', '1']].forEach(([name, value]) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = value;
        hidden.dataset.selfField = 'true';
        form.append(hidden);
      });
    }
  }

  function openEditor(button, values = null) {
    const data = values || button.dataset;
    idInput.value = data.id;
    loginInput.value = data.login;
    fullNameInput.value = data.full_name || data.fullName || '';
    roleInput.value = data.role;
    activeInput.checked = String(data.is_active ?? data.active) === '1' || data.is_active === true;
    const actorId = Number(dialog.dataset.actorId);
    const isSelf = data.self === '1' || Number(data.id) === actorId;
    setSelfFields(isSelf);
    if (!dialog.open) dialog.showModal();
    loginInput.focus();
  }

  document.querySelectorAll('.account-edit-button').forEach((button) => {
    button.addEventListener('click', () => openEditor(button));
  });

  dialog.querySelectorAll('[data-close-edit]').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  if (dialog.dataset.reopen === '1' && window.ACCOUNT_EDIT_VALUES) {
    openEditor(null, window.ACCOUNT_EDIT_VALUES);
  }
})();