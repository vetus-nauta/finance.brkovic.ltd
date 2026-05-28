# MVP Release Package

Date: 2026-05-26

Status: MVP foundation gate approved.

This package records the MVP foundation exit point for FinDesk. It is not the complete CEO business MVP and not a full accounting-platform release declaration.

## MVP Contract

FinDesk MVP must let a non-accountant answer:

```text
Где деньги, кто за них отвечает, что потрачено, что проверено, что закрыто, где доказательство?
```

## Approved Scope

- Fast Live Report capture for people in movement.
- Cash/card separation.
- Accountable-money entry point.
- Review before final report truth.
- Current open-period report/export.
- Historical finalized report/export by `report_id` for new finalizations.
- Archive/proof evidence path.
- Mobile/tablet/desktop usability for the verified flow.

## Evidence

- Instant field capture QA: `20260526141856`.
- Backend current/historical contract: `group_id=195`, `report_id=371`.
- UI current/historical report QA: `group_id=200`, `report_id=406`.
- Chief Auditor MVP gate: `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`.

## Code Areas In MVP Cycle

- `app/ledger.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/`

## Known Environment Limit

CLI PHP is unavailable in the current shell, so `php scripts/local-smoke.php http://127.0.0.1:18889` is environment-blocked here.

The local HTTP server at `http://127.0.0.1:18889` has been reachable during the cycle.

## Post-MVP P1

- Legacy finalizations without `report_snapshot`: provide a reproducible fixture and verify `historical_snapshot_missing`.
- Same-second cutoff: replace time-only boundary with deterministic finalization identity.
- Export wording: tighten downloaded current export wording if Product requires exact carryover phrasing.
- Continue small-screen page/menu rebalancing after MVP.

## Stop Rule

The MVP cycle is closed.

New work starts as post-MVP unless a P0 blocker is found.

## Deploy Handoff

Deployment planning continues in `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`.

After the CEO scope correction on 2026-05-26, production deployment as "MVP" should wait unless the CEO explicitly wants to deploy the foundation as an internal alpha.

The broader business MVP scope lives in `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`.

CEO also confirmed that group messages, travel equalization, and business solutions are part of the product scope and must not be dropped as old leftovers.
