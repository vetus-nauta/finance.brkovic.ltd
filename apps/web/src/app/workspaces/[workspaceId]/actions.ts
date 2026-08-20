"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { createClient } from "@/lib/supabase/server";
import { workspacePath } from "@/lib/workspace-data";

type AccountForEntry = {
  id: string;
  organization_id: string;
  workspace_id: string;
  account_type: string;
  currency_code: string;
};

type LatestRow = {
  row_no: number | null;
};

type WorkspaceForWrite = {
  id: string;
  organization_id: string;
};

type QuickNoteForWrite = {
  id: string;
  status: string;
};

function parseEntry(rawText: string) {
  const match = rawText.trim().match(/^([+-])?\s*(\d+(?:[.,]\d{1,2})?)/);

  if (!match) {
    return null;
  }

  const sign = match[1] ?? "-";
  const amount = Number.parseFloat(match[2].replace(",", "."));

  if (!Number.isFinite(amount) || amount <= 0) {
    return null;
  }

  return {
    amount: amount.toFixed(2),
    direction: sign === "+" ? "income" : "expense"
  };
}

function redirectWithStatus(workspaceId: string, accountCode: string, status: string): never {
  redirect(`${workspacePath(workspaceId)}?account=${encodeURIComponent(accountCode)}&entry=${encodeURIComponent(status)}`);
}

function redirectToMode(workspaceId: string, mode: string, status: string, extraParams: Record<string, string> = {}): never {
  const params = new URLSearchParams({
    mode,
    status,
    ...extraParams
  });

  redirect(`${workspacePath(workspaceId)}?${params.toString()}`);
}

function revalidateWorkspace(workspaceId: string) {
  revalidatePath(workspacePath(workspaceId));
}

async function getWritableWorkspace(workspaceId: string) {
  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    return { supabase, userId: null, workspace: null };
  }

  const { data: workspace, error } = await supabase
    .from("workspaces")
    .select("id, organization_id")
    .eq("id", workspaceId)
    .eq("status", "active")
    .maybeSingle<WorkspaceForWrite>();

  if (error || !workspace) {
    return { supabase, userId, workspace: null };
  }

  return { supabase, userId, workspace };
}

export async function createOperationalEntry(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const occurredOn = String(formData.get("occurredOn") || "").trim();
  const rawText = String(formData.get("rawText") || "").trim();

  if (!occurredOn || !rawText) {
    redirectWithStatus(workspaceId, accountCode, "missing");
  }

  const parsed = parseEntry(rawText);

  if (!parsed) {
    redirectWithStatus(workspaceId, accountCode, "amount");
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    redirectWithStatus(workspaceId, accountCode, "auth");
  }

  const { data: account, error: accountError } = await supabase
    .from("accounts")
    .select("id, organization_id, workspace_id, account_type, currency_code")
    .eq("workspace_id", workspaceId)
    .eq("code", accountCode)
    .eq("is_active", true)
    .maybeSingle<AccountForEntry>();

  if (accountError || !account) {
    redirectWithStatus(workspaceId, accountCode, "account");
  }

  const { data: latestRows, error: rowError } = await supabase
    .from("transactions")
    .select("row_no")
    .eq("workspace_id", workspaceId)
    .order("row_no", { ascending: false, nullsFirst: false })
    .limit(1)
    .returns<LatestRow[]>();

  if (rowError) {
    redirectWithStatus(workspaceId, accountCode, "save");
  }

  const rowNo = (latestRows?.[0]?.row_no ?? 0) + 1;

  const { data: transaction, error: transactionError } = await supabase
    .from("transactions")
    .insert({
      organization_id: account.organization_id,
      workspace_id: workspaceId,
      account_id: account.id,
      source_type: "manual",
      occurred_on: occurredOn,
      row_no: rowNo,
      raw_text: rawText,
      status: "open",
      created_by: userId,
      metadata: {
        account_code: accountCode,
        parser: "foundation_manual_amount_v1"
      }
    })
    .select("id")
    .single<{ id: string }>();

  if (transactionError || !transaction) {
    redirectWithStatus(workspaceId, accountCode, "save");
  }

  const { error: ledgerError } = await supabase.from("ledger_entries").insert({
    organization_id: account.organization_id,
    workspace_id: workspaceId,
    transaction_id: transaction.id,
    account_id: account.id,
    direction: parsed.direction,
    amount: parsed.amount,
    currency_code: account.currency_code,
    review_status: "accepted",
    metadata: {
      account_code: accountCode,
      source: "operational_entry_form"
    }
  });

  if (ledgerError) {
    redirectWithStatus(workspaceId, accountCode, "save");
  }

  redirectWithStatus(workspaceId, accountCode, "saved");
}

export async function saveQuickNoteDraft(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const noteId = String(formData.get("noteId") || "").trim();
  const body = String(formData.get("body") || "").trim();

  if (!body) {
    redirectToMode(workspaceId, "notes", "note-empty", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  const { supabase, userId, workspace } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToMode(workspaceId, "notes", "auth", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (!workspace) {
    redirectToMode(workspaceId, "notes", "workspace", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (noteId) {
    const { data: existingNote, error: existingError } = await supabase
      .from("quick_notes")
      .select("id, status")
      .eq("id", noteId)
      .eq("workspace_id", workspaceId)
      .maybeSingle<QuickNoteForWrite>();

    if (existingError) {
      redirectToMode(workspaceId, "notes", "note-save", { account: accountCode, note: noteId });
    }

    if (existingNote?.status === "draft") {
      const { error: updateError } = await supabase
        .from("quick_notes")
        .update({ body, status: "draft" })
        .eq("id", noteId)
        .eq("workspace_id", workspaceId);

      if (updateError) {
        redirectToMode(workspaceId, "notes", "note-save", { account: accountCode, note: noteId });
      }

      revalidateWorkspace(workspaceId);
      redirectToMode(workspaceId, "notes", "note-saved", { account: accountCode, note: noteId });
    }
  }

  const { data: insertedNote, error } = await supabase
    .from("quick_notes")
    .insert({
      organization_id: workspace.organization_id,
      workspace_id: workspace.id,
      author_user_id: userId,
      body,
      status: "draft"
    })
    .select("id")
    .single<{ id: string }>();

  if (error || !insertedNote) {
    redirectToMode(workspaceId, "notes", "note-save", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "notes", "note-saved", { account: accountCode, note: insertedNote.id });
}

export async function submitQuickNoteToSmith(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const noteId = String(formData.get("noteId") || "").trim();
  const body = String(formData.get("body") || "").trim();

  if (!body) {
    redirectToMode(workspaceId, "notes", "note-empty", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  const { supabase, userId, workspace } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToMode(workspaceId, "notes", "auth", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (!workspace) {
    redirectToMode(workspaceId, "notes", "workspace", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (noteId) {
    const { data: existingNote, error: existingError } = await supabase
      .from("quick_notes")
      .select("id, status")
      .eq("id", noteId)
      .eq("workspace_id", workspaceId)
      .maybeSingle<QuickNoteForWrite>();

    if (existingError) {
      redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, note: noteId });
    }

    if (existingNote?.status === "draft") {
      const { error: updateError } = await supabase
        .from("quick_notes")
        .update({ body, status: "submitted_to_smith" })
        .eq("id", noteId)
        .eq("workspace_id", workspaceId);

      if (updateError) {
        redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, note: noteId });
      }

      revalidateWorkspace(workspaceId);
      redirectToMode(workspaceId, "notes", "note-submitted", { account: accountCode });
    }
  }

  const { error } = await supabase.from("quick_notes").insert({
    organization_id: workspace.organization_id,
    workspace_id: workspace.id,
    author_user_id: userId,
    body,
    status: "submitted_to_smith"
  });

  if (error) {
    redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "notes", "note-submitted", { account: accountCode });
}

export async function deleteQuickNote(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const noteId = String(formData.get("noteId") || "").trim();

  if (!noteId) {
    redirectToMode(workspaceId, "notes", "note-missing", { account: accountCode });
  }

  const { supabase, userId } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToMode(workspaceId, "notes", "auth", { account: accountCode });
  }

  const { error } = await supabase
    .from("quick_notes")
    .update({ status: "void" })
    .eq("id", noteId)
    .eq("workspace_id", workspaceId);

  if (error) {
    redirectToMode(workspaceId, "notes", "note-delete", { account: accountCode });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "notes", "note-deleted", { account: accountCode });
}
