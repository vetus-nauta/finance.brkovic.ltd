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

export type WorkspaceDetails = WorkspaceSummary & {
  accounts: AccountSummary[];
  transactionCount: number;
  reviewCount: number;
  activeAccountCode: string;
  entries: OperationalEntry[];
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

  const [{ data: accounts, error: accountsError }, transactions, reviews] = await Promise.all([
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
      .eq("review_status", "review")
  ]);

  if (accountsError) {
    throw new Error(accountsError.message);
  }

  if (transactions.error) {
    throw new Error(transactions.error.message);
  }

  if (reviews.error) {
    throw new Error(reviews.error.message);
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
    reviewCount: reviews.count ?? 0,
    activeAccountCode: activeAccount?.code ?? requestedAccountCode,
    entries
  };
}
