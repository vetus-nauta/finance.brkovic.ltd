"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { routes } from "@/lib/routes";
import { SignOutButton } from "./SignOutButton";

export function AppHeader({ email }: { email: string | null }) {
  const pathname = usePathname();
  const brandHref = email ? routes.hall : routes.home;
  const showHallLink = Boolean(email) && pathname.startsWith(routes.workspaces);

  return (
    <header className="topbar">
      <Link className="brand" href={brandHref}>
        <span className="brand-mark">
          <img src="/assets/v2/findesk-mark.svg" alt="" width={36} height={36} />
        </span>
        <span>
          <strong>FinDesk</strong>
          <small>brkovic.app</small>
        </span>
      </Link>
      {email ? (
        <nav className="nav" aria-label="Основная навигация">
          {showHallLink ? <Link href={routes.hall}>В холл</Link> : null}
          <SignOutButton />
        </nav>
      ) : null}
    </header>
  );
}
