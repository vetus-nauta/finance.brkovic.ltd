import type { Metadata, Viewport } from "next";
import Link from "next/link";
import "./globals.css";
import { routes } from "@/lib/routes";

export const metadata: Metadata = {
  title: "FinDesk | brkovic.app",
  description: "Clean FinDesk foundation for brkovic.app"
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover"
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body>
        <header className="topbar">
          <Link className="brand" href={routes.home}>
            <span className="brand-mark">F</span>
            <span>
              <strong>FinDesk</strong>
              <small>brkovic.app</small>
            </span>
          </Link>
          <nav className="nav">
            <Link href={routes.hall}>Холл</Link>
            <Link href={routes.workspaces}>Пространства</Link>
          </nav>
        </header>
        {children}
      </body>
    </html>
  );
}
