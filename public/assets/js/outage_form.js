'use strict';

/*
 * Форма регистрации / редактирования отключений, повреждений и благоустройств.
 * Использует глобальные хелперы: $, api, toast.
 * Подсказки адреса (.sg-wrap) предполагаются инициализированными общим скриптом.
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = $('#outage-form');
  const editingId = Number($('#of-id').value) || 0;
  const listUrl = form.dataset.listUrl;

  const el = {
    address: $('#of-address'),
    lat: $('#of-address-wrap .sg-lat'),
    lng: $('#of-address-wrap .sg-lng'),
    type: $('#of-type'),
    detail: $('#of-detail-type'),
    mkd: $('#of-mkd-count'),
    social: $('#of-social-count'),
    other: $('#of-other-count'),
    reason: $('#of-reason'),
    plannedStart: $('#of-planned-start'),
    actualStart: $('#of-actual-start'),
    plannedEnd: $('#of-planned-end'),
    save: $('#of-save'),
    toggle: $('#of-toggle'),
    del: $('#of-delete'), // есть только у администратора
    title: $('#of-form-title'),
  };

  // Поля, которые не нужны для благоустройства.
  const outageOnlyFields = [
    '#of-detail-field', '#of-mkd-field', '#of-social-field', '#of-other-field',
    '#of-planned-start-field', '#of-actual-start-field', '#of-planned-end-field',
  ].map((selector) => $(selector));

  let original = null; // запись, загруженная для редактирования

  el.type.addEventListener('change', applyType);
  form.addEventListener('submit', onSubmit);
  el.toggle.addEventListener('click', onClose);
  if (el.del) el.del.addEventListener('click', onDelete);

  applyType();
  if (editingId) loadRecord();

  /* ---------- вспомогательные ---------- */

  function isImprovement() {
    return el.type.value === 'improvement';
  }

  function toLocal(value) {
    return value ? String(value).replace(' ', 'T').slice(0, 16) : '';
  }

  function applyType() {
    const improvement = isImprovement();
    outageOnlyFields.forEach((field) => { field.hidden = improvement; });
    el.reason.placeholder = improvement ? 'Описание работ' : 'Причина отключения';
  }

  function setBusy(busy) {
    el.save.disabled = busy;
    if (el.toggle) el.toggle.disabled = busy;
    if (el.del) el.del.disabled = busy;
  }

  function goToList() {
    location.href = listUrl;
  }

  /* ---------- загрузка записи ---------- */

  function loadRecord() {
    setBusy(true);
    api({ action: 'outages', op: 'get', id: editingId }).then(({ data: o }) => {
      original = o;

      // Категорию «Благоустройство» нельзя менять на другую и наоборот.
      if (o.type === 'improvement') {
        el.type.value = 'improvement';
        el.type.disabled = true;
      } else {
        const improvementOption = el.type.querySelector('option[value="improvement"]');
        if (improvementOption) improvementOption.remove();
        el.type.value = o.type;
      }

      el.address.value = o.address || '';
      el.detail.value = o.detail_type || 'full_shutdown';
      el.mkd.value = o.mkd_count ?? 0;
      el.social.value = o.social_count ?? 0;
      el.other.value = o.other_count ?? 0;
      el.reason.value = o.reason || '';
      el.plannedStart.value = toLocal(o.planned_start);
      el.actualStart.value = toLocal(o.actual_start);
      el.plannedEnd.value = toLocal(o.planned_end);

      const noun = o.type === 'improvement' ? 'благоустройство' : 'отключение';
      el.title.textContent = `Изменить ${noun} #${o.id}`;
      document.title = `Изменить ${noun}`;
      el.save.textContent = 'Сохранить';

      el.toggle.hidden = !(o.is_active && o.type !== 'improvement');
      el.toggle.textContent = 'Закрыть отключение';
      if (el.del) el.del.hidden = false;

      applyType();
    }).catch((error) => {
      toast(error.message, 'err');
    }).finally(() => setBusy(false));
  }

  /* ---------- сбор и проверка данных ---------- */

  function collect() {
    const improvement = isImprovement();
    const address = el.address.value.trim();
    if (address.length < 5) throw new Error('Укажите адрес (минимум 5 символов).');

    const body = {
      type: el.type.value,
      detail_type: el.detail.value,
      reason: el.reason.value.trim(),
    };

    if (!improvement) {
      const counts = {
        mkd_count: el.mkd.value.trim(),
        social_count: el.social.value.trim(),
        other_count: el.other.value.trim(),
      };
      Object.values(counts).forEach((value) => {
        if (!/^\d+$/.test(value)) {
          throw new Error('Укажите целое неотрицательное количество МКД, соцобъектов и прочего.');
        }
      });
      if (!el.actualStart.value) throw new Error('Укажите фактическое время отключения.');
      Object.assign(body, counts, {
        planned_start: el.plannedStart.value,
        actual_start: el.actualStart.value,
        planned_end: el.plannedEnd.value,
      });
    }

    // Адрес отправляем, только если он изменился (или записи ещё нет).
    const addressChanged = !original || address !== (original.address || '').trim();
    if (addressChanged || !original.house_id) {
      body.address = address;
      body.lat = el.lat.value;
      body.lng = el.lng.value;
    } else {
      body.house_id = original.house_id;
    }

    if (editingId) body.id = editingId;
    return body;
  }

  /* ---------- действия ---------- */

  async function onSubmit(event) {
    event.preventDefault();
    let body;
    try {
      body = collect();
    } catch (error) {
      toast(error.message, 'err');
      return;
    }
    setBusy(true);
    try {
      await api({ action: 'outages', op: editingId ? 'update' : 'create' }, { method: 'POST', body });
      toast(editingId ? 'Изменения сохранены.' : 'Запись зарегистрирована.', 'ok');
      goToList();
    } catch (error) {
      toast(error.message, 'err');
      setBusy(false);
    }
  }

  async function onClose() {
    if (!editingId) return;
    if (!confirm('Закрыть запись? Запись будет удалена без возможности повторного включения.')) return;
    setBusy(true);
    try {
      await api({ action: 'outages', op: 'update' }, { method: 'POST', body: { id: editingId, is_active: 0 } });
      toast(`Запись #${editingId} закрыта и удалена.`, 'ok');
      goToList();
    } catch (error) {
      toast(error.message, 'err');
      setBusy(false);
    }
  }

  async function onDelete() {
    if (!editingId) return;
    if (!confirm('Удалить запись безвозвратно?')) return;
    setBusy(true);
    try {
      await api({ action: 'outages', op: 'delete' }, { method: 'POST', body: { id: editingId } });
      toast(`Запись #${editingId} удалена.`, 'ok');
      goToList();
    } catch (error) {
      toast(error.message, 'err');
      setBusy(false);
    }
  }
});