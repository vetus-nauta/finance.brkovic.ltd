const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { handleApi, db, closeDb } = require('../server/findesk-v2-atlas-read-server');

const ROOT = path.resolve(__dirname, '..');
const WORKSPACE_ID = process.env.FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID || '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
const ACCOUNTABLE_WORKSPACE_ID = process.env.FINDESK_V2_ACCOUNTABLE_WORKSPACE_ID || '43a20c32-a9e6-4812-a556-6f1cb995147d';
const EXPECT_BLOCKED = process.argv.includes('--expect-blocked');
const FTP_PRODUCTION_CUTOVER_AUTHORIZED = process.argv.includes('--ftp-authorized')
  || process.env.FINDESK_V2_FTP_PRODUCTION_CUTOVER_AUTHORIZED === '1';
const BROWSER_SMOKE_RESULT = path.join(ROOT, 'test-results', 'v2-atlas-browser-smoke', 'result.json');
const CLAUDIA_Z_ENTRY_COUNT = 663;
const CLAUDIA_Z_VISIBLE_FRAGMENT_COUNT = 3;
const CLAUDIA_Z_OTHER_REVIEW_COUNT = 1;
const CLAUDIA_Z_OTHER_REVIEW_TOTAL = '100.00';

function browserRehearsalEvidence() {
  try {
    const result = JSON.parse(fs.readFileSync(BROWSER_SMOKE_RESULT, 'utf8'));
    const desktopOk = result.desktop_overflow
      && Number(result.desktop_overflow.width) <= Number(result.desktop_overflow.viewport) + 2;
    const mobileOk = result.mobile_overflow
      && Number(result.mobile_overflow.width) <= Number(result.mobile_overflow.viewport) + 2;
    return {
      available: true,
      ok: result.ok === true
        && result.workspace_id === WORKSPACE_ID
        && result.disposable_entry_cleaned === true
        && result.ui_delete_covered === true
        && desktopOk
        && mobileOk,
      checked_at: result.checked_at || null,
      ui_delete_covered: result.ui_delete_covered === true,
      desktop_overflow: result.desktop_overflow || null,
      mobile_overflow: result.mobile_overflow || null,
      result_path: BROWSER_SMOKE_RESULT,
    };
  } catch (error) {
    return {
      available: false,
      ok: false,
      error: error.message,
      result_path: BROWSER_SMOKE_RESULT,
    };
  }
}

const phpRouteSurface = [
  { method: 'GET', route: '/api/workspaces', area: 'hall' },
  { method: 'POST', route: '/api/workspaces', area: 'hall' },
  { method: 'GET', route: '/api/workspaces/:workspaceId', area: 'workspace' },
  { method: 'PATCH', route: '/api/workspaces/:workspaceId', area: 'workspace' },
  { method: 'DELETE', route: '/api/workspaces/:workspaceId', area: 'workspace' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/flows', area: 'operational' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/flows', area: 'operational' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/invites', area: 'hall' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/invites', area: 'hall' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/invites/:inviteId/revoke', area: 'hall' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/employee-mode', area: 'employee' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/accountable-dashboard', area: 'accountable' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/accountable-offers', area: 'accountable' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/accountable-offers', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-offers/:offerId/accept', area: 'accountable' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/accountable-reports', area: 'accountable' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/accountable-reports', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-reports/:reportId/submit', area: 'accountable' },
  { method: 'GET', route: '/api/accountable-reports/:reportId', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-reports/:reportId/review-preview', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-reports/:reportId/accept', area: 'accountable' },
  { method: 'GET', route: '/api/accountable-reports/:reportId/materialization', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-reports/:reportId/materialization-preview', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-reports/:reportId/materialize', area: 'accountable' },
  { method: 'POST', route: '/api/accountable-settlements/:settlementId/cash-resolve', area: 'accountable' },
  { method: 'POST', route: '/api/workspace-invites/preview', area: 'hall' },
  { method: 'POST', route: '/api/workspace-invites/accept', area: 'hall' },
  { method: 'GET', route: '/api/workspace-invites/:token', area: 'hall' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/summary', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/monthly', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/layer1-summary', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/layer1-source-entries', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/layer1-snapshots', area: 'summary' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/layer1-snapshots', area: 'summary' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/batch-preview', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/batches', area: 'summary' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/batches', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/batches/:batchId', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-fragments', area: 'reporting' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/operational-fragments', area: 'reporting' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/operational-fragments/preview', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId', area: 'reporting' },
  { method: 'PATCH', route: '/api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots', area: 'reporting' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots/:snapshotId', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-packages', area: 'reporting' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/reports/operational-packages', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/operational-packages/:packageId', area: 'reporting' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/category-matrix', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/reports/other-review', area: 'summary' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/dictionary-review-queue', area: 'training' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/raw-history', area: 'training' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/raw-history/convert', area: 'training' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/dictionary-training-decisions', area: 'training' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/dictionary-training-decisions', area: 'training' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/assistant-settings', area: 'training' },
  { method: 'PATCH', route: '/api/workspaces/:workspaceId/assistant-settings', area: 'training' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/dictionary-training-internet-reference', area: 'training' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups', area: 'training' },
  { method: 'PATCH', route: '/api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId', area: 'training' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/imports/excel', area: 'import' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/imports/:importId/review', area: 'import' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/imports/:importId/accept', area: 'import' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/other-expenses', area: 'operational' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/entries', area: 'operational' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/entries', area: 'operational' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/parse-preview', area: 'operational' },
  { method: 'POST', route: '/api/parse-entry-preview', area: 'operational' },
  { method: 'PATCH', route: '/api/entries/:entryId', area: 'operational' },
  { method: 'DELETE', route: '/api/entries/:entryId', area: 'operational' },
  { method: 'GET', route: '/api/entries/:entryId/attachments', area: 'attachments' },
  { method: 'POST', route: '/api/entries/:entryId/attachments', area: 'attachments' },
  { method: 'DELETE', route: '/api/attachments/:attachmentId', area: 'attachments' },
  { method: 'PATCH', route: '/api/entries/:entryId/category', area: 'operational' },
  { method: 'POST', route: '/api/entries/:entryId/category/closed-month-decision', area: 'operational' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/months/:year/:month/close', area: 'months' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/months/:year/:month/reopen', area: 'months' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/months/:year/:month/correction', area: 'months' },
  { method: 'GET', route: '/api/workspaces/:workspaceId/categories', area: 'operational' },
  { method: 'POST', route: '/api/workspaces/:workspaceId/category-rules', area: 'training' },
];

const atlasReadRoutes = new Set([
  'GET /api/workspaces',
  'GET /api/workspaces/:workspaceId',
  'GET /api/workspaces/:workspaceId/flows',
  'GET /api/workspaces/:workspaceId/invites',
  'GET /api/workspaces/:workspaceId/employee-mode',
  'GET /api/workspaces/:workspaceId/accountable-dashboard',
  'GET /api/workspaces/:workspaceId/accountable-offers',
  'GET /api/workspaces/:workspaceId/accountable-reports',
  'GET /api/accountable-reports/:reportId',
  'GET /api/accountable-reports/:reportId/materialization',
  'GET /api/workspace-invites/:token',
  'GET /api/workspaces/:workspaceId/categories',
  'GET /api/workspaces/:workspaceId/entries',
  'GET /api/workspaces/:workspaceId/summary',
  'GET /api/workspaces/:workspaceId/reports/monthly',
  'GET /api/workspaces/:workspaceId/reports/layer1-summary',
  'GET /api/workspaces/:workspaceId/reports/layer1-source-entries',
  'GET /api/workspaces/:workspaceId/reports/layer1-snapshots',
  'GET /api/workspaces/:workspaceId/other-expenses',
  'GET /api/workspaces/:workspaceId/reports/batches',
  'GET /api/workspaces/:workspaceId/reports/batches/:batchId',
  'GET /api/workspaces/:workspaceId/reports/operational-fragments',
  'GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId',
  'GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots',
  'GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots/:snapshotId',
  'GET /api/workspaces/:workspaceId/reports/operational-packages',
  'GET /api/workspaces/:workspaceId/reports/operational-packages/:packageId',
  'GET /api/workspaces/:workspaceId/reports/category-matrix',
  'GET /api/workspaces/:workspaceId/reports/other-review',
  'GET /api/workspaces/:workspaceId/dictionary-review-queue',
  'GET /api/workspaces/:workspaceId/raw-history',
  'GET /api/workspaces/:workspaceId/dictionary-training-decisions',
  'GET /api/workspaces/:workspaceId/assistant-settings',
  'GET /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups',
  'GET /api/workspaces/:workspaceId/imports/:importId/review',
  'GET /api/entries/:entryId/attachments',
]);

const atlasWriteRoutes = new Set([
  'POST /api/workspaces',
  'PATCH /api/workspaces/:workspaceId',
  'DELETE /api/workspaces/:workspaceId',
  'POST /api/workspaces/:workspaceId/flows',
  'POST /api/workspaces/:workspaceId/category-rules',
  'POST /api/workspaces/:workspaceId/invites',
  'POST /api/workspaces/:workspaceId/invites/:inviteId/revoke',
  'POST /api/workspace-invites/preview',
  'POST /api/workspace-invites/accept',
  'POST /api/workspaces/:workspaceId/accountable-offers',
  'POST /api/accountable-offers/:offerId/accept',
  'POST /api/workspaces/:workspaceId/accountable-reports',
  'POST /api/accountable-reports/:reportId/submit',
  'POST /api/accountable-reports/:reportId/review-preview',
  'POST /api/accountable-reports/:reportId/accept',
  'POST /api/accountable-reports/:reportId/materialization-preview',
  'POST /api/accountable-reports/:reportId/materialize',
  'POST /api/accountable-settlements/:settlementId/cash-resolve',
  'PATCH /api/workspaces/:workspaceId/assistant-settings',
  'POST /api/workspaces/:workspaceId/entries',
  'POST /api/workspaces/:workspaceId/parse-preview',
  'POST /api/parse-entry-preview',
  'PATCH /api/entries/:entryId',
  'PATCH /api/entries/:entryId/category',
  'POST /api/entries/:entryId/category/closed-month-decision',
  'DELETE /api/entries/:entryId',
  'POST /api/workspaces/:workspaceId/months/:year/:month/close',
  'POST /api/workspaces/:workspaceId/months/:year/:month/reopen',
  'POST /api/workspaces/:workspaceId/months/:year/:month/correction',
  'POST /api/workspaces/:workspaceId/reports/layer1-snapshots',
  'POST /api/workspaces/:workspaceId/reports/batch-preview',
  'POST /api/workspaces/:workspaceId/reports/batches',
  'POST /api/workspaces/:workspaceId/reports/operational-fragments',
  'POST /api/workspaces/:workspaceId/reports/operational-fragments/preview',
  'PATCH /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId',
  'POST /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots',
  'POST /api/workspaces/:workspaceId/reports/operational-packages',
  'POST /api/workspaces/:workspaceId/raw-history/convert',
  'POST /api/workspaces/:workspaceId/dictionary-training-decisions',
  'POST /api/workspaces/:workspaceId/dictionary-training-internet-reference',
  'PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId',
  'POST /api/workspaces/:workspaceId/imports/excel',
  'POST /api/workspaces/:workspaceId/imports/:importId/accept',
  'POST /api/entries/:entryId/attachments',
  'DELETE /api/attachments/:attachmentId',
]);

async function main() {
  const gatewaySource = fs.readFileSync(path.join(ROOT, 'public', 'v2-api.php'), 'utf8');
  const shadowGatewayAvailable = gatewaySource.includes('FINDESK_V2_RUNTIME_MODE')
    && gatewaySource.includes('atlas_shadow')
    && gatewaySource.includes('FINDESK_V2_ATLAS_READ_BASE_URL');
  const atlasProxyAvailable = gatewaySource.includes('findesk_v2_proxy_atlas_runtime')
    && gatewaySource.includes('atlas_read')
    && gatewaySource.includes('atlas_write')
    && gatewaySource.includes('atlas_proxy_local_only');
  assert.strictEqual(shadowGatewayAvailable, true, 'Atlas shadow gateway hook missing');
  assert.strictEqual(atlasProxyAvailable, true, 'Atlas controlled proxy hook missing');

  const workspaces = await handleApi('GET', '/api/workspaces', {});
  assert.strictEqual(workspaces.ok, true);
  assert(workspaces.workspaces.some((workspace) => workspace.id === WORKSPACE_ID), 'Claudia Z workspace missing from Atlas runtime');

  const entries = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/entries`, {});
  const invites = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/invites`, {});
  await assert.rejects(
    () => handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/employee-mode`, {}),
    /employee_mode_not_required/,
    'Admin user must not receive fake employee mode'
  );
  const dashboard = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-dashboard`, {});
  const offers = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-offers`, {});
  const accountableReports = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-reports`, { status: 'hall_open' });
  const accountableReport = await handleApi('GET', `/api/accountable-reports/${accountableReports.reports[0].id}`, {});
  const materialization = await handleApi('GET', `/api/accountable-reports/${accountableReports.reports[0].id}/materialization`, {});
  await assert.rejects(
    () => handleApi('GET', '/api/workspace-invites/000000000000000000000000000000000000000000000000', {}),
    /invite_not_found/,
    'Invite token route must return domain 404 for unknown token'
  );
  const summary = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/summary`, {});
  const month = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/monthly`, { year: '2026', month: '8' });
  const fragments = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments`, {});
  const layer1 = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/layer1-summary`, { year: '2026', month: '8' });
  const snapshots = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/layer1-snapshots`, { year: '2026', month: '8' });
  const packages = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages`, { limit: '50' });
  const batches = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/batches`, {});
  const batch = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/batches/${batches.reports[0].id}`, {});
  const htmlSnapshots = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments/${fragments.fragments[0].id}/html-snapshots`, {});
  const htmlSnapshot = await handleApi(
    'GET',
    `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments/${fragments.fragments[0].id}/html-snapshots/${htmlSnapshots.snapshots[0].id}`,
    {}
  );
  const categoryMatrix = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/category-matrix`, { year: '2026' });
  const otherReview = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/other-review`, {});
  const dictionaryQueue = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-review-queue`, { limit: '20', examples: '2' });
  const rawHistory = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/raw-history`, { sources: '5', samples: '2' });
  const trainingDecisions = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-training-decisions`, {});
  const assistantSettings = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/assistant-settings`, {});
  const internetLookups = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-training-internet-reference/lookups`, { limit: '5' });
  const database = await db();
  const importReview = await handleApi('GET', `/api/workspaces/${rawHistory.history.workspace_id}/imports/${rawHistory.history.sources[0].id}/review`, {});
  assert.strictEqual(entries.entries.length, CLAUDIA_Z_ENTRY_COUNT, 'Claudia Z entry count mismatch');
  assert.strictEqual(invites.invites.length, 3, 'Accountable fixture invite count mismatch');
  assert.strictEqual(Object.hasOwn(invites.invites[0], 'token'), false, 'Invite list leaked token');
  assert.strictEqual(Object.hasOwn(invites.invites[0], 'token_hash'), false, 'Invite list leaked token hash');
  assert.strictEqual(dashboard.dashboard.summary.offer_count, 3, 'Accountable dashboard offer count mismatch');
  assert.strictEqual(dashboard.dashboard.summary.report_count, 3, 'Accountable dashboard report count mismatch');
  assert.strictEqual(dashboard.dashboard.employees.length, 3, 'Accountable dashboard employee count mismatch');
  assert.strictEqual(offers.offers.length, 3, 'Accountable offer count mismatch');
  assert.strictEqual(accountableReports.reports.length, 3, 'Accountable hall_open report count mismatch');
  assert.strictEqual(accountableReport.report.rows.length, 1, 'Accountable report detail rows mismatch');
  assert.strictEqual(Boolean(accountableReport.report.settlement), true, 'Accountable report detail settlement missing');
  assert.strictEqual(materialization.materialization.entry_count, 1, 'Accountable materialization link count mismatch');
  assert.strictEqual(materialization.materialization.policy, 'cash_effect_none_category_projection', 'Accountable materialization policy mismatch');
  assert.strictEqual(Number(summary.summary.cash_now).toFixed(2), '3893.00', 'Claudia Z cash mismatch');
  assert.strictEqual(Number(month.report.ending_cash).toFixed(2), '3893.00', 'Claudia Z August cash mismatch');
  assert.strictEqual(fragments.fragments.length, CLAUDIA_Z_VISIBLE_FRAGMENT_COUNT, 'Operational report fragment count mismatch');
  assert.strictEqual(batches.reports.length, fragments.fragments.length, 'Report batches alias mismatch');
  assert.strictEqual(batch.report.id, batches.reports[0].id, 'Report batch detail mismatch');
  assert.strictEqual(htmlSnapshots.snapshots.length >= 1, true, 'HTML snapshot fixture missing');
  assert.strictEqual(Object.hasOwn(htmlSnapshots.snapshots[0], 'html_content'), false, 'HTML snapshot list leaked html_content');
  assert.strictEqual(typeof htmlSnapshot.snapshot.html_content, 'string', 'HTML snapshot detail missing html_content');
  assert.strictEqual(Number(layer1.report.totals.ending_cash).toFixed(2), '3893.00', 'Layer1 ending cash mismatch');
  assert(Array.isArray(snapshots.snapshots), 'Layer1 snapshots response missing');
  assert(Array.isArray(packages.packages), 'Operational packages response missing');
  assert.strictEqual(categoryMatrix.matrix.rows.length >= 20, true, 'Category matrix rows missing');
  assert.strictEqual(otherReview.report.count, CLAUDIA_Z_OTHER_REVIEW_COUNT, 'Other review report count mismatch');
  assert.strictEqual(Number(otherReview.report.total).toFixed(2), CLAUDIA_Z_OTHER_REVIEW_TOTAL, 'Other review report total mismatch');
  assert.strictEqual(dictionaryQueue.queue.rows_total, 3338, 'Dictionary review queue rows mismatch');
  assert.strictEqual(dictionaryQueue.queue.rows_with_money, 2508, 'Dictionary review queue money coverage changed');
  assert.strictEqual(dictionaryQueue.queue.groups.length > 0, true, 'Dictionary review queue groups missing');
  assert.strictEqual(rawHistory.history.sources_total, 57, 'Raw history sources mismatch');
  assert.strictEqual(rawHistory.history.rows_total, 3338, 'Raw history rows mismatch');
  assert.strictEqual(rawHistory.history.sources.length, 5, 'Raw history source limit ignored');
  assert.strictEqual(trainingDecisions.decisions.length, 111, 'Dictionary training decisions mismatch');
  assert.strictEqual(assistantSettings.settings.provider_key, 'stub', 'Assistant settings default/provider mismatch');
  assert.strictEqual(Array.isArray(internetLookups.lookups), true, 'Internet reference lookups missing');
  assert.strictEqual(Object.hasOwn(internetLookups, 'uri'), false, 'Internet lookup response leaked URI-like wrapper');
  assert.strictEqual(importReview.review.import_id, rawHistory.history.sources[0].id, 'Import review id mismatch');
  assert.strictEqual(importReview.review.rows_scanned, rawHistory.history.sources[0].row_count, 'Import review row count mismatch');
  assert.strictEqual(importReview.review.row_traces.length > 0, true, 'Import review row traces missing');

  let packageDetailPositiveFixture = false;
  if (packages.packages.length > 0) {
    const packageDetail = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages/${packages.packages[0].id}`, {});
    assert.strictEqual(packageDetail.package.id, packages.packages[0].id, 'Operational package detail mismatch');
    assert.strictEqual(Array.isArray(packageDetail.package.items), true, 'Operational package detail items missing');
    assert.strictEqual(Array.isArray(packageDetail.package.fragments), true, 'Operational package detail fragments missing');
    assert.strictEqual(Array.isArray(packageDetail.package.versions), true, 'Operational package detail versions missing');
    packageDetailPositiveFixture = true;
  } else {
    let packageDetail404Ok = false;
    try {
      await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages/00000000-0000-4000-8000-000000000000`, {});
    } catch (error) {
      packageDetail404Ok = error.message === 'report_package_not_found';
    }
    assert.strictEqual(packageDetail404Ok, true, 'Operational package detail route must return domain 404');
  }

  let writeGuardOk = false;
  try {
    await handleApi('POST', `/api/workspaces/${WORKSPACE_ID}/not-a-real-write-route`, {});
  } catch (error) {
    writeGuardOk = error.message === 'atlas_write_route_not_supported' || error.message === 'route_not_found';
  }
  assert.strictEqual(writeGuardOk, true, 'Atlas read server write guard failed');

  const readRoutes = phpRouteSurface.filter((route) => route.method === 'GET');
  const writeRoutes = phpRouteSurface.filter((route) => route.method !== 'GET');
  const unsupportedReads = readRoutes.filter((route) => !atlasReadRoutes.has(`${route.method} ${route.route}`));
  const unsupportedWrites = writeRoutes.filter((route) => !atlasWriteRoutes.has(`${route.method} ${route.route}`));
  const areas = {};
  for (const route of phpRouteSurface) {
    areas[route.area] = areas[route.area] || { total: 0, read: 0, write: 0 };
    areas[route.area].total += 1;
    if (route.method === 'GET') areas[route.area].read += 1;
    else areas[route.area].write += 1;
  }

  const browserRehearsal = browserRehearsalEvidence();
  const blockers = [
    ...(atlasProxyAvailable ? [] : ['atlas_runtime_proxy_not_available']),
    ...(unsupportedWrites.length > 0 ? ['atlas_write_repository_incomplete'] : []),
    ...(unsupportedReads.length > 0 ? ['full_get_route_parity_not_implemented'] : []),
    ...(browserRehearsal.ok ? [] : ['browser_cutover_rehearsal_not_completed']),
    ...(FTP_PRODUCTION_CUTOVER_AUTHORIZED ? [] : ['ftp_production_cutover_not_authorized']),
  ];

  const result = {
    ok: true,
    cutover_allowed: blockers.length === 0,
    checked_at: new Date().toISOString(),
    workspace_id: WORKSPACE_ID,
    atlas_runtime_smoke: {
      workspaces: workspaces.workspaces.length,
      entries: entries.entries.length,
      accountable_workspace_id: ACCOUNTABLE_WORKSPACE_ID,
      accountable_invites: invites.invites.length,
      accountable_offers: offers.offers.length,
      accountable_reports: accountableReports.reports.length,
      accountable_materialization_links: materialization.materialization.entry_count,
      invite_token_positive_fixture: false,
      cash_now: summary.summary.cash_now,
      august_ending_cash: month.report.ending_cash,
      active_fragments: fragments.fragments.length,
      report_batches: batches.reports.length,
      html_snapshots: htmlSnapshots.snapshots.length,
      layer1_ending_cash: layer1.report.totals.ending_cash,
      layer1_snapshots: snapshots.snapshots.length,
      operational_packages: packages.packages.length,
      package_detail_positive_fixture: packageDetailPositiveFixture,
      category_matrix_rows: categoryMatrix.matrix.rows.length,
      other_review_entries: otherReview.report.count,
      dictionary_review_groups: dictionaryQueue.queue.groups.length,
      raw_history_sources: rawHistory.history.sources_total,
      raw_history_rows: rawHistory.history.rows_total,
      dictionary_training_decisions: trainingDecisions.decisions.length,
      assistant_settings_provider: assistantSettings.settings.provider_key,
      internet_reference_lookups: internetLookups.lookups.length,
      import_review_rows: importReview.review.rows_scanned,
      write_guard: 'ok',
    },
    browser_rehearsal: browserRehearsal,
    route_surface: {
      total: phpRouteSurface.length,
      reads: readRoutes.length,
      writes: writeRoutes.length,
      atlas_read_supported: atlasReadRoutes.size,
      atlas_write_supported: atlasWriteRoutes.size,
      shadow_gateway_available: shadowGatewayAvailable,
      atlas_proxy_available: atlasProxyAvailable,
      ftp_production_cutover_authorized: FTP_PRODUCTION_CUTOVER_AUTHORIZED,
      unsupported_reads: unsupportedReads.length,
      unsupported_writes: unsupportedWrites.length,
      by_area: areas,
    },
    unsupported_reads: unsupportedReads,
    unsupported_writes: unsupportedWrites,
    blockers,
    decision: blockers.length === 0
      ? 'accepted_for_explicit_ftp_production_cutover'
      : blockers.length === 1 && blockers[0] === 'ftp_production_cutover_not_authorized'
      ? 'blocked_until_explicit_ftp_production_cutover_authorization'
      : 'blocked_until_full_atlas_runtime_write_gate_and_browser_cutover',
  };

  console.log(JSON.stringify(result, null, 2));
  if (!EXPECT_BLOCKED && blockers.length > 0) process.exitCode = 2;
}

main()
  .catch((error) => {
    console.error(JSON.stringify({ ok: false, error: error.message }, null, 2));
    process.exitCode = 1;
  })
  .finally(async () => {
    await closeDb();
  });
