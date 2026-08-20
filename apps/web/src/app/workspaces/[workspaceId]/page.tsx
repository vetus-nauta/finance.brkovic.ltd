import { notFound } from "next/navigation";
import Link from "next/link";
import { Fragment } from "react";
import {
  convertSmithProposalsToEntries,
  createOperationalEntry,
  deleteQuickNote,
  saveQuickNoteDraft,
  submitQuickNoteToSmith
} from "./actions";
import { QuickNoteComposer } from "./QuickNoteComposer";
import { SyncedLedgerTable } from "./SyncedLedgerTable";
import { calculateQuickNoteTotal } from "@/lib/quick-note-totals";
import { smithCategoryLabel, smithCategoryOptions } from "@/lib/smith-categories";
import { getWorkspaceDetails, roleLabels, workspacePath } from "@/lib/workspace-data";

type WorkspacePageProps = {
  params: Promise<{
    workspaceId: string;
  }>;
  searchParams?: Promise<{
    account?: string;
    entry?: string;
    mode?: string;
    newNote?: string;
    note?: string;
    notesView?: string;
    status?: string;
  }>;
};

type WorkspaceMode = "ledger" | "notes" | "reports";

function entryStatusText(status?: string) {
  switch (status) {
    case "saved":
      return "Запись сохранена.";
    case "amount":
      return "Начните запись с суммы: например, -350 продукты или +1000 от судовладельца.";
    case "review":
      return "Запись сохранена на проверку и пока не влияет на деньги.";
    case "card-income":
      return "Поступление на карту нужно проводить через импорт, корректировку или проверку Смита.";
    case "missing":
      return "Заполните дату и запись.";
    case "account":
      return "Счет не найден.";
    case "auth":
      return "Сессия не найдена. Войдите заново.";
    case "save":
      return "Не удалось сохранить запись.";
    default:
      return "";
  }
}

function normalizeMode(mode?: string): WorkspaceMode {
  return mode === "notes" || mode === "reports" ? mode : "ledger";
}

function workspaceStatusText(status?: string) {
  switch (status) {
    case "note-saved":
      return "Заметка сохранена в черновиках.";
    case "note-submitted":
      return "Заметка отправлена в журнал на разбор.";
    case "note-ready":
      return "Пакет подготовлен к переносу в журнал.";
    case "note-converted":
      return "Заметка перенесена в оперативный журнал.";
    case "note-deleted":
      return "Заметка убрана из истории.";
    case "note-empty":
      return "Напишите заметку перед сохранением.";
    case "note-date":
      return "Выберите дату для переноса заметки.";
    case "note-missing":
      return "Заметка не выбрана.";
    case "note-select-lines":
      return "Выберите хотя бы одну строку для переноса.";
    case "note-save":
    case "note-submit":
    case "note-convert":
    case "note-delete":
      return "Не удалось выполнить действие с заметкой.";
    case "auth":
      return "Сессия не найдена. Войдите заново.";
    case "workspace":
      return "Рабочее пространство не найдено.";
    default:
      return "";
  }
}

function isWorkspaceStatusSuccess(status?: string) {
  return (
    status === "note-saved" ||
    status === "note-submitted" ||
    status === "note-ready" ||
    status === "note-converted" ||
    status === "note-deleted"
  );
}

function quickNoteStatusText(status: string) {
  switch (status) {
    case "draft":
      return "Черновик";
    case "submitted_to_smith":
      return "На разборе";
    case "converted":
      return "Перенесено";
    default:
      return status;
  }
}

function reportStatusText(status: string) {
  switch (status) {
    case "draft":
      return "Черновик";
    case "created":
      return "Создан";
    case "sent":
      return "Отправлен";
    case "accepted":
      return "Принят";
    case "returned_for_revision":
      return "На доработке";
    default:
      return status;
  }
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(value));
}

function formatDateOnly(value: string) {
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit"
  }).format(new Date(value));
}

function formatNoteMonth(value: string) {
  const date = new Date(value);
  const month = new Intl.DateTimeFormat("ru-RU", { month: "long" }).format(date);
  return `${month.charAt(0).toUpperCase()}${month.slice(1)} ${date.getFullYear()}`;
}

function noteMonthKey(value: string) {
  return value.slice(0, 7);
}

function formatMoney(value: number, currency: string) {
  return new Intl.NumberFormat("ru-RU", {
    currency,
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
    style: "currency"
  }).format(value);
}

function formatSignedMoney(value: number, currency: string) {
  return new Intl.NumberFormat("ru-RU", {
    currency,
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
    signDisplay: "exceptZero",
    style: "currency"
  }).format(value);
}

function directionText(direction: string) {
  switch (direction) {
    case "income":
      return "Приход";
    case "expense":
      return "Расход";
    default:
      return "Учет";
  }
}

function proposalSignalText(parserReason: string | null, duplicateStatus: string) {
  if (duplicateStatus === "possible_duplicate") {
    return "Похожая строка уже есть";
  }

  if (parserReason === "missing_sign") {
    return "Нет знака";
  }

  if (parserReason === "amount_missing") {
    return "Нет суммы";
  }

  return "Готово";
}

export default async function WorkspacePage({ params, searchParams }: WorkspacePageProps) {
  const { workspaceId } = await params;
  const query = searchParams ? await searchParams : {};
  const workspace = await getWorkspaceDetails(workspaceId, query.account);
  const mode = normalizeMode(query.mode);

  if (!workspace) {
    notFound();
  }

  const entryAction = createOperationalEntry.bind(null, workspace.id);
  const saveNoteAction = saveQuickNoteDraft.bind(null, workspace.id);
  const submitNoteAction = submitQuickNoteToSmith.bind(null, workspace.id);
  const convertProposalAction = convertSmithProposalsToEntries.bind(null, workspace.id);
  const deleteNoteAction = deleteQuickNote.bind(null, workspace.id);
  const today = new Date().toISOString().slice(0, 10);
  const statusText = entryStatusText(query.entry);
  const modeStatusText = workspaceStatusText(query.status);
  const workspaceBasePath = workspacePath(workspace.id);
  const notesView = query.notesView === "history" || query.notesView === "transfer" ? query.notesView : "current";
  const composeNewQuickNote = query.newNote === "1";
  const selectedQuickNote =
    composeNewQuickNote
      ? null
      : workspace.quickNotes.find((note) => note.id === query.note) ??
        workspace.quickNotes.find((note) => note.status === "draft") ??
        null;
  const editableQuickNote =
    selectedQuickNote?.status === "draft" || selectedQuickNote?.status === "submitted_to_smith" ? selectedQuickNote : null;
  const openedConvertedQuickNote = selectedQuickNote?.status === "converted" ? selectedQuickNote : null;
  const historyQuickNotes = workspace.quickNotes.filter((note) => note.id !== editableQuickNote?.id);
  const currentQuickNoteHref = editableQuickNote
    ? `${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}&note=${encodeURIComponent(editableQuickNote.id)}`
    : `${workspaceBasePath}?mode=notes&newNote=1&account=${encodeURIComponent(workspace.activeAccountCode)}`;
  const pendingProposals = selectedQuickNote?.proposals.filter((proposal) => proposal.status === "pending") ?? [];
  const noteHasPendingTransfer = pendingProposals.length > 0 && selectedQuickNote?.status === "submitted_to_smith";
  const showNoteTransfer = mode === "notes" && notesView === "transfer" && noteHasPendingTransfer;
  const noteScreenOpen = mode === "notes" && (composeNewQuickNote || Boolean(query.note) || showNoteTransfer);
  const activeNavigationLabel =
    mode === "notes"
      ? "Заметки"
      : mode === "reports"
        ? "Отчеты"
        : workspace.accounts.find((account) => account.code === workspace.activeAccountCode)?.label ?? "Журнал";
  const summarySections = [
    {
      title: "Категории",
      hint: "Рабочие доходы и расходы",
      rows: workspace.categorySummary.operational
    },
    {
      title: "Учетные блоки",
      hint: "Долги, подотчет и деньги на руках",
      rows: workspace.categorySummary.accountingBlocks
    },
    {
      title: "Перемещения денег",
      hint: "Не прибыль и не расход",
      rows: workspace.categorySummary.moneyMovements
    },
    {
      title: "Без категории",
      hint: "Нужно разобрать вручную",
      rows: workspace.categorySummary.uncategorized
    }
  ].filter((section) => section.rows.length > 0);

  let previousQuickNoteMonth = "";

  return (
    <main className="page compact-page">
      <section className="section-head">
        <div>
          <p className="eyebrow">{workspace.type === "yacht" ? "Яхта" : "Пространство"}</p>
          <h1>{workspace.name}</h1>
          <p>
            {roleLabels[workspace.role] ?? workspace.role} · {workspace.currency} ·{" "}
            {workspace.status === "active" ? "активно" : workspace.status}
          </p>
        </div>
        <div className="workspace-metrics" aria-label="Состояние пространства">
          <span>
            <small>Записей</small>
            <strong>{workspace.transactionCount}</strong>
          </span>
          <span>
            <small>На проверке</small>
            <strong>{workspace.reviewCount}</strong>
          </span>
        </div>
      </section>

      <section className="workspace-shell">
        <details className="mobile-section-menu">
          <summary>
            <span>Раздел</span>
            <strong>{activeNavigationLabel}</strong>
          </summary>
          <nav className="mobile-section-menu-panel" aria-label="Мобильная навигация пространства">
            {workspace.accounts.length > 0 ? (
              workspace.accounts.map((account) => (
                <Link
                  className={mode === "ledger" && workspace.activeAccountCode === account.code ? "active" : undefined}
                  aria-current={mode === "ledger" && workspace.activeAccountCode === account.code ? "page" : undefined}
                  href={`${workspaceBasePath}?account=${encodeURIComponent(account.code)}`}
                  key={account.id}
                >
                  {account.label}
                </Link>
              ))
            ) : (
              <>
                <span className="active">Кеш</span>
                <span>Карта</span>
              </>
            )}
            <Link
              className={mode === "notes" ? "active" : undefined}
              aria-current={mode === "notes" ? "page" : undefined}
              href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}`}
            >
              Заметки
            </Link>
            <Link
              className={mode === "reports" ? "active" : undefined}
              aria-current={mode === "reports" ? "page" : undefined}
              href={`${workspaceBasePath}?mode=reports&account=${encodeURIComponent(workspace.activeAccountCode)}`}
            >
              Отчеты
            </Link>
          </nav>
        </details>
        <aside className="side-tabs" aria-label="Разделы рабочего пространства">
          {workspace.accounts.length > 0 ? (
            workspace.accounts.map((account) => (
              <Link
                className={mode === "ledger" && workspace.activeAccountCode === account.code ? "active" : undefined}
                aria-current={mode === "ledger" && workspace.activeAccountCode === account.code ? "page" : undefined}
                href={`${workspaceBasePath}?account=${encodeURIComponent(account.code)}`}
                key={account.id}
              >
                {account.label}
              </Link>
            ))
          ) : (
            <>
              <span className="active">
                Кеш
              </span>
              <span>Карта</span>
            </>
          )}
          <span className="side-tabs-divider" aria-hidden="true" />
          <Link
            className={mode === "notes" ? "active" : undefined}
            aria-current={mode === "notes" ? "page" : undefined}
            href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}`}
          >
            Заметки
          </Link>
          <Link
            className={mode === "reports" ? "active" : undefined}
            aria-current={mode === "reports" ? "page" : undefined}
            href={`${workspaceBasePath}?mode=reports&account=${encodeURIComponent(workspace.activeAccountCode)}`}
          >
            Отчеты
          </Link>
        </aside>
        {mode === "ledger" ? (
          <section className="operational-workspace" aria-label="Оперативный журнал и структурная проверка">
            <SyncedLedgerTable entries={workspace.entries} />
          </section>
        ) : null}
        {mode === "notes" ? (
          <section className="workspace-mode-panel notes-mode-panel" aria-label="Быстрые заметки">
            <section className={`notes-apple-workspace ${noteScreenOpen ? "is-note-open" : "is-note-list"}`}>
              <section className="notes-apple-list-panel" aria-label="Список заметок">
                <div className="notes-apple-head">
                  <div>
                    <h2>Заметки</h2>
                    <span>
                      {workspace.quickNotes.length > 0
                        ? `${workspace.quickNotes.length} заметки`
                        : "Быстрая запись перед журналом"}
                    </span>
                  </div>
                  <Link
                    className="notes-new-button"
                    href={`${workspaceBasePath}?mode=notes&newNote=1&account=${encodeURIComponent(workspace.activeAccountCode)}`}
                  >
                    Новая
                  </Link>
                </div>
                <div className="notes-apple-list">
                  <article className="note-card-row note-card-current-row">
                    <Link
                      className={[
                        "note-card note-card-current",
                        composeNewQuickNote || editableQuickNote ? "active" : ""
                      ].filter(Boolean).join(" ")}
                      href={currentQuickNoteHref}
                      aria-label="Открыть текущую заметку"
                    >
                      <span>Текущая</span>
                      <strong>
                        {formatSignedMoney(editableQuickNote ? calculateQuickNoteTotal(editableQuickNote.body) : 0, workspace.currency)}
                      </strong>
                    </Link>
                  </article>
                  {historyQuickNotes.length > 0 ? (
                    historyQuickNotes.map((note) => {
                      const noteTotal = calculateQuickNoteTotal(note.body);
                      const currentNoteMonth = noteMonthKey(note.createdAt);
                      const showMonthLabel = currentNoteMonth !== previousQuickNoteMonth;
                      previousQuickNoteMonth = currentNoteMonth;

                      return (
                        <Fragment key={note.id}>
                          {showMonthLabel ? <div className="note-month-separator">{formatNoteMonth(note.createdAt)}</div> : null}
                          <article className="note-card-row">
                            <Link
                              className={[
                                selectedQuickNote?.id === note.id ? "note-card active" : "note-card",
                                note.status === "submitted_to_smith" || note.status === "converted" ? "is-sent" : ""
                              ].filter(Boolean).join(" ")}
                              href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}&note=${encodeURIComponent(note.id)}`}
                              aria-label={`Открыть заметку от ${formatDateOnly(note.createdAt)}`}
                            >
                              <time dateTime={note.createdAt}>{formatDateOnly(note.createdAt)}</time>
                              <strong>{formatSignedMoney(noteTotal, workspace.currency)}</strong>
                            </Link>
                            {note.status !== "converted" ? (
                              <form className="note-delete-form" action={deleteNoteAction} aria-label="Удалить заметку">
                                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                                <input type="hidden" name="noteId" value={note.id} />
                                <button type="submit" aria-label="Удалить заметку">
                                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M10 11v6" />
                                    <path d="M14 11v6" />
                                    <path d="M5 7h14" />
                                    <path d="M8 7l1-3h6l1 3" />
                                    <path d="M6 7l1 14h10l1-14" />
                                  </svg>
                                </button>
                              </form>
                            ) : null}
                          </article>
                        </Fragment>
                      );
                    })
                  ) : workspace.quickNotes.length === 0 ? (
                    <div className="empty-state inline-empty">
                      <h2>Заметок пока нет</h2>
                      <p>Новая заметка откроется справа.</p>
                    </div>
                  ) : null}
                </div>
              </section>
              <section className="notes-apple-editor-panel" aria-label="Открытая заметка">
                <Link
                  className="notes-mobile-back"
                  href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}`}
                >
                  Назад к заметкам
                </Link>
                {modeStatusText ? (
                  <p className={isWorkspaceStatusSuccess(query.status) ? "form-note success" : "form-note error"}>
                    {modeStatusText}
                  </p>
                ) : null}
                {showNoteTransfer && selectedQuickNote ? (
                  <form className="notes-transfer-panel" action={convertProposalAction}>
                    <input type="hidden" name="account" value={workspace.activeAccountCode} />
                    <input type="hidden" name="noteId" value={selectedQuickNote.id} />
                    <div className="notes-transfer-head">
                      <div>
                        <h3>Отправка в журнал</h3>
                        <p>Проверьте строки пакета. Отмеченные строки попадут в оперативный журнал.</p>
                      </div>
                      <Link
                        href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}&note=${encodeURIComponent(selectedQuickNote.id)}`}
                      >
                        К заметке
                      </Link>
                    </div>
                    <div className="notes-source-card" aria-label="Исходная заметка">
                      <strong>Исходная заметка</strong>
                      <p>{selectedQuickNote.body}</p>
                    </div>
                    <div className="smith-proposal-list" aria-label="Строки для переноса">
                      {pendingProposals.map((proposal) => (
                        <label className="smith-proposal" key={proposal.id}>
                          <input
                            name="proposalId"
                            type="checkbox"
                            value={proposal.id}
                            defaultChecked={proposal.duplicateStatus !== "possible_duplicate"}
                          />
                          <span>
                            <strong>{proposal.rawText}</strong>
                            <small>
                              {proposal.duplicateStatus === "possible_duplicate"
                                ? "Похоже на дубль"
                                : `Категория: ${smithCategoryLabel(proposal.candidateCategoryCode)}`}
                            </small>
                          </span>
                          <select
                            aria-label={`Категория для строки ${proposal.lineNo}`}
                            defaultValue={proposal.candidateCategoryCode ?? "other"}
                            name={`categoryCode:${proposal.id}`}
                          >
                            {smithCategoryOptions.map((category) => (
                              <option key={category.code} value={category.code}>
                                {category.label}
                              </option>
                            ))}
                          </select>
                          <span
                            className={
                              proposal.duplicateStatus === "possible_duplicate" || proposal.reviewReason !== "accepted"
                                ? "status-pill attention"
                                : "status-pill"
                            }
                          >
                            {proposalSignalText(proposal.parserReason, proposal.duplicateStatus)}
                          </span>
                        </label>
                      ))}
                    </div>
                    <div className="mode-actions">
                      <button className="primary-action" type="submit">
                        Перенести в журнал
                      </button>
                    </div>
                  </form>
                ) : openedConvertedQuickNote ? (
                  <div className="notes-readonly-panel" aria-label="Перенесенная заметка">
                    <div className="notes-transfer-head">
                      <div>
                        <h3>Заметка перенесена</h3>
                        <p>Эти строки уже ушли в оперативный журнал. Для новых строк откройте текущую заметку.</p>
                      </div>
                      <Link href={currentQuickNoteHref}>К текущей</Link>
                    </div>
                    <div className="notes-source-card">
                      <strong>{formatDateOnly(openedConvertedQuickNote.createdAt)}</strong>
                      <p>{openedConvertedQuickNote.body}</p>
                    </div>
                  </div>
                ) : (
                  <>
                    {noteHasPendingTransfer && selectedQuickNote ? (
                      <div className="notes-current-notice">
                        <div>
                          <strong>Заметка уже отправлена в журнал</strong>
                          <span>Можно открыть подготовленный перенос или изменить текст и отправить пакет заново.</span>
                        </div>
                        <Link
                          href={`${workspaceBasePath}?mode=notes&notesView=transfer&account=${encodeURIComponent(workspace.activeAccountCode)}&note=${encodeURIComponent(selectedQuickNote.id)}`}
                        >
                          Открыть перенос
                        </Link>
                      </div>
                    ) : null}
                    <QuickNoteComposer
                      accountCode={workspace.activeAccountCode}
                      currency={workspace.currency}
                      defaultBody={editableQuickNote?.body ?? ""}
                      noteId={
                        editableQuickNote
                          ? editableQuickNote.id
                          : ""
                      }
                      saveAction={saveNoteAction}
                      submitAction={submitNoteAction}
                      today={today}
                    />
                  </>
                )}
              </section>
            </section>
          </section>
        ) : null}
        {mode === "reports" ? (
          <section className="workspace-mode-panel" aria-label="Отчеты">
            <div className="mode-title">
              <div>
                <h2>Отчеты</h2>
                <p>Сводка читает категории из оперативной ленты. Факт и учетные блоки не смешиваются.</p>
              </div>
              <small>{workspace.reportSnapshots.length} отчетов</small>
            </div>
            <div className="category-summary" aria-label="Сводка по категориям">
              {summarySections.length > 0 ? (
                summarySections.map((section) => (
                  <section className="category-summary-section" key={section.title}>
                    <div className="note-history-head">
                      <h3>{section.title}</h3>
                      <small>{section.hint}</small>
                    </div>
                    <div className="category-summary-table" role="table" aria-label={section.title}>
                      <div className="category-summary-row category-summary-head" role="row">
                        <span role="columnheader">Категория</span>
                        <span role="columnheader">Тип</span>
                        <span role="columnheader">Сумма</span>
                        <span role="columnheader">Строк</span>
                        <span role="columnheader">Проверка</span>
                      </div>
                      {section.rows.map((row) => (
                        <div className="category-summary-row" role="row" key={row.code}>
                          <strong role="cell">{row.label}</strong>
                          <span role="cell">{directionText(row.direction)}</span>
                          <span
                            className={
                              row.direction === "income"
                                ? "amount-income"
                                : row.direction === "expense"
                                  ? "amount-expense"
                                  : undefined
                            }
                            role="cell"
                          >
                            {formatMoney(row.total, workspace.currency)}
                          </span>
                          <span role="cell">{row.count}</span>
                          <span role="cell">{row.reviewCount}</span>
                        </div>
                      ))}
                    </div>
                  </section>
                ))
              ) : (
                <div className="empty-state inline-empty">
                  <h2>Категорий пока нет</h2>
                  <p>После переноса заметок через Смита здесь появится сводка по реальным категориям.</p>
                </div>
              )}
            </div>
            <div className="report-list">
              {workspace.reportSnapshots.length > 0 ? (
                workspace.reportSnapshots.map((report) => (
                  <article className="report-card" key={report.id}>
                    <div>
                      <h3>{report.title}</h3>
                      <p>
                        {report.periodStart} — {report.periodEnd}
                      </p>
                    </div>
                    <span className="status-pill">{reportStatusText(report.status)}</span>
                  </article>
                ))
              ) : (
                <div className="empty-state inline-empty">
                  <h2>Отчетов пока нет</h2>
                  <p>Создание отчета будет включено отдельной командой, чтобы не смешивать просмотр с журналом-истиной.</p>
                </div>
              )}
            </div>
          </section>
        ) : null}
      </section>

      {mode === "ledger" ? (
        <>
          <form className="entry-bar" action={entryAction}>
            <input type="hidden" name="account" value={workspace.activeAccountCode} />
            <label>
              <span>Дата</span>
              <input type="date" name="occurredOn" defaultValue={today} required />
            </label>
            <label>
              <span>Запись</span>
              <input name="rawText" placeholder="-350 продукты" required />
            </label>
            <button className="primary-action" type="submit">
              Сохранить
            </button>
          </form>
          {statusText ? <p className={query.entry === "saved" ? "form-note success" : "form-note error"}>{statusText}</p> : null}
        </>
      ) : null}
    </main>
  );
}
