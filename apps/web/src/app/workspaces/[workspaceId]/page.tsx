import { notFound } from "next/navigation";
import Link from "next/link";
import {
  convertSmithProposalsToEntries,
  createOperationalEntry,
  deleteQuickNote,
  saveQuickNoteDraft,
  submitQuickNoteToSmith
} from "./actions";
import { SyncedLedgerTable } from "./SyncedLedgerTable";
import { getWorkspaceDetails, roleLabels, workspacePath } from "@/lib/workspace-data";

type WorkspacePageProps = {
  params: Promise<{
    workspaceId: string;
  }>;
  searchParams?: Promise<{
    account?: string;
    entry?: string;
    mode?: string;
    note?: string;
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
      return "Заметка отправлена на проверку Смиту.";
    case "note-ready":
      return "Смит подготовил строки. Проверьте список и перенесите выбранное.";
    case "note-converted":
      return "Смит перенес заметку в оперативный журнал.";
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
      return "У Смита";
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

function categoryText(code: string | null) {
  switch (code) {
    case "crew":
      return "Экипаж";
    case "commercial_income":
      return "Коммерческий приход";
    case "non_commercial_income":
      return "Некоммерческое поступление";
    case "dry_dock":
      return "Сухой док";
    case "berth":
      return "Стоянка";
    case "marina_ports":
      return "Марины и портовые";
    case "service_water":
      return "Сервисные работы";
    case "tech_parts":
      return "Техчасть и запчасти";
    case "tender":
      return "Тендер / тузик";
    case "fuel":
      return "Топливо";
    case "provisions":
      return "Продукты и гости";
    case "guest_trip_support":
      return "Обеспечение гостей в походе";
    case "guest_cash_issued":
      return "Выданные наличные гостям";
    case "representation_expenses":
      return "Представительские расходы";
    case "interior":
      return "Интерьер и быт";
    case "cleaning":
      return "Клининг и химия";
    case "media_comms":
      return "Мультимедиа и связь";
    case "transport_expenses":
      return "Транспортные расходы";
    case "admin_legal":
      return "Админка / документы";
    case "current_boat_expenses":
      return "Текущие лодочные расходы";
    case "cash_topup_from_card":
      return "Пополнение кеша с карты";
    case "admin_debt":
      return "Долг администратора";
    case "lower_accounting":
      return "Подотчет / долг";
    case "other":
      return "Нужно разобрать вручную";
    default:
      return "Категория не выбрана";
  }
}

function reviewReasonText(reason: string | null) {
  switch (reason) {
    case "accepted":
      return "понятно";
    case "owner_funding":
      return "поступление от владельца";
    case "money_movement":
      return "перемещение денег";
    case "weak_only":
      return "слабый признак";
    case "mixed_context":
      return "смешанный смысл";
    case "lower_accounting":
      return "нижний учет";
    case "admin_debt":
      return "личный долг администратора";
    case "merchant_alias_review":
      return "название магазина, нужен ручной смысл";
    case "card_income_needs_guard":
      return "поступление на карту требует проверки";
    case "other_review":
    case "no_category":
      return "нужно решение";
    default:
      return reason ?? "нужно решение";
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
  const selectedQuickNote =
    workspace.quickNotes.find((note) => note.id === query.note) ??
    workspace.quickNotes.find((note) => note.status === "draft") ??
    null;
  const pendingProposals = selectedQuickNote?.proposals.filter((proposal) => proposal.status === "pending") ?? [];

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
          <section className="workspace-mode-panel" aria-label="Быстрые заметки">
            <div className="mode-title">
              <div>
                <h2>Быстрые заметки</h2>
                <p>Короткий блокнот. Смит перенесет строки в журнал, спорное оставит на проверке.</p>
              </div>
              <small>{workspace.quickNotes.length} в истории</small>
            </div>
            <form className="quick-note-form" action={saveNoteAction}>
              <input type="hidden" name="account" value={workspace.activeAccountCode} />
              <input
                type="hidden"
                name="noteId"
                value={
                  selectedQuickNote?.status === "draft" || selectedQuickNote?.status === "submitted_to_smith"
                    ? selectedQuickNote.id
                    : ""
                }
              />
              <label>
                <span>Дата переноса</span>
                <input type="date" name="occurredOn" defaultValue={today} required />
              </label>
              <label>
                <span>Текущая заметка</span>
                <textarea
                  name="body"
                  defaultValue={selectedQuickNote?.body ?? ""}
                  placeholder={"+1000 поступило от судовладельца\n-350 продукты\n-100 стоянка в марине"}
                  required
                />
              </label>
              <div className="mode-actions">
                <Link href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}`}>
                  Новая заметка
                </Link>
                <button type="submit">Сохранить черновик</button>
                <button className="primary-action" formAction={submitNoteAction} type="submit">
                  Проверить у Смита
                </button>
              </div>
            </form>
            {pendingProposals.length > 0 && selectedQuickNote ? (
              <form className="smith-review-panel" action={convertProposalAction}>
                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                <input type="hidden" name="noteId" value={selectedQuickNote.id} />
                <div className="note-history-head">
                  <h3>Проверка Смита</h3>
                  <small>{pendingProposals.length} строк</small>
                </div>
                <div className="smith-proposal-list">
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
                          {categoryText(proposal.candidateCategoryCode)} · {reviewReasonText(proposal.reviewReason)}
                          {proposal.confidence !== null ? ` · ${Math.round(proposal.confidence * 100)}%` : ""}
                        </small>
                      </span>
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
                    Перенести выбранное
                  </button>
                </div>
              </form>
            ) : null}
            {selectedQuickNote ? (
              <form className="note-delete-form" action={deleteNoteAction}>
                <input type="hidden" name="account" value={workspace.activeAccountCode} />
                <input type="hidden" name="noteId" value={selectedQuickNote.id} />
                <button type="submit">Удалить выбранную заметку</button>
              </form>
            ) : null}
            {modeStatusText ? (
              <p className={isWorkspaceStatusSuccess(query.status) ? "form-note success" : "form-note error"}>
                {modeStatusText}
              </p>
            ) : null}
            <div className="note-history-head">
              <h3>История заметок</h3>
              <small>Последние сохраненные заметки</small>
            </div>
            <div className="note-history" aria-label="История заметок">
              {workspace.quickNotes.length > 0 ? (
                workspace.quickNotes.map((note) => (
                  <Link
                    className={selectedQuickNote?.id === note.id ? "note-card active" : "note-card"}
                    href={`${workspaceBasePath}?mode=notes&account=${encodeURIComponent(workspace.activeAccountCode)}&note=${encodeURIComponent(note.id)}`}
                    key={note.id}
                  >
                    <div>
                      <span className="status-pill">{quickNoteStatusText(note.status)}</span>
                      {note.convertedCount > 0 ? <span className="status-pill">{note.convertedCount} строк</span> : null}
                      <small>{formatDateTime(note.updatedAt)}</small>
                    </div>
                    <p>{note.body}</p>
                  </Link>
                ))
              ) : (
                <div className="empty-state inline-empty">
                  <h2>Заметок пока нет</h2>
                  <p>Напишите короткую рабочую запись и сохраните ее как черновик или отправьте на проверку.</p>
                </div>
              )}
            </div>
          </section>
        ) : null}
        {mode === "reports" ? (
          <section className="workspace-mode-panel" aria-label="Отчеты">
            <div className="mode-title">
              <div>
                <h2>Отчеты</h2>
                <p>Здесь будут лежать созданные сводки и версии для отправки.</p>
              </div>
              <small>{workspace.reportSnapshots.length} отчетов</small>
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
