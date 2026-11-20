'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const picker = $('#ap-filter-picker');
  const popup = $('#ap-filter-popup');
  const toggle = $('#ap-filter-toggle');
  const fields = $$('[data-search-field]', picker);

  reload();
  $('#ap-q').addEventListener('input', debounce(reload, 300));
  $('#ap-status-filter').addEventListener('change', reload);
  $('#ap-from').addEventListener('change', reload);
  $('#ap-to').addEventListener('change', reload);
  fields.forEach((field) => field.addEventListener('change', () => {
    updateFilterLabel();
    reload();
  }));
  toggle.addEventListener('click', () => {
    popup.hidden = !popup.hidden;
    toggle.setAttribute('aria-expanded', String(!popup.hidden));
  });
  $('#ap-filter-reset').addEventListener('click', () => {
    fields.forEach((field) => { field.checked = false; });
    $('#ap-q').value = '';
    updateFilterLabel();
    reload();
  });
  document.addEventListener('pointerdown', (event) => {
    if (!picker.contains(event.target)) closeFilterPopup();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeFilterPopup();
  });
  $('#ap-table tbody').addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && !event.target.closest('a, button, input, select, textarea')) {
      location.href = row.dataset.editUrl;
    }
  });
  $('#ap-table tbody').addEventListener('keydown', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      location.href = row.dataset.editUrl;
    }
  });

  function closeFilterPopup() {
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }
  function updateFilterLabel() {
    const count = fields.filter((field) => field.checked).length;
    toggle.querySelector('span').textContent = count ? `Фильтр: ${count}` : 'Фильтр';
  }
});

function selectedFields() {
  return $$('[data-search-field]:checked', $('#ap-filter-picker')).map((field) => field.value);
}

function reload() {
  const query = $('#ap-q').value.trim();
  const fields = selectedFields();
  const status = $('#ap-status-filter').value;
  const from = $('#ap-from').value;
  const to = $('#ap-to').value;
  updateExportLink(from, to);
  return api({ action: 'appeals', op: 'list', q: query, fields: fields.join(','), status, from, to })
    .then((result) => {
      $('#ap-count').textContent = `записей: ${result.data.length}`;
      render(result.data, query);
    })
    .catch((error) => toast(error.message, 'err'));
}

function updateExportLink(from, to) {
  const link = document.querySelector('.excel-export');
  if (!link) return;
  const params = new URLSearchParams({ type: 'appeals' });
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  link.href = (window.APP?.base || '') + '/export.php?' + params.toString();
}

function render(rows, query) {
  const tbody = $('#ap-table tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="empty">Обращения не найдены.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((appeal) => `
    <tr class="appeal-row" data-edit-url="${esc((window.APP?.base || '') + '/appeal_form.php?id=' + appeal.id)}">
      <td><span class="idchip mono">#${appeal.id}</span></td>
      <td class="mono sm ap-date">${hl(fmtDT(appeal.call_at), query)}</td>
      <td class="td-addr ap-address">${hl(appeal.address, query)}</td>
      <td class="ap-fio">${hl(appeal.fio, query)}</td>
      <td class="mono sm ap-phone">${hl(appeal.phone, query)}</td>
      <td class="ap-type">${hl(appealTypeLabel(appeal.type), query)}</td>
      <td class="ap-status"><span class="pill ${appeal.status === 'completed' ? 'on' : 'hot'}">${appealStatusLabel(appeal.status)}</span></td>
      <td class="ap-operator">${hl(appeal.operator_name || '—', query)}</td>
      <td class="mono sm ap-date">${hl(fmtDT(appeal.created_at), query)}</td>
      <td class="mono sm ap-date">${hl(fmtDT(appeal.updated_at), query)}</td>
    </tr>`).join('');
}

function appealTypeLabel(type) {
  return ({
    no_hot_water: 'Нет ГВС',
    no_heating: 'Нет теплоснабжения',
    information: 'Предоставление информации',
    other: 'Другое',
    false_call: 'Ложный вызов',
  })[type] || 'Другое';
}

function appealStatusLabel(status) {
  return status === 'completed' || status === 'COMPLETED' ? 'Завершено' : 'В работе';
}
