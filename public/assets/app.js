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
    return '<h3>Already installed</h3><p>Quick Ledger is already running as a web app.</p>';
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
        <p>Chrome can install Quick Ledger as a web app on your Home screen.</p>
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
        <p>Chrome or Edge can install Quick Ledger as a web app.</p>
        <button id="nativeInstallBtn" class="primary-btn wide-btn" type="button">Install now</button>
        <p class="soft-note">You can also use the install icon in the browser address bar or browser menu.</p>
      `;
    }

    return `
      <h3>Install on computer</h3>
      <ol>
        <li>Open this page in <b>Chrome</b> or <b>Edge</b>.</li>
        <li>Look for the install icon in the address bar, or open the browser menu.</li>
        <li>Choose <b>Install Quick Ledger</b> or <b>Install app</b>.</li>
      </ol>
      <p class="soft-note">If the browser does not show install yet, keep using Quick Ledger in the browser and try again after reload.</p>
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

if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/service-worker.js').catch(function(){});
  });
}

/* === Quick Ledger Auth UI 20260503-02 === */
let qlCurrentUser = null;

async function qlApi(action, payload) {
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
  const data = await qlApi('current_user', {});
  if (data.ok && data.user) {
    qlRenderUser(data.user);
    qlShowPanel('user');
  } else {
    qlShowPanel('login');
  }
}

async function qlSendCode() {
  const email = (document.getElementById('loginEmail')?.value || '').trim();
  if (!email) {
    qlShowAuthMessage('Enter your email.');
    return;
  }

  qlShowAuthMessage('Sending code…');
  const data = await qlApi('request_code', {email});

  if (data.ok) {
    document.getElementById('codeBlock')?.classList.remove('hidden');
    qlShowAuthMessage('Code sent to your email. Open the message and enter the 6-digit code.');
  } else {
    qlShowAuthMessage('Error: ' + (data.error || 'unknown'));
  }
}

async function qlVerifyCode() {
  const email = (document.getElementById('loginEmail')?.value || '').trim();
  const code = (document.getElementById('loginCode')?.value || '').trim();

  if (!email || !code) {
    qlShowAuthMessage('Enter email and code.');
    return;
  }

  qlShowAuthMessage('Verifying…');
  const data = await qlApi('verify_code', {email, code});

  if (data.ok && data.user) {
    qlRenderUser(data.user);
    qlShowPanel('user');
  } else {
    qlShowAuthMessage('Error: ' + (data.error || 'unknown'));
  }
}

async function qlLogout() {
  await qlApi('logout', {});
  qlCurrentUser = null;
  qlShowPanel('login');
  qlShowAuthMessage('Logged out.');
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

/* === Quick Ledger Personal Ledger UI 20260503-03 === */
let qlLedgerType = 'income';
let qlMoneyType = 'cash';

function qlCurrency(value) {
  const n = Number(value || 0);
  return '€' + n.toFixed(2);
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
    feed.innerHTML = '<p class="soft-note">Ledger error: ' + (data.error || 'unknown') + '</p>';
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

  if (count) count.textContent = entries.length + (entries.length === 1 ? ' record' : ' records');
  if (income) income.textContent = qlCurrency(summary.income || 0);
  if (expense) expense.textContent = qlCurrency(summary.expense || 0);
  if (balance) balance.textContent = qlCurrency(summary.balance || 0);

  if (!feed) return;

  if (!entries.length) {
    feed.innerHTML = '<p class="soft-note">No records yet. Add the first one below.</p>';
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
        html += '<div class="day-total">Day balance: ' + qlCurrency(dayBalances[currentDay]) + '</div>';
      }
      currentDay = day;
      html += '<div class="day-divider">' + qlFormatDateLabel(entry.entry_datetime) + '</div>';
    }

    const sign = entry.entry_type === 'income' ? '+' : '-';
    const edited = entry.edited_at ? ' · edited' : '';
    const doc = Number(entry.file_count || 0) > 0 ? ' · with document' : ' · no document';
    const cat = entry.category_name ? ' · ' + entry.category_name : '';

    html += `
      <article class="entry-row ${entry.entry_type}" data-entry-id="${entry.id}">
        <div>
          <div class="entry-purpose">${escapeHtml(entry.purpose)}</div>
          <div class="entry-meta">${qlFormatTime(entry.entry_datetime)} · ${entry.money_type}${cat}${doc}${edited}</div>
          <div class="entry-actions">
            <button class="entry-edit" type="button" data-edit-entry="${entry.id}">✎ Edit</button>
            <button class="entry-delete" type="button" data-delete-entry="${entry.id}">Archive</button>
          </div>
        </div>
        <div class="entry-amount">${sign}${qlCurrency(entry.amount)}</div>
      </article>
    `;

    if (index === entries.length - 1) {
      html += '<div class="day-total">Day balance: ' + qlCurrency(dayBalances[currentDay]) + '</div>';
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
    qlLedgerMessage('Enter amount and purpose.');
    return;
  }

  qlLedgerMessage(selectedFile ? 'Saving entry and file…' : 'Saving…');

  const data = await qlApi('ledger_create', {
    entry_type: qlLedgerType,
    money_type: qlMoneyType,
    amount,
    purpose
  });

  if (!data.ok) {
    qlLedgerMessage('Error: ' + (data.error || 'unknown'));
    return;
  }

  if (selectedFile && data.entry && data.entry.id) {
    const upload = await qlUploadEntryFile(data.entry.id, selectedFile);
    if (!upload.ok) {
      qlLedgerMessage('Entry saved, but file error: ' + (upload.error || 'unknown'));
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
  if (fileNameEl) fileNameEl.textContent = 'No file selected';

  qlLedgerMessage('Saved.');
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

  const purpose = prompt('Purpose', currentPurpose);
  if (purpose === null) return;

  const type = confirm('OK = Income, Cancel = Expense') ? 'income' : 'expense';
  const money = confirm('OK = Cash, Cancel = Non-cash') ? 'cash' : 'noncash';

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
    fileNameEl.textContent = file ? file.name : 'No file selected';
  }
});




/* === Quick Ledger Personal Report UI 20260503-06 === */
let qlReportPeriod = 'today';

function qlToggleReportPanel() {
  const panel = document.getElementById('reportPanel');
  if (!panel) return;

  panel.classList.toggle('hidden');

  if (!panel.classList.contains('hidden')) {
    qlRunReport();
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
      out.innerHTML = '<p class="soft-note">Choose from and to dates.</p>';
      return;
    }
  }

  out.innerHTML = '<p class="soft-note">Calculating…</p>';

  const data = await qlApi('ledger_report', payload);

  if (!data.ok) {
    out.innerHTML = '<p class="soft-note">Report error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const s = data.summary || {};
  const p = data.period || {};
  const adjustment = data.adjustment === null || data.adjustment === undefined
    ? ''
    : `<div class="report-line strong"><span>Adjustment</span><b>${qlCurrency(data.adjustment)}</b></div>`;

  const scope = data.scope || {};
  const sections = data.sections || [];
  const members = data.members || [];

  const reportTitle = scope.mode === 'group'
    ? (scope.is_admin ? 'Group report · admin view' : 'Group report · your entries')
    : 'Personal report';

  const sectionsHtml = sections.length
    ? `
      <div class="section-report">
        <h3>By sections</h3>
        ${sections.map(function(section) {
          return `
            <div class="section-report-row">
              <div>
                <b>${escapeHtml(section.name || 'No section')}</b>
                <small>${section.records || 0} record(s)</small>
              </div>
              <div>
                <span>Income ${qlCurrency(section.income || 0)}</span>
                <span>Expense ${qlCurrency(section.expense || 0)}</span>
                <strong>${qlCurrency(section.balance || 0)}</strong>
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
        <h3>By members</h3>
        ${members.map(function(member) {
          return `
            <div class="section-report-row">
              <div>
                <b>${escapeHtml(member.name || member.email || 'Member')}</b>
                <small>${escapeHtml(member.email || '')} · ${member.records || 0} record(s)</small>
              </div>
              <div>
                <span>Income ${qlCurrency(member.income || 0)}</span>
                <span>Expense ${qlCurrency(member.expense || 0)}</span>
                <strong>${qlCurrency(member.balance || 0)}</strong>
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
    <div class="report-line strong"><span>Balance</span><b>${qlCurrency(s.balance || 0)}</b></div>
    <div class="report-line"><span>Income</span><b>${qlCurrency(s.income || 0)}</b></div>
    <div class="report-line"><span>Expense</span><b>${qlCurrency(s.expense || 0)}</b></div>
    <div class="report-split">
      <div><span>Cash</span><b>${qlCurrency(s.cash_balance || 0)}</b><small>in ${qlCurrency(s.cash_income || 0)} / out ${qlCurrency(s.cash_expense || 0)}</small></div>
      <div><span>Non-cash</span><b>${qlCurrency(s.noncash_balance || 0)}</b><small>in ${qlCurrency(s.noncash_income || 0)} / out ${qlCurrency(s.noncash_expense || 0)}</small></div>
    </div>
    <div class="report-line"><span>Records</span><b>${s.records || 0}</b></div>
    ${sectionsHtml}
    ${membersHtml}
    ${data.remaining === null || data.remaining === undefined ? '' : `<div class="report-line"><span>Remaining</span><b>${qlCurrency(data.remaining)}</b></div>`}
    ${adjustment}
  `;
}

document.addEventListener('click', function(event) {
  const resultBtn = event.target.closest('#ledgerResultBtn');
  const reportTab = event.target.closest('[data-report-period]');
  const runReport = event.target.closest('#runReportBtn');

  if (resultBtn) qlToggleReportPanel();
  if (reportTab) qlSetReportPeriod(reportTab);
  if (runReport) qlRunReport();
});


/* === Quick Ledger Group UI 20260503-07 === */
let qlGroups = [];
let qlActiveGroup = null;
let qlLastInvite = null;

function qlGroupMessage(message) {
  const el = document.getElementById('groupMessage');
  if (el) el.textContent = message || '';
}

async function qlLoadGroups() {
  const list = document.getElementById('groupList');
  if (!list) return;

  const data = await qlApi('group_list', {});

  if (!data.ok) {
    list.innerHTML = '<p class="soft-note">Group error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  qlGroups = data.groups || [];
  qlRenderGroups();
}

function qlRenderGroups() {
  const list = document.getElementById('groupList');
  const count = document.getElementById('groupCount');

  if (count) count.textContent = qlGroups.length + (qlGroups.length === 1 ? ' group' : ' groups');
  if (!list) return;

  if (!qlGroups.length) {
    list.innerHTML = '<p class="soft-note">No groups yet.</p>';
    return;
  }

  list.innerHTML = qlGroups.map(function(group) {
    return `
	      <button class="group-row" type="button" data-open-group="${group.id}">
	        <span>
	          <b>${escapeHtml(group.name)}</b>
	          <small>${escapeHtml(group.access_level || group.role)} · ${group.member_count || 1} member(s)</small>
	        </span>
	        <span>›</span>
	      </button>
    `;
  }).join('');
}

async function qlCreateGroup() {
  const input = document.getElementById('groupName');
  const name = (input?.value || '').trim();

  if (!name) {
    qlGroupMessage('Enter group name.');
    return;
  }

  qlGroupMessage('Creating group…');

  const data = await qlApi('group_create', {name});

  if (!data.ok) {
    qlGroupMessage('Error: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlGroupMessage('Group created.');
  await qlLoadGroups();

  if (data.group) {
    qlOpenGroup(data.group.id);
  }
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

  const inviteActions = document.getElementById('inviteActions');
  if (inviteActions) inviteActions.classList.add('hidden');

  await qlLoadMembers();
}

async function qlRenameGroup() {
  if (!qlActiveGroup) return;

  const name = prompt('New group name', qlActiveGroup.name);
  if (name === null) return;

  const data = await qlApi('group_rename', {
    group_id: qlActiveGroup.id,
    name
  });

  if (!data.ok) {
    alert('Error: ' + (data.error || 'unknown'));
    return;
  }

  await qlLoadGroups();
  qlOpenGroup(qlActiveGroup.id);
}

async function qlCreateInvite(channel) {
  if (!qlActiveGroup) {
    qlGroupMessage('Open a group first.');
    return;
  }

	  const data = await qlApi('group_invite_create', {
	    group_id: qlActiveGroup.id,
	    channel: channel || 'copy',
	    invited_email: (document.getElementById('inviteEmail')?.value || '').trim(),
	    access_level: document.getElementById('inviteAccessLevel')?.value || 'base'
	  });

  if (!data.ok) {
    qlGroupMessage('Invite error: ' + (data.error || 'unknown'));
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

async function qlCopyInvite() {
  const input = document.getElementById('inviteUrl');
  const value = input?.value || '';

  if (!value) return;

  try {
    await navigator.clipboard.writeText(value);
    qlGroupMessage('Invite copied.');
  } catch (e) {
    if (input) {
      input.select();
      document.execCommand('copy');
      qlGroupMessage('Invite copied.');
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
    box.innerHTML = '<p class="soft-note">Members error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const members = data.members || [];
  if (count) count.textContent = String(members.length);

	  const canManage = qlActiveGroup && qlActiveGroup.access_level === 'advanced';
	  box.innerHTML = members.map(function(member) {
	    const access = member.access_level || member.role || 'base';
	    const control = canManage ? `
	      <select class="ql-input member-access-select" data-member-access="${escapeHtml(member.user_id)}">
	        <option value="base" ${access === 'base' ? 'selected' : ''}>На бегу</option>
	        <option value="manager" ${access === 'manager' ? 'selected' : ''}>Средний</option>
	        <option value="advanced" ${access === 'advanced' ? 'selected' : ''}>Advanced</option>
	      </select>
	    ` : `<small>${escapeHtml(access)}</small>`;
	    return `
	      <div class="member-row">
	        <span>
	          <b>${escapeHtml(member.display_name || member.email)}</b>
	          <small>${escapeHtml(member.email)} · ${escapeHtml(member.role)} · ${escapeHtml(access)}</small>
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
    qlGroupMessage('Access update error: ' + (data.error || 'unknown'));
    await qlLoadMembers();
    return;
  }

  qlGroupMessage('Access updated.');
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
    qlShowAuthMessage('Sign in first, then this invite can be joined.');
    return;
  }

  const join = await qlApi('group_join', {token});

  if (join.ok) {
    history.replaceState({}, '', '/app.php');
    await qlLoadGroups();
    if (join.group) qlOpenGroup(join.group.id);
    qlGroupMessage('Joined group.');
  } else {
    qlGroupMessage('Invite error: ' + (join.error || 'unknown'));
  }
}

document.addEventListener('click', function(event) {
  const createGroup = event.target.closest('#createGroupBtn');
  const openGroup = event.target.closest('[data-open-group]');
	  const renameGroup = event.target.closest('#renameGroupBtn');
	  const createInvite = event.target.closest('#createInviteBtn');
	  const copyInvite = event.target.closest('#copyInviteBtn');

	  if (createGroup) qlCreateGroup();
	  if (openGroup) qlOpenGroup(openGroup.getAttribute('data-open-group'));
	  if (renameGroup) qlRenameGroup();
	  if (createInvite) qlCreateInvite('copy');
	  if (copyInvite) qlCopyInvite();
	});

document.addEventListener('change', function(event) {
  const memberAccess = event.target.closest('[data-member-access]');
  if (memberAccess) qlUpdateMemberAccess(memberAccess.getAttribute('data-member-access'), memberAccess.value);
});

const qlPreviousRenderUserForGroups = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForGroups(user);
  setTimeout(function() {
    qlLoadGroups();
    qlHandleInviteFromUrl();
  }, 80);
};

/* === Quick Ledger Group Ledger Scope UI 20260503-08 === */
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

  select.innerHTML = '<option value="">Choose group</option>' + qlGroups.map(function(group) {
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
    qlScopeMessage('Create or choose a group first.');
  } else {
    qlScopeMessage(qlLedgerScopeMode === 'group' ? 'Group ledger mode.' : 'Personal ledger mode.');
  }

  qlLoadLedger();
}

const qlOldLoadLedgerForScope = qlLoadLedger;
qlLoadLedger = async function() {
  const feed = document.getElementById('ledgerFeed');
  if (!feed) return;

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId) {
    feed.innerHTML = '<p class="soft-note">Choose a group to see group entries.</p>';
    qlRenderLedger([], {income:0, expense:0, balance:0});
    return;
  }

  const data = await qlApi('ledger_list', qlCurrentLedgerPayload({limit: 150}));

  if (!data.ok) {
    feed.innerHTML = '<p class="soft-note">Ledger error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  qlRenderLedger(data.entries || [], data.summary || {});
};

const qlOldSaveLedgerEntryForScope = qlSaveLedgerEntry;
qlSaveLedgerEntry = async function() {
  const amount = (document.getElementById('ledgerAmount')?.value || '').trim();
  const purpose = (document.getElementById('ledgerPurpose')?.value || '').trim();
  const fileInput = document.getElementById('ledgerFile');
  const selectedFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

  if (!amount || !purpose) {
    qlLedgerMessage('Enter amount and purpose.');
    return;
  }

  if (qlLedgerScopeMode === 'group' && !qlLedgerGroupId) {
    qlLedgerMessage('Choose a group first.');
    return;
  }

  qlLedgerMessage(selectedFile ? 'Saving entry and file…' : 'Saving…');

  const data = await qlApi('ledger_create', qlCurrentLedgerPayload({
    entry_type: qlLedgerType,
    money_type: qlMoneyType,
    amount,
    purpose,
    category_id: qlSelectedSectionId()
  }));

  if (!data.ok) {
    qlLedgerMessage('Error: ' + (data.error || 'unknown'));
    return;
  }

  if (selectedFile && data.entry && data.entry.id) {
    const upload = await qlUploadEntryFile(data.entry.id, selectedFile);
    if (!upload.ok) {
      qlLedgerMessage('Entry saved, but file error: ' + (upload.error || 'unknown'));
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
  if (fileNameEl) fileNameEl.textContent = 'No file selected';

  qlLedgerMessage(qlLedgerScopeMode === 'group' ? 'Saved to group.' : 'Saved.');
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
  qlScopeMessage(qlLedgerGroupId ? 'Group selected.' : 'Choose a group.');
  qlLoadLedger();

  if (qlLedgerGroupId) {
    qlOpenGroup(qlLedgerGroupId);
  }

  qlRunReport(); // refresh report after group selector change
});

const qlOldLoadGroupsForScope = qlLoadGroups;
qlLoadGroups = async function() {
  await qlOldLoadGroupsForScope();
  qlRefreshGroupSelect();
};



/* === Quick Ledger Section UI 20260503-09 === */
let qlCategories = [];

function qlSelectedSectionId() {
  const select = document.getElementById('ledgerSection');
  return select && select.value ? Number(select.value) : null;
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
  const filtered = qlCategories;

  select.innerHTML = '<option value="">No section</option>' + filtered.map(function(cat) {
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
    qlLedgerMessage('Enter section name.');
    return;
  }

  const payload = {
    name: name,
    category_type: 'income'
  };

  if (qlLedgerScopeMode === 'group' && qlLedgerGroupId) {
    payload.group_id = qlLedgerGroupId;
  }

  qlLedgerMessage('Creating section…');

  const data = await qlApi('category_create', payload);

  if (!data.ok) {
    qlLedgerMessage('Section error: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlLedgerMessage('Section created.');
  await qlLoadCategories();

  const select = document.getElementById('ledgerSection');
  const created = (data.categories || []).find(function(cat) {
    return cat.name === name;
  });

  if (select && created) {
    select.value = created.id;
  }
}


/* === Quick Ledger Group Messages UI 20260503-14 === */
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
    list.innerHTML = '<p class="soft-note">Messages error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const messages = data.messages || [];

  if (count) count.textContent = String(messages.length);

  if (!messages.length) {
    list.innerHTML = '<p class="soft-note">No messages yet.</p>';
    return;
  }

  list.innerHTML = messages.map(function(msg) {
    return `
      <article class="message-row ${Number(msg.is_read || 0) ? '' : 'unread'}">
        <div class="message-head">
          <b>${escapeHtml(msg.sender_name || msg.sender_email || 'User')}</b>
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
    qlMessageStatus('Open a group first.');
    return;
  }

  const input = document.getElementById('messageText');
  const text = (input?.value || '').trim();

  if (!text) {
    qlMessageStatus('Write a message.');
    return;
  }

  qlMessageStatus('Sending…');

  const data = await qlApi('message_send', {
    group_id: qlActiveGroup.id,
    message_text: text
  });

  if (!data.ok) {
    qlMessageStatus('Message error: ' + (data.error || 'unknown'));
    return;
  }

  if (input) input.value = '';
  qlMessageStatus('Sent.');

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

/* === Quick Ledger Unread Message Modal 20260503-17 === */
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

/* === Quick Ledger Business Desk UI 20260503-18 === */
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

/* === Quick Ledger Proforma View / Print 20260503-20 === */
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

/* === Quick Ledger Module Navigation NAV-1 20260503-24 === */
function qlSetModule(moduleName) {
  const requested = moduleName || 'ledger';
  const visible = requested === 'reports' ? 'ledger' : requested;

  document.querySelectorAll('[data-module-tab]').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-module-tab') === requested);
  });

  document.querySelectorAll('.ql-module[data-module]').forEach(function(module) {
    module.classList.toggle('hidden', module.getAttribute('data-module') !== visible);
    module.classList.toggle('active', module.getAttribute('data-module') === visible);
  });

  if (requested === 'reports') {
    const reportPanel = document.getElementById('reportPanel');
    const resultCard = document.getElementById('ledgerResultBtn');

    if (reportPanel) reportPanel.classList.remove('hidden');

    setTimeout(function() {
      if (reportPanel && reportPanel.scrollIntoView) {
        reportPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
      } else if (resultCard && resultCard.scrollIntoView) {
        resultCard.scrollIntoView({behavior: 'smooth', block: 'start'});
      }
    }, 40);
  }
}

document.addEventListener('click', function(event) {
  const tab = event.target.closest('[data-module-tab]');
  if (!tab) return;

  qlSetModule(tab.getAttribute('data-module-tab') || 'ledger');
});

window.qlSetModule = qlSetModule;

/* === Quick Ledger Accountable Money UI STEP-4 20260520 === */
let qlAdvanceGroupId = null;
let qlAdvances = [];
let qlAdvanceMembers = [];
let qlAdvanceScope = {};

function qlAdvanceStatus(message) {
  const el = document.getElementById('advanceStatus');
  if (el) el.textContent = message || '';
}

function qlAdvanceStatusLabel(status) {
  if (status === 'issued') return 'Issued';
  if (status === 'submitted') return 'To review';
  if (status === 'accepted') return 'Accepted';
  if (status === 'returned') return 'Returned';
  if (status === 'discrepancy') return 'Mismatch';
  if (status === 'closed') return 'Closed';
  return status || 'Advance';
}

function qlAdvanceIsWaiting(status) {
  return ['issued', 'submitted', 'returned', 'discrepancy'].includes(status);
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

  select.innerHTML = '<option value="">Choose group</option>' + groups.map(function(group) {
    const level = group.access_level || group.role || 'base';
    return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(level) + '</option>';
  }).join('');

  select.value = qlAdvanceGroupId ? String(qlAdvanceGroupId) : '';
}

function qlAdvanceRenderMembers() {
  const select = document.getElementById('advanceMemberSelect');
  if (!select) return;

  const members = qlAdvanceMembers || [];
  select.innerHTML = '<option value="">Choose employee</option>' + members.map(function(member) {
    const label = (member.display_name || member.email || 'Member') + ' · ' + (member.access_level || member.role || 'base');
    return '<option value="' + escapeHtml(member.user_id) + '">' + escapeHtml(label) + '</option>';
  }).join('');
}

async function qlAdvanceLoadMembers() {
  qlAdvanceMembers = [];
  qlAdvanceRenderMembers();

  if (!qlAdvanceGroupId || !qlAdvanceScope.can_manage_money) return;

  const data = await qlApi('group_members', { group_id: Number(qlAdvanceGroupId) });
  if (!data.ok) {
    qlAdvanceStatus('Members error: ' + (data.error || 'unknown'));
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

  let issued = 0;
  let spent = 0;
  let expectedLeft = 0;
  let waiting = 0;

  qlAdvances.forEach(function(advance) {
    const s = advance.summary || {};
    const status = advance.status || '';
    if (status !== 'accepted' && status !== 'closed') {
      issued += Number(advance.amount || 0);
      spent += Number(s.cash_out || 0) + Number(s.card_out || 0);
      expectedLeft += Number(s.cash_left || 0);
    }
    if (qlAdvanceIsWaiting(status)) waiting += 1;
  });

  summary.innerHTML = `
    <div><span>Issued</span><b>${qlCurrency(issued)}</b></div>
    <div><span>Spent</span><b>${qlCurrency(spent)}</b></div>
    <div><span>Expected left</span><b>${qlCurrency(expectedLeft)}</b></div>
    <div><span>Waiting</span><b>${waiting}</b></div>
  `;
}

function qlAdvanceActionHtml(advance) {
  const status = advance.status || '';
  const isAssigned = qlCurrentUser && String(advance.assigned_to_user_id) === String(qlCurrentUser.id);
  const canSubmit = isAssigned && ['issued', 'returned', 'discrepancy'].includes(status);
  const canModerate = !!(qlAdvanceScope && qlAdvanceScope.can_moderate && ['submitted', 'discrepancy'].includes(status));
  let html = '';

  if (canSubmit) {
    html += `
      <div class="advance-submit-row">
        <input class="ql-input" type="text" inputmode="decimal" placeholder="Real cash left" data-advance-actual="${escapeHtml(advance.id)}">
        <input class="ql-input" type="text" placeholder="Note" data-advance-note="${escapeHtml(advance.id)}">
        <button class="primary-btn" type="button" data-advance-submit="${escapeHtml(advance.id)}">Submit</button>
        <button class="ghost-btn" type="button" data-advance-open-tape="${escapeHtml(advance.on_the_go_tape_id || '')}">Open tape</button>
      </div>
    `;
  }

  if (canModerate) {
    html += `
      <div class="advance-moderate-row">
        <button class="primary-btn" type="button" data-advance-accept="${escapeHtml(advance.id)}">Accept</button>
        <button class="ghost-btn danger-soft-btn" type="button" data-advance-return="${escapeHtml(advance.id)}">Return</button>
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

  if (count) count.textContent = qlAdvances.length + (qlAdvances.length === 1 ? ' advance' : ' advances');
  qlAdvanceRenderSummary();
  qlAdvanceRenderIssuePanel();

  if (!list) return;

  if (!qlAdvanceGroupId) {
    list.innerHTML = '<p class="soft-note">Choose a group to see accountable money.</p>';
    return;
  }

  if (!qlAdvances.length) {
    list.innerHTML = '<p class="soft-note">No accountable money in this group yet.</p>';
    return;
  }

  list.innerHTML = qlAdvances.map(function(advance) {
    const s = advance.summary || {};
    const status = advance.status || 'issued';
    const employee = advance.assigned_to_display_name || advance.assigned_to_email || 'Employee';
    const diff = Number(advance.difference_amount || 0);
    const differenceHtml = advance.actual_remaining !== null && advance.actual_remaining !== undefined
      ? `<div><span>Actual</span><b>${qlCurrency(advance.actual_remaining)}</b></div><div class="${Math.abs(diff) > 0.009 ? 'metric-alert' : ''}"><span>Difference</span><b>${qlCurrency(diff)}</b></div>`
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
          <div><span>Cash spent</span><b>${qlCurrency(s.cash_out || 0)}</b></div>
          <div><span>Card spent</span><b>${qlCurrency(s.card_out || 0)}</b></div>
          <div><span>Expected left</span><b>${qlCurrency(s.cash_left || 0)}</b></div>
          <div><span>Records</span><b>${Number(s.records_count || 0)}</b></div>
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
    qlAdvanceRenderList();
    qlAdvanceStatus((qlGroups || []).length ? 'Choose a group.' : 'Create or join a group first.');
    return;
  }

  qlAdvanceStatus('Loading accountable money…');

  const data = await qlApi('advance_list', {
    group_id: Number(qlAdvanceGroupId),
    limit: 150
  });

  if (!data.ok) {
    qlAdvances = [];
    qlAdvanceScope = {};
    qlAdvanceRenderList();
    qlAdvanceStatus('Advance error: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvances = data.advances || [];
  qlAdvanceScope = data.scope || {};
  qlAdvanceStatus(qlAdvanceScope.can_manage_money ? 'Advanced money control.' : (qlAdvanceScope.can_moderate ? 'Moderation mode.' : 'Own assigned money.'));

  qlAdvanceRenderList();
  await qlAdvanceLoadMembers();
  qlAdvanceRenderIssuePanel();
}

async function qlAdvanceCreate() {
  const memberId = document.getElementById('advanceMemberSelect')?.value || '';
  const title = (document.getElementById('advanceTitle')?.value || '').trim();
  const amount = (document.getElementById('advanceAmount')?.value || '').trim();

  if (!qlAdvanceGroupId) {
    qlAdvanceStatus('Choose a group first.');
    return;
  }
  if (!memberId || !amount) {
    qlAdvanceStatus('Choose employee and amount.');
    return;
  }

  qlAdvanceStatus('Issuing accountable cash…');

  const data = await qlApi('advance_create', {
    group_id: Number(qlAdvanceGroupId),
    assigned_to_user_id: Number(memberId),
    title: title || 'Pocket advance',
    amount: amount,
    currency: 'EUR'
  });

  if (!data.ok) {
    qlAdvanceStatus('Issue error: ' + (data.error || 'unknown'));
    return;
  }

  const titleEl = document.getElementById('advanceTitle');
  const amountEl = document.getElementById('advanceAmount');
  if (titleEl) titleEl.value = '';
  if (amountEl) amountEl.value = '';

  qlAdvanceStatus('Money issued.');
  await qlLoadAdvances();
}

async function qlAdvanceSubmit(id) {
  const actual = (qlAdvanceField('data-advance-actual', id)?.value || '').trim();
  const note = (qlAdvanceField('data-advance-note', id)?.value || '').trim();

  if (!actual) {
    qlAdvanceStatus('Enter real cash left.');
    return;
  }

  qlAdvanceStatus('Submitting report…');

  const data = await qlApi('advance_submit', {
    id: Number(id),
    actual_remaining: actual,
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Submit error: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus(data.advance && data.advance.status === 'discrepancy' ? 'Submitted with mismatch.' : 'Submitted for moderation.');
  await qlLoadAdvances();
  if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
}

async function qlAdvanceAccept(id) {
  const note = prompt('Moderation note', '') || '';
  qlAdvanceStatus('Accepting report…');

  const data = await qlApi('advance_accept', {
    id: Number(id),
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Accept error: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus('Accepted. Expenses are now in the group ledger.');
  await qlLoadAdvances();

  if (qlLedgerScopeMode === 'group' && String(qlLedgerGroupId || '') === String(qlAdvanceGroupId || '')) {
    qlLoadLedger();
  }
}

async function qlAdvanceReturn(id) {
  const note = prompt('Return note', '') || '';
  qlAdvanceStatus('Returning report…');

  const data = await qlApi('advance_return', {
    id: Number(id),
    note: note
  });

  if (!data.ok) {
    qlAdvanceStatus('Return error: ' + (data.error || 'unknown'));
    return;
  }

  qlAdvanceStatus('Returned for correction.');
  await qlLoadAdvances();
}

function qlAdvanceOpenTape(tapeId) {
  if (!tapeId) return;

  qlOtrActiveTapeId = Number(tapeId);
  window.qlOtrActiveTapeId = Number(tapeId);
  qlSetModule('ontherun');

  setTimeout(function() {
    if (typeof qlLoadOtrTapes === 'function') qlLoadOtrTapes();
    if (typeof qlLoadOnTheGo === 'function') qlLoadOnTheGo();
  }, 100);
}

document.addEventListener('change', function(event) {
  const group = event.target.closest('#advanceGroupSelect');
  if (!group) return;

  qlAdvanceGroupId = group.value ? Number(group.value) : null;
  qlLoadAdvances();
});

document.addEventListener('click', function(event) {
  const create = event.target.closest('#advanceCreateBtn');
  const submit = event.target.closest('[data-advance-submit]');
  const accept = event.target.closest('[data-advance-accept]');
  const ret = event.target.closest('[data-advance-return]');
  const tape = event.target.closest('[data-advance-open-tape]');

  if (create) qlAdvanceCreate();
  if (submit) qlAdvanceSubmit(submit.getAttribute('data-advance-submit'));
  if (accept) qlAdvanceAccept(accept.getAttribute('data-advance-accept'));
  if (ret) qlAdvanceReturn(ret.getAttribute('data-advance-return'));
  if (tape) qlAdvanceOpenTape(tape.getAttribute('data-advance-open-tape'));
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
window.qlSetModule = function(moduleName) {
  if (typeof qlAdvancePreviousSetModule === 'function') {
    qlAdvancePreviousSetModule(moduleName);
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

/* === Quick Ledger On The Go OTR-1 20260503-25 === */
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

async function qlUploadOnTheGoFile(captureId, fileInput) {
  if (!fileInput || !fileInput.files || !fileInput.files[0]) return { ok: true };

  const form = new FormData();
  form.append('capture_id', String(captureId));
  form.append('file', fileInput.files[0]);

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

  box.innerHTML = '<p class="soft-note">Loading attachments…</p>';

  const data = await qlApi('on_the_go_file_list', { capture_id: Number(captureId) });

  if (!data.ok) {
    box.innerHTML = '<p class="soft-note">Attachment error: ' + escapeHtml(data.error || 'unknown') + '</p>';
    return;
  }

  const files = data.files || [];

  if (attach) {
    attach.textContent = files.length ? (files.length + ' attachment(s)') : 'No attachment';
  }

  if (!files.length) {
    box.innerHTML = '<p class="soft-note">No attachments.</p>';
    return;
  }

  box.innerHTML = files.map(function(file) {
    const size = Number(file.size_bytes || 0);
    const kb = size ? Math.max(1, Math.round(size / 1024)) + ' KB' : '';
    return `
      <div class="otr-file-row">
        <div>
          <b>${escapeHtml(file.original_name || 'Attachment')}</b>
          <small>${escapeHtml(kb)} · ${escapeHtml(file.created_at || '')}</small>
        </div>
        <div class="otr-file-actions">
          <a class="ghost-btn otr-file-open" href="${escapeHtml(file.download_url || '#')}" target="_blank" rel="noopener">Open</a>
          <button class="ghost-btn otr-file-delete danger-soft" type="button" data-otr-file-delete="${escapeHtml(file.id)}">Delete</button>
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
    if (status) status.textContent = 'Choose a file first.';
    return;
  }

  if (status) status.textContent = 'Uploading attachment…';

  const upload = await qlUploadOnTheGoFile(captureId, fileInput);

  if (!upload.ok) {
    if (status) status.textContent = 'Upload error: ' + (upload.error || 'unknown');
    return;
  }

  fileInput.value = '';
  const name = document.getElementById('otrReviewFileName');
  if (name) name.textContent = 'No file selected';

  if (status) status.textContent = 'Attachment uploaded.';
  await qlLoadOtrReviewFiles(captureId);
  await qlLoadOnTheGo();
}

async function qlDeleteOtrReviewFile(fileId) {
  const captureId = document.getElementById('otrReviewId')?.value || '';
  const status = document.getElementById('otrReviewStatus');

  if (!fileId) return;

  if (!confirm('Delete this attachment from the pending record?')) {
    return;
  }

  if (status) status.textContent = 'Deleting attachment…';

  const data = await qlApi('on_the_go_file_delete', { id: Number(fileId) });

  if (!data.ok) {
    if (status) status.textContent = 'Delete error: ' + (data.error || 'unknown');
    return;
  }

  if (status) status.textContent = 'Attachment deleted.';
  await qlLoadOtrReviewFiles(captureId);
  await qlLoadOnTheGo();
}

function qlOpenOtrReview(id) {
  const item = qlOtrFindItem(id);
  const modal = document.getElementById('otrReviewModal');

  if (!item || !modal) {
    qlOtrMessage('Could not open this record. Reload and try again.');
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
  if (attach) attach.textContent = Number(item.files_count || 0) > 0 ? 'Attachment: yes' : 'No attachment';
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
    if (status) status.textContent = 'Add amount or note before saving.';
    return;
  }

  if (status) status.textContent = 'Saving…';

  const data = await qlApi('on_the_go_update', {
    id: Number(id),
    capture_type: type,
    amount: amount,
    description: description
  });

  if (!data.ok) {
    if (status) status.textContent = 'Error: ' + (data.error || 'unknown');
    return;
  }

  if (status) status.textContent = 'Saved. Still not included in reports.';
  await qlLoadOnTheGo();
}

async function qlArchiveOtrRecord() {
  const id = document.getElementById('otrReviewId')?.value || '';
  const status = document.getElementById('otrReviewStatus');
  if (!id) return;

  if (!confirm('Archive this pending On the Go record? It will disappear from the review journal.')) {
    return;
  }

  if (status) status.textContent = 'Archiving…';

  const data = await qlApi('on_the_go_archive', { id: Number(id) });

  if (!data.ok) {
    if (status) status.textContent = 'Error: ' + (data.error || 'unknown');
    return;
  }

  qlCloseOtrReviewModal();
  qlOtrMessage('Pending record archived.');
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
    if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : 'No file selected';
    return;
  }

  if (!map[input.id]) return;

  const label = document.getElementById(map[input.id]);
  if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : 'No attachment';
});

const qlPreviousRenderUserForOnTheGo = qlRenderUser;
qlRenderUser = function(user) {
  qlPreviousRenderUserForOnTheGo(user);
  if (user) {
    setTimeout(qlLoadOnTheGo, 80);
  }
};

const qlPreviousSetModuleForOnTheGo = window.qlSetModule || qlSetModule;
qlSetModule = function(moduleName) {
  qlPreviousSetModuleForOnTheGo(moduleName);
  if (moduleName === 'ontherun') {
    setTimeout(qlLoadOnTheGo, 40);
  }
};
window.qlSetModule = qlSetModule;

/* === Quick Ledger On The Go Convert To Ledger OTR-2C 20260503-29 === */
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
  select.innerHTML = '<option value="">Choose group</option>' + groups.map(function(group) {
    return '<option value="' + escapeHtml(group.id) + '">' + escapeHtml(group.name) + ' · ' + escapeHtml(group.role) + '</option>';
  }).join('');
}

async function qlOtrLoadConvertSections() {
  const sectionSelect = document.getElementById('otrConvertSection');
  const entryType = document.getElementById('otrConvertEntryType')?.value || 'expense';
  const groupSelect = document.getElementById('otrConvertGroup');
  const groupId = qlOtrConvertScope === 'group' && groupSelect && groupSelect.value ? Number(groupSelect.value) : 0;

  if (!sectionSelect) return;

  sectionSelect.innerHTML = '<option value="">On the Go default</option>';

  const payload = { category_type: entryType };
  if (groupId) payload.group_id = groupId;

  const data = await qlApi('category_list', payload);

  if (!data.ok) return;

  const categories = data.categories || [];
  sectionSelect.innerHTML = '<option value="">On the Go default</option>' + categories.map(function(cat) {
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
  if (purposeEl) purposeEl.value = item.description || 'On the Go record';

  qlOtrSetConvertScope('personal');
  qlOtrLoadConvertSections();
}

async function qlOtrConvertToLedger() {
  const id = document.getElementById('otrReviewId')?.value || '';
  const amount = document.getElementById('otrReviewAmount')?.value || '';
  const description = document.getElementById('otrReviewDescription')?.value || '';
  const entryType = document.getElementById('otrConvertEntryType')?.value || 'expense';
  const moneyType = document.getElementById('otrConvertMoneyType')?.value || 'cash';
  const purpose = document.getElementById('otrConvertPurpose')?.value || description || 'On the Go record';
  const sectionId = document.getElementById('otrConvertSection')?.value || '';
  const groupId = qlOtrConvertScope === 'group' ? (document.getElementById('otrConvertGroup')?.value || '') : '';
  const status = document.getElementById('otrReviewStatus');

  if (!id) return;

  if (!amount.trim()) {
    if (status) status.textContent = 'Amount is required before converting to Ledger.';
    return;
  }

  if (qlOtrConvertScope === 'group' && !groupId) {
    if (status) status.textContent = 'Choose a group or switch to Personal.';
    return;
  }

  if (!confirm('Convert this On the Go record to a normal Ledger entry? It will be removed from the pending review list.')) {
    return;
  }

  if (status) status.textContent = 'Converting to Ledger…';

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
    if (status) status.textContent = 'Convert error: ' + (data.error || 'unknown') + (data.message ? ' · ' + data.message : '');
    return;
  }

  qlCloseOtrReviewModal();
  qlOtrMessage('Converted to Ledger. Attachments copied: ' + (data.copied_files || 0) + '.');

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

/* === Quick Ledger Entry Details Viewer LEDGER-2A 20260503-31 === */
function qlLedgerFormatEntryType(entry) {
  const type = entry.entry_type === 'income' ? 'Income' : 'Expense';
  const money = entry.money_type === 'cash' ? 'Cash' : 'Non-cash';
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
              <b>${escapeHtml(file.file_original_name || file.file_stored_name || 'Attachment')}</b>
              <small>${escapeHtml(file.file_kind || 'file')} · ${qlLedgerFileSize(file.file_size)} · ${escapeHtml(file.created_at || '')}</small>
            </div>
            <a class="ghost-btn ledger-file-open" href="${escapeHtml(file.download_url || '#')}" target="_blank" rel="noopener">Open</a>
          </div>
        `;
      }).join('')
    : '<p class="soft-note">No attachments.</p>';

  content.innerHTML = `
    ${qlLedgerIsFromOnTheGo(entry) ? '<div class="ledger-origin-badge">Converted from On the Go</div>' : ''}
    <div class="ledger-detail-main">
      <div class="ledger-detail-amount ${entry.entry_type === 'income' ? 'income' : 'expense'}">${qlCurrency(entry.amount || 0)}</div>
      <div class="ledger-detail-type">${escapeHtml(qlLedgerFormatEntryType(entry))}</div>
    </div>

    <div class="ledger-detail-grid">
      <div><span>Date</span><b>${escapeHtml(entry.entry_datetime || '')}</b></div>
      <div><span>Section</span><b>${escapeHtml(entry.category_name || 'No section')}</b></div>
      <div><span>Owner</span><b>${escapeHtml(entry.owner_display_name || '')}</b></div>
      <div><span>Files</span><b>${Number(entry.file_count || files?.length || 0)}</b></div>
    </div>

    <div class="ledger-detail-block">
      <span>Purpose</span>
      <p>${escapeHtml(entry.purpose || '')}</p>
    </div>

    <div class="ledger-detail-block">
      <span>Note</span>
      <p>${escapeHtml(entry.note || 'No note')}</p>
    </div>

    <div class="ledger-detail-files">
      <div class="ledger-detail-files-head">
        <h4>Attachments</h4>
        <span>${(files || []).length} file(s)</span>
      </div>
      ${fileRows}
    </div>
  `;
}

async function qlOpenLedgerDetail(entryId) {
  const modal = document.getElementById('ledgerDetailModal');
  const content = document.getElementById('ledgerDetailContent');

  if (!modal || !content) return;

  content.innerHTML = '<p class="soft-note">Loading entry…</p>';
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');

  const data = await qlApi('ledger_detail', { id: Number(entryId) });

  if (!data.ok) {
    content.innerHTML = '<p class="soft-note">Entry detail error: ' + escapeHtml(data.error || 'unknown') + '</p>';
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

/* === Quick Ledger On The Go Tape Controller OTR-3B-CLEAN 20260503-35 === */
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

window.qlSetModule = function(moduleName) {
  if (typeof qlOtrCleanPreviousSetModule === 'function') {
    qlOtrCleanPreviousSetModule(moduleName);
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


/* === Quick Ledger On the Go Operational Body Mode OTR-3F 20260503-40 === */
(function() {
  function qlSyncOtrBodyMode() {
    const module = document.getElementById('moduleOnTheGo');
    const isOtr = !!(module && !module.classList.contains('hidden'));

    document.body.classList.toggle('ql-otr-mode', isOtr);
  }

  document.addEventListener('click', function(event) {
    const tab = event.target.closest('[data-module-tab]');
    if (!tab) return;

    setTimeout(qlSyncOtrBodyMode, 80);
  });

  const prevSetModule = window.qlSetModule;
  window.qlSetModule = function(moduleName) {
    if (typeof prevSetModule === 'function') {
      prevSetModule(moduleName);
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

/* === Quick Ledger On the Go Mobile Cash/Card Action Flow OTR-3G 20260503-41 === */
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

  document.addEventListener('click', function(event) {
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

/* === Quick Ledger On the Go Close Session UI OTR-4C 20260503-48 === */
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

/* === Quick Ledger On the Go Save Guard OTR-4C-2 20260503-49 === */
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

/* === Quick Ledger On the Go Session Cards OTR-4D 20260503-50 === */
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

/* === Quick Ledger On the Go Session Reset OTR-4E-1 20260503-60 === */
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

/* === Quick Ledger On the Go Real Two-Zone Session UI OTR-4F 20260503-61 === */
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

/* === Quick Ledger On the Go Final Active Journal Override OTR-4F-2 20260503-62 === */
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

/* === Quick Ledger Premium Feature Shell STEP-5 20260520 === */
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
    const labels = {
      trip: 'Trip with Friends placeholder is ready. Backend and balancing logic will be added later.',
      reports: 'Report Studio placeholder is ready. Premium report package will be added later.'
    };
    premiumStatus(labels[feature] || 'Premium feature placeholder is ready.');
  }

  document.addEventListener('click', function(event) {
    const open = event.target.closest('[data-premium-open]');
    const soon = event.target.closest('[data-premium-soon]');

    if (open) qlPremiumOpen(open.getAttribute('data-premium-open'));
    if (soon) qlPremiumSoon(soon.getAttribute('data-premium-soon'));
  });

  window.qlPremiumOpen = qlPremiumOpen;
})();
