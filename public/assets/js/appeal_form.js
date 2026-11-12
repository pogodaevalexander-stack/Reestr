'use strict';

let editingId = null;

document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(location.search);
  const id = Number(params.get('id'));
  if (id > 0) {
    try {
      const rows = (await api({ action: 'appeals', op: 'list', id })).data;
      const appeal = rows.find((row) => Number(row.id) === id);
      if (!appeal) throw new Error('Обращение не найдено.');
      editingId = id;
      $('#ap-form-title').textContent = 'Изменить обращение';
      $('.topbar-title').textContent = 'Изменить обращение';
      document.title = 'Изменить обращение · Реестр отключений';
      $('#ap-edit-flag').hidden = false;
      $('#ap-edit-id').textContent = '#' + id;
      $('#ap-address').value = appeal.address;
      $('#ap-fio').value = appeal.fio;
      $('#ap-phone').value = appeal.phone;
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
  $('#ap-form').addEventListener('submit', onSubmit);
});

async function onSubmit(event) {
  event.preventDefault();
  const wrap = $('#ap-address').closest('.sg-wrap');
  const body = {
    address: $('#ap-address').value.trim(),
    fio: $('#ap-fio').value.trim(),
    phone: $('#ap-phone').value.trim(),
    lat: wrap.querySelector('.sg-lat').value,
    lng: wrap.querySelector('.sg-lng').value,
  };
  if (!body.lat || !body.lng) {
    toast('Выберите адрес из подсказок Яндекс Карт.', 'err');
    return;
  }
  if (editingId) body.id = editingId;
  try {
    const result = await api(
      { action: 'appeals', op: editingId ? 'update' : 'create' },
      { method: 'POST', body }
    );
    toast(editingId ? `Обращение #${editingId} обновлено.` : `Обращение #${result.data.id} сохранено.`, 'ok');
    loadBadges();
    setTimeout(() => { location.href = (window.APP?.base || '') + '/appeals.php'; }, 700);
  } catch (error) {
    toast(error.message, 'err');
  }
}
