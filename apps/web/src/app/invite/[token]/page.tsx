import Link from "next/link";
import { createHash } from "node:crypto";
import { AuthForm } from "@/components/AuthForm";
import { routes } from "@/lib/routes";
import { createAdminClient, hasSupabaseAdminEnv } from "@/lib/supabase/admin";
import { createClient } from "@/lib/supabase/server";
import { acceptInvitation } from "./actions";

type InvitePageProps = {
  params: Promise<{ token: string }>;
  searchParams: Promise<{ inviteStatus?: string }>;
};

type InvitationPreview = {
  email: string;
  role_code: string;
  status: string;
  expires_at: string;
  workspaces: {
    name: string;
    workspace_type: string;
  } | null;
};

function tokenHash(token: string) {
  return createHash("sha256").update(token).digest("hex");
}

function roleLabel(roleCode: string) {
  const labels: Record<string, string> = {
    employee: "Сотрудник",
    viewer: "Только просмотр",
    finance: "Финансист",
    admin: "Администратор"
  };

  return labels[roleCode] ?? roleCode;
}

function statusText(status?: string) {
  const labels: Record<string, string> = {
    auth: "Сначала войдите по email, затем подтвердите приглашение.",
    email: "Это приглашение выписано на другой email.",
    used: "Это приглашение уже использовано или отменено.",
    expired: "Срок действия приглашения истек.",
    membership: "Не удалось создать участие в пространстве.",
    config: "Серверная настройка приглашений еще не подключена. Администратор уже видит, что нужно исправить.",
    "not-found": "Приглашение не найдено."
  };

  return status ? labels[status] ?? null : null;
}

async function getUserEmail() {
  try {
    const supabase = await createClient();
    const { data } = await supabase.auth.getClaims();
    return typeof data?.claims?.email === "string" ? data.claims.email.toLowerCase() : null;
  } catch {
    return null;
  }
}

async function getPreview(token: string) {
  if (!hasSupabaseAdminEnv()) {
    return { data: null, configMissing: true };
  }

  try {
    const admin = createAdminClient();
    const { data } = await admin
      .from("invitations")
      .select("email, role_code, status, expires_at, workspaces(name, workspace_type)")
      .eq("token_hash", tokenHash(token))
      .maybeSingle<InvitationPreview>();

    return { data, configMissing: false };
  } catch {
    return { data: null, configMissing: true };
  }
}

export default async function InvitePage({ params, searchParams }: InvitePageProps) {
  const { token } = await params;
  const query = await searchParams;
  const previewState = await getPreview(token);
  const preview = previewState.data;
  const email = await getUserEmail();
  const message = statusText(query.inviteStatus) ?? (previewState.configMissing ? statusText("config") : null);
  const nextPath = `${routes.invite}/${encodeURIComponent(token)}`;

  return (
    <main className="page compact-page">
      <section className="panel invite-accept-panel">
        <p className="eyebrow">Приглашение</p>
        <h1>{preview?.workspaces?.name ?? "Рабочее пространство FinDesk"}</h1>
        {preview ? (
          <p>
            Роль: <strong>{roleLabel(preview.role_code)}</strong>. Email приглашения:{" "}
            <strong>{preview.email}</strong>.
          </p>
        ) : (
          <p>Ссылка не найдена или уже недоступна.</p>
        )}

        {message ? <p className="form-note error">{message}</p> : null}

        {!email ? (
          <div className="auth-panel invite-auth-panel">
            <h2>Войдите, чтобы принять приглашение</h2>
            <AuthForm nextPath={nextPath} />
          </div>
        ) : preview?.status === "pending" ? (
          <form action={acceptInvitation.bind(null, token)} className="button-row">
            <button className="primary-button" type="submit">
              Принять приглашение
            </button>
            <Link className="ghost-button" href={routes.hall}>
              В холл
            </Link>
          </form>
        ) : (
          <div className="button-row">
            <Link className="primary-button" href={routes.hall}>
              В холл
            </Link>
          </div>
        )}
      </section>
    </main>
  );
}
