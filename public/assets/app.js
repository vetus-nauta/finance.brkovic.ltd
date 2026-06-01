let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', function(event) {
  event.preventDefault();
  deferredPrompt = event;
});

function isStandaloneMode() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function qlDeviceInfo() {
  const ua = navigator.userAgent || '';
  const platform = navigator.platform || '';

  const isIOS = /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isAndroid = /Android/i.test(ua);
  const isIOSChrome = /CriOS/i.test(ua);
  const isIOSFirefox = /FxiOS/i.test(ua);
  const isIOSEdge = /EdgiOS/i.test(ua);
  const isSafari = isIOS && /Safari/i.test(ua) && !isIOSChrome && !isIOSFirefox && !isIOSEdge;
  const isDesktop = !isIOS && !isAndroid;

  return { ua, isIOS, isAndroid, isIOSChrome, isIOSFirefox, isIOSEdge, isSafari, isDesktop };
}

function qlInstallText(type) {
  const d = qlDeviceInfo();

  if (isStandaloneMode()) {
    return '<h3>Already installed</h3><p>FinDesk is already running as a web app.</p>';
  }

  if (!type || type === 'auto') {
    if (d.isIOS && d.isSafari) type = 'ios-safari';
    else if (d.isIOS && d.isIOSChrome) type = 'ios-chrome';
    else if (d.isIOS) type = 'ios-other';
    else if (d.isAndroid) type = 'android';
    else if (d.isDesktop) type = 'desktop';
    else type = 'generic';
  }

  if (type === 'ios' || type === 'ios-safari') {
    return `
      <h3>Install on iPhone / iPad</h3>
      <ol>
        <li>Open this page in <b>Safari</b>.</li>
        <li>Tap the real iPhone <b>Share</b> button: the square with an arrow up.</li>
        <li>Scroll down and choose <b>Add to Home Screen</b>.</li>
        <li>If you do not see it, tap <b>Edit Actions</b> and add <b>Add to Home Screen</b>.</li>
        <li>Tap <b>Add</b>.</li>
      </ol>
      <p class="soft-note">Your records are saved in your account after login, not only on this phone.</p>
    `;
  }

  if (type === 'ios-chrome') {
    return `
      <h3>Install from iPhone Chrome</h3>
      <ol>
        <li>Tap the <b>Share</b> button in Chrome.</li>
        <li>Look for <b>Add to Home Screen</b>.</li>
        <li>If this option is not visible, open this page in <b>Safari</b>.</li>
        <li>In Safari, use Share → <b>Add to Home Screen</b>.</li>
      </ol>
      <p class="soft-note">On iPhone, Safari is the most reliable way to install a web app on the Home Screen.</p>
    `;
  }

  if (type === 'ios-other') {
    return `
      <h3>Install on iPhone / iPad</h3>
      <p>For the most reliable installation, open this page in <b>Safari</b>.</p>
      <ol>
        <li>Tap Safari Share: the square with an arrow up.</li>
        <li>Choose <b>Add to Home Screen</b>.</li>
        <li>Tap <b>Add</b>.</li>
      </ol>
      <p class="soft-note">Your records are saved in your account after login, not only on this phone.</p>
    `;
  }

  if (type === 'android') {
    if (deferredPrompt) {
      return `
        <h3>Install on Android</h3>
        <p>Chrome can install FinDesk as a web app on your Home screen.</p>
        <button id="nativeInstallBtn" class="primary-btn wide-btn" type="button">Install now</button>
        <p class="soft-note">Your records are saved in your account after login, not only on this device.</p>
      `;
    }

    return `
      <h3>Install on Android</h3>
      <ol>
        <li>Open this page in <b>Chrome</b>.</li>
        <li>Tap the browser menu <b>⋮</b>.</li>
        <li>Choose <b>Install app</b> or <b>Add to Home screen</b>.</li>
      </ol>
      <p class="soft-note">If the option is not visible yet, reload the page and open this install help again.</p>
    `;
  }

  if (type === 'windows' || type === 'desktop') {
    if (deferredPrompt) {
      return `
        <h3>Install on computer</h3>
        <p>Chrome or Edge can install FinDesk as a web app.</p>
        <button id="nativeInstallBtn" class="primary-btn wide-btn" type="button">Install now</button>
        <p class="soft-note">You can also use the install icon in the browser address bar or browser menu.</p>
      `;
    }

    return `
      <h3>Install on computer</h3>
      <ol>
        <li>Open this page in <b>Chrome</b> or <b>Edge</b>.</li>
        <li>Look for the install icon in the address bar, or open the browser menu.</li>
        <li>Choose <b>Install FinDesk</b> or <b>Install app</b>.</li>
      </ol>
      <p class="soft-note">If the browser does not show install yet, keep using FinDesk in the browser and try again after reload.</p>
    `;
  }

  return `
    <h3>Install Web App</h3>
    <p>Open this page in Safari on iPhone/iPad, Chrome on Android, or Chrome/Edge on desktop, then use the browser install option.</p>
    <p class="soft-note">Your records are saved in your account after login, not only on this device.</p>
  `;
}

function openInstall(type) {
  const modal = document.getElementById('installModal');
  const content = document.getElementById('installContent');
  if (!modal || !content) return;

  content.innerHTML = qlInstallText(type || 'auto');
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
}

async function runNativeInstallPrompt() {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  try {
    await deferredPrompt.userChoice;
  } catch (e) {}
  deferredPrompt = null;
}

function closeModals() {
  document.querySelectorAll('.modal').forEach(function(modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  });
}

function qlCloseTransientPanels() {
  closeModals();

  const modulePanel = document.querySelector('[data-module-menu-panel]');
  if (modulePanel) {
    modulePanel.classList.add('hidden');
  }

  document.querySelectorAll('[data-module-menu-toggle]').forEach(function(btn) {
    btn.setAttribute('aria-expanded', 'false');
  });

  document.querySelectorAll('.otr-gate-menu[open]').forEach(function(menu) {
    menu.removeAttribute('open');
  });

  document.body.classList.remove('otr-stream-gate-open', 'otr-cards-open', 'otr-editor-open');

  ['#advancedExcelPreviewModal', '#otrStreamGate', '#otrReportCardsPanel', '#otrSimpleCard'].forEach(function(selector) {
    const node = document.querySelector(selector);
    if (!node) return;
    node.classList.add('hidden');
    node.setAttribute('aria-hidden', 'true');
  });
}

document.addEventListener('click', function(event) {
  const installButton = event.target.closest('[data-open-install]');
  const nativeInstallButton = event.target.closest('#nativeInstallBtn');
  const donateButton = event.target.closest('[data-open-donate]');
  const closeButton = event.target.closest('[data-close-modal]');

  if (installButton) openInstall(installButton.getAttribute('data-open-install') || 'auto');
  if (nativeInstallButton) runNativeInstallPrompt();
  if (donateButton && window.openDonateModal) window.openDonateModal();
  if (closeButton) closeModals();

  if (event.target.classList && event.target.classList.contains('modal')) {
    closeModals();
  }
});

if ('serviceWorker' in navigator && !['127.0.0.1', 'localhost'].includes(window.location.hostname)) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/service-worker.js').catch(function(){});
  });
} else if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.getRegistrations()
      .then(function(registrations) {
        registrations.forEach(function(registration) {
          registration.unregister().catch(function(){});
        });
      })
      .catch(function(){});

    if (window.caches && caches.keys) {
      caches.keys()
        .then(function(keys) {
          keys
            .filter(function(key) { return key.indexOf('findesk-') === 0; })
            .forEach(function(key) { caches.delete(key).catch(function(){}); });
        })
        .catch(function(){});
    }
  });
}

/* === FinDesk Auth UI 20260503-02 === */
let qlCurrentUser = null;

async function qlApi(action, payload) {
  const publicActions = ['current_user', 'request_code', 'verify_code', 'logout'];
  if (!qlCurrentUser && !publicActions.includes(action)) {
    return {ok: false, error: 'not_authenticated_client'};
  }

  const response = await fetch('/api.php?action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    credentials: 'same-origin',
    body: JSON.stringify(payload || {})
  });

  const text = await response.text();

  try {
    return JSON.parse(text);
  } catch (error) {
    return {
      ok: false,
      error: 'bad_json',
      message: text.slice(0, 300)
    };
  }
}

function qlShowAuthMessage(message) {
  const el = document.getElementById('authMessage');
  if (el) el.textContent = message || '';
}

function qlAuthText(key, fallback) {
  const text = typeof window.cfT === 'function' ? window.cfT(key) : '';
  return text && text !== key ? text : fallback;
}

function qlAuthErrorMessage(error) {
  const key = 'auth.error.' + (error || 'unknown');
  const message = qlAuthText(key, '');
  return message && message !== key ? message : qlAuthText('auth.error.unknown', 'We could not complete sign in. Try again.');
}

function qlShowPanel(name) {
  const loading = document.getElementById('authStateLoading');
  const login = document.getElementById('loginPanel');
  const user = document.getElementById('userPanel');

  if (loading) loading.classList.add('hidden');
  if (login) login.classList.add('hidden');
  if (user) user.classList.add('hidden');

  if (name === 'login' && login) login.classList.remove('hidden');
  if (name === 'user' && user) user.classList.remove('hidden');
}

function qlRenderUser(user) {
  qlCurrentUser = user || null;

  const name = document.getElementById('userName');
  const email = document.getElementById('userEmail');

  if (name) name.textContent = user.display_name || 'User';
  if (email) email.textContent = user.email || '';
}

async function qlCheckCurrentUser() {
  try {
    const data = await qlApi('current_user', {});
    if (data.ok && data.user) {
      qlRenderUser(data.user);
      qlShowPanel('user');
      return;
    }
  } catch (error) {
    qlShowAuthMessage(qlAuthText('auth.message.sessionCheckFailed', 'We could not check your session. Refresh FinDesk or sign in again.'));
  }

  qlCurrentUser = null;
  qlShowPanel('login');
}

function qlIsSignedIn() {
  return !!(qlCurrentUser && qlCurrentUser.id);
}

async function qlRequireSignedInForBackgroundLoad() {
  if (qlIsSignedIn()) return true;
  try {
    const data = await qlApi('current_user', {});
    if (data.ok && data.user) {
      qlRenderUser(data.user);
      return true;
    }
  } catch (error) {}

  return false;
}

async function qlRunWhenSignedIn(loader) {
  if (typeof loader !== 'function') return;
  if (await qlRequireSignedInForBackgroundLoad()) {
    return loader();
  }
}

function qlRunWhenSignedInSoon(loader, delay) {
  setTimeout(function() {
    qlRunWhenSignedIn(loader);
  }, delay || 0);
}

async function qlSendCode() {
  const email = (document.getElementById('loginEmail')?.value || '').trim();
  if (!email) {
    qlShowAuthMessage(qlAuthText('auth.message.enterEmail', 'Enter your email.'));
    return;
  }

  qlShowAuthMessage(qlAuthText('auth.message.sendingCode', 'Sending sign-in code...'));
  const data = await qlApi('request_code', {email});

  if (data.ok) {
    document.getElementById('codeBlock')?.classList.remove('hidden');
    if (data.dev_code) {
      const codeInput = document.getElementById('loginCode');
      if (codeInput) {
        codeInput.value = data.dev_code;
        codeInput.focus();
      }
      qlShowAuthMessage(qlAuthText('auth.message.localCode', 'Sign-in code: {code}. It is already filled in.').replace('{code}', data.dev_code));
    } else {
      qlShowAuthMessage(qlAuthText('auth.message.codeSent', 'Code sent. Check your email and enter the 6-digit code.'));
    }
  } else {
    qlShowAuthMessage(qlAuthErrorMessage(data.error));
  }
}

async function qlVerifyCode() {
  const email = (document.getElementById('loginEmail')?.value || '').trim();
  const code = (document.getElementById('loginCode')?.value || '').trim();

  if (!email || !code) {
    qlShowAuthMessage(qlAuthText('auth.message.enterEmailAndCode', 'Enter your email and the code.'));
    return;
  }

  qlShowAuthMessage(qlAuthText('auth.message.verifyingCode', 'Verifying code...'));
  const data = await qlApi('verify_code', {email, code});

  if (data.ok && data.user) {
    qlRenderUser(data.user);
    qlShowPanel('user');
  } else {
    qlShowAuthMessage(qlAuthErrorMessage(data.error));
  }
}

async function qlLogout() {
  await qlApi('logout', {});
  qlCurrentUser = null;
  qlShowPanel('login');
  qlShowAuthMessage(qlAuthText('auth.message.loggedOut', 'Signed out.'));
}

document.addEventListener('DOMContentLoaded', function() {
  const send = document.getElementById('sendCodeBtn');
  const verify = document.getElementById('verifyCodeBtn');
  const logout = document.getElementById('logoutBtn');

  if (send) send.addEventListener('click', qlSendCode);
  if (verify) verify.addEventListener('click', qlVerifyCode);
  if (logout) logout.addEventListener('click', qlLogout);

  if (document.getElementById('loginPanel')) {
    qlCheckCurrentUser();
  }
});

/* === FinDesk Personal Ledger UI 20260503-03 === */
let qlLedgerType = 'income';
let qlMoneyType = 'cash';

function qlCurrency(value) {
  const n = Number(value || 0);
  return '€' + n.toFixed(2);
}

function qlSignedCurrency(value) {
  const n = Number(value || 0);
  return (n < 0 ? '-' : '') + '€' + Math.abs(n).toFixed(2);
}

function qlLedgerMessage(message) {
  const el = document.getElementById('ledgerMessage');
  if (el) el.textContent = message || '';
}

function qlSetSegment(button, attr, valueSetter) {
  document.querySelectorAll('[' + attr + ']').forEach(function(btn) {
    btn.classList.remove('active');
  });
  button.classList.add('active');
  valueSetter(button.getAttribute(attr));
}

function qlFormatDateLabel(dateText) {
  const d = new Date(String(dateText).replace(' ', 'T'));
  if (isNaN(d.getTime())) return dateText.slice(0, 10);
  return d.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: 'numeric'});
}

function qlFormatTime(dateText) {
  const d = new Date(String(dateText).replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString(undefined, {hour: '2-digit', minute: '2-digit'});
}

async function qlLoadLedger() {
  const feed = document.getElementById('ledgerFeed');
  if (!feed) return;

  const data = await qlApi('ledger_list', {limit: 150});

  if (!data.ok) {
    feed.innerHTML = '<p class="soft-note">Ошибка журнала: ' + (data.error || 'unknown') + '</p>';
    return;
  }

  qlRenderLedger(data.entries || [], data.summary || {});
}

function qlRenderLedger(entries, summary) {
  const feed = document.getElementById('ledgerFeed');
  const count = document.getElementById('ledgerCount');
  const income = document.getElementById('ledgerIncome');
  const expense = document.getElementById('ledgerExpense');
  const balance = document.getElementById('ledgerBalance');
  const balanceLabel = document.getElementById('ledgerBalanceLabel');
  const feedTitle = document.getElementById('ledgerFeedTitle');

  if (count) count.textContent = entries.length + ' записей';
  if (income) income.textContent = qlCurrency(summary.income || 0);
  if (expense) expense.textContent = qlCurrency(summary.expense || 0);
  if (balance) balance.textContent = qlCurrency(summary.balance || 0);
  if (balanceLabel) balanceLabel.textContent = summary.open_period ? 'Открытый период' : 'Учетный баланс';
  if (feedTitle) feedTitle.textContent = summary.open_period ? 'Открытый журнал' : 'Журнал учета';

  if (!feed) return;

  if (!entries.length) {
    feed.innerHTML = '<p class="soft-note">' + (summary.open_period ? 'В открытом периоде пока нет новых записей.' : 'Записей пока нет. Добавьте первую ниже.') + '</p>';
    return;
  }

  const dayBalances = {};
  entries.forEach(function(entry) {
    const day = String(entry.entry_datetime).slice(0, 10);
    if (!dayBalances[day]) dayBalances[day] = 0;
    const amount = Number(entry.amount || 0);
    dayBalances[day] += entry.entry_type === 'income' ? amount : -amount;
  });

  let html = '';
  let currentDay = '';

  entries.forEach(function(entry, index) {
    const day = String(entry.entry_datetime).slice(0, 10);

    if (day !== currentDay) {
      if (currentDay) {
        html += '<div class="day-total">Итог дня: ' + qlCurrency(dayBalances[currentDay]) + '</div>';
      }
      currentDay = day;
      html += '<div class="day-divider">' + qlFormatDateLabel(entry.entry_datetime) + '</div>';
    }

    const sign = entry.entry_type === 'income' ? '+' : '-';
    const isVirtual = !!entry.virtual_source;
    const edited = entry.edited_at ? ' · изменено' : '';
    const doc = Number(entry.file_count || 0) > 0 ? ' · с документом' : ' · без документа';
    const categoryName = qlLedgerPublicCategoryName(entry.category_name || '');
    const cat = categoryName ? ' · ' + categoryName : '';
    const actions = entry.virtual_source === 'carryover'
      ? '<span class="entry-virtual-note">База открытого периода</span>'
      : isVirtual
      ? '<span class="entry-virtual-note">Включено из живого отчета</span>'
      : `
            <button class="entry-edit" type="button" data-edit-entry="${entry.id}">✎ Изменить</button>
            <button class="entry-delete" type="button" data-delete-entry="${entry.id}">В архив</button>
          `;

    html += `
      <article class="entry-row ${entry.entry_type}${isVirtual ? ' virtual-entry' : ''}" data-entry-id="${escapeHtml(entry.id)}">
        <div>
          <div class="entry-purpose">${escapeHtml(entry.purpose)}</div>
          <div class="entry-meta">${qlFormatTime(entry.entry_datetime)} · ${entry.money_type}${cat}${doc}${edited}</div>
          <div class="entry-actions">
            ${actions}
          </div>
        </div>
        <div class="entry-amount">${sign}${qlCurrency(entry.amount)}</div>
      </article>
    `;

    if (index === entries.length - 1) {
      html += '<div class="day-total">Итог дня: ' + qlCurrency(dayBalances[currentDay]) + '</div>';
    }
  });

  feed.innerHTML = html;
}

function escapeHtml(text) {
  return String(text ?? '').replace(/[&<>"']/g, function(ch) {
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
  });
}

async function qlSaveLedgerEntry() {
  const amount = (document.getElementById('ledgerAmount')?.value || '').trim();
  const purpose = (document.getElementById('ledgerPurpose')?.value || '').trim();
  const fileInput = document.getElementById('ledgerFile');
  const selectedFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

  if (!amount || !purpose) {
    qlLedgerMessage('Введите сумму и назначение.');
    return;
  }

  qlLedgerMessage(selectedFile ? 'Сохраняю запись и файл…' : 'Сохраняю…');

  const data = await qlApi('ledger_create', {
    entry_type: qlLedgerType,
    money_type: qlMoneyType,
    amount,
    purpose
  });

  if (!data.ok) {
    qlLedgerMessage('Ошибка: ' + (data.error || 'unknown'));
    return;
  }

  if (selectedFile && data.entry && data.entry.id) {
    const upload = await qlUploadEntryFile(data.entry.id, selectedFile);
    if (!upload.ok) {
      qlLedgerMessage('Запись сохранена, но файл не загрузился: ' + (upload.error || 'unknown'));
      await qlLoadLedger();
      return;
    }
  }

  const amountEl = document.getElementById('ledgerAmount');
  const purposeEl = document.getElementById('ledgerPurpose');
  const fileNameEl = document.getElementById('ledgerFileName');

  if (amountEl) amountEl.value = '';
  if (purposeEl) purposeEl.value = '';
  if (fileInput) fileInput.value = '';
  if (fileNameEl) fileNameEl.textContent = 'Файл не выбран';

  qlLedgerMessage('Сохранено.');
  await qlLoadLedger();
}

async function qlUploadEntryFile(entryId, file) {
  const form = new FormData();
  form.append('entry_id', entryId);
  form.append('file', file);

  const response = await fetch('/api.php?action=ledger_upload_file', {
    method: 'POST',
    credentials: 'same-origin',
    body: form
  });

  const text = await response.text();

  try {
    return JSON.parse(text);
  } catch (error) {
    return {
      ok: false,
      error: 'bad_json',
      message: text.slice(0, 300)
    };
  }
}

async function qlEditEntry(entryId) {
  const row = document.querySelector('[data-entry-id="' + entryId + '"]');
  if (!row) return;

  const currentPurpose = row.querySelector('.entry-purpose')?.textContent || '';
  const currentAmount = row.querySelector('.entry-amount')?.textContent.replace(/[€+\-]/g, '') || '';
  const isIncome = row.classList.contains('income');

  const amount = prompt('Amount', currentAmount);
  if (amount === null) return;

  const purpose = prompt('Назначение', currentPurpose);
  if (purpose === null) return;

  const type = confirm('OK = приход, отмена = расход') ? 'income' : 'expense';
  const money = confirm('OK = наличные, отмена = безнал') ? 'cash' : 'noncash';

  const data = await qlApi('ledger_update', {
    id: entryId,
    amount,
    purpose,
    entry_type: type,
    money_type: money
  });

  if (!data.ok) {
    alert('Error: ' + (data.error || 'unknown'));
    return;
  }

  await qlLoadLedger();
}

async function qlDeleteEntry(entryId) {
  if (!confirm('Archive this record? It will be hidden, not physically deleted.')) {
    return;
  }

  const data = await qlApi('ledger_delete', {id: entryId});

  if (!data.ok) {
    alert('Error: ' + (data.error || 'unknown'));
    return;
  }

  await qlLoadLedger();
}

document.addEventListener('click', function(event) {
  const ledgerTypeBtn = event.target.closest('[data-ledger-type]');
  const moneyTypeBtn = event.target.closest('[data-money-type]');
  const saveBtn = event.target.closest('#saveLedgerBtn');
  const createSectionBtn = event.target.closest('#createSectionBtn');
  const editBtn = event.target.closest('[data-edit-entry]');
  const deleteBtn = event.target.closest('[data-delete-entry]');

  if (ledgerTypeBtn) qlSetSegment(ledgerTypeBtn, 'data-ledger-type', function(v) { qlLedgerType = v; });
  if (moneyTypeBtn) qlSetSegment(moneyTypeBtn, 'data-money-type', function(v) { qlMoneyType = v; });
  if (saveBtn) qlSaveLedgerEntry();
  if (createSectionBtn) qlCreateSection();
  if (editBtn) qlEditEntry(editBtn.getAttribute('data-edit-entry'));
  if (deleteBtn) qlDeleteEntry(deleteBtn.getAttribute('data-delete-entry'));
});

const qlOriginalRenderUser = window.qlRenderUser || qlRenderUser;
qlRenderUser = function(user) {
  qlOriginalRenderUser(user);
  setTimeout(qlLoadLedger, 50);
};


document.addEventListener('change', function(event) {
  const fileInput = event.target.closest('#ledgerFile');
  if (!fileInput) return;

  const fileNameEl = document.getElementById('ledgerFileName');
  const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

  if (fileNameEl) {
    fileNameEl.textContent = file ? file.name : 'Файл не выбран';
  }
});




/* === FinDesk Personal Report UI 20260503-06 === */
let qlReportPeriod = 'today';
let qlFinalReports = [];
let qlSelectedFinalReportId = null;

function qlToggleReportPanel() {
  const panel = document.getElementById('reportPanel');
  if (!panel) return;

  panel.classList.toggle('hidden');

  if (!panel.classList.contains('hidden')) {
    qlRunReport();
    qlLoadFinalReports();
  }
}

function qlSetReportPeriod(btn) {
  document.querySelectorAll('[data-report-period]').forEach(function(el) {
    el.classList.remove('active');
  });

  btn.classList.add('active');
  qlReportPeriod = btn.getAttribute('data-report-period') || 'today';

  const custom = document.getElementById('customPeriod');
  if (custom) {
    custom.classList.toggle('hidden', qlReportPeriod !== 'custom');
  }

  qlRunReport();
}

function qlApplyOpenPeriodReportData(data, openData) {
  const finalizedAt = openData && openData.ok && openData.finalized_at ? String(openData.finalized_at) : '';
  if (!finalizedAt) return data;

  const carryovers = Array.isArray(openData.carryovers) && openData.carryovers.length
    ? openData.carryovers
    : (openData.carryover ? [openData.carryover] : []);
  const live = openData.open_period && openData.open_period.live_included ? openData.open_period.live_included : {};
  const newIncome = (openData.entries || []).reduce(function(sum, entry) {
    return sum + Number(entry.amount || 0);
  }, 0);
  const carryoverAmount = carryovers.reduce(function(sum, row) {
    return sum + Number(row.amount || 0);
  }, 0);
  const cashExpense = Number(live.cash_expense || 0);
  const noncashExpense = Number(live.noncash_expense || 0);
  const expense = cashExpense + noncashExpense;
  const balance = carryoverAmount + newIncome - expense;

  data.period = {
    type: 'open_period',
    from: finalizedAt.slice(0, 10),
    to: new Date().toISOString().slice(0, 10)
  };
  data.summary = {
    income: carryoverAmount + newIncome,
    expense: expense,
    balance: balance,
    cash_income: carryoverAmount + newIncome,
    cash_expense: cashExpense,
    cash_balance: carryoverAmount + newIncome - cashExpense,
    noncash_income: 0,
    noncash_expense: noncashExpense,
    noncash_balance: 0 - noncashExpense,
    records: carryovers.length + (openData.entries || []).length + Number(live.records || 0)
  };
  data.sections = [];
  if (carryovers.length) {
    data.sections.push({
      name: 'Переходящий остаток из финального отчета',
      records: carryovers.length,
      income: carryoverAmount,
      expense: 0,
      balance: carryoverAmount
    });
  }
  if ((openData.entries || []).length) {
    data.sections.push({
      name: 'Поступления периода',
      records: (openData.entries || []).length,
      income: newIncome,
      expense: 0,
      balance: newIncome
    });
  }
  if (Number(live.cards || 0) > 0 || expense > 0) {
    data.sections.push({
      name: 'Живой отчет текущего периода',
      records: Number(live.records || live.cards || 0),
      income: 0,
      expense: expense,
      balance: 0 - expense
    });
  }
  data.members = [];
  data.open_period = true;
  return data;
}

function qlFinalReportStatus(message) {
  const el = document.getElementById('finalReportStatus');
  if (el) el.textContent = message || '';
}

function qlFinalReportsActiveGroupId() {
  return qlLedgerScopeMode === 'group' && qlLedgerGroupId ? Number(qlLedgerGroupId) : 0;
}

function qlFinalReportDate(value) {
  return String(value || '').slice(0, 16).replace('T', ' ');
}

function qlFinalReportRows(rows) {
  if (Array.isArray(rows)) return rows;
  if (rows && typeof rows === 'object') {
    return Object.keys(rows).map(function(key) {
      return rows[key];
    });
  }
  return [];
}

function qlRenderFinalReportsList(reports, selectedId) {
  const list = document.getElementById('finalReportsList');
  if (!list) return;

  if (!reports.length) {
    list.innerHTML = '<p class="soft-note tight-note">Закрытых финальных отчетов пока нет.</p>';
    return;
  }

  list.innerHTML = reports.map(function(report) {
    const id = Number(report.report_id || report.id || 0);
    const totals = report.totals || {};
    const active = String(id) === String(selectedId || '');
    const packageNote = report.package_available
      ? 'полный пакет доступен'
      : (report.snapshot_available ? 'только снимок' : 'пакет недоступен');
    return `
      <button class="final-report-row ${active ? 'active' : ''}" type="button" data-final-report-open="${escapeHtml(id)}">
        <span>
          <b>Закрытый групповой отчет #${escapeHtml(id)}</b>
          <small>${escapeHtml(qlFinalReportDate(report.finalized_at))} · ${escapeHtml(report.finalized_by_display_name || report.finalized_by_email || 'Система')} · ${escapeHtml(packageNote)}</small>
        </span>
        <span>
          <strong>${qlCurrency(totals.balance || 0)}</strong>
          <small>приход ${qlCurrency(totals.income || 0)} / расход ${qlCurrency(totals.expense || 0)}</small>
        </span>
      </button>
    `;
  }).join('');
}

function qlFinalReportMetric(label, value) {
  return '<div><span>' + escapeHtml(label) + '</span><b>' + qlCurrency(value || 0) + '</b></div>';
}

function qlFinalReportArticleLabel(row) {
  const income = Number(row.income || 0);
  const expense = Number(row.expense || 0);
  if (income > 0.009 && expense <= 0.009) return 'Приход ' + qlCurrency(income);
  if (expense > 0.009 && income <= 0.009) return 'Расход ' + qlCurrency(expense);
  return 'Итог ' + qlCurrency(row.balance || 0);
}

function qlFinalReportMemberLabel(row) {
  const income = Number(row.income || 0);
  const expense = Number(row.expense || 0);
  if (expense > 0.009) return 'Потратил ' + qlCurrency(expense);
  if (income > 0.009) return 'Внес ' + qlCurrency(income);
  return 'Нет движения';
}

function qlFinalReportMiniRows(title, rows, labelFn, emptyText) {
  if (!rows.length) return '';
  return `
    <div class="final-report-mini-section">
      <h4>${escapeHtml(title)}</h4>
      ${rows.map(function(row) {
        return `
          <div class="section-report-row">
            <div>
              <b>${escapeHtml(row.name || row.owner_display_name || row.email || 'Без названия')}</b>
              <small>${escapeHtml(row.email || '')}${row.records !== undefined ? ' · ' + escapeHtml(row.records) + ' строк' : ''}</small>
            </div>
            <div>
              <strong>${escapeHtml(labelFn(row))}</strong>
            </div>
          </div>
        `;
      }).join('') || '<p class="soft-note tight-note">' + escapeHtml(emptyText || 'Нет строк.') + '</p>'}
    </div>
  `;
}

function qlFinalPackageArray(rows) {
  return qlFinalReportRows(rows || []);
}

function qlFinalPackageProofMap(packageData) {
  const map = {};
  qlFinalPackageArray(packageData.proofs).forEach(function(proof) {
    const id = proof.proof_id || proof.id || '';
    if (id) map[String(id)] = proof;
  });
  return map;
}

function qlFinalPackageMoney(value) {
  return qlCurrency(Number(value || 0));
}

function qlFinalPackageEffect(value) {
  const amount = Number(value || 0);
  if (Math.abs(amount) < 0.005) return qlCurrency(0);
  return (amount > 0 ? '+' : '') + qlCurrency(amount);
}

function qlFinalPackageLabel(value, fallback) {
  return String(value || fallback || '').replace(/_/g, ' ');
}

function qlFinalPackageStatusLabel(status) {
  const value = String(status || '').toLowerCase();
  if (value === 'closed') return 'Закрыт';
  if (value === 'accepted') return 'Принят';
  if (value === 'accepted_in_group_final_report') return 'Принят в финальный отчет';
  if (value === 'submitted') return 'Отправлен';
  if (value === 'archived') return 'В архиве';
  if (value === 'returned') return 'Возвращен';
  if (value === 'open') return 'Открыт';
  return status ? qlFinalPackageLabel(status, 'Статус не указан') : 'Статус не указан';
}

function qlFinalPackageMoneyTypeLabel(value) {
  const type = String(value || '').toLowerCase();
  if (type === 'cash') return 'Наличные';
  if (type === 'card' || type === 'noncash' || type === 'non_cash') return 'Карта';
  return value ? qlFinalPackageLabel(value, 'Деньги') : 'Деньги';
}

function qlFinalPackageEntryTypeLabel(value) {
  const type = String(value || '').toLowerCase();
  if (type === 'income' || type === 'cash_in' || type === 'in' || type === 'received') return 'приход';
  if (type === 'expense' || type === 'cash_out' || type === 'out' || type === 'spent') return 'расход';
  if (type === 'return' || type === 'returned') return 'возврат';
  return value ? qlFinalPackageLabel(value, 'движение') : 'движение';
}

function qlFinalPackageStreamLabel(value) {
  const stream = String(value || '').toLowerCase();
  if (stream === 'cash') return 'Наличные';
  if (stream === 'card') return 'Карта';
  if (stream === 'on_the_go_card') return 'Карточка участника';
  return value ? qlFinalPackageLabel(value, 'Поток') : 'Поток не указан';
}

function qlFinalPackageFileSize(bytes) {
  const value = Number(bytes || 0);
  if (!value) return '';
  if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(1) + ' MB';
  if (value >= 1024) return Math.round(value / 1024) + ' KB';
  return value + ' B';
}

function qlProofRoleLabel(value) {
  const role = String(value || '').toLowerCase();
  if (role === 'scanner_original') return 'Оригинал скана';
  if (role === 'scanner_cleaned_pdf') return 'Очищенный PDF';
  return 'Вложение';
}

function qlFinalPackageMetric(label, value, note) {
  return `
    <div class="final-package-metric">
      <span>${escapeHtml(label)}</span>
      <b>${qlFinalPackageMoney(value)}</b>
      ${note ? '<small>' + escapeHtml(note) + '</small>' : ''}
    </div>
  `;
}

function qlFinalPackageProofLinks(proofIds, proofMap) {
  const ids = qlFinalPackageArray(proofIds).map(function(id) {
    return String(id || '').trim();
  }).filter(Boolean);

  if (!ids.length) {
    return '<span class="final-package-proof is-missing">Доказательств нет в пакете</span>';
  }

  return `
    <div class="final-package-proofs">
      ${ids.map(function(id) {
	        const proof = proofMap[String(id)] || {};
	        const name = proof.original_name || proof.stored_name || ('proof #' + id);
	        const size = qlFinalPackageFileSize(proof.size_bytes);
	        const meta = [qlProofRoleLabel(proof.proof_role), proof.mime_type || '', size].filter(Boolean).join(' · ');
        if (proof.download_url) {
          return `
            <a class="final-package-proof" href="${escapeHtml(proof.download_url)}" target="_blank" rel="noopener">
              <span>${escapeHtml(name)}</span>
              ${meta ? '<small>' + escapeHtml(meta) + '</small>' : ''}
            </a>
          `;
        }
        return `
          <span class="final-package-proof is-missing">
            <span>${escapeHtml(name)}</span>
            <small>Файл недоступен в пакете</small>
          </span>
        `;
      }).join('')}
    </div>
  `;
}

function qlFinalPackagePerson(person, fallback) {
  if (!person) return fallback || 'Участник';
  return person.name || person.display_name || person.email || fallback || 'Участник';
}

function qlFinalPackageSummarySection(summary) {
  const carryover = summary.carryover || {};
  const carryoverNote = [
    'касса ' + qlFinalPackageMoney(carryover.admin_cash_left || 0),
    'сотрудники ' + qlFinalPackageMoney(carryover.employee_cash_left || 0),
    'баланс ' + qlFinalPackageMoney(carryover.balance || summary.balance || 0)
  ].join(' · ');

  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Сводка закрытого отчета</h4>
        <span>Итог по report_id</span>
      </div>
      <div class="final-package-metrics">
        ${qlFinalPackageMetric('Получено', summary.received_money)}
        ${qlFinalPackageMetric('Потрачено наличными', summary.physical_cash_spent)}
        ${qlFinalPackageMetric('Потрачено картой', summary.card_noncash_spent)}
        ${qlFinalPackageMetric('Осталось в кассе', summary.admin_cash_left)}
        ${qlFinalPackageMetric('Сотрудники net', summary.employee_net_remaining_total ?? summary.accountable_money_left)}
        ${qlFinalPackageMetric('Остатки сотрудников', summary.employee_positive_remaining_total)}
        ${qlFinalPackageMetric('К возмещению', summary.employee_reimbursement_due_total)}
        ${qlFinalPackageMetric('Вернули', summary.returned_cash)}
        ${qlFinalPackageMetric('Расхождение', summary.discrepancy)}
        ${qlFinalPackageMetric('Баланс', summary.balance)}
      </div>
      <p class="final-package-note">Перенос дальше: ${escapeHtml(carryoverNote)}</p>
    </section>
  `;
}

function qlFinalPackageParticipantsSection(participants, proofMap) {
  if (!participants.length) {
    return `
      <section class="final-package-section">
        <div class="final-package-section-head">
          <h4>Отчеты участников</h4>
          <span>Нет строк</span>
        </div>
        <p class="soft-note tight-note">В пакете нет отдельных отчетов участников.</p>
      </section>
    `;
  }

  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Отчеты участников</h4>
        <span>${escapeHtml(participants.length)} шт.</span>
      </div>
      <div class="final-package-card-grid">
        ${participants.map(function(row) {
          const person = row.participant || {};
          const summary = row.summary || {};
          const proofStatus = row.proof_status || {};
          const proofCount = Number(summary.proof_count || proofStatus.proof_count || qlFinalPackageArray(row.proof_ids).length || 0);
          const missingCount = Number(summary.missing_proof_count || proofStatus.missing_count || 0);
          return `
            <article class="final-package-card is-participant">
              <div class="final-package-card-head">
                <div>
                  <b>Отчет участника</b>
                  <small>${escapeHtml(qlFinalPackagePerson(person, 'Участник'))}${person.email ? ' · ' + escapeHtml(person.email) : ''}</small>
                </div>
                <span>${escapeHtml(qlFinalPackageStatusLabel(row.status))}</span>
              </div>
              <div class="final-package-badges">
                <span>${escapeHtml(qlFinalPackageStreamLabel(row.stream_type || row.source_type))}</span>
                <span>${escapeHtml(summary.records_count || 0)} строк</span>
                <span>${escapeHtml(proofCount)} доказ.</span>
                ${missingCount ? '<span class="is-warning">нужно ' + escapeHtml(missingCount) + '</span>' : ''}
              </div>
              <div class="final-package-mini-metrics">
                ${qlFinalPackageMetric('Получил наличными', summary.cash_received || summary.cash_in)}
                ${qlFinalPackageMetric('Расход наличными', summary.cash_out)}
                ${qlFinalPackageMetric('Расход картой', summary.card_out)}
                ${qlFinalPackageMetric('Осталось', summary.remaining_accountable_cash || summary.cash_left)}
              </div>
              <small class="final-package-card-foot">Принят ${escapeHtml(qlFinalReportDate(row.accepted_at || row.finalized_at || row.archived_at))}</small>
              ${qlFinalPackageProofLinks(row.proof_ids, proofMap)}
            </article>
          `;
        }).join('')}
      </div>
    </section>
  `;
}

function qlFinalPackageCapturesSection(captures, proofMap) {
  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Денежные факты и доказательства</h4>
        <span>${escapeHtml(captures.length)} шт.</span>
      </div>
      ${captures.length ? `
        <div class="final-package-row-list">
          ${captures.map(function(row) {
            const title = qlFinalPackageMoneyTypeLabel(row.money_type || row.capture_type) + ': ' + qlFinalPackageEntryTypeLabel(row.entry_type);
            return `
              <article class="final-package-row">
                <div class="final-package-row-main">
                  <b>${escapeHtml(title)}</b>
                  <small>${escapeHtml(row.description || 'Без описания')} · ${escapeHtml(qlFinalReportDate(row.created_at))}</small>
                </div>
                <div class="final-package-row-money">
                  <strong>${qlFinalPackageMoney(row.amount)}</strong>
                  <small>касса ${escapeHtml(qlFinalPackageEffect(row.cash_effect))} · карта ${escapeHtml(qlFinalPackageEffect(row.card_effect))} · подотчет ${escapeHtml(qlFinalPackageEffect(row.accountable_effect))}</small>
                </div>
                ${qlFinalPackageProofLinks(row.proof_ids, proofMap)}
              </article>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">В пакете нет денежных фактов.</p>'}
    </section>
  `;
}

function qlFinalPackageMoneyRowsSection(rows, proofMap) {
  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Строки группового отчета</h4>
        <span>${escapeHtml(rows.length)} шт.</span>
      </div>
      ${rows.length ? `
        <div class="final-package-row-list">
          ${rows.map(function(row) {
            const person = row.participant || {};
            const title = qlFinalPackageMoneyTypeLabel(row.money_type) + ': ' + qlFinalPackageEntryTypeLabel(row.entry_type);
            return `
              <article class="final-package-row">
                <div class="final-package-row-main">
                  <b>${escapeHtml(title)} · ${qlFinalPackageMoney(row.amount)}</b>
                  <small>${escapeHtml(qlFinalReportDate(row.date || row.created_at))} · ${escapeHtml(qlFinalPackagePerson(person, 'Участник'))} · ${escapeHtml(row.section || row.purpose || 'Без статьи')}</small>
                  ${row.note ? '<p>' + escapeHtml(row.note) + '</p>' : ''}
                </div>
                <div class="final-package-row-money">
                  <strong>${qlFinalPackageMoney(row.balance_after)}</strong>
                  <small>касса ${escapeHtml(qlFinalPackageEffect(row.cash_effect))} · карта ${escapeHtml(qlFinalPackageEffect(row.card_effect))} · подотчет ${escapeHtml(qlFinalPackageEffect(row.accountable_effect))}</small>
                </div>
                ${qlFinalPackageProofLinks(row.proof_ids, proofMap)}
              </article>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">В пакете нет строк группового отчета.</p>'}
    </section>
  `;
}

function qlFinalPackageAccountableSection(accountable) {
  const totals = accountable.totals || {};
  const items = qlFinalPackageArray(accountable.items);
  const byParticipant = qlFinalPackageArray(accountable.by_participant);

  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Подотчет и ответственность</h4>
        <span>${escapeHtml(items.length)} выдач</span>
      </div>
      <div class="final-package-metrics">
        ${qlFinalPackageMetric('Выдано', totals.issued)}
        ${qlFinalPackageMetric('Принято расходом', totals.accepted_spent)}
        ${qlFinalPackageMetric('Наличные расходы', totals.accepted_cash_spent)}
        ${qlFinalPackageMetric('Карта', totals.accepted_card_spent)}
        ${qlFinalPackageMetric('Вернули', totals.returned_cash)}
        ${qlFinalPackageMetric('Осталось у сотрудника', totals.open_remaining_cash)}
        ${qlFinalPackageMetric('К возмещению', totals.reimbursement_due)}
        ${qlFinalPackageMetric('Net сотрудников', totals.net_remaining_cash)}
        ${qlFinalPackageMetric('Расхождение', totals.discrepancy)}
      </div>
      ${items.length ? `
        <div class="final-package-row-list">
          ${items.map(function(item) {
            const summary = item.summary || {};
            return `
              <article class="final-package-row">
                <div class="final-package-row-main">
                  <b>${escapeHtml(item.title || ('Подотчет #' + (item.advance_id || '')))}</b>
                  <small>${escapeHtml(qlFinalPackagePerson(item.participant, 'Участник'))} · ${escapeHtml(qlFinalPackageStatusLabel(item.status))} · выдан ${escapeHtml(qlFinalReportDate(item.issued_at))}</small>
                </div>
                <div class="final-package-row-money">
                  <strong>${qlFinalPackageMoney(item.issued_amount)}</strong>
                  <small>расход ${qlFinalPackageMoney(summary.accepted_spent)} · вернул ${qlFinalPackageMoney(summary.returned_cash)} · осталось ${qlFinalPackageMoney(summary.participant_control_balance ?? summary.open_remaining_cash)} · к возмещению ${qlFinalPackageMoney(summary.reimbursement_due)}</small>
                </div>
              </article>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">В пакете нет подотчетных выдач.</p>'}
      ${byParticipant.length ? `
        <div class="final-package-message-list">
          ${byParticipant.map(function(row) {
            return `
              <div class="final-package-message">
                <b>${escapeHtml(qlFinalPackagePerson(row.participant, row.name || row.email || 'Участник'))}</b>
                <small>выдано ${qlFinalPackageMoney(row.issued)} · принято ${qlFinalPackageMoney(row.accepted_spent)} · осталось ${qlFinalPackageMoney(row.participant_control_balance ?? row.open_remaining_cash)} · к возмещению ${qlFinalPackageMoney(row.reimbursement_due)}</small>
              </div>
            `;
          }).join('')}
        </div>
      ` : ''}
    </section>
  `;
}

function qlFinalPackageMessagesSection(messages) {
  const reportContext = qlFinalPackageArray(messages.report_context);
  const generalRefs = qlFinalPackageArray(messages.general_group_refs);
  const schemaNote = messages.schema_note || 'Прямых ссылок сообщений на report_id/tape_id/capture_id/advance_id в этом пакете нет; общий чат группы ниже не считается доказательством отчета.';
  const contextLabel = function(item) {
    const links = [];
    if (Number(item.report_id || 0)) links.push('report_id=' + Number(item.report_id));
    if (Number(item.tape_id || 0)) links.push('tape_id=' + Number(item.tape_id));
    if (Number(item.capture_id || 0)) links.push('capture_id=' + Number(item.capture_id));
    if (Number(item.advance_id || 0)) links.push('advance_id=' + Number(item.advance_id));
    if (Number(item.audit_id || 0)) links.push('audit_id=' + Number(item.audit_id));
    return links.join(' · ');
  };

  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Сообщения по отчету</h4>
        <span>Отдельно от общего чата</span>
      </div>
      <p class="final-package-note">${escapeHtml(schemaNote)}</p>
      <div class="final-package-subhead">Прямые сообщения и события проверки</div>
      ${reportContext.length ? `
        <div class="final-package-message-list">
          ${reportContext.map(function(item) {
            const context = contextLabel(item);
            return `
              <div class="final-package-message">
                <b>${escapeHtml(qlFinalPackageLabel(item.event || item.message_type || item.action || 'Событие отчета', 'Событие отчета'))}</b>
                <small>${escapeHtml(qlFinalReportDate(item.created_at))} · ${escapeHtml(item.sender_name || item.sender_email || item.actor_name || item.user_name || item.user_email || item.user_id || 'Система')}${context ? ' · ' + escapeHtml(context) : ''}</small>
                ${item.text ? '<p>' + escapeHtml(item.text) + '</p>' : ''}
                ${item.details ? '<p>' + escapeHtml(typeof item.details === 'string' ? item.details : JSON.stringify(item.details)) + '</p>' : ''}
              </div>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">Привязанных к отчету сообщений нет.</p>'}
      <div class="final-package-subhead">Общий чат группы без прямой связи с отчетом</div>
      ${generalRefs.length ? `
        <div class="final-package-message-list">
          ${generalRefs.map(function(item) {
            return `
              <div class="final-package-message is-muted">
                <b>${escapeHtml(item.text || item.title || item.message || 'Сообщение группы')}</b>
                <small>${escapeHtml(qlFinalReportDate(item.created_at))} · ${escapeHtml(item.sender_name || item.sender_email || item.author_name || item.user_name || item.user_email || 'Участник')} · не привязано к report_id</small>
              </div>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">Непривязанных ссылок на общий чат нет.</p>'}
    </section>
  `;
}

function qlFinalPackageAuditSection(auditRefs) {
  return `
    <section class="final-package-section">
      <div class="final-package-section-head">
        <h4>Аудит закрытия</h4>
        <span>${escapeHtml(auditRefs.length)} событий</span>
      </div>
      ${auditRefs.length ? `
        <div class="final-package-message-list">
          ${auditRefs.map(function(item) {
            return `
              <div class="final-package-audit">
                <b>${escapeHtml(qlFinalPackageLabel(item.action || 'audit', 'audit'))}</b>
                <small>${escapeHtml(qlFinalReportDate(item.created_at))} · ${escapeHtml(item.entity_type || 'entity')} #${escapeHtml(item.entity_id || '')} · audit #${escapeHtml(item.audit_id || item.id || '')}</small>
              </div>
            `;
          }).join('')}
        </div>
      ` : '<p class="soft-note tight-note">Аудит-ссылки для закрытия не переданы.</p>'}
    </section>
  `;
}

function qlRenderFinalReportPackage(report, packageData) {
  const detail = document.getElementById('finalReportDetail');
  if (!detail) return;

  const reportId = Number(packageData.report_id || report.report_id || report.id || qlSelectedFinalReportId || 0);
  const summary = packageData.summary || {};
  const group = packageData.group || {};
  const finalization = packageData.finalization || {};
  const proofMap = qlFinalPackageProofMap(packageData);
  const participants = qlFinalPackageArray(packageData.participants);
  const captures = qlFinalPackageArray(packageData.captures);
  const moneyRows = qlFinalPackageArray(packageData.money_rows);
  const accountable = packageData.accountable || {};
  const messages = packageData.messages || {};
  const auditRefs = qlFinalPackageArray(packageData.audit_refs);

  detail.innerHTML = `
    <article class="final-report-package">
      <div class="final-report-detail-head final-package-head">
        <div>
          <b>Закрытый групповой отчет #${escapeHtml(reportId)}</b>
          <small>${escapeHtml(group.name || 'Группа')} · закрыт ${escapeHtml(qlFinalReportDate(finalization.finalized_at || report.finalized_at))} · финализировал ${escapeHtml(finalization.finalized_by_display_name || finalization.finalized_by_email || report.finalized_by_display_name || report.finalized_by_email || 'Система')}</small>
        </div>
        <span>Один архивный объект</span>
      </div>
      <div class="report-actions final-package-actions">
        <button class="primary-btn wide-btn" type="button" data-final-report-print="${escapeHtml(reportId)}">Печать / PDF закрытого отчета</button>
        <button class="ghost-btn wide-btn" type="button" data-final-report-package-export="${escapeHtml(reportId)}">Скачать пакет JSON</button>
        <button class="ghost-btn wide-btn" type="button" data-final-report-excel="${escapeHtml(reportId)}">Краткая таблица: Excel</button>
        <button class="ghost-btn wide-btn" type="button" data-final-report-google="${escapeHtml(reportId)}">Краткая таблица: Google</button>
      </div>
      <p class="final-package-note">JSON скачивает весь сохраненный пакет отчета с индексом proof links. Excel/Google остаются краткими таблицами финального отчета.</p>
      ${qlFinalPackageSummarySection(summary)}
      ${qlFinalPackageParticipantsSection(participants, proofMap)}
      ${qlFinalPackageCapturesSection(captures, proofMap)}
      ${qlFinalPackageMoneyRowsSection(moneyRows, proofMap)}
      ${qlFinalPackageAccountableSection(accountable)}
      ${qlFinalPackageMessagesSection(messages)}
      ${qlFinalPackageAuditSection(auditRefs)}
    </article>
  `;
}

function qlRenderFinalReportDetail(report, snapshot, options) {
  const detail = document.getElementById('finalReportDetail');
  if (!detail) return;

  const totals = report.totals || snapshot.totals || {};
  const reportId = Number(report.report_id || report.id || qlSelectedFinalReportId || 0);
  const articleRows = qlFinalReportRows(snapshot.article_rows || []);
  const memberRows = qlFinalReportRows(snapshot.member_rows || []);
  const legacyNote = options && options.packageMissing
    ? '<p class="final-package-note is-warning">Для этого закрытого отчета полный пакет не сохранен. Показан только старый исторический снимок; это не новый пакет закрытого группового отчета.</p>'
    : '';

  detail.innerHTML = `
    <div class="final-report-detail-head">
      <div>
        <b>Финальный отчет за закрытый период #${escapeHtml(reportId)}</b>
        <small>report_id=${escapeHtml(reportId)} · закрыт ${escapeHtml(qlFinalReportDate(report.finalized_at))}</small>
      </div>
      <span>Исторический снимок</span>
    </div>
    ${legacyNote}
    <div class="final-report-kpis">
      ${qlFinalReportMetric('Приход', totals.income)}
      ${qlFinalReportMetric('Расход', totals.expense)}
      ${qlFinalReportMetric('У администратора', totals.admin_cash_left)}
      ${qlFinalReportMetric('Сотрудники net', totals.employee_net_remaining_total ?? totals.employee_cash_left)}
      ${qlFinalReportMetric('Остатки сотрудников', totals.employee_positive_remaining_total)}
      ${qlFinalReportMetric('К возмещению', totals.employee_reimbursement_due_total)}
      ${qlFinalReportMetric('Баланс', totals.balance)}
    </div>
    <div class="report-actions final-report-actions">
      <button class="primary-btn wide-btn" type="button" data-final-report-package-export="${escapeHtml(reportId)}">Скачать старый снимок JSON</button>
      <button class="primary-btn wide-btn" type="button" data-final-report-excel="${escapeHtml(reportId)}">Краткая таблица: Excel</button>
      <button class="ghost-btn wide-btn" type="button" data-final-report-google="${escapeHtml(reportId)}">Краткая таблица: Google</button>
    </div>
    ${qlFinalReportMiniRows('Статьи финального отчета', articleRows, qlFinalReportArticleLabel, 'В снимке нет статей.')}
    ${qlFinalReportMiniRows('Участники финального отчета', memberRows, qlFinalReportMemberLabel, 'В снимке нет участников.')}
  `;
}

async function qlLoadFinalReports() {
  const list = document.getElementById('finalReportsList');
  const detail = document.getElementById('finalReportDetail');
  if (!list || !detail) return;

  const groupId = qlFinalReportsActiveGroupId();
  if (!groupId) {
    qlFinalReports = [];
    qlSelectedFinalReportId = null;
    list.innerHTML = '<p class="soft-note tight-note">Выберите группу, чтобы увидеть закрытые финальные отчеты.</p>';
    detail.innerHTML = '<p class="soft-note tight-note">Закрытые финальные отчеты доступны только для группы.</p>';
    qlFinalReportStatus('');
    return;
  }

  qlFinalReportStatus('');
  list.innerHTML = '<p class="soft-note tight-note">Загружаю закрытые финальные отчеты…</p>';
  const data = await qlApi('ledger_group_final_report_list', {group_id: groupId, limit: 25});
  if (!data.ok) {
    list.innerHTML = '<p class="soft-note tight-note">Не удалось загрузить закрытые финальные отчеты: ' + escapeHtml(data.error || 'unknown') + '</p>';
    detail.innerHTML = '<p class="soft-note tight-note">Исторический отчет не выбран.</p>';
    return;
  }

  qlFinalReports = data.reports || [];
  const selectedStillExists = qlFinalReports.some(function(report) {
    return String(report.report_id || report.id || '') === String(qlSelectedFinalReportId || '');
  });
  if (!selectedStillExists) {
    qlSelectedFinalReportId = qlFinalReports.length ? Number(qlFinalReports[0].report_id || qlFinalReports[0].id || 0) : null;
  }

  qlRenderFinalReportsList(qlFinalReports, qlSelectedFinalReportId);
  if (qlSelectedFinalReportId) {
    await qlOpenFinalReport(qlSelectedFinalReportId, {silent: true});
  } else {
    detail.innerHTML = '<p class="soft-note tight-note">Закрытых финальных отчетов пока нет.</p>';
  }
}

async function qlOpenFinalReport(reportId, options) {
  const detail = document.getElementById('finalReportDetail');
  if (!detail || !reportId) return;

  qlSelectedFinalReportId = Number(reportId);
  qlRenderFinalReportsList(qlFinalReports, qlSelectedFinalReportId);
  detail.innerHTML = '<p class="soft-note tight-note">Загружаю закрытый групповой отчет #' + escapeHtml(reportId) + '…</p>';
  if (!options || !options.silent) qlFinalReportStatus('');

  const packageData = await qlApi('ledger_group_final_report_package', {report_id: Number(reportId)});
  if (packageData.ok) {
    qlRenderFinalReportPackage(packageData.report || {}, packageData.package || {});
    return;
  }

  const packageMissing = packageData.error === 'historical_package_missing' || packageData.error === 'report_package_missing';
  if (!packageMissing) {
    detail.innerHTML = '<p class="soft-note tight-note">Не удалось открыть закрытый групповой отчет: ' + escapeHtml(packageData.error || 'unknown') + '</p>';
    return;
  }

  const detailData = await qlApi('ledger_group_final_report_detail', {report_id: Number(reportId)});
  if (!detailData.ok) {
    const missing = detailData.error === 'historical_snapshot_missing';
    detail.innerHTML = '<p class="soft-note tight-note">' + (missing
      ? 'Для этого закрытого отчета нет полного пакета или исторического снимка. Экспорт финального отчета недоступен.'
      : 'Не удалось открыть старый снимок финального отчета: ' + escapeHtml(detailData.error || 'unknown')) + '</p>';
    return;
  }

  qlRenderFinalReportDetail(detailData.report || packageData.report || {}, detailData.snapshot || {}, {packageMissing: true});
}

function qlPrintFinalReportPackage() {
  const detail = document.getElementById('finalReportDetail');
  if (!detail || !detail.textContent.trim()) return;

  qlCloseTransientPanels();
  document.body.classList.add('printing-final-package');
  window.print();
  setTimeout(function() {
    document.body.classList.remove('printing-final-package');
  }, 500);
}

function qlDownloadFinalReportExcel(reportId) {
  if (!reportId) return;
  qlCloseTransientPanels();
  window.location.href = '/api.php?action=ledger_group_final_report_excel&report_id=' + encodeURIComponent(String(reportId));
}

function qlDownloadFinalReportPackageExport(reportId) {
  if (!reportId) return;
  qlCloseTransientPanels();
  window.location.href = '/api.php?action=ledger_group_final_report_package_export&report_id=' + encodeURIComponent(String(reportId));
}

async function qlOpenFinalReportGoogleSheet(reportId) {
  if (!reportId) return;

  qlCloseTransientPanels();
  qlFinalReportStatus('Готовлю краткую таблицу финального отчета…');
  const data = await qlApi('ledger_group_final_report_google_sheet', {report_id: Number(reportId)});
  if (!data.ok || !data.tsv) {
    qlFinalReportStatus('Не удалось подготовить краткую таблицу финального отчета: ' + (data.error || 'unknown'));
    return;
  }

  const copied = await qlCopyTextToClipboard(data.tsv, data.html || '');
  alert(copied
    ? 'Краткая таблица финального отчета скопирована. В открывшейся Google Таблице нажмите Ctrl+V / Cmd+V.'
    : 'Сейчас откроется Google Таблица. Браузер не дал скопировать краткую таблицу автоматически, поэтому при необходимости скачайте Excel.');
  window.open('https://docs.google.com/spreadsheets/u/0/create', '_blank', 'noopener');
  qlFinalReportStatus(copied
    ? 'Краткая таблица финального отчета скопирована для Google Таблиц.'
    : 'Google Таблица открыта. Копирование не разрешено браузером.');
}

async function qlRunReport() {
  const out = document.getElementById('reportOutput');
  if (!out) return;

  const payload = qlCurrentLedgerPayload({
    period: qlReportPeriod,
    remaining: (document.getElementById('remainingAmount')?.value || '').trim()
  });

  if (qlReportPeriod === 'custom') {
    payload.from = (document.getElementById('reportFrom')?.value || '').trim();
    payload.to = (document.getElementById('reportTo')?.value || '').trim();

    if (!payload.from || !payload.to) {
      out.innerHTML = '<p class="soft-note">Выберите даты начала и конца периода.</p>';
      return;
    }
  }

  out.innerHTML = '<p class="soft-note">Считаю сводку…</p>';

  const data = await qlApi('ledger_report', payload);

  if (!data.ok) {
    out.innerHTML = '<p class="soft-note">Ошибка сводки: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  if (payload.group_id && qlReportPeriod !== 'custom') {
    const openData = await qlApi('ledger_group_open_received_funds', {group_id: Number(payload.group_id)});
    qlApplyOpenPeriodReportData(data, openData);
  }

  const s = data.summary || {};
  const p = data.period || {};
  const adjustment = data.adjustment === null || data.adjustment === undefined
    ? ''
    : `<div class="report-line strong"><span>Расхождение с фактическим остатком</span><b>${qlCurrency(data.adjustment)}</b></div>`;

  const scope = data.scope || {};
  const sections = data.sections || [];
  const members = data.members || [];

  const reportTitle = scope.mode === 'group'
    ? (data.open_period ? 'Сводка открытого периода' : (scope.is_admin ? 'Сводка группы · полный доступ' : 'Сводка группы · ваши записи'))
    : 'Личная сводка';
  const sectionTotalLabel = function(section) {
    const income = Number(section.income || 0);
    const expense = Number(section.expense || 0);
    if (income > 0.009 && expense <= 0.009) return 'Приход ' + qlCurrency(income);
    if (expense > 0.009 && income <= 0.009) return 'Расход ' + qlCurrency(expense);
    return 'Итог ' + qlCurrency(section.balance || 0);
  };
  const memberTotalLabel = function(member) {
    const income = Number(member.income || 0);
    const expense = Number(member.expense || 0);
    if (expense > 0.009) return 'Потратил ' + qlCurrency(expense);
    if (income > 0.009) return 'Внес ' + qlCurrency(income);
    return 'Нет движения';
  };

  const sectionsHtml = sections.length
    ? `
      <div class="section-report">
        <h3>По разделам</h3>
        ${sections.map(function(section) {
          return `
            <div class="section-report-row">
              <div>
                <b>${escapeHtml(section.name || 'Без раздела')}</b>
                <small>${section.records || 0} записей</small>
              </div>
              <div>
                <strong>${escapeHtml(sectionTotalLabel(section))}</strong>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `
    : '';

  const membersHtml = members.length
    ? `
      <div class="section-report members-report">
        <h3>По участникам</h3>
        ${members.map(function(member) {
          return `
            <div class="section-report-row">
              <div>
                <b>${escapeHtml(member.name || member.email || 'Участник')}</b>
                <small>${escapeHtml(member.email || '')} · ${member.records || 0} записей</small>
              </div>
              <div>
                <strong>${escapeHtml(memberTotalLabel(member))}</strong>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `
    : '';

  out.innerHTML = `
    <div class="report-title">${escapeHtml(reportTitle)}</div>
    <div class="report-period">${escapeHtml(p.from || '')} → ${escapeHtml(p.to || '')}</div>
    <div class="report-line strong"><span>Баланс</span><b>${qlCurrency(s.balance || 0)}</b></div>
    <div class="report-line"><span>Приход</span><b>${qlCurrency(s.income || 0)}</b></div>
    <div class="report-line"><span>Расход</span><b>${qlCurrency(s.expense || 0)}</b></div>
    <div class="report-split">
      <div><span>Наличные</span><b>${qlCurrency(s.cash_balance || 0)}</b><small>приход ${qlCurrency(s.cash_income || 0)} / расход ${qlCurrency(s.cash_expense || 0)}</small></div>
      <div><span>Безнал</span><b>${qlCurrency(s.noncash_balance || 0)}</b><small>приход ${qlCurrency(s.noncash_income || 0)} / расход ${qlCurrency(s.noncash_expense || 0)}</small></div>
    </div>
    <div class="report-line"><span>Записей</span><b>${s.records || 0}</b></div>
    ${sectionsHtml}
    ${membersHtml}
    ${data.remaining === null || data.remaining === undefined ? '' : `<div class="report-line"><span>Фактический остаток</span><b>${qlCurrency(data.remaining)}</b></div>`}
    ${adjustment}
  `;
}

function qlPrintReport() {
  qlCloseTransientPanels();
  const out = document.getElementById('reportOutput');
  if (!out || !out.textContent.trim()) return;

  document.body.classList.add('printing-report');
  window.print();
  setTimeout(function() {
    document.body.classList.remove('printing-report');
  }, 500);
}

document.addEventListener('click', function(event) {
  const resultBtn = event.target.closest('#ledgerResultBtn');
  const reportTab = event.target.closest('[data-report-period]');
  const runReport = event.target.closest('#runReportBtn');
  const printReport = event.target.closest('#printReportBtn');
  const finalReportOpen = event.target.closest('[data-final-report-open]');
  const finalReportPrint = event.target.closest('[data-final-report-print]');
  const finalReportPackageExport = event.target.closest('[data-final-report-package-export]');
  const finalReportExcel = event.target.closest('[data-final-report-excel]');
  const finalReportGoogle = event.target.closest('[data-final-report-google]');

  if (resultBtn) qlToggleReportPanel();
  if (reportTab) qlSetReportPeriod(reportTab);
  if (runReport) qlRunReport();
  if (printReport) {
    qlCloseTransientPanels();
    qlPrintReport();
  }
  if (finalReportOpen) qlOpenFinalReport(finalReportOpen.getAttribute('data-final-report-open'));
  if (finalReportPrint) {
    qlCloseTransientPanels();
    qlPrintFinalReportPackage();
  }
  if (finalReportPackageExport) {
    qlDownloadFinalReportPackageExport(finalReportPackageExport.getAttribute('data-final-report-package-export'));
  }
  if (finalReportExcel) {
    qlCloseTransientPanels();
    qlDownloadFinalReportExcel(finalReportExcel.getAttribute('data-final-report-excel'));
  }
  if (finalReportGoogle) {
    qlCloseTransientPanels();
    qlOpenFinalReportGoogleSheet(finalReportGoogle.getAttribute('data-final-report-google'));
  }
});


/* === FinDesk Group UI 20260503-07 === */
let qlGroups = [];
let qlActiveGroup = null;
let qlLastInvite = null;

function qlGroupMessage(message) {
  const el = document.getElementById('groupMessage');
  if (el) el.textContent = message || '';
}

function qlGroupDeleteErrorMessage(error) {
  const map = {
    owner_or_admin_required: 'Для удаления нужна роль администратора.',
    admin_required: 'Для удаления нужна роль администратора.',
    group_not_found: 'Группа не найдена или уже удалена.',
    access_denied: 'У вас недостаточно прав для удаления этой группы.',
    invalid_group_id: 'Некорректный id группы.',
    server_error: 'Серверная ошибка при удалении группы.'
  };

  return map[String(error || '')] || ('Ошибка: ' + (error || 'unknown'));
}

function qlGroupAccessLabel(access) {
  const value = String(access || 'base').toLowerCase();
  if (value === 'advanced' || value === 'admin' || value === 'owner') return 'Администратор';
  if (value === 'manager') return 'Проверка отчетов';
  return 'Фиксация';
}

function qlGroupCanManageMembers(group) {
  if (!group) return false;
  const access = String(group.access_level || group.role || '').toLowerCase();
  const role = String(group.role || '').toLowerCase();
  const permissions = group.permissions || {};
  return access === 'advanced'
    || role === 'admin'
    || role === 'owner'
    || !!permissions.can_manage_members;
}

function qlGroupCanUseGroupData(group) {
  if (!group) return false;
  const access = String(group.access_level || group.role || '').toLowerCase();
  const role = String(group.role || '').toLowerCase();
  const permissions = group.permissions || {};
  return access === 'advanced'
    || access === 'manager'
    || role === 'admin'
    || role === 'owner'
    || !!permissions.can_view_group_reports
    || !!permissions.can_moderate
    || !!permissions.can_manage_money
    || !!permissions.can_manage_members;
}

async function qlLoadGroups() {
  const list = document.getElementById('groupList');
  if (!list) return;

  const data = await qlApi('group_list', {});

  if (!data.ok) {
    list.innerHTML = '<p class="soft-note">Ошибка групп: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  qlGroups = data.groups || [];
  qlRenderGroups();
}

function qlRenderGroups() {
  const list = document.getElementById('groupList');
  const count = document.getElementById('groupCount');

  if (count) count.textContent = qlGroups.length + ' групп';
  if (!list) return;

  if (!qlGroups.length) {
    list.innerHTML = '<p class="soft-note">Групп пока нет.</p>';
    return;
  }

  list.innerHTML = qlGroups.map(function(group) {
    const canManage = qlGroupCanManageMembers(group);
    const deleteButton = canManage
      ? '<button class="ghost-btn danger-soft-btn group-delete-btn" type="button" data-delete-group="' + escapeHtml(group.id) + '">Удалить</button>'
      : '';

    return '<div class="group-row-wrap">' +
      '<button class="group-row" type="button" data-open-group="' + escapeHtml(group.id) + '">' +
      '  <span>' +
      '    <b>' + escapeHtml(group.name) + '</b>' +
      '    <small>' + escapeHtml(qlGroupAccessLabel(group.access_level || group.role)) + ' · участников: ' + escapeHtml(group.member_count || 1) + '</small>' +
      '  </span>' +
      '  <span>›</span>' +
      '</button>' +
      deleteButton +
      '</div>';
  }).join('');
}

async function qlCreateGroup() {
  const input = document.getElementById('groupName');
  const name = (input?.value || '').trim();

  if (!name) {
    qlGroupMessage('Введите название группы.');
    return;
  }

  qlGroupMessage('Создаю группу…');

  const data = await qlApi('group_create', {name});

  if (!data.ok) {
    qlGroupMessage('Ошибка: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlGroupMessage('Группа создана.');
  await qlLoadGroups();

  if (data.group) {
    qlOpenGroup(data.group.id);
  }
}

async function qlDeleteGroup(groupId) {
  const id = Number(groupId || 0);
  if (!id) return;

  const ok = confirm('Удалить группу и перевести её в архив? Это действие нельзя отменить.');
  if (!ok) return;

  qlGroupMessage('Удаляю группу…');
  const data = await qlApi('group_delete', {group_id: id});

  if (!data.ok) {
    if (data.error === 'already_deleted') {
      qlGroupMessage('Группа уже была в архиве, обновляю список.');
      await qlLoadGroups();

      if (qlActiveGroup && String(qlActiveGroup.id) === String(id)) {
        qlActiveGroup = null;
        const details = document.getElementById('groupDetails');
        const title = document.getElementById('activeGroupName');

        if (details) details.classList.add('hidden');
        if (title) title.textContent = 'Группа';
      }

      return;
    }

    qlGroupMessage(qlGroupDeleteErrorMessage(data.error));
    return;
  }

  if (qlActiveGroup && String(qlActiveGroup.id) === String(id)) {
    qlActiveGroup = null;
    const details = document.getElementById('groupDetails');
    const title = document.getElementById('activeGroupName');

    if (details) details.classList.add('hidden');
    if (title) title.textContent = 'Группа';
  }

  await qlLoadGroups();
  qlGroupMessage('Группа удалена из рабочего списка и перенесена в архив.');
}

async function qlOpenGroup(groupId) {
  qlActiveGroup = qlGroups.find(function(g) {
    return String(g.id) === String(groupId);
  }) || null;

  if (!qlActiveGroup) {
    await qlLoadGroups();
    qlActiveGroup = qlGroups.find(function(g) {
      return String(g.id) === String(groupId);
    }) || null;
  }

  const details = document.getElementById('groupDetails');
  const title = document.getElementById('activeGroupName');

  if (details) details.classList.remove('hidden');
  if (title && qlActiveGroup) title.textContent = qlActiveGroup.name;

  const canManage = qlGroupCanManageMembers(qlActiveGroup);
  const canUseGroupData = qlGroupCanUseGroupData(qlActiveGroup);
  document.getElementById('renameGroupBtn')?.classList.toggle('hidden', !canManage);
  document.getElementById('deleteActiveGroupBtn')?.classList.toggle('hidden', !canManage);
  document.querySelector('#groupDetails .invite-box')?.classList.toggle('hidden', !canManage);
  document.querySelector('#groupDetails .messages-box')?.classList.toggle('hidden', !canUseGroupData);
  document.querySelector('#groupDetails .members-box')?.classList.toggle('hidden', !canUseGroupData && !canManage);
  if (!canUseGroupData) {
    qlGroupMessage('Доступ сотрудника: фиксация, подотчет и личный самоконтроль без групповых данных.');
  } else {
    qlGroupMessage('');
  }

  const inviteActions = document.getElementById('inviteActions');
  if (inviteActions) inviteActions.classList.add('hidden');

  await qlLoadMembers();
}

async function qlRenameGroup() {
  if (!qlActiveGroup) return;
  if (!qlGroupCanManageMembers(qlActiveGroup)) return;

  const name = prompt('Новое название группы', qlActiveGroup.name);
  if (name === null) return;

  const data = await qlApi('group_rename', {
    group_id: qlActiveGroup.id,
    name
  });

  if (!data.ok) {
    alert('Ошибка: ' + (data.error || 'unknown'));
    return;
  }

  await qlLoadGroups();
  qlOpenGroup(qlActiveGroup.id);
}

async function qlCreateInvite(channel) {
  if (!qlActiveGroup) {
    qlGroupMessage('Сначала выберите группу.');
    return;
  }
  if (!qlGroupCanManageMembers(qlActiveGroup)) {
    qlGroupMessage('Недостаточно прав для приглашений.');
    return;
  }

	  const data = await qlApi('group_invite_create', {
	    group_id: qlActiveGroup.id,
	    channel: channel || 'copy',
	    invited_email: (document.getElementById('inviteEmail')?.value || '').trim(),
	    access_level: document.getElementById('inviteAccessLevel')?.value || 'base'
	  });

  if (!data.ok) {
    qlGroupMessage('Ошибка приглашения: ' + (data.error || 'unknown'));
    return;
  }

  qlLastInvite = data;
  qlRenderInvite(data);
}

function qlRenderInvite(data) {
  const actions = document.getElementById('inviteActions');
  const url = document.getElementById('inviteUrl');

  if (actions) actions.classList.remove('hidden');
  if (url) url.value = data.invite?.url || '';

  const links = data.share_links || {};

  const email = document.getElementById('shareEmail');
  const wa = document.getElementById('shareWhatsapp');
  const vb = document.getElementById('shareViber');
  const tg = document.getElementById('shareTelegram');

  if (email) email.href = links.email || '#';
  if (wa) wa.href = links.whatsapp || '#';
  if (vb) vb.href = links.viber || '#';
  if (tg) tg.href = links.telegram || '#';
}

function qlClearInviteActions() {
  const actions = document.getElementById('inviteActions');
  const url = document.getElementById('inviteUrl');
  if (actions) actions.classList.add('hidden');
  if (url) {
    url.value = '';
    url.blur();
  }
  qlLastInvite = null;
  qlGroupMessage('');
}

async function qlCopyInvite() {
  qlCloseTransientPanels();
  const input = document.getElementById('inviteUrl');
  const value = input?.value || '';

  if (!value) return;

  try {
    await navigator.clipboard.writeText(value);
    qlGroupMessage('Ссылка скопирована.');
  } catch (e) {
    if (input) {
      input.select();
      document.execCommand('copy');
      qlGroupMessage('Ссылка скопирована.');
    }
  }
}

async function qlLoadMembers() {
  const box = document.getElementById('memberList');
  const count = document.getElementById('memberCount');

  if (!box || !qlActiveGroup) return;

  const data = await qlApi('group_members', {
    group_id: qlActiveGroup.id
  });

  if (!data.ok) {
    box.innerHTML = '<p class="soft-note">Ошибка участников: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const members = data.members || [];
  if (count) count.textContent = String(members.length);

	  const activeAccess = qlActiveGroup ? String(qlActiveGroup.access_level || qlActiveGroup.role || '').toLowerCase() : '';
	  const canManage = ['advanced', 'admin', 'owner'].includes(activeAccess);
	  box.innerHTML = members.map(function(member) {
	    const access = member.access_level || member.role || 'base';
	    const control = canManage ? `
	      <select class="ql-input member-access-select" data-member-access="${escapeHtml(member.user_id)}">
	        <option value="base" ${access === 'base' ? 'selected' : ''}>Фиксация и самоконтроль</option>
	        <option value="manager" ${access === 'manager' ? 'selected' : ''}>Проверка отчетов</option>
	        <option value="advanced" ${access === 'advanced' ? 'selected' : ''}>Администратор</option>
	      </select>
	    ` : `<small>${escapeHtml(qlGroupAccessLabel(access))}</small>`;
	    return `
	      <div class="member-row">
	        <span>
	          <b>${escapeHtml(member.display_name || member.email)}</b>
	          <small>${escapeHtml(member.email)} · ${escapeHtml(qlGroupAccessLabel(access))}</small>
	        </span>
	        ${control}
	      </div>
	    `;
	  }).join('');
	}

async function qlUpdateMemberAccess(userId, accessLevel) {
  if (!qlActiveGroup || !userId) return;

  const data = await qlApi('group_member_access_update', {
    group_id: qlActiveGroup.id,
    user_id: Number(userId),
    access_level: accessLevel
  });

  if (!data.ok) {
    qlGroupMessage('Ошибка роли: ' + (data.error || 'unknown'));
    await qlLoadMembers();
    return;
  }

  qlGroupMessage('Роль обновлена.');
  await qlLoadGroups();
  await qlOpenGroup(qlActiveGroup.id);
}

async function qlHandleInviteFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('invite');

  if (!token) return;

  const userData = await qlApi('current_user', {});
  if (!userData.ok || !userData.user) {
    qlShowPanel('login');
    qlShowAuthMessage('Сначала войдите, затем приглашение подключится к аккаунту.');
    return;
  }

  const join = await qlApi('group_join', {token});

  if (join.ok) {
    history.replaceState({}, '', '/app.php');
    await qlLoadGroups();
    if (join.group) qlOpenGroup(join.group.id);
    qlGroupMessage('Вы присоединились к группе.');
  } else {
    qlGroupMessage('Ошибка приглашения: ' + (join.error || 'unknown'));
  }
}

document.addEventListener('click', function(event) {
  const createGroup = event.target.closest('#createGroupBtn');
  const openGroup = event.target.closest('[data-open-group]');
  const deleteGroup = event.target.closest('[data-delete-group]');
  const deleteActiveGroup = event.target.closest('#deleteActiveGroupBtn');
  const renameGroup = event.target.closest('#renameGroupBtn');
  const createInvite = event.target.closest('#createInviteBtn');
  const copyInvite = event.target.closest('#copyInviteBtn');
  const clearInvite = event.target.closest('#clearInviteActionsBtn');

  if (createGroup) qlCreateGroup();
  if (deleteActiveGroup) {
    qlDeleteGroup(qlActiveGroup && qlActiveGroup.id);
    return;
  }
  if (deleteGroup) {
      qlDeleteGroup(deleteGroup.getAttribute('data-delete-group'));
      return;
    }
  if (openGroup) qlOpenGroup(openGroup.getAttribute('data-open-group'));
  if (renameGroup) qlRenameGroup();
  if (createInvite) qlCreateInvite('copy');
  if (copyInvite) qlCopyInvite();
  if (clearInvite) qlClearInviteActions();
		});

document.addEventListener('change', function(event) {
  const memberAccess = event.target.closest('[data-member-access]');
  if (memberAccess) qlUpdateMemberAccess(memberAccess.getAttribute('data-member-access'), memberAccess.value);
});

const qlPreviousRenderUserForGroups = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForGroups(user);
  setTimeout(async function() {
    await qlLoadGroups();
    qlHandleInviteFromUrl();
    if (!qlRestoreModuleState()) {
      const activeModule = document.querySelector('.ql-module[data-module].active');
      const fallbackModule = activeModule ? activeModule.getAttribute('data-module') : 'ontherun';
      qlSetModule(fallbackModule, {history: 'replace'});
    }
    qlReplaceBrowserHistoryFromCurrentState();
  }, 80);
};

/* === FinDesk Group Ledger Scope UI 20260503-08 === */
let qlLedgerScopeMode = 'personal';
let qlLedgerGroupId = null;

function qlScopeMessage(message) {
  const el = document.getElementById('scopeMessage');
  if (el) el.textContent = message || '';
}

function qlCurrentLedgerPayload(extra) {
  const payload = extra || {};
  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId) {
    payload.group_id = qlLedgerGroupId;
  }
  return payload;
}

function qlRefreshGroupSelect() {
  const select = document.getElementById('ledgerGroupSelect');
  if (!select) return;

  const current = String(qlLedgerGroupId || '');

  select.innerHTML = '<option value="">Выберите группу</option>' + qlGroups.map(function(group) {
    return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(group.role) + '</option>';
  }).join('');

  select.value = current;
}

function qlSetScopeMode(mode) {
  qlLedgerScopeMode = mode === 'group' ? 'group' : 'personal';

  document.querySelectorAll('[data-scope-mode]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-scope-mode') === qlLedgerScopeMode);
  });

  const select = document.getElementById('ledgerGroupSelect');
  if (select) {
    select.classList.toggle('hidden', qlLedgerScopeMode !== 'group');
  }

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId && qlGroups.length) {
    qlLedgerGroupId = qlGroups[0].id;
    if (select) select.value = qlLedgerGroupId;
  }

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId) {
    qlScopeMessage('Создайте или выберите группу.');
  } else {
    qlScopeMessage(qlLedgerScopeMode === 'group' ? 'Открытый журнал группы.' : 'Личный журнал.');
  }

  qlLoadLedger();
}

const qlOldLoadLedgerForScope = qlLoadLedger;
qlLoadLedger = async function() {
  const feed = document.getElementById('ledgerFeed');
  if (!feed) return;

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId) {
    feed.innerHTML = '<p class="soft-note">Выберите группу, чтобы увидеть записи группы.</p>';
    qlRenderLedger([], {income:0, expense:0, balance:0});
    return;
  }

  const data = await qlApi('ledger_list', qlCurrentLedgerPayload({limit: 150}));

  if (!data.ok) {
    feed.innerHTML = '<p class="soft-note">Ошибка журнала: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  let entries = data.entries || [];
  let summary = data.summary || {};
  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId) {
    const openData = await qlApi('ledger_group_open_received_funds', {group_id: Number(qlLedgerGroupId)});
    if (openData.ok && openData.finalized_at) {
      const finalizedAt = String(openData.finalized_at);
      entries = entries.filter(function(entry) {
        const createdAt = String(entry.created_at || entry.entry_datetime || '');
        if (createdAt > finalizedAt) return true;
        if (entry.virtual_source && entry.archived_at) return false;
        return false;
      });
      const carryovers = Array.isArray(openData.carryovers) && openData.carryovers.length
        ? openData.carryovers
        : (openData.carryover ? [openData.carryover] : []);
      if (carryovers.length) {
        entries = carryovers.map(function(row, index) {
          return {
            id: row.id || ('carryover-' + index),
            entry_type: 'income',
            money_type: 'cash',
            amount: Number(row.amount || 0),
            purpose: 'Переходящий остаток из финального отчета',
            note: row.note || '',
            entry_datetime: row.entry_datetime || finalizedAt,
            created_at: row.created_at || finalizedAt,
            category_name: 'Без раздела',
            file_count: 0,
            owner_display_name: row.owner_display_name || 'Система',
            virtual_source: 'carryover'
          };
        }).concat(entries);
      }

      summary = entries.reduce(function(acc, entry) {
        const amount = Number(entry.amount || 0);
        if (entry.entry_type === 'income') {
          acc.income += amount;
          if (entry.money_type === 'cash') acc.cash_income += amount;
          else acc.noncash_income += amount;
        } else {
          acc.expense += amount;
          if (entry.money_type === 'cash') acc.cash_expense += amount;
          else acc.noncash_expense += amount;
        }
        acc.records += 1;
        return acc;
      }, {
        income: 0,
        expense: 0,
        balance: 0,
        cash_income: 0,
        cash_expense: 0,
        cash_balance: 0,
        noncash_income: 0,
        noncash_expense: 0,
        noncash_balance: 0,
        records: 0
      });
      summary.balance = summary.income - summary.expense;
      summary.cash_balance = summary.cash_income - summary.cash_expense;
      summary.noncash_balance = summary.noncash_income - summary.noncash_expense;
      summary.open_period = true;
    }
  }

  qlRenderLedger(entries, summary);
};

const qlOldSaveLedgerEntryForScope = qlSaveLedgerEntry;
qlSaveLedgerEntry = async function() {
  const amount = (document.getElementById('ledgerAmount')?.value || '').trim();
  const purpose = (document.getElementById('ledgerPurpose')?.value || '').trim();
  const fileInput = document.getElementById('ledgerFile');
  const selectedFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

  if (!amount || !purpose) {
    qlLedgerMessage('Введите сумму и назначение.');
    return;
  }

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId) {
    qlLedgerMessage('Сначала выберите группу.');
    return;
  }

  qlLedgerMessage(selectedFile ? 'Сохраняю запись и файл…' : 'Сохраняю…');

  const data = await qlApi('ledger_create', qlCurrentLedgerPayload({
    entry_type: qlLedgerType,
    money_type: qlMoneyType,
    amount,
    purpose,
    category_id: qlSelectedSectionId()
  }));

  if (!data.ok) {
    qlLedgerMessage('Ошибка: ' + (data.error || 'unknown'));
    return;
  }

  if (selectedFile && data.entry && data.entry.id) {
    const upload = await qlUploadEntryFile(data.entry.id, selectedFile);
    if (!upload.ok) {
      qlLedgerMessage('Запись сохранена, но файл не загрузился: ' + (upload.error || 'unknown'));
      await qlLoadLedger();
      return;
    }
  }

  const amountEl = document.getElementById('ledgerAmount');
  const purposeEl = document.getElementById('ledgerPurpose');
  const fileNameEl = document.getElementById('ledgerFileName');

  if (amountEl) amountEl.value = '';
  if (purposeEl) purposeEl.value = '';
  if (fileInput) fileInput.value = '';
  if (fileNameEl) fileNameEl.textContent = 'Файл не выбран';

  qlLedgerMessage(qlLedgerScopeMode === 'group' ? 'Сохранено в группу.' : 'Сохранено.');
  await qlLoadLedger();
};

const qlOldRenderLedgerForOwner = qlRenderLedger;
qlRenderLedger = function(entries, summary) {
  qlOldRenderLedgerForOwner(entries, summary);

  if (qlLedgerScopeMode !== 'group') return;

  document.querySelectorAll('.entry-row').forEach(function(row) {
    const id = row.getAttribute('data-entry-id');
    const entry = (entries || []).find(function(e) { return String(e.id) === String(id); });
    if (!entry || !entry.owner_display_name) return;

    const meta = row.querySelector('.entry-meta');
    if (meta && !meta.textContent.includes('by ')) {
      meta.textContent = meta.textContent + ' · by ' + entry.owner_display_name;
    }
  });
};

document.addEventListener('click', function(event) {
  const scopeBtn = event.target.closest('[data-scope-mode]');
  if (scopeBtn) qlSetScopeMode(scopeBtn.getAttribute('data-scope-mode'));
});

document.addEventListener('change', function(event) {
  const select = event.target.closest('#ledgerGroupSelect');
  if (!select) return;

  qlLedgerGroupId = select.value ? Number(select.value) : null;
  qlScopeMessage(qlLedgerGroupId ? 'Группа выбрана.' : 'Выберите группу.');
  qlLoadLedger();

  if (qlLedgerGroupId) {
    qlOpenGroup(qlLedgerGroupId);
  }

  qlRunReport(); // refresh report after group selector change
  qlLoadFinalReports();
});

const qlOldLoadGroupsForScope = qlLoadGroups;
qlLoadGroups = async function() {
  await qlOldLoadGroupsForScope();
  qlRefreshGroupSelect();
};



/* === FinDesk Section UI 20260503-09 === */
let qlCategories = [];

function qlSelectedSectionId() {
  const select = document.getElementById('ledgerSection');
  return select && select.value ? Number(select.value) : null;
}

function qlLedgerPublicCategoryName(name) {
  const raw = String(name || '').trim();
  if (!raw || raw === 'No section') return 'Без раздела';
  if (raw === 'On the Go') return 'Живой отчет';
  return raw;
}

async function qlLoadCategories() {
  const payload = {};
  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId) {
    payload.group_id = qlLedgerGroupId;
  }

  const data = await qlApi('category_list', payload);

  if (!data.ok) {
    qlCategories = [];
    qlRenderSectionSelect();
    return;
  }

  qlCategories = data.categories || [];
  qlRenderSectionSelect();
}

function qlRenderSectionSelect() {
  const select = document.getElementById('ledgerSection');
  if (!select) return;

  const current = select.value;
  const label = document.getElementById('ledgerSectionLabel');
  const help = document.getElementById('ledgerSectionHelp');
  const createInput = document.getElementById('newSectionName');
  const filtered = qlCategories.filter(function(cat) {
    const name = String(cat.name || '').trim();
    if (!name || name === 'On the Go' || name === 'No section') return false;
    return !cat.category_type || cat.category_type === qlLedgerType;
  });
  const isIncome = qlLedgerType === 'income';

  if (label) label.textContent = isIncome ? 'Источник поступления' : 'Статья расхода';
  if (help) {
    help.textContent = isIncome
      ? 'Для прихода статья обычно не нужна: кто дал деньги или основание пишется в назначении.'
      : 'Статья нужна для расходов: продукты, топливо, ремонт, поездка. Детали пишутся в назначении.';
  }
  if (createInput) {
    createInput.placeholder = isIncome ? 'Например: владелец, касса, возврат' : 'Например: продукты, топливо, ремонт';
  }

  select.innerHTML = '<option value="">' + (isIncome ? 'Без статьи' : 'Без статьи расхода') + '</option>' + filtered.map(function(cat) {
    return '<option value="' + escapeHtml(cat.id) + '">' + escapeHtml(cat.name) + '</option>';
  }).join('');

  if (current && filtered.some(function(cat) { return String(cat.id) === String(current); })) {
    select.value = current;
  } else {
    select.value = '';
  }
}

const qlOldSetSegmentForCategories = qlSetSegment;
qlSetSegment = function(button, attr, valueSetter) {
  qlOldSetSegmentForCategories(button, attr, valueSetter);

  if (attr === 'data-ledger-type') {
    setTimeout(qlRenderSectionSelect, 20);
  }
};

const qlOldSetScopeModeForCategories = qlSetScopeMode;
qlSetScopeMode = function(mode) {
  qlOldSetScopeModeForCategories(mode);
  setTimeout(qlLoadCategories, 60);
};

const qlOldLoadGroupsForCategories = qlLoadGroups;
qlLoadGroups = async function() {
  await qlOldLoadGroupsForCategories();
  qlRefreshGroupSelect();
  qlLoadCategories();
};

document.addEventListener('change', function(event) {
  const select = event.target.closest('#ledgerGroupSelect');
  if (select) {
    setTimeout(qlLoadCategories, 60);
  }
});




async function qlCreateSection() {
  const input = document.getElementById('newSectionName');
  const name = (input?.value || '').trim();

  if (!name) {
    qlLedgerMessage('Введите название раздела.');
    return;
  }

  const payload = {
    name: name,
    category_type: qlLedgerType
  };

  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId) {
    payload.group_id = qlLedgerGroupId;
  }

  qlLedgerMessage('Создаю раздел…');

  const data = await qlApi('category_create', payload);

  if (!data.ok) {
    qlLedgerMessage('Ошибка раздела: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlLedgerMessage('Раздел создан.');
  await qlLoadCategories();

  const select = document.getElementById('ledgerSection');
  const created = (data.categories || []).find(function(cat) {
    return cat.name === name;
  });

  if (select && created) {
    select.value = created.id;
  }
}


/* === FinDesk Group Messages UI 20260503-14 === */
function qlMessageStatus(message) {
  const el = document.getElementById('messageStatus');
  if (el) el.textContent = message || '';
}

function qlFormatMessageTime(dateText) {
  const d = new Date(String(dateText).replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

async function qlLoadMessages() {
  const list = document.getElementById('messageList');
  const count = document.getElementById('messageCount');

  if (!list || !qlActiveGroup) return;

  const data = await qlApi('message_list', {
    group_id: qlActiveGroup.id,
    limit: 50
  });

  if (!data.ok) {
    list.innerHTML = '<p class="soft-note">Ошибка сообщений: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const messages = data.messages || [];

  if (count) count.textContent = String(messages.length);

  if (!messages.length) {
    list.innerHTML = '<p class="soft-note">Сообщений пока нет.</p>';
    return;
  }

  list.innerHTML = messages.map(function(msg) {
    return `
      <article class="message-row ${Number(msg.is_read || 0) ? '' : 'unread'}">
        <div class="message-head">
          <b>${escapeHtml(msg.sender_name || msg.sender_email || 'Участник')}</b>
          <span>${qlFormatMessageTime(msg.created_at)}</span>
        </div>
        <div class="message-text">${escapeHtml(msg.message_text)}</div>
      </article>
    `;
  }).join('');

  await qlApi('message_mark_read', {group_id: qlActiveGroup.id});
}

async function qlSendMessage() {
  if (!qlActiveGroup) {
    qlMessageStatus('Сначала выберите группу.');
    return;
  }

  const input = document.getElementById('messageText');
  const text = (input?.value || '').trim();

  if (!text) {
    qlMessageStatus('Напишите сообщение.');
    return;
  }

  qlMessageStatus('Отправляю…');

  const data = await qlApi('message_send', {
    group_id: qlActiveGroup.id,
    message_text: text
  });

  if (!data.ok) {
    qlMessageStatus('Ошибка сообщения: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlMessageStatus('Отправлено.');

  await qlLoadMessages();
}

const qlOldOpenGroupForMessages = qlOpenGroup;
qlOpenGroup = async function(groupId) {
  await qlOldOpenGroupForMessages(groupId);
  setTimeout(qlLoadMessages, 80);
};

document.addEventListener('click', function(event) {
  const sendMessage = event.target.closest('#sendMessageBtn');
  if (sendMessage) qlSendMessage();
});

document.addEventListener('keydown', function(event) {
  if (event.key !== 'Enter') return;
  const input = event.target.closest('#messageText');
  if (!input) return;
  event.preventDefault();
  qlSendMessage();
});

/* === FinDesk Unread Message Modal 20260503-17 === */
let qlPendingUnreadMessage = null;
let qlUnreadCheckDone = false;

function qlSoftBeep() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;

    const ctx = new AudioContext();
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = 880;
    gain.gain.value = 0.035;

    oscillator.connect(gain);
    gain.connect(ctx.destination);

    oscillator.start();

    setTimeout(function() {
      oscillator.stop();
      ctx.close();
    }, 130);
  } catch (e) {
    // Browser may block sound before user interaction. Visual modal still works.
  }
}

function qlShowMessageModal(message) {
  qlPendingUnreadMessage = message;

  const modal = document.getElementById('messageModal');
  const title = document.getElementById('messageModalTitle');
  const text = document.getElementById('messageModalText');

  if (!modal) return;

  const sender = message.sender_name || message.sender_email || 'someone';
  const group = message.group_name || 'group';
  const preview = message.message_text || '';

  if (title) title.textContent = 'You have a message from ' + sender;
  if (text) text.textContent = group + ': ' + preview;

  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');

  qlSoftBeep();
}

function qlHideMessageModal() {
  const modal = document.getElementById('messageModal');
  if (!modal) return;

  modal.classList.add('hidden');
  modal.setAttribute('aria-hidden', 'true');
}

async function qlCheckUnreadMessages() {
  if (qlUnreadCheckDone) return;
  qlUnreadCheckDone = true;

  const data = await qlApi('message_unread', {});

  if (!data.ok) return;

  const messages = data.messages || [];
  if (!messages.length) return;

  qlShowMessageModal(messages[0]);
}

async function qlOpenUnreadGroup() {
  if (!qlPendingUnreadMessage) {
    qlHideMessageModal();
    return;
  }

  const groupId = qlPendingUnreadMessage.group_id;

  qlHideMessageModal();

  await qlLoadGroups();

  if (groupId) {
    qlSetScopeMode('group');
    qlLedgerGroupId = Number(groupId);
    qlRefreshGroupSelect();

    const select = document.getElementById('ledgerGroupSelect');
    if (select) select.value = String(groupId);

    await qlOpenGroup(groupId);
    await qlLoadLedger();
    await qlRunReport();
    await qlApi('message_mark_read', {group_id: groupId});
  }
}

document.addEventListener('click', function(event) {
  const close = event.target.closest('[data-close-message-modal]');
  const later = event.target.closest('#laterMessageBtn');
  const open = event.target.closest('#openMessageGroupBtn');

  if (close || later) qlHideMessageModal();
  if (open) qlOpenUnreadGroup();
});

const qlPreviousRenderUserForUnread = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForUnread(user);
  setTimeout(qlCheckUnreadMessages, 500);
};

/* === FinDesk Business Desk UI 20260503-18 === */
let qlBdCompanyProfile = null;
let qlBdClients = [];
let qlBdProformas = [];

function qlBdStatus(id, message) {
  const el = document.getElementById(id);
  if (el) el.textContent = message || '';
}

function qlBdVal(id) {
  return (document.getElementById(id)?.value || '').trim();
}

function qlBdSetTab(tab) {
  document.querySelectorAll('[data-business-tab]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-business-tab') === tab);
  });

  const panels = {
    company: document.getElementById('businessCompanyPanel'),
    clients: document.getElementById('businessClientsPanel'),
    proformas: document.getElementById('businessProformasPanel')
  };

  Object.keys(panels).forEach(function(key) {
    if (panels[key]) panels[key].classList.toggle('hidden', key !== tab);
  });

  if (tab === 'company') qlBdLoadCompany();
  if (tab === 'clients') qlBdLoadClients();
  if (tab === 'proformas') {
    qlBdLoadClients();
    qlBdLoadProformas();
  }
}

async function qlBdLoadCompany() {
  const data = await qlApi('company_profile_get', {});
  if (!data.ok) {
    qlBdStatus('companyStatus', 'Company error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdCompanyProfile = data.profile || null;

  if (!qlBdCompanyProfile) {
    qlBdStatus('companyStatus', 'No company profile yet.');
    return;
  }

  const p = qlBdCompanyProfile;

  if (document.getElementById('bdCompanyName')) document.getElementById('bdCompanyName').value = p.company_name || '';
  if (document.getElementById('bdCompanyEmail')) document.getElementById('bdCompanyEmail').value = p.email || '';
  if (document.getElementById('bdCompanyPhone')) document.getElementById('bdCompanyPhone').value = p.phone || '';
  if (document.getElementById('bdCompanyAddress')) document.getElementById('bdCompanyAddress').value = p.address || '';
  if (document.getElementById('bdCompanyReg')) document.getElementById('bdCompanyReg').value = p.registration_number || '';
  if (document.getElementById('bdCompanyVat')) document.getElementById('bdCompanyVat').value = p.vat_number || '';
  if (document.getElementById('bdVatRate')) document.getElementById('bdVatRate').value = p.default_vat_rate || '0.00';

  qlBdStatus('companyStatus', 'Company profile loaded.');
}

async function qlBdSaveCompany() {
  qlBdStatus('companyStatus', 'Saving company profile…');

  const data = await qlApi('company_profile_save', {
    company_name: qlBdVal('bdCompanyName'),
    email: qlBdVal('bdCompanyEmail'),
    phone: qlBdVal('bdCompanyPhone'),
    address: qlBdVal('bdCompanyAddress'),
    registration_number: qlBdVal('bdCompanyReg'),
    vat_number: qlBdVal('bdCompanyVat'),
    default_vat_rate: qlBdVal('bdVatRate'),
    currency: 'EUR'
  });

  if (!data.ok) {
    qlBdStatus('companyStatus', 'Company error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdCompanyProfile = data.profile || null;
  qlBdStatus('companyStatus', 'Company profile saved.');
}

async function qlBdCreateClient() {
  const name = qlBdVal('bdClientName');
  if (!name) {
    qlBdStatus('clientStatus', 'Enter client name.');
    return;
  }

  qlBdStatus('clientStatus', 'Creating client…');

  const data = await qlApi('client_create', {
    client_name: name,
    email: qlBdVal('bdClientEmail'),
    phone: qlBdVal('bdClientPhone'),
    address: qlBdVal('bdClientAddress')
  });

  if (!data.ok) {
    qlBdStatus('clientStatus', 'Client error: ' + (data.error || 'unknown'));
    return;
  }

  ['bdClientName', 'bdClientEmail', 'bdClientPhone', 'bdClientAddress'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });

  qlBdStatus('clientStatus', 'Client created.');
  await qlBdLoadClients();
}

async function qlBdLoadClients() {
  const data = await qlApi('client_list', {});
  if (!data.ok) {
    qlBdStatus('clientStatus', 'Client list error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdClients = data.clients || [];
  qlBdRenderClients();
  qlBdRenderClientSelect();
}

function qlBdRenderClients() {
  const list = document.getElementById('clientList');
  if (!list) return;

  if (!qlBdClients.length) {
    list.innerHTML = '<p class="soft-note">No clients yet.</p>';
    return;
  }

  list.innerHTML = qlBdClients.map(function(client) {
    return `
      <div class="business-row">
        <div>
          <b>${escapeHtml(client.client_name)}</b>
          <small>${escapeHtml(client.email || '')}</small>
        </div>
      </div>
    `;
  }).join('');
}

function qlBdRenderClientSelect() {
  const select = document.getElementById('bdProformaClient');
  if (!select) return;

  const current = select.value;

  select.innerHTML = '<option value="">No client selected</option>' + qlBdClients.map(function(client) {
    return '<option value="' + escapeHtml(client.id) + '">' + escapeHtml(client.client_name) + '</option>';
  }).join('');

  if (current) select.value = current;
}

async function qlBdCreateProforma() {
  const itemName = qlBdVal('bdItemName');
  const unitPrice = qlBdVal('bdItemPrice');

  if (!itemName || !unitPrice) {
    qlBdStatus('proformaStatus', 'Enter service/item and price.');
    return;
  }

  qlBdStatus('proformaStatus', 'Creating proforma…');

  const clientId = qlBdVal('bdProformaClient');
  const vatRate = qlBdVal('bdProformaVatRate') || qlBdVal('bdVatRate') || '0';
  const discountRate = qlBdVal('bdProformaDiscountRate') || '0';

  const data = await qlApi('proforma_create', {
    client_id: clientId || null,
    title: qlBdVal('bdProformaTitle') || 'Proforma',
    currency: 'EUR',
    vat_rate: vatRate,
    discount_rate: discountRate,
    public_note: qlBdVal('bdPublicNote'),
    items: [
      {
        item_name: itemName,
        quantity: qlBdVal('bdItemQty') || '1',
        unit_name: qlBdVal('bdItemUnit') || 'pcs',
        unit_price: unitPrice
      }
    ]
  });

  if (!data.ok) {
    qlBdStatus('proformaStatus', 'Proforma error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdStatus('proformaStatus', 'Proforma created: ' + (data.proforma?.proforma_number || ''));

  ['bdItemName', 'bdItemPrice', 'bdPublicNote', 'bdProformaDiscountRate'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });

  await qlBdLoadProformas();
}

async function qlBdLoadProformas() {
  const data = await qlApi('proforma_list', {});

  if (!data.ok) {
    qlBdStatus('proformaStatus', 'Proforma list error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdProformas = data.proformas || [];
  qlBdRenderProformas();
}

function qlBdRenderProformas() {
  const list = document.getElementById('proformaList');
  if (!list) return;

  if (!qlBdProformas.length) {
    list.innerHTML = '<p class="soft-note">No proformas yet.</p>';
    return;
  }

  list.innerHTML = qlBdProformas.map(function(p) {
    return `
      <div class="business-row">
        <div>
          <b>${escapeHtml(p.proforma_number)} · ${escapeHtml(p.title || 'Proforma')}</b>
          <small>${escapeHtml(p.client_name || 'No client')} · ${escapeHtml(p.issue_date || '')}</small>
        </div>
        <strong>${escapeHtml(p.currency || 'EUR')} ${Number(p.total_amount || 0).toFixed(2)}</strong>
      </div>
    `;
  }).join('');
}

document.addEventListener('click', function(event) {
  const tab = event.target.closest('[data-business-tab]');
  const saveCompany = event.target.closest('#saveCompanyBtn');
  const createClient = event.target.closest('#createClientBtn');
  const createProforma = event.target.closest('#createProformaBtn');

  if (tab) qlBdSetTab(tab.getAttribute('data-business-tab'));
  if (saveCompany) qlBdSaveCompany();
  if (createClient) qlBdCreateClient();
  if (createProforma) qlBdCreateProforma();
});

const qlPreviousRenderUserForBusinessDesk = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForBusinessDesk(user);
  setTimeout(function() {
    qlBdLoadCompany();
    qlBdLoadClients();
    qlBdLoadProformas();
  }, 900);
};

/* === FinDesk Proforma View / Print 20260503-20 === */
function qlBdMoney(value, currency) {
  return (currency || 'EUR') + ' ' + Number(value || 0).toFixed(2);
}

function qlBdLine(text) {
  return text ? escapeHtml(text) : '';
}

async function qlBdOpenProforma(id) {
  const data = await qlApi('proforma_get', {id: Number(id)});

  if (!data.ok) {
    qlBdStatus('proformaStatus', 'Open error: ' + (data.error || 'unknown'));
    return;
  }

  qlBdRenderProformaDocument(data.proforma);
}

function qlBdRenderProformaDocument(p) {
  const preview = document.getElementById('proformaPreview');
  const doc = document.getElementById('proformaDocument');

  if (!preview || !doc || !p) return;

  const items = p.items || [];
  const currency = p.currency || 'EUR';

  doc.innerHTML = `
    <div class="pf-head">
      <div class="pf-brand-block">
        <div class="pf-logo-slot">
          <span>Company logo</span>
        </div>
        <div>
          <div class="pf-kicker">PROFORMA</div>
          <h1>${escapeHtml(p.proforma_number || '')}</h1>
          <p>${escapeHtml(p.title || 'Proforma')}</p>
        </div>
      </div>
      <div class="pf-meta">
        <div><span>Issue date</span><b>${escapeHtml(p.issue_date || '')}</b></div>
        <div><span>Due date</span><b>${escapeHtml(p.due_date || '—')}</b></div>
        <div><span>Status</span><b>${escapeHtml(p.status || 'draft')}</b></div>
      </div>
    </div>

    <div class="pf-parties">
      <div>
        <h3>From</h3>
        <b>${qlBdLine(p.company_name) || 'Company'}</b>
        <p>${qlBdLine(p.company_address)}</p>
        <p>${qlBdLine(p.company_city)} ${qlBdLine(p.company_country)}</p>
        <p>${qlBdLine(p.company_email)}</p>
        <p>${qlBdLine(p.company_phone)}</p>
        <p>${p.company_registration_number ? 'Reg. no: ' + escapeHtml(p.company_registration_number) : ''}</p>
        <p>${p.company_vat_number ? 'VAT: ' + escapeHtml(p.company_vat_number) : ''}</p>
      </div>
      <div>
        <h3>To</h3>
        <b>${qlBdLine(p.client_name) || 'Client'}</b>
        <p>${qlBdLine(p.client_contact_person)}</p>
        <p>${qlBdLine(p.client_address)}</p>
        <p>${qlBdLine(p.client_city)} ${qlBdLine(p.client_country)}</p>
        <p>${qlBdLine(p.client_email)}</p>
        <p>${qlBdLine(p.client_phone)}</p>
        <p>${p.client_registration_number ? 'Reg. no: ' + escapeHtml(p.client_registration_number) : ''}</p>
        <p>${p.client_vat_number ? 'VAT: ' + escapeHtml(p.client_vat_number) : ''}</p>
      </div>
    </div>

    <table class="pf-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Service / item</th>
          <th>Qty</th>
          <th>Unit</th>
          <th>Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(function(item) {
          return `
            <tr>
              <td>${escapeHtml(item.item_order || '')}</td>
              <td>
                <b>${escapeHtml(item.item_name || '')}</b>
                ${item.item_description ? `<small>${escapeHtml(item.item_description)}</small>` : ''}
              </td>
              <td>${escapeHtml(item.quantity || '')}</td>
              <td>${escapeHtml(item.unit_name || '')}</td>
              <td>${qlBdMoney(item.unit_price, currency)}</td>
              <td>${qlBdMoney(item.line_subtotal, currency)}</td>
            </tr>
          `;
        }).join('')}
      </tbody>
    </table>

    <div class="pf-totals">
      <div><span>Subtotal</span><b>${qlBdMoney(p.subtotal, currency)}</b></div>
      <div><span>Discount ${Number(p.discount_rate || 0).toFixed(2)}%</span><b>${qlBdMoney(p.discount_amount, currency)}</b></div>
      <div><span>VAT ${Number(p.vat_rate || 0).toFixed(2)}%</span><b>${qlBdMoney(p.vat_amount, currency)}</b></div>
      <div class="pf-total"><span>Total</span><b>${qlBdMoney(p.total_amount, currency)}</b></div>
    </div>

    ${p.public_note ? `<div class="pf-note"><h3>Note</h3><p>${escapeHtml(p.public_note)}</p></div>` : ''}

    <div class="pf-fiscal-note">${escapeHtml(p.fiscal_note || 'This document is a proforma offer and is not a fiscal invoice.')}</div>
  `;

  preview.classList.remove('hidden');
  qlBdStatus('proformaStatus', 'Proforma opened.');
}

function qlBdCloseProformaPreview() {
  const preview = document.getElementById('proformaPreview');
  if (preview) preview.classList.add('hidden');
}

function qlBdPrintProforma() {
  qlCloseTransientPanels();
  window.print();
}

const qlBdOldRenderProformasForOpen = qlBdRenderProformas;
qlBdRenderProformas = function() {
  const list = document.getElementById('proformaList');
  if (!list) return;

  if (!qlBdProformas.length) {
    list.innerHTML = '<p class="soft-note">No proformas yet.</p>';
    return;
  }

  list.innerHTML = qlBdProformas.map(function(p) {
    return `
      <div class="business-row">
        <div>
          <b>${escapeHtml(p.proforma_number)} · ${escapeHtml(p.title || 'Proforma')}</b>
          <small>${escapeHtml(p.client_name || 'No client')} · ${escapeHtml(p.issue_date || '')}</small>
        </div>
        <div class="business-row-actions">
          <strong>${escapeHtml(p.currency || 'EUR')} ${Number(p.total_amount || 0).toFixed(2)}</strong>
          <button class="ghost-btn small-btn" type="button" data-open-proforma="${p.id}">Open</button>
        </div>
      </div>
    `;
  }).join('');
};

document.addEventListener('click', function(event) {
  const open = event.target.closest('[data-open-proforma]');
  const close = event.target.closest('#closeProformaPreviewBtn');
  const print = event.target.closest('#printProformaBtn');

  if (open) qlBdOpenProforma(open.getAttribute('data-open-proforma'));
  if (close) qlBdCloseProformaPreview();
  if (print) qlBdPrintProforma();
});

/* === FinDesk Module Navigation NAV-1 20260503-24 === */
function qlResolveActiveGroupId() {
  const advanceId = typeof qlAdvanceGroupId !== 'undefined' ? qlAdvanceGroupId : 0;
  const ledgerId = typeof qlLedgerGroupId !== 'undefined' ? qlLedgerGroupId : 0;
  const captainId = window.qlCaptainActiveGroupId || 0;
  const firstGroupId = Array.isArray(qlGroups) && qlGroups.length ? Number(qlGroups[0].id || 0) : 0;
  return Number(advanceId || ledgerId || captainId || firstGroupId || 0);
}

function qlUseActiveGroupForLedger() {
  const groupId = qlResolveActiveGroupId();
  if (!groupId) return;

  qlLedgerScopeMode = 'group';
  qlLedgerGroupId = groupId;

  const select = document.getElementById('ledgerGroupSelect');
  if (select) {
    select.classList.remove('hidden');
    select.value = String(groupId);
  }
  document.querySelectorAll('[data-scope-mode]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-scope-mode') === 'group');
  });
}

const QL_MODULE_STATE_KEY = 'ql_module_state_v1';
const QL_MODULE_STATE_ALLOWED = ['ontherun', 'ledger', 'reports', 'captain', 'money', 'groups', 'business', 'premium', 'settings'];
let qlBrowserHistoryReady = false;
let qlBrowserHistoryApplying = false;

function qlBrowserHistorySupported() {
  return !!(window.history && typeof window.history.pushState === 'function' && typeof window.history.replaceState === 'function');
}

function qlBuildBrowserState(moduleName, options) {
  const opts = options || {};
  const requested = QL_MODULE_STATE_ALLOWED.includes(String(moduleName || '')) ? String(moduleName) : 'ontherun';
  const state = {
    findesk_app: true,
    module: requested,
    screen: opts.screen ? String(opts.screen) : '',
    focus: opts.focus ? String(opts.focus) : '',
    scope_mode: qlLedgerScopeMode === 'group' ? 'group' : 'personal',
    scope_group_id: qlResolveActiveGroupId() || 0,
    ts: Date.now()
  };

  if (requested === 'ontherun') {
    state.ontherun_screen = opts.ontherun_screen || opts.screen || (
      document.body.classList.contains('otr-editor-open')
        ? 'editor'
        : (document.body.classList.contains('otr-cards-open')
          ? 'cards'
          : (document.body.classList.contains('otr-stream-gate-open') ? 'stream_gate' : ''))
    );
    state.stream_type = opts.stream_type || (typeof window.qlOtrSimpleCurrentStream === 'function' ? String(window.qlOtrSimpleCurrentStream() || '') : '');
    state.tape_id = Number(opts.tape_id || opts.tapeId || window.qlOtrActiveTapeId || 0);
    state.archived_only = opts.archivedOnly || opts.archived_only ? 1 : 0;
  }

  return state;
}

function qlWriteBrowserState(moduleName, options, mode) {
  if (!qlBrowserHistorySupported() || qlBrowserHistoryApplying) return;
  const state = qlBuildBrowserState(moduleName, options || {});
  const method = mode === 'replace' || !qlBrowserHistoryReady ? 'replaceState' : (mode === 'push' ? 'pushState' : '');
  if (!method) return;
  window.history[method](state, '', '/app.php');
  qlBrowserHistoryReady = true;
}

function qlReplaceBrowserHistoryFromCurrentState() {
  if (!qlBrowserHistorySupported()) return;
  const state = qlLoadModuleState() || qlBuildBrowserState('ontherun', {screen: 'editor'});
  if (!state.findesk_app) state.findesk_app = true;
  window.history.replaceState(state, '', '/app.php');
  qlBrowserHistoryReady = true;
}

window.addEventListener('popstate', function(event) {
  const state = event.state;
  if (!state || state.findesk_app !== true) return;
  qlBrowserHistoryApplying = true;
  try {
    qlApplyModuleState(state);
  } finally {
    setTimeout(function() {
      qlBrowserHistoryApplying = false;
    }, 80);
  }
});

function qlSaveModuleState(moduleName, options) {
  if (!window.localStorage) return;
  if (!moduleName) return;

  const requested = String(moduleName);
  if (!QL_MODULE_STATE_ALLOWED.includes(requested)) return;

  const payload = {
    module: requested,
    screen: options && options.screen ? String(options.screen) : '',
    focus: options && options.focus ? String(options.focus) : '',
    scope_mode: qlLedgerScopeMode === 'group' ? 'group' : 'personal',
    scope_group_id: qlResolveActiveGroupId() || 0,
    ts: Date.now()
  };

  if (requested === 'ontherun') {
    const visibleOtrScreen = document.body.classList.contains('otr-editor-open')
      ? 'editor'
      : (document.body.classList.contains('otr-cards-open')
        ? 'cards'
        : (document.body.classList.contains('otr-stream-gate-open') ? 'stream_gate' : ''));
    const stream = options && options.stream_type
      ? String(options.stream_type)
      : (typeof window.qlOtrSimpleCurrentStream === 'function' ? String(window.qlOtrSimpleCurrentStream() || '') : '');
    const openCardId = Number(document.getElementById('otrSimpleCard')?.dataset?.otrOpenCardId || 0);
    const tapeId = Number((options && (options.tape_id || options.tapeId)) || openCardId || window.qlOtrActiveTapeId || 0);
    const archiveButton = document.getElementById('otrArchiveCardsBtn');
    const visibleArchiveMode = !!(visibleOtrScreen === 'cards'
      && archiveButton
      && String(archiveButton.textContent || '').trim() === 'Журнал');
    payload.ontherun_screen = options && (options.ontherun_screen || options.screen)
      ? String(options.ontherun_screen || options.screen)
      : visibleOtrScreen;
    payload.stream_type = stream === 'card' ? 'card' : (stream === 'cash' ? 'cash' : '');
    payload.tape_id = Number.isFinite(tapeId) && tapeId > 0 ? tapeId : 0;
    payload.archived_only = options && (options.archivedOnly || options.archived_only) ? 1 : (visibleArchiveMode ? 1 : 0);
  }

  localStorage.setItem(QL_MODULE_STATE_KEY, JSON.stringify(payload));
}

function qlLoadModuleState() {
  if (!window.localStorage) return null;
  const raw = localStorage.getItem(QL_MODULE_STATE_KEY);
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return null;
    if (!parsed.module || !QL_MODULE_STATE_ALLOWED.includes(String(parsed.module))) return null;
    return parsed;
  } catch (error) {
    return null;
  }
}

function qlApplyModuleState(state) {
  if (!state || typeof state !== 'object') return false;

  const requested = String(state.module || '');
  if (!QL_MODULE_STATE_ALLOWED.includes(requested)) return false;

  if (state.scope_mode === 'group') {
    const hasGroups = Array.isArray(qlGroups) && qlGroups.length > 0;
    const rawScopeGroupId = Number(state.scope_group_id || 0);
    const requestedGroupId = Number.isFinite(rawScopeGroupId) ? rawScopeGroupId : 0;
    const isValidGroup = hasGroups && requestedGroupId > 0 && qlGroups.some(function(group) {
      return Number(group.id) === requestedGroupId;
    });

    const fallbackGroupId = isValidGroup
      ? requestedGroupId
      : (hasGroups ? Number(qlGroups[0].id || 0) : 0);

    if (fallbackGroupId > 0) {
      qlLedgerScopeMode = 'group';
      qlLedgerGroupId = fallbackGroupId;
      if (typeof qlAdvanceGroupId !== 'undefined') qlAdvanceGroupId = fallbackGroupId;

      const select = document.getElementById('ledgerGroupSelect');
      if (select) {
        select.classList.remove('hidden');
        select.value = String(fallbackGroupId);
      }

      if (typeof qlRefreshGroupSelect === 'function') qlRefreshGroupSelect();
      if (typeof qlAdvanceRefreshGroupSelect === 'function') qlAdvanceRefreshGroupSelect();

      if (typeof qlScopeMessage === 'function') qlScopeMessage('Группа выбрана.');
      if (typeof qlLoadLedger === 'function') qlLoadLedger();
    } else {
      qlLedgerScopeMode = 'personal';
      qlLedgerGroupId = null;
      if (typeof qlAdvanceGroupId !== 'undefined') qlAdvanceGroupId = null;
    }
  } else {
    qlLedgerScopeMode = 'personal';
  }

  const moduleOptions = {
    screen: String(state.screen || ''),
    focus: String(state.focus || ''),
    label: 'Восстановление'
  };

  if (requested === 'ontherun') {
    moduleOptions.screen = String(state.ontherun_screen || state.screen || '');
    moduleOptions.ontherun_screen = moduleOptions.screen;
    moduleOptions.stream_type = String(state.stream_type || '');
    moduleOptions.tape_id = Number(state.tape_id || 0);
    moduleOptions.archivedOnly = !!Number(state.archived_only || 0);
  }

  qlSetModule(requested, moduleOptions);

  return true;
}

function qlRestoreModuleState() {
  const state = qlLoadModuleState();
  if (!state) return false;
  return qlApplyModuleState(state);
}

function qlSetModule(moduleName, options) {
  options = options || {};
  const requested = moduleName || 'ledger';
  const visible = requested === 'reports' ? 'ledger' : requested;
  let requestedScreen = options.screen || '';
  if (requested === 'money' && !requestedScreen) {
    requestedScreen = 'overview';
  }

  if (requested !== 'ontherun') {
    document.body.classList.remove('otr-stream-gate-open', 'otr-cards-open', 'otr-editor-open');
    ['otrStreamGate', 'otrReportCardsPanel', 'otrSimpleCard'].forEach(function(id) {
      const node = document.getElementById(id);
      if (node) {
        node.classList.add('hidden');
        node.setAttribute('aria-hidden', 'true');
      }
    });
  }

  document.querySelectorAll('[data-module-tab]').forEach(function(btn) {
    const btnModule = btn.getAttribute('data-module-tab');
    const btnScreen = btn.getAttribute('data-module-screen') || '';
    const moduleMatches = btnModule === requested;
    const screenMatches = requestedScreen ? btnScreen === requestedScreen : !btnScreen;
    btn.classList.toggle('active', moduleMatches && screenMatches);
  });

  const currentItem = Array.from(document.querySelectorAll('[data-module-tab]')).find(function(btn) {
    const btnModule = btn.getAttribute('data-module-tab');
    const btnScreen = btn.getAttribute('data-module-screen') || '';
    return btnModule === requested && (requestedScreen ? btnScreen === requestedScreen : !btnScreen);
  });
  const currentLabel = options.label || (currentItem && currentItem.textContent ? currentItem.textContent.trim() : '') || 'Меню';
  document.querySelectorAll('[data-module-menu-current]').forEach(function(label) {
    label.textContent = currentLabel;
  });
  document.querySelectorAll('[data-module-menu-toggle]').forEach(function(btn) {
    btn.classList.add('active');
  });

  document.querySelectorAll('.ql-module[data-module]').forEach(function(module) {
    module.classList.toggle('hidden', module.getAttribute('data-module') !== visible);
    module.classList.toggle('active', module.getAttribute('data-module') === visible);
  });

  if (requested === 'ledger') {
    qlUseActiveGroupForLedger();
    const reportPanel = document.getElementById('reportPanel');
    const ledgerFeed = document.getElementById('ledgerFeed');

    if (reportPanel) reportPanel.classList.add('hidden');
    if (typeof qlLoadLedger === 'function') qlLoadLedger();
    if (ledgerFeed && ledgerFeed.scrollIntoView) {
      setTimeout(function() {
        ledgerFeed.scrollIntoView({behavior: 'smooth', block: 'start'});
      }, 40);
    }
  }

  if (requested === 'reports') {
    qlUseActiveGroupForLedger();
    const reportPanel = document.getElementById('reportPanel');
    const resultCard = document.getElementById('ledgerResultBtn');

    if (reportPanel) reportPanel.classList.remove('hidden');
    if (typeof qlRunReport === 'function') qlRunReport();
    if (typeof qlLoadFinalReports === 'function') qlLoadFinalReports();

    setTimeout(function() {
      if (reportPanel && reportPanel.scrollIntoView) {
        reportPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
      } else if (resultCard && resultCard.scrollIntoView) {
        resultCard.scrollIntoView({behavior: 'smooth', block: 'start'});
      }
    }, 40);
  }

  if (visible === 'money' && requestedScreen && typeof qlAdvancedSetScreen === 'function') {
    qlAdvancedSetScreen(requestedScreen);
  }

  if (requested === 'captain' && options.focus === 'archive') {
    const archive = document.getElementById('captainArchivePanel') || document.getElementById('captainArchiveList');
    if (archive && archive.scrollIntoView) {
      setTimeout(function() {
        archive.scrollIntoView({behavior: 'smooth', block: 'start'});
      }, 80);
    }
  }

  qlSaveModuleState(requested, {
    screen: requestedScreen,
    focus: options.focus || ''
  });
  qlWriteBrowserState(requested, {
    screen: requestedScreen,
    focus: options.focus || ''
  }, options.history || '');
}

document.addEventListener('click', function(event) {
  const tab = event.target.closest('[data-module-tab]');
  if (!tab) return;

  qlSetModule(tab.getAttribute('data-module-tab') || 'ledger', {
    screen: tab.getAttribute('data-module-screen') || '',
    focus: tab.getAttribute('data-module-focus') || '',
    label: tab.textContent ? tab.textContent.trim() : '',
    history: 'push'
  });

  const panel = tab.closest('[data-module-menu-panel]');
  if (panel) {
    panel.classList.add('hidden');
    document.querySelectorAll('[data-module-menu-toggle]').forEach(function(btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
  }
});

document.addEventListener('click', function(event) {
  const toggle = event.target.closest('[data-module-menu-toggle]');
  const panel = document.querySelector('[data-module-menu-panel]');

  if (toggle && panel) {
    event.preventDefault();
    const nextOpen = panel.classList.contains('hidden');
    panel.classList.toggle('hidden', !nextOpen);
    toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
    return;
  }

  if (panel && !event.target.closest('.module-menu')) {
    panel.classList.add('hidden');
    document.querySelectorAll('[data-module-menu-toggle]').forEach(function(btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
  }
});

document.addEventListener('click', function(event) {
  document.querySelectorAll('.otr-gate-menu[open]').forEach(function(menu) {
    if (!menu.contains(event.target)) {
      menu.removeAttribute('open');
    }
  });
});

document.addEventListener('keydown', function(event) {
  if (event.key !== 'Escape') return;
  const openModal = Array.from(document.querySelectorAll('.modal:not(.hidden)')).pop();
  if (openModal) {
    const closeButton = openModal.querySelector('[data-close-proof-viewer], [data-close-receipt-scanner], [data-close-otr-card], [data-close-otr-review], [data-close-otr-session], [data-close-captain-review], [data-close-captain-included], [data-close-captain-archive], [data-close-advanced-excel-preview], [data-close-ledger-detail], [data-close-message-modal], [data-close-modal]');
    if (closeButton) {
      closeButton.click();
    } else {
      openModal.classList.add('hidden');
      openModal.setAttribute('aria-hidden', 'true');
    }
    return;
  }
  const inviteActions = document.getElementById('inviteActions');
  if (inviteActions && !inviteActions.classList.contains('hidden')) {
    inviteActions.classList.add('hidden');
    return;
  }
  const modulePanel = document.querySelector('[data-module-menu-panel]');
  if (modulePanel && !modulePanel.classList.contains('hidden')) {
    modulePanel.classList.add('hidden');
    document.querySelectorAll('[data-module-menu-toggle]').forEach(function(btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
    return;
  }
  document.querySelectorAll('.otr-gate-menu[open]').forEach(function(menu) {
    menu.removeAttribute('open');
  });
});

document.addEventListener('click', function(event) {
  const mode = event.target.closest('[data-mode-open]');
  if (!mode) return;

  qlSetModule(mode.getAttribute('data-mode-open') || 'ledger', {history: 'push'});
});

window.qlSetModule = qlSetModule;

/* === FinDesk Accountable Money UI STEP-4 20260520 === */
let qlAdvanceGroupId = null;
let qlAdvances = [];
let qlAdvanceMembers = [];
let qlAdvanceScope = {};
let qlAdvanceTotals = {};
let qlAdvancedLastPosition = null;
let qlAdvancedOpenPeriod = {};

function qlAdvanceStatus(message) {
  const el = document.getElementById('advanceStatus');
  if (el) el.textContent = message || '';
}

function qlAdvanceStatusLabel(status) {
  if (status === 'issued') return 'Выдано';
  if (status === 'submitted') return 'На проверке';
  if (status === 'accepted') return 'Принято';
  if (status === 'returned') return 'Возврат';
  if (status === 'discrepancy') return 'Расхождение';
  if (status === 'closed') return 'Закрыто';
  return status || 'Под отчет';
}

function qlAdvanceIsWaiting(status) {
  return ['issued', 'submitted', 'returned', 'discrepancy'].includes(status);
}

function qlAdvanceEffectiveCashLeft(advance) {
  const a = advance || {};
  const s = a.summary || {};
  const values = [
    a.actual_remaining,
    s.effective_cash_left,
    s.cash_left
  ];

  for (const value of values) {
    if (value === null || value === undefined || value === '') continue;
    const n = Number(value);
    if (Number.isFinite(n)) return n;
  }

  return 0;
}

function qlAdvanceRefreshGroupSelect() {
  const select = document.getElementById('advanceGroupSelect');
  if (!select) return;

  const groups = Array.isArray(qlGroups) ? qlGroups : [];

  if (qlAdvanceGroupId && !groups.some(function(group) { return String(group.id) === String(qlAdvanceGroupId); })) {
    qlAdvanceGroupId = null;
  }

  if (!qlAdvanceGroupId && groups.length) {
    qlAdvanceGroupId = groups[0].id;
  }

  select.innerHTML = '<option value="">Выберите группу</option>' + groups.map(function(group) {
    const level = group.access_level || group.role || 'base';
    return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(level) + '</option>';
  }).join('');

  select.value = qlAdvanceGroupId ? String(qlAdvanceGroupId) : '';
}

function qlAdvanceRenderMembers() {
  const select = document.getElementById('advanceMemberSelect');
  if (!select) return;

  const members = qlAdvanceMembers || [];
  select.innerHTML = '<option value="">Выберите сотрудника</option>' + members.map(function(member) {
    const label = (member.display_name || member.email || 'Участник') + ' · ' + (member.access_level || member.role || 'base');
    return '<option value="' + escapeHtml(member.user_id) + '">' + escapeHtml(label) + '</option>';
  }).join('');
}

async function qlAdvanceLoadMembers() {
  qlAdvanceMembers = [];
  qlAdvanceRenderMembers();

  if (!qlAdvanceGroupId || !qlAdvanceScope.can_manage_money) return;

  const data = await qlApi('group_members', { group_id: Number(qlAdvanceGroupId) });
  if (!data.ok) {
    qlAdvanceStatus('Ошибка участников: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceMembers = data.members || [];
  qlAdvanceRenderMembers();
}

function qlAdvanceRenderIssuePanel() {
  const panel = document.getElementById('advanceIssuePanel');
  if (!panel) return;

  const canIssue = !!(qlAdvanceGroupId && qlAdvanceScope && qlAdvanceScope.can_manage_money);
  panel.classList.toggle('hidden', !canIssue);
}

function qlAdvanceRenderSummary() {
  const summary = document.getElementById('advanceSummary');
  if (!summary) return;

  const finalizedAt = qlAdvancedOpenPeriod && qlAdvancedOpenPeriod.finalized_at ? String(qlAdvancedOpenPeriod.finalized_at) : '';
  let issued = 0;
  let spent = 0;
  let expectedLeft = 0;
  let waiting = 0;
  let closedSpent = 0;
  let closedCount = 0;

  qlAdvances.forEach(function(advance) {
    const s = advance.summary || {};
    const status = advance.status || '';
    if (status !== 'accepted' && status !== 'closed') {
      issued += Number(advance.amount || 0);
      spent += Number(s.cash_out || 0) + Number(s.card_out || 0);
      expectedLeft += qlAdvanceEffectiveCashLeft(advance);
    }
    if (status === 'accepted' && (!finalizedAt || String(advance.accepted_at || '') > finalizedAt)) {
      closedSpent += Number(s.cash_out || 0) + Number(s.card_out || 0);
      closedCount += 1;
    }
    if (qlAdvanceIsWaiting(status)) waiting += 1;
  });

  const totals = qlAdvanceTotals || {};
  const openIssued = totals.open_issued !== undefined ? Number(totals.open_issued || 0) : issued;
  const openSpent = totals.open_spent !== undefined ? Number(totals.open_spent || 0) : spent;
  const openCashSpent = totals.open_cash_spent !== undefined ? Number(totals.open_cash_spent || 0) : Number(openSpent || 0);
  const openCardSpent = totals.open_card_spent !== undefined ? Number(totals.open_card_spent || 0) : 0;
  const openLeft = totals.open_cash_left !== undefined ? Number(totals.open_cash_left || 0) : expectedLeft;
  const acceptedSpent = finalizedAt ? closedSpent : (totals.accepted_spent !== undefined ? Number(totals.accepted_spent || 0) : closedSpent);
  const acceptedCount = finalizedAt ? closedCount : (totals.accepted_count !== undefined ? Number(totals.accepted_count || 0) : closedCount);

  summary.innerHTML = `
    <div><span>Открыто выдано</span><b>${qlCurrency(openIssued)}</b></div>
    <div><span>Расход наличными</span><b>${qlCurrency(openCashSpent)}</b></div>
    <div><span>Расход с карты</span><b>${qlCurrency(openCardSpent)}</b></div>
    <div><span>На руках</span><b>${qlCurrency(openLeft)}</b></div>
    <div><span>Закрыто</span><b>${qlCurrency(acceptedSpent)}</b><small>${acceptedCount} отчетов</small></div>
  `;
}

function qlAdvanceComputeStats() {
  const stats = {
    issued: 0,
    spent: 0,
    left: 0,
    waiting: 0,
    submitted: 0,
    discrepancy: 0,
    returned: 0,
    active: 0,
    accepted: 0,
    records: 0,
    acceptedSpent: 0,
    openCashSpent: 0,
    openCardSpent: 0,
    acceptedCashSpent: 0,
    acceptedCardSpent: 0,
    acceptedRecords: 0,
    reviewCashSpent: 0,
    reviewCardSpent: 0
  };

  qlAdvances.forEach(function(advance) {
    const s = advance.summary || {};
    const status = advance.status || '';
    const isOpen = status !== 'accepted' && status !== 'closed';

    if (isOpen) {
      stats.issued += Number(advance.amount || 0);
      stats.spent += Number(s.cash_out || 0) + Number(s.card_out || 0);
      stats.openCashSpent += Number(s.cash_out || 0);
      stats.openCardSpent += Number(s.card_out || 0);
      stats.left += qlAdvanceEffectiveCashLeft(advance);
      stats.records += Number(s.records_count || 0);
    }

    if (qlAdvanceIsWaiting(status)) stats.waiting += 1;
    if (status === 'submitted') stats.submitted += 1;
    if (status === 'discrepancy') stats.discrepancy += 1;
    if (status === 'submitted' || status === 'discrepancy') {
      stats.reviewCashSpent += Number(s.cash_out || 0);
      stats.reviewCardSpent += Number(s.card_out || 0);
    }
    if (status === 'returned') stats.returned += 1;
    if (status === 'issued') stats.active += 1;
    if (status === 'accepted') {
      stats.accepted += 1;
      stats.acceptedSpent += Number(s.cash_out || 0) + Number(s.card_out || 0);
      stats.acceptedCashSpent += Number(s.cash_out || 0);
      stats.acceptedCardSpent += Number(s.card_out || 0);
      stats.acceptedRecords += Number(s.records_count || 0);
    }
  });

  return stats;
}

function qlAdvancedSelectedGroup() {
  const groups = Array.isArray(qlGroups) ? qlGroups : [];
  return groups.find(function(group) {
    return String(group.id) === String(qlAdvanceGroupId || '');
  }) || null;
}

function qlAdvancedRoleLabel(access) {
  if (access === 'advanced') return 'Advanced';
  if (access === 'manager') return 'FinDesk';
  return 'Живой отчет';
}

function qlAdvancedReceiveStatus(message) {
  const el = document.getElementById('advancedReceiveStatus');
  if (el) el.textContent = message || '';
}

function qlAdvancedRenderPosition(position, period) {
  const before = document.getElementById('advancedBeforeAmount');
  const after = document.getElementById('advancedAfterAmount');
  const movement = document.getElementById('advancedMovementAmount');
  const beforeMeta = document.getElementById('advancedBeforeMeta');
  const afterMeta = document.getElementById('advancedAfterMeta');
  const periodMeta = document.getElementById('advancedPeriodMeta');

  const pos = position || {};
  const p = period || {};
  const from = p.from || '';
  const to = p.to || '';

  if (before) before.textContent = qlCurrency(pos.before || 0);
  if (after) after.textContent = qlCurrency(pos.after || 0);
  if (movement) {
    const value = Number(pos.movement || 0);
    movement.textContent = qlSignedCurrency(value);
    movement.classList.toggle('negative', value < 0);
    movement.classList.toggle('positive', value > 0);
  }

  if (beforeMeta) beforeMeta.textContent = pos.before_label || (from ? 'до ' + from : 'перед последним отчетом');
  if (afterMeta) afterMeta.textContent = pos.after_label || 'текущий баланс';
  if (periodMeta) periodMeta.textContent = pos.movement_label || (from && to ? from + ' → ' + to : 'последний отчет');
}

function qlAdvancedRenderCashSplit(balance, stats) {
  const before = document.getElementById('advancedBeforeAmount');
  const after = document.getElementById('advancedAfterAmount');
  const movement = document.getElementById('advancedMovementAmount');
  const beforeMeta = document.getElementById('advancedBeforeMeta');
  const afterMeta = document.getElementById('advancedAfterMeta');
  const periodMeta = document.getElementById('advancedPeriodMeta');
  const arrow = document.querySelector('#advancedPositionStrip .advanced-position-arrow');
  const availableCash = balance ? Number(balance.available_cash_balance || 0) : 0;
  const reserveCash = balance ? Number(balance.accountable_cash_left_open || 0) : Number((stats || {}).left || 0);
  const reserveCount = balance ? Number(balance.accountable_open_count || 0) : 0;
  const physicalTotal = availableCash + reserveCash;

  if (before) before.textContent = balance ? qlCurrency(availableCash) : '...';
  if (after) after.textContent = balance ? qlCurrency(reserveCash) : '...';
  if (movement) {
    movement.textContent = balance ? qlCurrency(physicalTotal) : '...';
    movement.classList.remove('negative', 'positive');
  }
  if (beforeMeta) beforeMeta.textContent = 'фактически у администратора';
  if (afterMeta) afterMeta.textContent = reserveCount ? (reserveCount + ' открытых подотчетов') : 'нет открытых подотчетов';
  if (periodMeta) periodMeta.textContent = 'контрольная сумма наличных';
  if (arrow) arrow.textContent = '+';
}

function qlAdvancedSetScreen(screen, options) {
  const target = screen || 'overview';
  const opts = options || {};
  const module = document.getElementById('moduleMoney');
  if (module) {
    module.setAttribute('data-advanced-current-screen', target);
  }

  document.querySelectorAll('[data-advanced-screen]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-advanced-screen') === target);
  });

  document.querySelectorAll('[data-advanced-screen-panel]').forEach(function(panel) {
    panel.classList.toggle('is-active', panel.getAttribute('data-advanced-screen-panel') === target);
  });
  if (opts.history) {
    qlSaveModuleState('money', {screen: target});
    qlWriteBrowserState('money', {screen: target}, opts.history);
  }
}

function qlAdvancedRenderPanels(balanceSummary, reportData, balanceData) {
  const stats = qlAdvanceComputeStats();
  const kpi = document.getElementById('advancedKpiGrid');
  const pipeline = document.getElementById('advancedPipeline');
  const team = document.getElementById('advancedTeamPanel');
  const teamCount = document.getElementById('advancedTeamCount');
  const rules = document.getElementById('advancedRulesPanel');
  const integrations = document.getElementById('advancedIntegrationPanel');
  const group = qlAdvancedSelectedGroup();
  const canManage = !!(qlAdvanceScope && qlAdvanceScope.can_manage_money);
  const canModerate = !!(qlAdvanceScope && qlAdvanceScope.can_moderate);
  const balance = balanceSummary || null;
  const balanceEnvelope = balanceData || {};
  const report = reportData || {};
  const reportSummary = report.summary || null;
  const cardTotals = report.virtual_cards || {};
  const includedCardTotals = balanceEnvelope.virtual_cards || {};
  const cardRecords = Number(cardTotals.records || 0);
  const cardCards = Number(cardTotals.cards || 0);
  const submittedCards = Number(cardTotals.submitted_cards || 0);
  const submittedRecords = Number(cardTotals.submitted_records || 0);
  const submittedLiveCashExpense = Number(cardTotals.submitted_cash_expense || 0);
  const submittedLiveCardExpense = Number(cardTotals.submitted_noncash_expense || 0);
  const includedCards = Number(cardTotals.included_cards || 0);
  const advancedRows = cardRecords + Number(stats.records || 0);
  const availableCash = balance ? Number(balance.available_cash_balance || 0) : 0;
  const reserveCash = balance ? Number(balance.accountable_cash_left_open || 0) : Number(stats.left || 0);
  const reserveCount = balance ? Number(balance.accountable_open_count || 0) : Number(stats.active || 0) + Number(stats.returned || 0) + Number(stats.submitted || 0) + Number(stats.discrepancy || 0);
  const advanceTotals = qlAdvanceTotals || {};
  const openSpent = advanceTotals.open_spent !== undefined ? Number(advanceTotals.open_spent || 0) : Number(stats.spent || 0);
  const openCashSpent = advanceTotals.open_cash_spent !== undefined ? Number(advanceTotals.open_cash_spent || 0) : Number(stats.openCashSpent || 0);
  const openCardSpent = advanceTotals.open_card_spent !== undefined ? Number(advanceTotals.open_card_spent || 0) : Number(stats.openCardSpent || 0);
  const acceptedAdvanceSpent = advanceTotals.accepted_spent !== undefined ? Number(advanceTotals.accepted_spent || 0) : Number(stats.acceptedSpent || 0);
  const acceptedAdvanceCashSpent = advanceTotals.accepted_cash_spent !== undefined ? Number(advanceTotals.accepted_cash_spent || 0) : Number(stats.acceptedCashSpent || 0);
  const acceptedAdvanceCardSpent = advanceTotals.accepted_card_spent !== undefined ? Number(advanceTotals.accepted_card_spent || 0) : Number(stats.acceptedCardSpent || 0);
  const acceptedAdvanceCount = advanceTotals.accepted_count !== undefined ? Number(advanceTotals.accepted_count || 0) : Number(stats.accepted || 0);
  const physicalTotal = availableCash + reserveCash;
  const workBalance = reportSummary ? Number(reportSummary.balance || 0) : (balance ? Number(balance.balance || 0) : 0);
  const includedLiveCashExpense = Number(includedCardTotals.cash_expense || 0);
  const includedLiveCardExpense = Number(includedCardTotals.noncash_expense || 0);
  const finalizedAt = qlAdvancedOpenPeriod && qlAdvancedOpenPeriod.finalized_at ? String(qlAdvancedOpenPeriod.finalized_at) : '';
  const openLive = qlAdvancedOpenPeriod && qlAdvancedOpenPeriod.live_included ? qlAdvancedOpenPeriod.live_included : null;
  const currentAcceptedAdvances = finalizedAt
    ? qlAdvances.filter(function(advance) {
      return (advance.status || '') === 'accepted' && String(advance.accepted_at || '') > finalizedAt;
    })
    : qlAdvances.filter(function(advance) { return (advance.status || '') === 'accepted'; });
  const currentAcceptedAdvanceSpent = finalizedAt
    ? currentAcceptedAdvances.reduce(function(sum, advance) {
      const s = advance.summary || {};
      return sum + Number(s.cash_out || 0) + Number(s.card_out || 0);
    }, 0)
    : acceptedAdvanceSpent;
  const currentAcceptedAdvanceCashSpent = finalizedAt
    ? currentAcceptedAdvances.reduce(function(sum, advance) {
      return sum + Number((advance.summary || {}).cash_out || 0);
    }, 0)
    : acceptedAdvanceCashSpent;
  const currentAcceptedAdvanceCardSpent = finalizedAt
    ? currentAcceptedAdvances.reduce(function(sum, advance) {
      return sum + Number((advance.summary || {}).card_out || 0);
    }, 0)
    : acceptedAdvanceCardSpent;
  const currentAcceptedAdvanceCount = finalizedAt ? currentAcceptedAdvances.length : acceptedAdvanceCount;
  const currentIncludedLiveCashExpense = openLive ? Number(openLive.cash_expense || 0) : includedLiveCashExpense;
  const currentIncludedLiveCardExpense = openLive ? Number(openLive.noncash_expense || 0) : includedLiveCardExpense;
  const currentIncludedLiveExpense = currentIncludedLiveCashExpense + currentIncludedLiveCardExpense;
  const currentIncludedLiveCount = openLive ? Number(openLive.cards || 0) : Number(includedCardTotals.included_cards || includedCardTotals.cards || 0);
  const closedReportsCount = currentAcceptedAdvanceCount + currentIncludedLiveCount;
  const closedReportsTotal = currentAcceptedAdvanceSpent + currentIncludedLiveExpense;
  const closedCashExpense = currentAcceptedAdvanceCashSpent + currentIncludedLiveCashExpense;
  const closedCardExpense = currentAcceptedAdvanceCardSpent + currentIncludedLiveCardExpense;
  const reviewCashExpense = Number(stats.reviewCashSpent || 0) + submittedLiveCashExpense;
  const reviewCardExpense = Number(stats.reviewCardSpent || 0) + submittedLiveCardExpense;
  const employeeIssuedControl = reserveCash;
  if (!qlAdvanceGroupId) {
    qlAdvancedLastPosition = null;
  }
  const position = report.position || qlAdvancedLastPosition || null;

  if (position) {
    qlAdvancedLastPosition = position;
  }
  qlAdvancedRenderCashSplit(balance, stats);

  const count = document.getElementById('advanceCount');
  if (count) {
    count.textContent = advancedRows ? (advancedRows + ' строк') : '0 строк';
  }

  const submittedTotal = Number(stats.submitted || 0) + submittedCards;
  const acceptedTotal = currentAcceptedAdvanceCount + currentIncludedLiveCount;
  const reserveMeta = reserveCount
    ? (reserveCount + ' открытых · наличный расход ' + qlCurrency(openCashSpent))
    : 'нет открытых подотчетов';
  const issuedMeta = reserveCount ? (reserveCount + ' открыто') : 'нет открытых подотчетов';
  const closedMeta = closedReportsCount + ' отчетов включено';

  if (kpi) {
    kpi.innerHTML = `
      <div class="advanced-kpi-card blue"><span>У администратора</span><b>${qlCurrency(availableCash)}</b><small>фактически на руках</small></div>
      <div class="advanced-kpi-card green"><span>У сотрудников</span><b>${qlCurrency(employeeIssuedControl)}</b><small>${issuedMeta}</small></div>
      <div class="advanced-kpi-card teal"><span>Физически всего</span><b>${qlCurrency(physicalTotal)}</b><small>администратор + сотрудники</small></div>
      <div class="advanced-kpi-card gold"><span>Карточные расходы</span><b>${qlCurrency(closedCardExpense)}</b><small>не уменьшают физическую кассу</small></div>
    `;
  }

  if (pipeline) {
    pipeline.innerHTML = `
      <div class="advanced-pipe-card red"><b>${stats.active}</b><span>Выдано</span></div>
      <div class="advanced-pipe-card gold"><b>${submittedTotal}</b><span>Сдано</span></div>
      <div class="advanced-pipe-card coral"><b>${stats.discrepancy}</b><span>Расхождение</span></div>
      <div class="advanced-pipe-card teal"><b>${acceptedTotal}</b><span>В учете</span></div>
    `;
  }

  if (teamCount) {
    teamCount.textContent = (qlAdvanceMembers || []).length + ' участников';
  }

  if (team) {
    if (!group) {
      team.innerHTML = '<p class="soft-note">Выберите группу.</p>';
    } else if (!canManage && !canModerate) {
      team.innerHTML = '<p class="soft-note">В этом уровне доступа список команды закрыт администратором.</p>';
    } else if (!(qlAdvanceMembers || []).length) {
      team.innerHTML = '<p class="soft-note">Участники загружаются или еще не добавлены.</p>';
    } else {
      const roleStats = {base: 0, manager: 0, advanced: 0};
      qlAdvanceMembers.forEach(function(member) {
        const access = member.access_level || 'base';
        roleStats[access] = (roleStats[access] || 0) + 1;
      });

      team.innerHTML = `
        <div class="advanced-role-strip">
          <span><b>${roleStats.base || 0}</b> Живой отчет</span>
          <span><b>${roleStats.manager || 0}</b> FinDesk</span>
          <span><b>${roleStats.advanced || 0}</b> Advanced</span>
        </div>
        <div class="advanced-team-list">
          ${qlAdvanceMembers.slice(0, 8).map(function(member) {
            const access = member.access_level || member.role || 'base';
            return '<div><span><b>' + escapeHtml(member.display_name || member.email || 'Участник') + '</b><small>' + escapeHtml(member.email || '') + '</small></span><em>' + escapeHtml(qlAdvancedRoleLabel(access)) + '</em></div>';
          }).join('')}
        </div>
      `;
    }
  }

  if (rules) {
    rules.innerHTML = `
      <div class="advanced-rule active"><b>Причина отмены обязательна</b><span>ошибочная выдача не удаляется молча</span></div>
      <div class="advanced-rule active"><b>Принятый отчет не удаляется</b><span>после ledger нужен корректирующий документ</span></div>
      <div class="advanced-rule ${canManage ? 'active' : 'locked'}"><b>Выдача денег</b><span>${canManage ? 'доступна текущей учетной записи' : 'только Advanced-администратору'}</span></div>
      <div class="advanced-rule ${canModerate ? 'active' : 'locked'}"><b>Модерация отчетов</b><span>${canModerate ? 'можно принимать и возвращать' : 'только FinDesk/Advanced'}</span></div>
    `;
  }

  if (integrations) {
    integrations.innerHTML = `
      <div class="advanced-integration on"><b>Сервер сайта</b><span>файлы и отчеты сохраняются в рабочем хранилище проекта</span></div>
      <div class="advanced-integration on"><b>GitHub handoff</b><span>кодовая база готова к фиксации и передаче</span></div>
      <div class="advanced-integration wait"><b>Google Drive</b><span>резервное дублирование требует подключенного коннектора/ключа</span></div>
      <div class="advanced-integration wait"><b>Внешний AI</b><span>сейчас анализ идет по данным учетной записи, без отправки наружу</span></div>
    `;
  }
}

async function qlAdvancedLoadLedgerBalance() {
  if (!qlAdvanceGroupId) {
    qlAdvancedLastPosition = null;
    qlAdvancedOpenPeriod = {};
    qlAdvancedRenderPanels(null, null);
    return;
  }

  const payload = {group_id: Number(qlAdvanceGroupId)};
  const results = await Promise.all([
    qlApi('ledger_balance', payload),
    qlApi('ledger_work_position', payload),
    qlApi('ledger_group_open_received_funds', payload)
  ]);
  const balance = results[0];
  const report = results[1];
  const openPeriod = results[2];
  qlAdvancedOpenPeriod = openPeriod && openPeriod.ok ? (openPeriod.open_period || {}) : {};
  qlAdvancedOpenPeriod.finalized_at = openPeriod && openPeriod.ok ? (openPeriod.finalized_at || null) : null;
  qlAdvancedRenderPanels(balance.ok ? (balance.summary || null) : null, report.ok ? report : null, balance.ok ? balance : null);
}

function qlAdvancedAuditLabel(action) {
  const labels = {
    group_funds_received: 'Получены средства',
    on_the_go_report_submitted: 'Сдан живой отчет',
    advance_issued: 'Выдача денег',
    advance_submitted: 'Отчет сдан',
    advance_accepted: 'Отчет принят',
    advance_returned: 'Возврат на правку',
    advance_unaccepted: 'Возврат из учета',
    advance_cash_returned: 'Остаток в кассу',
    advance_cancelled: 'Выдача отменена',
    member_access_updated: 'Права участника',
    ai_analysis_run: 'AI-анализ',
    login: 'Вход'
  };
  return labels[action] || action || 'Действие';
}

async function qlAdvancedReceiveFunds() {
  if (!qlAdvanceGroupId) {
    qlAdvancedReceiveStatus('Сначала выберите группу.');
    return;
  }

  const sourceEl = document.getElementById('advancedReceiveSource');
  const amountEl = document.getElementById('advancedReceiveAmount');
  const moneyTypeEl = document.getElementById('advancedReceiveMoneyType');
  const noteEl = document.getElementById('advancedReceiveNote');
  const source = (sourceEl?.value || '').trim();
  const amount = (amountEl?.value || '').trim();
  const moneyType = moneyTypeEl?.value || 'cash';
  const note = (noteEl?.value || '').trim();

  if (!amount) {
    qlAdvancedReceiveStatus('Введите сумму.');
    return;
  }

  qlAdvancedReceiveStatus('Добавляю полученные средства...');

  const data = await qlApi('ledger_create', {
    group_id: Number(qlAdvanceGroupId),
    entry_type: 'income',
    money_type: moneyType,
    amount: amount,
    purpose: source || 'Полученные средства',
    note: note || 'Advanced: полученные средства администратора'
  });

  if (!data.ok) {
    qlAdvancedReceiveStatus('Ошибка прихода: ' + (data.error || 'unknown'));
    return;
  }

  if (sourceEl) sourceEl.value = '';
  if (amountEl) amountEl.value = '';
  if (noteEl) noteEl.value = '';

  qlAdvancedReceiveStatus('Приход добавлен. Баланс пересчитан.');
  await qlAdvancedRefreshMoneyState();
}

function qlAdvancedExcelUrl() {
  return '/api.php?action=ledger_group_excel&group_id=' + encodeURIComponent(String(qlAdvancedActiveGroupId()));
}

function qlAdvancedActiveGroupId() {
  const groupId = qlResolveActiveGroupId();
  if (groupId && !qlAdvanceGroupId) {
    qlAdvanceGroupId = groupId;
  }
  return groupId;
}

async function qlCopyTextToClipboard(text, html) {
  if (html && navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
    const item = new ClipboardItem({
      'text/html': new Blob([html], {type: 'text/html'}),
      'text/plain': new Blob([text], {type: 'text/plain'})
    });
    await navigator.clipboard.write([item]);
    return true;
  }

  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(text);
    return true;
  }

  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', 'readonly');
  area.style.position = 'fixed';
  area.style.left = '-9999px';
  area.style.top = '0';
  document.body.appendChild(area);
  area.focus();
  area.select();
  let ok = false;
  try {
    ok = document.execCommand('copy');
  } finally {
    document.body.removeChild(area);
  }
  return ok;
}

function qlAdvancedCloseExcelPreview() {
  const modal = document.getElementById('advancedExcelPreviewModal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.setAttribute('aria-hidden', 'true');
}

async function qlAdvancedPreviewExcel() {
  const groupId = qlAdvancedActiveGroupId();
  if (!groupId) {
    qlAdvanceStatus('Выберите группу для экспорта текущего периода.');
    return;
  }

  const modal = document.getElementById('advancedExcelPreviewModal');
  const content = document.getElementById('advancedExcelPreviewContent');
  const amount = document.getElementById('advancedExcelPreviewAmount');
  if (!modal || !content) return;

  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  content.innerHTML = '<p class="soft-note">Готовлю текущий период…</p>';
  if (amount) amount.textContent = '...';

  const payload = {group_id: groupId};
  const results = await Promise.all([
    qlApi('ledger_balance', payload),
    qlApi('ledger_report', Object.assign({period: 'today'}, payload)),
    qlApi('ledger_group_open_received_funds', payload)
  ]);
  const balanceData = results[0] || {};
  const reportData = results[1] || {};
  const openData = results[2] || {};

  if (!balanceData.ok || !reportData.ok) {
    content.innerHTML = '<p class="soft-note">Не удалось собрать предпросмотр текущего периода.</p>';
    if (amount) amount.textContent = '€0.00';
    return;
  }

  qlApplyOpenPeriodReportData(reportData, openData);

  const s = reportData.summary || {};
  const b = balanceData.summary || {};
  const sections = reportData.sections || [];
  const members = reportData.members || [];
  const adminCash = Number(b.available_cash_balance || 0);
  const employeeCash = Number(b.accountable_cash_left_open || 0);
  const physical = adminCash + employeeCash;
  if (amount) amount.textContent = qlCurrency(physical);
  const signed = function(value) {
    const n = Number(value || 0);
    return (n > 0 ? '+' : '') + qlCurrency(n);
  };
  const sectionRows = sections.length ? sections.map(function(section) {
    return `
      <tr>
        <td>${escapeHtml(section.name || 'Без раздела')}</td>
        <td>${escapeHtml(section.records || 0)}</td>
        <td class="money">${Number(section.income || 0) > 0.009 ? qlCurrency(section.income || 0) : ''}</td>
        <td class="money">${Number(section.expense || 0) > 0.009 ? qlCurrency(section.expense || 0) : ''}</td>
        <td class="money">${signed(section.balance || 0)}</td>
      </tr>
    `;
  }).join('') : '<tr><td colspan="5">Нет строк</td></tr>';
  const memberRows = members.length ? members.map(function(member) {
    return `
      <tr>
        <td>${escapeHtml(member.name || member.email || 'Участник')}</td>
        <td>${escapeHtml(member.records || 0)}</td>
        <td class="money">${Number(member.income || 0) > 0.009 ? qlCurrency(member.income || 0) : ''}</td>
        <td class="money">${Number(member.expense || 0) > 0.009 ? qlCurrency(member.expense || 0) : ''}</td>
        <td class="money">${qlCurrency(Number(member.income || 0) - Number(member.expense || 0))}</td>
      </tr>
    `;
  }).join('') : '<tr><td colspan="5">Нет строк</td></tr>';

  content.innerHTML = `
    <table class="advanced-excel-preview-table summary">
      <colgroup><col class="w-label"><col class="w-money"></colgroup>
      <thead><tr><th>Показатель</th><th>Сумма</th></tr></thead>
      <tbody>
        <tr><td>Приход</td><td class="money">${qlCurrency(s.income || 0)}</td></tr>
        <tr><td>Расход</td><td class="money">${qlCurrency(s.expense || 0)}</td></tr>
        <tr><td>У администратора</td><td class="money">${qlCurrency(adminCash)}</td></tr>
        <tr><td>У сотрудников</td><td class="money">${qlCurrency(employeeCash)}</td></tr>
        <tr><td>Физически всего</td><td class="money">${qlCurrency(physical)}</td></tr>
        <tr><td>Учетный баланс</td><td class="money">${qlCurrency(s.balance || 0)}</td></tr>
      </tbody>
    </table>
    <h4 class="advanced-excel-preview-title">Статьи текущего периода</h4>
    <table class="advanced-excel-preview-table articles">
      <colgroup><col class="w-title"><col class="w-small"><col class="w-money"><col class="w-money"><col class="w-money"></colgroup>
      <thead><tr><th>Статья</th><th>Стр.</th><th>Приход</th><th>Расход</th><th>Итог</th></tr></thead>
      <tbody>${sectionRows}</tbody>
    </table>
    <h4 class="advanced-excel-preview-title">Участники текущего периода</h4>
    <table class="advanced-excel-preview-table members">
      <colgroup><col class="w-title"><col class="w-small"><col class="w-money"><col class="w-money"><col class="w-money"></colgroup>
      <thead><tr><th>Участник</th><th>Стр.</th><th>Внес</th><th>Расход</th><th>Итог</th></tr></thead>
      <tbody>${memberRows}</tbody>
    </table>
  `;
}

function qlAdvancedDownloadExcel() {
  if (!qlAdvancedActiveGroupId()) return;
  qlCloseTransientPanels();
  window.location.href = qlAdvancedExcelUrl();
}

function qlAdvancedFinalizeStatus(message) {
  const el = document.getElementById('advancedFinalizeStatus');
  if (el) el.textContent = message || '';
}

async function qlAdvancedOpenGoogleSheet() {
  const groupId = qlAdvancedActiveGroupId();
  if (!groupId) {
    qlAdvanceStatus('Выберите группу для экспорта текущего периода.');
    return;
  }

  qlCloseTransientPanels();
  qlAdvancedCloseExcelPreview();
  qlAdvanceStatus('Готовлю текущий период для Google Sheets…');
  const data = await qlApi('ledger_group_google_sheet', {group_id: groupId});
  if (!data.ok || !data.tsv) {
    qlAdvanceStatus('Не удалось подготовить экспорт текущего периода: ' + (data.error || 'unknown'));
    return;
  }

  const copied = await qlCopyTextToClipboard(data.tsv, data.html || '');
  alert(copied
    ? 'Текущий период скопирован. В открывшейся Google Таблице нажмите Ctrl+V / Cmd+V, чтобы вставить цветную таблицу.'
    : 'Сейчас откроется Google Таблица. Браузер не дал скопировать текущий период автоматически, поэтому при необходимости скачайте Excel рядом.');
  window.open('https://docs.google.com/spreadsheets/u/0/create', '_blank', 'noopener');
  qlAdvanceStatus(copied
    ? 'Текущий период скопирован. В новой Google Таблице нажмите Ctrl+V / Cmd+V.'
    : 'Google Таблица открыта. Копирование не разрешено браузером, скачайте Excel текущего периода рядом.');
}

async function qlAdvancedFinalizeReport() {
  const groupId = qlAdvancedActiveGroupId();
  if (!groupId) {
    qlAdvancedFinalizeStatus('Выберите группу.');
    return;
  }

  const ok = confirm('Зафиксировать текущий отчет? Включенные живые карточки уйдут из рабочего пакета и живого журнала в архивную зону. Финансовые суммы останутся в отчете.');
  if (!ok) return;

  qlAdvancedFinalizeStatus('Фиксирую отчет…');
  const data = await qlApi('ledger_group_finalize_report', {group_id: groupId});
  if (!data.ok) {
    qlAdvancedFinalizeStatus('Не удалось зафиксировать отчет: ' + (data.error || 'unknown'));
    return;
  }

  const count = Number(data.finalized || 0);
  qlAdvancedFinalizeStatus(count
    ? 'Отчет зафиксирован. Карточек закрыто: ' + count + '.'
    : 'Нет включенных live-карточек для фиксации.');

  await qlAdvancedRefreshMoneyState();
  if (typeof window.qlLoadCaptainAdminDesk === 'function') await window.qlLoadCaptainAdminDesk();
  if (typeof window.qlLoadOtrReportCards === 'function') await window.qlLoadOtrReportCards();
  if (typeof qlLoadFinalReports === 'function') await qlLoadFinalReports();
}

async function qlAdvancedRefreshMoneyState() {
  await qlAdvancedLoadLedgerBalance();
  await qlAdvancedLoadReceivedFunds();
  await qlAdvancedLoadAudit();

  if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(qlAdvanceGroupId || '')) {
    await qlLoadLedger();
    if (typeof qlRunReport === 'function') qlRunReport();
  }

  if (typeof window.qlOtrSimpleLoad === 'function') {
    window.qlOtrSimpleLoad({force: false});
  }
  if (typeof window.qlLoadCaptainAdminDesk === 'function') {
    window.qlLoadCaptainAdminDesk();
  }
}

function qlAdvancedIsReceivedFund(entry) {
  if (!entry || entry.entry_type !== 'income') return false;

  const note = String(entry.note || '');
  if (note.indexOf('From On the Go') === 0) return false;
  if (note.indexOf('From advance #') === 0) return false;

  return true;
}

async function qlAdvancedLoadReceivedFunds() {
  const box = document.getElementById('advancedReceivedFundsList');
  if (!box) return;

  if (!qlAdvanceGroupId) {
    box.innerHTML = '<p class="soft-note">Выберите группу, чтобы увидеть внесенные средства.</p>';
    return;
  }

  const data = await qlApi('ledger_group_open_received_funds', {
    group_id: Number(qlAdvanceGroupId)
  });

  if (!data.ok) {
    box.innerHTML = '<p class="soft-note">Не удалось загрузить внесенные средства: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  qlAdvancedOpenPeriod = data.open_period || {};
  qlAdvancedOpenPeriod.finalized_at = data.finalized_at || null;

  const rows = [];
  if (data.carryover) rows.push(data.carryover);
  (data.entries || []).forEach(function(entry) {
    if (qlAdvancedIsReceivedFund(entry)) rows.push(entry);
  });
  const canEdit = !!(qlAdvanceScope && qlAdvanceScope.can_manage_money);

  if (!rows.length) {
    box.innerHTML = '<p class="soft-note">Внесенных средств пока нет.</p>';
    return;
  }

  box.innerHTML = rows.map(function(entry) {
    const isCarryover = !!entry.carryover;
    const purposeLabel = isCarryover ? 'Переходящий остаток из финального отчета' : (entry.purpose || 'Полученные средства');
    const actions = canEdit && !isCarryover ? `
      <div class="advanced-received-actions">
        <button class="ghost-btn" type="button" data-advanced-edit-income="${escapeHtml(entry.id)}">Изменить</button>
        <button class="ghost-btn danger-soft-btn" type="button" data-advanced-delete-income="${escapeHtml(entry.id)}">Удалить</button>
      </div>
    ` : '';

    return `
      <article class="advanced-received-row ${isCarryover ? 'is-carryover' : ''}" data-advanced-income-row="${escapeHtml(entry.id)}" data-advanced-income-money="${escapeHtml(entry.money_type || 'cash')}">
        <div>
          <b>${qlCurrency(entry.amount || 0)}</b>
          <span>${escapeHtml(purposeLabel)}</span>
          <small>${escapeHtml(entry.note || '')}${entry.entry_datetime ? ' · ' + escapeHtml(String(entry.entry_datetime).slice(0, 16)) : ''}</small>
        </div>
        ${actions}
      </article>
    `;
  }).join('');
}

async function qlAdvancedEditIncome(entryId) {
  const row = document.querySelector('[data-advanced-income-row="' + entryId + '"]');
  const currentAmount = row?.querySelector('b')?.textContent.replace(/[€\s]/g, '') || '';
  const currentPurpose = row?.querySelector('span')?.textContent || '';

  const amount = prompt('Сумма полученных средств', currentAmount);
  if (amount === null) return;

  const purpose = prompt('От кого / основание', currentPurpose);
  if (purpose === null) return;

  const note = prompt('Комментарий', row?.querySelector('small')?.textContent.split(' · ')[0] || '') || '';

  qlAdvancedReceiveStatus('Обновляю внесенные средства...');
  const data = await qlApi('ledger_update', {
    id: Number(entryId),
    entry_type: 'income',
    money_type: row?.getAttribute('data-advanced-income-money') || 'cash',
    amount,
    purpose: purpose || 'Полученные средства',
    note
  });

  if (!data.ok) {
    qlAdvancedReceiveStatus('Ошибка изменения: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvancedReceiveStatus('Внесенные средства обновлены.');
  await qlAdvancedRefreshMoneyState();
}

async function qlAdvancedDeleteIncome(entryId) {
  if (!confirm('Удалить эту строку полученных средств? Баланс и “Было/Стало” пересчитаются.')) {
    return;
  }

  qlAdvancedReceiveStatus('Удаляю внесенные средства...');
  const data = await qlApi('ledger_delete', {id: Number(entryId)});

  if (!data.ok) {
    qlAdvancedReceiveStatus('Ошибка удаления: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvancedReceiveStatus('Строка удалена. Баланс пересчитан.');
  await qlAdvancedRefreshMoneyState();
}

let qlCaptainCurrentSubmitting = false;

function qlCaptainSubmitStatus(message) {
  const captain = document.getElementById('captainStatus');
  const advanced = document.getElementById('advanceStatus');

  if (captain) captain.textContent = message || '';
  if (advanced) advanced.textContent = message || '';
}

function qlCaptainCanWriteGroup(group) {
  if (!group) return false;
  const access = String(group.access_level || '').toLowerCase();
  const role = String(group.role || '').toLowerCase();
  const permissions = group.permissions || {};

  return access === 'manager'
    || access === 'advanced'
    || role === 'admin'
    || !!permissions.can_write_group_ledger
    || !!permissions.can_manage_money;
}

function qlResolveCaptainSubmitGroupId(explicitGroupId) {
  const explicit = Number(explicitGroupId || 0);
  if (explicit > 0) return explicit;

  const groups = Array.isArray(qlGroups) ? qlGroups : [];
  const currentCaptain = Number(window.qlCaptainActiveGroupId || 0);
  if (currentCaptain > 0 && groups.some(function(group) { return String(group.id) === String(currentCaptain); })) {
    return currentCaptain;
  }

  if (qlAdvanceGroupId && groups.some(function(group) { return String(group.id) === String(qlAdvanceGroupId); })) {
    return Number(qlAdvanceGroupId);
  }

  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId && groups.some(function(group) { return String(group.id) === String(qlLedgerGroupId); })) {
    return Number(qlLedgerGroupId);
  }

  const writable = groups.find(qlCaptainCanWriteGroup);
  return writable && writable.id ? Number(writable.id) : 0;
}

async function qlSubmitCurrentOnTheGoToFinDesk(groupId, tapeId) {
  if (qlCaptainCurrentSubmitting) return;

  const resolvedGroupId = qlResolveCaptainSubmitGroupId(groupId);
  if (!resolvedGroupId) {
    qlCaptainSubmitStatus('Сначала выберите группу FinDesk.');
    return;
  }

  qlCaptainCurrentSubmitting = true;
  qlCaptainSubmitStatus('Ставлю маркер “сдал” на текущую карточку...');

  try {
    const payload = {group_id: resolvedGroupId};
    const resolvedTapeId = Number(tapeId || window.qlOtrActiveTapeId || qlOtrActiveTapeId || 0);
    if (resolvedTapeId > 0) payload.tape_id = resolvedTapeId;

    const data = await qlApi('on_the_go_card_submit', payload);
    if (!data.ok) {
      qlCaptainSubmitStatus('Не удалось сдать отчет: ' + (data.error || 'unknown'));
      return;
    }

    qlCaptainSubmitStatus('Карточка сдана в FinDesk на проверку.');

    if (typeof window.qlOtrSimpleLoad === 'function') {
      await window.qlOtrSimpleLoad({force: true});
    }
    if (typeof qlLoadOtrTapes === 'function') {
      await qlLoadOtrTapes();
    }
    if (typeof window.qlLoadOtrReportCards === 'function') {
      await window.qlLoadOtrReportCards();
    }
    if (typeof window.qlLoadCaptainAdminDesk === 'function') {
      await window.qlLoadCaptainAdminDesk();
    } else if (typeof window.qlLoadCaptainFin === 'function') {
      await window.qlLoadCaptainFin();
    }
    if (typeof qlLoadAdvances === 'function') {
      await qlLoadAdvances();
    }
    if (typeof qlLoadLedger === 'function') {
      await qlLoadLedger();
    }
    if (typeof qlRunReport === 'function') {
      await qlRunReport();
    }
  } finally {
    qlCaptainCurrentSubmitting = false;
  }
}

document.addEventListener('click', function(event) {
  const submitCurrent = event.target.closest('[data-captain-submit-current]');
  if (!submitCurrent) return;

  qlSubmitCurrentOnTheGoToFinDesk(
    submitCurrent.getAttribute('data-captain-submit-current'),
    submitCurrent.getAttribute('data-captain-submit-tape')
  );
});

window.qlSubmitCurrentOnTheGoToFinDesk = qlSubmitCurrentOnTheGoToFinDesk;

async function qlAdvancedLoadAudit() {
  const box = document.getElementById('advancedAuditPanel');
  if (!box) return;

  if (!qlAdvanceGroupId) {
    box.innerHTML = '<p class="soft-note">Выберите группу, чтобы увидеть аудит.</p>';
    return;
  }

  const data = await qlApi('audit_list', {
    group_id: Number(qlAdvanceGroupId),
    limit: 12
  });

  if (!data.ok) {
    box.innerHTML = '<p class="soft-note">Аудит недоступен: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const items = data.items || [];
  if (!items.length) {
    box.innerHTML = '<p class="soft-note">Пока нет действий по группе.</p>';
    return;
  }

  box.innerHTML = items.map(function(item) {
    const details = item.details || {};
    const extra = details.reason || details.access_level || details.status || details.title || '';
    return `
      <article class="advanced-audit-row">
        <span>
          <b>${escapeHtml(qlAdvancedAuditLabel(item.action))}</b>
          <small>${escapeHtml(item.user_display_name || item.email || 'Система')} · ${escapeHtml(item.created_at || '')}</small>
        </span>
        ${extra ? '<em>' + escapeHtml(String(extra)) + '</em>' : ''}
      </article>
    `;
  }).join('');
}

function qlAdvancedRenderAi(data) {
  const out = document.getElementById('advancedAiOutput');
  if (!out) return;

  if (!data || !data.ok) {
    out.innerHTML = '<p class="soft-note">AI-анализ недоступен: ' + escapeHtml(data && data.error ? data.error : 'unknown') + '</p>';
    return;
  }

  const risks = data.risks || [];
  const actions = data.action_items || [];
  const structure = data.report_structure || [];
  const position = data.summary && data.summary.position ? data.summary.position : null;

  if (position) {
    qlAdvancedLastPosition = position;
    qlAdvancedRenderPosition(position, data.period || null);
  }

  out.innerHTML = `
    ${position ? '<div class="advanced-ai-position"><span>Было <b>' + qlCurrency(position.before || 0) + '</b></span><span>Стало <b>' + qlCurrency(position.after || 0) + '</b></span></div>' : ''}
    <div class="advanced-ai-summary">${escapeHtml(data.executive_summary || '')}</div>
    <div class="advanced-ai-block">
      <b>Сигналы</b>
      ${risks.map(function(risk) {
        return '<span class="risk-' + escapeHtml(risk.level || 'low') + '">' + escapeHtml(risk.title || '') + '<small>' + escapeHtml(risk.detail || '') + '</small></span>';
      }).join('')}
    </div>
    <div class="advanced-ai-block">
      <b>Что сделать</b>
      ${(actions.length ? actions : ['Критичных действий нет.']).map(function(action) {
        return '<span>' + escapeHtml(action) + '</span>';
      }).join('')}
    </div>
    <div class="advanced-ai-block">
      <b>Структура отчета</b>
      ${structure.map(function(section) {
        return '<span><strong>' + escapeHtml(section.title || '') + '</strong><small>' + escapeHtml((section.items || []).join(' · ')) + '</small></span>';
      }).join('')}
    </div>
  `;
}

async function qlAdvancedRunAi() {
  const out = document.getElementById('advancedAiOutput');
  if (!qlAdvanceGroupId) {
    if (out) out.innerHTML = '<p class="soft-note">Сначала выберите группу.</p>';
    return;
  }

  if (out) out.innerHTML = '<p class="soft-note">AI-анализ собирает данные учетной записи...</p>';

  const data = await qlApi('ai_analysis_run', {
    group_id: Number(qlAdvanceGroupId),
    period: document.getElementById('advancedAiPeriod')?.value || 'month'
  });

  qlAdvancedRenderAi(data);
  qlAdvancedLoadAudit();
}

function qlAdvanceActionHtml(advance) {
  const status = advance.status || '';
  const s = advance.summary || {};
  const effectiveLeft = qlAdvanceEffectiveCashLeft(advance);
  const isAssigned = qlCurrentUser && String(advance.assigned_to_user_id) === String(qlCurrentUser.id);
  const canSubmit = isAssigned && ['issued', 'returned', 'discrepancy'].includes(status);
  const canModerate = !!(qlAdvanceScope && qlAdvanceScope.can_moderate && ['submitted', 'discrepancy'].includes(status));
  const canCancel = !!(qlAdvanceScope && qlAdvanceScope.can_manage_money && ['issued', 'submitted', 'returned', 'discrepancy'].includes(status));
  const canReturnCash = !!(
    qlAdvanceScope
    && qlAdvanceScope.can_manage_money
    && ['issued', 'returned'].includes(status)
    && Number(s.records_count || 0) === 0
    && effectiveLeft > 0
  );
  let html = '';

  if (canSubmit) {
    html += `
      <div class="advance-submit-row">
        <input class="ql-input" type="text" inputmode="decimal" placeholder="Фактический остаток" data-advance-actual="${escapeHtml(advance.id)}">
        <input class="ql-input" type="text" placeholder="Комментарий" data-advance-note="${escapeHtml(advance.id)}">
        <button class="primary-btn" type="button" data-advance-submit="${escapeHtml(advance.id)}">Сдать</button>
        <button class="ghost-btn" type="button" data-advance-open-tape="${escapeHtml(advance.on_the_go_tape_id || '')}">Открыть записи</button>
      </div>
    `;
  }

  if (canModerate) {
    html += `
      <div class="advance-moderate-row">
        <button class="primary-btn" type="button" data-advance-accept="${escapeHtml(advance.id)}">Принять</button>
        <button class="ghost-btn danger-soft-btn" type="button" data-advance-return="${escapeHtml(advance.id)}">Вернуть</button>
        ${canCancel ? '<button class="ghost-btn danger-soft-btn" type="button" data-advance-cancel="' + escapeHtml(advance.id) + '">Отменить выдачу</button>' : ''}
      </div>
    `;
  } else if (canCancel || canReturnCash) {
    html += `
      <div class="advance-moderate-row">
        ${canReturnCash ? '<button class="ghost-btn" type="button" data-advance-return-cash="' + escapeHtml(advance.id) + '">В кассу</button>' : ''}
        ${canCancel ? '<button class="ghost-btn danger-soft-btn" type="button" data-advance-cancel="' + escapeHtml(advance.id) + '">Отменить выдачу</button>' : ''}
      </div>
    `;
  }

  return html;
}

function qlAdvanceField(attr, id) {
  return Array.from(document.querySelectorAll('[' + attr + ']')).find(function(el) {
    return String(el.getAttribute(attr)) === String(id);
  }) || null;
}

function qlAdvanceRenderList() {
  const list = document.getElementById('advanceList');
  const count = document.getElementById('advanceCount');

  qlAdvanceRenderSummary();
  qlAdvanceRenderIssuePanel();

  if (!list) return;

  if (!qlAdvanceGroupId) {
    list.innerHTML = '<p class="soft-note">Выберите группу, чтобы увидеть деньги под отчет.</p>';
    return;
  }

  if (!qlAdvances.length) {
    list.innerHTML = '<p class="soft-note">В этой группе пока нет денег под отчет.</p>';
    return;
  }

  const finalizedAt = qlAdvancedOpenPeriod && qlAdvancedOpenPeriod.finalized_at ? String(qlAdvancedOpenPeriod.finalized_at) : '';
  const visibleAdvances = finalizedAt
    ? qlAdvances.filter(function(advance) {
      return (advance.status || '') !== 'accepted' || String(advance.accepted_at || '') > finalizedAt;
    })
    : qlAdvances;

  if (count) count.textContent = visibleAdvances.length + ' строк';

  if (!visibleAdvances.length) {
    list.innerHTML = '<p class="soft-note">В открытом периоде пока нет подотчетов.</p>';
    return;
  }

  list.innerHTML = visibleAdvances.map(function(advance) {
    const s = advance.summary || {};
    const status = advance.status || 'issued';
    const employee = advance.assigned_to_display_name || advance.assigned_to_email || 'Сотрудник';
    const diff = Number(advance.difference_amount || 0);
    const effectiveLeft = qlAdvanceEffectiveCashLeft(advance);
    const differenceHtml = advance.actual_remaining !== null && advance.actual_remaining !== undefined
      ? `<div><span>Расчет</span><b>${qlCurrency(s.cash_left || 0)}</b></div><div class="${Math.abs(diff) > 0.009 ? 'metric-alert' : ''}"><span>Разница</span><b>${qlCurrency(diff)}</b></div>`
      : '';

    return `
      <article class="advance-row status-${escapeHtml(status)}">
        <div class="advance-row-top">
          <div>
            <div class="advance-status-line">
              <span>${escapeHtml(qlAdvanceStatusLabel(status))}</span>
              <small>${escapeHtml(advance.created_at || '')}</small>
            </div>
            <h3>${escapeHtml(advance.title || 'Pocket advance')}</h3>
            <p>${escapeHtml(employee)} · ${escapeHtml(advance.assigned_to_email || '')}</p>
          </div>
          <strong>${qlCurrency(advance.amount || 0)}</strong>
        </div>

        <div class="advance-metrics">
          <div><span>Наличные</span><b>${qlCurrency(s.cash_out || 0)}</b></div>
          <div><span>Безнал</span><b>${qlCurrency(s.card_out || 0)}</b></div>
          <div><span>Остаток</span><b>${qlCurrency(effectiveLeft)}</b></div>
          <div><span>Записи</span><b>${Number(s.records_count || 0)}</b></div>
          ${differenceHtml}
        </div>

        ${advance.submitted_note ? '<p class="advance-note">' + escapeHtml(advance.submitted_note) + '</p>' : ''}
        ${advance.moderation_note ? '<p class="advance-note moderator">' + escapeHtml(advance.moderation_note) + '</p>' : ''}
        ${qlAdvanceActionHtml(advance)}
      </article>
    `;
  }).join('');
}

async function qlLoadAdvances() {
  const module = document.getElementById('moduleMoney');
  if (!module) return;

  qlAdvanceRefreshGroupSelect();

  if (!qlAdvanceGroupId) {
    qlAdvances = [];
    qlAdvanceScope = {};
    qlAdvanceTotals = {};
    qlAdvanceRenderList();
    qlAdvancedRenderPanels(null);
    qlAdvancedLoadAudit();
    qlAdvanceStatus((qlGroups || []).length ? 'Выберите группу.' : 'Создайте группу или войдите по приглашению.');
    return;
  }

  qlAdvanceStatus('Загружаю деньги под отчет...');

  const data = await qlApi('advance_list', {
    group_id: Number(qlAdvanceGroupId),
    limit: 150
  });

  if (!data.ok) {
    qlAdvances = [];
    qlAdvanceScope = {};
    qlAdvanceTotals = {};
    qlAdvanceRenderList();
    qlAdvancedRenderPanels(null);
    qlAdvancedLoadAudit();
    qlAdvanceStatus('Ошибка подотчета: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvances = data.advances || [];
  qlAdvanceScope = data.scope || {};
  qlAdvanceTotals = data.totals || {};
  qlAdvanceStatus(qlAdvanceScope.can_manage_money ? 'Advanced-управление деньгами.' : (qlAdvanceScope.can_moderate ? 'Режим модерации.' : 'Ваши выданные деньги.'));

  qlAdvanceRenderList();
  await qlAdvanceLoadMembers();
  qlAdvanceRenderIssuePanel();
  qlAdvancedRenderPanels(null);
  await qlAdvancedLoadLedgerBalance();
  await qlAdvancedLoadReceivedFunds();
  qlAdvanceRenderList();
  await qlAdvancedLoadAudit();
}

async function qlAdvanceCreate() {
  const memberId = document.getElementById('advanceMemberSelect')?.value || '';
  const title = (document.getElementById('advanceTitle')?.value || '').trim();
  const amount = (document.getElementById('advanceAmount')?.value || '').trim();

  if (!qlAdvanceGroupId) {
    qlAdvanceStatus('Сначала выберите группу.');
    return;
  }
  if (!memberId || !amount) {
    qlAdvanceStatus('Выберите сотрудника и сумму.');
    return;
  }

  qlAdvanceStatus('Выдаю деньги под отчет...');

  const data = await qlApi('advance_create', {
    group_id: Number(qlAdvanceGroupId),
    assigned_to_user_id: Number(memberId),
    title: title || 'Деньги под отчет',
    amount: amount,
    currency: 'EUR'
  });

  if (!data.ok) {
    qlAdvanceStatus('Ошибка выдачи: ' + (data.error || 'unknown'));
    return;
  }

  const titleEl = document.getElementById('advanceTitle');
  const amountEl = document.getElementById('advanceAmount');
  if (titleEl) titleEl.value = '';
  if (amountEl) amountEl.value = '';

  qlAdvanceStatus('Деньги выданы.');
  await qlLoadAdvances();
}

async function qlAdvanceSubmit(id) {
  const actual = (qlAdvanceField('data-advance-actual', id)?.value || '').trim();
  const note = (qlAdvanceField('data-advance-note', id)?.value || '').trim();

  if (!actual) {
    qlAdvanceStatus('Введите фактический остаток.');
    return;
  }

  qlAdvanceStatus('Отправляю отчет...');

  const data = await qlApi('advance_submit', {
    id: Number(id),
    actual_remaining: actual,
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Ошибка сдачи: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus(data.advance && data.advance.status === 'discrepancy' ? 'Сдано с расхождением.' : 'Сдано на проверку.');
  await qlLoadAdvances();
  if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
}

async function qlAdvanceAccept(id) {
  const note = prompt('Комментарий модерации', '') || '';
  qlAdvanceStatus('Принимаю отчет...');

  const data = await qlApi('advance_accept', {
    id: Number(id),
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Ошибка принятия: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus('Принято. Расходы добавлены в групповой учет.');
  await qlLoadAdvances();

  if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(qlAdvanceGroupId || '')) {
    qlLoadLedger();
  }
}

async function qlAdvanceReturn(id) {
  const note = prompt('Причина возврата', '') || '';
  qlAdvanceStatus('Возвращаю отчет...');

  const data = await qlApi('advance_return', {
    id: Number(id),
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Ошибка возврата: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus('Возвращено на исправление.');
  await qlLoadAdvances();
}

async function qlAdvanceCancel(id) {
  const reason = prompt('Причина отмены выдачи', '');
  if (reason === null) return;

  const cleanReason = reason.trim();
  if (!cleanReason) {
    qlAdvanceStatus('Нужна причина отмены.');
    return;
  }

  qlAdvanceStatus('Отменяю ошибочную выдачу...');

  const data = await qlApi('advance_cancel', {
    id: Number(id),
    reason: cleanReason
  });

  if (!data.ok) {
    qlAdvanceStatus('Ошибка отмены: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus('Выдача отменена и убрана из текущих отчетов.');
  await qlLoadAdvances();
  if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
  if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo();
}

async function qlAdvanceReturnCash(id) {
  if (!confirm('Вернуть этот остаток в кассу? Расход или доход не создается.')) return;

  qlAdvanceStatus('Возвращаю остаток в кассу...');

  const data = await qlApi('advance_return_cash', {
    id: Number(id),
    note: 'Остаток возвращен в кассу'
  });

  if (!data.ok) {
    const message = data.error === 'advance_has_records'
      ? 'В этом остатке есть записи. Сначала сдайте или исправьте отчет.'
      : (data.error || 'unknown');
    qlAdvanceStatus('Ошибка возврата остатка: ' + message);
    return;
  }

  qlAdvanceStatus('Остаток вернулся в доступную кассу.');
  await qlLoadAdvances();
  if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(qlAdvanceGroupId || '')) {
    qlLoadLedger();
  }
  if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
  if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo();
}

function qlAdvanceOpenTape(tapeId) {
  if (!tapeId) return;

  qlOtrActiveTapeId = Number(tapeId);
  window.qlOtrActiveTapeId = Number(tapeId);
  window.qlOtrPinnedTapeId = Number(tapeId);
  qlSetModule('ontherun');

  setTimeout(function() {
    if (typeof window.qlShowOtrSimpleEditor === 'function') window.qlShowOtrSimpleEditor();
    if (typeof window.qlOtrSimpleLoad === 'function') {
      window.qlOtrSimpleLoad({force: true, tape_id: Number(tapeId), viewOnly: false});
    }
    if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
    if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo();
  }, 120);
}

document.addEventListener('change', function(event) {
  const group = event.target.closest('#advanceGroupSelect');
  if (!group) return;

  qlAdvanceGroupId = group.value ? Number(group.value) : null;
  qlLoadAdvances();
});

document.addEventListener('click', function(event) {
  const create = event.target.closest('#advanceCreateBtn');
  const receive = event.target.closest('#advancedReceiveCreateBtn');
  const exportExcel = event.target.closest('#advancedExcelExportBtn');
  const downloadExcel = event.target.closest('#advancedExcelDownloadBtn');
  const googleSheet = event.target.closest('#advancedGoogleSheetBtn');
  const finalizeReport = event.target.closest('#advancedFinalizeReportBtn');
  const closeExcelPreview = event.target.closest('[data-close-advanced-excel-preview]');
  const ai = event.target.closest('#advancedAiRunBtn');
  const screen = event.target.closest('[data-advanced-screen]');
  const submit = event.target.closest('[data-advance-submit]');
  const accept = event.target.closest('[data-advance-accept]');
  const ret = event.target.closest('[data-advance-return]');
  const returnCash = event.target.closest('[data-advance-return-cash]');
  const cancel = event.target.closest('[data-advance-cancel]');
  const tape = event.target.closest('[data-advance-open-tape]');
  const editIncome = event.target.closest('[data-advanced-edit-income]');
  const deleteIncome = event.target.closest('[data-advanced-delete-income]');

  if (create) qlAdvanceCreate();
  if (receive) qlAdvancedReceiveFunds();
  if (exportExcel) qlAdvancedPreviewExcel();
  if (downloadExcel) qlAdvancedDownloadExcel();
  if (googleSheet) qlAdvancedOpenGoogleSheet();
  if (finalizeReport) qlAdvancedFinalizeReport();
  if (closeExcelPreview) qlAdvancedCloseExcelPreview();
  if (ai) qlAdvancedRunAi();
  if (screen) qlAdvancedSetScreen(screen.getAttribute('data-advanced-screen'), {history: 'push'});
  if (submit) qlAdvanceSubmit(submit.getAttribute('data-advance-submit'));
  if (accept) qlAdvanceAccept(accept.getAttribute('data-advance-accept'));
  if (ret) qlAdvanceReturn(ret.getAttribute('data-advance-return'));
  if (returnCash) qlAdvanceReturnCash(returnCash.getAttribute('data-advance-return-cash'));
  if (cancel) qlAdvanceCancel(cancel.getAttribute('data-advance-cancel'));
  if (tape) qlAdvanceOpenTape(tape.getAttribute('data-advance-open-tape'));
  if (editIncome) qlAdvancedEditIncome(editIncome.getAttribute('data-advanced-edit-income'));
  if (deleteIncome) qlAdvancedDeleteIncome(deleteIncome.getAttribute('data-advanced-delete-income'));
});

const qlAdvancePreviousLoadGroups = qlLoadGroups;
qlLoadGroups = async function() {
  await qlAdvancePreviousLoadGroups();
  qlAdvanceRefreshGroupSelect();

  const module = document.getElementById('moduleMoney');
  if (module && !module.classList.contains('hidden')) {
    qlLoadAdvances();
  }
};

const qlAdvancePreviousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);
window.qlSetModule = function(moduleName, options) {
  if (typeof qlAdvancePreviousSetModule === 'function') {
    qlAdvancePreviousSetModule(moduleName, options);
  }

  if (moduleName === 'money') {
    setTimeout(function() {
      qlAdvanceRefreshGroupSelect();
      qlLoadAdvances();
    }, 80);
  }
};

try {
  qlSetModule = window.qlSetModule;
} catch (error) {}

/* === FinDesk On The Go OTR-1 20260503-25 === */
let qlOtrItems = [];
function qlOtrCurrency(value) {
  const n = Number(value || 0);
  return '€' + n.toFixed(2);
}

function qlOtrTypeLabel(type) {
  if (type === 'cash_in') return 'Cash received';
  if (type === 'cash_out') return 'Cash spent';
  if (type === 'noncash_out') return 'Card / non-cash spent';
  return 'On the Go';
}

function qlOtrIds(type) {
  if (type === 'cash_in') return { amount: 'otrCashInAmount', desc: 'otrCashInDesc', file: 'otrCashInFile', fileName: 'otrCashInFileName' };
  if (type === 'cash_out') return { amount: 'otrCashOutAmount', desc: 'otrCashOutDesc', file: 'otrCashOutFile', fileName: 'otrCashOutFileName' };
  return { amount: 'otrNoncashOutAmount', desc: 'otrNoncashOutDesc', file: 'otrNoncashOutFile', fileName: 'otrNoncashOutFileName' };
}

function qlOtrMessage(text) {
  const el = document.getElementById('otrMessage');
  if (el) el.textContent = text || '';
}

async function qlLoadOnTheGo() {
  const data = await qlApi('on_the_go_list', { limit: 100 });
  const journal = document.getElementById('otrJournal');
  if (!journal) return;

  if (!data.ok) {
    journal.innerHTML = '<p class="soft-note">On the go error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const s = data.summary || {};
  const cashIn = document.getElementById('otrCashIn');
  const cashOut = document.getElementById('otrCashOut');
  const expected = document.getElementById('otrExpectedCash');
  const count = document.getElementById('otrCount');

  if (cashIn) cashIn.textContent = qlOtrCurrency(s.cash_in || 0);
  if (cashOut) cashOut.textContent = qlOtrCurrency(s.cash_out || 0);
  if (expected) expected.textContent = qlOtrCurrency(s.expected_cash || 0);
  if (count) count.textContent = (s.needs_review_count || 0) + ' to review';

  const items = data.items || [];
  qlOtrItems = items;
  if (!items.length) {
    journal.innerHTML = '<p class="soft-note">Пока нет записей на разбор.</p>';
    return;
  }

  journal.innerHTML = items.map(function(item) {
    const amount = item.amount === null || item.amount === undefined ? 'Amount not set' : qlOtrCurrency(item.amount);
    const desc = item.description ? escapeHtml(item.description) : 'No note';
    const attach = Number(item.files_count || 0) > 0 ? '<span class="otr-attach">📎 вложение</span>' : '';
    return `
      <div class="otr-row">
        <div class="otr-row-top">
          <div>
            <b>${escapeHtml(qlOtrTypeLabel(item.capture_type))} · ${amount}</b>
            <small>${desc} · ${escapeHtml(item.created_at || '')}</small>
            ${attach}
          </div>
          <div class="otr-row-actions">
            <span class="otr-review-badge">To review</span>
            <button class="ghost-btn otr-review-btn" type="button" data-otr-review="${escapeHtml(item.id)}">Review</button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

async function qlUploadOnTheGoFile(captureId, fileInput, options) {
  const opts = options || {};
  const uploadFile = opts.file || (fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);
  if (!uploadFile) return { ok: true };

  const form = new FormData();
  form.append('capture_id', String(captureId));
  form.append('file', uploadFile);
  if (opts.client_upload_id) form.append('client_upload_id', String(opts.client_upload_id));
  if (opts.client_draft_id) form.append('client_draft_id', String(opts.client_draft_id));
  if (opts.draft_id) form.append('draft_id', String(opts.draft_id));
  if (opts.proof_role) form.append('proof_role', String(opts.proof_role));
  if (opts.proof_bundle_id) form.append('proof_bundle_id', String(opts.proof_bundle_id));
  if (opts.source_file_id) form.append('source_file_id', String(opts.source_file_id));
  if (opts.metadata_json) form.append('metadata_json', String(opts.metadata_json));

  const response = await fetch('/api.php?action=on_the_go_upload_file', {
    method: 'POST',
    credentials: 'same-origin',
    body: form
  });

  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    return { ok: false, error: 'bad_json', message: text.slice(0, 200) };
  }
}

async function qlSaveOnTheGo(type) {
  const ids = qlOtrIds(type);
  const amountEl = document.getElementById(ids.amount);
  const descEl = document.getElementById(ids.desc);
  const fileEl = document.getElementById(ids.file);

  const amount = amountEl ? amountEl.value.trim() : '';
  const description = descEl ? descEl.value.trim() : '';
  const hasFile = fileEl && fileEl.files && fileEl.files.length;

  if (!amount && !description && !hasFile) {
    qlOtrMessage('Add an amount, note or attachment.');
    return;
  }

  qlOtrMessage('Saving for review…');

  const data = await qlApi('on_the_go_create', {
    capture_type: type,
    amount: amount,
    description: description,
    currency: 'EUR'
  });

  if (!data.ok) {
    qlOtrMessage('Error: ' + (data.error || 'unknown'));
    return;
  }

  if (hasFile) {
    const upload = await qlUploadOnTheGoFile(data.capture.id, fileEl);
    if (!upload.ok) {
      qlOtrMessage('Record saved, but attachment failed: ' + (upload.error || 'unknown'));
      await qlLoadOnTheGo();
      return;
    }
  }

  if (amountEl) amountEl.value = '';
  if (descEl) descEl.value = '';
  if (fileEl) fileEl.value = '';
  const fileName = document.getElementById(ids.fileName);
  if (fileName) fileName.textContent = 'No attachment';

  qlOtrMessage('Saved. Marked for review and not included in reports.');
  await qlLoadOnTheGo();
}

document.addEventListener('click', function(event) {
  const btn = event.target.closest('[data-otr-save]');
  if (!btn) return;
  qlSaveOnTheGo(btn.getAttribute('data-otr-save'));
});

function qlCloseOtrReviewModal() {
  const modal = document.getElementById('otrReviewModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }
}

function qlOtrFindItem(id) {
  return (qlOtrItems || []).find(function(item) {
    return String(item.id) === String(id);
  });
}

async function qlLoadOtrReviewFiles(captureId) {
  const box = document.getElementById('otrReviewFiles');
  const attach = document.getElementById('otrReviewAttachment');

  if (!box || !captureId) return;

  box.innerHTML = '<p class="soft-note">Загружаю вложения…</p>';

  const data = await qlApi('on_the_go_file_list', { capture_id: Number(captureId) });

  if (!data.ok) {
    box.innerHTML = '<p class="soft-note">Ошибка вложений: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const files = data.files || [];

  if (attach) {
    attach.textContent = files.length ? (files.length + ' вложений') : 'Вложений нет';
  }

  if (!files.length) {
    box.innerHTML = '<p class="soft-note">Вложений нет.</p>';
    return;
  }

	  box.innerHTML = files.map(function(file) {
	    const size = Number(file.size_bytes || 0);
	    const kb = size ? Math.max(1, Math.round(size / 1024)) + ' KB' : '';
	    const role = qlProofRoleLabel(file.proof_role);
	    return `
	      <div class="otr-file-row">
	        <div>
	          <b>${escapeHtml(file.original_name || 'Вложение')}</b>
	          <small>${escapeHtml([role, kb, file.created_at || ''].filter(Boolean).join(' · '))}</small>
	        </div>
        <div class="otr-file-actions">
          <a class="ghost-btn otr-file-open" href="${escapeHtml(file.download_url || '#')}" target="_blank" rel="noopener">Открыть</a>
          <button class="ghost-btn otr-file-delete danger-soft" type="button" data-otr-file-delete="${escapeHtml(file.id)}">Удалить</button>
        </div>
      </div>
    `;
  }).join('');
}

async function qlUploadOtrReviewFile() {
  const captureId = document.getElementById('otrReviewId')?.value || '';
  const fileInput = document.getElementById('otrReviewFileInput');
  const status = document.getElementById('otrReviewStatus');

  if (!captureId) return;

  if (!fileInput || !fileInput.files || !fileInput.files[0]) {
    if (status) status.textContent = 'Сначала выберите файл.';
    return;
  }

  if (status) status.textContent = 'Загружаю вложение…';

  const upload = await qlUploadOnTheGoFile(captureId, fileInput);

  if (!upload.ok) {
    if (status) status.textContent = 'Ошибка загрузки: ' + (upload.error || 'unknown');
    return;
  }

  fileInput.value = '';
  const name = document.getElementById('otrReviewFileName');
  if (name) name.textContent = 'Файл не выбран';

  if (status) status.textContent = 'Вложение загружено.';
  await qlLoadOtrReviewFiles(captureId);
  await qlLoadOnTheGo();
}

async function qlDeleteOtrReviewFile(fileId) {
  const captureId = document.getElementById('otrReviewId')?.value || '';
  const status = document.getElementById('otrReviewStatus');

  if (!fileId) return;

  if (!confirm('Удалить это вложение из строки на проверке?')) {
    return;
  }

  if (status) status.textContent = 'Удаляю вложение…';

  const data = await qlApi('on_the_go_file_delete', { id: Number(fileId) });

  if (!data.ok) {
    if (status) status.textContent = 'Ошибка удаления: ' + (data.error || 'unknown');
    return;
  }

  if (status) status.textContent = 'Вложение удалено.';
  await qlLoadOtrReviewFiles(captureId);
  await qlLoadOnTheGo();
}

function qlOpenOtrReview(id) {
  const item = qlOtrFindItem(id);
  const modal = document.getElementById('otrReviewModal');

  if (!item || !modal) {
    qlOtrMessage('Не удалось открыть строку. Обновите страницу и попробуйте снова.');
    return;
  }

  const idInput = document.getElementById('otrReviewId');
  const typeInput = document.getElementById('otrReviewType');
  const amountInput = document.getElementById('otrReviewAmount');
  const descInput = document.getElementById('otrReviewDescription');
  const attach = document.getElementById('otrReviewAttachment');
  const status = document.getElementById('otrReviewStatus');

  if (idInput) idInput.value = item.id || '';
  if (typeInput) typeInput.value = item.capture_type || 'cash_out';
  if (amountInput) amountInput.value = item.amount === null || item.amount === undefined ? '' : String(item.amount);
  if (descInput) descInput.value = item.description || '';
  if (attach) attach.textContent = Number(item.files_count || 0) > 0 ? 'Вложения есть' : 'Вложений нет';
  qlLoadOtrReviewFiles(item.id);
  if (status) status.textContent = '';

  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
}

async function qlSaveOtrReviewChanges() {
  const id = document.getElementById('otrReviewId')?.value || '';
  const type = document.getElementById('otrReviewType')?.value || 'cash_out';
  const amount = document.getElementById('otrReviewAmount')?.value || '';
  const description = document.getElementById('otrReviewDescription')?.value || '';
  const status = document.getElementById('otrReviewStatus');

  if (!id) return;
  if (!amount.trim() && !description.trim()) {
    if (status) status.textContent = 'Добавьте сумму или примечание перед сохранением.';
    return;
  }

  if (status) status.textContent = 'Сохраняю…';

  const data = await qlApi('on_the_go_update', {
    id: Number(id),
    capture_type: type,
    amount: amount,
    description: description
  });

  if (!data.ok) {
    if (status) status.textContent = 'Ошибка: ' + (data.error || 'unknown');
    return;
  }

  if (status) status.textContent = 'Сохранено. В учет еще не включено.';
  await qlLoadOnTheGo();
}

async function qlArchiveOtrRecord() {
  const id = document.getElementById('otrReviewId')?.value || '';
  const status = document.getElementById('otrReviewStatus');
  if (!id) return;

  if (!confirm('Убрать эту строку живого отчета в архив? Она исчезнет из журнала проверки.')) {
    return;
  }

  if (status) status.textContent = 'Убираю в архив…';

  const data = await qlApi('on_the_go_archive', { id: Number(id) });

  if (!data.ok) {
    if (status) status.textContent = 'Ошибка: ' + (data.error || 'unknown');
    return;
  }

  qlCloseOtrReviewModal();
  qlOtrMessage('Строка на проверке убрана в архив.');
  await qlLoadOnTheGo();
}

document.addEventListener('click', function(event) {
  const btn = event.target.closest('[data-otr-review]');
  if (!btn) return;
  qlOpenOtrReview(btn.getAttribute('data-otr-review'));
});

document.addEventListener('click', function(event) {
  if (event.target.closest('[data-close-otr-review]')) {
    qlCloseOtrReviewModal();
  }

  if (event.target.closest('#saveOtrReviewBtn')) {
    qlSaveOtrReviewChanges();
  }

  if (event.target.closest('#archiveOtrBtn')) {
    qlArchiveOtrRecord();
  }

  if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'advancedExcelPreviewModal') {
    qlAdvancedCloseExcelPreview();
  }

  if (event.target.closest('#uploadOtrReviewFileBtn')) {
    qlUploadOtrReviewFile();
  }

  const deleteFileBtn = event.target.closest('[data-otr-file-delete]');
  if (deleteFileBtn) {
    qlDeleteOtrReviewFile(deleteFileBtn.getAttribute('data-otr-file-delete'));
  }
});

document.addEventListener('change', function(event) {
  const input = event.target;
  if (!input || !input.id) return;

  const map = {
    otrCashInFile: 'otrCashInFileName',
    otrCashOutFile: 'otrCashOutFileName',
    otrNoncashOutFile: 'otrNoncashOutFileName'
  };

  if (input.id === 'otrReviewFileInput') {
    const label = document.getElementById('otrReviewFileName');
    if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : 'Файл не выбран';
    return;
  }

  if (!map[input.id]) return;

  const label = document.getElementById(map[input.id]);
  if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : 'Вложений нет';
});

const qlPreviousRenderUserForOnTheGo = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForOnTheGo(user);
  if (user) {
    setTimeout(qlLoadOnTheGo, 80);
  }
};

const qlPreviousSetModuleForOnTheGo = window.qlSetModule || qlSetModule;
qlSetModule = function(moduleName, options) {
  qlPreviousSetModuleForOnTheGo(moduleName, options);
  if (moduleName === 'ontherun') {
    setTimeout(qlLoadOnTheGo, 40);
  }
};
window.qlSetModule = qlSetModule;

/* === FinDesk On The Go Convert To Ledger OTR-2C 20260503-29 === */
let qlOtrConvertScope = 'personal';

function qlOtrDefaultEntryType(captureType) {
  return captureType === 'cash_in' ? 'income' : 'expense';
}

function qlOtrDefaultMoneyType(captureType) {
  return captureType === 'noncash_out' ? 'noncash' : 'cash';
}

function qlOtrPopulateConvertGroups() {
  const select = document.getElementById('otrConvertGroup');
  if (!select) return;

  const groups = Array.isArray(qlGroups) ? qlGroups : [];
  select.innerHTML = '<option value="">Выберите группу</option>' + groups.map(function(group) {
    return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(group.role) + '</option>';
  }).join('');
}

async function qlOtrLoadConvertSections() {
  const sectionSelect = document.getElementById('otrConvertSection');
  const entryType = document.getElementById('otrConvertEntryType')?.value || 'expense';
  const groupSelect = document.getElementById('otrConvertGroup');
  const groupId = qlOtrConvertScope === 'group' && groupSelect && groupSelect.value ? Number(groupSelect.value) : 0;

  if (!sectionSelect) return;

  sectionSelect.innerHTML = '<option value="">Живой отчет по умолчанию</option>';

  const payload = { category_type: entryType };
  if (groupId) payload.group_id = groupId;

  const data = await qlApi('category_list', payload);

  if (!data.ok) return;

  const categories = data.categories || [];
  sectionSelect.innerHTML = '<option value="">Живой отчет по умолчанию</option>' + categories.map(function(cat) {
    return '<option value="' + escapeHtml(cat.id) + '">' + escapeHtml(cat.name) + '</option>';
  }).join('');
}

function qlOtrSetConvertScope(scope) {
  qlOtrConvertScope = scope === 'group' ? 'group' : 'personal';

  document.querySelectorAll('[data-otr-convert-scope]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-otr-convert-scope') === qlOtrConvertScope);
  });

  const groupLabel = document.getElementById('otrConvertGroupLabel');
  const groupSelect = document.getElementById('otrConvertGroup');

  if (groupLabel) groupLabel.classList.toggle('hidden', qlOtrConvertScope !== 'group');
  if (groupSelect) groupSelect.classList.toggle('hidden', qlOtrConvertScope !== 'group');

  qlOtrPopulateConvertGroups();
  qlOtrLoadConvertSections();
}

function qlOtrPrepareConvertPanel(item) {
  if (!item) return;

  const entryType = qlOtrDefaultEntryType(item.capture_type);
  const moneyType = qlOtrDefaultMoneyType(item.capture_type);

  const entryTypeEl = document.getElementById('otrConvertEntryType');
  const moneyTypeEl = document.getElementById('otrConvertMoneyType');
  const purposeEl = document.getElementById('otrConvertPurpose');

  if (entryTypeEl) entryTypeEl.value = entryType;
  if (moneyTypeEl) moneyTypeEl.value = moneyType;
  if (purposeEl) purposeEl.value = item.description || 'Строка живого отчета';

  qlOtrSetConvertScope('personal');
  qlOtrLoadConvertSections();
}

async function qlOtrConvertToLedger() {
  const id = document.getElementById('otrReviewId')?.value || '';
  const amount = document.getElementById('otrReviewAmount')?.value || '';
  const description = document.getElementById('otrReviewDescription')?.value || '';
  const entryType = document.getElementById('otrConvertEntryType')?.value || 'expense';
  const moneyType = document.getElementById('otrConvertMoneyType')?.value || 'cash';
  const purpose = document.getElementById('otrConvertPurpose')?.value || description || 'Строка живого отчета';
  const sectionId = document.getElementById('otrConvertSection')?.value || '';
  const groupId = qlOtrConvertScope === 'group' ? (document.getElementById('otrConvertGroup')?.value || '') : '';
  const status = document.getElementById('otrReviewStatus');

  if (!id) return;

  if (!amount.trim()) {
    if (status) status.textContent = 'Для переноса в журнал нужна сумма.';
    return;
  }

  if (qlOtrConvertScope === 'group' && !groupId) {
    if (status) status.textContent = 'Выберите группу или переключитесь на личный журнал.';
    return;
  }

  if (!confirm('Перенести эту строку живого отчета в обычную запись журнала? Она уйдет из списка проверки.')) {
    return;
  }

  if (status) status.textContent = 'Переношу в журнал…';

  const payload = {
    id: Number(id),
    entry_type: entryType,
    money_type: moneyType,
    amount: amount,
    purpose: purpose,
    note: description
  };

  if (sectionId) payload.category_id = Number(sectionId);
  if (groupId) payload.group_id = Number(groupId);

  const data = await qlApi('on_the_go_convert_to_ledger', payload);

  if (!data.ok) {
    if (status) status.textContent = 'Ошибка переноса: ' + (data.error || 'unknown') + (data.message ? ' · ' + data.message : '');
    return;
  }

  qlCloseOtrReviewModal();
  qlOtrMessage('Перенесено в журнал. Вложений скопировано: ' + (data.copied_files || 0) + '.');

  await qlLoadOnTheGo();
  await qlLoadLedger();
  await qlRunReport();
  await qlLoadCategories();
}

document.addEventListener('click', function(event) {
  const scopeBtn = event.target.closest('[data-otr-convert-scope]');
  if (scopeBtn) {
    qlOtrSetConvertScope(scopeBtn.getAttribute('data-otr-convert-scope') || 'personal');
  }

  if (event.target.closest('#convertOtrToLedgerBtn')) {
    qlOtrConvertToLedger();
  }
});

document.addEventListener('change', function(event) {
  if (event.target && (event.target.id === 'otrConvertEntryType' || event.target.id === 'otrConvertGroup')) {
    qlOtrLoadConvertSections();
  }
});

const qlOldOpenOtrReviewForConvert = qlOpenOtrReview;
qlOpenOtrReview = function(id) {
  qlOldOpenOtrReviewForConvert(id);
  const item = qlOtrFindItem(id);
  if (item) {
    setTimeout(function() {
      qlOtrPrepareConvertPanel(item);
    }, 40);
  }
};

/* === FinDesk Entry Details Viewer LEDGER-2A 20260503-31 === */
function qlLedgerFormatEntryType(entry) {
  const type = entry.entry_type === 'income' ? 'Приход' : 'Расход';
  const money = entry.money_type === 'cash' ? 'Наличные' : 'Безнал';
  return type + ' · ' + money;
}

function qlLedgerFileSize(bytes) {
  const n = Number(bytes || 0);
  if (n >= 1024 * 1024) return (n / 1024 / 1024).toFixed(1) + ' MB';
  if (n >= 1024) return Math.round(n / 1024) + ' KB';
  return n + ' B';
}

function qlLedgerIsFromOnTheGo(entry) {
  return String(entry.note || '').indexOf('From On the Go') === 0;
}

function qlLedgerRenderDetail(entry, files) {
  const content = document.getElementById('ledgerDetailContent');
  if (!content) return;

  const fileRows = (files || []).length
    ? (files || []).map(function(file) {
        return `
          <div class="ledger-detail-file-row">
            <div>
              <b>${escapeHtml(file.file_original_name || file.file_stored_name || 'Вложение')}</b>
              <small>${escapeHtml(file.file_kind || 'file')} · ${qlLedgerFileSize(file.file_size)} · ${escapeHtml(file.created_at || '')}</small>
            </div>
            <a class="ghost-btn ledger-file-open" href="${escapeHtml(file.download_url || '#')}" target="_blank" rel="noopener">Открыть</a>
          </div>
        `;
      }).join('')
    : '<p class="soft-note">Вложений нет.</p>';

  content.innerHTML = `
    ${qlLedgerIsFromOnTheGo(entry) ? '<div class="ledger-origin-badge">Перенесено из живого отчета</div>' : ''}
    <div class="ledger-detail-main">
      <div class="ledger-detail-amount ${entry.entry_type === 'income' ? 'income' : 'expense'}">${qlCurrency(entry.amount || 0)}</div>
      <div class="ledger-detail-type">${escapeHtml(qlLedgerFormatEntryType(entry))}</div>
    </div>

    <div class="ledger-detail-grid">
      <div><span>Дата</span><b>${escapeHtml(entry.entry_datetime || '')}</b></div>
      <div><span>Раздел</span><b>${escapeHtml(entry.category_name || 'Без раздела')}</b></div>
      <div><span>Хранитель</span><b>${escapeHtml(entry.owner_display_name || '')}</b></div>
      <div><span>Файлы</span><b>${Number(entry.file_count || files?.length || 0)}</b></div>
    </div>

    <div class="ledger-detail-block">
      <span>Назначение</span>
      <p>${escapeHtml(entry.purpose || '')}</p>
    </div>

    <div class="ledger-detail-block">
      <span>Примечание</span>
      <p>${escapeHtml(entry.note || 'Нет примечания')}</p>
    </div>

    <div class="ledger-detail-files">
      <div class="ledger-detail-files-head">
        <h4>Вложения</h4>
        <span>${(files || []).length} файлов</span>
      </div>
      ${fileRows}
    </div>
  `;
}

async function qlOpenLedgerDetail(entryId) {
  const modal = document.getElementById('ledgerDetailModal');
  const content = document.getElementById('ledgerDetailContent');

  if (!modal || !content) return;

  content.innerHTML = '<p class="soft-note">Загружаю запись…</p>';
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');

  const data = await qlApi('ledger_detail', { id: Number(entryId) });

  if (!data.ok) {
    content.innerHTML = '<p class="soft-note">Ошибка деталей записи: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  qlLedgerRenderDetail(data.entry || {}, data.files || []);
}

function qlCloseLedgerDetail() {
  const modal = document.getElementById('ledgerDetailModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }
}

function qlEnhanceLedgerDetailsButtons() {
  document.querySelectorAll('[data-edit-entry]').forEach(function(editBtn) {
    const id = editBtn.getAttribute('data-edit-entry');
    const parent = editBtn.parentElement;
    if (!id || !parent || parent.querySelector('[data-ledger-detail="' + id + '"]')) return;

    const btn = document.createElement('button');
    btn.className = 'entry-details';
    btn.type = 'button';
    btn.setAttribute('data-ledger-detail', id);
    btn.textContent = 'Details';
    parent.insertBefore(btn, editBtn);
  });
}

const qlOldRenderLedgerForDetails = qlRenderLedger;
qlRenderLedger = function(entries, summary) {
  qlOldRenderLedgerForDetails(entries, summary);
  qlEnhanceLedgerDetailsButtons();
};

document.addEventListener('click', function(event) {
  const detailBtn = event.target.closest('[data-ledger-detail]');
  if (detailBtn) {
    qlOpenLedgerDetail(detailBtn.getAttribute('data-ledger-detail'));
  }

  if (event.target.closest('[data-close-ledger-detail]')) {
    qlCloseLedgerDetail();
  }

  if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'ledgerDetailModal') {
    qlCloseLedgerDetail();
  }
});

/* === FinDesk On The Go Tape Controller OTR-3B-CLEAN 20260503-35 === */
let qlOtrActiveTapeId = null;
let qlOtrTapes = [];

function qlOtrTapeStatus(message) {
  const el = document.getElementById('otrTapeStatus');
  if (el) el.textContent = message || '';
}

function qlOtrSetTapeSummary(summary) {
  const s = summary || {};
  const given = document.getElementById('otrTapeGiven');
  const cashSpent = document.getElementById('otrTapeCashSpent');
  const cashLeft = document.getElementById('otrTapeCashLeft');
  const cardSpent = document.getElementById('otrTapeCardSpent');

  if (given) given.textContent = qlOtrCurrency(s.cash_in || 0);
  if (cashSpent) cashSpent.textContent = qlOtrCurrency(s.cash_out || 0);
  if (cashLeft) {
    const left = Number(s.cash_left || 0);
    cashLeft.textContent = qlOtrCurrency(left);
    const box = cashLeft.closest('div');
    if (box) {
      box.classList.toggle('cash-overrun', left < 0);
      const label = box.querySelector('span');
      if (label) label.textContent = left < 0 ? 'Cash overrun' : 'Cash left';
    }
  }
  if (cardSpent) cardSpent.textContent = qlOtrCurrency(s.card_out || 0);
}

function qlOtrRenderTapes(tapes, activeId) {
  const list = document.getElementById('otrTapeList');
  if (!list) return;

  if (!tapes || !tapes.length) {
    list.innerHTML = '<p class="soft-note">No tapes yet. Create the first one.</p>';
    qlOtrSetTapeSummary({});
    return;
  }

  list.innerHTML = tapes.map(function(tape) {
    const s = tape.summary || {};
    const active = String(tape.id) === String(activeId);
    const title = tape.title || 'On the Go';
    const created = tape.created_at || '';

    return `
      <button class="otr-tape-card ${active ? 'active' : ''}" type="button" data-otr-tape="${escapeHtml(tape.id)}">
        <span>${escapeHtml(title)}</span>
        <b>${escapeHtml(created)}</b>
        <small>Left ${qlOtrCurrency(s.cash_left || 0)}</small>
        <em>Card ${qlOtrCurrency(s.card_out || 0)}</em>
      </button>
    `;
  }).join('');

  const activeTape = tapes.find(function(tape) {
    return String(tape.id) === String(activeId);
  }) || tapes[0];

  if (activeTape) {
    qlOtrSetTapeSummary(activeTape.summary || {});
  }
}

async function qlLoadOtrTapes() {
  const data = await qlApi('on_the_go_tape_list', {});

  if (!data.ok) {
    qlOtrTapeStatus('Tape load error: ' + (data.error || 'unknown'));
    return;
  }

  qlOtrTapes = data.tapes || [];

  if (!qlOtrActiveTapeId) {
    qlOtrActiveTapeId = data.active_tape_id || (qlOtrTapes[0] ? qlOtrTapes[0].id : null);
  }

  qlOtrRenderTapes(qlOtrTapes, qlOtrActiveTapeId);
}

async function qlCreateOtrTape() {
  const amountInput = document.getElementById('otrNewTapeAmount');
  const amount = amountInput ? amountInput.value : '';

  qlOtrTapeStatus('Creating tape…');

  const data = await qlApi('on_the_go_tape_create', {
    cash_received: amount,
    title: 'On the Go'
  });

  if (!data.ok) {
    qlOtrTapeStatus('Tape create error: ' + (data.error || 'unknown'));
    return;
  }

  if (amountInput) amountInput.value = '';

  const panel = document.getElementById('otrNewTapePanel');
  if (panel) panel.classList.add('hidden');

  qlOtrActiveTapeId = data.tape && data.tape.id ? data.tape.id : null;

  qlOtrTapeStatus('Tape created.');

  await qlLoadOtrTapes();
  await qlLoadOnTheGo();
}

function qlSelectOtrTape(id) {
  qlOtrActiveTapeId = Number(id || 0) || null;
  qlOtrRenderTapes(qlOtrTapes, qlOtrActiveTapeId);
  qlLoadOnTheGo();
}

async function qlLoadOnTheGo() {
  const data = await qlApi('on_the_go_list', {
    tape_id: qlOtrActiveTapeId || undefined,
    limit: 100
  });

  const count = document.getElementById('otrCount');
  const journal = document.getElementById('otrJournal');
  const cashIn = document.getElementById('otrCashIn');
  const cashOut = document.getElementById('otrCashOut');
  const expected = document.getElementById('otrExpectedCash');

  if (!data.ok) {
    if (journal) journal.innerHTML = '<p class="soft-note">On the Go error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const items = data.items || [];
  const s = data.summary || {};

  qlOtrItems = items;

  if (!qlOtrActiveTapeId) {
    qlOtrActiveTapeId = data.active_tape_id || null;
  }

  if (data.tapes) {
    qlOtrTapes = data.tapes || [];
    qlOtrRenderTapes(qlOtrTapes, qlOtrActiveTapeId);
  }

  if (count) count.textContent = items.length + ' to review';
  if (cashIn) cashIn.textContent = qlOtrCurrency(s.cash_in || 0);
  if (cashOut) cashOut.textContent = qlOtrCurrency(s.cash_out || 0);
  if (expected) expected.textContent = qlOtrCurrency(s.cash_left || 0);

  qlOtrSetTapeSummary(s);

  if (!journal) return;

  if (!items.length) {
    journal.innerHTML = '<p class="soft-note">No records to review in this tape.</p>';
    return;
  }

  journal.innerHTML = items.map(function(item) {
    const amount = item.amount === null || item.amount === undefined ? 'Amount not set' : qlOtrCurrency(item.amount);
    const desc = item.description ? escapeHtml(item.description) : 'No note';
    const attach = Number(item.files_count || 0) > 0 ? '<span class="otr-attach">📎 attachment</span>' : '';

    return `
      <article class="otr-row">
        <div>
          <b>${escapeHtml(qlOtrTypeLabel(item.capture_type))}</b>
          <span>${desc}</span>
          <small>${escapeHtml(item.created_at || '')}</small>
        </div>
        <div class="otr-row-side">
          <strong>${amount}</strong>
          ${attach}
          <div class="otr-row-actions">
            <span class="otr-review-badge">To review</span>
            <button class="ghost-btn otr-review-btn" type="button" data-otr-review="${escapeHtml(item.id)}">Review</button>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

async function qlSaveOnTheGo(type) {
  const ids = qlOtrIds(type);
  if (!ids) return;

  const amountEl = document.getElementById(ids.amount);
  const descEl = document.getElementById(ids.desc);
  const fileEl = document.getElementById(ids.file);
  const fileName = document.getElementById(ids.fileName);

  const amount = amountEl ? amountEl.value.trim() : '';
  const description = descEl ? descEl.value.trim() : '';
  const hasFile = fileEl && fileEl.files && fileEl.files[0];

  if (!amount && !description && !hasFile) {
    qlOtrMessage('Add an amount, note or attachment.');
    return;
  }

  qlOtrMessage('Saving…');

  if (!qlOtrActiveTapeId) {
    await qlLoadOtrTapes();
  }

  const data = await qlApi('on_the_go_create', {
    capture_type: type,
    amount: amount,
    description: description,
    currency: 'EUR',
    tape_id: qlOtrActiveTapeId || undefined
  });

  if (!data.ok) {
    qlOtrMessage('Save error: ' + (data.error || 'unknown'));
    return;
  }

  const captureId = data.item && data.item.id ? data.item.id : (data.capture && data.capture.id ? data.capture.id : null);

  if (captureId && hasFile) {
    const upload = await qlUploadOnTheGoFile(captureId, fileEl);
    if (!upload.ok) {
      qlOtrMessage('Record saved, but attachment failed: ' + (upload.error || 'unknown'));
      await qlLoadOnTheGo();
      await qlLoadOtrTapes();
      return;
    }
  }

  if (amountEl) amountEl.value = '';
  if (descEl) descEl.value = '';
  if (fileEl) fileEl.value = '';
  if (fileName) fileName.textContent = 'No attachment';

  qlOtrMessage('Saved. Marked for review and not included in reports.');

  await qlLoadOnTheGo();
  await qlLoadOtrTapes();
}

function qlBindOnTheGoControls() {
  const newTapeBtn = document.getElementById('otrNewTapeBtn');
  const createTapeBtn = document.getElementById('otrCreateTapeBtn');

  if (newTapeBtn && !newTapeBtn.dataset.otrCleanBound) {
    newTapeBtn.dataset.otrCleanBound = '1';
    newTapeBtn.addEventListener('click', function(event) {
      event.preventDefault();

      const panel = document.getElementById('otrNewTapePanel');
      if (panel) panel.classList.toggle('hidden');

      const input = document.getElementById('otrNewTapeAmount');
      if (input && panel && !panel.classList.contains('hidden')) {
        setTimeout(function() { input.focus(); }, 80);
      }
    });
  }

  if (createTapeBtn && !createTapeBtn.dataset.otrCleanBound) {
    createTapeBtn.dataset.otrCleanBound = '1';
    createTapeBtn.addEventListener('click', function(event) {
      event.preventDefault();
      qlCreateOtrTape();
    });
  }
}

document.addEventListener('click', function(event) {
  const tab = event.target.closest('[data-module-tab]');
  const tape = event.target.closest('[data-otr-tape]');

  if (tab && tab.getAttribute('data-module-tab') === 'ontherun') {
    setTimeout(function() {
      qlBindOnTheGoControls();
      qlLoadOtrTapes();
      qlLoadOnTheGo();
    }, 80);
  }

  if (tape) {
    event.preventDefault();
    qlSelectOtrTape(tape.getAttribute('data-otr-tape'));
  }
});

const qlOtrCleanPreviousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);

window.qlSetModule = function(moduleName, options) {
  if (typeof qlOtrCleanPreviousSetModule === 'function') {
    qlOtrCleanPreviousSetModule(moduleName, options);
  }

  if (moduleName === 'ontherun') {
    setTimeout(function() {
      qlBindOnTheGoControls();
      qlLoadOtrTapes();
      qlLoadOnTheGo();
    }, 80);
  }
};

try {
  qlSetModule = window.qlSetModule;
} catch (error) {}

if (typeof qlRenderUser === 'function' && !window.__qlOtrCleanRenderUserBound) {
  window.__qlOtrCleanRenderUserBound = true;
  const qlOtrCleanPreviousRenderUser = qlRenderUser;

  qlRenderUser = function(user) {
    qlOtrCleanPreviousRenderUser(user);

    setTimeout(function() {
      qlBindOnTheGoControls();

      const module = document.getElementById('moduleOnTheGo');
      if (module && !module.classList.contains('hidden')) {
        qlLoadOtrTapes();
        qlLoadOnTheGo();
      }
    }, 120);
  };
}

document.addEventListener('DOMContentLoaded', function() {
  qlBindOnTheGoControls();
});

window.qlBindOnTheGoControls = qlBindOnTheGoControls;
window.qlLoadOtrTapes = qlLoadOtrTapes;
window.qlLoadOnTheGo = qlLoadOnTheGo;
window.qlSaveOnTheGo = qlSaveOnTheGo;
window.qlCreateOtrTape = qlCreateOtrTape;
window.qlSelectOtrTape = qlSelectOtrTape;


/* === FinDesk On the Go Operational Body Mode OTR-3F 20260503-40 === */
(function() {
  function qlSyncOtrBodyMode() {
    const module = document.getElementById('moduleOnTheGo');
    const isOtr = !!(module && !module.classList.contains('hidden'));

    document.body.classList.toggle('ql-otr-mode', isOtr);
  }

  document.addEventListener('click', async function(event) {
    const tab = event.target.closest('[data-module-tab]');
    if (!tab) return;

    setTimeout(qlSyncOtrBodyMode, 80);
  });

  const prevSetModule = window.qlSetModule;
  window.qlSetModule = function(moduleName, options) {
    if (typeof prevSetModule === 'function') {
      prevSetModule(moduleName, options);
    }
    setTimeout(qlSyncOtrBodyMode, 80);
  };

  try {
    qlSetModule = window.qlSetModule;
  } catch (error) {}

  if (typeof qlRenderUser === 'function' && !window.__qlOtr3fRenderUserBound) {
    window.__qlOtr3fRenderUserBound = true;
    const prevRenderUser = qlRenderUser;

    qlRenderUser = function(user) {
      prevRenderUser(user);
      setTimeout(qlSyncOtrBodyMode, 120);
    };
  }

  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(qlSyncOtrBodyMode, 120);
  });

  window.qlSyncOtrBodyMode = qlSyncOtrBodyMode;
})();

/* === FinDesk On the Go Mobile Cash/Card Action Flow OTR-3G 20260503-41 === */
(function() {
  let qlOtrMobileType = 'cash_out';

  function qlOtrMobileStatus(text) {
    const el = document.getElementById('otrMobileStatus');
    if (el) el.textContent = text || '';
  }

  function qlOpenOtrMobileInput(type) {
    qlOtrMobileType = type === 'noncash_out' ? 'noncash_out' : 'cash_out';

    const panel = document.getElementById('otrMobileInputPanel');
    const title = document.getElementById('otrMobileInputTitle');
    const kicker = document.getElementById('otrMobileInputKicker');
    const watermark = document.getElementById('otrMobileInputWatermark');
    const amount = document.getElementById('otrMobileAmount');
    const desc = document.getElementById('otrMobileDesc');

    const isCard = qlOtrMobileType === 'noncash_out';
    const label = isCard ? 'Card expense' : 'Cash expense';

    if (title) title.textContent = label;
    if (kicker) kicker.textContent = isCard ? 'CARD' : 'CASH';
    if (watermark) {
      watermark.textContent = label;
      watermark.classList.toggle('card', isCard);
      watermark.classList.toggle('cash', !isCard);
    }

    if (amount) {
      amount.value = '';
      amount.placeholder = isCard ? 'Card expense amount' : 'Cash expense amount';
    }

    if (desc) {
      desc.value = '';
      desc.placeholder = isCard ? 'Card payment note — optional' : 'Cash payment note — optional';
    }

    const file = document.getElementById('otrMobileFile');
    const fileName = document.getElementById('otrMobileFileName');
    if (file) file.value = '';
    if (fileName) fileName.textContent = 'No attachment';

    qlOtrMobileStatus('');

    if (panel) {
      panel.classList.remove('hidden');
      panel.setAttribute('aria-hidden', 'false');
    }

    setTimeout(function() {
      if (amount) amount.focus();
    }, 80);
  }

  function qlCloseOtrMobileInput() {
    const panel = document.getElementById('otrMobileInputPanel');
    if (panel) {
      panel.classList.add('hidden');
      panel.setAttribute('aria-hidden', 'true');
    }
  }

  async function qlSaveOtrMobileInput() {
    const amountEl = document.getElementById('otrMobileAmount');
    const descEl = document.getElementById('otrMobileDesc');
    const fileEl = document.getElementById('otrMobileFile');

    const amount = amountEl ? amountEl.value.trim() : '';
    const description = descEl ? descEl.value.trim() : '';
    const hasFile = fileEl && fileEl.files && fileEl.files[0];

    if (!amount && !description && !hasFile) {
      qlOtrMobileStatus('Add an amount, note or attachment.');
      return;
    }

    qlOtrMobileStatus('Saving…');

    if (!window.qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') {
      await qlLoadOtrTapes();
    }

    const data = await qlApi('on_the_go_create', {
      capture_type: qlOtrMobileType,
      amount: amount,
      description: description,
      currency: 'EUR',
      tape_id: window.qlOtrActiveTapeId || qlOtrActiveTapeId || undefined
    });

    if (!data.ok) {
      qlOtrMobileStatus('Save error: ' + (data.error || 'unknown'));
      return;
    }

    const captureId = data.item && data.item.id ? data.item.id : (data.capture && data.capture.id ? data.capture.id : null);

    if (captureId && hasFile) {
      const upload = await qlUploadOnTheGoFile(captureId, fileEl);
      if (!upload.ok) {
        qlOtrMobileStatus('Saved, but attachment failed: ' + (upload.error || 'unknown'));
        if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
        if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
        return;
      }
    }

    qlOtrMobileStatus('Saved.');

    if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
    if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();

    setTimeout(function() {
      qlCloseOtrMobileInput();
    }, 450);
  }

  document.addEventListener('click', async function(event) {
    const cashBtn = event.target.closest('#otrMobileCashBtn');
    const cardBtn = event.target.closest('#otrMobileCardBtn');
    const closeBtn = event.target.closest('#otrMobileInputCloseBtn');
    const saveBtn = event.target.closest('#otrMobileSaveBtn');

    if (cashBtn) {
      event.preventDefault();
      qlOpenOtrMobileInput('cash_out');
      return;
    }

    if (cardBtn) {
      event.preventDefault();
      qlOpenOtrMobileInput('noncash_out');
      return;
    }

    if (closeBtn) {
      event.preventDefault();
      qlCloseOtrMobileInput();
      return;
    }

    if (saveBtn) {
      event.preventDefault();
      qlSaveOtrMobileInput();
      return;
    }
  });

  document.addEventListener('change', function(event) {
    const input = event.target;
    if (!input || input.id !== 'otrMobileFile') return;

    const label = document.getElementById('otrMobileFileName');
    if (label) {
      label.textContent = input.files && input.files[0] ? input.files[0].name : 'No attachment';
    }
  });

  window.qlOpenOtrMobileInput = qlOpenOtrMobileInput;
  window.qlCloseOtrMobileInput = qlCloseOtrMobileInput;
  window.qlSaveOtrMobileInput = qlSaveOtrMobileInput;
})();

/* === FinDesk On the Go Close Session UI OTR-4C 20260503-48 === */
(function() {
  function qlOtrMobileCurrentSessionType() {
    try {
      return qlOtrMobileType === 'noncash_out' ? 'card' : 'cash';
    } catch (error) {
      return 'cash';
    }
  }

  async function qlCloseCurrentOtrSession() {
    if (window.__qlOtrCloseSessionBusy) {
      const statusBusy = document.getElementById('otrMobileStatus');
      if (statusBusy) statusBusy.textContent = 'Closing session already in progress…';
      return;
    }

    const sessionType = qlOtrMobileCurrentSessionType();
    const label = sessionType === 'card' ? 'Card' : 'Cash';

    if (!confirm('Close current ' + label + ' session and start a new one?')) {
      return;
    }

    window.__qlOtrCloseSessionBusy = true;

    const closeBtn = document.getElementById('otrMobileCloseSessionBtn');
    if (closeBtn) {
      closeBtn.disabled = true;
      closeBtn.classList.add('is-busy');
    }

    const status = document.getElementById('otrMobileStatus');

    try {
      if (status) status.textContent = 'Closing ' + label.toLowerCase() + ' session…';

      if (!window.qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') {
        await qlLoadOtrTapes();
      }

      const data = await qlApi('on_the_go_close_session', {
        tape_id: window.qlOtrActiveTapeId || qlOtrActiveTapeId || undefined,
        session_type: sessionType
      });

      if (!data.ok) {
        if (status) status.textContent = 'Close session error: ' + (data.error || 'unknown');
        return;
      }

      if (status) status.textContent = label + ' session closed. New session started.';

      if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
      if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();

      setTimeout(function() {
        if (typeof qlCloseOtrMobileInput === 'function') {
          qlCloseOtrMobileInput();
        }
      }, 700);
    } finally {
      window.__qlOtrCloseSessionBusy = false;

      if (closeBtn) {
        closeBtn.disabled = false;
        closeBtn.classList.remove('is-busy');
      }
    }
  }


  document.addEventListener('click', function(event) {
    const btn = event.target.closest('#otrMobileCloseSessionBtn');
    if (!btn) return;

    event.preventDefault();
    qlCloseCurrentOtrSession();
  });

  window.qlCloseCurrentOtrSession = qlCloseCurrentOtrSession;
})();

/* === FinDesk On the Go Save Guard OTR-4C-2 20260503-49 === */
(function() {
  if (window.__qlOtrSaveGuardInstalled) return;
  window.__qlOtrSaveGuardInstalled = true;

  let saveBusy = false;

  function qlSetOtrSaveButtonsDisabled(disabled) {
    document.querySelectorAll('[data-otr-save], #otrMobileSaveBtn').forEach(function(btn) {
      btn.disabled = !!disabled;
      btn.classList.toggle('is-busy', !!disabled);
    });
  }

  if (typeof qlSaveOnTheGo === 'function') {
    const previousSaveOnTheGo = qlSaveOnTheGo;

    qlSaveOnTheGo = async function(type) {
      if (saveBusy) {
        if (typeof qlOtrMessage === 'function') qlOtrMessage('Saving already in progress…');
        const mobileStatus = document.getElementById('otrMobileStatus');
        if (mobileStatus) mobileStatus.textContent = 'Saving already in progress…';
        return;
      }

      saveBusy = true;
      qlSetOtrSaveButtonsDisabled(true);

      try {
        return await previousSaveOnTheGo(type);
      } finally {
        saveBusy = false;
        qlSetOtrSaveButtonsDisabled(false);
      }
    };

    window.qlSaveOnTheGo = qlSaveOnTheGo;
  }
})();

/* === FinDesk On the Go Session Cards OTR-4D 20260503-50 === */
(function() {
  if (window.__qlOtrSessionCardsInstalled) return;
  window.__qlOtrSessionCardsInstalled = true;

  function qlOtrSessionLabel(session) {
    const type = session && session.session_type === 'card' ? 'Card' : 'Cash';
    const status = session && session.status ? session.status : 'active';
    return type + ' · ' + status;
  }

  function qlOtrSessionAmount(session) {
    const total = Number(session && session.amount_total ? session.amount_total : 0);
    return qlOtrCurrency(total);
  }

  function qlOtrSessionMeta(session) {
    const count = Number(session && session.pending_total ? session.pending_total : 0);
    const started = session && session.started_at ? session.started_at : '';
    const closed = session && session.closed_at ? session.closed_at : '';

    if (closed) {
      return count + ' record' + (count === 1 ? '' : 's') + ' · closed ' + closed;
    }

    return count + ' record' + (count === 1 ? '' : 's') + (started ? ' · started ' + started : '');
  }

  function qlOtrRenderSessionCards(sessions) {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;

    const list = (sessions || []).filter(function(session) {
      const records = Number(session.records_total || 0);
      const pending = Number(session.pending_total || 0);
      return session.status === 'closed' || pending > 0 || records > 0;
    });

    if (!list.length) {
      box.innerHTML = '<p class="soft-note">No session cards yet. Close a session after a few records.</p>';
      return;
    }

    box.innerHTML = list.map(function(session) {
      const type = session.session_type === 'card' ? 'card' : 'cash';
      const status = session.status === 'closed' ? 'closed' : 'active';
      const amount = qlOtrSessionAmount(session);
      const label = qlOtrSessionLabel(session);
      const meta = qlOtrSessionMeta(session);

      return `
        <button class="otr-session-card ${escapeHtml(type)} ${escapeHtml(status)}" type="button" data-otr-session="${escapeHtml(session.id)}">
          <span>${escapeHtml(label)}</span>
          <b>${escapeHtml(amount)}</b>
          <small>${escapeHtml(meta)}</small>
        </button>
      `;
    }).join('');
  }

  async function qlLoadOtrSessionCards() {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;

    try {
      if (!qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') {
        await qlLoadOtrTapes();
      }

      const data = await qlApi('on_the_go_session_list', {
        tape_id: qlOtrActiveTapeId || undefined
      });

      if (!data.ok) {
        box.innerHTML = '<p class="soft-note">Session load error: ' + escapeHtml(data.error || 'unknown') + '</p>';
        return;
      }

      qlOtrRenderSessionCards(data.sessions || []);
    } catch (error) {
      box.innerHTML = '<p class="soft-note">Session load error.</p>';
    }
  }

  const previousLoadOnTheGoForSessionCards = window.qlLoadOnTheGo || (typeof qlLoadOnTheGo === 'function' ? qlLoadOnTheGo : null);
  if (typeof previousLoadOnTheGoForSessionCards === 'function') {
    qlLoadOnTheGo = async function() {
      const result = await previousLoadOnTheGoForSessionCards.apply(this, arguments);
      await qlLoadOtrSessionCards();
      return result;
    };
    window.qlLoadOnTheGo = qlLoadOnTheGo;
  }

  document.addEventListener('click', function(event) {
    const tab = event.target.closest('[data-module-tab]');
    const tape = event.target.closest('[data-otr-tape]');

    if ((tab && tab.getAttribute('data-module-tab') === 'ontherun') || tape) {
      setTimeout(qlLoadOtrSessionCards, 140);
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(qlLoadOtrSessionCards, 180);
  });

  window.qlLoadOtrSessionCards = qlLoadOtrSessionCards;
  window.qlOtrRenderSessionCards = qlOtrRenderSessionCards;
})();

/* === FinDesk On the Go Session Reset OTR-4E-1 20260503-60 === */
(function() {
  if (window.__qlOtr4eResetInstalled) return;
  window.__qlOtr4eResetInstalled = true;

  window.qlOtrActiveZone = window.qlOtrActiveZone || 'cash';

  function qlOtr4eZoneFromCaptureType(type) {
    return type === 'noncash_out' ? 'card' : 'cash';
  }

  function qlOtr4eCaptureTypeFromZone(zone) {
    return zone === 'card' ? 'noncash_out' : 'cash_out';
  }

  function qlOtr4eZoneLabel(zone) {
    return zone === 'card' ? 'Card' : 'Cash';
  }

  function qlOtr4eSetZone(zone) {
    window.qlOtrActiveZone = zone === 'card' ? 'card' : 'cash';
    document.body.classList.toggle('ql-otr-zone-card', window.qlOtrActiveZone === 'card');
    document.body.classList.toggle('ql-otr-zone-cash', window.qlOtrActiveZone !== 'card');

    const cashBtn = document.getElementById('otrMobileCashBtn');
    const cardBtn = document.getElementById('otrMobileCardBtn');
    if (cashBtn) cashBtn.classList.toggle('active', window.qlOtrActiveZone === 'cash');
    if (cardBtn) cardBtn.classList.toggle('active', window.qlOtrActiveZone === 'card');
  }

  function qlOtr4eActiveJournalTitle() {
    const head = document.querySelector('.otr-journal-head h3');
    const sub = document.querySelector('.otr-journal-head span');
    const label = qlOtr4eZoneLabel(window.qlOtrActiveZone);
    if (head) head.textContent = label + ' active session';
    if (sub) sub.textContent = 'Only current active session';
  }

  function qlOtr4eRenderJournal(items) {
    const journal = document.getElementById('otrJournal');
    const count = document.getElementById('otrCount');
    if (!journal) return;

    const list = items || [];
    const label = qlOtr4eZoneLabel(window.qlOtrActiveZone);
    if (count) count.textContent = list.length + ' active ' + label.toLowerCase() + ' record' + (list.length === 1 ? '' : 's');
    qlOtr4eActiveJournalTitle();

    if (!list.length) {
      journal.innerHTML = '<p class="soft-note">No records in active ' + label.toLowerCase() + ' session. Add a record or open a closed session card.</p>';
      return;
    }

    journal.innerHTML = list.map(function(item) {
      const amount = item.amount === null || item.amount === undefined ? 'Amount not set' : qlOtrCurrency(item.amount);
      const desc = item.description ? escapeHtml(item.description) : 'No note';
      const attach = Number(item.files_count || 0) > 0 ? '<span class="otr-attach">📎 attachment</span>' : '';
      return `
        <article class="otr-row active-session-row ${escapeHtml(window.qlOtrActiveZone)}">
          <div>
            <b>${escapeHtml(qlOtrTypeLabel(item.capture_type))}</b>
            <span>${desc}</span>
            <small>${escapeHtml(item.created_at || '')}</small>
          </div>
          <div class="otr-row-side">
            <strong>${amount}</strong>
            ${attach}
            <div class="otr-row-actions">
              <span class="otr-review-badge">Active</span>
              <button class="ghost-btn otr-review-btn" type="button" data-otr-review="${escapeHtml(item.id)}">Review</button>
            </div>
          </div>
        </article>
      `;
    }).join('');
  }

  qlLoadOnTheGo = async function() {
    if (!qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') {
      await qlLoadOtrTapes();
    }

    const data = await qlApi('on_the_go_list', {
      tape_id: qlOtrActiveTapeId || undefined,
      session_type: window.qlOtrActiveZone || 'cash',
      limit: 100
    });

    const journal = document.getElementById('otrJournal');
    if (!data.ok) {
      if (journal) journal.innerHTML = '<p class="soft-note">On the Go error: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    qlOtrItems = data.items || [];
    if (!qlOtrActiveTapeId) qlOtrActiveTapeId = data.active_tape_id || null;

    if (data.tapes) {
      qlOtrTapes = data.tapes || [];
      qlOtrRenderTapes(qlOtrTapes, qlOtrActiveTapeId);
    }

    qlOtrSetTapeSummary(data.summary || {});
    qlOtr4eRenderJournal(qlOtrItems);

    if (typeof window.qlLoadOtrSessionCards === 'function') {
      await window.qlLoadOtrSessionCards();
    }
  };
  window.qlLoadOnTheGo = qlLoadOnTheGo;

  window.qlOtrRenderSessionCards = function(sessions) {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;

    const closed = (sessions || []).filter(function(session) {
      const records = Number(session.records_total || 0);
      return session.status === 'closed' && records > 0;
    });

    const cash = closed.filter(function(s) { return s.session_type !== 'card'; });
    const card = closed.filter(function(s) { return s.session_type === 'card'; });

    function renderGroup(title, type, list) {
      if (!list.length) {
        return '<div class="otr-session-group ' + type + '"><h4>' + title + '</h4><p class="soft-note">No closed sessions yet.</p></div>';
      }
      return '<div class="otr-session-group ' + type + '"><h4>' + title + '</h4><div class="otr-session-strip">' + list.map(function(session) {
        const amount = qlOtrCurrency(session.amount_total || 0);
        const count = Number(session.pending_total || 0);
        const closedAt = session.closed_at || session.started_at || '';
        return `
          <button class="otr-session-card ${escapeHtml(type)} closed" type="button" data-otr-session="${escapeHtml(session.id)}" data-otr-session-type="${escapeHtml(type)}">
            <span>${escapeHtml(title)}</span>
            <b>${escapeHtml(amount)}</b>
            <small>${count} record${count === 1 ? '' : 's'} · ${escapeHtml(closedAt)}</small>
          </button>
        `;
      }).join('') + '</div></div>';
    }

    box.innerHTML = renderGroup('Cash sessions', 'cash', cash) + renderGroup('Card sessions', 'card', card);
  };

  window.qlLoadOtrSessionCards = async function() {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;

    try {
      if (!qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
      const data = await qlApi('on_the_go_session_list', { tape_id: qlOtrActiveTapeId || undefined });
      if (!data.ok) {
        box.innerHTML = '<p class="soft-note">Session load error: ' + escapeHtml(data.error || 'unknown') + '</p>';
        return;
      }
      window.qlOtrRenderSessionCards(data.sessions || []);
    } catch (error) {
      box.innerHTML = '<p class="soft-note">Session load error.</p>';
    }
  };

  document.addEventListener('click', function(event) {
    const cashBtn = event.target.closest('#otrMobileCashBtn');
    const cardBtn = event.target.closest('#otrMobileCardBtn');
    if (cashBtn) {
      qlOtr4eSetZone('cash');
      setTimeout(function() { if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo(); }, 60);
    }
    if (cardBtn) {
      qlOtr4eSetZone('card');
      setTimeout(function() { if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo(); }, 60);
    }
  }, true);

  document.addEventListener('click', async function(event) {
    const btn = event.target.closest('#otrMobileCloseSessionBtn');
    if (!btn) return;
    event.preventDefault();
    event.stopImmediatePropagation();

    const sessionType = window.qlOtrActiveZone === 'card' ? 'card' : 'cash';
    const label = qlOtr4eZoneLabel(sessionType);
    if (!confirm('Close current ' + label + ' session and start a new one?')) return;

    const status = document.getElementById('otrMobileStatus');
    try {
      btn.disabled = true;
      btn.classList.add('is-busy');
      if (status) status.textContent = 'Closing ' + label.toLowerCase() + ' session…';

      if (!qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
      const data = await qlApi('on_the_go_close_session', {
        tape_id: qlOtrActiveTapeId || undefined,
        session_type: sessionType
      });

      if (!data.ok) {
        if (status) status.textContent = 'Close session error: ' + (data.error || 'unknown');
        return;
      }

      if (status) status.textContent = label + ' session closed. New empty session started.';
      if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
      if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
      if (typeof qlCloseOtrMobileInput === 'function') setTimeout(qlCloseOtrMobileInput, 600);
    } finally {
      btn.disabled = false;
      btn.classList.remove('is-busy');
    }
  }, true);

  document.addEventListener('click', function(event) {
    const card = event.target.closest('[data-otr-session]');
    if (!card) return;
    event.preventDefault();
    const type = card.getAttribute('data-otr-session-type') === 'card' ? 'card' : 'cash';
    alert('Session card selected. Next step opens this session for review/edit/activate. Type: ' + type + ', ID: ' + card.getAttribute('data-otr-session'));
  });

  document.addEventListener('DOMContentLoaded', function() {
    qlOtr4eSetZone(window.qlOtrActiveZone || 'cash');
    setTimeout(function() {
      if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo();
    }, 180);
  });

  window.qlOtr4eSetZone = qlOtr4eSetZone;
})();

/* === FinDesk On the Go Real Two-Zone Session UI OTR-4F 20260503-61 === */
(function() {
  if (window.__qlOtr4fInstalled) return;
  window.__qlOtr4fInstalled = true;

  let qlOtr4fCurrentSession = null;

  function money(value) {
    return typeof qlOtrCurrency === 'function' ? qlOtrCurrency(value || 0) : ('€' + Number(value || 0).toFixed(2));
  }

  function currentZone() {
    return window.qlOtrActiveZone === 'card' ? 'card' : 'cash';
  }

  function typeLabel(type) {
    return type === 'card' ? 'Card' : 'Cash';
  }

  function sessionTypeLabel(session) {
    return (session && session.session_type === 'card') ? 'Card' : 'Cash';
  }

  function statusLabel(session) {
    return (session && session.status) ? String(session.status).toUpperCase() : 'ACTIVE';
  }

  function renderSessionCard(session, mode) {
    const type = session.session_type === 'card' ? 'card' : 'cash';
    const status = session.status || 'active';
    const total = money(session.amount_total || 0);
    const count = Number(session.pending_total || session.records_total || 0);
    const date = session.closed_at || session.started_at || session.created_at || '';
    const activeText = status === 'active' ? 'current active' : 'tap to open';

    return `
      <button class="otr-session-card-4f ${escapeHtml(type)} ${escapeHtml(status)} ${escapeHtml(mode || '')}" type="button"
        data-otr-session="${escapeHtml(session.id)}" data-otr-session-type="${escapeHtml(type)}">
        <span>${escapeHtml(sessionTypeLabel(session))} · ${escapeHtml(statusLabel(session))}</span>
        <b>${escapeHtml(total)}</b>
        <small>${count} record${count === 1 ? '' : 's'} · ${escapeHtml(date)}</small>
        <em>${escapeHtml(activeText)}</em>
      </button>
    `;
  }

  window.qlOtrRenderSessionCards = function(sessions) {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;

    const all = sessions || [];
    const cash = all.filter(function(s) { return s.session_type !== 'card' && s.status !== 'archived'; });
    const card = all.filter(function(s) { return s.session_type === 'card' && s.status !== 'archived'; });

    function column(type, title, items) {
      const active = items.filter(function(s) { return s.status === 'active'; });
      const closed = items.filter(function(s) {
        const records = Number(s.records_total || 0);
        return s.status === 'closed' && records > 0;
      });

      return `
        <section class="otr-session-column-4f ${escapeHtml(type)}">
          <div class="otr-session-column-head-4f">
            <h4>${escapeHtml(title)}</h4>
            <span>${closed.length} closed</span>
          </div>
          <div class="otr-session-active-slot-4f">
            ${active.length ? active.map(function(s) { return renderSessionCard(s, 'active-slot'); }).join('') : '<p class="soft-note">No active session.</p>'}
          </div>
          <div class="otr-session-closed-list-4f">
            ${closed.length ? closed.map(function(s) { return renderSessionCard(s, 'closed-slot'); }).join('') : '<p class="soft-note">No closed sessions yet.</p>'}
          </div>
        </section>
      `;
    }

    box.innerHTML = `
      <div class="otr-session-board-4f">
        ${column('cash', 'Cash', cash)}
        ${column('card', 'Card', card)}
      </div>
    `;
  };

  window.qlLoadOtrSessionCards = async function() {
    const box = document.getElementById('otrSessionCards');
    if (!box) return;
    try {
      if (!qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
      const data = await qlApi('on_the_go_session_list', { tape_id: qlOtrActiveTapeId || undefined });
      if (!data.ok) {
        box.innerHTML = '<p class="soft-note">Session load error: ' + escapeHtml(data.error || 'unknown') + '</p>';
        return;
      }
      window.qlOtrRenderSessionCards(data.sessions || []);
    } catch (error) {
      box.innerHTML = '<p class="soft-note">Session load error.</p>';
    }
  };

  function openSessionModalShell() {
    const modal = document.getElementById('otrSessionModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeSessionModal() {
    const modal = document.getElementById('otrSessionModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  function renderSessionModal(data) {
    const session = data.session || {};
    const summary = data.summary || {};
    const items = data.items || [];
    qlOtr4fCurrentSession = session;

    const kicker = document.getElementById('otrSessionModalKicker');
    const title = document.getElementById('otrSessionModalTitle');
    const amount = document.getElementById('otrSessionModalAmount');
    const meta = document.getElementById('otrSessionModalMeta');
    const records = document.getElementById('otrSessionModalRecords');
    const activateBtn = document.getElementById('otrActivateSessionBtn');
    const archiveBtn = document.getElementById('otrArchiveSessionBtn');
    const status = document.getElementById('otrSessionModalStatus');

    const type = session.session_type === 'card' ? 'card' : 'cash';
    const label = typeLabel(type);

    if (kicker) kicker.textContent = label + ' · ' + statusLabel(session);
    if (title) title.textContent = label + ' session';
    if (amount) amount.textContent = money(summary.amount_total || 0);
    if (meta) meta.textContent = (summary.pending_total || 0) + ' records · started ' + (session.started_at || '') + (session.closed_at ? ' · closed ' + session.closed_at : '');
    if (status) status.textContent = '';

    if (activateBtn) {
      activateBtn.classList.toggle('hidden', session.status === 'active');
      activateBtn.textContent = 'Activate this ' + label.toLowerCase() + ' session';
    }
    if (archiveBtn) archiveBtn.textContent = 'Archive ' + label.toLowerCase() + ' session';

    if (!records) return;
    if (!items.length) {
      records.innerHTML = '<p class="soft-note">No records in this session.</p>';
      return;
    }

    records.innerHTML = items.map(function(item) {
      const desc = item.description ? escapeHtml(item.description) : 'No note';
      const attach = Number(item.files_count || 0) > 0 ? '<span class="otr-attach">📎 attachment</span>' : '';
      return `
        <article class="otr-session-record-4f">
          <div>
            <b>${escapeHtml(qlOtrTypeLabel(item.capture_type))}</b>
            <span>${desc}</span>
            <small>${escapeHtml(item.created_at || '')}</small>
            ${attach}
          </div>
          <div class="otr-session-record-actions-4f">
            <strong>${escapeHtml(money(item.amount || 0))}</strong>
            <button class="ghost-btn small-btn" type="button" data-otr-review="${escapeHtml(item.id)}">Review</button>
          </div>
        </article>
      `;
    }).join('');
  }

  async function openSessionDetail(sessionId) {
    openSessionModalShell();
    const records = document.getElementById('otrSessionModalRecords');
    const status = document.getElementById('otrSessionModalStatus');
    if (records) records.innerHTML = '<p class="soft-note">Loading session…</p>';
    if (status) status.textContent = '';

    const data = await qlApi('on_the_go_session_detail', { session_id: Number(sessionId || 0) });
    if (!data.ok) {
      if (records) records.innerHTML = '<p class="soft-note">Session error: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }
    renderSessionModal(data);
  }

  async function activateCurrentSession() {
    if (!qlOtr4fCurrentSession || !qlOtr4fCurrentSession.id) return;
    const status = document.getElementById('otrSessionModalStatus');
    if (status) status.textContent = 'Activating session…';

    const data = await qlApi('on_the_go_activate_session', { session_id: Number(qlOtr4fCurrentSession.id) });
    if (!data.ok) {
      if (status) status.textContent = 'Activate error: ' + (data.error || 'unknown');
      return;
    }

    const type = data.session && data.session.session_type === 'card' ? 'card' : 'cash';
    if (typeof window.qlOtr4eSetZone === 'function') window.qlOtr4eSetZone(type);
    window.qlOtrActiveZone = type;
    if (status) status.textContent = 'Session activated.';
    renderSessionModal(data);
    if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
    if (typeof window.qlLoadOtrSessionCards === 'function') await window.qlLoadOtrSessionCards();
  }

  async function archiveCurrentSession() {
    if (!qlOtr4fCurrentSession || !qlOtr4fCurrentSession.id) return;
    if (!confirm('Archive this session card? Records will be hidden from On the Go session cards.')) return;
    const status = document.getElementById('otrSessionModalStatus');
    if (status) status.textContent = 'Archiving session…';

    const data = await qlApi('on_the_go_archive_session', { session_id: Number(qlOtr4fCurrentSession.id) });
    if (!data.ok) {
      if (status) status.textContent = 'Archive error: ' + (data.error || 'unknown');
      return;
    }

    closeSessionModal();
    if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
    if (typeof window.qlLoadOtrSessionCards === 'function') await window.qlLoadOtrSessionCards();
  }

  document.addEventListener('click', function(event) {
    const card = event.target.closest('[data-otr-session]');
    if (!card) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openSessionDetail(card.getAttribute('data-otr-session'));
  }, true);

  document.addEventListener('click', function(event) {
    if (event.target.closest('[data-close-otr-session]')) {
      event.preventDefault();
      closeSessionModal();
      return;
    }
    if (event.target.closest('#otrActivateSessionBtn')) {
      event.preventDefault();
      activateCurrentSession();
      return;
    }
    if (event.target.closest('#otrArchiveSessionBtn')) {
      event.preventDefault();
      archiveCurrentSession();
      return;
    }
    if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'otrSessionModal') {
      closeSessionModal();
    }
  }, true);

  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
      if (typeof window.qlLoadOtrSessionCards === 'function') window.qlLoadOtrSessionCards();
    }, 250);
  });

  window.qlOtr4fOpenSessionDetail = openSessionDetail;
})();

/* === FinDesk On the Go Final Active Journal Override OTR-4F-2 20260503-62 === */
(function() {
  function otrMoney(value) {
    if (typeof qlOtrCurrency === 'function') return qlOtrCurrency(value || 0);
    return '€' + Number(value || 0).toFixed(2);
  }

  function otrZone() {
    return window.qlOtrActiveZone === 'card' ? 'card' : 'cash';
  }

  function otrCaptureTypeLabel(type) {
    if (typeof qlOtrTypeLabel === 'function') return qlOtrTypeLabel(type);
    if (type === 'noncash_out') return 'Card / non-cash spent';
    if (type === 'cash_out') return 'Cash spent';
    return 'On the Go';
  }

  function otrSetActiveJournalTitle(zone, count) {
    const head = document.querySelector('.otr-journal-head h3');
    const sub = document.querySelector('.otr-journal-head span');
    const topCount = document.getElementById('otrCount');

    const label = zone === 'card' ? 'Card active session' : 'Cash active session';

    if (head) head.textContent = label;
    if (sub) sub.textContent = 'Only current active session';
    if (topCount) topCount.textContent = String(count || 0) + ' active ' + zone + ' records';
  }

  function otrRenderActiveJournal(items, zone) {
    const journal = document.getElementById('otrJournal');
    if (!journal) return;

    otrSetActiveJournalTitle(zone, items.length);

    if (!items.length) {
      journal.innerHTML = '<p class="soft-note">No records in active ' + zone + ' session. Add a record or open a closed session card.</p>';
      return;
    }

    journal.innerHTML = items.map(function(item) {
      const desc = item.description ? escapeHtml(item.description) : 'No note';
      const attach = Number(item.files_count || 0) > 0 ? '<span class="otr-attach">📎 attachment</span>' : '';
      return `
        <article class="otr-row active-session-row">
          <div>
            <b>${escapeHtml(otrCaptureTypeLabel(item.capture_type))}</b>
            <span>${desc}</span>
            <small>${escapeHtml(item.created_at || '')}</small>
            ${attach}
          </div>
          <div class="otr-row-side">
            <strong>${escapeHtml(otrMoney(item.amount || 0))}</strong>
            <div class="otr-row-actions">
              <span class="otr-review-badge">Active</span>
              <button class="ghost-btn otr-review-btn" type="button" data-otr-review="${escapeHtml(item.id)}">Review</button>
            </div>
          </div>
        </article>
      `;
    }).join('');
  }

  async function otrFinalLoadOnTheGo() {
    const zone = otrZone();

    if (!window.qlOtrActiveTapeId && typeof qlLoadOtrTapes === 'function') {
      await qlLoadOtrTapes();
    }

    const data = await qlApi('on_the_go_list', {
      tape_id: window.qlOtrActiveTapeId || undefined,
      session_type: zone,
      limit: 100
    });

    const journal = document.getElementById('otrJournal');

    if (!data.ok) {
      if (journal) journal.innerHTML = '<p class="soft-note">On the Go error: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    const items = data.items || [];
    window.qlOtrItems = items;

    otrRenderActiveJournal(items, zone);

    if (typeof qlOtrSetTapeSummary === 'function') {
      qlOtrSetTapeSummary(data.summary || {});
    }

    if (data.tapes && typeof qlOtrRenderTapes === 'function') {
      window.qlOtrTapes = data.tapes || [];
      qlOtrRenderTapes(window.qlOtrTapes, window.qlOtrActiveTapeId || data.active_tape_id);
    }

    if (typeof window.qlLoadOtrSessionCards === 'function') {
      await window.qlLoadOtrSessionCards();
    }
  }

  window.qlLoadOnTheGo = otrFinalLoadOnTheGo;

  try {
    qlLoadOnTheGo = otrFinalLoadOnTheGo;
  } catch (error) {}

  const previousSetZone = window.qlOtr4eSetZone;
  window.qlOtr4eSetZone = function(zone) {
    const normalized = zone === 'card' ? 'card' : 'cash';
    window.qlOtrActiveZone = normalized;

    if (typeof previousSetZone === 'function') {
      previousSetZone(normalized);
    }

    const journal = document.getElementById('otrJournal');
    if (journal) {
      journal.innerHTML = '<p class="soft-note">Loading active ' + normalized + ' session…</p>';
    }

    setTimeout(function() {
      otrFinalLoadOnTheGo();
    }, 80);
  };

  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
      otrFinalLoadOnTheGo();
    }, 250);
  });

  window.qlOtrFinalLoadOnTheGo = otrFinalLoadOnTheGo;
})();

/* === FinDesk Premium Feature Shell STEP-5 20260520 === */
(function() {
  function premiumStatus(message) {
    const el = document.getElementById('premiumStatus');
    if (el) el.textContent = message || '';
  }

  function qlPremiumOpen(moduleName) {
    if (typeof window.qlSetModule === 'function') {
      window.qlSetModule(moduleName || 'money');
      premiumStatus('');
    }
  }

  function qlPremiumSoon(feature) {
    const t = window.cfT || function(key) { return key; };
    const labels = {
      trip: t('premium.tripText'),
      reports: t('premium.reportText')
    };
    premiumStatus(labels[feature] || t('premium.prepared'));
  }

  document.addEventListener('click', function(event) {
    const open = event.target.closest('[data-premium-open]');
    const soon = event.target.closest('[data-premium-soon]');

    if (open) qlPremiumOpen(open.getAttribute('data-premium-open'));
    if (soon) qlPremiumSoon(soon.getAttribute('data-premium-soon'));
  });

  window.qlPremiumOpen = qlPremiumOpen;
})();

/* === FinDesk Middle Layer Live Summary STEP-7 20260520 === */
(function() {
  let captainLoading = false;

  function t(key) {
    return typeof window.cfT === 'function' ? window.cfT(key) : key;
  }

  function statusLabel(status) {
    if (typeof qlAdvanceStatusLabel === 'function') return qlAdvanceStatusLabel(status);
    return status || '';
  }

  function renderCurrentReport(data, ledgerData, groupId, canSubmitGroup) {
    const box = document.getElementById('captainCurrentSummary');
    if (!box) return;

    if (!data.ok) {
      box.innerHTML = '<p class="soft-note">' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    const tapes = Array.isArray(data.tapes) ? data.tapes : [];
    const activeId = data.active_tape_id || null;
    const tape = tapes.find(function(item) { return activeId && String(item.id) === String(activeId); })
      || tapes.find(function(item) { return item.status === 'active' && !item.submitted_at; })
      || tapes[0]
      || null;

    if (!tape) {
      box.innerHTML = '<p class="soft-note">' + escapeHtml(t('captain.noCurrent')) + '</p>';
      return;
    }

    const summary = tape.summary || {};
    const ledgerSummary = ledgerData && ledgerData.ok && ledgerData.summary ? ledgerData.summary : null;
    const ledgerBalance = ledgerSummary ? Number(
      ledgerSummary.available_cash_balance
      ?? ledgerSummary.cash_balance
      ?? ledgerSummary.available_balance
      ?? ledgerSummary.balance
      ?? 0
    ) : null;
    const tapeBase = Number(tape.cash_received || summary.admin_cash_in || 0);
    const records = Number(summary.records_count || 0);
    const hasTapeBase = records > 0;
    const base = hasTapeBase ? tapeBase : (ledgerBalance !== null ? ledgerBalance : (Math.abs(tapeBase) > 0.009 ? tapeBase : Number(summary.cash_in || 0)));
    const extraIncome = Number(summary.extra_cash_in || 0);
    const spent = Number(summary.cash_out || 0) + Number(summary.card_out || 0);
    const left = base + extraIncome - spent;
    const submitAction = records > 0 && groupId && canSubmitGroup ? `
      <button class="primary-btn wide-btn captain-submit-current-btn" type="button" data-captain-submit-current="${escapeHtml(groupId)}" data-captain-submit-tape="${escapeHtml(tape.id || '')}">Сдать в FinDesk</button>
    ` : `
      <p class="soft-note captain-current-note">${records > 0 ? (groupId ? 'Для сдачи нужен доступ FinDesk/Advanced с правом записи.' : 'Выберите рабочую группу, чтобы сдать отчет.') : 'Сохраните строки в “Живом отчете”, и они появятся здесь как черновик.'}</p>
    `;
    const cardActions = records > 0 ? `
      <button class="ghost-btn wide-btn" type="button" data-otr-card-open="${escapeHtml(tape.id || '')}">Открыть</button>
      ${submitAction}
      <button class="ghost-btn wide-btn danger-soft-btn" type="button" data-otr-card-delete="${escapeHtml(tape.id || '')}">Удалить</button>
    ` : submitAction;

    box.innerHTML = `
      <div class="captain-current-head">
        <span>Черновик из “Живого отчета”</span>
        <b>${records ? 'Готов к сдаче' : 'Пусто'}</b>
      </div>
      <div class="captain-mini-metrics">
        <div><span>База</span><b>${qlCurrency(base + extraIncome)}</b></div>
        <div><span>${escapeHtml(t('captain.spent'))}</span><b>${qlCurrency(spent)}</b></div>
        <div><span>${escapeHtml(t('captain.left'))}</span><b>${qlCurrency(left)}</b></div>
      </div>
      <div class="captain-current-actions">${cardActions}</div>
    `;
  }

  async function loadGroupsForCaptain() {
    if (Array.isArray(qlGroups) && qlGroups.length) return qlGroups;

    const data = await qlApi('group_list', {});
    if (!data.ok) return [];

    qlGroups = data.groups || [];
    if (typeof qlRenderGroups === 'function') qlRenderGroups();
    return qlGroups;
  }

  function renderSubmitted(rows) {
    const box = document.getElementById('captainSubmittedList');
    if (!box) return;

    const activeRows = rows
      .filter(function(row) {
        return !['accepted', 'closed'].includes(row.status || '');
      })
      .slice(0, 6);

    if (!activeRows.length) {
      box.innerHTML = '<p class="soft-note">' + escapeHtml(t('captain.noSubmitted')) + '</p>';
      return;
    }

    box.innerHTML = activeRows.map(function(advance) {
      const summary = advance.summary || {};
      const employee = advance.assigned_to_display_name || advance.assigned_to_email || '';
      const group = advance.group_name || '';
      const records = Number(summary.records_count || 0);

      return `
        <article class="captain-review-row status-${escapeHtml(advance.status || '')}">
          <b>${escapeHtml(advance.title || 'FinDesk')}</b>
          <small>${escapeHtml(group)} · ${escapeHtml(employee)}</small>
          <small>${escapeHtml(statusLabel(advance.status))} · ${qlCurrency(advance.amount || 0)} · ${records} ${escapeHtml(t('captain.records'))}</small>
        </article>
      `;
    }).join('');
  }

  async function loadSubmittedReports() {
    const groups = await loadGroupsForCaptain();
    const rows = [];

    for (const group of groups.slice(0, 8)) {
      const data = await qlApi('advance_list', {
        group_id: Number(group.id),
        limit: 30
      });

      if (data.ok && Array.isArray(data.advances)) {
        data.advances.forEach(function(advance) {
          rows.push(Object.assign({}, advance, {
            group_name: advance.group_name || group.name
          }));
        });
      }
    }

    rows.sort(function(a, b) {
      const priority = {submitted: 0, discrepancy: 1, returned: 2, issued: 3, accepted: 4, closed: 5};
      const pa = priority[a.status] ?? 9;
      const pb = priority[b.status] ?? 9;
      if (pa !== pb) return pa - pb;
      return String(b.created_at || '').localeCompare(String(a.created_at || ''));
    });

    renderSubmitted(rows);
  }

  async function qlLoadCaptainFin() {
    if (document.getElementById('captainCardView')) return;
    if (captainLoading) return;
    captainLoading = true;

    const current = document.getElementById('captainCurrentSummary');
    const submitted = document.getElementById('captainSubmittedList');
    if (current) current.innerHTML = '<p class="soft-note">' + escapeHtml(t('captain.loading')) + '</p>';
    if (submitted) submitted.innerHTML = '<p class="soft-note">' + escapeHtml(t('captain.loading')) + '</p>';

    try {
      const groups = await loadGroupsForCaptain();
      const group = groups.find(function(row) {
        return qlAdvanceGroupId && String(row.id) === String(qlAdvanceGroupId);
      }) || groups.find(function(row) {
        const access = String(row.access_level || '').toLowerCase();
        const role = String(row.role || '').toLowerCase();
        const permissions = row.permissions || {};
        return access === 'advanced' || role === 'admin' || permissions.can_view_group_reports || permissions.can_manage_money;
      }) || groups[0] || null;
      const payload = group && group.id ? {group_id: Number(group.id)} : {};
      const results = await Promise.all([
        qlApi('on_the_go_tape_list', payload),
        qlApi('ledger_balance', payload)
      ]);
      renderCurrentReport(results[0], results[1], group && group.id ? Number(group.id) : 0, group ? qlCaptainCanWriteGroup(group) : false);
      await loadSubmittedReports();
    } finally {
      captainLoading = false;
    }
  }

  const previousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);
  window.qlSetModule = function(moduleName, options) {
    if (typeof previousSetModule === 'function') {
      previousSetModule(moduleName, options);
    }

    if (moduleName === 'captain') {
      setTimeout(qlLoadCaptainFin, 80);
    }
  };

  try {
    qlSetModule = window.qlSetModule;
  } catch (error) {}

  function refreshFinDeskOnLanguageChange() {
    const module = document.getElementById('moduleCaptain');
    if (module && !module.classList.contains('hidden')) {
      qlLoadCaptainFin();
    }
  }

  window.addEventListener('findesk:languagechange', refreshFinDeskOnLanguageChange);
  window.addEventListener('captainfin:languagechange', refreshFinDeskOnLanguageChange);

  window.qlLoadCaptainFin = qlLoadCaptainFin;
})();

/* === FinDesk Admin Desk Layer 20260520 === */
(function() {
  let captainGroupId = null;
  let captainMembers = [];
  let captainAdvances = [];
  let captainOtrReports = [];
  let captainOtrArchive = [];
  let captainCurrentReviewId = null;
  let captainCurrentReviewStatus = '';
  let captainLoading = false;
  let captainLedgerData = null;
  let captainFinalizing = false;
  let captainParticipantRows = [];
  let captainActiveCard = {type: 'board', id: ''};

  function captainStatus(message) {
    const el = document.getElementById('captainStatus');
    if (el) el.textContent = message || '';
  }

  function money(value) {
    return typeof qlCurrency === 'function' ? qlCurrency(value || 0) : '€' + Number(value || 0).toFixed(2);
  }

  function statusLabel(status) {
    return typeof qlAdvanceStatusLabel === 'function' ? qlAdvanceStatusLabel(status) : (status || 'Отчет');
  }

  function typeLabel(type) {
    if (type === 'cash_in') return 'Приход';
    if (type === 'cash_out') return 'Расход наличными';
    if (type === 'noncash_out') return 'Расход безнал';
    return 'Запись';
  }

  function firstNumber() {
    for (let i = 0; i < arguments.length; i++) {
      const value = Number(arguments[i]);
      if (Number.isFinite(value)) return value;
    }
    return 0;
  }

  function reportOwnerId(report) {
    return String(report && (report.user_id || report.owner_user_id || report.assigned_to_user_id || '')) || '';
  }

  function reportOwnerName(report) {
    return report && (report.user_display_name || report.assigned_to_display_name || report.email || report.assigned_to_email) || 'Участник';
  }

  function advanceOwnerId(advance) {
    return String(advance && (advance.assigned_to_user_id || advance.user_id || '')) || '';
  }

  function advanceOwnerName(advance) {
    return advance && (advance.assigned_to_display_name || advance.assigned_to_email || 'Участник') || 'Участник';
  }

  function currentUserId() {
    return qlCurrentUser && qlCurrentUser.id ? String(qlCurrentUser.id) : '';
  }

  function participantInitials(name) {
    const clean = String(name || 'У').trim();
    const parts = clean.split(/\s+/).filter(Boolean);
    if (!parts.length) return 'У';
    return parts.slice(0, 2).map(function(part) { return part.charAt(0).toUpperCase(); }).join('');
  }

  function reportStream(report) {
    return report && report.stream_type === 'card' ? 'card' : 'cash';
  }

  function reportStreamLabel(report) {
    return reportStream(report) === 'card' ? 'Карта' : 'Наличные';
  }

  function reportDisplayTitle(report, fallback) {
    const stream = reportStream(report);
    if (stream === 'card') return 'Карточный отчет';
    const title = String(report && report.title ? report.title : '').trim();
    if (title && title !== 'On the Go' && title !== 'Живой отчет') return title;
    return fallback || 'Наличный отчет';
  }

  function reportMainAmount(report) {
    const s = report && report.summary ? report.summary : {};
    return reportStream(report) === 'card'
      ? Number(s.card_out || 0)
      : Number(s.after_amount ?? s.cash_left ?? 0);
  }

  function reportMainLabel(report) {
    return reportStream(report) === 'card' ? 'Потрачено с карты' : 'Остаток наличными';
  }

  function reportMoneyLine(report) {
    const s = report && report.summary ? report.summary : {};
    if (reportStream(report) === 'card') {
      return 'Карта · потрачено ' + money(s.card_out || 0) + ' · наличная касса не меняется';
    }

    return 'Наличные · было ' + money(s.before_amount || 0)
      + ' · расход ' + money(s.cash_out || 0)
      + ' · остаток ' + money(s.after_amount ?? s.cash_left ?? 0);
  }

  function selectedGroup() {
    return (qlGroups || []).find(function(group) {
      return String(group.id) === String(captainGroupId || '');
    }) || null;
  }

  function groupCanManage(group) {
    if (!group) return false;
    const permissions = group.permissions || {};
    return group.access_level === 'advanced' || group.role === 'admin' || !!permissions.can_manage_money || !!permissions.can_manage_members;
  }

  function groupCanViewArchive(group) {
    if (!group) return false;
    return group.access_level === 'advanced' || group.role === 'admin';
  }

  function groupCanModerate(group) {
    if (!group) return false;
    const permissions = group.permissions || {};
    return groupCanManage(group) || group.access_level === 'manager' || !!permissions.can_moderate || !!permissions.can_view_group_reports;
  }

  function ensureCaptainGroup() {
    const groups = Array.isArray(qlGroups) ? qlGroups : [];
    const advancedGroup = groups.find(function(group) {
      return qlAdvanceGroupId && String(group.id) === String(qlAdvanceGroupId) && groupCanModerate(group);
    });
    if (advancedGroup) {
      captainGroupId = Number(advancedGroup.id);
      window.qlCaptainActiveGroupId = captainGroupId;
      return;
    }

    if (captainGroupId && groups.some(function(group) { return String(group.id) === String(captainGroupId); })) {
      window.qlCaptainActiveGroupId = Number(captainGroupId);
      return;
    }

    const preferred = groups.find(groupCanModerate) || groups[0] || null;
    captainGroupId = preferred ? Number(preferred.id) : null;
    window.qlCaptainActiveGroupId = captainGroupId;
  }

  function renderCaptainGroupSelect() {
    const select = document.getElementById('captainGroupSelect');
    if (!select) return;

    ensureCaptainGroup();

    const groups = Array.isArray(qlGroups) ? qlGroups : [];
    select.innerHTML = '<option value="">Выберите группу</option>' + groups.map(function(group) {
      const level = group.access_level || group.role || 'base';
      return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(level) + '</option>';
    }).join('');
    select.value = captainGroupId ? String(captainGroupId) : '';
  }

  function renderCaptainBalances(ledgerData) {
    const adminEl = document.getElementById('captainAdminCashLeft');
    const employeeEl = document.getElementById('captainEmployeeCashLeft');
    const amountEl = document.getElementById('captainAdminReportAmount');
    const summary = ledgerData && ledgerData.ok && ledgerData.summary ? ledgerData.summary : {};
    const adminCash = firstNumber(
      summary.available_cash_balance,
      summary.cash_balance,
      summary.available_balance,
      summary.balance
    );
    const employeeCash = firstNumber(summary.accountable_cash_left_open, 0);

    if (adminEl) adminEl.textContent = money(adminCash);
    if (employeeEl) employeeEl.textContent = money(employeeCash);
    if (amountEl && !amountEl.dataset.liveAmount) amountEl.textContent = money(adminCash);
  }

  function captainAdminCashValue() {
    const summary = captainLedgerData && captainLedgerData.ok && captainLedgerData.summary ? captainLedgerData.summary : {};
    return firstNumber(
      summary.available_cash_balance,
      summary.cash_balance,
      summary.available_balance,
      summary.balance
    );
  }

  function captainRowStatusClass(row) {
    if (!row) return '';
    if (row.submitted > 0) return 'is-submitted';
    if (row.included > 0) return 'is-included';
    if (row.assigned > 0) return 'is-assigned';
    return 'is-passive';
  }

  function captainRowStatusDots(row) {
    const dots = [];
    if (row && row.submitted > 0) dots.push('<span class="captain-card-dot orange" title="Отчет сдан"></span>');
    if (row && row.assigned > 0 && row.submitted <= 0 && row.included <= 0) dots.push('<span class="captain-card-dot red" title="Деньги на руках"></span>');
    if (row && row.included > 0 && row.submitted <= 0) dots.push('<span class="captain-card-dot green" title="Проверено"></span>');
    return dots.length ? '<span class="captain-card-dots">' + dots.join('') + '</span>' : '';
  }

  function renderCaptainBoard(rows) {
    const box = document.getElementById('captainSubmittedList');
    if (!box) return;
    const adminCash = captainAdminCashValue();
    const participantButtons = (rows || []).filter(function(row) {
      return row.id !== currentUserId();
    }).slice(0, 8).map(function(row) {
      return `
        <button class="captain-card-button person ${captainRowStatusClass(row)}" type="button" data-captain-open-card="participant" data-captain-card-id="${escapeHtml(row.id)}">
          <span><b>${escapeHtml(row.name)}</b></span>
          <strong>${money(row.left)}</strong>
          ${captainRowStatusDots(row)}
        </button>
      `;
    }).join('');
    const redLamp = '<span class="captain-ref-lamp red"></span>';

    box.innerHTML = `
      <section class="captain-ref-work">
        <div class="captain-ref-top">
          <div>
            <h2>Входящие отчеты</h2>
            <p>Проверка живых отчетов, подотчетов и сбор общего пакета.</p>
          </div>
          <button class="captain-ref-pill" type="button" data-mode-open="ontherun">${redLamp} Живой отчет</button>
        </div>

        <div class="captain-ref-switchboard">
          <button class="captain-ref-card main-card" type="button" data-captain-open-card="admin" data-captain-card-id="admin">
            <span class="card-title">Администратор</span>
            <span class="card-sub">${money(adminCash)}</span>
            <span class="captain-ref-lamp red floating-lamp"></span>
          </button>
        </div>

        <div class="captain-ref-subtitle"><strong>Карточки сотрудников</strong><span></span></div>

        <div class="captain-ref-tree people-tree">
          <div class="captain-ref-subgrid people-grid">
            ${participantButtons || '<p class="soft-note">Карточек сотрудников пока нет.</p>'}
          </div>
        </div>
      </section>
    `;
  }

  function closeCaptainCardView() {
    captainActiveCard = {type: 'board', id: ''};
    renderCaptainCardView();
  }

  function openCaptainCardView(type, id) {
    captainActiveCard = {type: type || 'participant', id: String(id || '')};
    renderCaptainCardView();
  }

  function renderCaptainCardView() {
    const home = document.getElementById('captainBoardHome');
    const view = document.getElementById('captainCardView');
    const adminWork = document.getElementById('captainAdminWork');
    const participantWork = document.getElementById('captainParticipantWork');
    const details = document.getElementById('captainDetailsPanel');
    const module = document.getElementById('moduleCaptain');
    const title = document.getElementById('captainCardViewTitle');
    const kicker = document.getElementById('captainCardViewKicker');
    const amount = document.getElementById('captainCardViewAmount');
    if (!home || !view) return;

    const isOpen = captainActiveCard && captainActiveCard.type && captainActiveCard.type !== 'board';
    home.classList.toggle('hidden', isOpen);
    view.classList.toggle('hidden', !isOpen);
    if (details) details.classList.toggle('hidden', isOpen);
    if (module) {
      module.querySelectorAll('.captain-ref-brand, .captain-balance-strip, .captain-admin-bar').forEach(function(el) {
        el.classList.toggle('hidden', isOpen);
      });
    }
    if (!isOpen) return;

    const isAdmin = captainActiveCard.type === 'admin';
    const row = !isAdmin ? captainParticipantRows.find(function(item) {
      return String(item.id) === String(captainActiveCard.id);
    }) : null;

    if (adminWork) adminWork.classList.toggle('hidden', !isAdmin);
    if (participantWork) participantWork.classList.toggle('hidden', isAdmin);

    if (isAdmin) {
      if (kicker) kicker.textContent = 'Карточка администратора';
      if (title) title.textContent = 'Администратор';
      if (amount) amount.textContent = money(captainAdminCashValue());
      return;
    }

    if (!row) {
      closeCaptainCardView();
      return;
    }

    if (kicker) kicker.textContent = row.submitted > 0 ? 'Отчет сдан' : row.included > 0 ? 'Проверено' : 'Карточка сотрудника';
    if (title) title.textContent = row.name || 'Участник';
    if (amount) amount.textContent = money(row.left);
    if (participantWork) participantWork.innerHTML = renderEmployeeCard(row);
  }

  function renderCurrentReport(data, ledgerData) {
    const box = document.getElementById('captainCurrentSummary');
    if (!box) return;

    if (!data.ok) {
      box.innerHTML = '<p class="soft-note">' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    const tapes = Array.isArray(data.tapes) ? data.tapes : [];
    const activeId = data.active_tape_id || null;
    const tape = tapes.find(function(item) { return activeId && String(item.id) === String(activeId); })
      || tapes.find(function(item) { return item.status === 'active' && !item.submitted_at; })
      || tapes[0]
      || null;
    if (!tape) {
      box.innerHTML = '<p class="soft-note">Нет текущего живого отчета.</p>';
      return;
    }

    const s = tape.summary || {};
    const ledgerSummary = ledgerData && ledgerData.ok && ledgerData.summary ? ledgerData.summary : null;
    const ledgerBalance = ledgerSummary ? Number(
      ledgerSummary.available_cash_balance
      ?? ledgerSummary.cash_balance
      ?? ledgerSummary.available_balance
      ?? ledgerSummary.balance
      ?? 0
    ) : null;
    const tapeBase = Number(tape.cash_received || s.admin_cash_in || 0);
    const records = Number(s.records_count || 0);
    const hasTapeBase = records > 0;
    const base = hasTapeBase ? tapeBase : (ledgerBalance !== null ? ledgerBalance : (Math.abs(tapeBase) > 0.009 ? tapeBase : Number(s.cash_in || 0)));
    const extraIncome = Number(s.extra_cash_in || 0);
    const spent = Number(s.cash_out || 0) + Number(s.card_out || 0);
    const left = base + extraIncome - spent;
    const canSubmitCurrent = qlCaptainCanWriteGroup(selectedGroup());
    const submitAction = records > 0 && captainGroupId && canSubmitCurrent ? `
      <button class="primary-btn wide-btn captain-submit-current-btn" type="button" data-captain-submit-current="${escapeHtml(captainGroupId)}" data-captain-submit-tape="${escapeHtml(tape.id || '')}">Сдать в FinDesk</button>
    ` : `
      <p class="soft-note captain-current-note">${records > 0 ? (captainGroupId ? 'Для сдачи нужен доступ FinDesk/Advanced с правом записи.' : 'Выберите рабочую группу, чтобы сдать отчет.') : 'Сохраните строки в “Живом отчете”, и они появятся здесь как черновик.'}</p>
    `;
    const cardActions = records > 0 ? `
      <button class="ghost-btn wide-btn" type="button" data-otr-card-open="${escapeHtml(tape.id || '')}">Открыть</button>
      ${submitAction}
      <button class="ghost-btn wide-btn danger-soft-btn" type="button" data-otr-card-delete="${escapeHtml(tape.id || '')}">Удалить</button>
    ` : submitAction;
    const amountEl = document.getElementById('captainAdminReportAmount');
    if (amountEl) {
      amountEl.dataset.liveAmount = '1';
      amountEl.textContent = money(left);
    }

    box.innerHTML = `
      <div class="captain-current-head captain-admin-current-head">
        <span>Быстрая запись администратора</span>
        <b>${records ? 'Готова к сдаче' : 'Ожидает строк'}</b>
      </div>
      <div class="captain-mini-metrics">
        <div><span>Общий остаток</span><b>${money(base + extraIncome)}</b></div>
        <div><span>Потрачено</span><b>${money(spent)}</b></div>
        <div><span>Остаток</span><b>${money(left)}</b></div>
      </div>
      <div class="captain-current-actions">${cardActions}</div>
    `;
  }

  function memberByUserId(userId) {
    const id = String(userId || '');
    return captainMembers.find(function(member) {
      return String(member.user_id || '') === id;
    }) || null;
  }

  function participantRows(submittedOtr, submittedAdvances, includedOtr, acceptedAdvances, assignedAdvances) {
    const rows = new Map();

    function ensure(id, name, email, role) {
      const key = String(id || name || email || ('row-' + rows.size));
      if (!rows.has(key)) {
        rows.set(key, {
          id: key,
          name: name || 'Участник',
          email: email || '',
          role: role || '',
          submitted: 0,
          included: 0,
          assigned: 0,
          received: 0,
          left: 0,
          latest: null,
          latestType: ''
        });
      }
      return rows.get(key);
    }

    (captainMembers || []).forEach(function(member) {
      ensure(
        member.user_id,
        member.display_name || member.email || 'Участник',
        member.email || '',
        member.access_level || member.role || ''
      );
    });

    (submittedOtr || []).forEach(function(report) {
      const s = report.summary || {};
      const row = ensure(reportOwnerId(report), reportOwnerName(report), report.email || '', '');
      row.submitted++;
      row.received += firstNumber(report.cash_received, s.before_amount, s.cash_in, 0);
      row.left += firstNumber(s.after_amount, s.cash_left, report.actual_remaining, 0);
      row.latest = report;
      row.latestType = 'otr-submitted';
    });

    (submittedAdvances || []).forEach(function(advance) {
      const s = advance.summary || {};
      const row = ensure(advanceOwnerId(advance), advanceOwnerName(advance), advance.assigned_to_email || '', '');
      row.submitted++;
      row.received += firstNumber(advance.amount, s.cash_in, 0);
      row.left += firstNumber(s.effective_cash_left, s.cash_left, advance.actual_remaining, 0);
      row.latest = advance;
      row.latestType = 'advance-submitted';
    });

    (includedOtr || []).forEach(function(report) {
      const s = report.summary || {};
      const row = ensure(reportOwnerId(report), reportOwnerName(report), report.email || '', '');
      row.included++;
      row.received += firstNumber(report.cash_received, s.before_amount, s.cash_in, 0);
      row.left += firstNumber(s.after_amount, s.cash_left, report.actual_remaining, 0);
      if (!row.latest) {
        row.latest = report;
        row.latestType = 'otr-included';
      }
    });

    (acceptedAdvances || []).forEach(function(advance) {
      const s = advance.summary || {};
      const row = ensure(advanceOwnerId(advance), advanceOwnerName(advance), advance.assigned_to_email || '', '');
      row.included++;
      row.received += firstNumber(advance.amount, s.cash_in, 0);
      row.left += firstNumber(s.effective_cash_left, s.cash_left, advance.actual_remaining, 0);
      if (!row.latest) {
        row.latest = advance;
        row.latestType = 'advance-accepted';
      }
    });

    (assignedAdvances || []).forEach(function(advance) {
      const s = advance.summary || {};
      const row = ensure(advanceOwnerId(advance), advanceOwnerName(advance), advance.assigned_to_email || '', '');
      row.assigned++;
      row.received += firstNumber(advance.amount, s.cash_in, 0);
      row.left += firstNumber(s.effective_cash_left, s.cash_left, advance.actual_remaining, 0);
      if (!row.latest) {
        row.latest = advance;
        row.latestType = 'advance-open';
      }
    });

    return Array.from(rows.values()).sort(function(a, b) {
      if ((a.id === currentUserId()) !== (b.id === currentUserId())) return a.id === currentUserId() ? -1 : 1;
      if ((a.submitted > 0) !== (b.submitted > 0)) return a.submitted > 0 ? -1 : 1;
      if ((a.included > 0) !== (b.included > 0)) return a.included > 0 ? -1 : 1;
      return a.name.localeCompare(b.name, 'ru');
    });
  }

  function renderParticipantStrip(rows) {
    const box = document.getElementById('captainParticipantStrip');
    if (!box) return;
    if (!rows.length) {
      box.innerHTML = '<p class="soft-note">В группе пока нет участников.</p>';
      return;
    }

    box.innerHTML = rows.slice(0, 12).map(function(row) {
      const own = row.id === currentUserId();
      const active = row.submitted > 0;
      const done = !active && row.included > 0;
      return `
        <span class="captain-participant-chip ${own ? 'is-admin' : ''} ${active ? 'is-submitted' : ''} ${done ? 'is-included' : ''}">
          <i>${escapeHtml(participantInitials(own ? 'Администратор' : row.name))}</i>
          <b>${escapeHtml(own ? 'Администратор' : row.name)}</b>
        </span>
      `;
    }).join('');
  }

  function liveReportActions(report, included) {
    const id = escapeHtml(report.id || report.tape_id || '');
    if (!id) return '';
    const canReturn = report.can_return !== false;
    if (included) {
      return `
        <button class="ghost-btn" type="button" data-captain-open-otr-card="${id}">Открыть</button>
        <button class="ghost-btn danger-soft-btn" type="button" data-otr-card-uninclude="${id}">Вернуть в проверку</button>
      `;
    }
    return `
      <button class="ghost-btn" type="button" data-captain-open-otr-card="${id}">Открыть</button>
      <button class="primary-btn" type="button" data-otr-card-include="${id}">Утвердить</button>
      ${canReturn ? '<button class="ghost-btn danger-soft-btn" type="button" data-otr-card-unsubmit="' + id + '">Вернуть</button>' : ''}
    `;
  }

  function advanceActions(advance, accepted) {
    const id = escapeHtml(advance.id || '');
    if (!id) return '';
    if (accepted) {
      return `
        <button class="ghost-btn" type="button" data-captain-open-review="${id}">Открыть</button>
        <button class="ghost-btn danger-soft-btn" type="button" data-captain-unaccept="${id}">Вернуть</button>
      `;
    }
    return `
      <button class="ghost-btn" type="button" data-captain-open-review="${id}">Открыть</button>
      <button class="primary-btn" type="button" data-captain-accept="${id}">Утвердить</button>
      <button class="ghost-btn danger-soft-btn" type="button" data-captain-return="${id}">Вернуть</button>
    `;
  }

  function renderEmployeeCard(row) {
    const isOwn = row.id === currentUserId();
    const latest = row.latest || {};
    const type = row.latestType || '';
    const submitted = row.submitted > 0;
    const included = row.included > 0 && !submitted;
    const passive = !submitted && !included && row.assigned <= 0;
    const title = isOwn ? 'Администратор' : row.name;
    let actions = '';
    if (type === 'otr-submitted') actions = liveReportActions(latest, false);
    if (type === 'otr-included') actions = liveReportActions(latest, true);
    if (type === 'advance-submitted') actions = advanceActions(latest, false);
    if (type === 'advance-accepted') actions = advanceActions(latest, true);
    if (type === 'advance-open' && latest.id) actions = '<button class="ghost-btn" type="button" data-captain-open-review="' + escapeHtml(latest.id) + '">Открыть</button>';

    return `
      <article class="captain-employee-card ${submitted ? 'is-submitted' : ''} ${included ? 'is-included' : ''} ${passive ? 'is-passive' : ''} ${isOwn ? 'is-admin' : ''}">
        <div class="captain-employee-head">
          <span class="captain-employee-avatar">${escapeHtml(participantInitials(title))}</span>
          <div>
            <b>${escapeHtml(title)}</b>
            <small>${escapeHtml(submitted ? 'Сдано на проверку' : included ? 'Проверен' : row.assigned > 0 ? 'Деньги на руках' : 'Пассивен')}</small>
          </div>
        </div>
        <div class="captain-employee-money">
          <span>Принял <b>${money(row.received)}</b></span>
          <span>Остаток <b>${money(row.left)}</b></span>
        </div>
        ${actions ? '<div class="captain-row-actions">' + actions + '</div>' : ''}
      </article>
    `;
  }

  function renderCaptainChildReports(includedOtr, acceptedAdvances) {
    const rows = [];
    (includedOtr || []).forEach(function(report) {
      const s = report.summary || {};
      rows.push(`
        <article class="captain-child-card">
          <span>Дочерний отчет · Проверен</span>
          <b>${escapeHtml(reportOwnerName(report))}</b>
          <small>Принял ${money(firstNumber(report.cash_received, s.before_amount, s.cash_in, 0))} · остаток ${money(firstNumber(s.after_amount, s.cash_left, report.actual_remaining, 0))}</small>
        </article>
      `);
    });
    (acceptedAdvances || []).forEach(function(advance) {
      const s = advance.summary || {};
      rows.push(`
        <article class="captain-child-card">
          <span>Подотчет · Проверен</span>
          <b>${escapeHtml(advanceOwnerName(advance))}</b>
          <small>Принял ${money(firstNumber(advance.amount, s.cash_in, 0))} · остаток ${money(firstNumber(s.effective_cash_left, s.cash_left, advance.actual_remaining, 0))}</small>
        </article>
      `);
    });

    return rows.length
      ? rows.slice(0, 8).join('')
      : '<p class="soft-note">После утверждения отчеты сотрудников прикрепятся сюда как дочерние карточки.</p>';
  }

  function renderCaptainMembers() {
    const box = document.getElementById('captainMemberList');
    const issueSelect = document.getElementById('captainIssueMemberSelect');
    const group = selectedGroup();
    const canManage = groupCanManage(group);

    if (issueSelect) {
      issueSelect.innerHTML = '<option value="">Выберите исполнителя</option>' + captainMembers.map(function(member) {
        const label = (member.display_name || member.email || 'Участник') + ' · ' + (member.access_level || member.role || 'base');
        return '<option value="' + escapeHtml(member.user_id) + '">' + escapeHtml(label) + '</option>';
      }).join('');
      issueSelect.disabled = !canManage;
    }

    document.querySelectorAll('#captainIssuePanel input, #captainIssuePanel button').forEach(function(el) {
      el.disabled = !canManage;
    });

    if (!box) return;
    if (!group) {
      box.innerHTML = '<p class="soft-note">Создайте или выберите группу.</p>';
      return;
    }

    if (!captainMembers.length) {
      box.innerHTML = '<p class="soft-note">Участники пока не загружены.</p>';
      return;
    }

    box.innerHTML = captainMembers.map(function(member) {
      const access = member.access_level || member.role || 'base';
      const control = canManage ? `
        <select class="ql-input" data-captain-member-access="${escapeHtml(member.user_id)}">
          <option value="base" ${access === 'base' ? 'selected' : ''}>Живой отчет</option>
          <option value="manager" ${access === 'manager' ? 'selected' : ''}>FinDesk</option>
          <option value="advanced" ${access === 'advanced' ? 'selected' : ''}>Advanced</option>
        </select>
      ` : '<small>' + escapeHtml(access) + '</small>';

      return `
        <article class="captain-member-row">
          <span>
            <b>${escapeHtml(member.display_name || member.email || 'Участник')}</b>
            <small>${escapeHtml(member.email || '')} · ${escapeHtml(member.role || '')}</small>
          </span>
          ${control}
        </article>
      `;
    }).join('');
  }

  function rowActions(advance) {
    const status = advance.status || '';
    const s = advance.summary || {};
    const canReview = ['submitted', 'discrepancy'].includes(status);
    const canOpen = !!advance.id;
    const group = selectedGroup();
    const canUnaccept = !!(canOpen && groupCanModerate(group) && status === 'accepted');
    const canCancel = !!(canOpen && groupCanManage(group) && ['issued', 'submitted', 'returned', 'discrepancy'].includes(status));
    const canReturnCash = !!(
      canOpen
      && groupCanManage(group)
      && ['issued', 'returned'].includes(status)
      && Number(s.records_count || 0) === 0
      && Number(s.cash_left || advance.amount || 0) > 0
    );
    let html = '<div class="captain-row-actions">';
    if (canReview && canOpen) {
      html += '<button class="ghost-btn" type="button" data-captain-open-review="' + escapeHtml(advance.id) + '">Открыть</button>';
      html += '<button class="primary-btn" type="button" data-captain-accept="' + escapeHtml(advance.id) + '">Включить</button>';
      html += '<button class="ghost-btn danger-soft-btn" type="button" data-captain-return="' + escapeHtml(advance.id) + '">Вернуть</button>';
      if (canCancel) {
        html += '<button class="ghost-btn danger-soft-btn" type="button" data-captain-cancel="' + escapeHtml(advance.id) + '">Отменить</button>';
      }
    } else if (canOpen) {
      html += '<button class="ghost-btn" type="button" data-captain-open-review="' + escapeHtml(advance.id) + '">Проверить</button>';
      if (canUnaccept) {
        html += '<button class="ghost-btn danger-soft-btn" type="button" data-captain-unaccept="' + escapeHtml(advance.id) + '">Вернуть на доработку</button>';
      }
      if (canReturnCash) {
        html += '<button class="ghost-btn" type="button" data-captain-return-cash="' + escapeHtml(advance.id) + '">В кассу</button>';
      }
      if (canCancel) {
        html += '<button class="ghost-btn danger-soft-btn" type="button" data-captain-cancel="' + escapeHtml(advance.id) + '">Отменить</button>';
      }
    }
    html += '</div>';
    return html;
  }

  function renderAdvanceRow(advance) {
    const s = advance.summary || {};
    const status = advance.status || 'issued';
    const employee = advance.assigned_to_display_name || advance.assigned_to_email || 'Исполнитель';
    const spent = Number(s.cash_out || 0) + Number(s.card_out || 0);
    const reviewAmount = ['submitted', 'discrepancy', 'accepted', 'closed'].includes(status)
      ? spent
      : Number(advance.amount || 0);
    const amountLabel = ['submitted', 'discrepancy'].includes(status)
      ? 'К утверждению'
      : ['accepted', 'closed'].includes(status)
        ? 'Принято'
        : 'Выдано';

    return `
      <article class="captain-review-row status-${escapeHtml(status)} ${['submitted','discrepancy'].includes(status) ? 'is-actionable' : ''}">
        <div class="captain-review-row-top">
          <b>${escapeHtml(advance.title || 'Отчет')}</b>
          <strong>${money(reviewAmount)}</strong>
        </div>
        <small>${escapeHtml(employee)} · ${escapeHtml(advance.assigned_to_email || '')}</small>
        <small>${escapeHtml(amountLabel)} · выдано ${money(advance.amount || 0)} · остаток ${money(s.cash_left || 0)}</small>
        ${rowActions(advance)}
      </article>
    `;
  }

  function renderOtrReportRow(report) {
    const s = report.summary || {};
    const mainAmount = reportMainAmount(report);
    const archivedNote = report.archived_at ? ' · в архиве живого журнала' : '';
    const requestNote = report.return_requested_at
      ? '<small class="return-request-note">Сотрудник просит вернуть на доработку · ' + escapeHtml(report.return_requested_at) + '</small>'
      : '';

    return `
      <article class="captain-review-row status-accepted">
        <div class="captain-review-row-top">
          <b>${escapeHtml(reportMainLabel(report))}</b>
          <strong>${money(mainAmount)}</strong>
        </div>
        <small>${escapeHtml(report.user_display_name || report.email || 'Участник')} · ${escapeHtml(reportStreamLabel(report))}${escapeHtml(archivedNote)}</small>
        <small>${escapeHtml(reportMoneyLine(report))}</small>
        ${requestNote}
        <div class="captain-row-actions">
          <button class="ghost-btn" type="button" data-captain-open-otr-card="${escapeHtml(report.id || report.tape_id)}">Открыть</button>
          <button class="ghost-btn danger-soft-btn" type="button" data-otr-card-uninclude="${escapeHtml(report.id || report.tape_id)}">Убрать из отчета</button>
        </div>
      </article>
    `;
  }

  function renderOtrArchiveRow(report) {
    const mainAmount = reportMainAmount(report);
    const state = report.card_state === 'included'
      ? 'Включено в отчет'
      : report.card_state === 'submitted'
        ? 'На проверке'
        : report.card_state === 'draft'
          ? 'Черновик'
          : 'Живой отчет';
    const date = report.archived_at || report.submitted_at || report.updated_at || report.created_at || '';
    return `
      <article class="captain-review-row status-archived">
        <div class="captain-review-row-top">
          <b>${escapeHtml(reportDisplayTitle(report, 'Живой отчет'))}</b>
          <strong>${money(mainAmount)}</strong>
        </div>
        <small>${escapeHtml(state)} · ${escapeHtml(reportStreamLabel(report))} · ${escapeHtml(date)}</small>
        <small>${escapeHtml(reportMoneyLine(report))}</small>
        <div class="captain-row-actions">
          <button class="ghost-btn" type="button" data-captain-open-otr-card="${escapeHtml(report.id || report.tape_id)}">Открыть</button>
        </div>
      </article>
    `;
  }

  function archiveRecordsCount(rows) {
    return (rows || []).reduce(function(sum, report) {
      const s = report.summary || {};
      const count = Number(s.records_count || 0);
      return sum + (Number.isFinite(count) ? count : 0);
    }, 0);
  }

  function archiveOwnerName(report) {
    return report.user_display_name || report.email || 'Участник';
  }

  function archiveOwnerKey(report) {
    return String(report.user_id || report.email || archiveOwnerName(report));
  }

  function groupArchiveRowsByOwner(rows) {
    const groups = new Map();
    (rows || []).forEach(function(report) {
      const key = archiveOwnerKey(report);
      if (!groups.has(key)) {
        groups.set(key, {
          key: key,
          name: archiveOwnerName(report),
          isOwn: Boolean(report.viewer_is_owner),
          rows: []
        });
      }
      groups.get(key).rows.push(report);
    });

    return Array.from(groups.values()).sort(function(a, b) {
      if (a.isOwn !== b.isOwn) return a.isOwn ? -1 : 1;
      return a.name.localeCompare(b.name, 'ru');
    });
  }

  function groupArchiveFoldersWithMembers(rows) {
    const folders = new Map();
    (captainMembers || []).forEach(function(member) {
      const key = String(member.user_id || member.email || member.display_name || '');
      if (!key) return;
      folders.set(key, {
        key: key,
        name: member.display_name || member.email || 'Участник',
        email: member.email || '',
        isOwn: qlCurrentUser && member.user_id && String(member.user_id) === String(qlCurrentUser.id),
        rows: []
      });
    });

    (rows || []).forEach(function(report) {
      const key = archiveOwnerKey(report);
      if (!folders.has(key)) {
        folders.set(key, {
          key: key,
          name: archiveOwnerName(report),
          email: report.email || '',
          isOwn: Boolean(report.viewer_is_owner),
          rows: []
        });
      }
      folders.get(key).rows.push(report);
    });

    return Array.from(folders.values()).sort(function(a, b) {
      if (a.isOwn !== b.isOwn) return a.isOwn ? -1 : 1;
      return a.name.localeCompare(b.name, 'ru');
    });
  }

  function renderArchivePackSummary(rows, canSeeArchive) {
    if (!canSeeArchive) {
      return '<p class="soft-note">Архив группы доступен администратору.</p>';
    }

    const cards = rows.length;
    const records = archiveRecordsCount(rows);
    const owners = groupArchiveFoldersWithMembers(rows).length;

    if (!cards && !owners) {
      return '<p class="soft-note">В группе пока нет живых отчетов.</p>';
    }

    return `
      <div class="captain-pack-summary captain-archive-summary">
        <div class="captain-current-head">
          <span>Живые отчеты группы</span>
          <b>${escapeHtml(records)}</b>
        </div>
        <div class="captain-mini-metrics">
          <div><span>Карточки</span><b>${escapeHtml(cards)}</b></div>
          <div><span>Сотрудники</span><b>${escapeHtml(owners)}</b></div>
        </div>
        <p class="captain-pack-note">Общий обзор живых отчетов по сотрудникам группы.</p>
      </div>
    `;
  }

  function renderOtrSubmittedRow(report) {
    const mainAmount = reportMainAmount(report);
    const requestNote = report.return_requested_at
      ? '<small class="return-request-note">Сотрудник просит вернуть на доработку · ' + escapeHtml(report.return_requested_at) + '</small>'
      : '';
    return `
      <article class="captain-review-row status-submitted is-actionable">
        <div class="captain-review-row-top">
          <b>${escapeHtml(reportDisplayTitle(report, 'Живой отчет'))}</b>
          <strong>${money(mainAmount)}</strong>
        </div>
        <small>${escapeHtml(report.user_display_name || report.email || 'Участник')} · ${escapeHtml(reportStreamLabel(report))} · ${escapeHtml(report.submitted_at || report.updated_at || report.created_at || '')}</small>
        <small>${escapeHtml(reportMoneyLine(report))}</small>
        ${requestNote}
        <div class="captain-row-actions">
          <button class="ghost-btn" type="button" data-captain-open-otr-card="${escapeHtml(report.id || report.tape_id)}">Открыть</button>
          <button class="primary-btn" type="button" data-otr-card-include="${escapeHtml(report.id || report.tape_id)}">Включить</button>
          <button class="ghost-btn danger-soft-btn" type="button" data-otr-card-unsubmit="${escapeHtml(report.id || report.tape_id)}">Вернуть на исправление</button>
        </div>
      </article>
    `;
  }

  function renderOtrDraftRow(report) {
    const mainAmount = reportMainAmount(report);
    return `
      <article class="captain-review-row status-issued">
        <div class="captain-review-row-top">
          <b>${escapeHtml(reportDisplayTitle(report, 'Черновик живого отчета'))}</b>
          <strong>${money(mainAmount)}</strong>
        </div>
        <small>${escapeHtml(report.user_display_name || report.email || 'Участник')} · ${escapeHtml(reportStreamLabel(report))} · ${escapeHtml(report.updated_at || report.created_at || '')}</small>
        <small>${escapeHtml(reportMoneyLine(report))}</small>
        <div class="captain-row-actions">
          <button class="ghost-btn" type="button" data-captain-open-otr-card="${escapeHtml(report.id || report.tape_id)}">Открыть</button>
          <button class="primary-btn" type="button" data-otr-card-submit="${escapeHtml(report.id || report.tape_id)}">Сдал</button>
        </div>
      </article>
    `;
  }

  function renderIncludedPackSummary(includedOtr, accepted) {
    const liveCount = includedOtr.length;
    const advanceCount = accepted.length;
    const totalCount = liveCount + advanceCount;

    if (!totalCount) {
      return '<p class="soft-note">Пока нет отчетов, включенных в общий пакет.</p>';
    }

    return `
      <div class="captain-pack-summary">
        <div class="captain-current-head">
          <span>В рабочем пакете</span>
          <b>${escapeHtml(totalCount)}</b>
        </div>
        <div class="captain-mini-metrics">
          <div><span>Живые</span><b>${escapeHtml(liveCount)}</b></div>
          <div><span>Подотчеты</span><b>${escapeHtml(advanceCount)}</b></div>
        </div>
        <p class="captain-pack-note">Пакет остается здесь до подготовки итогового отчета. Архив живого журнала не меняет эти данные.</p>
      </div>
    `;
  }

  function renderCaptainIncludedModal() {
    const box = document.getElementById('captainIncludedList');
    const count = document.getElementById('captainIncludedCount');
    if (!box && !count) return;

    const includedOtr = (captainOtrReports || []).filter(function(row) {
      return row.card_state === 'included' && !row.ui_archived;
    });
    const accepted = captainAdvances.filter(function(row) {
      return ['accepted', 'closed'].includes(row.status || '');
    });
    const rows = includedOtr.map(renderOtrReportRow).concat(accepted.map(renderAdvanceRow));
    const totalCount = rows.length;

    if (count) count.textContent = String(totalCount);
    if (box) {
      box.innerHTML = rows.length
        ? rows.join('')
        : '<p class="soft-note">Пока нет включенных карточек.</p>';
    }
  }

  function renderCaptainArchiveModal() {
    const box = document.getElementById('captainArchiveList');
    const count = document.getElementById('captainArchiveCount');
    const canSeeArchive = groupCanViewArchive(selectedGroup());
    if (!box && !count) return;

    const rows = canSeeArchive ? (captainOtrArchive || []) : [];
    const folders = groupArchiveFoldersWithMembers(rows);

    if (count) count.textContent = String(archiveRecordsCount(rows));
    if (box) {
      if (!canSeeArchive) {
        box.innerHTML = '<p class="soft-note">Архив доступен администратору группы.</p>';
      } else {
        box.innerHTML = folders.length
          ? folders.map(function(folder) {
            const records = archiveRecordsCount(folder.rows);
            const title = folder.isOwn ? 'Мой архив' : folder.name;
            return `
              <section class="captain-archive-folder">
                <div class="captain-archive-folder-head">
                  <div>
                    <b>${escapeHtml(title)}</b>
                    <small>${escapeHtml(folder.name)}</small>
                  </div>
                  <span>${escapeHtml(folder.rows.length)} карточек · ${escapeHtml(records)} записей</span>
                </div>
                <div class="captain-archive-folder-list">
                  ${folder.rows.length
                    ? folder.rows.map(renderOtrArchiveRow).join('')
                    : '<p class="soft-note">У сотрудника пока нет живых отчетов.</p>'}
                </div>
              </section>
            `;
          }).join('')
          : '<p class="soft-note">В группе пока нет сотрудников и живых отчетов.</p>';
      }
    }
  }

  function renderCaptainAdvances() {
    const submittedBox = document.getElementById('captainSubmittedList');
    const assignedBox = document.getElementById('captainAssignedList');
    const acceptedBox = document.getElementById('captainReportPack');
    const archiveBox = document.getElementById('captainArchivePack');
    const journalOpen = document.getElementById('captainJournalExportBtn');
    const canSeeArchive = groupCanViewArchive(selectedGroup());

    const submitted = captainAdvances.filter(function(row) {
      return ['submitted', 'discrepancy'].includes(row.status || '');
    });
    const submittedOtr = (captainOtrReports || []).filter(function(row) {
      return row.card_state === 'submitted';
    });
    const includedOtr = (captainOtrReports || []).filter(function(row) {
      return row.card_state === 'included' && !row.ui_archived;
    });
    const assigned = captainAdvances.filter(function(row) {
      return ['issued', 'returned'].includes(row.status || '');
    });
    const accepted = captainAdvances.filter(function(row) {
      return ['accepted', 'closed'].includes(row.status || '');
    });
    const participants = participantRows(submittedOtr, submitted, includedOtr, accepted, assigned);

    if (submittedBox) {
      captainParticipantRows = participants;
      renderCaptainBoard(participants);
    }
    renderCaptainCardView();

    if (assignedBox) {
      const assignedRows = assigned.slice(0, 8).map(renderAdvanceRow);
      assignedBox.innerHTML = assignedRows.length
        ? assignedRows.join('')
        : '<p class="soft-note">Активных назначенных отчетов пока нет.</p>';
    }

    if (acceptedBox) {
      acceptedBox.innerHTML = renderCaptainChildReports(includedOtr, accepted);
    }

    if (archiveBox) {
      archiveBox.innerHTML = renderArchivePackSummary(captainOtrArchive || [], canSeeArchive);
    }
    if (journalOpen) journalOpen.classList.toggle('hidden', !canSeeArchive);
    renderCaptainIncludedModal();
    renderCaptainArchiveModal();
  }

  async function loadCaptainMembers() {
    captainMembers = [];
    renderCaptainMembers();

    if (!captainGroupId) return;

    const data = await qlApi('group_members', {group_id: Number(captainGroupId)});
    if (!data.ok) {
      const box = document.getElementById('captainMemberList');
      if (box) box.innerHTML = '<p class="soft-note">Ошибка участников: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    captainMembers = data.members || [];
    renderCaptainMembers();
  }

  async function loadCaptainAdvances() {
    captainAdvances = [];
    renderCaptainAdvances();

    if (!captainGroupId) return;

    const data = await qlApi('advance_list', {
      group_id: Number(captainGroupId),
      limit: 200
    });

    if (!data.ok) {
      captainStatus('Ошибка отчетов: ' + (data.error || 'unknown'));
      return;
    }

    captainAdvances = data.advances || [];
    renderCaptainAdvances();
  }

  async function loadCaptainOtrReports() {
    captainOtrReports = [];
    renderCaptainAdvances();

    if (!captainGroupId) return;

    const data = await qlApi('on_the_go_card_list', {
      group_id: Number(captainGroupId),
      submitted_only: 1,
      include_archived: 1,
      exclude_advances: 1,
      limit: 30
    });

    if (!data.ok) {
      captainStatus('Ошибка живых отчетов: ' + (data.error || 'unknown'));
      return;
    }

    captainOtrReports = data.cards || [];
    renderCaptainAdvances();
  }

  async function loadCaptainOtrArchive() {
    captainOtrArchive = [];
    renderCaptainAdvances();

    const group = selectedGroup();
    if (!captainGroupId || !groupCanViewArchive(group)) return;

    const results = await Promise.all([
      qlApi('on_the_go_card_list', {
        group_id: Number(captainGroupId),
        include_archived: 1,
        limit: 200
      }),
      qlApi('group_members', {group_id: Number(captainGroupId)})
    ]);
    const data = results[0] || {};
    const membersData = results[1] || {};

    if (!data.ok) {
      captainStatus('Ошибка архива: ' + (data.error || 'unknown'));
      return;
    }
    if (membersData.ok) {
      captainMembers = membersData.members || [];
    }

    captainOtrArchive = data.cards || [];
    renderCaptainAdvances();
  }

  async function loadCaptainAdminDesk() {
    if (captainLoading) return;
    captainLoading = true;

    try {
      if (!Array.isArray(qlGroups) || !qlGroups.length) {
        const groupsData = await qlApi('group_list', {});
        if (groupsData.ok) {
          qlGroups = groupsData.groups || [];
          if (typeof qlRenderGroups === 'function') qlRenderGroups();
        }
      }

      renderCaptainGroupSelect();
      const group = selectedGroup();
      captainStatus(group ? ((groupCanManage(group) ? 'Администратор группы.' : groupCanModerate(group) ? 'Режим проверки отчетов.' : 'Ограниченный доступ.') + ' ' + (group.name || '')) : 'Создайте группу или войдите по приглашению.');

      const ledgerPayload = captainGroupId ? {group_id: Number(captainGroupId)} : {};
      const results = await Promise.all([
        qlApi('on_the_go_tape_list', ledgerPayload),
        qlApi('ledger_balance', ledgerPayload)
      ]);
      captainLedgerData = results[1] || null;
      renderCaptainBalances(captainLedgerData);
      renderCurrentReport(results[0], results[1]);

      await loadCaptainMembers();
      await loadCaptainAdvances();
      await loadCaptainOtrReports();
      await loadCaptainOtrArchive();
    } finally {
      captainLoading = false;
    }
  }

  async function createCaptainGroup() {
    const input = document.getElementById('captainGroupName');
    const name = (input?.value || '').trim();
    if (!name) {
      captainStatus('Введите название группы.');
      return;
    }

    captainStatus('Создаю группу...');
    const data = await qlApi('group_create', {name});
    if (!data.ok) {
      captainStatus('Ошибка группы: ' + (data.error || 'unknown'));
      return;
    }

    if (input) input.value = '';
    captainGroupId = data.group && data.group.id ? Number(data.group.id) : captainGroupId;
    if (typeof qlLoadGroups === 'function') await qlLoadGroups();
    await loadCaptainAdminDesk();
    captainStatus('Группа создана.');
  }

  async function createCaptainInvite() {
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }

    captainStatus('Создаю приглашение...');
    const data = await qlApi('group_invite_create', {
      group_id: Number(captainGroupId),
      invited_email: (document.getElementById('captainInviteEmail')?.value || '').trim(),
      access_level: document.getElementById('captainInviteAccessLevel')?.value || 'base',
      channel: 'copy'
    });

    if (!data.ok) {
      captainStatus('Ошибка приглашения: ' + (data.error || 'unknown'));
      return;
    }

    const out = document.getElementById('captainInviteUrl');
    if (out) {
      out.classList.remove('hidden');
      out.value = data.invite && data.invite.url ? data.invite.url : '';
      out.select();
    }

    try {
      if (out && out.value) await navigator.clipboard.writeText(out.value);
      captainStatus('Приглашение создано и скопировано.');
    } catch (error) {
      captainStatus('Приглашение создано.');
    }
  }

  async function updateCaptainMemberAccess(userId, accessLevel) {
    if (!captainGroupId || !userId) return;

    captainStatus('Обновляю роль...');
    const data = await qlApi('group_member_access_update', {
      group_id: Number(captainGroupId),
      user_id: Number(userId),
      access_level: accessLevel
    });

    if (!data.ok) {
      captainStatus('Ошибка роли: ' + (data.error || 'unknown'));
      await loadCaptainMembers();
      return;
    }

    if (typeof qlLoadGroups === 'function') await qlLoadGroups();
    await loadCaptainMembers();
    captainStatus('Роль обновлена.');
  }

  async function issueCaptainAdvance() {
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }

    const memberId = document.getElementById('captainIssueMemberSelect')?.value || '';
    const title = (document.getElementById('captainIssueTitle')?.value || '').trim();
    const amount = (document.getElementById('captainIssueAmount')?.value || '').trim();

    if (!memberId || !amount) {
      captainStatus('Выберите исполнителя и сумму.');
      return;
    }

    captainStatus('Выдаю под отчет...');
    const data = await qlApi('advance_create', {
      group_id: Number(captainGroupId),
      assigned_to_user_id: Number(memberId),
      title: title || 'Деньги под отчет',
      amount,
      currency: 'EUR'
    });

    if (!data.ok) {
      captainStatus('Ошибка выдачи: ' + (data.error || 'unknown'));
      return;
    }

    const titleEl = document.getElementById('captainIssueTitle');
    const amountEl = document.getElementById('captainIssueAmount');
    if (titleEl) titleEl.value = '';
    if (amountEl) amountEl.value = '';

    captainStatus('Отчет назначен исполнителю.');
    await loadCaptainAdvances();
  }

  function openCaptainReviewShell() {
    const modal = document.getElementById('captainReviewModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeCaptainReview() {
    const modal = document.getElementById('captainReviewModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    captainCurrentReviewId = null;
    captainCurrentReviewStatus = '';
  }

  function openCaptainIncludedModal() {
    const modal = document.getElementById('captainIncludedModal');
    if (!modal) return;
    renderCaptainIncludedModal();
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeCaptainIncludedModal() {
    const modal = document.getElementById('captainIncludedModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  async function openCaptainArchiveModal() {
    if (!groupCanViewArchive(selectedGroup())) {
      captainStatus('Архив доступен только администратору группы.');
      return;
    }

    const modal = document.getElementById('captainArchiveModal');
    if (!modal) return;
    renderCaptainArchiveModal();
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    await loadCaptainOtrArchive();
  }

  function closeCaptainArchiveModal() {
    const modal = document.getElementById('captainArchiveModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  function renderCaptainReview(data) {
    const advance = data.advance || {};
    const s = advance.summary || {};
    const items = data.items || [];
    const status = advance.status || '';
    const canModerate = data.scope && data.scope.can_moderate && ['submitted', 'discrepancy'].includes(status);
    const canUnaccept = data.scope && data.scope.can_moderate && status === 'accepted';
    captainCurrentReviewStatus = status;

    const kicker = document.getElementById('captainReviewKicker');
    const title = document.getElementById('captainReviewTitle');
    const amount = document.getElementById('captainReviewAmount');
    const meta = document.getElementById('captainReviewMeta');
    const records = document.getElementById('captainReviewRecords');
    const accept = document.getElementById('captainReviewAcceptBtn');
    const ret = document.getElementById('captainReviewReturnBtn');
    const modalStatus = document.getElementById('captainReviewStatus');

    if (kicker) kicker.textContent = statusLabel(status);
    if (title) title.textContent = advance.title || 'Отчет исполнителя';
    if (amount) amount.textContent = money(advance.amount || 0);
    if (meta) {
      meta.textContent = (advance.assigned_to_display_name || advance.assigned_to_email || 'Исполнитель')
        + ' · потрачено ' + money(Number(s.cash_out || 0) + Number(s.card_out || 0))
        + ' · остаток ' + money(s.cash_left || 0);
    }
    if (modalStatus) modalStatus.textContent = '';
    if (accept) accept.classList.toggle('hidden', !canModerate);
    if (ret) {
      ret.classList.toggle('hidden', !(canModerate || canUnaccept));
      ret.textContent = canUnaccept ? 'Вернуть на доработку' : 'Вернуть на правку';
    }

    if (!records) return;
    if (!items.length) {
      records.innerHTML = '<p class="soft-note">В этом отчете пока нет записей.</p>';
      return;
    }

    records.innerHTML = items.map(function(item) {
      const sign = item.capture_type === 'cash_in' ? '+' : '-';
      const desc = item.description || 'Без описания';
      const attach = Number(item.files_count || 0) > 0 ? ' · вложений ' + Number(item.files_count || 0) : '';
      return `
        <article class="captain-review-record">
          <span>
            <b>${escapeHtml(typeLabel(item.capture_type))}</b>
            <span>${escapeHtml(desc)}</span>
            <small>${escapeHtml(item.created_at || '')}${escapeHtml(attach)}</small>
          </span>
          <strong>${escapeHtml(sign)}${money(item.amount || 0)}</strong>
        </article>
      `;
    }).join('');
  }

  async function openCaptainReview(id) {
    captainCurrentReviewId = Number(id || 0);
    if (!captainCurrentReviewId) return;

    openCaptainReviewShell();
    const records = document.getElementById('captainReviewRecords');
    if (records) records.innerHTML = '<p class="soft-note">Загрузка отчета…</p>';

    const data = await qlApi('advance_detail', {id: captainCurrentReviewId});
    if (!data.ok) {
      if (records) records.innerHTML = '<p class="soft-note">Ошибка отчета: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    renderCaptainReview(data);
  }

  async function acceptCaptainAdvance(id) {
    const targetId = Number(id || captainCurrentReviewId || 0);
    if (!targetId) return;

    const status = document.getElementById('captainReviewStatus');
    if (status) status.textContent = 'Включаю в отчет...';
    captainStatus('Включаю отчет...');

    const data = await qlApi('advance_accept', {
      id: targetId,
      note: 'Включено через FinDesk'
    });

    if (!data.ok) {
      if (status) status.textContent = 'Ошибка: ' + (data.error || 'unknown');
      captainStatus('Ошибка включения: ' + (data.error || 'unknown'));
      return;
    }

    if (status) status.textContent = 'Отчет включен.';
    captainStatus('Отчет включен в общий пакет.');
    await loadCaptainAdvances();
    if (captainCurrentReviewId) await openCaptainReview(captainCurrentReviewId);
    if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(captainGroupId || '')) {
      qlLoadLedger();
    }
  }

  async function returnCaptainAdvance(id) {
    const targetId = Number(id || captainCurrentReviewId || 0);
    if (!targetId) return;

    const note = prompt('Причина возврата', '') || '';
    captainStatus('Возвращаю отчет...');

    const data = await qlApi('advance_return', {
      id: targetId,
      note
    });

    if (!data.ok) {
      captainStatus('Ошибка возврата: ' + (data.error || 'unknown'));
      return;
    }

    captainStatus('Отчет возвращен исполнителю.');
    await loadCaptainAdvances();
    if (captainCurrentReviewId) await openCaptainReview(captainCurrentReviewId);
  }

  async function unacceptCaptainAdvance(id) {
    const targetId = Number(id || captainCurrentReviewId || 0);
    if (!targetId) return;

    const note = prompt('Почему вернуть включенный подотчет на доработку?', 'Вернуть из рабочего пакета');
    if (note === null) return;

    const status = document.getElementById('captainReviewStatus');
    if (status) status.textContent = 'Откатываю строки из учета...';
    captainStatus('Возвращаю включенный подотчет на доработку...');

    const data = await qlApi('advance_unaccept', {
      id: targetId,
      note: note.trim()
    });

    if (!data.ok) {
      const message = data.message || (data.error === 'advance_has_followup'
        ? 'Сначала обработайте следующий подотчет.'
        : (data.error || 'unknown'));
      if (status) status.textContent = 'Ошибка: ' + message;
      captainStatus('Не удалось вернуть подотчет: ' + message);
      return;
    }

    if (status) status.textContent = 'Подотчет снова доступен для правки.';
    captainStatus('Подотчет убран из рабочего пакета и возвращен на доработку.');
    await loadCaptainAdvances();
    if (captainCurrentReviewId && Number(captainCurrentReviewId) === targetId) {
      await openCaptainReview(targetId);
    }
    if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(captainGroupId || '')) {
      qlLoadLedger();
    }
    if (typeof qlLoadAdvances === 'function') qlLoadAdvances();
    if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
    if (typeof window.qlOtrSimpleLoad === 'function') window.qlOtrSimpleLoad({force: true});
  }

  async function cancelCaptainAdvance(id) {
    const targetId = Number(id || captainCurrentReviewId || 0);
    if (!targetId) return;

    const reason = prompt('Причина отмены выдачи', '');
    if (reason === null) return;

    const cleanReason = reason.trim();
    if (!cleanReason) {
      captainStatus('Нужна причина отмены.');
      return;
    }

    captainStatus('Отменяю ошибочную выдачу...');

    const data = await qlApi('advance_cancel', {
      id: targetId,
      reason: cleanReason
    });

    if (!data.ok) {
      captainStatus('Ошибка отмены: ' + (data.error || 'unknown'));
      return;
    }

    captainStatus('Выдача отменена и убрана из текущих отчетов.');
    await loadCaptainAdvances();
    if (captainCurrentReviewId && Number(captainCurrentReviewId) === targetId) {
      closeCaptainReview();
    }
    if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
  }

  async function returnCashCaptainAdvance(id) {
    const targetId = Number(id || captainCurrentReviewId || 0);
    if (!targetId) return;
    if (!confirm('Вернуть остаток подотчета в кассу? Расход или доход не создается.')) return;

    captainStatus('Возвращаю остаток в кассу...');

    const data = await qlApi('advance_return_cash', {
      id: targetId,
      note: 'Остаток возвращен в кассу через FinDesk'
    });

    if (!data.ok) {
      const message = data.error === 'advance_has_records'
        ? 'в этом остатке есть записи; сначала сдайте или исправьте отчет'
        : (data.error || 'unknown');
      captainStatus('Ошибка возврата остатка: ' + message);
      return;
    }

    captainStatus('Остаток вернулся в доступную кассу.');
    await loadCaptainAdvances();
    if (captainCurrentReviewId && Number(captainCurrentReviewId) === targetId) {
      closeCaptainReview();
    }
    if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(captainGroupId || '')) {
      qlLoadLedger();
    }
    if (typeof qlLoadAdvances === 'function') qlLoadAdvances();
    if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
  }

  async function finalizeCaptainReport() {
    if (captainFinalizing) return;
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }
    const group = selectedGroup();
    if (!groupCanManage(group)) {
      captainStatus('Создать сводный отчет может администратор группы.');
      return;
    }

    const includedOtr = (captainOtrReports || []).filter(function(row) {
      return row.card_state === 'included' && !row.ui_archived;
    });
    const accepted = captainAdvances.filter(function(row) {
      return ['accepted', 'closed'].includes(row.status || '');
    });
    const total = includedOtr.length + accepted.length;
    const ok = confirm(total
      ? 'Создать и утвердить сводный отчет? Финансовые суммы будут зафиксированы, а включенные быстрые карточки уйдут в архив.'
      : 'В рабочем пакете нет дочерних карточек. Все равно проверить фиксацию сводного отчета?');
    if (!ok) return;

    captainFinalizing = true;
    captainStatus('Фиксирую сводный отчет...');
    const data = await qlApi('ledger_group_finalize_report', {group_id: Number(captainGroupId)});
    captainFinalizing = false;

    if (!data.ok) {
      captainStatus('Не удалось зафиксировать отчет: ' + (data.message || data.error || 'unknown'));
      return;
    }

    captainStatus(Number(data.finalized || 0)
      ? 'Сводный отчет создан. Карточек закрыто: ' + Number(data.finalized || 0) + '.'
      : 'Нет включенных быстрых карточек для закрытия.');
    await loadCaptainAdminDesk();
    if (typeof qlLoadFinalReports === 'function') qlLoadFinalReports();
    if (typeof qlLoadLedger === 'function') qlLoadLedger();
    if (typeof window.qlOtrSimpleLoad === 'function') window.qlOtrSimpleLoad({force: true});
  }

  function printCaptainReport() {
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }
    openCaptainGroupReport();
    setTimeout(function() {
      try {
        window.print();
      } catch (error) {
        captainStatus('Печать недоступна в этом браузере.');
      }
    }, 320);
  }

  function openCaptainGroupReport() {
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }

    qlLedgerScopeMode = 'group';
    qlLedgerGroupId = Number(captainGroupId);

    const select = document.getElementById('ledgerGroupSelect');
    if (select) {
      select.classList.remove('hidden');
      select.value = String(captainGroupId);
    }

    document.querySelectorAll('[data-scope-mode]').forEach(function(btn) {
      btn.classList.toggle('active', btn.getAttribute('data-scope-mode') === 'group');
    });

    if (typeof window.qlSetModule === 'function') {
      window.qlSetModule('reports');
    } else if (typeof qlSetModule === 'function') {
      qlSetModule('reports');
    }

    const panel = document.getElementById('reportPanel');
    if (panel) panel.classList.remove('hidden');

    setTimeout(function() {
      if (typeof qlLoadLedger === 'function') qlLoadLedger();
      qlRunReport();
    }, 120);
  }

  async function exportCaptainJournal() {
    if (!captainGroupId) {
      captainStatus('Сначала выберите группу.');
      return;
    }

    captainStatus('Готовлю защищенный журнал живых отчетов...');
    const data = await qlApi('on_the_go_journal_export', {
      group_id: Number(captainGroupId)
    });

    if (!data.ok) {
      captainStatus('Не удалось выгрузить журнал: ' + (data.error || 'unknown'));
      return;
    }

    captainStatus('Журнал сохранен на сервере. Строк: ' + Number(data.rows || 0) + '.');
    if (data.download_url) {
      window.location.href = data.download_url;
    }
  }

  document.addEventListener('change', function(event) {
    const group = event.target.closest('#captainGroupSelect');
    const access = event.target.closest('[data-captain-member-access]');

    if (group) {
      captainGroupId = group.value ? Number(group.value) : null;
      window.qlCaptainActiveGroupId = captainGroupId;
      qlAdvanceGroupId = captainGroupId;
      captainActiveCard = {type: 'board', id: ''};
      loadCaptainAdminDesk();
    }

    if (access) {
      updateCaptainMemberAccess(access.getAttribute('data-captain-member-access'), access.value);
    }
  });

  document.addEventListener('click', function(event) {
    const openCard = event.target.closest('[data-captain-open-card]');
    const closeCard = event.target.closest('#captainCardBackBtn');
    const createGroup = event.target.closest('#captainCreateGroupBtn');
    const invite = event.target.closest('#captainCreateInviteBtn');
    const issue = event.target.closest('#captainIssueCreateBtn');
    const report = event.target.closest('[data-captain-open-report]');
    const included = event.target.closest('[data-captain-open-included]');
    const archive = event.target.closest('[data-captain-open-archive]');
    const journal = event.target.closest('#captainJournalExportBtn, #captainArchiveJournalExportBtn');
    const finalize = event.target.closest('[data-captain-finalize-report]');
    const print = event.target.closest('[data-captain-print]');
    const openReview = event.target.closest('[data-captain-open-review]');
    const accept = event.target.closest('[data-captain-accept]');
    const ret = event.target.closest('[data-captain-return]');
    const unaccept = event.target.closest('[data-captain-unaccept]');
    const returnCash = event.target.closest('[data-captain-return-cash]');
    const cancel = event.target.closest('[data-captain-cancel]');

    if (openCard) {
      openCaptainCardView(openCard.getAttribute('data-captain-open-card'), openCard.getAttribute('data-captain-card-id'));
      return;
    }
    if (closeCard) {
      closeCaptainCardView();
      return;
    }
    if (createGroup) createCaptainGroup();
    if (invite) createCaptainInvite();
    if (issue) issueCaptainAdvance();
    if (report) {
      closeCaptainIncludedModal();
      closeCaptainArchiveModal();
      openCaptainGroupReport();
    }
    if (included) openCaptainIncludedModal();
    if (archive) openCaptainArchiveModal();
    if (journal) exportCaptainJournal();
    if (finalize) finalizeCaptainReport();
    if (print) printCaptainReport();
    if (openReview) openCaptainReview(openReview.getAttribute('data-captain-open-review'));
    if (accept) acceptCaptainAdvance(accept.getAttribute('data-captain-accept'));
    if (ret) returnCaptainAdvance(ret.getAttribute('data-captain-return'));
    if (unaccept) unacceptCaptainAdvance(unaccept.getAttribute('data-captain-unaccept'));
    if (returnCash) returnCashCaptainAdvance(returnCash.getAttribute('data-captain-return-cash'));
    if (cancel) cancelCaptainAdvance(cancel.getAttribute('data-captain-cancel'));
  });

  document.addEventListener('click', function(event) {
    if (event.target.closest('[data-close-captain-review]')) {
      closeCaptainReview();
      return;
    }
    if (event.target.closest('[data-close-captain-included]')) {
      closeCaptainIncludedModal();
      return;
    }
    if (event.target.closest('[data-close-captain-archive]')) {
      closeCaptainArchiveModal();
      return;
    }
    if (event.target.closest('#captainReviewAcceptBtn')) {
      acceptCaptainAdvance();
      return;
    }
    if (event.target.closest('#captainReviewReturnBtn')) {
      if (captainCurrentReviewStatus === 'accepted') {
        unacceptCaptainAdvance();
      } else {
        returnCaptainAdvance();
      }
      return;
    }
    if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'captainReviewModal') {
      closeCaptainReview();
    }
    if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'captainIncludedModal') {
      closeCaptainIncludedModal();
    }
    if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'captainArchiveModal') {
      closeCaptainArchiveModal();
    }
  }, true);

  const previousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);
  window.qlSetModule = function(moduleName, options) {
    if (typeof previousSetModule === 'function') {
      previousSetModule(moduleName, options);
    }

    if (moduleName === 'captain') {
      setTimeout(loadCaptainAdminDesk, 220);
    }
  };

  try {
    qlSetModule = window.qlSetModule;
  } catch (error) {}

  const previousLoadGroups = qlLoadGroups;
  qlLoadGroups = async function() {
    await previousLoadGroups();
    renderCaptainGroupSelect();

    const module = document.getElementById('moduleCaptain');
    if (module && !module.classList.contains('hidden')) {
      setTimeout(loadCaptainAdminDesk, 80);
    }
  };

  window.qlLoadCaptainAdminDesk = loadCaptainAdminDesk;
})();

/* === FinDesk On the Go Simple Signed Notes 20260520 === */
(function() {
  let simpleDirty = false;
  let simpleLoading = false;
  let simpleReplaceTape = false;
  let simpleCurrentCard = null;
  let simpleEditMode = true;
  let simpleOpenedCardId = 0;
  let simpleCurrentStream = 'cash';
  let simpleStreamChosen = false;
  let simpleClientDraftId = '';
  let simpleDraftId = 0;
  let simpleSessionId = 0;
  let simpleProofStates = [];
  let simpleSelectedUploadId = '';
  let simpleProofRetryContext = null;
  let simpleAutosaveTimer = null;
  let simpleAutosaveBusy = false;
  let simpleAutosaveQueued = false;
  let simpleLastAutosaveSignature = '';
  let simpleUiBusy = false;
  let simplePendingOperationId = '';
  let simplePendingOperationSignature = '';
  let simpleScannerFile = null;
  let simpleScannerOriginalFile = null;
  let simpleScannerBundleId = '';
  let simpleScannerMetadata = null;
  let receiptScannerImage = null;
  let receiptScannerObjectUrl = '';
  let receiptScannerCorners = null;
  let receiptScannerDraggingCorner = '';

  function normalizeSimpleStream(value) {
    return String(value || '') === 'card' ? 'card' : 'cash';
  }

  function simpleToken(prefix) {
    const safePrefix = prefix || 'id';
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return safePrefix + '-' + window.crypto.randomUUID();
    }
    return safePrefix + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  function simpleDraftStorageKey(stream) {
    const userId = qlCurrentUser && qlCurrentUser.id ? qlCurrentUser.id : 'guest';
    return 'findesk-field-combat-draft:' + userId + ':' + normalizeSimpleStream(stream || simpleCurrentStream);
  }

  function readSimpleDraftContext(stream) {
    try {
      const raw = window.localStorage ? localStorage.getItem(simpleDraftStorageKey(stream)) : '';
      const parsed = raw ? JSON.parse(raw) : null;
      return parsed && parsed.client_draft_id ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function writeSimpleDraftContext(context) {
    try {
      if (!window.localStorage) return;
      localStorage.setItem(simpleDraftStorageKey(), JSON.stringify(context || {}));
    } catch (error) {}
  }

  function simpleProofRetryStorageKey(stream) {
    const userId = qlCurrentUser && qlCurrentUser.id ? qlCurrentUser.id : 'guest';
    return 'findesk-field-combat-proof-retry:' + userId + ':' + normalizeSimpleStream(stream || simpleCurrentStream);
  }

  function readSimpleProofRetryContext(stream) {
    try {
      const raw = window.localStorage ? localStorage.getItem(simpleProofRetryStorageKey(stream)) : '';
      const parsed = raw ? JSON.parse(raw) : null;
      return parsed && parsed.client_draft_id ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function writeSimpleProofRetryContext(context) {
    try {
      if (!window.localStorage || !context) return;
      localStorage.setItem(simpleProofRetryStorageKey(context.stream_type), JSON.stringify(context));
    } catch (error) {}
  }

  function clearSimpleProofRetryContext(stream) {
    try {
      if (window.localStorage) {
        localStorage.removeItem(simpleProofRetryStorageKey(stream || simpleCurrentStream));
      }
    } catch (error) {}
    if (!stream || normalizeSimpleStream(stream) === normalizeSimpleStream(simpleCurrentStream)) {
      simpleProofRetryContext = null;
    }
  }

  function simpleProofStateStatus(row) {
    return String(row && row.status || '').toLowerCase();
  }

  function simpleProofStateCaptureId(row) {
    return Number(row && row.capture_id || 0);
  }

  function isValidSimpleProofRetryContext(context, state) {
    if (!context || typeof context !== 'object') return false;
    const targetCapture = simpleProofRetryTargetCaptureId(state, context);
    if (targetCapture <= 0) return false;

    const contextDraftId = String(context.client_draft_id || '');
    const currentDraftId = String(simpleClientDraftId || '');
    if (contextDraftId && currentDraftId && contextDraftId !== currentDraftId) return false;

    const contextStream = normalizeSimpleStream(context.stream_type || '');
    const currentStream = normalizeSimpleStream(simpleCurrentStream);
    if (contextStream && contextStream !== currentStream) return false;

    const contextTapeId = Number(context.tape_id || 0);
    const currentTapeId = Number(qlOtrActiveTapeId || simpleOpenedCardId || 0);
    if (contextTapeId > 0 && currentTapeId > 0 && contextTapeId !== currentTapeId) return false;

    const contextSessionId = Number(context.session_id || 0);
    const currentSessionId = Number(simpleSessionId || 0);
    if (contextSessionId > 0 && currentSessionId > 0 && contextSessionId !== currentSessionId) return false;

    const contextDraftRowId = Number(context.draft_id || 0);
    const currentDraftRowId = Number(simpleDraftId || 0);
    if (contextDraftRowId > 0 && currentDraftRowId > 0 && contextDraftRowId !== currentDraftRowId) return false;

    const contextSignature = context.draft_signature || '';
    if (contextSignature && contextSignature !== simpleDraftSignature()) return false;
    return true;
  }

  function getSimpleProofRetryContext(state) {
    const context = simpleProofRetryContext || readSimpleProofRetryContext();
    if (!isValidSimpleProofRetryContext(context, state)) {
      clearSimpleProofRetryContext();
      return null;
    }
    if (context && !simpleProofRetryContext) {
      simpleProofRetryContext = context;
    }
    return context;
  }

  function isUnresolvedSimpleProofState(row) {
    const status = simpleProofStateStatus(row);
    return status !== 'uploaded' && status !== '';
  }

  function findSimpleRetryProofState() {
    const rows = Array.isArray(simpleProofStates) ? simpleProofStates.filter(isUnresolvedSimpleProofState) : [];
    if (!rows.length) return null;

    if (simpleSelectedUploadId) {
      const selectedWithCapture = rows.find(function(row) {
        return String(row.client_upload_id || '') === String(simpleSelectedUploadId)
          && simpleProofStateCaptureId(row) > 0;
      });
      if (selectedWithCapture) return selectedWithCapture;
    }

    const failedWithCapture = rows.find(function(row) {
      const status = simpleProofStateStatus(row);
      return (status === 'retry_needed' || status === 'failed') && simpleProofStateCaptureId(row) > 0;
    });
    if (failedWithCapture) return failedWithCapture;

    const anyWithCapture = rows.find(function(row) {
      return simpleProofStateCaptureId(row) > 0;
    });
    if (anyWithCapture) return anyWithCapture;

    if (simpleSelectedUploadId) {
      const selected = rows.find(function(row) {
        return String(row.client_upload_id || '') === String(simpleSelectedUploadId);
      });
      if (selected) return selected;
    }

    return rows[0] || null;
  }

  function simpleProofRetryTargetCaptureId(state, context) {
    return Number(
      simpleProofStateCaptureId(state)
      || (context && context.capture_id)
      || (context && context.capture_ids && context.capture_ids[0])
      || 0
    );
  }

  function hasSimpleProofRetryTarget() {
    const state = findSimpleRetryProofState();
    const context = getSimpleProofRetryContext(state);
    return Boolean(state) && simpleProofRetryTargetCaptureId(state, context) > 0;
  }

  function rememberSimpleProofRetryContext(extra) {
    const group = simpleAdminGroup();
    const existing = simpleProofRetryContext || readSimpleProofRetryContext();
    const data = Object.assign({}, existing || {}, extra || {});
    const captureIds = Array.isArray(data.capture_ids)
      ? data.capture_ids.map(function(id) { return Number(id || 0); }).filter(function(id) { return id > 0; })
      : [];
    const captureId = Number(data.capture_id || captureIds[0] || 0);
    const context = Object.assign({}, data, {
      stream_type: normalizeSimpleStream(data.stream_type || simpleCurrentStream),
      client_draft_id: data.client_draft_id || simpleClientDraftId || (existing && existing.client_draft_id) || '',
      draft_id: Number(data.draft_id || simpleDraftId || (existing && existing.draft_id) || 0),
      tape_id: Number(data.tape_id || qlOtrActiveTapeId || simpleOpenedCardId || (existing && existing.tape_id) || 0),
      session_id: Number(data.session_id || simpleSessionId || (existing && existing.session_id) || 0),
      group_id: Number(data.group_id || (group && group.id) || (existing && existing.group_id) || 0),
      draft_signature: data.draft_signature || simpleDraftSignature(),
      capture_id: captureId
    });
    if (captureIds.length) {
      context.capture_ids = captureIds;
    } else if (captureId > 0) {
      context.capture_ids = [captureId];
    }
    if (!context.client_draft_id) return null;
    simpleProofRetryContext = context;
    writeSimpleProofRetryContext(context);
    return context;
  }

  function restoreSimpleProofRetryContextFromState() {
    const state = findSimpleRetryProofState();
    const stored = readSimpleProofRetryContext();
    if (stored && isValidSimpleProofRetryContext(stored, state)) {
      simpleProofRetryContext = stored;
    } else if (stored) {
      clearSimpleProofRetryContext();
    }

    const captureId = simpleProofStateCaptureId(state);
    if (state && captureId > 0) {
      const status = simpleProofStateStatus(state);
      if (status === 'failed' || status === 'retry_needed') {
        setSimpleSyncState('retry_needed', 'Фото нужно повторить');
      }
      const stored = simpleProofRetryContext || readSimpleProofRetryContext();
      return rememberSimpleProofRetryContext({
        client_upload_id: state.client_upload_id || (simpleProofRetryContext && simpleProofRetryContext.client_upload_id) || '',
        capture_id: captureId,
        draft_id: Number(state.draft_id || simpleDraftId || 0),
        client_draft_id: simpleClientDraftId,
        tape_id: Number((stored && stored.tape_id) || qlOtrActiveTapeId || simpleOpenedCardId || 0),
        session_id: Number((stored && stored.session_id) || simpleSessionId || 0),
        stream_type: (stored && stored.stream_type) || simpleCurrentStream,
        draft_signature: simpleDraftSignature()
      });
    }

    return simpleProofRetryContext;
  }

  function applySimpleProofRetryContext(context) {
    if (!context) return;
    if (context.stream_type) {
      setSimpleStream(context.stream_type, {chosen: true, keepChoiceState: true, skipAutosave: true});
    }
    if (context.client_draft_id) simpleClientDraftId = context.client_draft_id;
    if (Number(context.draft_id || 0) > 0) simpleDraftId = Number(context.draft_id);
    if (Number(context.session_id || 0) > 0) simpleSessionId = Number(context.session_id);
    if (Number(context.tape_id || 0) > 0) {
      qlOtrActiveTapeId = Number(context.tape_id);
      window.qlOtrActiveTapeId = Number(context.tape_id);
      simpleOpenedCardId = Number(context.tape_id);
    }
  }

  function ensureSimpleClientDraftId(tapeId) {
    const requestedTapeId = Number(tapeId || 0);
    if (simpleClientDraftId) {
      return simpleClientDraftId;
    }

    const stored = readSimpleDraftContext();
    if (stored && stored.client_draft_id) {
      const storedTapeId = Number(stored.tape_id || 0);
      if (!requestedTapeId || !storedTapeId || storedTapeId === requestedTapeId) {
        simpleClientDraftId = stored.client_draft_id;
        simpleDraftId = Number(stored.draft_id || simpleDraftId || 0);
        simpleSessionId = Number(stored.session_id || simpleSessionId || 0);
        return simpleClientDraftId;
      }
    }

    simpleClientDraftId = simpleToken('draft');
    writeSimpleDraftContext({
      client_draft_id: simpleClientDraftId,
      draft_id: simpleDraftId || 0,
      tape_id: requestedTapeId || 0,
      session_id: simpleSessionId || 0,
      stream_type: normalizeSimpleStream(simpleCurrentStream)
    });
    return simpleClientDraftId;
  }

  function rememberSimpleDraftContext(extra) {
    const group = simpleAdminGroup();
    const context = Object.assign({
      client_draft_id: ensureSimpleClientDraftId(extra && extra.tape_id),
      draft_id: simpleDraftId || 0,
      tape_id: Number(qlOtrActiveTapeId || simpleOpenedCardId || 0),
      session_id: simpleSessionId || 0,
      stream_type: normalizeSimpleStream(simpleCurrentStream),
      group_id: group && group.id ? Number(group.id) : 0
    }, extra || {});
    writeSimpleDraftContext(context);
  }

  function resetSimpleDraftIdentity(tapeId) {
    simpleClientDraftId = simpleToken('draft');
    simpleDraftId = 0;
    simpleSessionId = 0;
    simpleProofStates = [];
    simpleSelectedUploadId = '';
    clearSimpleProofRetryContext();
    simpleLastAutosaveSignature = '';
    simplePendingOperationId = '';
    simplePendingOperationSignature = '';
    rememberSimpleDraftContext({
      client_draft_id: simpleClientDraftId,
      tape_id: Number(tapeId || 0),
      draft_id: 0,
      session_id: 0,
      group_id: Number((simpleAdminGroup() || {}).id || 0)
    });
    renderSimpleProofStates([]);
    return simpleClientDraftId;
  }

  function simpleSyncCopy(state) {
    if (state === 'pending') return 'Сохраняю...';
    if (state === 'failed') return 'Не сохранилось';
    if (state === 'retry_needed') return 'Нужно повторить';
    return 'Сохранено';
  }

  function setSimpleSyncState(state, message) {
    const normalized = ['saved', 'pending', 'failed', 'retry_needed'].includes(state) ? state : 'saved';
    const box = document.getElementById('otrSimpleSyncStatus');
    if (!box) return;

    box.classList.remove('is-saved', 'is-pending', 'is-failed', 'is-retry');
    box.classList.add(normalized === 'retry_needed' ? 'is-retry' : 'is-' + normalized);
    const label = box.querySelector('[data-otr-sync-label]');
    if (label) label.textContent = message || simpleSyncCopy(normalized);
    const retry = document.getElementById('otrAutosaveRetryBtn');
    if (retry) retry.classList.toggle('hidden', normalized !== 'failed' && normalized !== 'retry_needed');
  }

  function simpleProofStateLabel(state) {
    const status = String(state && state.status || '').toLowerCase();
    if (status === 'uploaded') return 'Доказательство сохранено';
    if (status === 'pending') return 'Доказательство отправляется';
    if (status === 'failed') return 'Доказательство не загрузилось';
    if (status === 'retry_needed') return 'Доказательство нужно повторить';
    return 'Доказательство ожидает отправки';
  }

	  function renderSimpleProofStates(states) {
	    const box = document.getElementById('otrProofStateList');
	    if (!box) return;

	    const rows = Array.isArray(states) ? states.slice() : [];
	    const fileInput = document.getElementById('otrSimpleFile');
	    const selectedFile = getSimpleSelectedProofFile(fileInput);
	    if (selectedFile && simpleSelectedUploadId && !rows.some(function(row) {
	      return String(row.client_upload_id || '') === String(simpleSelectedUploadId);
	    })) {
      rows.push({
        client_upload_id: simpleSelectedUploadId,
        status: 'pending',
        original_name: selectedFile.name
      });
    }

    if (!rows.length) {
      box.innerHTML = '<span>Доказательства: нет</span>';
      syncSimpleProofsButton();
      return;
    }

    box.innerHTML = rows.map(function(row) {
      const status = String(row.status || 'pending').toLowerCase();
      const cls = status === 'retry_needed' ? 'is-retry_needed' : 'is-' + status;
      const name = row.original_name || row.storage_path || row.client_upload_id || 'файл';
      const retry = Number(row.retry_count || 0) > 0 ? ' · попыток ' + Number(row.retry_count || 0) : '';
      return `
        <div class="otr-proof-state ${escapeHtml(cls)}">
          <span>
            <b>${escapeHtml(simpleProofStateLabel(row))}</b>
            <small>${escapeHtml(name)}${escapeHtml(retry)}</small>
          </span>
        </div>
      `;
	    }).join('');
	    syncSimpleProofsButton();
	  }

	  function getSimpleSelectedProofFile(fileInput) {
	    if (simpleScannerFile) return simpleScannerFile;
	    const input = fileInput || document.getElementById('otrSimpleFile');
	    return input && input.files && input.files[0] ? input.files[0] : null;
	  }

	  function setSimpleProofFileLabel(file, sourceLabel) {
	    const label = document.getElementById('otrSimpleFileName');
	    if (!label) return;
	    if (!file) {
	      label.textContent = 'Без вложения';
	      return;
	    }
	    label.textContent = sourceLabel ? sourceLabel + ': ' + file.name : file.name;
	  }

	  function clearSimpleSelectedProofFile() {
	    const fileInput = document.getElementById('otrSimpleFile');
	    if (fileInput) fileInput.value = '';
	    simpleScannerFile = null;
	    simpleScannerOriginalFile = null;
	    simpleScannerBundleId = '';
	    simpleScannerMetadata = null;
	    simpleSelectedUploadId = '';
	    setSimpleProofFileLabel(null);
	  }

	  function setSimpleScannerProofFile(file, metadata) {
	    if (!file) return;
	    const fileInput = document.getElementById('otrSimpleFile');
	    if (fileInput) fileInput.value = '';
	    simpleScannerFile = file;
	    simpleScannerMetadata = metadata || null;
	    simpleScannerBundleId = (metadata && metadata.proof_bundle_id) || simpleScannerBundleId || simpleToken('scan');
	    restoreSimpleProofRetryContextFromState();
	    const retryState = findSimpleRetryProofState();
	    const retryContext = getSimpleProofRetryContext(retryState);
	    const retryCaptureId = simpleProofRetryTargetCaptureId(retryState, retryContext);
	    simpleSelectedUploadId = (retryState && retryState.client_upload_id)
	      || (retryContext && retryContext.client_upload_id)
	      || simpleToken('upload');
	    setSimpleProofFileLabel(file, 'PDF-скан');
	    renderSimpleProofStates(simpleProofStates);
	    beginSimpleProofState(file, retryCaptureId || undefined)
	      .then(function() {
	        setSimpleSyncState(retryCaptureId ? 'retry_needed' : 'pending', retryCaptureId ? 'PDF готов к повтору' : 'PDF готов к сохранению');
	      })
	      .catch(function() {
	        setSimpleSyncState('retry_needed', 'PDF ждет сохранения');
	      });
	  }

  function simpleDraftSignature() {
    const notes = document.getElementById('otrSimpleNotes')?.value || '';
    const group = simpleAdminGroup();
    return JSON.stringify({
      notes,
      stream_type: normalizeSimpleStream(simpleCurrentStream),
      cash_received: normalizeSignedAmount(document.getElementById('otrAdminAmount')?.value || '0'),
      group_id: group && group.id ? Number(group.id) : 0,
      tape_id: Number(qlOtrActiveTapeId || simpleOpenedCardId || 0)
    });
  }

  function simpleDraftPayload(syncState, lastError) {
    const group = simpleAdminGroup();
    const tapeId = Number(qlOtrActiveTapeId || simpleOpenedCardId || 0);
    const payload = {
      client_draft_id: ensureSimpleClientDraftId(tapeId),
      stream_type: normalizeSimpleStream(simpleCurrentStream),
      raw_notes: document.getElementById('otrSimpleNotes')?.value || '',
      notes: document.getElementById('otrSimpleNotes')?.value || '',
      cash_received: normalizeSimpleStream(simpleCurrentStream) === 'card' ? 0 : simpleAdminAmount(),
      sync_state: syncState || 'saved'
    };
    if (tapeId > 0) payload.tape_id = tapeId;
    if (group && group.id) payload.group_id = Number(group.id);
    if (lastError) payload.last_error = String(lastError);
    return payload;
  }

  function applySimpleDraftEnvelope(data, options) {
    if (!data || !data.ok) return false;
    const draft = data.draft || null;
    const tape = data.tape || null;
    const opts = options || {};

    if (draft) {
      simpleDraftId = Number(draft.id || simpleDraftId || 0);
      simpleSessionId = Number(draft.session_id || data.session_id || simpleSessionId || 0);
      if (draft.client_draft_id) simpleClientDraftId = draft.client_draft_id;
      if (draft.stream_type) setSimpleStream(draft.stream_type, {chosen: true, keepChoiceState: true, skipAutosave: true});
      if (draft.tape_id) {
        qlOtrActiveTapeId = Number(draft.tape_id);
        window.qlOtrActiveTapeId = Number(draft.tape_id);
        simpleOpenedCardId = Number(draft.tape_id);
      }
      const amount = document.getElementById('otrAdminAmount');
      if (amount && normalizeSimpleStream(draft.stream_type) !== 'card') {
        amount.value = String(draft.cash_received ?? amount.value ?? '').replace(/\.00$/, '');
      }
      const notes = document.getElementById('otrSimpleNotes');
      if (notes) {
        const recoveredNotes = draft.raw_notes || '';
        const currentNotes = notes.value || '';
        if (opts.force || (!simpleDirty && (String(recoveredNotes).trim() || !String(currentNotes).trim()))) {
          notes.value = recoveredNotes;
        }
      }
      setSimpleSyncState(draft.sync_state || 'saved');
    }

    if (tape) {
      simpleCurrentCard = tape;
      syncSimpleEditorActions(tape);
    }

    simpleProofStates = data.proof_states || [];
    renderSimpleProofStates(simpleProofStates);
    restoreSimpleProofRetryContextFromState();
    rememberSimpleDraftContext({
      client_draft_id: simpleClientDraftId,
      draft_id: simpleDraftId || 0,
      tape_id: Number((draft && draft.tape_id) || data.tape_id || qlOtrActiveTapeId || 0),
      session_id: Number((draft && draft.session_id) || data.session_id || simpleSessionId || 0),
      stream_type: normalizeSimpleStream(simpleCurrentStream)
    });
    renderSimpleResult();
    return !!draft;
  }

  async function recoverStoredSimpleFieldDraft(stream, options) {
    const targetStream = normalizeSimpleStream(stream || simpleCurrentStream);
    const stored = readSimpleDraftContext(targetStream);
    if (!stored || !stored.client_draft_id) {
      return false;
    }

    const previousStream = simpleCurrentStream;
    setSimpleStream(targetStream, {chosen: true, keepChoiceState: true, skipAutosave: true});
    simpleClientDraftId = stored.client_draft_id;
    simpleDraftId = Number(stored.draft_id || 0);
    simpleSessionId = Number(stored.session_id || 0);
    if (Number(stored.tape_id || 0) > 0) {
      qlOtrActiveTapeId = Number(stored.tape_id);
      window.qlOtrActiveTapeId = Number(stored.tape_id);
      simpleOpenedCardId = Number(stored.tape_id);
    }

    try {
      const payload = {
        client_draft_id: stored.client_draft_id,
        stream_type: targetStream
      };
      if (Number(stored.group_id || 0) > 0) payload.group_id = Number(stored.group_id);

      const data = await qlApi('on_the_go_field_recover', payload);
      if (!data.ok || !data.draft || data.draft.draft_status !== 'active') {
        setSimpleStream(previousStream, {chosen: true, keepChoiceState: true, skipAutosave: true});
        return false;
      }

      applySimpleDraftEnvelope(data, {force: !!(options && options.force)});
      await refreshSimpleProofStates();
      simpleLastAutosaveSignature = simpleDraftSignature();
      return true;
    } catch (error) {
      setSimpleSyncState('retry_needed', 'Черновик не восстановлен');
      return false;
    }
  }

  async function autosaveSimpleDraft(options) {
    const opts = options || {};
    if (!qlCurrentUser || simpleLoading && !opts.force) return false;
    if (simpleAutosaveBusy) {
      simpleAutosaveQueued = true;
      return false;
    }

    const signature = simpleDraftSignature();
    if (!opts.force && signature === simpleLastAutosaveSignature) {
      return true;
    }

    simpleAutosaveBusy = true;
    if (!opts.silent) setSimpleSyncState('pending');

    try {
      const data = await qlApi('on_the_go_field_draft_save', simpleDraftPayload('saved'));
      if (!data.ok) {
        setSimpleSyncState('retry_needed', 'Не сохранилось. Повторить');
        return false;
      }
      simpleLastAutosaveSignature = signature;
      applySimpleDraftEnvelope(data, {force: false});
      setSimpleSyncState('saved');
      return true;
    } catch (error) {
      setSimpleSyncState('retry_needed', 'Нет связи. Повторить');
      try {
        await qlApi('on_the_go_field_draft_save', simpleDraftPayload('retry_needed', error && error.message ? error.message : 'network_error'));
      } catch (innerError) {}
      return false;
    } finally {
      simpleAutosaveBusy = false;
      if (simpleAutosaveQueued) {
        simpleAutosaveQueued = false;
        setTimeout(function() { autosaveSimpleDraft({force: true, silent: true}); }, 80);
      }
    }
  }

  function scheduleSimpleAutosave(delay) {
    clearTimeout(simpleAutosaveTimer);
    setSimpleSyncState('pending');
    simpleAutosaveTimer = setTimeout(function() {
      autosaveSimpleDraft({silent: true});
    }, delay === undefined ? 650 : delay);
  }

  async function recoverSimpleFieldDraft(options) {
    if (!qlCurrentUser) return false;
    const opts = options || {};
    try {
      const group = simpleAdminGroup();
      const payload = {
        client_draft_id: ensureSimpleClientDraftId(Number(qlOtrActiveTapeId || simpleOpenedCardId || 0)),
        stream_type: normalizeSimpleStream(simpleCurrentStream),
        ensure_open: 1
      };
      if (group && group.id) payload.group_id = Number(group.id);
      const data = await qlApi('on_the_go_field_recover', payload);
      if (!data.ok) {
        setSimpleSyncState('retry_needed', 'Черновик не восстановлен');
        return false;
      }
      const applied = applySimpleDraftEnvelope(data, {force: !!opts.force});
      await refreshSimpleProofStates();
      if (applied && data.draft && String(data.draft.raw_notes || '').trim()) {
        simpleStatus('Восстановлен незакрытый черновик.');
      }
      return applied;
    } catch (error) {
      setSimpleSyncState('retry_needed', 'Нет связи. Повторить');
      return false;
    }
  }

  function operationIdForSignature(signature) {
    if (simplePendingOperationId && simplePendingOperationSignature === signature) {
      return simplePendingOperationId;
    }
    simplePendingOperationId = simpleToken('op');
    simplePendingOperationSignature = signature;
    return simplePendingOperationId;
  }

	  async function beginSimpleProofState(file, captureId) {
	    if (!file) return null;
	    const clientUploadId = simpleSelectedUploadId || simpleToken('upload');
	    simpleSelectedUploadId = clientUploadId;
    await autosaveSimpleDraft({force: true, silent: true});

    const payload = {
      client_upload_id: clientUploadId,
      client_draft_id: ensureSimpleClientDraftId(Number(qlOtrActiveTapeId || simpleOpenedCardId || 0)),
      draft_id: simpleDraftId || undefined,
	      capture_id: captureId || undefined,
	      original_name: file.name || '',
	      mime_type: file.type || '',
	      size_bytes: file.size || 0,
	      proof_role: simpleScannerFile === file ? 'scanner_cleaned_pdf' : 'attachment',
	      proof_bundle_id: simpleScannerFile === file ? simpleScannerBundleId : undefined,
	      metadata_json: simpleScannerFile === file && simpleScannerMetadata ? JSON.stringify(simpleScannerMetadata) : undefined
	    };
    const data = await qlApi('on_the_go_proof_state_begin', payload);
    if (data.ok && data.proof_state) {
      simpleProofStates = simpleProofStates.filter(function(row) {
        return String(row.client_upload_id || '') !== String(clientUploadId);
      }).concat([data.proof_state]);
      renderSimpleProofStates(simpleProofStates);
      return data.proof_state;
    }
    return null;
  }

	  async function failSimpleProofState(clientUploadId, error, captureId) {
	    if (!clientUploadId) return null;
    try {
      const data = await qlApi('on_the_go_proof_state_fail', {
        client_upload_id: clientUploadId,
        client_draft_id: ensureSimpleClientDraftId(Number(qlOtrActiveTapeId || simpleOpenedCardId || 0)),
        draft_id: simpleDraftId || undefined,
        capture_id: captureId || undefined,
        status: 'retry_needed',
        last_error: error || 'upload_failed'
      });
      if (data.ok && data.proof_state) {
        simpleProofStates = simpleProofStates.filter(function(row) {
          return String(row.client_upload_id || '') !== String(clientUploadId);
        }).concat([data.proof_state]);
        renderSimpleProofStates(simpleProofStates);
        return data.proof_state;
      }
    } catch (innerError) {}
	    return null;
	  }

	  async function uploadSimpleScannerOriginal(captureId, metadata) {
	    if (!simpleScannerOriginalFile || !simpleScannerBundleId || !captureId) return null;
	    const originalUploadId = String(simpleScannerBundleId) + ':original:' + String(captureId);
	    const upload = await qlUploadOnTheGoFile(captureId, null, {
	      file: simpleScannerOriginalFile,
	      client_upload_id: originalUploadId,
	      client_draft_id: simpleClientDraftId || undefined,
	      draft_id: simpleDraftId || undefined,
	      proof_role: 'scanner_original',
	      proof_bundle_id: simpleScannerBundleId,
	      metadata_json: JSON.stringify(Object.assign({}, metadata || simpleScannerMetadata || {}, {
	        artifact_role: 'scanner_original',
	        generated_at: new Date().toISOString()
	      }))
	    });
	    if (!upload || !upload.ok) {
	      throw new Error((upload && upload.error) || 'scanner_original_upload_failed');
	    }
	    return upload.file || null;
	  }

  async function refreshSimpleProofStates() {
    if (!simpleDraftId) return [];
    try {
      const data = await qlApi('on_the_go_proof_state_list', {draft_id: simpleDraftId});
      if (data.ok) {
        simpleProofStates = data.proof_states || [];
        renderSimpleProofStates(simpleProofStates);
        restoreSimpleProofRetryContextFromState();
        return simpleProofStates;
      }
    } catch (error) {}
    return simpleProofStates;
  }

	  async function retrySimpleProofUpload() {
	    const fileInput = document.getElementById('otrSimpleFile');
	    const selectedFile = getSimpleSelectedProofFile(fileInput);
	    if (!selectedFile) {
	      return autosaveSimpleDraft({force: true});
	    }

    await refreshSimpleProofStates();
    const state = findSimpleRetryProofState();
    const storedContext = getSimpleProofRetryContext(state);
    const captureId = simpleProofRetryTargetCaptureId(state, storedContext);
    if (!captureId) {
      setSimpleSyncState('retry_needed', 'Нужно сохранить строку перед фото');
      simpleStatus('Не вижу исходную строку для фото. Денежная строка не отправлялась повторно.');
      return false;
    }

    const context = rememberSimpleProofRetryContext(Object.assign({}, storedContext || {}, {
      client_upload_id: (state && state.client_upload_id) || (storedContext && storedContext.client_upload_id) || simpleSelectedUploadId || simpleToken('upload'),
      capture_id: captureId,
      client_draft_id: (storedContext && storedContext.client_draft_id) || simpleClientDraftId,
      draft_id: Number((state && state.draft_id) || (storedContext && storedContext.draft_id) || simpleDraftId || 0),
      tape_id: Number((storedContext && storedContext.tape_id) || qlOtrActiveTapeId || simpleOpenedCardId || 0),
      session_id: Number((storedContext && storedContext.session_id) || simpleSessionId || 0),
      stream_type: (storedContext && storedContext.stream_type) || simpleCurrentStream
    }));
    applySimpleProofRetryContext(context);
    simpleSelectedUploadId = context && context.client_upload_id ? context.client_upload_id : (simpleSelectedUploadId || simpleToken('upload'));

    simpleStatus('Повторяю отправку фото...');
    setSimpleSyncState('pending', 'Отправляю фото...');

    const proofState = await beginSimpleProofState(selectedFile, captureId);
    const uploadId = proofState && proofState.client_upload_id ? proofState.client_upload_id : simpleSelectedUploadId;
    simpleSelectedUploadId = uploadId;
    rememberSimpleProofRetryContext({
      client_upload_id: uploadId,
      capture_id: captureId,
      client_draft_id: simpleClientDraftId,
      draft_id: simpleDraftId || undefined,
      tape_id: Number(qlOtrActiveTapeId || simpleOpenedCardId || 0),
      session_id: simpleSessionId || 0,
      stream_type: simpleCurrentStream
    });

	    let upload = {ok: false, error: 'upload_not_started'};
	    try {
	      const sourceFile = simpleScannerFile === selectedFile
	        ? await uploadSimpleScannerOriginal(captureId, simpleScannerMetadata)
	        : null;
	      upload = await qlUploadOnTheGoFile(captureId, fileInput, {
	        file: selectedFile,
	        client_upload_id: uploadId,
	        client_draft_id: simpleClientDraftId,
	        draft_id: simpleDraftId || undefined,
	        proof_role: simpleScannerFile === selectedFile ? 'scanner_cleaned_pdf' : 'attachment',
	        proof_bundle_id: simpleScannerFile === selectedFile ? simpleScannerBundleId : undefined,
	        source_file_id: sourceFile && sourceFile.id ? sourceFile.id : undefined,
	        metadata_json: simpleScannerFile === selectedFile && simpleScannerMetadata
	          ? JSON.stringify(Object.assign({}, simpleScannerMetadata, {
	              artifact_role: 'scanner_cleaned_pdf',
	              source_file_id: sourceFile && sourceFile.id ? sourceFile.id : null
	            }))
	          : undefined
	      });
    } catch (error) {
      upload = {ok: false, error: error && error.message ? error.message : 'upload_failed'};
    }

    if (!upload.ok) {
      await failSimpleProofState(uploadId, upload.error || 'upload_failed', captureId);
      await refreshSimpleProofStates();
      setSimpleSyncState('retry_needed', 'Фото не загрузилось');
      simpleStatus('Фото не загрузилось. Денежная строка не отправлялась повторно.');
      return false;
    }

    if (upload.proof_state) {
      simpleProofStates = simpleProofStates.filter(function(row) {
        return String(row.client_upload_id || '') !== String(uploadId);
      }).concat([upload.proof_state]);
      renderSimpleProofStates(simpleProofStates);
	    }
	    await refreshSimpleProofStates();
	    clearSimpleSelectedProofFile();
	    clearSimpleProofRetryContext();
	    setSimpleSyncState('saved', 'Фото сохранено');
	    simpleStatus('Фото прикреплено.');
    return true;
  }

  function simpleStreamMeta(stream) {
    const type = normalizeSimpleStream(stream || simpleCurrentStream);
    return type === 'card'
      ? {
          type,
          label: 'Карта',
          short: 'Карта',
          title: 'Живой отчет: карта',
          amountLabel: 'Карта',
          amountHelp: 'Карточные расходы ведутся от нуля и не меняют кассу.',
          placeholder: '-45 продукты\n-67 топливо\n-120 расход по карте'
        }
      : {
          type,
          label: 'Наличные',
          short: 'Наличные',
          title: 'Живой отчет',
          amountLabel: 'Дали',
          amountHelp: 'Сумма на руках для этого отчета. Дополнительные приходы пишите строками со знаком +.',
          placeholder: '-45 продукты\n-67 топливо\n+100 получил от руководителя'
        };
  }

  function syncSimpleStreamChrome() {
    const meta = simpleStreamMeta();
    const card = document.getElementById('otrSimpleCard');
    const kicker = document.getElementById('otrSimpleStreamKicker');
    const switchBtn = document.getElementById('otrStreamSwitchBtn');
    const notes = document.getElementById('otrSimpleNotes');
    const amount = document.getElementById('otrAdminAmount');
    const quickBar = document.getElementById('otrQuickCaptureBar');

    document.body.classList.toggle('otr-stream-cash', meta.type === 'cash');
    document.body.classList.toggle('otr-stream-card', meta.type === 'card');

    if (card) {
      card.classList.toggle('stream-cash', meta.type === 'cash');
      card.classList.toggle('stream-card', meta.type === 'card');
      card.dataset.otrStream = meta.type;
    }
    if (kicker) kicker.textContent = meta.label;
    if (switchBtn) {
      switchBtn.textContent = meta.short;
      switchBtn.setAttribute('aria-label', 'Сменить поток: сейчас ' + meta.label);
      switchBtn.setAttribute('title', 'Сейчас ' + meta.label + '. Нажмите, чтобы сменить поток.');
    }
    if (quickBar) {
      quickBar.classList.toggle('stream-cash', meta.type === 'cash');
      quickBar.classList.toggle('stream-card', meta.type === 'card');
      quickBar.dataset.otrStream = meta.type;
    }
    if (notes) notes.setAttribute('placeholder', meta.placeholder);
    if (amount) {
      amount.readOnly = true;
      amount.setAttribute('aria-readonly', 'true');
    }
  }

  function setSimpleStream(stream, options) {
    const opts = options || {};
    simpleCurrentStream = normalizeSimpleStream(stream);
    if (!opts.keepChoiceState) {
      simpleStreamChosen = opts.chosen !== false;
    }
    syncSimpleStreamChrome();
    setSimpleAdminModeCopy(simpleIsAdminMode());
    renderSimpleResult();
  }

  function hideStreamGate() {
    const gate = document.getElementById('otrStreamGate');
    if (gate) {
      gate.classList.add('hidden');
      gate.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('otr-stream-gate-open');
  }

  function showStreamGate(options) {
    const opts = options || {};
    const gate = document.getElementById('otrStreamGate');
    if (gate) {
      gate.classList.remove('hidden');
      gate.setAttribute('aria-hidden', 'false');
    }
    hideSimpleEditor();
    const cards = document.getElementById('otrReportCardsPanel');
    if (cards) {
      cards.classList.add('hidden');
      cards.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('otr-cards-open');
    document.body.classList.add('otr-stream-gate-open');
    if (typeof qlSaveModuleState === 'function') {
      qlSaveModuleState('ontherun', {screen: 'stream_gate', stream_type: simpleCurrentStream});
    }
    qlWriteBrowserState('ontherun', {screen: 'stream_gate', stream_type: simpleCurrentStream}, opts.history || '');
  }

  function showSimpleEditor(options) {
    const opts = options || {};
    hideStreamGate();
    const card = document.getElementById('otrSimpleCard');
    if (card) {
      card.classList.remove('hidden');
      card.setAttribute('aria-hidden', 'false');
    }
    document.body.classList.add('otr-editor-open');
    if (typeof qlSaveModuleState === 'function') {
      qlSaveModuleState('ontherun', {
        screen: 'editor',
        stream_type: simpleCurrentStream,
        tape_id: Number(simpleOpenedCardId || qlOtrActiveTapeId || 0)
      });
    }
    qlWriteBrowserState('ontherun', {
      screen: 'editor',
      stream_type: simpleCurrentStream,
      tape_id: Number(simpleOpenedCardId || qlOtrActiveTapeId || 0)
    }, opts.history || '');
  }

  function hideSimpleEditor() {
    const card = document.getElementById('otrSimpleCard');
    if (card) {
      card.classList.add('hidden');
      card.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('otr-editor-open');
  }

  function syncSimpleEditorActions(card) {
    simpleCurrentCard = card || simpleCurrentCard || null;
    const shell = document.getElementById('otrSimpleCard');
    const cardId = Number(simpleCurrentCard && (simpleCurrentCard.id || simpleCurrentCard.tape_id) || 0);
    if (cardId > 0) {
      simpleOpenedCardId = cardId;
    }
    if (shell) {
      if (cardId > 0) {
        shell.dataset.otrOpenCardId = String(cardId);
      } else {
        delete shell.dataset.otrOpenCardId;
      }
    }
    const submit = document.getElementById('otrSimpleSubmitBtn');
    const del = document.getElementById('otrSimpleDeleteBtn');
    const summary = simpleCurrentCard && (simpleCurrentCard.card_summary || simpleCurrentCard.summary) ? (simpleCurrentCard.card_summary || simpleCurrentCard.summary) : {};
    const canSubmit = simpleCurrentCard
      && simpleCurrentCard.card_state === 'draft'
      && Number(summary.records_count || 0) > 0
      && typeof window.qlSubmitOtrReportCard === 'function';
    const canDelete = simpleCurrentCard
      && Number(simpleCurrentCard.id || simpleCurrentCard.tape_id || 0) > 0
      && simpleCurrentCard.card_state === 'draft'
      && typeof window.qlDeleteOtrReportCard === 'function';

    if (submit) submit.classList.toggle('hidden', !canSubmit);
    if (del) del.classList.toggle('hidden', !canDelete);
    syncSimpleProofsButton();
  }

  function simpleCurrentProofCardId() {
    const shellCardId = Number(document.getElementById('otrSimpleCard')?.dataset?.otrOpenCardId || 0);
    return shellCardId
      || Number(simpleOpenedCardId || 0)
      || Number(simpleCurrentCard && (simpleCurrentCard.id || simpleCurrentCard.tape_id) || 0)
      || Number(qlOtrActiveTapeId || 0);
  }

  function simpleCurrentProofFilesCount() {
    const summary = simpleCurrentCard && (simpleCurrentCard.card_summary || simpleCurrentCard.summary)
      ? (simpleCurrentCard.card_summary || simpleCurrentCard.summary)
      : {};
    return Number(summary.files_count || 0) || 0;
  }

  function simpleHasSavedProofs() {
    const cardFiles = simpleCurrentProofFilesCount();
    return cardFiles > 0;
  }

  function syncSimpleProofsButton() {
    const button = document.getElementById('otrSimpleProofsBtn');
    if (!button) return;
    const cardId = simpleCurrentProofCardId();
    const show = cardId > 0 && simpleHasSavedProofs();
    button.classList.toggle('hidden', !show);
    button.disabled = !show;
  }

  function toggleSimpleUiBusyState(busy) {
    simpleUiBusy = !!busy;
    const ids = [
      'otrSimpleSaveBtn',
      'otrSimpleEditBtn',
      'otrSimpleSubmitBtn',
      'otrSimpleDeleteBtn',
      'otrAutosaveRetryBtn',
      'otrSimpleProofsBtn',
      'otrCardsBackBtn',
      'otrEditorBackBtn',
      'otrSimpleProofStateRefresh'
    ];
    ids.forEach(function(id) {
      const element = document.getElementById(id);
      if (!element) return;
      element.disabled = simpleUiBusy && !element.classList.contains('persistent-action');
    });
  }

  async function withSimpleUiBusy(action) {
    if (typeof action !== 'function') return;
    if (simpleUiBusy) return;
    toggleSimpleUiBusyState(true);
    try {
      return await action();
    } finally {
      toggleSimpleUiBusyState(false);
    }
  }

  async function withSimpleActionBusy(button, action, options) {
    if (typeof action !== 'function') return;
    const buttonEl = typeof button === 'string' ? document.getElementById(button) : button;
    const opts = options || {};
    const wasBusy = !!simpleUiBusy;
    const busyLabel = opts.label || '';
    const restoreText = buttonEl ? buttonEl.textContent || '' : '';

    if (buttonEl && !wasBusy) {
      if (busyLabel) buttonEl.textContent = busyLabel;
      buttonEl.classList.add('is-busy');
      buttonEl.setAttribute('aria-busy', 'true');
      buttonEl.dataset.qlSimpleBusyText = restoreText;
    }

    try {
      return await withSimpleUiBusy(action);
    } finally {
      if (buttonEl && !wasBusy) {
        buttonEl.textContent = buttonEl.dataset.qlSimpleBusyText || restoreText || '';
        buttonEl.classList.remove('is-busy');
        buttonEl.removeAttribute('aria-busy');
        if (typeof buttonEl.dataset !== 'undefined') {
          buttonEl.removeAttribute('data-ql-simple-busy-text');
        }
      }
    }
  }

  function setSimpleEditMode(enabled) {
    simpleEditMode = !!enabled;
    const notes = document.getElementById('otrSimpleNotes');
    const save = document.getElementById('otrSimpleSaveBtn');
    const edit = document.getElementById('otrSimpleEditBtn');
    const attachButtons = document.querySelectorAll('[data-otr-attach]');
    const card = document.getElementById('otrSimpleCard');

    if (notes) {
      notes.readOnly = !simpleEditMode;
      notes.setAttribute('aria-readonly', simpleEditMode ? 'false' : 'true');
    }
    if (save) save.classList.toggle('hidden', !simpleEditMode);
    if (edit) {
      edit.classList.remove('hidden');
      edit.classList.toggle('is-fixing', simpleEditMode);
      edit.textContent = simpleEditMode ? 'Готово' : 'Редактировать';
      edit.setAttribute('aria-label', simpleEditMode ? 'Зафиксировать записи' : 'Начать редактирование');
      edit.setAttribute('title', simpleEditMode ? 'Зафиксировать' : 'Редактировать');
    }
    if (card) card.classList.toggle('is-view-mode', !simpleEditMode);
    attachButtons.forEach(function(button) {
      button.disabled = !simpleEditMode;
    });
  }

  async function returnToCards(options) {
    const opts = options || {};
    if (simpleDirty) {
      await autosaveSimpleDraft({force: true, silent: true});
    }
    if (typeof window.qlOpenOtrReportCards === 'function') {
      await window.qlOpenOtrReportCards({history: opts.history || ''});
    } else {
      hideSimpleEditor();
    }
  }

  async function openDefaultOnTheGoScreen(options) {
    const requestedScreen = options && (options.ontherun_screen || options.screen)
      ? String(options.ontherun_screen || options.screen)
      : '';
    if (requestedScreen === 'stream_gate') {
      if (options && options.stream_type) setSimpleStream(options.stream_type, {chosen: true});
      showStreamGate();
      return;
    }
    if (requestedScreen === 'cards' && typeof window.qlOpenOtrReportCards === 'function') {
      if (options && options.stream_type) setSimpleStream(options.stream_type, {chosen: true});
      await window.qlOpenOtrReportCards({
        archivedOnly: !!(options && (options.archivedOnly || options.archived_only)),
        forceCards: true
      });
      return;
    }
    const pinnedTapeId = Number(window.qlOtrPinnedTapeId || 0);
    if (pinnedTapeId > 0 && !(options && (options.tape_id || options.tapeId))) {
      options = Object.assign({}, options || {}, {force: true, tape_id: pinnedTapeId});
    }
    if (options && options.stream_type) {
      setSimpleStream(options.stream_type, {chosen: true});
    }
    if (!simpleStreamChosen && !(options && (options.tape_id || options.tapeId))) {
      const recoveredCash = await recoverStoredSimpleFieldDraft('cash', {force: true});
      if (recoveredCash) {
        showSimpleEditor();
        return;
      }
      const recoveredCard = await recoverStoredSimpleFieldDraft('card', {force: true});
      if (recoveredCard) {
        showSimpleEditor();
        return;
      }
      showStreamGate();
      return;
    }
    if (document.body.classList.contains('otr-editor-open') && simpleOpenedCardId > 0 && !(options && (options.tape_id || options.tapeId))) {
      await recoverSimpleFieldDraft({force: false});
      return;
    }
    await loadSimpleOnTheGo(options || {force: false});
    const notes = document.getElementById('otrSimpleNotes');
    if ((requestedScreen === 'editor') || (notes && notes.value.trim())) {
      showSimpleEditor();
    } else if (typeof window.qlOpenOtrReportCards === 'function') {
      await window.qlOpenOtrReportCards();
    } else {
      hideSimpleEditor();
    }
  }

  function simpleStatus(message) {
    const el = document.getElementById('otrSimpleStatus');
    if (el) el.textContent = message || '';
  }

  function normalizeSignedAmount(raw) {
    let value = String(raw || '').replace(/\s/g, '');
    if (/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/.test(value)) {
      value = value.replace(/\./g, '');
    }
    value = value.replace(',', '.');
    const amount = Math.abs(Number(value));
    return Number.isFinite(amount) ? amount : 0;
  }

  function parseSimpleSignedNotes(text, stream) {
    const streamType = normalizeSimpleStream(stream || simpleCurrentStream);
    const normalized = String(text || '').replace(/\r/g, '\n').replace(/;/g, '\n');
    const parts = normalized
      .split(/\n|,(?=\s*[+-]\s*\d)/g)
      .map(function(part) { return part.trim(); })
      .filter(Boolean);

    const items = [];
    const skipped = [];
    const re = /^([+-])\s*((?:\d{1,3}(?:[ .]\d{3})+|\d+)(?:[,.]\d+)?)\s*(.*)$/;

    parts.forEach(function(part) {
      const match = part.match(re);
      if (!match) {
        skipped.push(part);
        return;
      }

      const amount = normalizeSignedAmount(match[2]);
      if (amount <= 0) {
        skipped.push(part);
        return;
      }

      if (streamType === 'card' && match[1] === '+') {
        skipped.push(part);
        return;
      }

      items.push({
        sign: match[1],
        type: streamType === 'card' ? 'noncash_out' : (match[1] === '+' ? 'cash_in' : 'cash_out'),
        amount,
        description: (match[3] || '').trim(),
        source: part
      });
    });

    return {items, skipped};
  }

  function simpleMoney(value) {
    return typeof qlOtrCurrency === 'function' ? qlOtrCurrency(value || 0) : qlCurrency(value || 0);
  }

  function simpleAdminAmount() {
    return normalizeSignedAmount(document.getElementById('otrAdminAmount')?.value || '0');
  }

  function simpleIsAdminMode() {
    return Array.isArray(qlGroups) && qlGroups.some(function(group) {
      const access = String(group.access_level || '').toLowerCase();
      const role = String(group.role || '').toLowerCase();
      const permissions = group.permissions || {};
      return access === 'advanced' || role === 'admin' || role === 'owner' || permissions.mode === 'advanced';
    });
  }

  function simpleAdminGroup() {
    const groups = Array.isArray(qlGroups) ? qlGroups : [];
    const selectedAdvanced = groups.find(function(group) {
      return qlAdvanceGroupId && String(group.id) === String(qlAdvanceGroupId);
    });
    if (selectedAdvanced) return selectedAdvanced;

    const selectedLedger = groups.find(function(group) {
      return qlLedgerScopeMode === 'group' && qlLedgerGroupId && String(group.id) === String(qlLedgerGroupId);
    });
    if (selectedLedger) return selectedLedger;

    return groups.find(function(group) {
      const access = String(group.access_level || '').toLowerCase();
      const role = String(group.role || '').toLowerCase();
      const permissions = group.permissions || {};
      return access === 'advanced' || role === 'admin' || role === 'owner' || permissions.mode === 'advanced';
    }) || groups.find(function(group) {
      const access = String(group.access_level || '').toLowerCase();
      return access === 'manager';
    }) || null;
  }

  function simpleAdminLedgerPayload() {
    const group = simpleAdminGroup();
    if (group && group.id) {
      return {group_id: Number(group.id)};
    }

    return typeof qlCurrentLedgerPayload === 'function' ? qlCurrentLedgerPayload({}) : {};
  }

  async function simpleAdminLedgerBaseAmount() {
    try {
      const data = await qlApi('ledger_balance', simpleAdminLedgerPayload());
      if (data.ok && data.summary) {
        const value = data.summary.available_cash_balance
          ?? data.summary.cash_balance
          ?? data.summary.balance
          ?? 0;
        return String(value || 0).replace(/\.00$/, '');
      }
    } catch (error) {}

    return '';
  }

  function simpleCardCashReceived(activeTape) {
    if (!activeTape) return '';
    const summary = activeTape.summary || activeTape.card_summary || {};
    const value = activeTape.cash_received
      ?? summary.admin_cash_in
      ?? summary.before_amount
      ?? '';
    return String(value).replace(/\.00$/, '');
  }

  function setSimpleAdminModeCopy(isAdminMode) {
    const label = document.getElementById('otrAdminAmountLabel');
    const help = document.getElementById('otrAdminAmountHelp');
    const group = isAdminMode ? simpleAdminGroup() : null;
    const meta = simpleStreamMeta();

    if (meta.type === 'card') {
      if (label) label.textContent = meta.amountLabel;
      if (help) help.textContent = meta.amountHelp;
      return;
    }

    if (label) {
      label.textContent = isAdminMode ? 'Касса' : 'Дали';
    }

    if (help) {
      help.textContent = isAdminMode
        ? 'Касса текущего отчета' + (group && group.name ? ' группы “' + group.name + '”' : '') + '. Это информативное поле, расходы пишите ниже.'
        : 'Сумма, которую выдали на этот отчет. Дополнительные приходы пишите строками со знаком +.';
    }
  }

  async function simpleBaseAmount(activeTape, isAdminMode) {
    if (simpleCurrentStream === 'card' || normalizeSimpleStream(activeTape && activeTape.stream_type) === 'card') {
      return '0';
    }

    if (activeTape && Number(activeTape.advance_id || 0) > 0) {
      return simpleCardCashReceived(activeTape);
    }

    if (!isAdminMode) {
      return activeTape ? simpleCardCashReceived(activeTape) : '';
    }

    const hasRows = activeTape && (
      Number(activeTape.summary && activeTape.summary.records_count || 0) > 0
      || Number(activeTape.card_summary && activeTape.card_summary.records_count || 0) > 0
    );
    if (hasRows) {
      return simpleCardCashReceived(activeTape);
    }

    const group = simpleAdminGroup();
    const ledgerBase = await simpleAdminLedgerBaseAmount();
    if (ledgerBase !== '') return ledgerBase;

    if (activeTape && group && String(activeTape.group_id || '') === String(group.id || '')) {
      return simpleCardCashReceived(activeTape);
    }

    return '0';
  }

  function renderSimpleResult() {
    const result = document.getElementById('otrSimpleResult');
    const preview = document.getElementById('otrSimplePreview');
    const notes = document.getElementById('otrSimpleNotes')?.value || '';
    const streamType = normalizeSimpleStream(simpleCurrentStream);
    const parsed = parseSimpleSignedNotes(notes, streamType);
    const admin = streamType === 'card' ? 0 : simpleAdminAmount();
    const isAdminMode = simpleIsAdminMode();
    let income = 0;
    let expense = 0;

    parsed.items.forEach(function(item) {
      if (item.sign === '+') income += item.amount;
      if (item.sign === '-') expense += item.amount;
    });

    const left = streamType === 'card' ? 0 - expense : admin + income - expense;

    if (result) {
      result.innerHTML = streamType === 'card'
        ? `
          <div><span>Было</span><b>${simpleMoney(0)}</b></div>
          <div><span>Карта</span><b>${simpleMoney(expense)}</b></div>
          <div><span>Касса</span><b>${simpleMoney(0)}</b></div>
          <div class="is-negative"><span>Стало</span><b>${simpleMoney(left)}</b></div>
        `
        : `
          <div><span>Было</span><b>${simpleMoney(admin)}</b></div>
          <div><span>Приход</span><b>${simpleMoney(income)}</b></div>
          <div><span>Расход</span><b>${simpleMoney(expense)}</b></div>
          <div class="${left < 0 ? 'is-negative' : ''}"><span>Стало</span><b>${simpleMoney(left)}</b></div>
        `;
    }

    if (preview) {
      if (!parsed.items.length) {
        preview.innerHTML = streamType === 'card'
          ? '<p class="soft-note">Карта ведется отдельным потоком: только строки со знаком -. Пример: -45 продукты, -67 топливо.</p>'
          : '<p class="soft-note">Введите строки со знаком + или -. Пример: -45 продукты, -67 топливо, +100 получил от руководителя.</p>';
      } else {
        preview.innerHTML = parsed.items.map(function(item) {
          const cls = item.type === 'cash_in' ? 'income' : (item.type === 'noncash_out' ? 'card-expense' : 'expense');
          const label = item.type === 'cash_in' ? 'Приход' : (item.type === 'noncash_out' ? 'Карта' : 'Расход');
          const desc = item.description || 'Без описания';
          return `
            <article class="otr-simple-row ${cls}">
              <span>${escapeHtml(label)}</span>
              <b>${item.sign}${simpleMoney(item.amount)}</b>
              <small>${escapeHtml(desc)}</small>
            </article>
          `;
        }).join('');

        if (parsed.skipped.length) {
          preview.innerHTML += '<p class="soft-note danger-note">Не распознано: ' + escapeHtml(parsed.skipped.join(' | ')) + '</p>';
        }
      }
    }

    const pill = document.getElementById('otrSimpleStatusPill');
    if (pill) {
      const cardState = simpleCurrentCard && simpleCurrentCard.card_state ? String(simpleCurrentCard.card_state) : '';
      const tapeStatus = simpleCurrentCard && simpleCurrentCard.status ? String(simpleCurrentCard.status) : '';
      if (cardState === 'submitted') pill.textContent = 'На проверке';
      else if (cardState === 'included') pill.textContent = 'В отчете';
      else if (tapeStatus === 'closed') pill.textContent = 'Закрыто';
      else pill.textContent = parsed.items.length ? 'Заполнено' : 'Черновик';
    }

    return {admin, income, expense, left, parsed};
  }

  function appendSimpleQuickLine(type) {
    const notes = document.getElementById('otrSimpleNotes');
    if (!notes || !simpleEditMode) return;

    const streamType = normalizeSimpleStream(simpleCurrentStream);
    let prefix = '- ';
    if (type === 'cash_in') {
      prefix = '+ ';
      if (streamType !== 'cash') {
        setSimpleStream('cash', {chosen: true});
      }
    } else if (type === 'noncash_out') {
      prefix = '- ';
      if (streamType !== 'card') {
        setSimpleStream('card', {chosen: true});
      }
    } else if (type === 'cash_out') {
      prefix = '- ';
      if (streamType !== 'cash') {
        setSimpleStream('cash', {chosen: true});
      }
    }

    const current = notes.value || '';
    const separator = current && !current.endsWith('\n') ? '\n' : '';
    notes.value = current + separator + prefix;
    simpleDirty = true;
    renderSimpleResult();
    notes.focus();
    notes.selectionStart = notes.value.length;
    notes.selectionEnd = notes.value.length;
    scheduleSimpleAutosave(350);
  }

	  function openSimpleProofPicker() {
	    const input = document.getElementById('otrSimpleFile');
	    if (!input || !simpleEditMode) return;
	    input.setAttribute('accept', 'image/*');
	    input.setAttribute('capture', 'environment');
	    input.click();
	  }

	  function receiptScannerEls() {
	    return {
	      modal: document.getElementById('receiptScannerModal'),
	      input: document.getElementById('receiptScannerSourceInput'),
	      stage: document.getElementById('receiptScannerStage'),
	      canvas: document.getElementById('receiptScannerCanvas'),
	      overlay: document.getElementById('receiptScannerOverlay'),
	      empty: document.getElementById('receiptScannerEmpty'),
	      status: document.getElementById('receiptScannerStatus'),
	      clean: document.getElementById('receiptScannerCleanLevel'),
	      mono: document.getElementById('receiptScannerMono')
	    };
	  }

	  function receiptScannerStatus(message) {
	    const status = document.getElementById('receiptScannerStatus');
	    if (status) status.textContent = message || '';
	  }

	  function openReceiptScanner(options) {
	    if (!simpleEditMode) return;
	    const els = receiptScannerEls();
	    if (!els.modal) return;
	    if (els.input) {
	      els.input.setAttribute('accept', 'image/*');
	      els.input.setAttribute('capture', 'environment');
	    }
	    els.modal.classList.remove('hidden');
	    els.modal.setAttribute('aria-hidden', 'false');
	    document.body.classList.add('modal-open');
	    receiptScannerStatus('Камера откроется, если браузер/PWA разрешает capture; иначе выберите фото из файлов.');
	    setTimeout(function() {
	      renderReceiptScanner();
	      if (options && options.pick && els.input) els.input.click();
	    }, 60);
	  }

	  function closeReceiptScanner() {
	    const els = receiptScannerEls();
	    if (!els.modal) return;
	    els.modal.classList.add('hidden');
	    els.modal.setAttribute('aria-hidden', 'true');
	    document.body.classList.remove('modal-open');
	  }

	  function resetReceiptScannerCorners() {
	    receiptScannerCorners = {
	      tl: {x: 0.06, y: 0.06},
	      tr: {x: 0.94, y: 0.06},
	      br: {x: 0.94, y: 0.94},
	      bl: {x: 0.06, y: 0.94}
	    };
	  }

	  function clearReceiptScannerImage() {
	    if (receiptScannerObjectUrl) {
	      try { URL.revokeObjectURL(receiptScannerObjectUrl); } catch (error) {}
	    }
	    receiptScannerObjectUrl = '';
	    receiptScannerImage = null;
	    simpleScannerOriginalFile = null;
	    resetReceiptScannerCorners();
	    renderReceiptScanner();
	  }

	  function loadReceiptScannerFile(file) {
	    if (!file) return;
	    if (!/^image\//.test(file.type || '')) {
	      receiptScannerStatus('Для скана выберите фото. PDF и документы можно прикрепить через скрепку.');
	      return;
	    }
	    clearReceiptScannerImage();
	    receiptScannerStatus('Открываю фото...');
	    const img = new Image();
	    const url = URL.createObjectURL(file);
	    receiptScannerObjectUrl = url;
	    simpleScannerOriginalFile = file;
	    simpleScannerBundleId = simpleScannerBundleId || simpleToken('scan');
	    img.onload = function() {
	      receiptScannerImage = img;
	      resetReceiptScannerCorners();
	      receiptScannerStatus('Поправьте рамку и нажмите “Прикрепить PDF”.');
	      renderReceiptScanner();
	    };
	    img.onerror = function() {
	      receiptScannerStatus('Не удалось открыть фото. Попробуйте другое изображение.');
	      clearReceiptScannerImage();
	    };
	    img.src = url;
	  }

	  function receiptScannerCornerList() {
	    return ['tl', 'tr', 'br', 'bl'];
	  }

	  function receiptScannerDisplaySize(stage, image) {
	    const maxW = Math.max(280, Math.min(stage ? stage.clientWidth - 18 : 340, 720));
	    const maxH = Math.max(300, Math.min(window.innerHeight * 0.58, 640));
	    const ratio = image && image.naturalWidth && image.naturalHeight ? image.naturalWidth / image.naturalHeight : 0.72;
	    let width = maxW;
	    let height = width / ratio;
	    if (height > maxH) {
	      height = maxH;
	      width = height * ratio;
	    }
	    return {
	      width: Math.max(220, Math.round(width)),
	      height: Math.max(260, Math.round(height))
	    };
	  }

	  function renderReceiptScanner() {
	    const els = receiptScannerEls();
	    if (!els.canvas || !els.stage || !els.overlay) return;
	    const canvas = els.canvas;
	    const ctx = canvas.getContext('2d');
	    const dpr = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));

	    if (!receiptScannerImage) {
	      canvas.style.width = '';
	      canvas.style.height = '';
	      canvas.width = 320;
	      canvas.height = 420;
	      ctx.clearRect(0, 0, canvas.width, canvas.height);
	      els.stage.classList.add('is-empty');
	      els.overlay.classList.add('hidden');
	      if (els.empty) els.empty.classList.remove('hidden');
	      return;
	    }

	    els.stage.classList.remove('is-empty');
	    els.overlay.classList.remove('hidden');
	    if (els.empty) els.empty.classList.add('hidden');

	    const size = receiptScannerDisplaySize(els.stage, receiptScannerImage);
	    canvas.style.width = size.width + 'px';
	    canvas.style.height = size.height + 'px';
	    canvas.width = Math.round(size.width * dpr);
	    canvas.height = Math.round(size.height * dpr);
	    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
	    ctx.clearRect(0, 0, size.width, size.height);
	    ctx.drawImage(receiptScannerImage, 0, 0, size.width, size.height);

	    if (!receiptScannerCorners) resetReceiptScannerCorners();
	    const points = receiptScannerCornerList().map(function(key) {
	      return {
	        key,
	        x: receiptScannerCorners[key].x * size.width,
	        y: receiptScannerCorners[key].y * size.height
	      };
	    });

	    ctx.save();
	    ctx.beginPath();
	    points.forEach(function(point, index) {
	      if (index === 0) ctx.moveTo(point.x, point.y);
	      else ctx.lineTo(point.x, point.y);
	    });
	    ctx.closePath();
	    ctx.fillStyle = 'rgba(10,132,255,.12)';
	    ctx.fill();
	    ctx.strokeStyle = '#0a84ff';
	    ctx.lineWidth = 2;
	    ctx.stroke();
	    ctx.restore();

	    const canvasRect = canvas.getBoundingClientRect();
	    const stageRect = els.stage.getBoundingClientRect();
	    els.overlay.style.left = Math.round(canvasRect.left - stageRect.left) + 'px';
	    els.overlay.style.top = Math.round(canvasRect.top - stageRect.top) + 'px';
	    els.overlay.style.width = size.width + 'px';
	    els.overlay.style.height = size.height + 'px';
	    points.forEach(function(point) {
	      const handle = els.overlay.querySelector('[data-receipt-corner="' + point.key + '"]');
	      if (!handle) return;
	      handle.style.left = point.x + 'px';
	      handle.style.top = point.y + 'px';
	    });
	  }

	  function pointerToReceiptCorner(event) {
	    const els = receiptScannerEls();
	    if (!els.overlay || !receiptScannerCorners) return null;
	    const rect = els.overlay.getBoundingClientRect();
	    const x = Math.max(0.01, Math.min(0.99, (event.clientX - rect.left) / Math.max(1, rect.width)));
	    const y = Math.max(0.01, Math.min(0.99, (event.clientY - rect.top) / Math.max(1, rect.height)));
	    return {x, y};
	  }

	  function receiptDistance(a, b) {
	    const dx = (a.x || 0) - (b.x || 0);
	    const dy = (a.y || 0) - (b.y || 0);
	    return Math.sqrt(dx * dx + dy * dy);
	  }

	  function receiptHomography(points) {
	    const p0 = points.tl;
	    const p1 = points.tr;
	    const p2 = points.br;
	    const p3 = points.bl;
	    const dx1 = p1.x - p2.x;
	    const dy1 = p1.y - p2.y;
	    const dx2 = p3.x - p2.x;
	    const dy2 = p3.y - p2.y;
	    const sx = p0.x - p1.x + p2.x - p3.x;
	    const sy = p0.y - p1.y + p2.y - p3.y;
	    const denom = dx1 * dy2 - dx2 * dy1;
	    let g = 0;
	    let h = 0;
	    if (Math.abs(denom) > 0.00001) {
	      g = (sx * dy2 - dx2 * sy) / denom;
	      h = (dx1 * sy - sx * dy1) / denom;
	    }
	    const a = p1.x - p0.x + g * p1.x;
	    const b = p3.x - p0.x + h * p3.x;
	    const c = p0.x;
	    const d = p1.y - p0.y + g * p1.y;
	    const e = p3.y - p0.y + h * p3.y;
	    const f = p0.y;
	    return function(u, v) {
	      const z = g * u + h * v + 1;
	      return {
	        x: (a * u + b * v + c) / z,
	        y: (d * u + e * v + f) / z
	      };
	    };
	  }

	  function receiptSample(src, width, height, x, y) {
	    const sx = Math.max(0, Math.min(width - 1, x));
	    const sy = Math.max(0, Math.min(height - 1, y));
	    const x0 = Math.floor(sx);
	    const y0 = Math.floor(sy);
	    const x1 = Math.min(width - 1, x0 + 1);
	    const y1 = Math.min(height - 1, y0 + 1);
	    const fx = sx - x0;
	    const fy = sy - y0;
	    const i00 = (y0 * width + x0) * 4;
	    const i10 = (y0 * width + x1) * 4;
	    const i01 = (y1 * width + x0) * 4;
	    const i11 = (y1 * width + x1) * 4;
	    const out = [0, 0, 0];
	    for (let channel = 0; channel < 3; channel += 1) {
	      const top = src[i00 + channel] * (1 - fx) + src[i10 + channel] * fx;
	      const bottom = src[i01 + channel] * (1 - fx) + src[i11 + channel] * fx;
	      out[channel] = top * (1 - fy) + bottom * fy;
	    }
	    return out;
	  }

	  function receiptClamp(value) {
	    return Math.max(0, Math.min(255, Math.round(value)));
	  }

	  function cleanReceiptPixel(rgb, cleanLevel, mono) {
	    const contrast = 1 + cleanLevel * 1.45;
	    const brightness = cleanLevel * 18;
	    if (mono) {
	      let gray = rgb[0] * 0.299 + rgb[1] * 0.587 + rgb[2] * 0.114;
	      gray = (gray - 128) * contrast + 128 + brightness;
	      if (cleanLevel > 0.72) gray = gray > 172 - cleanLevel * 34 ? 255 : gray * 0.64;
	      const v = receiptClamp(gray);
	      return [v, v, v];
	    }
	    return [
	      receiptClamp((rgb[0] - 128) * contrast + 128 + brightness),
	      receiptClamp((rgb[1] - 128) * contrast + 128 + brightness),
	      receiptClamp((rgb[2] - 128) * contrast + 128 + brightness)
	    ];
	  }

	  function imageCanvasToBlob(canvas, type, quality) {
	    return new Promise(function(resolve, reject) {
	      canvas.toBlob(function(blob) {
	        if (blob) resolve(blob);
	        else reject(new Error('canvas_blob_failed'));
	      }, type || 'image/jpeg', quality === undefined ? 0.92 : quality);
	    });
	  }

	  async function buildReceiptCleanCanvas() {
	    if (!receiptScannerImage || !receiptScannerCorners) throw new Error('scanner_image_missing');
	    const source = document.createElement('canvas');
	    const maxSide = 1800;
	    const naturalW = receiptScannerImage.naturalWidth || receiptScannerImage.width;
	    const naturalH = receiptScannerImage.naturalHeight || receiptScannerImage.height;
	    const scale = Math.min(1, maxSide / Math.max(naturalW, naturalH));
	    source.width = Math.max(1, Math.round(naturalW * scale));
	    source.height = Math.max(1, Math.round(naturalH * scale));
	    const sctx = source.getContext('2d', {willReadFrequently: true});
	    sctx.drawImage(receiptScannerImage, 0, 0, source.width, source.height);

	    const points = {
	      tl: {x: receiptScannerCorners.tl.x * source.width, y: receiptScannerCorners.tl.y * source.height},
	      tr: {x: receiptScannerCorners.tr.x * source.width, y: receiptScannerCorners.tr.y * source.height},
	      br: {x: receiptScannerCorners.br.x * source.width, y: receiptScannerCorners.br.y * source.height},
	      bl: {x: receiptScannerCorners.bl.x * source.width, y: receiptScannerCorners.bl.y * source.height}
	    };
	    const top = receiptDistance(points.tl, points.tr);
	    const bottom = receiptDistance(points.bl, points.br);
	    const left = receiptDistance(points.tl, points.bl);
	    const right = receiptDistance(points.tr, points.br);
	    const aspect = Math.max(0.28, Math.min(4.8, ((left + right) / 2) / Math.max(1, (top + bottom) / 2)));
	    const outW = Math.round(Math.max(640, Math.min(1400, (top + bottom) / 2)));
	    const outH = Math.round(Math.max(640, Math.min(2400, outW * aspect)));
	    const output = document.createElement('canvas');
	    output.width = outW;
	    output.height = outH;
	    const octx = output.getContext('2d', {willReadFrequently: true});
	    const srcData = sctx.getImageData(0, 0, source.width, source.height).data;
	    const outImage = octx.createImageData(outW, outH);
	    const outData = outImage.data;
	    const map = receiptHomography(points);
	    const cleanInput = document.getElementById('receiptScannerCleanLevel');
	    const monoInput = document.getElementById('receiptScannerMono');
	    const cleanLevel = Math.max(0, Math.min(1, Number(cleanInput && cleanInput.value || 42) / 100));
	    const mono = !monoInput || !!monoInput.checked;

	    for (let y = 0; y < outH; y += 1) {
	      const v = outH > 1 ? y / (outH - 1) : 0;
	      for (let x = 0; x < outW; x += 1) {
	        const u = outW > 1 ? x / (outW - 1) : 0;
	        const srcPoint = map(u, v);
	        const rgb = cleanReceiptPixel(receiptSample(srcData, source.width, source.height, srcPoint.x, srcPoint.y), cleanLevel, mono);
	        const index = (y * outW + x) * 4;
	        outData[index] = rgb[0];
	        outData[index + 1] = rgb[1];
	        outData[index + 2] = rgb[2];
	        outData[index + 3] = 255;
	      }
	    }
	    octx.putImageData(outImage, 0, 0);
	    return output;
	  }

	  function pdfBytesFromJpeg(jpegBytes, imageWidth, imageHeight) {
	    const encoder = new TextEncoder();
	    const chunks = [];
	    const offsets = [];
	    let length = 0;
	    function push(data) {
	      const bytes = typeof data === 'string' ? encoder.encode(data) : data;
	      chunks.push(bytes);
	      length += bytes.length;
	    }
	    function objectStart(number) {
	      offsets[number] = length;
	      push(number + ' 0 obj\n');
	    }
	    const pageW = 595;
	    const pageH = Math.max(420, Math.min(1800, Math.round(pageW * imageHeight / Math.max(1, imageWidth))));
	    const margin = 24;
	    const scale = Math.min((pageW - margin * 2) / imageWidth, (pageH - margin * 2) / imageHeight);
	    const drawW = imageWidth * scale;
	    const drawH = imageHeight * scale;
	    const drawX = (pageW - drawW) / 2;
	    const drawY = (pageH - drawH) / 2;
	    const content = 'q\n' + drawW.toFixed(2) + ' 0 0 ' + drawH.toFixed(2) + ' ' + drawX.toFixed(2) + ' ' + drawY.toFixed(2) + ' cm\n/Im0 Do\nQ\n';

	    push('%PDF-1.4\n');
	    objectStart(1);
	    push('<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
	    objectStart(2);
	    push('<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n');
	    objectStart(3);
	    push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageW + ' ' + pageH + '] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>\nendobj\n');
	    objectStart(4);
	    push('<< /Type /XObject /Subtype /Image /Width ' + imageWidth + ' /Height ' + imageHeight + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegBytes.length + ' >>\nstream\n');
	    push(jpegBytes);
	    push('\nendstream\nendobj\n');
	    objectStart(5);
	    push('<< /Length ' + encoder.encode(content).length + ' >>\nstream\n' + content + 'endstream\nendobj\n');
	    const xrefOffset = length;
	    push('xref\n0 6\n0000000000 65535 f \n');
	    for (let i = 1; i <= 5; i += 1) {
	      push(String(offsets[i] || 0).padStart(10, '0') + ' 00000 n \n');
	    }
	    push('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF\n');

	    const pdf = new Uint8Array(length);
	    let offset = 0;
	    chunks.forEach(function(chunk) {
	      pdf.set(chunk, offset);
	      offset += chunk.length;
	    });
	    return pdf;
	  }

	  async function buildReceiptPdfFile() {
	    const cleanCanvas = await buildReceiptCleanCanvas();
	    const jpegBlob = await imageCanvasToBlob(cleanCanvas, 'image/jpeg', 0.92);
	    const jpegBytes = new Uint8Array(await jpegBlob.arrayBuffer());
	    const pdfBytes = pdfBytesFromJpeg(jpegBytes, cleanCanvas.width, cleanCanvas.height);
	    const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);
	    const fileName = 'findesk-receipt-scan-' + stamp + '.pdf';
	    const pdfBlob = new Blob([pdfBytes], {type: 'application/pdf'});
	    return new File([pdfBlob], fileName, {type: 'application/pdf'});
	  }

	  function openSimpleAdvanceScreen() {
	    if (typeof window.qlSetModule === 'function') {
	      window.qlSetModule('money', {screen: 'advances', label: 'Подотчеты'});
	    }
  }

  function signedTextFromCaptures(items, stream) {
    const streamType = normalizeSimpleStream(stream || simpleCurrentStream);
    return (items || []).filter(function(item) {
      return streamType === 'card'
        ? item.capture_type === 'noncash_out'
        : (item.capture_type === 'cash_in' || item.capture_type === 'cash_out');
    }).slice().sort(function(a, b) {
      const timeA = Date.parse(a.created_at || '') || 0;
      const timeB = Date.parse(b.created_at || '') || 0;
      if (timeA !== timeB) return timeA - timeB;
      return Number(a.id || 0) - Number(b.id || 0);
    }).map(function(item) {
      const sign = item.capture_type === 'cash_in' ? '+' : '-';
      const amount = Number(item.amount || 0).toFixed(2).replace(/\.00$/, '');
      const desc = item.description ? ' ' + item.description : '';
      return sign + amount + desc;
    }).join('\n');
  }

  async function simpleActiveSessionIdFromCard(tapeId, streamType) {
    if (!tapeId) return 0;
    const targetStream = normalizeSimpleStream(streamType || simpleCurrentStream);
    try {
      const data = await qlApi('on_the_go_session_list', {tape_id: tapeId});
      if (!data.ok || !Array.isArray(data.sessions)) {
        return 0;
      }

      const active = data.sessions.find(function(session) {
        return String(session.status || '').toLowerCase() === 'active'
          && String(session.session_type || '').toLowerCase() === targetStream;
      });
      if (active && active.id) {
        return Number(active.id);
      }

      const activeAny = data.sessions.find(function(session) {
        return String(session.status || '').toLowerCase() === 'active';
      });
      if (activeAny && activeAny.id) {
        return Number(activeAny.id);
      }
    } catch (error) {}

    return 0;
  }

  async function applySimpleCardDetail(detailData, options) {
    const opts = options || {};
    const activeTape = detailData.card || {};
    const streamType = normalizeSimpleStream(activeTape.stream_type || simpleCurrentStream || 'cash');
    const detailItems = Array.isArray(detailData.items) ? detailData.items : [];
    const activeSessionId = Number(await simpleActiveSessionIdFromCard(activeTape.id || activeTape.tape_id || 0, streamType));
    const activeItems = activeSessionId > 0
      ? detailItems.filter(function(item) {
          return Number(item.session_id || 0) === activeSessionId;
        })
      : detailItems;
    if (activeSessionId > 0) {
      simpleSessionId = activeSessionId;
    }
    const detailFilesCount = detailItems.reduce(function(total, item) {
      return total + Number(item && item.files_count || 0);
    }, 0);
    activeTape.files_count = Number(activeTape.files_count || detailFilesCount || 0);
    activeTape.summary = Object.assign({}, activeTape.summary || {}, {
      files_count: Number((activeTape.summary && activeTape.summary.files_count) || detailFilesCount || activeTape.files_count || 0)
    });
    const openedId = Number(activeTape.id || activeTape.tape_id || opts.tape_id || opts.tapeId || 0);
    if (openedId > 0) {
      simpleOpenedCardId = openedId;
      qlOtrActiveTapeId = openedId;
      window.qlOtrActiveTapeId = openedId;
    }

    simpleCurrentCard = activeTape;
    simpleReplaceTape = true;
    setSimpleStream(streamType, {chosen: true});

    const isAdminMode = simpleIsAdminMode();
    setSimpleAdminModeCopy(isAdminMode);

    const amountInput = document.getElementById('otrAdminAmount');
    if (amountInput) {
      amountInput.value = await simpleBaseAmount(activeTape, isAdminMode);
    }

    const notes = document.getElementById('otrSimpleNotes');
    if (notes && (opts.force || !simpleDirty)) {
      notes.value = signedTextFromCaptures(activeItems, streamType);
    }

    renderSimpleResult();
    syncSimpleEditorActions(activeTape);
    setSimpleEditMode(opts.viewOnly ? false : true);
    simpleStatus('');
  }

  async function loadSimpleOnTheGo(options) {
    if (simpleLoading) return;
    simpleLoading = true;

    try {
      if (options && options.stream_type) {
        setSimpleStream(options.stream_type, {chosen: true});
      }
      const requestedTapeId = Number((options && (options.tape_id || options.tapeId)) || 0);
      if (!requestedTapeId && document.body.classList.contains('otr-editor-open') && simpleOpenedCardId > 0) {
        return;
      }

      if (requestedTapeId > 0) {
        const detailData = await qlApi('on_the_go_card_detail', {id: requestedTapeId});
        if (!detailData.ok) {
          simpleStatus('Не удалось открыть карточку: ' + (detailData.error || 'unknown'));
          return;
        }

        await applySimpleCardDetail(detailData, {
          force: !!(options && options.force),
          viewOnly: !!(options && options.viewOnly),
          tape_id: requestedTapeId
        });
        simpleClientDraftId = '';
        ensureSimpleClientDraftId(requestedTapeId);
        await autosaveSimpleDraft({force: true, silent: true});
        return;
      }

      const tapesData = await qlApi('on_the_go_tape_list', {stream_type: simpleCurrentStream});
      if (!tapesData.ok) {
        simpleStatus('Ошибка загрузки: ' + (tapesData.error || 'unknown'));
        return;
      }

      const activeId = requestedTapeId || tapesData.active_tape_id || (tapesData.tapes && tapesData.tapes[0] ? tapesData.tapes[0].id : null);
      if (activeId) {
        try {
          qlOtrActiveTapeId = Number(activeId);
          window.qlOtrActiveTapeId = Number(activeId);
        } catch (error) {}
      }

      let activeTape = (tapesData.tapes || []).find(function(tape) {
        return String(tape.id) === String(activeId);
      }) || (!requestedTapeId ? (tapesData.tapes || [])[0] : null) || null;
      const isAdminMode = simpleIsAdminMode();
      setSimpleAdminModeCopy(isAdminMode);
      simpleReplaceTape = false;

      let loadedFromCard = false;
      if (activeId && (requestedTapeId || (activeTape && activeTape.card_state !== 'submitted'))) {
        const detailData = await qlApi('on_the_go_card_detail', {id: activeId});
        if (detailData.ok) {
          activeTape = detailData.card || activeTape;
          const activeStream = normalizeSimpleStream(activeTape.stream_type || simpleCurrentStream || 'cash');
          setSimpleStream(activeStream, {chosen: true});
          const detailItems = Array.isArray(detailData.items) ? detailData.items : [];
          const activeSessionId = Number(await simpleActiveSessionIdFromCard(activeTape.id || activeTape.tape_id || activeId, activeStream));
          if (activeSessionId > 0) {
            simpleSessionId = activeSessionId;
          }
          const activeItems = activeSessionId > 0
            ? detailItems.filter(function(item) {
                return Number(item.session_id || 0) === activeSessionId;
              })
            : detailItems;
          simpleOpenedCardId = Number(activeTape && (activeTape.id || activeTape.tape_id) || activeId || 0);
          loadedFromCard = true;
          simpleReplaceTape = true;
          const notes = document.getElementById('otrSimpleNotes');
          if (notes && (options && options.force || !simpleDirty)) {
            notes.value = signedTextFromCaptures(activeItems, activeStream);
          }
        }
      }

      if (!loadedFromCard) {
        const listData = await qlApi('on_the_go_list', {
          tape_id: activeId || undefined,
          session_type: simpleCurrentStream,
          limit: 200
        });

        if (listData.ok) {
          const notes = document.getElementById('otrSimpleNotes');
          if (notes && (options && options.force || !simpleDirty)) {
            notes.value = signedTextFromCaptures(listData.items || [], simpleCurrentStream);
          }
        }
      }

      simpleCurrentCard = activeTape;
      const amountInput = document.getElementById('otrAdminAmount');
      if (amountInput) {
        amountInput.value = await simpleBaseAmount(activeTape, isAdminMode);
      }

      renderSimpleResult();
      syncSimpleEditorActions(activeTape);
      setSimpleEditMode(options && options.viewOnly ? false : true);
      simpleStatus('');
      await recoverSimpleFieldDraft({force: !!(options && options.force)});
    } finally {
      simpleLoading = false;
    }
  }

	  async function saveSimpleOnTheGo(options) {
	    const opts = options || {};
	    const notes = document.getElementById('otrSimpleNotes')?.value || '';
	    const fileInput = document.getElementById('otrSimpleFile');
	    const selectedProofFile = getSimpleSelectedProofFile(fileInput);
	    const hasFile = !!selectedProofFile;
	    const parsed = renderSimpleResult().parsed;

    if (!notes.trim() && !hasFile && !simpleReplaceTape) {
      simpleStatus('Введите строки отчета или добавьте фото.');
      return false;
    }

    if (notes.trim() && !parsed.items.length) {
      simpleStatus(simpleCurrentStream === 'card'
        ? 'В карточном потоке нужны расходы со знаком -. Приходы здесь не вводятся.'
        : 'Не нашел строки со знаком. Пример: -45 продукты, -67 топливо, +100 получил от руководителя.');
      return false;
    }

    if (simpleCurrentStream === 'card' && parsed.skipped.some(function(line) { return /^\s*\+/.test(line); })) {
      simpleStatus('В карточном потоке приход не вводится. Для наличных выберите поток “Наличные”.');
      return false;
    }

    if (!notes.trim() && simpleReplaceTape && !confirm('Сохранить пустой отчет? Все строки этой карточки будут убраны.')) {
      return false;
    }

    simpleStatus('Сохраняю...');
    clearTimeout(simpleAutosaveTimer);
    await autosaveSimpleDraft({force: true, silent: true});
    const operationSignature = simpleDraftSignature();
    const clientOperationId = operationIdForSignature(operationSignature);

    const savePayload = {
      tape_id: qlOtrActiveTapeId || undefined,
      stream_type: simpleCurrentStream,
      notes,
      cash_received: simpleCurrentStream === 'card'
        ? 0
        : simpleCurrentCard && Number(simpleCurrentCard.advance_id || 0) > 0
        ? simpleCardCashReceived(simpleCurrentCard)
        : simpleAdminAmount(),
      replace_tape: simpleReplaceTape ? 1 : 0,
      start_next: opts.stayInEditor ? 0 : 1,
      client_draft_id: ensureSimpleClientDraftId(Number(qlOtrActiveTapeId || simpleOpenedCardId || 0)),
      draft_id: simpleDraftId || undefined,
      client_operation_id: clientOperationId
    };
    const group = simpleAdminGroup();
    if (group && group.id) savePayload.group_id = Number(group.id);

    let data = null;
    try {
      data = await qlApi('on_the_go_signed_sync', savePayload);
    } catch (error) {
      setSimpleSyncState('retry_needed', 'Сохранение не ушло');
      simpleStatus('Ошибка сохранения: нет связи.');
      return false;
    }

    if (!data || !data.ok) {
      setSimpleSyncState('retry_needed', 'Сохранение не ушло');
      simpleStatus('Ошибка сохранения: ' + ((data && data.error) || 'unknown'));
      return false;
    }
    if (data.stream_type) {
      setSimpleStream(data.stream_type, {chosen: true});
    }

    const savedTapeId = Number(data.tape_id || 0);
    const nextTapeId = Number(data.next_tape_id || 0);
    if (Number(data.session_id || 0) > 0) {
      simpleSessionId = Number(data.session_id);
    }
    const activeAfterSave = hasFile ? savedTapeId : (nextTapeId || savedTapeId);
    if (activeAfterSave) {
      try {
        qlOtrActiveTapeId = Number(activeAfterSave);
        window.qlOtrActiveTapeId = Number(activeAfterSave);
      } catch (error) {}
    }
    if (savedTapeId > 0) {
      simpleOpenedCardId = savedTapeId;
      const shell = document.getElementById('otrSimpleCard');
      if (shell) shell.dataset.otrOpenCardId = String(savedTapeId);
    }
    if (data.tape) {
      simpleCurrentCard = data.tape;
      syncSimpleEditorActions(simpleCurrentCard);
    }

    let uploadErrors = 0;
    const created = data.items || [];
    const createdCaptureIds = created.map(function(item) {
      return Number(item && item.id || 0);
    }).filter(function(id) {
      return id > 0;
    });
    if (hasFile && savedTapeId > 0 && createdCaptureIds.length) {
      rememberSimpleProofRetryContext({
        client_draft_id: savePayload.client_draft_id,
        draft_id: simpleDraftId || undefined,
        tape_id: savedTapeId,
        session_id: simpleSessionId || Number(data.session_id || 0),
        stream_type: simpleCurrentStream,
        group_id: Number(savePayload.group_id || 0),
        client_operation_id: savePayload.client_operation_id,
        capture_id: createdCaptureIds[0],
        capture_ids: createdCaptureIds
      });
	    }
	    if (hasFile && created.length) {
	      simpleStatus('Сохраняю доказательство...');
	      const selectedFile = selectedProofFile;
	      for (const item of created) {
        const captureId = item && item.id ? item.id : null;
        if (!captureId) continue;
        rememberSimpleProofRetryContext({
          client_draft_id: savePayload.client_draft_id,
          draft_id: simpleDraftId || undefined,
          tape_id: savedTapeId,
          session_id: simpleSessionId || Number(data.session_id || 0),
          stream_type: simpleCurrentStream,
          group_id: Number(savePayload.group_id || 0),
          client_operation_id: savePayload.client_operation_id,
          capture_id: captureId,
          capture_ids: createdCaptureIds
        });
	        const proofState = await beginSimpleProofState(selectedFile, captureId);
	        const uploadId = proofState && proofState.client_upload_id ? proofState.client_upload_id : (simpleSelectedUploadId || simpleToken('upload'));
	        simpleSelectedUploadId = uploadId;
	        const sourceFile = simpleScannerFile === selectedFile
	          ? await uploadSimpleScannerOriginal(captureId, simpleScannerMetadata)
	          : null;
	        rememberSimpleProofRetryContext({
	          client_upload_id: uploadId,
	          capture_id: captureId,
          capture_ids: createdCaptureIds
        });
        let upload = {ok: false, error: 'upload_not_started'};
        try {
	          upload = await qlUploadOnTheGoFile(captureId, fileInput, {
	            file: selectedFile,
	            client_upload_id: uploadId,
	            client_draft_id: simpleClientDraftId || savePayload.client_draft_id,
	            draft_id: simpleDraftId || undefined,
	            proof_role: simpleScannerFile === selectedFile ? 'scanner_cleaned_pdf' : 'attachment',
	            proof_bundle_id: simpleScannerFile === selectedFile ? simpleScannerBundleId : undefined,
	            source_file_id: sourceFile && sourceFile.id ? sourceFile.id : undefined,
	            metadata_json: simpleScannerFile === selectedFile && simpleScannerMetadata
	              ? JSON.stringify(Object.assign({}, simpleScannerMetadata, {
	                  artifact_role: 'scanner_cleaned_pdf',
	                  source_file_id: sourceFile && sourceFile.id ? sourceFile.id : null
	                }))
	              : undefined
	          });
        } catch (error) {
          upload = {ok: false, error: error && error.message ? error.message : 'upload_failed'};
        }
        if (!upload.ok) {
          uploadErrors += 1;
          await failSimpleProofState(uploadId, upload.error || 'upload_failed', captureId);
        } else if (upload.proof_state) {
          simpleProofStates = simpleProofStates.filter(function(row) {
            return String(row.client_upload_id || '') !== String(uploadId);
          }).concat([upload.proof_state]);
          renderSimpleProofStates(simpleProofStates);
        }
        await refreshSimpleProofStates();
      }
    }

    if (uploadErrors && savedTapeId > 0) {
      qlOtrActiveTapeId = savedTapeId;
      window.qlOtrActiveTapeId = savedTapeId;
      simpleOpenedCardId = savedTapeId;
      rememberSimpleProofRetryContext({
        client_draft_id: savePayload.client_draft_id,
        draft_id: simpleDraftId || undefined,
        tape_id: savedTapeId,
        session_id: simpleSessionId || Number(data.session_id || 0),
        stream_type: simpleCurrentStream,
        group_id: Number(savePayload.group_id || 0),
        client_operation_id: savePayload.client_operation_id,
        capture_ids: createdCaptureIds
      });
    }

	    if (!uploadErrors) {
	      clearSimpleSelectedProofFile();
	      clearSimpleProofRetryContext();
	    }

    simpleDirty = false;
    simplePendingOperationId = '';
    simplePendingOperationSignature = '';
    if (data.next_tape_id && !uploadErrors) {
      const notesBox = document.getElementById('otrSimpleNotes');
      if (notesBox) notesBox.value = '';
      resetSimpleDraftIdentity(Number(data.next_tape_id || 0));
    } else if (savedTapeId > 0) {
      rememberSimpleDraftContext({tape_id: savedTapeId, draft_id: simpleDraftId || 0, session_id: simpleSessionId || 0});
    }

    if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
    if (typeof qlLoadOnTheGo === 'function') await qlLoadOnTheGo();
    if (typeof window.qlLoadOtrReportCards === 'function') await window.qlLoadOtrReportCards();

    const skipped = data.skipped && data.skipped.length ? ' Не распознано: ' + data.skipped.join(' | ') : '';
	    const photo = uploadErrors ? ' Есть ошибка доказательства.' : (hasFile && created.length ? ' Доказательство прикреплено.' : '');
	    simpleStatus('Сохранено.' + photo + skipped);
	    if (uploadErrors) {
	      setSimpleSyncState('retry_needed', 'Доказательство не загрузилось');
	      return false;
	    }
    if (opts.stayInEditor) {
      setSimpleEditMode(false);
      simpleStatus('Зафиксировано.');
      return true;
    }
    if (typeof window.qlOpenOtrReportCards === 'function') {
      await window.qlOpenOtrReportCards();
    }
    return true;
  }

  document.addEventListener('input', function(event) {
    if (event.target.closest('#otrSimpleNotes')) {
      simpleDirty = true;
      renderSimpleResult();
      scheduleSimpleAutosave();
    }
    if (event.target.closest('#otrAdminAmount')) {
      renderSimpleResult();
      scheduleSimpleAutosave();
    }
  });

	  document.addEventListener('change', function(event) {
	    const scannerInput = event.target.closest('#receiptScannerSourceInput');
	    if (scannerInput) {
	      const selected = scannerInput.files && scannerInput.files[0] ? scannerInput.files[0] : null;
	      loadReceiptScannerFile(selected);
	      scannerInput.value = '';
	      return;
	    }

	    const file = event.target.closest('#otrSimpleFile');
	    if (!file) return;
	    const label = document.getElementById('otrSimpleFileName');
	    const selected = file.files && file.files[0] ? file.files[0] : null;
	    simpleScannerFile = null;
	    simpleScannerMetadata = null;
	    if (label) label.textContent = selected ? selected.name : 'Без вложения';
    if (selected) {
      restoreSimpleProofRetryContextFromState();
      const retryState = findSimpleRetryProofState();
      const retryContext = getSimpleProofRetryContext(retryState);
      const retryCaptureId = simpleProofRetryTargetCaptureId(retryState, retryContext);
      simpleSelectedUploadId = (retryState && retryState.client_upload_id)
        || (retryContext && retryContext.client_upload_id)
        || simpleToken('upload');
      if (retryCaptureId && retryContext) {
        applySimpleProofRetryContext(retryContext);
      }
      renderSimpleProofStates(simpleProofStates);
      beginSimpleProofState(selected, retryCaptureId || undefined)
        .then(function() {
          if (retryCaptureId) {
            setSimpleSyncState('retry_needed', 'Фото готово к повтору');
          }
        })
        .catch(function() {
          setSimpleSyncState('retry_needed', 'Доказательство ждет повтора');
        });
    } else {
      simpleSelectedUploadId = '';
	      renderSimpleProofStates(simpleProofStates);
	    }
	  });

	  document.addEventListener('input', function(event) {
	    if (event.target.closest('#receiptScannerCleanLevel') || event.target.closest('#receiptScannerMono')) {
	      receiptScannerStatus('Настройки очистки применятся при прикреплении PDF.');
	    }
	  });

	  document.addEventListener('pointerdown', function(event) {
	    const handle = event.target.closest('[data-receipt-corner]');
	    if (!handle) return;
	    event.preventDefault();
	    receiptScannerDraggingCorner = handle.getAttribute('data-receipt-corner') || '';
	    try { handle.setPointerCapture(event.pointerId); } catch (error) {}
	  });

	  document.addEventListener('pointermove', function(event) {
	    if (!receiptScannerDraggingCorner || !receiptScannerCorners) return;
	    const point = pointerToReceiptCorner(event);
	    if (!point) return;
	    receiptScannerCorners[receiptScannerDraggingCorner] = point;
	    renderReceiptScanner();
	  });

	  document.addEventListener('pointerup', function(event) {
	    if (!receiptScannerDraggingCorner) return;
	    const handle = event.target.closest('[data-receipt-corner]');
	    if (handle) {
	      try { handle.releasePointerCapture(event.pointerId); } catch (error) {}
	    }
	    receiptScannerDraggingCorner = '';
	  });

	  window.addEventListener('resize', function() {
	    if (receiptScannerImage && document.getElementById('receiptScannerModal') && !document.getElementById('receiptScannerModal').classList.contains('hidden')) {
	      renderReceiptScanner();
	    }
	  });

	  document.addEventListener('click', async function(event) {
	    if (event.target.closest('[data-close-receipt-scanner]')) {
	      event.preventDefault();
	      closeReceiptScanner();
	      return;
	    }
	    if (event.target.closest('#receiptScannerPickBtn') || event.target.closest('#receiptScannerRetakeBtn')) {
	      event.preventDefault();
	      const input = document.getElementById('receiptScannerSourceInput');
	      if (input) {
	        input.setAttribute('accept', 'image/*');
	        input.setAttribute('capture', 'environment');
	        input.click();
	      }
	      return;
	    }
	    if (event.target.closest('#receiptScannerAttachBtn')) {
	      event.preventDefault();
	      if (!receiptScannerImage) {
	        receiptScannerStatus('Сначала выберите фото чека.');
	        return;
	      }
	      try {
	        receiptScannerStatus('Очищаю фото и собираю PDF...');
	        const pdfFile = await buildReceiptPdfFile();
	        simpleScannerBundleId = simpleScannerBundleId || simpleToken('scan');
	        setSimpleScannerProofFile(pdfFile, {
	          proof_bundle_id: simpleScannerBundleId,
	          original_name: simpleScannerOriginalFile ? simpleScannerOriginalFile.name : 'browser-image-source',
	          original_mime_type: simpleScannerOriginalFile ? simpleScannerOriginalFile.type : '',
	          original_size_bytes: simpleScannerOriginalFile ? simpleScannerOriginalFile.size : 0,
	          corners: receiptScannerCorners,
	          clean_level: Number(document.getElementById('receiptScannerCleanLevel')?.value || 42),
	          mono: !!document.getElementById('receiptScannerMono')?.checked,
	          generated_at: new Date().toISOString()
	        });
	        closeReceiptScanner();
	        simpleStatus('PDF-скан готов. Сохраните живой отчет, чтобы прикрепить доказательство.');
	      } catch (error) {
	        receiptScannerStatus('Не удалось собрать PDF: ' + (error && error.message ? error.message : 'unknown'));
	      }
	      return;
	    }
	    if (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'receiptScannerModal') {
	      closeReceiptScanner();
	      return;
	    }

	    const streamChoice = event.target.closest('[data-otr-stream-choice]');
	    if (streamChoice) {
      event.preventDefault();
      const nextStream = normalizeSimpleStream(streamChoice.getAttribute('data-otr-stream-choice'));
      simpleDirty = false;
      simpleOpenedCardId = 0;
      qlOtrActiveTapeId = 0;
      window.qlOtrActiveTapeId = 0;
      setSimpleStream(nextStream, {chosen: true});
      hideStreamGate();
      const recovered = await recoverStoredSimpleFieldDraft(nextStream, {force: true});
      if (recovered) {
        showSimpleEditor({history: 'push'});
        return;
      }
      simpleClientDraftId = '';
      simpleDraftId = 0;
      simpleSessionId = 0;
      simpleProofStates = [];
      simpleSelectedUploadId = '';
      clearSimpleProofRetryContext(nextStream);
      simpleLastAutosaveSignature = '';
      simplePendingOperationId = '';
      simplePendingOperationSignature = '';
      renderSimpleProofStates([]);
      await loadSimpleOnTheGo({force: true, stream_type: nextStream});
      await autosaveSimpleDraft({force: true, silent: true});
      if (typeof window.qlOpenOtrReportCards === 'function') {
        await window.qlOpenOtrReportCards({history: 'push'});
      } else {
        showSimpleEditor({history: 'push'});
      }
      return;
    }
    if (event.target.closest('#otrStreamSwitchBtn')) {
      event.preventDefault();
      showStreamGate({history: 'push'});
      return;
    }
    const quickLine = event.target.closest('[data-otr-quick-line]');
    if (quickLine) {
      event.preventDefault();
      appendSimpleQuickLine(quickLine.getAttribute('data-otr-quick-line'));
      return;
    }
    if (event.target.closest('[data-otr-quick-proof]')) {
      event.preventDefault();
      openSimpleProofPicker();
      return;
    }
    if (event.target.closest('[data-otr-quick-advance]')) {
      event.preventDefault();
      openSimpleAdvanceScreen();
      return;
    }
    if (event.target.closest('#otrStreamGateBackBtn')) {
      event.preventDefault();
      if (typeof window.qlOpenOtrReportCards === 'function' && simpleStreamChosen) {
        await window.qlOpenOtrReportCards();
      }
      return;
    }
	    const attach = event.target.closest('[data-otr-attach]');
	    if (attach) {
	      event.preventDefault();
	      const input = document.getElementById('otrSimpleFile');
	      if (!input) return;
	      const mode = attach.getAttribute('data-otr-attach');
	      if (mode === 'scan') {
	        openReceiptScanner({pick: true});
	        return;
	      }
	      input.removeAttribute('capture');
	      if (mode === 'camera') {
	        input.setAttribute('accept', 'image/*');
	        input.setAttribute('capture', 'environment');
	      } else {
	        input.setAttribute('accept', 'image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt');
	      }
      input.click();
      return;
    }
    if (event.target.closest('#otrSimpleSaveBtn')) {
      event.preventDefault();
      const saveButton = event.target.closest('#otrSimpleSaveBtn');
      const fileInput = document.getElementById('otrSimpleFile');
      const retryMode = getSimpleSelectedProofFile(fileInput) && hasSimpleProofRetryTarget();
      const actionLabel = retryMode ? 'Повторяю фото…' : 'Сохраняю…';
      await withSimpleActionBusy(saveButton, async function() {
        const fileInput = document.getElementById('otrSimpleFile');
        if (getSimpleSelectedProofFile(fileInput) && hasSimpleProofRetryTarget()) {
          await retrySimpleProofUpload();
          return;
        }
        await saveSimpleOnTheGo();
      }, {label: actionLabel});
      return;
    }
    if (event.target.closest('#otrAutosaveRetryBtn')) {
      event.preventDefault();
      const retryButton = event.target.closest('#otrAutosaveRetryBtn');
      await withSimpleActionBusy(retryButton, async function() {
        const fileInput = document.getElementById('otrSimpleFile');
        if (getSimpleSelectedProofFile(fileInput)) {
          await retrySimpleProofUpload();
          return;
        }
        if (hasSimpleProofRetryTarget()) {
          setSimpleSyncState('retry_needed', 'Выберите фото для повтора');
          simpleStatus('Выберите фото еще раз, затем повторите отправку. Денежная строка уже сохранена.');
          return;
        }
        await autosaveSimpleDraft({force: true});
      }, {label: 'Повторяю фото…'});
      return;
    }
    if (event.target.closest('#otrSimpleProofsBtn')) {
      event.preventDefault();
      const cardId = simpleCurrentProofCardId();
      const proofsButton = event.target.closest('#otrSimpleProofsBtn');
      if (!cardId || typeof window.qlOpenOtrReportCard !== 'function') {
        simpleStatus('Сохраненные файлы появятся после сохранения записи.');
        return;
      }
      await withSimpleActionBusy(proofsButton, async function() {
        await window.qlOpenOtrReportCard(cardId, {viewFiles: true, history: 'push'});
      }, {label: 'Открываю вложения…'});
      return;
    }
    if (event.target.closest('#otrSimpleEditBtn')) {
      event.preventDefault();
      const editButton = event.target.closest('#otrSimpleEditBtn');
      await withSimpleActionBusy(editButton, async function() {
        if (simpleEditMode) {
          const fileInput = document.getElementById('otrSimpleFile');
          if (getSimpleSelectedProofFile(fileInput) && hasSimpleProofRetryTarget()) {
            await retrySimpleProofUpload();
            return;
          }
          await saveSimpleOnTheGo({stayInEditor: true});
          return;
        }
        setSimpleEditMode(true);
        const notes = document.getElementById('otrSimpleNotes');
        if (notes) notes.focus();
        simpleStatus('Редактирование включено.');
      }, {label: 'Фиксирую…'});
      return;
    }
    if (event.target.closest('#otrOpenCardsBtn')) {
      event.preventDefault();
      await withSimpleUiBusy(async function() {
        await returnToCards({history: 'push'});
      });
      return;
    }
    if (event.target.closest('#otrEditorBackBtn')) {
      event.preventDefault();
      await withSimpleUiBusy(async function() {
        await returnToCards();
      });
      return;
    }
    if (event.target.closest('#otrSimpleSubmitBtn')) {
      event.preventDefault();
      if (typeof window.qlSubmitOtrReportCard === 'function') {
        const submitButton = event.target.closest('#otrSimpleSubmitBtn');
        await withSimpleActionBusy(submitButton, async function() {
          const submitButton = event.target.closest('#otrSimpleSubmitBtn');
          const deleteButton = document.getElementById('otrSimpleDeleteBtn');
          const targetCardId = Number(
            document.getElementById('otrSimpleCard')?.dataset?.otrOpenCardId
            || simpleOpenedCardId
            || (simpleCurrentCard && (simpleCurrentCard.id || simpleCurrentCard.tape_id))
            || qlOtrActiveTapeId
            || 0
          );
          if (submitButton) submitButton.disabled = true;
          if (deleteButton) {
            deleteButton.disabled = true;
            deleteButton.classList.add('hidden');
          }
          const result = await window.qlSubmitOtrReportCard(targetCardId);
          if (result && result.ok) {
            if (typeof window.qlOpenOtrReportCards === 'function') {
              window.qlOpenOtrReportCards();
            }
            return;
          }
          if (submitButton) submitButton.disabled = false;
          syncSimpleEditorActions(simpleCurrentCard);
        }, {label: 'Сдаю в FinDesk…'});
      }
      return;
    }
    if (event.target.closest('#otrSimpleDeleteBtn')) {
      event.preventDefault();
      await withSimpleUiBusy(async function() {
        const targetCardId = Number(
          document.getElementById('otrSimpleCard')?.dataset?.otrOpenCardId
          || simpleOpenedCardId
          || (simpleCurrentCard && (simpleCurrentCard.id || simpleCurrentCard.tape_id))
          || qlOtrActiveTapeId
          || 0
        );
        simpleDirty = false;
        if (!targetCardId) {
          simpleStatus('Не вижу открытую карточку для удаления. Вернитесь в список и откройте ее еще раз.');
          return;
        }
        if (typeof window.qlDeleteOtrReportCard === 'function') {
          const button = event.target.closest('#otrSimpleDeleteBtn');
          if (button) button.disabled = true;
          try {
            await window.qlDeleteOtrReportCard(targetCardId);
          } finally {
            if (button) button.disabled = false;
          }
        }
      });
      return;
    }
  });

  const previousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);
  window.qlSetModule = function(moduleName, options) {
    if (moduleName !== 'ontherun' && simpleDirty) {
      autosaveSimpleDraft({force: true, silent: true});
    }
    if (typeof previousSetModule === 'function') previousSetModule(moduleName, options);
    if (moduleName === 'ontherun') {
      setTimeout(function() {
        openDefaultOnTheGoScreen(Object.assign({force: false}, options || {}));
      }, 150);
    }
  };

  try {
    qlSetModule = window.qlSetModule;
  } catch (error) {}

  const previousRenderUser = qlRenderUser;
  qlRenderUser = function(user) {
    previousRenderUser(user);
    setTimeout(function() {
      const module = document.getElementById('moduleOnTheGo');
      if (module && !module.classList.contains('hidden')) {
        openDefaultOnTheGoScreen({force: false});
      }
    }, 180);
  };

  const previousLoadGroupsForSimple = qlLoadGroups;
  qlLoadGroups = async function() {
    await previousLoadGroupsForSimple();
    const module = document.getElementById('moduleOnTheGo');
    if (module && !module.classList.contains('hidden')) {
      setTimeout(function() { openDefaultOnTheGoScreen({force: false}); }, 80);
    } else {
      setSimpleAdminModeCopy(simpleIsAdminMode());
    }
  };

  syncSimpleStreamChrome();

  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden' && simpleDirty) {
      autosaveSimpleDraft({force: true, silent: true});
    }
  });

  window.addEventListener('beforeunload', function() {
    if (simpleDirty) {
      autosaveSimpleDraft({force: true, silent: true});
    }
  });

  window.qlOtrSimpleParseSignedNotes = parseSimpleSignedNotes;
  window.qlOtrSimpleLoad = loadSimpleOnTheGo;
  window.qlOtrSimpleOpenCardDetail = applySimpleCardDetail;
  window.qlOtrSimpleCurrentStream = function() { return simpleCurrentStream; };
  window.qlOtrSimpleHasStreamChosen = function() { return simpleStreamChosen; };
  window.qlOtrSimpleChooseStream = setSimpleStream;
  window.qlOtrSimpleShowStreamGate = showStreamGate;
  window.qlOtrSimpleHideStreamGate = hideStreamGate;
  window.qlShowOtrSimpleEditor = showSimpleEditor;
  window.qlHideOtrSimpleEditor = hideSimpleEditor;
  window.qlSyncOtrSimpleEditorActions = syncSimpleEditorActions;
  window.qlSetOtrSimpleEditMode = setSimpleEditMode;
  window.qlOtrSimpleIsAdminMode = simpleIsAdminMode;
  window.qlOtrSimpleAdminLedgerBaseAmount = simpleAdminLedgerBaseAmount;
})();

/* === FinDesk On the Go Report Cards 20260520 === */
(function() {
  let currentCardId = null;
  let currentCard = null;
  let reportCards = [];
  let reportCardsArchiveMode = false;

  function money(value) {
    return typeof qlCurrency === 'function' ? qlCurrency(value || 0) : '€' + Number(value || 0).toFixed(2);
  }

  function cardStatus(message) {
    const el = document.getElementById('otrCardStatus') || document.getElementById('otrSimpleStatus');
    if (el) el.textContent = message || '';
  }

  function currentStream() {
    return typeof window.qlOtrSimpleCurrentStream === 'function'
      ? (window.qlOtrSimpleCurrentStream() === 'card' ? 'card' : 'cash')
      : 'cash';
  }

  function streamLabel(stream) {
    return stream === 'card' ? 'Карта' : 'Наличные';
  }

  function stateLabel(card) {
    if (card && card.ui_archived) return 'В архиве';
    if (card && card.card_state === 'included') return 'В отчете';
    if (card && card.card_state === 'submitted') return 'На проверке';
    if (card && card.card_state === 'draft') return 'Черновик';
    return 'Пусто';
  }

  function showCardsHint(message) {
    const text = message || 'Обработайте предыдущую запись в FinDesk.';
    let hint = document.getElementById('otrCardsHint');
    if (!hint) {
      hint = document.createElement('div');
      hint.id = 'otrCardsHint';
      hint.className = 'otr-cards-hint';
      hint.setAttribute('role', 'status');
      hint.setAttribute('aria-live', 'polite');
      document.body.appendChild(hint);
    }

    hint.textContent = text;
    hint.classList.remove('show');
    hint.offsetHeight;
    hint.classList.add('show');

    clearTimeout(showCardsHint.timer);
    showCardsHint.timer = setTimeout(function() {
      hint.classList.remove('show');
    }, 3600);
  }

  async function highlightReportCard(id, message) {
    const target = Number(id || 0);
    if (!target) {
      if (message) showCardsHint(message);
      return;
    }

    openCardsPanel();
    await loadCards({archivedOnly: false});

    const row = document.querySelector('[data-otr-report-card="' + target + '"]');
    if (row) {
      row.classList.remove('is-attention');
      row.offsetHeight;
      row.classList.add('is-attention');
      row.scrollIntoView({block: 'center', behavior: 'smooth'});
      setTimeout(function() {
        row.classList.remove('is-attention');
      }, 2600);
    }

    if (message) {
      cardStatus(message);
      showCardsHint(message);
    }
  }

  function resolveReportGroupId(card) {
    const existing = Number(card && card.group_id ? card.group_id : 0);
    if (existing > 0) return existing;

    const groups = Array.isArray(qlGroups) ? qlGroups : [];
    const candidates = [
      Number(window.qlCaptainActiveGroupId || 0),
      Number(qlAdvanceGroupId || 0),
      qlLedgerScopeMode === 'group' ? Number(qlLedgerGroupId || 0) : 0
    ];

    for (const id of candidates) {
      if (id > 0 && groups.some(function(group) { return String(group.id) === String(id); })) {
        return id;
      }
    }

    const preferred = groups.find(function(group) {
      const access = String(group.access_level || '').toLowerCase();
      const role = String(group.role || '').toLowerCase();
      return access === 'advanced' || access === 'manager' || role === 'admin';
    }) || groups[0];

    return preferred && preferred.id ? Number(preferred.id) : 0;
  }

  function cardsGroupCanUse(group) {
    if (!group) return false;
    if (typeof qlGroupCanUseGroupData === 'function') return qlGroupCanUseGroupData(group);

    const access = String(group.access_level || group.role || '').toLowerCase();
    const role = String(group.role || '').toLowerCase();
    const permissions = group.permissions || {};
    return access === 'advanced'
      || access === 'manager'
      || role === 'admin'
      || role === 'owner'
      || !!permissions.can_view_group_reports
      || !!permissions.can_moderate
      || !!permissions.can_manage_money
      || !!permissions.can_manage_members;
  }

  function cardsGroupScope() {
    const groups = Array.isArray(qlGroups) ? qlGroups : [];
    const candidateIds = [
      Number(window.qlCaptainActiveGroupId || 0),
      qlActiveGroup && qlActiveGroup.id ? Number(qlActiveGroup.id) : 0,
      Number(qlAdvanceGroupId || 0),
      qlLedgerScopeMode === 'group' ? Number(qlLedgerGroupId || 0) : 0
    ];

    for (const id of candidateIds) {
      const group = groups.find(function(row) {
        return id > 0 && String(row.id) === String(id);
      });
      if (group && group.id && cardsGroupCanUse(group)) {
        return group;
      }
    }

    return groups.find(cardsGroupCanUse) || null;
  }

  function canUseFinDesk() {
    return (Array.isArray(qlGroups) ? qlGroups : []).some(function(group) {
      const access = String(group.access_level || '').toLowerCase();
      const role = String(group.role || '').toLowerCase();
      const permissions = group.permissions || {};
      return access === 'advanced'
        || access === 'manager'
        || role === 'admin'
        || !!permissions.can_view_group_reports
        || !!permissions.can_manage_money;
    });
  }

  function cardDate(card) {
    const s = card && card.summary ? card.summary : {};
    return String(s.first_record_at || card.created_at || card.submitted_at || card.updated_at || '').slice(0, 10);
  }

  function cardDisplayTitle(card) {
    const title = String(card && card.title ? card.title : '').trim();
    if (card && card.stream_type === 'card') return 'Карта';
    return title && title !== 'On the Go' ? title : 'Наличные';
  }

  function cardShortDate(card) {
    const raw = cardDate(card);
    if (!raw) return 'Без даты';
    const parts = raw.split('-');
    if (parts.length === 3) return parts[2] + '.' + parts[1] + '.' + parts[0].slice(2);
    return raw;
  }

  function localDateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function cardCountLabel(count) {
    const n = Math.abs(Number(count || 0));
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return count + ' карточка';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return count + ' карточки';
    return count + ' карточек';
  }

  function cardMovementParts(summary, card) {
    const s = summary || {};
    const stream = card && card.stream_type ? card.stream_type : (s.stream_type || 'cash');
    const income = Number(s.extra_cash_in || 0);
    const cashExpense = Number(s.cash_out || 0);
    const cardExpense = Number(s.card_out || 0);
    const expense = stream === 'card'
      ? cardExpense
      : Number(s.spent_total || (cashExpense + cardExpense));
    const parts = [];

    if (stream !== 'card' && income > 0) parts.push('приход ' + money(income));
    if (expense > 0) parts.push((stream === 'card' ? 'карта ' : 'расход ') + money(expense));
    if (!parts.length) parts.push('без движений');

    return parts;
  }

  function cardPreview(card) {
    const s = card.summary || {};
    const records = Number(s.records_count || 0);
    if (records <= 0) return 'Пустая карточка';
    const first = String(s.first_record_at || card.created_at || card.updated_at || '').slice(11, 16);
    return (first ? first + '  ' : '') + cardMovementParts(s, card).join(' · ');
  }

  function cardGroupTitle(card) {
    const raw = cardDate(card);
    const parsed = raw ? new Date(raw + 'T00:00:00') : null;
    if (!parsed || Number.isNaN(parsed.getTime())) return 'Без даты';
    return parsed.toLocaleDateString('ru-RU', {month: 'long', year: 'numeric'});
  }

  function syncCardsPanelChrome(cards) {
    const count = document.getElementById('otrCardsCount');
    const finDesk = document.getElementById('otrCardsFinDeskBtn');
    const archive = document.getElementById('otrArchiveCardsBtn');
    const create = document.getElementById('otrNewCardBtn');
    const group = cardsGroupScope();
    const scopeLabel = reportCardsArchiveMode ? 'Архив' : streamLabel(currentStream());
    if (count) {
      count.textContent = group && group.name
        ? group.name + ' · ' + scopeLabel
        : scopeLabel;
    }
    if (finDesk) finDesk.classList.toggle('hidden', !canUseFinDesk());
    if (archive) {
      archive.textContent = reportCardsArchiveMode ? 'Журнал' : 'Архив';
      archive.setAttribute('aria-label', reportCardsArchiveMode ? 'Вернуться к живым отчетам' : 'Открыть архив живых отчетов');
      archive.setAttribute('title', reportCardsArchiveMode ? 'Журнал' : 'Архив');
    }
    if (create) create.classList.toggle('hidden', reportCardsArchiveMode);
  }

  function openCardsPanel(options) {
    const opts = options || {};
    const panel = document.getElementById('otrReportCardsPanel');
    if (!panel) return;
    if (typeof window.qlOtrSimpleHideStreamGate === 'function') {
      window.qlOtrSimpleHideStreamGate();
    } else {
      const gate = document.getElementById('otrStreamGate');
      if (gate) {
        gate.classList.add('hidden');
        gate.setAttribute('aria-hidden', 'true');
      }
      document.body.classList.remove('otr-stream-gate-open');
    }
    if (typeof window.qlHideOtrSimpleEditor === 'function') window.qlHideOtrSimpleEditor();
    panel.classList.remove('hidden');
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('otr-cards-open');
    if (typeof qlSaveModuleState === 'function') {
      qlSaveModuleState('ontherun', {
        screen: 'cards',
        stream_type: currentStream(),
        archivedOnly: reportCardsArchiveMode
      });
    }
    qlWriteBrowserState('ontherun', {
      screen: 'cards',
      stream_type: currentStream(),
      archivedOnly: reportCardsArchiveMode
    }, opts.history || '');
  }

  function closeCardsPanel() {
    const panel = document.getElementById('otrReportCardsPanel');
    if (!panel) return;
    panel.classList.add('hidden');
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('otr-cards-open');
  }

  function renderCardRow(card) {
    const s = card.summary || {};
    const included = card.card_state === 'included';
    const submitted = card.card_state === 'submitted';
    const correctionRequested = !!card.return_requested_at;
    const blockMessage = card.submit_block_message || 'Обработайте предыдущую запись в FinDesk.';
    const canSubmit = card.card_state === 'draft' && Number(s.records_count || 0) > 0 && card.can_submit !== false && !card.submit_block_reason;
    const state = stateLabel(card);
    const canDelete = !reportCardsArchiveMode && card.card_state === 'draft' && (!!card.can_delete || card.can_edit);
    const canRequestCorrection = !reportCardsArchiveMode && (submitted || included) && (card.can_request_return || correctionRequested);
    const canReturnCard = !reportCardsArchiveMode && (submitted || included) && !!card.can_return;
    const stream = card.stream_type === 'card' ? 'card' : 'cash';
    const submitButton = canSubmit ? '<button class="otr-note-row-action" type="button" data-otr-card-submit="' + escapeHtml(card.id) + '" aria-label="Сдать в FinDesk" title="Сдать в FinDesk">✓</button>' : '';
    const blockedState = card.submit_block_reason
      ? '<button class="otr-note-row-state blocked" type="button" data-otr-card-highlight="' + escapeHtml(card.submit_blocking_card_id || '') + '" aria-label="' + escapeHtml(blockMessage) + '" title="' + escapeHtml(blockMessage) + '"></button>'
      : '';
    const requestCorrectionButton = canReturnCard
      ? '<button class="otr-note-row-request requested" type="button" data-otr-card-unsubmit="' + escapeHtml(card.id) + '" aria-label="Вернуть карточку на исправление" title="Вернуть на исправление">Вернуть</button>'
      : (canRequestCorrection
        ? (correctionRequested
          ? '<button class="otr-note-row-request requested" type="button" data-otr-card-open="' + escapeHtml(card.id) + '" aria-label="Запрос на исправление ожидает администратора" title="Запрос ожидает администратора">Ожидает</button>'
          : '<button class="otr-note-row-request" type="button" data-otr-card-request-correction="' + escapeHtml(card.id) + '" aria-label="Запросить исправление" title="Запросить исправление">Исправить</button>')
        : '');
    const archiveButton = !reportCardsArchiveMode && card.can_archive_completed
      ? '<button class="otr-note-row-archive" type="button" data-otr-card-archive="' + escapeHtml(card.id) + '" aria-label="Убрать выполненную карточку в архив" title="В архив">↓</button>'
      : '';
    const deleteButton = canDelete
      ? '<button class="otr-note-row-delete" type="button" data-otr-card-delete="' + escapeHtml(card.id) + '" aria-label="Удалить карточку" title="Удалить карточку">×</button>'
      : '';
    const action = submitButton || blockedState || '<span class="otr-note-row-state" aria-label="' + escapeHtml(state) + '" title="' + escapeHtml(state) + '"></span>';

    return `
      <article class="otr-report-card ${included ? 'included' : submitted ? 'submitted' : 'draft'} stream-${stream}" data-otr-report-card="${escapeHtml(card.id)}">
        <button class="otr-report-card-main" type="button" data-otr-card-open="${escapeHtml(card.id)}">
          <span class="otr-note-row-date">${escapeHtml(cardShortDate(card))}</span>
          <span class="otr-note-row-title">${escapeHtml(cardDisplayTitle(card))}</span>
          <span class="otr-note-row-preview"><i>${escapeHtml(streamLabel(stream))}</i>${escapeHtml(cardPreview(card))}${Number(s.files_count || 0) ? ' · вложений ' + Number(s.files_count || 0) : ''}</span>
        </button>
        <div class="otr-report-card-actions">
          ${action}
          ${requestCorrectionButton}
          ${archiveButton}
          ${deleteButton}
        </div>
      </article>
    `;
  }

  async function loadCards(options) {
    const opts = options || {};
    if (Object.prototype.hasOwnProperty.call(opts, 'archivedOnly')) {
      reportCardsArchiveMode = !!opts.archivedOnly;
    }

    const box = document.getElementById('otrReportCardsList');
    if (!box) return;
    if (typeof qlRequireSignedInForBackgroundLoad === 'function' && !await qlRequireSignedInForBackgroundLoad()) {
      syncCardsPanelChrome([]);
      box.innerHTML = '<p class="soft-note">Войдите, чтобы открыть карточки.</p>';
      return [];
    }

    box.innerHTML = '<p class="soft-note">' + (reportCardsArchiveMode ? 'Загружаю архив…' : 'Загружаю карточки…') + '</p>';

    const payload = {limit: 80};
    const group = cardsGroupScope();
    if (group && group.id) {
      payload.group_id = Number(group.id);
    }
    if (!reportCardsArchiveMode) {
      payload.stream_type = currentStream();
    }
    if (reportCardsArchiveMode) {
      payload.archived_only = 1;
      payload.submitted_only = 1;
    }
    const data = await qlApi('on_the_go_card_list', payload);
    if (!data.ok) {
      box.innerHTML = '<p class="soft-note">Ошибка карточек: ' + escapeHtml(data.error || 'unknown') + '</p>';
      return;
    }

    const cards = data.cards || [];
    reportCards = cards;
    syncCardsPanelChrome(cards);
    if (!cards.length) {
      box.innerHTML = reportCardsArchiveMode
        ? '<p class="soft-note">В архиве пока нет ваших живых отчетов.</p>'
        : '<p class="soft-note">Сохраненных карточек пока нет. Сохраните текущие строки, и отчет появится здесь.</p>';
      return cards;
    }

    const groups = [];
    cards.forEach(function(card) {
      const title = cardGroupTitle(card);
      let bucket = groups.find(function(group) { return group.title === title; });
      if (!bucket) {
        bucket = {title, cards: []};
        groups.push(bucket);
      }
      bucket.cards.push(card);
    });

    box.innerHTML = groups.map(function(group) {
      return '<section class="otr-cards-group"><h4>' + escapeHtml(group.title) + '</h4>' + group.cards.map(renderCardRow).join('') + '</section>';
    }).join('');
    return cards;
  }

  async function nextBaseForNewCard() {
    if (currentStream() === 'card') {
      return 0;
    }

    if (typeof window.qlOtrSimpleIsAdminMode === 'function' && window.qlOtrSimpleIsAdminMode()) {
      const ledgerBase = typeof window.qlOtrSimpleAdminLedgerBaseAmount === 'function'
        ? await window.qlOtrSimpleAdminLedgerBaseAmount()
        : '';
      if (ledgerBase !== '') {
        const normalizedLedger = String(ledgerBase).replace(/\s/g, '').replace(',', '.');
        const ledgerAmount = Number(normalizedLedger);
        return Number.isFinite(ledgerAmount) ? ledgerAmount : 0;
      }
    }

    const source = reportCards.find(function(card) {
      const s = card.summary || {};
      return card.stream_type !== 'card' && Number(s.records_count || 0) > 0;
    });
    if (source && source.summary) {
      return Number(source.summary.after_amount || 0);
    }

    const current = document.getElementById('otrAdminAmount')?.value || '';
    const normalized = String(current).replace(/\s/g, '').replace(',', '.');
    const amount = Number(normalized);
    return Number.isFinite(amount) ? amount : 0;
  }

  async function createNewCard() {
    cardStatus('Создаю новую карточку...');
    const groupId = resolveReportGroupId(null);
    const stream = currentStream();
    const payload = {
      stream_type: stream,
      cash_received: stream === 'card' ? 0 : await nextBaseForNewCard(),
      title: stream === 'card' ? 'Живой отчет: карта' : 'Живой отчет'
    };
    if (groupId) payload.group_id = groupId;

    const data = await qlApi('on_the_go_tape_create', payload);
    if (!data.ok) {
      cardStatus('Не удалось создать карточку: ' + (data.error || 'unknown'));
      return;
    }

    const tapeId = data.tape && data.tape.id ? Number(data.tape.id) : 0;
    if (tapeId) {
      qlOtrActiveTapeId = tapeId;
      window.qlOtrActiveTapeId = tapeId;
    }

    const notes = document.getElementById('otrSimpleNotes');
    if (notes) notes.value = '';
    closeCardsPanel();
    if (typeof window.qlShowOtrSimpleEditor === 'function') window.qlShowOtrSimpleEditor();
    if (typeof window.qlSetOtrSimpleEditMode === 'function') window.qlSetOtrSimpleEditMode(true);
    if (typeof window.qlOtrSimpleLoad === 'function') {
      await window.qlOtrSimpleLoad({force: true, tape_id: tapeId});
    }
    cardStatus('');
  }

  function openCardModalShell() {
    const modal = document.getElementById('otrCardModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeCardModal() {
    const modal = document.getElementById('otrCardModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    currentCardId = null;
    currentCard = null;
  }

  function renderCardModal(data) {
    const card = data.card || {};
    const items = data.items || [];
    const s = card.summary || {};
    const submitted = card.card_state === 'submitted' || card.card_state === 'included';
    currentCard = card;

    const kicker = document.getElementById('otrCardKicker');
    const title = document.getElementById('otrCardTitle');
    const metrics = document.getElementById('otrCardMetrics');
    const meta = document.getElementById('otrCardMeta');
    const records = document.getElementById('otrCardRecords');
    const submit = document.getElementById('otrCardSubmitBtn');
    const unsubmit = document.getElementById('otrCardUnsubmitBtn');
    const uninclude = document.getElementById('otrCardUnincludeBtn');
    const isOwnCard = qlCurrentUser && card.user_id && String(card.user_id) === String(qlCurrentUser.id);
    const canModerate = !!(card.can_moderate || card.can_return);
    const isSubmitted = card.card_state === 'submitted';
    const isIncluded = card.card_state === 'included';
    const showReturnToEdit = isSubmitted && !!card.can_return;
    const showReturnToReview = isIncluded && canModerate;
    const stream = card.stream_type === 'card' ? 'card' : 'cash';

    if (kicker) kicker.textContent = card.card_state === 'included' ? 'Включено в отчет' : card.card_state === 'submitted' ? 'На проверке в FinDesk' : 'Карточка отчета';
    if (title) title.textContent = stream === 'card' ? 'Карта' : ((card.title && card.title !== 'On the Go') ? card.title : 'Наличные');
    if (metrics) {
      metrics.innerHTML = stream === 'card'
        ? `
          <div><span>Старт</span><b>${money(0)}</b></div>
          <div><span>Карта</span><b>${money(s.card_out || 0)}</b></div>
          <div><span>Касса</span><b>${money(0)}</b></div>
          <div><span>Итог</span><b>${money(s.after_amount || 0)}</b></div>
        `
        : `
          <div><span>Было</span><b>${money(s.before_amount || 0)}</b></div>
          <div><span>Приход</span><b>${money(s.extra_cash_in || 0)}</b></div>
          <div><span>Расход</span><b>${money(s.spent_total || 0)}</b></div>
          <div><span>Остаток</span><b>${money(s.after_amount || 0)}</b></div>
        `;
    }
    if (meta) {
      meta.textContent = stateLabel(card)
        + ' · ' + cardMovementParts(s, card).join(' · ')
        + (card.submitted_at ? ' · сдан ' + card.submitted_at : '');
    }

    if (submit) submit.classList.add('hidden');
    if (unsubmit) {
      unsubmit.textContent = isOwnCard ? 'Вернуть в редактирование' : 'Вернуть на исправление';
      unsubmit.classList.toggle('hidden', !showReturnToEdit);
      unsubmit.hidden = !showReturnToEdit;
    }
    if (uninclude) {
      uninclude.textContent = 'Вернуть в FinDesk';
      uninclude.classList.toggle('hidden', !showReturnToReview);
      uninclude.hidden = !showReturnToReview;
    }
    const actions = document.querySelector('#otrCardModal .otr-card-modal-actions');
    if (actions) {
      actions.classList.toggle('hidden', !(showReturnToEdit || showReturnToReview));
      actions.hidden = !(showReturnToEdit || showReturnToReview);
    }
    cardStatus('');

    if (!records) return;
    if (!items.length) {
      records.innerHTML = '<p class="soft-note">В карточке пока нет строк.</p>';
      return;
    }

    function recordFileSize(bytes) {
      const value = Number(bytes || 0);
      if (!value) return '';
      if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(1) + ' MB';
      if (value >= 1024) return Math.round(value / 1024) + ' KB';
      return value + ' B';
    }

    function recordFileLabel(file) {
      const role = qlProofRoleLabel(file.proof_role);
      const name = file.original_name || role || 'Файл';
      const size = recordFileSize(file.size_bytes);
      return [role, name, size].filter(Boolean).join(' · ');
    }

    function closeProofViewer() {
      const modal = document.getElementById('proofViewerModal');
      const body = document.getElementById('proofViewerBody');
      if (modal) {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
      }
      if (body) body.innerHTML = '<p class="soft-note">Загрузка файла…</p>';
    }

    function openProofViewer(file) {
      const modal = document.getElementById('proofViewerModal');
      const title = document.getElementById('proofViewerTitle');
      const body = document.getElementById('proofViewerBody');
      const link = document.getElementById('proofViewerOpenLink');
      const meta = document.getElementById('proofViewerMeta');
      const url = file.download_url || '';
      const mime = String(file.mime_type || '').toLowerCase();
      const name = file.original_name || qlProofRoleLabel(file.proof_role) || 'Файл';
      if (!modal || !body || !url) return;

      if (title) title.textContent = name;
      if (link) {
        link.href = url;
        link.removeAttribute('download');
      }
      if (meta) {
        meta.textContent = [qlProofRoleLabel(file.proof_role), file.mime_type || '', recordFileSize(file.size_bytes)].filter(Boolean).join(' · ');
      }

      if (mime.indexOf('image/') === 0) {
        body.innerHTML = '<img class="proof-viewer-image" src="' + escapeHtml(url) + '" alt="' + escapeHtml(name) + '">';
      } else if (mime.indexOf('pdf') !== -1 || /\.pdf(?:$|\?)/i.test(url) || /\.pdf$/i.test(name)) {
        body.innerHTML = '<iframe class="proof-viewer-frame" src="' + escapeHtml(url) + '" title="' + escapeHtml(name) + '"></iframe>';
      } else {
        body.innerHTML = '<div class="proof-viewer-fallback"><p class="soft-note">Этот тип файла лучше открыть отдельной ссылкой.</p><a class="primary-btn wide-btn" href="' + escapeHtml(url) + '" target="_blank" rel="noopener">Открыть файл</a></div>';
      }

      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
    }

    async function loadRecordFiles(captureId) {
      const box = document.querySelector('[data-otr-card-record-files="' + captureId + '"]');
      if (!box) return;
      box.innerHTML = '<small>Загружаю файлы…</small>';
      const data = await qlApi('on_the_go_file_list', {capture_id: Number(captureId)});
      if (!data.ok) {
        box.innerHTML = '<small>Файлы недоступны: ' + escapeHtml(data.error || 'unknown') + '</small>';
        return;
      }
      const files = data.files || [];
      if (!files.length) {
        box.innerHTML = '';
        return;
      }
      box.innerHTML = files.map(function(file) {
        const label = recordFileLabel(file);
        const encoded = encodeURIComponent(JSON.stringify({
          original_name: file.original_name || '',
          download_url: file.download_url || '',
          mime_type: file.mime_type || '',
          proof_role: file.proof_role || '',
          size_bytes: file.size_bytes || 0
        }));
        return `
          <button class="otr-card-record-file" type="button" data-otr-proof-view="${escapeHtml(encoded)}">
            ${escapeHtml(label)}
          </button>
        `;
      }).join('');
    }

    records.innerHTML = items.map(function(item) {
      const sign = item.capture_type === 'cash_in' ? '+' : '-';
      const cls = item.capture_type === 'cash_in' ? 'income' : (item.capture_type === 'noncash_out' ? 'card-expense' : 'expense');
      const desc = item.description || 'Без описания';
      const files = Number(item.files_count || 0);
      const fileLinks = files
        ? '<div class="otr-card-record-files" data-otr-card-record-files="' + escapeHtml(item.id) + '"><small>Файлов: ' + files + '</small></div>'
        : '';
      const actions = card.can_edit ? `
        <div class="otr-card-record-actions">
          <button class="ghost-btn small-btn" type="button" data-otr-card-edit-item="${escapeHtml(item.id)}">Изменить</button>
          <button class="ghost-btn small-btn danger-soft-btn" type="button" data-otr-card-delete-item="${escapeHtml(item.id)}">Удалить</button>
        </div>
      ` : '';

      return `
        <article class="otr-card-record ${escapeHtml(cls)}" data-otr-card-item="${escapeHtml(item.id)}" data-type="${escapeHtml(item.capture_type)}" data-amount="${escapeHtml(item.amount || 0)}" data-desc="${escapeHtml(desc)}">
          <span>
            <b>${escapeHtml(sign)}${money(item.amount || 0)}</b>
            <small>${escapeHtml(desc)}${files ? ' · вложений ' + files : ''}</small>
            ${fileLinks}
          </span>
          ${actions}
        </article>
      `;
    }).join('');
    if (!records.dataset.proofViewerBound) {
      records.addEventListener('click', function(event) {
        const button = event.target.closest('[data-otr-proof-view]');
        if (!button || !records.contains(button)) return;
        event.preventDefault();
        event.stopPropagation();
        try {
          openProofViewer(JSON.parse(decodeURIComponent(button.getAttribute('data-otr-proof-view') || '{}')));
        } catch (error) {
          cardStatus('Не удалось открыть файл.');
        }
      });
      records.dataset.proofViewerBound = '1';
    }
    items.forEach(function(item) {
      if (Number(item.files_count || 0) > 0) {
        loadRecordFiles(item.id);
      }
    });
  }

  async function openCardFirstSavedProof(detailCard) {
    const records = document.getElementById('otrCardRecords');
    const items = Array.isArray(detailCard && detailCard.items) ? detailCard.items : [];
    const hasProofRows = items.some(function(item) {
      return Number(item.files_count || 0) > 0;
    });
    if (!hasProofRows || !records) {
      return false;
    }

    const deadline = Date.now() + 1200;
    while (Date.now() < deadline) {
      const button = records.querySelector('button[data-otr-proof-view]');
      if (button) {
        button.click();
        return true;
      }
      await new Promise(function(resolve) {
        setTimeout(resolve, 120);
      });
    }
    return false;
  }

  async function openCard(id, options) {
    const opts = options || {};
    currentCardId = Number(id || 0);
    if (!currentCardId) return;

    const data = await qlApi('on_the_go_card_detail', {id: currentCardId});
    if (!data.ok) {
      cardStatus('Не удалось открыть карточку: ' + (data.error || 'unknown'));
      return;
    }

    const detailCard = data.card || {};
    if (detailCard.stream_type && typeof window.qlOtrSimpleChooseStream === 'function') {
      window.qlOtrSimpleChooseStream(detailCard.stream_type, {chosen: true});
    }
    const detailState = String(detailCard.card_state || '').toLowerCase();
    if (detailCard.can_edit && detailState !== 'submitted' && detailState !== 'included' && !opts.viewFiles) {
      qlOtrActiveTapeId = currentCardId;
      window.qlOtrActiveTapeId = currentCardId;
      const shell = document.getElementById('otrSimpleCard');
      if (shell) shell.dataset.otrOpenCardId = String(currentCardId);
      closeCardsPanel();
      closeCardModal();
      if (typeof window.qlShowOtrSimpleEditor === 'function') window.qlShowOtrSimpleEditor({history: opts.history || ''});
      if (typeof window.qlOtrSimpleOpenCardDetail === 'function') {
        await window.qlOtrSimpleOpenCardDetail(data, {force: true, tape_id: currentCardId, viewOnly: true});
      } else if (typeof window.qlOtrSimpleLoad === 'function') {
        await window.qlOtrSimpleLoad({force: true, tape_id: currentCardId, viewOnly: true});
      }
      if (typeof window.qlSetOtrSimpleEditMode === 'function') window.qlSetOtrSimpleEditMode(false);
      return;
    }

    openCardModalShell();
    renderCardModal(data);
    if (opts.viewFiles) {
      const opened = await openCardFirstSavedProof(detailCard);
      if (!opened) {
        cardStatus('Вложения для этой записи пока не сохранены.');
      }
    }
    if (opts.history) {
      qlWriteBrowserState('ontherun', {screen: 'cards', stream_type: currentStream(), archivedOnly: reportCardsArchiveMode}, opts.history);
    }
  }

  async function refreshAll() {
    await loadCards();
    if (currentCardId) await openCard(currentCardId);
    if (typeof window.qlOtrSimpleLoad === 'function') await window.qlOtrSimpleLoad({force: true});
    if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
    if (typeof window.qlLoadCaptainAdminDesk === 'function') await window.qlLoadCaptainAdminDesk();
    if (typeof qlAdvancedRefreshMoneyState === 'function') await qlAdvancedRefreshMoneyState();
  }

  async function submitCard(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return null;
    const card = currentCard && Number(currentCard.id) === target
      ? currentCard
      : reportCards.find(function(item) { return Number(item.id) === target; }) || null;
    const groupId = resolveReportGroupId(card);
    if (!groupId) {
      cardStatus('Нужна рабочая группа, чтобы сдать карточку в FinDesk.');
      return {ok: false, error: 'invalid_group_id'};
    }

    cardStatus('Передаю карточку в FinDesk на проверку…');
    const data = await qlApi('on_the_go_card_submit', {
      id: target,
      group_id: groupId
    });

    if (!data.ok) {
      const message = data.message || data.error || 'unknown';
      if (data.blocking_card_id) {
        await highlightReportCard(data.blocking_card_id, message);
      } else {
        cardStatus('Не удалось сдать карточку: ' + message);
      }
      return data;
    }

    cardStatus('Карточка сдана в FinDesk на проверку.');
    await refreshAll();
    return data;
  }

  async function includeCard(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    const card = currentCard && Number(currentCard.id) === target
      ? currentCard
      : reportCards.find(function(item) { return Number(item.id) === target; }) || null;
    const groupId = resolveReportGroupId(card);
    if (!groupId) {
      cardStatus('Нужна рабочая группа, чтобы включить карточку в отчет.');
      return;
    }

    cardStatus('Включаю карточку в общий отчет…');
    const data = await qlApi('on_the_go_card_include', {
      id: target,
      group_id: groupId
    });

    if (!data.ok) {
      cardStatus('Не удалось включить карточку: ' + (data.error || 'unknown'));
      return;
    }

    cardStatus('Карточка включена в общий отчет.');
    await refreshAll();
  }

  async function unincludeCard(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    if (!confirm('Убрать карточку из общего отчета? Она останется во второй колонке “Сданные отчеты” для проверки.')) return;

    cardStatus('Убираю карточку из общего отчета…');
    const data = await qlApi('on_the_go_card_uninclude', {id: target});

    if (!data.ok) {
      cardStatus('Не удалось убрать из отчета: ' + (data.error || 'unknown'));
      return;
    }

    cardStatus('Карточка убрана из общего отчета и оставлена на проверке.');
    await refreshAll();
  }

  async function unsubmitCard(id, options) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    const opts = options || {};
    if (!opts.skipConfirm && !confirm('Вернуть эту карточку на исправление? Она снова станет живым отчетом.')) return;

    cardStatus('Готовлю карточку для исправления…');
    const data = await qlApi('on_the_go_card_unsubmit', {id: target});

    if (!data.ok) {
      cardStatus('Не удалось вернуть карточку: ' + (data.error || 'unknown'));
      return;
    }

    const returnedCard = data.card || {};
    const isOwnCard = qlCurrentUser && returnedCard.user_id && String(returnedCard.user_id) === String(qlCurrentUser.id);

    cardStatus(isOwnCard
      ? 'Карточка вернулась в “Живой отчет”.'
      : 'Карточка возвращена сотруднику на исправление.');
    await refreshAll();

    if (isOwnCard) {
      closeCardModal();
      if (typeof window.qlSetModule === 'function') {
        window.qlSetModule('ontherun');
      }
      if (typeof window.qlOtrSimpleLoad === 'function') {
        setTimeout(function() { window.qlOtrSimpleLoad({force: true, tape_id: returnedCard.id || target}); }, 220);
      }
    }
  }

  async function archiveCompletedCard(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    if (!confirm('Убрать выполненную карточку только из живого журнала? В расчетах и в пакете FinDesk она останется до обработки итогового отчета.')) return;

    cardStatus('Убираю карточку с рабочего экрана…');
    const data = await qlApi('on_the_go_card_archive_completed', {id: target});

    if (!data.ok) {
      cardStatus('Не удалось перенести в архив: ' + (data.error || 'unknown'));
      return;
    }

    reportCards = reportCards.filter(function(card) {
      return Number(card.id || card.tape_id || 0) !== target;
    });
    closeCardModal();
    currentCardId = null;
    currentCard = null;
    await loadCards();
    if (typeof window.qlLoadCaptainAdminDesk === 'function') await window.qlLoadCaptainAdminDesk();
    cardStatus('Карточка скрыта из живого журнала. FinDesk-пакет не изменен.');
  }

  async function requestCardReturn(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    const reason = prompt('Коротко напишите, что нужно исправить. Можно оставить пустым.', '');
    if (reason === null) return;

    cardStatus('Отправляю запрос на исправление…');
    const data = await qlApi('on_the_go_card_request_return', {
      id: target,
      reason: reason.trim()
    });

    if (!data.ok) {
      cardStatus('Не удалось запросить исправление: ' + (data.error || 'unknown'));
      return;
    }

    cardStatus('Запрос на исправление отправлен администратору.');
    renderCardModal(data);
    await loadCards();
    if (typeof window.qlLoadCaptainAdminDesk === 'function') await window.qlLoadCaptainAdminDesk();
  }

  async function deleteCard(id) {
    const target = Number(id || currentCardId || 0);
    if (!target) return;
    if (!confirm('Удалить этот живой отчет?')) return;

    cardStatus('Удаляю карточку…');
    const data = await qlApi('on_the_go_card_delete', {id: target});

    if (!data.ok) {
      cardStatus('Не удалось удалить карточку: ' + (data.error || 'unknown'));
      return;
    }

    reportCards = reportCards.filter(function(card) {
      return Number(card.id || card.tape_id || 0) !== target;
    });

    qlOtrActiveTapeId = 0;
    window.qlOtrActiveTapeId = 0;
    const notes = document.getElementById('otrSimpleNotes');
    if (notes) notes.value = '';
    const fileInput = document.getElementById('otrSimpleFile');
    if (fileInput) fileInput.value = '';
    const fileName = document.getElementById('otrSimpleFileName');
    if (fileName) fileName.textContent = 'Без вложения';
    const shell = document.getElementById('otrSimpleCard');
    if (shell) delete shell.dataset.otrOpenCardId;

    closeCardModal();
    currentCardId = null;
    currentCard = null;
    if (typeof window.qlHideOtrSimpleEditor === 'function') window.qlHideOtrSimpleEditor();
    openCardsPanel();
    await loadCards();
    if (reportCards.some(function(card) { return Number(card.id || card.tape_id || 0) === target; })) {
      cardStatus('Сервер ответил, но карточка осталась в списке. Обновите страницу и проверьте права на эту карточку.');
      return;
    }
    if (typeof qlLoadOtrTapes === 'function') await qlLoadOtrTapes();
    if (typeof window.qlLoadCaptainAdminDesk === 'function') await window.qlLoadCaptainAdminDesk();
    if (typeof qlAdvancedRefreshMoneyState === 'function') await qlAdvancedRefreshMoneyState();
    cardStatus('Карточка удалена.');
  }

  function parseEditedLine(raw, fallback) {
    if (typeof window.qlOtrSimpleParseSignedNotes === 'function') {
      const parsed = window.qlOtrSimpleParseSignedNotes(raw, currentCard && currentCard.stream_type);
      if (parsed.items && parsed.items[0]) {
        return parsed.items[0];
      }
    }

    return fallback;
  }

  async function editItem(id) {
    const row = document.querySelector('[data-otr-card-item="' + id + '"]');
    if (!row) return;

    const type = row.getAttribute('data-type') || 'cash_out';
    const sign = type === 'cash_in' ? '+' : '-';
    const current = sign + String(row.getAttribute('data-amount') || '0').replace(/\\.00$/, '') + ' ' + (row.getAttribute('data-desc') || '');
    const raw = prompt('Изменить строку отчета', current);
    if (raw === null) return;

    const parsed = parseEditedLine(raw, {
      type,
      amount: row.getAttribute('data-amount') || '0',
      description: row.getAttribute('data-desc') || ''
    });

    cardStatus('Сохраняю строку…');
    const data = await qlApi('on_the_go_update', {
      id: Number(id),
      capture_type: parsed.type || parsed.capture_type || type,
      amount: parsed.amount,
      description: parsed.description || ''
    });

    if (!data.ok) {
      cardStatus('Не удалось изменить строку: ' + (data.error || 'unknown'));
      return;
    }

    await refreshAll();
  }

  async function deleteItem(id) {
    if (!confirm('Удалить эту строку из карточки?')) return;

    cardStatus('Удаляю строку…');
    const data = await qlApi('on_the_go_archive', {id: Number(id)});
    if (!data.ok) {
      cardStatus('Не удалось удалить строку: ' + (data.error || 'unknown'));
      return;
    }

    await refreshAll();
  }

  document.addEventListener('click', function(event) {
    const open = event.target.closest('[data-otr-card-open], [data-captain-open-otr-card]');
    const submit = event.target.closest('[data-otr-card-submit]');
    const include = event.target.closest('[data-otr-card-include]');
    const highlight = event.target.closest('[data-otr-card-highlight]');
    const uninclude = event.target.closest('[data-otr-card-uninclude]');
    const unsubmit = event.target.closest('[data-otr-card-unsubmit]');
    const archiveCompleted = event.target.closest('[data-otr-card-archive]');
    const requestCorrection = event.target.closest('[data-otr-card-request-correction]');
    const del = event.target.closest('[data-otr-card-delete]');
    const editItemBtn = event.target.closest('[data-otr-card-edit-item]');
    const deleteItemBtn = event.target.closest('[data-otr-card-delete-item]');
    const proofClose = event.target.closest('[data-close-proof-viewer]');

    if (proofClose || (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'proofViewerModal')) {
      event.preventDefault();
      const modal = document.getElementById('proofViewerModal');
      const body = document.getElementById('proofViewerBody');
      if (modal) {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
      }
      if (body) body.innerHTML = '<p class="soft-note">Загрузка файла…</p>';
      return;
    }

    if (open) {
      event.preventDefault();
      if (open.hasAttribute('data-captain-open-otr-card')) {
        ['captainIncludedModal', 'captainArchiveModal'].forEach(function(id) {
          const modal = document.getElementById(id);
          if (modal) {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
          }
        });
      }
      openCard(open.getAttribute('data-otr-card-open') || open.getAttribute('data-captain-open-otr-card'), {history: 'push'});
      return;
    }
    if (submit) {
      event.preventDefault();
      submitCard(submit.getAttribute('data-otr-card-submit'));
      return;
    }
    if (include) {
      event.preventDefault();
      includeCard(include.getAttribute('data-otr-card-include'));
      return;
    }
    if (highlight) {
      event.preventDefault();
      highlightReportCard(highlight.getAttribute('data-otr-card-highlight'), highlight.getAttribute('aria-label') || '');
      return;
    }
    if (uninclude) {
      event.preventDefault();
      unincludeCard(uninclude.getAttribute('data-otr-card-uninclude'));
      return;
    }
    if (unsubmit) {
      event.preventDefault();
      unsubmitCard(unsubmit.getAttribute('data-otr-card-unsubmit'));
      return;
    }
    if (archiveCompleted) {
      event.preventDefault();
      archiveCompletedCard(archiveCompleted.getAttribute('data-otr-card-archive'));
      return;
    }
    if (requestCorrection) {
      event.preventDefault();
      requestCardReturn(requestCorrection.getAttribute('data-otr-card-request-correction'));
      return;
    }
    if (del) {
      event.preventDefault();
      deleteCard(del.getAttribute('data-otr-card-delete'));
      return;
    }
    if (editItemBtn) {
      event.preventDefault();
      editItem(editItemBtn.getAttribute('data-otr-card-edit-item'));
      return;
    }
    if (deleteItemBtn) {
      event.preventDefault();
      deleteItem(deleteItemBtn.getAttribute('data-otr-card-delete-item'));
      return;
    }
    if (event.target.closest('#otrRefreshCardsBtn')) {
      event.preventDefault();
      loadCards();
      return;
    }
    if (event.target.closest('#otrArchiveCardsBtn')) {
      event.preventDefault();
      loadCards({archivedOnly: !reportCardsArchiveMode});
      return;
    }
    if (event.target.closest('#otrCardsBackBtn')) {
      event.preventDefault();
      closeCardsPanel();
      if (typeof window.qlOtrSimpleShowStreamGate === 'function') {
        window.qlOtrSimpleShowStreamGate();
      }
      return;
    }
    if (event.target.closest('#otrNewCardBtn')) {
      event.preventDefault();
      createNewCard();
      return;
    }
    if (event.target.closest('#otrCardsFinDeskBtn')) {
      closeCardsPanel();
      return;
    }
    if (event.target.closest('#otrCardSubmitBtn')) {
      event.preventDefault();
      submitCard(currentCardId);
      return;
    }
    if (event.target.closest('#otrCardUnsubmitBtn')) {
      event.preventDefault();
      unsubmitCard(currentCardId, {skipConfirm: true});
      return;
    }
    if (event.target.closest('#otrCardUnincludeBtn')) {
      event.preventDefault();
      unincludeCard(currentCardId);
      return;
    }
    if (event.target.closest('[data-close-otr-card]') || (event.target.classList && event.target.classList.contains('modal') && event.target.id === 'otrCardModal')) {
      closeCardModal();
    }
  }, true);

  const previousSetModule = window.qlSetModule || (typeof qlSetModule === 'function' ? qlSetModule : null);
  window.qlSetModule = function(moduleName, options) {
    if (typeof previousSetModule === 'function') previousSetModule(moduleName, options);
    if (moduleName === 'ontherun') {
      setTimeout(loadCards, 240);
    }
  };

  try {
    qlSetModule = window.qlSetModule;
  } catch (error) {}

  document.addEventListener('DOMContentLoaded', function() {
    if (typeof qlRunWhenSignedInSoon === 'function') {
      qlRunWhenSignedInSoon(loadCards, 400);
    }
  });

  window.qlLoadOtrReportCards = loadCards;
  window.qlOpenOtrReportCards = async function() {
    const opts = arguments[0] || {};
    if (typeof window.qlOtrSimpleHasStreamChosen === 'function'
      && !window.qlOtrSimpleHasStreamChosen()
      && !opts.forceCards
      && typeof window.qlOtrSimpleShowStreamGate === 'function') {
      window.qlOtrSimpleShowStreamGate();
      return;
    }
    openCardsPanel({history: opts.history || ''});
    await loadCards({archivedOnly: !!opts.archivedOnly});
  };
  window.qlHighlightOtrReportCard = highlightReportCard;
  window.qlOpenOtrReportCard = openCard;
  window.qlSubmitOtrReportCard = submitCard;
  window.qlDeleteOtrReportCard = deleteCard;
})();
