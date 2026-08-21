import Link from "next/link";
import { redirect } from "next/navigation";
import { AuthForm } from "@/components/AuthForm";
import { getPublicEnv, hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

async function getUserEmail() {
  if (!hasSupabasePublicEnv()) {
    return null;
  }

  try {
    const supabase = await createClient();
    const { data } = await supabase.auth.getClaims();
    return data?.claims?.email ?? null;
  } catch {
    return null;
  }
}

type HomePageProps = {
  searchParams: Promise<{ invite?: string }>;
};

export default async function HomePage({ searchParams }: HomePageProps) {
  const query = await searchParams;
  if (query.invite) {
    redirect(`${routes.invite}/${encodeURIComponent(query.invite)}`);
  }

  const env = getPublicEnv();
  const email = await getUserEmail();

  return (
    <main className="page">
      <section className="hero">
        <div>
          <p className="eyebrow">Foundation-04</p>
          <h1>Чистый фундамент FinDesk для brkovic.app</h1>
          <p>
            Новый слой отделен от старого PHP runtime. Домен остается под вашим контролем в
            Namecheap, приложение готовится к Vercel, Supabase Auth, PostgreSQL/RLS и будущим
            мобильным клиентам.
          </p>
        </div>
        <div className="panel auth-panel">
          {email ? (
            <>
              <p className="eyebrow">Сессия активна</p>
              <h2>{email}</h2>
              <div className="button-row">
                <Link className="primary-button" href={routes.hall}>
                  Открыть холл
                </Link>
              </div>
            </>
          ) : (
            <>
              <p className="eyebrow">Вход</p>
              <h2>Авторизация по email</h2>
              <AuthForm />
            </>
          )}
        </div>
      </section>

      <section className="grid">
        <article className="panel">
          <p className="eyebrow">Домен</p>
          <h2>{env.appDomain}</h2>
          <p>Namecheap остается регистратором. DNS будет указывать на выбранный web runtime.</p>
        </article>
        <article className="panel">
          <p className="eyebrow">Backend</p>
          <h2>Supabase</h2>
          <p>PostgreSQL, Auth, private Storage и RLS являются целевым фундаментом продукта.</p>
        </article>
        <article className="panel">
          <p className="eyebrow">Legacy</p>
          <h2>Только источник миграции</h2>
          <p>Старые PHP/V2 файлы пока не углубляем. Новая разработка идет в `apps/web`.</p>
        </article>
      </section>
    </main>
  );
}
