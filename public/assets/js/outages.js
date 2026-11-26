'use strict';

const TYPE = { shutdown: 'Отключение', damage: 'Повреждение', improvement: 'Благоустройство' };
const DETAIL = { full_shutdown: 'Полное отключение', supply_without_circulation: 'Подача без циркуляции', damage_without_shutdown: 'Повреждение без отключения' };
const isImprovementsPage = document.body.dataset.page === 'improvements';
const state = { q: '', type: isImprovementsPage ? 'improvement' : 'outage', status: 'all', from: '', to: '' };

document.addEventListener('DOMContentLoaded', () => {
  bindFilterPopup();
  reloadOutages();
  bindToolbar();
  bindOutageTable();
});

/* ---------- ссылки ---------- */

function appBase() {
  return window.APP?.base || '';
}

function canEditRecords() {
  return !window.APP?.readOnly;
}

/** Адрес формы редактирования: тип берём из самой записи, а не из страницы. */
function editUrlFor(outage) {
  const type = outage.type === 'improvement' ? '&type=improvement' : '';
  return `${appBase()}/outage_form.php?id=${encodeURIComponent(outage.id)}${type}`;
}

/* ---------- панель фильтров ---------- */

function bindToolbar() {
  $('#ot-q').addEventListener('input', debounce(reloadOutages, 300));
  $('#ot-from').addEventListener('change', updateFilterLabel);
  $('#ot-to').addEventListener('change', updateFilterLabel);
}

function updateFilterLabel() {
  const popup = $('#ot-filter-popup');
  const toggle = $('#ot-filter-toggle');
  const activeCount = [
    popup.querySelector('input[name="ot-type"]:checked').value !== 'all',
    popup.querySelector('input[name="ot-status"]:checked').value !== 'all',
    Boolean($('#ot-from').value),
    Boolean($('#ot-to').value),
  ].filter(Boolean).length;
  toggle.querySelector('span').textContent = activeCount ? `Фильтр: ${activeCount}` : 'Фильтр';
}

function bindFilterPopup() {
  const picker = $('#ot-filter-picker');
  const popup = $('#ot-filter-popup');
  const toggle = $('#ot-filter-toggle');
  const controls = $$('input[type="radio"]', popup);

  controls.forEach((control) => control.addEventListener('change', updateFilterLabel));
  $('#ot-filter-search').addEventListener('click', () => { reloadOutages(); closeFilterPopup(); });
  toggle.addEventListener('click', () => {
    popup.hidden = !popup.hidden;
    toggle.setAttribute('aria-expanded', String(!popup.hidden));
  });
  $('#ot-filter-reset').addEventListener('click', () => {
    popup.querySelector('input[name="ot-type"][value="all"]').checked = true;
    popup.querySelector('input[name="ot-status"][value="all"]').checked = true;
    $('#ot-from').value = '';
    $('#ot-to').value = '';
    updateFilterLabel();
    reloadOutages();
  });
  document.addEventListener('pointerdown', (event) => {
    if (!picker.contains(event.target)) closeFilterPopup();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeFilterPopup();
  });

  function closeFilterPopup() {
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }
}

/* ---------- загрузка и отрисовка ---------- */

function reloadOutages() {
  state.q = $('#ot-q').value.trim();
  state.type = isImprovementsPage ? 'improvement' : $('input[name="ot-type"]:checked').value;
  state.status = $('input[name="ot-status"]:checked').value;
  state.from = $('#ot-from').value;
  state.to = $('#ot-to').value;
  updateOutageExportLink(state.from, state.to);
  return api({
    action: 'outages', op: 'list', q: state.q, type: state.type, status: state.status, from: state.from, to: state.to,
  }).then((result) => {
    $('#ot-count').textContent = `записей: ${result.data.length}`;
    renderOutages(result.data);
  }).catch((error) => toast(error.message, 'err'));
}

function renderOutages(rows) {
  const tbody = $('#ot-table tbody');
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="13" class="empty">${isImprovementsPage ? 'Благоустройства' : 'Отключения'} не найдены.</td></tr>`;
    return;
  }
  const canEdit = canEditRecords();
  tbody.innerHTML = rows.map((outage) => {
    const isImprovement = outage.type === 'improvement';
    const editUrl = esc(editUrlFor(outage));
    const faxUrl = esc(`${appBase()}/faxogram.php?id=${outage.id}`);
    return `
    <tr class="${outage.is_active ? '' : 'row-off'} outage-row"${canEdit ? ` data-edit-url="${editUrl}" tabindex="0"` : ''}>
      <td><span class="idchip mono">${outage.id}</span></td>
      <td><span class="pill ${outage.is_active ? 'hot' : 'on'}">${outage.is_active ? 'Активно' : 'Восстановлено'}</span></td>
      <td class="td-addr">${hl(outage.address, state.q)}</td>
      <td><span class="pill category-${esc(outage.type)}">${TYPE[outage.type] || esc(outage.type)}</span><br><small>${DETAIL[outage.detail_type] || ''}</small></td>
      <td>${outage.reason ? hl(outage.reason, state.q) : '—'}</td>
      <td class="mono sm">${Number(outage.mkd_count || 0)}</td>
      <td class="mono sm">${Number(outage.social_count || 0)}</td>
      <td class="mono sm">${Number(outage.other_count || 0)}</td>
      <td>${esc(outage.operator_name || '—')}</td>
      <td class="mono sm">${!isImprovement && outage.planned_start ? fmtDT(outage.planned_start) : '—'}</td>
      <td class="mono sm">${!isImprovement ? fmtDT(outage.actual_start) : '—'}</td>
      <td class="mono sm">${!isImprovement && outage.planned_end ? fmtDT(outage.planned_end) : '—'}</td>
      <td class="td-act">
        ${canEdit ? `<a class="btn ghost sm act-edit" href="${editUrl}">Изменить</a>` : ''}
        <a class="btn ghost sm" href="${faxUrl}" download>Факсограмма</a>
        ${canEdit && outage.is_active && !isImprovement ? `<button class="btn ghost sm act-toggle" data-id="${outage.id}">Закрыть</button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

/* ---------- действия в таблице ---------- */

function bindOutageTable() {
  const tbody = $('#ot-table tbody');

  // Клик по строке открывает редактирование (кнопки и ссылки внутри строки не затрагиваются).
  tbody.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (button) {
      handleRowButton(button);
      return;
    }
    const row = event.target.closest('tr[data-edit-url]');
    if (row && !event.target.closest('a, input, select')) {
      location.href = row.dataset.editUrl;
    }
  });

  // Enter / пробел на самой строке (не на вложенных кнопках).
  tbody.addEventListener('keydown', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      location.href = row.dataset.editUrl;
    }
  });
}

function handleRowButton(button) {
  if (!button.classList.contains('act-toggle')) return;
  const id = Number(button.dataset.id);
  const isActive = !button.closest('tr').classList.contains('row-off');
  if (!isActive) return;
  if (!confirm('Закрыть запись? Запись будет удалена без возможности повторного включения.')) return;
  (async () => {
    await api({ action: 'outages', op: 'update' }, {
      method: 'POST', body: { id, is_active: 0 },
    });
    toast(`Запись #${id} закрыта и удалена.`, 'ok');
    reloadOutages();
    loadBadges();
  })().catch((error) => toast(error.message, 'err'));
}

function updateOutageExportLink(from, to) {
  const link = document.querySelector('.excel-export');
  if (!link) return;
  const params = new URLSearchParams({ type: 'outages' });
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  link.href = appBase() + '/export.php?' + params.toString();
}