import { notFound } from "next/navigation";
import Link from "next/link";
import { Fragment } from "react";
import {
  convertSmithProposalsToEntries,
  createReportSnapshot,
  createReportPackage,
  createReportLockedCorrection,
  createOperationalEntry,
  deleteOperationalEntry,
  deleteQuickNote,
  returnReportSnapshotForRevision,
  saveQuickNoteDraft,
  setReportPackageDeliveryStatus,
  setReportSnapshotDeliveryStatus,
  submitQuickNoteToSmith,
  updateOperationalEntry
} from "./actions";
import { QuickNoteComposer } from "./QuickNoteComposer";
import { SyncedLedgerTable } from "./SyncedLedgerTable";
import { calculateQuickNoteTotal } from "@/lib/quick-note-totals";
import { smithCategoryLabel, smithCategoryOptions } from "@/lib/smith-categories";
import type { ApprovalEventSummary } from "@/lib/workspace-data";
import { getWorkspaceDetails, roleLabels, workspacePath } from "@/lib/workspace-data";

type WorkspacePageProps = {
  params: Promise<{
    workspaceId: string;
  }>;
  searchParams?: Promise<{
    account?: string;
    edit?: string;
    entry?: string;
    mode?: string;
    newNote?: string;
    note?: string;
    notesView?: string;
    report?: string;
    status?: string;
  }>;
};

type WorkspaceMode = "ledger" | "notes" | "reports";

function entryStatusText(status?: string) {
  switch (status) {
    case "saved":
      return "Запись сохранена.";
    case "updated":
      return "Запись обновлена.";
    case "deleted":
      return "Запись удалена из рабочей ленты.";
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
    case "entry-not-found":
      return "Запись не найдена или уже удалена.";
    case "auth":
      return "Сессия не найдена. Войдите заново.";
    case "update":
      return "Не удалось обновить запись.";
    case "delete":
      return "Не удалось удалить запись.";
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
    case "report-created":
      return "Отчет создан, строки периода закрыты от обычного редактирования.";
    case "report-package-created":
      return "Пакет отчетов создан.";
    case "report-sent":
      return "Отчет отмечен как отправленный.";
    case "report-accepted":
      return "Отчет отмечен как принятый.";
    case "report-package-sent":
      return "Пакет отчетов отмечен как отправленный.";
    case "report-package-accepted":
      return "Пакет отчетов отмечен как принятый.";
    case "report-returned":
      return "Отчет отмечен как возвращенный на доработку.";
    case "report-correction-created":
      return "Корректировка создана новой строкой в рабочей ленте.";
    case "report-period":
      return "Выберите корректный период отчета.";
    case "report-empty":
      return "В выбранном периоде нет открытых строк для отчета.";
    case "report-missing":
      return "Отчет не найден.";
    case "report-correction-missing":
      return "Выберите строку, дату, корректирующую запись и причину.";
    case "report-correction-source":
      return "Исходная строка не найдена или не закрыта отчетом.";
    case "report-package-empty":
      return "Выберите один или несколько сохраненных отчетов.";
    case "report-status-transition":
    case "report-package-status-transition":
      return "Этот переход статуса сейчас недоступен.";
    case "report-auth":
      return "Нет прав на создание отчета и закрытие периода.";
    case "report-create":
    case "report-package-create":
      return "Не удалось создать отчет.";
    case "report-revision":
      return "Не удалось вернуть отчет на доработку.";
    case "report-status":
      return "Не удалось изменить статус отчета.";
    case "report-package-status":
      return "Не удалось изменить статус пакета.";
    case "report-correction":
      return "Не удалось создать корректировку.";
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
    status === "note-deleted" ||
    status === "report-created" ||
    status === "report-package-created" ||
    status === "report-sent" ||
    status === "report-accepted" ||
    status === "report-package-sent" ||
    status === "report-package-accepted" ||
    status === "report-returned" ||
    status === "report-correction-created"
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

function approvalEventText(eventType: string) {
  switch (eventType) {
    case "report_snapshot_created":
      return "Отчет создан";
    case "report_snapshot_sent":
      return "Отчет отправлен";
    case "report_snapshot_accepted":
      return "Отчет принят";
    case "report_snapshot_returned_for_revision":
      return "Возвращен на доработку";
    case "report_locked_correction_created":
      return "Создана корректировка";
    case "report_package_created":
      return "Пакет создан";
    case "report_package_sent":
      return "Пакет отправлен";
    case "report_package_accepted":
      return "Пакет принят";
    default:
      return eventType;
  }
}

function latestApprovalEvent(events: ApprovalEventSummary[]) {
  return events.at(-1) ?? null;
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
  const updateEntryAction = updateOperationalEntry.bind(null, workspace.id);
  const deleteEntryAction = deleteOperationalEntry.bind(null, workspace.id);
  const saveNoteAction = saveQuickNoteDraft.bind(null, workspace.id);
  const submitNoteAction = submitQuickNoteToSmith.bind(null, workspace.id);
  const convertProposalAction = convertSmithProposalsToEntries.bind(null, workspace.id);
  const deleteNoteAction = deleteQuickNote.bind(null, workspace.id);
  const createReportAction = createReportSnapshot.bind(null, workspace.id);
  const createReportPackageAction = createReportPackage.bind(null, workspace.id);
  const returnReportAction = returnReportSnapshotForRevision.bind(null, workspace.id);
  const createReportCorrectionAction = createReportLockedCorrection.bind(null, workspace.id);
  const setReportStatusAction = setReportSnapshotDeliveryStatus.bind(null, workspace.id);
  const setReportPackageStatusAction = setReportPackageDeliveryStatus.bind(null, workspace.id);
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
  const selectedReport =
    workspace.reportSnapshots.find((report) => report.id === query.report) ?? workspace.reportSnapshots[0] ?? null;
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
  const selectedEntry = workspace.entries.find((entry) => entry.id === query.edit) ?? null;
  const entryFormAction = selectedEntry ? updateEntryAction : entryAction;

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
            <SyncedLedgerTable
              accountCode={workspace.activeAccountCode}
              entries={workspace.entries}
              selectedEntryId={selectedEntry?.id}
              workspacePath={workspaceBasePath}
            />
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
                <p>Отчет создается из открытых строк оперативной ленты и сохраняет источник каждой строки.</p>
              </div>
              <small>{workspace.reportSnapshots.length} отчетов</small>
            </div>
            {modeStatusText ? (
              <p className={isWorkspaceStatusSuccess(query.status) ? "form-note success" : "form-note error"}>
                {modeStatusText}
              </p>
            ) : null}
            <form className="report-create-panel" action={createReportAction} aria-label="Создать отчет за период">
              <input type="hidden" name="account" value={workspace.activeAccountCode} />
              <label>
                <span>С</span>
                <input type="date" name="periodStart" defaultValue={workspace.entries[0]?.occurredOn ?? today} required />
              </label>
              <label>
                <span>По</span>
                <input
                  type="date"
                  name="periodEnd"
                  defaultValue={workspace.entries[workspace.entries.length - 1]?.occurredOn ?? today}
                  required
                />
              </label>
              <label className="report-title-field">
                <span>Название</span>
                <input name="title" placeholder="Отчет за период" />
              </label>
              <button className="primary-action" type="submit">
                Создать отчет
              </button>
            </form>
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
            <section className="report-package-panel" aria-label="Пакеты отчетов">
              {workspace.reportPackages.length > 0 ? (
                <section className="report-package-list" aria-label="Сохраненные пакеты отчетов">
                  <div className="note-history-head">
                    <h3>Пакеты</h3>
                    <small>{workspace.reportPackages.length} сохранено</small>
                  </div>
                  {workspace.reportPackages.map((reportPackage) => (
                    <article className="report-package-card" key={reportPackage.id}>
                      <div>
                        <strong>{reportPackage.title}</strong>
                        <small>
                          {formatDateTime(reportPackage.createdAt)} · {reportPackage.reportCount} отчетов
                        </small>
                        {latestApprovalEvent(reportPackage.events) ? (
                          <small>
                            {approvalEventText(latestApprovalEvent(reportPackage.events)!.eventType)} ·{" "}
                            {formatDateTime(latestApprovalEvent(reportPackage.events)!.createdAt)}
                          </small>
                        ) : null}
                      </div>
                      <div className="report-detail-actions">
                        <span className="status-pill">{reportStatusText(reportPackage.status)}</span>
                        {reportPackage.status === "created" ? (
                          <form className="compact-action-form" action={setReportPackageStatusAction}>
                            <input type="hidden" name="account" value={workspace.activeAccountCode} />
                            <input type="hidden" name="packageId" value={reportPackage.id} />
                            <input type="hidden" name="nextStatus" value="sent" />
                            <button type="submit">Отправить</button>
                          </form>
                        ) : null}
                        {reportPackage.status === "sent" ? (
                          <form className="compact-action-form" action={setReportPackageStatusAction}>
                            <input type="hidden" name="account" value={workspace.activeAccountCode} />
                            <input type="hidden" name="packageId" value={reportPackage.id} />
                            <input type="hidden" name="nextStatus" value="accepted" />
                            <button type="submit">Принять</button>
                          </form>
                        ) : null}
                        <Link
                          className="ghost-button"
                          href={`${workspaceBasePath}/report-packages/${encodeURIComponent(reportPackage.id)}`}
                          target="_blank"
                        >
                          HTML
                        </Link>
                      </div>
                    </article>
                  ))}
                </section>
              ) : null}
              <form className="report-package-builder" action={createReportPackageAction}>
                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                <div className="report-package-head">
                  <label>
                    <span>Пакет для отправки</span>
                    <input name="title" placeholder="Например: Отчет шефу за август" />
                  </label>
                  <button type="submit">Собрать пакет</button>
                </div>
                <div className="report-list">
                {workspace.reportSnapshots.length > 0 ? (
                  workspace.reportSnapshots.map((report) => (
                    <article className="report-card" key={report.id}>
                      <label className="report-select">
                        <input name="reportId" type="checkbox" value={report.id} />
                        <span>В пакет</span>
                      </label>
                      <div>
                        <h3>{report.title}</h3>
                        <p>
                          {formatDateOnly(report.periodStart)} — {formatDateOnly(report.periodEnd)} · {report.entryCount} строк
                        </p>
                      </div>
                      <div className="report-card-metrics" aria-label="Итоги отчета">
                        <span>
                          <small>Приход</small>
                          <strong className="amount-income">{formatMoney(report.incomeTotal, workspace.currency)}</strong>
                        </span>
                        <span>
                          <small>Расход</small>
                          <strong className="amount-expense">{formatMoney(report.expenseTotal, workspace.currency)}</strong>
                        </span>
                        <span>
                          <small>Итог</small>
                          <strong>{formatMoney(report.netTotal, workspace.currency)}</strong>
                        </span>
                        <span>
                          <small>Проверка</small>
                          <strong>{report.reviewCount}</strong>
                        </span>
                      </div>
                      <span className="status-pill">{reportStatusText(report.status)}</span>
                      <Link
                        className="ghost-button"
                        href={`${workspaceBasePath}?mode=reports&account=${encodeURIComponent(workspace.activeAccountCode)}&report=${encodeURIComponent(report.id)}`}
                      >
                        Открыть
                      </Link>
                    </article>
                  ))
                ) : (
                  <div className="empty-state inline-empty">
                    <h2>Отчетов пока нет</h2>
                    <p>Выберите период выше и создайте первый сохраненный отчет.</p>
                  </div>
                )}
                </div>
              </form>
            </section>
            {selectedReport ? (
              <section className="report-detail-panel" aria-label="Открытый отчет">
                <div className="note-history-head">
                  <div>
                    <h3>{selectedReport.title}</h3>
                    <small>
                      {formatDateOnly(selectedReport.periodStart)} — {formatDateOnly(selectedReport.periodEnd)} ·{" "}
                      {selectedReport.entryCount} строк
                    </small>
                  </div>
                  <div className="report-detail-actions">
                    <span className="status-pill">{reportStatusText(selectedReport.status)}</span>
                    {selectedReport.status === "created" || selectedReport.status === "returned_for_revision" ? (
                      <form className="compact-action-form" action={setReportStatusAction}>
                        <input type="hidden" name="account" value={workspace.activeAccountCode} />
                        <input type="hidden" name="reportId" value={selectedReport.id} />
                        <input type="hidden" name="nextStatus" value="sent" />
                        <button type="submit">Отправить</button>
                      </form>
                    ) : null}
                    {selectedReport.status === "sent" ? (
                      <form className="compact-action-form" action={setReportStatusAction}>
                        <input type="hidden" name="account" value={workspace.activeAccountCode} />
                        <input type="hidden" name="reportId" value={selectedReport.id} />
                        <input type="hidden" name="nextStatus" value="accepted" />
                        <button type="submit">Принять</button>
                      </form>
                    ) : null}
                    <Link
                      className="ghost-button"
                      href={`${workspaceBasePath}/reports/${encodeURIComponent(selectedReport.id)}`}
                      target="_blank"
                    >
                      HTML
                    </Link>
                  </div>
                </div>
                {selectedReport.status !== "returned_for_revision" ? (
                  <form className="report-revision-form" action={returnReportAction}>
                    <input type="hidden" name="account" value={workspace.activeAccountCode} />
                    <input type="hidden" name="reportId" value={selectedReport.id} />
                    <label>
                      <span>Причина доработки</span>
                      <input name="reason" placeholder="Например: нужна корректировка строки" />
                    </label>
                    <button type="submit">На доработку</button>
                  </form>
                ) : null}
                {selectedReport.status === "returned_for_revision" ? (
                  <form className="report-correction-form" action={createReportCorrectionAction}>
                    <input type="hidden" name="reportId" value={selectedReport.id} />
                    <label>
                      <span>Исходная строка</span>
                      <select name="originalTransactionId" required>
                        <option value="">Выберите строку</option>
                        {selectedReport.entries.map((entry) => (
                          <option key={entry.id} value={entry.id}>
                            {entry.rowNo} · {formatDateOnly(entry.occurredOn)} · {entry.rawText}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      <span>Счет</span>
                      <select name="account" defaultValue={workspace.activeAccountCode}>
                        {workspace.accounts.map((account) => (
                          <option key={account.id} value={account.code}>
                            {account.label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      <span>Дата</span>
                      <input type="date" name="occurredOn" defaultValue={today} required />
                    </label>
                    <label className="wide-field">
                      <span>Корректирующая запись</span>
                      <input name="rawText" placeholder="+20 уточнение расхода" required />
                    </label>
                    <label className="wide-field">
                      <span>Причина</span>
                      <input name="reason" placeholder="Почему нужна корректировка" required />
                    </label>
                    <button type="submit">Создать корректировку</button>
                  </form>
                ) : null}
                {selectedReport.events.length > 0 ? (
                  <section className="report-detail-section">
                    <h4>История</h4>
                    <div className="approval-event-list" aria-label="История отчета">
                      {selectedReport.events.map((event) => (
                        <div className="approval-event-row" key={event.id}>
                          <time dateTime={event.createdAt}>{formatDateTime(event.createdAt)}</time>
                          <strong>{approvalEventText(event.eventType)}</strong>
                          {event.note ? <span>{event.note}</span> : <span>—</span>}
                        </div>
                      ))}
                    </div>
                  </section>
                ) : null}
                <div className="report-detail-totals" aria-label="Итоги открытого отчета">
                  <span>
                    <small>Приход</small>
                    <strong className="amount-income">{formatMoney(selectedReport.incomeTotal, workspace.currency)}</strong>
                  </span>
                  <span>
                    <small>Расход</small>
                    <strong className="amount-expense">{formatMoney(selectedReport.expenseTotal, workspace.currency)}</strong>
                  </span>
                  <span>
                    <small>Итог</small>
                    <strong>{formatMoney(selectedReport.netTotal, workspace.currency)}</strong>
                  </span>
                  <span>
                    <small>Проверка</small>
                    <strong>{selectedReport.reviewCount}</strong>
                  </span>
                </div>
                {selectedReport.accounts.length > 0 ? (
                  <section className="report-detail-section">
                    <h4>Счета</h4>
                    <div className="report-mini-table">
                      {selectedReport.accounts.map((account) => (
                        <div className="report-mini-row" key={account.accountCode}>
                          <strong>{account.label}</strong>
                          <span>{account.entryCount} строк</span>
                          <span className="amount-income">{formatMoney(account.incomeTotal, workspace.currency)}</span>
                          <span className="amount-expense">{formatMoney(account.expenseTotal, workspace.currency)}</span>
                        </div>
                      ))}
                    </div>
                  </section>
                ) : null}
                <section className="report-detail-section">
                  <h4>Категории</h4>
                  {selectedReport.categories.length > 0 ? (
                    <div className="report-category-list">
                      {selectedReport.categories.map((category) => (
                        <details className="report-category-card" key={`${category.code}-${category.direction}`}>
                          <summary>
                            <span>
                              <strong>{category.label}</strong>
                              <small>
                                {directionText(category.direction)} · {category.entryCount} строк · проверка {category.reviewCount}
                              </small>
                            </span>
                            <b>{formatMoney(category.total, workspace.currency)}</b>
                          </summary>
                          <div className="report-source-row-list">
                            {selectedReport.entries
                              .filter(
                                (entry) =>
                                  entry.categoryCode === category.code &&
                                  (entry.direction ?? category.direction) === category.direction
                              )
                              .map((entry) => (
                                <div className="report-source-row" key={entry.id}>
                                  <span>{entry.rowNo}</span>
                                  <time dateTime={entry.occurredOn}>{formatDateOnly(entry.occurredOn)}</time>
                                  <strong>{entry.rawText}</strong>
                                  <b
                                    className={
                                      entry.direction === "income"
                                        ? "amount-income"
                                        : entry.direction === "expense"
                                          ? "amount-expense"
                                          : undefined
                                    }
                                  >
                                    {entry.amount === null ? "—" : formatMoney(entry.amount, workspace.currency)}
                                  </b>
                                  <small>{entry.reviewStatus === "accepted" ? "Принято" : "Проверка"}</small>
                                </div>
                              ))}
                          </div>
                        </details>
                      ))}
                    </div>
                  ) : (
                    <div className="empty-state inline-empty">
                      <h2>Категорий нет</h2>
                      <p>В отчете есть только строки на проверке или без принятой категории.</p>
                    </div>
                  )}
                </section>
              </section>
            ) : null}
          </section>
        ) : null}
      </section>

      {mode === "ledger" ? (
        <>
          <section className={selectedEntry ? "entry-editor is-editing" : "entry-editor"} aria-label="Запись">
            <form className="entry-bar" action={entryFormAction}>
              <input type="hidden" name="account" value={workspace.activeAccountCode} />
              {selectedEntry ? <input type="hidden" name="transactionId" value={selectedEntry.id} /> : null}
              <label>
                <span>Дата</span>
                <input type="date" name="occurredOn" defaultValue={selectedEntry?.occurredOn ?? today} required />
              </label>
              <label>
                <span>{selectedEntry ? `Строка ${selectedEntry.rowNo}` : "Новая запись"}</span>
                <input
                  autoFocus
                  name="rawText"
                  placeholder="-350 продукты"
                  defaultValue={selectedEntry?.rawText ?? ""}
                  required
                />
              </label>
              <div className="entry-actions">
                {selectedEntry ? (
                  <Link
                    className="ghost-button"
                    href={`${workspaceBasePath}?account=${encodeURIComponent(workspace.activeAccountCode)}`}
                  >
                    Новая
                  </Link>
                ) : null}
                <button className="primary-action" type="submit">
                  {selectedEntry ? "Обновить" : "Сохранить"}
                </button>
              </div>
            </form>
            {selectedEntry ? (
              <form className="entry-delete-form" action={deleteEntryAction}>
                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                <input type="hidden" name="transactionId" value={selectedEntry.id} />
                <button type="submit">Удалить</button>
              </form>
            ) : null}
          </section>
          {statusText ? (
            <p className={query.entry === "saved" || query.entry === "updated" || query.entry === "deleted" ? "form-note success" : "form-note error"}>
              {statusText}
            </p>
          ) : null}
        </>
      ) : null}
    </main>
  );
}
