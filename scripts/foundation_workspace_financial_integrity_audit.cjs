#!/usr/bin/env node

const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

const rootDir = path.resolve(__dirname, "..");
const envPath = path.join(rootDir, ".env.foundation.local");

function readLocalEnvValue(name) {
  const fromProcess = process.env[name];
  if (fromProcess) return fromProcess;

  if (!fs.existsSync(envPath)) return "";

  for (const line of fs.readFileSync(envPath, "utf8").split(/\r?\n/)) {
    if (line.startsWith(`${name}=`)) return line.slice(name.length + 1).trim();
  }

  return "";
}

function argValue(name, fallback = "") {
  const index = process.argv.indexOf(name);
  if (index >= 0 && process.argv[index + 1]) return process.argv[index + 1];
  return fallback;
}

function asNumber(value) {
  if (value === null || value === undefined) return 0;
  return Number(value);
}

function money(value) {
  return asNumber(value).toLocaleString("ru-RU", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

async function loadWorkspaces(client, requestedWorkspaceName) {
  const params = [];
  let where = "w.status = 'active'";

  if (requestedWorkspaceName) {
    params.push(requestedWorkspaceName);
    where += ` and w.name = $${params.length}`;
  }

  const result = await client.query(
    `
      select w.id, w.name, w.currency_code
      from public.workspaces w
      where ${where}
      order by w.name
    `,
    params
  );

  return result.rows;
}

async function auditWorkspace(client, workspace) {
  const params = [workspace.id];
  const result = await client.query(
    `
      with
      live_transactions as (
        select t.id, t.row_no, t.occurred_on, t.raw_text, t.status, t.account_id
        from public.transactions t
        where t.workspace_id = $1
          and t.status <> 'void'
      ),
      live_ledger as (
        select le.transaction_id, le.account_id, le.direction, le.amount, le.review_status
        from public.ledger_entries le
        join live_transactions t on t.id = le.transaction_id
        where le.workspace_id = $1
      ),
      report_sources as (
        select rs.id as report_id, rs.title, rs.period_start, rs.period_end, rs.status as report_status,
          source.transaction_id, source.ordinality
        from public.report_snapshots rs
        cross join lateral unnest(rs.source_transaction_ids) with ordinality as source(transaction_id, ordinality)
        where rs.workspace_id = $1
          and rs.status <> 'void'
      ),
      report_source_unique as (
        select distinct transaction_id
        from report_sources
      ),
      report_actuals as (
        select rs.id as report_id,
          rs.title,
          rs.period_start,
          rs.period_end,
          rs.status,
          rs.totals,
          cardinality(rs.source_transaction_ids) as snapshot_entry_count,
          coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2) as actual_income_total,
          coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2) as actual_expense_total,
          coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2)
            - coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2) as actual_net_total,
          count(distinct t.id)::integer as actual_entry_count,
          count(distinct t.id) filter (
            where t.status = 'needs_review'
              or coalesce(le.review_status, 'review') <> 'accepted'
          )::integer as actual_review_count
        from public.report_snapshots rs
        left join lateral unnest(rs.source_transaction_ids) as source(transaction_id) on true
        left join public.transactions t on t.id = source.transaction_id and t.workspace_id = rs.workspace_id and t.status <> 'void'
        left join public.ledger_entries le on le.transaction_id = t.id and le.workspace_id = rs.workspace_id
        where rs.workspace_id = $1
          and rs.status <> 'void'
        group by rs.id
      ),
      report_mismatches as (
        select report_id, title, period_start, period_end, status,
          snapshot_entry_count,
          actual_entry_count,
          coalesce((totals->>'entry_count')::integer, snapshot_entry_count) as stored_entry_count,
          coalesce((totals->>'review_count')::integer, 0) as stored_review_count,
          actual_review_count,
          coalesce((totals->>'income_total')::numeric, 0)::numeric(14,2) as stored_income_total,
          actual_income_total,
          coalesce((totals->>'expense_total')::numeric, 0)::numeric(14,2) as stored_expense_total,
          actual_expense_total,
          coalesce((totals->>'net_total')::numeric, 0)::numeric(14,2) as stored_net_total,
          actual_net_total
        from report_actuals
        where coalesce((totals->>'entry_count')::integer, snapshot_entry_count) <> actual_entry_count
          or coalesce((totals->>'review_count')::integer, 0) <> actual_review_count
          or coalesce((totals->>'income_total')::numeric, 0)::numeric(14,2) <> actual_income_total
          or coalesce((totals->>'expense_total')::numeric, 0)::numeric(14,2) <> actual_expense_total
          or coalesce((totals->>'net_total')::numeric, 0)::numeric(14,2) <> actual_net_total
      ),
      duplicate_report_sources as (
        select rs.transaction_id,
          count(*)::integer as usage_count,
          jsonb_agg(jsonb_build_object(
            'report_id', rs.report_id,
            'title', rs.title,
            'period_start', rs.period_start,
            'period_end', rs.period_end,
            'status', rs.report_status
          ) order by rs.period_start, rs.period_end, rs.title) as reports
        from report_sources rs
        group by rs.transaction_id
        having count(*) > 1
      ),
      status_mismatches as (
        select t.id, t.row_no, t.occurred_on, t.raw_text, t.status,
          case
            when rsu.transaction_id is not null and t.status not in ('included_in_report', 'closed')
              then 'reported_transaction_not_locked'
            when rsu.transaction_id is null and t.status in ('included_in_report', 'closed')
              then 'locked_transaction_without_live_report'
            else 'ok'
          end as issue
        from live_transactions t
        left join report_source_unique rsu on rsu.transaction_id = t.id
        where (rsu.transaction_id is not null and t.status not in ('included_in_report', 'closed'))
          or (rsu.transaction_id is null and t.status in ('included_in_report', 'closed'))
      ),
      missing_ledger as (
        select t.id, t.row_no, t.occurred_on, t.raw_text, t.status
        from live_transactions t
        left join live_ledger le on le.transaction_id = t.id
        where le.transaction_id is null
      ),
      missing_report_transactions as (
        select rs.report_id, rs.title, rs.period_start, rs.period_end, rs.transaction_id
        from report_sources rs
        left join public.transactions t on t.id = rs.transaction_id and t.workspace_id = $1 and t.status <> 'void'
        where t.id is null
      ),
      balances_by_account as (
        select coalesce(a.code, 'unknown') as account_code,
          coalesce(a.label, 'Без счета') as account_label,
          coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2) as total_income,
          coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2) as total_expense,
          coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2)
            - coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2) as total_net,
          coalesce(sum(le.amount) filter (where le.direction = 'income' and rsu.transaction_id is not null), 0)::numeric(14,2) as closed_income,
          coalesce(sum(le.amount) filter (where le.direction = 'expense' and rsu.transaction_id is not null), 0)::numeric(14,2) as closed_expense,
          coalesce(sum(le.amount) filter (where le.direction = 'income' and rsu.transaction_id is null), 0)::numeric(14,2) as open_income,
          coalesce(sum(le.amount) filter (where le.direction = 'expense' and rsu.transaction_id is null), 0)::numeric(14,2) as open_expense,
          count(distinct le.transaction_id)::integer as entry_count
        from live_ledger le
        left join public.accounts a on a.id = le.account_id
        left join report_source_unique rsu on rsu.transaction_id = le.transaction_id
        group by coalesce(a.code, 'unknown'), coalesce(a.label, 'Без счета')
        order by coalesce(a.code, 'unknown')
      ),
      totals as (
        select
          (select count(*) from live_transactions)::integer as live_transaction_count,
          (select count(*) from live_ledger)::integer as live_ledger_count,
          (select count(*) from public.report_snapshots where workspace_id = $1 and status <> 'void')::integer as report_count,
          (select count(*) from report_sources)::integer as report_source_count,
          (select count(*) from report_source_unique)::integer as unique_report_source_count,
          (select count(*) from missing_ledger)::integer as missing_ledger_count,
          (select count(*) from missing_report_transactions)::integer as missing_report_transaction_count,
          (select count(*) from duplicate_report_sources)::integer as duplicate_report_source_count,
          (select count(*) from status_mismatches)::integer as status_mismatch_count,
          (select count(*) from report_mismatches)::integer as report_mismatch_count,
          coalesce((select sum(total_income) from balances_by_account), 0)::numeric(14,2) as total_income,
          coalesce((select sum(total_expense) from balances_by_account), 0)::numeric(14,2) as total_expense,
          coalesce((select sum(total_net) from balances_by_account), 0)::numeric(14,2) as total_net,
          coalesce((select sum(closed_income) from balances_by_account), 0)::numeric(14,2) as closed_income,
          coalesce((select sum(closed_expense) from balances_by_account), 0)::numeric(14,2) as closed_expense,
          coalesce((select sum(closed_income - closed_expense) from balances_by_account), 0)::numeric(14,2) as closed_net,
          coalesce((select sum(open_income) from balances_by_account), 0)::numeric(14,2) as open_income,
          coalesce((select sum(open_expense) from balances_by_account), 0)::numeric(14,2) as open_expense,
          coalesce((select sum(open_income - open_expense) from balances_by_account), 0)::numeric(14,2) as open_net
      )
      select jsonb_build_object(
        'totals', (select to_jsonb(totals) from totals),
        'balances_by_account', coalesce((select jsonb_agg(to_jsonb(balances_by_account)) from balances_by_account), '[]'::jsonb),
        'report_mismatches', coalesce((select jsonb_agg(to_jsonb(report_mismatches) order by period_start, period_end, title) from report_mismatches), '[]'::jsonb),
        'duplicate_report_sources', coalesce((select jsonb_agg(to_jsonb(duplicate_report_sources)) from duplicate_report_sources), '[]'::jsonb),
        'status_mismatches', coalesce((select jsonb_agg(to_jsonb(status_mismatches) order by row_no nulls last, occurred_on) from status_mismatches), '[]'::jsonb),
        'missing_ledger', coalesce((select jsonb_agg(to_jsonb(missing_ledger) order by row_no nulls last, occurred_on) from missing_ledger), '[]'::jsonb),
        'missing_report_transactions', coalesce((select jsonb_agg(to_jsonb(missing_report_transactions) order by period_start, period_end) from missing_report_transactions), '[]'::jsonb)
      ) as audit
    `,
    params
  );

  return result.rows[0].audit;
}

function printWorkspaceAudit(workspace, audit) {
  const totals = audit.totals;
  const hardIssues =
    totals.report_mismatch_count +
    totals.duplicate_report_source_count +
    totals.status_mismatch_count +
    totals.missing_report_transaction_count;

  console.log(`\n${workspace.name} (${workspace.currency_code})`);
  console.log("-".repeat(Math.max(32, workspace.name.length + 8)));
  console.log(`Лента: ${totals.live_transaction_count} строк, ledger: ${totals.live_ledger_count} строк`);
  console.log(`Отчеты: ${totals.report_count}, строк в отчетах: ${totals.unique_report_source_count}/${totals.report_source_count}`);
  console.log(`Всего: приход ${money(totals.total_income)}, расход ${money(totals.total_expense)}, остаток ${money(totals.total_net)}`);
  console.log(`Закрыто отчетами: приход ${money(totals.closed_income)}, расход ${money(totals.closed_expense)}, нетто ${money(totals.closed_net)}`);
  console.log(`Открытый остаток: приход ${money(totals.open_income)}, расход ${money(totals.open_expense)}, нетто ${money(totals.open_net)}`);
  console.log(`Контроль: закрыто + открыто = ${money(asNumber(totals.closed_net) + asNumber(totals.open_net))}`);

  for (const account of audit.balances_by_account) {
    const openNet = asNumber(account.open_income) - asNumber(account.open_expense);
    const closedNet = asNumber(account.closed_income) - asNumber(account.closed_expense);
    console.log(
      `  ${account.account_label}: всего ${money(account.total_net)}, закрыто ${money(closedNet)}, открыто ${money(openNet)}, строк ${account.entry_count}`
    );
  }

  console.log(
    `Проблемы: totals ${totals.report_mismatch_count}, дубли ${totals.duplicate_report_source_count}, статусы ${totals.status_mismatch_count}, missing report rows ${totals.missing_report_transaction_count}, missing ledger ${totals.missing_ledger_count}`
  );

  if (hardIssues > 0) {
    console.log("Жесткие расхождения:");
    for (const mismatch of audit.report_mismatches.slice(0, 10)) {
      console.log(
        `  totals: ${mismatch.title} ${mismatch.period_start}..${mismatch.period_end} stored net ${money(mismatch.stored_net_total)} actual net ${money(mismatch.actual_net_total)}`
      );
    }
    for (const duplicate of audit.duplicate_report_sources.slice(0, 10)) {
      console.log(`  duplicate source: ${duplicate.transaction_id} used ${duplicate.usage_count} times`);
    }
    for (const status of audit.status_mismatches.slice(0, 10)) {
      console.log(`  status: #${status.row_no ?? "?"} ${status.status} ${status.issue} · ${status.raw_text}`);
    }
    for (const missing of audit.missing_report_transactions.slice(0, 10)) {
      console.log(`  missing report row: ${missing.title} · ${missing.transaction_id}`);
    }
  }

  if (totals.missing_ledger_count > 0) {
    console.log("Строки без ledger, проверить вручную:");
    for (const row of audit.missing_ledger.slice(0, 10)) {
      console.log(`  #${row.row_no ?? "?"} ${row.occurred_on} ${row.status} · ${row.raw_text}`);
    }
  }

  return hardIssues;
}

async function main() {
  const connectionString = readLocalEnvValue("SUPABASE_DB_POOLER_URL");
  const workspaceName = argValue("--workspace-name", process.env.FINDESK_AUDIT_WORKSPACE_NAME || "");
  const jsonOutput = process.argv.includes("--json");

  if (!connectionString) {
    throw new Error("SUPABASE_DB_POOLER_URL is not set.");
  }

  const client = new Client({
    connectionString,
    ssl: { rejectUnauthorized: false }
  });

  await client.connect();
  try {
    await client.query("begin read only");
    const workspaces = await loadWorkspaces(client, workspaceName);

    if (workspaces.length === 0) {
      throw new Error(workspaceName ? `Workspace not found: ${workspaceName}` : "No active workspaces found.");
    }

    const results = [];
    let hardIssueCount = 0;

    for (const workspace of workspaces) {
      const audit = await auditWorkspace(client, workspace);
      results.push({ workspace, audit });

      if (!jsonOutput) {
        hardIssueCount += printWorkspaceAudit(workspace, audit);
      }
    }

    if (jsonOutput) {
      console.log(JSON.stringify(results, null, 2));
    }

    await client.query("rollback");

    if (hardIssueCount > 0) {
      process.exitCode = 1;
    }
  } catch (error) {
    try {
      await client.query("rollback");
    } catch {
      // Ignore rollback failures after connection errors.
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
