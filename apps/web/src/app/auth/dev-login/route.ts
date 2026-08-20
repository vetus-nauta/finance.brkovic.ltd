import { NextResponse, type NextRequest } from "next/server";
import { createClient as createAdminClient } from "@supabase/supabase-js";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";
import { getPublicEnv } from "@/lib/env";

type DevLoginBody = {
  email?: string;
  code?: string;
};

function isLocalRequest(request: NextRequest) {
  const host = request.nextUrl.hostname;

  return host === "localhost" || host === "127.0.0.1";
}

export async function POST(request: NextRequest) {
  if (process.env.FINDESK_DEV_LOGIN_ENABLED !== "1" || !isLocalRequest(request)) {
    return NextResponse.json({ ok: false, error: "not_found" }, { status: 404 });
  }

  const expectedCode = process.env.FINDESK_DEV_LOGIN_CODE;
  const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  const env = getPublicEnv();
  const body = (await request.json().catch(() => ({}))) as DevLoginBody;
  const email = String(body.email || "").trim().toLowerCase();
  const code = String(body.code || "").trim();

  if (!expectedCode || !serviceRoleKey || !env.supabaseUrl || !env.supabasePublishableKey) {
    return NextResponse.json({ ok: false, error: "dev_login_not_configured" }, { status: 503 });
  }

  if (!email || code !== expectedCode) {
    return NextResponse.json({ ok: false, error: "invalid_code" }, { status: 401 });
  }

  const admin = createAdminClient(env.supabaseUrl, serviceRoleKey, {
    auth: {
      autoRefreshToken: false,
      persistSession: false
    }
  });
  const { data: link, error: linkError } = await admin.auth.admin.generateLink({
    type: "magiclink",
    email,
    options: {
      redirectTo: new URL(routes.hall, request.url).toString()
    }
  });

  const token = link?.properties?.email_otp;

  if (linkError || !token) {
    return NextResponse.json({ ok: false, error: "dev_login_token_failed" }, { status: 500 });
  }

  const supabase = await createClient();
  const { error: verifyError } = await supabase.auth.verifyOtp({
    email,
    token,
    type: "email"
  });

  if (verifyError) {
    return NextResponse.json({ ok: false, error: "dev_login_verify_failed" }, { status: 500 });
  }

  return NextResponse.json({ ok: true });
}
