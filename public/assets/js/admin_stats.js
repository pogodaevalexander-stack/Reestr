'use strict';

document.addEventListener('DOMContentLoaded', () => {
  $('#st-from').addEventListener('change', loadOperatorStats);
  $('#st-to').addEventListener('change', loadOperatorStats);
  loadOperatorStats();
  loadAuditLog();
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

async function loadAuditLog() {
  try {
    const result = await api({ action: 'audit' });
    const body = $('#st-audit');
    if (!result.data.length) {
      body.innerHTML = '<tr><td colspan="5" class="empty">Записей пока нет.</td></tr>';
      return;
    }
    body.innerHTML = result.data.map((entry) => {
      let details = {};
      try { details = JSON.parse(entry.details || '{}'); } catch (_) { /* повреждённая старая запись */ }
      const summary = summarizeAudit(details);
      return `<tr><td class="mono sm">${fmtDT(entry.created_at)}</td><td>${esc(entry.actor_name)}</td><td>${esc(auditAction(entry.action))}</td><td>${esc(auditEntity(entry.entity))} ${entry.entity_id ? '#' + entry.entity_id : ''}</td><td class="audit-details">${esc(summary || '—')}</td></tr>`;
    }).join('');
  } catch (error) { toast(error.message, 'err'); }
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
