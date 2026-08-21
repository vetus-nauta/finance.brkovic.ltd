"use server";

import { createHash, randomBytes } from "node:crypto";
import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { getPublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

type WorkspaceForInvite = {
  id: string;
  organization_id: string;
  name: string;
};

function normalizeEmail(value: FormDataEntryValue | null) {
  return String(value || "").trim().toLowerCase();
}

function tokenHash(token: string) {
  return createHash("sha256").update(token).digest("hex");
}

function inviteUrl(token: string) {
  const appDomain = getPublicEnv().appDomain.replace(/\/+$/, "");
  return `${appDomain}${routes.invite}/${encodeURIComponent(token)}`;
}

function hallRedirect(params: Record<string, string>): never {
  const query = new URLSearchParams(params);
  redirect(`${routes.hall}?${query.toString()}`);
}

function inviteRedirect(formData: FormData, params: Record<string, string>): never {
  if (formData.get("returnTo") === "workspace" && params.workspaceId) {
    const query = new URLSearchParams({
      mode: "team",
      status: params.inviteStatus ?? "",
      ...(params.inviteEmail ? { inviteEmail: params.inviteEmail } : {}),
      ...(params.inviteUrl ? { inviteUrl: params.inviteUrl } : {})
    });

    redirect(`${routes.workspaces}/${encodeURIComponent(params.workspaceId)}?${query.toString()}`);
  }

  hallRedirect(params);
}

export async function createWorkspaceInvitation(workspaceId: string, formData: FormData) {
  const email = normalizeEmail(formData.get("email"));
  const roleCode = String(formData.get("roleCode") || "employee").trim();
  const allowedRoles = new Set(["employee", "viewer", "finance", "admin"]);

  if (!email || !email.includes("@")) {
    inviteRedirect(formData, { inviteStatus: "email", workspaceId });
  }

  if (!allowedRoles.has(roleCode)) {
    inviteRedirect(formData, { inviteStatus: "role", workspaceId });
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    inviteRedirect(formData, { inviteStatus: "auth", workspaceId });
  }

  const { data: workspace, error: workspaceError } = await supabase
    .from("workspaces")
    .select("id, organization_id, name")
    .eq("id", workspaceId)
    .eq("status", "active")
    .maybeSingle<WorkspaceForInvite>();

  if (workspaceError || !workspace) {
    inviteRedirect(formData, { inviteStatus: "workspace", workspaceId });
  }

  const token = randomBytes(32).toString("base64url");
  const expiresAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString();
  const { data: invitation, error } = await supabase
    .from("invitations")
    .insert({
      organization_id: workspace.organization_id,
      workspace_id: workspace.id,
      email,
      role_code: roleCode,
      status: "pending",
      token_hash: tokenHash(token),
      invited_by: userId,
      expires_at: expiresAt
    })
    .select("id")
    .single<{ id: string }>();

  if (error || !invitation) {
    inviteRedirect(formData, { inviteStatus: "create", workspaceId });
  }

  await supabase.from("audit_log").insert({
    organization_id: workspace.organization_id,
    workspace_id: workspace.id,
    actor_user_id: userId,
    event_type: "invitation.created",
    entity_type: "invitation",
    entity_id: invitation.id,
    after_data: { email, role_code: roleCode, expires_at: expiresAt },
    metadata: { source: "hall" }
  });

  revalidatePath(routes.hall);
  revalidatePath(`${routes.workspaces}/${workspace.id}`);
  inviteRedirect(formData, {
    inviteStatus: "created",
    workspaceId: workspace.id,
    workspaceName: workspace.name,
    inviteEmail: email,
    inviteUrl: inviteUrl(token)
  });
}
