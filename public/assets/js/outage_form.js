'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const id = Number($('#of-id').value);
  if ($('#of-type').value === 'improvement') updateDetailVisibility();
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
      $('#of-form-title').textContent = `Изменить ${outage.type === 'improvement' ? 'благоустройство' : 'отключение'} #${editingId}`;
      $('.topbar-title').textContent = `Изменить ${outage.type === 'improvement' ? 'благоустройство' : 'отключение'} #${editingId}`;
      document.title = `Изменить ${outage.type === 'improvement' ? 'благоустройство' : 'отключение'} #${editingId} · Реестр АОТК`;
      $('#of-address').value = outage.address || '';
      $('#of-type').value = outage.type || 'shutdown';
      $('#of-detail-type').value = outage.detail_type || 'full_shutdown';
      updateDetailVisibility();
      $('#of-address-wrap .sg-lat').value = outage.lat ?? '';
      $('#of-address-wrap .sg-lng').value = outage.lng ?? '';
      $('#of-reason').value = outage.reason || '';
      $('#of-mkd-count').value = outage.mkd_count ?? '';
      $('#of-social-count').value = outage.social_count ?? '';
      $('#of-other-count').value = outage.other_count ?? '';
      $('#of-planned-start').value = outage.planned_start ? outage.planned_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-actual-start').value = outage.actual_start ? outage.actual_start.replace(' ', 'T').slice(0, 16) : '';
      $('#of-planned-end').value = outage.planned_end ? outage.planned_end.replace(' ', 'T').slice(0, 16) : '';
      $('#of-save').textContent = 'Сохранить изменения';
      $('#of-toggle').hidden = outage.type === 'improvement';
      $('#of-toggle').dataset.active = Number(outage.is_active) === 1 ? '1' : '0';
      if (outage.type !== 'improvement' && Number(outage.is_active) === 1) {
        $('#of-toggle').textContent = 'Закрыть';
      } else {
        $('#of-toggle').hidden = true;
      }
      if ($('#of-delete')) $('#of-delete').hidden = false;
      updateDetailVisibility();
    }
  } catch (error) {
    toast(error.message, 'err');
  }
}

function updateDetailVisibility() {
  const type = $('#of-type').value;
  const typeSelect = $('#of-type');
  const color = type === 'shutdown' ? '#d71920' : (type === 'damage' ? '#111111' : '#6b7280');
  typeSelect.style.borderColor = color;
  const isImprovement = type === 'improvement';
  $('#of-detail-field').hidden = isImprovement;
  $('#of-detail-type').disabled = isImprovement;
  ['of-planned-start-field', 'of-actual-start-field', 'of-planned-end-field', 'of-mkd-field', 'of-social-field']
    .forEach((id) => { $('#' + id).hidden = isImprovement; });
  $('#of-mkd-count').required = !isImprovement;
  $('#of-social-count').required = !isImprovement;
  $('#of-other-field').hidden = isImprovement;
  $('#of-other-count').required = !isImprovement;
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
    other_count: $('#of-other-count').value.trim(),
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
    const other = $('#of-other-count');
    const validCount = (value) => value.trim() !== '' && Number.isInteger(Number(value)) && Number(value) >= 0;
    if (!validCount(mkd.value)) { mkd.focus(); toast('Укажите количество МКД целым неотрицательным числом.', 'err'); return; }
    if (!validCount(social.value)) { social.focus(); toast('Укажите количество объектов социального жилья целым неотрицательным числом.', 'err'); return; }
    if (!validCount(other.value)) { other.focus(); toast('Укажите количество прочего целым неотрицательным числом.', 'err'); return; }
  }
  try {
    const result = await api({ action: 'outages', op: id ? 'update' : 'create' }, {
      method: 'POST', body: id ? { ...body, id } : body,
    });
    const recordName = $('#of-type').value === 'improvement' ? 'Благоустройство' : 'Отключение';
    toast(id ? `${recordName} #${id} сохранено.` : `${recordName} #${result.data.id} зарегистрировано.`, 'ok');
    loadBadges();
    setTimeout(() => { location.href = (window.APP?.base || '') + ($('#of-type').value === 'improvement' ? '/improvements.php' : '/outages.php'); }, 700);
  } catch (error) {
    toast(error.message, 'err');
  }
}

if (window.APP?.readOnly) {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#outage-form input, #outage-form select, #outage-form textarea').forEach((field) => { field.disabled = true; });
    ['#of-save', '#of-toggle', '#of-delete'].forEach((selector) => { const button = $(selector); if (button) button.hidden = true; });
  });
}

async function toggleOutage() {
  const id = Number($('#of-id').value);
  if (!id) return;
  if ($('#of-type').value === 'improvement') return;
  if (!confirm('Закрыть отключение? Запись будет удалена без возможности повторного включения.')) return;
  try {
    await api({ action: 'outages', op: 'update' }, { method: 'POST', body: { id, is_active: 0 } });
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
    location.href = (window.APP?.base || '') + ($('#of-type').value === 'improvement' ? '/improvements.php' : '/outages.php');
  } catch (error) {
    toast(error.message, 'err');
  }
}
