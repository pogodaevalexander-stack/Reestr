'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const id = Number($('#of-id').value);
  loadOutage(id);
  $('#outage-form').addEventListener('submit', submitOutage);
  $('#of-type').addEventListener('change', updateDetailVisibility);
  $('#of-toggle').addEventListener('click', toggleOutage);
  if ($('#of-delete')) $('#of-delete').addEventListener('click', deleteOutage);
});

async function loadOutage(editingId) {
  try {
    if (editingId) {
      const outage = (await api({ action: 'outages', op: 'get', id: editingId })).data;
      $('#of-form-title').textContent = `Изменить отключение #${editingId}`;
      $('.topbar-title').textContent = `Изменить отключение #${editingId}`;
      document.title = `Изменить отключение #${editingId} · Реестр отключений`;
      $('#of-address').value = outage.address || '';
      $('#of-type').value = outage.type || 'shutdown';
      $('#of-detail-type').value = outage.detail_type || 'full_shutdown';
      updateDetailVisibility();
      $('#of-address-wrap .sg-lat').value = outage.lat ?? '';
      $('#of-address-wrap .sg-lng').value = outage.lng ?? '';
      $('#of-reason').value = outage.reason || '';
      $('#of-mkd-count').value = outage.mkd_count ?? '';
      $('#of-social-count').value = outage.social_count ?? '';
      $('#of-planned-start').value = outage.planned_start ? outage.planned_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-actual-start').value = outage.actual_start ? outage.actual_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-planned-end').value = outage.planned_end ? outage.planned_end.replace(' ', 'T').slice(0, 16) : '';
      $('#of-save').textContent = 'Сохранить изменения';
      $('#of-toggle').hidden = false;
      $('#of-toggle').dataset.active = Number(outage.is_active) === 1 ? '1' : '0';
      $('#of-toggle').textContent = Number(outage.is_active) === 1 ? 'Восстановить' : 'Снова отключить';
      if ($('#of-delete')) $('#of-delete').hidden = false;
    }
  } catch (error) {
    toast(error.message, 'err');
  }
}

function updateDetailVisibility() {
  const isImprovement = $('#of-type').value === 'improvement';
  $('#of-detail-field').hidden = isImprovement;
  $('#of-detail-type').disabled = isImprovement;
  ['of-planned-start-field', 'of-actual-start-field', 'of-planned-end-field', 'of-mkd-field', 'of-social-field']
    .forEach((id) => { $('#' + id).hidden = isImprovement; });
  $('#of-mkd-count').required = !isImprovement;
  $('#of-social-count').required = !isImprovement;
}

async function submitOutage(event) {
  event.preventDefault();
  const address = $('#of-address').value.trim();
  if (address.length < 5) {
    toast('Укажите адрес длиной не менее 5 символов.', 'err');
    return;
  }
  const id = Number($('#of-id').value);
  const addressWrap = $('#of-address-wrap');
  const body = {
    address,
    lat: addressWrap.querySelector('.sg-lat').value,
    lng: addressWrap.querySelector('.sg-lng').value,
    type: $('#of-type').value,
    detail_type: $('#of-detail-type').value,
    reason: $('#of-reason').value.trim(),
    mkd_count: $('#of-mkd-count').value.trim(),
    social_count: $('#of-social-count').value.trim(),
  };
  if ($('#of-type').value !== 'improvement') {
    body.planned_start = $('#of-planned-start').value;
    body.actual_start = $('#of-actual-start').value;
    body.planned_end = $('#of-planned-end').value;
  }
  if ($('#of-type').value !== 'improvement') {
    if (!body.actual_start) {
      $('#of-actual-start').closest('.field').classList.add('is-invalid');
      $('#of-actual-start').focus();
      return;
    }
    const mkd = $('#of-mkd-count');
    const social = $('#of-social-count');
    const validCount = (value) => value.trim() !== '' && Number.isInteger(Number(value)) && Number(value) >= 0;
    if (!validCount(mkd.value)) { mkd.focus(); toast('Укажите количество МКД целым неотрицательным числом.', 'err'); return; }
    if (!validCount(social.value)) { social.focus(); toast('Укажите количество объектов социального жилья целым неотрицательным числом.', 'err'); return; }
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
    const makeImprovement = isActive && $('#of-type').value !== 'improvement'
      && confirm('Начать благоустройство?');
    await api({ action: 'outages', op: 'update' }, {
      method: 'POST', body: { id, is_active: isActive ? 0 : 1 },
    });
    if (makeImprovement) {
      const wrap = $('#of-address-wrap');
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      await api({ action: 'outages', op: 'create' }, {
        method: 'POST', body: {
          address: $('#of-address').value.trim(), lat: wrap.querySelector('.sg-lat').value,
          lng: wrap.querySelector('.sg-lng').value, type: 'improvement',
          detail_type: 'full_shutdown', actual_start: now.toISOString().slice(0, 16), reason: '',
        },
      });
    }
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
