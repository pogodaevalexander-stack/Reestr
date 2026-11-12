'use strict';

document.addEventListener('DOMContentLoaded', () => {
  loadHouses();
  $('#outage-form').addEventListener('submit', submitOutage);
});

async function loadHouses() {
  try {
    const houses = (await api({ action: 'houses', op: 'list' })).data;
    const select = $('#of-house');
    select.innerHTML = houses.length
      ? houses.map((house) => `<option value="${house.id}">${esc(house.address)}</option>`).join('')
      : '<option value="">В реестре нет адресов</option>';
  } catch (error) {
    $('#of-house').innerHTML = `<option value="">${esc(error.message)}</option>`;
  }
}

async function submitOutage(event) {
  event.preventDefault();
  const houseId = $('#of-house').value;
  if (!houseId) {
    toast('Выберите адрес дома из реестра.', 'err');
    return;
  }
  const body = {
    house_id: Number(houseId),
    type: document.querySelector('input[name="of-type"]:checked').value,
    reason: $('#of-reason').value.trim(),
    planned_end: $('#of-planned').value,
  };
  try {
    const result = await api({ action: 'outages', op: 'create' }, { method: 'POST', body });
    toast(`Отключение #${result.data.id} зарегистрировано.`, 'ok');
    loadBadges();
    setTimeout(() => { location.href = (window.APP?.base || '') + '/outages.php'; }, 700);
  } catch (error) {
    toast(error.message, 'err');
  }
}
