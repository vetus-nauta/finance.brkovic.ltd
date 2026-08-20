#!/usr/bin/env node

const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

const rootDir = path.resolve(__dirname, "..");
const envPath = path.join(rootDir, ".env.foundation.local");

function readLocalEnvValue(name) {
  const fromProcess = process.env[name];
  if (fromProcess) {
    return fromProcess;
  }

  if (!fs.existsSync(envPath)) {
    return "";
  }

  for (const line of fs.readFileSync(envPath, "utf8").split(/\r?\n/)) {
    if (line.startsWith(`${name}=`)) {
      return line.slice(name.length + 1).trim();
    }
  }

  return "";
}

function argValue(name, fallback = "") {
  const index = process.argv.indexOf(name);
  if (index >= 0 && process.argv[index + 1]) {
    return process.argv[index + 1];
  }
  return fallback;
}

const ownerEmail = argValue("--owner-email", process.env.FINDESK_BOOTSTRAP_OWNER_EMAIL || "vetus.nauta@gmail.com")
  .trim()
  .toLowerCase();
const workspaceName = argValue("--workspace-name", process.env.FINDESK_BOOTSTRAP_WORKSPACE_NAME || "Claudia Z").trim();
const organizationName = argValue(
  "--organization-name",
  process.env.FINDESK_BOOTSTRAP_ORGANIZATION_NAME || "Vetus Nauta"
).trim();

async function main() {
  const connectionString = readLocalEnvValue("SUPABASE_DB_POOLER_URL");

  if (!connectionString) {
    throw new Error("SUPABASE_DB_POOLER_URL is not set.");
  }

  if (!ownerEmail || !workspaceName || !organizationName) {
    throw new Error("Owner email, workspace name, and organization name are required.");
  }

  const client = new Client({
    connectionString,
    ssl: { rejectUnauthorized: false }
  });

  await client.connect();

  try {
    await client.query("begin");

    const userResult = await client.query(
      `
        select id, email
        from auth.users
        where lower(email) = lower($1)
        order by created_at desc
        limit 1
      `,
      [ownerEmail]
    );

    const user = userResult.rows[0];

    if (!user) {
      throw new Error(`Supabase Auth user not found for ${ownerEmail}. Sign in once before bootstrap.`);
    }

    await client.query(
      `
        insert into public.profiles (id, email, display_name, locale, timezone)
        values ($1, $2, $3, 'ru', 'Europe/Podgorica')
        on conflict (id) do update set
          email = excluded.email,
          display_name = coalesce(public.profiles.display_name, excluded.display_name),
          updated_at = now()
      `,
      [user.id, user.email, "Alexey"]
    );

    const organizationResult = await client.query(
      `
        insert into public.organizations (owner_user_id, name, slug, status)
        values ($1, $2, 'vetus-nauta', 'active')
        on conflict (slug) do update set
          owner_user_id = excluded.owner_user_id,
          name = excluded.name,
          status = 'active',
          deleted_at = null,
          updated_at = now()
        returning id
      `,
      [user.id, organizationName]
    );
    const organizationId = organizationResult.rows[0].id;

    const existingWorkspace = await client.query(
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

    const workspaceResult = existingWorkspace.rows[0]
      ? await client.query(
          `
            update public.workspaces
            set
              workspace_type = 'yacht',
              currency_code = 'EUR',
              locale = 'ru',
              status = 'active',
              deleted_at = null,
              updated_at = now()
            where id = $1
            returning id
          `,
          [existingWorkspace.rows[0].id]
        )
      : await client.query(
      `
        insert into public.workspaces (
          organization_id,
          name,
          workspace_type,
          currency_code,
          locale,
          status,
          metadata
        )
        values ($1, $2, 'yacht', 'EUR', 'ru', 'active', '{"vessel":"Claudia Z","bootstrap":"foundation"}'::jsonb)
        returning id
      `,
      [organizationId, workspaceName]
        );
    const workspaceId = workspaceResult.rows[0].id;

    await client.query(
      `
        insert into public.memberships (
          organization_id,
          workspace_id,
          user_id,
          role_code,
          status,
          access_scope,
          invited_at,
          accepted_at
        )
        values ($1, $2, $3, 'owner', 'active', 'workspace', now(), now())
        on conflict (workspace_id, user_id) do update set
          organization_id = excluded.organization_id,
          role_code = 'owner',
          status = 'active',
          access_scope = 'workspace',
          accepted_at = coalesce(public.memberships.accepted_at, now()),
          revoked_at = null,
          left_at = null,
          updated_at = now()
      `,
      [organizationId, workspaceId, user.id]
    );

    await client.query(
      `
        insert into public.accounts (organization_id, workspace_id, code, label, account_type, currency_code, is_active)
        values
          ($1, $2, 'cash', 'Кеш', 'cash', 'EUR', true),
          ($1, $2, 'card', 'Карта', 'card', 'EUR', true)
        on conflict (workspace_id, code) do update set
          label = excluded.label,
          account_type = excluded.account_type,
          currency_code = excluded.currency_code,
          is_active = true,
          updated_at = now()
      `,
      [organizationId, workspaceId]
    );

    await client.query("commit");

    console.log(
      JSON.stringify(
        {
          ok: true,
          owner_email: ownerEmail,
          organization: organizationName,
          workspace: workspaceName,
          role: "owner",
          accounts: ["cash", "card"]
        },
        null,
        2
      )
    );
  } catch (error) {
    await client.query("rollback");
    throw error;
  } finally {
    await client.end();
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
