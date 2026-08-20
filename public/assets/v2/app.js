(function () {
  'use strict';

  const state = {
    workspaceId: '',
    loadedWorkspaceId: '',
    workspaces: [],
    flows: [],
    categories: [],
    entries: [],
    otherExpenseQueue: [],
    summary: null,
    activeScreen: 'hall',
    activeSummaryTab: 'information',
    layer1Summary: null,
    layer1SummaryStatus: 'idle',
    layer1SummaryError: '',
    dictionaryQueue: null,
    dictionaryQueueStatus: 'idle',
    dictionaryQueueError: '',
    rawHistory: null,
    rawHistoryStatus: 'idle',
    rawHistoryError: '',
    rawHistoryConversion: null,
    rawHistoryConversionBusy: false,
    rawHistoryConversionError: '',
    dictionaryTrainingDecisions: [],
    dictionaryTrainingStatus: 'idle',
    dictionaryTrainingError: '',
    dictionaryTrainingBusyKey: '',
    dictionaryInternetBusyKey: '',
    dictionaryInternetFeedbackBusyKey: '',
    dictionaryInternetResults: {},
    dictionaryInternetError: '',
    quickNotes: [],
    quickNotesStatus: 'idle',
    quickNotesError: '',
    activeQuickNoteId: '',
    quickNotePreview: null,
    quickNoteBusy: false,
    quickNoteComposingNew: false,
    quickNoteModalOpen: false,
    quickNoteHistoryOpen: false,
    quickNoteAutoSaveTimer: 0,
    quickNoteAutoSaving: false,
    activeTrainingSourceRowId: '',
    trainingFilter: 'all',
    trainingSearch: '',
    layer1Snapshots: [],
    layer1SnapshotsStatus: 'idle',
    layer1SnapshotsError: '',
    layer1SnapshotSaving: false,
    reportPackages: [],
    reportPackagesStatus: 'idle',
    reportPackagesError: '',
    sourceTraceOpen: false,
    sourceTraceTitle: '',
    sourceTraceMeta: '',
    sourceTraceEntries: [],
    sourceTraceRaw: null,
    sourceTraceByKey: {},
    sourceTraceError: '',
    sourceEntryCache: {},
    sourceCategorySavingEntryId: '',
    sourceCategorySavingAll: false,
    sourceCategoryDrafts: {},
    reportSelectionMode: false,
    reportRangeFrom: '',
    reportRangeTo: '',
    reportRangeLoading: false,
    reportSelectionStartId: '',
    reportSelectionEndId: '',
    reportFragmentOpen: false,
    reportFragmentPreview: null,
    reportFragmentCreated: null,
    reportFragmentStatus: '',
    reportFragmentLoading: false,
    reportFragmentCreating: false,
    reportFragmentUpdating: false,
    reportFragmentCancelConfirm: false,
    reportEditConfirmedEntryIds: {},
    reportPackageSelectionIds: {},
    reportExpandedIds: {},
    reportArchiveView: false,
    reportArchiveFragments: [],
    reportArchiveStatus: 'idle',
    reportArchiveError: '',
    period: null,
    summaryPeriodFrom: '',
    summaryPeriodTo: '',
    summaryPeriodTouched: false,
    archiveOpen: false,
    unsavedGuardOpen: false,
    pendingPeriodAction: null,
    closedEditOpen: false,
    closedEditAction: null,
    closedEditPeriodKey: '',
    closedEditConfirmedPeriods: {},
    mobileFinanceMode: false,
    monthReport: null,
    monthActionBusy: false,
    activeFlowType: 'cash',
    feedView: 'month',
    activeEntryId: '',
    selectedEntryId: '',
    detailOpen: false,
    focusedSurface: 'journal',
    focusOnlyClick: null,
    focusOnlyClickTimer: 0,
    lastRowClick: null,
    previewEntryId: '',
    previewDraftBefore: null,
    previewDateBefore: null,
    editingEntryId: '',
    editDraftBefore: null,
    deleteConfirmEntryId: '',
    deleteConfirmTimer: 0,
    editBusy: false,
    suppressEntryEditUntil: 0,
    autoSaveTimer: 0,
    autoSaveRaw: '',
    syncingScroll: false,
    categorySaving: false,
    attachmentsByEntry: {},
    attachmentStatus: '',
    attachmentBusy: false,
    closedMonthDecision: null,
    saving: false,
    openingWorkspaceId: '',
    inviteToken: '',
    invitePreview: null,
    inviteBusy: false,
    inviteLinks: {},
    accountableDashboards: {},
    accountableDashboardBusyId: '',
    accountableReportQueues: {},
    accountableReportBusyId: '',
    accountableSettlementBusyId: '',
    employeeMode: null,
    employeeModeStatus: 'idle',
    employeeModeError: '',
    employeeOfferBusyId: '',
    employeeReportDraftRows: {},
    employeeReportBusyId: '',
    draftKey: 'findesk.v2.operational.draft'
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));
  const ENTRY_AUTO_SAVE_IDLE_MS = 150000;

  const els = {
    status: $('[data-v2-status]'),
    auth: $('[data-v2-auth]'),
    create: $('[data-v2-create]'),
    hall: $('[data-v2-hall]'),
    hallWorkspaces: $('[data-v2-hall-workspace-list]'),
    invitePanel: $('[data-v2-invite-panel]'),
    inviteTitle: $('[data-v2-invite-title]'),
    inviteText: $('[data-v2-invite-text]'),
    inviteAccept: $('[data-v2-invite-accept]'),
    inviteDismiss: $('[data-v2-invite-dismiss]'),
    createBack: $('[data-v2-create-back]'),
    createForm: $('[data-v2-create-form]'),
    summary: $('[data-v2-summary]'),
    workspace: $('[data-v2-workspace]'),
    employeeScreen: $('[data-v2-employee-screen]'),
    employeeTitle: $('[data-v2-employee-title]'),
    employeeMeta: $('[data-v2-employee-meta]'),
    employeeSummary: $('[data-v2-employee-summary]'),
    employeeOffers: $('[data-v2-employee-offers]'),
    employeeHall: $('[data-v2-employee-hall]'),
    summaryScreen: $('[data-v2-summary-screen]'),
    trainingScreen: $('[data-v2-training-screen]'),
    quickNotesScreen: $('[data-v2-quick-notes-screen]'),
    quickNotesList: $('[data-v2-quick-notes-list]'),
    quickNotesStatus: $('[data-v2-quick-notes-status]'),
    quickNoteNew: $('[data-v2-quick-note-new]'),
    quickNoteBack: $('[data-v2-quick-note-back]'),
    quickNoteDate: $('[data-v2-quick-note-date]'),
    quickNoteText: $('[data-v2-quick-note-text]'),
    quickNoteSave: $('[data-v2-quick-note-save]'),
    quickNoteParse: $('[data-v2-quick-note-parse]'),
    quickNoteConvert: $('[data-v2-quick-note-convert]'),
    quickNotePreview: $('[data-v2-quick-note-preview]'),
    quickNoteLayer: $('[data-v2-quick-note-layer]'),
    quickNoteModalClose: $$('[data-v2-quick-note-modal-close]'),
    quickNoteModalRaw: $('[data-v2-quick-note-modal-raw]'),
    trainingQueue: $('[data-v2-training-queue]'),
    trainingDetail: $('[data-v2-training-detail]'),
    trainingStatus: $('[data-v2-training-status]'),
    trainingRefresh: $('[data-v2-training-refresh]'),
    layer1Information: $('[data-v2-layer1-information]'),
    layer1SummaryStatus: $('[data-v2-layer1-summary-status]'),
    layer1SummaryRefresh: $('[data-v2-layer1-summary-refresh]'),
    layer1Storage: $('[data-v2-layer1-storage]'),
    layer1StorageStatus: $('[data-v2-layer1-storage-status]'),
    layer1StorageRefresh: $('[data-v2-layer1-storage-refresh]'),
    layer1StorageSave: $('[data-v2-layer1-storage-save]'),
    workspaceSelect: $('[data-v2-workspace-select]'),
    refresh: $('[data-v2-refresh]'),
    hallOpen: $('[data-v2-hall-open]'),
    mobileFinanceToggle: $('[data-v2-mobile-finance-toggle]'),
    logout: $('[data-v2-logout]'),
    authForm: $('[data-v2-auth-form]'),
    authEmail: $('[data-v2-auth-email]'),
    authSend: $('[data-v2-auth-send]'),
    authCodeBlock: $('[data-v2-auth-code-block]'),
    authCode: $('[data-v2-auth-code]'),
    authVerify: $('[data-v2-auth-verify]'),
    authMessage: $('[data-v2-auth-message]'),
    month: $('[data-v2-month]'),
    mobileMonth: $('[data-v2-mobile-month]'),
    mobileMonthOpen: $('[data-v2-mobile-month-open]'),
    feed: $('[data-v2-feed]'),
    journalTitle: $('[data-v2-journal-title]'),
    reportSelectionToggle: $('[data-v2-report-selection-toggle]'),
    reportSelectionToggles: $$('[data-v2-report-selection-toggle]'),
    reportSelectionBar: $('[data-v2-report-selection-bar]'),
    reportContext: $('[data-v2-report-context]'),
    reportContextTitle: $('[data-v2-report-context-title]'),
    reportContextOpen: $('[data-v2-report-context-open]'),
    reportSelectionSpacer: $('[data-v2-report-selection-spacer]'),
    reportRangeFrom: $('[data-v2-report-range-from]'),
    reportRangeTo: $('[data-v2-report-range-to]'),
    reportRangeApply: $('[data-v2-report-range-apply]'),
    reportSelectionState: $('[data-v2-report-selection-state]'),
    reportSelectionPreview: $('[data-v2-report-selection-preview]'),
    reportSelectionCancel: $('[data-v2-report-selection-cancel]'),
    reportFragmentLayer: $('[data-v2-report-fragment-layer]'),
    reportFragmentTitle: $('[data-v2-report-fragment-title]'),
    reportFragmentMeta: $('[data-v2-report-fragment-meta]'),
    reportFragmentBody: $('[data-v2-report-fragment-body]'),
    reportFragmentStatus: $('[data-v2-report-fragment-status]'),
    reportFragmentCreate: $('[data-v2-report-fragment-create]'),
    reportFragmentControls: $('[data-v2-report-fragment-controls]'),
    reportFragmentHtml: $('[data-v2-report-fragment-html]'),
    reportFragmentDownload: $('[data-v2-report-fragment-download]'),
    reportFragmentTable: $('[data-v2-report-fragment-table]'),
    reportFragmentPrint: $('[data-v2-report-fragment-print]'),
    reportFragmentCloseDate: $('[data-v2-report-fragment-close-date]'),
    reportFragmentCloseDateSave: $('[data-v2-report-fragment-close-date-save]'),
    reportFragmentSend: $('[data-v2-report-fragment-send]'),
    reportFragmentRebuild: $('[data-v2-report-fragment-rebuild]'),
    reportFragmentRevision: $('[data-v2-report-fragment-revision]'),
    reportFragmentCancel: $('[data-v2-report-fragment-cancel]'),
    reportFragmentClose: $$('[data-v2-report-fragment-close]'),
    journalHeader: $('[data-v2-journal-header]'),
    checkTable: $('[data-v2-check-table]'),
    checkTitle: $('[data-v2-check-title]'),
    checkMeta: $('[data-v2-check-meta]'),
    checkHeader: $('[data-v2-check-header]'),
    count: $('[data-v2-count]'),
    form: $('[data-v2-entry-form]'),
    date: $('[data-v2-date]'),
    rawText: $('[data-v2-raw-text]'),
    submit: $('[data-v2-submit]'),
    editActions: $('[data-v2-entry-edit-actions]'),
    editSave: $('[data-v2-entry-edit-save]'),
    editDelete: $('[data-v2-entry-delete]'),
    previewButton: $('[data-v2-preview]'),
    previewPanel: $('[data-v2-preview-panel]'),
    workspaceShell: $('[data-v2-workspace]'),
    writing: $('[data-v2-writing]'),
    check: $('[data-v2-check]'),
    detail: $('[data-v2-entry-detail]'),
    detailLayer: $('[data-v2-entry-detail-layer]'),
    detailBackdrop: $('[data-v2-entry-detail-backdrop]'),
    detailClose: $('[data-v2-entry-detail-close]'),
    detailBody: $('[data-v2-entry-detail-body]'),
    detailContent: $('[data-v2-detail-content]'),
    detailKicker: $('[data-v2-detail-kicker]'),
    detailTitleText: $('[data-v2-detail-title-text]'),
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
    otherCount: $('[data-v2-other-count]'),
    monthState: $('[data-v2-month-state]'),
    monthToggle: $('[data-v2-month-toggle]'),
    archiveOpen: $('[data-v2-archive-open]'),
    allFeedToggle: $('[data-v2-all-feed-toggle]'),
    reportArchiveToggle: $('[data-v2-report-archive-toggle]'),
    currentMonth: $('[data-v2-current-month]'),
    archiveLayer: $('[data-v2-archive-layer]'),
    archiveModal: $('[data-v2-archive-modal]'),
    archiveClose: $('[data-v2-archive-close]'),
    archiveYear: $('[data-v2-archive-year]'),
    archiveMonth: $('[data-v2-archive-month]'),
    archiveLoad: $('[data-v2-archive-load]'),
    archiveCancel: $$('[data-v2-archive-cancel]'),
    unsavedGuard: $('[data-v2-unsaved-guard]'),
    unsavedSave: $('[data-v2-unsaved-save]'),
    unsavedDiscard: $('[data-v2-unsaved-discard]'),
    unsavedCancel: $$('[data-v2-unsaved-cancel]'),
    closedEditLayer: $('[data-v2-closed-edit-layer]'),
    closedEditConfirm: $('[data-v2-closed-edit-confirm]'),
    closedEditCancel: $$('[data-v2-closed-edit-cancel]'),
    sourceLayer: $('[data-v2-source-layer]'),
    sourceDetail: $('[data-v2-source-detail]'),
    sourceBackdrop: $('[data-v2-source-backdrop]'),
    sourceClose: $('[data-v2-source-close]'),
    sourceTitle: $('[data-v2-source-title]'),
    sourceMeta: $('[data-v2-source-meta]'),
    sourceBody: $('[data-v2-source-body]')
  };

  function currentMonthParts() {
    const now = new Date();
    return monthParts(now.getFullYear(), now.getMonth() + 1, now);
  }

  function dateInputValue(year, month, day) {
    return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
  }

  function localDateInputValue(date) {
    return dateInputValue(date.getFullYear(), date.getMonth() + 1, date.getDate());
  }

  function todayIso() {
    return localDateInputValue(new Date());
  }

  function monthStartDate(parts) {
    return dateInputValue(parts.year, parts.month, 1);
  }

  function monthEndDate(parts) {
    return dateInputValue(parts.year, parts.month, new Date(parts.year, parts.month, 0).getDate());
  }

  function monthParts(year, month, todayDate) {
    const now = todayDate || new Date(year, month - 1, 1);
    const normalized = new Date(year, month - 1, 1);
    const selectedToday = now.getFullYear() === year && now.getMonth() + 1 === month
      ? localDateInputValue(now)
      : dateInputValue(year, month, 1);
    return {
      year,
      month,
      label: normalized.toLocaleString('ru-RU', { month: 'short', year: 'numeric' }),
      today: selectedToday
    };
  }

  function previousMonthParts() {
    const current = currentMonthParts();
    return current.month === 1
      ? monthParts(current.year - 1, 12)
      : monthParts(current.year, current.month - 1);
  }

  function nextMonthParts(parts) {
    const source = parts || selectedMonthParts();
    return source.month === 12
      ? monthParts(source.year + 1, 1)
      : monthParts(source.year, source.month + 1);
  }

  function selectedMonthParts() {
    if (!state.period) state.period = currentMonthParts();
    return state.period;
  }

  function createEntryDefaultDate() {
    return selectedMonthParts().today || todayIso();
  }

  function monthPartsFromDate(dateValue) {
    const parts = String(dateValue || '').match(/^(\d{4})-(\d{2})-\d{2}$/);
    if (!parts) return null;
    return monthParts(Number(parts[1]), Number(parts[2]));
  }

  function normalizeDateRange(fromDate, toDate) {
    let from = String(fromDate || '').trim();
    let to = String(toDate || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(from)) from = '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(to)) to = '';
    if (from && to && from > to) {
      [from, to] = [to, from];
    }
    return { from, to };
  }

  function ensureReportRangeDefaults() {
    const month = selectedMonthParts();
    if (!state.reportRangeFrom) state.reportRangeFrom = monthStartDate(month);
    if (!state.reportRangeTo) {
      state.reportRangeTo = isCurrentPeriod() ? month.today : monthEndDate(month);
    }
    const range = normalizeDateRange(state.reportRangeFrom, state.reportRangeTo);
    state.reportRangeFrom = range.from || monthStartDate(month);
    state.reportRangeTo = range.to || (isCurrentPeriod() ? month.today : monthEndDate(month));
  }

  function reportRangeQuery() {
    ensureReportRangeDefaults();
    return {
      from: state.reportRangeFrom,
      to: state.reportRangeTo
    };
  }

  function periodKey(parts) {
    return String(parts.year).padStart(4, '0') + '-' + String(parts.month).padStart(2, '0');
  }

  function monthPartsFromKey(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})$/);
    if (!match) return null;
    return monthParts(Number(match[1]), Number(match[2]));
  }

  function summaryPeriodRange() {
    const selected = state.summaryPeriodTouched ? selectedMonthParts() : currentMonthParts();
    let from = monthPartsFromKey(state.summaryPeriodFrom) || selected;
    let to = monthPartsFromKey(state.summaryPeriodTo) || from;
    if (periodIndex(from) > periodIndex(to)) {
      [from, to] = [to, from];
    }
    state.summaryPeriodFrom = periodKey(from);
    state.summaryPeriodTo = periodKey(to);
    return {
      from,
      to,
      isRange: periodKey(from) !== periodKey(to),
      label: periodKey(from) === periodKey(to) ? periodKey(from) : periodKey(from) + ' - ' + periodKey(to)
    };
  }

  function periodIndex(parts) {
    return (Number(parts.year) * 12) + Number(parts.month);
  }

  function isCurrentPeriod() {
    return periodIndex(selectedMonthParts()) >= periodIndex(currentMonthParts());
  }

  async function v2Api(method, route, body, query) {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-FinDesk-V2-Request': 'fetch'
      },
      body: body == null ? undefined : JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    if (!response.ok || data.ok !== true) {
      throw Object.assign({ status: response.status }, data);
    }
    return data;
  }

  function urlWithParam(url, key, value) {
    if (!url) return '';
    const next = new URL(url, window.location.origin);
    next.searchParams.set(key, value);
    return next.pathname + next.search;
  }

  async function v2ApiFormData(method, route, formData, query) {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: { 'X-FinDesk-V2-Request': 'fetch' },
      body: formData
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    if (!response.ok || data.ok !== true) {
      throw Object.assign({ status: response.status }, data);
    }
    return data;
  }

  async function authApi(action, body) {
    const url = new URL('/api.php', window.location.origin);
    url.searchParams.set('action', action);
    const response = await fetch(url.toString(), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
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

  function authErrorMessage(error) {
    const messages = {
      invalid_email: 'Введите корректный email.',
      email_send_failed: 'Код не отправлен. Проверьте настройки почты или локальный журнал.',
      invalid_code: 'Введите 6 цифр.',
      code_not_found_or_expired: 'Код истек. Запросите код еще раз.',
      wrong_code: 'Код не совпадает. Если писем несколько, подойдет любой код, которому меньше 30 минут.',
      too_many_attempts: 'Слишком много попыток. Запросите новый код.',
      invalid_json: 'Ответ авторизации поврежден.'
    };
    return messages[error] || 'Не удалось войти. Попробуйте еще раз.';
  }

  function setAuthMessage(message, isError) {
    if (!els.authMessage) return;
    els.authMessage.textContent = message || '';
    els.authMessage.classList.toggle('is-error', !!isError);
  }

  function setAuthBusy(isBusy) {
    if (els.authSend) els.authSend.disabled = !!isBusy;
    if (els.authVerify) els.authVerify.disabled = !!isBusy;
  }

  function money(value) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'EUR' }).format(number);
  }

  function text(value, fallback) {
    const out = value === null || value === undefined ? '' : String(value);
    return out || fallback || '—';
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value || ''));
    return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function currentWorkspace() {
    return state.workspaces.find((workspace) => workspace.id === state.workspaceId) || null;
  }

  function routePreference() {
    const params = new URLSearchParams(window.location.search || '');
    const hash = String(window.location.hash || '').replace(/^#/, '');
    const screen = params.get('screen') || params.get('tab') || hash;
    return {
      screen: ['hall', 'summary', 'training', 'operational'].includes(screen) ? screen : '',
      summaryTab: params.get('summary_tab') || params.get('summaryTab') || '',
      workspace: params.get('workspace') || params.get('workspace_id') || params.get('workspace_name') || '',
      invite: params.get('invite') || ''
    };
  }

  function applyRouteScreenPreference() {
    const preference = routePreference();
    if (preference.invite) {
      state.inviteToken = preference.invite;
      state.activeScreen = 'hall';
    }
    if (preference.screen) state.activeScreen = preference.screen;
    if (!preference.screen && preference.workspace) state.activeScreen = 'operational';
    if (['information', 'sending', 'printing', 'storage'].includes(preference.summaryTab)) {
      state.activeSummaryTab = preference.summaryTab;
    }
  }

  function preferredRouteWorkspaceId() {
    const wanted = routePreference().workspace.trim().toLowerCase();
    if (!wanted) return '';
    const byId = state.workspaces.find((workspace) => String(workspace.id || '').toLowerCase() === wanted);
    if (byId) return byId.id;
    const byName = state.workspaces.find((workspace) => String(workspace.name || '').toLowerCase() === wanted);
    if (byName) return byName.id;
    const contains = state.workspaces.find((workspace) => String(workspace.name || '').toLowerCase().includes(wanted));
    return contains ? contains.id : '';
  }

  function preferredWorkspaceId() {
    if (state.workspaceId && state.workspaces.some((workspace) => workspace.id === state.workspaceId)) {
      return state.workspaceId;
    }
    const routed = preferredRouteWorkspaceId();
    if (routed) return routed;
    const operational = state.workspaces.find((workspace) => !String(workspace.name || '').toLowerCase().includes('archive raw history'));
    return (operational || state.workspaces[0] || {}).id || '';
  }

  function displayNumber(value) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    if (!Number.isFinite(number)) return text(value);
    return new Intl.NumberFormat('ru-RU').format(number);
  }

  function numericValue(value, fallback) {
    const number = Number(value);
    return Number.isFinite(number) ? number : (fallback === undefined ? 0 : fallback);
  }

  function recordWord(count) {
    const number = Math.abs(Number(count));
    const mod10 = number % 10;
    const mod100 = number % 100;
    if (mod10 === 1 && mod100 !== 11) return 'запись';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'записи';
    return 'записей';
  }

  function valueLabel(value) {
    const labels = {
      workspace: 'Пространство',
      period: 'Период',
      currency: 'Валюта',
      status: 'Статус',
      generated_at: 'Сформировано',
      entries_count: 'Записей',
      review_count: 'На проверке',
      opening_cash: 'Входящий остаток',
      ending_cash: 'Конечный остаток',
      source_ids: 'Записей-источников',
      correction_ids: 'Корректировок',
      basis_opening: 'Основа остатка',
      prior_delta: 'Дельта прошлых периодов',
      raw_text: 'Запись',
      date: 'Дата',
      flow: 'Поток',
      sign: 'Знак',
      amount: 'Сумма',
      direction: 'Направление',
      category: 'Категория',
      accounting: 'Учет',
      actor: 'Участник',
      balance_after: 'Остаток после',
      source_type: 'Источник',
      archive_close: 'Архивное закрытие',
      notes: 'Заметки',
      semantic_markers: 'Смысловые маркеры',
      matched_rules: 'Сработавшие правила',
      cash: 'Кеш',
      card: 'Карта',
      cash_expense: 'Расход наличными',
      cash_income: 'Приход наличными',
      card_expense: 'Расход по карте',
      card_income: 'Приход по карте',
      operational: 'Операционный учет',
      lower_accounting: 'Деньги под отчет',
      admin_debt: 'Задолженность администратора',
      debt_or_return: 'Под отчет, займы, долги, возвраты',
      money_movement: 'Перемещение денег',
      guest_cash_issued: 'Наличные, выданные гостям',
      needs_actor: 'Нужно указать участника',
      Unassigned: 'Участник не указан',
      unassigned: 'Участник не указан',
      other_review: 'На проверке',
      unrecognized: 'Не распознано',
      closed: 'Закрыт',
      live: 'В работе',
      open: 'Открыт',
      imported: 'Импортировано',
      ignored: 'Пропущено',
      duplicate_suspect: 'Похоже на дубликат',
      unrecognized: 'Не распознано',
      closed_archive_exception: 'Закрыт архивом',
      include: 'Включить',
      exclude: 'Исключить',
      in: 'Приход',
      out: 'Расход',
      income: 'Приход',
      expense: 'Расход',
      manual: 'Вручную',
      import: 'Импорт',
      imported: 'Импортировано',
      provisions: 'Провизия',
      cleaning: 'Уборка',
      service: 'Сервисные работы',
      boat_supplies: 'Лодочные расходы',
      transport: 'Транспортные расходы',
      guest_expenses: 'Расходы на гостей',
      representative: 'Представительские расходы',
      crew: 'Экипаж',
      other: 'Другие расходы',
      non_commercial_income: 'Некоммерческие поступления',
      commercial_income: 'Коммерческий приход',
      flow_opening_balance: 'Входящий остаток',
      approve_existing_guess_local: 'Подтверждено локально',
      correct_category_local: 'Исправлено локально',
      reject_training: 'Отклонено',
      defer: 'Отложено',
      mark_semantic_blocked: 'Заблокировано',
      propose_universal_candidate: 'Кандидат в общее правило'
    };
    const key = String(value || '');
    return labels[key] || key || '—';
  }

  function firstValue(object, keys, fallback) {
    if (!object) return fallback;
    for (const key of keys) {
      const parts = String(key).split('.');
      let cursor = object;
      let found = true;
      for (const part of parts) {
        if (!cursor || !Object.prototype.hasOwnProperty.call(cursor, part)) {
          found = false;
          break;
        }
        cursor = cursor[part];
      }
      if (found && cursor !== undefined && cursor !== null) {
        return cursor;
      }
    }
    return fallback;
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

  function activeFlowEntries() {
    return state.entries.filter((entry) => {
      const flowType = entry.flow && entry.flow.type ? entry.flow.type : entry.flow_type;
      return String(flowType || '') === state.activeFlowType;
    });
  }

  function reportSelectionEntries() {
    const packageEntries = reportPackageSelectionEntries();
    if (packageEntries.length) return packageEntries;
    const entries = activeFlowEntries();
    if (!state.reportSelectionStartId || !state.reportSelectionEndId) return [];
    const start = entries.findIndex((entry) => String(entry.id) === String(state.reportSelectionStartId));
    const end = entries.findIndex((entry) => String(entry.id) === String(state.reportSelectionEndId));
    if (start < 0 || end < 0) return [];
    const from = Math.min(start, end);
    const to = Math.max(start, end);
    return entries.slice(from, to + 1);
  }

  function reportSelectionIds() {
    return reportSelectionEntries().map((entry) => entry.id);
  }

  function entryIsInReportSelection(entryId) {
    if (!state.reportSelectionMode || !entryId) return false;
    const entry = state.entries.find((item) => String(item.id) === String(entryId));
    const reportId = reportLockId(entry);
    if (reportId && state.reportPackageSelectionIds[reportId]) return true;
    if (String(entryId) === String(state.reportSelectionStartId)) return true;
    if (!state.reportSelectionEndId) return false;
    return reportSelectionEntries().some((entry) => String(entry.id) === String(entryId));
  }

  function resetReportSelection(options) {
    state.reportSelectionMode = false;
    state.reportRangeLoading = false;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    if (options && options.closeFragment) closeReportFragment({ render: false });
    renderFeed();
    renderCheckTable();
    renderReportSelectionState();
  }

  async function leaveReportSelectionMode(options) {
    resetReportSelection({ closeFragment: !(options && options.keepFragment) });
    await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
    focusCreateEntryInput({ clearPreview: true });
  }

  function handleReportSelectionRow(entryId) {
    if (!state.reportSelectionMode || !entryId) return false;
    if (!activeFlowEntries().some((entry) => String(entry.id) === String(entryId))) return true;
    if (state.previewEntryId) clearEntryPreview({ restoreDraft: true });
    if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
    state.reportPackageSelectionIds = {};
    state.selectedEntryId = '';
    state.detailOpen = false;
    state.activeEntryId = entryId;
    if (!state.reportSelectionStartId || state.reportSelectionEndId) {
      state.reportSelectionStartId = entryId;
      state.reportSelectionEndId = '';
      state.reportFragmentPreview = null;
      state.reportFragmentCreated = null;
      state.reportFragmentStatus = '';
      state.reportFragmentCancelConfirm = false;
    } else {
      state.reportSelectionEndId = entryId;
      state.reportFragmentPreview = null;
      state.reportFragmentCreated = null;
      state.reportFragmentStatus = '';
      state.reportFragmentCancelConfirm = false;
    }
    renderFeed();
    renderCheckTable();
    renderDetailState();
    renderReportSelectionState();
    return true;
  }

  async function toggleReportSelectionMode() {
    if (state.reportSelectionMode) {
      await leaveReportSelectionMode();
      return;
    }
    state.reportArchiveView = false;
    if (state.previewEntryId) clearEntryPreview({ restoreDraft: true });
    if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
    closeDetail();
    state.reportSelectionMode = true;
    ensureReportRangeDefaults();
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    renderReportSelectionState();
    setStatus('Открываю ленту отчета');
    await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
    setStatus('Выберите первую и последнюю строку отчета');
  }

  async function applyReportRange() {
    if (!state.workspaceId || !state.reportSelectionMode || state.reportRangeLoading) return;
    const range = normalizeDateRange(
      els.reportRangeFrom ? els.reportRangeFrom.value : state.reportRangeFrom,
      els.reportRangeTo ? els.reportRangeTo.value : state.reportRangeTo
    );
    if (!range.from || !range.to) {
      setStatus('Укажите начало и конец ленты отчета', true);
      return;
    }
    state.reportRangeFrom = range.from;
    state.reportRangeTo = range.to;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    state.reportRangeLoading = true;
    renderReportSelectionState();
    setStatus('Открываю ленту отчета ' + state.reportRangeFrom + ' — ' + state.reportRangeTo);
    try {
      await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
      setStatus('Лента отчета открыта');
    } finally {
      state.reportRangeLoading = false;
      renderReportSelectionState();
    }
  }

  function renderReportSelectionState() {
    if (!els.reportSelectionBar) return;
    const reportContext = activeFlowReportContext();
    const archiveView = reportArchiveViewInfo();
    const showReportContext = Boolean(state.feedView !== 'all' && !state.reportSelectionMode && !archiveView.active && reportContext && reportContext.reportId);
    els.reportSelectionBar.hidden = !state.reportSelectionMode;
    if (els.reportContext) els.reportContext.hidden = !showReportContext;
    if (els.reportContextTitle) {
      els.reportContextTitle.textContent = showReportContext
        ? reportContext.label
        : '';
    }
    if (els.reportContextOpen) {
      els.reportContextOpen.disabled = !showReportContext || state.reportFragmentLoading;
      els.reportContextOpen.textContent = state.reportFragmentLoading ? 'Открываю' : 'Открыть отчет';
      els.reportContextOpen.setAttribute('data-v2-report-id', showReportContext ? reportContext.reportId : '');
    }
    if (els.workspaceShell) {
      els.workspaceShell.classList.toggle('is-report-selection-mode', state.reportSelectionMode);
      if (state.reportSelectionMode || showReportContext) {
        window.requestAnimationFrame(() => {
          const visibleBar = state.reportSelectionMode ? els.reportSelectionBar : els.reportContext;
          const height = visibleBar && !visibleBar.hidden ? Math.ceil(visibleBar.getBoundingClientRect().height) : 0;
          els.workspaceShell.style.setProperty('--v2-report-selection-height', height + 'px');
        });
      } else {
        els.workspaceShell.style.setProperty('--v2-report-selection-height', '0px');
      }
    }
    if (els.reportSelectionSpacer) els.reportSelectionSpacer.hidden = !(state.reportSelectionMode || showReportContext);
    if (els.reportRangeFrom) els.reportRangeFrom.value = state.reportRangeFrom || '';
    if (els.reportRangeTo) els.reportRangeTo.value = state.reportRangeTo || '';
    if (els.reportRangeApply) {
      els.reportRangeApply.disabled = state.reportRangeLoading;
      els.reportRangeApply.textContent = state.reportRangeLoading ? 'Открываю' : 'Открыть';
    }
    (els.reportSelectionToggles || []).forEach((button) => {
      button.hidden = archiveView.active;
      button.classList.toggle('is-active', state.reportSelectionMode);
      button.textContent = state.reportSelectionMode ? 'Отмена отчета' : 'Новый отчет';
      button.setAttribute('aria-pressed', state.reportSelectionMode ? 'true' : 'false');
    });
    const startIndex = activeFlowEntryIndex(state.reportSelectionStartId);
    const endIndex = activeFlowEntryIndex(state.reportSelectionEndId);
    const entries = reportSelectionEntries();
    const selectedPackages = reportPackageSelectionGroups();
    let label = 'Выберите диапазон или несколько строк-отчетов.';
    if (state.reportSelectionStartId && !state.reportSelectionEndId) {
      label = 'Начало: строка ' + displayNumber(startIndex + 1) + '. Выберите конец.';
    }
    if (selectedPackages.length) {
      label = 'Пакет: ' + displayNumber(selectedPackages.length) + ' ' + reportWord(selectedPackages.length)
        + ' · ' + displayNumber(entries.length) + ' ' + recordWord(entries.length) + '.';
    }
    if (entries.length) {
      const from = Math.min(startIndex, endIndex) + 1;
      const to = Math.max(startIndex, endIndex) + 1;
      if (!selectedPackages.length) {
        label = 'Строки ' + displayNumber(from) + '-' + displayNumber(to) + ' · ' + displayNumber(entries.length) + ' ' + recordWord(entries.length) + '.';
      }
    }
    if (els.reportSelectionState) els.reportSelectionState.textContent = label;
    if (els.reportSelectionPreview) {
      if (selectedPackages.length) {
        els.reportSelectionPreview.disabled = selectedPackages.length < 2 || state.reportFragmentLoading || state.reportFragmentCreating;
        els.reportSelectionPreview.textContent = state.reportFragmentCreating ? 'Создаю' : 'Объединить';
      } else {
        els.reportSelectionPreview.disabled = !entries.length || state.reportFragmentLoading;
        els.reportSelectionPreview.textContent = state.reportFragmentLoading ? 'Считаю' : 'Сводка';
      }
    }
    renderReportArchiveViewState();
  }

  function openActiveReportContext() {
    if (state.reportSelectionMode) return false;
    const reportContext = activeFlowReportContext();
    if (!reportContext || !reportContext.reportId) return false;
    openReportFragmentById(reportContext.reportId, 'Открываю отчет по текущей ленте');
    return true;
  }

  function activeFlowEntryIndex(entryId) {
    return activeFlowEntries().findIndex((entry) => entry.id === entryId);
  }

  function reportWord(count) {
    const number = Math.abs(Number(count));
    const mod10 = number % 10;
    const mod100 = number % 100;
    if (mod10 === 1 && mod100 !== 11) return 'отчет';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'отчета';
    return 'отчетов';
  }

  function reportLock(entry) {
    return entry && entry.report_lock ? entry.report_lock : null;
  }

  function reportLockId(entry) {
    const lock = reportLock(entry);
    const id = firstValue(lock, ['report_id', 'fragment_id', 'id'], '');
    return id ? String(id) : '';
  }

  function formatReportDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '—';
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      const parts = raw.split('-');
      return parts[2] + '.' + parts[1] + '.' + parts[0];
    }
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return raw.slice(0, 10);
    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function reportGroupMeta(entries, reportId) {
    const first = entries[0] || null;
    const last = entries[entries.length - 1] || first;
    const lock = reportLock(first) || {};
    const createdAt = firstValue(lock, ['created_at', 'report_created_at', 'generated_at'], firstValue(last, ['report_lock.created_at', 'created_at'], ''));
    const balance = firstValue(lock, [
      'ending_cash',
      'ending_balance',
      'balance_after',
      'totals.ending_cash',
      'summary.totals.ending_cash'
    ], firstValue(last, ['balance_after'], null));
    const title = text(firstValue(lock, ['title', 'report_title'], ''), 'Отчет');
    const version = firstValue(lock, ['version', 'package_version'], '');
    const status = text(firstValue(lock, ['status', 'report_status', 'fragment_status'], 'created'), 'created');
    const closedAt = firstValue(lock, ['closed_at', 'close_date', 'period_closed_at'], createdAt);
    const periodStart = firstValue(lock, ['period_start', 'from_date'], firstValue(first, ['date'], ''));
    const periodEnd = firstValue(lock, ['period_end', 'to_date'], firstValue(last, ['date'], periodStart));
    const entryCount = numericValue(firstValue(lock, ['entry_count', 'entries_count'], entries.length), entries.length);
    const periodLabel = formatReportDate(periodStart) + (periodStart && periodEnd && periodStart !== periodEnd ? ' - ' + formatReportDate(periodEnd) : '');
    const reportDate = closedAt || createdAt;
    return {
      reportId,
      entries,
      title,
      createdAt,
      closedAt,
      balance,
      version,
      status,
      entryCount,
      period: periodLabel || formatReportDate(createdAt),
      label: 'Отчет от ' + formatReportDate(reportDate)
    };
  }

  function reportArchiveGroupFromFragment(fragment, index) {
    const summary = reportSummaryFromFragment(fragment) || {};
    const totals = summary.totals || {};
    const header = summary.header || {};
    const startDate = firstValue(fragment, ['start_date', 'period.from'], firstValue(header, ['from'], ''));
    const endDate = firstValue(fragment, ['end_date', 'period.to'], firstValue(header, ['to'], startDate));
    const closedAt = firstValue(fragment, ['closed_at'], firstValue(header, ['closed_at'], firstValue(fragment, ['generated_at', 'created_at'], '')));
    const createdAt = firstValue(fragment, ['created_at', 'generated_at'], closedAt);
    const entryCount = numericValue(firstValue(fragment, ['entry_count', 'entries_count'], firstValue(header, ['entries_count'], 0)), 0);
    const balance = firstValue(totals, ['ending_cash'], firstValue(summary, ['money_position.physical_total'], null));
    const periodLabel = formatReportDate(startDate) + (startDate && endDate && startDate !== endDate ? ' - ' + formatReportDate(endDate) : '');
    const entries = Array.isArray(fragment.entries) ? fragment.entries : [];
    return {
      reportId: String(fragment.id || ''),
      entries,
      startIndex: index,
      endIndex: index,
      title: text(fragment.title, 'Отчет'),
      createdAt,
      closedAt,
      balance,
      version: firstValue(fragment, ['version'], ''),
      status: text(fragment.status, 'created'),
      entryCount,
      period: periodLabel || formatReportDate(createdAt),
      label: text(fragment.title, 'Отчет от ' + formatReportDate(closedAt || createdAt))
    };
  }

  function reportArchiveFragmentDateKey(fragment) {
    const summary = reportSummaryFromFragment(fragment) || {};
    const header = summary.header || {};
    const startDate = firstValue(fragment, ['start_date', 'period.from'], firstValue(header, ['from'], ''));
    const endDate = firstValue(fragment, ['end_date', 'period.to'], firstValue(header, ['to'], startDate));
    return String(startDate || endDate || fragment.created_at || '');
  }

  function sortReportArchiveFragments(fragments) {
    return (fragments || []).slice().sort((left, right) => {
      const leftKey = reportArchiveFragmentDateKey(left);
      const rightKey = reportArchiveFragmentDateKey(right);
      if (leftKey !== rightKey) return leftKey.localeCompare(rightKey);
      return String(left.created_at || '').localeCompare(String(right.created_at || ''));
    });
  }

  function reportArchiveGroups() {
    if (state.reportArchiveFragments.length) {
      return state.reportArchiveFragments.map((fragment, index) => reportArchiveGroupFromFragment(fragment, index));
    }
    return activeFlowReportGroups();
  }

  function activeFlowReportGroups() {
    const groups = {};
    activeFlowEntries().forEach((entry, index) => {
      const reportId = reportLockId(entry);
      if (!reportId) return;
      if (!groups[reportId]) {
        groups[reportId] = {
          reportId,
          entries: [],
          startIndex: index,
          endIndex: index
        };
      }
      groups[reportId].entries.push(entry);
      groups[reportId].endIndex = index;
    });
    return Object.values(groups).map((group) => Object.assign(group, reportGroupMeta(group.entries, group.reportId)));
  }

  function activeFlowReportGroupById(reportId) {
    return activeFlowReportGroups().find((group) => String(group.reportId) === String(reportId)) || null;
  }

  function reportArchiveViewInfo() {
    if (state.activeScreen !== 'operational' || state.reportSelectionMode) {
      return { active: false, groups: [], entries: [] };
    }
    const entries = activeFlowEntries();
    const groups = reportArchiveGroups();
    return {
      active: Boolean(state.reportArchiveView),
      groups,
      entries
    };
  }

  function isReportArchiveView() {
    return reportArchiveViewInfo().active;
  }

  function reportPackageSelectionGroups() {
    return activeFlowReportGroups().filter((group) => state.reportPackageSelectionIds[group.reportId]);
  }

  function reportPackageSelectionEntries() {
    const seen = {};
    return reportPackageSelectionGroups().flatMap((group) => group.entries).filter((entry) => {
      const key = String(entry.id);
      if (seen[key]) return false;
      seen[key] = true;
      return true;
    });
  }

  function toggleReportPackageSelection(reportId) {
    if (!state.reportSelectionMode || !reportId) return false;
    const group = activeFlowReportGroupById(reportId);
    if (!group) return false;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    state.reportFragmentCancelConfirm = false;
    if (state.reportPackageSelectionIds[reportId]) {
      delete state.reportPackageSelectionIds[reportId];
    } else {
      state.reportPackageSelectionIds[reportId] = true;
    }
    renderFeed();
    renderCheckTable();
    renderReportSelectionState();
    return true;
  }

  function toggleReportRowExpanded(reportId) {
    if (!reportId) return false;
    if (state.reportArchiveView) {
      return openReportFragmentById(reportId, 'Открываю отчет');
    }
    if (state.reportExpandedIds[reportId]) {
      delete state.reportExpandedIds[reportId];
    } else {
      state.reportExpandedIds[reportId] = true;
    }
    renderFeed();
    renderCheckTable();
    return true;
  }

  async function toggleReportArchiveView() {
    if (state.reportArchiveView) {
      state.reportArchiveView = false;
      renderAll();
      setStatus('Оперативный журнал');
      focusCreateEntryInput({ clearPreview: true });
      return;
    }
    if (editingEntry() && hasDirtyEdit()) {
      setStatus('Сначала сохраните или отмените правку записи', true);
      return;
    }
    if (hasPendingCreateEntry()) {
      const saved = await savePendingCreateEntry();
      if (!saved) return;
    }
    await loadReportArchiveFragments({ force: true });
    state.reportArchiveView = true;
    state.reportSelectionMode = false;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    closeDetail();
    clearEntryPreview({ restoreDraft: true });
    renderAll();
    setStatus(state.reportArchiveFragments.length ? 'Режим просмотра отчетов' : 'Сохраненных отчетов пока нет');
  }

  async function toggleAllFeedView() {
    if (!state.workspaceId || state.activeScreen !== 'operational') return;
    if (editingEntry() && hasDirtyEdit()) {
      setStatus('Сначала сохраните или отмените правку записи', true);
      return;
    }
    if (hasPendingCreateEntry()) {
      const saved = await savePendingCreateEntry();
      if (!saved) return;
    }
    const nextView = state.feedView === 'all' ? 'month' : 'all';
    state.feedView = nextView;
    state.reportArchiveView = false;
    state.reportSelectionMode = false;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    closeDetail();
    clearEntryPreview({ restoreDraft: true });
    clearFocusOnlyClick();
    setStatus(nextView === 'all' ? 'Открываю всю ленту' : 'Открываю текущий месяц');
    await loadWorkspaceData({
      allowLatestFallback: false,
      autoAdvanceClosed: false,
      preferLatest: true,
      scrollToBottom: true
    });
    setStatus(nextView === 'all' ? 'Вся лента' : (isCurrentPeriod() ? 'Текущий месяц' : 'Архив: ' + selectedMonthParts().label));
    focusCreateEntryInput({ clearPreview: true });
  }

  function setActiveEntryNearFlowIndex(index) {
    const entries = activeFlowEntries();
    const next = entries[Math.min(Math.max(0, index), entries.length - 1)] || entries[entries.length - 1] || null;
    state.activeEntryId = next ? next.id : '';
    return next;
  }

  function ensureActiveEntryForCurrentFlow(options) {
    const entries = activeFlowEntries();
    const keepExisting = entries.some((entry) => entry.id === state.activeEntryId);
    if (keepExisting) return;
    const next = options && options.preferFirst ? entries[0] : entries[entries.length - 1];
    state.activeEntryId = next ? next.id : '';
    if (state.previewEntryId && !entries.some((entry) => entry.id === state.previewEntryId)) {
      state.previewEntryId = '';
      state.previewDraftBefore = null;
      state.previewDateBefore = null;
    }
    if (state.editingEntryId && !entries.some((entry) => entry.id === state.editingEntryId)) {
      clearEntryEdit({ restoreDraft: true });
    }
  }

  async function switchActiveFlow(type) {
    if (!type || type === state.activeFlowType) return;
    if (hasPendingCreateEntry()) {
      const saved = await savePendingCreateEntry();
      if (!saved) return;
    }
    const wasReportSelectionMode = state.reportSelectionMode;
    state.activeFlowType = type;
    state.lastRowClick = null;
    state.selectedEntryId = '';
    state.detailOpen = false;
    state.reportSelectionMode = false;
    state.reportRangeFrom = '';
    state.reportRangeTo = '';
    state.reportRangeLoading = false;
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    state.reportPackageSelectionIds = {};
    if (state.reportArchiveView && !activeFlowReportGroups().length) state.reportArchiveView = false;
    closeReportFragment({ render: false });
    clearEntryPreview({ restoreDraft: true });
    if (wasReportSelectionMode) {
      await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
      focusCreateEntryInput({ clearPreview: true });
      return;
    }
    ensureActiveEntryForCurrentFlow();
    renderAll();
    focusCreateEntryInput({ clearPreview: true });
  }

  function selectedEntry() {
    return state.entries.find((entry) => entry.id === state.selectedEntryId) || null;
  }

  function activeEntry() {
    return state.entries.find((entry) => entry.id === state.activeEntryId) || null;
  }

  function editingEntry() {
    return state.entries.find((entry) => entry.id === state.editingEntryId) || null;
  }

  function previewingEntry() {
    return state.entries.find((entry) => entry.id === state.previewEntryId) || null;
  }

  function isCurrentMonthClosed() {
    return Boolean(state.monthReport && state.monthReport.is_closed);
  }

  function categoryLabel(category) {
    const name = category && category.name && (category.name.ru || category.name.en);
    return category ? (name || valueLabel(category.code)) : '—';
  }

  function categoryDisplayLabel(entry) {
    if (!entry) return '—';
    const name = entry.category_name && (entry.category_name.ru || entry.category_name.en);
    if (name) return name;
    return categoryNameByCode(entry.category_code);
  }

  function categoryNameByCode(code) {
    const value = String(code || '');
    if (!value) return '—';
    const category = state.categories.find((item) => item.code === value);
    return category ? categoryLabel(category) : valueLabel(value);
  }

  function categoryOptionsHtml(selected) {
    return '<option value="">Категория не выбрана</option>' + state.categories.map((category) => (
      '<option value="' + escapeHtml(category.code) + '"' + (category.code === selected ? ' selected' : '') + '>' + escapeHtml(categoryLabel(category)) + '</option>'
    )).join('');
  }

  function lowerAccountingParticipantLabel(value) {
    const participant = text(value);
    if (!participant || participant === 'Unassigned' || participant.toLowerCase() === 'unassigned') {
      return 'Участник не указан';
    }
    return participant;
  }

  function accountingDisplayLabel(entry) {
    if (!entry) return 'Операционный учет';
    const type = entry.accounting_type || '';
    if (type === 'debt_or_return') {
      return lowerAccountingDebtLabel(entry.raw_text || entry.notes || '');
    }
    if (entry.accounting_section === 'admin_debt' || type === 'admin_debt') {
      return 'Задолженность администратора';
    }
    if (entry.accounting_section === 'lower_accounting' || type) {
      return valueLabel(type || 'lower_accounting');
    }
    return entry.accounting_label || valueLabel(entry.accounting_section || 'operational');
  }

  function lowerAccountingDebtLabel(rawText) {
    const sample = String(rawText || '').toLowerCase();
    if (/под ?отчет|подотчет|пот отчет|выдал|передал|дал\s/u.test(sample)) {
      return 'Деньги под отчет экипажу/участнику';
    }
    if (/возврат|вернул|вернула|вернули|сдал|сдала/u.test(sample)) {
      return 'Возврат или закрытие подотчета';
    }
    if (/долг|кредит|займ|заем|рассроч/u.test(sample)) {
      return 'Займы, долги, кредиты';
    }
    return valueLabel('debt_or_return');
  }

  function dictionaryBlockedTargetHelp(example) {
    const sample = String(example && example.description || '').toLowerCase();
    if (/под ?отчет|подотчет|пот отчет|выдал|передал|дал\s/u.test(sample)) {
      return 'Выданные экипажу деньги пока не расходная категория. Оставьте нижний учет, а фактические траты экипажа потом разберите по обычным категориям.';
    }
    return 'Это не обучает словарь автоматически. Если строка на самом деле обычный расход, выберите категорию ниже и сохраните ручной разбор.';
  }

  function dictionaryBlockedTargetLabel(example) {
    const blockers = Array.isArray(example && example.blockers) ? example.blockers : [];
    const reviewReason = String(example && example.review_reason || '');
    if (blockers.includes('debt_or_return') || reviewReason === 'blocked_by_debt') {
      return lowerAccountingDebtLabel(example && example.description || dictionaryExampleSample(example || {}));
    }
    if (blockers.includes('money_movement') || reviewReason === 'private_money_movement') {
    return 'Перемещение денег';
  }
    if (blockers.includes('card_income_manual_guard') || reviewReason === 'card_income_manual_guard') {
      return 'Ручная проверка: пополнение карты';
    }
    return 'Ручная проверка';
  }

  function loadMobileModePreference() {
    try {
      state.mobileFinanceMode = localStorage.getItem('findesk.v2.mobile.financeMode') === '1';
    } catch (error) {
      state.mobileFinanceMode = false;
    }
  }

  function saveMobileModePreference() {
    try {
      localStorage.setItem('findesk.v2.mobile.financeMode', state.mobileFinanceMode ? '1' : '0');
    } catch (error) {}
  }

  function toggleMobileFinanceMode() {
    state.mobileFinanceMode = !state.mobileFinanceMode;
    saveMobileModePreference();
    renderMobileMode();
    renderShellVisibility(state.activeScreen === 'hall' ? 'hall' : (state.workspaceId ? 'workspace' : 'auth'));
    renderAll();
    if (!state.mobileFinanceMode) focusCreateEntryInput({ clearPreview: true });
  }

  function renderMobileMode() {
    document.body.classList.toggle('v2-mobile-finance-mode', state.mobileFinanceMode);
    document.body.classList.toggle('v2-flow-card-active', state.activeFlowType === 'card');
    document.body.classList.toggle('v2-flow-cash-active', state.activeFlowType !== 'card');
    if (els.mobileFinanceToggle) {
      els.mobileFinanceToggle.hidden = false;
      els.mobileFinanceToggle.textContent = state.mobileFinanceMode ? 'Ввод' : 'Обзор';
      els.mobileFinanceToggle.title = state.mobileFinanceMode ? 'Ввод записей' : 'Финансовый обзор';
      els.mobileFinanceToggle.setAttribute('aria-label', state.mobileFinanceMode ? 'Ввод записей' : 'Финансовый обзор');
    }
    if (els.mobileMonthOpen) els.mobileMonthOpen.hidden = false;
  }

  function renderShellVisibility(mode) {
    const hallMode = mode === 'hall';
    const authMode = mode === 'auth';
    const workspaceMode = mode === 'workspace';
    const employeeMode = mode === 'employee';
    const operationalActive = workspaceMode && state.activeScreen === 'operational';
    const quickNotesActive = workspaceMode && state.activeScreen === 'quick-notes';
    const summaryActive = workspaceMode && state.activeScreen === 'summary';
    const trainingActive = workspaceMode && state.activeScreen === 'training';
    els.auth.hidden = mode !== 'auth';
    els.create.hidden = mode !== 'create';
    if (els.createBack) els.createBack.hidden = mode !== 'create' || !state.workspaces.length;
    if (els.hall) els.hall.hidden = !hallMode;
    if (els.employeeScreen) els.employeeScreen.hidden = !employeeMode;
    els.summary.hidden = !workspaceMode;
    els.workspace.hidden = !operationalActive;
    if (els.quickNotesScreen) els.quickNotesScreen.hidden = !quickNotesActive;
    if (els.summaryScreen) els.summaryScreen.hidden = !summaryActive;
    if (els.trainingScreen) els.trainingScreen.hidden = !trainingActive;
    if (els.logout) els.logout.hidden = mode === 'auth';
    if (els.hallOpen) els.hallOpen.hidden = !workspaceMode;
    els.form.classList.toggle('v2-hidden', !operationalActive);
    if (els.previewPanel) els.previewPanel.classList.toggle('v2-hidden', !operationalActive);
    document.body.classList.remove('v2-booting');
    document.body.classList.toggle('v2-auth-mode', authMode);
    document.body.classList.toggle('v2-hall-mode', hallMode || employeeMode);
    document.body.classList.toggle('v2-employee-shell-mode', employeeMode);
    document.body.classList.toggle('v2-screen-operational', operationalActive);
    document.body.classList.toggle('v2-screen-quick-notes', quickNotesActive);
    document.body.classList.toggle('v2-screen-summary', summaryActive);
    document.body.classList.toggle('v2-screen-training', trainingActive);
    renderMobileMode();
    renderScreenNavigation();
    renderMobileViewNavigation();
    if (!operationalActive) closeDetail();
    if (!summaryActive && !trainingActive) closeSourceTrace();
  }

  function renderScreenNavigation() {
    $$('[data-v2-screen]').forEach((button) => {
      const screen = button.getAttribute('data-v2-screen') || 'operational';
      const active = screen === state.activeScreen;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function renderMobileViewNavigation() {
    $$('[data-v2-view]').forEach((button) => {
      const view = button.getAttribute('data-v2-view') || 'write';
      const active = state.activeScreen === 'quick-notes'
        ? view === 'quick-notes'
        : state.activeScreen === 'operational'
          ? view === (state.focusedSurface === 'check' ? 'check' : 'write')
          : false;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function renderSummaryTabs() {
    $$('[data-v2-summary-tab]').forEach((button) => {
      const tab = button.getAttribute('data-v2-summary-tab') || 'information';
      const active = tab === state.activeSummaryTab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    $$('[data-v2-summary-panel]').forEach((panel) => {
      panel.hidden = panel.getAttribute('data-v2-summary-panel') !== state.activeSummaryTab;
    });
  }

  function renderWorkspaces() {
    els.workspaceSelect.innerHTML = state.workspaces.map((workspace) => (
      '<option value="' + escapeHtml(workspace.id) + '">' + escapeHtml(workspace.name) + '</option>'
    )).join('');
    els.workspaceSelect.value = state.workspaceId;
    renderHall();
  }

  function workspaceTypeLabel(type) {
    const labels = {
      yacht: 'Яхта',
      family: 'Семья',
      personal: 'Личное',
      business: 'Бизнес',
      trip: 'Поездка',
      custom: 'Другое'
    };
    return labels[type] || 'Пространство';
  }

  function workspaceRoleLabel(role) {
    const labels = {
      owner: 'Владелец',
      admin: 'Администратор',
      assistant: 'Финансист',
      finance: 'Финансист',
      employee: 'Сотрудник',
      viewer: 'Только просмотр'
    };
    return labels[role] || 'Участник';
  }

  function renderHall() {
    if (!els.hallWorkspaces) return;
    renderInvitePanel();
    if (!state.workspaces.length) {
      els.hallWorkspaces.innerHTML = '<section class="v2-hall-empty"><h2>Пространств пока нет</h2><p>Создайте первое пространство, чтобы начать учет.</p><button type="button" data-v2-hall-create-open>Создать пространство</button></section>';
      return;
    }
    els.hallWorkspaces.innerHTML = state.workspaces.map((workspace) => {
      const role = workspace.role || workspace.member_role || '';
      const selected = String(workspace.id || '') === String(state.workspaceId || '');
      const busy = String(workspace.id || '') === String(state.openingWorkspaceId || '');
      const canInvite = workspace.can_admin === true;
      const inviteUrl = (state.inviteLinks || {})[workspace.id] || '';
      const isScopedEmployee = workspace.can_read_workspace === false;
      const accountableOpen = !!state.accountableDashboards[String(workspace.id || '')] || !!state.accountableReportQueues[String(workspace.id || '')];
      return '<article class="v2-hall-card' + (selected ? ' is-selected' : '') + (accountableOpen ? ' has-accountable' : '') + '" data-v2-hall-workspace-tile>'
        + '<div class="v2-hall-card-main">'
        + '<div><span>' + escapeHtml(workspaceTypeLabel(workspace.type)) + '</span><h2 title="' + escapeHtml(workspace.name) + '">' + escapeHtml(workspace.name) + '</h2></div>'
        + '<p data-v2-hall-role>' + escapeHtml(workspace.role_label || workspaceRoleLabel(role)) + '</p>'
        + '</div>'
        + '<div class="v2-hall-card-actions">'
        + '<button class="v2-hall-primary-action" type="button" data-v2-hall-workspace-open data-v2-workspace-id="' + escapeHtml(workspace.id) + '"' + (busy ? ' disabled' : '') + '>' + (busy ? 'Открываю' : (isScopedEmployee ? 'Мой учет' : 'Открыть')) + '</button>'
        + (canInvite ? '<button class="v2-hall-primary-action" type="button" data-v2-hall-accountable-open data-v2-workspace-id="' + escapeHtml(workspace.id) + '">Под отчет</button>' : '')
        + (canInvite ? '<button class="v2-hall-secondary-action" type="button" data-v2-hall-invite-create data-v2-workspace-id="' + escapeHtml(workspace.id) + '">Пригласить</button>' : '')
        + (canInvite ? '<button class="v2-hall-secondary-action" type="button" data-v2-hall-offer-create data-v2-workspace-id="' + escapeHtml(workspace.id) + '" title="Выдать под отчет">Выдать</button>' : '')
        + '</div>'
        + (canInvite ? '<button class="v2-hall-danger-action" type="button" data-v2-hall-workspace-delete data-v2-workspace-id="' + escapeHtml(workspace.id) + '" data-v2-workspace-name="' + escapeHtml(workspace.name) + '">Удалить пространство</button>' : '')
        + (inviteUrl ? '<label class="v2-invite-link"><span>Ссылка сотруднику</span><input type="text" readonly value="' + escapeHtml(inviteUrl) + '"></label>' : '')
        + renderHallAccountableDashboard(workspace)
        + renderHallAccountableReportQueue(workspace)
        + '</article>';
    }).join('');
  }

  function accountableOfferStatusLabel(status) {
    const labels = {
      pending_offer: 'Ожидает сотрудника',
      accepted_by_employee: 'Деньги у сотрудника',
      cancelled: 'Отменено'
    };
    return labels[status] || 'Статус не задан';
  }

  function accountableReportStatusLabel(status) {
    const labels = {
      draft: 'Черновик',
      submitted: 'На проверке',
      accepted_by_admin: 'Принят',
      rework_requested: 'Нужна правка',
      rejected: 'Отклонен',
      cancelled: 'Отменен'
    };
    return labels[status] || 'Отчет не создан';
  }

  function accountableSettlementStatusLabel(status) {
    const labels = {
      closed: 'Закрыто',
      return_due: 'Остаток к возврату',
      reimburse_due: 'Нужно возместить сотруднику',
      discrepancy: 'Есть расхождение'
    };
    return labels[status] || 'Расчет не принят';
  }

  function accountableSettlementResolutionLabel(settlement) {
    if (!settlement) return '';
    if (String(settlement.resolution_status || '') === 'resolved') {
      const amount = Number(settlement.resolved_amount || 0);
      return 'Физически закрыто' + (amount ? ' · ' + money(amount) : '');
    }
    const status = String(settlement.status || '');
    if (status === 'return_due') return 'Ждет возврата';
    if (status === 'reimburse_due') return 'Ждет возмещения';
    return '';
  }

  function accountableSettlementActionLabel(settlement) {
    const status = String((settlement || {}).status || '');
    if (String((settlement || {}).resolution_status || 'open') === 'resolved') return '';
    if (status === 'return_due') return 'Принять возврат';
    if (status === 'reimburse_due') return 'Возместить';
    return '';
  }

  function accountableLedgerStatusLabel(status) {
    const labels = {
      not_materialized: 'Не включен в учет',
      materialized: 'Уже в учете',
      partial: 'В учете частично',
      revoked: 'Исключен из учета'
    };
    return labels[status] || 'Не включен в учет';
  }

  function renderHallAccountableMetric(label, value, tone) {
    return '<div class="v2-hall-accountable-metric' + (tone ? ' is-' + escapeHtml(tone) : '') + '">'
      + '<span>' + escapeHtml(label) + '</span>'
      + '<strong>' + escapeHtml(money(value || 0)) + '</strong>'
      + '</div>';
  }

  function renderHallAccountableDashboard(workspace) {
    const workspaceId = String(workspace.id || '');
    const dashboard = state.accountableDashboards[workspaceId] || null;
    if (!dashboard) return '';
    if (dashboard.status === 'loading') {
      return '<section class="v2-hall-accountable"><div><h3>Под отчет</h3><span>Загружаю контроль сотрудников...</span></div></section>';
    }
    if (dashboard.error) {
      return '<section class="v2-hall-accountable is-error"><div><h3>Под отчет</h3><span>' + escapeHtml(dashboard.error) + '</span></div></section>';
    }
    const data = dashboard.data || {};
    const summary = data.summary || {};
    const employees = Array.isArray(data.employees) ? data.employees : [];
    return '<section class="v2-hall-accountable">'
      + '<div class="v2-hall-accountable-head">'
      + '<div><h3>Под отчет</h3><span>Без изменения кассы и карты</span></div>'
      + '<button type="button" data-v2-hall-accountable-refresh data-v2-workspace-id="' + escapeHtml(workspaceId) + '">Обновить</button>'
      + '</div>'
      + '<div class="v2-hall-accountable-metrics">'
      + renderHallAccountableMetric('Выдано', summary.issued_total, '')
      + renderHallAccountableMetric('На проверке', summary.submitted_report_total, '')
      + renderHallAccountableMetric('К возврату', summary.return_due_total, 'return')
      + renderHallAccountableMetric('К возмещению', summary.reimburse_due_total, 'reimburse')
      + '</div>'
      + (summary.pending_offer_total ? '<p class="v2-hall-accountable-note">Ожидает принятия сотрудником: <strong>' + escapeHtml(money(summary.pending_offer_total)) + '</strong></p>' : '')
      + (employees.length ? '<div class="v2-hall-accountable-rows">'
        + employees.map((employee) => {
          const metrics = employee.metrics || {};
          const reports = Array.isArray(employee.reports) ? employee.reports.slice(0, 3) : [];
          return '<div class="v2-hall-accountable-row">'
            + '<div><strong>' + escapeHtml(employee.employee_label || 'Сотрудник') + '</strong><span>'
            + 'Выдано ' + escapeHtml(money(metrics.issued_total || 0))
            + ' · Отчитано ' + escapeHtml(money(metrics.accepted_report_total || 0))
            + ' · Открыто ' + escapeHtml(money(metrics.open_position_total || 0))
            + '</span></div>'
            + '<div class="v2-hall-accountable-badges">'
            + '<span>Проверка ' + escapeHtml(String(metrics.submitted_report_count || 0)) + '</span>'
            + '<span>Не в учете ' + escapeHtml(String(metrics.not_materialized_report_count || 0)) + '</span>'
            + '<span>В учете ' + escapeHtml(String(metrics.materialized_report_count || 0)) + '</span>'
            + '</div>'
            + '<div class="v2-hall-accountable-money">'
            + '<span>Возврат ' + escapeHtml(money(metrics.return_due_total || 0)) + '</span>'
            + '<span>Возместить ' + escapeHtml(money(metrics.reimburse_due_total || 0)) + '</span>'
            + '</div>'
            + (reports.length ? '<div class="v2-hall-accountable-reports">'
              + reports.map((report) => {
                const ledgerStatus = String(report.ledger_materialization_status || 'not_materialized');
                const status = report.settlement_status || (report.settlement || {}).status || report.status;
                return '<span>'
                  + escapeHtml(accountableReportStatusLabel(report.status))
                  + ' · ' + escapeHtml(accountableSettlementStatusLabel(status))
                  + ' · ' + escapeHtml(accountableLedgerStatusLabel(ledgerStatus))
                  + ' · ' + escapeHtml(String(report.row_count || 0)) + ' строк'
                  + '</span>';
              }).join('')
              + '</div>' : '')
            + '</div>';
        }).join('')
        + '</div>' : '<p class="v2-hall-accountable-note">Денег под отчет и отчетов сотрудников пока нет.</p>')
      + '</section>';
  }

  function renderHallAccountableReportQueue(workspace) {
    const workspaceId = String(workspace.id || '');
    const queue = state.accountableReportQueues[workspaceId] || null;
    if (!queue) return '';
    if (queue.status === 'loading') {
      return '<div class="v2-hall-report-queue"><span>Загружаю отчеты сотрудников...</span></div>';
    }
    if (queue.error) {
      return '<div class="v2-hall-report-queue is-error"><span>' + escapeHtml(queue.error) + '</span></div>';
    }
    const reports = Array.isArray(queue.reports) ? queue.reports : [];
    if (!reports.length) {
      return '<div class="v2-hall-report-queue"><span>Отчетов сотрудников для обработки пока нет.</span></div>';
    }
    return '<div class="v2-hall-report-queue">'
      + reports.map((report) => {
        const busy = state.accountableReportBusyId === String(report.id || '');
        const settlement = report.settlement || {};
        const status = report.settlement_status || settlement.status || report.status;
        const ledgerStatus = String(report.ledger_materialization_status || 'not_materialized');
        const accepted = report.status === 'accepted_by_admin';
        const included = ledgerStatus === 'materialized';
        const settlementAction = accountableSettlementActionLabel(settlement);
        const settlementBusy = state.accountableSettlementBusyId === String(settlement.id || '');
        const settlementAmount = String(settlement.status || '') === 'return_due'
          ? Number(settlement.return_due_amount || 0)
          : Number(settlement.reimburse_due_amount || 0);
        const settlementResolution = accountableSettlementResolutionLabel(settlement);
        return '<article class="v2-hall-report-card">'
          + '<div><span>' + escapeHtml(accountableReportStatusLabel(report.status)) + '</span><strong>' + escapeHtml(money(report.total_amount || 0)) + '</strong></div>'
          + '<p>' + escapeHtml(report.title || 'Отчет сотрудника') + '</p>'
          + '<small>' + escapeHtml(accountableSettlementStatusLabel(status))
          + (settlementResolution ? ' · ' + escapeHtml(settlementResolution) : '')
          + ' · ' + escapeHtml(accountableLedgerStatusLabel(ledgerStatus)) + ' · ' + escapeHtml(String(report.row_count || 0)) + ' строк</small>'
          + '<div class="v2-hall-report-actions">'
          + (report.status === 'submitted'
            ? '<button type="button" data-v2-hall-report-accept="' + escapeHtml(report.id || '') + '" data-v2-workspace-id="' + escapeHtml(workspaceId) + '"' + (busy ? ' disabled' : '') + '>' + (busy ? 'Принимаю отчет' : 'Принять отчет') + '</button>'
            : '')
          + (accepted && !included
            ? '<button type="button" data-v2-hall-report-materialize="' + escapeHtml(report.id || '') + '" data-v2-workspace-id="' + escapeHtml(workspaceId) + '"' + (busy ? ' disabled' : '') + '>' + (busy ? 'Включаю в учет' : 'Включить в учет') + '</button>'
            : '')
          + (accepted && settlementAction && settlement.id
            ? '<button class="v2-hall-settlement-action' + (String(settlement.status || '') === 'return_due' ? ' is-return' : ' is-reimburse') + '" type="button" data-v2-hall-settlement-cash-resolve="' + escapeHtml(settlement.id || '') + '" data-v2-workspace-id="' + escapeHtml(workspaceId) + '" data-v2-settlement-status="' + escapeHtml(settlement.status || '') + '" data-v2-settlement-amount="' + escapeHtml(String(settlementAmount || 0)) + '"' + (settlementBusy ? ' disabled' : '') + '>' + (settlementBusy ? 'Закрываю' : settlementAction) + '</button>'
            : '')
          + (included ? '<button type="button" disabled>Уже в учете</button>' : '')
          + '</div>'
          + '</article>';
      }).join('')
      + '</div>';
  }

  function employeeReportForOffer(reports, offerId) {
    return (reports || []).find((report) => String(report.offer_id || '') === String(offerId || '')) || null;
  }

  function employeeDraftRowsForOffer(offerId) {
    const id = String(offerId || '');
    if (!state.employeeReportDraftRows[id]) state.employeeReportDraftRows[id] = [];
    return state.employeeReportDraftRows[id];
  }

  function renderEmployeeReportPanel(offer, report) {
    const offerId = String(offer.id || '');
    const status = String(offer.status || '');
    const rows = employeeDraftRowsForOffer(offerId);
    const busy = state.employeeReportBusyId === offerId;
    if (status !== 'accepted_by_employee') return '';
    if (report) {
      return '<div class="v2-employee-report-state">'
        + '<span>' + escapeHtml(accountableReportStatusLabel(report.status)) + '</span>'
        + '<strong>' + escapeHtml(money(report.total_amount || 0)) + '</strong>'
        + '<small>' + escapeHtml(String(report.row_count || 0) + ' строк') + '</small>'
        + '</div>';
    }
    return '<div class="v2-employee-report" data-v2-employee-report-form="' + escapeHtml(offerId) + '">'
      + '<div class="v2-employee-report-title"><strong>Мой отчет</strong><span>' + escapeHtml(rows.length ? String(rows.length) + ' строк' : 'строк пока нет') + '</span></div>'
      + '<div class="v2-employee-report-fields">'
      + '<label><span>Дата</span><input type="date" data-v2-employee-report-date value="' + escapeHtml(todayIso()) + '"></label>'
      + '<label><span>Сумма</span><input type="number" min="0" step="0.01" inputmode="decimal" data-v2-employee-report-amount placeholder="0.00"></label>'
      + '<label><span>Описание</span><input type="text" data-v2-employee-report-text placeholder="что купил или оплатил"></label>'
      + '<button type="button" data-v2-employee-report-add="' + escapeHtml(offerId) + '">Добавить</button>'
      + '</div>'
      + (rows.length
        ? '<div class="v2-employee-report-rows">' + rows.map((row, index) => (
          '<div class="v2-employee-report-row">'
            + '<span>' + escapeHtml(String(index + 1)) + '</span>'
            + '<strong>' + escapeHtml(row.description) + '</strong>'
            + '<em>' + escapeHtml(money(row.amount)) + '</em>'
            + '<button type="button" aria-label="Убрать строку" data-v2-employee-report-remove="' + escapeHtml(offerId) + '" data-v2-employee-report-index="' + escapeHtml(String(index)) + '">x</button>'
          + '</div>'
        )).join('') + '</div>'
        : '')
      + '<button class="v2-employee-report-submit" type="button" data-v2-employee-report-submit="' + escapeHtml(offerId) + '"' + (busy || !rows.length ? ' disabled' : '') + '>'
      + escapeHtml(busy ? 'Отправляю' : 'Отправить отчет')
      + '</button>'
      + '</div>';
  }

  function renderEmployeeMode() {
    if (!els.employeeScreen) return;
    const mode = state.employeeMode || {};
    const workspace = mode.workspace || currentWorkspace() || {};
    const summary = mode.summary || {};
    const offers = Array.isArray(mode.offers) ? mode.offers : [];
    const reports = Array.isArray(mode.reports) ? mode.reports : [];
    if (els.employeeTitle) els.employeeTitle.textContent = workspace.name || 'Рабочее пространство сотрудника';
    if (els.employeeMeta) {
      els.employeeMeta.textContent = 'Роль: ' + (workspace.role_label || workspaceRoleLabel(workspace.role || 'employee')) + '. Общая бухгалтерия скрыта.';
    }
    if (els.employeeSummary) {
      els.employeeSummary.innerHTML = ''
        + '<article><span>Назначено</span><strong>' + escapeHtml(money(summary.pending_total || 0)) + '</strong></article>'
        + '<article><span>Принято</span><strong>' + escapeHtml(money(summary.accepted_total || 0)) + '</strong></article>'
        + '<article><span>Отчетов</span><strong>' + escapeHtml(String(summary.submitted_reports || 0)) + '</strong></article>';
    }
    if (els.employeeOffers) {
      if (state.employeeModeStatus === 'loading') {
        els.employeeOffers.innerHTML = '<div class="v2-employee-empty">Загружаю назначения...</div>';
        return;
      }
      if (state.employeeModeError) {
        els.employeeOffers.innerHTML = '<div class="v2-employee-empty is-error">' + escapeHtml(state.employeeModeError) + '</div>';
        return;
      }
      if (!offers.length) {
        els.employeeOffers.innerHTML = '<div class="v2-employee-empty">Назначений под отчет пока нет.</div>';
        return;
      }
      els.employeeOffers.innerHTML = offers.map((offer) => {
        const status = String(offer.status || '');
        const pending = status === 'pending_offer';
        const busy = state.employeeOfferBusyId === String(offer.id || '');
        return '<article class="v2-employee-offer" data-v2-employee-offer-id="' + escapeHtml(offer.id || '') + '">'
          + '<div><span>' + escapeHtml(accountableOfferStatusLabel(status)) + '</span><strong>' + escapeHtml(money(offer.amount)) + '</strong></div>'
          + '<p>' + escapeHtml(offer.purpose || 'Назначение без описания') + '</p>'
          + (pending ? '<button type="button" data-v2-employee-offer-accept="' + escapeHtml(offer.id || '') + '"' + (busy ? ' disabled' : '') + '>' + (busy ? 'Принимаю' : 'Принять') + '</button>' : '')
          + renderEmployeeReportPanel(offer, employeeReportForOffer(reports, offer.id))
          + '</article>';
      }).join('');
    }
  }

  function renderInvitePanel() {
    if (!els.invitePanel) return;
    if (!state.inviteToken) {
      els.invitePanel.hidden = true;
      return;
    }
    els.invitePanel.hidden = false;
    const workspace = state.invitePreview && state.invitePreview.workspace ? state.invitePreview.workspace : null;
    const invite = state.invitePreview && state.invitePreview.invite ? state.invitePreview.invite : null;
    if (els.inviteTitle) {
      els.inviteTitle.textContent = workspace ? 'Приглашение в ' + workspace.name : 'Приглашение в рабочее пространство';
    }
    if (els.inviteText) {
      if (invite && invite.invited_email) {
        els.inviteText.textContent = 'Роль: ' + (invite.role_label || 'Сотрудник') + '. Email приглашения: ' + invite.invited_email + '.';
      } else {
        els.inviteText.textContent = 'После принятия пространство появится в холле с ролью сотрудника.';
      }
    }
    if (els.inviteAccept) {
      els.inviteAccept.disabled = state.inviteBusy || (state.invitePreview && state.invitePreview.email_matches === false);
      els.inviteAccept.textContent = state.inviteBusy ? 'Принимаю' : 'Принять';
    }
  }

  function renderFlows() {
    $$('[data-v2-flow]').forEach((button) => {
      const type = button.getAttribute('data-v2-flow');
      const exists = state.flows.some((flow) => flow.type === type);
      button.disabled = !exists;
      button.classList.toggle('is-active', type === state.activeFlowType);
    });
    renderMobileMode();
    if (els.currentMonth) els.currentMonth.hidden = isCurrentPeriod();
    if (els.archiveOpen) els.archiveOpen.disabled = !state.workspaceId;
    if (els.allFeedToggle) {
      const archiveView = reportArchiveViewInfo();
      const active = state.activeScreen === 'operational' && state.feedView === 'all' && !state.reportSelectionMode && !archiveView.active;
      els.allFeedToggle.disabled = !state.workspaceId || state.reportSelectionMode || archiveView.active;
      els.allFeedToggle.classList.toggle('is-active', active);
      els.allFeedToggle.textContent = active ? 'Текущий месяц' : 'Вся лента';
      els.allFeedToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
      els.allFeedToggle.title = active ? 'Вернуться к текущему месяцу' : 'Показать все записи пространства';
      els.allFeedToggle.setAttribute('aria-label', active ? 'Вернуться к текущему месяцу' : 'Показать всю ленту');
    }
    if (els.reportArchiveToggle) {
      const archiveView = reportArchiveViewInfo();
      els.reportArchiveToggle.disabled = !state.workspaceId || state.reportSelectionMode || state.reportArchiveStatus === 'loading';
      els.reportArchiveToggle.classList.toggle('is-active', archiveView.active);
      els.reportArchiveToggle.textContent = archiveView.active ? 'Журнал' : 'Отчеты';
      els.reportArchiveToggle.setAttribute('aria-pressed', archiveView.active ? 'true' : 'false');
      els.reportArchiveToggle.title = archiveView.active ? 'Вернуться в оперативный журнал' : 'Открыть просмотр сохраненных отчетов';
      els.reportArchiveToggle.setAttribute('aria-label', archiveView.active ? 'Вернуться в оперативный журнал' : 'Открыть просмотр сохраненных отчетов');
    }
  }

  function renderSummary() {
    const periodReport = state.monthReport || null;
    const usePeriodFigures = !isCurrentPeriod() && periodReport;
    const monthOpeningCash = periodReport && periodReport.opening_cash !== undefined
      ? periodReport.opening_cash
      : (state.summary && state.summary.opening_cash);
    els.cashNow.textContent = money(usePeriodFigures ? periodReport.ending_cash : (state.summary && state.summary.cash_now));
    els.cardTotal.textContent = money(state.summary && state.summary.card_balance !== undefined ? state.summary.card_balance : 0);
    els.openingCash.textContent = money(monthOpeningCash);
    const otherRows = state.entries.filter(isUserReviewEntry);
    els.otherCount.textContent = String(otherRows.length);
    renderMonthClosure();
    renderInputState();
  }

  function isUserReviewEntry(entry) {
    if (!entry) return false;
    if (entry.status === 'other_review' && entry.category_code === 'other') return true;
    const hasAmount = entry.amount !== null && entry.amount !== undefined && entry.amount !== '';
    return hasAmount
      && !entry.category_code
      && entry.entry_type !== 'correction'
      && entry.accounting_section !== 'lower_accounting'
      && entry.accounting_section !== 'admin_debt'
      && entry.accounting_type !== 'admin_debt';
  }

  function renderPeriodState() {
    const month = selectedMonthParts();
    if (state.activeScreen === 'hall') {
      els.month.textContent = 'Холл';
      if (els.mobileMonth) els.mobileMonth.textContent = 'Холл';
    } else if (state.activeScreen === 'summary') {
      const range = summaryPeriodRange();
      const label = range.isRange
        ? monthKeyLabel(periodKey(range.from)) + ' — ' + monthKeyLabel(periodKey(range.to))
        : monthKeyLabel(periodKey(range.from));
      els.month.textContent = 'Сводка · ' + label;
      if (els.mobileMonth) els.mobileMonth.textContent = label;
    } else {
      els.month.textContent = isCurrentPeriod() ? month.label : 'Архив · ' + month.label;
      if (els.mobileMonth) els.mobileMonth.textContent = month.label;
    }
    if (els.archiveLayer) els.archiveLayer.hidden = !state.archiveOpen;
    if (els.unsavedGuard) els.unsavedGuard.hidden = !state.unsavedGuardOpen;
    if (els.closedEditLayer) els.closedEditLayer.hidden = !state.closedEditOpen;
  }

  function renderInputState() {
    const isEditing = Boolean(editingEntry());
    const isPreviewing = !isEditing && Boolean(previewingEntry());
    els.form.classList.toggle('is-editing', isEditing);
    els.form.classList.toggle('is-previewing', isPreviewing);
    if (els.editActions) els.editActions.hidden = !(isEditing || isPreviewing);
    els.submit.textContent = isEditing ? 'Обновить' : 'Сохранить';
    els.submit.setAttribute('aria-label', isEditing ? 'Обновить запись' : 'Сохранить запись');
    els.submit.disabled = state.saving || state.editBusy;
    els.submit.hidden = isPreviewing;
    els.previewButton.hidden = isPreviewing;
    els.rawText.readOnly = isPreviewing;
    els.date.readOnly = isPreviewing;
    if (els.editSave) {
      els.editSave.disabled = state.editBusy;
      els.editSave.textContent = isPreviewing ? 'Править' : 'Готово';
      els.editSave.setAttribute('aria-label', isPreviewing ? 'Редактировать запись' : 'Сохранить изменения записи');
      els.editSave.title = isPreviewing ? 'Редактировать запись' : 'Сохранить изменения';
    }
    if (els.editDelete) {
      const confirmingDelete = isEditing && state.deleteConfirmEntryId === state.editingEntryId;
      els.editDelete.hidden = isPreviewing;
      els.editDelete.disabled = state.editBusy;
      els.editDelete.textContent = confirmingDelete ? 'Удалить?' : 'Удал.';
      els.editDelete.classList.toggle('is-confirming', confirmingDelete);
      els.editDelete.setAttribute('aria-label', confirmingDelete ? 'Подтвердить удаление записи' : 'Удалить запись');
      els.editDelete.title = confirmingDelete ? 'Нажмите еще раз, чтобы удалить запись' : 'Удалить запись';
    }
  }

  function renderMonthClosure() {
    const isClosed = isCurrentMonthClosed();
    els.monthState.textContent = state.monthActionBusy ? 'В работе' : (isClosed ? 'Закрыт' : 'Открыт');
    els.monthToggle.disabled = state.monthActionBusy || !state.workspaceId;
    els.monthToggle.setAttribute('aria-label', isClosed ? 'Открыть выбранный месяц' : 'Закрыть выбранный месяц');
    els.monthToggle.title = isClosed ? 'Открыть выбранный месяц' : 'Закрыть выбранный месяц';
    els.monthToggle.classList.toggle('is-closed', isClosed);
  }

  function layer1Report() {
    return state.layer1Summary || null;
  }

  function sourceTraceReport() {
    return state.reportFragmentOpen && state.reportFragmentPreview ? state.reportFragmentPreview : layer1Report();
  }

  function sourceTraceFor(key, row) {
    const report = sourceTraceReport();
    if (!row && state.sourceTraceByKey[key]) return state.sourceTraceByKey[key];
    const traces = report && report.source_trace;
    const categoryKey = key && key.indexOf('category:') === 0 ? key.slice('category:'.length) : '';
    const totalTrace = traces && traces.totals && traces.totals[key];
    const basisTrace = traces && traces.basis && traces.basis[key];
    if (!row && key === 'total_cash_income' && traces && traces.totals && !totalTrace) {
      const sourceIds = traceEntryIds(traces.totals.cash_income).concat(traceEntryIds(traces.totals.commercial_income));
      if (!sourceIds.length) return null;
      return {
        source_entry_ids: sourceIds,
        basis: [],
      };
    }
    if (!row && (totalTrace || basisTrace)) {
      return {
        source_entry_ids: Array.isArray(totalTrace) ? totalTrace : traceEntryIds(totalTrace),
        basis: Array.isArray(basisTrace) ? basisTrace : (basisTrace ? [basisTrace] : []),
      };
    }
    const candidates = [
      row && row.source_trace,
      row && row.source_entry_ids,
      traces && traces[key],
      totalTrace,
      categoryKey && traces && traces.categories && traces.categories[categoryKey],
      traces && traces.fields && traces.fields[key],
      report && report.totals && report.totals[key] && report.totals[key].source_trace,
      report && report[key] && report[key].source_trace
    ];
    return candidates.find((candidate) => {
      if (!candidate) return false;
      if (Array.isArray(candidate)) return candidate.length > 0;
      if (typeof candidate === 'object') return Object.keys(candidate).length > 0;
      return true;
    }) || null;
  }

  function traceEntryIds(trace) {
    if (Array.isArray(trace)) {
      return trace.map((item) => {
        if (item && typeof item === 'object' && item.id !== undefined) return String(item.id);
        return String(item);
      }).filter(Boolean);
    }
    if (!trace || typeof trace !== 'object') return [];
    const ids = trace.entry_ids || trace.source_entry_ids || trace.operational_entry_ids || trace.entries_ids || [];
    return Array.isArray(ids) ? ids.map((id) => String(id)) : [];
  }

  function traceBasisRows(trace) {
    if (!trace || typeof trace !== 'object' || Array.isArray(trace)) return [];
    const basis = trace.basis || trace.basis_rows || trace.non_entry_basis || [];
    return Array.isArray(basis) ? basis.map((row) => Object.assign({ is_basis: true }, row || {})) : [];
  }

  function traceEntries(trace) {
    if (!trace) return [];
    if (Array.isArray(trace)) {
      return entriesForTraceIds(traceEntryIds(trace), []);
    }
    if (typeof trace !== 'object') return [];
    const direct = trace.entries || trace.source_entries || trace.operational_entries || trace.records || [];
    const entries = Array.isArray(direct) ? direct.slice() : [];
    return traceBasisRows(trace).concat(entriesForTraceIds(traceEntryIds(trace), entries));
  }

  function entriesForTraceIds(ids, directEntries) {
    const direct = Array.isArray(directEntries) ? directEntries.slice() : [];
    const byId = new Map();
    direct.forEach((entry) => {
      if (entry && entry.id) byId.set(String(entry.id), entry);
    });
    state.entries.forEach((entry) => {
      if (entry && entry.id && !byId.has(String(entry.id))) byId.set(String(entry.id), entry);
    });
    Object.values(state.sourceEntryCache || {}).forEach((entry) => {
      if (entry && entry.id && !byId.has(String(entry.id))) byId.set(String(entry.id), entry);
    });

    const ordered = [];
    const used = new Set();
    ids.forEach((id) => {
      const key = String(id);
      const entry = byId.get(key);
      if (entry) {
        ordered.push(entry);
        used.add(key);
      }
    });
    direct.forEach((entry) => {
      const key = entry && entry.id ? String(entry.id) : '';
      if (key && used.has(key)) return;
      ordered.push(entry);
    });

    return ordered;
  }

  function totalSourceControl(key, label, value, row, formatter) {
    const trace = sourceTraceFor(key, row);
    const shown = formatter ? formatter(value) : money(value);
    if (trace) {
      state.sourceTraceByKey[key] = trace;
      return '<button class="v2-summary-total-action" type="button" data-v2-source-total="' + escapeHtml(key) + '" data-v2-source-label="' + escapeHtml(label) + '">'
        + '<span><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(shown) + '</strong></span>'
        + '<span class="v2-summary-trace-pill">Записи</span>'
        + '</button>';
    }
    return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(shown) + '</strong></div>';
  }

  function plainSummaryTotal(label, value, formatter) {
    const shown = formatter ? formatter(value) : money(value);
    return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(shown) + '</strong></div>';
  }

  function renderSummaryTotals(keys, report) {
    return '<div class="v2-summary-total-grid">'
      + keys.map((item) => {
        const value = firstValue(report, item.keys, null);
        const formatter = item.kind === 'count' ? displayNumber : null;
        return '<div class="v2-summary-total-item">' + totalSourceControl(item.key, item.label, value, null, formatter) + '</div>';
      }).join('')
      + '</div>';
  }

  function reportPeriodLabel(report) {
    const month = selectedMonthParts();
    return text(firstValue(report, ['header.period.month_key', 'period.month_key', 'period_label', 'month_key'], month.label));
  }

  function monthKeyLabel(value) {
    const parts = monthPartsFromKey(value);
    if (!parts) return text(value);
    return String(parts.month).padStart(2, '0') + '.' + String(parts.year);
  }

  function reportPeriodTitle(report) {
    const from = text(firstValue(report, ['header.period.from_month_key'], ''));
    const to = text(firstValue(report, ['header.period.to_month_key'], from));
    if (from && to && from !== '—' && to !== '—' && from !== to) {
      return monthKeyLabel(from) + ' — ' + monthKeyLabel(to);
    }
    const key = text(firstValue(report, ['header.period.month_key', 'period.month_key', 'period_label', 'month_key'], ''));
    return key && key !== '—' ? monthKeyLabel(key) : selectedMonthParts().label;
  }

  function summaryTotal(report, keys, fallback) {
    return firstValue(report, keys, fallback === undefined ? null : fallback);
  }

  function renderSummaryPeriodFilter(report) {
    const range = summaryPeriodRange();
    const start = text(firstValue(report, ['header.period.from_month_key'], periodKey(range.from)));
    const end = text(firstValue(report, ['header.period.to_month_key'], periodKey(range.to)));
    return '<form class="v2-summary-period-form" data-v2-summary-period-form>'
      + '<label><span>с</span><input type="month" data-v2-summary-period-from value="' + escapeHtml(start) + '"></label>'
      + '<label><span>по</span><input type="month" data-v2-summary-period-to value="' + escapeHtml(end) + '"></label>'
      + '<button type="submit">Показать</button>'
      + '</form>';
  }

  function renderSummaryHero(report) {
    const workspace = currentWorkspace();
    const counts = report.counts || {};
    const entriesCount = firstValue(report, ['header.entries_count', 'entries_count'], counts.entries);
    const reviewCount = firstValue(report, ['header.review_count', 'review_count'], counts.other_review || counts.review || 0);
    const status = firstValue(report, ['header.status', 'status'], report.is_closed === true ? 'closed' : 'open');
    const endingCash = summaryTotal(report, ['totals.ending_cash', 'ending_cash'], 0);
    const statusLabel = text(status) === 'closed' ? 'Закрыт' : 'Открыт';
    return '<section class="v2-summary-hero">'
      + '<div class="v2-summary-title">'
      + '<span>' + escapeHtml(firstValue(report, ['header.workspace.name'], workspace && workspace.name || 'Пространство')) + '</span>'
      + '<h2>' + escapeHtml(reportPeriodTitle(report)) + '</h2>'
      + '<p>' + escapeHtml(statusLabel + ' · ' + displayNumber(entriesCount) + ' ' + recordWord(entriesCount) + ' · проверка ' + displayNumber(reviewCount)) + '</p>'
      + '</div>'
      + '<div class="v2-summary-period-box">' + renderSummaryPeriodFilter(report) + '</div>'
      + '<div class="v2-summary-balance">'
      + plainSummaryTotal('Касса администратора', endingCash)
      + '</div>'
      + '</section>';
  }

  function renderReportFragmentHero(report) {
    const workspace = currentWorkspace();
    const header = report.header || {};
    const entriesCount = firstValue(report, ['header.entries_count', 'entries_count'], reportSelectionIds().length);
    const reviewCount = firstValue(report, ['header.review_count', 'review_count'], 0);
    const lockedCount = firstValue(report, ['header.locked_count'], 0);
    const endingCash = summaryTotal(report, ['totals.ending_cash', 'ending_cash'], null);
    const startDate = text(firstValue(report, ['header.start_date'], ''), '');
    const endDate = text(firstValue(report, ['header.end_date'], startDate), startDate);
    const rangeLabel = startDate && endDate && startDate !== endDate ? startDate + ' - ' + endDate : (startDate || text(header.range_label));
    return '<section class="v2-report-fragment-hero">'
      + '<div class="v2-summary-title">'
      + '<span>' + escapeHtml(workspace && workspace.name || 'Пространство') + '</span>'
      + '<h2>' + escapeHtml(rangeLabel || 'Выбранные строки') + '</h2>'
      + '<p>' + escapeHtml(displayNumber(entriesCount) + ' ' + recordWord(entriesCount) + ' · проверка ' + displayNumber(reviewCount) + (lockedCount ? ' · уже в отчете ' + displayNumber(lockedCount) : '')) + '</p>'
      + '</div>'
      + '<div class="v2-summary-balance">'
      + plainSummaryTotal('Касса администратора', endingCash)
      + '</div>'
      + '</section>';
  }

  function reportMoneyPosition(report) {
    const position = firstValue(report, ['money_position', 'blocks.money_position'], {}) || {};
    const adminCash = firstValue(position, ['admin_cash'], summaryTotal(report, ['totals.ending_cash', 'ending_cash'], null));
    const employeeHeld = firstValue(position, ['employee_held_cash'], 0);
    const physicalTotal = firstValue(position, ['physical_available_total'], adminCash === null || adminCash === undefined || adminCash === '' ? null : numericValue(adminCash, 0) + numericValue(employeeHeld, 0));
    return {
      physical_available_total: physicalTotal,
      admin_cash: adminCash,
      employee_held_cash: employeeHeld,
      return_due_from_employees: firstValue(position, ['return_due_from_employees'], 0),
      reimburse_due_to_employees: firstValue(position, ['reimburse_due_to_employees'], 0),
      submitted_employee_reports_total: firstValue(position, ['submitted_employee_reports_total'], 0),
      pending_employee_offer_total: firstValue(position, ['pending_employee_offer_total'], 0)
    };
  }

  function renderMoneyPosition(report) {
    const position = reportMoneyPosition(report);
    const hasEmployeeContext = Math.abs(numericValue(position.employee_held_cash, 0)) > 0.005
      || Math.abs(numericValue(position.reimburse_due_to_employees, 0)) > 0.005
      || Math.abs(numericValue(position.return_due_from_employees, 0)) > 0.005
      || Math.abs(numericValue(position.submitted_employee_reports_total, 0)) > 0.005
      || Math.abs(numericValue(position.pending_employee_offer_total, 0)) > 0.005;
    return '<section class="v2-summary-block v2-money-position-block">'
      + '<div class="v2-summary-block-head"><h3>Деньги на дату отчета</h3><span>общий физический пул</span></div>'
      + '<div class="v2-summary-metric-grid">'
      + '<div class="v2-summary-metric is-strong">' + plainSummaryTotal('Всего физически доступно', position.physical_available_total) + '</div>'
      + '<div class="v2-summary-metric">' + plainSummaryTotal('У администратора', position.admin_cash) + '</div>'
      + '<div class="v2-summary-metric">' + plainSummaryTotal('У сотрудников', position.employee_held_cash) + '</div>'
      + '<div class="v2-summary-metric' + (numericValue(position.reimburse_due_to_employees, 0) > 0 ? ' is-warning' : '') + '">' + plainSummaryTotal('К возмещению', position.reimburse_due_to_employees) + '</div>'
      + '</div>'
      + (hasEmployeeContext
        ? '<p class="v2-money-position-note">Сотрудники входят в общий физический пул только по деньгам, которые еще у них на руках. Перерасход показывается отдельно как сумма к возмещению.</p>'
        : '')
	      + '</section>';
  }

  function currentReportFragmentEntries() {
    const fragment = state.reportFragmentCreated || {};
    const entries = fragment.entry_snapshot || fragment.entries || [];
    return Array.isArray(entries) ? entries : [];
  }

  function reportFragmentEntryById(id) {
    const target = String(id || '');
    if (!target) return null;
    return currentReportFragmentEntries().find((entry) => String(entry.id || '') === target) || null;
  }

  function currentReportArchiveNeighbors() {
    const fragment = state.reportFragmentCreated || {};
    const currentId = String(fragment.id || '');
    if (!currentId || !state.reportArchiveFragments.length) return { previous: null, next: null };
    const fragments = sortReportArchiveFragments(state.reportArchiveFragments);
    const index = fragments.findIndex((item) => String(item.id || '') === currentId);
    return {
      previous: index > 0 ? fragments[index - 1] : null,
      next: index >= 0 && index < fragments.length - 1 ? fragments[index + 1] : null
    };
  }

  function reportBalanceFromFragment(fragment, key) {
    const summary = reportSummaryFromFragment(fragment) || {};
    return firstValue(summary, ['totals.' + key, key], null);
  }

  function renderCashChainThread(report) {
    const totals = report && report.totals ? report.totals : {};
    const trace = report && report.source_trace ? report.source_trace : {};
    const basis = firstValue(trace, ['basis.opening_cash'], null);
    const correctionIds = traceEntryIds(firstValue(trace, ['totals.corrections_total'], []));
    const correctionRows = correctionIds
      .map((id) => reportFragmentEntryById(id))
      .filter(Boolean);
    const correctionsTotal = numericValue(firstValue(totals, ['corrections_total'], 0), 0);
    const neighbors = currentReportArchiveNeighbors();
    const previousEnding = neighbors.previous ? reportBalanceFromFragment(neighbors.previous, 'ending_cash') : null;
    const nextOpening = neighbors.next ? reportBalanceFromFragment(neighbors.next, 'opening_cash') : null;
    const parts = [];
    if (neighbors.previous) {
      parts.push('<div class="v2-cash-thread-row"><span>Предыдущий отчет</span><strong>' + escapeHtml(neighbors.previous.title || 'Отчет') + '</strong><em>финал ' + escapeHtml(money(previousEnding)) + '</em></div>');
    }
    parts.push('<div class="v2-cash-thread-row"><span>Входящий этой смычки</span><strong>' + escapeHtml(money(firstValue(totals, ['opening_cash'], basis && basis.total))) + '</strong><em>' + escapeHtml(basis && basis.label ? 'база остатка' : 'начало периода') + '</em></div>');
    correctionRows.forEach((entry) => {
      const signed = (entry.sign || '') + money(entry.amount).replace(/^[-+]\s*/, '');
      parts.push('<div class="v2-cash-thread-row is-correction"><span>' + escapeHtml(formatReportDate(entry.date)) + '</span><strong>' + escapeHtml(entry.raw_text || 'Корректировка') + '</strong><em>' + escapeHtml(signed) + '</em></div>');
    });
    if (!correctionRows.length && Math.abs(correctionsTotal) > 0.005) {
      parts.push('<div class="v2-cash-thread-row is-correction"><span>Корректировка</span><strong>Стыковка остатка внутри периода</strong><em>' + escapeHtml(money(correctionsTotal)) + '</em></div>');
    }
    parts.push('<div class="v2-cash-thread-row"><span>Финал этой смычки</span><strong>' + escapeHtml(money(firstValue(totals, ['ending_cash'], null))) + '</strong><em>остаток отчета</em></div>');
    if (neighbors.next) {
      parts.push('<div class="v2-cash-thread-row"><span>Следующий отчет</span><strong>' + escapeHtml(neighbors.next.title || 'Отчет') + '</strong><em>входящий ' + escapeHtml(money(nextOpening)) + '</em></div>');
    }
    if (!parts.length) return '';
    return '<section class="v2-summary-block v2-cash-thread-block">'
      + '<div class="v2-summary-block-head"><h3>Нить остатка</h3><span>как отчет связан с соседними периодами</span></div>'
      + '<div class="v2-cash-thread-list">' + parts.join('') + '</div>'
      + (Math.abs(correctionsTotal) > 0.005 ? '<p class="v2-cash-thread-note">Корректировка не является новым доходом. Это служебная стыковка, чтобы историческая лента сошлась с фактическим остатком следующего периода.</p>' : '')
      + '</section>';
  }

  function renderReportFragmentSummary(report) {
    state.sourceTraceByKey = {};
    const categoryRows = firstValue(report, ['blocks.categories.rows', 'category_totals', 'category_rows', 'categories'], []);
    return '<div class="v2-summary-dashboard">'
      + renderReportFragmentHero(report)
      + renderMoneyPosition(report)
      + renderCashFlowLine(report)
      + renderCashChainThread(report)
      + renderSummaryAttention(report)
      + renderAdminDebt(report)
      + renderLowerAccounting(report)
      + '<section class="v2-summary-block"><div class="v2-summary-block-head"><h3>Категории</h3><span>Кеш / карта / итого</span></div>' + renderCategoryRows(categoryRows) + '</section>'
      + renderOtherReview(report)
      + '</div>';
  }

  function renderSummaryMetricGrid(items, report) {
    return '<div class="v2-summary-metric-grid">'
      + items.map((item) => {
        const value = item.value !== undefined ? item.value : summaryTotal(report, item.keys, item.fallback);
        const formatter = item.kind === 'count' ? displayNumber : null;
        const tone = item.tone ? ' is-' + item.tone : '';
        const isZeroMoney = item.kind !== 'count' && Math.abs(numericValue(value, 0)) < 0.005;
        return '<div class="v2-summary-metric' + tone + '">'
          + (item.plain || isZeroMoney ? plainSummaryTotal(item.label, value, formatter) : totalSourceControl(item.key, item.label, value, null, formatter))
          + '</div>';
      }).join('')
      + '</div>';
  }

  function renderCashFlowLine(report) {
    const otherIncome = summaryTotal(report, ['totals.cash_income', 'cash_income', 'external_cash_income'], 0);
    const commercialIncome = summaryTotal(report, ['totals.commercial_income', 'commercial_income'], 0);
    const totalIncome = summaryTotal(
      report,
      ['totals.total_cash_income', 'total_cash_income'],
      numericValue(otherIncome, 0) + numericValue(commercialIncome, 0)
    );
    const items = [
      { key: 'opening_cash', label: 'Входящий', keys: ['totals.opening_cash', 'opening_cash'], plain: true },
      { key: 'total_cash_income', label: 'Поступления всего', keys: ['totals.total_cash_income', 'total_cash_income'], value: totalIncome, tone: 'positive' },
      { key: 'commercial_income', label: 'Коммерческие', keys: ['totals.commercial_income', 'commercial_income'], tone: 'positive' },
      { key: 'cash_income', label: 'Прочие поступления', keys: ['totals.cash_income', 'cash_income', 'external_cash_income'], tone: 'positive' },
      { key: 'cash_expense', label: 'Расход', keys: ['totals.cash_expense', 'cash_expense'], tone: 'negative' },
      { key: 'corrections_total', label: 'Корректировки', keys: ['totals.corrections_total', 'corrections_total', 'correction_total'] },
      { key: 'ending_cash', label: 'Остаток', keys: ['totals.ending_cash', 'ending_cash'], plain: true }
    ];
    return '<section class="v2-summary-block v2-summary-flow-block">'
      + '<div class="v2-summary-block-head"><h3>Наличные</h3><span>Цепочка периода</span></div>'
      + renderSummaryMetricGrid(items, report)
      + '</section>';
  }

  function renderSummaryAttention(report) {
    const items = [
      { key: 'card_expense', label: 'Карта', keys: ['totals.card_expense', 'blocks.card.card_expense', 'card_expense', 'card_expense_total'] },
      { key: 'other_review_total', label: 'На проверке', keys: ['totals.other_review_total', 'other_review_total', 'other_expenses'], tone: numericValue(summaryTotal(report, ['totals.other_review_total'], 0)) > 0 ? 'warning' : '' },
      { key: 'lower_accounting_total', label: 'Деньги под отчет', keys: ['totals.lower_accounting_total', 'lower_accounting_total'], tone: numericValue(summaryTotal(report, ['totals.lower_accounting_total'], 0)) > 0 ? 'warning' : '' },
      { key: 'admin_debt_total', label: 'Долг администратора', keys: ['totals.admin_debt_total', 'blocks.admin_debt.total'], tone: numericValue(summaryTotal(report, ['totals.admin_debt_total'], 0)) > 0 ? 'warning' : '' }
    ];
    return '<section class="v2-summary-block">'
      + '<div class="v2-summary-block-head"><h3>Контроль</h3><span>Карта / проверка / долги</span></div>'
      + renderSummaryMetricGrid(items, report)
      + '</section>';
  }

  function renderCategoryRows(rows) {
    if (!Array.isArray(rows) || !rows.length) {
      return '<div class="v2-summary-state">За период категорийных расходов нет.</div>';
    }
    const displayRows = rows
      .filter((row) => Math.abs(numericValue(row.total === undefined ? row.amount : row.total, 0)) > 0.005 || numericValue(firstValue(row, ['entry_count', 'entries_count', 'count'], 0), 0) > 0)
      .sort((a, b) => Math.abs(numericValue(b.total === undefined ? b.amount : b.total, 0)) - Math.abs(numericValue(a.total === undefined ? a.amount : a.total, 0)));
    if (!displayRows.length) {
      return '<div class="v2-summary-state">За период категорийных расходов нет.</div>';
    }
    return '<div class="v2-summary-category-table">'
      + '<div class="v2-summary-category-row is-header"><span>Категория</span><span>Кеш</span><span>Карта</span><span>Итого</span><span>Записи</span><span>Кол-во</span><span>Проверка</span></div>'
      + displayRows.map((row) => {
        const code = row.category_code || row.category || row.name || '';
        const label = categoryNameByCode(code);
        const totalKey = 'category:' + (code || label);
        const trace = sourceTraceFor(totalKey, row);
        if (trace) state.sourceTraceByKey[totalKey] = trace;
        const total = row.total === undefined ? row.amount : row.total;
        const sourceCell = trace
          ? '<button class="v2-summary-source-link" type="button" data-v2-source-total="' + escapeHtml(totalKey) + '" data-v2-source-label="' + escapeHtml('Категория ' + label) + '">Записи</button>'
          : '<span class="v2-summary-source-empty">-</span>';
        return '<div class="v2-summary-category-row">'
          + '<span>' + escapeHtml(text(label)) + '</span>'
          + '<span>' + escapeHtml(money(firstValue(row, ['cash', 'cash_total'], null))) + '</span>'
          + '<span>' + escapeHtml(money(firstValue(row, ['card', 'card_total'], null))) + '</span>'
          + '<span><strong>' + escapeHtml(money(total)) + '</strong></span>'
          + '<span>' + sourceCell + '</span>'
          + '<span>' + escapeHtml(displayNumber(firstValue(row, ['entry_count', 'entries_count', 'count'], null))) + '</span>'
          + '<span>' + escapeHtml(displayNumber(firstValue(row, ['review', 'review_count'], null))) + '</span>'
          + '</div>';
      }).join('')
      + '</div>';
  }

  function renderOtherReview(report) {
    const review = firstValue(report, ['blocks.other_review', 'other_review'], {}) || {};
    const entries = Array.isArray(review.entries)
      ? review.entries
      : (Array.isArray(report.other_review_entries) ? report.other_review_entries : []);
    const count = firstValue(review, ['count'], firstValue(report, ['header.review_count', 'review_count', 'other_review_count'], entries.length));
    const total = firstValue(review, ['total', 'amount'], firstValue(report, ['totals.other_review_total', 'other_review_total', 'other_expenses'], null));
    if (!entries.length && (!count || count === '0') && (total === null || total === undefined || total === '')) return '';
    return '<section class="v2-summary-block">'
      + '<div class="v2-summary-block-head"><h3>Прочее / проверка</h3><span>' + escapeHtml(displayNumber(count)) + ' ' + escapeHtml(recordWord(count)) + '</span></div>'
      + '<div class="v2-summary-total-grid"><div class="v2-summary-total-item">' + totalSourceControl('other_review_total', 'Итого на проверке', total) + '</div></div>'
      + (entries.length ? '<div class="v2-summary-source-list">' + entries.map((entry, index) => sourceRowHtml(entry, index)).join('') + '</div>' : '')
      + '</section>';
  }

  function renderLowerAccounting(report) {
    const block = firstValue(report, ['blocks.lower_accounting', 'lower_accounting'], {}) || {};
    const entries = Array.isArray(block.entries) ? block.entries : [];
    const settlements = block.settlements || {};
    const byCounterparty = Array.isArray(settlements.by_counterparty) ? settlements.by_counterparty : [];
    const count = firstValue(block, ['count'], entries.length);
    const total = firstValue(block, ['total', 'amount'], firstValue(report, ['totals.lower_accounting_total'], null));
    const visibleCounterparties = visibleLowerAccountingRows(byCounterparty);
    const numericTotal = Number(total || 0);
    if (!visibleCounterparties.length && Math.abs(numericTotal) < 0.01) return '';
    if (!entries.length && (!count || count === '0') && (total === null || total === undefined || total === '')) return '';
    return '<section class="v2-summary-block" data-v2-lower-accounting>'
      + '<div class="v2-summary-block-head"><h3>Деньги под отчет</h3><span data-v2-lower-accounting-count>' + escapeHtml(displayNumber(count)) + ' ' + escapeHtml(recordWord(count)) + '</span></div>'
      + '<div class="v2-summary-total-grid"><div class="v2-summary-total-item">' + totalSourceControl('lower_accounting_total', 'Открытая сумма', total) + '</div></div>'
      + (visibleCounterparties.length ? renderLowerAccountingSettlements(visibleCounterparties) : '')
      + '</section>';
  }

  function adminDebtHintText(block, total) {
    const lines = ['Сводка задолженности администратора'];
    const basis = block && typeof block.basis_breakdown === 'object' && block.basis_breakdown ? block.basis_breakdown : null;
    if (basis) {
      const creditTotal = numericValue(basis.credit_total, 0);
      const creditCount = basis.credit_count === null || basis.credit_count === undefined ? null : Number(basis.credit_count);
      const creditUnit = basis.credit_unit === null || basis.credit_unit === undefined ? null : numericValue(basis.credit_unit, 0);
      if (creditTotal > 0.005 && Number.isFinite(creditCount) && creditUnit > 0.005) {
        lines.push('Основание: ' + displayNumber(creditCount) + ' выдач по ' + money(creditUnit) + ' = ' + money(creditTotal));
      } else if (creditTotal > 0.005) {
        lines.push('Основание: ' + money(creditTotal));
      }

      const returnedTotal = numericValue(basis.returned_total, 0);
      const returnCount = basis.return_count === null || basis.return_count === undefined ? null : Number(basis.return_count);
      const returnUnit = basis.return_unit === null || basis.return_unit === undefined ? null : numericValue(basis.return_unit, 0);
      if (returnedTotal > 0.005 && Number.isFinite(returnCount) && returnUnit > 0.005) {
        lines.push('Возвращено до базы: ' + displayNumber(returnCount) + ' возвратов по ' + money(returnUnit) + ' = ' + money(returnedTotal));
      } else if (returnedTotal > 0.005) {
        lines.push('Возвращено до базы: ' + money(returnedTotal));
      }

      if (basis.remaining_total !== null && basis.remaining_total !== undefined) {
        lines.push('Базовый остаток: ' + money(basis.remaining_total));
      }
    }
    lines.push('На начало периода: ' + money(block.opening_total));
    lines.push('Увеличение периода: ' + money(block.increased_total));
    lines.push('Возврат периода: ' + money(block.returned_total));
    lines.push('Остаток: ' + money(total));

    return lines.join('\n');
  }

  function renderAdminDebt(report) {
    const block = firstValue(report, ['blocks.admin_debt', 'admin_debt'], {}) || {};
    const entries = Array.isArray(block.entries) ? block.entries : [];
    const total = firstValue(block, ['total', 'amount'], firstValue(report, ['totals.admin_debt_total'], null));
    const numericTotal = Number(total || 0);
    if (!entries.length && Math.abs(numericTotal) < 0.01) return '';
    const trace = Array.isArray(block.source_entry_ids) && block.source_entry_ids.length ? { source_entry_ids: block.source_entry_ids } : null;
    if (trace) state.sourceTraceByKey.admin_debt_total = trace;
    const headNote = entries.length ? displayNumber(entries.length) + ' ' + recordWord(entries.length) : 'переходящий остаток';
    const adminDebtHint = adminDebtHintText(block, numericTotal);
    const rows = entries.length ? entries.map((entry) => {
      const rawEffect = entry.admin_debt_effect === undefined
        ? (entry.direction === 'in' ? 0 - numericValue(entry.amount, 0) : numericValue(entry.amount, 0))
        : numericValue(entry.admin_debt_effect, 0);
      const isReturn = rawEffect < 0;
      return '<div class="v2-summary-category-row">'
        + '<span>' + escapeHtml(text(entry.date || '—')) + '</span>'
        + '<span>' + escapeHtml(text(entry.raw_text || entry.title || 'Основание')) + '</span>'
        + '<span>' + escapeHtml(money(Math.abs(rawEffect))) + '</span>'
        + '<span>' + escapeHtml(isReturn ? 'уменьшает' : 'увеличивает') + '</span>'
        + '</div>';
    }).join('') : '<div class="v2-summary-category-row"><span>—</span><span>Переходящий остаток личного долга администратора</span><span>' + escapeHtml(money(numericTotal)) + '</span><span>остаток</span></div>';
    return '<section class="v2-summary-block" data-v2-admin-debt>'
      + '<div class="v2-summary-block-head"><h3>Задолженность администратора <span class="v2-help-dot" tabindex="0" title="' + escapeHtml(adminDebtHint) + '" data-tooltip="' + escapeHtml(adminDebtHint) + '" aria-label="' + escapeHtml(adminDebtHint) + '">?</span></h3><span>' + escapeHtml(headNote) + '</span></div>'
      + '<div class="v2-summary-total-grid"><div class="v2-summary-total-item">'
      + (trace ? totalSourceControl('admin_debt_total', 'Остаток долга', total) : plainSummaryTotal('Остаток долга', total))
      + '</div><div class="v2-summary-total-item">' + plainSummaryTotal('Начало периода', block.opening_total) + '</div>'
      + '<div class="v2-summary-total-item">' + plainSummaryTotal('Новые личные траты', block.increased_total) + '</div>'
      + '<div class="v2-summary-total-item">' + plainSummaryTotal('Возвраты', block.returned_total) + '</div></div>'
      + '<div class="v2-summary-category-table v2-admin-debt-table">'
      + '<div class="v2-summary-category-row is-header"><span>Дата</span><span>Основание</span><span>Сумма</span><span>Влияние</span></div>'
      + rows
      + '</div>'
      + '</section>';
  }

  function visibleLowerAccountingRows(rows) {
    if (!Array.isArray(rows)) return [];
    return rows.filter((row) => {
      const status = text(row.status);
      const openAmount = Number(row.open_amount === undefined ? row.net_open : row.open_amount);
      return !(status === 'closed_archive_exception' && Math.abs(openAmount || 0) < 0.01);
    });
  }

  function renderLowerAccountingSettlements(rows) {
    return '<div class="v2-summary-category-table v2-settlement-table" data-v2-lower-settlements data-v2-settlement-workflow>'
      + '<div class="v2-summary-category-row is-header"><span>Участник</span><span>Выдано</span><span>Возвращено</span><span>Открыто</span><span>Статус</span><span>Строки</span></div>'
      + rows.map((row) => {
        const key = 'settlement:' + (row.counterparty || '');
        const trace = Array.isArray(row.source_entry_ids) && row.source_entry_ids.length ? { source_entry_ids: row.source_entry_ids } : null;
        if (trace) state.sourceTraceByKey[key] = trace;
        const openValue = row.open_amount === undefined ? row.net_open : row.open_amount;
        const status = text(row.status);
        const participant = lowerAccountingParticipantLabel(row.counterparty);
        const openCell = trace
          ? '<button class="v2-summary-total-action" type="button" data-v2-source-total="' + escapeHtml(key) + '" data-v2-source-label="' + escapeHtml('Расчет: ' + participant) + '" data-v2-settlement-source><strong>' + escapeHtml(money(openValue)) + '</strong><span class="v2-summary-trace-pill">Записи</span></button>'
          : '<span><strong>' + escapeHtml(money(openValue)) + '</strong></span>';
        return '<div class="v2-summary-category-row" data-v2-lower-settlement-row data-v2-settlement-status="' + escapeHtml(status) + '">'
          + '<span>' + escapeHtml(participant) + '</span>'
          + '<span>' + escapeHtml(money(row.issued_total)) + '</span>'
          + '<span>' + escapeHtml(money(row.returned_total)) + '</span>'
          + '<span>' + openCell + '</span>'
          + '<span>' + escapeHtml(valueLabel(status)) + '</span>'
          + '<span>' + escapeHtml(displayNumber(row.entry_count)) + '</span>'
          + '</div>';
      }).join('')
      + '</div>';
  }

  function sourceRowHtml(entry, index) {
    const matched = entry && entry.id ? state.entries.find((item) => String(item.id) === String(entry.id)) : null;
    const row = matched || entry || {};
    if (row.is_basis || row.type === 'flow_opening_balance') {
      const amount = row.flow_opening_balance === undefined ? row.amount : row.flow_opening_balance;
      return '<div class="v2-source-row is-basis">'
        + '<span class="v2-row-number">' + escapeHtml(String(index + 1)) + '</span>'
        + '<strong>' + escapeHtml(valueLabel(row.label || row.type || 'Основа')) + '</strong>'
        + '<span>' + escapeHtml(amount === undefined || amount === null ? text(row.note || row.flow_name) : money(amount)) + '</span>'
        + '</div>';
    }
    return '<div class="v2-source-row">'
      + '<span class="v2-row-number">' + escapeHtml(String(index + 1)) + '</span>'
      + '<strong>' + escapeHtml(text(row.raw_text || row.description || row.id)) + '</strong>'
      + '<span>' + escapeHtml(row.amount === undefined || row.amount === null ? text(row.date) : money(row.amount)) + '</span>'
      + '</div>';
  }

  function sourceTraceCardHtml(entry, index) {
    const matched = entry && entry.id ? state.entries.find((item) => String(item.id) === String(entry.id)) : null;
    const row = matched || entry || {};
    if (row.is_basis || row.type === 'flow_opening_balance') {
      return sourceRowHtml(row, index);
    }
    const entryId = row.id ? String(row.id) : '';
    const currentCategory = entryId && Object.prototype.hasOwnProperty.call(state.sourceCategoryDrafts, entryId)
      ? state.sourceCategoryDrafts[entryId]
      : (row.category_code || '');
    const isSaving = state.sourceCategorySavingAll || (entryId && state.sourceCategorySavingEntryId === entryId);
    const isLowerAccounting = row.accounting_section === 'lower_accounting';
    const isAdminDebt = row.accounting_section === 'admin_debt' || row.accounting_type === 'admin_debt';
    const categoryNow = isAdminDebt ? 'Категория не нужна' : categoryDisplayLabel(row);
    const helper = isLowerAccounting
      ? 'Если это обычный расход или поступление, выберите категорию и сохраните как обычную запись.'
      : 'Здесь можно исправить категорию именно этой записи.';
    const forceOperational = isLowerAccounting
      ? '<label class="v2-source-accounting-toggle"><input type="checkbox" data-v2-source-force-operational checked><span>Убрать из нижнего учета</span></label>'
      : '';
    const submitLabel = isLowerAccounting ? 'Сохранить как обычную запись' : 'Сохранить категорию';
    return '<div class="v2-source-card" data-v2-source-entry-card="' + escapeHtml(entryId) + '">'
      + sourceRowHtml(row, index)
      + '<dl class="v2-source-card-meta">'
      + '<div><dt>Категория сейчас</dt><dd>' + escapeHtml(categoryNow) + '</dd></div>'
      + '<div><dt>Учет сейчас</dt><dd>' + escapeHtml(accountingDisplayLabel(row)) + '</dd></div>'
      + '</dl>'
      + (isAdminDebt
        ? '<div class="v2-source-card-note">Это личный расход администратора. Он идет в блок задолженности администратора и не требует категории расходов.</div>'
        : (entryId
        ? '<form class="v2-source-category-form" data-v2-source-category-form data-v2-source-entry-id="' + escapeHtml(entryId) + '">'
          + '<label><span>Куда отнести запись</span><select data-v2-source-category-select ' + (isSaving ? 'disabled' : '') + '>' + categoryOptionsHtml(currentCategory) + '</select></label>'
          + '<button type="submit" ' + (isSaving ? 'disabled' : '') + '>' + (isSaving ? 'Сохраняю' : submitLabel) + '</button>'
          + forceOperational
          + '<small>' + escapeHtml(helper) + '</small>'
          + '</form>'
        : '<div class="v2-source-card-note">У этой строки нет прямой записи для правки.</div>'))
      + '</div>';
  }

  function sourceTraceEntryCanEditCategory(entry) {
    if (!entry || !entry.id) return false;
    return entry.accounting_section !== 'admin_debt' && entry.accounting_type !== 'admin_debt';
  }

  function renderLayer1Information() {
    if (!els.layer1Information) return;
    renderSummaryTabs();
    const statusText = state.layer1SummaryStatus === 'loading'
      ? 'Загружаю сводку'
      : (state.layer1SummaryError || 'Финансовый результат');
    if (els.layer1SummaryStatus) els.layer1SummaryStatus.textContent = statusText;
    if (state.layer1SummaryStatus === 'loading') {
      els.layer1Information.innerHTML = '<div class="v2-summary-state">Загружаю сводку.</div>';
      return;
    }
    if (state.layer1SummaryError) {
      els.layer1Information.innerHTML = '<div class="v2-summary-state">' + escapeHtml(state.layer1SummaryError) + '</div>';
      return;
    }
    const report = layer1Report();
    if (!report) {
      els.layer1Information.innerHTML = '<div class="v2-summary-state">Сводка периода еще не загружена.</div>';
      return;
    }
    state.sourceTraceByKey = {};

    const categoryRows = firstValue(report, ['blocks.categories.rows', 'category_totals', 'category_rows', 'categories'], []);

    els.layer1Information.innerHTML = '<div class="v2-summary-dashboard">'
      + renderSummaryHero(report)
      + renderMoneyPosition(report)
      + renderCashFlowLine(report)
      + renderSummaryAttention(report)
      + renderAdminDebt(report)
      + renderLowerAccounting(report)
      + '<section class="v2-summary-block"><div class="v2-summary-block-head"><h3>Категории</h3><span>Кеш / карта / итого</span></div>' + renderCategoryRows(categoryRows) + '</section>'
      + renderOtherReview(report)
      + '</div>';
  }

  function renderRawHistory() {
    const status = state.rawHistoryStatus;
    const history = state.rawHistory || null;
    const head = '<summary class="v2-summary-block-head"><h3>Импортированная история</h3><span>'
      + escapeHtml(status === 'loading'
        ? 'Загружаю файлы и строки'
        : (history ? (displayNumber(history.sources_total || 0) + ' файлов / ' + displayNumber(history.rows_total || 0) + ' строк') : 'Строки архивного источника'))
      + '</span></summary>';
    const conversion = state.rawHistoryConversion || null;
    const conversionText = state.rawHistoryConversionError
      ? state.rawHistoryConversionError
      : (conversion
        ? (conversion.mode === 'commit'
          ? 'Сконвертировано ' + displayNumber(conversion.converted || 0) + ' из ' + displayNumber(conversion.scanned || 0) + ' просмотренных строк'
          : 'Предпросмотр: ' + displayNumber(conversion.convertible || 0) + ' к конвертации, ' + displayNumber(conversion.duplicates || 0) + ' дубликатов, ' + displayNumber(conversion.unrecognized || 0) + ' не распознано')
        : 'Конвертируйте постепенно: сначала предпросмотр, затем небольшая партия.');
    const canCommitConversion = conversion && conversion.mode === 'preview' && Number(conversion.convertible || 0) > 0;
    const conversionControls = '<div class="v2-training-actions">'
      + '<button type="button" data-v2-raw-history-convert="preview" ' + (state.rawHistoryConversionBusy ? 'disabled' : '') + '>Проверить 25</button>'
      + '<button type="button" data-v2-raw-history-convert="commit" ' + (state.rawHistoryConversionBusy || !canCommitConversion ? 'disabled' : '') + '>Добавить 25</button>'
      + '<span class="v2-training-form-status">' + escapeHtml(state.rawHistoryConversionBusy ? 'В работе...' : conversionText) + '</span>'
      + '</div>';
    if (status === 'loading') {
      return '<details class="v2-summary-block v2-training-raw-history">' + head + '<div class="v2-summary-state">Загружаю импортированную историю.</div></details>';
    }
    if (state.rawHistoryError) {
      return '<details class="v2-summary-block v2-training-raw-history">' + head + '<div class="v2-summary-state">' + escapeHtml(state.rawHistoryError) + '</div></details>';
    }
    if (!history) {
      return '<details class="v2-summary-block v2-training-raw-history">' + head + '<div class="v2-summary-state">Импортированная история еще не загружена.</div></details>';
    }
    const sources = history.sources || [];
    if (!sources.length) {
      return '<details class="v2-summary-block v2-training-raw-history">' + head + '<div class="v2-summary-state">В этом пространстве нет файлов импортированной истории.</div></details>';
    }
    const rows = sources.slice(0, 30).map((source) => {
      const samples = (source.samples || []).map((sample) => {
        const amount = sample.amount === null || sample.amount === undefined ? '' : money(sample.amount);
        const sampleText = (sample.sign || '') + (amount ? amount + ' · ' : '') + text(sample.description || sample.parse_notes || 'сырая строка');
        return '<small title="' + escapeHtml(sampleText) + '">' + escapeHtml((sample.row_number ? '#' + sample.row_number + ' · ' : '') + sampleText) + '</small>';
      }).join('');
      return '<div class="v2-dictionary-row">'
        + '<span><strong>' + escapeHtml(text(source.file_name || source.id)) + '</strong>' + samples + '</span>'
        + '<span>' + escapeHtml(displayNumber(source.row_count || 0)) + '</span>'
        + '<span>' + escapeHtml(rawHistoryStatusLabel(source.status)) + '</span>'
        + '<span>' + escapeHtml(rawHistoryDecisionLabel(source.include_decision)) + '</span>'
        + '<span>' + escapeHtml(text(source.first_row) + ' - ' + text(source.last_row)) + '</span>'
        + '</div>';
    }).join('');
    return '<details class="v2-summary-block v2-training-raw-history">'
      + head
      + '<div class="v2-dictionary-note">' + escapeHtml('Архивные файлы нужны для обучения словаря и постепенного переноса записей. Они не меняют текущие деньги сами по себе.') + '</div>'
      + conversionControls
      + '<div class="v2-dictionary-table">'
      + '<div class="v2-dictionary-row is-header"><span>Файл и примеры</span><span>Строк</span><span>Файл</span><span>В работе</span><span>Строки</span></div>'
      + rows
      + '</div>'
      + '</details>';
  }

  function rawHistoryStatusLabel(status) {
    const labels = {
      accepted: 'Принят',
      pending: 'Ждет',
      skipped: 'Пропущен',
      rejected: 'Отклонен',
      draft: 'Черновик',
      final: 'Финальный'
    };
    return labels[String(status || '').toLowerCase()] || valueLabel(status);
  }

  function rawHistoryDecisionLabel(decision) {
    const labels = {
      included: 'Включен',
      excluded: 'Не включен',
      deferred: 'Позже',
      review: 'Проверить'
    };
    return labels[String(decision || '').toLowerCase()] || valueLabel(decision);
  }

  function renderDictionaryReviewQueue() {
    const status = state.dictionaryQueueStatus;
    const queue = state.dictionaryQueue || null;
    const signalSetTotal = queue ? queue['g' + 'roups_total'] : 0;
    const head = '<div class="v2-summary-block-head"><h3>Очередь проверки словаря</h3><span>'
      + escapeHtml(status === 'loading' ? 'Загружаю историю' : (queue ? (displayNumber(signalSetTotal || 0) + ' групп / ' + displayNumber(queue.rows_total || 0) + ' строк') : 'Сигналы истории'))
      + '</span></div>';
    if (status === 'loading') {
      return '<section class="v2-summary-block">' + head + '<div class="v2-summary-state">Загружаю очередь проверки словаря.</div></section>';
    }
    if (state.dictionaryQueueError) {
      return '<section class="v2-summary-block">' + head + '<div class="v2-summary-state">' + escapeHtml(state.dictionaryQueueError) + '</div></section>';
    }
    if (!queue) {
      return '<section class="v2-summary-block">' + head + '<div class="v2-summary-state">Очередь проверки словаря еще не загружена.</div></section>';
    }
    const signalSets = queue['g' + 'roups'] || [];
    if (!signalSets.length) {
      return '<section class="v2-summary-block">' + head + '<div class="v2-summary-state">В сырой истории нет строк для проверки словаря.</div></section>';
    }
    const rows = signalSets.slice(0, 12).map((group) => {
      const example = (group.examples || [])[0] || {};
      const markers = (group.semantic_markers || []).join(', ') || (group.current_rule_guess || 'нужна проверка');
      const source = example.source || {};
      const sourceLabel = source.file_name
        ? source.file_name + ' · ' + (source.sheet_name || 'лист') + ':' + (source.row_number || '')
        : 'источник не готов';
      const sample = (example.sign || '') + (example.amount ? money(example.amount) + ' · ' : '') + text(example.description || '');
      return '<div class="v2-dictionary-row">'
        + '<span><strong>' + escapeHtml(text(group.label)) + '</strong><small>' + escapeHtml(text(markers)) + '</small></span>'
        + '<span>' + escapeHtml(displayNumber(group.count || 0)) + '</span>'
        + '<span>' + escapeHtml(money(group.amount_abs_total || 0)) + '</span>'
        + '<span title="' + escapeHtml(sample) + '">' + escapeHtml(sample) + '</span>'
        + '<span title="' + escapeHtml(sourceLabel) + '">' + escapeHtml(sourceLabel) + '</span>'
        + '</div>';
    }).join('');

    return '<section class="v2-summary-block">'
      + head
      + '<div class="v2-dictionary-note">' + escapeHtml(queue.note || 'Только метаданные проверки.') + '</div>'
      + '<div class="v2-dictionary-table">'
      + '<div class="v2-dictionary-row is-header"><span>Группа</span><span>Строк</span><span>Сумма abs</span><span>Пример</span><span>Источник</span></div>'
      + rows
      + '</div>'
      + '</section>';
  }

  function dictionaryTrainingRows() {
    const queue = state.dictionaryQueue || {};
    const signalSets = queue['g' + 'roups'] || [];
    const rows = [];
    signalSets.forEach((group) => {
      (group.examples || []).forEach((example) => {
        const source = example.source || {};
        const sourceRowId = source.source_row_id || '';
        if (!sourceRowId) return;
        rows.push({ group, example, source, sourceRowId });
      });
    });
    return rows;
  }

  function dictionaryTrainingHasReadableExample(item) {
    const group = item && item.group ? item.group : {};
    const example = item && item.example ? item.example : {};
    const label = text(group.label || '').toLowerCase();
    return Boolean(text(example.description || '') || example.amount || example.sign)
      && label !== 'needs review';
  }

  function dictionaryDecisionBySourceRow() {
    const map = {};
    (state.dictionaryTrainingDecisions || []).forEach((decision) => {
      if (decision && decision.source_row_id) map[decision.source_row_id] = decision;
    });
    return map;
  }

  function dictionaryTrainingStatusText() {
    if (state.dictionaryQueueStatus === 'loading' || state.dictionaryTrainingStatus === 'loading') return 'Загружаю данные обучения';
    if (state.dictionaryQueueError) return state.dictionaryQueueError;
    if (state.dictionaryTrainingError) return state.dictionaryTrainingError;
    const rows = dictionaryTrainingRows();
    return displayNumber(rows.length) + ' строк на проверку / ' + displayNumber((state.dictionaryTrainingDecisions || []).length) + ' сохранено';
  }

  function dictionarySourceLabel(item) {
    const source = item && item.source ? item.source : {};
    return source.file_name
      ? source.file_name + ' · ' + (source.sheet_name || 'лист') + ':' + (source.row_number || '')
      : 'источник не готов';
  }

  function dictionaryExampleSample(example) {
    return (example.sign || '') + (example.amount ? money(example.amount) + ' · ' : '') + text(example.description || '');
  }

  function dictionaryHasRuleBlockers(example) {
    const blockers = Array.isArray(example && example.blockers) ? example.blockers : [];
    const reviewReason = example && example.review_reason ? String(example.review_reason) : '';
    return blockers.length > 0 || [
      'blocked_by_personal',
      'blocked_by_debt',
      'private_money_movement',
      'commercial_income_unclear',
      'card_income_not_allowed'
    ].includes(reviewReason);
  }

  function dictionaryDecisionLabel(decision) {
    if (!decision) return 'Открыто';
    const type = text(decision.decision_type);
    if (type === 'reject_training' && decision.target_category_code) {
      return 'Ручной разбор: ' + dictionaryReadableCategory(decision.target_category_code);
    }
    if (type === 'reject_training') {
      return 'Без обучения';
    }
    const labels = {
      approve_existing_guess_local: 'Запомнено как верно',
      correct_category_local: 'Категория исправлена',
      defer: 'Отложено',
      mark_semantic_blocked: 'Обучение запрещено',
      propose_universal_candidate: 'Предложено в общий словарь'
    };
    return labels[type] || valueLabel(type);
  }

  function dictionaryDecisionState(decision, example) {
    if (decision) {
      if (decision.category_rule_id) return 'local_rule';
      if (decision.decision_type === 'defer') return 'deferred';
      if (decision.decision_type === 'mark_semantic_blocked') return 'blocked';
      if (decision.decision_type === 'propose_universal_candidate') return 'universal_candidate';
      return 'decided';
    }
    return dictionaryHasRuleBlockers(example) ? 'blocked' : 'open';
  }

  function dictionaryDecisionBadge(decision, example) {
    const stateName = dictionaryDecisionState(decision, example);
    const labels = {
      open: 'Ждет решения',
      deferred: 'Отложено',
      blocked: 'Осторожно',
      decided: 'Решено',
      local_rule: 'Запомнено',
      universal_candidate: 'В общий словарь'
    };
    return '<b class="v2-training-badge is-' + escapeHtml(stateName) + '" data-v2-training-badge="' + escapeHtml(stateName) + '">' + escapeHtml(labels[stateName] || 'Открыто') + '</b>';
  }

  function dictionaryTrainingTokens(value) {
    return String(value || '').split(/[,\n]/).map((item) => item.trim()).filter(Boolean);
  }

  function dictionaryTrainingTokensValue(tokens) {
    return (Array.isArray(tokens) ? tokens : []).filter(Boolean).join(', ');
  }

  function dictionaryPlainReviewReason(reason) {
    const labels = {
      other_review: 'нужно решение человека',
      weak_only: 'слабый признак',
      mixed_context: 'в строке смешаны разные смыслы',
      blocked_by_personal: 'похоже на личные деньги',
      blocked_by_debt: 'есть долг, займ или кредит',
      private_money_movement: 'похоже на личный расчет',
      commercial_income_unclear: 'неясный коммерческий приход',
      card_income_not_allowed: 'приход на карту требует ручной проверки',
      card_income_manual_guard: 'пополнение карты требует ручного подтверждения'
    };
    return labels[String(reason || '')] || String(reason || 'нет');
  }

  function dictionaryReadableCategory(code) {
    const category = state.categories.find((item) => item.code === code);
    if (!category) return code || 'категория не выбрана';
    const name = category.name && (category.name.ru || category.name.en);
    return name || category.code;
  }

  function dictionaryHumanSignalLabel(label) {
    const value = String(label || '').trim();
    const normalized = value.toLowerCase().replace(/\s+/g, ' ');
    const categoryGuess = normalized.match(/^category guess:\s*([a-z0-9_/-]+)$/);
    if (categoryGuess) {
      return 'Похоже на категорию: ' + dictionaryReadableCategory(categoryGuess[1]);
    }
    const labels = {
      'owner funding': 'Пополнение от владельца',
      'actor / source context': 'Участник или источник неясен',
      'debt / loan / credit': 'Долг, займ или кредит',
      'money movement / private settlement': 'Личный расчет или перенос денег',
      'non-yacht / personal context': 'Похоже на личные расходы',
      'mixed dictionary context': 'Смешанный смысл',
      'weak dictionary context': 'Слабый признак',
      'commercial income': 'Коммерческое поступление',
      'card income': 'Поступление на карту'
    };
    return labels[normalized] || valueLabel(value);
  }

  function dictionaryHumanNextStep(blocked, hasGuess, decision) {
    if (decision) {
      return 'Решение уже сохранено. Меняйте его только если прежний выбор оказался неверным.';
    }
    if (blocked) {
      return 'Здесь есть риск: личные деньги, долг, займ или похожий случай. Такую строку можно разобрать вручную, но не стоит делать из нее правило для будущих записей.';
    }
    if (hasGuess) {
      return 'Если предложение системы верное, нажмите «Верно, запомнить». Если нет - выберите правильную категорию и сохраните.';
    }
    return 'Сначала выберите понятную категорию. Если сомневаетесь, лучше отложите строку.';
  }

  function dictionaryAssistantSuggestion(item, decision) {
    const group = item.group || {};
    const example = item.example || {};
    const description = text(example.description || group.label).toLowerCase();
    const guessedCategory = example.current_rule_guess || group.current_rule_guess || '';
    const blockers = Array.isArray(example.blockers) ? example.blockers : [];
    const reviewReason = String(example.review_reason || '');
    const blocked = dictionaryHasRuleBlockers(example);
    const requires = [];
    const excludes = [];
    let title = 'Нужна ручная проверка';
    let advice = 'Я бы не обучал словарь по этой строке сразу. Сигнал пока слабый для самостоятельного правила.';

    if (blocked) {
      title = 'Не обучать по этой строке';
      advice = 'Здесь есть личные деньги, долг или другой риск. Такую строку лучше не превращать в правило без ручного решения.';
      excludes.push('мой', 'личный', 'долг', 'кредит', 'займ');
    } else if (reviewReason === 'mixed_context' || description.includes('доставка фильтра')) {
      title = 'Смешанный сигнал';
      advice = 'Одно слово подсказывает категорию, но остальная запись меняет смысл. Нужны уточняющие слова или отложенное решение.';
      if (description.includes('доставка')) requires.push('такси', 'трансфер', 'курьер', 'порт', 'аэропорт');
      excludes.push('фильтр', 'запчаст', 'деталь');
    } else if (reviewReason === 'weak_only') {
      title = 'Слабый сигнал';
      advice = 'Категория возможна, но фраза слишком общая. Правило безопасно только с уточняющими словами и исключениями.';
      excludes.push('мой', 'личный', 'долг', 'кредит');
    } else if (!guessedCategory) {
      title = 'Категория не угадана';
      advice = 'Надежной категории пока нет. Выберите ее вручную или вернитесь к строке позже.';
    } else if (decision) {
      title = 'Решение уже сохранено';
      advice = 'Это уже сохраненное решение. Новая правка изменит обучение для этой строки.';
    } else {
      title = 'Возможно локальное правило';
      advice = 'Можно запомнить правило для этого рабочего пространства, если фраза точная и категория подходит.';
    }

    if (description.includes('цоги') || description.includes('cogimar')) {
      title = 'Похоже на название поставщика';
      advice = 'Не стоит обучать глобальное правило только по названию. Нужно понять, что купили, или оставить строку на проверке.';
      requires.push('рыба', 'магазин', 'провизия');
    }

    return {
      title,
      advice,
      requires: Array.from(new Set(requires)),
      excludes: Array.from(new Set(excludes))
    };
  }

  function dictionaryInternetReferenceResult(sourceRowId) {
    return state.dictionaryInternetResults[sourceRowId] || null;
  }

  function dictionaryInternetReferenceHtml(item) {
    const example = item.example || {};
    const sourceRowId = item.sourceRowId;
    const result = dictionaryInternetReferenceResult(sourceRowId);
    const busy = state.dictionaryInternetBusyKey === sourceRowId;
    const feedbackBusy = state.dictionaryInternetFeedbackBusyKey === sourceRowId;
    const description = text(example.description || '');
    const query = result ? result.sanitized_query : description.replace(/[+\-]?\s*\d+(?:[.,]\d+)?/g, '').trim();
    const match = result && result.matches && result.matches[0] ? result.matches[0] : null;
    const sourceUrl = match && match.source_url ? String(match.source_url) : '';
    const sourceDomain = match && match.source_domain ? String(match.source_domain) : '';
    const selectedMatch = result && result.selected_match ? result.selected_match : null;
    const verdict = selectedMatch && selectedMatch.verdict ? String(selectedMatch.verdict) : '';
    const error = state.dictionaryInternetError && state.dictionaryInternetBusyKey === '' ? state.dictionaryInternetError : '';
    const previewDisabled = true;
    return '<section class="v2-training-smith" data-v2-mr-smith>'
      + '<div><strong>Mr. Smith beta</strong><span>Только справочная проверка. Она не меняет категорию и не сохраняет обучение.</span></div>'
      + '<label><span>Очищенный запрос</span><input type="text" value="' + escapeHtml(query) + '" data-v2-smith-query maxlength="190" placeholder="Только публичное название"></label>'
      + '<label><span>Публичный URL источника</span><input type="url" value="' + escapeHtml(sourceUrl) + '" data-v2-smith-url data-v2-smith-candidate-url maxlength="2000" placeholder="https://approved-source.example/page"></label>'
      + '<label class="v2-training-smith-consent"><input type="checkbox" data-v2-smith-consent><span>Я разрешаю просмотреть этот публичный URL как справочный источник.</span></label>'
      + '<button type="button" data-v2-smith-reference ' + (previewDisabled ? 'disabled' : '') + '>' + escapeHtml(busy ? 'Проверяю источник...' : 'Проверить источник') + '</button>'
      + (result ? '<div class="v2-training-smith-result" data-v2-smith-result>'
        + '<b>' + escapeHtml('Источник: ' + (result.matches && result.matches[0] ? result.matches[0].label : 'Внешняя проверка не выполнялась')) + '</b>'
        + (sourceDomain ? '<span>' + escapeHtml(sourceDomain) + '</span>' : '')
        + '<span>' + escapeHtml(result.suggested_reviewer_question || 'Проверьте источник перед сохранением правила.') + '</span>'
        + '<small>' + escapeHtml('источник ' + (sourceDomain || (match && match.source_type ? match.source_type : 'stub')) + ' · проверка ' + (result.lookup_id || result.request_id || '').slice(0, 8) + ' · hash запроса ' + (result.query_hash || '').slice(0, 12) + ' · без изменения финансов и обучения') + '</small>'
        + '<div class="v2-training-smith-feedback" data-v2-smith-feedback>'
        + '<button type="button" data-v2-smith-feedback-action="useful" ' + (feedbackBusy ? 'disabled' : '') + '>Полезно</button>'
        + '<button type="button" data-v2-smith-feedback-action="unclear" ' + (feedbackBusy ? 'disabled' : '') + '>Неясно</button>'
        + '<button type="button" data-v2-smith-feedback-action="not_useful" ' + (feedbackBusy ? 'disabled' : '') + '>Не полезно</button>'
        + (verdict ? '<small>' + escapeHtml('Оценка: ' + verdict.replace('_', ' ')) + '</small>' : '')
        + '</div>'
        + '</div>' : '')
      + (error ? '<div class="v2-training-warning">' + escapeHtml(error) + '</div>' : '')
      + '</section>';
  }

  function dictionaryRowMatchesFilter(item, decision) {
    const filter = state.trainingFilter || 'all';
    const example = item.example || {};
    const reviewReason = String(example.review_reason || '');
    if (filter === 'all') return true;
    if (filter === 'weak') return reviewReason === 'weak_only' || dictionaryExampleText(item).includes('weak_dictionary_context');
    if (filter === 'mixed') return reviewReason === 'mixed_context' || dictionaryExampleText(item).includes('mixed_dictionary_context');
    if (filter === 'blocked') return dictionaryDecisionState(decision, example) === 'blocked' || dictionaryHasRuleBlockers(example);
    if (filter === 'no_category') return !(example.current_rule_guess || (item.group && item.group.current_rule_guess));
    if (filter === 'deferred') return Boolean(decision && decision.decision_type === 'defer');
    if (filter === 'decided') return Boolean(decision && decision.decision_type !== 'defer');
    return true;
  }

  function dictionaryExampleText(item) {
    const group = item.group || {};
    const example = item.example || {};
    const decision = dictionaryDecisionBySourceRow()[item.sourceRowId] || null;
    return [
      group.label,
      example.description,
      example.current_rule_guess,
      group.current_rule_guess,
      example.review_reason,
      dictionarySourceLabel(item),
      decision && decision.decision_type,
      decision && decision.target_category_code,
      decision && decision.pattern
    ].concat(example.blockers || [], example.matched_signals || [], example.semantic_markers || []).map((value) => {
      if (value === null || value === undefined) return '';
      return typeof value === 'string' ? value : JSON.stringify(value);
    }).join(' ').toLowerCase();
  }

  function dictionaryFilteredTrainingRows(rows, decisions) {
    const search = (state.trainingSearch || '').trim().toLowerCase();
    return rows.filter((item) => {
      if (!dictionaryTrainingHasReadableExample(item)) return false;
      const decision = decisions[item.sourceRowId] || null;
      if (!dictionaryRowMatchesFilter(item, decision)) return false;
      return search === '' || dictionaryExampleText(item).includes(search);
    }).sort((left, right) => dictionaryRowSortRank(left, decisions[left.sourceRowId] || null) - dictionaryRowSortRank(right, decisions[right.sourceRowId] || null));
  }

  function dictionaryRowSortRank(item, decision) {
    const example = item.example || {};
    if (!dictionaryTrainingHasReadableExample(item)) return 90;
    if (decision && decision.decision_type !== 'defer') return 70;
    if (decision && decision.decision_type === 'defer') return 60;
    if (dictionaryHasRuleBlockers(example)) return 10;
    if (!(example.current_rule_guess || (item.group && item.group.current_rule_guess))) return 20;
    if (example.review_reason === 'mixed_context') return 30;
    if (example.review_reason === 'weak_only') return 40;
    return 50;
  }

  function renderDictionaryTrainingControls(totalRows, visibleRows) {
    const filters = [
      ['all', 'Все'],
      ['weak', 'Слабый сигнал'],
      ['mixed', 'Смешанный смысл'],
      ['blocked', 'Осторожно'],
      ['no_category', 'Без категории'],
      ['deferred', 'Отложенные'],
      ['decided', 'Готовые']
    ];
    return '<div class="v2-training-controls" data-v2-training-controls>'
      + '<div class="v2-training-filters">'
      + filters.map(([value, label]) => '<button type="button" data-v2-training-filter="' + escapeHtml(value) + '" class="' + (state.trainingFilter === value ? 'is-active' : '') + '">' + escapeHtml(label) + '</button>').join('')
      + '</div>'
      + '<label class="v2-training-search"><span>Поиск</span><input type="search" value="' + escapeHtml(state.trainingSearch) + '" data-v2-training-search placeholder="текст записи или категория"></label>'
      + '<div class="v2-training-count" data-v2-training-count>' + escapeHtml(displayNumber(visibleRows.length)) + ' из ' + escapeHtml(displayNumber(totalRows.length)) + '</div>'
      + '</div>';
  }

  function renderDictionaryTraining() {
    if (!els.trainingQueue || !els.trainingDetail) return;
    if (els.trainingStatus) els.trainingStatus.textContent = dictionaryTrainingStatusText();
    if (els.trainingRefresh) {
      els.trainingRefresh.disabled = state.dictionaryQueueStatus === 'loading' || state.dictionaryTrainingStatus === 'loading';
    }
    const rows = dictionaryTrainingRows();
    const decisions = dictionaryDecisionBySourceRow();
    const visibleRows = dictionaryFilteredTrainingRows(rows, decisions);
    const rawHistoryHtml = renderRawHistory();
    if (!rows.length) {
      els.trainingQueue.innerHTML = (state.dictionaryQueueStatus === 'loading'
        ? '<div class="v2-summary-state">Загружаю очередь проверки словаря.</div>'
        : '<div class="v2-summary-state">' + escapeHtml(state.dictionaryQueueError || 'Примеров для проверки пока нет.') + '</div>') + rawHistoryHtml;
      els.trainingDetail.innerHTML = '<div class="v2-summary-state">Выберите строку проверки, чтобы принять решение.</div>';
      return;
    }
    const activeItem = visibleRows.find((item) => item.sourceRowId === state.activeTrainingSourceRowId) || null;
    if (!state.activeTrainingSourceRowId || !activeItem || !dictionaryTrainingHasReadableExample(activeItem)) {
      const firstReadable = visibleRows.find(dictionaryTrainingHasReadableExample) || visibleRows[0] || null;
      state.activeTrainingSourceRowId = firstReadable ? firstReadable.sourceRowId : '';
    }
    els.trainingQueue.innerHTML = renderDictionaryTrainingControls(rows, visibleRows) + (visibleRows.length
      ? renderDictionaryTrainingQueue(visibleRows)
      : '<div class="v2-summary-state" data-v2-training-empty>По этому фильтру ничего не найдено.</div>') + rawHistoryHtml;
    els.trainingDetail.innerHTML = visibleRows.length
      ? renderDictionaryTrainingDetail(visibleRows)
      : '<div class="v2-summary-state">Измените фильтр или поиск, чтобы выбрать строку.</div>';
    window.requestAnimationFrame(() => {
      const activeRow = els.trainingQueue ? els.trainingQueue.querySelector('[data-v2-dictionary-row].is-active') : null;
      if (activeRow) activeRow.scrollIntoView({ block: 'nearest' });
    });
  }

  function renderDictionaryTrainingQueue(rows) {
    const decisions = dictionaryDecisionBySourceRow();
    const header = '<div class="v2-training-row is-header"><span>Запись</span><span>Предложено</span><span>Почему проверяем</span><span>Статус</span></div>';
    return '<div class="v2-training-table">' + header + rows.map((item) => {
      const group = item.group || {};
      const example = item.example || {};
      const decision = decisions[item.sourceRowId] || null;
      const blockers = Array.isArray(example.blockers) ? example.blockers : [];
      const selected = item.sourceRowId === state.activeTrainingSourceRowId;
      const classes = ['v2-training-row'];
      if (selected) classes.push('is-active');
      if (decision) classes.push('is-decided');
      if (dictionaryHasRuleBlockers(example)) classes.push('is-blocked');
      const reason = example.review_reason || blockers.length
        ? dictionaryPlainReviewReason(example.review_reason || blockers[0])
        : 'похоже верно';
      const confidence = example.confidence === null || example.confidence === undefined ? '' : ' · уверенность ' + Math.round(Number(example.confidence) * 100) + '%';
      const guessed = example.current_rule_guess || group.current_rule_guess || '';
      return '<button class="' + classes.join(' ') + '" type="button" data-v2-dictionary-row data-source-row-id="' + escapeHtml(item.sourceRowId) + '" data-v2-decision-state="' + escapeHtml(dictionaryDecisionState(decision, example)) + '">'
        + '<span><strong>' + escapeHtml(dictionaryHumanSignalLabel(group.label || example.description)) + '</strong><small>' + escapeHtml(text(example.description)) + '</small></span>'
        + '<span>' + escapeHtml(guessed ? dictionaryReadableCategory(guessed) : 'Не выбрано') + '</span>'
        + '<span>' + escapeHtml(reason + confidence) + '</span>'
        + '<span>' + dictionaryDecisionBadge(decision, example) + '<small>' + escapeHtml(dictionaryDecisionLabel(decision)) + '</small></span>'
        + '</button>';
    }).join('') + '</div>';
  }

  function renderDictionaryTrainingDetail(rows) {
    const item = rows.find((row) => row.sourceRowId === state.activeTrainingSourceRowId) || rows[0];
    if (!item) return '<div class="v2-summary-state">Выберите строку проверки, чтобы принять решение.</div>';
    const group = item.group || {};
    const example = item.example || {};
    const decisions = dictionaryDecisionBySourceRow();
    const decision = decisions[item.sourceRowId] || null;
    const blockers = Array.isArray(example.blockers) ? example.blockers : [];
    const markers = (example.semantic_markers || []).map((marker) => typeof marker === 'string' ? marker : (marker.marker || marker.source || '')).filter(Boolean);
    const signals = (example.matched_signals || []).map((signal) => typeof signal === 'string' ? signal : (signal.marker || signal.pattern || signal.source || JSON.stringify(signal))).filter(Boolean);
    const guessedCategory = example.current_rule_guess || group.current_rule_guess || '';
    const pattern = text(example.description || group.label, '');
    const blocked = dictionaryHasRuleBlockers(example);
    const hasGuess = Boolean(guessedCategory);
    const busy = state.dictionaryTrainingBusyKey === item.sourceRowId;
    const assistant = dictionaryAssistantSuggestion(item, decision);
    const reviewReasonPlain = dictionaryPlainReviewReason(example.review_reason || (blockers.length ? blockers[0] : ''));
    const nextStep = dictionaryHumanNextStep(blocked, hasGuess, decision);
    const guessedHuman = guessedCategory ? dictionaryReadableCategory(guessedCategory) : 'категория не выбрана';
    const decisionMeta = decision
      ? '<details class="v2-training-technical"><summary>Сохраненное решение</summary><div class="v2-training-existing"><strong>' + escapeHtml(dictionaryDecisionLabel(decision)) + '</strong><span>' + escapeHtml(text(decision.decided_at || decision.updated_at)) + '</span></div></details>'
      : '';
    const warning = blocked
      ? '<div class="v2-training-warning"><strong>Не делайте из этой строки правило.</strong><span>Причина: ' + escapeHtml(reviewReasonPlain) + '. Можно сохранить ручной разбор для этой записи или отложить ее.</span></div>'
      : '';
    const manualCategorySelected = decision && decision.target_category_code ? decision.target_category_code : '';
    const categoryField = blocked
      ? '<div class="v2-training-primary-category is-manual"><div class="v2-training-readonly-target"><span>Что заметила система</span><strong>' + escapeHtml(dictionaryBlockedTargetLabel(example)) + '</strong><small class="v2-field-help">' + escapeHtml(dictionaryBlockedTargetHelp(example)) + '</small></div><label><span>Если это обычная операция, выберите категорию</span><select data-v2-dictionary-category>' + categoryOptionsHtml(manualCategorySelected) + '</select></label></div>'
      : '<label class="v2-training-primary-category"><span>Правильная категория</span><select data-v2-dictionary-category>' + categoryOptionsHtml(guessedCategory) + '</select><small class="v2-field-help">Если категория верная, можно сразу запомнить. Если нет - выберите правильную.</small></label>';
    const primaryActions = blocked
      ? '<div class="v2-training-actions v2-training-primary-actions is-blocked">'
        + '<button type="button" data-v2-dictionary-decision-action="reject_training" data-v2-training-keep-lower="1" ' + (busy ? 'disabled' : '') + '>Оставить как особый учет</button>'
        + '<button type="button" data-v2-dictionary-decision-action="reject_training" data-v2-training-require-category="1" ' + (busy ? 'disabled' : '') + '>Сохранить только эту запись</button>'
        + '<button type="button" data-v2-dictionary-decision-action="defer" ' + (busy ? 'disabled' : '') + '>Разобраться позже</button>'
        + '<button type="button" data-v2-dictionary-decision-action="mark_semantic_blocked" ' + (busy ? 'disabled' : '') + '>Запретить обучение</button>'
        + '</div>'
      : '<div class="v2-training-actions v2-training-primary-actions">'
        + '<button type="button" data-v2-dictionary-decision-action="approve_existing_guess_local" ' + (busy || !hasGuess ? 'disabled' : '') + '>Верно, запомнить</button>'
        + '<button type="button" data-v2-dictionary-decision-action="correct_category_local" ' + (busy ? 'disabled' : '') + '>Запомнить выбранное</button>'
        + '<button type="button" data-v2-dictionary-decision-action="defer" ' + (busy ? 'disabled' : '') + '>Не уверен, позже</button>'
        + '<button type="button" data-v2-dictionary-decision-action="reject_training" ' + (busy ? 'disabled' : '') + '>Не учить</button>'
        + '</div>';
    return '<div class="v2-training-detail-body" data-source-row-id="' + escapeHtml(item.sourceRowId) + '">'
      + '<section class="v2-training-current-row">'
      + '<div><span>Проверяемая запись</span><strong>' + escapeHtml(dictionaryExampleSample(example)) + '</strong></div>'
      + '<div><span>Система предлагает</span><strong>' + escapeHtml(guessedHuman) + '</strong></div>'
      + '</section>'
      + warning
      + '<section class="v2-training-assistant" data-v2-training-assistant-readback>'
      + '<div><strong>Как поступить</strong><span>' + escapeHtml(nextStep) + '</span></div>'
      + '</section>'
      + '<form class="v2-training-form" data-v2-dictionary-training-form>'
      + '<div class="v2-training-form-title"><strong>Главное решение</strong><span>Выберите категорию и нажмите одну из кнопок. Это обучает только текущее рабочее пространство.</span></div>'
      + categoryField
      + primaryActions
      + '<div class="v2-training-form-status">' + escapeHtml(state.dictionaryTrainingError || (busy ? 'Сохраняю...' : 'Этого достаточно для обычной ручной работы.')) + '</div>'
      + '<details class="v2-training-technical">'
      + '<summary>Дополнительные условия правила</summary>'
      + '<div class="v2-training-advanced-grid">'
      + '<label><span>Что искать в тексте</span><input type="text" value="' + escapeHtml(pattern) + '" data-v2-dictionary-pattern maxlength="255"><small class="v2-field-help">Лучше точная фраза, не одно широкое слово.</small></label>'
      + '<label><span>Тип подсказки</span><select data-v2-dictionary-pattern-type><option value="keyword">отдельное слово</option><option value="phrase">точная фраза</option><option value="supplier">название поставщика</option><option value="role">роль человека</option></select></label>'
      + '<label><span>Язык записи</span><select data-v2-dictionary-language><option value="ru">RU</option><option value="en">EN</option><option value="it">IT</option><option value="es">ES</option><option value="de">DE</option><option value="bcms">BCMS</option><option value="multi">Multi</option></select></label>'
      + '<label><span>Сила подсказки</span><input type="number" value="10" min="-100" max="100" step="1" data-v2-dictionary-weight></label>'
      + '<label><span>Применять только если есть слова</span><input type="text" value="' + escapeHtml(dictionaryTrainingTokensValue(decision && decision.requires_any ? decision.requires_any : assistant.requires)) + '" data-v2-dictionary-requires-any placeholder="например: служебная, карта"></label>'
      + '<label><span>Не применять, если есть слова</span><input type="text" value="' + escapeHtml(dictionaryTrainingTokensValue(decision && decision.excludes_any ? decision.excludes_any : assistant.excludes)) + '" data-v2-dictionary-excludes-any placeholder="например: мой, долг, кредит"></label>'
      + '<label class="v2-training-note"><span>Заметка для себя</span><textarea data-v2-dictionary-note maxlength="2000" placeholder="Почему вы выбрали эту категорию"></textarea></label>'
      + '<div class="v2-training-actions">'
      + '<button type="button" data-v2-dictionary-decision-action="mark_semantic_blocked" ' + (busy ? 'disabled' : '') + '>Запретить обучение</button>'
      + '<button type="button" data-v2-dictionary-decision-action="propose_universal_candidate" ' + (busy ? 'disabled' : '') + '>Предложить для общего словаря</button>'
      + '</div>'
      + '</div>'
      + '</details>'
      + '</form>'
      + '<details class="v2-training-technical">'
      + '<summary>Справка Mr. Smith</summary>'
      + dictionaryInternetReferenceHtml(item)
      + '</details>'
      + decisionMeta
      + '<details class="v2-training-technical">'
      + '<summary>Технические признаки</summary>'
      + '<dl class="v2-detail-grid v2-training-evidence">'
      + '<div><dt>пример</dt><dd>' + escapeHtml(dictionaryExampleSample(example)) + '</dd></div>'
      + '<div><dt>источник</dt><dd>' + escapeHtml(dictionarySourceLabel(item)) + '</dd></div>'
      + '<div><dt>текущая версия</dt><dd>' + escapeHtml(text(guessedCategory || 'нет')) + '</dd></div>'
      + '<div><dt>уверенность</dt><dd>' + escapeHtml(example.confidence === null || example.confidence === undefined ? '—' : String(example.confidence)) + '</dd></div>'
      + '<div><dt>причина проверки</dt><dd>' + escapeHtml(text(example.review_reason || 'нет')) + '</dd></div>'
      + '<div><dt>блокеры</dt><dd>' + escapeHtml(blockers.length ? blockers.join(', ') : 'нет') + '</dd></div>'
      + '<div><dt>маркеры</dt><dd>' + escapeHtml(markers.length ? markers.join(', ') : 'нет') + '</dd></div>'
      + '<div><dt>сигналы</dt><dd>' + escapeHtml(signals.length ? signals.join(', ') : 'нет') + '</dd></div>'
      + '</dl>'
      + '</details>'
      + '</div>';
  }

  function renderDictionarySurfaces() {
    if (state.activeScreen === 'summary' && state.activeSummaryTab === 'information') renderLayer1Information();
    if (state.activeScreen === 'training') renderDictionaryTraining();
  }

  function quickNoteStatusLabel(status) {
    return {
      draft: 'Черновик',
      reviewed: 'Разобрано',
      converted: 'Перенесено'
    }[status] || 'Черновик';
  }

  function activeQuickNote() {
    if (state.quickNoteComposingNew) return null;
    return state.quickNotes.find((note) => String(note.id) === String(state.activeQuickNoteId)) || null;
  }

  function quickNoteIsLocked(note) {
    return !!note && note.status === 'converted';
  }

  function quickNotePreviewItems() {
    return (state.quickNotePreview && Array.isArray(state.quickNotePreview.items)) ? state.quickNotePreview.items : [];
  }

  function closeQuickNoteSmith() {
    state.quickNoteModalOpen = false;
    renderQuickNotes();
  }

  function toggleQuickNoteHistory() {
    state.quickNoteHistoryOpen = !state.quickNoteHistoryOpen;
    renderQuickNotes();
  }

  function orderedQuickNotes(notes) {
    const list = Array.isArray(notes) ? notes.slice() : [];
    if (!state.activeQuickNoteId) return list;
    const activeIndex = list.findIndex((note) => String(note.id) === String(state.activeQuickNoteId));
    if (activeIndex <= 0) return list;
    const active = list.splice(activeIndex, 1)[0];
    return [active].concat(list);
  }

  function resetQuickNoteComposer() {
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    state.activeQuickNoteId = '';
    state.quickNoteComposingNew = true;
    state.quickNotePreview = null;
    if (els.quickNoteDate) els.quickNoteDate.value = todayIso();
    if (els.quickNoteText) els.quickNoteText.value = '';
  }

  function quickNoteCardHtml(note, options) {
    const opts = options || {};
    const lines = String(note.raw_text || '').split(/\n/).map((line) => line.trim()).filter(Boolean);
    const title = note.title || ('Заметка от ' + formatReportDate(note.note_date));
    const tag = opts.tag || 'article';
    const attrs = opts.selectable
      ? ' role="button" tabindex="0" data-v2-quick-note-select="' + escapeHtml(note.id) + '"'
      : '';
    const currentBadge = opts.current
      ? '<span class="v2-quick-note-current">Текущая</span>'
      : '';
    const deleteAction = opts.deletable
      ? '<button class="v2-quick-note-delete" type="button" data-v2-quick-note-delete="' + escapeHtml(note.id) + '" aria-label="Удалить заметку">🗑</button>'
      : '';
    return '<' + tag + ' class="v2-quick-note-card' + (opts.active ? ' is-active' : '') + (opts.readonly ? ' is-readonly' : '') + (opts.selectable ? ' is-selectable' : '') + '"' + attrs + '>'
      + '<span class="v2-quick-note-card-main">'
      + '<strong>' + escapeHtml(title) + currentBadge + '</strong>'
      + '<span>' + escapeHtml(formatReportDate(note.note_date)) + ' · ' + escapeHtml(quickNoteStatusLabel(note.status)) + '</span>'
      + '<small>' + escapeHtml(lines.slice(0, opts.lines || 2).join(' · ') || 'Без текста') + '</small>'
      + '</span>'
      + deleteAction
      + '</' + tag + '>';
  }

  function renderQuickNotes() {
    if (!els.quickNotesScreen) return;
    const notes = state.quickNotes || [];
    const active = activeQuickNote();
    const visibleNotes = orderedQuickNotes(notes);
    const topNote = active || visibleNotes[0] || null;
    const historyNotes = active
      ? visibleNotes.filter((note) => String(note.id) !== String(active.id))
      : visibleNotes;
    const activeLocked = quickNoteIsLocked(active);
    const currentBlock = active
      ? '<section class="v2-quick-note-current-block">'
        + '<div class="v2-quick-note-current-head"><strong>' + (activeLocked ? 'Перенесенная заметка' : 'Текущая заметка') + '</strong><span>' + (activeLocked ? 'источник уже защищен' : 'открыта для работы') + '</span></div>'
        + quickNoteCardHtml(active, { active: true, current: true, readonly: activeLocked, tag: 'article', lines: 3 })
        + '</section>'
      : '';
    if (els.quickNotesStatus) {
      els.quickNotesStatus.textContent = state.quickNotesStatus === 'loading'
        ? 'Загружаю'
        : (state.quickNoteAutoSaving ? 'Сохраняю текущую' : (activeLocked ? 'Источник защищен' : (active ? 'Текущая заметка' : (state.quickNoteHistoryOpen ? visibleNotes.length + ' заметок' : (topNote ? 'Последняя заметка' : 'Быстрая запись перед журналом')))));
    }
    if (els.quickNotesList) {
      els.quickNotesList.classList.toggle('is-history', state.quickNoteHistoryOpen);
      if (state.quickNoteHistoryOpen) {
        els.quickNotesList.innerHTML = currentBlock
          + '<div class="v2-quick-note-history-head">'
          + '<strong>' + (active ? 'Другие заметки' : 'История заметок') + '</strong>'
          + '<button type="button" data-v2-quick-note-history-toggle>Свернуть</button>'
          + '</div>'
          + (historyNotes.length
            ? historyNotes.map((note) => {
              const isActive = String(note.id) === String(state.activeQuickNoteId);
              return quickNoteCardHtml(note, { selectable: true, active: isActive, current: isActive, readonly: quickNoteIsLocked(note), tag: 'article', lines: 3, deletable: !quickNoteIsLocked(note) });
            }).join('')
            : '<div class="v2-quick-note-empty">' + (active ? 'Других заметок пока нет.' : 'История пока пустая.') + '</div>');
      } else {
        els.quickNotesList.innerHTML = active
          ? currentBlock
            + '<button class="v2-quick-note-history-button is-compact" type="button" data-v2-quick-note-history-toggle>История</button>'
          : topNote
          ? '<div class="v2-quick-note-last-row">'
            + quickNoteCardHtml(topNote, { selectable: true, active: active && String(topNote.id) === String(active.id), current: active && String(topNote.id) === String(active.id), readonly: quickNoteIsLocked(topNote), tag: 'article' })
            + '<button class="v2-quick-note-history-button" type="button" data-v2-quick-note-history-toggle>История</button>'
            + '</div>'
          : '<div class="v2-quick-note-empty">Напишите заметку и нажмите «Поделиться». После принятия она уйдет в журнал.</div>';
      }
    }
    if (els.quickNoteDate && !state.quickNoteBusy && (active || !state.quickNoteComposingNew)) {
      els.quickNoteDate.value = active ? active.note_date : todayIso();
    }
    if (els.quickNoteText && !state.quickNoteBusy && document.activeElement !== els.quickNoteText) {
      if (active) {
        els.quickNoteText.value = active.raw_text || '';
      } else if (!state.quickNoteComposingNew) {
        els.quickNoteText.value = '';
      }
    }
    if (els.quickNoteText) els.quickNoteText.readOnly = activeLocked;
    if (els.quickNoteDate) els.quickNoteDate.disabled = activeLocked;
    const items = quickNotePreviewItems();
    if (els.quickNoteConvert) els.quickNoteConvert.disabled = activeLocked || !active || !items.length || state.quickNoteBusy;
    if (els.quickNoteLayer) els.quickNoteLayer.hidden = !state.quickNoteModalOpen;
    if (els.quickNoteModalRaw) {
      const raw = els.quickNoteText && els.quickNoteText.value.trim()
        ? els.quickNoteText.value.trim()
        : (active && active.raw_text ? active.raw_text : '');
      els.quickNoteModalRaw.textContent = raw || 'Заметка пустая';
    }
    if (els.quickNotePreview) {
      if (state.quickNoteBusy) {
        els.quickNotePreview.innerHTML = '<div class="v2-quick-note-empty">Смит разбирает заметку</div>';
      } else if (!items.length) {
        els.quickNotePreview.innerHTML = '<div class="v2-quick-note-empty">Нажмите «Поделиться», чтобы Смит предложил строки для журнала.</div>';
      } else {
        els.quickNotePreview.innerHTML = items.map((item) => {
          const p = item.preview || {};
          const duplicateCount = Array.isArray(item.duplicate_candidates) ? item.duplicate_candidates.length : 0;
          const category = categoryNameByCode(p.category_code) || 'на проверку';
          return '<label class="v2-quick-note-proposal" data-v2-quick-note-proposal="' + String(item.line_index) + '">'
            + '<input type="checkbox" data-v2-quick-note-proposal-enabled="' + String(item.line_index) + '" checked>'
            + '<span class="v2-quick-note-proposal-main">'
            + '<strong>' + escapeHtml(item.raw_text || p.raw_text || '') + '</strong>'
            + '<small>' + escapeHtml(formatReportDate(p.date)) + ' · ' + escapeHtml(valueLabel((p.flow && p.flow.type) || state.activeFlowType)) + ' · ' + escapeHtml(category) + '</small>'
            + (duplicateCount ? '<em>Похожая строка: ' + duplicateCount + ' с той же датой и суммой. Проверьте перед переносом.</em>' : '')
            + '</span>'
            + '<span class="v2-quick-note-proposal-amount ' + (p.direction === 'in' ? 'is-in' : 'is-out') + '">' + escapeHtml(p.amount === null || p.amount === undefined ? '—' : money(p.amount)) + '</span>'
            + '</label>';
        }).join('');
      }
    }
  }

  async function loadQuickNotes() {
    if (!state.workspaceId || state.quickNotesStatus === 'loading') return;
    state.quickNotesStatus = 'loading';
    renderQuickNotes();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/quick-notes');
      state.quickNotes = data.notes || [];
      if (!state.activeQuickNoteId && !state.quickNoteComposingNew) state.quickNoteComposingNew = true;
      state.quickNotesStatus = 'ready';
      state.quickNotesError = '';
    } catch (error) {
      state.quickNotesStatus = 'error';
      state.quickNotesError = error.error || 'quick_notes_failed';
      setStatus('Заметки не загрузились', true);
    }
    renderQuickNotes();
  }

  function newQuickNote() {
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    state.activeQuickNoteId = '';
    state.quickNoteComposingNew = true;
    state.quickNotePreview = null;
    if (els.quickNoteDate) els.quickNoteDate.value = todayIso();
    if (els.quickNoteText) {
      els.quickNoteText.value = '';
      els.quickNoteText.focus({ preventScroll: true });
    }
    renderQuickNotes();
  }

  function openQuickNote(noteId) {
    const note = state.quickNotes.find((item) => String(item.id) === String(noteId));
    if (!note) return;
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    state.activeQuickNoteId = note.id;
    state.quickNoteComposingNew = false;
    state.quickNotePreview = null;
    if (els.quickNoteDate) els.quickNoteDate.value = note.note_date || todayIso();
    if (els.quickNoteText) {
      els.quickNoteText.value = note.raw_text || '';
      if (!quickNoteIsLocked(note)) els.quickNoteText.focus({ preventScroll: true });
    }
    renderQuickNotes();
  }

  function scheduleQuickNoteAutoSave() {
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    if (!state.workspaceId || !els.quickNoteText || state.quickNoteBusy) return;
    if (quickNoteIsLocked(activeQuickNote())) return;
    const raw = els.quickNoteText.value.trim();
    if (!raw) return;
    state.quickNoteAutoSaveTimer = window.setTimeout(() => {
      saveQuickNote({ silent: true });
    }, 900);
  }

  async function saveQuickNote(options) {
    const opts = options || {};
    if (!state.workspaceId || state.quickNoteBusy || state.quickNoteAutoSaving || !els.quickNoteText) return null;
    if (quickNoteIsLocked(activeQuickNote())) {
      if (!opts.silent) setStatus('Эта заметка уже перенесена. Нажмите «Новая», чтобы продолжить.', true);
      return null;
    }
    const raw = els.quickNoteText.value.trim();
    if (!raw) {
      if (!opts.silent) setStatus('Заметка пустая', true);
      return null;
    }
    if (opts.silent) {
      state.quickNoteAutoSaving = true;
    } else {
      state.quickNoteBusy = true;
      renderQuickNotes();
    }
    try {
      const payload = {
        note_date: els.quickNoteDate && els.quickNoteDate.value ? els.quickNoteDate.value : todayIso(),
        raw_text: raw
      };
      const route = state.activeQuickNoteId && !state.quickNoteComposingNew
        ? '/api/workspaces/' + state.workspaceId + '/quick-notes/' + state.activeQuickNoteId
        : '/api/workspaces/' + state.workspaceId + '/quick-notes';
      const data = await v2Api(state.activeQuickNoteId && !state.quickNoteComposingNew ? 'PATCH' : 'POST', route, payload);
      const note = data.note;
      if (note) {
        const existingIndex = state.quickNotes.findIndex((item) => String(item.id) === String(note.id));
        if (existingIndex >= 0) state.quickNotes[existingIndex] = note;
        else state.quickNotes.unshift(note);
        state.activeQuickNoteId = note.id;
        state.quickNoteComposingNew = false;
      }
      state.quickNotePreview = null;
      if (!opts.silent) setStatus('Заметка сохранена');
      state.quickNotesStatus = 'ready';
      return note || null;
    } catch (error) {
      if (!opts.silent) setStatus(error.error || 'Заметка не сохранена', true);
      return null;
    } finally {
      if (opts.silent) state.quickNoteAutoSaving = false;
      else state.quickNoteBusy = false;
      renderQuickNotes();
    }
  }

  async function previewQuickNote() {
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    let note = activeQuickNote();
    if (quickNoteIsLocked(note)) {
      setStatus('Эта заметка уже перенесена в журнал', true);
      return;
    }
    if (!note || (els.quickNoteText && els.quickNoteText.value.trim() !== note.raw_text)) {
      note = await saveQuickNote();
    }
    const flow = activeFlow();
    if (!note || !flow || state.quickNoteBusy) return;
    state.quickNoteModalOpen = true;
    state.quickNoteBusy = true;
    renderQuickNotes();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/quick-notes/' + note.id + '/preview', {
        flow_id: flow.id,
        date: els.quickNoteDate && els.quickNoteDate.value ? els.quickNoteDate.value : note.note_date
      });
      state.quickNotePreview = { items: data.items || [] };
      if (data.note) {
        state.quickNotes = state.quickNotes.map((item) => String(item.id) === String(data.note.id) ? data.note : item);
      }
      setStatus('Разбор готов');
    } catch (error) {
      setStatus(error.error || 'Разбор не выполнен', true);
    } finally {
      state.quickNoteBusy = false;
      renderQuickNotes();
    }
  }

  async function convertQuickNote() {
    clearTimeout(state.quickNoteAutoSaveTimer);
    state.quickNoteAutoSaveTimer = 0;
    const note = activeQuickNote();
    if (quickNoteIsLocked(note)) {
      setStatus('Эта заметка уже перенесена в журнал', true);
      return;
    }
    const flow = activeFlow();
    const items = quickNotePreviewItems().map((item) => {
      const enabled = !!(els.quickNotePreview && els.quickNotePreview.querySelector('[data-v2-quick-note-proposal-enabled="' + String(item.line_index) + '"]:checked'));
      return {
        line_index: item.line_index,
        enabled: enabled,
        category_code: item.preview && item.preview.category_code ? item.preview.category_code : ''
      };
    }).filter((item) => item.enabled);
    if (!note || !flow || !items.length || state.quickNoteBusy) {
      setStatus('Выберите строки для переноса', true);
      return;
    }
    state.quickNoteBusy = true;
    renderQuickNotes();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/quick-notes/' + note.id + '/convert', {
        flow_id: flow.id,
        date: els.quickNoteDate && els.quickNoteDate.value ? els.quickNoteDate.value : note.note_date,
        items: items
      });
      if (data.note) state.quickNotes = state.quickNotes.map((item) => String(item.id) === String(data.note.id) ? data.note : item);
      state.quickNotePreview = null;
      state.quickNoteModalOpen = false;
      resetQuickNoteComposer();
      await loadWorkspaceData({ preferLatest: true, scrollToBottom: true });
      if (state.activeScreen === 'quick-notes' && state.quickNotesStatus === 'idle') await loadQuickNotes();
      setStatus('Заметка перенесена в журнал');
    } catch (error) {
      setStatus(error.error || 'Перенос не выполнен', true);
    } finally {
      state.quickNoteBusy = false;
      renderQuickNotes();
    }
  }

  async function deleteQuickNote(noteId) {
    if (!state.workspaceId || !noteId || state.quickNoteBusy) return;
    const note = state.quickNotes.find((item) => String(item.id) === String(noteId));
    if (quickNoteIsLocked(note)) {
      setStatus('Перенесенная заметка защищена как источник журнала', true);
      return;
    }
    state.quickNoteBusy = true;
    renderQuickNotes();
    try {
      await v2Api('DELETE', '/api/workspaces/' + state.workspaceId + '/quick-notes/' + noteId);
      state.quickNotes = state.quickNotes.filter((note) => String(note.id) !== String(noteId));
      if (String(state.activeQuickNoteId) === String(noteId)) resetQuickNoteComposer();
      setStatus('Заметка удалена из истории');
    } catch (error) {
      setStatus(error.error || 'Заметка не удалена', true);
    } finally {
      state.quickNoteBusy = false;
      renderQuickNotes();
    }
  }

  function renderLayer1Storage() {
    if (!els.layer1Storage) return;
    const statusText = state.layer1SnapshotSaving
      ? 'Сохраняю снимок'
      : state.layer1SnapshotsStatus === 'loading'
      ? 'Загружаю снимки'
      : (state.layer1SnapshotsError || 'Сохраненные снимки');
    if (els.layer1StorageStatus) els.layer1StorageStatus.textContent = statusText;
    if (els.layer1StorageSave) els.layer1StorageSave.disabled = state.layer1SnapshotSaving || state.layer1SnapshotsStatus === 'loading';
    if (els.layer1StorageRefresh) els.layer1StorageRefresh.disabled = state.layer1SnapshotSaving || state.layer1SnapshotsStatus === 'loading';

    const packagesLoading = state.reportPackagesStatus === 'loading';
    const packagesError = state.reportPackagesError;
    if (state.layer1SnapshotsStatus === 'loading' || packagesLoading) {
      els.layer1Storage.innerHTML = '<div class="v2-summary-state">Загружаю сохраненные отчеты.</div>';
      return;
    }
    if (state.layer1SnapshotsError || packagesError) {
      els.layer1Storage.innerHTML = '<div class="v2-summary-state">' + escapeHtml(state.layer1SnapshotsError || packagesError) + '</div>';
      return;
    }
    const snapshots = state.layer1Snapshots || [];
    const packages = state.reportPackages || [];
    const snapshotsHtml = snapshots.length
      ? snapshots.map((snapshot) => renderSnapshotReadback(snapshot)).join('')
      : '<div class="v2-summary-state">Для этого периода сохраненных снимков пока нет.</div>';
    const packagesHtml = packages.length
      ? packages.map((item) => renderReportPackageStorageItem(item)).join('')
      : '<div class="v2-summary-state">Объединенных отчетов пока нет.</div>';

    els.layer1Storage.innerHTML = '<section class="v2-summary-block"><div class="v2-summary-block-head"><h3>Пакеты отчетов</h3><span>HTML / версии</span></div>'
      + packagesHtml
      + '</section>'
      + '<section class="v2-summary-block"><div class="v2-summary-block-head"><h3>Снимки сводки</h3><span>Layer 1</span></div>'
      + snapshotsHtml
      + '</section>';
  }

  function renderReportPackageStorageItem(item) {
    const summary = item.summary || {};
    const totals = summary.totals || {};
    const from = firstValue(item, ['period.from', 'start_date'], '');
    const to = firstValue(item, ['period.to', 'end_date'], from);
    const htmlUrl = item.html_url || ('/v2-report.php?type=package&id=' + encodeURIComponent(item.id || ''));
    const downloadUrl = urlWithParam(htmlUrl, 'download', '1');
    const tableUrl = urlWithParam(htmlUrl, 'format', 'xlsx');
    const statusClass = reportStatusClass(item.status);
    return '<article class="v2-snapshot-card v2-report-package-card ' + escapeHtml(statusClass) + '">'
      + '<div class="v2-snapshot-head"><div><h3>' + escapeHtml(text(item.title || 'Пакет отчетов')) + '</h3>'
      + '<span>' + escapeHtml(text(from) + ' - ' + text(to) + ' · ' + displayNumber(item.fragment_count || 0) + ' ' + reportWord(item.fragment_count || 0) + ' · ' + displayNumber(item.entry_count || item.entries_count || 0) + ' ' + recordWord(item.entry_count || item.entries_count || 0)) + '</span></div>'
      + '<span class="v2-report-package-status ' + escapeHtml(statusClass) + '">' + escapeHtml(reportFragmentStatusLabel(item.status)) + '</span></div>'
      + '<dl class="v2-snapshot-grid">'
      + '<div><dt>Входящий</dt><dd>' + escapeHtml(money(totals.opening_cash)) + '</dd></div>'
      + '<div><dt>Расход</dt><dd>' + escapeHtml(money(totals.cash_expense)) + '</dd></div>'
      + '<div><dt>Остаток</dt><dd>' + escapeHtml(money(totals.ending_cash)) + '</dd></div>'
      + '<div><dt>Создан</dt><dd>' + escapeHtml(text(item.created_at || item.generated_at || '—')) + '</dd></div>'
      + '</dl>'
      + '<div class="v2-report-package-actions">'
      + '<a class="v2-summary-source-link" href="' + escapeHtml(htmlUrl) + '" target="_blank" rel="noopener">HTML</a>'
      + '<a class="v2-summary-source-link" href="' + escapeHtml(downloadUrl) + '">Скачать</a>'
      + '<a class="v2-summary-source-link" href="' + escapeHtml(tableUrl) + '">Таблица</a>'
      + '</div>'
      + '</article>';
  }

  function renderSnapshotReadback(snapshot) {
    const summary = snapshot.summary || {};
    const totals = summary.totals || {};
    const basis = firstValue(snapshot, ['source_trace.basis.opening_cash', 'summary.source_trace.basis.opening_cash'], null);
    const basisRows = Array.isArray(basis) ? basis : (basis ? [basis] : []);
    const basisRow = basisRows[0] || {};
    const periodYear = snapshot.year || firstValue(summary, ['header.period.year'], '');
    const periodMonth = snapshot.month || firstValue(summary, ['header.period.month'], '');
    const period = String(periodYear) + '-' + String(periodMonth).padStart(2, '0');
    const hash = text(snapshot.content_hash || '').slice(0, 12);
    const sourceCount = Array.isArray(snapshot.source_entry_ids) ? snapshot.source_entry_ids.length : 0;
    const correctionCount = Array.isArray(snapshot.correction_ids) ? snapshot.correction_ids.length : 0;
    const basisAmount = basisRow.flow_opening_balance === undefined ? basisRow.amount : basisRow.flow_opening_balance;
    const rows = [
      ['opening_cash', money(totals.opening_cash)],
      ['ending_cash', money(totals.ending_cash)],
      ['source_ids', displayNumber(sourceCount)],
      ['correction_ids', displayNumber(correctionCount)],
      ['basis_opening', money(basisAmount)],
      ['prior_delta', money(basisRow.prior_cash_delta)],
    ];
    return '<section class="v2-summary-snapshot" data-v2-layer1-snapshot="' + escapeHtml(snapshot.id || '') + '">'
      + '<div class="v2-summary-snapshot-head">'
      + '<div><h3>Снимок v' + escapeHtml(displayNumber(snapshot.version)) + ' · ' + escapeHtml(period) + '</h3>'
      + '<span>' + escapeHtml(valueLabel(snapshot.status)) + ' · сохранен ' + escapeHtml(text(snapshot.stored_at)) + '</span></div>'
      + '<span class="v2-summary-snapshot-hash">' + escapeHtml(hash) + '</span>'
      + '</div>'
      + '<div class="v2-summary-meta-grid">'
      + rows.map(([label, value]) => '<div class="v2-summary-meta-item"><span>' + escapeHtml(valueLabel(label)) + '</span><strong>' + escapeHtml(value) + '</strong></div>').join('')
      + '</div>'
      + '<div class="v2-summary-snapshot-note">' + escapeHtml(text(snapshot.comment || 'Автоматический снимок только для чтения')) + '</div>'
      + '</section>';
  }

  function entryMeta(entry) {
    const parts = [
      entry.date,
      entry.flow && entry.flow.name,
      valueLabel(entry.status),
      categoryDisplayLabel(entry)
    ].filter(Boolean);
    return parts.join(' · ');
  }

  function draftText() {
    const entry = editingEntry();
    if (entry && els.rawText && els.rawText.value === entry.raw_text) return '';
    const preview = previewingEntry();
    if (preview && els.rawText && els.rawText.value === preview.raw_text) return '';
    return els.rawText ? (els.rawText.value || '') : '';
  }

  function draftRowNumber() {
    return activeFlowEntries().length + 1;
  }

  function journalDraftRowHtml() {
    const raw = draftText();
    const rowNumber = draftRowNumber();
    return '<button class="v2-entry v2-entry-draft" type="button" tabindex="-1" data-v2-draft-row data-v2-row-number="' + rowNumber + '" aria-label="Новая запись, строка ' + rowNumber + '" aria-live="polite">'
      + '<span class="v2-row-number" data-v2-row-number-label>' + rowNumber + '</span>'
      + '<div class="v2-entry-main"><strong data-v2-draft-text>' + escapeHtml(raw || 'Новая запись') + '</strong></div>'
      + '<div class="v2-entry-amount" data-v2-draft-amount></div>'
      + '</button>';
  }

  function checkDraftRowHtml() {
    const raw = draftText();
    const rowNumber = draftRowNumber();
    const cells = [
      rowNumber,
      '',
      raw,
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      ''
    ];
    return '<button class="v2-check-row v2-check-draft-row" type="button" tabindex="-1" data-v2-check-draft-row data-v2-row-number="' + rowNumber + '" aria-label="Новая запись, строка ' + rowNumber + '" aria-live="polite">'
      + cells.map((cell, cellIndex) => '<span' + (cellIndex === 0 ? ' class="v2-row-number"' : (cellIndex === 2 ? ' data-v2-check-draft-text' : '')) + '>' + escapeHtml(text(cell)) + '</span>').join('')
      + '</button>';
  }

  function activeFlowRenderItems() {
    const entries = activeFlowEntries();
    if (state.reportSelectionMode) {
      return entries.map((entry, index) => ({ type: 'entry', entry, index, childReportId: '' }));
    }
    const items = [];
    for (let index = 0; index < entries.length; index += 1) {
      const entry = entries[index];
      const reportId = reportLockId(entry);
      if (!reportId) {
        items.push({ type: 'entry', entry, index, childReportId: '' });
        continue;
      }
      const groupEntries = [entry];
      let endIndex = index;
      while (endIndex + 1 < entries.length && reportLockId(entries[endIndex + 1]) === reportId) {
        endIndex += 1;
        groupEntries.push(entries[endIndex]);
      }
      const group = Object.assign({
        startIndex: index,
        endIndex
      }, reportGroupMeta(groupEntries, reportId));
      const expanded = Boolean(state.reportExpandedIds[reportId]);
      items.push({ type: 'report', group, expanded });
      if (expanded) {
        groupEntries.forEach((childEntry, childOffset) => {
          items.push({
            type: 'entry',
            entry: childEntry,
            index: index + childOffset,
            childReportId: reportId
          });
        });
      }
      index = endIndex;
    }
    return items;
  }

  function reportRowNumber(group) {
    const from = group.startIndex + 1;
    const to = group.endIndex + 1;
    return from === to ? String(from) : String(from) + '-' + String(to);
  }

  function renderJournalEntryRow(entry, index, childReportId) {
    const rowNumber = index + 1;
    const amountClass = entry.direction === 'in' ? 'is-in' : (entry.direction === 'out' ? 'is-out' : '');
    const rowClasses = ['v2-entry'];
    if (entry.status === 'unrecognized') rowClasses.push('is-unrecognized');
    if (entry.status === 'other_review' && entry.category_code === 'other') rowClasses.push('is-review');
    if (entry.accounting_section === 'lower_accounting') rowClasses.push('is-lower-accounting');
    if (entry.id === state.activeEntryId) rowClasses.push('is-active');
    if (entry.id === state.selectedEntryId) rowClasses.push('is-selected');
    if (entryIsInReportSelection(entry.id)) rowClasses.push('is-report-range');
    if (entry.id === state.reportSelectionStartId || entry.id === state.reportSelectionEndId) rowClasses.push('is-report-edge');
    if (entry.report_lock) rowClasses.push('is-report-locked');
    if (childReportId) rowClasses.push('is-report-child');
    const lockBadge = entry.report_lock ? '<small class="v2-report-lock-badge" data-v2-report-lock-open title="Открыть созданный отчет">В отчете</small>' : '';
    return '<button class="' + rowClasses.join(' ') + '" type="button" tabindex="-1" data-v2-entry-select data-v2-entry-id="' + escapeHtml(entry.id) + '" data-v2-row-number="' + rowNumber + '" data-v2-entry-raw-text="' + escapeHtml(entry.raw_text) + '" aria-label="Строка ' + rowNumber + ': ' + escapeHtml(entry.raw_text) + '">'
      + '<span class="v2-row-number" data-v2-row-number-label>' + rowNumber + '</span>'
      + '<div class="v2-entry-main"><strong>' + escapeHtml(entry.raw_text) + '</strong>' + lockBadge + '</div>'
      + '<div class="v2-entry-amount ' + amountClass + '">' + money(entry.amount) + '</div>'
      + '</button>';
  }

  function renderJournalReportRow(group, expanded) {
    const classes = ['v2-entry', 'v2-report-row'];
    const statusClass = reportStatusClass(group.status);
    if (statusClass) classes.push(statusClass);
    if (state.reportPackageSelectionIds[group.reportId]) classes.push('is-report-package-selected');
    if (group.entries.some((entry) => entry.id === state.activeEntryId)) classes.push('is-active');
    const visibleCount = group.entries.length;
    const totalCount = group.entryCount || visibleCount;
    const visibleMeta = totalCount > visibleCount ? ' · показано ' + displayNumber(visibleCount) + ' из ' + displayNumber(totalCount) : '';
    const meta = 'период ' + group.period + ' · закрытый отчет · ' + displayNumber(totalCount) + ' ' + recordWord(totalCount)
      + visibleMeta
      + (group.version ? ' · версия ' + displayNumber(group.version) : '')
      + ' · остаток ' + money(group.balance);
    return '<div class="' + classes.join(' ') + '" role="button" tabindex="-1" data-v2-report-row data-v2-report-id="' + escapeHtml(group.reportId) + '" aria-expanded="' + (expanded ? 'true' : 'false') + '">'
      + '<span class="v2-row-number v2-report-range-number" data-v2-row-number-label title="Строки ' + escapeHtml(reportRowNumber(group)) + '">' + escapeHtml(reportRowNumber(group)) + '</span>'
      + '<div class="v2-entry-main"><strong>' + escapeHtml(group.label) + '</strong><span class="v2-report-lock-badge ' + escapeHtml(statusClass) + '" title="Статус отчета">' + escapeHtml(reportFragmentStatusLabel(group.status)) + '</span><small>' + escapeHtml(meta) + '</small></div>'
      + '<div class="v2-report-row-actions">'
      + '<button type="button" data-v2-report-row-open title="Открыть отчет">Открыть</button>'
      + '<button type="button" data-v2-report-row-versions title="Открыть отчет и версии">Версии</button>'
      + '</div>'
      + '</div>';
  }

  function renderReportArchiveDocument(group) {
    const statusClass = reportStatusClass(group.status);
    const closedDate = group.closedAt || group.createdAt;
    const title = group.title || group.label || ('Отчет от ' + formatReportDate(closedDate));
    const count = group.entryCount || group.entries.length;
    const visibleMeta = count > group.entries.length ? ' · в этом месяце ' + displayNumber(group.entries.length) : '';
    const meta = 'Период ' + group.period + ' · ' + displayNumber(count) + ' ' + recordWord(count) + visibleMeta;
    const balance = group.balance === null || group.balance === undefined ? '—' : money(group.balance);
    return '<article class="v2-report-document ' + escapeHtml(statusClass) + '" data-v2-report-row data-v2-report-id="' + escapeHtml(group.reportId) + '">'
      + '<div class="v2-report-document-icon" aria-hidden="true">F</div>'
      + '<div class="v2-report-document-main">'
      + '<div class="v2-report-document-title"><strong>' + escapeHtml(title) + '</strong><span class="v2-report-lock-badge ' + escapeHtml(statusClass) + '">' + escapeHtml(reportFragmentStatusLabel(group.status)) + '</span></div>'
      + '<span>' + escapeHtml(meta) + '</span>'
      + '</div>'
      + '<div class="v2-report-document-balance"><span>Остаток</span><strong>' + escapeHtml(balance) + '</strong></div>'
      + '<div class="v2-report-row-actions">'
      + '<button type="button" data-v2-report-row-open title="Открыть отчет">Открыть</button>'
      + '<button type="button" data-v2-report-row-versions title="Открыть отчет и версии">Версии</button>'
      + '</div>'
      + '</article>';
  }

  function renderReportArchiveIndexRow(group) {
    const statusClass = reportStatusClass(group.status);
    const closedDate = group.closedAt || group.createdAt;
    return '<button class="v2-report-index-row ' + escapeHtml(statusClass) + '" type="button" data-v2-check-row data-v2-report-row data-v2-report-id="' + escapeHtml(group.reportId) + '">'
      + '<span class="v2-row-number v2-report-range-number" aria-hidden="true">' + escapeHtml(reportRowNumber(group)) + '</span>'
      + '<strong>' + escapeHtml(formatReportDate(closedDate)) + '</strong>'
      + '<span>' + escapeHtml(group.period) + '</span>'
      + '<small class="v2-report-lock-badge ' + escapeHtml(statusClass) + '">' + escapeHtml(reportFragmentStatusLabel(group.status)) + '</small>'
      + '</button>';
  }

  function renderFeed() {
    const entries = activeFlowEntries();
    const archiveView = reportArchiveViewInfo();
    if (archiveView.active) {
      els.count.textContent = archiveView.groups.length + ' ' + reportWord(archiveView.groups.length);
      let archiveBody = archiveView.groups.map((group) => renderReportArchiveDocument(group)).join('');
      if (state.reportArchiveStatus === 'loading') {
        archiveBody = '<div class="v2-entry-empty">Загружаю сохраненные отчеты...</div>';
      } else if (state.reportArchiveStatus === 'error') {
        archiveBody = '<div class="v2-entry-empty">Список отчетов не открыт: ' + escapeHtml(state.reportArchiveError) + '</div>';
      } else if (!archiveBody) {
        archiveBody = '<div class="v2-entry-empty">Сохраненных отчетов пока нет.</div>';
      }
      els.feed.innerHTML = '<div class="v2-report-archive-note"><strong>Режим просмотра отчетов</strong><span>Это отдельный просмотр сохраненных документов. Рабочие записи остаются в оперативном журнале.</span></div>'
        + archiveBody;
      syncJournalHeaderGutter();
      return;
    }
    els.count.textContent = entries.length + ' ' + recordWord(entries.length);
    if (!entries.length) {
      els.feed.innerHTML = '<div class="v2-entry-empty">В этом потоке записей пока нет. Введите первую строку ниже.</div>' + journalDraftRowHtml();
      syncJournalHeaderGutter();
      return;
    }
    els.feed.innerHTML = activeFlowRenderItems().map((item) => {
      if (item.type === 'report') return renderJournalReportRow(item.group, item.expanded);
      return renderJournalEntryRow(item.entry, item.index, item.childReportId);
    }).join('') + journalDraftRowHtml();
    syncJournalHeaderGutter();
  }

  function renderCheckEntryRow(entry, index, childReportId) {
    const rowNumber = index + 1;
    const row = [
      rowNumber,
      entry.date,
      entry.raw_text,
      entry.flow && valueLabel(entry.flow.type),
      entry.sign,
      entry.amount === null ? 'null' : money(entry.amount),
      valueLabel(entry.direction),
      categoryDisplayLabel(entry),
      accountingDisplayLabel(entry),
      entry.actor && entry.actor.name,
      entry.report_lock ? valueLabel(entry.status) + ' · в отчете' : valueLabel(entry.status),
      entry.balance_after === null ? '—' : money(entry.balance_after)
    ];
    const classes = ['v2-check-row'];
    if (entry.accounting_section === 'lower_accounting') classes.push('is-lower-accounting');
    if (entry.id === state.activeEntryId) classes.push('is-active');
    if (entry.id === state.selectedEntryId) classes.push('is-selected');
    if (entryIsInReportSelection(entry.id)) classes.push('is-report-range');
    if (entry.id === state.reportSelectionStartId || entry.id === state.reportSelectionEndId) classes.push('is-report-edge');
    if (entry.report_lock) classes.push('is-report-locked');
    if (childReportId) classes.push('is-report-child');
    return '<button class="' + classes.join(' ') + '" type="button" tabindex="-1" data-v2-check-row data-v2-entry-id="' + escapeHtml(entry.id) + '" data-v2-row-number="' + rowNumber + '" data-v2-entry-raw-text="' + escapeHtml(entry.raw_text) + '" aria-label="Строка ' + rowNumber + ': ' + escapeHtml(entry.raw_text) + '">'
      + row.map((cell, cellIndex) => {
        const className = cellIndex === 0 ? ' class="v2-row-number"' : '';
        if (cellIndex === 1 && entry.report_lock) {
          return '<span' + className + '>' + escapeHtml(text(cell)) + ' <small class="v2-report-lock-badge" data-v2-report-lock-open title="Открыть созданный отчет">В отчете</small></span>';
        }
        return '<span' + className + '>' + escapeHtml(text(cell)) + '</span>';
      }).join('')
      + '</button>';
  }

  function renderCheckReportRow(group, expanded) {
    const classes = ['v2-check-row', 'v2-report-row', 'v2-check-report-row'];
    const statusClass = reportStatusClass(group.status);
    if (statusClass) classes.push(statusClass);
    if (state.reportPackageSelectionIds[group.reportId]) classes.push('is-report-package-selected');
    if (group.entries.some((entry) => entry.id === state.activeEntryId)) classes.push('is-active');
    const row = [
      reportRowNumber(group),
      group.period,
      'Закрытый отчет',
      '',
      '',
      '',
      '',
      group.title || group.label,
      displayNumber(group.entries.length) + ' ' + recordWord(group.entries.length),
      group.version ? 'v' + displayNumber(group.version) : '',
      'Открыть',
      money(group.balance)
    ];
    return '<button class="' + classes.join(' ') + '" type="button" tabindex="-1" data-v2-check-row data-v2-report-row data-v2-report-id="' + escapeHtml(group.reportId) + '" aria-expanded="' + (expanded ? 'true' : 'false') + '">'
      + row.map((cell, cellIndex) => {
        const className = cellIndex === 0 ? ' class="v2-row-number v2-report-range-number" title="Строки ' + escapeHtml(reportRowNumber(group)) + '"' : '';
        if (cellIndex === 1) {
          return '<span' + className + '>' + escapeHtml(text(cell)) + ' <small class="v2-report-lock-badge ' + escapeHtml(statusClass) + '" title="Статус отчета">' + escapeHtml(reportFragmentStatusLabel(group.status)) + '</small></span>';
        }
        if (cellIndex === 10) {
          return '<span' + className + '>Открыть</span>';
        }
        return '<span' + className + '>' + escapeHtml(text(cell)) + '</span>';
      }).join('')
      + '</button>';
  }

  function renderCheckTable() {
    const entries = activeFlowEntries();
    const archiveView = reportArchiveViewInfo();
    if (archiveView.active) {
      if (state.reportArchiveStatus === 'loading') {
        els.checkTable.innerHTML = '<div class="v2-check-empty">Загружаю...</div>';
      } else if (state.reportArchiveStatus === 'error') {
        els.checkTable.innerHTML = '<div class="v2-check-empty">Ошибка списка отчетов</div>';
      } else {
        els.checkTable.innerHTML = archiveView.groups.length
          ? archiveView.groups.map((group) => renderReportArchiveIndexRow(group)).join('')
          : '<div class="v2-check-empty">Сохраненных отчетов нет.</div>';
      }
      syncStructuredHeaderScroll();
      return;
    }
    if (!entries.length) {
      els.checkTable.innerHTML = '<div class="v2-check-empty">В этом потоке нет записей для проверки.</div>' + checkDraftRowHtml();
      syncStructuredHeaderScroll();
      return;
    }
    els.checkTable.innerHTML = activeFlowRenderItems().map((item) => {
      if (item.type === 'report') return renderCheckReportRow(item.group, item.expanded);
      return renderCheckEntryRow(item.entry, item.index, item.childReportId);
    }).join('') + checkDraftRowHtml();
    syncStructuredHeaderScroll();
  }

  function renderCategoryOptions(entry) {
    const current = entry && entry.category_code ? entry.category_code : '';
    const emptyLabel = entry && entry.accounting_section === 'lower_accounting'
      ? accountingDisplayLabel(entry)
      : 'Категория не выбрана';
    els.categorySelect.innerHTML = '<option value="">' + escapeHtml(emptyLabel) + '</option>' + state.categories.map((category) => (
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
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>Файлов нет</div>';
      els.attachmentStatus.textContent = '';
      els.attachmentUpload.disabled = true;
      return;
    }

    const attachments = state.attachmentsByEntry[entry.id];
    els.attachmentStatus.textContent = state.attachmentStatus || '';
    els.attachmentUpload.disabled = state.attachmentBusy;
    els.attachmentInput.disabled = state.attachmentBusy;

    if (!attachments) {
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>Загружаю файлы</div>';
      return;
    }
    if (!attachments.length) {
      els.attachmentList.innerHTML = '<div class="v2-attachment-empty" data-v2-attachment-empty>Файлов нет</div>';
      return;
    }

    els.attachmentList.innerHTML = attachments.map((attachment) => (
      '<div class="v2-attachment-item" data-v2-attachment-item data-v2-attachment-id="' + escapeHtml(attachment.id) + '">'
        + '<div><strong>' + escapeHtml(attachment.file_name) + '</strong>'
        + '<small>' + escapeHtml([attachment.mime_type, formatBytes(attachment.size_bytes), attachment.created_at].filter(Boolean).join(' · ')) + '</small></div>'
        + '<button type="button" data-v2-attachment-delete data-v2-attachment-id="' + escapeHtml(attachment.id) + '">Удалить</button>'
      + '</div>'
    )).join('');
  }

  function archiveExceptionDetailText(entry) {
    const exception = entry && entry.settlement_archive_exception;
    if (!exception) return '';
    const parts = [];
    if (exception.closed_amount !== undefined && exception.closed_amount !== null) {
      parts.push('закрыто ' + money(exception.closed_amount));
    }
    if (exception.counterparty) parts.push('участник: ' + exception.counterparty);
    const breakdown = Array.isArray(exception.reported_breakdown) ? exception.reported_breakdown : [];
    if (breakdown.length) {
      parts.push('расшифровка: ' + breakdown.map((row) => {
        const label = categoryNameByCode(row.category_code || '') || valueLabel(row.category_code || '');
        return label + ' ' + money(row.amount || 0);
      }).join('; '));
    }
    if (exception.note) parts.push(exception.note);
    return parts.join('. ');
  }

  function renderDetail() {
    const entry = selectedEntry();
    const rowIndex = entry ? state.entries.findIndex((item) => item.id === entry.id) : -1;
    const rowNumber = rowIndex >= 0 ? rowIndex + 1 : '';
    if (els.detailKicker) {
      els.detailKicker.textContent = entry && rowNumber ? 'Запись ' + rowNumber : 'Детали записи';
    }
    if (els.detailTitleText) {
      els.detailTitleText.textContent = entry ? entry.raw_text : 'Детали записи';
    }
    els.selectedEntryId.textContent = entry
      ? [entry.date, entry.flow && valueLabel(entry.flow.type), entry.amount === null ? 'null' : money(entry.amount), valueLabel(entry.status)].filter(Boolean).join(' · ')
      : 'Запись не выбрана';
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
    const archiveExceptionText = archiveExceptionDetailText(entry);
    const rows = [
      ['raw_text', entry.raw_text],
      ['date', entry.date],
      ['flow', entry.flow && valueLabel(entry.flow.type)],
      ['sign', entry.sign || 'null'],
      ['amount', entry.amount === null ? 'null' : money(entry.amount)],
      ['direction', valueLabel(entry.direction)],
      ['category', categoryDisplayLabel(entry)],
      ['accounting', accountingDisplayLabel(entry)],
      ['actor', entry.actor && entry.actor.name],
      ['status', valueLabel(entry.status)],
      ['balance_after', entry.balance_after === null ? '—' : money(entry.balance_after)],
      ['source_type', valueLabel(entry.source_type)],
      ['archive_close', archiveExceptionText || '—'],
      ['notes', entry.notes || '—']
    ];
    els.detailFields.innerHTML = rows.map(([label, value]) => (
      '<div><dt>' + escapeHtml(valueLabel(label)) + '</dt><dd>' + escapeHtml(text(value)) + '</dd></div>'
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

  function renderDetailState() {
    const shouldOpen = state.detailOpen && Boolean(selectedEntry());
    state.detailOpen = shouldOpen;
    els.detailLayer.hidden = !shouldOpen;
    els.detail.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    document.body.classList.toggle('v2-detail-is-open', shouldOpen);
  }

  function renderSourceTrace() {
    const shouldOpen = state.sourceTraceOpen;
    if (!els.sourceLayer) return;
    els.sourceLayer.hidden = !shouldOpen;
    els.sourceDetail.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    if (!shouldOpen) return;
    els.sourceTitle.textContent = state.sourceTraceTitle || 'Записи-источники';
    els.sourceMeta.textContent = state.sourceTraceMeta || 'Оперативные записи';
    const rows = state.sourceTraceEntries || [];
    const editableRows = rows.filter(sourceTraceEntryCanEditCategory);
    const changedRows = editableRows.filter((entry) => {
      const entryId = entry && entry.id ? String(entry.id) : '';
      if (!entryId || !Object.prototype.hasOwnProperty.call(state.sourceCategoryDrafts, entryId)) return false;
      const draft = state.sourceCategoryDrafts[entryId] || '';
      return draft && draft !== (entry.category_code || '');
    });
    const bulkControls = editableRows.length
      ? '<div class="v2-source-bulk-actions">'
        + '<span>' + (changedRows.length ? ('Выбрано к сохранению: ' + changedRows.length) : 'Выберите категории у строк на проверке') + '</span>'
        + '<button type="button" data-v2-source-save-all ' + (!changedRows.length || state.sourceCategorySavingAll ? 'disabled' : '') + '>'
        + (state.sourceCategorySavingAll ? 'Сохраняю' : 'Сохранить выбранные')
        + '</button>'
        + '</div>'
      : '';
    els.sourceBody.innerHTML = (state.sourceTraceError
      ? '<div class="v2-summary-state">' + escapeHtml(state.sourceTraceError) + '</div>'
      : rows.length
      ? bulkControls + '<div class="v2-summary-source-list is-trace-modal">' + rows.map((entry, index) => sourceTraceCardHtml(entry, index)).join('') + '</div>'
      : '<div class="v2-summary-state">Связь с источником есть, но подходящие оперативные записи не вернулись.</div>');
  }

  function reportFragmentCloseDateValue(fragment) {
    if (!fragment) return selectedMonthParts().today || currentMonthParts().today;
    if (fragment.closed_at) return String(fragment.closed_at).slice(0, 10);
    return todayIso();
  }

  function reportFragmentRangeLabel(source) {
    const rangeLabel = text(firstValue(source, ['header.range_label', 'summary.header.range_label'], ''), '');
    if (rangeLabel) return rangeLabel;
    const startDate = text(firstValue(source, ['start_date', 'period.from', 'header.start_date', 'summary.header.start_date'], ''), '');
    const endDate = text(firstValue(source, ['end_date', 'period.to', 'header.end_date', 'summary.header.end_date'], startDate), startDate);
    return startDate && endDate && startDate !== endDate ? startDate + ' - ' + endDate : (startDate || 'выбранных строк');
  }

  function reportFragmentTitle(closeDate, source) {
    return 'Отчет от ' + formatReportDate(closeDate || todayIso()) + ' · период ' + reportFragmentRangeLabel(source || state.reportFragmentCreated || state.reportFragmentPreview);
  }

  function renderReportFragmentControls(fragment) {
    const visible = Boolean(fragment && fragment.html_url);
    if (els.reportFragmentControls) els.reportFragmentControls.hidden = !visible;
    if (!visible) return;
    const htmlUrl = fragment.html_url;
    const downloadUrl = urlWithParam(htmlUrl, 'download', '1');
    const printUrl = urlWithParam(htmlUrl, 'print', '1');
    const tableUrl = urlWithParam(htmlUrl, 'format', 'xlsx');
    if (els.reportFragmentHtml) els.reportFragmentHtml.href = htmlUrl;
    if (els.reportFragmentDownload) els.reportFragmentDownload.href = downloadUrl;
    if (els.reportFragmentTable) els.reportFragmentTable.href = tableUrl;
    if (els.reportFragmentPrint) {
      els.reportFragmentPrint.disabled = state.reportFragmentUpdating;
      els.reportFragmentPrint.setAttribute('data-v2-print-url', printUrl);
    }
    const isPackage = fragment.report_type === 'operational_package';
    const closeDateWrap = els.reportFragmentCloseDate ? els.reportFragmentCloseDate.closest('label') : null;
    if (closeDateWrap) closeDateWrap.hidden = isPackage;
    if (els.reportFragmentCloseDateSave) els.reportFragmentCloseDateSave.hidden = isPackage;
    if (els.reportFragmentSend) els.reportFragmentSend.hidden = isPackage;
    if (els.reportFragmentRebuild) els.reportFragmentRebuild.hidden = isPackage;
    if (els.reportFragmentRevision) els.reportFragmentRevision.hidden = isPackage;
    if (els.reportFragmentCancel) els.reportFragmentCancel.hidden = isPackage;
    if (isPackage) return;
    const isCancelled = fragment.status === 'superseded';
    const isNeedsUpdate = ['requires_update', 'outdated'].includes(fragment.status);
    const isRevision = fragment.status === 'returned_for_revision';
    if (els.reportFragmentCloseDate) {
      els.reportFragmentCloseDate.value = reportFragmentCloseDateValue(fragment);
      els.reportFragmentCloseDate.disabled = state.reportFragmentUpdating || isCancelled;
    }
    if (els.reportFragmentCloseDateSave) {
      els.reportFragmentCloseDateSave.disabled = state.reportFragmentUpdating || isCancelled;
      els.reportFragmentCloseDateSave.textContent = state.reportFragmentUpdating ? 'Сохраняю' : 'Сохранить дату';
    }
    if (els.reportFragmentSend) {
      const isSent = fragment.status === 'sent';
      els.reportFragmentSend.disabled = state.reportFragmentUpdating || isSent || isCancelled || isNeedsUpdate || isRevision;
      els.reportFragmentSend.textContent = isSent ? 'Отправлено' : 'Готов к отправке';
    }
    if (els.reportFragmentRebuild) {
      els.reportFragmentRebuild.hidden = !(isNeedsUpdate || isRevision);
      els.reportFragmentRebuild.disabled = state.reportFragmentUpdating || isCancelled;
      els.reportFragmentRebuild.textContent = state.reportFragmentUpdating ? 'Сохраняю' : 'Сохранить изменения отчета';
    }
    if (els.reportFragmentRevision) {
      els.reportFragmentRevision.disabled = state.reportFragmentUpdating || isCancelled || isRevision;
      els.reportFragmentRevision.textContent = isRevision ? 'Уже на доработке' : 'Вернуть на доработку';
    }
    if (els.reportFragmentCancel) {
      els.reportFragmentCancel.disabled = state.reportFragmentUpdating || isCancelled;
      els.reportFragmentCancel.classList.toggle('is-confirming', state.reportFragmentCancelConfirm && !isCancelled);
      els.reportFragmentCancel.textContent = isCancelled
        ? 'Заменен'
        : (state.reportFragmentCancelConfirm ? 'Точно отменить?' : 'Отменить отчет');
    }
  }

  function reportFragmentStatusLabel(status) {
    const labels = {
      draft: 'Черновик',
      created: 'Создан',
      closed: 'Закрыт',
      sent: 'Отправлен',
      returned_for_revision: 'На доработке',
      outdated: 'Требует обновления',
      requires_update: 'Требует обновления',
      superseded: 'Заменен',
      cancelled: 'Отменен'
    };
    return labels[status] || valueLabel(status);
  }

  function reportFragmentHeading(fragment, isPackage) {
    if (!fragment) return 'Сводка выбранных строк';
    if (fragment.title) return text(fragment.title);
    if (isPackage) return 'Пакет отчетов создан';
    if (fragment.status === 'requires_update' || fragment.status === 'outdated') return 'Отчет требует обновления';
    if (fragment.status === 'returned_for_revision') return 'Отчет на доработке';
    if (fragment.status === 'superseded') return 'Отчет отменен';
    if (fragment.status === 'sent') return 'Отчет готов к отправке';
    return 'Отчет создан';
  }

  function reportFragmentNotice(fragment) {
    if (!fragment) return '';
    if (state.reportFragmentCancelConfirm && fragment.status !== 'superseded') {
      return '<div class="v2-report-notice is-warning"><strong>Подтвердите отмену отчета</strong><span>Записи не удалятся и снова будут доступны в оперативном журнале.</span></div>';
    }
    if (fragment.status === 'requires_update' || fragment.status === 'outdated') {
      return '<div class="v2-report-notice is-warning"><strong>Отчет требует обновления</strong><span>После создания отчета строки были изменены. Верните отчет на доработку, проверьте записи и создайте новую версию перед отправкой.</span></div>';
    }
    if (fragment.status === 'returned_for_revision') {
      return '<div class="v2-report-notice is-revision"><strong>Отчет на доработке</strong><span>Границы отчета остаются в оперативном журнале. Внесите правки в строки и нажмите «Сохранить изменения отчета».</span></div>';
    }
    if (fragment.status === 'superseded') {
      return '<div class="v2-report-notice is-muted"><strong>Отчет отменен или заменен</strong><span>Эта версия сохранена в истории, но не закрывает строки журнала.</span></div>';
    }
    return '';
  }

  function reportStatusClass(status) {
    const normalized = text(status || 'created').toLowerCase().replace(/[^a-z0-9_]+/g, '_');
    if (['sent', 'closed'].includes(normalized)) return 'is-report-status-final';
    if (['returned_for_revision', 'revision', 'draft'].includes(normalized)) return 'is-report-status-revision';
    if (['outdated', 'requires_update', 'needs_update'].includes(normalized)) return 'is-report-status-warning';
    if (['superseded', 'cancelled', 'canceled'].includes(normalized)) return 'is-report-status-muted';
    return 'is-report-status-created';
  }

  function renderReportFragment() {
    if (!els.reportFragmentLayer) return;
    const shouldOpen = state.reportFragmentOpen;
    els.reportFragmentLayer.hidden = !shouldOpen;
    if (!shouldOpen) return;
    const report = state.reportFragmentPreview;
    const created = state.reportFragmentCreated;
    const isPackage = Boolean(created && created.report_type === 'operational_package');
    if (els.reportFragmentTitle) {
      els.reportFragmentTitle.textContent = reportFragmentHeading(created, isPackage);
    }
    if (els.reportFragmentMeta) {
      const fragmentCount = isPackage ? firstValue(created, ['fragment_count', 'summary.header.fragments_count'], 0) : 0;
      const count = report ? firstValue(report, ['header.entries_count'], reportSelectionIds().length) : reportSelectionIds().length;
      els.reportFragmentMeta.textContent = isPackage
        ? displayNumber(fragmentCount) + ' ' + reportWord(fragmentCount) + ' · ' + displayNumber(count) + ' ' + recordWord(count)
        : displayNumber(count) + ' ' + recordWord(count);
    }
    if (els.reportFragmentStatus) {
      const statusText = created
        ? reportFragmentStatusLabel(created.status) + (created.closed_at ? ' · дата отчета ' + String(created.closed_at).slice(0, 10) : '')
        : (state.reportFragmentStatus || '');
      els.reportFragmentStatus.textContent = statusText;
      els.reportFragmentStatus.className = 'v2-report-fragment-status ' + (created ? reportStatusClass(created.status) : '');
    }
    renderReportFragmentControls(created);
    if (els.reportFragmentCreate) {
      const lockedCount = report ? Number(firstValue(report, ['header.locked_count'], 0)) : 0;
      els.reportFragmentCreate.hidden = Boolean(created);
      els.reportFragmentCreate.disabled = state.reportFragmentLoading || state.reportFragmentCreating || !report || lockedCount > 0 || Boolean(created);
      els.reportFragmentCreate.textContent = state.reportFragmentCreating
        ? 'Создаю'
        : (created ? (isPackage ? 'Пакет создан' : 'Отчет создан') : 'Создать отчет к отправке');
    }
    if (state.reportFragmentLoading) {
      els.reportFragmentBody.innerHTML = '<div class="v2-summary-state">Собираю сводку выбранных строк.</div>';
      return;
    }
    if (!report) {
      els.reportFragmentBody.innerHTML = '<div class="v2-summary-state">' + escapeHtml(state.reportFragmentStatus || 'Выберите диапазон строк и нажмите «Сводка».') + '</div>';
      return;
    }
    const lockedCount = Number(firstValue(report, ['header.locked_count'], 0));
    const warning = lockedCount > 0
      ? '<div class="v2-summary-state is-warning">В выбранном диапазоне есть строки, которые уже включены в отчет. Создание нового отчета заблокировано.</div>'
      : '';
    els.reportFragmentBody.innerHTML = reportFragmentNotice(created) + warning + renderReportFragmentSummary(report);
  }

  function closeReportFragment(options) {
    state.reportFragmentOpen = false;
    state.reportFragmentLoading = false;
    state.reportFragmentCreating = false;
    state.reportFragmentCancelConfirm = false;
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = '';
    if (!options || options.render !== false) renderReportFragment();
  }

  async function previewReportFragment() {
    if (reportPackageSelectionGroups().length) {
      await createReportPackage();
      return;
    }
    const ids = reportSelectionIds();
    if (!state.workspaceId || !ids.length || state.reportFragmentLoading) return;
    const existingReportId = singleActiveReportIdForEntries(reportSelectionEntries());
    if (existingReportId) {
      await openReportFragmentById(existingReportId, 'Открываю уже созданный отчет');
      return;
    }
    state.reportFragmentOpen = true;
    state.reportFragmentLoading = true;
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = 'Собираю сводку';
    state.reportFragmentCancelConfirm = false;
    renderReportSelectionState();
    renderReportFragment();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/reports/operational-fragments/preview', {
        entry_ids: ids
      });
      state.reportFragmentPreview = data.report || (data.preview && data.preview.report) || null;
      const lockedCount = Number(firstValue(state.reportFragmentPreview, ['header.locked_count'], 0));
      state.reportFragmentStatus = lockedCount > 0
        ? 'Есть строки, уже включенные в другой отчет'
        : 'Предпросмотр готов. Можно создать отчет к отправке.';
      setStatus('Сводка выбранных строк готова');
    } catch (error) {
      state.reportFragmentPreview = null;
      state.reportFragmentStatus = error.error || 'Сводка выбранных строк недоступна';
      setStatus(state.reportFragmentStatus, true);
    } finally {
      state.reportFragmentLoading = false;
      renderReportSelectionState();
      renderReportFragment();
    }
  }

  async function createReportFragment() {
    const ids = reportSelectionIds();
    if (!state.workspaceId || !ids.length || state.reportFragmentCreating || !state.reportFragmentPreview) return;
    state.reportFragmentCreating = true;
    state.reportFragmentCancelConfirm = false;
    state.reportFragmentStatus = 'Создаю отчет и закрываю выбранные строки';
    renderReportFragment();
    try {
      const report = state.reportFragmentPreview;
      const title = reportFragmentTitle(todayIso(), report);
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/reports/operational-fragments', {
        entry_ids: ids,
        title,
        status: 'created'
      });
      state.reportFragmentCreated = data.fragment || data.report || null;
      state.reportFragmentStatus = 'Отчет создан. Выбранный фрагмент защищен от случайных правок.';
      state.reportSelectionMode = false;
      state.reportRangeLoading = false;
      state.reportSelectionStartId = '';
      state.reportSelectionEndId = '';
      await loadWorkspaceData({ allowLatestFallback: false, preferLatest: false });
      setStatus('Отчетный фрагмент создан');
    } catch (error) {
      state.reportFragmentStatus = error.error || 'Отчет не создан';
      setStatus(state.reportFragmentStatus, true);
    } finally {
      state.reportFragmentCreating = false;
      renderReportSelectionState();
      renderReportFragment();
    }
  }

  async function createReportPackage() {
    const groups = reportPackageSelectionGroups();
    const fragmentIds = groups.map((group) => group.reportId).filter(Boolean);
    if (!state.workspaceId || fragmentIds.length < 2 || state.reportFragmentCreating) {
      setStatus('Выберите минимум два закрытых отчета для объединения', true);
      return;
    }
    state.reportFragmentOpen = true;
    state.reportFragmentLoading = false;
    state.reportFragmentCreating = true;
    state.reportFragmentCancelConfirm = false;
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = 'Объединяю выбранные отчеты в один пакет';
    renderReportSelectionState();
    renderReportFragment();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/reports/operational-packages', {
        fragment_ids: fragmentIds,
        status: 'created'
      });
      const reportPackage = data.package || null;
      state.reportFragmentCreated = reportPackage;
      state.reportFragmentPreview = reportPackage ? (reportPackage.summary || reportPackage.snapshot || null) : null;
      state.reportFragmentStatus = reportPackage
        ? 'Пакет создан. В него вошли выбранные закрытые отчеты.'
        : 'Пакет не создан';
      state.reportSelectionMode = false;
      state.reportPackageSelectionIds = {};
      await loadWorkspaceData({ allowLatestFallback: false, preferLatest: false });
      setStatus(state.reportFragmentStatus, !reportPackage);
    } catch (error) {
      state.reportFragmentStatus = error.error || 'Пакет отчетов не создан';
      setStatus(state.reportFragmentStatus, true);
    } finally {
      state.reportFragmentCreating = false;
      renderReportSelectionState();
      renderReportFragment();
    }
  }

  async function updateReportFragment(payload, busyStatus, doneStatus, options) {
    const fragment = state.reportFragmentCreated;
    if (!fragment || !fragment.id || state.reportFragmentUpdating) return;
    state.reportFragmentUpdating = true;
    state.reportFragmentCancelConfirm = false;
    state.reportFragmentStatus = busyStatus;
    renderReportFragment();
    try {
      const data = await v2Api('PATCH', '/api/workspaces/' + state.workspaceId + '/reports/operational-fragments/' + fragment.id, payload);
      state.reportFragmentCreated = data.fragment || fragment;
      state.reportFragmentStatus = doneStatus;
      if (options && options.reloadAfter) {
        await loadWorkspaceData({ allowLatestFallback: false, preferLatest: false });
      }
      setStatus(doneStatus);
    } catch (error) {
      state.reportFragmentStatus = error.error || 'Отчет не обновлен';
      setStatus(state.reportFragmentStatus, true);
    } finally {
      state.reportFragmentUpdating = false;
      renderReportFragment();
    }
  }

  function saveReportFragmentCloseDate() {
    const closeDate = els.reportFragmentCloseDate ? els.reportFragmentCloseDate.value : '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(closeDate)) {
      setStatus('Укажите дату закрытия отчета', true);
      return;
    }
    updateReportFragment({ closed_date: closeDate, title: reportFragmentTitle(closeDate) }, 'Сохраняю дату закрытия', 'Дата закрытия отчета сохранена');
  }

  function markReportFragmentSent() {
    const closeDate = els.reportFragmentCloseDate && els.reportFragmentCloseDate.value
      ? els.reportFragmentCloseDate.value
      : reportFragmentCloseDateValue(state.reportFragmentCreated);
    updateReportFragment({ status: 'sent', closed_date: closeDate, title: reportFragmentTitle(closeDate) }, 'Фиксирую отчет как готовый к отправке', 'Отчет готов к отправке');
  }

  function rebuildReportFragmentFromEntries() {
    const fragment = state.reportFragmentCreated;
    if (!fragment || state.reportFragmentUpdating || fragment.status === 'superseded') return;
    updateReportFragment(
      { rebuild_from_entries: true, status: 'created', title: fragment.title || reportFragmentTitle(todayIso(), fragment.summary || fragment.snapshot) },
      'Сохраняю изменения отчета',
      'Изменения отчета сохранены. Проверьте и отметьте готовность к отправке.',
      { reloadAfter: true }
    );
  }

  function cancelReportFragment() {
    const fragment = state.reportFragmentCreated;
    if (!fragment || state.reportFragmentUpdating || fragment.status === 'superseded') return;
    if (!state.reportFragmentCancelConfirm) {
      state.reportFragmentCancelConfirm = true;
      state.reportFragmentStatus = 'Нажмите «Точно отменить?», чтобы отменить отчет. Записи не удалятся.';
      renderReportFragment();
      return;
    }
    updateReportFragment(
      { status: 'superseded' },
      'Отменяю отчет и освобождаю строки',
      'Отчет отменен. Строки снова доступны.',
      { reloadAfter: true }
    );
  }

  function returnReportFragmentForRevision() {
    const fragment = state.reportFragmentCreated;
    if (!fragment || state.reportFragmentUpdating || fragment.status === 'superseded' || fragment.status === 'returned_for_revision') return;
    const ok = window.confirm('Вернуть отчет на доработку? Отчет останется видимым в оперативной ленте со статусом «На доработке». После правок нажмите «Сохранить изменения отчета».');
    if (!ok) return;
    updateReportFragment(
      { status: 'returned_for_revision' },
      'Возвращаю отчет на доработку',
      'Отчет возвращен на доработку. Границы остались в оперативной ленте.',
      { reloadAfter: true }
    );
  }

  function reportSummaryFromFragment(fragment) {
    if (!fragment) return null;
    const summary = fragment.summary || fragment.snapshot || null;
    if (!summary || !fragment.source_trace || summary.source_trace) return summary;
    return Object.assign({}, summary, { source_trace: fragment.source_trace });
  }

  async function openReportFragmentById(reportId, statusText) {
    if (!state.workspaceId || !reportId || state.reportFragmentLoading) return;
    state.reportFragmentOpen = true;
    state.reportFragmentLoading = true;
    state.reportFragmentCreating = false;
    state.reportFragmentUpdating = false;
    state.reportFragmentCancelConfirm = false;
    state.reportFragmentPreview = null;
    state.reportFragmentCreated = null;
    state.reportFragmentStatus = statusText || 'Открываю отчет';
    renderReportFragment();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/operational-fragments/' + reportId);
      const fragment = data.fragment || data.report || null;
      state.reportFragmentCreated = fragment;
      state.reportFragmentPreview = reportSummaryFromFragment(fragment);
      state.reportFragmentStatus = fragment ? 'Открыт созданный отчет' : 'Отчет не найден';
      setStatus(state.reportFragmentStatus, !fragment);
    } catch (error) {
      state.reportFragmentCreated = null;
      state.reportFragmentPreview = null;
      state.reportFragmentStatus = error.error || 'Отчет не открыт';
      setStatus(state.reportFragmentStatus, true);
    } finally {
      state.reportFragmentLoading = false;
      renderReportFragment();
    }
  }

  function singleActiveReportIdForEntries(entries) {
    if (!entries || !entries.length) return '';
    let reportId = '';
    for (const entry of entries) {
      const lock = entry && entry.report_lock ? entry.report_lock : null;
      const nextId = lock && lock.report_id ? String(lock.report_id) : '';
      if (!nextId) return '';
      if (!reportId) {
        reportId = nextId;
      } else if (reportId !== nextId) {
        return '';
      }
    }
    return reportId;
  }

  function activeFlowReportContext() {
    const entries = activeFlowEntries();
    if (!entries.length) return null;
    const locked = entries.filter((entry) => entry && entry.report_lock && entry.report_lock.report_id);
    if (!locked.length) return null;
    const firstId = String(locked[0].report_lock.report_id);
    const sameReport = locked.every((entry) => String(entry.report_lock.report_id) === firstId);
    if (!sameReport) {
      return {
        reportId: '',
        label: 'В этой ленте есть строки из нескольких отчетов. Откройте нужную строку.'
      };
    }
    const title = locked[0].report_lock.title || 'созданный отчет';
    const allLocked = locked.length === entries.length;
    return {
      reportId: firstId,
      label: allLocked
        ? 'Лента закрыта отчетом: ' + title
        : 'Часть ленты закрыта отчетом: ' + title
    };
  }

  function openReportFragmentForEntryId(entryId) {
    const entry = state.entries.find((item) => String(item.id) === String(entryId));
    const reportId = entry && entry.report_lock ? entry.report_lock.report_id : '';
    if (!reportId) return false;
    openReportFragmentById(String(reportId), 'Открываю отчет по выбранной строке');
    return true;
  }

  function printReportFragment() {
    const fragment = state.reportFragmentCreated;
    if (!fragment || !fragment.html_url) return;
    const printUrl = urlWithParam(fragment.html_url, 'print', '1');
    window.open(printUrl, '_blank', 'noopener');
  }

  function renderFocusMode() {
    els.workspaceShell.classList.toggle('is-check-focused', state.focusedSurface === 'check');
    els.workspaceShell.classList.toggle('is-journal-focused', state.focusedSurface === 'journal');
    syncJournalHeaderGutter();
    renderMobileViewNavigation();
  }

  function renderReportArchiveViewState() {
    const archiveView = reportArchiveViewInfo();
    const active = archiveView.active;
    if (els.workspaceShell) els.workspaceShell.classList.toggle('is-report-archive-view', active);
    if (els.workspaceShell) els.workspaceShell.classList.toggle('is-all-feed-view', !active && state.activeScreen === 'operational' && state.feedView === 'all');
    if (els.journalTitle) els.journalTitle.textContent = active ? 'Просмотр отчетов' : (state.feedView === 'all' ? 'Вся лента' : 'Оперативный журнал');
    if (els.checkTitle) els.checkTitle.textContent = active ? 'Индекс отчетов' : (state.feedView === 'all' ? 'Проверка всей ленты' : 'Структурная проверка');
    if (els.checkMeta) {
      els.checkMeta.textContent = active
        ? displayNumber(archiveView.groups.length) + ' ' + reportWord(archiveView.groups.length)
        : (state.feedView === 'all' ? 'Вся история' : 'Те же записи');
    }
    if (els.form) els.form.classList.toggle('v2-hidden', state.activeScreen !== 'operational' || active);
    if (els.previewPanel) els.previewPanel.classList.toggle('v2-hidden', state.activeScreen !== 'operational' || active);
    if (els.checkHeader) els.checkHeader.hidden = active;
  }

  function clearFocusOnlyClick() {
    if (state.focusOnlyClickTimer) window.clearTimeout(state.focusOnlyClickTimer);
    state.focusOnlyClickTimer = 0;
    state.focusOnlyClick = null;
  }

  function renderAll() {
    renderScreenNavigation();
    renderMobileViewNavigation();
    renderSummaryTabs();
    renderPeriodState();
    renderWorkspaces();
    renderFlows();
    renderSummary();
    renderLayer1Information();
    renderLayer1Storage();
    renderDictionaryTraining();
    renderQuickNotes();
    renderEmployeeMode();
    renderFeed();
    renderCheckTable();
    renderReportArchiveViewState();
    renderDetail();
    renderDetailState();
    renderSourceTrace();
    renderReportSelectionState();
    renderReportFragment();
    renderFocusMode();
  }

  function populateArchivePicker(defaultToSelected) {
    if (!els.archiveYear || !els.archiveMonth) return;
    const current = currentMonthParts();
    const selected = selectedMonthParts();
    const defaultMonth = defaultToSelected ? selected : previousMonthParts();
    const startYear = Math.min(2022, current.year - 4, selected.year, defaultMonth.year);
    const endYear = Math.max(current.year + 1, selected.year, defaultMonth.year);
    els.archiveYear.innerHTML = '';
    for (let year = endYear; year >= startYear; year -= 1) {
      const option = document.createElement('option');
      option.value = String(year);
      option.textContent = String(year);
      els.archiveYear.appendChild(option);
    }
    els.archiveMonth.innerHTML = '';
    for (let month = 1; month <= 12; month += 1) {
      const option = document.createElement('option');
      option.value = String(month);
      option.textContent = new Date(2000, month - 1, 1).toLocaleString('ru-RU', { month: 'long' });
      els.archiveMonth.appendChild(option);
    }
    els.archiveYear.value = String(defaultMonth.year);
    els.archiveMonth.value = String(defaultMonth.month);
  }

  function openArchivePicker(options) {
    const defaultToSelected = Boolean(options && options.defaultToSelected);
    populateArchivePicker(defaultToSelected);
    state.archiveOpen = true;
    renderPeriodState();
    window.requestAnimationFrame(() => {
      if (els.archiveYear) els.archiveYear.focus({ preventScroll: true });
    });
  }

  function closeArchivePicker() {
    state.archiveOpen = false;
    renderPeriodState();
  }

  function hasDirtyEdit() {
    const entry = editingEntry();
    if (!entry) return false;
    return (els.rawText.value.trim() !== entry.raw_text) || ((els.date.value || entry.date) !== entry.date);
  }

  function hasPendingCreateEntry() {
    if (state.activeScreen !== 'operational') return false;
    if (editingEntry() || previewingEntry() || state.saving || state.editBusy) return false;
    return looksLikeSavableEntry(els.rawText.value);
  }

  async function savePendingCreateEntry() {
    if (!hasPendingCreateEntry()) return true;
    const raw = els.rawText.value.trim();
    await submitEntry(null, { auto: true });
    return els.rawText.value.trim() !== raw;
  }

  function selectedClosedPeriodConfirmed() {
    return Boolean(state.closedEditConfirmedPeriods[periodKey(selectedMonthParts())]);
  }

  function entryPeriodKey(entry) {
    const parts = entry && entry.date ? monthPartsFromDate(entry.date) : null;
    return parts ? periodKey(parts) : '';
  }

  function entryClosedPeriodConfirmed(entry) {
    const key = entryPeriodKey(entry);
    return Boolean(key && state.closedEditConfirmedPeriods[key]);
  }

  function closedMonthErrorPeriodKey(error, entry) {
    if (error && error.year && error.month) {
      return String(error.year).padStart(4, '0') + '-' + String(error.month).padStart(2, '0');
    }
    return entryPeriodKey(entry) || periodKey(selectedMonthParts());
  }

  function requiresClosedMonthConfirmation() {
    return isCurrentMonthClosed() && !selectedClosedPeriodConfirmed();
  }

  function mutationPayload(payload) {
    const next = Object.assign({}, payload || {});
    if (isCurrentMonthClosed() && selectedClosedPeriodConfirmed()) {
      next.closed_month_decision = 'recalculate_chain';
    }
    return next;
  }

  function mutationPayloadForEntry(payload, entry) {
    const next = mutationPayload(payload);
    if (entryClosedPeriodConfirmed(entry)) {
      next.closed_month_decision = 'recalculate_chain';
    }
    if (entry && entry.report_lock && state.reportEditConfirmedEntryIds[entry.id]) {
      next.report_fragment_decision = 'recalculate_fragment';
    }
    return next;
  }

  function confirmReportFragmentMutation(entry, actionLabel) {
    if (!entry || !entry.report_lock || state.reportEditConfirmedEntryIds[entry.id]) return true;
    const reportTitle = entry.report_lock.title || 'созданный отчет';
    const ok = window.confirm('Эта строка уже включена в отчет «' + reportTitle + '». ' + actionLabel + ' и пересчитать отчетный фрагмент?');
    if (ok) state.reportEditConfirmedEntryIds[entry.id] = true;
    return ok;
  }

  function requestClosedMonthConfirmation(action, confirmedPeriodKey) {
    state.closedEditAction = action;
    state.closedEditPeriodKey = confirmedPeriodKey || periodKey(selectedMonthParts());
    state.closedEditOpen = true;
    renderPeriodState();
    window.requestAnimationFrame(() => {
      if (els.closedEditConfirm) els.closedEditConfirm.focus({ preventScroll: true });
    });
  }

  function cancelClosedMonthConfirmation() {
    state.closedEditOpen = false;
    state.closedEditAction = null;
    state.closedEditPeriodKey = '';
    renderPeriodState();
  }

  function confirmClosedMonthEdit() {
    const action = state.closedEditAction;
    state.closedEditConfirmedPeriods[state.closedEditPeriodKey || periodKey(selectedMonthParts())] = true;
    state.closedEditOpen = false;
    state.closedEditAction = null;
    state.closedEditPeriodKey = '';
    renderPeriodState();
    if (typeof action === 'function') action();
  }

  async function requestPeriodAction(action) {
    if (hasDirtyEdit()) {
      state.pendingPeriodAction = action;
      state.unsavedGuardOpen = true;
      renderPeriodState();
      window.requestAnimationFrame(() => {
        if (els.unsavedSave) els.unsavedSave.focus({ preventScroll: true });
      });
      return;
    }
    if (hasPendingCreateEntry()) {
      const saved = await savePendingCreateEntry();
      if (!saved) return;
    }
    action();
  }

  function cancelPendingPeriodAction() {
    state.pendingPeriodAction = null;
    state.unsavedGuardOpen = false;
    renderPeriodState();
  }

  async function saveAndRunPendingPeriodAction() {
    const action = state.pendingPeriodAction;
    if (requiresClosedMonthConfirmation()) {
      requestClosedMonthConfirmation(() => saveAndRunPendingPeriodAction());
      return;
    }
    const saved = await saveEntryEdit(null);
    if (!saved || editingEntry()) return;
    state.pendingPeriodAction = null;
    state.unsavedGuardOpen = false;
    renderPeriodState();
    if (typeof action === 'function') action();
  }

  function discardAndRunPendingPeriodAction() {
    const action = state.pendingPeriodAction;
    clearEntryEdit({ restoreDraft: true });
    state.pendingPeriodAction = null;
    state.unsavedGuardOpen = false;
    renderPeriodState();
    if (typeof action === 'function') action();
  }

  async function switchOperationalPeriod(parts, options) {
    const month = parts && parts.today ? parts : monthParts(parts.year, parts.month);
    state.period = month;
    state.closedEditOpen = false;
    state.closedEditAction = null;
    state.closedEditPeriodKey = '';
    state.closedMonthDecision = null;
    state.selectedEntryId = '';
    state.detailOpen = false;
    state.reportSelectionMode = false;
    state.reportArchiveView = false;
    state.feedView = 'month';
    state.reportSelectionStartId = '';
    state.reportSelectionEndId = '';
    closeReportFragment({ render: false });
    clearFocusOnlyClick();
    state.lastRowClick = null;
    state.previewEntryId = '';
    state.editingEntryId = '';
    state.editDraftBefore = null;
    state.previewDraftBefore = null;
    state.previewDateBefore = null;
    resetDeleteConfirmation();
    els.date.value = month.today;
    els.rawText.value = '';
    els.previewPanel.hidden = true;
    closeArchivePicker();
    setStatus('Загружаю ' + month.label);
    await loadWorkspaceData(Object.assign({ preferLatest: true, scrollToBottom: true }, options || {}));
    setStatus(isCurrentPeriod() ? 'Текущий месяц' : 'Архив: ' + month.label);
    focusCreateEntryInput({ clearPreview: false });
  }

  async function loadWorkspaceData(options) {
    const loadOptions = options || {};
    const month = selectedMonthParts();
    const workspaceId = state.workspaceId;
    state.lastRowClick = null;
    state.layer1Summary = null;
    state.layer1SummaryStatus = 'idle';
    state.layer1SummaryError = '';
    state.dictionaryQueue = null;
    state.dictionaryQueueStatus = 'idle';
    state.dictionaryQueueError = '';
    state.rawHistory = null;
    state.rawHistoryStatus = 'idle';
    state.rawHistoryError = '';
    state.rawHistoryConversion = null;
    state.rawHistoryConversionBusy = false;
    state.rawHistoryConversionError = '';
    state.dictionaryTrainingDecisions = [];
    state.dictionaryTrainingStatus = 'idle';
    state.dictionaryTrainingError = '';
    state.dictionaryTrainingBusyKey = '';
    state.activeTrainingSourceRowId = '';
    state.quickNotes = [];
    state.quickNotesStatus = 'idle';
    state.quickNotesError = '';
    state.activeQuickNoteId = '';
    state.quickNotePreview = null;
    state.quickNoteComposingNew = false;
    state.quickNoteModalOpen = false;
    state.quickNoteHistoryOpen = false;
    state.layer1Snapshots = [];
    state.layer1SnapshotsStatus = 'idle';
    state.layer1SnapshotsError = '';
    state.reportPackages = [];
    state.reportPackagesStatus = 'idle';
    state.reportPackagesError = '';
    state.reportArchiveFragments = [];
    state.reportArchiveStatus = 'idle';
    state.reportArchiveError = '';
    state.sourceEntryCache = {};
    closeSourceTrace();
    const flowsData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/flows');
    state.flows = flowsData.flows || [];
    if (!state.flows.some((flow) => flow.type === state.activeFlowType)) state.activeFlowType = 'cash';
    const categoriesData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/categories');
    state.categories = categoriesData.categories || [];
    const entriesQuery = state.reportSelectionMode
      ? reportRangeQuery()
      : (state.feedView === 'all'
          ? {}
          : {
              year: month.year,
              month: month.month
            });
    const entriesData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/entries', null, entriesQuery);
    state.entries = entriesData.entries || [];
    const flowEntries = activeFlowEntries();
    const latestEntry = flowEntries[flowEntries.length - 1] || state.entries[state.entries.length - 1] || null;
    if (loadOptions.preferLatest && latestEntry) {
      state.activeEntryId = latestEntry.id;
      state.focusedSurface = 'journal';
    }
    if (!state.activeEntryId && latestEntry) {
      state.activeEntryId = latestEntry.id;
    }
    if (state.activeEntryId && !state.entries.some((entry) => entry.id === state.activeEntryId)) {
      state.activeEntryId = latestEntry ? latestEntry.id : '';
    }
    if (state.editingEntryId && !state.entries.some((entry) => entry.id === state.editingEntryId)) {
      state.editingEntryId = '';
      els.rawText.value = '';
    }
    if (state.previewEntryId && !state.entries.some((entry) => entry.id === state.previewEntryId)) {
      state.previewEntryId = '';
      state.previewDraftBefore = null;
      state.previewDateBefore = null;
    }
    if (state.selectedEntryId && !state.entries.some((entry) => entry.id === state.selectedEntryId)) {
      state.selectedEntryId = '';
      state.detailOpen = false;
    }
    if (state.reportSelectionMode) {
      const flowIds = new Set(activeFlowEntries().map((entry) => String(entry.id)));
      const flowReportIds = new Set(activeFlowReportGroups().map((group) => String(group.reportId)));
      Object.keys(state.reportPackageSelectionIds).forEach((reportId) => {
        if (!flowReportIds.has(String(reportId))) delete state.reportPackageSelectionIds[reportId];
      });
      if (
        (state.reportSelectionStartId && !flowIds.has(String(state.reportSelectionStartId))) ||
        (state.reportSelectionEndId && !flowIds.has(String(state.reportSelectionEndId)))
      ) {
        state.reportSelectionMode = false;
        state.reportSelectionStartId = '';
        state.reportSelectionEndId = '';
        state.reportPackageSelectionIds = {};
        closeReportFragment({ render: false });
      }
    }
    ensureActiveEntryForCurrentFlow();
    const otherExpenseData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/other-expenses');
    state.otherExpenseQueue = otherExpenseData.entries || [];
    const summaryData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/summary');
    state.summary = summaryData.summary || null;
    await loadReportArchiveFragments({ force: true, silent: true });
    if (state.feedView !== 'all' && !state.entries.length && loadOptions.allowLatestFallback !== false && isCurrentPeriod()) {
      const latestMonth = monthPartsFromDate(state.summary && state.summary.latest_entry_date);
      if (latestMonth && periodKey(latestMonth) !== periodKey(month)) {
        state.period = latestMonth;
        setStatus('Открываю последние записи: ' + latestMonth.label);
        await loadWorkspaceData(Object.assign({}, loadOptions, {
          allowLatestFallback: false,
          preferLatest: true,
          scrollToBottom: true
        }));
        return;
      }
    }
    const reportData = await v2Api('GET', '/api/workspaces/' + workspaceId + '/reports/monthly', null, {
      year: month.year,
      month: month.month
    });
    state.monthReport = reportData.report || null;
    if (state.monthReport && state.monthReport.is_closed && loadOptions.autoAdvanceClosed !== false && isCurrentPeriod()) {
      const nextMonth = nextMonthParts(month);
      setStatus('Открываю новый рабочий месяц: ' + nextMonth.label);
      await switchOperationalPeriod(nextMonth, {
        allowLatestFallback: false,
        autoAdvanceClosed: false,
        preferLatest: true,
        scrollToBottom: true
      });
      return;
    }
    state.loadedWorkspaceId = workspaceId;
    renderAll();
    if (state.activeScreen === 'summary') {
      loadLayer1SummaryData();
      loadRawHistory();
      loadDictionaryReviewQueue();
      loadDictionaryTrainingDecisions();
    }
    if (state.activeScreen === 'training') {
      loadRawHistory();
      loadDictionaryReviewQueue();
      loadDictionaryTrainingDecisions();
    }
    if (state.activeScreen === 'quick-notes') {
      loadQuickNotes();
    }
    if (loadOptions.scrollToBottom) {
      scrollOperationalWindowToBottom();
    }
    if (state.selectedEntryId) {
      loadSelectedEntryAttachments();
    }
  }

  async function loadLayer1SummaryData() {
    if (!state.workspaceId || state.layer1SummaryStatus === 'loading') return;
    const range = summaryPeriodRange();
    state.layer1SummaryStatus = 'loading';
    state.layer1SummaryError = '';
    renderLayer1Information();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/layer1-summary', null, {
        year: range.from.year,
        month: range.from.month,
        from_year: range.from.year,
        from_month: range.from.month,
        to_year: range.to.year,
        to_month: range.to.month
      });
      state.layer1Summary = data.layer1_summary || data.layer1Summary || data.summary || data.report || null;
      state.layer1SummaryStatus = state.layer1Summary ? 'ready' : 'empty';
      state.layer1SummaryError = state.layer1Summary ? '' : 'Ответ сводки первого слоя не содержит данных отчета.';
    } catch (error) {
      state.layer1Summary = null;
      state.layer1SummaryStatus = 'error';
      state.layer1SummaryError = error.status === 404
        ? 'Основа данных для сводки первого слоя еще не готова.'
        : (error.error || 'Сводка первого слоя недоступна');
    }
    renderLayer1Information();
  }

  function applySummaryPeriodFilter(form) {
    const fromInput = form ? form.querySelector('[data-v2-summary-period-from]') : null;
    const toInput = form ? form.querySelector('[data-v2-summary-period-to]') : null;
    const from = monthPartsFromKey(fromInput ? fromInput.value : '');
    const to = monthPartsFromKey(toInput ? toInput.value : '');
    if (!from || !to) {
      state.layer1SummaryError = 'Выберите начальный и конечный месяц периода.';
      renderLayer1Information();
      return;
    }
    state.summaryPeriodFrom = periodKey(from);
    state.summaryPeriodTo = periodKey(to);
    state.summaryPeriodTouched = true;
    renderPeriodState();
    loadLayer1SummaryData();
  }

  async function loadDictionaryReviewQueue() {
    if (!state.workspaceId || state.dictionaryQueueStatus === 'loading') return;
    state.dictionaryQueueStatus = 'loading';
    state.dictionaryQueueError = '';
    renderDictionarySurfaces();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/dictionary-review-queue', null, {
        limit: 120,
        examples: 4
      });
      state.dictionaryQueue = data.queue || null;
      state.dictionaryQueueStatus = state.dictionaryQueue ? 'ready' : 'empty';
      state.dictionaryQueueError = state.dictionaryQueue ? '' : 'Ответ проверки словаря не содержит очередь.';
      const rows = dictionaryTrainingRows();
      if (rows.length && !rows.some((item) => item.sourceRowId === state.activeTrainingSourceRowId)) {
        const firstReadable = rows.find(dictionaryTrainingHasReadableExample) || rows[0];
        state.activeTrainingSourceRowId = firstReadable.sourceRowId;
      }
    } catch (error) {
      state.dictionaryQueue = null;
      state.dictionaryQueueStatus = 'error';
      state.dictionaryQueueError = error.error || 'Очередь проверки словаря недоступна';
    }
    renderDictionarySurfaces();
  }

  async function loadRawHistory() {
    if (!state.workspaceId || state.rawHistoryStatus === 'loading') return;
    state.rawHistoryStatus = 'loading';
    state.rawHistoryError = '';
    renderDictionarySurfaces();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/raw-history', null, {
        sources: 80,
        samples: 3
      });
      state.rawHistory = data.history || null;
      state.rawHistoryStatus = state.rawHistory ? 'ready' : 'empty';
      state.rawHistoryError = state.rawHistory ? '' : 'Ответ raw history не содержит данных истории.';
    } catch (error) {
      state.rawHistory = null;
      state.rawHistoryStatus = 'error';
      state.rawHistoryError = error.error || 'Импортированная история недоступна';
    }
    renderDictionarySurfaces();
  }

  async function convertRawHistoryBatch(mode) {
    if (!state.workspaceId || state.rawHistoryConversionBusy) return;
    const action = mode === 'commit' ? 'commit' : 'preview';
    if (action === 'commit') {
      const conversion = state.rawHistoryConversion || null;
      if (!conversion || conversion.mode !== 'preview' || Number(conversion.convertible || 0) <= 0) return;
      const confirmed = window.confirm('Конвертировать просмотренную партию истории в оперативные записи? Затронутые месяцы будут пересчитаны.');
      if (!confirmed) return;
    }
    state.rawHistoryConversionBusy = true;
    state.rawHistoryConversionError = '';
    renderDictionarySurfaces();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/raw-history/convert', {
        mode: action,
        limit: 25
      });
      state.rawHistoryConversion = data.conversion || null;
      if (action === 'commit') {
        const conversionResult = state.rawHistoryConversion;
        await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
        state.rawHistoryConversion = conversionResult;
        await loadRawHistory();
        if (state.activeScreen === 'summary') loadLayer1SummaryData();
      }
      setStatus(action === 'commit' ? 'Партия истории сконвертирована' : 'Предпросмотр истории готов');
    } catch (error) {
      state.rawHistoryConversionError = error.error || 'Конвертация истории не удалась';
      setStatus(state.rawHistoryConversionError, true);
    } finally {
      state.rawHistoryConversionBusy = false;
      renderDictionarySurfaces();
    }
  }

  async function loadDictionaryTrainingDecisions() {
    if (!state.workspaceId || state.dictionaryTrainingStatus === 'loading') return;
    state.dictionaryTrainingStatus = 'loading';
    state.dictionaryTrainingError = '';
    renderDictionarySurfaces();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/dictionary-training-decisions', null, {
        limit: 120
      });
      state.dictionaryTrainingDecisions = data.decisions || [];
      state.dictionaryTrainingStatus = 'ready';
    } catch (error) {
      state.dictionaryTrainingDecisions = [];
      state.dictionaryTrainingStatus = 'error';
      state.dictionaryTrainingError = error.error || 'Решения обучения словаря недоступны';
    }
    renderDictionarySurfaces();
  }

  async function loadLayer1Snapshots() {
    if (!state.workspaceId || state.layer1SnapshotsStatus === 'loading') return;
    const month = selectedMonthParts();
    state.layer1SnapshotsStatus = 'loading';
    state.layer1SnapshotsError = '';
    renderLayer1Storage();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/layer1-snapshots', null, {
        year: month.year,
        month: month.month
      });
      state.layer1Snapshots = data.snapshots || [];
      state.layer1SnapshotsStatus = 'ready';
    } catch (error) {
      state.layer1Snapshots = [];
      state.layer1SnapshotsStatus = 'error';
      state.layer1SnapshotsError = error.error || 'Сохраненные снимки недоступны';
    }
    renderLayer1Storage();
  }

  async function loadReportPackages() {
    if (!state.workspaceId || state.reportPackagesStatus === 'loading') return;
    state.reportPackagesStatus = 'loading';
    state.reportPackagesError = '';
    renderLayer1Storage();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/operational-packages', null, {
        limit: 50
      });
      state.reportPackages = data.packages || [];
      state.reportPackagesStatus = 'ready';
    } catch (error) {
      state.reportPackages = [];
      state.reportPackagesStatus = 'error';
      state.reportPackagesError = error.error || 'Пакеты отчетов недоступны';
    }
    renderLayer1Storage();
  }

  async function loadReportArchiveFragments(options) {
    if (!state.workspaceId) return;
    const loadOptions = options || {};
    if (state.reportArchiveStatus === 'loading') return;
    if (!loadOptions.force && state.reportArchiveStatus === 'ready') return;
    state.reportArchiveStatus = 'loading';
    state.reportArchiveError = '';
    if (!loadOptions.silent) renderAll();
    try {
      const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/operational-fragments', null, {
        limit: 100
      });
      state.reportArchiveFragments = sortReportArchiveFragments(data.fragments || []);
      state.reportArchiveStatus = 'ready';
    } catch (error) {
      state.reportArchiveFragments = [];
      state.reportArchiveStatus = 'error';
      state.reportArchiveError = error.error || 'Список отчетов недоступен';
    }
    if (!loadOptions.silent) renderAll();
  }

  async function saveLayer1Snapshot() {
    if (!state.workspaceId || state.layer1SnapshotSaving) return;
    const month = selectedMonthParts();
    state.layer1SnapshotSaving = true;
    renderLayer1Storage();
    try {
      await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/reports/layer1-snapshots', {
        year: month.year,
        month: month.month,
        comment: 'Сохранено из вкладки хранения'
      });
      await loadLayer1Snapshots();
      setStatus('Снимок первого слоя сохранен');
    } catch (error) {
      state.layer1SnapshotsError = error.error || 'Снимок не сохранен';
      setStatus(state.layer1SnapshotsError, true);
    } finally {
      state.layer1SnapshotSaving = false;
      renderLayer1Storage();
    }
  }

  async function toggleMonthClosure() {
    if (!state.workspaceId || state.monthActionBusy) return;
    const month = selectedMonthParts();
    const isClosed = isCurrentMonthClosed();
    const action = isClosed ? 'reopen' : 'close';
    const shouldOpenNextMonth = !isClosed && isCurrentPeriod();
    state.monthActionBusy = true;
    renderMonthClosure();
    setStatus(isClosed ? 'Открываю месяц' : 'Закрываю месяц');
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/months/' + month.year + '/' + month.month + '/' + action, {
        comment: isClosed ? null : 'Закрыто из оперативного журнала'
      });
      state.monthReport = data.report || null;
      if (shouldOpenNextMonth) {
        const nextMonth = nextMonthParts(month);
        await switchOperationalPeriod(nextMonth, { allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
        setStatus('Месяц закрыт. Открыт новый месяц: ' + nextMonth.label);
      } else {
        await loadWorkspaceData({ allowLatestFallback: false, preferLatest: true, scrollToBottom: true });
        setStatus(isClosed ? 'Месяц открыт' : 'Месяц закрыт');
      }
    } catch (error) {
      setStatus(error.error || (isClosed ? 'Не удалось открыть месяц' : 'Не удалось закрыть месяц'), true);
    } finally {
      state.monthActionBusy = false;
      renderMonthClosure();
      renderInputState();
    }
  }

  async function loadSelectedEntryAttachments() {
    const entry = selectedEntry();
    if (!entry) return;
    state.attachmentStatus = 'Загрузка';
    renderAttachments(entry);
    try {
      const data = await v2Api('GET', '/api/entries/' + entry.id + '/attachments');
      state.attachmentsByEntry[entry.id] = data.attachments || [];
      state.attachmentStatus = '';
    } catch (error) {
      state.attachmentStatus = error.error || 'Файлы не загружены';
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
    state.attachmentStatus = 'Прикрепляю';
    renderAttachments(entry);
    try {
      const data = await v2ApiFormData('POST', '/api/entries/' + entry.id + '/attachments', formData);
      const current = state.attachmentsByEntry[entry.id] || [];
      state.attachmentsByEntry[entry.id] = current.concat([data.attachment]);
      els.attachmentInput.value = '';
      state.attachmentStatus = 'Прикреплено';
      setStatus('Файл сохранен');
    } catch (error) {
      state.attachmentStatus = error.error || 'Файл не прикреплен';
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
    state.attachmentStatus = 'Удаляю';
    renderAttachments(entry);
    try {
      await v2Api('DELETE', '/api/attachments/' + attachmentId);
      state.attachmentsByEntry[entry.id] = (state.attachmentsByEntry[entry.id] || []).filter((attachment) => attachment.id !== attachmentId);
      state.attachmentStatus = 'Удалено';
      setStatus('Файл удален');
    } catch (error) {
      state.attachmentStatus = error.error || 'Файл не удален';
      setStatus(state.attachmentStatus, true);
    } finally {
      state.attachmentBusy = false;
      renderAttachments(entry);
    }
  }

  async function openWorkspace(workspaceId, screen, options) {
    const targetId = String(workspaceId || '').trim();
    if (!targetId) {
      state.activeScreen = 'hall';
      renderShellVisibility('hall');
      renderAll();
      setStatus('Выберите пространство', true);
      return;
    }
    const nextScreen = ['summary', 'training', 'quick-notes'].includes(screen) ? screen : 'operational';
    const openOptions = options || {};
    state.workspaceId = targetId;
    const workspace = state.workspaces.find((item) => String(item.id || '') === targetId);
    if (workspace && workspace.can_read_workspace === false) {
      await openEmployeeWorkspace(targetId);
      return;
    }
    state.activeScreen = nextScreen;
    state.openingWorkspaceId = targetId;
    state.selectedEntryId = '';
    state.detailOpen = false;
    closeSourceTrace();
    renderShellVisibility('workspace');
    renderAll();
    setStatus('Открываю пространство');
    try {
      await loadWorkspaceData({ preferLatest: true, scrollToBottom: true });
      if (openOptions.restoreDraft) {
        restoreDraft();
        updateDraftRows();
      }
      scrollOperationalWindowToBottom();
      if (nextScreen === 'operational') focusCreateEntryInput({ clearPreview: true });
      setStatus(navigator.onLine === false ? 'Офлайн: черновик сохранен локально' : 'Готово');
    } catch (error) {
      setStatus(error.error || 'Пространство не открылось', true);
    } finally {
      state.openingWorkspaceId = '';
      renderAll();
    }
  }

  async function loadApp() {
    loadMobileModePreference();
    applyRouteScreenPreference();
    const month = currentMonthParts();
    if (!state.period) state.period = month;
    els.date.value = selectedMonthParts().today;
    renderPeriodState();
    setStatus('Загрузка');
    try {
      const data = await v2Api('GET', '/api/workspaces');
      state.workspaces = data.workspaces || [];
      if (state.inviteToken) {
        await loadInvitePreview({ silent: true });
      }
      if (!state.workspaces.length) {
        renderShellVisibility(state.inviteToken ? 'hall' : 'create');
        renderAll();
        setStatus(state.inviteToken ? 'Примите приглашение' : 'Создайте пространство, чтобы начать записи');
        return;
      }
      state.workspaceId = preferredWorkspaceId();
      if (state.activeScreen === 'hall') {
        renderShellVisibility('hall');
        renderAll();
        setStatus('Выберите пространство');
        return;
      }
      await openWorkspace(state.workspaceId, state.activeScreen, { restoreDraft: true });
    } catch (error) {
      if (error.status === 401) {
        renderShellVisibility('auth');
        setStatus('Нужен вход', true);
        setAuthMessage('Введите email, чтобы получить код входа.');
      } else {
        renderShellVisibility(state.activeScreen === 'hall' ? 'hall' : 'workspace');
        setStatus(error.error || 'Загрузка не удалась', true);
      }
    }
  }

  async function createWorkspace(event) {
    event.preventDefault();
    const form = new FormData(els.createForm);
    setStatus('Создаю пространство');
    try {
      const data = await v2Api('POST', '/api/workspaces', {
        name: form.get('name') || 'Рабочее пространство FinDesk v2',
        type: 'yacht',
        currency: 'EUR',
        locale: 'ru',
        opening_cash: form.get('opening_cash') || null
      });
      state.workspaces = [data.workspace].concat(state.workspaces.filter((workspace) => workspace.id !== data.workspace.id));
      state.workspaceId = data.workspace.id;
      await openWorkspace(state.workspaceId, 'operational', { restoreDraft: true });
    } catch (error) {
      setStatus(error.error || 'Пространство не создано', true);
    }
  }

  async function loadInvitePreview(options) {
    if (!state.inviteToken || state.inviteBusy) return;
    state.inviteBusy = true;
    renderInvitePanel();
    try {
      const data = await v2Api('POST', '/api/workspace-invites/preview', { token: state.inviteToken });
      state.invitePreview = {
        invite: data.invite || null,
        workspace: data.workspace || null,
        email_matches: data.email_matches
      };
      if (state.invitePreview.email_matches === false) {
        setStatus('Email входа не совпадает с приглашением', true);
      } else if (!options || !options.silent) {
        setStatus('Приглашение найдено');
      }
    } catch (error) {
      state.invitePreview = null;
      setStatus(error.error || 'Приглашение не найдено', true);
    } finally {
      state.inviteBusy = false;
      renderInvitePanel();
    }
  }

  async function acceptInvite() {
    if (!state.inviteToken || state.inviteBusy) return;
    state.inviteBusy = true;
    renderInvitePanel();
    setStatus('Принимаю приглашение');
    try {
      const data = await v2Api('POST', '/api/workspace-invites/accept', { token: state.inviteToken });
      const workspace = data.workspace || null;
      const list = await v2Api('GET', '/api/workspaces');
      state.workspaces = list.workspaces || [];
      state.workspaceId = workspace && workspace.id ? workspace.id : preferredWorkspaceId();
      state.inviteToken = '';
      state.invitePreview = null;
      clearInviteFromUrl();
      state.activeScreen = 'hall';
      renderShellVisibility('hall');
      renderAll();
      setStatus('Приглашение принято');
    } catch (error) {
      setStatus(error.error || 'Приглашение не принято', true);
    } finally {
      state.inviteBusy = false;
      renderInvitePanel();
    }
  }

  function dismissInvite() {
    state.inviteToken = '';
    state.invitePreview = null;
    clearInviteFromUrl();
    renderInvitePanel();
    setStatus('Приглашение скрыто');
  }

  function clearInviteFromUrl() {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('invite')) return;
    url.searchParams.delete('invite');
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
  }

  async function createEmployeeInvite(workspaceId) {
    const workspace = state.workspaces.find((item) => String(item.id) === String(workspaceId));
    if (!workspace || state.inviteBusy) return;
    const email = window.prompt('Email сотрудника для приглашения');
    if (!email) return;
    state.inviteBusy = true;
    setStatus('Создаю приглашение');
    renderAll();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + workspaceId + '/invites', {
        email,
        role: 'employee',
        access_scope: 'own_entries'
      });
      const invite = data.invite || {};
      if (invite.url) {
        state.inviteLinks = Object.assign({}, state.inviteLinks, { [workspaceId]: invite.url });
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(invite.url).catch(() => {});
        }
      }
      setStatus(invite.url ? 'Ссылка создана и показана в карточке' : 'Приглашение создано');
    } catch (error) {
      setStatus(error.error || 'Приглашение не создано', true);
    } finally {
      state.inviteBusy = false;
      renderAll();
    }
  }

  async function createAccountableOfferFromHall(workspaceId) {
    const workspace = state.workspaces.find((item) => String(item.id) === String(workspaceId));
    if (!workspace || state.inviteBusy) return;
    const email = window.prompt('Email сотрудника, кому назначить деньги под отчет');
    if (!email) return;
    const amount = window.prompt('Сумма под отчет, EUR');
    if (!amount) return;
    const purpose = window.prompt('Коротко: на что выданы деньги', 'Под отчет');
    if (!purpose) return;
    state.inviteBusy = true;
    setStatus('Создаю оферту сотруднику');
    renderAll();
    try {
      await v2Api('POST', '/api/workspaces/' + workspaceId + '/accountable-offers', {
        email,
        amount,
        currency: workspace.currency || 'EUR',
        purpose
      });
      setStatus('Оферта создана. Сотрудник увидит ее в своем режиме.');
      if (state.accountableDashboards[String(workspaceId || '')]) {
        await loadHallAccountableDashboard(workspaceId);
      }
    } catch (error) {
      setStatus(error.error || 'Оферта не создана', true);
    } finally {
      state.inviteBusy = false;
      renderAll();
    }
  }

  async function deleteWorkspaceFromHall(workspaceId, workspaceName) {
    const id = String(workspaceId || '').trim();
    if (!id || state.inviteBusy) return;
    const name = String(workspaceName || 'это пространство');
    const phrase = 'я согласен. удалить';
    const answer = window.prompt(
      'Удалить пространство "' + name + '"?\n\n'
      + 'Пространство будет перенесено в корзину и будет храниться там еще 60 дней. '
      + 'В течение этого срока восстановление возможно через администрирование данных.\n\n'
      + 'Для подтверждения напишите точно:\n' + phrase
    );
    if (answer !== phrase) {
      setStatus('Удаление пространства отменено');
      return;
    }

    state.inviteBusy = true;
    renderAll();
    setStatus('Переношу пространство в корзину');
    try {
      await v2Api('DELETE', '/api/workspaces/' + id);
      state.workspaces = state.workspaces.filter((workspace) => String(workspace.id || '') !== id);
      delete state.accountableDashboards[id];
      delete state.accountableReportQueues[id];
      if (String(state.workspaceId || '') === id) {
        state.workspaceId = preferredWorkspaceId();
        state.entries = [];
        state.flows = [];
        state.categories = [];
        state.summary = null;
      }
      state.activeScreen = 'hall';
      renderShellVisibility('hall');
      renderAll();
      setStatus('Пространство удалено. Оно хранится в корзине 60 дней.');
    } catch (error) {
      setStatus(error.error || 'Пространство не удалено', true);
    } finally {
      state.inviteBusy = false;
      renderAll();
    }
  }

  async function loadHallAccountableReports(workspaceId) {
    const id = String(workspaceId || '').trim();
    if (!id) return;
    state.accountableReportQueues[id] = { status: 'loading', reports: [], error: '' };
    renderHall();
    setStatus('Загружаю отчеты сотрудников');
    try {
      const data = await v2Api('GET', '/api/workspaces/' + id + '/accountable-reports', null, { status: 'hall_open' });
      state.accountableReportQueues[id] = {
        status: 'ready',
        reports: data.reports || [],
        error: ''
      };
      setStatus('Отчеты сотрудников загружены');
    } catch (error) {
      state.accountableReportQueues[id] = {
        status: 'error',
        reports: [],
        error: error.error || 'Отчеты сотрудников не загружены'
      };
      setStatus(state.accountableReportQueues[id].error, true);
    } finally {
      renderHall();
    }
  }

  async function loadHallAccountableDashboard(workspaceId) {
    const id = String(workspaceId || '').trim();
    if (!id) return;
    state.accountableDashboardBusyId = id;
    state.accountableDashboards[id] = { status: 'loading', data: null, error: '' };
    renderHall();
    setStatus('Загружаю контроль под отчет');
    try {
      const data = await v2Api('GET', '/api/workspaces/' + id + '/accountable-dashboard');
      state.accountableDashboards[id] = {
        status: 'ready',
        data: data.dashboard || {},
        error: ''
      };
      setStatus('Контроль под отчет загружен');
    } catch (error) {
      state.accountableDashboards[id] = {
        status: 'error',
        data: null,
        error: error.error || 'Контроль под отчет не загружен'
      };
      setStatus(state.accountableDashboards[id].error, true);
    } finally {
      state.accountableDashboardBusyId = '';
      renderHall();
    }
  }

  async function openHallAccountableControl(workspaceId) {
    const id = String(workspaceId || '').trim();
    if (!id) return;
    await Promise.all([
      loadHallAccountableDashboard(id),
      loadHallAccountableReports(id)
    ]);
  }

  async function refreshHallAccountableControl(workspaceId) {
    const id = String(workspaceId || '').trim();
    if (!id) return;
    if (state.accountableDashboards[id]) {
      await loadHallAccountableDashboard(id);
    }
    if (state.accountableReportQueues[id]) {
      await loadHallAccountableReports(id);
    }
  }

  async function acceptHallAccountableReport(reportId, workspaceId) {
    const id = String(reportId || '').trim();
    const targetWorkspaceId = String(workspaceId || '').trim();
    if (!id || state.accountableReportBusyId) return;
    state.accountableReportBusyId = id;
    renderHall();
    setStatus('Принимаю отчет сотрудника');
    try {
      await v2Api('POST', '/api/accountable-reports/' + id + '/accept', {
        payment_method: 'cash',
        review_note: 'Принято из холла'
      });
      setStatus('Отчет принят. Расчет зафиксирован без изменения кассы и карты.');
      if (targetWorkspaceId) {
        await refreshHallAccountableControl(targetWorkspaceId);
      }
    } catch (error) {
      setStatus(error.error || 'Отчет не принят', true);
    } finally {
      state.accountableReportBusyId = '';
      renderHall();
    }
  }

  async function materializeHallAccountableReport(reportId, workspaceId) {
    const id = String(reportId || '').trim();
    const targetWorkspaceId = String(workspaceId || '').trim();
    if (!id || state.accountableReportBusyId) return;
    state.accountableReportBusyId = id;
    renderHall();
    setStatus('Проверяю отчет перед включением в учет');
    try {
      const previewData = await v2Api('POST', '/api/accountable-reports/' + id + '/materialization-preview', {});
      const preview = previewData.preview || {};
      const rows = Number(preview.eligible_row_count || 0);
      const amount = money(preview.projected_total_amount || 0);
      if (rows < 1) {
        setStatus('В отчете нет строк, которые можно включить в учет', true);
        return;
      }
      const confirmed = window.confirm(
        'Включить отчет сотрудника в общий учет?\n\n'
        + 'Будет добавлено строк: ' + rows + '\n'
        + 'Сумма для категорий: ' + amount + '\n\n'
        + 'Остаток наличных и карта не изменятся. Это перенос принятого отчета в сводку.'
      );
      if (!confirmed) {
        setStatus('Включение отчета отменено');
        return;
      }
      setStatus('Включаю отчет в общий учет');
      await v2Api('POST', '/api/accountable-reports/' + id + '/materialize', {});
      setStatus('Отчет включен в общий учет');
      if (targetWorkspaceId) {
        await refreshHallAccountableControl(targetWorkspaceId);
      }
    } catch (error) {
      setStatus(error.error || 'Отчет не включен в учет', true);
    } finally {
      state.accountableReportBusyId = '';
      renderHall();
    }
  }

  async function resolveHallAccountableSettlement(settlementId, workspaceId, status, amount) {
    const id = String(settlementId || '').trim();
    const targetWorkspaceId = String(workspaceId || '').trim();
    if (!id || state.accountableSettlementBusyId) return;
    const settlementStatus = String(status || '');
    const settlementAmount = Number(amount || 0);
    const actionText = settlementStatus === 'return_due' ? 'принять возврат в кассу' : 'выдать возмещение из кассы';
    const date = window.prompt('Дата физического движения по кассе', todayIso());
    if (!date) {
      setStatus('Закрытие расчета отменено');
      return;
    }
    const confirmed = window.confirm(
      'Закрыть расчет под отчет?\n\n'
      + 'Действие: ' + actionText + '\n'
      + 'Сумма: ' + money(settlementAmount) + '\n'
      + 'Дата: ' + date + '\n\n'
      + 'Будет создана кассовая запись и привязана к расчету сотрудника.'
    );
    if (!confirmed) {
      setStatus('Закрытие расчета отменено');
      return;
    }

    state.accountableSettlementBusyId = id;
    renderHall();
    setStatus('Закрываю расчет физическим движением кассы');
    try {
      await v2Api('POST', '/api/accountable-settlements/' + id + '/cash-resolve', {
        date,
        note: 'Закрыто из холла'
      });
      setStatus('Расчет закрыт, кассовая запись создана');
      if (targetWorkspaceId) {
        await refreshHallAccountableControl(targetWorkspaceId);
      }
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        setStatus('Месяц закрыт: кассовое движение нужно оформить после подтверждения закрытого месяца', true);
      } else {
        setStatus(error.error || 'Расчет не закрыт', true);
      }
    } finally {
      state.accountableSettlementBusyId = '';
      renderHall();
    }
  }

  async function openEmployeeWorkspace(workspaceId) {
    const targetId = String(workspaceId || '').trim();
    if (!targetId) return;
    state.workspaceId = targetId;
    state.loadedWorkspaceId = '';
    state.activeScreen = 'employee';
    state.openingWorkspaceId = targetId;
    state.employeeModeStatus = 'loading';
    state.employeeModeError = '';
    state.employeeMode = null;
    renderShellVisibility('employee');
    renderAll();
    setStatus('Открываю режим сотрудника');
    try {
      const data = await v2Api('GET', '/api/workspaces/' + targetId + '/employee-mode');
      state.employeeMode = {
        workspace: data.workspace || null,
        offers: data.offers || [],
        reports: data.reports || [],
        summary: data.summary || {}
      };
      state.employeeModeStatus = 'ready';
      setStatus('Режим сотрудника');
    } catch (error) {
      state.employeeModeStatus = 'error';
      state.employeeModeError = error.error || 'Режим сотрудника не открылся';
      setStatus(state.employeeModeError, true);
    } finally {
      state.openingWorkspaceId = '';
      renderAll();
    }
  }

  async function acceptEmployeeOffer(offerId) {
    const id = String(offerId || '').trim();
    if (!id || state.employeeOfferBusyId) return;
    state.employeeOfferBusyId = id;
    setStatus('Принимаю оферту');
    renderEmployeeMode();
    try {
      const data = await v2Api('POST', '/api/accountable-offers/' + id + '/accept', {});
      const updated = data.offer || null;
      if (updated && state.employeeMode && Array.isArray(state.employeeMode.offers)) {
        state.employeeMode.offers = state.employeeMode.offers.map((offer) => (
          String(offer.id || '') === String(updated.id || '') ? updated : offer
        ));
        let pendingTotal = 0;
        let acceptedTotal = 0;
        state.employeeMode.offers.forEach((offer) => {
          if (offer.status === 'pending_offer') pendingTotal += Number(offer.amount || 0);
          if (offer.status === 'accepted_by_employee') acceptedTotal += Number(offer.amount || 0);
        });
        state.employeeMode.summary = Object.assign({}, state.employeeMode.summary || {}, {
          pending_total: pendingTotal,
          accepted_total: acceptedTotal,
          open_offers: state.employeeMode.offers.filter((offer) => ['pending_offer', 'accepted_by_employee'].includes(String(offer.status || ''))).length
        });
      }
      setStatus('Оферта принята');
    } catch (error) {
      setStatus(error.error || 'Оферта не принята', true);
    } finally {
      state.employeeOfferBusyId = '';
      renderEmployeeMode();
    }
  }

  function addEmployeeReportDraftRow(offerId) {
    const id = String(offerId || '').trim();
    const form = id ? document.querySelector('[data-v2-employee-report-form="' + cssEscape(id) + '"]') : null;
    if (!form) return;
    const dateInput = form.querySelector('[data-v2-employee-report-date]');
    const amountInput = form.querySelector('[data-v2-employee-report-amount]');
    const textInput = form.querySelector('[data-v2-employee-report-text]');
    const date = dateInput ? String(dateInput.value || '').trim() : '';
    const amount = amountInput ? String(amountInput.value || '').trim() : '';
    const description = textInput ? String(textInput.value || '').trim() : '';
    if (!date || !amount || !description) {
      setStatus('Заполните дату, сумму и описание', true);
      return;
    }
    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      setStatus('Сумма отчета должна быть больше нуля', true);
      return;
    }
    employeeDraftRowsForOffer(id).push({
      date,
      description,
      amount: numericAmount.toFixed(2)
    });
    if (amountInput) amountInput.value = '';
    if (textInput) textInput.value = '';
    setStatus('Строка добавлена в отчет');
    renderEmployeeMode();
  }

  function removeEmployeeReportDraftRow(offerId, index) {
    const rows = employeeDraftRowsForOffer(offerId);
    const rowIndex = Number(index);
    if (!Number.isInteger(rowIndex) || rowIndex < 0 || rowIndex >= rows.length) return;
    rows.splice(rowIndex, 1);
    setStatus('Строка убрана из отчета');
    renderEmployeeMode();
  }

  async function submitEmployeeAccountableReport(offerId) {
    const id = String(offerId || '').trim();
    const rows = employeeDraftRowsForOffer(id);
    const workspaceId = state.workspaceId;
    if (!id || !workspaceId || state.employeeReportBusyId) return;
    if (!rows.length) {
      setStatus('Добавьте хотя бы одну строку отчета', true);
      return;
    }
    state.employeeReportBusyId = id;
    setStatus('Отправляю отчет');
    renderEmployeeMode();
    try {
      const created = await v2Api('POST', '/api/workspaces/' + workspaceId + '/accountable-reports', {
        offer_id: id,
        title: 'Отчет сотрудника',
        rows: rows.map((row) => ({
          date: row.date,
          description: row.description,
          amount: row.amount
        }))
      });
      const draft = created.report || null;
      if (!draft || !draft.id) throw new Error('report_not_created');
      const submitted = await v2Api('POST', '/api/accountable-reports/' + draft.id + '/submit', {});
      const report = submitted.report || draft;
      if (state.employeeMode) {
        const reports = Array.isArray(state.employeeMode.reports) ? state.employeeMode.reports.slice() : [];
        reports.push(report);
        state.employeeMode.reports = reports;
        const summary = Object.assign({}, state.employeeMode.summary || {});
        summary.submitted_reports = reports.filter((item) => String(item.status || '') === 'submitted').length;
        summary.draft_reports = reports.filter((item) => String(item.status || '') === 'draft').length;
        state.employeeMode.summary = summary;
      }
      state.employeeReportDraftRows[id] = [];
      setStatus('Отчет отправлен админу');
    } catch (error) {
      setStatus(error.error || 'Отчет не отправлен', true);
    } finally {
      state.employeeReportBusyId = '';
      renderEmployeeMode();
    }
  }

  async function sendAuthCode() {
    const email = (els.authEmail && els.authEmail.value ? els.authEmail.value : '').trim();
    if (!email) {
      setAuthMessage('Введите email.', true);
      if (els.authEmail) els.authEmail.focus();
      return;
    }

    setAuthBusy(true);
    setStatus('Отправляю код входа');
    setAuthMessage('Отправляю код входа...');
    try {
      const data = await authApi('request_code', { email });
      if (els.authCodeBlock) els.authCodeBlock.hidden = false;
      if (data.dev_code && els.authCode) {
        els.authCode.value = data.dev_code;
        setAuthMessage('Локальный код входа заполнен автоматически.');
        els.authCode.focus();
      } else {
        setAuthMessage('Код отправлен. Он действует 30 минут. Если письмо идет долго, не запрашивайте код повторно сразу.');
        if (els.authCode) els.authCode.focus();
      }
      setStatus('Код запрошен');
    } catch (error) {
      const message = authErrorMessage(error.error);
      setAuthMessage(message, true);
      setStatus(message, true);
    } finally {
      setAuthBusy(false);
    }
  }

  async function verifyAuthCode(event) {
    if (event) event.preventDefault();
    const email = (els.authEmail && els.authEmail.value ? els.authEmail.value : '').trim();
    const code = (els.authCode && els.authCode.value ? els.authCode.value : '').trim();
    if (!email || !code) {
      setAuthMessage('Введите email и код.', true);
      return;
    }

    setAuthBusy(true);
    setStatus('Вход');
    setAuthMessage('Проверяю код...');
    try {
      const data = await authApi('verify_code', { email, code });
      if (!data.user) throw { error: 'not_authenticated' };
      setAuthMessage('Вход выполнен.');
      state.workspaceId = '';
      await loadApp();
    } catch (error) {
      const message = authErrorMessage(error.error);
      setAuthMessage(message, true);
      setStatus(message, true);
    } finally {
      setAuthBusy(false);
    }
  }

  async function logout() {
    setStatus('Выход');
    try {
      await authApi('logout', {});
    } catch (error) {
      // The UI still returns to locked state if the request fails after cookie expiry.
    }
    state.workspaceId = '';
    state.loadedWorkspaceId = '';
    state.workspaces = [];
    state.flows = [];
    state.entries = [];
    state.activeScreen = 'hall';
    renderShellVisibility('auth');
    setStatus('Вы вышли');
    setAuthMessage('Вы вышли. Введите email, чтобы войти снова.');
  }

  function saveDraft() {
    try {
      localStorage.setItem(state.draftKey, els.rawText.value || '');
    } catch (error) {}
  }

  function cancelEntryAutoSave() {
    if (state.autoSaveTimer) window.clearTimeout(state.autoSaveTimer);
    state.autoSaveTimer = 0;
    state.autoSaveRaw = '';
  }

  function looksLikeSavableEntry(raw) {
    const value = String(raw || '').trim();
    return /^[+-]\s*\d/.test(value) || /^[+-]\s*[.,]\d/.test(value);
  }

  function looksLikeAutoSavableEntry(raw) {
    const value = String(raw || '').trim();
    const match = value.match(/^[+-]\s*(?:\d+(?:[.,]\d+)?|[.,]\d+)\s+(.+)$/);
    return Boolean(match && match[1].trim().length >= 2);
  }

  function scheduleEntryAutoSave() {
    cancelEntryAutoSave();
    if (state.activeScreen !== 'operational') return;
    if (!state.workspaceId || !activeFlow()) return;
    if (editingEntry() || previewingEntry() || state.saving || state.editBusy) return;
    const raw = els.rawText.value.trim();
    if (!looksLikeAutoSavableEntry(raw)) return;
    state.autoSaveRaw = raw;
    state.autoSaveTimer = window.setTimeout(() => {
      state.autoSaveTimer = 0;
      autoSaveEntry(state.autoSaveRaw);
    }, ENTRY_AUTO_SAVE_IDLE_MS);
  }

  async function autoSaveEntry(raw) {
    if (!raw || raw !== els.rawText.value.trim()) return;
    if (editingEntry() || previewingEntry() || state.saving || state.editBusy) return;
    if (!looksLikeAutoSavableEntry(raw)) return;
    await submitEntry(null, { auto: true });
  }

  function handleRawTextInput() {
    if (previewingEntry()) {
      cancelEntryAutoSave();
      clearEntryPreview({ restoreDraft: true });
    }
    const entry = editingEntry();
    if (entry) {
      cancelEntryAutoSave();
      if (!els.rawText.value.trim()) {
        clearEntryEdit({ clearInput: false });
        return;
      }
      if (state.deleteConfirmEntryId) resetDeleteConfirmation();
      if (els.rawText.value !== entry.raw_text) {
        saveDraft();
      }
      renderInputState();
      updateDraftRows();
      return;
    }
    saveDraft();
    updateDraftRows();
    scheduleEntryAutoSave();
    scrollOperationalWindowToBottom();
  }

  function restoreDraft() {
    try {
      const draft = localStorage.getItem(state.draftKey);
      if (draft && !els.rawText.value) els.rawText.value = draft;
    } catch (error) {}
  }

  function focusCreateEntryInput(options) {
    const settings = options || {};
    if (state.activeScreen !== 'operational' || !els.rawText || els.form.classList.contains('v2-hidden')) return;
    if (state.detailOpen || state.archiveOpen || state.unsavedGuardOpen || state.closedEditOpen || state.sourceTraceOpen) return;
    if (settings.clearPreview !== false && state.previewEntryId) clearEntryPreview({ restoreDraft: true });
    if (settings.restoreDraft !== false) restoreDraft();
    renderInputState();
    updateDraftRows();
    window.requestAnimationFrame(() => {
      if (state.activeScreen !== 'operational' || state.detailOpen || state.archiveOpen || state.unsavedGuardOpen || state.closedEditOpen || state.sourceTraceOpen) return;
      els.rawText.focus({ preventScroll: true });
      const end = els.rawText.value.length;
      if (typeof els.rawText.setSelectionRange === 'function') els.rawText.setSelectionRange(end, end);
    });
  }

  function activateCreateDraftRow(surface) {
    if (surface) setFocusedSurface(surface);
    resetEntryInputForCreate();
    state.activeEntryId = '';
    state.selectedEntryId = '';
    state.detailOpen = false;
    state.closedMonthDecision = null;
    if (els.categoryError) els.categoryError.textContent = '';
    renderFeed();
    renderCheckTable();
    renderDetail();
    focusCreateEntryInput({ clearPreview: true });
  }

  function clearDraft() {
    cancelEntryAutoSave();
    try {
      localStorage.removeItem(state.draftKey);
    } catch (error) {}
    updateDraftRows();
  }

  function resetEntryInputForCreate() {
    resetDeleteConfirmation();
    state.previewEntryId = '';
    state.previewDraftBefore = null;
    state.previewDateBefore = null;
    state.editingEntryId = '';
    state.editDraftBefore = null;
    state.suppressEntryEditUntil = Date.now() + 250;
    els.date.value = createEntryDefaultDate();
    els.rawText.value = '';
    els.previewPanel.hidden = true;
    clearDraft();
    renderInputState();
    updateDraftRows();
  }

  function resetDeleteConfirmation() {
    if (state.deleteConfirmTimer) window.clearTimeout(state.deleteConfirmTimer);
    state.deleteConfirmTimer = 0;
    state.deleteConfirmEntryId = '';
  }

  function beginEntryPreview(entryId) {
    if (state.reportSelectionMode) return;
    if (Date.now() < state.suppressEntryEditUntil) return;
    if (editingEntry() || state.editBusy) return;
    const entry = state.entries.find((item) => item.id === entryId);
    if (!entry) return;
    if (state.previewEntryId === entry.id) return;
    if (!state.previewEntryId) {
      state.previewDraftBefore = els.rawText.value || '';
      state.previewDateBefore = els.date.value || '';
    }
    state.previewEntryId = entry.id;
    els.date.value = entry.date || els.date.value;
    els.rawText.value = entry.raw_text || '';
    els.previewPanel.hidden = true;
    renderInputState();
    updateDraftRows();
  }

  function clearEntryPreview(options) {
    if (!state.previewEntryId) return;
    const restoreDraft = !options || options.restoreDraft !== false;
    state.previewEntryId = '';
    if (restoreDraft) {
      els.rawText.value = state.previewDraftBefore !== null ? state.previewDraftBefore : '';
      if (state.previewDateBefore) els.date.value = state.previewDateBefore;
    }
    state.previewDraftBefore = null;
    state.previewDateBefore = null;
    renderInputState();
    updateDraftRows();
  }

  function beginEntryEdit(entryId, surface, options) {
    if (Date.now() < state.suppressEntryEditUntil) return;
    const entry = state.entries.find((item) => item.id === entryId);
    if (!entry || state.editBusy) return;
    const currentEdit = editingEntry();
    if (currentEdit && currentEdit.id !== entry.id && els.rawText.value.trim() !== currentEdit.raw_text) return;
    if (state.deleteConfirmEntryId && state.deleteConfirmEntryId !== entry.id) resetDeleteConfirmation();
    const shouldActivate = !options || options.activate !== false;
    const draftBeforeEdit = state.previewEntryId
      ? (state.previewDraftBefore !== null ? state.previewDraftBefore : '')
      : (els.rawText.value || '');
    if (state.previewEntryId) clearEntryPreview({ restoreDraft: false });
    if (!state.editingEntryId) {
      state.editDraftBefore = draftBeforeEdit;
    }
    state.editingEntryId = entry.id;
    if (shouldActivate) {
      state.activeEntryId = entry.id;
      if (surface) setFocusedSurface(surface);
    }
    els.date.value = entry.date || els.date.value;
    els.rawText.value = entry.raw_text || '';
    els.previewPanel.hidden = true;
    renderInputState();
    if (shouldActivate) renderActiveRowState();
    updateDraftRows();
  }

  function clearEntryEdit(options) {
    resetDeleteConfirmation();
    state.editingEntryId = '';
    if (options && options.restoreDraft) {
      els.rawText.value = state.editDraftBefore !== null ? state.editDraftBefore : '';
      if (!els.rawText.value) restoreDraft();
    } else if (!options || options.clearInput !== false) {
      els.rawText.value = '';
    }
    state.editDraftBefore = null;
    renderInputState();
    updateDraftRows();
  }

  function requestEntryEdit(entry, surface, event) {
    if (!entry) return false;
    if (event) event.preventDefault();
    if (!confirmReportFragmentMutation(entry, 'Разрешить редактирование')) return true;
    const begin = () => {
      beginEntryEdit(entry.id, surface || state.focusedSurface);
      els.rawText.focus({ preventScroll: true });
      setStatus('Редактирование строки');
    };
    if (requiresClosedMonthConfirmation()) {
      requestClosedMonthConfirmation(begin);
      return true;
    }
    begin();
    return true;
  }

  function activatePreviewedEntryEdit(event) {
    const entry = previewingEntry();
    return entry ? requestEntryEdit(entry, state.focusedSurface, event) : false;
  }

  async function saveEntryEdit(event) {
    if (event) event.preventDefault();
    const entry = editingEntry();
    const raw = els.rawText.value.trim();
    if (!entry || state.editBusy || !raw) return;
    if (!confirmReportFragmentMutation(entry, 'Сохранить изменение')) return false;
    if (requiresClosedMonthConfirmation()) {
      requestClosedMonthConfirmation(() => saveEntryEdit(null));
      return false;
    }
    state.editBusy = true;
    resetDeleteConfirmation();
    renderInputState();
    setStatus('Обновляю запись');
    try {
      const data = await v2Api('PATCH', '/api/entries/' + entry.id, mutationPayloadForEntry({
        flow_id: entry.flow && entry.flow.id,
        date: els.date.value || entry.date,
        raw_text: raw
      }, entry));
      state.entries = state.entries.map((item) => item.id === entry.id ? data.entry : item);
      state.activeEntryId = '';
      if (state.selectedEntryId === entry.id) state.selectedEntryId = '';
      state.lastRowClick = null;
      resetEntryInputForCreate();
      renderFeed();
      renderCheckTable();
      await loadWorkspaceData({ allowLatestFallback: false });
      state.activeEntryId = '';
      state.selectedEntryId = '';
      resetEntryInputForCreate();
      renderFeed();
      renderCheckTable();
      focusCreateEntryInput({ clearPreview: true, restoreDraft: false });
      setStatus('Запись обновлена');
      return true;
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        setStatus('Закрытый месяц: запись нельзя изменить без подтверждения', true);
        requestClosedMonthConfirmation(() => saveEntryEdit(null), closedMonthErrorPeriodKey(error, entry));
      } else if (navigator.onLine === false || error.status === 0) {
        saveDraft();
        setStatus('Офлайн: черновик сохранен локально', true);
      } else {
        setStatus(error.error || 'Запись не обновлена', true);
      }
    } finally {
      state.editBusy = false;
      renderInputState();
    }
    return false;
  }

  async function deleteEntryEdit() {
    const entry = editingEntry();
    if (!entry || state.editBusy) return;
    if (!confirmReportFragmentMutation(entry, 'Удалить запись')) return;
    if (requiresClosedMonthConfirmation()) {
      requestClosedMonthConfirmation(() => deleteEntryEdit());
      return;
    }
    const visibleIndex = activeFlowEntryIndex(entry.id);
    const rowNumber = visibleIndex >= 0 ? visibleIndex + 1 : state.entries.findIndex((item) => item.id === entry.id) + 1;
    if (state.deleteConfirmEntryId !== entry.id) {
      resetDeleteConfirmation();
      state.deleteConfirmEntryId = entry.id;
      state.deleteConfirmTimer = window.setTimeout(() => {
        state.deleteConfirmEntryId = '';
        state.deleteConfirmTimer = 0;
        renderInputState();
      }, 3500);
      renderInputState();
      setStatus('Нажмите «Удалить?» еще раз, чтобы удалить запись ' + rowNumber);
      return;
    }
    state.editBusy = true;
    resetDeleteConfirmation();
    renderInputState();
    setStatus('Удаляю запись');
    try {
      await v2Api('DELETE', '/api/entries/' + entry.id, mutationPayloadForEntry({}, entry));
      state.lastRowClick = null;
      if (state.selectedEntryId === entry.id) {
        state.selectedEntryId = '';
        state.detailOpen = false;
      }
      state.entries = state.entries.filter((item) => item.id !== entry.id);
      state.activeEntryId = '';
      resetEntryInputForCreate();
      renderFeed();
      renderCheckTable();
      renderDetail();
      await loadWorkspaceData({ allowLatestFallback: false });
      state.activeEntryId = '';
      state.selectedEntryId = '';
      resetEntryInputForCreate();
      renderFeed();
      renderCheckTable();
      renderDetail();
      focusCreateEntryInput({ clearPreview: true, restoreDraft: false });
      setStatus('Запись удалена');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        setStatus('Закрытый месяц: запись нельзя удалить без подтверждения', true);
        requestClosedMonthConfirmation(() => deleteEntryEdit(), closedMonthErrorPeriodKey(error, entry));
      } else {
        setStatus(error.error || 'Запись не удалена', true);
      }
    } finally {
      state.editBusy = false;
      renderInputState();
    }
  }

  async function previewEntry() {
    const flow = activeFlow();
    if (!flow || !els.rawText.value.trim()) return;
    els.previewPanel.hidden = false;
    els.previewPanel.textContent = 'Проверяю';
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/parse-preview', {
        flow_id: flow.id,
        date: els.date.value,
        raw_text: els.rawText.value
      });
      const p = data.preview || {};
      els.previewPanel.textContent = [
        'поток ' + valueLabel(p.flow && p.flow.type),
        'знак ' + text(p.sign, 'null'),
        'сумма ' + text(p.amount, 'null'),
        'категория ' + categoryNameByCode(p.category_code),
        'учет ' + valueLabel(p.accounting_type || p.accounting_section || 'operational'),
        'статус ' + valueLabel(p.status)
      ].join(' · ');
    } catch (error) {
      els.previewPanel.textContent = error.error || 'Предпросмотр недоступен';
    }
  }

  async function submitEntry(event, options) {
    if (event) event.preventDefault();
    const settings = options || {};
    cancelEntryAutoSave();
    if (editingEntry()) {
      await saveEntryEdit(event);
      return;
    }
    if (previewingEntry()) {
      clearEntryPreview({ restoreDraft: true });
      els.rawText.focus({ preventScroll: true });
      return;
    }
    if (state.saving) return;
    const flow = activeFlow();
    const raw = els.rawText.value.trim();
    if (!flow || !raw) return;
    if (requiresClosedMonthConfirmation()) {
      saveDraft();
      requestClosedMonthConfirmation(() => submitEntry(null));
      return;
    }
    state.saving = true;
    renderInputState();
    saveDraft();
    setStatus(navigator.onLine === false ? 'Офлайн: черновик сохранен локально' : (settings.auto ? 'Автосохраняю' : 'Сохраняю'));
    try {
      await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/entries', mutationPayload({
        flow_id: flow.id,
        date: els.date.value,
        raw_text: raw
      }));
      clearEntryEdit();
      els.previewPanel.hidden = true;
      clearDraft();
      await loadWorkspaceData({ preferLatest: true, scrollToBottom: true });
      focusCreateEntryInput({ clearPreview: true, restoreDraft: false });
      setStatus(settings.auto ? 'Автосохранено' : 'Сохранено');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        setStatus('Закрытый месяц: создайте корректировку, пересчитайте цепочку или отмените', true);
        requestClosedMonthConfirmation(() => submitEntry(null), closedMonthErrorPeriodKey(error, { date: els.date.value }));
      } else if (navigator.onLine === false || error.status === 0) {
        setStatus('Офлайн: черновик сохранен локально', true);
      } else {
        setStatus(error.error || 'Не удалось сохранить', true);
      }
    } finally {
      state.saving = false;
      renderInputState();
    }
  }

  function openDetail() {
    if (!selectedEntry()) return;
    state.detailOpen = true;
    renderDetailState();
    window.requestAnimationFrame(() => {
      if (els.detailClose) els.detailClose.focus({ preventScroll: true });
    });
  }

  function closeDetail() {
    state.detailOpen = false;
    state.suppressEntryEditUntil = Date.now() + 60;
    renderDetailState();
    focusActiveRow();
  }

  async function loadSourceTraceEntries(ids) {
    const missing = missingSourceTraceIds(ids);
    if (!missing.length || !state.workspaceId) return { entries: entriesForTraceIds(ids, []), missing_ids: [] };
    const data = await v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/layer1-source-entries', null, {
      ids: missing.join(',')
    });
    (data.entries || []).forEach((entry) => {
      if (entry && entry.id) state.sourceEntryCache[String(entry.id)] = entry;
    });
    return {
      entries: entriesForTraceIds(ids, data.entries || []),
      missing_ids: data.missing_ids || []
    };
  }

  function missingSourceTraceIds(ids) {
    return ids.filter((id) => !state.sourceEntryCache[String(id)] && !state.entries.some((entry) => String(entry.id) === String(id)));
  }

  async function openSourceTrace(key, label) {
    const trace = sourceTraceFor(key);
    if (!trace) return;
    const ids = traceEntryIds(trace);
    const entries = traceEntries(trace);
    const missingIds = missingSourceTraceIds(ids);
    state.sourceTraceOpen = true;
    state.sourceTraceTitle = label || key || 'Записи-источники';
    state.sourceTraceMeta = missingIds.length
      ? 'Загружаю записи-источники'
      : entries.length + ' ' + recordWord(entries.length) + '-источник';
    state.sourceTraceEntries = entries;
    state.sourceTraceRaw = trace;
    state.sourceTraceError = '';
    renderSourceTrace();
    if (missingIds.length) {
      try {
        const loaded = await loadSourceTraceEntries(ids);
        state.sourceTraceEntries = loaded.entries;
        state.sourceTraceMeta = loaded.entries.length + ' ' + recordWord(loaded.entries.length) + '-источник'
          + (loaded.missing_ids.length ? ' · нет ' + loaded.missing_ids.length : '');
      } catch (error) {
        state.sourceTraceError = error.error || 'Записи-источники недоступны';
        state.sourceTraceMeta = 'Записи-источники недоступны';
      }
      renderSourceTrace();
    }
    window.requestAnimationFrame(() => {
      if (els.sourceClose) els.sourceClose.focus({ preventScroll: true });
    });
  }

  function closeSourceTrace() {
    state.sourceTraceOpen = false;
    state.sourceTraceTitle = '';
    state.sourceTraceMeta = '';
    state.sourceTraceEntries = [];
    state.sourceTraceRaw = null;
    state.sourceTraceError = '';
    state.sourceCategorySavingEntryId = '';
    state.sourceCategorySavingAll = false;
    state.sourceCategoryDrafts = {};
    renderSourceTrace();
  }

  function setFocusedSurface(surface) {
    state.focusedSurface = surface === 'check' ? 'check' : 'journal';
    renderFocusMode();
  }

  function renderActiveRowState() {
    $$('[data-v2-entry-select].is-active, [data-v2-check-row][data-v2-entry-id].is-active').forEach((row) => {
      row.classList.remove('is-active');
    });
    if (!state.activeEntryId) return;
    $$('[data-v2-entry-id="' + state.activeEntryId.replace(/"/g, '\\"') + '"]').forEach((row) => {
      if (row.matches('[data-v2-entry-select], [data-v2-check-row][data-v2-entry-id]')) {
        row.classList.add('is-active');
      }
    });
  }

  function updateDraftRows() {
    const raw = draftText();
    $$('[data-v2-draft-text], [data-v2-check-draft-text]').forEach((node) => {
      node.textContent = raw;
    });
  }

  function scrollOperationalWindowToBottom() {
    window.requestAnimationFrame(() => {
      if (!els.feed) return;
      els.feed.scrollTop = els.feed.scrollHeight;
      syncVerticalScroll(els.feed);
    });
  }

  function setActiveEntry(entryId, surface) {
    if (!entryId || !state.entries.some((entry) => entry.id === entryId)) return;
    state.activeEntryId = entryId;
    if (surface) setFocusedSurface(surface);
    renderActiveRowState();
  }

  function activeRowSelector(surface) {
    if (!state.activeEntryId) return '';
    const attr = '[data-v2-entry-id="' + state.activeEntryId.replace(/"/g, '\\"') + '"]';
    return surface === 'check' ? '[data-v2-check-row]' + attr : '[data-v2-entry-select]' + attr;
  }

  function focusActiveRow() {
    const selector = activeRowSelector(state.focusedSurface);
    if (!selector) return;
    window.requestAnimationFrame(() => {
      const row = document.querySelector(selector);
      if (!row) return;
      row.focus({ preventScroll: true });
      row.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
  }

  function scrollActiveRowsIntoView() {
    if (!state.activeEntryId) return;
    window.requestAnimationFrame(() => {
      const selector = '[data-v2-entry-id="' + state.activeEntryId.replace(/"/g, '\\"') + '"]';
      document.querySelectorAll(selector).forEach((row) => {
        row.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      });
    });
  }

  function moveActiveRow(delta) {
    const entries = activeFlowEntries();
    if (!entries.length) return;
    const currentIndex = Math.max(0, entries.findIndex((entry) => entry.id === state.activeEntryId));
    const nextIndex = Math.min(entries.length - 1, Math.max(0, currentIndex + delta));
    const next = entries[nextIndex];
    if (!next) return;
    state.activeEntryId = next.id;
    renderFeed();
    renderCheckTable();
    focusActiveRow();
  }

  function switchFocusedSurface(surface) {
    setFocusedSurface(surface);
    focusActiveRow();
  }

  function syncVerticalScroll(source) {
    if (state.syncingScroll) return;
    const target = source === els.feed ? els.checkTable : els.feed;
    if (!source || !target) return;
    state.syncingScroll = true;
    target.scrollTop = source.scrollTop;
    window.requestAnimationFrame(() => {
      state.syncingScroll = false;
    });
  }

  function syncJournalHeaderGutter() {
    if (!els.feed || !els.journalHeader) return;
    const feedStyle = window.getComputedStyle(els.feed);
    const hidesScrollbar = feedStyle.overflowY === 'hidden' || feedStyle.scrollbarWidth === 'none';
    const gutter = hidesScrollbar ? 0 : Math.max(0, els.feed.offsetWidth - els.feed.clientWidth);
    els.journalHeader.style.width = gutter ? 'calc(100% - ' + gutter + 'px)' : '100%';
    els.journalHeader.style.marginRight = '';
  }

  function syncStructuredHeaderScroll() {
    if (!els.checkTable || !els.checkHeader) return;
    const left = els.checkTable.scrollLeft || 0;
    els.checkHeader.style.transform = left ? 'translateX(-' + left + 'px)' : '';
  }

  function openActiveEntry() {
    if (!activeEntry()) return;
    selectEntry(state.activeEntryId, state.focusedSurface);
  }

  function selectEntry(entryId, source) {
    if (state.previewEntryId) {
      clearEntryPreview({ restoreDraft: true });
    }
    if (state.editingEntryId) {
      clearEntryEdit({ restoreDraft: true });
    }
    if (entryId) state.activeEntryId = entryId;
    state.selectedEntryId = entryId || '';
    state.closedMonthDecision = null;
    state.attachmentStatus = '';
    els.categoryError.textContent = '';
    renderFeed();
    renderCheckTable();
    renderDetail();
    if (state.selectedEntryId) {
      openDetail();
      loadSelectedEntryAttachments();
    } else {
      closeDetail();
    }
    if (source === 'check') setFocusedSurface('check');
    if (source === 'journal') setFocusedSurface('journal');
  }

  function activateJournalRow(row) {
    if (!row || !els.feed.contains(row)) return;
    const entryId = row.getAttribute('data-v2-entry-id') || '';
    if (!entryId) return;
    activateRowForEditing(entryId, 'journal');
  }

  function activateCheckRow(row) {
    if (!row || !els.checkTable.contains(row)) return;
    const entryId = row.getAttribute('data-v2-entry-id') || '';
    if (!entryId) return;
    activateRowForEditing(entryId, 'check');
  }

  function focusRowOnly(row, surface) {
    const entryId = row.getAttribute('data-v2-entry-id') || '';
    if (!entryId) return;
    setActiveEntry(entryId, surface);
    beginEntryPreview(entryId);
  }

  function activateRowForEditing(entryId, surface) {
    const now = Date.now();
    const previous = state.lastRowClick;
    state.lastRowClick = { entryId, surface, time: now };
    if (
      previous
      && previous.entryId === entryId
      && previous.surface === surface
      && now - previous.time < 650
    ) {
      selectEntry(entryId, surface);
      return;
    }
    setActiveEntry(entryId, surface);
    beginEntryPreview(entryId);
  }

  function rememberFocusOnlyClick(row, surface) {
    const entryId = row.getAttribute('data-v2-entry-id') || '';
    if (!entryId || state.focusedSurface === surface) return;
    if (state.focusOnlyClickTimer) window.clearTimeout(state.focusOnlyClickTimer);
    state.focusOnlyClick = { entryId, surface };
    focusRowOnly(row, surface);
    state.lastRowClick = { entryId, surface, time: Date.now() };
    state.focusOnlyClickTimer = window.setTimeout(() => {
      const remembered = state.focusOnlyClick;
      if (remembered && remembered.entryId === entryId && remembered.surface === surface) {
        state.focusOnlyClick = null;
      }
      state.focusOnlyClickTimer = 0;
    }, 900);
  }

  function consumeFocusOnlyClick(row, surface) {
    const entryId = row.getAttribute('data-v2-entry-id') || '';
    const remembered = state.focusOnlyClick;
    if (!remembered || remembered.entryId !== entryId || remembered.surface !== surface) return false;
    if (state.focusOnlyClickTimer) window.clearTimeout(state.focusOnlyClickTimer);
    state.focusOnlyClickTimer = 0;
    state.focusOnlyClick = null;
    return true;
  }

  function handleRowClick(event, selector, surface, activate) {
    const row = event.target.closest(selector);
    if (!row) return;
    event.stopPropagation();
    if (consumeFocusOnlyClick(row, surface)) return;
    if (state.focusedSurface !== surface) {
      focusRowOnly(row, surface);
      return;
    }
    activate(row);
  }

  function handleRowPointerDown(event, selector, surface) {
    const row = event.target.closest(selector);
    if (!row) return;
    if (state.focusedSurface === surface) return;
    event.stopPropagation();
    rememberFocusOnlyClick(row, surface);
  }

  function handleWorkspaceKey(event) {
    const target = event.target;
    const tagName = target && target.tagName ? target.tagName.toLowerCase() : '';
    const isRowControl = Boolean(target && target.closest && target.closest('[data-v2-entry-select], [data-v2-check-row][data-v2-entry-id]'));
    const isPlainButton = target && !isRowControl && tagName === 'button';
    const editable = target && !isRowControl && (target.isContentEditable || ['input', 'textarea', 'select'].includes(tagName));
    if (isRowControl && target.closest('[data-v2-entry-select]')) setFocusedSurface('journal');
    if (isRowControl && target.closest('[data-v2-check-row][data-v2-entry-id]')) setFocusedSurface('check');

    if (event.key === 'Escape' && state.detailOpen) {
      event.preventDefault();
      closeDetail();
      return;
    }

    if (event.key === 'Escape' && state.archiveOpen) {
      event.preventDefault();
      closeArchivePicker();
      return;
    }

    if (event.key === 'Escape' && state.unsavedGuardOpen) {
      event.preventDefault();
      cancelPendingPeriodAction();
      return;
    }

    if (event.key === 'Escape' && state.closedEditOpen) {
      event.preventDefault();
      cancelClosedMonthConfirmation();
      return;
    }

    if (event.key === 'Escape' && state.sourceTraceOpen) {
      event.preventDefault();
      closeSourceTrace();
      return;
    }

    if (event.key === 'Escape' && state.reportFragmentOpen) {
      event.preventDefault();
      closeReportFragment();
      return;
    }

    if (event.key === 'Escape' && state.quickNoteModalOpen) {
      event.preventDefault();
      closeQuickNoteSmith();
      return;
    }

    if (event.key === 'Escape' && state.reportSelectionMode) {
      event.preventDefault();
      leaveReportSelectionMode();
      return;
    }

    if (event.key === 'Escape' && editingEntry()) {
      event.preventDefault();
      clearEntryEdit({ restoreDraft: true });
      return;
    }

    if (event.key === 'Escape' && previewingEntry()) {
      event.preventDefault();
      clearEntryPreview({ restoreDraft: true });
      return;
    }

    if (state.detailOpen || editable) return;

    if (state.reportSelectionMode && event.key === 'Enter') {
      event.preventDefault();
      handleReportSelectionRow(state.activeEntryId);
      return;
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      switchFocusedSurface('check');
      return;
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      switchFocusedSurface('journal');
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      moveActiveRow(1);
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      moveActiveRow(-1);
      return;
    }

    if (event.key === 'Enter') {
      if (isPlainButton) return;
      event.preventDefault();
      openActiveEntry();
    }
  }

  async function selectFirstOtherReview() {
    const entry = state.entries.find(isUserReviewEntry)
      || state.otherExpenseQueue[0];
    if (entry) {
      if (!state.entries.some((item) => item.id === entry.id) && entry.date) {
        const targetMonth = monthPartsFromDate(entry.date);
        if (targetMonth) {
          state.feedView = 'month';
          state.period = targetMonth;
          await loadWorkspaceData({ allowLatestFallback: false, preferLatest: false, scrollToBottom: false });
        }
      }
      selectEntry(entry.id, 'journal');
      return;
    }
    setStatus('Записей на проверке нет');
  }

  async function switchScreen(screen) {
    const nextScreen = ['hall', 'summary', 'training', 'quick-notes'].includes(screen) ? screen : 'operational';
    if (state.activeScreen === nextScreen) {
      if (nextScreen === 'operational') focusCreateEntryInput({ clearPreview: true });
      if (nextScreen === 'training') {
        state.activeTrainingSourceRowId = '';
        renderDictionaryTraining();
      }
      if (nextScreen === 'quick-notes') {
        loadQuickNotes();
      }
      return;
    }
    if (nextScreen === 'hall') {
      state.activeScreen = 'hall';
      state.reportSelectionMode = false;
      state.reportSelectionStartId = '';
      state.reportSelectionEndId = '';
      state.reportPackageSelectionIds = {};
      closeDetail();
      closeSourceTrace();
      closeReportFragment({ render: false });
      clearEntryPreview({ restoreDraft: true });
      if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
      renderShellVisibility('hall');
      renderAll();
      setStatus('Выберите пространство');
      return;
    }
    if (!state.workspaceId || state.loadedWorkspaceId !== state.workspaceId) {
      await openWorkspace(state.workspaceId, nextScreen, { restoreDraft: true });
      return;
    }
    state.activeScreen = nextScreen;
    if (nextScreen === 'summary') {
      closeDetail();
      clearEntryPreview({ restoreDraft: true });
      if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
      if (!state.layer1Summary && state.layer1SummaryStatus !== 'loading') loadLayer1SummaryData();
    } else if (nextScreen === 'training') {
      closeDetail();
      clearEntryPreview({ restoreDraft: true });
      if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
      state.activeTrainingSourceRowId = '';
      if (!state.rawHistory && state.rawHistoryStatus !== 'loading') loadRawHistory();
      if (!state.dictionaryQueue && state.dictionaryQueueStatus !== 'loading') loadDictionaryReviewQueue();
      if (state.dictionaryTrainingStatus === 'idle') loadDictionaryTrainingDecisions();
    } else if (nextScreen === 'quick-notes') {
      closeDetail();
      clearEntryPreview({ restoreDraft: true });
      if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
      if (state.quickNotesStatus === 'idle') loadQuickNotes();
    } else {
      closeSourceTrace();
      clearEntryPreview({ restoreDraft: true });
      if (state.editingEntryId) clearEntryEdit({ restoreDraft: true });
    }
    renderShellVisibility('workspace');
    renderAll();
    if (nextScreen === 'operational') focusCreateEntryInput({ clearPreview: true });
  }

  function switchSummaryTab(tab) {
    const allowed = ['information', 'sending', 'printing', 'storage'];
    state.activeSummaryTab = allowed.includes(tab) ? tab : 'information';
    renderSummaryTabs();
    if (state.activeSummaryTab === 'information' && !state.layer1Summary && state.layer1SummaryStatus === 'idle') {
      loadLayer1SummaryData();
    }
    if (state.activeSummaryTab === 'storage' && state.layer1SnapshotsStatus === 'idle') {
      loadLayer1Snapshots();
    }
    if (state.activeSummaryTab === 'storage' && state.reportPackagesStatus === 'idle') {
      loadReportPackages();
    }
  }

  function selectDictionaryTrainingRow(sourceRowId) {
    if (!sourceRowId) return;
    state.activeTrainingSourceRowId = sourceRowId;
    state.dictionaryTrainingError = '';
    renderDictionaryTraining();
  }

  function setDictionaryTrainingFilter(filter) {
    state.trainingFilter = filter || 'all';
    state.dictionaryTrainingError = '';
    renderDictionaryTraining();
  }

  function setDictionaryTrainingSearch(value) {
    state.trainingSearch = value || '';
    state.dictionaryTrainingError = '';
    renderDictionaryTraining();
    window.requestAnimationFrame(() => {
      const input = els.trainingScreen && els.trainingScreen.querySelector('[data-v2-training-search]');
      if (!input) return;
      input.focus({ preventScroll: true });
      const end = input.value.length;
      if (typeof input.setSelectionRange === 'function') input.setSelectionRange(end, end);
    });
  }

  async function submitDictionaryTrainingDecision(button) {
    const detail = button.closest('[data-source-row-id]');
    const sourceRowId = detail ? (detail.getAttribute('data-source-row-id') || '') : state.activeTrainingSourceRowId;
    const decisionType = button.getAttribute('data-v2-dictionary-decision-action') || '';
    if (!sourceRowId || !decisionType || state.dictionaryTrainingBusyKey) return;
    const form = button.closest('[data-v2-dictionary-training-form]');
    const category = form ? form.querySelector('[data-v2-dictionary-category]') : null;
    const pattern = form ? form.querySelector('[data-v2-dictionary-pattern]') : null;
    const patternType = form ? form.querySelector('[data-v2-dictionary-pattern-type]') : null;
    const language = form ? form.querySelector('[data-v2-dictionary-language]') : null;
    const weight = form ? form.querySelector('[data-v2-dictionary-weight]') : null;
    const requiresAny = form ? form.querySelector('[data-v2-dictionary-requires-any]') : null;
    const excludesAny = form ? form.querySelector('[data-v2-dictionary-excludes-any]') : null;
    const note = form ? form.querySelector('[data-v2-dictionary-note]') : null;
    const requiresManualCategory = button.hasAttribute('data-v2-training-require-category');
    const keepLowerAccounting = button.hasAttribute('data-v2-training-keep-lower');
    const categoryValue = category ? category.value : '';
    const payload = {
      source_row_id: sourceRowId,
      decision_type: decisionType,
      note: note && note.value ? note.value.trim() : (requiresManualCategory ? 'ручной разбор без обучения словаря' : 'консоль обучения интерфейса')
    };
    if (['approve_existing_guess_local', 'correct_category_local', 'propose_universal_candidate'].includes(decisionType)) {
      payload.category_code = categoryValue;
      payload.pattern = pattern ? pattern.value.trim() : '';
      payload.pattern_type = patternType ? patternType.value : 'keyword';
      payload.language = language ? language.value : 'multi';
      payload.weight = weight && weight.value !== '' ? Number(weight.value) : 10;
      payload.requires_any = dictionaryTrainingTokens(requiresAny ? requiresAny.value : '');
      payload.excludes_any = dictionaryTrainingTokens(excludesAny ? excludesAny.value : '');
      if (!payload.category_code || !payload.pattern) {
        state.dictionaryTrainingError = 'Выберите категорию. Если не уверены, нажмите «Не уверен, позже».';
        renderDictionaryTraining();
        return;
      }
    } else if (requiresManualCategory) {
      if (!categoryValue) {
        state.dictionaryTrainingError = 'Выберите категорию для этой записи или нажмите «Оставить как особый учет».';
        renderDictionaryTraining();
        return;
      }
      payload.category_code = categoryValue;
    } else if (!keepLowerAccounting && categoryValue) {
      payload.category_code = categoryValue;
    }
    state.dictionaryTrainingBusyKey = sourceRowId;
    state.dictionaryTrainingError = '';
    renderDictionaryTraining();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/dictionary-training-decisions', payload);
      const decision = data.decision || null;
      const decisions = (state.dictionaryTrainingDecisions || []).filter((item) => item.source_row_id !== sourceRowId);
      if (decision) decisions.unshift(decision);
      state.dictionaryTrainingDecisions = decisions;
      setStatus('Решение сохранено');
      await loadDictionaryTrainingDecisions();
    } catch (error) {
      state.dictionaryTrainingError = error.error || 'Решение не сохранено';
      setStatus(state.dictionaryTrainingError, true);
    } finally {
      state.dictionaryTrainingBusyKey = '';
      renderDictionaryTraining();
    }
  }

  async function requestDictionaryInternetReference(button) {
    const detail = button.closest('[data-source-row-id]');
    const sourceRowId = detail ? (detail.getAttribute('data-source-row-id') || '') : state.activeTrainingSourceRowId;
    const queryInput = detail ? detail.querySelector('[data-v2-smith-query]') : null;
    const urlInput = detail ? detail.querySelector('[data-v2-smith-url]') : null;
    const consentInput = detail ? detail.querySelector('[data-v2-smith-consent]') : null;
    const query = queryInput ? queryInput.value.trim() : '';
    const candidateUrl = urlInput ? urlInput.value.trim() : '';
    const hasConsent = consentInput ? consentInput.checked : false;
    if (!sourceRowId || state.dictionaryInternetBusyKey) return;
    if (!query || !candidateUrl || !hasConsent) {
      state.dictionaryInternetError = !candidateUrl
        ? 'Вставьте проверенный HTTPS URL источника.'
        : (!hasConsent ? 'Для проверки источника нужно согласие.' : 'Для проверки нужно публичное название.');
      renderDictionaryTraining();
      return;
    }
    state.dictionaryInternetBusyKey = sourceRowId;
    state.dictionaryInternetError = '';
    renderDictionaryTraining();
    try {
      const data = await v2Api('POST', '/api/workspaces/' + state.workspaceId + '/dictionary-training-internet-reference', {
        source_row_id: sourceRowId,
        sanitized_query: query,
        candidate_url: candidateUrl,
        lookup_consent: true
      });
      state.dictionaryInternetResults[sourceRowId] = data.reference || null;
      setStatus('Справка Mr. Smith готова');
    } catch (error) {
      state.dictionaryInternetError = error.error || 'Справка Mr. Smith не получена';
      setStatus(state.dictionaryInternetError, true);
    } finally {
      state.dictionaryInternetBusyKey = '';
      renderDictionaryTraining();
    }
  }

  function syncDictionaryInternetReferenceButton(target) {
    const section = target.closest ? target.closest('[data-v2-mr-smith]') : null;
    if (!section) return;
    const query = section.querySelector('[data-v2-smith-query]');
    const url = section.querySelector('[data-v2-smith-url]');
    const consent = section.querySelector('[data-v2-smith-consent]');
    const button = section.querySelector('[data-v2-smith-reference]');
    if (!button) return;
    button.disabled = !(
      query && query.value.trim()
      && url && url.value.trim()
      && consent && consent.checked
      && !state.dictionaryInternetBusyKey
    );
  }

  async function submitDictionaryInternetFeedback(button) {
    const detail = button.closest('[data-source-row-id]');
    const sourceRowId = detail ? (detail.getAttribute('data-source-row-id') || '') : state.activeTrainingSourceRowId;
    const result = dictionaryInternetReferenceResult(sourceRowId);
    const lookupId = result ? (result.lookup_id || result.id || result.request_id || '') : '';
    const verdict = button.getAttribute('data-v2-smith-feedback-action') || 'unclear';
    if (!sourceRowId || !lookupId || state.dictionaryInternetFeedbackBusyKey) return;
    state.dictionaryInternetFeedbackBusyKey = sourceRowId;
    state.dictionaryInternetError = '';
    renderDictionaryTraining();
    try {
      const data = await v2Api('PATCH', '/api/workspaces/' + state.workspaceId + '/dictionary-training-internet-reference/lookups/' + lookupId, {
        verdict,
        match_index: 0,
        note: 'оценка источника из интерфейса'
      });
      const lookup = data.lookup || null;
      if (lookup) {
        state.dictionaryInternetResults[sourceRowId] = Object.assign({}, result, lookup, {
          lookup_id: lookup.id || lookupId
        });
      }
      setStatus('Оценка источника Mr. Smith сохранена');
    } catch (error) {
      state.dictionaryInternetError = error.error || 'Оценка Mr. Smith не сохранена';
      setStatus(state.dictionaryInternetError, true);
    } finally {
      state.dictionaryInternetFeedbackBusyKey = '';
      renderDictionaryTraining();
    }
  }

  async function saveCategory(event) {
    event.preventDefault();
    const entry = selectedEntry();
    if (!entry || state.categorySaving) return;
    const categoryCode = els.categorySelect.value;
    if (!confirmReportFragmentMutation(entry, 'Изменить категорию')) return;
    state.categorySaving = true;
    els.categorySelect.disabled = true;
    els.categorySave.disabled = true;
    els.categoryError.textContent = '';
    setStatus('Сохраняю категорию');
    try {
      const data = await v2Api('PATCH', '/api/entries/' + entry.id + '/category', mutationPayloadForEntry({
        category_code: categoryCode
      }, entry));
      state.selectedEntryId = data.entry.id;
      await loadWorkspaceData();
      setStatus('Категория обновлена');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        const message = 'Закрытый месяц: категорию нельзя изменить без решения. Варианты: создать корректировку, пересчитать цепочку, отменить.';
        state.closedMonthDecision = {
          entryId: entry.id,
          fromCategoryCode: entry.category_code,
          toCategoryCode: categoryCode
        };
        els.categoryError.textContent = message;
        setStatus(message, true);
        renderClosedMonthDecision();
      } else {
        const message = error.error || 'Категория не обновлена';
        els.categoryError.textContent = message;
        setStatus(message, true);
      }
    } finally {
      state.categorySaving = false;
      renderDetail();
    }
  }

  function applySourceTraceUpdatedEntry(updated) {
    if (!updated || !updated.id) return;
    const entryId = String(updated.id);
    state.sourceEntryCache[entryId] = updated;
    state.entries = state.entries.map((item) => String(item.id) === entryId ? updated : item);
    state.sourceTraceEntries = state.sourceTraceEntries.map((item) => item && String(item.id) === entryId ? updated : item);
    delete state.sourceCategoryDrafts[entryId];
  }

  async function refreshSourceTraceDerivedData() {
    if (!state.workspaceId) return;
    const month = selectedMonthParts();
    try {
      const [otherExpenseData, summaryData, reportData] = await Promise.all([
        v2Api('GET', '/api/workspaces/' + state.workspaceId + '/other-expenses'),
        v2Api('GET', '/api/workspaces/' + state.workspaceId + '/summary'),
        v2Api('GET', '/api/workspaces/' + state.workspaceId + '/reports/monthly', null, {
          year: month.year,
          month: month.month
        })
      ]);
      state.otherExpenseQueue = otherExpenseData.entries || [];
      state.summary = summaryData.summary || null;
      state.monthReport = reportData.report || null;
      if (state.activeScreen === 'summary') await loadLayer1SummaryData();
    } catch (error) {
      setStatus(error.error || 'Сводка обновится после перезагрузки', true);
    }
  }

  async function saveSourceTraceCategory(form) {
    const entryId = form ? (form.getAttribute('data-v2-source-entry-id') || '') : '';
    const select = form ? form.querySelector('[data-v2-source-category-select]') : null;
    const forceOperational = form ? form.querySelector('[data-v2-source-force-operational]') : null;
    const categoryCode = select ? select.value : '';
    if (!entryId || !categoryCode || state.sourceCategorySavingEntryId) return;
    const entry = state.entries.find((item) => String(item.id) === entryId)
      || state.sourceTraceEntries.find((item) => item && String(item.id) === entryId)
      || state.sourceEntryCache[entryId]
      || null;
    state.sourceCategorySavingEntryId = entryId;
    setStatus('Сохраняю категорию источника');
    renderSourceTrace();
    try {
      const data = await v2Api('PATCH', '/api/entries/' + entryId + '/category', {
        category_code: categoryCode,
        force_operational: Boolean(forceOperational && forceOperational.checked)
      });
      const updated = data.entry || null;
      applySourceTraceUpdatedEntry(updated);
      await refreshSourceTraceDerivedData();
      setStatus('Категория источника обновлена');
    } catch (error) {
      if (error.status === 409 && error.error === 'closed_month_requires_decision') {
        const shouldRecalculate = window.confirm('Месяц закрыт. Изменить эту запись и пересчитать цепочку остатков?');
        if (shouldRecalculate) {
          const data = await v2Api('POST', '/api/entries/' + entryId + '/category/closed-month-decision', {
            decision: 'recalculate_chain',
            category_code: categoryCode,
            force_operational: Boolean(forceOperational && forceOperational.checked),
            reason: 'source_trace_category_correction'
          });
          const updated = data.entry || null;
          applySourceTraceUpdatedEntry(updated);
          await refreshSourceTraceDerivedData();
          setStatus('Запись закрытого месяца обновлена');
        } else {
          setStatus('Изменение закрытого месяца отменено');
        }
      } else {
        setStatus(error.error || 'Категория источника не обновлена', true);
      }
    } finally {
      state.sourceCategorySavingEntryId = '';
      renderAll();
    }
  }

  async function saveAllSourceTraceCategories() {
    if (!els.sourceBody || state.sourceCategorySavingAll || state.sourceCategorySavingEntryId) return;
    const forms = Array.from(els.sourceBody.querySelectorAll('[data-v2-source-category-form]'));
    const changes = forms.map((form) => {
      const entryId = form.getAttribute('data-v2-source-entry-id') || '';
      const select = form.querySelector('[data-v2-source-category-select]');
      const forceOperational = form.querySelector('[data-v2-source-force-operational]');
      const entry = state.sourceTraceEntries.find((item) => item && String(item.id) === entryId) || null;
      const categoryCode = select ? select.value : '';
      if (!entryId || !categoryCode || categoryCode === (entry && entry.category_code ? entry.category_code : '')) return null;
      return {
        entryId,
        categoryCode,
        forceOperational: Boolean(forceOperational && forceOperational.checked)
      };
    }).filter(Boolean);
    if (!changes.length) return;

    state.sourceCategorySavingAll = true;
    setStatus('Сохраняю выбранные категории: ' + changes.length);
    renderSourceTrace();
    let saved = 0;
    try {
      for (const change of changes) {
        const data = await v2Api('PATCH', '/api/entries/' + change.entryId + '/category', {
          category_code: change.categoryCode,
          force_operational: change.forceOperational
        });
        applySourceTraceUpdatedEntry(data.entry || null);
        saved += 1;
      }
      await refreshSourceTraceDerivedData();
      setStatus('Сохранено категорий: ' + saved);
    } catch (error) {
      setStatus(error.error || 'Не все категории сохранились', true);
    } finally {
      state.sourceCategorySavingAll = false;
      renderAll();
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
      setStatus('Изменение закрытого месяца отменено');
      return;
    }

    state.categorySaving = true;
    els.categorySelect.disabled = true;
    els.categorySave.disabled = true;
    setStatus(decision === 'create_correction' ? 'Фиксирую корректировку' : 'Пересчитываю цепочку');
    try {
      const data = await v2Api('POST', '/api/entries/' + entry.id + '/category/closed-month-decision', {
        decision,
        category_code: pending.toCategoryCode,
        reason: 'исправление категории из деталей записи'
      });
      state.selectedEntryId = data.entry.id;
      state.closedMonthDecision = null;
      els.categoryError.textContent = '';
      await loadWorkspaceData();
      setStatus(decision === 'create_correction' ? 'Корректировка зафиксирована' : 'Категория обновлена с пересчетом');
    } catch (error) {
      const message = error.error || 'Решение по закрытому месяцу не сохранено';
      els.categoryError.textContent = message;
      setStatus(message, true);
    } finally {
      state.categorySaving = false;
      renderDetail();
    }
  }

  function bindEvents() {
    bindViewportHeight();
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
    els.rawText.addEventListener('input', handleRawTextInput);
    els.rawText.addEventListener('pointerdown', (event) => {
      if (previewingEntry()) {
        activatePreviewedEntryEdit(event);
      }
    });
    els.rawText.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && previewingEntry()) activatePreviewedEntryEdit(event);
    });
    els.refresh.addEventListener('click', () => requestPeriodAction(loadApp));
    els.otherReviewJump.addEventListener('click', selectFirstOtherReview);
    els.monthToggle.addEventListener('click', toggleMonthClosure);
    if (els.archiveOpen) els.archiveOpen.addEventListener('click', openArchivePicker);
    if (els.allFeedToggle) els.allFeedToggle.addEventListener('click', toggleAllFeedView);
    if (els.reportArchiveToggle) els.reportArchiveToggle.addEventListener('click', toggleReportArchiveView);
    if (els.archiveClose) els.archiveClose.addEventListener('click', closeArchivePicker);
    (els.archiveCancel || []).forEach((button) => button.addEventListener('click', closeArchivePicker));
    if (els.archiveLoad) {
      els.archiveLoad.addEventListener('click', () => {
        const year = parseInt(els.archiveYear.value, 10);
        const month = parseInt(els.archiveMonth.value, 10);
        requestPeriodAction(() => switchOperationalPeriod({ year, month }, { allowLatestFallback: false, autoAdvanceClosed: false, preferLatest: true, scrollToBottom: true }));
      });
    }
    if (els.currentMonth) {
      els.currentMonth.addEventListener('click', () => {
        requestPeriodAction(() => switchOperationalPeriod(currentMonthParts(), { allowLatestFallback: false, preferLatest: true, scrollToBottom: true }));
      });
    }
    if (els.unsavedSave) els.unsavedSave.addEventListener('click', saveAndRunPendingPeriodAction);
    if (els.unsavedDiscard) els.unsavedDiscard.addEventListener('click', discardAndRunPendingPeriodAction);
    (els.unsavedCancel || []).forEach((button) => button.addEventListener('click', cancelPendingPeriodAction));
    if (els.closedEditConfirm) els.closedEditConfirm.addEventListener('click', confirmClosedMonthEdit);
    (els.closedEditCancel || []).forEach((button) => button.addEventListener('click', cancelClosedMonthConfirmation));
    els.feed.addEventListener('click', (event) => {
      if (event.target.closest('[data-v2-draft-row]')) {
        event.stopPropagation();
        activateCreateDraftRow('journal');
        return;
      }
      const reportRowAction = event.target.closest('[data-v2-report-row-open], [data-v2-report-row-versions]');
      if (reportRowAction) {
        const row = reportRowAction.closest('[data-v2-report-row]');
        const reportId = row ? row.getAttribute('data-v2-report-id') || '' : '';
        if (reportId) {
          event.preventDefault();
          event.stopPropagation();
          openReportFragmentById(reportId, reportRowAction.matches('[data-v2-report-row-versions]') ? 'Открываю отчет и версии' : 'Открываю отчет');
        }
        return;
      }
      const reportRow = event.target.closest('[data-v2-report-row]');
      if (reportRow) {
        const reportId = reportRow.getAttribute('data-v2-report-id') || '';
        event.preventDefault();
        event.stopPropagation();
        if (state.reportSelectionMode) {
          toggleReportPackageSelection(reportId);
        } else {
          toggleReportRowExpanded(reportId);
        }
        return;
      }
      const lockedReportBadge = event.target.closest('[data-v2-report-lock-open]');
      if (lockedReportBadge) {
        const row = lockedReportBadge.closest('[data-v2-entry-select]');
        if (row && openReportFragmentForEntryId(row.getAttribute('data-v2-entry-id') || '')) {
          event.preventDefault();
          event.stopPropagation();
        }
        return;
      }
      if (state.reportSelectionMode) {
        const row = event.target.closest('[data-v2-entry-select]');
        if (row) {
          event.preventDefault();
          event.stopPropagation();
          handleReportSelectionRow(row.getAttribute('data-v2-entry-id') || '');
        }
        return;
      }
      handleRowClick(event, '[data-v2-entry-select]', 'journal', activateJournalRow);
    });
    els.feed.addEventListener('dblclick', (event) => {
      const row = event.target.closest('[data-v2-entry-select]');
      if (row) selectEntry(row.getAttribute('data-v2-entry-id') || '', 'journal');
    });
    els.feed.addEventListener('pointerdown', (event) => {
      if (state.reportSelectionMode) return;
      const row = event.target.closest('[data-v2-entry-select]');
      if (row && event.pointerType !== 'mouse') beginEntryPreview(row.getAttribute('data-v2-entry-id') || '');
      handleRowPointerDown(event, '[data-v2-entry-select]', 'journal');
    });
    els.feed.addEventListener('focusin', (event) => {
      const row = event.target.closest('[data-v2-entry-select]');
      setFocusedSurface('journal');
      if (row && !state.reportSelectionMode) {
        const entryId = row.getAttribute('data-v2-entry-id') || '';
        setActiveEntry(entryId, 'journal');
      }
    });
    els.feed.addEventListener('scroll', () => syncVerticalScroll(els.feed));
    els.writing.addEventListener('click', (event) => {
      if (!event.target.closest('[data-v2-entry-select]')) setFocusedSurface('journal');
    });
    els.checkTable.addEventListener('click', (event) => {
      if (event.target.closest('[data-v2-check-draft-row]')) {
        event.stopPropagation();
        activateCreateDraftRow('check');
        return;
      }
      const reportRowAction = event.target.closest('[data-v2-report-row-open], [data-v2-report-row-versions]');
      if (reportRowAction) {
        const row = reportRowAction.closest('[data-v2-report-row]');
        const reportId = row ? row.getAttribute('data-v2-report-id') || '' : '';
        if (reportId) {
          event.preventDefault();
          event.stopPropagation();
          openReportFragmentById(reportId, reportRowAction.matches('[data-v2-report-row-versions]') ? 'Открываю отчет и версии' : 'Открываю отчет');
        }
        return;
      }
      const reportRow = event.target.closest('[data-v2-report-row]');
      if (reportRow) {
        const reportId = reportRow.getAttribute('data-v2-report-id') || '';
        event.preventDefault();
        event.stopPropagation();
        if (state.reportSelectionMode) {
          toggleReportPackageSelection(reportId);
        } else {
          toggleReportRowExpanded(reportId);
        }
        return;
      }
      const lockedReportBadge = event.target.closest('[data-v2-report-lock-open]');
      if (lockedReportBadge) {
        const row = lockedReportBadge.closest('[data-v2-check-row][data-v2-entry-id]');
        if (row && openReportFragmentForEntryId(row.getAttribute('data-v2-entry-id') || '')) {
          event.preventDefault();
          event.stopPropagation();
        }
        return;
      }
      if (state.reportSelectionMode) {
        const row = event.target.closest('[data-v2-check-row][data-v2-entry-id]');
        if (row) {
          event.preventDefault();
          event.stopPropagation();
          handleReportSelectionRow(row.getAttribute('data-v2-entry-id') || '');
        }
        return;
      }
      handleRowClick(event, '[data-v2-check-row][data-v2-entry-id]', 'check', activateCheckRow);
    });
    els.checkTable.addEventListener('dblclick', (event) => {
      const row = event.target.closest('[data-v2-check-row][data-v2-entry-id]');
      if (row) selectEntry(row.getAttribute('data-v2-entry-id') || '', 'check');
    });
    els.checkTable.addEventListener('pointerdown', (event) => {
      if (state.reportSelectionMode) return;
      const row = event.target.closest('[data-v2-check-row][data-v2-entry-id]');
      if (row && event.pointerType !== 'mouse') beginEntryPreview(row.getAttribute('data-v2-entry-id') || '');
      handleRowPointerDown(event, '[data-v2-check-row][data-v2-entry-id]', 'check');
    });
    els.checkTable.addEventListener('focusin', (event) => {
      const row = event.target.closest('[data-v2-check-row][data-v2-entry-id]');
      setFocusedSurface('check');
      if (row && !state.reportSelectionMode) {
        const entryId = row.getAttribute('data-v2-entry-id') || '';
        setActiveEntry(entryId, 'check');
      }
    });
    els.checkTable.addEventListener('scroll', () => {
      syncVerticalScroll(els.checkTable);
      syncStructuredHeaderScroll();
    });
    els.check.addEventListener('click', (event) => {
      if (!event.target.closest('[data-v2-check-row][data-v2-entry-id]')) setFocusedSurface('check');
    });
    els.detailClose.addEventListener('click', closeDetail);
    els.detailBackdrop.addEventListener('click', closeDetail);
    if (els.sourceClose) els.sourceClose.addEventListener('click', closeSourceTrace);
    if (els.sourceBackdrop) els.sourceBackdrop.addEventListener('click', closeSourceTrace);
    (els.reportSelectionToggles || []).forEach((button) => button.addEventListener('click', () => {
      toggleReportSelectionMode();
    }));
    if (els.reportRangeApply) els.reportRangeApply.addEventListener('click', applyReportRange);
    if (els.reportSelectionCancel) els.reportSelectionCancel.addEventListener('click', () => {
      leaveReportSelectionMode();
    });
    if (els.reportSelectionPreview) els.reportSelectionPreview.addEventListener('click', previewReportFragment);
    if (els.reportContextOpen) {
      els.reportContextOpen.addEventListener('click', () => {
        const reportId = els.reportContextOpen.getAttribute('data-v2-report-id') || '';
        if (reportId) openReportFragmentById(reportId, 'Открываю отчет по текущей ленте');
      });
    }
    document.addEventListener('click', (event) => {
      if (state.reportSelectionMode) return;
      const badge = event.target.closest('[data-v2-report-lock-open]');
      if (!badge) return;
      const row = badge.closest('[data-v2-entry-id]');
      if (!row) return;
      if (openReportFragmentForEntryId(row.getAttribute('data-v2-entry-id') || '')) {
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);
    document.addEventListener('click', (event) => {
      const row = event.target.closest('[data-v2-check-row][data-v2-report-row]');
      if (!row) return;
      const reportId = row ? row.getAttribute('data-v2-report-id') || '' : '';
      if (!reportId) return;
      event.preventDefault();
      event.stopPropagation();
      if (state.reportSelectionMode) {
        toggleReportPackageSelection(reportId);
      } else {
        toggleReportRowExpanded(reportId);
      }
    }, true);
    if (els.reportFragmentCreate) els.reportFragmentCreate.addEventListener('click', createReportFragment);
    if (els.reportFragmentPrint) els.reportFragmentPrint.addEventListener('click', printReportFragment);
    if (els.reportFragmentCloseDateSave) els.reportFragmentCloseDateSave.addEventListener('click', saveReportFragmentCloseDate);
    if (els.reportFragmentSend) els.reportFragmentSend.addEventListener('click', markReportFragmentSent);
    if (els.reportFragmentRebuild) els.reportFragmentRebuild.addEventListener('click', rebuildReportFragmentFromEntries);
    if (els.reportFragmentRevision) els.reportFragmentRevision.addEventListener('click', returnReportFragmentForRevision);
    if (els.reportFragmentCancel) els.reportFragmentCancel.addEventListener('click', cancelReportFragment);
    (els.reportFragmentClose || []).forEach((button) => button.addEventListener('click', closeReportFragment));
    if (els.reportFragmentBody) {
      els.reportFragmentBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-v2-source-total]');
        if (!button) return;
        openSourceTrace(button.getAttribute('data-v2-source-total') || '', button.getAttribute('data-v2-source-label') || '');
      });
    }
    if (els.sourceBody) {
      els.sourceBody.addEventListener('change', (event) => {
        const select = event.target.closest('[data-v2-source-category-select]');
        if (!select) return;
        const form = select.closest('[data-v2-source-category-form]');
        const entryId = form ? (form.getAttribute('data-v2-source-entry-id') || '') : '';
        if (!entryId) return;
        state.sourceCategoryDrafts[entryId] = select.value || '';
        renderSourceTrace();
      });
      els.sourceBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-v2-source-save-all]');
        if (!button) return;
        event.preventDefault();
        saveAllSourceTraceCategories();
      });
      els.sourceBody.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-v2-source-category-form]');
        if (!form) return;
        event.preventDefault();
        saveSourceTraceCategory(form);
      });
    }
    if (els.layer1SummaryRefresh) els.layer1SummaryRefresh.addEventListener('click', loadLayer1SummaryData);
    if (els.layer1StorageRefresh) {
      els.layer1StorageRefresh.addEventListener('click', () => {
        loadLayer1Snapshots();
        loadReportPackages();
      });
    }
    if (els.layer1StorageSave) els.layer1StorageSave.addEventListener('click', saveLayer1Snapshot);
    if (els.trainingRefresh) {
      els.trainingRefresh.addEventListener('click', () => {
        loadRawHistory();
        loadDictionaryReviewQueue();
        loadDictionaryTrainingDecisions();
      });
    }
    if (els.trainingScreen) {
      els.trainingScreen.addEventListener('click', (event) => {
        const rawConvert = event.target.closest('[data-v2-raw-history-convert]');
        if (rawConvert) {
          convertRawHistoryBatch(rawConvert.getAttribute('data-v2-raw-history-convert') || 'preview');
          return;
        }
        const filter = event.target.closest('[data-v2-training-filter]');
        if (filter) {
          setDictionaryTrainingFilter(filter.getAttribute('data-v2-training-filter') || 'all');
          return;
        }
        const action = event.target.closest('[data-v2-dictionary-decision-action]');
        if (action) {
          submitDictionaryTrainingDecision(action);
          return;
        }
        const smith = event.target.closest('[data-v2-smith-reference]');
        if (smith) {
          requestDictionaryInternetReference(smith);
          return;
        }
        const smithFeedback = event.target.closest('[data-v2-smith-feedback-action]');
        if (smithFeedback) {
          submitDictionaryInternetFeedback(smithFeedback);
          return;
        }
        const row = event.target.closest('[data-v2-dictionary-row][data-source-row-id]');
        if (row) selectDictionaryTrainingRow(row.getAttribute('data-source-row-id') || '');
      });
      els.trainingScreen.addEventListener('input', (event) => {
        const search = event.target.closest('[data-v2-training-search]');
        if (search) setDictionaryTrainingSearch(search.value || '');
        if (event.target.closest('[data-v2-smith-query], [data-v2-smith-url]')) {
          syncDictionaryInternetReferenceButton(event.target);
        }
      });
      els.trainingScreen.addEventListener('change', (event) => {
        if (event.target.closest('[data-v2-smith-consent]')) {
          syncDictionaryInternetReferenceButton(event.target);
        }
      });
    }
    if (els.quickNotesScreen) {
      els.quickNotesScreen.addEventListener('click', (event) => {
        const historyToggle = event.target.closest('[data-v2-quick-note-history-toggle]');
        if (historyToggle) {
          toggleQuickNoteHistory();
          return;
        }
        const deleteNote = event.target.closest('[data-v2-quick-note-delete]');
        if (deleteNote) {
          deleteQuickNote(deleteNote.getAttribute('data-v2-quick-note-delete') || '');
          return;
        }
        const select = event.target.closest('[data-v2-quick-note-select]');
        if (select) {
          openQuickNote(select.getAttribute('data-v2-quick-note-select') || '');
        }
      });
    }
    if (els.quickNoteNew) els.quickNoteNew.addEventListener('click', newQuickNote);
    if (els.quickNoteBack) els.quickNoteBack.addEventListener('click', () => switchScreen('operational'));
    if (els.quickNoteSave) els.quickNoteSave.addEventListener('click', () => saveQuickNote());
    if (els.quickNoteParse) els.quickNoteParse.addEventListener('click', previewQuickNote);
    if (els.quickNoteConvert) els.quickNoteConvert.addEventListener('click', convertQuickNote);
    if (els.quickNoteText) els.quickNoteText.addEventListener('input', scheduleQuickNoteAutoSave);
    if (els.quickNoteDate) els.quickNoteDate.addEventListener('change', scheduleQuickNoteAutoSave);
    (els.quickNoteModalClose || []).forEach((button) => button.addEventListener('click', closeQuickNoteSmith));
    if (els.layer1Information) {
      els.layer1Information.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-v2-summary-period-form]');
        if (!form) return;
        event.preventDefault();
        applySummaryPeriodFilter(form);
      });
      els.layer1Information.addEventListener('click', (event) => {
        const rawConvert = event.target.closest('[data-v2-raw-history-convert]');
        if (rawConvert) {
          convertRawHistoryBatch(rawConvert.getAttribute('data-v2-raw-history-convert') || 'preview');
          return;
        }
        const button = event.target.closest('[data-v2-source-total]');
        if (!button) return;
        openSourceTrace(button.getAttribute('data-v2-source-total') || '', button.getAttribute('data-v2-source-label') || '');
      });
    }
    els.editSave.addEventListener('click', (event) => {
      if (!activatePreviewedEntryEdit(event)) saveEntryEdit(event);
    });
    els.editDelete.addEventListener('click', deleteEntryEdit);
    if (els.mobileFinanceToggle) els.mobileFinanceToggle.addEventListener('click', toggleMobileFinanceMode);
    if (els.mobileMonthOpen) {
      els.mobileMonthOpen.addEventListener('click', () => openArchivePicker({ defaultToSelected: true }));
    }
    if (els.hallOpen) els.hallOpen.addEventListener('click', () => switchScreen('hall'));
    if (els.hall) {
      els.hall.addEventListener('click', (event) => {
        const createButton = event.target.closest('[data-v2-hall-create-open]');
        if (createButton) {
          renderShellVisibility('create');
          setStatus('Создание пространства');
        }
      });
    }
    if (els.hallWorkspaces) {
      els.hallWorkspaces.addEventListener('click', (event) => {
        const inviteButton = event.target.closest('[data-v2-hall-invite-create]');
        if (inviteButton) {
          createEmployeeInvite(inviteButton.getAttribute('data-v2-workspace-id') || '');
          return;
        }
        const offerButton = event.target.closest('[data-v2-hall-offer-create]');
        if (offerButton) {
          createAccountableOfferFromHall(offerButton.getAttribute('data-v2-workspace-id') || '');
          return;
        }
        const deleteWorkspaceButton = event.target.closest('[data-v2-hall-workspace-delete]');
        if (deleteWorkspaceButton) {
          deleteWorkspaceFromHall(
            deleteWorkspaceButton.getAttribute('data-v2-workspace-id') || '',
            deleteWorkspaceButton.getAttribute('data-v2-workspace-name') || ''
          );
          return;
        }
        const accountableButton = event.target.closest('[data-v2-hall-accountable-open]');
        if (accountableButton) {
          openHallAccountableControl(accountableButton.getAttribute('data-v2-workspace-id') || '');
          return;
        }
        const accountableRefresh = event.target.closest('[data-v2-hall-accountable-refresh]');
        if (accountableRefresh) {
          refreshHallAccountableControl(accountableRefresh.getAttribute('data-v2-workspace-id') || '');
          return;
        }
        const acceptReportButton = event.target.closest('[data-v2-hall-report-accept]');
        if (acceptReportButton) {
          acceptHallAccountableReport(
            acceptReportButton.getAttribute('data-v2-hall-report-accept') || '',
            acceptReportButton.getAttribute('data-v2-workspace-id') || ''
          );
          return;
        }
        const materializeReportButton = event.target.closest('[data-v2-hall-report-materialize]');
        if (materializeReportButton) {
          materializeHallAccountableReport(
            materializeReportButton.getAttribute('data-v2-hall-report-materialize') || '',
            materializeReportButton.getAttribute('data-v2-workspace-id') || ''
          );
          return;
        }
        const resolveSettlementButton = event.target.closest('[data-v2-hall-settlement-cash-resolve]');
        if (resolveSettlementButton) {
          resolveHallAccountableSettlement(
            resolveSettlementButton.getAttribute('data-v2-hall-settlement-cash-resolve') || '',
            resolveSettlementButton.getAttribute('data-v2-workspace-id') || '',
            resolveSettlementButton.getAttribute('data-v2-settlement-status') || '',
            resolveSettlementButton.getAttribute('data-v2-settlement-amount') || ''
          );
          return;
        }
        const openButton = event.target.closest('[data-v2-hall-workspace-open]');
        if (openButton) openWorkspace(openButton.getAttribute('data-v2-workspace-id') || '', 'operational', { restoreDraft: true });
      });
    }
    if (els.employeeHall) {
      els.employeeHall.addEventListener('click', () => {
        state.activeScreen = 'hall';
        renderShellVisibility('hall');
        renderAll();
        setStatus('Выберите пространство');
      });
    }
    if (els.employeeOffers) {
      els.employeeOffers.addEventListener('click', (event) => {
        const button = event.target.closest('[data-v2-employee-offer-accept]');
        if (button) {
          acceptEmployeeOffer(button.getAttribute('data-v2-employee-offer-accept') || '');
          return;
        }
        const addReportRow = event.target.closest('[data-v2-employee-report-add]');
        if (addReportRow) {
          addEmployeeReportDraftRow(addReportRow.getAttribute('data-v2-employee-report-add') || '');
          return;
        }
        const removeReportRow = event.target.closest('[data-v2-employee-report-remove]');
        if (removeReportRow) {
          removeEmployeeReportDraftRow(
            removeReportRow.getAttribute('data-v2-employee-report-remove') || '',
            removeReportRow.getAttribute('data-v2-employee-report-index') || ''
          );
          return;
        }
        const submitReport = event.target.closest('[data-v2-employee-report-submit]');
        if (submitReport) submitEmployeeAccountableReport(submitReport.getAttribute('data-v2-employee-report-submit') || '');
      });
    }
    if (els.inviteAccept) els.inviteAccept.addEventListener('click', acceptInvite);
    if (els.inviteDismiss) els.inviteDismiss.addEventListener('click', dismissInvite);
    if (els.createBack) {
      els.createBack.addEventListener('click', () => {
        state.activeScreen = 'hall';
        renderShellVisibility(state.workspaces.length ? 'hall' : 'create');
        renderAll();
        setStatus(state.workspaces.length ? 'Выберите пространство' : 'Создайте пространство, чтобы начать записи');
      });
    }
    if (els.logout) els.logout.addEventListener('click', logout);
    if (els.authSend) els.authSend.addEventListener('click', sendAuthCode);
    if (els.authForm) els.authForm.addEventListener('submit', verifyAuthCode);
    if (els.authEmail) {
      els.authEmail.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          sendAuthCode();
        }
      });
    }
    window.addEventListener('keydown', handleWorkspaceKey);
    els.workspaceSelect.addEventListener('change', async () => {
      state.workspaceId = els.workspaceSelect.value;
      if (state.activeScreen === 'hall') {
        renderAll();
        setStatus('Выберите пространство');
        return;
      }
      await openWorkspace(state.workspaceId, state.activeScreen, { restoreDraft: true });
    });
    $$('[data-v2-screen]').forEach((button) => {
      button.addEventListener('click', () => switchScreen(button.getAttribute('data-v2-screen') || 'operational'));
    });
    $$('[data-v2-quick-notes-open]').forEach((button) => {
      button.addEventListener('click', () => switchScreen('quick-notes'));
    });
    $$('[data-v2-summary-tab]').forEach((button) => {
      button.addEventListener('click', () => switchSummaryTab(button.getAttribute('data-v2-summary-tab') || 'information'));
    });
    $$('[data-v2-flow]').forEach((button) => {
      button.addEventListener('click', async () => {
        await switchActiveFlow(button.getAttribute('data-v2-flow') || 'cash');
      });
    });
    $$('[data-v2-view]').forEach((button) => {
      button.addEventListener('click', () => {
        $$('[data-v2-view]').forEach((item) => item.classList.toggle('is-active', item === button));
        const target = button.getAttribute('data-v2-view');
        if (target === 'quick-notes') {
          switchScreen('quick-notes');
          return;
        }
        const panel = target === 'check' ? els.check : els.writing;
        setFocusedSurface(target === 'check' ? 'check' : 'journal');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
      });
    });
    window.addEventListener('online', () => setStatus('Связь восстановлена'));
    window.addEventListener('offline', () => setStatus('Офлайн: черновик сохранен локально', true));
  }

  function bindViewportHeight() {
    let frame = 0;
    const sync = () => {
      if (frame) window.cancelAnimationFrame(frame);
      frame = window.requestAnimationFrame(() => {
        frame = 0;
        const viewport = window.visualViewport;
        const visibleHeight = viewport ? Math.min(window.innerHeight, viewport.height) : window.innerHeight;
        const height = Math.max(320, Math.floor(visibleHeight));
        document.documentElement.style.setProperty('--v2-visual-viewport-height', height + 'px');
        document.body.classList.toggle('v2-keyboard-open', viewport ? window.innerHeight - viewport.height > 120 : false);
      });
    };
    sync();
    window.addEventListener('resize', sync, { passive: true });
    window.addEventListener('orientationchange', sync, { passive: true });
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', sync, { passive: true });
      window.visualViewport.addEventListener('scroll', sync, { passive: true });
    }
  }

  bindEvents();
  loadApp();
})();
