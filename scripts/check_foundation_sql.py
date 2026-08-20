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


def duplicate_columns(sql: str) -> list[str]:
    duplicates: list[str] = []
    table_blocks = re.finditer(
        r"^create table public\.([a-z0-9_]+)\s*\((.*?)^\);",
        sql,
        flags=re.IGNORECASE | re.MULTILINE | re.DOTALL,
    )

    for block in table_blocks:
        table_name = block.group(1)
        body = block.group(2)
        seen: set[str] = set()
        for line in body.splitlines():
            match = re.match(r"^\s{2}([a-z][a-z0-9_]*)\s+", line, flags=re.IGNORECASE)
            if not match:
                continue
            column_name = match.group(1).lower()
            if column_name in {"primary", "unique", "check", "foreign", "constraint"}:
                continue
            if column_name in seen:
                duplicates.append(f"{table_name}.{column_name}")
            seen.add(column_name)

    return duplicates


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

    duplicate_column_names = duplicate_columns(sql)
    if duplicate_column_names:
        errors.append("Duplicate columns: " + ", ".join(sorted(duplicate_column_names)))

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
