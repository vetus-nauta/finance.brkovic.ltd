# SPRINT-104R — Smith Linguistic Classifier

Date: 2026-08-20

## Objective

Bring Mr. Smith closer to a useful yacht-finance assistant by adding an explainable linguistic classifier to the quick-note review flow.

The goal is not a black-box AI. The goal is a safer rules-first assistant that understands common human phrases, proposes a category, and marks weak or risky meanings before money is written into the operational journal.

## Director Discipline

Source of truth remains the operational journal.

No financial formulas were changed.

No report arithmetic was changed.

No category is trained automatically from a single noisy merchant or person name.

## Agent Roles

Localization/Linguistic Agent:
- mapped Russian yacht-operation phrases into the fixed category set;
- kept `цоги мар` / `цогимар` / `cogimar` as a manual merchant alias, not a universal dictionary rule;
- separated strong object words from weak context words.

Financial Logic Reviewer:
- kept `admin_debt`, `lower_accounting`, and `guest_cash_issued` out of ordinary operational category totals;
- kept owner funding separate from commercial income;
- preserved the card-income guard.

QA / Acceptance Agent:
- checked SQL, JSON, TypeScript, build, and direct classifier smoke examples.

Subagent spawning note:
- live subagent spawn was blocked by thread limit, so the Director executed the agent roles locally and recorded the reports here.

## Implemented

Database:
- Added `public.classify_foundation_entry(raw_text, candidate_direction, account_code)`.
- Added Smith proposal fields:
  - `candidate_category_code`
  - `confidence`
  - `review_reason`
  - `matched_signals`
  - `blockers`
  - `semantic_markers`
- `prepare_quick_note_entry_proposals(...)` now classifies each note line before the user approves transfer.
- `convert_smith_entry_proposals(...)` carries Smith classification metadata into created operational entries.

Application:
- Smith review panel now shows a human category label, decision reason, and confidence.
- Possible duplicate rows are not selected by default.
- Editing a note already sent to Smith now reuses the current note body and voids stale pending/rejected proposals instead of silently checking old text.

Dictionary documents:
- Added `non_commercial_income` to the money-balance group.
- Marked `admin_debt` and `lower_accounting` as review/accounting blocks, not normal leaf categories.
- Expanded guest-trip support, representative expenses, provisions, and current-boat overhead phrase coverage.

## Classifier Rules Accepted

Income:
- explicit yacht rental/charter wording -> `commercial_income`;
- cash income without commercial yacht wording -> `non_commercial_income`;
- card-to-cash phrases -> `cash_topup_from_card`;
- card income remains guarded.

Expenses:
- object words beat weak action words;
- `доставка фильтра` -> `tech_parts`, not transport;
- `долг таможне дьюти фри` -> `admin_legal` with debt marker, not blocked lower accounting;
- `дал гостям` -> `guest_cash_issued` / lower-accounting context;
- `заказал себе с temu` -> `admin_debt`;
- `цоги мар` stays manual review.

## Evidence

Local checks:
- `npm run check:foundation:sql` — PASS.
- Dictionary JSON parse — PASS.
- `npm run typecheck:web` — PASS.
- `npm run build:web` — PASS.

Supabase:
- Migration `smith_linguistic_classifier` — applied.
- Migration `smith_linguistic_classifier_tuning` — applied.
- Direct classifier smoke passed for:
  - `+1000 поступило от судовладельца` -> `non_commercial_income`
  - `+5525 аренда яхты` -> `commercial_income`
  - `+1000 снял с карты` -> `cash_topup_from_card`
  - `-55 кабель старлинка` -> `tech_parts`
  - `-350 продукты` -> `provisions`
  - `-87 заказал себе с temu` -> `admin_debt`
  - `-532 совместный поход в цоги мар` -> manual review
  - `-55 дал гостям` -> `guest_cash_issued`
  - `-150 долг таможне дьюти фри` -> `admin_legal`
  - `-40 украшения к дню рождения` -> `representation_expenses`
  - `-20 доставка фильтра` -> `tech_parts` with mixed-context marker.

## Remaining Work

1. Add a proper category-edit step in Smith review before transfer.
2. Connect classifier output to report category totals through a formal category materialization rule.
3. Add workspace-local learning from accepted user corrections.
4. Add internet/provider lookup as an allowlisted reference source, not as automatic truth.
