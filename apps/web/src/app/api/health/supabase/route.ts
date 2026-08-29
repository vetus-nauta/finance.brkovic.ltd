import { NextRequest, NextResponse } from "next/server";

import { createAdminClient, hasSupabaseAdminEnv } from "@/lib/supabase/admin";

export const dynamic = "force-dynamic";

function isAuthorized(request: NextRequest) {
  const cronSecret = process.env.CRON_SECRET;

  if (!cronSecret) {
    return true;
  }

  return request.headers.get("authorization") === `Bearer ${cronSecret}`;
}

export async function GET(request: NextRequest) {
  if (!isAuthorized(request)) {
    return NextResponse.json(
      { ok: false, error: "unauthorized" },
      { status: 401, headers: { "cache-control": "no-store" } }
    );
  }

  if (!hasSupabaseAdminEnv()) {
    return NextResponse.json(
      { ok: false, error: "supabase_admin_env_missing" },
      { status: 503, headers: { "cache-control": "no-store" } }
    );
  }

  const supabase = createAdminClient();
  const { error } = await supabase
    .from("workspaces")
    .select("id", { count: "exact", head: true })
    .limit(1);

  if (error) {
    return NextResponse.json(
      { ok: false, error: "supabase_keepalive_failed" },
      { status: 500, headers: { "cache-control": "no-store" } }
    );
  }

  return NextResponse.json(
    { ok: true, service: "supabase", checkedAt: new Date().toISOString() },
    { headers: { "cache-control": "no-store" } }
  );
}
