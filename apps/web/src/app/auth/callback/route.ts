import { NextResponse, type NextRequest } from "next/server";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

export async function GET(request: NextRequest) {
  const requestUrl = new URL(request.url);
  const code = requestUrl.searchParams.get("code");
  const next = requestUrl.searchParams.get("next") ?? routes.hall;

  if (code) {
    const supabase = await createClient();
    const { error } = await supabase.auth.exchangeCodeForSession(code);

    if (error) {
      const url = new URL(routes.home, requestUrl.origin);
      url.searchParams.set("auth", "callback-error");
      return NextResponse.redirect(url);
    }
  } else {
    const url = new URL(routes.home, requestUrl.origin);
    url.searchParams.set("auth", "missing-code");
    return NextResponse.redirect(url);
  }

  return NextResponse.redirect(new URL(next, requestUrl.origin));
}
