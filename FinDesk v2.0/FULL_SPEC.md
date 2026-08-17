# FinDesk v2.0 — Consolidated Full Specification

## Status

Authoritative package location: `vetus-nauta/finance.brkovic.ltd/FinDesk v2.0/`.

The Revoyacht copy was a mistaken draft location and is not authoritative.

## Mission

Build a clean MVP financial journal that feels like iOS Notes, not accounting software.

The user writes short financial notes. The system parses them into structured entries, calculates balances, categorizes, and generates reports.

## Non-negotiable foundation

1. Operational journal is source of truth.
2. Summary is generated from entries.
3. Cash/Card are funding flows, not categories.
4. Card-to-cash is Card expense plus Cash income.
5. Commercial income is a separate income category.
6. Opening balance is not income.
7. Rows without `+` or `-` are visible but not counted.
8. Other expenses are visible and reviewed.
9. Old FinDesk is infrastructure donor only.
10. No real secrets are committed to repository.

## Core entities

```text
workspaces
workspace_members
flows
entries
categories
category_rules
actors
attachments
monthly_closures
import_sources
import_rows
audit_log
```

## Workspaces

FinDesk is universal, not yacht-only.

Workspace types:

```text
yacht
family
personal
business
trip
custom
```

## Flows

MVP flows:

```text
cash
card
assistant_journal
```

Cash has live balance.
Card is a funding-flow expense stream by default and does not require bank-balance reconciliation in MVP.

## Entries

Each line in the journal becomes an entry.

Fields:

```text
date
raw_text
sign
amount
flow
direction
entry_type
description
category
actor
status
balance_after
source
attachments
```

## Entry types

```text
cash_income
cash_expense
card_expense
card_income
opening_balance
correction
info
unrecognized
assistant_pending
```

## Entry statuses

```text
recognized
unrecognized
other_review
excluded
imported
assistant_pending
accepted
rejected
corrected
duplicate_suspect
```

## Parsing rule

A counted row must begin with `+` or `-`.

Valid:

```text
-250 рыба
+1000 снял с карты
```

Invalid:

```text
250 рыба
Вова аванс 500
```

Invalid rows remain visible, red/underlined, and do not affect arithmetic.

## Cash calculation

```text
cash_now = opening_cash + cash_income - cash_expense + corrections
```

## Card calculation

```text
card_expense = sum(card out entries)
```

## Card-to-cash approved model

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

Both records are counted in their own flows. This must never be treated as an error.

## Commercial income

Category code:

```text
commercial_income
```

Use for:

```text
аренда
чартер
комиссии
агентские
брокерские
charter
rental
commission
agency fee
brokerage
booking
```

Do not use for opening balance, private top-up, debt return, correction, or card-to-cash top-up.

## Categories

MVP category codes:

```text
crew
commercial_income
dry_dock
berth
marina_ports
service_water
tech_parts
tender
fuel
provisions
guest_trip_support
guest_cash_issued
representation_expenses
interior
cleaning
media_comms
transport_expenses
current_boat_expenses
admin_legal
cash_topup_from_card
other
```

Category direction:

```text
income
expense
movement
mixed
```

Examples:

```text
commercial_income => income
cash_topup_from_card => movement
crew/fuel/provisions/etc. => expense
other => expense by default
```

## Actor logic

Person names are actors, not categories.

Examples:

```text
-500 Вова аванс => actor Вова, category depends on phrase
-87 Вова купил кабель => actor Вова, category tech_parts
+120 Вова вернул остаток => actor Вова, return/cash income context
```

## Reports

Reports are generated.

MVP reports:

```text
monthly summary
category-by-month matrix
yearly summary base
Other expenses review
```

Monthly summary includes:

```text
opening_cash
external_cash_income
commercial_income
cash_expense
card_expense
cash_topup_from_card_card_side
cash_topup_from_card_cash_side
other_expenses
ending_cash
comment
```

## Closed month

A closed month cannot silently change.

Editing a closed month requires:

```text
create correction
recalculate chain
cancel
```

## Responsive layout

Two interaction systems:

### Mobile financial-notes system

Applies to:

```text
iPhone portrait
iPhone landscape
iPad mini portrait
iPad mini landscape
```

Vertical movement scrolls current-month notes.
Horizontal movement reveals structured/report-ready rows.

### Full workspace system

Applies to:

```text
desktop/laptop
iPad 11+ portrait
iPad 11+ landscape
iPad Pro
```

Use full available space. Do not render desktop as a centered mobile column.

## Scroll rule

No body/page scroll.

Allowed internal scroll:

```text
EventFeed
DetailPanelBody
ReportBody
AttachmentList
WorkspaceList
```

## Legacy import

Legacy files are sources, not truth.

Importer must support:

- recursive scan;
- exclude markers;
- final-version priority;
- row-date over filename-date;
- old Cash/Card column mapping;
- source row traceability;
- duplicate suspicion.

Exclude by default:

```text
не отправлял
не отправлено
не готово
не закончен
не полный
неполный
черновик
draft
test
```

## Old FinDesk rule

Old FinDesk may donate only:

```text
env/secrets patterns
runtime config
backend shell
auth shell
DB connection method
admin shell base
deployment pipeline
file upload base
user/session plumbing
logging utilities
generic helpers after review
```

Forbidden as v2.0 truth:

```text
old finance model
old database tables
old entity names
old category logic
old report logic
old dashboard logic
old UX decisions
old project disciplines
old documentation assumptions
old calculations
old cash/card interpretation
old import assumptions
```

## Secrets and hosting

Sprint 01 must inventory:

```text
hosting provider
control panel
server host/IP
FTP/SFTP/SSH
database connection method
env variables
deployment path
domain/DNS/SSL
```

Do not commit actual passwords, private keys, API tokens, database credentials, recovery codes, or cPanel credentials.

## Build order

```text
Sprint 01 — legacy cleanup and infrastructure donor extraction
Sprint 02 — clean core foundation
Sprint 03 — financial logic engine
Sprint 04 — summary, reports, month control
Sprint 05 — legacy import MVP
Sprint 06 — main journal UX shell
Sprint 07 — integration hardening and attachments
Sprint 08 — QA, acceptance, release candidate
```

## Director/subagent model

Director does not write code.

Subagents:

```text
Financial Logic Engine
Data and Backend Core
iOS-Native UX Layout
Frontend Performance and Interaction
Localization and Linguistic Rules
Legacy Import and Archive
QA, Audit, and Acceptance
```

Each sprint has its own Director and its own agent group.

## Definition of Done

MVP is not done unless:

- fixtures pass;
- arithmetic is correct;
- parser works;
- Cash/Card model is preserved;
- commercial income is separated;
- monthly summary is generated from entries;
- old FinDesk logic is not reused;
- desktop/iPad/mobile layout rules pass;
- no secrets are committed.
