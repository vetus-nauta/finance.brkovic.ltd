<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
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
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="/assets/icon-512.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
  <link rel="stylesheet" href="/assets/app.css?v=20260607-cash-professional-report-routes38">
</head>
<body>
  <main class="ql-shell app-shell">
    <section class="hero-card glass">
      <div class="brand-pill">
        <img class="brand-mark" src="/assets/brand-mark.png?v=20260522-106" alt="FinDesk" width="28" height="28">
        <span data-i18n="app.brand">FinDesk · brkovic.ltd</span><span class="brand-dot"></span><span data-i18n="app.secure">secure finance web app</span>
      </div>

      <div class="language-strip glass-soft" data-language-strip>
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
        <button class="language-close" type="button" data-language-close aria-label="Close language notice">×</button>
      </div>

      <div id="authStateLoading">
        <h1 data-i18n="auth.loadingTitle">FinDesk</h1>
        <p class="lead" data-i18n="auth.loadingLead">Checking your session…</p>
      </div>

        <div id="loginPanel" class="auth-panel hidden">
        <section class="findesk-auth-welcome" aria-label="FinDesk">
          <span>FinDesk</span>
          <h1>Деньги исчезают тихо.</h1>
          <p>Потратил — запиши. Получил — запиши. Работайте один или с людьми, а FinDesk сохранит картину денег.</p>
          <div class="findesk-auth-paths">
            <button type="button" data-auth-start="solo">Работаю один</button>
            <button type="button" data-auth-start="team">Работаю с людьми</button>
            <button type="button" data-auth-start="templates">Готовые шаблоны</button>
          </div>
        </section>

        <h2 data-i18n="auth.signInTitle">Код доступа</h2>
        <p class="lead" data-i18n="auth.signInLead">Введите email и получите 6-значный код. После входа FinDesk продолжит выбранный путь.</p>

        <label class="form-label" for="loginEmail" data-i18n="auth.email">Email</label>
        <input id="loginEmail" class="ql-input" type="email" data-i18n-placeholder="auth.placeholder.email" placeholder="email@example.com" autocomplete="email">

        <button id="sendCodeBtn" class="primary-btn wide-btn" type="button" data-i18n="auth.sendCode">Get code</button>

        <div id="codeBlock" class="code-block hidden">
          <label class="form-label" for="loginCode" data-i18n="auth.code">Code</label>
        <input id="loginCode" class="ql-input code-input" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" data-i18n-placeholder="auth.placeholder.code" placeholder="6 digits">
          <button id="verifyCodeBtn" class="primary-btn wide-btn" type="button" data-i18n="auth.verify">Verify code</button>
        </div>

        <p id="authMessage" class="soft-note"></p>
      </div>

      <div id="userPanel" class="auth-panel hidden">
          <nav class="module-nav module-nav-menu-only findesk-primary-nav phase1-primary-nav phase2-top-shell" aria-label="FinDesk">
            <button class="phase2-shell-back" type="button" data-phase-back aria-label="Назад" disabled>‹</button>
            <div class="phase2-shell-title">
              <strong>FinDesk</strong>
              <span data-phase-shell-title>Welcome Hall</span>
              <select class="phase2-workspace-select" data-phase-workspace-select aria-label="Рабочее пространство">
                <option value="">Workspace</option>
              </select>
            </div>
            <button class="module-primary-tab" type="button" data-phase-screen="journal-choice">Журнал</button>
            <div class="module-menu">
              <button class="module-tab module-menu-toggle" type="button" data-module-menu-toggle aria-expanded="false">
                <span>Меню</span>
                <b data-module-menu-current>Product</b>
              </button>
              <div class="module-menu-panel hidden" data-module-menu-panel>
                <section class="module-menu-group">
                  <span>Workspace</span>
                  <button class="module-menu-item active" type="button" data-phase-screen="workspace-hub">На главную</button>
                  <button class="module-menu-item" type="button" data-phase-screen="workspace-hub">Мои пространства</button>
                  <button class="module-menu-item" type="button" data-phase-screen="workspace-trash">Корзина</button>
                  <button class="module-menu-item" type="button" data-phase-screen="workspace-home">Текущее пространство</button>
                  <button class="module-menu-item" type="button" data-phase-screen="journal-choice">Журнал</button>
                  <button class="module-menu-item" type="button" data-phase-screen="workspace-create">Создать пространство</button>
                </section>
                <section class="module-menu-group">
                  <span>Reports</span>
                  <button class="module-menu-item" type="button" data-phase-screen="reports">Отчеты</button>
                </section>
                <section class="module-menu-group">
                  <span>Account</span>
                  <button class="module-menu-item" type="button" data-phase-screen="profile">Профиль</button>
                </section>
              </div>
            </div>
            <div class="phase2-shell-account">
              <span data-phase-account>Account</span>
              <button type="button" data-phase-logout>Выйти</button>
              <select class="phase2-language-select language-select" data-language-select aria-label="Language">
                <option value="en">EN</option>
                <option value="ru">RU</option>
                <option value="de">DE</option>
                <option value="it">IT</option>
                <option value="es">ES</option>
                <option value="sr">SR</option>
                <option value="zh">中文</option>
              </select>
            </div>
          </nav>

          <section id="phase1ProductShell" class="phase1-product-shell" data-phase-current="welcome" aria-live="polite">
            <div id="phase1Screen" class="phase1-screen">
              <div class="phase1-quiet-panel">
                <span class="phase1-kicker">FinDesk</span>
                <h1>Загружаю рабочую среду</h1>
              </div>
            </div>
          </section>

          <div id="moduleLedger" class="ql-module hidden" data-module="ledger">

        <section class="ledger-stack">
          <div class="result-card glass-soft">
            <button id="ledgerResultBtn" class="result-button" type="button">
              <span id="ledgerBalanceLabel">Учетный баланс</span>
              <strong id="ledgerBalance">€0.00</strong>
            </button>
            <div class="mini-results">
              <span>Приход: <b id="ledgerIncome">€0.00</b></span>
              <span>Расход: <b id="ledgerExpense">€0.00</b></span>
            </div>

              <div id="reportPanel" class="report-panel hidden">
                <section class="report-final-card report-current-period-card">
                  <div class="advance-panel-head">
                    <h3>Текущий период</h3>
                    <span>Открытый период · Excel / Google / фиксация</span>
                  </div>
                  <p class="soft-note tight-note">Показывает текущий открытый период: переходящий остаток из финального отчета и новые движения после закрытия.</p>
                  <div class="report-actions">
                    <button id="advancedExcelExportBtn" class="primary-btn wide-btn" type="button">Экспорт текущего периода</button>
                    <button id="advancedFinalizeReportBtn" class="ghost-btn wide-btn" type="button">Зафиксировать отчет</button>
                  </div>
                  <p id="advancedFinalizeStatus" class="soft-note tight-note"></p>
                </section>

                <section class="report-history-panel" aria-labelledby="finalReportsTitle">
                  <div class="advance-panel-head">
                    <h3 id="finalReportsTitle">Закрытые финальные отчеты</h3>
                    <span>Полный архив по report_id</span>
                  </div>
                  <div id="finalReportsList" class="final-reports-list">
                    <p class="soft-note tight-note">Выберите группу, чтобы увидеть закрытые финальные отчеты.</p>
                  </div>
                  <div id="finalReportDetail" class="final-report-detail">
                    <p class="soft-note tight-note">Откройте закрытый групповой отчет, чтобы увидеть пакет отчета и доказательства.</p>
                  </div>
                  <p id="finalReportStatus" class="soft-note tight-note"></p>
                </section>

              <div class="report-tabs">
                <button class="report-tab active" type="button" data-report-period="today">Сегодня</button>
                <button class="report-tab" type="button" data-report-period="month">Месяц</button>
                <button class="report-tab" type="button" data-report-period="custom">Период</button>
              </div>

              <div id="customPeriod" class="custom-period hidden">
                <input id="reportFrom" class="ql-input" type="date">
                <input id="reportTo" class="ql-input" type="date">
              </div>

              <label class="form-label" for="remainingAmount">Фактический остаток на конец периода</label>
              <input id="remainingAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Необязательно">

              <div class="report-actions">
                <button id="runReportBtn" class="ghost-btn wide-btn" type="button">Рассчитать сводку</button>
                <button id="printReportBtn" class="primary-btn wide-btn" type="button">Печать / PDF</button>
              </div>

              <div id="reportOutput" class="report-output">
                <p class="soft-note">Выберите период и рассчитайте сводку.</p>
              </div>
            </div>
          </div>

          <div class="ledger-feed-card glass-soft">
            <div class="feed-head">
              <h2 id="ledgerFeedTitle">Открытый журнал</h2>
              <span id="ledgerCount">0 записей</span>
            </div>

            <div class="scope-box">
              <div class="segmented">
                <button type="button" class="seg active" data-scope-mode="personal">Личный</button>
                <button type="button" class="seg" data-scope-mode="group">Группа</button>
              </div>
              <select id="ledgerGroupSelect" class="ql-input group-scope-select hidden">
                <option value="">Выберите группу</option>
              </select>
              <p id="scopeMessage" class="soft-note"></p>
            </div>
            <div id="ledgerFeed" class="ledger-feed">
              <p class="soft-note">Записей пока нет. Добавьте первую ниже.</p>
            </div>
          </div>

          <div class="ledger-input-card glass-soft">
            <h2>Новая запись</h2>

            <div class="segmented" role="group" aria-label="Приход или расход">
              <button type="button" class="seg active" data-ledger-type="income">Приход</button>
              <button type="button" class="seg" data-ledger-type="expense">Расход</button>
            </div>

            <div class="segmented" role="group" aria-label="Наличные или безнал">
              <button type="button" class="seg active" data-money-type="cash">Наличные</button>
              <button type="button" class="seg" data-money-type="noncash">Безнал</button>
            </div>

            <label class="form-label" for="ledgerSection" id="ledgerSectionLabel">Статья учета</label>
            <select id="ledgerSection" class="ql-input">
              <option value="">Без раздела</option>
            </select>

            <div class="section-create-row">
              <input id="newSectionName" class="ql-input" type="text" placeholder="Например: касса, яхта, закупки, поездка">
              <button id="createSectionBtn" class="ghost-btn" type="button">+ Создать раздел</button>
            </div>
            <p class="section-help" id="ledgerSectionHelp">Статья нужна для расходов: продукты, топливо, ремонт, поездка. Детали пишутся в назначении.</p>

            <label class="form-label" for="ledgerAmount">Сумма</label>
            <input id="ledgerAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">

            <label class="form-label" for="ledgerPurpose">Назначение</label>
            <input id="ledgerPurpose" class="ql-input" type="text" placeholder="Топливо, марина, поступление наличных">

            <label class="file-picker">
              <input id="ledgerFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
              <span>Фото / документ</span>
              <small id="ledgerFileName">Файл не выбран</small>
            </label>

            <button id="saveLedgerBtn" class="primary-btn wide-btn" type="button">Сохранить запись</button>
            <p id="ledgerMessage" class="soft-note"></p>
          </div>
        </section>
          </div>

          <div id="moduleOnTheGo" class="ql-module hidden" data-module="ontherun">

            <section id="otrStreamGate" class="otr-stream-gate phase1-retired-gate glass-soft hidden" aria-hidden="true">
              <div class="otr-stream-shell">
                <div class="otr-stream-gate-head">
                  <h2>Журнал</h2>
                </div>
                <p class="soft-note">Этот переход заменен Phase 2. Сначала выбирается поток: наличные или карта.</p>
                <button class="primary-btn wide-btn" type="button" data-phase-screen="journal-choice">Открыть выбор потока</button>
              </div>
            </section>

            <section id="otrSimpleCard" class="otr-simple-card glass-soft stream-cash">
              <div class="otr-simple-head">
                <button id="otrEditorBackBtn" class="otr-cards-round-btn otr-editor-back-btn" type="button" aria-label="Назад к карточкам">‹</button>
                <div>
                  <span id="otrSimpleStreamKicker" class="captain-kicker">Наличные</span>
                  <h2>Живой журнал</h2>
                </div>
                <div class="otr-editor-tools">
                  <button id="otrStreamSwitchBtn" class="otr-stream-switch" type="button" aria-label="Сменить поток" title="Сменить поток">Наличные</button>
                  <div id="otrSimpleStatusPill" class="otr-simple-status-pill">Черновик</div>
                  <div id="otrSimpleSyncStatus" class="otr-simple-sync-status is-saved" role="status" aria-live="polite">
                    <span data-otr-sync-label>Сохранено</span>
                    <button id="otrAutosaveRetryBtn" class="otr-sync-retry hidden" type="button">Повторить</button>
                  </div>
                </div>
              </div>

              <div class="otr-live-stack">
                <section class="otr-live-block otr-live-money">
                  <div class="otr-live-facts-grid">
                    <div class="otr-live-fact">
                      <label id="otrAdminAmountLabel" class="form-label" for="otrAdminAmount">Старт журнала</label>
                      <input id="otrAdminAmount" class="ql-input otr-admin-amount" type="text" inputmode="decimal" placeholder="0.00" readonly aria-readonly="true">
                      <p id="otrAdminAmountHelp" class="soft-note compact-note">Это стартовая сумма текущего журнала.</p>
                    </div>
                    <div id="otrSimpleResult" class="otr-simple-result otr-journal-current" aria-label="Текущий остаток">
                      <div><span>Сейчас осталось</span><b>€0.00</b></div>
                      <div><span>Записей</span><b>0</b></div>
                    </div>
                  </div>
                </section>

                <section class="otr-live-block otr-live-note">
                  <label class="form-label" for="otrSimpleNotes">Живая запись</label>
                  <div class="otr-notes-surface">
                    <textarea id="otrSimpleNotes" class="ql-input otr-simple-notes" placeholder="± Сумма и заметка..."></textarea>
                  </div>
                </section>

                <div class="otr-live-side">
                  <section class="otr-live-block otr-live-attach">
                    <div class="otr-attach-panel otr-attach-panel-phase1" aria-label="Вложения живого отчета">
                      <input id="otrSimpleFile" class="otr-attach-input" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
                      <details class="otr-attach-menu">
                        <summary class="otr-attach-menu-trigger">Скрепка</summary>
                        <div class="otr-attach-menu-list">
                          <button class="otr-attach-btn" type="button" data-otr-attach="camera" aria-label="Камера" title="Камера">
                            <span>Фото</span>
                            <small>сфотографировать чек</small>
                          </button>
                          <button class="otr-attach-btn" type="button" data-otr-attach="scan" aria-label="Скан документа" title="Скан документа">
                            <span>Скан</span>
                            <small>документ / PDF</small>
                          </button>
                          <button class="otr-attach-btn" type="button" data-otr-attach="media" aria-label="Медиатека" title="Медиатека">
                            <span>Файл</span>
                            <small>выбрать фото или документ</small>
                          </button>
                        </div>
                      </details>
                      <div id="otrSimpleFileName" class="otr-attach-file">Без вложения</div>
                      <div id="otrProofStateList" class="otr-proof-state-list" aria-live="polite">
                        <span>Доказательства: нет</span>
                      </div>
                      <button id="otrSimpleProofsBtn" class="otr-proof-open-btn hidden" type="button">Открыть вложения по карточке</button>
                    </div>
                  </section>

                  <section class="otr-live-block otr-live-summary">
                    <label class="form-label">Живые записи</label>
                    <div id="otrSimplePreview" class="otr-simple-preview">
                      <p class="soft-note">Введите строки со знаком + или -.</p>
                    </div>
                  </section>

                  <section class="otr-live-actions">
                    <div class="otr-simple-actions">
                      <button id="otrSimpleEditBtn" class="primary-btn wide-btn otr-edit-action-btn" type="button" aria-label="Зафиксировать журнал" title="Зафиксировать журнал">Зафиксировать журнал</button>
                      <button id="otrSimpleSubmitBtn" class="ghost-btn wide-btn submit-soft-btn hidden" type="button" aria-label="Сдать в FinDesk" title="Сдать в FinDesk">Сдать в FinDesk</button>
                      <button id="otrSimpleDeleteBtn" class="ghost-btn wide-btn danger-soft-btn hidden" type="button" aria-label="Удалить карточку" title="Удалить карточку">Удалить</button>
                    </div>

                    <p id="otrSimpleStatus" class="soft-note"></p>
                  </section>
                </div>
              </div>
            </section>

            <section id="otrReportCardsPanel" class="otr-report-cards-panel glass-soft hidden" aria-hidden="true">
              <div class="otr-report-cards-head">
                <div>
                  <button id="otrCardsBackBtn" class="otr-cards-round-btn" type="button" aria-label="К выбору наличные или карта" title="К выбору потока">‹</button>
                </div>
                <div class="otr-cards-title">
                  <span id="otrCardsCount">Журнал</span>
                  <h3>Живые отчеты</h3>
                </div>
                <div class="otr-cards-toolbar">
                  <button id="otrCardsFinDeskBtn" class="otr-cards-round-btn hidden" type="button" data-mode-open="captain" aria-label="Открыть FinDesk" title="Открыть FinDesk">FinDesk</button>
                  <button id="otrArchiveCardsBtn" class="ghost-btn small-btn" type="button" aria-label="Открыть архив живых отчетов" title="Архив">Архив</button>
                  <button id="otrRefreshCardsBtn" class="ghost-btn small-btn" type="button">Обновить</button>
                  <button id="otrNewCardBtn" class="otr-cards-round-btn primary" type="button" aria-label="Новая карточка">+</button>
                </div>
              </div>
              <div id="otrReportCardsList" class="otr-report-cards-list">
                <p class="soft-note">Загружаю карточки…</p>
              </div>
            </section>

            <section id="otrTapeDesk" class="otr-tape-desk glass-soft hidden legacy-otr-panel">
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

            <section class="on-the-go-card glass-soft hidden legacy-otr-panel">
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
            <section class="findesk-surface" aria-label="FinDesk">
              <header class="findesk-product-rail" aria-label="Навигация FinDesk">
                <div class="findesk-product-brand">
                  <strong>FinDesk</strong>
                  <span>Рабочий экран контроля денег</span>
                </div>
                <div class="findesk-product-actions">
                  <button class="ghost-btn small-btn" type="button" data-captain-open-quick="editor">Быстрые записи</button>
                  <details class="findesk-top-menu">
                    <summary>Детали</summary>
                    <div class="findesk-top-menu-panel">
                      <button class="findesk-top-menu-item" type="button" data-module-tab="money" data-module-screen="advances">Подотчеты</button>
                      <button class="findesk-top-menu-item" type="button" data-module-tab="reports">Итоговые отчеты</button>
                      <button class="findesk-top-menu-item" type="button" data-captain-open-archive>Архив</button>
                      <button class="findesk-top-menu-item" type="button" data-module-tab="groups">Сотрудники и группы</button>
                      <button class="findesk-top-menu-item" type="button" data-module-tab="settings">Настройки</button>
                    </div>
                  </details>
                </div>
              </header>

              <header class="findesk-shell-head">
                <div class="findesk-shell-copy">
                  <span class="findesk-shell-kicker">Рабочая группа</span>
                  <strong id="captainDeskTitle">Текущая группа</strong>
                  <p id="captainStatus" class="soft-note"></p>
                </div>
                <div class="findesk-shell-controls">
                  <label class="form-label" for="captainGroupSelect">Группа</label>
                  <select id="captainGroupSelect" class="ql-input">
                    <option value="">Выберите группу</option>
                  </select>
                  <div class="findesk-shell-metrics" aria-label="Остатки наличных">
                    <div>
                      <span>У администратора</span>
                      <b id="captainAdminCashLeft">€0.00</b>
                    </div>
                    <div>
                      <span>У сотрудников</span>
                      <b id="captainEmployeeCashLeft">€0.00</b>
                    </div>
                  </div>
                </div>
              </header>

              <div id="captainBoardHome" class="findesk-board-home">
                <div id="captainSubmittedList" class="findesk-card-board">
                  <p class="soft-note" data-i18n="captain.loading">Загружаю данные FinDesk…</p>
                </div>
              </div>

              <section id="captainCardView" class="captain-card-view hidden" aria-live="polite">
                <header class="captain-card-view-head">
                  <button id="captainCardBackBtn" class="ghost-btn" type="button">Назад</button>
                  <div class="captain-admin-card-head">
                    <div>
                      <span id="captainCardViewKicker">Карточка</span>
                      <h3 id="captainCardViewTitle">Участник</h3>
                    </div>
                    <strong id="captainCardViewAmount">€0.00</strong>
                  </div>
                </header>

                <div id="captainAdminWork" class="captain-card-work hidden">
                  <section class="captain-session-panel captain-session-panel-primary">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>Администратор</span>
                        <h4>Мой журнал</h4>
                      </div>
                    </div>
                    <div id="captainCurrentSummary" class="captain-current-summary">
                      <p class="soft-note" data-i18n="captain.loading">Загружаю данные FinDesk…</p>
                    </div>
                  </section>

                  <section class="captain-session-panel">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>Работа с сотрудниками</span>
                        <h4>Передать деньги сотруднику</h4>
                      </div>
                    </div>
                    <div id="captainIssuePanel" class="findesk-issue-panel">
                      <label class="form-label" for="captainIssueMemberSelect">Сотрудник</label>
                      <select id="captainIssueMemberSelect" class="ql-input">
                        <option value="">Выберите сотрудника</option>
                      </select>
                      <label class="form-label" for="captainIssueTitle">Назначение</label>
                      <input id="captainIssueTitle" class="ql-input" type="text" placeholder="Например: закупка, топливо, дорога">
                      <label class="form-label" for="captainIssueAmount">Сумма</label>
                      <input id="captainIssueAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">
                      <div class="captain-current-actions">
                        <button id="captainIssueCreateBtn" class="primary-btn" type="button">Передать деньги</button>
                        <button class="ghost-btn" type="button" data-captain-open-quick="editor">Открыть быстрые записи</button>
                      </div>
                    </div>
                  </section>

                  <section class="captain-session-panel">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>Сотрудники</span>
                        <h4>Кому выданы деньги и кто работает в группе</h4>
                      </div>
                    </div>
                    <div id="captainMemberList" class="captain-session-stack">
                      <p class="soft-note">Список сотрудников появится после загрузки группы.</p>
                    </div>
                  </section>

                  <section class="captain-session-panel">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>На проверке</span>
                        <h4>Готовые журналы сотрудников</h4>
                      </div>
                    </div>
                    <div id="captainAdminInbox" class="captain-session-stack">
                      <p class="soft-note">Входящих отчетов пока нет.</p>
                    </div>
                  </section>

                  <section class="captain-session-panel">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>В сборке</span>
                        <h4>Прикрепленные журналы и выдачи</h4>
                      </div>
                    </div>
                    <div id="captainReportPack" class="captain-child-report-list">
                      <p class="soft-note">Принятые карточки и подотчеты появятся здесь после проверки.</p>
                    </div>
                  </section>

                  <section class="captain-session-panel">
                    <div class="captain-session-panel-head">
                      <div>
                        <span>Итог</span>
                        <h4>Сборка общего отчета</h4>
                      </div>
                    </div>
                    <div id="captainAssemblySummary" class="captain-assembly-summary">
                      <p class="soft-note">Собираю статус общего отчета…</p>
                    </div>
                    <div class="captain-admin-action-bar">
                      <button class="primary-btn" type="button" data-captain-finalize-report>Сохранить общий отчет</button>
                      <button class="ghost-btn" type="button" data-captain-print>Печать / PDF</button>
                      <button class="ghost-btn" type="button" data-captain-send-report>Отправить</button>
                      <button class="ghost-btn" type="button" data-captain-open-included>Состав</button>
                    </div>
                    <p id="captainAssemblyStatus" class="soft-note"></p>
                  </section>

                  <details class="findesk-details-fold">
                    <summary>Детали</summary>
                    <div class="findesk-details-fold-body">
                      <div class="captain-invite-panel">
                        <label class="form-label" for="captainInviteEmail">Пригласить сотрудника</label>
                        <input id="captainInviteEmail" class="ql-input" type="email" placeholder="email@example.com">
                        <label class="form-label" for="captainInviteAccessLevel">Роль</label>
                        <select id="captainInviteAccessLevel" class="ql-input">
                          <option value="base">Сотрудник</option>
                          <option value="manager">Проверка отчетов</option>
                          <option value="advanced">Полный доступ</option>
                        </select>
                        <button id="captainCreateInviteBtn" class="ghost-btn small-btn" type="button">Создать приглашение</button>
                        <input id="captainInviteUrl" class="ql-input hidden" type="text" readonly>
                      </div>
                      <div id="captainArchivePack" class="captain-session-stack">
                        <p class="soft-note">Архив выполненных записей пока пуст.</p>
                      </div>
                      <div class="captain-detail-actions">
                        <button id="captainJournalExportBtn" class="ghost-btn small-btn hidden" type="button">Журнал</button>
                        <button class="ghost-btn small-btn" type="button" data-captain-open-archive>Открыть архив</button>
                        <button class="ghost-btn small-btn" type="button" data-module-tab="money" data-module-screen="advances">Все подотчеты</button>
                      </div>
                    </div>
                  </details>
                </div>

                <div id="captainParticipantWork" class="captain-card-work"></div>
              </section>
            </section>
          </div>

          <div id="moduleMoney" class="ql-module hidden" data-module="money" data-advanced-current-screen="overview">
            <section class="advance-card advanced-card glass-soft">
              <div class="advanced-hero">
                <div>
                  <span class="captain-kicker">Деньги на руках</span>
                  <h2>Касса и подотчеты</h2>
                  <p class="soft-note tight-note">Физические деньги у администратора и сотрудников, выдача под отчет и контроль наличных остатков.</p>
                </div>
                <div class="advanced-hero-actions">
                  <button class="primary-btn" type="button" data-mode-open="captain">Проверка</button>
                  <button class="ghost-btn" type="button" data-mode-open="reports">Сводка</button>
                  <button class="ghost-btn" type="button" data-mode-open="groups">Сотрудники</button>
                </div>
              </div>

              <div class="advance-toolbar">
                <label class="form-label" for="advanceGroupSelect">Группа</label>
                <select id="advanceGroupSelect" class="ql-input">
                  <option value="">Выберите группу</option>
                </select>
                <span id="advanceCount" class="advanced-count-pill">0 строк</span>
                <p id="advanceStatus" class="soft-note"></p>
              </div>

              <div id="advancedPositionStrip" class="advanced-position-strip">
                <div class="advanced-position-card before">
                  <span>У меня</span>
                  <b id="advancedBeforeAmount">€0.00</b>
                  <small id="advancedBeforeMeta">фактическая касса</small>
                </div>
                <div class="advanced-position-arrow">+</div>
                <div class="advanced-position-card after">
                  <span>У сотрудников</span>
                  <b id="advancedAfterAmount">€0.00</b>
                  <small id="advancedAfterMeta">открытые подотчеты</small>
                </div>
                <div class="advanced-position-card movement">
                  <span>Физически всего</span>
                  <b id="advancedMovementAmount">€0.00</b>
                  <small id="advancedPeriodMeta">у меня + у сотрудников</small>
                </div>
              </div>

              <nav class="advanced-screen-nav" aria-label="Advanced screens">
                <button class="active" type="button" data-advanced-screen="overview">Деньги</button>
                <button type="button" data-advanced-screen="advances">Подотчеты</button>
                <button type="button" data-advanced-screen="ai">AI-анализ</button>
                <button type="button" data-advanced-screen="audit">AI-аудит</button>
              </nav>

              <div id="advancedKpiGrid" class="advanced-kpi-grid is-active" data-advanced-screen-panel="overview">
                <div><span>У меня фактически</span><b>€0.00</b><small>доступно сейчас</small></div>
                <div><span>У сотрудников</span><b>€0.00</b><small>открытые подотчеты</small></div>
                <div><span>Физически всего</span><b>€0.00</b><small>контроль наличных</small></div>
                <div><span>На проверке</span><b>€0.00</b><small>ожидает решения</small></div>
              </div>

              <div class="advanced-command-grid is-active" data-advanced-screen-panel="overview">
                <div id="advancedReceivePanel" class="advanced-receive-panel">
                  <div class="advance-panel-head">
                    <h3>Полученные средства</h3>
                    <span>в основной учет</span>
                  </div>
                  <input id="advancedReceiveSource" class="ql-input" type="text" placeholder="От кого / основание">
                  <div class="advanced-inline-money">
                    <input id="advancedReceiveAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">
                    <select id="advancedReceiveMoneyType" class="ql-input">
                      <option value="cash">Наличные</option>
                      <option value="noncash">Безнал</option>
                    </select>
                  </div>
                  <input id="advancedReceiveNote" class="ql-input" type="text" placeholder="Комментарий">
                  <button id="advancedReceiveCreateBtn" class="primary-btn wide-btn" type="button">Добавить приход</button>
                  <p id="advancedReceiveStatus" class="soft-note"></p>
                  <div id="advancedReceivedFundsList" class="advanced-received-list">
                    <p class="soft-note">Внесенные средства появятся здесь.</p>
                  </div>
                </div>

                <div id="advanceIssuePanel" class="advance-issue-panel hidden">
                  <div class="advance-panel-head">
                    <h3>Выдать деньги</h3>
                    <span>под отчет сотруднику</span>
                  </div>
                  <select id="advanceMemberSelect" class="ql-input">
                    <option value="">Выберите сотрудника</option>
                  </select>
                  <input id="advanceTitle" class="ql-input" type="text" placeholder="Назначение / поездка / отчет">
                  <input id="advanceAmount" class="ql-input" type="text" inputmode="decimal" placeholder="0.00">
                  <button id="advanceCreateBtn" class="primary-btn wide-btn" type="button">Выдать под отчет</button>
                </div>
              </div>

              <div class="advanced-board-grid">
                <section class="advanced-panel advanced-board-main" data-advanced-screen-panel="advances">
                  <div class="advance-panel-head">
                    <div>
                      <h3>Деньги под отчет</h3>
                      <p class="soft-note tight-note">Красная строка живет здесь, пока отчет не проверен и не раскрыт в реальные расходы.</p>
                    </div>
                  </div>

                  <div id="advanceSummary" class="advance-summary">
                    <div><span>Открыто выдано</span><b>€0.00</b></div>
                    <div><span>В открытых расходах</span><b>€0.00</b></div>
                    <div><span>На руках</span><b>€0.00</b></div>
                    <div><span>Закрыто</span><b>€0.00</b></div>
                  </div>

                  <div id="advancedPipeline" class="advanced-pipeline">
                    <p class="soft-note">Пайплайн появится после загрузки группы.</p>
                  </div>

                  <div id="advanceList" class="advance-list">
                    <p class="soft-note">Выберите группу, чтобы увидеть деньги под отчет.</p>
                  </div>
                </section>

                <aside class="advanced-side-stack">
                  <section class="advanced-panel advanced-ai-panel" data-advanced-screen-panel="ai">
                    <div class="advance-panel-head">
                      <div>
                        <h3>AI-анализ</h3>
                        <p class="soft-note tight-note">Структурирует данные твоей учетной записи: сводка, риски, что проверить и как собрать отчет.</p>
                      </div>
                    </div>
                    <div class="advanced-ai-controls">
                      <select id="advancedAiPeriod" class="ql-input">
                        <option value="month">Этот месяц</option>
                        <option value="today">Сегодня</option>
                      </select>
                      <button id="advancedAiRunBtn" class="primary-btn wide-btn" type="button">AI-анализ</button>
                    </div>
                    <div id="advancedAiOutput" class="advanced-ai-output">
                      <p class="soft-note">Нажмите “AI-анализ”, чтобы получить структуру отчета и список действий.</p>
                    </div>
                  </section>

                  <section class="advanced-panel is-active" data-advanced-screen-panel="overview">
                    <div class="advance-panel-head">
                      <h3>Правила контроля</h3>
                      <span>активно</span>
                    </div>
                    <div id="advancedRulesPanel" class="advanced-rules-panel"></div>
                  </section>

                  <section class="advanced-panel" data-advanced-screen-panel="audit">
                    <div class="advance-panel-head">
                      <h3>Аудит</h3>
                      <span>последние действия</span>
                    </div>
                    <div id="advancedAuditPanel" class="advanced-audit-panel">
                      <p class="soft-note">История появится после загрузки группы.</p>
                    </div>
                  </section>

                  <section class="advanced-panel is-active" data-advanced-screen-panel="overview">
                    <div class="advance-panel-head">
                      <h3>Интеграции</h3>
                      <span>контроль хранения</span>
                    </div>
                    <div id="advancedIntegrationPanel" class="advanced-integration-panel"></div>
                  </section>
                </aside>
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
            <h2>Сотрудники и группы</h2>
            <span id="groupCount">0 групп</span>
          </div>

          <div class="group-create">
            <input id="groupName" class="ql-input" type="text" placeholder="Название группы или проекта">
            <button id="createGroupBtn" class="primary-btn wide-btn" type="button">Создать группу</button>
            <p id="groupMessage" class="soft-note"></p>
          </div>

          <div id="groupList" class="group-list">
            <p class="soft-note">Групп пока нет.</p>
          </div>

          <div id="groupDetails" class="group-details hidden">
            <div class="group-title-row">
              <h2 id="activeGroupName">Группа</h2>
              <button id="renameGroupBtn" class="ghost-btn" type="button">Переименовать</button>
              <button id="deleteActiveGroupBtn" class="ghost-btn danger-soft-btn hidden" type="button">Удалить группу</button>
            </div>

            <div class="invite-box">
              <div class="feed-head compact-head">
                <h3>Приглашение</h3>
                <span>доступ сотрудника</span>
              </div>
              <input id="inviteEmail" class="ql-input" type="email" placeholder="Email сотрудника (необязательно)">
              <select id="inviteAccessLevel" class="ql-input">
                <option value="base">Сотрудник · фиксация и самоконтроль</option>
                <option value="manager">Менеджер · проверка отчетов</option>
                <option value="advanced">Администратор · деньги и роли</option>
              </select>
              <button id="createInviteBtn" class="primary-btn wide-btn" type="button">Создать ссылку</button>

              <div id="inviteActions" class="invite-actions hidden">
                <input id="inviteUrl" class="ql-input" type="text" readonly>

                <div class="share-grid">
                  <a id="shareEmail" class="share-btn" href="#" target="_blank" rel="noopener">Email</a>
                  <a id="shareWhatsapp" class="share-btn" href="#" target="_blank" rel="noopener">WhatsApp</a>
                  <a id="shareViber" class="share-btn" href="#" target="_blank" rel="noopener">Viber</a>
                  <a id="shareTelegram" class="share-btn" href="#" target="_blank" rel="noopener">Telegram</a>
                  <button id="copyInviteBtn" class="share-btn" type="button">Копировать</button>
                  <button id="clearInviteActionsBtn" class="share-btn share-close-btn" type="button">Закрыть</button>
                </div>
              </div>
            </div>

            <div class="messages-box">
              <div class="feed-head">
                <h2>Сообщения группы</h2>
                <span id="messageCount">0</span>
              </div>

              <div id="messageList" class="message-list">
                <p class="soft-note">Сообщений пока нет.</p>
              </div>

              <div class="message-compose">
                <input id="messageText" class="ql-input" type="text" placeholder="Сообщение группе…">
                <button id="sendMessageBtn" class="primary-btn" type="button">Отправить</button>
              </div>

              <p id="messageStatus" class="soft-note"></p>
            </div>

            <div class="members-box">
              <div class="feed-head">
                <h2>Участники</h2>
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
      <h3>Детали записи</h3>

      <div id="ledgerDetailContent" class="ledger-detail-content">
        <p class="soft-note">Загружаю запись…</p>
      </div>

      <div class="ledger-detail-actions">
        <button class="ghost-btn wide-btn" type="button" data-close-ledger-detail>Закрыть</button>
      </div>
    </div>
  </div>

  <div id="advancedExcelPreviewModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass captain-review-modal-card advanced-excel-modal-card">
      <button class="modal-close" type="button" data-close-advanced-excel-preview>×</button>
      <div class="captain-review-modal-head">
        <div>
          <span>Экспорт текущего периода</span>
          <h3>Предпросмотр текущего периода</h3>
        </div>
        <b id="advancedExcelPreviewAmount">€0.00</b>
      </div>
      <div id="advancedExcelPreviewContent" class="captain-review-modal-records">
        <p class="soft-note">Готовлю текущий период…</p>
      </div>
      <div class="captain-review-modal-actions">
        <button id="advancedExcelDownloadBtn" class="primary-btn wide-btn" type="button">Скачать Excel текущего периода</button>
        <button id="advancedGoogleSheetBtn" class="ghost-btn wide-btn" type="button">Открыть текущий период в Google Таблицах</button>
        <button class="ghost-btn wide-btn" type="button" data-close-advanced-excel-preview>Закрыть</button>
      </div>
    </div>
  </div>

  <div id="receiptScannerModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass receipt-scanner-modal-card">
      <button class="modal-close" type="button" data-close-receipt-scanner>×</button>
      <div class="receipt-scanner-head">
        <div>
          <span>Доказательство</span>
          <h3>Скан чека в PDF</h3>
        </div>
        <button id="receiptScannerPickBtn" class="ghost-btn small-btn" type="button">Фото</button>
      </div>

      <input id="receiptScannerSourceInput" class="hidden" type="file" accept="image/*" capture="environment">

      <div id="receiptScannerStage" class="receipt-scanner-stage is-empty">
        <canvas id="receiptScannerCanvas" width="320" height="420"></canvas>
        <div id="receiptScannerOverlay" class="receipt-scanner-overlay" aria-hidden="true">
          <button class="receipt-crop-handle tl" type="button" data-receipt-corner="tl" aria-label="Верхний левый угол"></button>
          <button class="receipt-crop-handle tr" type="button" data-receipt-corner="tr" aria-label="Верхний правый угол"></button>
          <button class="receipt-crop-handle br" type="button" data-receipt-corner="br" aria-label="Нижний правый угол"></button>
          <button class="receipt-crop-handle bl" type="button" data-receipt-corner="bl" aria-label="Нижний левый угол"></button>
        </div>
        <p id="receiptScannerEmpty" class="soft-note">Сфотографируйте чек или выберите фото. Рамку можно поправить пальцем.</p>
      </div>

      <div class="receipt-scanner-controls">
        <label class="receipt-scan-slider" for="receiptScannerCleanLevel">
          <span>Очистка</span>
          <input id="receiptScannerCleanLevel" type="range" min="0" max="100" value="42">
        </label>
        <label class="receipt-scan-toggle">
          <input id="receiptScannerMono" type="checkbox" checked>
          <span>Черно-белый чек</span>
        </label>
      </div>

      <p id="receiptScannerStatus" class="soft-note compact-note">Камера откроется там, где браузер/PWA разрешает capture. Иначе выберите фото из файлов.</p>

      <div class="receipt-scanner-actions">
        <button id="receiptScannerRetakeBtn" class="ghost-btn wide-btn" type="button">Переснять</button>
        <button class="ghost-btn wide-btn" type="button" data-close-receipt-scanner>Закрыть</button>
        <button id="receiptScannerAttachBtn" class="primary-btn wide-btn" type="button">Прикрепить PDF</button>
      </div>
    </div>
  </div>

  <div id="otrReviewModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass otr-review-modal-card">
      <button class="modal-close" type="button" data-close-otr-review>×</button>
      <h3>Проверка строки живого отчета</h3>
      <p class="soft-note">Эта строка еще не включена в учет. Проверьте ее сейчас или оставьте на потом.</p>

      <input id="otrReviewId" type="hidden">

      <label class="form-label" for="otrReviewType">Тип</label>
      <select id="otrReviewType" class="ql-input">
        <option value="cash_in">Приход наличных</option>
        <option value="cash_out">Расход наличных</option>
        <option value="noncash_out">Расход с карты / безнал</option>
      </select>

      <label class="form-label" for="otrReviewAmount">Сумма</label>
      <input id="otrReviewAmount" class="ql-input" type="text" inputmode="decimal" placeholder="Сумма">

      <label class="form-label" for="otrReviewDescription">Примечание</label>
      <input id="otrReviewDescription" class="ql-input" type="text" placeholder="Примечание / описание">

      <div class="otr-attachment-panel">
        <div class="otr-attachment-head">
          <h4>Вложения</h4>
          <span id="otrReviewAttachment">Вложений нет.</span>
        </div>

        <div id="otrReviewFiles" class="otr-review-files">
          <p class="soft-note">Вложений нет.</p>
        </div>

        <label class="file-picker otr-review-upload">
          <input id="otrReviewFileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt">
          <span>Добавить / заменить вложение</span>
          <small id="otrReviewFileName">Файл не выбран</small>
        </label>

        <button id="uploadOtrReviewFileBtn" class="ghost-btn wide-btn otr-upload-compact-btn" type="button">Загрузить выбранный файл</button>
      </div>

      <div class="otr-convert-panel">
        <h4>Перенести в журнал учета</h4>
        <p class="soft-note compact-note">Выберите место учета. Пустой раздел означает “Живой отчет”.</p>

        <div class="segmented" role="group" aria-label="Куда переносить">
          <button id="otrConvertScopePersonal" class="seg active" type="button" data-otr-convert-scope="personal">Личный</button>
          <button id="otrConvertScopeGroup" class="seg" type="button" data-otr-convert-scope="group">Группа</button>
        </div>

        <label class="form-label hidden" id="otrConvertGroupLabel" for="otrConvertGroup">Группа</label>
        <select id="otrConvertGroup" class="ql-input hidden">
          <option value="">Выберите группу</option>
        </select>

        <label class="form-label" for="otrConvertSection">Раздел учета</label>
        <select id="otrConvertSection" class="ql-input">
          <option value="">Живой отчет по умолчанию</option>
        </select>

        <div class="business-grid-2">
          <label class="bd-field">
            <span>Тип записи</span>
            <select id="otrConvertEntryType" class="ql-input">
              <option value="income">Приход</option>
              <option value="expense">Расход</option>
            </select>
          </label>

          <label class="bd-field">
            <span>Тип денег</span>
            <select id="otrConvertMoneyType" class="ql-input">
              <option value="cash">Наличные</option>
              <option value="noncash">Безнал</option>
            </select>
          </label>
        </div>

        <label class="form-label" for="otrConvertPurpose">Назначение</label>
        <input id="otrConvertPurpose" class="ql-input" type="text" placeholder="Назначение для журнала">

        <button id="convertOtrToLedgerBtn" class="primary-btn wide-btn" type="button">Перенести в журнал</button>
      </div>

      <div class="otr-review-actions">
        <button id="saveOtrReviewBtn" class="ghost-btn wide-btn" type="button">Сохранить правки</button>
        <button id="archiveOtrBtn" class="ghost-btn wide-btn danger-soft" type="button">Убрать в архив</button>
        <button class="ghost-btn wide-btn" type="button" data-close-otr-review>Оставить на потом</button>
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
        <button class="ghost-btn wide-btn" type="button" data-close-otr-session>Закрыть</button>
      </div>
      <p id="otrSessionModalStatus" class="soft-note"></p>
    </div>
  </div>

  <div id="otrCardModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass otr-card-modal-card">
      <button class="modal-close" type="button" data-close-otr-card>×</button>
      <div class="otr-card-modal-head">
        <div>
          <span id="otrCardKicker">Карточка отчета</span>
          <h3 id="otrCardTitle">Живой отчет</h3>
        </div>
      </div>
      <div id="otrCardMetrics" class="otr-card-metrics">
        <div><span>Было</span><b>€0.00</b></div>
        <div><span>Остаток</span><b>€0.00</b></div>
      </div>
      <p id="otrCardMeta" class="soft-note"></p>
      <div id="otrCardRecords" class="otr-card-records">
        <p class="soft-note">Загрузка карточки…</p>
      </div>
      <div class="otr-card-modal-actions">
        <button id="otrCardUnsubmitBtn" class="primary-btn wide-btn" type="button">Перейти к исправлению</button>
        <button id="otrCardUnincludeBtn" class="primary-btn wide-btn hidden" type="button">Вернуть в FinDesk</button>
      </div>
      <p id="otrCardStatus" class="soft-note"></p>
    </div>
  </div>

  <div id="proofViewerModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass proof-viewer-card">
      <button class="modal-close" type="button" data-close-proof-viewer>×</button>
      <div class="proof-viewer-head">
        <div>
          <span>Доказательство</span>
          <h3 id="proofViewerTitle">Файл</h3>
        </div>
        <a id="proofViewerOpenLink" class="ghost-btn small-btn" href="#" target="_blank" rel="noopener">Открыть</a>
      </div>
      <div id="proofViewerBody" class="proof-viewer-body">
        <p class="soft-note">Загрузка файла…</p>
      </div>
      <p id="proofViewerMeta" class="soft-note compact-note"></p>
    </div>
  </div>

  <div id="captainReviewModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass captain-review-modal-card">
      <button class="modal-close" type="button" data-close-captain-review>×</button>
      <div class="captain-review-modal-head">
        <div>
          <span id="captainReviewKicker">FinDesk</span>
          <h3 id="captainReviewTitle">Отчет исполнителя</h3>
        </div>
        <b id="captainReviewAmount">€0.00</b>
      </div>
      <p id="captainReviewMeta" class="soft-note"></p>
      <div id="captainReviewRecords" class="captain-review-modal-records">
        <p class="soft-note">Загрузка отчета…</p>
      </div>
      <div class="captain-review-modal-actions">
        <button id="captainReviewAcceptBtn" class="primary-btn wide-btn" type="button">Включить в отчет</button>
        <button id="captainReviewReturnBtn" class="ghost-btn wide-btn danger-soft-btn" type="button">Вернуть на правку</button>
        <button class="ghost-btn wide-btn" type="button" data-close-captain-review>Закрыть</button>
      </div>
      <p id="captainReviewStatus" class="soft-note"></p>
    </div>
  </div>

  <div id="captainIncludedModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass captain-review-modal-card">
      <button class="modal-close" type="button" data-close-captain-included>×</button>
      <div class="captain-review-modal-head">
        <div>
          <span>FinDesk</span>
          <h3>Включено в отчет</h3>
        </div>
        <b id="captainIncludedCount">0</b>
      </div>
      <p class="soft-note">Рабочий пакет для проверки, печати и подготовки итогового отчета. Архив живого журнала его не меняет.</p>
      <div id="captainIncludedList" class="captain-review-modal-records">
        <p class="soft-note">Пока нет включенных карточек.</p>
      </div>
      <div class="captain-review-modal-actions">
        <button class="primary-btn wide-btn" type="button" data-captain-open-report>Открыть отчеты</button>
        <button class="ghost-btn wide-btn" type="button" data-close-captain-included>Закрыть</button>
      </div>
    </div>
  </div>

  <div id="captainArchiveModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass captain-review-modal-card">
      <button class="modal-close" type="button" data-close-captain-archive>×</button>
      <div class="captain-review-modal-head">
        <div>
          <span>Общий архив</span>
          <h3>Живые отчеты группы</h3>
        </div>
        <b id="captainArchiveCount">0</b>
      </div>
      <p class="soft-note">Все живые отчеты группы разложены по сотрудникам: черновики, сданные, включенные и архивные карточки.</p>
      <div id="captainArchiveList" class="captain-review-modal-records">
        <p class="soft-note">Живые отчеты группы пока не загружены.</p>
      </div>
      <div class="captain-review-modal-actions">
        <button id="captainArchiveJournalExportBtn" class="ghost-btn wide-btn" type="button">Скачать журнал</button>
        <button class="ghost-btn wide-btn" type="button" data-close-captain-archive>Закрыть</button>
      </div>
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

  <script src="/assets/i18n.js?v=20260607-cash-professional-report-routes38"></script>
  <script src="/assets/donate.js?v=20260503-11"></script>
  <script src="/assets/notifications.js?v=20260503-11"></script>
  <script src="/assets/app.js?v=20260607-cash-professional-report-routes38"></script>
</body>
</html>
