#!/usr/bin/env python3
"""Lightweight static checks for the brkovic.app foundation SQL.

This does not replace running migrations in Supabase/PostgreSQL. It catches
basic foundation mistakes before a project account exists.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MIGRATIONS = ROOT / "supabase" / "migrations"


def migration_path() -> Path:
    matches = sorted(MIGRATIONS.glob("*_foundation_core.sql"))
    if not matches:
        raise FileNotFoundError("No *_foundation_core.sql migration found")
    return matches[-1]


def names(pattern: str, sql: str) -> set[str]:
    return set(re.findall(pattern, sql, flags=re.IGNORECASE | re.MULTILINE))


def main() -> int:
    sql = migration_path().read_text(encoding="utf-8")

    tables = names(r"^create table public\.([a-z0-9_]+)\s*\(", sql)
    rls_tables = names(r"^alter table public\.([a-z0-9_]+) enable row level security;", sql)
    policy_tables = names(r"^create policy [a-z0-9_]+ on public\.([a-z0-9_]+)", sql)

    missing_rls = sorted(tables - rls_tables)
    missing_policy = sorted(tables - policy_tables)

    errors: list[str] = []
    if missing_rls:
        errors.append("Tables without RLS: " + ", ".join(missing_rls))
    if missing_policy:
        errors.append("Tables without policies: " + ", ".join(missing_policy))

    if "numeric(14,2)" not in sql:
        errors.append("Money numeric(14,2) not found")
    if "references auth.users" not in sql:
        errors.append("Supabase auth.users references not found")
    if "security definer" not in sql:
        errors.append("RLS helper functions are not SECURITY DEFINER")

    if errors:
        for error in errors:
            print(f"FAIL: {error}")
        return 1

    print(f"OK: {len(tables)} tables, {len(rls_tables)} RLS enables, {len(policy_tables)} policy targets")
    print("NOTE: run this migration in Supabase/PostgreSQL before accepting Foundation-02.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
