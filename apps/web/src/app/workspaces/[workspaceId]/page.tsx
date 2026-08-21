import { notFound } from "next/navigation";
import Link from "next/link";
import { Fragment } from "react";
import {
  acceptAccountableOffer,
  addAccountableExpenseItem,
  convertSmithProposalsToEntries,
  createAccountableOffer,
  createReportExportVersion,
  createReportSnapshot,
  createReportPackage,
  createReportLockedCorrection,
  createOperationalEntry,
  deleteOperationalEntry,
  deleteQuickNote,
  returnReportSnapshotForRevision,
  reviewAccountableReport,
  saveQuickNoteDraft,
  setReportPackageDeliveryStatus,
  setReportSnapshotDeliveryStatus,
  submitAccountableReport,
  submitQuickNoteToSmith,
  updateOperationalEntry
} from "./actions";
import { createWorkspaceInvitation } from "@/app/hall/actions";
import { QuickNoteComposer } from "./QuickNoteComposer";
import { OperationalEntryDraftController } from "./OperationalEntryDraftController";
import { SyncedLedgerTable } from "./SyncedLedgerTable";
import { calculateQuickNoteTotal } from "@/lib/quick-note-totals";
import { routes } from "@/lib/routes";
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
    inviteEmail?: string;
    inviteUrl?: string;
    mode?: string;
    newNote?: string;
    note?: string;
    notesView?: string;
    report?: string;
    status?: string;
  }>;
};

type WorkspaceMode = "ledger" | "notes" | "reports" | "team";

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
  return mode === "notes" || mode === "reports" || mode === "team" ? mode : "ledger";
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
    case "created":
      return "Приглашение создано. Ссылка показана ниже.";
    case "email":
      return "Укажите email сотрудника.";
    case "role":
      return "Роль приглашения не распознана.";
    case "create":
      return "Не удалось создать приглашение.";
    case "accountable-created":
      return "Выдача создана. Сотрудник должен подтвердить получение.";
    case "accountable-accepted":
      return "Получение денег подтверждено.";
    case "accountable-missing":
      return "Укажите сотрудника и сумму.";
    case "accountable-member":
      return "Выберите сотрудника с ограниченным доступом к своему отчету.";
    case "accountable-not-found":
      return "Выдача не найдена или уже обработана.";
    case "accountable-config":
      return "Серверная команда подтверждения не настроена.";
    case "accountable-create":
      return "Не удалось создать выдачу под отчет.";
    case "accountable-accept":
      return "Не удалось подтвердить получение денег.";
    case "accountable-item-created":
      return "Строка добавлена в отчет сотрудника.";
    case "accountable-report-submitted":
      return "Отчет отправлен администратору.";
    case "accountable-report-approved":
      return "Отчет сотрудника принят.";
    case "accountable-report-returned":
      return "Отчет сотрудника возвращен на доработку.";
    case "accountable-report-missing":
      return "Отчет сотрудника не найден.";
    case "accountable-item-create":
      return "Не удалось добавить строку в отчет.";
    case "accountable-report-submit":
      return "Не удалось отправить отчет.";
    case "accountable-report-review":
      return "Не удалось изменить статус отчета сотрудника.";
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
    case "report-export-saved":
      return "Версия файла сохранена.";
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
    case "report-export-missing":
      return "Не выбран отчет, пакет или формат файла.";
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
    case "report-export-save":
      return "Не удалось сохранить версию файла.";
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
    status === "created" ||
    status === "accountable-created" ||
    status === "accountable-accepted" ||
    status === "accountable-item-created" ||
    status === "accountable-report-submitted" ||
    status === "accountable-report-approved" ||
    status === "accountable-report-returned" ||
    status === "report-created" ||
    status === "report-package-created" ||
    status === "report-sent" ||
    status === "report-accepted" ||
    status === "report-package-sent" ||
    status === "report-package-accepted" ||
    status === "report-returned" ||
    status === "report-correction-created" ||
    status === "report-export-saved"
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
    case "submitted":
      return "Отправлен";
    case "approved":
      return "Принят";
    case "returned":
      return "На доработке";
    case "closed":
      return "Закрыт";
    case "void":
      return "Отменен";
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

function accountableStatusText(status: string) {
  switch (status) {
    case "offered":
      return "Ожидает подтверждения";
    case "accepted":
      return "Принято сотрудником";
    case "closed":
      return "Закрыто";
    case "declined":
      return "Отклонено";
    case "void":
      return "Отменено";
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
    case "report_export_version_created":
      return "Сохранена версия файла";
    default:
      return eventType;
  }
}

function latestApprovalEvent(events: ApprovalEventSummary[]) {
  return events.at(-1) ?? null;
}

function exportFormatLabel(format: string) {
  switch (format) {
    case "html":
      return "HTML";
    case "xls":
      return "Excel";
    case "pdf":
      return "PDF";
    default:
      return "Файл";
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
  let mode = normalizeMode(query.mode);
  const workspace = await getWorkspaceDetails(workspaceId, query.account, {
    includeReportDetails: mode === "reports"
  });

  if (!workspace) {
    notFound();
  }

  if (workspace.accessScope === "own_reports" && mode === "ledger") {
    mode = "team";
  }

  const entryAction = createOperationalEntry.bind(null, workspace.id);
  const updateEntryAction = updateOperationalEntry.bind(null, workspace.id);
  const deleteEntryAction = deleteOperationalEntry.bind(null, workspace.id);
  const saveNoteAction = saveQuickNoteDraft.bind(null, workspace.id);
  const submitNoteAction = submitQuickNoteToSmith.bind(null, workspace.id);
  const convertProposalAction = convertSmithProposalsToEntries.bind(null, workspace.id);
  const deleteNoteAction = deleteQuickNote.bind(null, workspace.id);
  const inviteAction = createWorkspaceInvitation.bind(null, workspace.id);
  const createAccountableOfferAction = createAccountableOffer.bind(null, workspace.id);
  const acceptAccountableOfferAction = acceptAccountableOffer.bind(null, workspace.id);
  const addAccountableExpenseItemAction = addAccountableExpenseItem.bind(null, workspace.id);
  const submitAccountableReportAction = submitAccountableReport.bind(null, workspace.id);
  const reviewAccountableReportAction = reviewAccountableReport.bind(null, workspace.id);
  const createReportAction = createReportSnapshot.bind(null, workspace.id);
  const createReportPackageAction = createReportPackage.bind(null, workspace.id);
  const createReportExportAction = createReportExportVersion.bind(null, workspace.id);
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
  const selectedReport = query.report
    ? workspace.reportSnapshots.find((report) => report.id === query.report) ?? null
    : null;
  const canManageMembers = workspace.role === "owner" || workspace.role === "admin";
  const canIssueAccountableMoney = workspace.role === "owner" || workspace.role === "admin" || workspace.role === "finance";
  const isEmployeeScope = workspace.accessScope === "own_reports";
  const canUseGeneralWorkspace = !isEmployeeScope;
  const employeeMembers = workspace.members.filter((member) => member.accessScope === "own_reports" && member.status === "active");
  const acceptedEmployeeAdvances = workspace.accountableAdvances.filter((advance) => advance.status === "accepted");
  const activeEmployeeAdvance =
    acceptedEmployeeAdvances[0] ??
    workspace.accountableAdvances.find((advance) => advance.status === "offered") ??
    workspace.accountableAdvances[0] ??
    null;
  const activeEmployeeReport =
    activeEmployeeAdvance
      ? workspace.accountableReports.find(
          (report) =>
            report.cashAdvanceId === activeEmployeeAdvance.id &&
            (report.status === "draft" || report.status === "returned" || report.status === "submitted")
        ) ?? null
      : null;
  const employeeReportItems = activeEmployeeReport?.items ?? [];
  const employeeIssuedTotal = acceptedEmployeeAdvances.reduce((sum, advance) => sum + advance.amount, 0);
  const employeeReportedTotal = workspace.accountableReports
    .filter((report) => report.status !== "void")
    .reduce((sum, report) => sum + report.acceptedItemsTotal, 0);
  const employeeBalance = employeeIssuedTotal - employeeReportedTotal;
  const pendingAccountableReports = workspace.accountableReports.filter((report) => report.status === "submitted");
  const activeAccount = workspace.accounts.find((account) => account.code === workspace.activeAccountCode) ?? workspace.accounts[0] ?? null;
  const activeNavigationLabel =
    mode === "notes"
      ? "Заметки"
      : mode === "reports"
        ? "Отчеты"
        : mode === "team"
          ? workspace.accessScope === "own_reports"
            ? "Мой отчет"
            : "Участники"
          : activeAccount?.label ?? "Журнал";
  let previousQuickNoteMonth = "";
  const selectedEntry = workspace.entries.find((entry) => entry.id === query.edit) ?? null;
  const entryFormAction = selectedEntry ? updateEntryAction : entryAction;
  const entryDraftFormId = "operational-entry-form";
  const entryDeleteFormId = "operational-entry-delete-form";
  const entryDraftKey = [
    "findesk:v2:entry-draft",
    workspace.id,
    workspace.activeAccountCode,
    selectedEntry?.id ?? "new"
  ].join(":");
  const entryDraftSuccess = query.entry === "saved" || query.entry === "updated" || query.entry === "deleted";

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
          <Link className="workspace-switch-link" href={routes.hall}>
            Сменить пространство
          </Link>
        </div>
        {canUseGeneralWorkspace ? (
          <div className="workspace-metrics" aria-label="Состояние пространства">
            {workspace.accountBalances.map((account) => (
              <span className="money-metric" key={account.accountCode}>
                <small>{account.label}</small>
                <strong>{formatMoney(account.balance, workspace.currency)}</strong>
              </span>
            ))}
            <span>
              <small>Записей</small>
              <strong>{workspace.transactionCount}</strong>
            </span>
            <span>
              <small>На проверке</small>
              <strong>{workspace.reviewCount}</strong>
            </span>
          </div>
        ) : (
          <div className="workspace-metrics compact-team-metrics" aria-label="Состояние моего отчета">
            <span>
              <small>Выдано</small>
              <strong>{formatMoney(employeeIssuedTotal, workspace.currency)}</strong>
            </span>
            <span>
              <small>В отчете</small>
              <strong>{formatMoney(employeeReportedTotal, workspace.currency)}</strong>
            </span>
            <span>
              <small>{employeeBalance >= 0 ? "Остаток" : "Перерасход"}</small>
              <strong>{formatMoney(Math.abs(employeeBalance), workspace.currency)}</strong>
            </span>
          </div>
        )}
      </section>

      <section className="workspace-shell">
        <details className="mobile-section-menu">
          <summary>
            <span>Раздел</span>
            <strong>{activeNavigationLabel}</strong>
          </summary>
          <nav className="mobile-section-menu-panel" aria-label="Мобильная навигация пространства">
            {canUseGeneralWorkspace ? (
              <>
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
              </>
            ) : null}
            <Link
              className={mode === "team" ? "active" : undefined}
              aria-current={mode === "team" ? "page" : undefined}
              href={`${workspaceBasePath}?mode=team&account=${encodeURIComponent(workspace.activeAccountCode)}`}
            >
              {workspace.accessScope === "own_reports" ? "Мой отчет" : "Участники"}
            </Link>
          </nav>
        </details>
        <aside className="side-tabs" aria-label="Разделы рабочего пространства">
          {canUseGeneralWorkspace ? (
            <>
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
            </>
          ) : null}
          <Link
            className={mode === "team" ? "active" : undefined}
            aria-current={mode === "team" ? "page" : undefined}
            href={`${workspaceBasePath}?mode=team&account=${encodeURIComponent(workspace.activeAccountCode)}`}
          >
            {workspace.accessScope === "own_reports" ? "Мой отчет" : "Участники"}
          </Link>
        </aside>
        {mode === "ledger" ? (
          <section className="operational-workspace" aria-label="Оперативный журнал и структурная проверка">
            <SyncedLedgerTable
              accountCode={workspace.activeAccountCode}
              entries={workspace.entries}
              reports={workspace.reportSnapshots}
              selectedEntryId={selectedEntry?.id}
              workspacePath={workspaceBasePath}
            />
          </section>
        ) : null}
        {mode === "team" ? (
          <section className="workspace-mode-panel team-mode-panel" aria-label="Участники и подотчетные деньги">
            <div className="mode-title">
              <div>
                <h2>{workspace.accessScope === "own_reports" ? "Мой отчет" : "Участники"}</h2>
                <p>
                  {workspace.accessScope === "own_reports"
                    ? "Здесь сотрудник принимает выданные деньги и готовит свой отчет без доступа к общей финансовой картине."
                    : "Приглашения, выдачи под отчет и отчеты сотрудников живут внутри этого пространства."}
                </p>
              </div>
              <small>
                {workspace.accountableAdvances.length} выдач · {workspace.accountableReports.length} отчетов
                {canUseGeneralWorkspace ? ` · ждут ${pendingAccountableReports.length}` : ""}
              </small>
            </div>
            {modeStatusText ? (
              <p className={isWorkspaceStatusSuccess(query.status) ? "form-note success" : "form-note error"}>
                {modeStatusText}
              </p>
            ) : null}
            {query.inviteUrl ? (
              <div className="invite-result team-invite-result">
                <span>Ссылка для {query.inviteEmail ?? "участника"}</span>
                <input readOnly value={query.inviteUrl} />
                <small>Ссылка показывается один раз. После входа участник увидит это пространство в своем холле.</small>
              </div>
            ) : null}
            {workspace.accessScope !== "own_reports" ? (
              <section className="team-command-grid" aria-label="Команды владельца пространства">
                {canManageMembers ? (
                  <form action={inviteAction} className="team-command-card">
                    <input type="hidden" name="returnTo" value="workspace" />
                    <h3>Пригласить участника</h3>
                    <label>
                      <span>Email</span>
                      <input name="email" type="email" placeholder="name@example.com" required />
                    </label>
                    <label>
                      <span>Роль</span>
                      <select name="roleCode" defaultValue="employee">
                        <option value="employee">Сотрудник</option>
                        <option value="viewer">Только просмотр</option>
                        <option value="finance">Финансист</option>
                        <option value="admin">Администратор</option>
                      </select>
                    </label>
                    <button className="primary-action" type="submit">
                      Создать ссылку
                    </button>
                  </form>
                ) : null}
                {canIssueAccountableMoney ? (
                  <form action={createAccountableOfferAction} className="team-command-card">
                    <input type="hidden" name="account" value={workspace.activeAccountCode} />
                    <h3>Выдать под отчет</h3>
                    <label>
                      <span>Сотрудник</span>
                      <select name="employeeUserId" defaultValue="" required>
                        <option value="">Выберите сотрудника</option>
                        {employeeMembers.map((member) => (
                          <option key={member.userId} value={member.userId}>
                            {member.email ?? roleLabels[member.role] ?? member.role}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      <span>Сумма</span>
                      <input name="amount" inputMode="decimal" placeholder="500" required />
                    </label>
                    <label>
                      <span>Комментарий</span>
                      <input name="purpose" placeholder="Например: продукты в поход" />
                    </label>
                    <button className="primary-action" type="submit" disabled={employeeMembers.length === 0}>
                      Выдать
                    </button>
                    {employeeMembers.length === 0 ? (
                      <small>Сначала пригласите сотрудника и дождитесь принятия приглашения.</small>
                    ) : null}
                  </form>
                ) : null}
              </section>
            ) : null}
            {isEmployeeScope ? (
              <section className="team-ledger-card employee-report-card" aria-label="Мой отчет по выданным деньгам">
                <div className="note-history-head">
                  <div>
                    <h3>Рабочий отчет</h3>
                    <small>
                      {activeEmployeeAdvance
                        ? `${accountableStatusText(activeEmployeeAdvance.status)} · ${activeEmployeeAdvance.purpose || "без комментария"}`
                        : "Ожидает выдачи от администратора"}
                    </small>
                  </div>
                  {activeEmployeeReport ? <span className="status-pill">{reportStatusText(activeEmployeeReport.status)}</span> : null}
                </div>
                <div className="team-summary-grid" aria-label="Сводка моего отчета">
                  <span>
                    <small>Выдано</small>
                    <strong>{formatMoney(employeeIssuedTotal, workspace.currency)}</strong>
                  </span>
                  <span>
                    <small>В отчете</small>
                    <strong>{formatMoney(employeeReportedTotal, workspace.currency)}</strong>
                  </span>
                  <span>
                    <small>{employeeBalance >= 0 ? "Остаток" : "Перерасход"}</small>
                    <strong>{formatMoney(Math.abs(employeeBalance), workspace.currency)}</strong>
                  </span>
                </div>
                {activeEmployeeAdvance?.status === "accepted" &&
                (!activeEmployeeReport || activeEmployeeReport.status === "draft" || activeEmployeeReport.status === "returned") ? (
                  <form action={addAccountableExpenseItemAction} className="team-report-entry-form">
                    <input type="hidden" name="advanceId" value={activeEmployeeAdvance.id} />
                    <label>
                      <span>Дата</span>
                      <input type="date" name="occurredOn" defaultValue={today} required />
                    </label>
                    <label>
                      <span>Сумма</span>
                      <input name="amount" inputMode="decimal" placeholder="80" required />
                    </label>
                    <label className="wide-field">
                      <span>Запись</span>
                      <input name="rawText" placeholder="-80 продукты" required />
                    </label>
                    <button className="primary-action" type="submit">
                      Добавить строку
                    </button>
                  </form>
                ) : null}
                {activeEmployeeAdvance?.status === "offered" ? (
                  <p className="form-note">Сначала подтвердите получение денег в блоке ниже.</p>
                ) : null}
                {employeeReportItems.length > 0 ? (
                  <div className="team-expense-item-list" aria-label="Строки моего отчета">
                    {employeeReportItems.map((item, index) => (
                      <div className="team-expense-item-row" key={item.id}>
                        <span>{index + 1}</span>
                        <time dateTime={item.occurredOn}>{formatDateOnly(item.occurredOn)}</time>
                        <strong>{item.rawText}</strong>
                        <b>{formatMoney(item.amount, workspace.currency)}</b>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="empty-state inline-empty">
                    <h2>Строк отчета пока нет</h2>
                    <p>После принятия денег добавляйте сюда расходы простыми строками.</p>
                  </div>
                )}
                {activeEmployeeReport &&
                (activeEmployeeReport.status === "draft" || activeEmployeeReport.status === "returned") &&
                employeeReportItems.length > 0 ? (
                  <form action={submitAccountableReportAction} className="team-submit-report-form">
                    <input type="hidden" name="reportId" value={activeEmployeeReport.id} />
                    <button className="primary-action" type="submit">
                      Отправить администратору
                    </button>
                  </form>
                ) : null}
                {activeEmployeeReport?.status === "submitted" ? (
                  <p className="form-note success">Отчет отправлен. Администратор примет его или вернет на доработку.</p>
                ) : null}
              </section>
            ) : null}
            <section className="team-ledger-card" aria-label="Выданные под отчет деньги">
              <div className="note-history-head">
                <h3>{workspace.accessScope === "own_reports" ? "Мои деньги под отчет" : "Выданные суммы"}</h3>
                <small>{workspace.accountableAdvances.length} строк</small>
              </div>
              {workspace.accountableAdvances.length > 0 ? (
                <div className="team-accountable-list">
                  {workspace.accountableAdvances.map((advance) => (
                    <article className="team-accountable-row" key={advance.id}>
                      <div>
                        <strong>{advance.issuedToEmail ?? "Сотрудник"}</strong>
                        <span>{advance.purpose || "Без комментария"}</span>
                      </div>
                      <b>{formatMoney(advance.amount, advance.currency)}</b>
                      <span>{formatMoney(advance.spentTotal, advance.currency)} отчитано</span>
                      <span>{formatMoney(advance.openAmount, advance.currency)} открыто</span>
                      <span className="status-pill">{accountableStatusText(advance.status)}</span>
                      {workspace.accessScope === "own_reports" && advance.status === "offered" ? (
                        <form action={acceptAccountableOfferAction} className="compact-action-form">
                          <input type="hidden" name="advanceId" value={advance.id} />
                          <button type="submit">Принять</button>
                        </form>
                      ) : null}
                    </article>
                  ))}
                </div>
              ) : (
                <div className="empty-state inline-empty">
                  <h2>{workspace.accessScope === "own_reports" ? "Денег под отчет пока нет" : "Выдач пока нет"}</h2>
                  <p>
                    {workspace.accessScope === "own_reports"
                      ? "Когда администратор выдаст сумму, здесь появится сообщение для подтверждения."
                      : "После выдачи сотрудник подтвердит получение, а затем сдаст отчет по этой сумме."}
                  </p>
                </div>
              )}
            </section>
            <section className="team-ledger-card" aria-label="Отчеты сотрудников">
              <div className="note-history-head">
                <h3>{workspace.accessScope === "own_reports" ? "Мои отчеты" : "Отчеты сотрудников"}</h3>
                <small>{workspace.accountableReports.length} строк</small>
              </div>
              {workspace.accountableReports.length > 0 ? (
                <div className="team-accountable-list">
                  {workspace.accountableReports.map((report) => (
                    <article className="team-report-review-card" key={report.id}>
                      <div className="team-report-review-head">
                        <div>
                          <strong>{report.submittedByEmail ?? "Сотрудник"}</strong>
                          <span>
                            {formatDateTime(report.submittedAt ?? report.createdAt)} · {report.items.length} строк
                          </span>
                        </div>
                        <b>{formatMoney(report.totalAmount, report.currency)}</b>
                        <span className="status-pill">{reportStatusText(report.status)}</span>
                      </div>
                      {report.items.length > 0 ? (
                        <div className="team-expense-item-list compact-expense-item-list" aria-label="Строки отчета сотрудника">
                          {report.items.map((item, index) => (
                            <div className="team-expense-item-row" key={item.id}>
                              <span>{index + 1}</span>
                              <time dateTime={item.occurredOn}>{formatDateOnly(item.occurredOn)}</time>
                              <strong>{item.rawText}</strong>
                              <b>{formatMoney(item.amount, report.currency)}</b>
                            </div>
                          ))}
                        </div>
                      ) : null}
                      {canIssueAccountableMoney && report.status === "submitted" ? (
                        <div className="team-review-actions" aria-label="Решение по отчету сотрудника">
                          <form action={reviewAccountableReportAction} className="compact-action-form">
                            <input type="hidden" name="reportId" value={report.id} />
                            <input type="hidden" name="nextStatus" value="approved" />
                            <button type="submit">Принять отчет</button>
                          </form>
                          <form action={reviewAccountableReportAction} className="team-return-report-form">
                            <input type="hidden" name="reportId" value={report.id} />
                            <input type="hidden" name="nextStatus" value="returned" />
                            <input name="note" placeholder="Что исправить" />
                            <button type="submit">На доработку</button>
                          </form>
                        </div>
                      ) : null}
                    </article>
                  ))}
                </div>
              ) : (
                <div className="empty-state inline-empty">
                  <h2>Отчетов пока нет</h2>
                  <p>Расходы сотрудника будут попадать сюда перед прикреплением к общему отчету.</p>
                </div>
              )}
            </section>
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
                        {reportPackage.exportVersions.length > 0 ? (
                          <small>Файлы: {reportPackage.exportVersions.length}</small>
                        ) : null}
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
                        <Link
                          className="ghost-button"
                          href={`${workspaceBasePath}/report-packages/${encodeURIComponent(reportPackage.id)}/excel`}
                        >
                          Excel
                        </Link>
                        {(["html", "xls", "pdf"] as const).map((format) => (
                          <form className="compact-action-form" action={createReportExportAction} key={format}>
                            <input type="hidden" name="account" value={workspace.activeAccountCode} />
                            <input type="hidden" name="entityType" value="report_package" />
                            <input type="hidden" name="entityId" value={reportPackage.id} />
                            <input type="hidden" name="format" value={format} />
                            <button type="submit">v {exportFormatLabel(format)}</button>
                          </form>
                        ))}
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
                    <article className="report-card report-document-card" key={report.id}>
                      <label className="report-select">
                        <input name="reportId" type="checkbox" value={report.id} />
                        <span>В пакет</span>
                      </label>
                      <Link
                        className="report-document-link"
                        href={`${workspaceBasePath}?mode=reports&account=${encodeURIComponent(workspace.activeAccountCode)}&report=${encodeURIComponent(report.id)}`}
                      >
                        <span className="report-document-icon" aria-hidden="true">F</span>
                        <div>
                        <h3>{report.title}</h3>
                        <p>
                          {formatDateOnly(report.periodStart)} — {formatDateOnly(report.periodEnd)} · {report.entryCount} строк
                          {report.reviewCount > 0 ? ` · проверка ${report.reviewCount}` : ""}
                        </p>
                        </div>
                      </Link>
                      <div className="report-card-metrics compact-report-metrics" aria-label="Итог отчета">
                        <span>
                          <small>{report.endingCash === null ? "Итог" : "Остаток"}</small>
                          <strong>
                            {formatMoney(report.endingCash === null ? report.netTotal : report.endingCash, workspace.currency)}
                          </strong>
                        </span>
                      </div>
                      <span className="status-pill">{reportStatusText(report.status)}</span>
                      <div className="report-card-actions">
                        <Link
                          className="ghost-button"
                          href={`${workspaceBasePath}?mode=reports&account=${encodeURIComponent(workspace.activeAccountCode)}&report=${encodeURIComponent(report.id)}`}
                        >
                          Открыть
                        </Link>
                        <Link
                          className="ghost-button"
                          href={`${workspaceBasePath}/reports/${encodeURIComponent(report.id)}`}
                          target="_blank"
                        >
                          HTML
                        </Link>
                      </div>
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
                    <Link
                      className="ghost-button"
                      href={`${workspaceBasePath}/reports/${encodeURIComponent(selectedReport.id)}/excel`}
                    >
                      Excel
                    </Link>
                    {(["html", "xls", "pdf"] as const).map((format) => (
                      <form className="compact-action-form" action={createReportExportAction} key={format}>
                        <input type="hidden" name="account" value={workspace.activeAccountCode} />
                        <input type="hidden" name="entityType" value="report_snapshot" />
                        <input type="hidden" name="entityId" value={selectedReport.id} />
                        <input type="hidden" name="format" value={format} />
                        <button type="submit">v {exportFormatLabel(format)}</button>
                      </form>
                    ))}
                  </div>
                </div>
                {selectedReport.exportVersions.length > 0 ? (
                  <section className="report-detail-section">
                    <h4>Файлы</h4>
                    <div className="report-export-list" aria-label="Версии файлов отчета">
                      {selectedReport.exportVersions.map((version) => (
                        <a className="report-export-row" href={version.downloadPath} key={version.documentVersionId}>
                          <strong>
                            {exportFormatLabel(version.format)} · v{version.versionNo}
                          </strong>
                          <span>{version.filename}</span>
                          <time dateTime={version.createdAt}>{formatDateTime(version.createdAt)}</time>
                        </a>
                      ))}
                    </div>
                  </section>
                ) : null}
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
            <form id={entryDraftFormId} className="entry-bar" action={entryFormAction}>
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
              <form id={entryDeleteFormId} className="entry-delete-form" action={deleteEntryAction}>
                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                <input type="hidden" name="transactionId" value={selectedEntry.id} />
                <button type="submit">Удалить</button>
              </form>
            ) : null}
            <OperationalEntryDraftController
              draftKey={entryDraftKey}
              formId={entryDraftFormId}
              submitFormIds={selectedEntry ? [entryDraftFormId, entryDeleteFormId] : [entryDraftFormId]}
              successSignal={entryDraftSuccess}
            />
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
