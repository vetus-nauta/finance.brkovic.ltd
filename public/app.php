<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>FinDesk App</title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#f6f8fb">
  <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="FinDesk">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/app.css?v=20260520-09">
</head>
<body>
  <main class="ql-shell app-shell">
    <section class="hero-card glass">
      <div class="brand-pill"><span data-i18n="app.brand">FinDesk · brkovic.ltd</span><span class="brand-dot"></span><span data-i18n="app.secure">secure finance web app</span></div>

      <div class="language-strip glass-soft">
        <div class="language-strip-copy">
          <span data-i18n="language.detected">Language</span>
          <b data-detected-language>English</b>
          <p data-i18n="language.notice">If the app language does not match your system language, choose the right option here.</p>
        </div>
        <label class="language-picker">
          <span data-i18n="language.choose">Choose language</span>
          <select class="ql-input language-select" data-language-select aria-label="Language">
            <option value="ru">Русский</option>
            <option value="en">English</option>
            <option value="de">Deutsch</option>
            <option value="it">Italiano</option>
            <option value="es">Español</option>
            <option value="sr">Srpski / MNE / HR</option>
            <option value="zh">中文（普通话）</option>
          </select>
        </label>
      </div>

      <div id="authStateLoading">
        <h1 data-i18n="auth.loadingTitle">FinDesk</h1>
        <p class="lead" data-i18n="auth.loadingLead">Checking your session…</p>
      </div>

      <div id="loginPanel" class="auth-panel hidden">
        <h1 data-i18n="auth.signInTitle">Sign in</h1>
        <p class="lead" data-i18n="auth.signInLead">Enter your email. We will send a 6-digit code.</p>

        <label class="form-label" for="loginEmail" data-i18n="auth.email">Email</label>
        <input id="loginEmail" class="ql-input" type="email" placeholder="you@example.com" autocomplete="email">

        <button id="sendCodeBtn" class="primary-btn wide-btn" type="button" data-i18n="auth.sendCode">Send code</button>

        <div id="codeBlock" class="code-block hidden">
          <label class="form-label" for="loginCode" data-i18n="auth.code">Code</label>
          <input id="loginCode" class="ql-input code-input" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="000000">
          <button id="verifyCodeBtn" class="primary-btn wide-btn" type="button" data-i18n="auth.verify">Verify and enter</button>
        </div>

        <p id="authMessage" class="soft-note"></p>
      </div>

      <div id="userPanel" class="auth-panel hidden">
        <h1 data-i18n="auth.welcomeTitle">Welcome</h1>
        <p class="lead" data-i18n="auth.welcomeLead">You are signed in. This session should stay after reload.</p>

        <div class="user-card">
          <div class="user-avatar">FD</div>
          <div>
            <div id="userName" class="user-name">User</div>
            <div id="userEmail" class="user-email"></div>
          </div>
        </div>
          <nav class="module-nav glass-soft" aria-label="FinDesk modules">
            <button class="module-tab" type="button" data-module-tab="ontherun" data-i18n="nav.ontherun">На бегу</button>
            <button class="module-tab" type="button" data-module-tab="captain" data-i18n="nav.captain">FinDesk</button>
            <button class="module-tab" type="button" data-module-tab="money" data-i18n="nav.advanced">Advanced</button>
            <button class="module-tab active" type="button" data-module-tab="ledger" data-i18n="nav.ledger">Учет</button>
            <button class="module-tab" type="button" data-module-tab="premium" data-i18n="nav.premium">Premium</button>
            <button class="module-tab" type="button" data-module-tab="reports" data-i18n="nav.reports">Отчеты</button>
            <button class="module-tab" type="button" data-module-tab="groups" data-i18n="nav.groups">Группы</button>
            <button class="module-tab" type="button" data-module-tab="business" data-i18n="nav.business">Бизнес</button>
            <button class="module-tab" type="button" data-module-tab="settings" data-i18n="nav.settings">Настройки</button>
          </nav>

          <section class="mode-ladder glass-soft">
            <div class="mode-ladder-head">
              <h2 data-i18n="modes.title">Три рабочих слоя</h2>
            </div>
            <div class="mode-card-grid">
              <button class="mode-card ontherun" type="button" data-mode-open="ontherun">
                <span>01</span>
                <b data-i18n="modes.ontherunTitle">На бегу</b>
                <small data-i18n="modes.ontherunText">Минимум на экране: получил, потратил, осталось. Отправка только когда реальный остаток совпал.</small>
              </button>
              <button class="mode-card captain" type="button" data-mode-open="captain">
                <span>02</span>
                <b data-i18n="modes.captainTitle">FinDesk</b>
                <small data-i18n="modes.captainText">Средний слой для менеджеров: чистые отчеты, сданные записи и контекст модерации.</small>
              </button>
              <button class="mode-card advanced" type="button" data-mode-open="money">
                <span>03</span>
                <b data-i18n="modes.advancedTitle">Advanced</b>
                <small data-i18n="modes.advancedText">Слой организатора: группы, выданные деньги, модерация и расширенные инструменты.</small>
              </button>
            </div>
          </section>

          <div id="moduleLedger" class="ql-module active" data-module="ledger">

        <section class="ledger-stack">
          <div class="result-card glass-soft">
            <button id="ledgerResultBtn" class="result-button" type="button">
              <span>Current balance</span>
              <strong id="ledgerBalance">€0.00</strong>
            </button>
            <div class="mini-results">
              <span>Income: <b id="ledgerIncome">€0.00</b></span>
              <span>Expense: <b id="ledgerExpense">€0.00</b></span>
            </div>

            <div id="reportPanel" class="report-panel hidden">
              <div class="report-tabs">
                <button class="report-tab active" type="button" data-report-period="today">Today</button>
                <button class="report-tab" type="button" data-report-period="month">Month</button>
                <button class="report-tab" type="button" data-report-period="custom">Custom</button>
              </div>

              <div id="customPeriod" class="custom-period hidden">
                <input id="reportFrom" class="ql-input" type="date">
                <input id="reportTo" class="ql-input" type="date">
              </div>

              <label class="form-label" for="remainingAmount">Remaining at period end</label>
              <input id="remainingAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Optional">

              <button id="runReportBtn" class="ghost-btn wide-btn" type="button">Calculate report</button>

              <div id="reportOutput" class="report-output">
                <p class="soft-note">Choose a period and calculate.</p>
              </div>
            </div>
          </div>

          <div class="ledger-feed-card glass-soft">
            <div class="feed-head">
              <h2>Entries</h2>
              <span id="ledgerCount">0 records</span>
            </div>

            <div class="scope-box">
              <div class="segmented">
                <button type="button" class="seg active" data-scope-mode="personal">Personal</button>
                <button type="button" class="seg" data-scope-mode="group">Group</button>
              </div>
              <select id="ledgerGroupSelect" class="ql-input group-scope-select hidden">
                <option value="">Choose group</option>
              </select>
              <p id="scopeMessage" class="soft-note"></p>
            </div>
            <div id="ledgerFeed" class="ledger-feed">
              <p class="soft-note">No records yet. Add the first one below.</p>
            </div>
          </div>

          <div class="ledger-input-card glass-soft">
            <h2>New entry</h2>

            <div class="segmented" role="group" aria-label="Income or expense">
              <button type="button" class="seg active" data-ledger-type="income">Income</button>
              <button type="button" class="seg" data-ledger-type="expense">Expense</button>
            </div>

            <div class="segmented" role="group" aria-label="Cash or non-cash">
              <button type="button" class="seg active" data-money-type="cash">Cash</button>
              <button type="button" class="seg" data-money-type="noncash">Non-cash</button>
            </div>

            <label class="form-label" for="ledgerSection">Section</label>
            <select id="ledgerSection" class="ql-input">
              <option value="">No section</option>
            </select>

            <div class="section-create-row">
              <input id="newSectionName" class="ql-input" type="text" placeholder="Example: Home, Work, Yacht, Trip…">
              <button id="createSectionBtn" class="ghost-btn" type="button">+ Create section</button>
            </div>
            <p class="section-help">Section is an accounting area. Details like fuel, provisions or service go into Purpose.</p>

            <label class="form-label" for="ledgerAmount">Amount</label>
            <input id="ledgerAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">

            <label class="form-label" for="ledgerPurpose">Purpose</label>
            <input id="ledgerPurpose" class="ql-input" type="text" placeholder="Fuel, marina, cash received…">

            <label class="file-picker">
              <input id="ledgerFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
              <span>Choose photo / document</span>
              <small id="ledgerFileName">No file selected</small>
            </label>

            <button id="saveLedgerBtn" class="primary-btn wide-btn" type="button">Save entry</button>
            <p id="ledgerMessage" class="soft-note"></p>
          </div>
        </section>
          </div>

          <div id="moduleOnTheGo" class="ql-module hidden" data-module="ontherun">

            <section id="otrTapeDesk" class="otr-tape-desk glass-soft">
              <div class="otr-tape-head">
                <div>
                  <h2>On the Go</h2>
                  <p class="soft-note">Operational tapes: capture first, review later.</p>
                </div>
                <button id="otrNewTapeBtn" class="primary-btn otr-new-tape-btn" type="button">+ New tape</button>
              </div>

              <div id="otrNewTapePanel" class="otr-new-tape-panel hidden">
                <label class="form-label" for="otrNewTapeAmount">Cash received / given</label>
                <div class="otr-new-tape-row">
                  <input id="otrNewTapeAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">
                  <button id="otrCreateTapeBtn" class="primary-btn" type="button">Create tape</button>
                </div>
                <p id="otrTapeStatus" class="soft-note"></p>
              </div>

              <div id="otrTapeList" class="otr-tape-list">
                <p class="soft-note">Loading tapes…</p>
              </div>

              <div id="otrTapeSummary" class="otr-tape-summary">
                <div class="otr-cash-summary-group">
                  <div><span>Given</span><b id="otrTapeGiven">€0.00</b></div>
                  <div><span>Cash spent</span><b id="otrTapeCashSpent">€0.00</b></div>
                  <div><span>Cash left</span><b id="otrTapeCashLeft">€0.00</b></div>
                </div>
                <div class="otr-card-summary">
                  <span>Card spent</span><b id="otrTapeCardSpent">€0.00</b>
                </div>
              </div>
            </section>

            <section class="on-the-go-card glass-soft">
              <div class="feed-head">
                <h2>On the Go</h2>
                <span id="otrCount">0 to review</span>
              </div>

              <p class="soft-note">Quickly capture cash received, cash spent or card/non-cash spending. These records do not enter reports until you review them.</p>

                <div id="otrMobileActions" class="otr-mobile-actions">
                  <button id="otrMobileCashBtn" class="otr-mobile-action-btn cash" type="button">
                    <span>Cash</span>
                    <b>Cash expense</b>
                  </button>
                  <button id="otrMobileCardBtn" class="otr-mobile-action-btn card" type="button">
                    <span>Card</span>
                    <b>Card expense</b>
                  </button>
                </div>

                <div id="otrMobileInputPanel" class="otr-mobile-input-panel hidden" aria-hidden="true">
                  <div class="otr-mobile-input-head">
                    <div>
                      <span id="otrMobileInputKicker">CASH</span>
                      <h3 id="otrMobileInputTitle">Cash expense</h3>
                    </div>
                    <button id="otrMobileInputCloseBtn" class="modal-close" type="button">×</button>
                  </div>

                  <div id="otrMobileInputWatermark" class="otr-mobile-input-watermark cash">Cash expense</div>

                  <input id="otrMobileAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Cash expense amount">
                  <input id="otrMobileDesc" class="ql-input" type="text" placeholder="Cash payment note — optional">

                  <label class="file-picker otr-file otr-mobile-file">
                    <input id="otrMobileFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
                    <span>Photo / document</span>
                    <small id="otrMobileFileName">No attachment</small>
                  </label>

                  <button id="otrMobileSaveBtn" class="primary-btn wide-btn" type="button">✓ Save</button>
                  <button id="otrMobileCloseSessionBtn" class="ghost-btn wide-btn otr-close-session-btn" type="button">Close session</button>
                  <p id="otrMobileStatus" class="soft-note"></p>
                </div>

                  <div class="otr-session-stack">
                    <div class="otr-session-stack-head">
                      <h3>Sessions</h3>
                      <span>Tap later to review</span>
                    </div>
                    <div id="otrSessionCards" class="otr-session-cards">
                      <p class="soft-note">Loading sessions…</p>
                    </div>
                  </div>

                <div class="otr-grid">
                <div class="otr-card">
                  <h3>Cash received</h3>
                  <input id="otrCashInAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Amount">
                  <input id="otrCashInDesc" class="ql-input" type="text" placeholder="Note / from whom — optional">
                  <label class="file-picker otr-file">
                    <input id="otrCashInFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
                    <span>Photo / document</span>
                    <small id="otrCashInFileName">No attachment</small>
                  </label>
                  <button class="primary-btn wide-btn" type="button" data-otr-save="cash_in">Save cash received</button>
                </div>

                <div class="otr-card">
                  <h3>Cash spent</h3>
                  <input id="otrCashOutAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Amount">
                  <input id="otrCashOutDesc" class="ql-input" type="text" placeholder="Note / where — optional">
                  <label class="file-picker otr-file">
                    <input id="otrCashOutFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
                    <span>Photo / document</span>
                    <small id="otrCashOutFileName">No attachment</small>
                  </label>
                  <button class="primary-btn wide-btn" type="button" data-otr-save="cash_out">Save cash spent</button>
                </div>

                <div class="otr-card">
                  <h3>Card / non-cash spent</h3>
                  <input id="otrNoncashOutAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Amount">
                  <input id="otrNoncashOutDesc" class="ql-input" type="text" placeholder="Note / where — optional">
                  <label class="file-picker otr-file">
                    <input id="otrNoncashOutFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
                    <span>Photo / document</span>
                    <small id="otrNoncashOutFileName">No attachment</small>
                  </label>
                  <button class="primary-btn wide-btn" type="button" data-otr-save="noncash_out">Save non-cash spent</button>
                </div>
              </div>

              <p id="otrMessage" class="soft-note"></p>

              <div class="otr-journal-head">
                <h3>On the Go journal</h3>
                <span>Not included in reports</span>
              </div>
              <div id="otrJournal" class="otr-journal">
                <p class="soft-note">No records to review yet.</p>
              </div>
            </section>
          </div>

          <div id="moduleCaptain" class="ql-module hidden" data-module="captain">
            <section class="captain-card glass-soft">
              <div class="captain-hero">
                <div>
                  <span class="captain-kicker" data-i18n="captain.kicker">Средний слой</span>
                  <h2 data-i18n="captain.title">Отчеты FinDesk</h2>
                  <p class="soft-note tight-note" data-i18n="captain.lead">Этот слой связывает быстрые записи с проверкой менеджера до попадания данных в отчет группы.</p>
                </div>
              </div>

              <div class="captain-workflow">
                <article class="captain-work-card active">
                  <span>01</span>
                  <h3 data-i18n="captain.currentTitle">Текущий отчет</h3>
                  <p data-i18n="captain.currentText">Продолжить активный отчет “на бегу” и подготовить его к сдаче.</p>
                  <div id="captainCurrentSummary" class="captain-live-panel">
                    <p class="soft-note" data-i18n="captain.loading">Загружаю данные FinDesk…</p>
                  </div>
                  <button class="ghost-btn wide-btn" type="button" data-mode-open="ontherun" data-i18n="captain.currentAction">Открыть “На бегу”</button>
                </article>

                <article class="captain-work-card review">
                  <span>02</span>
                  <h3 data-i18n="captain.reviewTitle">Сданные записи</h3>
                  <p data-i18n="captain.reviewText">Рабочий вид менеджера для проверки отчетов перед включением в общие итоги.</p>
                  <div id="captainSubmittedList" class="captain-live-panel captain-review-list">
                    <p class="soft-note" data-i18n="captain.loading">Загружаю данные FinDesk…</p>
                  </div>
                  <button class="ghost-btn wide-btn" type="button" data-mode-open="groups" data-i18n="captain.reviewAction">Открыть группы</button>
                </article>

                <article class="captain-work-card export">
                  <span>03</span>
                  <h3 data-i18n="captain.exportTitle">Пакет отчета</h3>
                  <p data-i18n="captain.exportText">Здесь будут сводки, печатные формы и Excel-выгрузки.</p>
                  <div id="captainReportPack" class="captain-live-panel">
                    <p class="soft-note" data-i18n="captain.packSoon">Печатные формы и Excel будут собраны вокруг проверенного среднего слоя.</p>
                  </div>
                  <button class="ghost-btn wide-btn" type="button" data-mode-open="reports" data-i18n="captain.exportAction">Открыть отчеты</button>
                </article>
              </div>
            </section>
          </div>

          <div id="moduleMoney" class="ql-module hidden" data-module="money">
            <section class="advance-card glass-soft">
              <div class="feed-head">
                <div>
                  <h2 data-i18n="money.title">Advanced: деньги под отчет</h2>
                  <p class="soft-note tight-note" data-i18n="money.lead">Выдача, сверка и принятие расходов в общий отчет группы.</p>
                </div>
                <span id="advanceCount">0 строк</span>
              </div>

              <div class="advance-toolbar">
                <label class="form-label" for="advanceGroupSelect">Группа</label>
                <select id="advanceGroupSelect" class="ql-input">
                  <option value="">Выберите группу</option>
                </select>
                <p id="advanceStatus" class="soft-note"></p>
              </div>

              <div id="advanceIssuePanel" class="advance-issue-panel hidden">
                <div class="advance-panel-head">
                  <h3 data-i18n="money.issueTitle">Выдать деньги</h3>
                  <span data-i18n="money.issueScope">Advanced / администратор</span>
                </div>
                <select id="advanceMemberSelect" class="ql-input">
                  <option value="">Выберите сотрудника</option>
                </select>
                <input id="advanceTitle" class="ql-input" type="text" placeholder="Назначение / поездка / отчет">
                <input id="advanceAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">
                <button id="advanceCreateBtn" class="primary-btn wide-btn" type="button">Выдать под отчет</button>
              </div>

              <div id="advanceSummary" class="advance-summary">
                <div><span>Выдано</span><b>€0.00</b></div>
                <div><span>Потрачено</span><b>€0.00</b></div>
                <div><span>Остаток</span><b>€0.00</b></div>
                <div><span>Ожидает</span><b>0</b></div>
              </div>

              <div id="advanceList" class="advance-list">
                <p class="soft-note">Выберите группу, чтобы увидеть деньги под отчет.</p>
              </div>
            </section>
          </div>

          <div id="modulePremium" class="ql-module hidden" data-module="premium">
            <section class="premium-card glass-soft">
              <div class="premium-hero">
                <div>
                  <span class="premium-kicker" data-i18n="premium.kicker">Премиум аккаунт</span>
                  <h2 data-i18n="premium.title">Премиум функции</h2>
                  <p class="soft-note tight-note" data-i18n="premium.lead">Тестовый доступ открыт, пока мы фиксируем форму продукта.</p>
                </div>
                <span class="premium-status-pill" data-i18n="premium.status">Тестовый доступ</span>
              </div>

              <div class="premium-feature-grid">
                <article id="premiumAdvancedMode" class="premium-feature-card available">
                  <div class="premium-feature-head">
                    <span class="premium-feature-icon">A</span>
                    <span class="premium-feature-state" data-i18n="premium.status">Тестовый доступ</span>
                  </div>
                  <h3 data-i18n="premium.advancedTitle">Advanced Mode</h3>
                  <p data-i18n="premium.advancedText">Режим организатора: модерация денег, группы и расширенные финансовые инструменты.</p>
                  <button class="ghost-btn wide-btn" type="button" data-premium-open="money" data-i18n="premium.openAdvanced">Открыть Advanced</button>
                </article>

                <article id="premiumTripFriends" class="premium-feature-card planned">
                  <div class="premium-feature-head">
                    <span class="premium-feature-icon">T</span>
                    <span class="premium-feature-state" data-i18n="premium.prepared">Подготовлено</span>
                  </div>
                  <h3 data-i18n="premium.tripTitle">Поездка с друзьями</h3>
                  <p data-i18n="premium.tripText">Группа людей, общая копилка, расходы поездки и выравнивание балансов по взносам.</p>
                  <button class="ghost-btn wide-btn" type="button" data-premium-soon="trip" data-i18n="premium.prepared">Подготовлено</button>
                </article>

                <article class="premium-feature-card planned">
                  <div class="premium-feature-head">
                    <span class="premium-feature-icon">R</span>
                    <span class="premium-feature-state" data-i18n="premium.prepared">Подготовлено</span>
                  </div>
                  <h3 data-i18n="premium.reportTitle">Студия отчетов</h3>
                  <p data-i18n="premium.reportText">Премиум-сводки, аккуратная печать и пакеты проверки для менеджера.</p>
                  <button class="ghost-btn wide-btn" type="button" data-premium-soon="reports" data-i18n="premium.prepared">Подготовлено</button>
                </article>
              </div>

              <p id="premiumStatus" class="soft-note"></p>
            </section>
          </div>

          <div id="moduleGroups" class="ql-module hidden" data-module="groups">

        <section class="group-card glass-soft">
          <div class="feed-head">
            <h2>Groups</h2>
            <span id="groupCount">0 groups</span>
          </div>

          <div class="group-create">
            <input id="groupName" class="ql-input" type="text" placeholder="Group name">
            <button id="createGroupBtn" class="primary-btn wide-btn" type="button">Create group</button>
            <p id="groupMessage" class="soft-note"></p>
          </div>

          <div id="groupList" class="group-list">
            <p class="soft-note">No groups yet.</p>
          </div>

          <div id="groupDetails" class="group-details hidden">
            <div class="group-title-row">
              <h2 id="activeGroupName">Group</h2>
              <button id="renameGroupBtn" class="ghost-btn" type="button">Rename</button>
            </div>

            <div class="invite-box">
              <input id="inviteEmail" class="ql-input" type="email" placeholder="Employee email (optional)">
              <select id="inviteAccessLevel" class="ql-input">
                <option value="base">На бегу / Base</option>
                <option value="manager">Средний / Manager</option>
                <option value="advanced">Advanced / Admin</option>
              </select>
              <button id="createInviteBtn" class="primary-btn wide-btn" type="button">Create invite link</button>

              <div id="inviteActions" class="invite-actions hidden">
                <input id="inviteUrl" class="ql-input" type="text" readonly>

                <div class="share-grid">
                  <a id="shareEmail" class="share-btn" href="#" target="_blank" rel="noopener">Email</a>
                  <a id="shareWhatsapp" class="share-btn" href="#" target="_blank" rel="noopener">WhatsApp</a>
                  <a id="shareViber" class="share-btn" href="#" target="_blank" rel="noopener">Viber</a>
                  <a id="shareTelegram" class="share-btn" href="#" target="_blank" rel="noopener">Telegram</a>
                  <button id="copyInviteBtn" class="share-btn" type="button">Copy</button>
                </div>
              </div>
            </div>

            <div class="messages-box">
              <div class="feed-head">
                <h2>Messages</h2>
                <span id="messageCount">0</span>
              </div>

              <div id="messageList" class="message-list">
                <p class="soft-note">No messages yet.</p>
              </div>

              <div class="message-compose">
                <input id="messageText" class="ql-input" type="text" placeholder="Write a message to the group…">
                <button id="sendMessageBtn" class="primary-btn" type="button">Send</button>
              </div>

              <p id="messageStatus" class="soft-note"></p>
            </div>

            <div class="members-box">
              <div class="feed-head">
                <h2>Members</h2>
                <span id="memberCount">0</span>
              </div>
              <div id="memberList" class="member-list"></div>
            </div>
          </div>
        </section>
          </div>

          <div id="moduleBusiness" class="ql-module hidden" data-module="business">

        <section class="business-card glass-soft">
          <div class="feed-head">
            <h2>Business Desk</h2>
            <span>Company tools</span>
          </div>

          <p class="soft-note">Company profile, clients and proforma documents live here. Ledger stays clean for fast daily entries.</p>

          <div class="business-tabs">
            <button class="business-tab active" type="button" data-business-tab="company">Company</button>
            <button class="business-tab" type="button" data-business-tab="clients">Clients</button>
            <button class="business-tab" type="button" data-business-tab="proformas">Proformas</button>
          </div>

          <div id="businessCompanyPanel" class="business-panel">
            <h3>Company profile</h3>
            <p class="business-panel-note">Your own company details. Used later in proforma and printable documents.</p>

            <label class="bd-field">
              <span>Company name</span>
              <input id="bdCompanyName" class="ql-input" type="text" placeholder="Example: Brkovic Ltd">
            </label>

            <div class="business-grid-2">
              <label class="bd-field">
                <span>Email</span>
                <input id="bdCompanyEmail" class="ql-input" type="email" placeholder="company@example.com">
              </label>
              <label class="bd-field">
                <span>Phone</span>
                <input id="bdCompanyPhone" class="ql-input" type="text" placeholder="+382 ...">
              </label>
            </div>

            <label class="bd-field">
              <span>Address</span>
              <input id="bdCompanyAddress" class="ql-input" type="text" placeholder="Street, city, country">
            </label>

            <div class="business-grid-2">
              <label class="bd-field">
                <span>Registration number</span>
                <input id="bdCompanyReg" class="ql-input" type="text" placeholder="Company ID / registration no.">
              </label>
              <label class="bd-field">
                <span>VAT number</span>
                <input id="bdCompanyVat" class="ql-input" type="text" placeholder="VAT ID if applicable">
              </label>
            </div>

            <label class="bd-field">
              <span>Default VAT %</span>
              <input id="bdVatRate" class="ql-input" type="text" inputmode="decimal" placeholder="0 or 21">
            </label>

            <p class="business-panel-note">Discount is not a company setting. It belongs to a specific proforma.</p>

            <button id="saveCompanyBtn" class="primary-btn wide-btn" type="button">Save company profile</button>
            <p id="companyStatus" class="soft-note"></p>
          </div>

          <div id="businessClientsPanel" class="business-panel hidden">
            <h3>Clients</h3>
            <p class="business-panel-note">People or companies you prepare proformas for.</p>

            <label class="bd-field">
              <span>Client name</span>
              <input id="bdClientName" class="ql-input" type="text" placeholder="Client or company name">
            </label>

            <div class="business-grid-2">
              <label class="bd-field">
                <span>Email</span>
                <input id="bdClientEmail" class="ql-input" type="email" placeholder="client@example.com">
              </label>
              <label class="bd-field">
                <span>Phone</span>
                <input id="bdClientPhone" class="ql-input" type="text" placeholder="+382 ...">
              </label>
            </div>

            <label class="bd-field">
              <span>Address</span>
              <input id="bdClientAddress" class="ql-input" type="text" placeholder="Client address">
            </label>

            <button id="createClientBtn" class="primary-btn wide-btn" type="button">Create client</button>
            <p id="clientStatus" class="soft-note"></p>
            <div id="clientList" class="business-list"></div>
          </div>

          <div id="businessProformasPanel" class="business-panel hidden">
            <h3>Proforma</h3>
            <p class="business-panel-note">Create a simple offer document. It is not a fiscal invoice.</p>

            <label class="bd-field">
              <span>Client</span>
              <select id="bdProformaClient" class="ql-input">
                <option value="">No client selected</option>
              </select>
            </label>

            <label class="bd-field">
              <span>Document title</span>
              <input id="bdProformaTitle" class="ql-input" type="text" placeholder="Title" value="Proforma">
            </label>

            <label class="bd-field">
              <span>Service / item</span>
              <input id="bdItemName" class="ql-input" type="text" placeholder="Example: Yacht management service">
            </label>

            <div class="business-grid-3">
              <label class="bd-field">
                <span>Qty</span>
                <input id="bdItemQty" class="ql-input" type="text" inputmode="decimal" placeholder="Qty" value="1">
              </label>
              <label class="bd-field">
                <span>Unit</span>
                <input id="bdItemUnit" class="ql-input" type="text" placeholder="Unit" value="pcs">
              </label>
              <label class="bd-field">
                <span>Unit price</span>
                <input id="bdItemPrice" class="ql-input" type="text" inputmode="decimal" placeholder="Unit price">
              </label>
            </div>

            <div class="business-grid-2">
              <label class="bd-field">
                <span>VAT % for this proforma</span>
                <input id="bdProformaVatRate" class="ql-input" type="text" inputmode="decimal" placeholder="0 or 21">
              </label>
              <label class="bd-field">
                <span>Discount % for this proforma</span>
                <input id="bdProformaDiscountRate" class="ql-input" type="text" inputmode="decimal" placeholder="Optional">
              </label>
            </div>

            <label class="bd-field">
              <span>Public note</span>
              <textarea id="bdPublicNote" class="ql-input business-textarea" placeholder="Visible note for the client"></textarea>
            </label>

            <button id="createProformaBtn" class="primary-btn wide-btn" type="button">Create proforma</button>
            <p id="proformaStatus" class="soft-note"></p>
            <div id="proformaList" class="business-list"></div>

            <div id="proformaPreview" class="proforma-preview hidden">
              <div class="proforma-preview-actions">
                <button id="printProformaBtn" class="primary-btn" type="button">Print / Save PDF</button>
                <button id="closeProformaPreviewBtn" class="ghost-btn" type="button">Close preview</button>
              </div>
              <p class="print-hint">To export as PDF, choose “Save to PDF” in the print dialog.</p>
              <div id="proformaDocument" class="proforma-document"></div>
            </div>
          </div>
        </section>
          </div>

          <div id="moduleSettings" class="ql-module hidden" data-module="settings">
            <section class="settings-card glass-soft">
              <div class="feed-head">
                <h2 data-i18n="settings.title">Настройки</h2>
                <span data-i18n="settings.tools">Инструменты приложения</span>
              </div>

              <p class="soft-note" data-i18n="settings.lead">Установить FinDesk как web app, поддержать проект и управлять аккаунтом.</p>

              <div class="settings-actions">
                <button type="button" class="ghost-btn wide-btn" data-open-install="auto" data-i18n="settings.install">Установить web app</button>
                <button type="button" class="ghost-btn wide-btn" data-open-donate data-i18n="settings.donate">Donate</button>
                <button id="logoutBtn" class="ghost-btn wide-btn danger-soft" type="button" data-i18n="settings.logout">Выйти</button>
              </div>

              <div class="settings-note">
                <h3 data-i18n="settings.languageTitle">Язык</h3>
                <p class="soft-note" data-i18n="language.notice">Если язык приложения не совпал с языком системы, выберите правильный вариант здесь.</p>
                <select class="ql-input language-select" data-language-select aria-label="Language">
                  <option value="ru">Русский</option>
                  <option value="en">English</option>
                  <option value="de">Deutsch</option>
                  <option value="it">Italiano</option>
                  <option value="es">Español</option>
                  <option value="sr">Srpski / MNE / HR</option>
                  <option value="zh">中文（普通话）</option>
                </select>
              </div>
            </section>
          </div>
      </div>
    </section>

    <footer class="ql-footer">
      <button type="button" class="footer-link" data-open-install="auto" data-i18n="footer.install">Установить</button>
      <button type="button" class="footer-link" data-open-donate data-i18n="footer.donate">Donate</button>
    </footer>
  </main>

  <div id="installModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass">
      <button class="modal-close" type="button" data-close-modal>×</button>
      <div id="installContent"></div>
    </div>
  </div>

  <div id="donateModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass">
      <button class="modal-close" type="button" data-close-modal>×</button>
      <h3>Donate</h3>
      <p>Donation widget slot is ready.</p>
      <div id="donate-widget-slot"></div>
    </div>
  </div>


  <div id="ledgerDetailModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass ledger-detail-modal-card">
      <button class="modal-close" type="button" data-close-ledger-detail>×</button>
      <h3>Entry details</h3>

      <div id="ledgerDetailContent" class="ledger-detail-content">
        <p class="soft-note">Loading entry…</p>
      </div>

      <div class="ledger-detail-actions">
        <button class="ghost-btn wide-btn" type="button" data-close-ledger-detail>Close</button>
      </div>
    </div>
  </div>

  <div id="otrReviewModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass otr-review-modal-card">
      <button class="modal-close" type="button" data-close-otr-review>×</button>
      <h3>Review On the Go record</h3>
      <p class="soft-note">This record is still not included in reports. Edit it now or keep it for later.</p>

      <input id="otrReviewId" type="hidden">

      <label class="form-label" for="otrReviewType">Type</label>
      <select id="otrReviewType" class="ql-input">
        <option value="cash_in">Cash received</option>
        <option value="cash_out">Cash spent</option>
        <option value="noncash_out">Card / non-cash spent</option>
      </select>

      <label class="form-label" for="otrReviewAmount">Amount</label>
      <input id="otrReviewAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Amount">

      <label class="form-label" for="otrReviewDescription">Note</label>
      <input id="otrReviewDescription" class="ql-input" type="text" placeholder="Note / description">

      <div class="otr-attachment-panel">
        <div class="otr-attachment-head">
          <h4>Attachments</h4>
          <span id="otrReviewAttachment">No attachment.</span>
        </div>

        <div id="otrReviewFiles" class="otr-review-files">
          <p class="soft-note">No attachments.</p>
        </div>

        <label class="file-picker otr-review-upload">
          <input id="otrReviewFileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
          <span>Add / replace attachment</span>
          <small id="otrReviewFileName">No file selected</small>
        </label>

        <button id="uploadOtrReviewFileBtn" class="ghost-btn wide-btn otr-upload-compact-btn" type="button">Upload selected file</button>
      </div>

      <div class="otr-convert-panel">
        <h4>Move to Ledger</h4>
        <p class="soft-note compact-note">Choose destination. Empty section means “On the Go”.</p>

        <div class="segmented" role="group" aria-label="Convert scope">
          <button id="otrConvertScopePersonal" class="seg active" type="button" data-otr-convert-scope="personal">Personal</button>
          <button id="otrConvertScopeGroup" class="seg" type="button" data-otr-convert-scope="group">Group</button>
        </div>

        <label class="form-label hidden" id="otrConvertGroupLabel" for="otrConvertGroup">Group</label>
        <select id="otrConvertGroup" class="ql-input hidden">
          <option value="">Choose group</option>
        </select>

        <label class="form-label" for="otrConvertSection">Section</label>
        <select id="otrConvertSection" class="ql-input">
          <option value="">On the Go default</option>
        </select>

        <div class="business-grid-2">
          <label class="bd-field">
            <span>Ledger type</span>
            <select id="otrConvertEntryType" class="ql-input">
              <option value="income">Income</option>
              <option value="expense">Expense</option>
            </select>
          </label>

          <label class="bd-field">
            <span>Money type</span>
            <select id="otrConvertMoneyType" class="ql-input">
              <option value="cash">Cash</option>
              <option value="noncash">Non-cash</option>
            </select>
          </label>
        </div>

        <label class="form-label" for="otrConvertPurpose">Purpose</label>
        <input id="otrConvertPurpose" class="ql-input" type="text" placeholder="Purpose for Ledger">

        <button id="convertOtrToLedgerBtn" class="primary-btn wide-btn" type="button">Convert to Ledger</button>
      </div>

      <div class="otr-review-actions">
        <button id="saveOtrReviewBtn" class="ghost-btn wide-btn" type="button">Save pending changes</button>
        <button id="archiveOtrBtn" class="ghost-btn wide-btn danger-soft" type="button">Archive pending record</button>
        <button class="ghost-btn wide-btn" type="button" data-close-otr-review>Keep for later</button>
      </div>

      <p id="otrReviewStatus" class="soft-note"></p>
    </div>
  </div>


  <div id="otrSessionModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass otr-session-modal-card">
      <button class="modal-close" type="button" data-close-otr-session>×</button>
      <div class="otr-session-modal-head">
        <div>
          <span id="otrSessionModalKicker">SESSION</span>
          <h3 id="otrSessionModalTitle">Session</h3>
        </div>
        <b id="otrSessionModalAmount">€0.00</b>
      </div>
      <p id="otrSessionModalMeta" class="soft-note"></p>
      <div id="otrSessionModalRecords" class="otr-session-modal-records">
        <p class="soft-note">Loading session…</p>
      </div>
      <div class="otr-session-modal-actions">
        <button id="otrActivateSessionBtn" class="primary-btn wide-btn" type="button">Activate this session</button>
        <button id="otrArchiveSessionBtn" class="ghost-btn wide-btn danger-soft" type="button">Archive session</button>
        <button class="ghost-btn wide-btn" type="button" data-close-otr-session>Close</button>
      </div>
      <p id="otrSessionModalStatus" class="soft-note"></p>
    </div>
  </div>

  <div id="messageModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass message-modal-card">
      <button class="modal-close" type="button" data-close-message-modal>×</button>
      <div class="message-modal-icon">✉</div>
      <h3 id="messageModalTitle">You have a message</h3>
      <p id="messageModalText">New group message.</p>
      <div class="message-modal-actions">
        <button id="openMessageGroupBtn" class="primary-btn wide-btn" type="button">Open group</button>
        <button id="laterMessageBtn" class="ghost-btn wide-btn" type="button">Later</button>
      </div>
    </div>
  </div>

  <script src="/assets/i18n.js?v=20260520-09"></script>
  <script src="/assets/donate.js?v=20260503-11"></script>
  <script src="/assets/notifications.js?v=20260503-11"></script>
  <script src="/assets/app.js?v=20260520-09"></script>
</body>
</html>
