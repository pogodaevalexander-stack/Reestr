'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const id = Number($('#of-id').value);
  loadHouses(id);
  $('#outage-form').addEventListener('submit', submitOutage);
  $('#of-toggle').addEventListener('click', toggleOutage);
  $('#of-delete').addEventListener('click', deleteOutage);
});

async function loadHouses(editingId) {
  try {
    const [housesResult, outageResult] = await Promise.all([
      api({ action: 'houses', op: 'list' }),
      editingId ? api({ action: 'outages', op: 'get', id: editingId }) : Promise.resolve(null),
    ]);
    const houses = housesResult.data;
    const select = $('#of-house');
    select.innerHTML = '<option value="">Выберите адрес</option>' + houses
      .map((house) => `<option value="${house.id}">${esc(house.address)}</option>`).join('');
    if (editingId && outageResult) {
      const outage = outageResult.data;
      $('#of-form-title').textContent = `Изменить отключение #${editingId}`;
      $('.topbar-title').textContent = `Изменить отключение #${editingId}`;
      document.title = `Изменить отключение #${editingId} · Реестр отключений`;
      select.value = String(outage.house_id);
      document.querySelector(`input[name="of-type"][value="${outage.type}"]`).checked = true;
      $('#of-reason').value = outage.reason || '';
      $('#of-planned-start').value = outage.planned_start ? outage.planned_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-actual-start').value = outage.actual_start ? outage.actual_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-planned-end').value = outage.planned_end ? outage.planned_end.replace(' ', 'T').slice(0, 16) : '';
      $('#of-save').textContent = 'Сохранить изменения';
      $('#of-toggle').hidden = false;
      $('#of-toggle').dataset.active = Number(outage.is_active) === 1 ? '1' : '0';
      $('#of-toggle').textContent = Number(outage.is_active) === 1 ? 'Восстановить' : 'Снова отключить';
      $('#of-delete').hidden = false;
    }
  } catch (error) {
    $('#of-house').innerHTML = `<option value="">${esc(error.message)}</option>`;
    toast(error.message, 'err');
  }
}

async function submitOutage(event) {
  event.preventDefault();
  const houseId = $('#of-house').value;
  if (!houseId) {
    toast('Выберите адрес дома из реестра.', 'err');
    return;
  }
  const id = Number($('#of-id').value);
  const body = {
    house_id: Number(houseId),
    type: document.querySelector('input[name="of-type"]:checked').value,
    reason: $('#of-reason').value.trim(),
    planned_start: $('#of-planned-start').value,
    actual_start: $('#of-actual-start').value,
    planned_end: $('#of-planned-end').value,
  };
  if (!body.actual_start) {
    $('#of-actual-start').closest('.field').classList.add('is-invalid');
    $('#of-actual-start').focus();
    return;
  }
  try {
    const result = await api({ action: 'outages', op: id ? 'update' : 'create' }, {
      method: 'POST', body: id ? { ...body, id } : body,
    });
    toast(id ? `Отключение #${id} сохранено.` : `Отключение #${result.data.id} зарегистрировано.`, 'ok');
    loadBadges();
    setTimeout(() => { location.href = (window.APP?.base || '') + '/outages.php'; }, 700);
  } catch (error) {
    toast(error.message, 'err');
  }
}

async function toggleOutage() {
  const id = Number($('#of-id').value);
  if (!id) return;
  const isActive = $('#of-toggle').dataset.active === '1';
  try {
    await api({ action: 'outages', op: 'update' }, {
      method: 'POST', body: { id, is_active: isActive ? 0 : 1 },
    });
    location.href = (window.APP?.base || '') + '/outages.php';
  } catch (error) {
    toast(error.message, 'err');
  }
}

async function deleteOutage() {
  const id = Number($('#of-id').value);
  if (!id || !confirm('Удалить отключение?')) return;
  try {
    await api({ action: 'outages', op: 'delete' }, { method: 'POST', body: { id } });
    location.href = (window.APP?.base || '') + '/outages.php';
  } catch (error) {
    toast(error.message, 'err');
  }
}
