'use strict';

let lastRows = [];

document.addEventListener('DOMContentLoaded', () => {
  const picker = $('#ap-filter-picker');
  const popup = $('#ap-filter-popup');
  const toggle = $('#ap-filter-toggle');
  const fields = $$('[data-search-field]', picker);

  reload();
  $('#ap-q').addEventListener('input', debounce(reload, 300));
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
  $('#ap-table tbody').addEventListener('click', onRowAction);

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
  return api({ action: 'appeals', op: 'list', q: query, fields: fields.join(',') })
    .then((result) => {
      lastRows = result.data;
      $('#ap-count').textContent = `записей: ${result.data.length}`;
      render(result.data, query);
    })
    .catch((error) => toast(error.message, 'err'));
}

function render(rows, query) {
  const tbody = $('#ap-table tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">Обращения не найдены.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((appeal) => `
    <tr>
      <td><span class="idchip mono">#${appeal.id}</span></td>
      <td class="td-addr">${hl(appeal.address, query)}</td>
      <td>${hl(appeal.fio, query)}</td>
      <td class="mono sm">${hl(appeal.phone, query)}</td>
      <td class="mono sm">${hl(fmtDT(appeal.created_at), query)}</td>
      <td class="mono sm">${hl(fmtDT(appeal.updated_at), query)}</td>
      <td class="td-act">
        <a class="btn ghost sm" href="${esc((window.APP?.base || '') + '/appeal_form.php?id=' + appeal.id)}">Изменить</a>
        <button class="btn danger sm act-del" data-id="${appeal.id}">Удалить</button>
      </td>
    </tr>`).join('');
}

function onRowAction(event) {
  const button = event.target.closest('.act-del');
  if (!button) return;
  const id = Number(button.dataset.id);
  if (!lastRows.some((row) => Number(row.id) === id)) return;
  if (!confirmSwap(button, { label2: 'Точно удалить?' })) return;
  api({ action: 'appeals', op: 'delete' }, { method: 'POST', body: { id } })
    .then(() => {
      toast(`Обращение #${id} удалено.`, 'ok');
      reload();
      loadBadges();
    })
    .catch((error) => toast(error.message, 'err'));
}
