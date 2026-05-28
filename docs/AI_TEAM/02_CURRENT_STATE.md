# Current State

Date: 2026-05-23

## Repository State

Known baseline:

```text
HEAD:        72b38e6
origin/main: 72b38e6
Working tree: dirty, important local work
```

Never discard local changes.

## Implemented Recently

- Live Report has cash/card stream separation.
- Card no longer reduces physical cash.
- Menu was reorganized into Work, Reports, Analytics, Management, System.
- Advanced was split into smaller screens: money, advances, AI, audit.
- FinDesk was reduced toward report checking instead of mixing staff management and money issuing.
- Final report actions moved toward report summary.
- Excel/Google export exists and was improved visually.
- Open period logic started using carryover after final report fixation.
- Branding assets were added from Drive:
  - `public/assets/brand-mark.png`
  - `public/assets/brand-logo.png`
  - `public/assets/brand-og.png`
  - favicon and PWA icons.

## Known Financial Example

Historical report:

```text
Income:  €1000
Expense: €600
Cash:    €400
```

Open carryover after fixation:

```text
Administrator: €100
Employees:     €300 total
Total cash:    €400
```

Old report history must remain intact. New open period must not show old `€1000` as new income.

## Weak Areas

- Financial wording can still confuse users if screens show old report totals in open-period context.
- Ledger/open accounting has scope selection risk: personal vs group can make numbers appear as zeros.
- Journal/accounting and report summary still need careful separation of history, open period, and final report.
- UX is improving, but the product still has old-screen residue and duplicated responsibilities.
- Full manual device review is still required for desktop/tablet/mobile.

## Current Office Goal

Create disciplined specialist work streams so further work does not mix:

- product meaning;
- data logic;
- frontend layout;
- QA verification;
- release/audit gate.
