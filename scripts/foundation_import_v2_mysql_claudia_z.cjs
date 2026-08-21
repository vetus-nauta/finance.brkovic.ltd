#!/usr/bin/env node

const crypto = require("node:crypto");
const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

const rootDir = path.resolve(__dirname, "..");
const envPath = path.join(rootDir, ".env.foundation.local");
const sourceWorkspaceId = "0d4faca6-3138-4ffe-9805-a6a29895b7ed";
const targetWorkspaceName = "Claudia Z";
const expectedFinalCash = 8356;

function readLocalEnvValue(name) {
  if (process.env[name]) return process.env[name];
  if (!fs.existsSync(envPath)) return "";
  for (const line of fs.readFileSync(envPath, "utf8").split(/\r?\n/)) {
    if (line.startsWith(`${name}=`)) return line.slice(name.length + 1).trim();
  }
  return "";
}

function argValue(name, fallback = "") {
  const index = process.argv.indexOf(name);
  return index >= 0 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

function latestPayloadPath() {
  const root = path.join(rootDir, "storage/production-audits");
  if (!fs.existsSync(root)) return "";
  const candidates = [];
  for (const dirent of fs.readdirSync(root, { withFileTypes: true })) {
    if (!dirent.isDirectory() || !dirent.name.startsWith("prod-db-sync-")) continue;
    const filePath = path.join(root, dirent.name, "v2-payload.json");
    if (fs.existsSync(filePath)) candidates.push(filePath);
  }
  candidates.sort((left, right) => fs.statSync(right).mtimeMs - fs.statSync(left).mtimeMs);
  return candidates[0] ?? "";
}

function table(payload, name) {
  const found = payload.tables.find((item) => item.name === name);
  if (!found) throw new Error(`Missing source table: ${name}`);
  return found.rows;
}

function parseJson(value, fallback) {
  if (value === null || value === undefined || value === "") return fallback;
  if (typeof value !== "string") return value;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

function amount(value) {
  if (value === null || value === undefined || value === "") return null;
  return Number(Number(value).toFixed(2));
}

function round2(value) {
  return Number(Number(value).toFixed(2));
}

function isoDate(value) {
  return String(value).slice(0, 10);
}

function mapTransactionStatus(oldEntry, reportedIds) {
  if (reportedIds.has(oldEntry.id)) return "included_in_report";
  if (oldEntry.status === "unrecognized" || oldEntry.status === "other_review" || oldEntry.status === "duplicate_suspect") {
    return "needs_review";
  }
  return "open";
}

function mapSourceType(value) {
  switch (value) {
    case "manual":
    case "import":
    case "correction":
      return value;
    case "accountable_report":
      return "expense_report";
    case "assistant":
      return "system";
    default:
      return "manual";
  }
}

function mapDirection(value) {
  if (value === "in") return "income";
  if (value === "out") return "expense";
  return "neutral";
}

function mapReviewStatus(value) {
  return value === "recognized" || value === "imported" || value === "accepted" || value === "corrected" ? "accepted" : "review";
}

function categoryDirection(value) {
  if (value === "income") return "income";
  if (value === "expense") return "expense";
  return "neutral";
}

function labelFromOldCategory(row) {
  const label = parseJson(row.name_json, {});
  return {
    ru: label.ru || row.code,
    en: label.en || row.code
  };
}

function reportTotals(entries, categoryById, accountByFlowType) {
  let income = 0;
  let expense = 0;
  let review = 0;
  const accountTotals = new Map();
  const categoryTotals = new Map();

  for (const entry of entries) {
    const entryAmount = amount(entry.amount);
    if (entryAmount === null || entry.direction === "none") continue;

    const direction = mapDirection(entry.direction);
    if (direction === "income") income += entryAmount;
    if (direction === "expense") expense += entryAmount;
    if (mapReviewStatus(entry.status) !== "accepted") review += 1;

    const account = accountByFlowType.get(entry.flow_type) ?? { code: "unknown", label: "Без счета" };
    const accountKey = account.code;
    const accountRow = accountTotals.get(accountKey) ?? {
      account_code: account.code,
      label: account.label,
      income_total: 0,
      expense_total: 0,
      entry_count: 0
    };
    accountRow.entry_count += 1;
    if (direction === "income") accountRow.income_total += entryAmount;
    if (direction === "expense") accountRow.expense_total += entryAmount;
    accountTotals.set(accountKey, accountRow);

    const category = categoryById.get(entry.category_id) ?? {
      code: entry.category_code || "uncategorized",
      label: { ru: "Без категории", en: "Uncategorized" }
    };
    const categoryKey = `${category.code}:${direction}`;
    const categoryRow = categoryTotals.get(categoryKey) ?? {
      category_code: category.code,
      label: category.label.ru || category.code,
      direction,
      total: 0,
      entry_count: 0,
      review_count: 0
    };
    categoryRow.total += entryAmount;
    categoryRow.entry_count += 1;
    if (mapReviewStatus(entry.status) !== "accepted") categoryRow.review_count += 1;
    categoryTotals.set(categoryKey, categoryRow);
  }

  return {
    currency: "EUR",
    entry_count: entries.length,
    review_count: review,
    income_total: round2(income),
    expense_total: round2(expense),
    net_total: round2(income - expense),
    accounts: [...accountTotals.values()].map((row) => ({
      ...row,
      income_total: round2(row.income_total),
      expense_total: round2(row.expense_total)
    })),
    categories: [...categoryTotals.values()].map((row) => ({
      ...row,
      total: round2(row.total)
    }))
  };
}

function buildImportModel(payload) {
  const oldEntries = table(payload, "v2_entries")
    .filter((entry) => entry.workspace_id === sourceWorkspaceId && entry.archived_at === null)
    .sort((left, right) => {
      const dateOrder = String(left.date).localeCompare(String(right.date));
      return dateOrder || Number(left.created_seq) - Number(right.created_seq);
    });
  const oldFlows = table(payload, "v2_flows").filter((flow) => flow.workspace_id === sourceWorkspaceId);
  const flowTypeById = new Map(oldFlows.map((flow) => [flow.id, flow.type]));
  const oldCategories = table(payload, "v2_categories").filter(
    (category) => category.workspace_id === null || category.workspace_id === sourceWorkspaceId
  );
  const categoryById = new Map(
    oldCategories.map((category) => [
      category.id,
      {
        id: category.id,
        code: category.code,
        label: labelFromOldCategory(category),
        direction: categoryDirection(category.direction)
      }
    ])
  );
  const oldReports = table(payload, "v2_report_batches")
    .filter((report) => report.workspace_id === sourceWorkspaceId && report.status === "created")
    .sort((left, right) => {
      const startOrder = String(left.start_date).localeCompare(String(right.start_date));
      return startOrder || String(left.end_date).localeCompare(String(right.end_date)) || String(left.created_at).localeCompare(String(right.created_at));
    });
  const reportEntryRows = table(payload, "v2_report_batch_entries");
  const reportEntryIdsByReportId = new Map();

  for (const row of reportEntryRows) {
    const list = reportEntryIdsByReportId.get(row.batch_id) ?? [];
    list.push({ entryId: row.entry_id, rowNumber: Number(row.row_number) || 0 });
    reportEntryIdsByReportId.set(row.batch_id, list);
  }

  const activeReportSourceIds = new Set();
  for (const report of oldReports) {
    const idsFromRows = (reportEntryIdsByReportId.get(report.id) ?? [])
      .sort((left, right) => left.rowNumber - right.rowNumber)
      .map((row) => row.entryId);
    const idsFromJson = parseJson(report.source_entry_ids_json, []);
    const ids = idsFromRows.length > 0 ? idsFromRows : idsFromJson;
    report.sourceIds = ids;
    for (const id of ids) activeReportSourceIds.add(id);
  }

  const oldEntryById = new Map(oldEntries.map((entry) => [entry.id, entry]));
  const computedCash = oldEntries.reduce((total, entry) => {
    if (entry.amount === null || flowTypeById.get(entry.flow_id) !== "cash") return total;
    if (entry.direction === "in") return total + amount(entry.amount);
    if (entry.direction === "out") return total - amount(entry.amount);
    return total;
  }, 0);
  const bridgeAmount = round2(expectedFinalCash - computedCash);
  const bridgeId = "11111111-1260-4b1d-9a76-c1a0d1a00001";

  const bridgeEntry = {
    id: bridgeId,
    created_seq: 0,
    workspace_id: sourceWorkspaceId,
    flow_id: oldFlows.find((flow) => flow.type === "cash")?.id ?? "",
    flow_type: "cash",
    created_by: null,
    actor_id: null,
    date: "2025-04-10",
    raw_text: `+${bridgeAmount.toFixed(2)} входящий остаток по исторической смычке`,
    sign: "+",
    amount: bridgeAmount.toFixed(2),
    direction: "in",
    entry_type: "correction",
    category_id: "__opening_balance_bridge__",
    category_code: "opening_balance_bridge",
    status: "accepted",
    balance_after: null,
    source_type: "system",
    source_id: null,
    source_row_id: null,
    notes: "Системная строка переноса: разница между принятой финальной суммой старого MVP и арифметической суммой v2_entries.",
    confidence: null,
    matched_rules_json: "[]",
    created_at: "2026-08-21 00:00:00",
    updated_at: null,
    archived_at: null
  };

  const entries = [bridgeEntry, ...oldEntries.map((entry) => ({
    ...entry,
    flow_type: flowTypeById.get(entry.flow_id) ?? "cash",
    category_code: categoryById.get(entry.category_id)?.code ?? null
  }))].sort((left, right) => {
    const dateOrder = String(left.date).localeCompare(String(right.date));
    return dateOrder || Number(left.created_seq) - Number(right.created_seq);
  });

  const rowNoByEntryId = new Map();
  entries.forEach((entry, index) => rowNoByEntryId.set(entry.id, index + 1));

  const bridgeReport = {
    id: "22222222-1260-4b1d-9a76-c1a0d1a00001",
    title: "Смычка входящего остатка · перенос v2",
    status: "created",
    start_date: "2025-04-10",
    end_date: "2025-04-10",
    sourceIds: [bridgeId],
    entry_count: 1,
    generated_at: "2026-08-21 00:00:00",
    closed_at: "2025-04-10 23:59:59",
    summary_json: "{}",
    content_hash: crypto.createHash("sha256").update(`${bridgeId}:${bridgeAmount}`).digest("hex")
  };
  const reports = [bridgeReport, ...oldReports];
  const reportedIds = new Set(activeReportSourceIds);
  reportedIds.add(bridgeId);

  const entriesWithRows = entries.map((entry) => ({
    ...entry,
    row_no: rowNoByEntryId.get(entry.id),
    target_status: mapTransactionStatus(entry, reportedIds)
  }));

  const finalNet = entriesWithRows.reduce((total, entry) => {
    const entryAmount = amount(entry.amount);
    if (entryAmount === null) return total;
    if (entry.direction === "in") return total + entryAmount;
    if (entry.direction === "out") return total - entryAmount;
    return total;
  }, 0);
  const finalCash = entriesWithRows.reduce((total, entry) => {
    const entryAmount = amount(entry.amount);
    if (entryAmount === null || entry.flow_type !== "cash") return total;
    if (entry.direction === "in") return total + entryAmount;
    if (entry.direction === "out") return total - entryAmount;
    return total;
  }, 0);
  const finalCard = entriesWithRows.reduce((total, entry) => {
    const entryAmount = amount(entry.amount);
    if (entryAmount === null || entry.flow_type !== "card") return total;
    if (entry.direction === "in") return total + entryAmount;
    if (entry.direction === "out") return total - entryAmount;
    return total;
  }, 0);

  return {
    entries: entriesWithRows,
    reports,
    categoryById,
    oldCategories,
    oldEntryById: new Map(entriesWithRows.map((entry) => [entry.id, entry])),
    flowTypeById,
    bridgeAmount,
    finalCash: round2(finalCash),
    finalCard: round2(finalCard),
    finalNet: round2(finalNet)
  };
}

async function queryJson(client, sql, params = []) {
  const result = await client.query(sql, params);
  return result.rows;
}

async function backupTarget(client, workspaceId) {
  const backup = {
    generated_at: new Date().toISOString(),
    workspace_id: workspaceId,
    tables: {}
  };
  for (const tableName of [
    "transactions",
    "ledger_entries",
    "period_closures",
    "report_snapshots",
    "report_packages",
    "report_package_items",
    "document_links",
    "documents",
    "document_versions",
    "approval_events"
  ]) {
    backup.tables[tableName] = await queryJson(client, `select * from public.${tableName} where workspace_id = $1`, [workspaceId]);
  }
  const dir = path.join(rootDir, "storage/foundation-transfer", new Date().toISOString().replace(/[:.]/g, "-"));
  fs.mkdirSync(dir, { recursive: true });
  const filePath = path.join(dir, "supabase-target-before-import.json");
  fs.writeFileSync(filePath, JSON.stringify(backup, null, 2));
  return filePath;
}

async function clearTargetWorkspace(client, workspaceId) {
  for (const sql of [
    "delete from public.document_links where workspace_id = $1",
    "delete from public.document_versions where workspace_id = $1",
    "delete from public.documents where workspace_id = $1",
    "delete from public.report_package_items where workspace_id = $1",
    "delete from public.report_packages where workspace_id = $1",
    "delete from public.report_snapshots where workspace_id = $1",
    "delete from public.period_closures where workspace_id = $1",
    "delete from public.approval_events where workspace_id = $1",
    "delete from public.ledger_entries where workspace_id = $1",
    "delete from public.transactions where workspace_id = $1"
  ]) {
    await client.query(sql, [workspaceId]);
  }
}

async function ensureCategories(client, workspace, model) {
  await client.query(
    `
      insert into public.categories (organization_id, workspace_id, code, direction, label, metadata, is_active)
      values ($1, $2, 'opening_balance_bridge', 'income', '{"ru":"Входящий остаток / перенос","en":"Opening balance transfer"}'::jsonb, '{"system":"foundation_transfer"}'::jsonb, true)
      on conflict (organization_id, workspace_id, code) do update
      set direction = excluded.direction,
          label = excluded.label,
          metadata = public.categories.metadata || excluded.metadata,
          is_active = true,
          updated_at = now()
    `,
    [workspace.organization_id, workspace.id]
  );

  for (const oldCategory of model.oldCategories) {
    const label = labelFromOldCategory(oldCategory);
    await client.query(
      `
        insert into public.categories (organization_id, workspace_id, code, direction, label, metadata, is_active)
        values ($1, $2, $3, $4, $5::jsonb, $6::jsonb, $7)
        on conflict (organization_id, workspace_id, code) do update
        set label = excluded.label,
            direction = excluded.direction,
            metadata = public.categories.metadata || excluded.metadata,
            is_active = excluded.is_active,
            updated_at = now()
      `,
      [
        workspace.organization_id,
        workspace.id,
        oldCategory.code,
        categoryDirection(oldCategory.direction),
        JSON.stringify(label),
        JSON.stringify({ source: "v2_mysql_transfer", old_category_id: oldCategory.id }),
        oldCategory.is_active === 1
      ]
    );
  }

  const result = await client.query("select id, code, label from public.categories where workspace_id = $1", [workspace.id]);
  return new Map(result.rows.map((row) => [row.code, row]));
}

async function insertEntries(client, workspace, model, accountByCode, categoryByCode, userId) {
  const transactionRows = [];
  const ledgerRows = [];

  for (const entry of model.entries) {
    const account = accountByCode.get(entry.flow_type === "card" ? "card" : "cash");
    const metadata = {
      source: "v2_mysql_transfer",
      old_workspace_id: sourceWorkspaceId,
      old_entry_id: entry.id,
      old_created_seq: Number(entry.created_seq) || null,
      old_status: entry.status,
      old_entry_type: entry.entry_type,
      old_balance_after: entry.balance_after,
      old_source_type: entry.source_type,
      old_source_id: entry.source_id,
      old_source_row_id: entry.source_row_id,
      old_flow_type: entry.flow_type,
      notes: entry.notes
    };

    transactionRows.push({
      id: entry.id,
      organization_id: workspace.organization_id,
      workspace_id: workspace.id,
      account_id: account?.id ?? null,
      created_by: userId,
      source_type: mapSourceType(entry.source_type),
      source_id: null,
      occurred_on: isoDate(entry.date),
      row_no: entry.row_no,
      raw_text: entry.raw_text,
      status: entry.target_status,
      metadata,
      created_at: entry.created_at,
      updated_at: entry.updated_at ?? entry.created_at
    });

    const entryAmount = amount(entry.amount);
    const ledgerDirection = entryAmount === null || entry.direction === "none" ? "neutral" : mapDirection(entry.direction);

    const categoryCode = entry.category_code ?? "other";
    const category = categoryByCode.get(categoryCode);
    ledgerRows.push({
      organization_id: workspace.organization_id,
      workspace_id: workspace.id,
      transaction_id: entry.id,
      account_id: account?.id ?? null,
      category_id: category?.id ?? null,
      direction: ledgerDirection,
      amount: entryAmount ?? 0,
      currency_code: "EUR",
      review_status: mapReviewStatus(entry.status),
      metadata: {
        source: "v2_mysql_transfer",
        old_category_code: categoryCode,
        old_flow_type: entry.flow_type,
        old_entry_type: entry.entry_type
      },
      created_at: entry.created_at,
      updated_at: entry.updated_at ?? entry.created_at
    });
  }

  await client.query(
    `
      insert into public.transactions (
        id, organization_id, workspace_id, account_id, created_by, source_type, source_id,
        occurred_on, row_no, raw_text, status, metadata, created_at, updated_at
      )
      select
        r.id, r.organization_id, r.workspace_id, r.account_id, r.created_by, r.source_type, r.source_id,
        r.occurred_on, r.row_no, r.raw_text, r.status, r.metadata,
        coalesce(r.created_at, now()), coalesce(r.updated_at, now())
      from jsonb_to_recordset($1::jsonb) as r(
        id uuid,
        organization_id uuid,
        workspace_id uuid,
        account_id uuid,
        created_by uuid,
        source_type text,
        source_id uuid,
        occurred_on date,
        row_no integer,
        raw_text text,
        status text,
        metadata jsonb,
        created_at timestamptz,
        updated_at timestamptz
      )
    `,
    [JSON.stringify(transactionRows)]
  );

  await client.query(
    `
      insert into public.ledger_entries (
        organization_id, workspace_id, transaction_id, account_id, category_id,
        direction, amount, currency_code, review_status, metadata, created_at, updated_at
      )
      select
        r.organization_id, r.workspace_id, r.transaction_id, r.account_id, r.category_id,
        r.direction, r.amount, r.currency_code, r.review_status, r.metadata,
        coalesce(r.created_at, now()), coalesce(r.updated_at, now())
      from jsonb_to_recordset($1::jsonb) as r(
        organization_id uuid,
        workspace_id uuid,
        transaction_id uuid,
        account_id uuid,
        category_id uuid,
        direction text,
        amount numeric,
        currency_code char(3),
        review_status text,
        metadata jsonb,
        created_at timestamptz,
        updated_at timestamptz
      )
    `,
    [JSON.stringify(ledgerRows)]
  );
}

async function insertReports(client, workspace, model, accountByFlowType, categoryById, userId) {
  for (const report of model.reports) {
    const sourceIds = report.sourceIds.filter((id) => model.oldEntryById.has(id));
    const sourceEntries = sourceIds.map((id) => model.oldEntryById.get(id));
    const totals = reportTotals(sourceEntries, categoryById, accountByFlowType);
    const oldSummary = parseJson(report.summary_json, {});
    totals.period_start = isoDate(report.start_date);
    totals.period_end = isoDate(report.end_date);
    totals.v2_source = {
      old_report_id: report.id,
      old_title: report.title,
      old_summary_totals: oldSummary.totals ?? null,
      old_status: report.status
    };

    const closureId = crypto.randomUUID();
    await client.query(
      `
        insert into public.period_closures (
          id, organization_id, workspace_id, period_start, period_end, status, closed_by, closed_at, metadata, created_at, updated_at
        ) values (
          $1, $2, $3, $4, $5, 'closed', $6, coalesce($7::timestamptz, now()), $8::jsonb, coalesce($9::timestamptz, now()), coalesce($10::timestamptz, now())
        )
      `,
      [
        closureId,
        workspace.organization_id,
        workspace.id,
        isoDate(report.start_date),
        isoDate(report.end_date),
        userId,
        report.closed_at ?? report.generated_at ?? report.created_at,
        JSON.stringify({
          source: "v2_mysql_transfer",
          old_report_id: report.id,
          source_transaction_ids: sourceIds,
          entry_count: sourceIds.length
        }),
        report.created_at ?? report.generated_at,
        report.updated_at ?? report.created_at ?? report.generated_at
      ]
    );

    await client.query(
      `
        insert into public.report_snapshots (
          id, organization_id, workspace_id, period_closure_id, title, period_start, period_end,
          status, source_transaction_ids, totals, content_hash, created_by, created_at, updated_at
        ) values (
          $1, $2, $3, $4, $5, $6, $7,
          'created', $8::uuid[], $9::jsonb, $10, $11, coalesce($12::timestamptz, now()), coalesce($13::timestamptz, now())
        )
      `,
      [
        report.id,
        workspace.organization_id,
        workspace.id,
        closureId,
        report.title,
        isoDate(report.start_date),
        isoDate(report.end_date),
        sourceIds,
        JSON.stringify(totals),
        report.content_hash ?? crypto.createHash("sha256").update(JSON.stringify(totals)).digest("hex"),
        userId,
        report.created_at ?? report.generated_at,
        report.updated_at ?? report.created_at ?? report.generated_at
      ]
    );

    await client.query(
      `
        insert into public.approval_events (
          organization_id, workspace_id, entity_type, entity_id, event_type, actor_user_id, metadata, created_at
        ) values (
          $1, $2, 'report_snapshot', $3, 'report_snapshot_created', $4, $5::jsonb, coalesce($6::timestamptz, now())
        )
      `,
      [
        workspace.organization_id,
        workspace.id,
        report.id,
        userId,
        JSON.stringify({
          source: "v2_mysql_transfer",
          period_start: isoDate(report.start_date),
          period_end: isoDate(report.end_date),
          entry_count: sourceIds.length
        }),
        report.created_at ?? report.generated_at
      ]
    );
  }
}

async function verifyImport(client, workspaceId) {
  const result = await client.query(
    `
      with tx as (
        select t.id, t.status
        from public.transactions t
        where t.workspace_id = $1
          and t.status <> 'void'
      ),
      le as (
        select le.*
        from public.ledger_entries le
        join tx on tx.id = le.transaction_id
        where le.workspace_id = $1
      )
      select
        (select count(*) from tx)::integer as transaction_count,
        (select count(*) from le)::integer as ledger_count,
        coalesce((select sum(amount) from le where direction = 'income'), 0)::numeric(14,2) as income_total,
        coalesce((select sum(amount) from le where direction = 'expense'), 0)::numeric(14,2) as expense_total,
        (
          coalesce((select sum(amount) from le where direction = 'income'), 0)
          - coalesce((select sum(amount) from le where direction = 'expense'), 0)
        )::numeric(14,2) as net_total,
        (select count(*) from public.report_snapshots where workspace_id = $1 and status <> 'void')::integer as report_count,
        (select count(*) from tx where status = 'open')::integer as open_count,
        (select count(*) from tx where status = 'included_in_report')::integer as reported_count,
        coalesce((
          select coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)
            - coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)
          from le
          join public.accounts a on a.id = le.account_id
          where a.code = 'cash'
        ), 0)::numeric(14,2) as cash_net,
        coalesce((
          select coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)
            - coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)
          from le
          join public.accounts a on a.id = le.account_id
          where a.code = 'card'
        ), 0)::numeric(14,2) as card_net
    `,
    [workspaceId]
  );
  return result.rows[0];
}

async function main() {
  const payloadPath = argValue("--payload", latestPayloadPath());
  const replace = process.argv.includes("--replace");
  const dryRun = process.argv.includes("--dry-run");
  const connectionString = readLocalEnvValue("SUPABASE_DB_POOLER_URL");

  if (!connectionString) throw new Error("SUPABASE_DB_POOLER_URL is not set.");
  if (!payloadPath) throw new Error("Payload path not found. Run scripts/v2_export_v2_mysql_payload.php first.");

  const payload = JSON.parse(fs.readFileSync(payloadPath, "utf8"));
  const model = buildImportModel(payload);

  console.log(JSON.stringify({
    payload: payloadPath,
    source_entries: model.entries.length - 1,
    bridge_amount: model.bridgeAmount,
    final_cash: model.finalCash,
    final_card: model.finalCard,
    final_net: model.finalNet,
    reports: model.reports.length,
    dry_run: dryRun,
    replace
  }, null, 2));

  if (model.finalCash !== expectedFinalCash) {
    throw new Error(`Import model final cash ${model.finalCash} does not match accepted final cash ${expectedFinalCash}.`);
  }

  if (dryRun) return;
  if (!replace) throw new Error("Refusing to import without --replace.");

  const client = new Client({ connectionString, ssl: { rejectUnauthorized: false } });
  await client.connect();
  try {
    await client.query("begin");
    const workspaceResult = await client.query(
      "select id, organization_id, name, currency_code from public.workspaces where name = $1 and status = 'active' limit 1",
      [targetWorkspaceName]
    );
    const workspace = workspaceResult.rows[0];
    if (!workspace) throw new Error(`Target workspace not found: ${targetWorkspaceName}`);

    const userResult = await client.query(
      "select id from auth.users where lower(email) = lower($1) order by created_at desc limit 1",
      ["vetus.nauta@gmail.com"]
    );
    const userId = userResult.rows[0]?.id ?? null;
    const targetBackupPath = await backupTarget(client, workspace.id);

    await clearTargetWorkspace(client, workspace.id);
    const accountRows = await client.query("select id, code, label from public.accounts where workspace_id = $1", [workspace.id]);
    const accountByCode = new Map(accountRows.rows.map((row) => [row.code, row]));
    const accountByFlowType = new Map([
      ["cash", accountByCode.get("cash")],
      ["card", accountByCode.get("card")]
    ]);
    const categoryByCode = await ensureCategories(client, workspace, model);
    const categoryByOldId = new Map(model.categoryById);
    categoryByOldId.set("__opening_balance_bridge__", {
      code: "opening_balance_bridge",
      label: { ru: "Входящий остаток / перенос", en: "Opening balance transfer" }
    });

    await insertEntries(client, workspace, model, accountByCode, categoryByCode, userId);
    await insertReports(client, workspace, model, accountByFlowType, categoryByOldId, userId);

    const verification = await verifyImport(client, workspace.id);
    if (round2(verification.cash_net) !== expectedFinalCash) {
      throw new Error(`Imported cash ${verification.cash_net} does not match ${expectedFinalCash}.`);
    }

    await client.query("commit");
    console.log(JSON.stringify({ ok: true, target_backup_path: targetBackupPath, verification }, null, 2));
  } catch (error) {
    try {
      await client.query("rollback");
    } catch {
      // ignore rollback failure
    }
    throw error;
  } finally {
    await client.end();
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
