import Link from "next/link";
import { hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

async function getSessionState() {
  if (!hasSupabasePublicEnv()) {
    return { email: null, error: "Supabase env не заполнен." };
  }

  const supabase = await createClient();
  const { data, error } = await supabase.auth.getClaims();

  return {
    email: data?.claims?.email ?? null,
    error: error?.message ?? null
  };
}

export default async function HallPage() {
  const session = await getSessionState();

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
      ) : (
        <section className="grid">
          <article className="panel workspace-card">
            <p className="eyebrow">Владелец</p>
            <h2>Claudia Z</h2>
            <p>Будущий боевой workspace после подтвержденной миграции.</p>
            <Link className="primary-button" href={routes.workspaces}>
              Открыть
            </Link>
          </article>
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
      )}
    </main>
  );
}
