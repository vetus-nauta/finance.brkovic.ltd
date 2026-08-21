"use server";

import { createHash } from "node:crypto";
import { redirect } from "next/navigation";
import { routes } from "@/lib/routes";
import { createAdminClient } from "@/lib/supabase/admin";
import { createClient } from "@/lib/supabase/server";

type InvitationRow = {
  id: string;
  organization_id: string;
  workspace_id: string;
  email: string;
  role_code: string;
  status: string;
  expires_at: string;
};

type WorkspaceNameRow = {
  name: string;
};

function tokenHash(token: string) {
  return createHash("sha256").update(token).digest("hex");
}

function inviteRedirect(status: string): never {
  redirect(`${routes.hall}?inviteStatus=${encodeURIComponent(status)}`);
}

export async function acceptInvitation(token: string) {
  const normalizedToken = String(token || "").trim();

  if (!normalizedToken) {
    inviteRedirect("invalid");
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;
  const userEmail = typeof claims?.claims?.email === "string" ? claims.claims.email.toLowerCase() : "";

  if (typeof userId !== "string" || !userEmail) {
    redirect(`${routes.invite}/${encodeURIComponent(normalizedToken)}?inviteStatus=auth`);
  }

  const admin = createAdminClient();
  const { data: invitation, error } = await admin
    .from("invitations")
    .select("id, organization_id, workspace_id, email, role_code, status, expires_at")
    .eq("token_hash", tokenHash(normalizedToken))
    .maybeSingle<InvitationRow>();

  if (error || !invitation) {
    inviteRedirect("not-found");
  }

  if (invitation.status !== "pending") {
    inviteRedirect("used");
  }

  if (new Date(invitation.expires_at).getTime() < Date.now()) {
    await admin
      .from("invitations")
      .update({ status: "expired" })
      .eq("id", invitation.id)
      .eq("status", "pending");
    inviteRedirect("expired");
  }

  if (invitation.email.toLowerCase() !== userEmail) {
    redirect(`${routes.invite}/${encodeURIComponent(normalizedToken)}?inviteStatus=email`);
  }

  const { data: workspace } = await admin
    .from("workspaces")
    .select("name")
    .eq("id", invitation.workspace_id)
    .maybeSingle<WorkspaceNameRow>();

  const { data: existingMembership } = await admin
    .from("memberships")
    .select("id")
    .eq("workspace_id", invitation.workspace_id)
    .eq("user_id", userId)
    .maybeSingle<{ id: string }>();

  if (!existingMembership) {
    const { error: membershipError } = await admin.from("memberships").insert({
      organization_id: invitation.organization_id,
      workspace_id: invitation.workspace_id,
      user_id: userId,
      role_code: invitation.role_code,
      status: "active",
      access_scope: invitation.role_code === "employee" ? "own_reports" : "workspace",
      invited_at: new Date().toISOString(),
      accepted_at: new Date().toISOString()
    });

    if (membershipError) {
      inviteRedirect("membership");
    }
  }

  await admin
    .from("invitations")
    .update({
      status: "accepted",
      accepted_by: userId,
      accepted_at: new Date().toISOString()
    })
    .eq("id", invitation.id)
    .eq("status", "pending");

  await admin.from("audit_log").insert({
    organization_id: invitation.organization_id,
    workspace_id: invitation.workspace_id,
    actor_user_id: userId,
    event_type: "invitation.accepted",
    entity_type: "invitation",
    entity_id: invitation.id,
    after_data: {
      email: invitation.email,
      role_code: invitation.role_code,
      workspace_name: workspace?.name ?? null
    },
    metadata: { source: "invite_link" }
  });

  redirect(`${routes.hall}?inviteStatus=accepted`);
}
