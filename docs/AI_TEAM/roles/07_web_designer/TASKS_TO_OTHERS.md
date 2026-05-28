# Tasks To Others: Web Designer

Date: 2026-05-27

P0: none

## P1 / P2

### P1: QA visual confirmation

To: QA Release Engineer

Request:

- run local browser smoke for branding on `index.php` and `app.php` using mobile/tablet/desktop matrix.
- verify logo lockup and brand pill/logo positions visually in hero and top app panel.
- verify favicon/icon consistency in page metadata and browser tab availability.

Acceptance:

- mobile `390x844`, tablet `820x1180`, desktop `1440x900` show centered/consistent logo placement;
- no obvious crop/shift in hero logo and top brand pill;
- `16x16`, `32x32`, `192x192`, `512x512`, and Apple icon are referenced and visible in relevant contexts.

Evidence pointer:
- QA should log screenshots and status in `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`.

Status update:

- PASS (run `20260527`) on mobile/tablet/desktop smoke.
- Evidence pointer now: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/web_designer_branding_20260527/SUMMARY.md`.

Next owner after pass: Project Director.
