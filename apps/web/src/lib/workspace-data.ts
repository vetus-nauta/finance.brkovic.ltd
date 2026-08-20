import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

export type WorkspaceRole = "owner" | "admin" | "finance" | "assistant" | "viewer" | "employee";

export type WorkspaceSummary = {
  id: string;
  name: string;
  type: string;
  currency: string;
  status: string;
  role: WorkspaceRole | string;
  accessScope: string;
};

type MembershipRow = {
  workspace_id: string;
  role_code: string;
  access_scope: string;
};

type WorkspaceRow = {
  id: string;
  organization_id?: string;
  name: string;
  workspace_type: string;
  currency_code: string;
  status: string;
};

type AccountRow = {
  id: string;
  code: string;
  label: string;
  account_type: string;
  currency_code: string;
};

export type AccountSummary = AccountRow;

export type OperationalEntry = {
  id: string;
  rowNo: number;
  occurredOn: string;
  rawText: string;
  status: string;
  amount: number | null;
  direction: "income" | "expense" | "neutral" | null;
  reviewStatus: string | null;
  accountId: string | null;
};

export type QuickNoteSummary = {
  id: string;
  body: string;
  status: string;
  convertedCount: number;
  proposals: SmithEntryProposalSummary[];
  createdAt: string;
  updatedAt: string;
};

export type SmithEntryProposalSummary = {
  id: string;
  lineNo: number;
  rawText: string;
  candidateAmount: number | null;
  candidateDirection: "income" | "expense" | "neutral" | null;
  candidateCategoryCode: string | null;
  confidence: number | null;
  reviewReason: string | null;
  matchedSignals: string[];
  blockers: string[];
  semanticMarkers: string[];
  parserReason: string | null;
  duplicateStatus: string;
  duplicateReason: string | null;
  status: string;
};

export type ReportSnapshotSummary = {
  id: string;
  title: string;
  periodStart: string;
  periodEnd: string;
  status: string;
  createdAt: string;
};

type TransactionRow = {
  id: string;
  row_no: number | null;
  occurred_on: string;
  raw_text: string;
  status: string;
  account_id: string | null;
};

type LedgerRow = {
  transaction_id: string;
  account_id: string | null;
  direction: "income" | "expense" | "neutral";
  amount: number | string;
  review_status: string;
};

type QuickNoteRow = {
  id: string;
  body: string;
  status: string;
  converted_transaction_ids: string[];
  created_at: string;
  updated_at: string;
};

type ReportSnapshotRow = {
  id: string;
  title: string;
  period_start: string;
  period_end: string;
  status: string;
  created_at: string;
};

type SmithEntryProposalRow = {
  id: string;
  quick_note_id: string;
  line_no: number;
  raw_text: string;
  candidate_amount: number | string | null;
  candidate_direction: "income" | "expense" | "neutral" | null;
  candidate_category_code: string | null;
  confidence: number | string | null;
  review_reason: string | null;
  matched_signals: string[] | null;
  blockers: string[] | null;
  semantic_markers: string[] | null;
  parser_reason: string | null;
  duplicate_status: string;
  duplicate_reason: string | null;
  status: string;
};

export type WorkspaceDetails = WorkspaceSummary & {
  accounts: AccountSummary[];
  transactionCount: number;
  reviewCount: number;
  activeAccountCode: string;
  entries: OperationalEntry[];
  quickNotes: QuickNoteSummary[];
  reportSnapshots: ReportSnapshotSummary[];
};

export const roleLabels: Record<string, string> = {
  owner: "Владелец",
  admin: "Админ",
  finance: "Финансист",
  assistant: "Помощник",
  viewer: "Просмотр",
  employee: "Сотрудник"
};

export function workspacePath(workspaceId: string) {
  return `${routes.workspaces}/${workspaceId}`;
}

export async function listUserWorkspaces(): Promise<WorkspaceSummary[]> {
  const supabase = await createClient();
  const { data: memberships, error: membershipsError } = await supabase
    .from("memberships")
    .select("workspace_id, role_code, access_scope")
    .eq("status", "active")
    .not("accepted_at", "is", null)
    .order("created_at", { ascending: true })
    .returns<MembershipRow[]>();

  if (membershipsError) {
    throw new Error(membershipsError.message);
  }

  const workspaceIds = [...new Set((memberships ?? []).map((row) => row.workspace_id))];

  if (workspaceIds.length === 0) {
    return [];
  }

  const { data: workspaces, error: workspacesError } = await supabase
    .from("workspaces")
    .select("id, name, workspace_type, currency_code, status")
    .in("id", workspaceIds)
    .eq("status", "active")
    .returns<WorkspaceRow[]>();

  if (workspacesError) {
    throw new Error(workspacesError.message);
  }

  const workspaceById = new Map((workspaces ?? []).map((workspace) => [workspace.id, workspace]));

  return (memberships ?? [])
    .map((membership) => {
      const workspace = workspaceById.get(membership.workspace_id);

      if (!workspace) {
        return null;
      }

      return {
        id: workspace.id,
        name: workspace.name,
        type: workspace.workspace_type,
        currency: workspace.currency_code,
        status: workspace.status,
        role: membership.role_code,
        accessScope: membership.access_scope
      };
    })
    .filter((workspace): workspace is WorkspaceSummary => workspace !== null);
}

export async function getWorkspaceDetails(
  workspaceId: string,
  requestedAccountCode = "cash"
): Promise<WorkspaceDetails | null> {
  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const userId = claims?.claims?.sub;

  if (typeof userId !== "string") {
    return null;
  }

  const { data: membership, error: membershipError } = await supabase
    .from("memberships")
    .select("workspace_id, role_code, access_scope")
    .eq("workspace_id", workspaceId)
    .eq("user_id", userId)
    .eq("status", "active")
    .not("accepted_at", "is", null)
    .maybeSingle<MembershipRow>();

  if (membershipError) {
    throw new Error(membershipError.message);
  }

  if (!membership) {
    return null;
  }

  const { data: workspace, error: workspaceError } = await supabase
    .from("workspaces")
    .select("id, name, workspace_type, currency_code, status")
    .eq("id", workspaceId)
    .eq("status", "active")
    .maybeSingle<WorkspaceRow>();

  if (workspaceError) {
    throw new Error(workspaceError.message);
  }

  if (!workspace) {
    return null;
  }

  const [
    { data: accounts, error: accountsError },
    transactions,
    ledgerReviews,
    transactionReviews,
    quickNotes,
    reportSnapshots
  ] = await Promise.all([
    supabase
      .from("accounts")
      .select("id, code, label, account_type, currency_code")
      .eq("workspace_id", workspaceId)
      .eq("is_active", true)
      .order("account_type", { ascending: true })
      .returns<AccountRow[]>(),
    supabase.from("transactions").select("id", { count: "exact", head: true }).eq("workspace_id", workspaceId),
    supabase
      .from("ledger_entries")
      .select("id", { count: "exact", head: true })
      .eq("workspace_id", workspaceId)
      .eq("review_status", "review"),
    supabase
      .from("transactions")
      .select("id", { count: "exact", head: true })
      .eq("workspace_id", workspaceId)
      .eq("status", "needs_review"),
    supabase
      .from("quick_notes")
      .select("id, body, status, converted_transaction_ids, created_at, updated_at")
      .eq("workspace_id", workspaceId)
      .neq("status", "void")
      .order("updated_at", { ascending: false })
      .limit(20)
      .returns<QuickNoteRow[]>(),
    supabase
      .from("report_snapshots")
      .select("id, title, period_start, period_end, status, created_at")
      .eq("workspace_id", workspaceId)
      .neq("status", "void")
      .order("period_end", { ascending: false })
      .limit(20)
      .returns<ReportSnapshotRow[]>()
  ]);

  if (accountsError) {
    throw new Error(accountsError.message);
  }

  if (transactions.error) {
    throw new Error(transactions.error.message);
  }

  if (ledgerReviews.error) {
    throw new Error(ledgerReviews.error.message);
  }

  if (transactionReviews.error) {
    throw new Error(transactionReviews.error.message);
  }

  if (quickNotes.error) {
    throw new Error(quickNotes.error.message);
  }

  if (reportSnapshots.error) {
    throw new Error(reportSnapshots.error.message);
  }

  const normalizedAccounts = [...(accounts ?? [])].sort((left, right) => {
    const order = new Map([
      ["cash", 0],
      ["card", 1]
    ]);

    return (order.get(left.code) ?? 10) - (order.get(right.code) ?? 10) || left.label.localeCompare(right.label);
  });
  const activeAccount =
    normalizedAccounts.find((account) => account.code === requestedAccountCode) ??
    normalizedAccounts.find((account) => account.account_type === "cash") ??
    normalizedAccounts[0] ??
    null;

  let transactionRows: TransactionRow[] = [];

  if (activeAccount) {
    const { data, error: entriesError } = await supabase
      .from("transactions")
      .select("id, row_no, occurred_on, raw_text, status, account_id")
      .eq("workspace_id", workspaceId)
      .eq("account_id", activeAccount.id)
      .order("row_no", { ascending: true, nullsFirst: false })
      .returns<TransactionRow[]>();

    if (entriesError) {
      throw new Error(entriesError.message);
    }

    transactionRows = data ?? [];
  }

  const transactionIds = transactionRows.map((row) => row.id);
  const { data: ledgerRows, error: ledgerError } =
    transactionIds.length > 0
      ? await supabase
          .from("ledger_entries")
          .select("transaction_id, account_id, direction, amount, review_status")
          .in("transaction_id", transactionIds)
          .returns<LedgerRow[]>()
      : { data: [], error: null };

  if (ledgerError) {
    throw new Error(ledgerError.message);
  }

  const ledgerByTransactionId = new Map((ledgerRows ?? []).map((row) => [row.transaction_id, row]));
  const entries = transactionRows
    .map((row) => {
      const ledger = ledgerByTransactionId.get(row.id);

      return {
        id: row.id,
        rowNo: row.row_no ?? 0,
        occurredOn: row.occurred_on,
        rawText: row.raw_text,
        status: row.status,
        amount: ledger ? Number(ledger.amount) : null,
        direction: ledger?.direction ?? null,
        reviewStatus: ledger?.review_status ?? null,
        accountId: ledger?.account_id ?? row.account_id
      };
    })
    .sort((left, right) => left.rowNo - right.rowNo);
  const quickNoteIds = (quickNotes.data ?? []).map((note) => note.id);
  const { data: proposalRows, error: proposalsError } =
    quickNoteIds.length > 0
      ? await supabase
          .from("smith_entry_proposals")
          .select(
            "id, quick_note_id, line_no, raw_text, candidate_amount, candidate_direction, candidate_category_code, confidence, review_reason, matched_signals, blockers, semantic_markers, parser_reason, duplicate_status, duplicate_reason, status"
          )
          .in("quick_note_id", quickNoteIds)
          .neq("status", "void")
          .order("line_no", { ascending: true })
          .returns<SmithEntryProposalRow[]>()
      : { data: [], error: null };

  if (proposalsError) {
    throw new Error(proposalsError.message);
  }

  const proposalsByNoteId = new Map<string, SmithEntryProposalSummary[]>();

  for (const proposal of proposalRows ?? []) {
    const list = proposalsByNoteId.get(proposal.quick_note_id) ?? [];
    list.push({
      id: proposal.id,
      lineNo: proposal.line_no,
      rawText: proposal.raw_text,
      candidateAmount: proposal.candidate_amount === null ? null : Number(proposal.candidate_amount),
      candidateDirection: proposal.candidate_direction,
      candidateCategoryCode: proposal.candidate_category_code,
      confidence: proposal.confidence === null ? null : Number(proposal.confidence),
      reviewReason: proposal.review_reason,
      matchedSignals: proposal.matched_signals ?? [],
      blockers: proposal.blockers ?? [],
      semanticMarkers: proposal.semantic_markers ?? [],
      parserReason: proposal.parser_reason,
      duplicateStatus: proposal.duplicate_status,
      duplicateReason: proposal.duplicate_reason,
      status: proposal.status
    });
    proposalsByNoteId.set(proposal.quick_note_id, list);
  }

  return {
    id: workspace.id,
    name: workspace.name,
    type: workspace.workspace_type,
    currency: workspace.currency_code,
    status: workspace.status,
    role: membership.role_code,
    accessScope: membership.access_scope,
    accounts: normalizedAccounts,
    transactionCount: transactions.count ?? 0,
    reviewCount: (ledgerReviews.count ?? 0) + (transactionReviews.count ?? 0),
    activeAccountCode: activeAccount?.code ?? requestedAccountCode,
    entries,
    quickNotes: (quickNotes.data ?? []).map((note) => ({
      id: note.id,
      body: note.body,
      status: note.status,
      convertedCount: note.converted_transaction_ids?.length ?? 0,
      proposals: proposalsByNoteId.get(note.id) ?? [],
      createdAt: note.created_at,
      updatedAt: note.updated_at
    })),
    reportSnapshots: (reportSnapshots.data ?? []).map((report) => ({
      id: report.id,
      title: report.title,
      periodStart: report.period_start,
      periodEnd: report.period_end,
      status: report.status,
      createdAt: report.created_at
    }))
  };
}
