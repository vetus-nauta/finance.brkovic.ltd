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
  sourceTransactionIds: string[];
  entryCount: number;
  reviewCount: number;
  incomeTotal: number;
  expenseTotal: number;
  netTotal: number;
  endingCash: number | null;
  accounts: ReportAccountSummary[];
  categories: ReportCategorySummary[];
  entries: ReportSourceEntry[];
  events: ApprovalEventSummary[];
  exportVersions: ReportExportVersionSummary[];
  createdAt: string;
};

export type ReportPackageSummary = {
  id: string;
  title: string;
  status: string;
  reportIds: string[];
  reportCount: number;
  events: ApprovalEventSummary[];
  exportVersions: ReportExportVersionSummary[];
  createdAt: string;
};

export type ReportExportVersionSummary = {
  documentId: string;
  documentVersionId: string;
  entityType: string;
  entityId: string;
  format: "html" | "xls" | "pdf" | "file";
  versionNo: number;
  filename: string;
  createdAt: string;
  downloadPath: string;
};

export type ApprovalEventSummary = {
  id: string;
  entityType: string;
  entityId: string;
  eventType: string;
  note: string | null;
  createdAt: string;
};

export type ReportAccountSummary = {
  accountCode: string;
  label: string;
  entryCount: number;
  incomeTotal: number;
  expenseTotal: number;
};

export type ReportCategorySummary = {
  code: string;
  label: string;
  direction: "income" | "expense" | "neutral";
  total: number;
  entryCount: number;
  reviewCount: number;
};

export type ReportSourceEntry = {
  id: string;
  rowNo: number;
  occurredOn: string;
  rawText: string;
  status: string;
  amount: number | null;
  direction: "income" | "expense" | "neutral" | null;
  categoryCode: string;
  categoryLabel: string;
  reviewStatus: string | null;
};

type TransactionRow = {
  id: string;
  row_no: number | null;
  occurred_on: string;
  raw_text: string;
  status: string;
  account_id: string | null;
};

type SummaryTransactionRow = {
  id: string;
  status: string;
};

type LedgerRow = {
  transaction_id: string;
  account_id: string | null;
  category_id: string | null;
  direction: "income" | "expense" | "neutral";
  amount: number | string;
  review_status: string;
  metadata: Record<string, unknown> | null;
};

type CategoryRow = {
  id: string;
  code: string;
  direction: "income" | "expense" | "neutral";
  label: Record<string, string> | null;
  metadata: Record<string, unknown> | null;
  is_active: boolean;
};

export type CategorySummaryRow = {
  code: string;
  label: string;
  direction: "income" | "expense" | "neutral";
  kind: "operational" | "accounting_block" | "money_movement" | "uncategorized";
  total: number;
  count: number;
  reviewCount: number;
};

export type WorkspaceCategorySummary = {
  operational: CategorySummaryRow[];
  accountingBlocks: CategorySummaryRow[];
  moneyMovements: CategorySummaryRow[];
  uncategorized: CategorySummaryRow[];
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
  source_transaction_ids: string[];
  totals: Record<string, unknown> | null;
  created_at: string;
};

type ReportPackageRow = {
  id: string;
  title: string;
  status: string;
  created_at: string;
};

type ReportPackageItemRow = {
  report_package_id: string;
  report_snapshot_id: string;
  position: number;
};

type DocumentLinkRow = {
  document_id: string;
  entity_type: string;
  entity_id: string;
};

type DocumentRow = {
  id: string;
  original_filename: string | null;
  mime_type: string | null;
};

type DocumentVersionRow = {
  id: string;
  document_id: string;
  version_no: number;
  object_key: string;
  created_at: string;
};

type ApprovalEventRow = {
  id: string;
  entity_type: string;
  entity_id: string;
  event_type: string;
  note: string | null;
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

type ReportSourceTransactionRow = {
  id: string;
  row_no: number | null;
  occurred_on: string;
  raw_text: string;
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
  reportPackages: ReportPackageSummary[];
  categorySummary: WorkspaceCategorySummary;
};

export type WorkspaceReportDocument = WorkspaceSummary & {
  report: ReportSnapshotSummary;
};

export type WorkspaceReportPackageDocument = WorkspaceSummary & {
  reportPackage: ReportPackageSummary;
  reports: ReportSnapshotSummary[];
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

function categoryLabel(category: Pick<CategoryRow, "code" | "label">) {
  return category.label?.ru ?? category.label?.en ?? category.code;
}

function categoryKind(category: Pick<CategoryRow, "metadata"> | null): CategorySummaryRow["kind"] {
  const kind = category?.metadata?.category_kind;

  if (kind === "accounting_block" || kind === "money_movement") {
    return kind;
  }

  return "operational";
}

function buildCategorySummary(ledgerRows: LedgerRow[], categories: CategoryRow[]): WorkspaceCategorySummary {
  const categoriesById = new Map(categories.map((category) => [category.id, category]));
  const categoriesByCode = new Map(categories.map((category) => [category.code, category]));
  const summaryByCode = new Map<string, CategorySummaryRow>();

  for (const ledger of ledgerRows) {
    const metadataCategoryCode =
      typeof ledger.metadata?.category_code === "string" ? ledger.metadata.category_code : null;
    const category = ledger.category_id
      ? categoriesById.get(ledger.category_id) ?? null
      : metadataCategoryCode
        ? categoriesByCode.get(metadataCategoryCode) ?? null
        : null;
    const code = category?.code ?? "uncategorized";
    const existing = summaryByCode.get(code);
    const row =
      existing ??
      ({
        code,
        label: category ? categoryLabel(category) : "Без категории",
        direction: category?.direction ?? ledger.direction,
        kind: category ? categoryKind(category) : "uncategorized",
        total: 0,
        count: 0,
        reviewCount: 0
      } satisfies CategorySummaryRow);

    row.total += Number(ledger.amount);
    row.count += 1;

    if (ledger.review_status === "review" || ledger.review_status === "blocked") {
      row.reviewCount += 1;
    }

    summaryByCode.set(code, row);
  }

  const sortRows = (rows: CategorySummaryRow[]) =>
    rows.sort((left, right) => {
      if (left.direction !== right.direction) {
        return left.direction === "income" ? -1 : right.direction === "income" ? 1 : 0;
      }

      return Math.abs(right.total) - Math.abs(left.total) || left.label.localeCompare(right.label, "ru");
    });
  const rows = [...summaryByCode.values()];

  return {
    operational: sortRows(rows.filter((row) => row.kind === "operational")),
    accountingBlocks: sortRows(rows.filter((row) => row.kind === "accounting_block")),
    moneyMovements: sortRows(rows.filter((row) => row.kind === "money_movement")),
    uncategorized: sortRows(rows.filter((row) => row.kind === "uncategorized"))
  };
}

function numberFromJson(value: unknown) {
  return typeof value === "number" ? value : Number(value ?? 0);
}

function reportAccountRows(totals: Record<string, unknown> | null): ReportAccountSummary[] {
  const rows = Array.isArray(totals?.accounts) ? totals.accounts : [];

  return rows
    .filter((row): row is Record<string, unknown> => row !== null && typeof row === "object")
    .map((row) => ({
      accountCode: String(row.account_code ?? "account"),
      label: String(row.label ?? row.account_code ?? "Счет"),
      entryCount: numberFromJson(row.entry_count),
      incomeTotal: numberFromJson(row.income_total),
      expenseTotal: numberFromJson(row.expense_total)
    }));
}

function reportCategoryRows(totals: Record<string, unknown> | null): ReportCategorySummary[] {
  const rows = Array.isArray(totals?.categories) ? totals.categories : [];

  return rows
    .filter((row): row is Record<string, unknown> => row !== null && typeof row === "object")
    .map((row) => {
      const direction = row.direction === "income" || row.direction === "expense" ? row.direction : "neutral";

      return {
        code: String(row.category_code ?? "uncategorized"),
        label: String(row.label ?? row.category_code ?? "Без категории"),
        direction,
        total: numberFromJson(row.total),
        entryCount: numberFromJson(row.entry_count),
        reviewCount: numberFromJson(row.review_count)
      };
    });
}

function buildReportSourceEntries(
  sourceIds: string[],
  transactionById: Map<string, ReportSourceTransactionRow>,
  ledgerByTransactionId: Map<string, LedgerRow>,
  categoryById: Map<string, CategoryRow>,
  categoryByCode: Map<string, CategoryRow>
): ReportSourceEntry[] {
  return sourceIds
    .map((transactionId) => {
      const transaction = transactionById.get(transactionId);

      if (!transaction) {
        return null;
      }

      const ledger = ledgerByTransactionId.get(transactionId);
      const metadataCategoryCode =
        typeof ledger?.metadata?.category_code === "string" ? ledger.metadata.category_code : null;
      const category = ledger?.category_id
        ? categoryById.get(ledger.category_id) ?? null
        : metadataCategoryCode
          ? categoryByCode.get(metadataCategoryCode) ?? null
          : null;
      const categoryCode = category?.code ?? metadataCategoryCode ?? "uncategorized";

      return {
        id: transaction.id,
        rowNo: transaction.row_no ?? 0,
        occurredOn: transaction.occurred_on,
        rawText: transaction.raw_text,
        status: transaction.status,
        amount: ledger ? Number(ledger.amount) : null,
        direction: ledger?.direction ?? null,
        categoryCode,
        categoryLabel: category ? categoryLabel(category) : categoryCode === "uncategorized" ? "Без категории" : categoryCode,
        reviewStatus: ledger?.review_status ?? null
      };
    })
    .filter((entry): entry is ReportSourceEntry => entry !== null);
}

function buildReportSnapshotSummary(
  report: ReportSnapshotRow,
  entries: ReportSourceEntry[],
  events: ApprovalEventSummary[] = [],
  exportVersions: ReportExportVersionSummary[] = []
): ReportSnapshotSummary {
  const source = report.totals?.v2_source;
  const oldSummaryTotals =
    source && typeof source === "object" && "old_summary_totals" in source
      ? (source.old_summary_totals as Record<string, unknown> | null)
      : null;

  return {
    id: report.id,
    title: report.title,
    periodStart: report.period_start,
    periodEnd: report.period_end,
    status: report.status,
    sourceTransactionIds: report.source_transaction_ids ?? [],
    entryCount:
      typeof report.totals?.entry_count === "number"
        ? report.totals.entry_count
        : report.source_transaction_ids?.length ?? 0,
    reviewCount: typeof report.totals?.review_count === "number" ? report.totals.review_count : 0,
    incomeTotal: numberFromJson(report.totals?.income_total),
    expenseTotal: numberFromJson(report.totals?.expense_total),
    netTotal: numberFromJson(report.totals?.net_total),
    endingCash: oldSummaryTotals ? numberFromJson(oldSummaryTotals.ending_cash) : null,
    accounts: reportAccountRows(report.totals),
    categories: reportCategoryRows(report.totals),
    entries,
    events,
    exportVersions,
    createdAt: report.created_at
  };
}

function buildApprovalEventSummaries(rows: ApprovalEventRow[] | null | undefined): ApprovalEventSummary[] {
  return (rows ?? []).map((row) => ({
    id: row.id,
    entityType: row.entity_type,
    entityId: row.entity_id,
    eventType: row.event_type,
    note: row.note,
    createdAt: row.created_at
  }));
}

function groupApprovalEventsByEntityId(rows: ApprovalEventRow[] | null | undefined) {
  const eventsByEntityId = new Map<string, ApprovalEventSummary[]>();

  for (const event of buildApprovalEventSummaries(rows)) {
    const list = eventsByEntityId.get(event.entityId) ?? [];
    list.push(event);
    eventsByEntityId.set(event.entityId, list);
  }

  return eventsByEntityId;
}

function exportFormatFromObjectKey(objectKey: string): ReportExportVersionSummary["format"] {
  if (objectKey.includes("/html/") || objectKey.endsWith(".html")) return "html";
  if (objectKey.includes("/xls/") || objectKey.endsWith(".xls")) return "xls";
  if (objectKey.includes("/pdf/") || objectKey.endsWith(".pdf")) return "pdf";
  return "file";
}

function exportDownloadPath(entityType: string, entityId: string, format: ReportExportVersionSummary["format"], workspaceId: string) {
  const base =
    entityType === "report_package"
      ? `${workspacePath(workspaceId)}/report-packages/${encodeURIComponent(entityId)}`
      : `${workspacePath(workspaceId)}/reports/${encodeURIComponent(entityId)}`;

  return format === "xls" ? `${base}/excel` : base;
}

function groupExportVersionsByEntityId(
  links: DocumentLinkRow[] | null | undefined,
  documents: DocumentRow[] | null | undefined,
  versions: DocumentVersionRow[] | null | undefined,
  workspaceId: string
) {
  const documentById = new Map((documents ?? []).map((document) => [document.id, document]));
  const linksByDocumentId = new Map((links ?? []).map((link) => [link.document_id, link]));
  const versionsByEntityId = new Map<string, ReportExportVersionSummary[]>();

  for (const version of versions ?? []) {
    const link = linksByDocumentId.get(version.document_id);
    const document = documentById.get(version.document_id);

    if (!link || !document) {
      continue;
    }

    const format = exportFormatFromObjectKey(version.object_key);
    const list = versionsByEntityId.get(link.entity_id) ?? [];
    list.push({
      documentId: version.document_id,
      documentVersionId: version.id,
      entityType: link.entity_type,
      entityId: link.entity_id,
      format,
      versionNo: version.version_no,
      filename: document.original_filename ?? `findesk-report.${format}`,
      createdAt: version.created_at,
      downloadPath: exportDownloadPath(link.entity_type, link.entity_id, format, workspaceId)
    });
    versionsByEntityId.set(link.entity_id, list);
  }

  for (const list of versionsByEntityId.values()) {
    list.sort((left, right) => {
      const dateOrder = new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime();
      return dateOrder || right.versionNo - left.versionNo;
    });
  }

  return versionsByEntityId;
}

async function getExportVersionsByEntityId(
  supabase: Awaited<ReturnType<typeof createClient>>,
  workspaceId: string,
  entityIds: string[]
) {
  const uniqueEntityIds = [...new Set(entityIds.filter(Boolean))];

  if (uniqueEntityIds.length === 0) {
    return new Map<string, ReportExportVersionSummary[]>();
  }

  const { data: links, error: linksError } = await supabase
    .from("document_links")
    .select("document_id, entity_type, entity_id")
    .eq("workspace_id", workspaceId)
    .in("entity_id", uniqueEntityIds)
    .in("entity_type", ["report_snapshot", "report_package"])
    .returns<DocumentLinkRow[]>();

  if (linksError) {
    throw new Error(linksError.message);
  }

  const documentIds = [...new Set((links ?? []).map((link) => link.document_id))];

  if (documentIds.length === 0) {
    return new Map<string, ReportExportVersionSummary[]>();
  }

  const [{ data: documents, error: documentsError }, { data: versions, error: versionsError }] = await Promise.all([
    supabase
      .from("documents")
      .select("id, original_filename, mime_type")
      .in("id", documentIds)
      .returns<DocumentRow[]>(),
    supabase
      .from("document_versions")
      .select("id, document_id, version_no, object_key, created_at")
      .in("document_id", documentIds)
      .order("created_at", { ascending: false })
      .returns<DocumentVersionRow[]>()
  ]);

  if (documentsError) {
    throw new Error(documentsError.message);
  }

  if (versionsError) {
    throw new Error(versionsError.message);
  }

  return groupExportVersionsByEntityId(links, documents, versions, workspaceId);
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
  const pageSize = 1000;
  const lookupChunkSize = 200;

  function chunkValues<T>(values: T[], chunkSize = lookupChunkSize) {
    const chunks: T[][] = [];

    for (let index = 0; index < values.length; index += chunkSize) {
      chunks.push(values.slice(index, index + chunkSize));
    }

    return chunks;
  }

  async function fetchAllSummaryTransactions() {
    const rows: SummaryTransactionRow[] = [];

    for (let from = 0; ; from += pageSize) {
      const { data, error } = await supabase
        .from("transactions")
        .select("id, status")
        .eq("workspace_id", workspaceId)
        .neq("status", "void")
        .order("row_no", { ascending: true, nullsFirst: false })
        .range(from, from + pageSize - 1)
        .returns<SummaryTransactionRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));

      if (!data || data.length < pageSize) {
        break;
      }
    }

    return rows;
  }

  async function fetchAllLedgerRows() {
    const rows: LedgerRow[] = [];

    for (let from = 0; ; from += pageSize) {
      const { data, error } = await supabase
        .from("ledger_entries")
        .select("transaction_id, account_id, category_id, direction, amount, review_status, metadata")
        .eq("workspace_id", workspaceId)
        .range(from, from + pageSize - 1)
        .returns<LedgerRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));

      if (!data || data.length < pageSize) {
        break;
      }
    }

    return rows;
  }

  async function fetchAccountTransactions(accountId: string) {
    const rows: TransactionRow[] = [];

    for (let from = 0; ; from += pageSize) {
      const { data, error } = await supabase
        .from("transactions")
        .select("id, row_no, occurred_on, raw_text, status, account_id")
        .eq("workspace_id", workspaceId)
        .eq("account_id", accountId)
        .neq("status", "void")
        .order("row_no", { ascending: true, nullsFirst: false })
        .range(from, from + pageSize - 1)
        .returns<TransactionRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));

      if (!data || data.length < pageSize) {
        break;
      }
    }

    return rows;
  }

  async function fetchAllReportSnapshots() {
    const rows: ReportSnapshotRow[] = [];

    for (let from = 0; ; from += pageSize) {
      const { data, error } = await supabase
        .from("report_snapshots")
        .select("id, title, period_start, period_end, status, source_transaction_ids, totals, created_at")
        .eq("workspace_id", workspaceId)
        .neq("status", "void")
        .order("period_end", { ascending: false })
        .range(from, from + pageSize - 1)
        .returns<ReportSnapshotRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));

      if (!data || data.length < pageSize) {
        break;
      }
    }

    return rows;
  }

  async function fetchAllReportPackages() {
    const rows: ReportPackageRow[] = [];

    for (let from = 0; ; from += pageSize) {
      const { data, error } = await supabase
        .from("report_packages")
        .select("id, title, status, created_at")
        .eq("workspace_id", workspaceId)
        .neq("status", "void")
        .order("created_at", { ascending: false })
        .range(from, from + pageSize - 1)
        .returns<ReportPackageRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));

      if (!data || data.length < pageSize) {
        break;
      }
    }

    return rows;
  }

  async function fetchReportSourceTransactions(transactionIds: string[]) {
    const rows: ReportSourceTransactionRow[] = [];

    for (const chunk of chunkValues(transactionIds)) {
      const { data, error } = await supabase
        .from("transactions")
        .select("id, row_no, occurred_on, raw_text, status")
        .in("id", chunk)
        .returns<ReportSourceTransactionRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));
    }

    return rows;
  }

  async function fetchReportSourceLedgerRows(transactionIds: string[]) {
    const rows: LedgerRow[] = [];

    for (const chunk of chunkValues(transactionIds)) {
      const { data, error } = await supabase
        .from("ledger_entries")
        .select("transaction_id, account_id, category_id, direction, amount, review_status, metadata")
        .in("transaction_id", chunk)
        .returns<LedgerRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));
    }

    return rows;
  }

  async function fetchReportPackageItems(reportPackageIds: string[]) {
    const rows: ReportPackageItemRow[] = [];

    for (const chunk of chunkValues(reportPackageIds)) {
      const { data, error } = await supabase
        .from("report_package_items")
        .select("report_package_id, report_snapshot_id, position")
        .in("report_package_id", chunk)
        .order("position", { ascending: true })
        .returns<ReportPackageItemRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));
    }

    return rows.sort((left, right) => left.position - right.position);
  }

  async function fetchReportApprovalEvents(entityIds: string[]) {
    const rows: ApprovalEventRow[] = [];

    for (const chunk of chunkValues(entityIds)) {
      const { data, error } = await supabase
        .from("approval_events")
        .select("id, entity_type, entity_id, event_type, note, created_at")
        .in("entity_id", chunk)
        .in("entity_type", ["report_snapshot", "report_package"])
        .order("created_at", { ascending: true })
        .returns<ApprovalEventRow[]>();

      if (error) {
        throw new Error(error.message);
      }

      rows.push(...(data ?? []));
    }

    return rows.sort((left, right) => left.created_at.localeCompare(right.created_at));
  }

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
    reportSnapshots,
    reportPackages,
    categories
  ] = await Promise.all([
    supabase
      .from("accounts")
      .select("id, code, label, account_type, currency_code")
      .eq("workspace_id", workspaceId)
      .eq("is_active", true)
      .order("account_type", { ascending: true })
      .returns<AccountRow[]>(),
    supabase
      .from("transactions")
      .select("id", { count: "exact", head: true })
      .eq("workspace_id", workspaceId)
      .neq("status", "void"),
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
    fetchAllReportSnapshots(),
    fetchAllReportPackages(),
    supabase
      .from("categories")
      .select("id, code, direction, label, metadata, is_active")
      .eq("workspace_id", workspaceId)
      .eq("is_active", true)
      .returns<CategoryRow[]>()
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

  if (categories.error) {
    throw new Error(categories.error.message);
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
    transactionRows = await fetchAccountTransactions(activeAccount.id);
  }

  const [summaryTransactionRows, ledgerSummaryRows] = await Promise.all([
    fetchAllSummaryTransactions(),
    fetchAllLedgerRows()
  ]);

  const liveTransactionIds = new Set(summaryTransactionRows.filter((row) => row.status !== "void").map((row) => row.id));
  const liveLedgerSummaryRows = ledgerSummaryRows.filter((row) => liveTransactionIds.has(row.transaction_id));
  const activeTransactionIds = new Set(transactionRows.map((row) => row.id));
  const ledgerByTransactionId = new Map(
    liveLedgerSummaryRows
      .filter((row) => activeTransactionIds.has(row.transaction_id))
      .map((row) => [row.transaction_id, row])
  );
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

  const reportPackageIds = reportPackages.map((reportPackage) => reportPackage.id);
  const reportPackageItems = reportPackageIds.length > 0 ? await fetchReportPackageItems(reportPackageIds) : [];

  const reportIdsByPackageId = new Map<string, string[]>();

  for (const item of reportPackageItems ?? []) {
    const list = reportIdsByPackageId.get(item.report_package_id) ?? [];
    list.push(item.report_snapshot_id);
    reportIdsByPackageId.set(item.report_package_id, list);
  }

  const categoryById = new Map((categories.data ?? []).map((category) => [category.id, category]));
  const categoryByCode = new Map((categories.data ?? []).map((category) => [category.code, category]));
  const reportSourceIds = [...new Set(reportSnapshots.flatMap((report) => report.source_transaction_ids ?? []))];
  const reportSnapshotIds = reportSnapshots.map((report) => report.id);
  const reportEntityIds = [...reportSnapshotIds, ...reportPackageIds];
  const [reportSourceTransactions, reportSourceLedgerRows, reportApprovalEvents] = await Promise.all([
    reportSourceIds.length > 0 ? fetchReportSourceTransactions(reportSourceIds) : [],
    reportSourceIds.length > 0 ? fetchReportSourceLedgerRows(reportSourceIds) : [],
    reportEntityIds.length > 0 ? fetchReportApprovalEvents(reportEntityIds) : []
  ]);

  const approvalEventsByEntityId = groupApprovalEventsByEntityId(reportApprovalEvents);
  const exportVersionsByEntityId = await getExportVersionsByEntityId(supabase, workspaceId, reportEntityIds);
  const reportSourceTransactionById = new Map(reportSourceTransactions.map((row) => [row.id, row]));
  const reportSourceLedgerByTransactionId = new Map(reportSourceLedgerRows.map((row) => [row.transaction_id, row]));

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
    reportSnapshots: reportSnapshots.map((report) =>
      buildReportSnapshotSummary(
        report,
        buildReportSourceEntries(
          report.source_transaction_ids ?? [],
          reportSourceTransactionById,
          reportSourceLedgerByTransactionId,
          categoryById,
          categoryByCode
        ),
        approvalEventsByEntityId.get(report.id) ?? [],
        exportVersionsByEntityId.get(report.id) ?? []
      )
    ),
    reportPackages: reportPackages.map((reportPackage) => {
      const reportIds = reportIdsByPackageId.get(reportPackage.id) ?? [];

      return {
        id: reportPackage.id,
        title: reportPackage.title,
        status: reportPackage.status,
        reportIds,
        reportCount: reportIds.length,
        events: approvalEventsByEntityId.get(reportPackage.id) ?? [],
        exportVersions: exportVersionsByEntityId.get(reportPackage.id) ?? [],
        createdAt: reportPackage.created_at
      };
    }),
    categorySummary: buildCategorySummary(liveLedgerSummaryRows, categories.data ?? [])
  };
}

export async function getWorkspaceReportSnapshot(
  workspaceId: string,
  reportId: string
): Promise<WorkspaceReportDocument | null> {
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

  const [
    { data: workspace, error: workspaceError },
    { data: report, error: reportError },
    { data: categories, error: categoriesError },
    { data: approvalEvents, error: approvalEventsError }
  ] = await Promise.all([
      supabase
        .from("workspaces")
        .select("id, name, workspace_type, currency_code, status")
        .eq("id", workspaceId)
        .eq("status", "active")
        .maybeSingle<WorkspaceRow>(),
      supabase
        .from("report_snapshots")
        .select("id, title, period_start, period_end, status, source_transaction_ids, totals, created_at")
        .eq("workspace_id", workspaceId)
        .eq("id", reportId)
        .neq("status", "void")
        .maybeSingle<ReportSnapshotRow>(),
      supabase
        .from("categories")
        .select("id, code, direction, label, metadata, is_active")
        .eq("workspace_id", workspaceId)
        .eq("is_active", true)
        .returns<CategoryRow[]>(),
      supabase
        .from("approval_events")
        .select("id, entity_type, entity_id, event_type, note, created_at")
        .eq("workspace_id", workspaceId)
        .eq("entity_type", "report_snapshot")
        .eq("entity_id", reportId)
        .order("created_at", { ascending: true })
        .returns<ApprovalEventRow[]>()
    ]);

  if (workspaceError) {
    throw new Error(workspaceError.message);
  }

  if (reportError) {
    throw new Error(reportError.message);
  }

  if (categoriesError) {
    throw new Error(categoriesError.message);
  }

  if (approvalEventsError) {
    throw new Error(approvalEventsError.message);
  }

  if (!workspace || !report) {
    return null;
  }

  const sourceIds = report.source_transaction_ids ?? [];
  const [{ data: transactions, error: transactionsError }, { data: ledgerRows, error: ledgerError }] =
    sourceIds.length > 0
      ? await Promise.all([
          supabase
            .from("transactions")
            .select("id, row_no, occurred_on, raw_text, status")
            .in("id", sourceIds)
            .returns<ReportSourceTransactionRow[]>(),
          supabase
            .from("ledger_entries")
            .select("transaction_id, account_id, category_id, direction, amount, review_status, metadata")
            .in("transaction_id", sourceIds)
            .returns<LedgerRow[]>()
        ])
      : [
          { data: [], error: null },
          { data: [], error: null }
        ];

  if (transactionsError) {
    throw new Error(transactionsError.message);
  }

  if (ledgerError) {
    throw new Error(ledgerError.message);
  }

  const categoryById = new Map((categories ?? []).map((category) => [category.id, category]));
  const categoryByCode = new Map((categories ?? []).map((category) => [category.code, category]));
  const transactionById = new Map((transactions ?? []).map((row) => [row.id, row]));
  const ledgerByTransactionId = new Map((ledgerRows ?? []).map((row) => [row.transaction_id, row]));
  const entries = buildReportSourceEntries(sourceIds, transactionById, ledgerByTransactionId, categoryById, categoryByCode);
  const exportVersionsByEntityId = await getExportVersionsByEntityId(supabase, workspaceId, [reportId]);

  return {
    id: workspace.id,
    name: workspace.name,
    type: workspace.workspace_type,
    currency: workspace.currency_code,
    status: workspace.status,
    role: membership.role_code,
    accessScope: membership.access_scope,
    report: buildReportSnapshotSummary(
      report,
      entries,
      buildApprovalEventSummaries(approvalEvents),
      exportVersionsByEntityId.get(report.id) ?? []
    )
  };
}

export async function getWorkspaceReportPackage(
  workspaceId: string,
  packageId: string
): Promise<WorkspaceReportPackageDocument | null> {
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

  const [
    { data: workspace, error: workspaceError },
    { data: reportPackage, error: reportPackageError },
    { data: packageItems, error: packageItemsError },
    { data: categories, error: categoriesError },
    { data: packageApprovalEvents, error: packageApprovalEventsError }
  ] = await Promise.all([
    supabase
      .from("workspaces")
      .select("id, name, workspace_type, currency_code, status")
      .eq("id", workspaceId)
      .eq("status", "active")
      .maybeSingle<WorkspaceRow>(),
    supabase
      .from("report_packages")
      .select("id, title, status, created_at")
      .eq("workspace_id", workspaceId)
      .eq("id", packageId)
      .neq("status", "void")
      .maybeSingle<ReportPackageRow>(),
    supabase
      .from("report_package_items")
      .select("report_package_id, report_snapshot_id, position")
      .eq("workspace_id", workspaceId)
      .eq("report_package_id", packageId)
      .order("position", { ascending: true })
      .returns<ReportPackageItemRow[]>(),
    supabase
      .from("categories")
      .select("id, code, direction, label, metadata, is_active")
      .eq("workspace_id", workspaceId)
      .eq("is_active", true)
      .returns<CategoryRow[]>(),
    supabase
      .from("approval_events")
      .select("id, entity_type, entity_id, event_type, note, created_at")
      .eq("workspace_id", workspaceId)
      .eq("entity_type", "report_package")
      .eq("entity_id", packageId)
      .order("created_at", { ascending: true })
      .returns<ApprovalEventRow[]>()
  ]);

  if (workspaceError) {
    throw new Error(workspaceError.message);
  }

  if (reportPackageError) {
    throw new Error(reportPackageError.message);
  }

  if (packageItemsError) {
    throw new Error(packageItemsError.message);
  }

  if (categoriesError) {
    throw new Error(categoriesError.message);
  }

  if (packageApprovalEventsError) {
    throw new Error(packageApprovalEventsError.message);
  }

  if (!workspace || !reportPackage) {
    return null;
  }

  const reportIds = (packageItems ?? []).map((item) => item.report_snapshot_id);
  const exportVersionsByEntityId = await getExportVersionsByEntityId(supabase, workspaceId, [packageId, ...reportIds]);

  if (reportIds.length === 0) {
    return {
      id: workspace.id,
      name: workspace.name,
      type: workspace.workspace_type,
      currency: workspace.currency_code,
      status: workspace.status,
      role: membership.role_code,
      accessScope: membership.access_scope,
      reportPackage: {
        id: reportPackage.id,
        title: reportPackage.title,
        status: reportPackage.status,
        reportIds,
        reportCount: 0,
        events: buildApprovalEventSummaries(packageApprovalEvents),
        exportVersions: exportVersionsByEntityId.get(reportPackage.id) ?? [],
        createdAt: reportPackage.created_at
      },
      reports: []
    };
  }

  const { data: reports, error: reportsError } = await supabase
    .from("report_snapshots")
    .select("id, title, period_start, period_end, status, source_transaction_ids, totals, created_at")
    .eq("workspace_id", workspaceId)
    .in("id", reportIds)
    .neq("status", "void")
    .returns<ReportSnapshotRow[]>();

  if (reportsError) {
    throw new Error(reportsError.message);
  }

  const { data: reportApprovalEvents, error: reportApprovalEventsError } =
    reportIds.length > 0
      ? await supabase
          .from("approval_events")
          .select("id, entity_type, entity_id, event_type, note, created_at")
          .eq("workspace_id", workspaceId)
          .eq("entity_type", "report_snapshot")
          .in("entity_id", reportIds)
          .order("created_at", { ascending: true })
          .returns<ApprovalEventRow[]>()
      : { data: [], error: null };

  if (reportApprovalEventsError) {
    throw new Error(reportApprovalEventsError.message);
  }

  const sourceIds = [...new Set((reports ?? []).flatMap((report) => report.source_transaction_ids ?? []))];
  const [{ data: transactions, error: transactionsError }, { data: ledgerRows, error: ledgerError }] =
    sourceIds.length > 0
      ? await Promise.all([
          supabase
            .from("transactions")
            .select("id, row_no, occurred_on, raw_text, status")
            .in("id", sourceIds)
            .returns<ReportSourceTransactionRow[]>(),
          supabase
            .from("ledger_entries")
            .select("transaction_id, account_id, category_id, direction, amount, review_status, metadata")
            .in("transaction_id", sourceIds)
            .returns<LedgerRow[]>()
        ])
      : [
          { data: [], error: null },
          { data: [], error: null }
        ];

  if (transactionsError) {
    throw new Error(transactionsError.message);
  }

  if (ledgerError) {
    throw new Error(ledgerError.message);
  }

  const categoryById = new Map((categories ?? []).map((category) => [category.id, category]));
  const categoryByCode = new Map((categories ?? []).map((category) => [category.code, category]));
  const transactionById = new Map((transactions ?? []).map((row) => [row.id, row]));
  const ledgerByTransactionId = new Map((ledgerRows ?? []).map((row) => [row.transaction_id, row]));
  const reportById = new Map((reports ?? []).map((report) => [report.id, report]));
  const approvalEventsByEntityId = groupApprovalEventsByEntityId(reportApprovalEvents);
  const orderedReports = reportIds
    .map((reportId) => {
      const report = reportById.get(reportId);

      if (!report) {
        return null;
      }

      return buildReportSnapshotSummary(
        report,
        buildReportSourceEntries(
          report.source_transaction_ids ?? [],
          transactionById,
          ledgerByTransactionId,
          categoryById,
          categoryByCode
        ),
        approvalEventsByEntityId.get(report.id) ?? [],
        exportVersionsByEntityId.get(report.id) ?? []
      );
    })
    .filter((report): report is ReportSnapshotSummary => report !== null);

  return {
    id: workspace.id,
    name: workspace.name,
    type: workspace.workspace_type,
    currency: workspace.currency_code,
    status: workspace.status,
    role: membership.role_code,
    accessScope: membership.access_scope,
    reportPackage: {
      id: reportPackage.id,
      title: reportPackage.title,
      status: reportPackage.status,
      reportIds,
      reportCount: reportIds.length,
      events: buildApprovalEventSummaries(packageApprovalEvents),
      exportVersions: exportVersionsByEntityId.get(reportPackage.id) ?? [],
      createdAt: reportPackage.created_at
    },
    reports: orderedReports
  };
}
