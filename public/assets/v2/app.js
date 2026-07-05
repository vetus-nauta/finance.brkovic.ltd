(function () {
  'use strict';

  const state = {
    workspaceId: '',
    workspaces: [],
    flows: [],
    categories: [],
    entries: [],
    otherExpenseQueue: [],
    summary: null,
    activeFlowType: 'cash',
    selectedEntryId: '',
    categorySaving: false,
    attachmentsByEntry: {},
    attachmentStatus: '',
    attachmentBusy: false,
    closedMonthDecision: null,
    saving: false,
    draftKey: 'findesk.v2.operational.draft'
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));

  const els = {
    status: $('[data-v2-status]'),
    auth: $('[data-v2-auth]'),
    create: $('[data-v2-create]'),
    createForm: $('[data-v2-create-form]'),
    workspace: $('[data-v2-workspace]'),
    workspaceSelect: $('[data-v2-workspace-select]'),
    refresh: $('[data-v2-refresh]'),
    month: $('[data-v2-month]'),
    feed: $('[data-v2-feed]'),
    checkTable: $('[data-v2-check-table]'),
    count: $('[data-v2-count]'),
    form: $('[data-v2-entry-form]'),
    date: $('[data-v2-date]'),
    rawText: $('[data-v2-raw-text]'),
    submit: $('[data-v2-submit]'),
    previewButton: $('[data-v2-preview]'),
    previewPanel: $('[data-v2-preview-panel]'),
    detail: $('[data-v2-entry-detail]'),
    detailBody: $('[data-v2-entry-detail-body]'),
    detailContent: $('[data-v2-detail-content]'),
    detailRaw: $('[data-v2-detail-raw]'),
    detailFields: $('[data-v2-detail-fields]'),
    selectedEntryId: $('[data-v2-selected-entry-id]'),
    categoryForm: $('[data-v2-category-form]'),
    categorySelect: $('[data-v2-category-select]'),
    categorySave: $('[data-v2-category-save]'),
    categoryError: $('[data-v2-category-error]'),
    attachments: $('[data-v2-attachments]'),
    attachmentForm: $('[data-v2-attachment-form]'),
    attachmentInput: $('[data-v2-attachment-input]'),
    attachmentUpload: $('[data-v2-attachment-upload]'),
    attachmentList: $('[data-v2-attachment-list]'),
    attachmentStatus: $('[data-v2-attachment-status]'),
    closedDecision: $('[data-v2-closed-month-decision]'),
    closedDecisionFrom: $('[data-v2-closed-month-decision-from]'),
    closedDecisionTo: $('[data-v2-closed-month-decision-to]'),
    otherReviewJump: $('[data-v2-other-review-jump]'),
    cashNow: $('[data-v2-cash-now]'),
    cardTotal: $('[data-v2-card-total]'),
    openingCash: $('[data-v2-opening-cash]'),
    otherCount: $('[data-v2-other-count]')
  };

  function currentMonthParts() {
    const now = new Date();
    return {
      year: now.getFullYear(),
      month: now.getMonth() + 1,
      label: now.toLocaleString('en', { month: 'short', year: 'numeric' }),
      today: now.toISOString().slice(0, 10)
    };
  }

  async function v2Api(method, route, body, query) {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: body == null ? undefined : JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    if (!response.ok || data.ok !== true) {
      throw Object.assign({ status: response.status }, data);
    }
    return data;
  }

  async function v2ApiFormData(method, route, formData, query) {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      body: formData
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    if (!response.ok || data.ok !== true) {
      throw Object.assign({ status: response.status }, data);
    }
    return data;
  }

  function setStatus(message, isError) {
    els.status.textContent = message || '';
    els.status.classList.toggle('is-error', !!isError);
  }

  function money(value) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';
    return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(number);
  }

  function text(value, fallback) {
    const out = value === null || value === undefined ? '' : String(value);
    return out || fallback || '—';
  }

  function escapeHtml(value) {
    return text(value, '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[char]);
  }

  function activeFlow() {
    return state.flows.find((flow) => flow.type === state.activeFlowType) || state.flows[0] || null;
  }

  function selectedEntry() {
    return state.entries.find((entry) => entry.id === state.selectedEntryId) || null;
  }

  function categoryLabel(category) {
    const name = category && category.name && (category.name.en || category.name.ru);
    return category ? category.code + (name ? ' · ' + name : '') : '—';
  }

  function renderShellVisibility(mode) {
    els.auth.hidden = mode !== 'auth';
    els.create.hidden = mode !== 'create';
    els.workspace.hidden = mode !== 'workspace';
    els.form.classList.toggle('v2-hidden', mode !== 'workspace');
  }

  function renderWorkspaces() {
    els.workspaceSelect.innerHTML = state.workspaces.map((workspace) => (
      '<option value="' + escapeHtml(workspace.id) + '">' + escapeHtml(workspace.name) + '</option>'
    )).join('');
    els.workspaceSelect.value = state.workspaceId;
  }

  function renderFlows() {
    $$('.v2-flow').forEach((button) => {
      const type = button.getAttribute('data-v2-flow');
      const exists = state.flows.some((flow) => flow.type === type);
      button.disabled = !exists;
      button.classList.toggle('is-active', type === state.activeFlowType);
    });
  }

  function renderSummary() {
    els.cashNow.textContent = money(state.summary && state.summary.cash_now);
    els.cardTotal.textContent = money(state.summary && state.summary.card_expense_total);
    els.openingCash.textContent = money(state.summary && state.summary.opening_cash);
    const otherRows = state.otherExpenseQueue.length
      ? state.otherExpenseQueue
      : state.entries.filter((entry) => entry.status === 'other_review' && entry.category_code === 'other');
    els.otherCount.textContent = String(otherRows.length);
  }

  function entryMeta(entry) {
    const parts = [
      entry.date,
      entry.flow && entry.flow.name,
      entry.status,
      entry.category_code || 'no category'
    ].filter(Boolean);
    return parts.join(' · ');
  }

  function renderFeed() {
    els.count.textContent = state.entries.length + (state.entries.length === 1 ? ' record' : ' records');
    if (!state.entries.length) {
      els.feed.innerHTML = '<div class="v2-entry"><strong>No records yet</strong><small>Write the first money line below.</small></div>';
      return;
    }
    els.feed.innerHTML = state.entries.map((entry) => {
      const amountClass = entry.direction === 'in' ? 'is-in' : (entry.direction === 'out' ? 'is-out' : '');
      const rowClasses = ['v2-entry'];
      if (entry.status === 'unrecognized') rowClasses.push('is-unrecognized');
      if (entry.status === 'other_review' && entry.category_code === 'other') rowClasses.push('is-review');
      if (entry.id === state.selectedEntryId) rowClasses.push('is-selected');
      return '<button class="' + rowClasses.join(' ') + '" type="button" data-v2-entry-select data-v2-entry-id="' + escapeHtml(entry.id) + '">'
        + '<div><strong>' + escapeHtml(entry.raw_text) + '</strong><small>' + escapeHtml(entryMeta(entry)) + '</small></div>'
        + '<div class="v2-entry-amount ' + amountClass + '">' + money(entry.amount) + '</div>'
        + '</button>';
    }).join('');
  }

  function renderCheckTable() {
    const header = ['date', 'raw_text', 'flow', 'sign', 'amount', 'direction', 'entry_type', 'category', 'actor', 'status', 'balance_after'];
    const rows = state.entries.map((entry) => [
      entry.date,
      entry.raw_text,
      entry.flow && entry.flow.type,
      entry.sign,
      entry.amount === null ? 'null' : money(entry.amount),
      entry.direction,
      entry.entry_type,
      entry.category_code,
      entry.actor && entry.actor.name,
      entry.status,
      entry.balance_after === null ? '—' : money(entry.balance_after)
    ]);
    els.checkTable.innerHTML = '<div class="v2-check-row">' + header.map((cell) => '<span>' + cell + '</span>').join('') + '</div>'
      + rows.map((row) => '<div class="v2-check-row">' + row.map((cell) => '<span>' + escapeHtml(text(cell)) + '</span>').join('') + '</div>').join('');
  }

  function renderCategoryOptions(entry) {
    const current = entry && entry.category_code ? entry.category_code : '';
    els.categorySelect.innerHTML = state.categories.map((category) => (
      '<option value="' + escapeHtml(category.code) + '">' + escapeHtml(categoryLabel(category)) + '</option>'
    )).join('');
    els.categorySelect.value = current;
  }

  function formatBytes(value) {
    const bytes = Number(value);
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 102.4) / 10 + ' KB';
    return Math.round(bytes / 1024 / 102.4) / 10 + ' MB';
  }

  function renderAttachments(entry) {
    if (!entry) {
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>No attachments</div>';
      els.attachmentStatus.textContent = '';
      els.attachmentUpload.disabled = true;
      return;
    }

    const attachments = state.attachmentsByEntry[entry.id];
    els.attachmentStatus.textContent = state.attachmentStatus || '';
    els.attachmentUpload.disabled = state.attachmentBusy;
    els.attachmentInput.disabled = state.attachmentBusy;

    if (!attachments) {
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>Loading attachments</div>';
      return;
    }
    if (!attachments.length) {
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>No attachments</div>';
      return;
    }

    els.attachmentList.innerHTML = attachments.map((attachment) => (
      '<div class="v2-attachment-item" data-v2-attachment-item data-v2-attachment-id="' + escapeHtml(attachment.id) + '">'
        + '<div><strong>' + escapeHtml(attachment.file_name) + '</strong>'
        + '<small>' + escapeHtml([attachment.mime_type, formatBytes(attachment.size_bytes), attachment.created_at].filter(Boolean).join(' · ')) + '</small></div>'
        + '<button type="button" data-v2-attachment-delete data-v2-attachment-id="' + escapeHtml(attachment.id) + '">Delete</button>'
      + '</div>'
    )).join('');
  }

  function renderDetail() {
    const entry = selectedEntry();
    els.selectedEntryId.textContent = entry ? entry.id.slice(0, 8) : 'None selected';
    renderClosedMonthDecision();

    if (!entry) {
      els.detailContent.hidden = true;
      const empty = els.detailBody.querySelector('.v2-detail-empty');
      if (empty) empty.hidden = false;
      renderAttachments(null);
      return;
    }

    const empty = els.detailBody.querySelector('.v2-detail-empty');
    if (empty) empty.hidden = true;
    els.detailContent.hidden = false;
    els.detailRaw.textContent = entry.raw_text;
    els.detailRaw.classList.toggle('is-review', entry.status === 'other_review' && entry.category_code === 'other');
    const rows = [
      ['raw_text', entry.raw_text],
      ['date', entry.date],
      ['flow', entry.flow && entry.flow.type],
      ['sign', entry.sign || 'null'],
      ['amount', entry.amount === null ? 'null' : money(entry.amount)],
      ['direction', entry.direction],
      ['entry_type', entry.entry_type],
      ['category', entry.category_code],
      ['actor', entry.actor && entry.actor.name],
      ['status', entry.status],
      ['balance_after', entry.balance_after === null ? '—' : money(entry.balance_after)],
      ['source_type', entry.source_type],
      ['notes', entry.notes || '—'],
      ['matched_rules', (entry.matched_rules || []).map((rule) => rule.pattern || rule.marker || rule.source).filter(Boolean).join(', ') || '—']
    ];
    els.detailFields.innerHTML = rows.map(([label, value]) => (
      '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(text(value)) + '</dd></div>'
    )).join('');
    renderCategoryOptions(entry);
    els.categorySelect.disabled = state.categorySaving;
    els.categorySave.disabled = state.categorySaving;
    renderAttachments(entry);
  }

  function renderClosedMonthDecision() {
    const decision = state.closedMonthDecision;
    els.closedDecision.hidden = !decision;
    if (!decision) return;
    els.closedDecisionFrom.textContent = decision.fromCategoryCode || '—';
    els.closedDecisionTo.textContent = decision.toCategoryCode || '—';
  }

  function renderAll() {
    renderWorkspaces();
    renderFlows();
    renderSummary();
    renderFeed();
    renderCheckTable();
    renderDetail();
  }

  async function loadWorkspaceData() {
    const month = currentMonthParts();
    const workspaceId = state.workspaceId;
    const flowsData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/flows');
    state.flows = flowsData.flows || [];
    if (!state.flows.some((flow) => flow.type === state.activeFlowType)) state.activeFlowType = 'cash';
    const categoriesData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/categories');
    state.categories = categoriesData.categories || [];
    const entriesData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/entries', null, {
      year: month.year,
      month: month.month
    });
    state.entries = entriesData.entries || [];
    if (state.selectedEntryId && !state.entries.some((entry) => entry.id === state.selectedEntryId)) {
      state.selectedEntryId = '';
    }
    const otherExpenseData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/other-expenses');
    state.otherExpenseQueue = otherExpenseData.entries || [];
    const summaryData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/summary');
    state.summary = summaryData.summary || null;
    renderAll();
    if (state.selectedEntryId) {
      loadSelectedEntryAttachments();
    }
  }

  async function loadSelectedEntryAttachments() {
    const entry = selectedEntry();
    if (!entry) return;
    state.attachmentStatus = 'Loading';
    renderAttachments(entry);
    try {
      const data = await v2Api('GET', '/api/entries/' + entry.id + '/attachments');
      state.attachmentsByEntry[entry.id] = data.attachments || [];
      state.attachmentStatus = '';
    } catch (error) {
      state.attachmentStatus = error.error || 'Attachment load failed';
      state.attachmentsByEntry[entry.id] = [];
    }
    renderAttachments(entry);
  }

  async function uploadAttachment(event) {
    event.preventDefault();
    const entry = selectedEntry();
    const file = els.attachmentInput.files && els.attachmentInput.files[0];
    if (!entry || !file || state.attachmentBusy) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('image_mode', 'original');
    state.attachmentBusy = true;
    state.attachmentStatus = 'Attaching';
    renderAttachments(entry);
    try {
      const data = await v2ApiFormData('POST', '/api/entries/' + entry.id + '/attachments', formData);
      const current = state.attachmentsByEntry[entry.id] || [];
      state.attachmentsByEntry[entry.id] = current.concat([data.attachment]);
      els.attachmentInput.value = '';
      state.attachmentStatus = 'Attached';
      setStatus('Attachment saved');
    } catch (error) {
      state.attachmentStatus = error.error || 'Attachment failed';
      setStatus(state.attachmentStatus, true);
    } finally {
      state.attachmentBusy = false;
      renderAttachments(entry);
    }
  }

  async function deleteAttachment(attachmentId) {
    const entry = selectedEntry();
    if (!entry || !attachmentId || state.attachmentBusy) return;
    state.attachmentBusy = true;
    state.attachmentStatus = 'Deleting';
    renderAttachments(entry);
    try {
      await v2Api('DELETE', '/api/attachments/' + attachmentId);
      state.attachmentsByEntry[entry.id] = (state.attachmentsByEntry[entry.id] || []).filter((attachment) => attachment.id !== attachmentId);
      state.attachmentStatus = 'Deleted';
      setStatus('Attachment deleted');
    } catch (error) {
      state.attachmentStatus = error.error || 'Attachment delete failed';
      setStatus(state.attachmentStatus, true);
    } finally {
      state.attachmentBusy = false;
      renderAttachments(entry);
    }
  }

  async function loadApp() {
    const month = currentMonthParts();
    els.month.textContent = month.label;
    els.date.value = month.today;
    setStatus('Loading');
    try {
      const data = await v2Api('GET', '/api/workspaces');
      state.workspaces = data.workspaces || [];
      if (!state.workspaces.length) {
        renderShellVisibility('create');
        setStatus('Create a workspace to start writing');
        return;
      }
      state.workspaceId = state.workspaceId || state.workspaces[0].id;
      renderShellVisibility('workspace');
      await loadWorkspaceData();
      restoreDraft();
      setStatus(navigator.onLine === false ? 'Offline: draft is kept locally' : 'Ready');
    } catch (error) {
      if (error.status === 401) {
        renderShellVisibility('auth');
        setStatus('Not authenticated', true);
      } else {
        renderShellVisibility('workspace');
        setStatus(error.error || 'Load failed', true);
      }
    }
  }

  async function createWorkspace(event) {
    event.preventDefault();
    const form = new FormData(els.createForm);
    setStatus('Creating workspace');
    try {
      const data = await v2Api('POST', '/api/workspaces', {
        name: form.get('name') || 'FinDesk v2 Workspace',
        type: 'yacht',
        currency: 'EUR',
        locale: 'ru',
        opening_cash: form.get('opening_cash') || null
      });
      state.workspaceId = data.workspace.id;
      await loadApp();
    } catch (error) {
      setStatus(error.error || 'Workspace create failed', true);
    }
  }

  function saveDraft() {
    try {
      localStorage.setItem(state.draftKey, els.rawText.value || '');
    } catch (error) {}
  }

  function restoreDraft() {
    try {
      const draft = localStorage.getItem(state.draftKey);
      if (draft && !els.rawText.value) els.rawText.value = draft;
    } catch (error) {}
  }

  function clearDraft() {
    try {
      localStorage.removeItem(state.draftKey);
    } catch (error) {}
  }

  async function previewEntry() {
    const flow = activeFlow();
    if (!flow || !els.rawText.value.trim()) return;
    els.previewPanel.hidden = false;
    els.previewPanel.textContent = 'Checking';
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/parse-preview', {
        flow_id: flow.id,
        date: els.date.value,
        raw_text: els.rawText.value
      });
      const p = data.preview || {};
      els.previewPanel.textContent = [
        'flow ' + text(p.flow && p.flow.type),
        'sign ' + text(p.sign, 'null'),
        'amount ' + text(p.amount, 'null'),
        'category ' + text(p.category_code),
        'status ' + text(p.status)
      ].join(' · ');
    } catch (error) {
      els.previewPanel.textContent = error.error || 'Preview unavailable';
    }
  }

  async function submitEntry(event) {
    event.preventDefault();
    if (state.saving) return;
    const flow = activeFlow();
    const raw = els.rawText.value.trim();
    if (!flow || !raw) return;
    state.saving = true;
    els.submit.disabled = true;
    saveDraft();
    setStatus(navigator.onLine === false ? 'Offline: draft kept locally' : 'Saving');
    try {
      await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/entries', {
        flow_id: flow.id,
        date: els.date.value,
        raw_text: raw
      });
      els.rawText.value = '';
      els.previewPanel.hidden = true;
      clearDraft();
      await loadWorkspaceData();
      setStatus('Saved');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        setStatus('Closed month: create correction, recalculate chain, or cancel', true);
      } else if (navigator.onLine === false || error.status === 0) {
        setStatus('Offline: draft kept locally', true);
      } else {
        setStatus(error.error || 'Save failed', true);
      }
    } finally {
      state.saving = false;
      els.submit.disabled = false;
    }
  }

  function selectEntry(entryId, view) {
    state.selectedEntryId = entryId || '';
    state.closedMonthDecision = null;
    state.attachmentStatus = '';
    els.categoryError.textContent = '';
    renderFeed();
    renderDetail();
    loadSelectedEntryAttachments();
    if (view) {
      const panel = view === 'detail' ? $('[data-v2-entry-detail]') : $('[data-v2-writing]');
      if (panel) panel.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
    }
  }

  function selectFirstOtherReview() {
    const entry = state.otherExpenseQueue[0]
      || state.entries.find((item) => item.status === 'other_review' && item.category_code === 'other');
    if (entry) {
      selectEntry(entry.id, 'detail');
      return;
    }
    setStatus('No Other review records');
  }

  async function saveCategory(event) {
    event.preventDefault();
    const entry = selectedEntry();
    if (!entry || state.categorySaving) return;
    const categoryCode = els.categorySelect.value;
    state.categorySaving = true;
    els.categorySelect.disabled = true;
    els.categorySave.disabled = true;
    els.categoryError.textContent = '';
    setStatus('Saving category');
    try {
      const data = await v2Api('PATCH', '/api/entries/' + entry.id + '/category', {
        category_code: categoryCode
      });
      state.selectedEntryId = data.entry.id;
      await loadWorkspaceData();
      setStatus('Category updated');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        const message = 'Closed month: category cannot be changed without a correction decision. Choices: create correction, recalculate chain, cancel.';
        state.closedMonthDecision = {
          entryId: entry.id,
          fromCategoryCode: entry.category_code,
          toCategoryCode: categoryCode
        };
        els.categoryError.textContent = message;
        setStatus(message, true);
        renderClosedMonthDecision();
      } else {
        const message = error.error || 'Category update failed';
        els.categoryError.textContent = message;
        setStatus(message, true);
      }
    } finally {
      state.categorySaving = false;
      renderDetail();
    }
  }

  async function applyClosedMonthDecision(decision) {
    const pending = state.closedMonthDecision;
    const entry = selectedEntry();
    if (!pending || !entry || pending.entryId !== entry.id || state.categorySaving) return;

    if (decision === 'cancel') {
      state.closedMonthDecision = null;
      els.categoryError.textContent = '';
      renderDetail();
      setStatus('Closed month change cancelled');
      return;
    }

    state.categorySaving = true;
    els.categorySelect.disabled = true;
    els.categorySave.disabled = true;
    setStatus(decision === 'create_correction' ? 'Recording correction decision' : 'Recalculating chain');
    try {
      const data = await v2Api('POST', '/api/entries/' + entry.id + '/category/closed-month-decision', {
        decision,
        category_code: pending.toCategoryCode,
        reason: 'category correction from operational detail'
      });
      state.selectedEntryId = data.entry.id;
      state.closedMonthDecision = null;
      els.categoryError.textContent = '';
      await loadWorkspaceData();
      setStatus(decision === 'create_correction' ? 'Correction decision recorded' : 'Category updated with recalculation');
    } catch (error) {
      const message = error.error || 'Closed month decision failed';
      els.categoryError.textContent = message;
      setStatus(message, true);
    } finally {
      state.categorySaving = false;
      renderDetail();
    }
  }

  function bindEvents() {
    els.createForm.addEventListener('submit', createWorkspace);
    els.form.addEventListener('submit', submitEntry);
    els.categoryForm.addEventListener('submit', saveCategory);
    els.attachmentForm.addEventListener('submit', uploadAttachment);
    els.attachmentList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-v2-attachment-delete]');
      if (button) deleteAttachment(button.getAttribute('data-v2-attachment-id'));
    });
    els.closedDecision.addEventListener('click', (event) => {
      const button = event.target.closest('[data-v2-closed-month-decision-action]');
      if (button) applyClosedMonthDecision(button.getAttribute('data-v2-closed-month-decision-action'));
    });
    els.previewButton.addEventListener('click', previewEntry);
    els.rawText.addEventListener('input', saveDraft);
    els.refresh.addEventListener('click', loadApp);
    els.otherReviewJump.addEventListener('click', selectFirstOtherReview);
    els.feed.addEventListener('click', (event) => {
      const row = event.target.closest('[data-v2-entry-select]');
      if (row) selectEntry(row.getAttribute('data-v2-entry-id'), 'detail');
    });
    els.workspaceSelect.addEventListener('change', async () => {
      state.workspaceId = els.workspaceSelect.value;
      await loadWorkspaceData();
    });
    $$('.v2-flow').forEach((button) => {
      button.addEventListener('click', () => {
        state.activeFlowType = button.getAttribute('data-v2-flow') || 'cash';
        renderFlows();
      });
    });
    $$('[data-v2-view]').forEach((button) => {
      button.addEventListener('click', () => {
        $$('[data-v2-view]').forEach((item) => item.classList.toggle('is-active', item === button));
        const target = button.getAttribute('data-v2-view');
        const panel = target === 'check'
          ? $('[data-v2-check]')
          : (target === 'detail' ? $('[data-v2-entry-detail]') : $('[data-v2-writing]'));
        if (panel) panel.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
      });
    });
    window.addEventListener('online', () => setStatus('Back online'));
    window.addEventListener('offline', () => setStatus('Offline: draft kept locally', true));
  }

  bindEvents();
  loadApp();
})();
