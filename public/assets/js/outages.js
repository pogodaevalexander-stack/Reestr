'use strict';

const TYPE = { hot_water: 'ГВС', heating: 'Отопление' };
const state = { q: '', type: 'all', status: 'all' };

document.addEventListener('DOMContentLoaded', () => {
  bindFilterPopup();
  reloadOutages();
  loadHousesTable();
  bindToolbar();
  bindOutageTable();
  $('#hs-q').addEventListener('input', debounce(loadHousesTable, 300));
});

function bindToolbar() {
  $('#ot-q').addEventListener('input', debounce(reloadOutages, 300));
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
    ].filter(Boolean).length;
    toggle.querySelector('span').textContent = activeCount ? `Фильтр: ${activeCount}` : 'Фильтр';
  }
}

function reloadOutages() {
  state.q = $('#ot-q').value.trim();
  state.type = $('input[name="ot-type"]:checked').value;
  state.status = $('input[name="ot-status"]:checked').value;
  return api({
    action: 'outages', op: 'list', q: state.q, type: state.type, status: state.status,
  }).then((result) => {
    $('#ot-count').textContent = `записей: ${result.data.length}`;
    renderOutages(result.data);
  }).catch((error) => toast(error.message, 'err'));
}

function renderOutages(rows) {
  const tbody = $('#ot-table tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty">Отключения не найдены.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map((outage) => `
    <tr class="${outage.is_active ? '' : 'row-off'}">
      <td><span class="idchip mono">#${outage.id}</span></td>
      <td class="td-addr">${hl(outage.address, state.q)}</td>
      <td><span class="pill ${outage.type === 'heating' ? 'heat' : 'hot'}">${TYPE[outage.type] || esc(outage.type)}</span></td>
      <td>${outage.reason ? hl(outage.reason, state.q) : '—'}</td>
      <td class="mono sm">${fmtDT(outage.start_at)}</td>
      <td class="mono sm">${outage.planned_end ? fmtDT(outage.planned_end) : '—'}</td>
      <td>${outage.is_active ? 'Активно' : 'Восстановлено'}</td>
      <td class="td-act">
        <button class="btn ghost sm act-toggle" data-id="${outage.id}">${outage.is_active ? 'Восстановить' : 'Снова отключить'}</button>
        <button class="btn danger sm act-del" data-id="${outage.id}">Удалить</button>
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
      api({ action: 'outages', op: 'update' }, {
        method: 'POST', body: { id, is_active: isActive ? 0 : 1 },
      }).then(() => {
        toast(isActive ? `Отключение #${id} восстановлено.` : `Отключение #${id} снова активно.`, 'ok');
        reloadOutages();
        loadHousesTable();
        loadBadges();
      }).catch((error) => toast(error.message, 'err'));
    }
    if (button.classList.contains('act-del')) {
      if (!confirmSwap(button, { label2: 'Точно удалить?' })) return;
      api({ action: 'outages', op: 'delete' }, { method: 'POST', body: { id } })
        .then(() => {
          toast(`Отключение #${id} удалено.`, 'ok');
          reloadOutages();
          loadHousesTable();
          loadBadges();
        }).catch((error) => toast(error.message, 'err'));
    }
  });
}

function loadHousesTable() {
  const query = $('#hs-q').value.trim();
  return api({ action: 'houses', op: 'list', q: query })
    .then((result) => {
      $('#hs-count').textContent = `адресов: ${result.data.length}`;
      const tbody = $('#hs-table tbody');
      if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty">Адреса не найдены.</td></tr>';
        return;
      }
      tbody.innerHTML = result.data.map((house) => `
        <tr>
          <td><span class="idchip mono">#${house.id}</span></td>
          <td class="td-addr">${hl(house.address, query)}</td>
          <td>${Number(house.active_outages)
            ? `<span class="pill heat">${house.active_outages} откл.</span>`
            : '<span class="pill on">В норме</span>'}</td>
        </tr>`).join('');
    }).catch((error) => toast(error.message, 'err'));
}
