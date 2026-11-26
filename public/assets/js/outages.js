'use strict';

const TYPE = { shutdown: 'Отключение', damage: 'Повреждение', improvement: 'Благоустройство' };
const DETAIL = { full_shutdown: 'Полное отключение', supply_without_circulation: 'Подача без циркуляции', damage_without_shutdown: 'Повреждение без отключения' };
const isImprovementsPage = document.body.dataset.page === 'improvements';
const state = { q: '', type: isImprovementsPage ? 'improvement' : 'outage', status: 'all', from: '', to: '' };

document.addEventListener('DOMContentLoaded', () => {
  bindFilterPopup();
  bindImproveModal();
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

function editUrlFor(outage) {
  const type = outage.type === 'improvement' ? '&type=improvement' : '';
  return `${appBase()}/outage_form.php?id=${encodeURIComponent(outage.id)}${type}`;
}

function improvementFromOutageUrl(outageId) {
  return `${appBase()}/outage_form.php?type=improvement&from_outage=${encodeURIComponent(outageId)}`;
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
  const typeInput = popup.querySelector('input[name="ot-type"]:checked');
  const statusInput = popup.querySelector('input[name="ot-status"]:checked');
  const activeCount = [
    typeInput && typeInput.value !== 'all',
    statusInput && statusInput.value !== 'all',
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
    const allType = popup.querySelector('input[name="ot-type"][value="all"]');
    const allStatus = popup.querySelector('input[name="ot-status"][value="all"]');
    if (allType) allType.checked = true;
    if (allStatus) allStatus.checked = true;
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

/* ---------- модалка «Создать благоустройство?» ---------- */

let improveModalResolver = null;
let previousActive = null;

function bindImproveModal() {
  const modal = $('#ot-improve-modal');
  if (!modal) return;

  modal.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-answer]');
    if (btn) {
      hideImproveModal(btn.dataset.answer === 'yes');
      return;
    }
    if (event.target === modal) hideImproveModal(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) hideImproveModal(false);
  });
}

function askCreateImprovement() {
  const modal = $('#ot-improve-modal');
  if (!modal) return Promise.resolve(false);

  previousActive = document.activeElement;
  modal.hidden = false;
  document.body.classList.add('modal-open');
  modal.querySelector('[data-answer="yes"]').focus();

  return new Promise((resolve) => { improveModalResolver = resolve; });
}

function hideImproveModal(answer) {
  const modal = $('#ot-improve-modal');
  if (!modal) return;
  modal.hidden = true;
  document.body.classList.remove('modal-open');
  if (previousActive && typeof previousActive.focus === 'function') previousActive.focus();
  previousActive = null;
  if (improveModalResolver) {
    const resolve = improveModalResolver;
    improveModalResolver = null;
    resolve(Boolean(answer));
  }
}

/* ---------- загрузка и отрисовка ---------- */

function reloadOutages() {
  state.q = $('#ot-q').value.trim();

  const popup = $('#ot-filter-popup');
  const typeInput = popup.querySelector('input[name="ot-type"]:checked');
  const statusInput = popup.querySelector('input[name="ot-status"]:checked');

  if (isImprovementsPage) {
    // Страница «Благоустройство»: только improvement, фильтр типа не используется.
    state.type = 'improvement';
  } else {
    const selected = typeInput ? typeInput.value : 'all';
    // «all» на странице отключений = «все, кроме благоустройства».
    // Отправляем 'outage' — сервер должен вернуть shutdown+damage.
    // Клиент дополнительно отфильтрует improvement (см. ниже) на случай,
    // если бэкенд не знает такого маркера.
    state.type = selected === 'all' ? 'outage' : selected;
  }
  state.status = statusInput ? statusInput.value : 'all';
  state.from = $('#ot-from').value;
  state.to = $('#ot-to').value;

  updateOutageExportLink(state.from, state.to);

  return api({
    action: 'outages', op: 'list', q: state.q, type: state.type, status: state.status, from: state.from, to: state.to,
  }).then((result) => {
    // Защита от бэкенда, который не знает маркер 'outage':
    // на странице отключений выкидываем благоустройства, на странице благоустройств — всё, кроме них.
    const rows = (result.data || []).filter((outage) => (
      isImprovementsPage
        ? outage.type === 'improvement'
        : outage.type !== 'improvement'
    ));
    $('#ot-count').textContent = `записей: ${rows.length}`;
    renderOutages(rows);
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

  tbody.addEventListener('keydown', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      location.href = row.dataset.editUrl;
    }
  });
}

async function handleRowButton(button) {
  if (!button.classList.contains('act-toggle')) return;
  if (button.disabled) return;

  const id = Number(button.dataset.id);
  const row = button.closest('tr');
  if (!row || row.classList.contains('row-off')) return;

  const createImprovement = await askCreateImprovement();

  if (createImprovement) {
    location.href = improvementFromOutageUrl(id);
    return;
  }

  button.disabled = true;
  try {
    await api({ action: 'outages', op: 'update' }, {
      method: 'POST', body: { id, is_active: 0 },
    });
    toast(`Запись #${id} закрыта.`, 'ok');
    reloadOutages();
    loadBadges();
  } catch (error) {
    button.disabled = false;
    toast(error.message, 'err');
  }
}

function updateOutageExportLink(from, to) {
  const link = document.querySelector('.excel-export');
  if (!link) return;
  const params = new URLSearchParams({ type: 'outages' });
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  link.href = appBase() + '/export.php?' + params.toString();
}