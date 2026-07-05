# SPRINT-12R — Other Review Fallback Hardening

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-12R — Other Review Fallback Hardening

Goal:
Fix the browser-found parser gap where explicit manual cash expenses such as `other expense` and `unknown_expense` stayed recognized with no category instead of entering the visible Other review queue.

Required files read:
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/15-test-fixtures.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:
- Financial Logic Engine Agent: Planck
- QA, Audit, and Acceptance Agent: Avicenna

Agent tasks:
- Financial Logic Engine Agent: verify the parser fallback does not change formulas, counted-status rules, or monthly report semantics.
- QA, Audit, and Acceptance Agent: verify coverage for the browser-found strings and guard cases around cash/card/income/no-sign boundaries.

Exit criteria:
- Explicit cash expense fallback words map to `category_code=other` and `status=other_review`.
- Cash income, card expense, and no-sign rows do not enter the cash Other review queue.
- Existing fixtures and HTTP smoke pass.
- Browser check confirms the UI Other counter increments for the browser-found strings.
- No dashboard, formula, report, or deploy behavior changes.

## Agent Reports

Financial Logic Engine Agent:
ACCEPT. The change is parser-only, still gated by cash flow and negative sign. Monthly formulas, counted status rules, and the Other review queue contract are unchanged.

QA, Audit, and Acceptance Agent:
ACCEPT. The fix covers the browser-found cases in fixtures and HTTP smoke. QA requested guard cases for `+ unknown`, card `- other`, and no-sign `misc`; these were added.

## Director Final Handoff

Sprint:
SPRINT-12R — Other Review Fallback Hardening

Status:
Accepted

Agents assigned:
- Financial Logic Engine Agent: Planck
- QA, Audit, and Acceptance Agent: Avicenna

Agent reports received:
- Financial Logic Engine Agent: ACCEPT.
- QA, Audit, and Acceptance Agent: ACCEPT.

Accepted work:
- `other expense`, `unknown_expense`, `unknown`, `unclear`, `misc`, and Russian fallback stems now map to Other review only for cash negative rows.
- Fixture and HTTP smoke coverage now includes the browser-found `other expense` and `unknown_expense` strings.
- Guard coverage confirms cash income, card expense, and no-sign rows do not enter cash Other review.
- Browser check confirmed Other count increments for `-23 other expense browser fixed` and `-24 unknown_expense browser fixed`.

Rejected work:
- Broad low-confidence classification for arbitrary unknown expenses remains out of scope.
- No formula, dashboard, report, deploy, or old FinDesk product logic changes were accepted.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-12R-other-review-fallback-hardening.md`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`

Tests or checks:
- `php -l app/v2/Repository.php`
- `php -l scripts/v2_fixture_runner.php`
- `php -l scripts/v2_http_api_smoke.php`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- Full local gate:
  `npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && npm run smoke:v2:db && npm run smoke:v2:ui && npm run smoke:v2:browser && npm run smoke:v2:deploy`
- Browser manual check in opened Chrome, screenshot:
  `test-results/v2-browser-smoke/manual-run-12-other-fallback-fixed.png`

Risks:
- This remains a keyword fallback, not a complete low-confidence parser model.
- Bare word `other` can classify a cash negative row as Other review; this is accepted for explicit manual review wording but should be revisited if false positives appear.

What must not be touched:
- Do not change financial formulas without separate decision.
- Do not make dashboard/report-first UX.
- Do not treat old FinDesk product logic as source of truth.

Next sprint:
Return to SPRINT-11R live production deployment evidence, unless another browser-found MVP blocker appears.

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. SPRINT-12R accepted a narrow Other review fallback fix found during visible browser testing. Do not broaden it into a full AI/low-confidence parser sprint without a separate decision. Continue with SPRINT-11R live production deployment evidence: production URL deny checks, auth smoke, v2 smoke, cleanup proof, and rollback artifacts.
