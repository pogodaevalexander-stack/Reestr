/* ============================================================
   Реестр · общие утилиты: API, тосты, подсказка адресов, часы
   ============================================================ */
'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

/* ---------- экранирование и подсветка поиска ---------- */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function hl(text, q) {
  text = String(text ?? '');
  if (!q) return esc(text);
  const needle = esc(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return esc(text).replace(new RegExp(needle, 'ig'), (m) => `<mark>${m}</mark>`);
}

/* ---------- запросы к PHP API ---------- */
function api(params = {}, opts = {}) {
  const cleanParams = Object.fromEntries(Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== ''));
  const qs  = new URLSearchParams(cleanParams).toString();
  const url = (window.APP ? window.APP.base : '') + '/api/index.php' + (qs ? '?' + qs : '');
  return fetch(url, {
    method:  opts.method || 'GET',
    headers: opts.body ? { 'Content-Type': 'application/json; charset=utf-8' } : undefined,
    body:    opts.body ? JSON.stringify(opts.body) : undefined,
  }).then(async (r) => {
    let j = {};
    try { j = await r.json(); } catch (_) { /* не JSON */ }
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Ошибка запроса (' + r.status + ')');
    return j;
  });
}

/* ---------- всплывающие уведомления ---------- */
function toast(msg, kind = 'info') {
  const box = $('#toasts');
  if (!box) return;
  const el = document.createElement('div');
  el.className = 'toast ' + kind;
  el.innerHTML = `<span class="t-ico" aria-hidden="true"></span><div>${esc(msg)}</div>`;
  box.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 4200);
}

/* ---------- мелочевка ---------- */
function debounce(fn, ms = 300) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

function countUp(el, to) {
  to = Number(to) || 0;
  const dur = 750, t0 = performance.now();
  const step = (t) => {
    const k = Math.min(1, (t - t0) / dur);
    el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3)));
    if (k < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

function fmtDT(s) {
  if (!s) return '—';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return String(s);
  return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
       + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

/* двухшаговое подтверждение: «Удалить» → «Точно удалить?» */
function confirmSwap(btn, { label2 = 'Точно?', ms = 3500 } = {}) {
  if (btn.dataset.armed) {
    btn.dataset.armed = '';
    btn.classList.remove('armed');
    return true;
  }
  const first = btn.textContent.trim();
  btn.dataset.armed  = '1';
  btn.classList.add('armed');
  btn.textContent = label2;
  setTimeout(() => {
    if (btn.isConnected && btn.dataset.armed) {
      btn.dataset.armed = '';
      btn.classList.remove('armed');
      btn.textContent = first;
    }
  }, ms);
  return false;
}

/* ---------- часы в шапке ---------- */
function startClock() {
  const el = $('#clock');
  if (!el) return;
  const tick = () => { el.textContent = new Date().toLocaleTimeString('ru-RU'); };
  tick();
  setInterval(tick, 1000);
}

/* ---------- бейджи активных отключений / звонков в меню ---------- */
async function loadBadges() {
  try {
    const d  = (await api({ action: 'stats' })).data;
    const b1 = $('#badge-outages');
    const b2 = $('#badge-calls');
    if (b1) { b1.textContent = d.outages;     b1.hidden = !d.outages; }
    if (b2) { b2.textContent = d.calls_today; b2.hidden = !d.calls_today; }
  } catch (_) { /* бейджи не критичны */ }
}

/* ---------- подсказка адресов через Яндекс.Геокодер ----------
   Ожидает разметку .sg-wrap:
   <input class="sg-input"> <input class="sg-lat"> <input class="sg-lng">
   <span class="sg-badge"> <ul class="sg-list">   */
class AddressSuggest {
  constructor(input) {
    this.input = input;
    this.wrap  = input.closest('.sg-wrap');
    this.list  = this.wrap && this.wrap.querySelector('.sg-list');
    this.latIn = this.wrap && this.wrap.querySelector('.sg-lat');
    this.lngIn = this.wrap && this.wrap.querySelector('.sg-lng');
    this.badge = this.wrap && this.wrap.querySelector('.sg-badge');
    this.items = [];
    this.idx   = -1;
    if (!this.list || !window.APP || !window.APP.hasYandex) return;

    const run = debounce(async (q) => {
      if (!q || q.length < 3) { this.hide(); return; }
      try {
        const result = await api({ action: 'geocode', q });
        this.items = result.data;
        this.idx = -1;
        this.render();
      } catch (_) { this.hide(); }
    }, 300);

    this.input.addEventListener('input', () => {
      this.clearPick();
      run(this.input.value.trim());
    });
    this.input.addEventListener('focus', () => this.show());
    this.input.addEventListener('blur',  () => setTimeout(() => this.hide(), 180));
    this.input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' && this.items.length)      { e.preventDefault(); this.move(1); }
      else if (e.key === 'ArrowUp' && this.items.length)   { e.preventDefault(); this.move(-1); }
      else if (e.key === 'Enter' && this.idx >= 0)         { e.preventDefault(); this.pick(this.idx); }
      else if (e.key === 'Escape') this.hide();
    });
  }

  render() {
    if (!this.items.length) { this.hide(); return; }
    this.list.innerHTML = this.items.map((it, i) =>
      `<li data-i="${i}" class="${i === this.idx ? 'is-sel' : ''}"><span class="sg-pin" aria-hidden="true"></span>${esc(it.text)}</li>`
    ).join('');
    this.list.hidden = false;
    $$('.sg-list li', this.wrap).forEach((li) =>
      li.addEventListener('mousedown', (e) => { e.preventDefault(); this.pick(Number(li.dataset.i)); })
    );
  }

  move(d) {
    this.idx = (this.idx + d + this.items.length) % this.items.length;
    this.render();
  }

  show() { if (this.items.length && this.list) this.list.hidden = false; }

  hide() {
    this.idx = -1;
    if (this.list) { this.list.hidden = true; this.list.innerHTML = ''; }
  }

  clearPick() {
    if (this.badge) this.badge.hidden = true;
    this.input.classList.remove('is-pinned');
    if (this.latIn) this.latIn.value = '';
    if (this.lngIn) this.lngIn.value = '';
  }

  pick(i) {
    const it = this.items[i];
    if (!it) return;
    this.input.value = it.text;
    if (this.latIn) this.latIn.value = it.lat;
    if (this.lngIn) this.lngIn.value = it.lng;
    if (this.badge) this.badge.hidden = false;
    this.input.classList.add('is-pinned');
    this.hide();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  startClock();
  loadBadges();
  $$('.sg-input').forEach((el) => new AddressSuggest(el));
});
