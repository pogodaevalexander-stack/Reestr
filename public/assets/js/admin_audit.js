'use strict';

let auditRows = [];

document.addEventListener('DOMContentLoaded', () => {
  const popup = $('#audit-filter-popup');
  const picker = $('#audit-filter-picker');
  const toggle = $('#audit-filter-toggle');
  toggle.addEventListener('click', () => {
    popup.hidden = !popup.hidden;
    toggle.setAttribute('aria-expanded', String(!popup.hidden));
  });
  $('#audit-q').addEventListener('input', renderAuditLog);
  $('#audit-filter-reset').addEventListener('click', () => {
    ['audit-from', 'audit-to', 'audit-actor', 'audit-action', 'audit-entity'].forEach((id) => { $('#' + id).value = ''; });
    loadAuditLog();
  });
  document.addEventListener('pointerdown', (event) => {
    if (!picker.contains(event.target)) {
      popup.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  $('#audit-filter-search').addEventListener('click', () => {
    loadAuditLog();
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  });
  loadAuditLog();
});

async function loadAuditLog() {
  try {
    const result = await api({
      action: 'audit', from: $('#audit-from').value, to: $('#audit-to').value,
      actor: $('#audit-actor').value, filter_action: $('#audit-action').value, entity: $('#audit-entity').value,
    });
    fillFilterOptions(result.filters || {});
    auditRows = result.data;
    renderAuditLog();
  } catch (error) { toast(error.message, 'err'); }
}

function renderAuditLog() {
    const body = $('#st-audit');
    const query = $('#audit-q').value.trim().toLocaleLowerCase('ru');
    const rows = auditRows.filter((entry) => [entry.id, entry.actor_name, entry.action, entry.entity, entry.entity_id, entry.details, entry.created_at]
      .some((value) => String(value ?? '').toLocaleLowerCase('ru').includes(query)));
    $('#audit-count').textContent = `Найдено записей: ${rows.length}`;
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="5" class="empty">${query ? 'Записи не найдены.' : 'Записей пока нет.'}</td></tr>`;
      return;
    }
    body.innerHTML = rows.map((entry) => {
      let details = {};
      try { details = JSON.parse(entry.details || '{}'); } catch (_) { /* повреждённая старая запись */ }
      const summary = summarizeAudit(details);
      return `<tr><td class="mono sm">${fmtDT(entry.created_at)}</td><td>${esc(entry.actor_name)}</td><td>${esc(auditAction(entry.action))}</td><td>${esc(auditEntity(entry.entity))} ${entry.entity_id ? '#' + entry.entity_id : ''}</td><td class="audit-details">${esc(summary || '—')}</td></tr>`;
    }).join('');
}

function fillFilterOptions(filters) {
  const options = [
    ['audit-actor', filters.actors || [], 'Все операторы'],
    ['audit-action', filters.actions || [], 'Все действия'],
    ['audit-entity', filters.entities || [], 'Все разделы'],
  ];
  options.forEach(([id, values, placeholder]) => {
    const select = $('#' + id);
    const previous = select.value;
    select.innerHTML = `<option value="">${esc(placeholder)}</option>` + values.map((value) => `<option value="${esc(value)}">${esc(auditAction(value) === value ? auditEntity(value) === value ? value : auditEntity(value) : auditAction(value))}</option>`).join('');
    if (values.includes(previous)) select.value = previous;
  });
}

function summarizeAudit(details) {
  if (details.count !== undefined) return `Удалено записей: ${details.count}; период ${details.from} — ${details.to}`;
  const before = details.before || {};
  const after = details.after || {};
  if (!Object.keys(before).length && after.address) return `Создано: ${after.address}`;
  if (!Object.keys(after).length && before.address) return `Удалено: ${before.address}`;
  const changes = [];
  [...new Set([...Object.keys(before), ...Object.keys(after)])].forEach((key) => {
    if (JSON.stringify(before[key]) !== JSON.stringify(after[key])) changes.push(`${key}: ${before[key] ?? '—'} → ${after[key] ?? '—'}`);
  });
  return changes.join('; ');
}

function auditAction(action) {
  return ({ create: 'Создание', update: 'Изменение', close: 'Закрытие', delete: 'Удаление', period_delete: 'Удаление за период', account_create: 'Создание пользователя', account_update: 'Изменение пользователя', account_delete: 'Удаление пользователя' })[action] || action;
}
function auditEntity(entity) { return ({ appeal: 'Обращение', outage: 'Отключение', house: 'Адрес', account: 'Пользователь', appeals: 'Обращения', outages: 'Отключения' })[entity] || entity; }
