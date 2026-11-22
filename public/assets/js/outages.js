'use strict';

const TYPE = { shutdown: 'Отключение', damage: 'Повреждение', improvement: 'Благоустройство' };
const DETAIL = { full_shutdown: 'Полное отключение', supply_without_circulation: 'Подача без циркуляции', damage_without_shutdown: 'Повреждение без отключения' };
const state = { q: '', type: 'all', status: 'all', from: '', to: '' };

document.addEventListener('DOMContentLoaded', () => {
  bindFilterPopup();
  reloadOutages();
  bindToolbar();
  bindOutageTable();
  $('#ot-table tbody').addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && !event.target.closest('button, a, input, select')) {
      location.href = row.dataset.editUrl;
    }
  });
  $('#ot-table tbody').addEventListener('keydown', (event) => {
    const row = event.target.closest('tr[data-edit-url]');
    if (row && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      location.href = row.dataset.editUrl;
    }
  });
});

function bindToolbar() {
  $('#ot-q').addEventListener('input', debounce(reloadOutages, 300));
  $('#ot-from').addEventListener('change', () => { updateFilterLabel(); reloadOutages(); });
  $('#ot-to').addEventListener('change', () => { updateFilterLabel(); reloadOutages(); });
}

function bindFilterPopup() {
  const picker = $('#ot-filter-picker');
  const popup = $('#ot-filter-popup');
  const toggle = $('#ot-filter-toggle');
  const controls = $$('input[type="radio"]', popup);

  controls.forEach((control) => control.addEventListener('change', () => {
    updateFilterLabel();
    reloadOutages();
  }));
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
  function updateFilterLabel() {
    const activeCount = [
      popup.querySelector('input[name="ot-type"]:checked').value !== 'all',
      popup.querySelector('input[name="ot-status"]:checked').value !== 'all',
      Boolean($('#ot-from').value),
      Boolean($('#ot-to').value),
    ].filter(Boolean).length;
    toggle.querySelector('span').textContent = activeCount ? `Фильтр: ${activeCount}` : 'Фильтр';
  }
}

function reloadOutages() {
  state.q = $('#ot-q').value.trim();
  state.type = $('input[name="ot-type"]:checked').value;
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
    tbody.innerHTML = '<tr><td colspan="12" class="empty">Отключения не найдены.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((outage) => `
    <tr class="${outage.is_active ? '' : 'row-off'} outage-row" data-edit-url="${esc((window.APP?.base || '') + '/outage_form.php?id=' + outage.id)}" tabindex="0">
      <td><span class="idchip mono">#${outage.id}</span></td>
      <td class="td-addr">${hl(outage.address, state.q)}</td>
      <td><span class="pill ${outage.type === 'damage' ? 'heat' : 'hot'}">${TYPE[outage.type] || esc(outage.type)}</span><br><small>${DETAIL[outage.detail_type] || ''}</small></td>
      <td>${outage.reason ? hl(outage.reason, state.q) : '—'}</td>
      <td class="mono sm">${Number(outage.mkd_count || 0)}</td>
      <td class="mono sm">${Number(outage.social_count || 0)}</td>
      <td>${esc(outage.operator_name || '—')}</td>
      <td class="mono sm">${outage.type !== 'improvement' && outage.planned_start ? fmtDT(outage.planned_start) : '—'}</td>
      <td class="mono sm">${outage.type !== 'improvement' ? fmtDT(outage.actual_start) : '—'}</td>
      <td class="mono sm">${outage.type !== 'improvement' && outage.planned_end ? fmtDT(outage.planned_end) : '—'}</td>
      <td>${outage.is_active ? 'Активно' : 'Восстановлено'}</td>
      <td class="td-act">
        <button class="btn ghost sm act-toggle" data-id="${outage.id}">${outage.is_active ? 'Восстановить' : 'Снова отключить'}</button>
      </td>
    </tr>`).join('');
}

function bindOutageTable() {
  $('#ot-table tbody').addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button) return;
    const id = Number(button.dataset.id);
    if (button.classList.contains('act-toggle')) {
      const isActive = !button.closest('tr').classList.contains('row-off');
      (async () => {
        const outage = isActive ? (await api({ action: 'outages', op: 'get', id })).data : null;
        const makeImprovement = isActive && outage.type !== 'improvement' && confirm('Начать благоустройство?');
        await api({ action: 'outages', op: 'update' }, {
          method: 'POST', body: { id, is_active: isActive ? 0 : 1 },
        });
        if (makeImprovement) {
          const now = new Date();
          now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
          await api({ action: 'outages', op: 'create' }, {
            method: 'POST', body: {
              address: outage.address, lat: outage.lat, lng: outage.lng, type: 'improvement',
              detail_type: 'full_shutdown', actual_start: now.toISOString().slice(0, 16), reason: '',
            },
          });
        }
        toast(isActive ? `Отключение #${id} восстановлено.` : `Отключение #${id} снова активно.`, 'ok');
        reloadOutages();
        loadBadges();
      })().catch((error) => toast(error.message, 'err'));
    }
  });
}

function updateOutageExportLink(from, to) {
  const link = document.querySelector('.excel-export');
  if (!link) return;
  const params = new URLSearchParams({ type: 'outages' });
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  link.href = (window.APP?.base || '') + '/export.php?' + params.toString();
}

