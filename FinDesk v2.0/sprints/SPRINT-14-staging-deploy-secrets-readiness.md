# Sprint 14 - Staging, Deployment, and Secrets Readiness

## Goal

Close operational readiness for a clean v2 deployment without committing secrets or changing product UI.

## Depends on

- Sprint 13 handoff
- `12-deployment-notes.md`
- `18-security-privacy-notes.md`
- `20-definition-of-done.md`

## Director rule

Deployment readiness is proven by boundaries, environment inventory, and repeatable steps, not by copying hidden credentials into the repo.

## Agents

- Director
- DevOps/Deploy Agent
- Security/Privacy Agent
- Backend Core Agent as reviewer

## Scope

- environment variables list without values;
- database migration readiness;
- rollback notes;
- staging/prod separation;
- permissions and secret boundaries;
- logs and audit data handling.

## Visible-change bypass

Any staging UI polish, banners, theme changes, or visual environment markers are deferred.

## Forbidden

- no secrets committed;
- no production mutation without explicit release action;
- no old deployment script treated as safe by default;
- no UI polish as readiness proof.

## Exit criteria

- secrets boundary is explicit;
- deployment checklist exists;
- migration order is clear;
- rollback risks are documented;
- Sprint 15 can decide release-candidate acceptance.

## Final handoff

Pass deploy readiness, secret risks, and rollback notes to Sprint 15.
