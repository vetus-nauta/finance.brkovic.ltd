const assert = require('assert');
const { handleApi, db, closeDb } = require('../server/findesk-v2-atlas-read-server');

const WORKSPACE_ID = process.env.FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID || '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
const ACCOUNTABLE_WORKSPACE_ID = process.env.FINDESK_V2_ACCOUNTABLE_WORKSPACE_ID || '43a20c32-a9e6-4812-a556-6f1cb995147d';
const CLAUDIA_Z_ENTRY_COUNT = 663;
const CLAUDIA_Z_VISIBLE_FRAGMENT_COUNT = 3;
const CLAUDIA_Z_ARCHIVED_FRAGMENT_BATCH_COUNT = 7;
const CLAUDIA_Z_OTHER_REVIEW_COUNT = 1;
const CLAUDIA_Z_OTHER_REVIEW_TOTAL = '100.00';

async function main() {
  const workspaces = await handleApi('GET', '/api/workspaces', {});
  assert.strictEqual(workspaces.ok, true);
  assert(workspaces.workspaces.some((workspace) => workspace.id === WORKSPACE_ID), 'Claudia Z workspace missing');

  const flows = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/flows`, {});
  assert.strictEqual(flows.flows.length >= 2, true, 'flows missing');

  const invites = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/invites`, {});
  assert.strictEqual(invites.invites.length, 3, 'Accountable fixture invites changed');
  assert.strictEqual(Object.hasOwn(invites.invites[0], 'token'), false, 'Invite list leaked token');
  assert.strictEqual(Object.hasOwn(invites.invites[0], 'token_hash'), false, 'Invite list leaked token hash');

  await assert.rejects(
    () => handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/employee-mode`, {}),
    /employee_mode_not_required/,
    'Admin user must not receive fake employee mode'
  );

  const dashboard = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-dashboard`, {});
  assert.strictEqual(dashboard.dashboard.summary.offer_count, 3, 'Accountable dashboard offer count changed');
  assert.strictEqual(dashboard.dashboard.summary.report_count, 3, 'Accountable dashboard report count changed');
  assert.strictEqual(dashboard.dashboard.employees.length, 3, 'Accountable dashboard employees changed');

  const offers = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-offers`, {});
  assert.strictEqual(offers.offers.length, 3, 'Accountable offers count changed');

  const accountableReportsDefault = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-reports`, {});
  assert.strictEqual(accountableReportsDefault.reports.length, 0, 'Default admin accountable reports should show submitted only');

  const accountableReports = await handleApi('GET', `/api/workspaces/${ACCOUNTABLE_WORKSPACE_ID}/accountable-reports`, { status: 'hall_open' });
  assert.strictEqual(accountableReports.reports.length, 3, 'Hall open accountable reports count changed');
  assert.strictEqual(Array.isArray(accountableReports.reports[0].rows), true, 'Accountable report rows missing');
  assert.strictEqual(Boolean(accountableReports.reports[0].settlement), true, 'Accountable report settlement missing');

  const accountableReport = await handleApi('GET', `/api/accountable-reports/${accountableReports.reports[0].id}`, {});
  assert.strictEqual(accountableReport.report.id, accountableReports.reports[0].id, 'Accountable report detail mismatch');
  assert.strictEqual(accountableReport.report.rows.length, 1, 'Accountable report detail rows changed');

  const materialization = await handleApi('GET', `/api/accountable-reports/${accountableReports.reports[0].id}/materialization`, {});
  assert.strictEqual(materialization.materialization.policy, 'cash_effect_none_category_projection', 'Accountable materialization policy mismatch');
  assert.strictEqual(materialization.materialization.entry_count, 1, 'Accountable materialization links changed');

  await assert.rejects(
    () => handleApi('GET', '/api/workspace-invites/000000000000000000000000000000000000000000000000', {}),
    /invite_not_found/,
    'Invite token route must return domain 404 for unknown token'
  );

  const categories = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/categories`, {});
  assert.strictEqual(categories.categories.length >= 20, true, 'categories missing');

  const entries = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/entries`, {});
  assert.strictEqual(entries.entries.length, CLAUDIA_Z_ENTRY_COUNT, 'Claudia Z all-feed entry count changed');

  const august = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/entries`, { year: '2026', month: '8' });
  assert.strictEqual(august.entries.length, 39, 'Claudia Z August entry count changed');

  const summary = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/summary`, {});
  assert.strictEqual(Number(summary.summary.cash_now).toFixed(2), '3893.00', 'cash_now mismatch');

  const month = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/monthly`, { year: '2026', month: '8' });
  assert.strictEqual(Number(month.report.ending_cash).toFixed(2), '3893.00', 'August ending cash mismatch');

  const fragments = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments`, {});
  assert.strictEqual(fragments.fragments.length, CLAUDIA_Z_VISIBLE_FRAGMENT_COUNT, 'visible operational fragment count changed');

  const fragment = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments/${fragments.fragments[0].id}`, {});
  assert.strictEqual(fragment.fragment.id, fragments.fragments[0].id, 'operational fragment detail mismatch');

  const batches = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/batches`, {});
  assert.strictEqual(batches.reports.length, fragments.fragments.length, 'report batches alias count mismatch');

  const batch = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/batches/${batches.reports[0].id}`, {});
  assert.strictEqual(batch.report.id, batches.reports[0].id, 'report batch detail mismatch');
  assert.strictEqual(batch.report.content_hash, fragment.fragment.content_hash, 'report batch alias content hash mismatch');

  const htmlSnapshots = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments/${fragment.fragment.id}/html-snapshots`, {});
  assert.strictEqual(Array.isArray(htmlSnapshots.snapshots), true, 'HTML snapshots list missing');
  assert.strictEqual(htmlSnapshots.snapshots.length >= 1, true, 'active fragment HTML snapshot missing');
  assert.strictEqual(Object.hasOwn(htmlSnapshots.snapshots[0], 'html_content'), false, 'HTML snapshot list leaked html_content');

  const htmlSnapshot = await handleApi(
    'GET',
    `/api/workspaces/${WORKSPACE_ID}/reports/operational-fragments/${fragment.fragment.id}/html-snapshots/${htmlSnapshots.snapshots[0].id}`,
    {}
  );
  assert.strictEqual(htmlSnapshot.snapshot.id, htmlSnapshots.snapshots[0].id, 'HTML snapshot detail mismatch');
  assert.strictEqual(typeof htmlSnapshot.snapshot.html_content, 'string', 'HTML snapshot detail missing html_content');

  const layer1 = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/layer1-summary`, { year: '2026', month: '8' });
  assert.strictEqual(Number(layer1.report.totals.ending_cash).toFixed(2), '3893.00', 'Layer1 ending cash mismatch');
  assert.strictEqual(Boolean(layer1.report.header), true, 'Layer1 header missing');
  assert.strictEqual(Boolean(layer1.report.blocks), true, 'Layer1 blocks missing');
  assert.strictEqual(Boolean(layer1.report.source_trace), true, 'Layer1 source trace missing');

  const sourceEntries = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/layer1-source-entries`, { ids: august.entries[0].id });
  assert.strictEqual(sourceEntries.entries.length, 1, 'Layer1 source entry lookup failed');
  assert.strictEqual(sourceEntries.entries[0].id, august.entries[0].id, 'Layer1 source entry order/id mismatch');
  assert.strictEqual(sourceEntries.missing_ids.length, 0, 'Layer1 source entry missing unexpectedly');

  const snapshots = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/layer1-snapshots`, { year: '2026', month: '8' });
  assert.strictEqual(Array.isArray(snapshots.snapshots), true, 'Layer1 snapshots missing');

  const packages = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages`, { limit: '50' });
  assert.strictEqual(Array.isArray(packages.packages), true, 'Operational packages missing');
  if (packages.packages.length > 0) {
    const packageDetail = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages/${packages.packages[0].id}`, {});
    assert.strictEqual(packageDetail.package.id, packages.packages[0].id, 'Operational package detail mismatch');
    assert.strictEqual(Array.isArray(packageDetail.package.items), true, 'Operational package detail items missing');
    assert.strictEqual(Array.isArray(packageDetail.package.fragments), true, 'Operational package detail fragments missing');
    assert.strictEqual(Array.isArray(packageDetail.package.versions), true, 'Operational package detail versions missing');
  } else {
    await assert.rejects(
      () => handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/operational-packages/00000000-0000-4000-8000-000000000000`, {}),
      /report_package_not_found/,
      'Operational package detail route must return domain 404'
    );
  }

  const categoryMatrix = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/category-matrix`, { year: '2026' });
  assert.strictEqual(categoryMatrix.matrix.months.length, 12, 'Category matrix months missing');
  assert.strictEqual(categoryMatrix.matrix.rows.length, categories.categories.length, 'Category matrix category coverage mismatch');
  for (const row of categoryMatrix.matrix.rows) {
    const monthSum = Object.values(row.months).reduce((sum, value) => sum + Number(value || 0), 0);
    assert.strictEqual(Number(row.total).toFixed(2), Number(monthSum).toFixed(2), `Category matrix total mismatch for ${row.category_code}`);
  }

  const otherReview = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/other-review`, {});
  assert.strictEqual(otherReview.report.count, CLAUDIA_Z_OTHER_REVIEW_COUNT, 'Other review report count changed');
  assert.strictEqual(Number(otherReview.report.total).toFixed(2), CLAUDIA_Z_OTHER_REVIEW_TOTAL, 'Other review report total changed');

  const dictionaryQueue = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-review-queue`, { limit: '20', examples: '2' });
  assert.strictEqual(dictionaryQueue.queue.rows_total, 3338, 'Dictionary review queue rows changed');
  assert.strictEqual(dictionaryQueue.queue.rows_with_money, 2508, 'Dictionary review queue money coverage changed');
  assert.strictEqual(dictionaryQueue.queue.groups.length > 0, true, 'Dictionary review queue groups missing');

  const rawHistory = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/raw-history`, { sources: '5', samples: '2' });
  assert.strictEqual(rawHistory.history.sources_total, 57, 'Raw history sources changed');
  assert.strictEqual(rawHistory.history.rows_total, 3338, 'Raw history rows changed');
  assert.strictEqual(rawHistory.history.sources.length, 5, 'Raw history source limit ignored');

  const trainingDecisions = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-training-decisions`, {});
  assert.strictEqual(trainingDecisions.decisions.length, 111, 'Dictionary training decisions changed');

  const assistantSettings = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/assistant-settings`, {});
  assert.strictEqual(assistantSettings.settings.mr_smith_enabled, false, 'Assistant settings default enabled mismatch');
  assert.strictEqual(assistantSettings.settings.internet_reference_mode, 'per_request', 'Assistant settings default mode mismatch');
  assert.strictEqual(assistantSettings.settings.provider_key, 'stub', 'Assistant settings provider mismatch');

  const internetLookups = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/dictionary-training-internet-reference/lookups`, { limit: '5' });
  assert.strictEqual(Array.isArray(internetLookups.lookups), true, 'Internet reference lookups missing');

  const database = await db();
  const importReview = await handleApi('GET', `/api/workspaces/${rawHistory.history.workspace_id}/imports/${rawHistory.history.sources[0].id}/review`, {});
  assert.strictEqual(importReview.review.import_id, rawHistory.history.sources[0].id, 'Import review id mismatch');
  assert.strictEqual(importReview.review.rows_scanned, rawHistory.history.sources[0].row_count, 'Import review row count mismatch');
  assert.strictEqual(importReview.review.row_traces.length > 0, true, 'Import review row traces missing');

  const attachments = await handleApi('GET', `/api/entries/${august.entries[0].id}/attachments`, {});
  assert.strictEqual(Array.isArray(attachments.attachments), true, 'Entry attachments missing');

  const otherExpenses = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/other-expenses`, {});
  assert.strictEqual(Array.isArray(otherExpenses.entries), true, 'other expense queue missing');

  const allFragmentBatches = await database.collection('v2_report_batches')
    .countDocuments({ workspace_id: WORKSPACE_ID, batch_type: 'operational_fragment' });
  assert.strictEqual(allFragmentBatches, CLAUDIA_Z_ARCHIVED_FRAGMENT_BATCH_COUNT, 'Atlas operational fragment batch archive changed');

  await assert.rejects(
    () => handleApi('DELETE', '/api/attachments/00000000-0000-4000-8000-000000000000', {}),
    /attachment_not_found/,
    'Attachment delete route must return domain 404 for unknown attachment'
  );

  console.log(JSON.stringify({
    ok: true,
    workspaces: workspaces.workspaces.length,
    flows: flows.flows.length,
    accountable_invites: invites.invites.length,
    accountable_offers: offers.offers.length,
    accountable_reports: accountableReports.reports.length,
    accountable_materialization_links: materialization.materialization.entry_count,
    invite_token_positive_fixture: false,
    categories: categories.categories.length,
    entries: entries.entries.length,
    august_entries: august.entries.length,
    cash_now: summary.summary.cash_now,
    august_ending_cash: month.report.ending_cash,
    active_fragments: fragments.fragments.length,
    all_fragment_batches: allFragmentBatches,
    other_expenses: otherExpenses.entries.length,
    report_batches: batches.reports.length,
    html_snapshots: htmlSnapshots.snapshots.length,
    layer1_ending_cash: layer1.report.totals.ending_cash,
    layer1_source_entries: sourceEntries.entries.length,
    layer1_snapshots: snapshots.snapshots.length,
    operational_packages: packages.packages.length,
    package_detail_positive_fixture: packages.packages.length > 0,
    category_matrix_rows: categoryMatrix.matrix.rows.length,
    other_review_report_entries: otherReview.report.count,
    dictionary_review_groups: dictionaryQueue.queue.groups.length,
    raw_history_sources: rawHistory.history.sources_total,
    raw_history_rows: rawHistory.history.rows_total,
    dictionary_training_decisions: trainingDecisions.decisions.length,
    assistant_settings_provider: assistantSettings.settings.provider_key,
    internet_reference_lookups: internetLookups.lookups.length,
    import_review_rows: importReview.review.rows_scanned,
    entry_attachments: attachments.attachments.length,
  }, null, 2));
}

main()
  .catch((error) => {
    console.error(JSON.stringify({ ok: false, error: error.message }, null, 2));
    process.exitCode = 1;
  })
  .finally(async () => {
    await closeDb();
  });
