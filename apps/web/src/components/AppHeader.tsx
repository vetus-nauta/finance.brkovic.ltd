"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { routes } from "@/lib/routes";
import { SignOutButton } from "./SignOutButton";

export function AppHeader({ email }: { email: string | null }) {
  const pathname = usePathname();
  const brandHref = email ? routes.hall : routes.home;
  const isHall = pathname === routes.hall;
  const showHallLink = Boolean(email) && pathname.startsWith(routes.workspaces);
  const initials = email ? email.slice(0, 2).toUpperCase() : "";

  return (
    <header className={isHall ? "topbar topbar-hall" : "topbar"}>
      <div className="topbar-left">
        <Link className="brand" href={brandHref}>
          <span className="brand-mark">
            <img src="/assets/v2/findesk-mark.svg" alt="" width={36} height={36} />
          </span>
          <span>
            <strong>FinDesk</strong>
            <small>brkovic.app</small>
          </span>
        </Link>
        {isHall ? <span className="topbar-section-title">Холл</span> : null}
      </div>
      {email ? (
        <nav className="nav" aria-label="Основная навигация">
          {isHall ? (
            <button className="icon-button" type="button" aria-label="Помощь" aria-disabled="true">
              <img src="/assets/hall/icons/ui/help.svg" alt="" width={20} height={20} />
            </button>
          ) : null}
          {showHallLink ? <Link href={routes.hall}>В холл</Link> : null}
          <SignOutButton />
          {isHall ? (
            <span className="user-avatar" aria-label={`Пользователь ${email}`}>
              {initials}
            </span>
          ) : null}
        </nav>
      ) : null}
    </header>
  );
}
