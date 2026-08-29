import Link from "next/link";
import type { CSSProperties } from "react";
import { hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";
import { listUserWorkspaces, roleLabels, type WorkspaceSummary, workspacePath } from "@/lib/workspace-data";

type HallPageProps = {
  searchParams: Promise<{
    inviteStatus?: string;
    workspaceId?: string;
    inviteEmail?: string;
    inviteUrl?: string;
  }>;
};

type WorkspaceTheme = {
  accent: string;
  icon: string;
  tone: string;
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

const workspaceThemes: Record<"ownerBlue" | "ownerGreen" | "employee", WorkspaceTheme> = {
  ownerBlue: { accent: "#1264ff", icon: "/assets/hall-new/icons/yacht_reference.jpg", tone: "blue" },
  ownerGreen: { accent: "#19c98a", icon: "/assets/hall-new/icons/analytics_reference.jpg", tone: "green" },
  employee: { accent: "#8b4dff", icon: "/assets/hall-new/icons/document_reference.jpg", tone: "violet" }
};

function workspaceTheme(roleTone: "owner" | "employee", index: number) {
  if (roleTone === "employee") {
    return workspaceThemes.employee;
  }

  return index % 2 === 0 ? workspaceThemes.ownerBlue : workspaceThemes.ownerGreen;
}

function workspaceKindLabel(type: string) {
  if (type === "yacht") {
    return "Яхта";
  }

  if (type === "home") {
    return "Дом";
  }

  if (type === "family") {
    return "Семья";
  }

  if (type === "work") {
    return "Работа";
  }

  return "Пространство";
}

function workspaceRoleTone(role: string): "owner" | "employee" {
  return role === "owner" || role === "admin" || role === "finance" ? "owner" : "employee";
}

function uniqueWorkspaces(workspaces: WorkspaceSummary[]) {
  const seen = new Set<string>();

  return workspaces.filter((workspace) => {
    if (seen.has(workspace.id)) {
      return false;
    }

    seen.add(workspace.id);
    return true;
  });
}

export default async function HallPage({ searchParams }: HallPageProps) {
  const query = await searchParams;
  const session = await getSessionState();
  const workspaces = session.email ? uniqueWorkspaces(await listUserWorkspaces()) : [];
  const inviteStatus = inviteStatusText(query.inviteStatus);

  return (
    <main className="page compact-page hall-page">
      <section className="hall-hero">
        <div className="hall-hero-copy">
          <p className="eyebrow">Холл FinDesk</p>
          <h1>Выбор пространства</h1>
          <p>Здесь пользователь входит в рабочие пространства и видит свою роль внутри каждого.</p>
        </div>
        <div className="hall-hero-art" aria-hidden="true" />
        <div className="hall-hero-actions">
          <button className="primary-action hall-create-button" type="button" aria-disabled="true">
            Создать пространство
          </button>
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
          <section className="hall-grid" aria-label="Рабочие пространства">
            {workspaces.map((workspace, index) => {
              const canManage = Boolean(workspace.canManageMembers);
              const roleTone = workspaceRoleTone(workspace.role);
              const theme = workspaceTheme(roleTone, index);

              return (
                <article
                  className={`workspace-card-v2 workspace-card-${roleTone} workspace-card-${theme.tone}`}
                  key={workspace.id}
                  style={
                    {
                      "--workspace-accent": theme.accent,
                      "--workspace-icon": `url(${theme.icon})`
                    } as CSSProperties
                  }
                >
                  <div className="workspace-card-top">
                    <span className="workspace-role">{roleLabels[workspace.role] ?? workspace.role}</span>
                    <span className="workspace-card-icon" aria-hidden="true">
                      <img src={theme.icon} alt="" width={34} height={34} />
                    </span>
                  </div>
                  <div className="workspace-card-main">
                    <h2>{workspace.name}</h2>
                    <p>
                      {workspaceKindLabel(workspace.type)} · {workspace.currency} ·{" "}
                      {workspace.accessScope === "own_reports" ? "личный отчет" : "полный доступ"}
                    </p>
                  </div>
                  <div className="workspace-card-actions">
                    <Link className="workspace-open-link" href={workspacePath(workspace.id)}>
                      <span>Открыть</span>
                      <img src="/assets/hall/icons/ui/arrow.svg" alt="" width={16} height={16} />
                    </Link>
                    {canManage ? (
                      <button type="button" aria-disabled="true">
                        <img src="/assets/hall/icons/ui/user-add.svg" alt="" width={16} height={16} />
                        <span>Пригласить</span>
                      </button>
                    ) : null}
                  </div>
                  {query.inviteStatus === "created" && query.workspaceId === workspace.id && query.inviteUrl ? (
                    <div className="invite-result">
                      <span>Ссылка для {query.inviteEmail}</span>
                      <input readOnly value={query.inviteUrl} />
                      <small>Показывается один раз. После принятия приглашения пространство появится в холле участника.</small>
                    </div>
                  ) : null}
                  {inviteStatus && query.workspaceId === workspace.id ? <p className="form-note error">{inviteStatus}</p> : null}
                </article>
              );
            })}
            <article
              className="workspace-card-v2 workspace-card-new"
              style={
                {
                  "--workspace-accent": "#0b63f6",
                  "--workspace-icon": "url(/assets/hall-new/icons/add_space_reference.jpg)"
                } as CSSProperties
              }
            >
              <div className="workspace-card-top">
                <span className="workspace-role">Новый учет</span>
                <span className="workspace-card-icon" aria-hidden="true">
                  <img src="/assets/hall-new/icons/add_space_reference.jpg" alt="" width={34} height={34} />
                </span>
              </div>
              <div className="workspace-card-main">
                <h2>Создать пространство</h2>
                <p>Новое рабочее пространство с ролями, доступом и отчетами.</p>
              </div>
              <div className="workspace-card-actions">
                <button className="workspace-open-link" type="button" aria-disabled="true">
                  <span>Создать</span>
                  <img src="/assets/hall/icons/ui/arrow.svg" alt="" width={16} height={16} />
                </button>
              </div>
            </article>
          </section>
        </>
      ) : (
        <section className="hall-empty-card">
          <div className="hall-empty-copy">
            <p className="eyebrow">Первое пространство</p>
            <h2>Создайте рабочее место FinDesk</h2>
            <p>Начните с яхты, компании или личного учета. После создания здесь появится карточка входа.</p>
            <button className="primary-action" type="button" aria-disabled="true">
              Создать пространство
            </button>
          </div>
          <div className="hall-empty-art" aria-hidden="true" />
        </section>
      )}
      <p className="hall-footer">© 2024 FinDesk. Все права защищены.</p>
    </main>
  );
}
