import type { Metadata, Viewport } from "next";
import "./globals.css";
import { AppHeader } from "@/components/AppHeader";
import { hasSupabasePublicEnv } from "@/lib/env";
import { createClient } from "@/lib/supabase/server";

export const metadata: Metadata = {
  title: "FinDesk | brkovic.app",
  description: "Clean FinDesk foundation for brkovic.app",
  manifest: "/manifest.webmanifest",
  icons: {
    icon: [
      { url: "/assets/v2/findesk-mark.svg", type: "image/svg+xml" },
      { url: "/favicon.ico", sizes: "any" },
      { url: "/assets/favicon-32x32.png", sizes: "32x32", type: "image/png" },
      { url: "/assets/favicon-16x16.png", sizes: "16x16", type: "image/png" }
    ],
    apple: [{ url: "/assets/apple-touch-icon.png", sizes: "180x180", type: "image/png" }]
  }
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

  try {
    const supabase = await createClient();
    const { data } = await supabase.auth.getClaims();
    return typeof data?.claims?.email === "string" ? data.claims.email : null;
  } catch {
    return null;
  }
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
