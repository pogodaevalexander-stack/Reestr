'use strict';

document.addEventListener('DOMContentLoaded', () => {
  $('#st-from').addEventListener('change', loadOperatorStats);
  $('#st-to').addEventListener('change', loadOperatorStats);
  loadOperatorStats();
});

async function loadOperatorStats() {
  try {
    const result = await api({ action: 'operator_stats', from: $('#st-from').value, to: $('#st-to').value });
    const body = $('#st-operators');
    if (!result.data.length) {
      body.innerHTML = '<tr><td colspan="3" class="empty">За выбранный период обращений нет.</td></tr>';
      return;
    }
    body.innerHTML = result.data.map((row) => `<tr><td>${esc(row.operator_name || 'Не указан')}</td><td>${row.received}</td><td>${row.closed}</td></tr>`).join('');
  } catch (error) { toast(error.message, 'err'); }
}
