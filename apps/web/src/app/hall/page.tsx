import Link from "next/link";
import { hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";
import { listUserWorkspaces, roleLabels, workspacePath } from "@/lib/workspace-data";
import { createWorkspaceInvitation } from "./actions";

type HallPageProps = {
  searchParams: Promise<{
    inviteStatus?: string;
    workspaceId?: string;
    workspaceName?: string;
    inviteEmail?: string;
    inviteUrl?: string;
  }>;
};

async function getSessionState() {
  if (!hasSupabasePublicEnv()) {
    return { email: null, error: "Supabase env не заполнен." };
  }

  try {
    const supabase = await createClient();
    const { data, error } = await supabase.auth.getClaims();

    return {
      email: data?.claims?.email ?? null,
      error: error?.message ?? null
    };
  } catch (error) {
    return {
      email: null,
      error: error instanceof Error ? error.message : "Не удалось проверить сессию."
    };
  }
}

function inviteStatusText(status?: string) {
  const messages: Record<string, string> = {
    email: "Укажите email сотрудника.",
    role: "Роль приглашения не распознана.",
    auth: "Нужно войти, чтобы создать приглашение.",
    workspace: "Пространство не найдено или недоступно.",
    create: "Не удалось создать приглашение. Проверьте роль и права доступа.",
    accepted: "Приглашение принято. Пространство добавлено в холл."
  };

  return status ? messages[status] ?? null : null;
}

export default async function HallPage({ searchParams }: HallPageProps) {
  const query = await searchParams;
  const session = await getSessionState();
  const workspaces = session.email ? await listUserWorkspaces() : [];
  const inviteStatus = inviteStatusText(query.inviteStatus);

  return (
    <main className="page compact-page">
      <section className="section-head">
        <div>
          <p className="eyebrow">Холл</p>
          <h1>Выбор пространства</h1>
          <p>Здесь пользователь входит в рабочие пространства и видит свою роль внутри каждого.</p>
        </div>
      </section>

      {!session.email ? (
        <section className="panel warning-panel">
          <h2>Нужен вход</h2>
          <p>{session.error ?? "Сессия не найдена. Вернитесь на экран авторизации."}</p>
          <Link className="primary-button" href={routes.home}>
            На вход
          </Link>
        </section>
      ) : workspaces.length > 0 ? (
        <>
          {inviteStatus && query.inviteStatus === "accepted" ? <p className="hall-status">{inviteStatus}</p> : null}
          <section className="grid">
            {workspaces.map((workspace) => (
            <article className="panel workspace-card" key={workspace.id}>
              <p className="eyebrow">{roleLabels[workspace.role] ?? workspace.role}</p>
              <h2>{workspace.name}</h2>
              <p>
                {workspace.type === "yacht" ? "Яхта" : "Пространство"} · {workspace.currency} ·{" "}
                {workspace.accessScope === "own_reports" ? "только свой отчет" : "рабочий доступ"}
              </p>
              <Link className="primary-button" href={workspacePath(workspace.id)}>
                Открыть
              </Link>
              {workspace.canManageMembers ? (
                <details className="invite-details">
                  <summary>Пригласить</summary>
                  <form action={createWorkspaceInvitation.bind(null, workspace.id)} className="invite-form">
                    <label>
                      <span>Email участника</span>
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
                    <button type="submit">Создать ссылку</button>
                  </form>
                  {query.inviteStatus === "created" && query.workspaceId === workspace.id && query.inviteUrl ? (
                    <div className="invite-result">
                      <span>Ссылка для {query.inviteEmail}</span>
                      <input readOnly value={query.inviteUrl} />
                      <small>Показывается один раз. После принятия приглашения пространство появится в холле участника.</small>
                    </div>
                  ) : null}
                  {inviteStatus && query.workspaceId === workspace.id ? <p className="form-note error">{inviteStatus}</p> : null}
                </details>
              ) : null}
            </article>
            ))}
            <article className="panel workspace-card muted-card">
            <p className="eyebrow">Сотрудник</p>
            <h2>Под отчет</h2>
            <p>Отдельный простой режим сотрудника без общей финансовой картины.</p>
            <button type="button" disabled>
              После API-команд
            </button>
            </article>
            <article className="panel workspace-card muted-card">
            <p className="eyebrow">Новый учет</p>
            <h2>Создать пространство</h2>
            <p>Создание пойдет через server command, membership и audit log.</p>
            <button type="button" disabled>
              После команд
            </button>
            </article>
          </section>
        </>
      ) : (
        <section className="panel empty-state">
          <p className="eyebrow">Нет доступных пространств</p>
          <h2>Холл готов, но членство еще не создано</h2>
          <p>
            После bootstrap/provision команды здесь появятся рабочие пространства пользователя.
            Статические карточки больше не показываются, чтобы не путать реальный доступ с макетом.
          </p>
          <button type="button" disabled>
            Создание пространства будет server command
          </button>
        </section>
      )}
    </main>
  );
}
