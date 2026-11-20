/* ============================================================
   Реестр · Главная: статистика, карта, звонки за день
   ============================================================ */
'use strict';

const TYPE = {
  shutdown: { label: 'Отключение', color: '#f59f0a' },
  damage: { label: 'Повреждение', color: '#e2483d' },
  improvement: { label: 'Благоустройство', color: '#0e9d8c' },
};
const CALL_COLOR = '#0e9d8c';

const state = { map: null };

document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadToday();
  initMap();
});

/* ---------- статистика ---------- */
async function loadStats() {
  try {
    const d = (await api({ action: 'stats' })).data;
    ['outages', 'appeals_in_work'].forEach((k) => {
      const el = document.querySelector(`[data-stat="${k}"]`);
      if (el) countUp(el, d[k]);
    });
  } catch (e) { toast(e.message, 'err'); }
}

/* ---------- звонки за день ---------- */
async function loadToday() {
  const box = $('#today-list');
  try {
    const d = (await api({ action: 'appeals', op: 'list', today: '1' })).data
      .sort((a, b) => {
        const statusOrder = (a.status === 'completed' ? 1 : 0) - (b.status === 'completed' ? 1 : 0);
        if (statusOrder !== 0) return statusOrder;
        return new Date(b.call_at.replace(' ', 'T')) - new Date(a.call_at.replace(' ', 'T'));
      });
    $('#today-note').textContent = `звонков за день: ${d.length}`;
    if (!d.length) { box.innerHTML = '<li class="empty">Сегодня звонков не было.</li>'; return; }
    box.innerHTML = d.slice(0, 10).map((a) => `
      <li class="call">
        <div class="call-top">
          <b>${esc(a.fio)}</b>
          <time class="mono">${new Date(a.call_at.replace(' ', 'T')).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}</time>
        </div>
        <div class="call-addr">${esc(a.address)}</div>
        <div class="call-phone mono">${esc(a.phone)} · <span class="idchip mono">#${a.id}</span></div>
      </li>`).join('');
  } catch (e) { box.innerHTML = `<li class="empty">${esc(e.message)}</li>`; }
}

/* ---------- карта ---------- */
async function initMap() {
  if (!window.YMAPS || !window.YMAPS.mapKey) { showFallback(); return; }
  if (typeof ymaps === 'undefined') {
    showFallback('Скрипт Яндекс Карт не загрузился. Проверьте интернет-соединение и корректность ключа API.');
    return;
  }

  let data;
  try {
    data = (await api({ action: 'map' })).data;
  } catch (e) {
    showFallback(e.message);
    return;
  }

  const map = new ymaps.Map('map',
    { center: window.YMAPS.center, zoom: window.YMAPS.zoom },
    { controls: ['zoomControl'], restrictMapArea: window.YMAPS.bounds });
  state.map = map;

  const mk = (o, color, html) => new ymaps.Placemark([o.lat, o.lng],
    { balloonContent: html, hintContent: o.address },
    { iconColor: color, preset: 'islands#circleDotIcon' });

  const cShutdown = new ymaps.GeoObjectCollection();
  const cDamage = new ymaps.GeoObjectCollection();
  const cImprovement = new ymaps.GeoObjectCollection();
  const cCall = new ymaps.GeoObjectCollection();
  let noCoords = 0;
  let outsideOmsk = 0;
  const isInOmsk = (lat, lng) => {
    const [[south, west], [north, east]] = window.YMAPS.bounds;
    return lat >= south && lat <= north && lng >= west && lng <= east;
  };

  data.outages.forEach((o) => {
    if (o.lat == null || o.lng == null) { noCoords++; return; }
    if (!isInOmsk(Number(o.lat), Number(o.lng))) { outsideOmsk++; return; }
    const t = TYPE[o.type] || TYPE.shutdown;
    const collection = o.type === 'damage' ? cDamage : (o.type === 'improvement' ? cImprovement : cShutdown);
    collection.add(mk(o, t.color,
      `<div class="mbal"><b>${esc(o.address)}</b>`
      + `<span class="mbal-tag" style="--c:${t.color}">${t.label} · отключено</span>`
      + `<span class="mbal-id mono">#${o.id}${o.reason ? ' · ' + esc(o.reason) : ''}</span></div>`));
  });

  data.appeals.forEach((a) => {
    if (a.lat == null || a.lng == null) { noCoords++; return; }
    if (!isInOmsk(Number(a.lat), Number(a.lng))) { outsideOmsk++; return; }
    cCall.add(mk(a, CALL_COLOR,
      `<div class="mbal"><b>${esc(a.address)}</b>`
      + `<span class="mbal-tag" style="--c:${CALL_COLOR}">звонок в поддержку</span>`
      + `<span class="mbal-id mono">#${a.id} · ${esc(a.fio)} · ${esc(a.phone)}</span></div>`));
  });

  map.geoObjects.add(cShutdown);
  map.geoObjects.add(cDamage);
  map.geoObjects.add(cImprovement);
  map.geoObjects.add(cCall);

  const all = [...cShutdown.getAll(), ...cDamage.getAll(), ...cImprovement.getAll(), ...cCall.getAll()];
  const note = $('#map-note');
  if (note) note.textContent = `${all.length} объектов на карте` + (noCoords ? ` · ${noCoords} без координат` : '') + (outsideOmsk ? ` · ${outsideOmsk} вне Омска` : '');
}

function showFallback(msg) {
  const fb = $('#map-fallback');
  const m  = $('#map');
  if (m) m.style.display = 'none';
  if (!fb) return;
  fb.hidden = false;
  if (msg) { const p = fb.querySelector('p'); if (p) p.textContent = msg; }
}

