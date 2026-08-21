#!/usr/bin/env node
const fs = require("node:fs");
const path = require("node:path");
const { Client } = require("pg");

const rootDir = path.resolve(__dirname, "..");
const envPath = path.join(rootDir, ".env.foundation.local");
const sqlPath = path.join(rootDir, "supabase/tests/foundation_operational_entry_command_smoke.sql");

function readLocalEnvValue(name) {
  if (!fs.existsSync(envPath)) return "";

  const lines = fs.readFileSync(envPath, "utf8").split(/\r?\n/);
  for (const line of lines) {
    if (line.startsWith(`${name}=`)) return line.slice(name.length + 1).trim();
  }

  return "";
}

async function main() {
  const connectionString = process.env.SUPABASE_DB_POOLER_URL || readLocalEnvValue("SUPABASE_DB_POOLER_URL");
  if (!connectionString) {
    throw new Error("SUPABASE_DB_POOLER_URL is not set. Put it in .env.foundation.local or export it.");
  }

  const client = new Client({
    connectionString,
    ssl: { rejectUnauthorized: false }
  });

  await client.connect();
  try {
    const sql = fs.readFileSync(sqlPath, "utf8");
    const result = await client.query(sql);
    const finalResult = Array.isArray(result) ? result.findLast((entry) => entry.rows?.length) : result;
    console.log(finalResult?.rows?.[0]?.result || "foundation operational entry command smoke completed");
  } finally {
    await client.end();
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
