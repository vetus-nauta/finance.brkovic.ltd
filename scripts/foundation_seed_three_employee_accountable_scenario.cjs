#!/usr/bin/env node

const crypto = require("node:crypto");
const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

const rootDir = path.resolve(__dirname, "..");
const envPath = path.join(rootDir, ".env.foundation.local");
const scenario = "three_employee_accountable_2026_08_21";
const ownerEmail = "vetus.nauta@gmail.com";
const organizationName = "Vetus Nauta";
const workspaceName = "Тестовый прогон сотрудников";
const occurredOn = "2026-08-21";

function readLocalEnvValue(name) {
  if (process.env[name]) return process.env[name];
  if (!fs.existsSync(envPath)) return "";

  for (const line of fs.readFileSync(envPath, "utf8").split(/\r?\n/)) {
    if (line.startsWith(`${name}=`)) return line.slice(name.length + 1).trim();
  }

  return "";
}

function hashToken(token) {
  return crypto.createHash("sha256").update(token).digest("hex");
}

function money(value) {
  return Number(value).toLocaleString("ru-RU", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

const adminExpenses = [
  "-120 продукты для тестового прогона",
  "-85 такси экипажа",
  "-240 стоянка в марине",
  "-60 бытовая химия",
  "-180 топливо",
  "-45 экспресс почта",
  "-310 запчасти и сервис",
  "-95 связь и интернет",
  "-150 клининг",
  "-70 вода и провизия"
];

const employees = [
  {
    id: "f1000000-0000-4000-8000-000000000001",
    email: "test.employee.1@brkovic.app",
    name: "Тест сотрудник 1",
    advanceId: "f3000000-0000-4000-8000-000000000001",
    reportId: "f4000000-0000-4000-8000-000000000001",
    issued: 1000,
    items: [
      ["продукты", 80],
      ["овощи и фрукты", 65],
      ["рыба и морепродукты", 120],
      ["хлеб и вода", 35],
      ["такси до марины", 55],
      ["стоянка", 100],
      ["топливо для тузика", 90],
      ["чистящие средства", 70],
      ["салфетки и бытовые мелочи", 45],
      ["прачечная", 60],
      ["кофе для гостей", 50],
      ["аптека", 40],
      ["доставка продуктов", 60],
      ["цветы для гостей", 130]
    ]
  },
  {
    id: "f1000000-0000-4000-8000-000000000002",
    email: "test.employee.2@brkovic.app",
    name: "Тест сотрудник 2",
    advanceId: "f3000000-0000-4000-8000-000000000002",
    reportId: "f4000000-0000-4000-8000-000000000002",
    issued: 500,
    items: [
      ["продукты", 90],
      ["мясо", 80],
      ["рыба", 100],
      ["такси", 45],
      ["доставка", 35],
      ["кухонные принадлежности", 75],
      ["клининг", 60],
      ["интернет", 55],
      ["подарок гостям", 110],
      ["вода", 50]
    ]
  },
  {
    id: "f1000000-0000-4000-8000-000000000003",
    email: "test.employee.3@brkovic.app",
    name: "Тест сотрудник 3",
    advanceId: "f3000000-0000-4000-8000-000000000003",
    reportId: "f4000000-0000-4000-8000-000000000003",
    issued: 300,
    items: [
      ["вода", 20],
      ["хлеб", 15],
      ["такси", 30],
      ["моющие средства", 25],
      ["овощи", 35],
      ["кофе", 25]
    ]
  }
];

function sumItems(items) {
  return items.reduce((sum, [, amount]) => sum + amount, 0);
}

async function ensureAuthUser(client, employee) {
  const existing = await client.query("select id from auth.users where lower(email) = lower($1) limit 1", [employee.email]);
  if (existing.rows[0]) {
    return existing.rows[0].id;
  }

  await client.query(
    `
      insert into auth.users (
        id, aud, role, email, encrypted_password, email_confirmed_at,
        raw_app_meta_data, raw_user_meta_data, created_at, updated_at,
        is_sso_user, is_anonymous
      ) values (
        $1, 'authenticated', 'authenticated', $2, '', now(),
        '{"provider":"email","providers":["email"]}'::jsonb, '{}'::jsonb, now(), now(),
        false, false
      )
    `,
    [employee.id, employee.email]
  );

  return employee.id;
}

async function setAuthenticatedUser(client, userId) {
  await client.query("set local role authenticated");
  await client.query("select set_config('request.jwt.claims', $1, true)", [
    JSON.stringify({ sub: userId, role: "authenticated" })
  ]);
}

async function createOperationalEntry(client, workspaceId, accountCode, rawText, userId, metadata = {}) {
  await client.query("begin");
  try {
    await setAuthenticatedUser(client, userId);
    const result = await client.query(
      `
        select *
        from public.create_operational_entry(
          $1::uuid,
          $2::text,
          $3::date,
          $4::text,
          'manual',
          'manual',
          'ru',
          null,
          '{}'::jsonb,
          $5::jsonb
        )
      `,
      [workspaceId, accountCode, occurredOn, rawText, JSON.stringify({ scenario, ...metadata })]
    );
    await client.query("commit");
    return result.rows[0];
  } catch (error) {
    await client.query("rollback");
    throw error;
  }
}

async function callOwnerRpc(client, ownerId, sql, params) {
  await client.query("begin");
  try {
    await setAuthenticatedUser(client, ownerId);
    const result = await client.query(sql, params);
    await client.query("commit");
    return result;
  } catch (error) {
    await client.query("rollback");
    throw error;
  }
}

async function main() {
  const connectionString = readLocalEnvValue("SUPABASE_DB_POOLER_URL");

  if (!connectionString) {
    throw new Error("SUPABASE_DB_POOLER_URL is not set.");
  }

  const client = new Client({
    connectionString,
    ssl: { rejectUnauthorized: false }
  });

  await client.connect();

  try {
    await client.query("begin");

    const ownerResult = await client.query(
      "select id, email from auth.users where lower(email) = lower($1) order by created_at desc limit 1",
      [ownerEmail]
    );
    const owner = ownerResult.rows[0];

    if (!owner) {
      throw new Error(`Owner auth user not found: ${ownerEmail}`);
    }

    await client.query(
      `
        insert into public.profiles (id, email, display_name, locale, timezone)
        values ($1, $2, 'Alexey', 'ru', 'Europe/Podgorica')
        on conflict (id) do update set
          email = excluded.email,
          display_name = coalesce(public.profiles.display_name, excluded.display_name),
          updated_at = now()
      `,
      [owner.id, owner.email]
    );

    const organizationResult = await client.query(
      `
        insert into public.organizations (owner_user_id, name, slug, status, metadata)
        values ($1, $2, 'vetus-nauta', 'active', jsonb_build_object('managed_by', 'foundation_seed'))
        on conflict (slug) do update set
          owner_user_id = excluded.owner_user_id,
          name = excluded.name,
          status = 'active',
          deleted_at = null,
          updated_at = now()
        returning id
      `,
      [owner.id, organizationName]
    );
    const organizationId = organizationResult.rows[0].id;

    const canonicalWorkspace = await client.query(
      `
        select id
        from public.workspaces
        where organization_id = $1
          and name = $2
        order by created_at asc
        limit 1
      `,
      [organizationId, workspaceName]
    );

    let workspaceId = canonicalWorkspace.rows[0]?.id;

    if (workspaceId) {
      await client.query(
        `
          update public.workspaces
          set
            workspace_type = 'yacht',
            currency_code = 'EUR',
            locale = 'ru',
            status = 'active',
            deleted_at = null,
            metadata = metadata || jsonb_build_object('scenario', $2::text, 'purpose', 'repeatable employee accountable QA'),
            updated_at = now()
          where id = $1
        `,
        [workspaceId, scenario]
      );
    } else {
      const workspaceResult = await client.query(
        `
          insert into public.workspaces (organization_id, name, workspace_type, currency_code, locale, status, metadata)
          values ($1, $2, 'yacht', 'EUR', 'ru', 'active', jsonb_build_object('scenario', $3::text, 'purpose', 'repeatable employee accountable QA'))
          returning id
        `,
        [organizationId, workspaceName, scenario]
      );
      workspaceId = workspaceResult.rows[0].id;
    }

    await client.query(
      `
        update public.workspaces
        set status = 'deleted',
            deleted_at = now(),
            updated_at = now()
        where organization_id = $1
          and name = $2
          and id <> $3
      `,
      [organizationId, workspaceName, workspaceId]
    );

    const cleanupStatements = [
      "delete from public.document_links where workspace_id = $1",
      "delete from public.document_versions where workspace_id = $1",
      "delete from public.documents where workspace_id = $1",
      "delete from public.report_package_items where workspace_id = $1",
      "delete from public.report_packages where workspace_id = $1",
      "delete from public.report_snapshots where workspace_id = $1",
      "delete from public.period_closures where workspace_id = $1",
      "delete from public.expense_report_ledger_links where workspace_id = $1",
      "delete from public.expense_items where workspace_id = $1",
      "delete from public.expense_reports where workspace_id = $1",
      "delete from public.cash_advances where workspace_id = $1",
      "delete from public.approval_events where workspace_id = $1",
      "delete from public.ledger_entries where workspace_id = $1",
      "delete from public.transactions where workspace_id = $1",
      "delete from public.quick_notes where workspace_id = $1",
      "delete from public.invitations where workspace_id = $1",
      "delete from public.memberships where workspace_id = $1",
      "delete from public.accounts where workspace_id = $1"
    ];

    for (const statement of cleanupStatements) {
      await client.query(statement, [workspaceId]);
    }

    await client.query(
      `
        delete from public.invitations
        where token_hash = any($1::text[])
          or lower(email) = any($2::text[])
      `,
      [
        employees.map((employee) => hashToken(`${scenario}:${employee.email}`)),
        employees.map((employee) => employee.email.toLowerCase())
      ]
    );

    await client.query(
      `
        insert into public.memberships (
          organization_id, workspace_id, user_id, role_code, status, access_scope, invited_at, accepted_at
        ) values ($1, $2, $3, 'owner', 'active', 'workspace', now(), now())
        on conflict (workspace_id, user_id) do update set
          organization_id = excluded.organization_id,
          role_code = 'owner',
          status = 'active',
          access_scope = 'workspace',
          accepted_at = now(),
          revoked_at = null,
          left_at = null,
          updated_at = now()
      `,
      [organizationId, workspaceId, owner.id]
    );

    const cashAccount = await client.query(
      `
        insert into public.accounts (organization_id, workspace_id, code, label, account_type, currency_code, is_active, metadata)
        values
          ($1, $2, 'cash', 'Кеш', 'cash', 'EUR', true, jsonb_build_object('scenario', $3::text)),
          ($1, $2, 'card', 'Карта', 'card', 'EUR', true, jsonb_build_object('scenario', $3::text))
        on conflict (workspace_id, code) do update set
          label = excluded.label,
          account_type = excluded.account_type,
          currency_code = excluded.currency_code,
          is_active = true,
          metadata = excluded.metadata,
          updated_at = now()
        returning id, code
      `,
      [organizationId, workspaceId, scenario]
    );
    const cashAccountId = cashAccount.rows.find((row) => row.code === "cash").id;

    for (const employee of employees) {
      const employeeId = await ensureAuthUser(client, employee);
      employee.userId = employeeId;

      await client.query(
        `
          insert into public.profiles (id, email, display_name, locale, timezone)
          values ($1, $2, $3, 'ru', 'Europe/Podgorica')
          on conflict (id) do update set
            email = excluded.email,
            display_name = excluded.display_name,
            updated_at = now()
        `,
        [employeeId, employee.email, employee.name]
      );

      const token = `${scenario}:${employee.email}`;
      await client.query(
        `
          insert into public.invitations (
            organization_id, workspace_id, email, role_code, status, token_hash,
            invited_by, expires_at, accepted_by, accepted_at
          ) values (
            $1, $2, $3, 'employee', 'accepted', $4, $5, now() + interval '30 days', $6, now()
          )
        `,
        [organizationId, workspaceId, employee.email, hashToken(token), owner.id, employeeId]
      );

      await client.query(
        `
          insert into public.memberships (
            organization_id, workspace_id, user_id, role_code, status, access_scope, invited_at, accepted_at
          ) values ($1, $2, $3, 'employee', 'active', 'own_reports', now(), now())
          on conflict (workspace_id, user_id) do update set
            role_code = 'employee',
            status = 'active',
            access_scope = 'own_reports',
            accepted_at = now(),
            updated_at = now()
        `,
        [organizationId, workspaceId, employeeId]
      );
    }

    await client.query("commit");

    await createOperationalEntry(client, workspaceId, "cash", "+7000 поступило от судовладельца на тестовый прогон", owner.id, {
      test_role: "admin_opening_balance"
    });

    for (const [index, rawText] of adminExpenses.entries()) {
      await createOperationalEntry(client, workspaceId, "cash", rawText, owner.id, {
        test_role: "admin_expense",
        admin_expense_no: index + 1
      });
    }

    await client.query("begin");
    try {
      for (const employee of employees) {
        const spent = sumItems(employee.items);

        await client.query(
          `
            insert into public.cash_advances (
              id, organization_id, workspace_id, issued_to, account_id, amount, currency_code,
              status, issued_by, issued_at, accepted_at, metadata
            ) values (
              $1, $2, $3, $4, $5, $6, 'EUR',
              'accepted', $7, now(), now(), $8::jsonb
            )
          `,
          [
            employee.advanceId,
            organizationId,
            workspaceId,
            employee.userId,
            cashAccountId,
            employee.issued,
            owner.id,
            JSON.stringify({ scenario, purpose: "Тестовая выдача под отчет" })
          ]
        );

        await client.query(
          `
            insert into public.expense_reports (
              id, organization_id, workspace_id, cash_advance_id, submitted_by, status,
              total_amount, currency_code, submitted_at, approved_by, approved_at, metadata
            ) values (
              $1, $2, $3, $4, $5, 'approved',
              $6, 'EUR', now(), $7, now(), $8::jsonb
            )
          `,
          [
            employee.reportId,
            organizationId,
            workspaceId,
            employee.advanceId,
            employee.userId,
            spent,
            owner.id,
            JSON.stringify({
              scenario,
              employee_name: employee.name,
              issued_amount: employee.issued,
              balance_after_report: employee.issued - spent
            })
          ]
        );

        await client.query(
          `
            insert into public.approval_events (
              organization_id, workspace_id, entity_type, entity_id, event_type, actor_user_id, note, metadata
            ) values
              ($1, $2, 'cash_advance', $3, 'offered', $4, 'Тестовая выдача под отчет', $5::jsonb),
              ($1, $2, 'cash_advance', $3, 'accepted_by_employee', $6, null, $5::jsonb),
              ($1, $2, 'expense_report', $7, 'submitted_by_employee', $6, null, $5::jsonb),
              ($1, $2, 'expense_report', $7, 'approved_by_admin', $4, null, $5::jsonb)
          `,
          [
            organizationId,
            workspaceId,
            employee.advanceId,
            owner.id,
            JSON.stringify({ scenario, issued_amount: employee.issued, spent_amount: spent }),
            employee.userId,
            employee.reportId
          ]
        );

        for (const [index, [label, amount]] of employee.items.entries()) {
          await client.query(
            `
              insert into public.expense_items (
                organization_id, workspace_id, expense_report_id, occurred_on, raw_text,
                amount, currency_code, status, metadata
              ) values ($1, $2, $3, $4, $5, $6, 'EUR', 'draft', $7::jsonb)
            `,
            [
              organizationId,
              workspaceId,
              employee.reportId,
              occurredOn,
              label,
              amount,
              JSON.stringify({ scenario, employee_name: employee.name, item_no: index + 1 })
            ]
          );
        }
      }

      await client.query("commit");
    } catch (error) {
      await client.query("rollback");
      throw error;
    }

    const materialized = [];
    for (const employee of employees) {
      const result = await callOwnerRpc(
        client,
        owner.id,
        "select * from public.materialize_expense_report($1::uuid)",
        [employee.reportId]
      );
      materialized.push(result.rows[0]);
    }

    const report = await callOwnerRpc(
      client,
      owner.id,
      `
        select *
        from public.create_period_report_snapshot(
          $1::uuid,
          $2::date,
          $2::date,
          'Тестовый отчет: админ и 3 сотрудника'
        )
      `,
      [workspaceId, occurredOn]
    );

    const auditResult = await client.query(
      `
        with
        tx as (
          select t.id, t.row_no, t.raw_text, t.status, t.source_type, t.metadata,
            le.direction, le.amount
          from public.transactions t
          join public.ledger_entries le on le.transaction_id = t.id
          where t.workspace_id = $1
            and t.status <> 'void'
        ),
        employee_groups as (
          select
            t.metadata->>'expense_report_id' as expense_report_id,
            coalesce(p.email, 'unknown') as employee_email,
            count(*)::integer as entry_count,
            coalesce(sum(t.amount), 0)::numeric(14,2) as spent_total
          from tx t
          left join public.expense_reports er on er.id = (t.metadata->>'expense_report_id')::uuid
          left join public.profiles p on p.id = er.submitted_by
          where t.source_type = 'expense_report'
          group by t.metadata->>'expense_report_id', p.email
        ),
        accountable as (
          select
            p.email as employee_email,
            ca.amount::numeric(14,2) as issued,
            er.total_amount::numeric(14,2) as spent,
            greatest(er.total_amount - ca.amount, 0)::numeric(14,2) as over_spend,
            greatest(ca.amount - er.total_amount, 0)::numeric(14,2) as return_due,
            er.status as report_status,
            count(ei.id)::integer as item_count,
            count(ei.id) filter (where ei.status = 'accepted')::integer as accepted_item_count
          from public.cash_advances ca
          join public.expense_reports er on er.cash_advance_id = ca.id
          join public.profiles p on p.id = ca.issued_to
          left join public.expense_items ei on ei.expense_report_id = er.id
          where ca.workspace_id = $1
          group by p.email, ca.amount, er.total_amount, er.status
        ),
        latest_report as (
          select id, title, cardinality(source_transaction_ids)::integer as entry_count,
            (totals->>'income_total')::numeric(14,2) as income_total,
            (totals->>'expense_total')::numeric(14,2) as expense_total,
            (totals->>'net_total')::numeric(14,2) as net_total
          from public.report_snapshots
          where id = $2
        )
        select jsonb_build_object(
          'workspace_id', $1,
          'workspace_name', $3::text,
          'ledger', jsonb_build_object(
            'entry_count', (select count(*) from tx),
            'income_total', (select coalesce(sum(amount) filter (where direction = 'income'), 0)::numeric(14,2) from tx),
            'expense_total', (select coalesce(sum(amount) filter (where direction = 'expense'), 0)::numeric(14,2) from tx),
            'net_total', (select coalesce(sum(amount) filter (where direction = 'income'), 0)::numeric(14,2) - coalesce(sum(amount) filter (where direction = 'expense'), 0)::numeric(14,2) from tx)
          ),
          'employee_blocks', coalesce((select jsonb_agg(to_jsonb(employee_groups) order by employee_email) from employee_groups), '[]'::jsonb),
          'accountable', coalesce((select jsonb_agg(to_jsonb(accountable) order by employee_email) from accountable), '[]'::jsonb),
          'report', (select to_jsonb(latest_report) from latest_report)
        ) as audit
      `,
      [workspaceId, report.rows[0].report_snapshot_id, workspaceName]
    );

    const audit = auditResult.rows[0].audit;
    const expectedAdminExpenses = adminExpenses.reduce((sum, line) => sum + Number(line.match(/^-([0-9]+)/)?.[1] ?? 0), 0);
    const expectedEmployeeExpenses = employees.reduce((sum, employee) => sum + sumItems(employee.items), 0);
    const expectedExpense = expectedAdminExpenses + expectedEmployeeExpenses;
    const expectedNet = 7000 - expectedExpense;

    const failures = [];
    if (audit.ledger.entry_count !== 41) failures.push(`ledger entry count ${audit.ledger.entry_count} != 41`);
    if (Number(audit.ledger.income_total) !== 7000) failures.push(`income ${audit.ledger.income_total} != 7000`);
    if (Number(audit.ledger.expense_total) !== expectedExpense) {
      failures.push(`expense ${audit.ledger.expense_total} != ${expectedExpense}`);
    }
    if (Number(audit.ledger.net_total) !== expectedNet) failures.push(`net ${audit.ledger.net_total} != ${expectedNet}`);
    if (audit.report.entry_count !== 41) failures.push(`report entry count ${audit.report.entry_count} != 41`);

    const expectedByEmail = new Map(
      employees.map((employee) => [
        employee.email,
        {
          issued: employee.issued,
          spent: sumItems(employee.items),
          count: employee.items.length
        }
      ])
    );

    for (const group of audit.employee_blocks) {
      const expected = expectedByEmail.get(group.employee_email);
      if (!expected) failures.push(`unexpected employee block ${group.employee_email}`);
      if (expected && Number(group.spent_total) !== expected.spent) {
        failures.push(`employee block ${group.employee_email} spent ${group.spent_total} != ${expected.spent}`);
      }
      if (expected && group.entry_count !== expected.count) {
        failures.push(`employee block ${group.employee_email} count ${group.entry_count} != ${expected.count}`);
      }
    }

    for (const row of audit.accountable) {
      const expected = expectedByEmail.get(row.employee_email);
      if (!expected) continue;
      if (Number(row.issued) !== expected.issued || Number(row.spent) !== expected.spent) {
        failures.push(`accountable ${row.employee_email} mismatch`);
      }
      if (row.report_status !== "closed") failures.push(`accountable ${row.employee_email} report status ${row.report_status}`);
      if (row.item_count !== expected.count || row.accepted_item_count !== expected.count) {
        failures.push(`accountable ${row.employee_email} item status mismatch`);
      }
    }

    if (failures.length > 0) {
      throw new Error(`Scenario audit failed: ${failures.join("; ")}`);
    }

    console.log(
      JSON.stringify(
        {
          ok: true,
          scenario,
          workspace: {
            id: workspaceId,
            name: workspaceName
          },
          report_snapshot_id: report.rows[0].report_snapshot_id,
          expected: {
            admin_expense_total: expectedAdminExpenses,
            employee_expense_total: expectedEmployeeExpenses,
            expense_total: expectedExpense,
            final_cash_balance: expectedNet
          },
          materialized,
          audit
        },
        null,
        2
      )
    );
  } finally {
    await client.end();
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
