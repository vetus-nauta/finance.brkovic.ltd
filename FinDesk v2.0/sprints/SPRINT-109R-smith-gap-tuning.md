# SPRINT-109R — Mr. Smith Gap Tuning

Date: 2026-08-20

## Director opening

Goal: improve Mr. Smith recognition on real Claudia Z beta wording without creating private yacht-name shortcuts or changing financial arithmetic.

Boundary:
- no operational-entry recategorization in this sprint;
- no formula, report, deployment, or balance logic changes;
- only classifier behavior and evidence artifacts.

## Agent assignment

- Linguistic Classification Agent: inspect beta wording gaps and propose universal phrases.
- Financial Logic Engine Agent: reject rules that would convert totals, technical placeholders, or ambiguous private aliases into accounting truth.
- QA and Acceptance Agent: run fixed control cases and beta-corpus regression check.

## Implemented rules

Added wrapper migration:

`supabase/migrations/20260820190000_smith_gap_tuning.sql`

New high-signal recognition:
- yacht commercial income from short `+ charter / чартер` wording;
- owner funding from `+ от судовладельца / пополнение владельцем`;
- cash returned after administrator paid own card expense;
- crew temporary help wording like `Данил, помощь`;
- tender launch/haul wording for `тузик спуск/подъем`;
- technical purchases: radio, ICOM, tools, Starlink cable, teak consumables;
- interior/tableware: dishes, kitchen items, cushion sewing;
- media/comms: TV picture, TV box, speaker, navigation subscriptions;
- courier/postal transport;
- notary/translator/document wording;
- guest flight seats;
- bank transfer expense wording when it is not a commercial/owner income.

## Explicit non-rules

These remain intentionally outside automatic dictionary expansion:
- `imported row` — technical placeholder, not user language;
- `итого` — total row, not an operational entry;
- `цоги мар` — merchant alias, must stay manual/review unless normalized by user;
- `бензин тузик` remains `fuel` with tender-related meaning, not forced into tender category;
- crew form/uniform remains current boat expense by accepted product decision.

## Evidence

Artifacts:
- `test-results/smith-gap-tuning/control-cases.json`
- `test-results/smith-gap-tuning/no-category-corpus-check.json`

Results:
- Control cases: 23 / 23 passed.
- Claudia Z historical no-category corpus: 65 / 69 matched after tuning.
- Remaining 4 mismatches are accepted non-rules or product decisions listed above.

Technical checks:
- `npm run typecheck:web` passed.
- `npm run build:web` passed.
- `git diff --check` passed.

## Acceptance decision

Accepted as a classifier-tuning slice.

Do not mark this as full Mr. Smith completion: the next work is broad multilingual phrase expansion, document/receipt attachment reasoning, and production UX validation of the quick-note-to-journal flow.
