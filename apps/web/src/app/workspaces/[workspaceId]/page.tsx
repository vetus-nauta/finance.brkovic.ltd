import { notFound } from "next/navigation";
import { getWorkspaceDetails, roleLabels } from "@/lib/workspace-data";

const foundationBlocks = [
  {
    title: "Оперативный журнал",
    text: "Рабочая лента записей, корректировок и закрытых отчетных периодов."
  },
  {
    title: "Сводка",
    text: "Итоги считаются из записей пространства. Ручные итоговые цифры здесь не живут."
  },
  {
    title: "Заметки",
    text: "Быстрые записи передаются Smith на разбор и подтверждение перед переносом в журнал."
  },
  {
    title: "Отчеты сотрудников",
    text: "Выдача под отчет, принятие расходов, возвраты и возмещение перерасхода."
  }
];

type WorkspacePageProps = {
  params: Promise<{
    workspaceId: string;
  }>;
};

export default async function WorkspacePage({ params }: WorkspacePageProps) {
  const { workspaceId } = await params;
  const workspace = await getWorkspaceDetails(workspaceId);

  if (!workspace) {
    notFound();
  }

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
              <button
                className={account.account_type === "cash" ? "active" : undefined}
                type="button"
                key={account.id}
              >
                {account.label}
              </button>
            ))
          ) : (
            <>
              <button className="active" type="button">
                Кеш
              </button>
              <button type="button">Карта</button>
            </>
          )}
          <button type="button">Заметки</button>
          <button type="button">Отчеты</button>
        </aside>
        <div className="workspace-main">
          {foundationBlocks.map((block) => (
            <article className="panel" key={block.title}>
              <h2>{block.title}</h2>
              <p>{block.text}</p>
            </article>
          ))}
        </div>
      </section>
    </main>
  );
}
