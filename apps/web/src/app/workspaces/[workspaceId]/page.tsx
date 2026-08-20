import { notFound } from "next/navigation";
import Link from "next/link";
import { createOperationalEntry } from "./actions";
import { getWorkspaceDetails, roleLabels, workspacePath } from "@/lib/workspace-data";

type WorkspacePageProps = {
  params: Promise<{
    workspaceId: string;
  }>;
  searchParams?: Promise<{
    account?: string;
    entry?: string;
  }>;
};

function formatAmount(amount: number | null, direction: string | null) {
  if (amount === null) {
    return "—";
  }

  const value = new Intl.NumberFormat("ru-RU", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount);

  return `${direction === "income" ? "+" : "-"}${value} €`;
}

function entryStatusText(status?: string) {
  switch (status) {
    case "saved":
      return "Запись сохранена.";
    case "amount":
      return "Начните запись с суммы: например, -350 продукты или +1000 от судовладельца.";
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

function reviewStatusText(status: string | null) {
  return status === "review" ? "проверить" : "принято";
}

export default async function WorkspacePage({ params, searchParams }: WorkspacePageProps) {
  const { workspaceId } = await params;
  const query = searchParams ? await searchParams : {};
  const workspace = await getWorkspaceDetails(workspaceId, query.account);

  if (!workspace) {
    notFound();
  }

  const entryAction = createOperationalEntry.bind(null, workspace.id);
  const today = new Date().toISOString().slice(0, 10);
  const statusText = entryStatusText(query.entry);

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
                className={workspace.activeAccountCode === account.code ? "active" : undefined}
                aria-current={workspace.activeAccountCode === account.code ? "page" : undefined}
                href={`${workspacePath(workspace.id)}?account=${encodeURIComponent(account.code)}`}
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
          <span>Заметки</span>
          <span>Отчеты</span>
        </aside>
        <section className="operational-workspace" aria-label="Оперативный журнал и структурная проверка">
          <div className="synced-title-row">
            <div className="table-title">
              <h2>Оперативный журнал</h2>
              <small>{workspace.entries.length} записей</small>
            </div>
            <div className="table-title">
              <h2>Структурная проверка</h2>
              <small>та же строка</small>
            </div>
          </div>
          <div className="synced-table" role="table">
            <div className="synced-row synced-head" role="row">
              <div className="journal-cells">
                <span>№</span>
                <span>Описание</span>
                <span>Сумма</span>
              </div>
              <div className="check-cells">
                <span>№</span>
                <span>Дата</span>
                <span>Проверка</span>
              </div>
            </div>
            {workspace.entries.length > 0 ? (
              workspace.entries.map((entry) => (
                <div className="synced-row" role="row" key={entry.id}>
                  <div className="journal-cells">
                    <span>{entry.rowNo}</span>
                    <strong>{entry.rawText}</strong>
                    <span className={entry.direction === "income" ? "amount-income" : "amount-expense"}>
                      {formatAmount(entry.amount, entry.direction)}
                    </span>
                  </div>
                  <div className="check-cells">
                    <span>{entry.rowNo}</span>
                    <span>{entry.occurredOn}</span>
                    <span className={entry.reviewStatus === "review" ? "status-pill attention" : "status-pill"}>
                      {reviewStatusText(entry.reviewStatus)}
                    </span>
                  </div>
                </div>
              ))
            ) : (
              <div className="synced-row empty-synced-row" role="row">
                <div className="journal-cells">
                  <div className="ledger-empty">Пока нет записей по выбранному счету.</div>
                </div>
                <div className="check-cells">
                  <div className="ledger-empty">Проверка появится вместе с первой записью.</div>
                </div>
              </div>
            )}
          </div>
        </section>
      </section>

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
    </main>
  );
}
