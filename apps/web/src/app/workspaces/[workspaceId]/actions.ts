"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { isSmithCategoryCode, smithReviewReasonForCategory } from "@/lib/smith-categories";
import { createAdminClient, hasSupabaseAdminEnv } from "@/lib/supabase/admin";
import { createClient } from "@/lib/supabase/server";
import { workspacePath } from "@/lib/workspace-data";

type WorkspaceForWrite = {
  id: string;
  organization_id: string;
};

type AccountForWrite = {
  id: string;
  currency_code: string;
};

type CashAdvanceForAccept = {
  id: string;
  organization_id: string;
  workspace_id: string;
  issued_to: string;
  status: string;
};

type CashAdvanceForReport = CashAdvanceForAccept & {
  currency_code: string;
};

type ExpenseReportForWrite = {
  id: string;
  organization_id: string;
  workspace_id: string;
  cash_advance_id: string | null;
  submitted_by: string;
  status: string;
  total_amount: number | string;
  currency_code: string;
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

type CreateReportSnapshotResult = {
  report_snapshot_id: string;
  period_closure_id: string;
  included_count: number;
  review_count: number;
  income_total: number;
  expense_total: number;
  net_total: number;
};

type CreateReportPackageResult = {
  report_package_id: string;
  included_count: number;
};

type CreateReportExportVersionResult = {
  document_id: string;
  document_version_id: string;
  version_no: number;
  format: string;
  download_path: string;
};

type SetReportSnapshotDeliveryStatusResult = {
  report_snapshot_id: string;
  previous_status: string;
  status: string;
};

type SetReportPackageDeliveryStatusResult = {
  report_package_id: string;
  previous_status: string;
  status: string;
};

type ReturnReportSnapshotForRevisionResult = {
  report_snapshot_id: string;
  period_closure_id: string | null;
  status: string;
};

type CreateReportLockedCorrectionResult = {
  correction_id: string;
  original_transaction_id: string;
  correction_transaction_id: string;
  correction_row_no: number;
  counted: boolean;
  transaction_status: string;
  review_status: string | null;
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

function redirectToTeam(workspaceId: string, status: string): never {
  redirectToMode(workspaceId, "team", status);
}

function revalidateWorkspace(workspaceId: string) {
  revalidatePath(workspacePath(workspaceId));
}

function parseMoneyInput(value: FormDataEntryValue | null) {
  const normalized = String(value || "")
    .trim()
    .replace(/\s+/g, "")
    .replace(",", ".");
  const amount = Number(normalized);

  return Number.isFinite(amount) ? amount : 0;
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

export async function createAccountableOffer(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const employeeUserId = String(formData.get("employeeUserId") || "").trim();
  const amount = parseMoneyInput(formData.get("amount"));
  const purpose = String(formData.get("purpose") || "").trim();

  if (!employeeUserId || amount <= 0) {
    redirectToTeam(workspaceId, "accountable-missing");
  }

  const { supabase, userId, workspace } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToTeam(workspaceId, "auth");
  }

  if (!workspace) {
    redirectToTeam(workspaceId, "workspace");
  }

  if (!hasSupabaseAdminEnv()) {
    redirectToTeam(workspaceId, "accountable-config");
  }

  const { data: member, error: memberError } = await supabase
    .from("memberships")
    .select("user_id, role_code, access_scope, status")
    .eq("workspace_id", workspaceId)
    .eq("user_id", employeeUserId)
    .eq("status", "active")
    .not("accepted_at", "is", null)
    .maybeSingle<{ user_id: string; role_code: string; access_scope: string; status: string }>();

  if (memberError || !member || member.access_scope !== "own_reports") {
    redirectToTeam(workspaceId, "accountable-member");
  }

  const { data: account, error: accountError } = await supabase
    .from("accounts")
    .select("id, currency_code")
    .eq("workspace_id", workspaceId)
    .eq("code", accountCode)
    .eq("is_active", true)
    .maybeSingle<AccountForWrite>();

  if (accountError) {
    redirectToTeam(workspaceId, "accountable-create");
  }

  const { data: insertedAdvance, error } = await supabase
    .from("cash_advances")
    .insert({
      organization_id: workspace.organization_id,
      workspace_id: workspace.id,
      issued_to: member.user_id,
      account_id: account?.id ?? null,
      amount,
      currency_code: account?.currency_code ?? "EUR",
      status: "offered",
      issued_by: userId,
      issued_at: new Date().toISOString(),
      metadata: { purpose }
    })
    .select("id")
    .single<{ id: string }>();

  if (error || !insertedAdvance) {
    redirectToTeam(workspaceId, "accountable-create");
  }

  const admin = createAdminClient();
  const { error: approvalError } = await admin.from("approval_events").insert({
    organization_id: workspace.organization_id,
    workspace_id: workspace.id,
    entity_type: "cash_advance",
    entity_id: insertedAdvance.id,
    event_type: "offered",
    actor_user_id: userId,
    note: purpose || null,
    metadata: { amount, account_code: accountCode }
  });

  if (approvalError) {
    await admin.from("cash_advances").delete().eq("id", insertedAdvance.id).eq("workspace_id", workspace.id);
    redirectToTeam(workspaceId, "accountable-create");
  }

  revalidateWorkspace(workspaceId);
  redirectToTeam(workspaceId, "accountable-created");
}

export async function acceptAccountableOffer(workspaceId: string, formData: FormData) {
  const advanceId = String(formData.get("advanceId") || "").trim();

  if (!advanceId) {
    redirectToTeam(workspaceId, "accountable-missing");
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    redirectToTeam(workspaceId, "auth");
  }

  const { data: advance, error: advanceReadError } = await supabase
    .from("cash_advances")
    .select("id, organization_id, workspace_id, issued_to, status")
    .eq("id", advanceId)
    .eq("workspace_id", workspaceId)
    .eq("issued_to", userId)
    .eq("status", "offered")
    .maybeSingle<CashAdvanceForAccept>();

  if (advanceReadError || !advance) {
    redirectToTeam(workspaceId, "accountable-not-found");
  }

  if (!hasSupabaseAdminEnv()) {
    redirectToTeam(workspaceId, "accountable-config");
  }

  const admin = createAdminClient();
  const acceptedAt = new Date().toISOString();
  const { data: acceptedAdvance, error: updateError } = await admin
    .from("cash_advances")
    .update({ status: "accepted", accepted_at: acceptedAt })
    .eq("id", advance.id)
    .eq("workspace_id", workspaceId)
    .eq("issued_to", userId)
    .eq("status", "offered")
    .select("id")
    .maybeSingle<{ id: string }>();

  if (updateError || !acceptedAdvance) {
    redirectToTeam(workspaceId, "accountable-accept");
  }

  await admin.from("approval_events").insert({
    organization_id: advance.organization_id,
    workspace_id: workspaceId,
    entity_type: "cash_advance",
    entity_id: advance.id,
    event_type: "accepted_by_employee",
    actor_user_id: userId,
    metadata: { accepted_at: acceptedAt }
  });

  revalidateWorkspace(workspaceId);
  redirectToTeam(workspaceId, "accountable-accepted");
}

export async function addAccountableExpenseItem(workspaceId: string, formData: FormData) {
  const advanceId = String(formData.get("advanceId") || "").trim();
  const occurredOn = String(formData.get("occurredOn") || "").trim();
  const rawText = String(formData.get("rawText") || "").trim();
  const amount = parseMoneyInput(formData.get("amount"));

  if (!advanceId || !occurredOn || !rawText || amount <= 0) {
    redirectToTeam(workspaceId, "accountable-missing");
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    redirectToTeam(workspaceId, "auth");
  }

  const { data: advance, error: advanceError } = await supabase
    .from("cash_advances")
    .select("id, organization_id, workspace_id, issued_to, status, currency_code")
    .eq("id", advanceId)
    .eq("workspace_id", workspaceId)
    .eq("issued_to", userId)
    .eq("status", "accepted")
    .maybeSingle<CashAdvanceForReport>();

  if (advanceError || !advance) {
    redirectToTeam(workspaceId, "accountable-not-found");
  }

  const { data: existingReports, error: reportReadError } = await supabase
    .from("expense_reports")
    .select("id, organization_id, workspace_id, cash_advance_id, submitted_by, status, total_amount, currency_code")
    .eq("workspace_id", workspaceId)
    .eq("cash_advance_id", advance.id)
    .eq("submitted_by", userId)
    .in("status", ["draft", "returned"])
    .order("created_at", { ascending: false })
    .limit(1)
    .returns<ExpenseReportForWrite[]>();

  if (reportReadError) {
    redirectToTeam(workspaceId, "accountable-item-create");
  }

  let report = existingReports?.[0] ?? null;

  if (!report) {
    const { data: insertedReport, error: reportCreateError } = await supabase
      .from("expense_reports")
      .insert({
        organization_id: advance.organization_id,
        workspace_id: workspaceId,
        cash_advance_id: advance.id,
        submitted_by: userId,
        status: "draft",
        total_amount: 0,
        currency_code: advance.currency_code
      })
      .select("id, organization_id, workspace_id, cash_advance_id, submitted_by, status, total_amount, currency_code")
      .single<ExpenseReportForWrite>();

    if (reportCreateError || !insertedReport) {
      redirectToTeam(workspaceId, "accountable-item-create");
    }

    report = insertedReport;
  }

  const { error: itemCreateError } = await supabase.from("expense_items").insert({
    organization_id: advance.organization_id,
    workspace_id: workspaceId,
    expense_report_id: report.id,
    occurred_on: occurredOn,
    raw_text: rawText,
    amount,
    currency_code: report.currency_code,
    status: "draft"
  });

  if (itemCreateError) {
    redirectToTeam(workspaceId, "accountable-item-create");
  }

  const { data: reportItems, error: reportItemsError } = await supabase
    .from("expense_items")
    .select("amount")
    .eq("expense_report_id", report.id)
    .eq("workspace_id", workspaceId)
    .neq("status", "rejected")
    .returns<{ amount: number | string }[]>();

  if (reportItemsError) {
    redirectToTeam(workspaceId, "accountable-item-create");
  }

  const nextTotal = (reportItems ?? []).reduce((sum, item) => sum + Number(item.amount), 0);
  const { data: updatedReport, error: totalUpdateError } = await supabase
    .from("expense_reports")
    .update({ total_amount: nextTotal })
    .eq("id", report.id)
    .eq("workspace_id", workspaceId)
    .eq("submitted_by", userId)
    .in("status", ["draft", "returned"])
    .select("id")
    .maybeSingle<{ id: string }>();

  if (totalUpdateError || !updatedReport) {
    redirectToTeam(workspaceId, "accountable-item-create");
  }

  revalidateWorkspace(workspaceId);
  redirectToTeam(workspaceId, "accountable-item-created");
}

export async function submitAccountableReport(workspaceId: string, formData: FormData) {
  const reportId = String(formData.get("reportId") || "").trim();

  if (!reportId) {
    redirectToTeam(workspaceId, "accountable-report-missing");
  }

  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    redirectToTeam(workspaceId, "auth");
  }

  const submittedAt = new Date().toISOString();
  const { data: submittedReport, error } = await supabase
    .from("expense_reports")
    .update({ status: "submitted", submitted_at: submittedAt })
    .eq("id", reportId)
    .eq("workspace_id", workspaceId)
    .eq("submitted_by", userId)
    .in("status", ["draft", "returned"])
    .select("id")
    .maybeSingle<{ id: string }>();

  if (error || !submittedReport) {
    redirectToTeam(workspaceId, "accountable-report-submit");
  }

  revalidateWorkspace(workspaceId);
  redirectToTeam(workspaceId, "accountable-report-submitted");
}

export async function reviewAccountableReport(workspaceId: string, formData: FormData) {
  const reportId = String(formData.get("reportId") || "").trim();
  const nextStatus = String(formData.get("nextStatus") || "").trim();
  const note = String(formData.get("note") || "").trim();

  if (!reportId || (nextStatus !== "approved" && nextStatus !== "returned")) {
    redirectToTeam(workspaceId, "accountable-report-missing");
  }

  const { supabase, userId, workspace } = await getWritableWorkspace(workspaceId);

  if (!userId) {
    redirectToTeam(workspaceId, "auth");
  }

  if (!workspace) {
    redirectToTeam(workspaceId, "workspace");
  }

  const reviewedAt = new Date().toISOString();
  if (!hasSupabaseAdminEnv()) {
    redirectToTeam(workspaceId, "accountable-config");
  }

  const { data: reviewedReport, error } = await supabase
    .from("expense_reports")
    .update({
      status: nextStatus,
      approved_by: nextStatus === "approved" ? userId : null,
      approved_at: nextStatus === "approved" ? reviewedAt : null,
      metadata: { review_note: note }
    })
    .eq("id", reportId)
    .eq("workspace_id", workspaceId)
    .eq("status", "submitted")
    .select("id")
    .maybeSingle<{ id: string }>();

  if (error || !reviewedReport) {
    redirectToTeam(workspaceId, "accountable-report-review");
  }

  const admin = createAdminClient();
  const { error: approvalError } = await admin.from("approval_events").insert({
    organization_id: workspace.organization_id,
    workspace_id: workspace.id,
    entity_type: "expense_report",
    entity_id: reportId,
    event_type: nextStatus === "approved" ? "approved_by_admin" : "returned_to_employee",
    actor_user_id: userId,
    note: note || null
  });

  if (approvalError) {
    redirectToTeam(workspaceId, "accountable-report-review");
  }

  revalidateWorkspace(workspaceId);
  redirectToTeam(workspaceId, nextStatus === "approved" ? "accountable-report-approved" : "accountable-report-returned");
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

export async function createReportSnapshot(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const periodStart = String(formData.get("periodStart") || "").trim();
  const periodEnd = String(formData.get("periodEnd") || "").trim();
  const title = String(formData.get("title") || "").trim();

  if (!periodStart || !periodEnd) {
    redirectToMode(workspaceId, "reports", "report-period", { account: accountCode });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("create_period_report_snapshot", {
      p_workspace_id: workspaceId,
      p_period_start: periodStart,
      p_period_end: periodEnd,
      p_title: title || null
    })
    .returns<CreateReportSnapshotResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required") || message.includes("period_close_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", { account: accountCode });
    }

    if (message.includes("invalid_period")) {
      redirectToMode(workspaceId, "reports", "report-period", { account: accountCode });
    }

    if (message.includes("no_report_entries")) {
      redirectToMode(workspaceId, "reports", "report-empty", { account: accountCode });
    }

    redirectToMode(workspaceId, "reports", "report-create", { account: accountCode });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", "report-created", {
    account: accountCode,
    report: data[0].report_snapshot_id,
    lines: String(data[0].included_count),
    review: String(data[0].review_count)
  });
}

export async function createReportPackage(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const title = String(formData.get("title") || "").trim();
  const reportIds = formData
    .getAll("reportId")
    .map((value) => String(value).trim())
    .filter(Boolean);

  if (reportIds.length === 0) {
    redirectToMode(workspaceId, "reports", "report-package-empty", { account: accountCode });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("create_report_package", {
      p_workspace_id: workspaceId,
      p_report_snapshot_ids: reportIds,
      p_title: title || null
    })
    .returns<CreateReportPackageResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", { account: accountCode });
    }

    if (message.includes("report_snapshots_required") || message.includes("report_snapshot_not_found")) {
      redirectToMode(workspaceId, "reports", "report-package-empty", { account: accountCode });
    }

    redirectToMode(workspaceId, "reports", "report-package-create", { account: accountCode });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", "report-package-created", {
    account: accountCode,
    package: data[0].report_package_id,
    reports: String(data[0].included_count)
  });
}

function normalizeDeliveryStatus(value: string) {
  const status = value.trim().toLowerCase();
  return status === "sent" || status === "accepted" ? status : "";
}

function normalizeExportFormat(value: string) {
  const format = value.trim().toLowerCase();
  return format === "html" || format === "xls" || format === "pdf" ? format : "";
}

export async function createReportExportVersion(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const entityType = String(formData.get("entityType") || "").trim();
  const entityId = String(formData.get("entityId") || "").trim();
  const reportId = entityType === "report_snapshot" ? entityId : String(formData.get("reportId") || "").trim();
  const format = normalizeExportFormat(String(formData.get("format") || ""));
  const title = String(formData.get("title") || "").trim();

  if (!entityId || !format || (entityType !== "report_snapshot" && entityType !== "report_package")) {
    redirectToMode(workspaceId, "reports", "report-export-missing", {
      account: accountCode,
      ...(reportId ? { report: reportId } : {})
    });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("create_report_export_version", {
      p_workspace_id: workspaceId,
      p_entity_type: entityType,
      p_entity_id: entityId,
      p_format: format,
      p_title: title || null
    })
    .returns<CreateReportExportVersionResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required") || message.includes("documents_write_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", {
        account: accountCode,
        ...(reportId ? { report: reportId } : {})
      });
    }

    if (message.includes("unsupported_report_entity") || message.includes("unsupported_export_format")) {
      redirectToMode(workspaceId, "reports", "report-export-missing", {
        account: accountCode,
        ...(reportId ? { report: reportId } : {})
      });
    }

    if (message.includes("report_snapshot_not_found")) {
      redirectToMode(workspaceId, "reports", "report-missing", { account: accountCode });
    }

    if (message.includes("report_package_not_found")) {
      redirectToMode(workspaceId, "reports", "report-package-empty", { account: accountCode });
    }

    redirectToMode(workspaceId, "reports", "report-export-save", {
      account: accountCode,
      ...(reportId ? { report: reportId } : {})
    });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", "report-export-saved", {
    account: accountCode,
    ...(reportId ? { report: reportId } : {}),
    format: data[0].format,
    version: String(data[0].version_no)
  });
}

export async function setReportSnapshotDeliveryStatus(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const reportId = String(formData.get("reportId") || "").trim();
  const nextStatus = normalizeDeliveryStatus(String(formData.get("nextStatus") || ""));
  const note = String(formData.get("note") || "").trim();

  if (!reportId || !nextStatus) {
    redirectToMode(workspaceId, "reports", "report-missing", { account: accountCode });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("set_report_snapshot_delivery_status", {
      p_report_snapshot_id: reportId,
      p_next_status: nextStatus,
      p_note: note || null
    })
    .returns<SetReportSnapshotDeliveryStatusResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", { account: accountCode, report: reportId });
    }

    if (message.includes("report_snapshot_required") || message.includes("report_snapshot_not_found")) {
      redirectToMode(workspaceId, "reports", "report-missing", { account: accountCode });
    }

    if (message.includes("invalid_report_status_transition") || message.includes("unsupported_report_status")) {
      redirectToMode(workspaceId, "reports", "report-status-transition", { account: accountCode, report: reportId });
    }

    redirectToMode(workspaceId, "reports", "report-status", { account: accountCode, report: reportId });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", nextStatus === "sent" ? "report-sent" : "report-accepted", {
    account: accountCode,
    report: data[0].report_snapshot_id
  });
}

export async function setReportPackageDeliveryStatus(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const packageId = String(formData.get("packageId") || "").trim();
  const nextStatus = normalizeDeliveryStatus(String(formData.get("nextStatus") || ""));
  const note = String(formData.get("note") || "").trim();

  if (!packageId || !nextStatus) {
    redirectToMode(workspaceId, "reports", "report-package-empty", { account: accountCode });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("set_report_package_delivery_status", {
      p_report_package_id: packageId,
      p_next_status: nextStatus,
      p_note: note || null
    })
    .returns<SetReportPackageDeliveryStatusResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", { account: accountCode });
    }

    if (message.includes("report_package_required") || message.includes("report_package_not_found")) {
      redirectToMode(workspaceId, "reports", "report-package-empty", { account: accountCode });
    }

    if (message.includes("invalid_report_package_status_transition") || message.includes("unsupported_report_status")) {
      redirectToMode(workspaceId, "reports", "report-package-status-transition", { account: accountCode, package: packageId });
    }

    redirectToMode(workspaceId, "reports", "report-package-status", { account: accountCode, package: packageId });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", nextStatus === "sent" ? "report-package-sent" : "report-package-accepted", {
    account: accountCode,
    package: data[0].report_package_id
  });
}

export async function returnReportSnapshotForRevision(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const reportId = String(formData.get("reportId") || "").trim();
  const reason = String(formData.get("reason") || "").trim();

  if (!reportId) {
    redirectToMode(workspaceId, "reports", "report-missing", { account: accountCode });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("return_report_snapshot_for_revision", {
      p_report_snapshot_id: reportId,
      p_reason: reason || null
    })
    .returns<ReturnReportSnapshotForRevisionResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (message.includes("auth_required") || message.includes("reports_manage_required")) {
      redirectToMode(workspaceId, "reports", "report-auth", { account: accountCode, report: reportId });
    }

    if (message.includes("report_snapshot_required") || message.includes("report_snapshot_not_found")) {
      redirectToMode(workspaceId, "reports", "report-missing", { account: accountCode });
    }

    redirectToMode(workspaceId, "reports", "report-revision", { account: accountCode, report: reportId });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", "report-returned", {
    account: accountCode,
    report: data[0].report_snapshot_id
  });
}

export async function createReportLockedCorrection(workspaceId: string, formData: FormData) {
  const accountCode = String(formData.get("account") || "cash").trim() || "cash";
  const originalTransactionId = String(formData.get("originalTransactionId") || "").trim();
  const occurredOn = String(formData.get("occurredOn") || "").trim();
  const rawText = String(formData.get("rawText") || "").trim();
  const reason = String(formData.get("reason") || "").trim();
  const reportId = String(formData.get("reportId") || "").trim();

  if (!originalTransactionId || !occurredOn || !rawText || !reason) {
    redirectToMode(workspaceId, "reports", "report-correction-missing", {
      account: accountCode,
      ...(reportId ? { report: reportId } : {})
    });
  }

  const supabase = await createClient();
  const { data, error } = await (supabase as unknown as SupabaseRpcClient)
    .rpc("create_report_locked_correction", {
      p_original_transaction_id: originalTransactionId,
      p_account_code: accountCode,
      p_occurred_on: occurredOn,
      p_raw_text: rawText,
      p_reason: reason
    })
    .returns<CreateReportLockedCorrectionResult[]>();

  if (error || !data?.[0]) {
    const message = error?.message ?? "";

    if (
      message.includes("auth_required") ||
      message.includes("ledger_correct_required") ||
      message.includes("ledger_write_required")
    ) {
      redirectToMode(workspaceId, "reports", "report-auth", {
        account: accountCode,
        ...(reportId ? { report: reportId } : {})
      });
    }

    if (
      message.includes("original_transaction_required") ||
      message.includes("original_transaction_not_found") ||
      message.includes("original_transaction_not_report_locked")
    ) {
      redirectToMode(workspaceId, "reports", "report-correction-source", {
        account: accountCode,
        ...(reportId ? { report: reportId } : {})
      });
    }

    redirectToMode(workspaceId, "reports", "report-correction", {
      account: accountCode,
      ...(reportId ? { report: reportId } : {})
    });
  }

  revalidateWorkspace(workspaceId);
  redirectToMode(workspaceId, "reports", "report-correction-created", {
    account: accountCode,
    ...(reportId ? { report: reportId } : {}),
    row: String(data[0].correction_row_no)
  });
}
