# Product Finance Handoff: Two Report Truths

Date: 2026-05-26

## Current State

Instant field capture slice passed QA and was approved by Chief Auditor for that slice only.

Backend/Data traced carryover/export/archive and found:

- current open-period carryover/export path exists;
- after finalization, open period can start from carryover instead of old income;
- raw historical evidence exists in ledger entries, archived Live Report cards/captures, and audit log;
- no first-class immutable finalized report/export source is exposed as a product object.

## Director Decision

Release requires two separate user-facing truths after finalization:

- current open-period report/export;
- historical finalized report/export.

Archive/audit evidence is not enough by itself for the user's closed final report.

## Product Task

Define the product contract and labels for the two report truths.

Use this scenario:

```text
EUR 1000 income -> EUR 600 expense -> EUR 400 carryover
```

Expected explanation:

- old closed report shows `EUR 1000` received, `EUR 600` spent, `EUR 400` left/carried forward;
- new open period starts from `EUR 400` carryover;
- old `EUR 1000` must not look like new current income;
- current export and historical finalized export must be visibly different actions or modes.

## Output

Write findings to:

- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/TASKS_TO_OTHERS.md`

Do not change formulas, backend/API, or UX code.
