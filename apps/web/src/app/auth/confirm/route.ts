import { type EmailOtpType } from "@supabase/supabase-js";
import { NextResponse, type NextRequest } from "next/server";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/server";

export async function GET(request: NextRequest) {
  const requestUrl = new URL(request.url);
  const tokenHash = requestUrl.searchParams.get("token_hash");
  const type = requestUrl.searchParams.get("type") as EmailOtpType | null;
  const next = requestUrl.searchParams.get("next") ?? routes.hall;

  if (tokenHash && type) {
    const supabase = await createClient();
    const { error } = await supabase.auth.verifyOtp({
      token_hash: tokenHash,
      type
    });

    if (error) {
      const url = new URL(routes.home, requestUrl.origin);
      url.searchParams.set("auth", "confirm-error");
      return NextResponse.redirect(url);
    }
  } else {
    const url = new URL(routes.home, requestUrl.origin);
    url.searchParams.set("auth", "missing-token");
    return NextResponse.redirect(url);
  }

  return NextResponse.redirect(new URL(next, requestUrl.origin));
}
