# Receipt Scanner Real Device QA Gate

Date: 2026-05-28
Owner: Project Director
Next owner: QA Release Engineer / CEO physical device check
Status: required before production scanner deploy

## Context

Local browser/HTTP scanner evidence is approved by Chief Auditor for the file-input path only.

Accepted local evidence:

- QA run `20260528RSQA01`;
- mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`;
- one money row after retry;
- `scanner_original` and `scanner_cleaned_pdf` stored in one proof bundle;
- original/PDF/signed-sync replay idempotent;
- final package recheck `group_id=226`, `report_id=516`.

Not accepted yet:

- physical camera capture;
- installed iPhone Safari PWA behavior;
- Android Chrome behavior;
- production device behavior.

## Required Device Matrix

Minimum:

- iPhone Safari in browser mode;
- iPhone installed PWA from home screen;
- Android Chrome in browser mode;
- Android installed PWA if available.

If Android installed PWA is not available, record the limitation explicitly.

## Scenario

Run the same scenario on each device/mode:

1. Login.
2. Open FinDesk PWA.
3. Create or open a test group.
4. Open `Живой отчет`.
5. Enter one cash row, for example `-12 receipt device scan`.
6. Tap `Скан`.
7. Confirm `Скан чека в PDF` opens.
8. Use the physical camera if the device offers it.
9. Confirm selected/captured receipt appears in the scanner preview.
10. Move all four crop corners by touch.
11. Change cleanup level.
12. Toggle black/white mode.
13. Tap `Прикрепить PDF`.
14. Save the Live Report row.
15. Refresh/close/reopen the PWA.
16. Confirm the same money row remains once, not duplicated.
17. Confirm proof remains attached.
18. Retry the proof path if possible without duplicating the money row.
19. Include the report.
20. Finalize the group report.
21. Open the closed group report package.
22. Confirm the package exposes both original source and cleaned PDF.

## Acceptance

Pass only if:

- scanner opens from `Живой отчет`;
- camera or file picker works without trapping the user;
- crop handles are usable by finger on phone;
- PDF attachment is created;
- original and cleaned PDF are stored as linked proof artifacts;
- refresh/return does not lose the money row or proof;
- retry does not duplicate the money row;
- final package still contains both source and cleaned PDF;
- no layout overlap blocks the scanner controls on the tested phones.

## Blockers

Block production scanner deploy if:

- PWA camera cannot open or return an image on iPhone Safari PWA;
- scanner modal cannot be closed/recovered;
- crop handles are not usable on small phone screen;
- save creates duplicate money rows;
- original receipt source is missing after save/final package;
- cleaned PDF is stored without source link;
- refresh loses current unsent scanner state after visible saved state;
- proof retry changes the money row target.

## Reporting Rule

QA writes full evidence in:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Short report to Director only:

Role / Task / Status / Evidence pointer / Blocker / Next owner.

## Director Decision

Do not deploy the scanner to production as device-ready until this gate passes or CEO explicitly accepts a limited release without physical camera/PWA claim.
