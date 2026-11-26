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
    del: $('#of-delete'),
    title: $('#of-form-title'),
    fromOutage: $('#of-from-outage'),
    closeSource: $('#of-close-source'),
  };

  const outageOnlyFields = [
    '#of-detail-field', '#of-mkd-field', '#of-social-field', '#of-other-field',
    '#of-planned-start-field', '#of-actual-start-field', '#of-planned-end-field',
  ].map((selector) => $(selector));

  let original = null;    // запись для редактирования
  let sourceHouse = null; // house_id/lat/lng из отключения-источника

  el.type.addEventListener('change', applyType);
  form.addEventListener('submit', onSubmit);
  el.toggle.addEventListener('click', onClose);
  if (el.del) el.del.addEventListener('click', onDelete);

  applyType();

  if (editingId) {
    loadRecord();
  } else if (el.fromOutage && Number(el.fromOutage.value) > 0) {
    prefillFromOutage(Number(el.fromOutage.value));
  }

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

  /* ---------- предзаполнение из отключения ---------- */

  function prefillFromOutage(outageId) {
    setBusy(true);
    api({ action: 'outages', op: 'get', id: outageId })
      .then(({ data: o }) => {
        if (!o) return;
        sourceHouse = {
          house_id: o.house_id || null,
          lat: o.lat || '',
          lng: o.lng || '',
          address: (o.address || '').trim(),
        };

        el.address.value = o.address || '';
        el.reason.value = o.reason || '';
        el.detail.value = o.detail_type || 'full_shutdown';
        el.mkd.value = o.mkd_count ?? 0;
        el.social.value = o.social_count ?? 0;
        el.other.value = o.other_count ?? 0;
        el.plannedStart.value = toLocal(o.planned_start || o.actual_start);
        el.actualStart.value = toLocal(o.actual_start);
        el.plannedEnd.value = toLocal(o.planned_end);

        if (el.lat && sourceHouse.lat) el.lat.value = sourceHouse.lat;
        if (el.lng && sourceHouse.lng) el.lng.value = sourceHouse.lng;

        el.title.textContent = 'Регистрация благоустройства';
        document.title = 'Новое благоустройство';
        applyType();
        toast(`Данные скопированы из отключения #${outageId}. Отключение закроется после сохранения.`, 'ok');
      })
      .catch((error) => toast(error.message, 'err'))
      .finally(() => setBusy(false));
  }

  /* ---------- загрузка записи (редактирование) ---------- */

  function loadRecord() {
    setBusy(true);
    api({ action: 'outages', op: 'get', id: editingId }).then(({ data: o }) => {
      original = o;

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

    const type = improvement ? 'improvement' : el.type.value;

    const body = {
      type,
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
    } else if (el.actualStart.value) {
      body.planned_start = el.plannedStart.value || '';
      body.actual_start = el.actualStart.value || '';
      body.planned_end = el.plannedEnd.value || '';
    }

    const baselineAddress = original
      ? (original.address || '').trim()
      : (sourceHouse ? sourceHouse.address : '');
    const addressChanged = address !== baselineAddress;

    if (addressChanged) {
      body.address = address;
      body.lat = el.lat.value;
      body.lng = el.lng.value;
    } else if (original && original.house_id) {
      body.house_id = original.house_id;
    } else if (sourceHouse && sourceHouse.house_id) {
      body.house_id = sourceHouse.house_id;
      if (sourceHouse.lat) body.lat = sourceHouse.lat;
      if (sourceHouse.lng) body.lng = sourceHouse.lng;
    } else {
      body.address = address;
      body.lat = el.lat.value;
      body.lng = el.lng.value;
    }

    if (editingId) body.id = editingId;
    return body;
  }

  /* ---------- закрытие исходного отключения после создания ---------- */

  async function closeSourceOutage() {
    if (!el.closeSource) return;
    const srcId = Number(el.fromOutage?.value) || 0;
    if (!srcId) return;
    await api({ action: 'outages', op: 'update' }, { method: 'POST', body: { id: srcId, is_active: 0 } });
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
      // 1. Сначала создаём/сохраняем запись (благоустройство или отключение)
      await api({ action: 'outages', op: editingId ? 'update' : 'create' }, { method: 'POST', body });

      // 2. Если пришли из «Закрыть → Да» — закрываем исходное отключение
      let closeWarning = '';
      if (!editingId && el.closeSource) {
        try {
          await closeSourceOutage();
        } catch (error) {
          closeWarning = ` Благоустройство создано, но исходное отключение не удалось закрыть: ${error.message}`;
        }
      }

      toast(
        (editingId ? 'Изменения сохранены.' : 'Запись зарегистрирована.') + closeWarning,
        closeWarning ? 'err' : 'ok'
      );
      goToList();
    } catch (error) {
      toast(error.message, 'err');
      setBusy(false);
    }
  }

  async function onClose() {
    if (!editingId) return;
    if (isImprovement()) return;
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