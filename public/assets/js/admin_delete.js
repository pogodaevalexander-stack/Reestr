'use strict';

document.addEventListener('DOMContentLoaded', () => {
  $('#period-delete-form').addEventListener('submit', deletePeriod);
});

async function deletePeriod(event) {
  event.preventDefault();
  const from = $('#pd-from').value;
  const to = $('#pd-to').value;
  if (!from || !to || from > to) {
    toast('Укажите корректные границы периода.', 'err');
    return;
  }
  const entity = $('#pd-entity').value;
  const title = entity === 'appeals' ? 'обращения' : 'отключения';
  if (!confirm(`Удалить все ${title} за период ${fmtDT(from)} — ${fmtDT(to)}? Это действие нельзя отменить.`)) return;
  try {
    const result = await api({ action: 'delete_period' }, {
      method: 'POST', body: { entity, from, to },
    });
    toast(`Удалено записей: ${result.data.deleted}.`, 'ok');
  } catch (error) { toast(error.message, 'err'); }
}
