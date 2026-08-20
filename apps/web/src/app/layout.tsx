import type { Metadata, Viewport } from "next";
import "./globals.css";
import { AppHeader } from "@/components/AppHeader";
import { hasSupabasePublicEnv } from "@/lib/env";
import { createClient } from "@/lib/supabase/server";

export const metadata: Metadata = {
  title: "FinDesk | brkovic.app",
  description: "Clean FinDesk foundation for brkovic.app"
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover"
};

async function getHeaderEmail() {
  if (!hasSupabasePublicEnv()) {
    return null;
  }

  const supabase = await createClient();
  const { data } = await supabase.auth.getClaims();
  return typeof data?.claims?.email === "string" ? data.claims.email : null;
}

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const email = await getHeaderEmail();

  return (
    <html lang="ru">
      <body>
        <AppHeader email={email} />
        {children}
      </body>
    </html>
  );
}
