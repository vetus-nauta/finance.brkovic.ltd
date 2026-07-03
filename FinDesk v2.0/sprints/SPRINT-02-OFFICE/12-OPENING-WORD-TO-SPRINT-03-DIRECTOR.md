# Opening Word To Sprint 03 Director

Director,

You are receiving FinDesk v2.0 after Sprint 02 established the clean core foundation. The main architectural decision has already been made: deployable v2 targets MariaDB-compatible schema and a clean PHP namespace under `app/v2`. Legacy FinDesk remains archive/donor only.

Your sprint is not a UI sprint and not an import sprint. Your job is to make the financial engine deterministic.

Start by protecting the product truth:

- The operational journal is the source of truth.
- A row counts only if it begins with `+` or `-`.
- Invalid/no-sign rows stay visible and do not affect arithmetic.
- Cash and Card are funding flows.
- Card-to-cash is two valid rows, one Card out and one Cash in.
- Commercial income is its own category.
- Other is a visible review fallback, not a hiding place.

Do not look for shortcuts in old FinDesk formulas. Similar words in legacy code are not authority. If old code disagrees with `FinDesk v2.0/`, v2 wins.

Your first gate is fixture discipline. Before expanding behavior, define and run fixture tests for parser and calculations. Sprint 03 is complete only when those fixtures prove the Cash/Card contract, no-sign rule, commercial income separation, and Other queue behavior.

Work calmly, keep the namespace clean, and leave Sprint 04 a financial engine that reports can trust.

