/* ============================================================
  Реестр · Главная: статистика, карта и месячные графики
   ============================================================ */
'use strict';

const TYPE = {
  shutdown: { label: 'Отключение', color: '#f59f0a' },
  damage: { label: 'Повреждение', color: '#e2483d' },
  improvement: { label: 'Благоустройство', color: '#0e9d8c' },
};
const CALL_COLOR = '#0e9d8c';

const state = { map: null, monthlyStats: null };

document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadMonthlyCharts();
  initMap();
  window.addEventListener('resize', debounce(() => {
    if (state.monthlyStats) renderMonthlyCharts(state.monthlyStats);
  }, 120));
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

/* ---------- месячные графики ---------- */
async function loadMonthlyCharts() {
  try {
    const data = (await api({ action: 'monthly_stats' })).data;
    state.monthlyStats = data;
    renderMonthlyCharts(data);
  } catch (error) {
    $('#appeals-chart-total').textContent = error.message;
    $('#outages-chart-total').textContent = error.message;
  }
}

function renderMonthlyCharts(data) {
  const month = new Date(`${data.month}-01T00:00:00`).toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
  const appealsTotal = data.days.reduce((sum, row) => sum + row.appeals, 0);
  const outagesTotal = data.days.reduce((sum, row) => sum + row.outages, 0);
  $('#appeals-chart-total').textContent = `${month} · всего: ${appealsTotal}`;
  $('#outages-chart-total').textContent = `${month} · всего: ${outagesTotal}`;
  drawMonthlyChart($('#appeals-month-chart'), data.days, 'appeals', '#0e9d8c');
  drawMonthlyChart($('#outages-month-chart'), data.days, 'outages', '#e2483d');
}

function drawMonthlyChart(canvas, rows, key, color) {
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth;
  const height = canvas.clientHeight;
  if (!width || !height) return;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  const context = canvas.getContext('2d');
  context.scale(ratio, ratio);
  const pad = { top: 12, right: 12, bottom: 28, left: 34 };
  const plotWidth = width - pad.left - pad.right;
  const plotHeight = height - pad.top - pad.bottom;
  const max = Math.max(1, ...rows.map((row) => row[key]));
  context.font = '11px Golos Text, sans-serif';
  context.textAlign = 'right';
  context.textBaseline = 'middle';
  for (let tick = 0; tick <= 4; tick++) {
    const value = Math.ceil(max * (4 - tick) / 4);
    const y = pad.top + plotHeight * tick / 4;
    context.strokeStyle = '#e8edf3';
    context.beginPath(); context.moveTo(pad.left, y); context.lineTo(width - pad.right, y); context.stroke();
    context.fillStyle = '#718096'; context.fillText(String(value), pad.left - 7, y);
  }
  const step = plotWidth / Math.max(1, rows.length - 1);
  context.beginPath();
  rows.forEach((row, index) => {
    const x = pad.left + index * step;
    const y = pad.top + plotHeight * (1 - row[key] / max);
    if (index === 0) context.moveTo(x, y); else context.lineTo(x, y);
  });
  context.strokeStyle = color; context.lineWidth = 2.5; context.lineJoin = 'round'; context.stroke();
  rows.forEach((row, index) => {
    if (index % 5 !== 0 && index !== rows.length - 1) return;
    const x = pad.left + index * step;
    context.fillStyle = '#718096'; context.textAlign = 'center'; context.textBaseline = 'top';
    context.fillText(String(row.day), x, height - pad.bottom + 8);
  });
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

