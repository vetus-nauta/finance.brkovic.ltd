# Tasks To Others: Backend Data Engineer

## To Project Director / Deploy Owner / Database Migration Owner

Date: 2026-05-28
Priority: P0 before production upload of candidate 34
Context: Backend/Data prepared DB deploy preflight for scanner proof columns and confirmed `group_delete` does not need an optional timestamp migration.
Request: run or delegate the production DB read-only preflight and record before/after evidence before any upload.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/DEPLOY_PREFLIGHT_DB_CANDIDATE_34_2026-05-28.md`

Required actions:

1. Run the read-only production SQL from the evidence pointer.
2. Record DB engine/version and scanner column/index state.
3. If scanner schema is complete, record DB-side GO.
4. If scanner schema is missing and production is MariaDB-compatible, apply the selected SQL after backup:
   - `deploy/on_the_go_sessions_runtime.sql` for existing runtime DBs;
   - `deploy/on_the_go_foundation.sql` first only if `on_the_go_files` is missing.
5. If production is MySQL/unknown and conditional `ADD COLUMN IF NOT EXISTS` / `ADD KEY IF NOT EXISTS` compatibility is not confirmed, stop and prepare missing-only DDL or staging proof before production apply.
6. Record backup/rollback owner and after-state SQL output.

Blocker:

- Production upload remains DB-side NO-GO until this evidence is recorded.

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 after DB preflight/apply and upload only
Context: Candidate 34 scanner schema and group-delete archive behavior require production smoke after deploy.
Request: add DB-backed scanner proof and `group_delete` checks to production smoke.

Acceptance criteria:

1. `on_the_go_upload_file` stores `scanner_original` and `scanner_cleaned_pdf` with one `proof_bundle_id`.
2. Replaying the same `client_upload_id` is idempotent.
3. `on_the_go_file_list` returns scanner roles, hashes, and metadata.
4. Final report/package proof index shows scanner roles and links after finalization.
5. `group_delete` by base/non-admin returns `admin_required`.
6. `group_delete` by admin soft-archives the group and preserves evidence counters.
7. No hard-delete of ledger entries, Live Reports, proofs, advances, messages, or final-report audit rows.

Blocker:

- Any missing scanner column/index after deploy, duplicate proof on retry, missing proof role/hash in package, or evidence counter loss is P0.

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 before deploy package
Context: Backend/Data hardened `group_delete` as a soft archive API and fixed the runtime blocker where DBs without `groups.updated_at` failed with `Unknown column 'updated_at' in 'SET'`.
Request: formally recheck safe test-group archive/delete behavior through HTTP/API.

Acceptance criteria:

1. Admin creates a disposable group.
2. Base employee joins by default/base invite.
3. Admin creates at least one financial evidence row in that group.
4. Base employee calling `group_delete` receives `admin_required`.
5. Admin calling `group_delete` receives `ok=true`, `archive_mode=soft`, and group `status=archived`.
6. Response `financial_evidence.before` and `financial_evidence.after` preserve the evidence counters, especially ledger rows.
7. Repeating `group_delete` by creator/admin returns idempotent `already_deleted=true`.
8. Archived group disappears from active group list and rejects new group ledger writes.
9. No hard-delete of ledger entries, Live Reports, proofs, advances, messages, or final-report audit rows.

Evidence to write:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Blocker if failed:

- Any server error on missing optional timestamp columns.
- Any non-admin/base user able to archive/delete the group.
- Any loss of financial evidence counters after archive.

## To Project Director / Product Finance Architect

Date: 2026-05-28
Priority: P0 before claiming Receipt Scanner MVP
Context: Backend/Data recorded the Receipt Scanner storage/API task card. MVP can start with client-side scanning/cleaning/PDF generation, but backend must persist original files, cleaned/PDF derivatives, metadata, proof states, finance/archive links, file-size/privacy controls, and deterministic hashes.
Request: approve the MVP processing/storage direction and schedule backend implementation.

Required decisions:

1. Accept MVP path: frontend processing plus backend authoritative storage.
2. Decide whether server-side PDF generation is post-MVP validator/reprocessor or required for MVP.
3. Approve receipt file limits, allowed MIME types, and privacy rule for EXIF/location metadata.
4. Confirm which report/export/archive surfaces must show original vs cleaned/PDF artifacts in MVP.
5. Assign backend implementation owner for schema/API/storage and QA owner for deterministic evidence proof.

Acceptance criteria:

- Receipt Scanner is not described as release-ready until storage/API exists.
- Product accepts that frontend-generated PDFs are acceptable only when backend stores bytes, metadata, states, links, and hashes.
- Server-side generation decision is recorded explicitly if it becomes MVP scope.

## To Frontend UX Engineer

Date: 2026-05-28
Priority: P0 after backend API shape is approved
Context: Receipt Scanner MVP may use client-side image cleaning and PDF generation, but the UI cannot remain frontend-only. Backend must receive enough data to preserve deterministic evidence and archive it.
Request: design scanner wiring around durable backend proof storage.

Frontend requirements:

1. Keep a stable `client_upload_id` per receipt upload attempt.
2. Start receipt proof as `pending` before or during upload.
3. Upload original image/file bytes, size, MIME, and expected hash.
4. Upload cleaned image/PDF bytes plus expected hash.
5. Send scanner metadata: corners, perspective/crop/rotation, filter parameters, scanner version, and processing mode.
6. Report upload failure/interruption so backend can store `failed` or retry-needed state.
7. Reuse the same upload id on retry instead of creating duplicate proof evidence.
8. Show proof as complete only after backend confirms stored artifacts and hashes.

Acceptance criteria:

- Refresh/browser-cache clear does not lose pending/failed/retry state after the first backend call.
- Final report/archive opens saved backend artifacts, not a regenerated current browser preview.
- UI distinguishes original receipt, cleaned view/PDF, and failed/retry upload states.

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 after Receipt Scanner implementation
Context: Receipt Scanner evidence must be deterministic and survive the same Field Combat/report/archive lifecycle as other proofs.
Request: create QA coverage for scanner storage/API once Backend and Frontend implement the task card.

Minimum checks:

1. Upload a receipt original image and verify stored byte size, MIME, and hash.
2. Store cleaned image/PDF and verify derivative byte size, MIME, hash, and link to original.
3. Verify scanner metadata contains corners, perspective/crop/rotation, filter/version, and canonical metadata hash.
4. Force upload interruption and verify durable `pending -> failed` or retry-needed state after refresh/login.
5. Retry with the same upload id and verify no duplicate proof rows/artifacts.
6. Attach the receipt to a capture and verify the link remains visible through tape/card detail.
7. Submit/include/finalize and verify the final report/archive package opens the saved original and cleaned/PDF artifacts.
8. Verify private storage: no public listing/read, auth-scoped download, noindex/noarchive boundary if reachable.
9. Verify oversized/unsupported MIME files are rejected with deterministic errors and no partial public artifact leak.

Acceptance criteria:

- Any scanner proof that exists only in the browser and not in backend storage is P0.
- Any final report/archive that regenerates receipt evidence instead of opening saved artifacts is P0.
- Any missing pending/failed/retry recovery is P0.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 production rights recheck
Context: Default invited `base` employee rights were hardened and deployed. Director production fixture `group_id=10`, employee user `27`, stamp `20260527210337` passed.
Request: independently recheck base invited employee rights on production.

Acceptance criteria:

- default invite creates `access_level=base`;
- base employee has no `can_view_group_reports`, `can_write_group_ledger`, `can_manage_money`, `can_moderate`, or `can_manage_members`;
- base employee can use own operational field capture inside the group;
- base employee self-control data does not include administrator/group cash;
- base employee cannot access group report export, final report list/detail/export/package, group messages, role management, money management, or other members' money;
- base employee sees only own operational cards and own accountable data;
- manager/admin access remains unchanged.

Evidence to write:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 production recheck
Context: Backend/Project Director implemented and deployed the participant-control patch for the production multi-employee blocker. Local HTTP fixture `group_id=223`, `report_id=499` passed. Director production smoke fixture `group_id=9`, `report_id=84` passed with `admin_cash_left=568`, employee positive remainders `184`, reimbursement due `36`, employee net `148`, and group balance `716`.
Request: rerun, inspect, or reproduce the production multi-employee scenario as QA Release Engineer and record the formal role gate.

Acceptance criteria:

- total expenses remains `284`;
- group balance remains `716`;
- final detail/package/export/print show `admin_cash_left=568`;
- employee 1 row shows `67`;
- employee 2 row shows signed remaining `-36` and reimbursement due `36`;
- employee 3 row shows `117`;
- headline totals expose either `employee_net_remaining_total=148` or equivalent first-class fields `positive remaining 184` and `reimbursement due 36`;
- employee 2 overrun is visible without opening raw audit refs;
- package/archive remains closed by `report_id` and later current-period activity does not mutate it.

Blocker if failed:

- Any return to `532 + 184 = 716` as the only headline/control explanation is P0.

Evidence to write:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- role artifact folder if screenshots/exports are generated.

## To Project Director / Deploy Owner

Date: 2026-05-27
Priority: P0 before production upload
Context: Technical SEO/PWA infra check is complete in `docs/AI_TEAM/22_TECHNICAL_SEO_INFRA_CHECK.md`. The current selected package lists `public/index.php`, `public/app.php`, and `public/service-worker.js`, but not `public/robots.txt`, `public/sitemap.xml`, or `public/manifest.webmanifest`. Production deploy remains NO-GO under the existing DB/backup controls.
Request: decide the SEO/PWA package and header rules before any public upload.

Required decisions:

1. Add `public/robots.txt`, `public/sitemap.xml`, and `public/manifest.webmanifest` to the selected package, or record evidence that production already has byte-equivalent current versions.
2. Verify production `/storage/` is 403/auth-protected or outside public docroot; robots is not enough for storage privacy.
3. Add/verify `X-Robots-Tag: noindex, nofollow, noarchive` for `/app.php`, `/api.php`, and any storage response that can be reached.
4. Verify MIME headers:
   - `/manifest.webmanifest`: `application/manifest+json; charset=utf-8`;
   - `/robots.txt`: `text/plain; charset=utf-8`;
   - `/sitemap.xml`: `application/xml; charset=utf-8` or `text/xml; charset=utf-8`;
   - `/service-worker.js`: JavaScript content type.
5. Verify cache headers:
   - `/` and `/app.php`: preserve no-store/no-cache;
   - `/api.php`: no-store;
   - `/service-worker.js`: no-cache/revalidate;
   - versioned assets: long cache only with rollback instructions.
6. Do not add Search Console/Bing verification tokens until an authorized owner provides an explicit no-credentials deploy task.
7. Do not add analytics until query stripping and finance-data exclusion rules are recorded.

Acceptance criteria:

- Public SEO/PWA file list is explicit.
- Private app/API/storage boundary is protected by server behavior, not only by robots.
- Header evidence is captured during production smoke.
- This does not override the existing production NO-GO for DB backup, storage backup, schema preflight, runtime SQL decision, rollback owner, and smoke owner.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 after approved upload only
Context: Technical SEO/PWA smoke must run only after Project Director/CEO authorizes production upload/smoke and deploy owner records the selected package.
Request: add SEO/PWA infra checks to the production smoke.

Minimum smoke:

1. `GET /` returns 200, canonical `https://finance.brkovic.ltd/`, and no-store/no-cache headers.
2. `GET /app.php` returns 200, includes `noindex,nofollow`, preserves no-store/no-cache headers, and has `X-Robots-Tag` if configured at server level.
3. `GET /robots.txt` returns 200, `text/plain`, and disallows `/app.php`, `/api.php`, and `/storage/`.
4. `GET /sitemap.xml` returns 200, XML content type, and contains only `https://finance.brkovic.ltd/`.
5. `GET /manifest.webmanifest` returns 200, `application/manifest+json`, and start URL `/app.php`.
6. `GET /service-worker.js` returns 200, JavaScript content type, and no-cache/revalidate behavior.
7. `/api.php` is not indexable and returns JSON with no-store for known smoke actions.
8. `/storage/` is not publicly listable/readable.
9. Active browser service worker is the uploaded version; old `findesk-*` caches are not serving stale HTML/assets after hard reload/private window.
10. No analytics request sends finance data, invite tokens, credentials, full app URLs with query strings, API bodies, or storage URLs.

Acceptance criteria:

- Evidence includes URL, status, content type, cache headers, robots/noindex/X-Robots state, active service-worker state, and pass/fail per item.
- Any public storage listing, stale service-worker response, sitemap private URL, missing private noindex/X-Robots boundary, or finance-data analytics leakage is P0.

## To Project Director / Deploy Owner

Date: 2026-05-27
Priority: P0 before production upload
Context: Backend/Data completed the production deploy readiness plan in `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md`. Backend/API product readiness remains PASS, but production deploy is blocked by deploy controls and dirty-tree dependency selection.
Request: choose and record the exact production deploy mode and file list before any upload.

Required decisions:

1. Choose full dirty-tree bundle or narrow MVP runtime bundle.
2. If narrow, resolve dependency closure for:
   - `public/api.php`
   - `app/auth.php`
   - `app/groups.php`
   - `app/on_the_go.php`
   - `app/ledger.php`
   - `app/advances.php`
   - `app/ai.php` if current `public/api.php` is deployed unchanged
   - `deploy/on_the_go_sessions_runtime.sql`
3. Decide whether untracked `app/ai.php` is deliberately in production scope or whether the selected API file must avoid requiring it.
4. Assign Frontend UX Engineer to confirm public/UI deploy files, including service worker and icons/assets.
5. Exclude local/test/reset/support files unless explicitly approved:
   - `public/reset-local.php`
   - `scripts/start-local.sh`
   - `test-results/`
   - local docs/work notes

Acceptance criteria:

- Exact file list is recorded.
- No blind upload of the dirty working tree.
- API dependency closure is resolved before upload.
- Production action is still blocked until DB migration, backup/rollback, and smoke owner are ready.

## To Database Migration Owner / Deploy Owner

Date: 2026-05-27
Priority: P0 before PHP upload
Context: Runtime PHP depends on On the Go session/field/proof schema. `deploy/on_the_go_sessions_runtime.sql` uses `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`, which may not be accepted by every MySQL/MariaDB production version.
Request: verify/apply required schema before uploading selected PHP.

Required checks:

1. Record production DB engine/version with no credentials in notes.
2. Verify compatibility with `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`.
3. If incompatible, stop and request engine-compatible idempotent SQL.
4. Confirm or apply:
   - `on_the_go_sessions`
   - `on_the_go_captures.tape_id`
   - `on_the_go_captures.session_id`
   - `on_the_go_tapes.group_id`
   - `on_the_go_tapes.advance_id`
   - `on_the_go_tapes.stream_type`
   - `on_the_go_tapes.submitted_at`
   - `on_the_go_tapes.actual_remaining`
   - `on_the_go_tapes.difference_amount`
   - `on_the_go_field_drafts`
   - `on_the_go_field_sync_ops`
   - `on_the_go_upload_states`
5. Verify previous foundation schema exists for auth/audit, groups/access, ledger, On the Go foundation, advances, messages, and Business Desk if included in deploy/smoke.

Acceptance criteria:

- Migration is applied or proven already present before PHP upload.
- Any SQL incompatibility is treated as a production deploy blocker.
- Schema evidence is recorded without credentials.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 after approved upload
Context: Production smoke is separate from business-MVP product readiness. It must be run only after Project Director/CEO authorizes the production smoke step and deploy owner records file list and DB state.
Request: run backend/API production smoke from `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md`.

Minimum smoke:

1. App load and `current_user`/session check.
2. Login flow without production dev-code exposure.
3. Field Combat draft save/recover.
4. Proof state begin/fail/list and upload retry.
5. Replayed `client_operation_id` does not duplicate money rows.
6. Cash/card stream separation.
7. Current export opens.
8. Closed report list/detail/package/export opens.
9. Package proof download works for authorized reviewer.
10. Historical/current separation remains true.
11. Advance/accountable state is represented in package.
12. Group messages remain group-scoped.
13. Business Desk/proforma smoke does not mutate ledger formulas.

Acceptance criteria:

- Evidence includes deployed file set, SQL state, URL/environment, smoke ids, and pass/fail per item.
- Any schema error, API fatal, missing package/proof route, duplicate money row, or current/historical export regression is P0.

## To Project Director / Deploy Owner

Date: 2026-05-27
Priority: P0 before production upload/smoke
Context: Backend/API product readiness has no known P0 after QA residual surface PASS, but production deploy readiness is still blocked by deployment controls. The current tree is broad and dirty; `HEAD=72b38e6` and `origin/main=72b38e6`, but runtime files and docs/assets/support files are modified or untracked.
Request: choose and record the exact production deploy package before any upload.

Required controls:

1. Choose deploy mode from `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`: full current working-tree bundle only if explicitly approved, or narrow MVP runtime bundle.
2. Record the exact file list to upload.
3. Exclude local/test/reset/support artifacts unless explicitly approved, including `public/reset-local.php`, `scripts/start-local.sh`, and `test-results/`.
4. Back up production files and production database before upload.
5. Keep a rollback copy and assign rollback owner.
6. Assign production smoke owner and require evidence after upload.

Acceptance criteria:

- No blind upload of the whole dirty tree.
- Deploy package contains all required runtime dependencies and no unapproved local/test artifacts.
- Backup, rollback, and smoke evidence are recorded before declaring production deploy ready.

## To Project Director / Database Migration Owner

Date: 2026-05-27
Priority: P0 before production upload/smoke
Context: Current PHP runtime depends on On the Go session schema. `deploy/on_the_go_sessions_runtime.sql` contains the required runtime migration, but production engine/version compatibility has not been confirmed in this pass.
Request: apply or verify the production database migration before uploading PHP that depends on it.

Required checks:

1. Confirm production already has or receives `on_the_go_sessions`.
2. Confirm production already has or receives `on_the_go_captures.session_id`.
3. Confirm production already has or receives `on_the_go_tapes.stream_type`.
4. Confirm field-draft support tables exist or can be created: `on_the_go_field_drafts`, `on_the_go_field_sync_ops`, `on_the_go_upload_states`.
5. Verify production MySQL/MariaDB supports `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS` as used in `deploy/on_the_go_sessions_runtime.sql`.
6. If production rejects that syntax, assign Backend/Data or DB owner to produce an engine-compatible idempotent migration before upload.

Acceptance criteria:

- Migration is applied successfully in staging/production or proven already present.
- PHP upload does not precede required schema.
- Any SQL incompatibility is treated as a deploy blocker and reported before code changes are requested.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 after deployment package is selected and uploaded to staging/production
Context: Local product evidence passed, but production smoke is still separate from business-MVP product readiness. CLI PHP is unavailable in the current shell, so `scripts/local-smoke.php` could not be rerun here.
Request: run production/staging smoke against the selected deployment package.

Minimum smoke:

1. App loads and `current_user`/session check works.
2. On the Go Field Combat entry is visible on mobile.
3. Durable draft/recovery and proof retry path does not duplicate money rows.
4. Current-period export is reachable.
5. Closed final report list/detail/export/package opens for a newly finalized report.
6. Package proof download works for an authorized reviewer.
7. Historical/current separation remains true: old report `1000 / 600 / 400`, current period starts from carryover `400` plus current entries only.
8. Group messages send/list/unread remain group-scoped.
9. Business Desk proforma create/open/print does not mutate ledger formulas.

Acceptance criteria:

- Evidence records deployed file set, DB migration state, server URL, smoke user/group/report ids, and pass/fail.
- Any production-only schema error, missing route, package/proof access failure, duplicate money row, or current/historical export regression is P0.

## To Product Finance Architect / Project Director

Date: 2026-05-27
Priority: P1 unless upgraded to release P0
Context: Remaining backend limitations are known and currently classified outside the business-MVP product blocker set.
Request: keep or reclassify these limitations explicitly.

Decision points:

1. Legacy finalizations without `report_package` currently return `historical_package_missing`; accepted MVP path uses new package finalizations.
2. Package-wide downloadable export beyond browser print/PDF remains separate from existing short Excel/Google final-report tables.
3. First-class report-linked message schema is not present; package report-context messages are audit-derived and general group messages are marked unlinked.

Acceptance criteria:

- If any item becomes P0, issue a named Backend/Data implementation task before requesting code changes.

## To Frontend UX Engineer

Date: 2026-05-27
Priority: P0
Context: Backend now exposes the immutable `Закрытый групповой отчет` package source by `report_id`.
Request: wire the closed group report archive/open screen to `ledger_group_final_report_package`.

API contract:

- Action: `ledger_group_final_report_package`.
- Input: `report_id`.
- Output: `package_type=group_final_report`, `report`, `package`.
- Package sections: `group`, `finalization`, `summary`, `participants`, `captures`, `money_rows`, `proofs`, `accountable`, `messages`, `audit_refs`, `exports`.
- Proof download: use each proof's `download_url` from the package. It calls `ledger_group_final_report_proof_download` and is authorized by report-view permissions.

Acceptance criteria:

- Opening one closed report does not manually stitch group final report detail, card detail, file list, advances, messages, and journal endpoints.
- UI shows participant reports as distinct accepted participant reports inside the package.
- UI uses package proof URLs for reviewer proof access.
- UI labels general group messages as unlinked group discussion unless they come from `messages.report_context`.
- Current-period export remains wired to current-period endpoints.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0
Context: Backend package source is implemented for new finalizations and passed a local HTTP/API fixture.
Request: run the full business-MVP multi-participant package gate.

Minimum checks:

1. Create group common-pot income.
2. Participant A submits/includes cash Live Report with proof.
3. Participant B submits/includes card/noncash Live Report with proof.
4. Employee/accountable advance path includes accepted spend and open or returned remaining cash.
5. Finalize group report and record `report_id`.
6. Call `ledger_group_final_report_package` by `report_id`.
7. Verify package has summary, participant reports, captures, proofs, accountable state, messages/audit refs, and exports metadata.
8. Download at least one proof as authorized reviewer through package proof URL.
9. Add later current-period activity and verify the closed package does not mutate.
10. Verify old finalizations without package return `historical_package_missing`.

Acceptance criteria:

- Any missing participant report, missing proof access, wrong cash/card effect, wrong accountable responsibility, or package mutation after current activity is P0.
- Evidence should include `group_id`, `report_id`, participant tape ids, capture ids, proof ids, advance ids, package response summary, proof download status, and current-period immutability check.

## To Product Finance Architect / Project Director

Date: 2026-05-27
Priority: P1 unless selected as release P0
Context: Backend package source is implemented, but package-wide printable/export file and first-class report-linked messages are separate product decisions.
Request: decide whether these remain downstream or become part of the immediate business-MVP gate.

Decision points:

1. Is `ledger_group_final_report_package` plus existing final-report export enough for the next Frontend/QA step, or is a new package-wide print/export endpoint required now?
2. Are audit-derived report-context messages acceptable for MVP, or must `group_messages` gain explicit `report_id/tape_id/capture_id/advance_id` fields before gate?

Acceptance criteria:

- If package-wide export is P0, issue a backend task for `ledger_group_final_report_package_export`.
- If report-linked messages are P0, issue a schema/API task for message context links.

## To Project Director / Product Finance Architect

Date: 2026-05-26
Priority: P0
Context: backend trace found that the current system has a group final report snapshot/export, but not the full business-MVP closed archive package.
Request: decide the exact P0 product contract for a closed group report package.

Required decision points:

1. Whether the closed group report package must include expanded participant report snapshots or whether storing participant snapshots inside the group snapshot is enough.
2. Whether proof files must be directly listable/downloadable by authorized managers from the closed report package.
3. How accepted employee advances, open accountable cash left, returned/discrepancy state, and carryover responsibility must appear in the closed package.
4. Whether group messages must be linked to `report_id`, `tape_id`, `capture_id`, or `advance_id`, or whether a separate group thread is enough for MVP.
5. Whether managers, not only advanced/admin users, must see all archived employee participant reports.

Acceptance criteria:

- Product contract names the API/object that represents one closed group report package.
- Product contract states whether participant report/proof/message expansion is P0 or staged.
- No backend formula change is requested without Chief Auditor visibility.

## To Backend Implementation Queue

Date: 2026-05-26
Priority: P0 after Product/Director decision
Context: trace-only pass found missing backend package support for `participant reports -> consolidated group report -> export -> archive`.
Request: implement the closed group report archive/package backend contract after product decision.

Minimum implementation candidates:

1. Add `ledger_group_final_report_package_detail` or equivalent by `report_id`.
2. Return immutable group snapshot plus expanded `card_ids`: participant card metadata, captures, reportable state, file metadata/download URLs, owner/participant identity, cash/card summaries, and archived/submitted/included timestamps.
3. Add reviewer-scope proof access for files attached to participant reports included in a closed group report.
4. Add or embed immutable participant finalized report snapshots for included Live Report cards.
5. Include accepted advance ledger evidence and open accountable carryover/responsibility state in the package.
6. Add a unified archive list/open API so UI does not stitch historical group reports, card archive, file endpoints, advances, and journal export manually.

Acceptance criteria:

- A closed group report can be reopened by `report_id` with all numbers and evidence needed to explain the group common pot.
- New current-period activity cannot mutate the closed package.
- Card spending remains noncash and has zero physical-cash effect.
- Current open-period export remains unchanged.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0 after backend implementation
Context: business MVP needs proof that multiple participant reports consolidate into one archived group report without losing common pot, responsibility, cash/card split, and evidence.
Request: prepare a multi-participant API/browser fixture once backend package endpoint exists.

Minimum scenario:

1. Group receives common-pot cash income.
2. Participant A submits/includes a cash Live Report with proof.
3. Participant B submits/includes a card Live Report with proof.
4. One employee/accountable advance is issued, spent/reported, and accepted or left open by product decision.
5. Reviewer finalizes the group report.
6. Historical group export opens by `report_id`.
7. Archive/package opens the closed group report and all linked participant reports/proofs.
8. New current-period income/expense is added after finalization.
9. Current export remains current truth and closed package remains historical truth.

Acceptance criteria:

- Evidence includes `group_id`, `report_id`, participant card ids, proof ids, any advance ids, historical package response, historical export, and current export.
- Any missing linked proof, wrong cash/card effect, hidden participant report, or mutation of historical package is P0.

## To QA Release Engineer

Date: 2026-05-23
Priority: P0
Context: open-period finance correctness.
Request: create manual scenario covering `€1000 -> expenses €600 -> final report -> carryover €400`.
Acceptance criteria: old report remains exportable, new open period starts from carryover.

## To Product Finance Architect

Date: 2026-05-23
Priority: P1
Context: export rows and labels.
Request: provide final names for columns representing `was`, `movement`, `became`, `cash`, `card`, `custodian`.
Acceptance criteria: backend export keys map cleanly to user-facing report labels.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: backend trace found the intended open-period carryover/export path, but CLI smoke is blocked in this shell and the full carryover/export/archive release gate still lacks repeatable evidence.
Request: run a manual/API verification for `EUR 1000 income -> EUR 600 expense -> EUR 400 carryover` against a group account.

Checklist:

1. Baseline: record `HEAD`, `origin/main`, dirty status, and whether CLI PHP is available. If CLI PHP is available, run `php scripts/local-smoke.php http://127.0.0.1:18889`; otherwise record `environment-blocked` and `curl -I --max-time 3 http://127.0.0.1:18889`.
2. Create/use a group where the tester has `advanced` or `manager` permissions.
3. Create group cash income: `ledger_create` with `group_id`, `entry_type=income`, `money_type=cash`, `amount=1000`.
4. Create a non-advance cash Live Report card in the same group, save one `cash_out` total of `600`, submit/include it into FinDesk.
5. Before finalization, verify group export/balance/report source shows old report truth: income `1000`, cash expense `600`, cash left `400`.
6. Call `ledger_group_finalize_report` with the same `group_id`; verify `finalized >= 1` and the finalized card id is returned.
7. Call `ledger_group_open_received_funds`; verify `finalized_at` is non-empty, `carryovers` contains `amount=400`, and `entries` does not contain the old `1000` income row as current income.
8. Call `ledger_group_google_sheet` and download `ledger_group_excel`; verify the current export source contains `Переходящий остаток` `400` and does not treat the old `1000` income as new current-period income.
9. Call `on_the_go_card_list` with `group_id` and `archived_only=1` as an advanced/admin user; verify the finalized card is listed and `on_the_go_card_detail` opens its original captures/proofs.
10. Repeat archive listing as a manager/base employee if possible; record whether employee-linked archived cards are visible or hidden by role.
11. Create a card-stream Live Report with `stream_type=card`, attempt `cash_received=999`, save `-600`; verify card detail summary has `cash_left=0`, `cash_delta=0`, `card_out=600`, and group physical cash does not decrease.
12. Create an employee advance, save employee cash and card spending, submit/accept it; verify accepted cash/card expenses appear through `ledger_entries`, while open accountable cash is removed or rolled over as designed.
13. Call group financial endpoints once with `group_id` and once without `group_id`; record that missing `group_id` returns personal/assigned scope and must not be used as group truth.

Acceptance criteria:

- QA evidence includes exact API responses or screenshots for carryover `400`, export source, archive visibility, card zero cash delta, employee-linked report behavior, and group scope.
- Any mismatch is filed as P0 if old `1000` appears as new current income in the open-period/export path, or if card spend changes physical cash.
- Any inability to prove old finalized report export is recorded separately from current open-period export.

## To Product Finance Architect / Project Director

Date: 2026-05-26
Priority: P0
Context: the current export endpoint switches to open-period export after a finalization exists. Raw historical evidence is preserved, but there is no first-class finalized-report snapshot/export endpoint.
Request: decide whether release requires a separate historical finalized report/export source before RC, or whether archive + audit evidence is enough for this release.

Acceptance criteria:

- Decision explicitly names two user actions if both are required: current open-period export and historical finalized-report export.
- If historical export is required, create a backend implementation task for a snapshot table or explicit `finalized_report_id` export mode.

## To Backend Implementation Queue

Date: 2026-05-26
Priority: P0 if historical export is release-required; otherwise P1
Context: trace-only pass found backend tasks that should not be patched silently during the audit.
Request:

1. Add focused smoke coverage for finalization/carryover/export/archive once CLI PHP is available.
2. Add or design a historical finalized report source if Product/Director require old report export after the main export switches to open period.
3. Decide whether employee carryover must be frozen from `audit_log.details.carryover_employee_cash_left` instead of recomputed from current open advances.
4. Decide whether `on_the_go_report_list` should be updated or deprecated, because it currently reads legacy `on_the_go_report_submitted` audit rows and misses newer card workflow actions.
5. Decide whether archive listing for managers should include group employee archived cards, or remain advanced/admin-only.

Acceptance criteria:

- No financial formula change lands without Product Finance Architect and Chief Auditor visibility.
- New smoke checks cover both cash and card streams and prove card spend has zero physical-cash effect.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: backend patch now stores immutable final-report snapshot in `audit_log.details.report_snapshot` for new finalizations and exposes historical report list/detail/export API.
Request: rerun the release-blocker scenario through UI/API after pulling this patch.

Verification checklist:

1. Run baseline smoke if CLI PHP is available; otherwise record CLI PHP as environment-blocked and confirm HTTP server `200 OK`.
2. Create a new group and run `EUR 1000 income -> EUR 600 cash Live Report expense -> include -> finalize`.
3. Confirm `ledger_group_finalize_report` returns `report_id`.
4. Confirm `ledger_group_final_report_list` shows that `report_id` with `snapshot_available=true`.
5. Confirm `ledger_group_final_report_detail` for that `report_id` returns immutable totals: `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`.
6. Confirm `ledger_group_final_report_google_sheet` or `ledger_group_final_report_excel` still exports the old final report rows: `1000 / 600 / 400`.
7. Confirm normal current export still starts from `400` carryover and does not show the old `1000` as current-period income.
8. Confirm an old finalization without `report_snapshot`, if present in test data, returns `historical_snapshot_missing` for historical detail/export.

Acceptance criteria:

- QA evidence includes the `report_id`, historical export evidence, and current open-period export evidence.
- Any case where historical export reconstructs mutable data instead of reading snapshot is a blocker.
- Any case where current export regresses and shows old income as current-period income is a blocker.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P1 after backend QA passes
Context: backend now has separate APIs for current export and historical final report export, but no UX wiring was changed in this task.
Request: add or verify separate UI actions for `Экспорт текущего периода` and `Экспорт финального отчета` without reusing the current export button for historical reports.

Acceptance criteria:

- Current export calls existing `ledger_group_excel` / `ledger_group_google_sheet`.
- Historical final report export calls `ledger_group_final_report_excel` / `ledger_group_final_report_google_sheet` with explicit `report_id`.
- Closed reports list/detail calls `ledger_group_final_report_list` and `ledger_group_final_report_detail`.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0 regression recheck
Context: current open-period regression was fixed after QA evidence showed current income could be overwritten by the following Live Report loop.
Request: rerun the combined scenario against the patched backend.

Checklist:

1. Create/finalize historical period: `EUR 1000 income -> EUR 600 cash Live Report expense -> finalize`.
2. Confirm selected historical final report/export still shows immutable `1000 / 600 / 400`.
3. Create current-period income `EUR 50`.
4. Create current-period Live Report expense `EUR 25`.
5. Confirm `ledger_group_open_received_funds.entries` contains the ledger income row for `50`, not the Live Report tape id.
6. Confirm current export contains carryover `400`, current income `50`, current Live Report expense `25`.
7. Confirm current export does not contain old finalized income `1000` as current income.

Acceptance criteria:

- Any recurrence of `entries: [{"id": <live_tape_id>}]` instead of the current ledger income row is a blocker.
- Any regression in historical final report detail/export is a blocker.

## To Backend Implementation Queue

Date: 2026-05-26
Priority: P1 hardening
Context: synthetic testing found a separate timestamp-boundary edge while reproducing the current export scenario.
Request: decide whether finalization should store a deterministic ledger cutoff identity, such as max ledger id / timestamp pair, for same-second current-period rows.

Acceptance criteria:

- Same-second rows created immediately after finalization are either explicitly supported or explicitly documented as outside the current backend contract.
- No change should include old finalized `1000` income in current open-period export.

## To Backend Implementation Queue

Date: 2026-05-26
Priority: P0
Context: Field Combat Mode cannot claim no-data-loss while typed money facts and proof upload retry state exist only in browser state before successful save/upload.
Request: add a durable Field Combat draft/sync model.

Required backend capabilities:

1. Create or reuse an open field session identity before the user submits/closes.
2. Persist raw draft note text, parsed rows, selected group, participant/user, stream, and current tape/session identity before final save.
3. Add client-generated operation ids for row sync to make retry idempotent beyond the current 4-second duplicate guard.
4. Add durable proof upload state before file transfer completes: pending, uploaded, failed, retry-needed, last error, retry count, and client upload id.
5. Expose recovery endpoints that return saved draft text, parsed rows, proof states, and recalculated totals after refresh/module switch/return.
6. Keep submit/include/finalization as deliberate separate actions.

Acceptance criteria:

- Refresh before final submit does not lose typed money facts after the first autosave.
- Failed proof upload remains visible after refresh and can be retried.
- Backend can distinguish saved, pending, failed, retry-needed, and submitted/closed states.
- No financial formula change lands without Product Finance Architect and Chief Auditor visibility.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: backend can recover saved Field Combat rows, but current UI typing/file selection is not durable before successful API save/upload.
Request: wire Field Combat UI to durable autosave/sync states once backend endpoints exist.

Checklist:

1. Autosave typed rows and selected stream/group/session identity without waiting for final submit.
2. Preserve selected proof/photo intent with a visible pending state before upload succeeds.
3. Show saved, pending, failed, and retry in the field capture surface.
4. Do not clear the selected proof or show only `Сохранено` if upload failed.
5. Keep close/submit/include as deliberate actions, separate from autosave.

Acceptance criteria:

- Type row -> refresh/module switch/return -> row is restored.
- Attach proof -> upload interrupted -> retry state is visible after refresh.
- A user can tell whether money fact and proof are saved or still need action.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: Backend trace found partial pass only after successful save/upload; full Field Combat no-data-loss remains blocked.
Request: test both saved-state recovery and pre-save/pre-upload loss boundaries.

Minimum checks:

1. Save `-25 note` through `on_the_go_signed_sync`; refresh/reopen; verify row remains in `on_the_go_card_detail`.
2. Upload proof after saved row; refresh/reopen; verify `on_the_go_file_list` and `files_count` preserve proof.
3. Type a row but interrupt before successful save; verify current build loses it or lacks durable recovery state.
4. Attach a proof and force upload failure/interruption; verify current build lacks durable pending/failed/retry proof state after refresh.
5. Save cash and card streams; verify card stream has `cash_left=0` and card spend only in card totals.
6. Verify no card becomes submitted/included/final without explicit submit/include/finalize action.

Acceptance criteria:

- Any lost typed money fact before deliberate submit/close is P0 until durable autosave exists.
- Any proof loss without persistent failed/pending/retry state is P0.
- QA evidence should reference the Backend finding section `Field Combat Mode Backend Persistence Trace 2026-05-26`.

## To Chief Auditor

Date: 2026-05-26
Priority: P0
Context: Field Combat Mode is MVP foundation, but Backend/Data found no durable pre-save draft and no durable failed/pending proof upload state.
Request: keep Business MVP / release gate blocked for Field Combat no-data-loss until Backend + Frontend + QA prove durable autosave and proof retry.

Acceptance criteria:

- No release-ready claim for Field Combat Mode while typed facts or proof retry state can disappear on refresh, phone kill, or failed upload.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: Backend now exposes durable Field Combat draft/recovery/proof-state APIs, but the UI must call them early for no-data-loss behavior.
Request: wire Field Combat capture to backend autosave and proof-state endpoints.

Backend endpoints:

1. Call `on_the_go_field_draft_save` as autosave while the user types or changes stream/group/cash base.
2. Keep and reuse a stable `client_draft_id` per open field session.
3. Send `client_operation_id` for final row sync via `on_the_go_signed_sync`.
4. Before uploading proof, call `on_the_go_proof_state_begin` with `client_upload_id`.
5. If upload fails/interruption is known, call `on_the_go_proof_state_fail`.
6. On refresh/module switch/return, call `on_the_go_field_recover` by `client_draft_id` or latest active context.

Acceptance criteria:

- Typed rows survive refresh after first autosave.
- Proof upload failure remains visible after refresh as `failed` or `retry_needed`.
- UI never shows proof as fully saved unless backend state is `uploaded`.
- Submit/include/finalize stay deliberate actions separate from autosave.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: Backend patch implemented durable Field Combat draft/sync/proof-state support.
Request: run API and UI verification after Frontend wiring.

API checks:

1. `on_the_go_field_draft_save` with `client_draft_id`, group, stream, cash base, and notes `-25`.
2. `on_the_go_field_recover` returns the same raw notes, parsed rows, tape/session identity, and totals `cash_out=25`, `cash_left=75` when cash base is `100`.
3. `on_the_go_proof_state_begin` returns `pending`.
4. `on_the_go_proof_state_fail` changes state to `retry_needed` and increments `retry_count`.
5. Recovery still returns the `retry_needed` proof state after refresh.
6. `on_the_go_signed_sync` with the same `client_operation_id` twice returns the saved response with `idempotent=true` on replay.
7. Upload with `client_upload_id` changes proof state to `uploaded`.
8. `on_the_go_card_detail` shows saved row/proof and card remains `draft` before explicit submit/include.

Backend fixture already passed:

- `group_id=202`
- `draft_id=1`
- `tape_id=202`
- `session_id=142`
- `capture_id=160`
- `client_operation_id=op-20260526203628`
- uploaded proof: `upload-20260526203628`
- retry-needed proof: `upload-fail-20260526203628`

Acceptance criteria:

- Any lost typed fact after successful autosave is P0.
- Any proof upload failure without durable `failed` or `retry_needed` state is P0.
- Any autosave that submits/includes/finalizes silently is P0.

## To Chief Auditor

Date: 2026-05-26
Priority: P0 gate follow-up
Context: Backend side of durable Field Combat draft/sync/proof state is implemented, but release readiness still depends on Frontend wiring and QA evidence.
Request: keep the Field Combat release gate blocked until QA proves the UI uses the new backend endpoints and survives refresh/module switch/proof failure.

Acceptance criteria:

- Backend evidence alone is not enough; release gate needs UI/API proof for the real field flow.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 recheck
Context: production base employee rights QA found `message_unread` HTTP `500` for a default base employee. Backend fixed the SQL alias collision in `app/messages.php` and deployed only that file.

Request: rerun the production default base employee rights slice.

Director smoke evidence:

- local group id: `225`
- production group id: `19`
- production base employee user id: `57`
- `message_unread`: `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

Acceptance criteria:

- base employee `message_unread` returns HTTP `200`, `ok=true`, `unread_count=0`;
- base employee still cannot list/send group messages;
- base employee still cannot access group reports, final reports, exports, money management, role management, or other participants' money data;
- participant-control PASS from the same QA recheck remains accepted unless a regression is observed.

## To QA Release Engineer / CEO Browser Check

Date: 2026-05-28
Priority: P0 live verification
Context: stale legacy personal report from `03.05.2026` was archived directly in production data and personal no-group self-return logic was hardened in `app/on_the_go.php`.

Request: verify the live UI after refresh.

Acceptance criteria:

- the old `03.05` legacy report is no longer visible in the working submitted/live report surface;
- it no longer affects current report totals;
- personal no-group locked reports do not offer a meaningless “request correction” path to the same owner;
- group participant submitted reports still require manager/admin return and do not become self-editable by base employees.

Evidence pointer: `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`.

---

## To Backend Implementation Owner

Date: 2026-05-28
Priority: P1 / required before Receipt Scanner release gate
Status: waiting for backend implementation
Context: Frontend local prototype can generate a cleaned one-page PDF from a scanned receipt and pass it through the current single-file Live Report proof upload path.

Request: implement scanner proof storage without breaking existing proof uploads.

Acceptance criteria:

- Original source file is stored.
- Cleaned PDF is stored as a derived artifact.
- Processing metadata is stored: crop corners, cleanup level, monochrome flag, transform version, source hash, PDF hash.
- Artifacts are linked to draft/capture/tape/report package/final report/archive.
- Existing `on_the_go_upload_file` consumers remain backward-compatible.
- Retry/pending/failed proof states cover both original and derived PDF.
