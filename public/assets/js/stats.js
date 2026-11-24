'use strict';

let operatorRows = [];

document.addEventListener('DOMContentLoaded', () => {
  const picker = $('#st-filter-picker');
  const popup = $('#st-filter-popup');
  const toggle = $('#st-filter-toggle');
  $('#st-from').addEventListener('change', updateStatsFilterLabel);
  $('#st-to').addEventListener('change', updateStatsFilterLabel);
  $('#st-filter-search').addEventListener('click', () => {
    loadOperatorStats();
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  });
  $('#st-q').addEventListener('input', renderOperatorStats);
  toggle.addEventListener('click', () => {
    popup.hidden = !popup.hidden;
    toggle.setAttribute('aria-expanded', String(!popup.hidden));
  });
  $('#st-filter-reset').addEventListener('click', () => {
    $('#st-from').value = '';
    $('#st-to').value = '';
    updateStatsFilterLabel();
    loadOperatorStats();
  });
  document.addEventListener('pointerdown', (event) => {
    if (!picker.contains(event.target)) {
      popup.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      popup.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  loadOperatorStats();
});

function updateStatsFilterLabel() {
  const active = [$('#st-from').value, $('#st-to').value].filter(Boolean).length;
  $('#st-filter-toggle').querySelector('span').textContent = active ? `Фильтр: ${active}` : 'Фильтр';
}

async function loadOperatorStats() {
  try {
    const result = await api({ action: 'operator_stats', from: $('#st-from').value, to: $('#st-to').value });
    operatorRows = result.data;
    renderOperatorStats();
  } catch (error) { toast(error.message, 'err'); }
}

function renderOperatorStats() {
    const body = $('#st-operators');
    const query = $('#st-q').value.trim().toLocaleLowerCase('ru');
    const rows = operatorRows.filter((row) => String(row.operator_name || 'Не указан').toLocaleLowerCase('ru').includes(query));
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="3" class="empty">${query ? 'Операторы не найдены.' : 'За выбранный период обращений нет.'}</td></tr>`;
      return;
    }
    body.innerHTML = rows.map((row) => `<tr><td>${esc(row.operator_name || 'Не указан')}</td><td>${row.received}</td><td>${row.closed}</td></tr>`).join('');
}
