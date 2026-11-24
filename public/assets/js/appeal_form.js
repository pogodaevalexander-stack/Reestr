'use strict';

let editingId = null;

document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(location.search);
  const id = Number(params.get('id'));
  if (id > 0) {
    try {
      const appeal = (await api({ action: 'appeals', op: 'get', id })).data;
      editingId = id;
      $('#ap-form-title').textContent = `Обращение № ${id}`;
      $('#ap-form').querySelector('[type="submit"]').textContent = 'Сохранить изменения';
      $('.topbar-title').textContent = 'Изменить обращение';
      document.title = 'Изменить обращение · Реестр АОТК';
      $('#ap-edit-flag').hidden = false;
      $('#ap-edit-id').textContent = String(id);
      $('#ap-address').value = appeal.address;
      $('#ap-fio').value = appeal.fio;
      $('#ap-subject').value = appeal.subject || '';
      $('#ap-type').value = appeal.type || 'other';
      $('#ap-status-field').hidden = false;
      $('#ap-status').textContent = appealStatusText(appeal.status);
      $('#ap-status').classList.toggle('is-completed', appeal.status === 'completed');
      $('#ap-close').hidden = appeal.status !== 'in_work';
      if ($('#ap-delete')) $('#ap-delete').hidden = false;
      $('#ap-phone').value = appeal.phone;
      $('#ap-call-at').value = appeal.call_at ? appeal.call_at.replace(' ', 'T').slice(0, 16) : '';
      $('#ap-operator').value = appeal.operator_name || 'Не указан';
      const wrap = $('#ap-address').closest('.sg-wrap');
      wrap.querySelector('.sg-lat').value = appeal.lat ?? '';
      wrap.querySelector('.sg-lng').value = appeal.lng ?? '';
      if (appeal.lat != null && appeal.lng != null) {
        wrap.querySelector('.sg-badge').hidden = false;
        $('#ap-address').classList.add('is-pinned');
      }
    } catch (error) {
      toast(error.message, 'err');
    }
  }
  $('#ap-close').addEventListener('click', closeAppeal);
  if ($('#ap-delete')) $('#ap-delete').addEventListener('click', deleteAppeal);
  $('#ap-form').addEventListener('submit', onSubmit);
  if (window.APP?.readOnly) {
    $('#ap-form').querySelectorAll('input, select, textarea').forEach((field) => { field.disabled = true; });
    $('#ap-form').querySelectorAll('button').forEach((button) => { button.hidden = true; });
  }
  $('#ap-form').querySelectorAll('input[required], textarea[required], select[required]').forEach((input) => {
    const clearInvalid = () => {
      input.closest('.field')?.classList.remove('is-invalid');
      input.setAttribute('aria-invalid', 'false');
    };
    input.addEventListener('input', clearInvalid);
    input.addEventListener('change', clearInvalid);
  });
});

async function onSubmit(event) {
  event.preventDefault();
  const required = [
    { selector: '#ap-address', valid: (value) => value.trim().length >= 5 },
    { selector: '#ap-fio', valid: (value) => value.trim().length >= 5 && value.trim().includes(' ') },
    { selector: '#ap-subject', valid: (value) => value.trim().length > 0 && value.length <= 10000 },
    { selector: '#ap-type', valid: (value) => ['no_hot_water', 'no_heating', 'information', 'other', 'false_call'].includes(value) },
    { selector: '#ap-phone', valid: (value) => /^[0-9+()\-\s]{6,20}$/.test(value.trim()) },
    { selector: '#ap-call-at', valid: (value) => value !== '' && !Number.isNaN(Date.parse(value)) },
    { selector: '#ap-operator', valid: (value) => value.trim().length >= 2 },
  ];
  let firstInvalid = null;
  required.forEach(({ selector, valid }) => {
    const input = $(selector);
    const field = input.closest('.field');
    const invalid = !valid(input.value);
    field.classList.toggle('is-invalid', invalid);
    input.setAttribute('aria-invalid', String(invalid));
    if (invalid && !firstInvalid) firstInvalid = input;
  });
  if (firstInvalid) {
    firstInvalid.focus();
    toast('Заполните обязательные поля, отмеченные красным.', 'err');
    return;
  }

  const wrap = $('#ap-address').closest('.sg-wrap');
  const body = {
    address: $('#ap-address').value.trim(),
    fio: $('#ap-fio').value.trim(),
    subject: $('#ap-subject').value.trim(),
    type: $('#ap-type').value,
    phone: $('#ap-phone').value.trim(),
    call_at: $('#ap-call-at').value,
    lat: wrap.querySelector('.sg-lat').value,
    lng: wrap.querySelector('.sg-lng').value,
  };
  if (editingId) body.id = editingId;
  try {
    const result = await api(
      { action: 'appeals', op: editingId ? 'update' : 'create' },
      { method: 'POST', body }
    );
    toast(editingId ? `Обращение № ${editingId} обновлено.` : `Обращение № ${result.data.id} сохранено.`, 'ok');
    loadBadges();
    setTimeout(() => { location.href = (window.APP?.base || '') + '/appeals.php'; }, 700);
  } catch (error) {
    toast(error.message, 'err');
  }
}

async function closeAppeal() {
  if (!editingId || !confirm('Закрыть обращение?')) return;
  try {
    await api({ action: 'appeals', op: 'set_status' }, {
      method: 'POST', body: { id: editingId, status: 'completed' },
    });
    location.href = (window.APP?.base || '') + '/appeals.php';
  } catch (error) {
    toast(error.message, 'err');
  }
}

async function deleteAppeal() {
  if (!editingId || !confirm('Удалить обращение?')) return;
  try {
    await api({ action: 'appeals', op: 'delete' }, { method: 'POST', body: { id: editingId } });
    location.href = (window.APP?.base || '') + '/appeals.php';
  } catch (error) {
    toast(error.message, 'err');
  }
}

function appealStatusText(status) {
  return status === 'completed' ? 'Завершено' : 'В работе';
}
