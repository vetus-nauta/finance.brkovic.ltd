"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { isSmithCategoryCode, smithReviewReasonForCategory } from "@/lib/smith-categories";
import { createClient } from "@/lib/supabase/server";
import { workspacePath } from "@/lib/workspace-data";

type WorkspaceForWrite = {
  id: string;
  organization_id: string;
};

type QuickNoteForWrite = {
  id: string;
  status: string;
};

type CreateOperationalEntryResult = {
  transaction_id: string;
  ledger_entry_id: string | null;
  row_no: number;
  counted: boolean;
  transaction_status: string;
  review_status: string | null;
};

type UpdateOperationalEntryResult = CreateOperationalEntryResult;

type VoidOperationalEntryResult = {
  transaction_id: string;
  row_no: number;
  voided: boolean;
};

type PrepareQuickNoteResult = {
  quick_note_id: string;
  proposal_count: number;
  review_count: number;
  duplicate_count: number;
};

type ConvertSmithProposalResult = {
  quick_note_id: string;
  transaction_ids: string[];
  converted_count: number;
  review_count: number;
  rejected_count: number;
};

type SupabaseRpcClient = {
  rpc: (
    functionName: string,
    args: Record<string, unknown>
  ) => {
    returns: <T>() => Promise<{
      data: T | null;
      error: { message: string } | null;
    }>;
  };
};

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

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("create_operational_entry", {
      p_workspace_id: workspaceId,
      p_account_code: accountCode,
      p_occurred_on: occurredOn,
      p_raw_text: rawText,
      p_source_type: "manual",
      p_source_channel: "manual",
      p_source_language: "ru",
      p_source_id: null,
      p_source_ref: {},
      p_metadata: {}
    })
    .returns<CreateOperationalEntryResult[]>();

  if (error) {
    const message = error.message;

    if (message.includes("auth_required") || message.includes("ledger_write_required")) {
      redirectWithStatus(workspaceId, accountCode, "auth");
    }

    if (message.includes("account_not_found")) {
      redirectWithStatus(workspaceId, accountCode, "account");
    }

    if (message.includes("manual_card_income_blocked")) {
      redirectWithStatus(workspaceId, accountCode, "card-income");
    }

    if (message.includes("amount_must_be_positive")) {
      redirectWithStatus(workspaceId, accountCode, "amount");
    }

    redirectWithStatus(workspaceId, accountCode, "save");
  }

  const result = data?.[0];

  if (!result) {
    redirectWithStatus(workspaceId, accountCode, "save");
  }

  redirectWithStatus(workspaceId, accountCode, result.counted ? "saved" : "review");
}

export async function updateOperationalEntry(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const transactionId = String(formData.get("transactionId") || "").trim();
  const occurredOn = String(formData.get("occurredOn") || "").trim();
  const rawText = String(formData.get("rawText") || "").trim();

  if (!transactionId || !occurredOn || !rawText) {
    redirectWithStatus(workspaceId, accountCode, "missing");
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("update_operational_entry", {
      p_transaction_id: transactionId,
      p_account_code: accountCode,
      p_occurred_on: occurredOn,
      p_raw_text: rawText,
      p_source_channel: "manual",
      p_source_language: "ru",
      p_metadata: {}
    })
    .returns<UpdateOperationalEntryResult[]>();

  if (error) {
    const message = error.message;

    if (message.includes("auth_required") || message.includes("ledger_write_required")) {
      redirectWithStatus(workspaceId, accountCode, "auth");
    }

    if (message.includes("transaction_not_found")) {
      redirectWithStatus(workspaceId, accountCode, "entry-not-found");
    }

    if (message.includes("account_not_found")) {
      redirectWithStatus(workspaceId, accountCode, "account");
    }

    if (message.includes("manual_card_income_blocked")) {
      redirectWithStatus(workspaceId, accountCode, "card-income");
    }

    if (message.includes("amount_must_be_positive")) {
      redirectWithStatus(workspaceId, accountCode, "amount");
    }

    redirectWithStatus(workspaceId, accountCode, "update");
  }

  const result = data?.[0];

  if (!result) {
    redirectWithStatus(workspaceId, accountCode, "update");
  }

  redirectWithStatus(workspaceId, accountCode, result.counted ? "updated" : "review");
}

export async function deleteOperationalEntry(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const transactionId = String(formData.get("transactionId") || "").trim();

  if (!transactionId) {
    redirectWithStatus(workspaceId, accountCode, "missing");
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("void_operational_entry", {
      p_transaction_id: transactionId
    })
    .returns<VoidOperationalEntryResult[]>();

  if (error) {
    const message = error.message;

    if (message.includes("auth_required") || message.includes("ledger_write_required")) {
      redirectWithStatus(workspaceId, accountCode, "auth");
    }

    if (message.includes("transaction_not_found")) {
      redirectWithStatus(workspaceId, accountCode, "entry-not-found");
    }

    redirectWithStatus(workspaceId, accountCode, "delete");
  }

  const result = data?.[0];

  if (!result?.voided) {
    redirectWithStatus(workspaceId, accountCode, "delete");
  }

  redirectWithStatus(workspaceId, accountCode, "deleted");
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

    if (existingNote?.status === "draft" || existingNote?.status === "submitted_to_smith") {
      const { error: updateError } = await supabase
        .from("quick_notes")
        .update({ body, status: "draft" })
        .eq("id", noteId)
        .eq("workspace_id", workspaceId);

      if (updateError) {
        redirectToMode(workspaceId, "notes", "note-save", { account: accountCode, note: noteId });
      }

      if (existingNote.status === "submitted_to_smith") {
        const { error: voidProposalsError } = await supabase
          .from("smith_entry_proposals")
          .update({ status: "void" })
          .eq("quick_note_id", noteId)
          .in("status", ["pending", "rejected"]);

        if (voidProposalsError) {
          redirectToMode(workspaceId, "notes", "note-save", { account: accountCode, note: noteId });
        }
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
  const occurredOn = String(formData.get("occurredOn") || "").trim();

  if (!body) {
    redirectToMode(workspaceId, "notes", "note-empty", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (!occurredOn) {
    redirectToMode(workspaceId, "notes", "note-date", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  const { supabase, userId, workspace } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToMode(workspaceId, "notes", "auth", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  if (!workspace) {
    redirectToMode(workspaceId, "notes", "workspace", { account: accountCode, ...(noteId ? { note: noteId } : {}) });
  }

  let quickNoteId = noteId;

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

    if (existingNote?.status === "draft" || existingNote?.status === "submitted_to_smith") {
      const { error: updateError } = await supabase
        .from("quick_notes")
        .update({ body, status: "draft" })
        .eq("id", noteId)
        .eq("workspace_id", workspaceId);

      if (updateError) {
        redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, note: noteId });
      }
    } else {
      redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, note: noteId });
    }
  }

  if (!quickNoteId) {
    const { data: insertedNote, error: insertError } = await supabase
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

    if (insertError || !insertedNote) {
      redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode });
    }

    quickNoteId = insertedNote.id;
  }

  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("prepare_quick_note_entry_proposals", {
      p_note_id: quickNoteId,
      p_account_code: accountCode,
      p_occurred_on: occurredOn,
      p_source_language: "ru"
    })
    .returns<PrepareQuickNoteResult[]>();

  if (error || !data?.[0]) {
    redirectToMode(workspaceId, "notes", "note-submit", { account: accountCode, note: quickNoteId });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "notes", "note-ready", {
    account: accountCode,
    note: quickNoteId,
    notesView: "transfer",
    lines: String(data[0].proposal_count),
    review: String(data[0].review_count)
  });
}

export async function convertSmithProposalsToEntries(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const noteId = String(formData.get("noteId") || "").trim();
  const proposalIds = formData
    .getAll("proposalId")
    .map((value) => String(value).trim())
    .filter(Boolean);

  if (!noteId) {
    redirectToMode(workspaceId, "notes", "note-missing", { account: accountCode });
  }

  if (proposalIds.length === 0) {
    redirectToMode(workspaceId, "notes", "note-select-lines", { account: accountCode, note: noteId });
  }

  const supabase = await createClient();

  for (const proposalId of proposalIds) {
    const categoryCode = String(formData.get(`categoryCode:${proposalId}`) || "").trim();

    if (!isSmithCategoryCode(categoryCode)) {
      redirectToMode(workspaceId, "notes", "note-convert", { account: accountCode, note: noteId });
    }

    const { error: categoryUpdateError } = await supabase
      .from("smith_entry_proposals")
      .update({
        candidate_category_code: categoryCode,
        review_reason: smithReviewReasonForCategory(categoryCode)
      })
      .eq("id", proposalId)
      .eq("quick_note_id", noteId)
      .eq("status", "pending");

    if (categoryUpdateError) {
      redirectToMode(workspaceId, "notes", "note-convert", { account: accountCode, note: noteId });
    }
  }

  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("convert_smith_entry_proposals", {
      p_note_id: noteId,
      p_proposal_ids: proposalIds
    })
    .returns<ConvertSmithProposalResult[]>();

  if (error || !data?.[0]) {
    redirectToMode(workspaceId, "notes", "note-convert", { account: accountCode, note: noteId });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "notes", "note-converted", {
    account: accountCode,
    lines: String(data[0].converted_count),
    newNote: "1",
    review: String(data[0].review_count)
  });
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
