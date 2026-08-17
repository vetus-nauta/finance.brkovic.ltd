# SPRINT-34R — Dictionary Training Assistant and Rule Constraints

## Director Sprint Opening

Sprint:
SPRINT-34R — Dictionary Training Assistant and Rule Constraints

Date:
2026-07-08

Goal:
Define the first controlled assistant layer for dictionary training: a human-friendly review helper that can explain category suggestions, propose safer rule constraints, and prepare a future beta internet reference agent named `Mr. Smith`.

Source of truth:
GitHub files only.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/sprints/SPRINT-33R-dictionary-training-triage-filters.md`

Agents assigned:

- UX/Product Agent
- QA, Audit, and Security Agent
- Localization and Linguistic Rules Agent

Agent tasks:

- UX/Product Agent: define the human-facing behavior of the assistant and the boundaries between suggestion, decision, and accounting truth.
- QA, Audit, and Security Agent: define privacy, provenance, tenant isolation, and no-autonomous-learning gates for the future internet reference layer.
- Localization and Linguistic Rules Agent: define how linguistic suggestions can improve dictionary rules without over-expanding categories or turning merchant names into universal rules.

Expected reports:

- Human-facing assistant behavior and tone.
- Mr. Smith beta scope and must-not-do list.
- Rule-constraint recommendations for `requires_any` / `excludes_any`.
- Security and audit acceptance gates.

Exit criteria:

- Assistant is documented as advisory only.
- Mr. Smith is documented as a beta reference agent, not a product-truth source.
- Internet evidence cannot write entries, rules, reports, imports, balances, or universal dictionary data by itself.
- Future implementation has clear consent, provenance, and audit requirements.

Risks:

- Treating internet search results as financial truth.
- Training global dictionaries from one workspace-specific merchant alias.
- Leaking private operational text into third-party searches.
- Producing technical diagnostics instead of clear user-facing guidance.

## Product Contract

The dictionary assistant is a review helper.

It may:

- explain why the current parser/category guess was made;
- point out weak, mixed, blocked, or missing signals;
- suggest a safer local rule pattern;
- suggest `requires_any` and `excludes_any` constraints;
- prepare a human-readable question for the reviewer;
- suggest that a row belongs in `review` when the evidence is not strong enough.

It must not:

- create or modify operational entries;
- change cash/card balances;
- change parser primitives;
- change reports, imports, closures, or deployment behavior;
- promote a universal rule;
- turn a workspace-specific merchant name into a global category rule;
- replace the reviewer decision.

The assistant answer must be loyal to a human operator, not a technical dump.

Preferred style:

```text
I would keep this in review for now.
The row looks like a supplier name, but the text does not say what was bought.
If this supplier appears again with words like fish, market, or provisions, a local rule can be proposed.
```

Forbidden style:

```text
Category resolved with classifier confidence 0.63.
Apply universal rule merchant=cogimar.
```

## Mr. Smith Beta Agent

`Mr. Smith` is the planned beta agent for internet/resource/supplier/store matching.

Purpose:

- identify whether an unclear word may be a store, supplier, marina, service company, restaurant, transport provider, or other external resource;
- collect reference context that helps the reviewer understand the row;
- provide aliases, transliterations, language variants, city/country hints, and likely business type;
- reduce blind manual review for real-world merchant names and supplier names.

Mr. Smith may return:

```text
reference label
possible business type
possible location
aliases/transliterations
source title
source URL
retrieved_at
confidence
reason for uncertainty
suggested reviewer question
```

Mr. Smith must not return:

```text
final accounting category
final financial classification
universal rule approval
entry mutation instruction
balance/report mutation instruction
private user data copied to an external source
```

Internet reference evidence is not product truth.

Reference evidence becomes useful only after a reviewer decision links it to a local workspace rule, a rejected candidate, or a manual correction.

Mr. Smith is separate from the Localization and Linguistic Rules Agent:

- the linguistic layer studies wording, variants, misspellings, and category evidence;
- Mr. Smith studies possible external real-world references;
- neither layer can make a financial decision without explicit reviewer action.

## Privacy And Consent Rules

Mr. Smith must use minimization:

- query only the smallest safe phrase;
- strip amounts, balances, personal names, private notes, and vessel/client identifiers unless the reviewer explicitly approves;
- prefer public business names over raw operational rows;
- store source provenance, not scraped pages;
- cache only evidence needed for audit.

Before production use, any internet lookup must have:

- explicit workspace-level enablement;
- visible user consent for sending text outside the workspace;
- audit log entry for each lookup;
- reviewer-visible source provenance;
- tenant isolation;
- no cross-workspace learning without anonymized promotion review.

## Rule Constraint Direction

The next implementation layer should make rule suggestions safer by using constraints.

Examples:

```text
pattern: агент
requires_any: []
excludes_any: [личный, мой, долг, кредит]
scope: workspace-local
```

```text
pattern: доставка
requires_any: [такси, трансфер, курьер, порт, аэропорт]
excludes_any: [доставка фильтра, доставка запчасти]
scope: workspace-local candidate
```

```text
pattern: merchant alias
requires_any: [рыба, магазин, провизия]
excludes_any: []
scope: workspace-local candidate only
```

Weak merchant aliases must stay in review until the row text contains category evidence or the reviewer confirms the local mapping.

## Not In Scope

- Autonomous internet learning.
- Production web search integration.
- Automatic universal promotion.
- Any change to financial formulas.
- Any change to parser/report/deploy behavior.
- Any import mutation.
- Any dashboard-first UX work.

## Director Handoff State

Status:
OPENING DOCUMENTED.

Current decision:
Mr. Smith is accepted as a future beta reference agent, with strict advisory-only boundaries.

Next work:

- implement assistant readback inside Training detail;
- expose `requires_any` / `excludes_any` proposal controls;
- keep all Training decisions behind explicit reviewer action;
- design Mr. Smith as a separate opt-in beta after the local assistant layer is stable.

## Implemented

Training detail:

- Added advisory assistant readback above the evidence/form area.
- Readback is scoped to the selected `source_row_id`.
- Readback explains weak, mixed, blocked, missing-category, saved-decision, and possible-local-rule states.
- Readback cites visible evidence: current guess, review reason, and blockers.
- Added editable `Requires any` and `Excludes any` controls to the Training decision form.
- Prefilled suggested constraints for weak, mixed, blocked, and merchant-alias rows.
- Decision POST now sends edited `requires_any` and `excludes_any` arrays for local approval/correction/universal-candidate decisions.

Safety:

- Assistant readback is local UI derivation only.
- No parser, report, import, entry, balance, closure, or financial formula changes.
- Blocked rows still disable local category-rule approval.
- Universal candidate remains audit-only.

Files changed:

- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_http_api_smoke.php`
- `FinDesk v2.0/sprints/SPRINT-34R-dictionary-training-assistant-and-rule-constraints.md`

Verification added:

- Browser smoke asserts `data-v2-training-assistant-readback`.
- Browser smoke asserts weak/mixed/blocked assistant wording.
- Browser smoke asserts edited `requires_any` / `excludes_any` arrays in the decision POST payload.
- HTTP smoke asserts persisted `requires_any` / `excludes_any` on a local training decision.

## Verification

Commands:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Result:

```text
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS 21
FinDesk v2 HTTP API smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

## Director Final Handoff

Sprint:
SPRINT-34R — Dictionary Training Assistant and Rule Constraints

Status:
ACCEPT.

Agents assigned:

- UX/Product Agent
- QA, Audit, and Security Agent
- Localization and Linguistic Rules Agent

Agent reports received:
YES.

Accepted work:

- Assistant readback is visible in Training detail.
- Constraint suggestions are visible and reviewer-editable.
- Edited `requires_any` / `excludes_any` are submitted and persisted.
- Blocked rows remain protected.

Rejected work:

- Autonomous assistant decisions.
- Universal rule promotion.
- Financial/import/report/parser mutation.

Next sprint:
SPRINT-35R — Mr. Smith Beta Reference Stub.

## Agent Reports

### UX/Product Agent

Status:
ACCEPT.

Required behavior:

- Mr. Smith is labeled as beta guidance, not product truth.
- Every suggestion shows row evidence and matched token/phrase/alias.
- Merchant/supplier/resource matching is separate from category decision.
- Weak, mixed, actor-only, private, debt, and settlement context must stay review-safe.
- User can defer, ignore, or manually review suggestions.
- Merchant aliases such as `цоги мар`, `цогимар`, and `cogimar` remain manual review context unless a human decides otherwise.
- Suggestions must not change category, balance, report, import, approval state, or saved training decision.

Tone:

- Use calm, practical, evidence-led wording.
- Prefer `possible match`, `weak signal`, `needs human review`, and `do not train from this alone`.
- Avoid `confirmed`, `definitely`, `I fixed it`, `category is`, and `universal rule created`.

### QA, Audit, and Security Agent

Status:
ACCEPT.

Required gates:

- Mr. Smith must be read-only for finances, reports, imports, closures, category rules, actors, and audit state.
- Internet lookup must be opt-in per action or through a clearly enabled beta setting.
- UI/API must show what text will be sent before lookup.
- No background lookup from raw import history.
- Queries must strip or mask amounts, balances, dates, workspace names, actor/person names, source ids, and unrelated notes unless explicitly needed.
- Matching must be tenant/workspace scoped.
- Cache keys must include workspace boundary and sanitized query hash.
- Every match must include provenance: source URL/domain, retrieval timestamp, sanitized query or hash, confidence/reason, and source type.
- External provenance must be visibly distinct from FinDesk dictionary signals.
- No autonomous learning from external matches.
- Future `save as rule` paths must require explicit reviewer approval and existing blocker protections.

Risks:

- Sensitive operational text can leak through external queries.
- Cross-workspace cache reuse can leak tenant data.
- Online supplier names can bias the reviewer.
- External sources can be stale, wrong, poisoned, or SEO-spam.
- Automatic learning from internet matches can corrupt dictionaries.
