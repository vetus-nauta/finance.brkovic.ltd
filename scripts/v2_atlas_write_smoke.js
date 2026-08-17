const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const os = require('os');
const path = require('path');
const childProcess = require('child_process');
const { handleApi, db, closeDb } = require('../server/findesk-v2-atlas-read-server');

const WORKSPACE_ID = process.env.FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID || '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
const WRITE_TEST_WORKSPACE_ID = process.env.FINDESK_V2_ACCOUNTABLE_WORKSPACE_ID || '43a20c32-a9e6-4812-a556-6f1cb995147d';

const FINANCE_COLLECTIONS = [
  'v2_entries',
  'v2_flows',
  'v2_monthly_closures',
  'v2_report_batches',
  'v2_report_batch_entries',
  'v2_report_batch_html_snapshots',
  'v2_report_packages',
  'v2_report_package_items',
  'v2_report_versions',
  'v2_report_snapshots',
  'v2_import_sources',
  'v2_import_rows',
  'v2_category_rules',
  'v2_dictionary_training_decisions',
  'v2_internet_reference_lookups',
  'v2_attachments',
];

async function financeSnapshot(database) {
  const counts = {};
  for (const collection of FINANCE_COLLECTIONS) {
    counts[collection] = await database.collection(collection).countDocuments();
  }
  const summary = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/summary`, {});
  const month = await handleApi('GET', `/api/workspaces/${WORKSPACE_ID}/reports/monthly`, { year: '2026', month: '8' });
  return {
    counts,
    cash_now: Number(summary.summary.cash_now).toFixed(2),
    august_ending_cash: Number(month.report.ending_cash).toFixed(2),
  };
}

function escapeXml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

function xlsxColumnName(index) {
  let number = index + 1;
  let name = '';
  while (number > 0) {
    const modulo = (number - 1) % 26;
    name = String.fromCharCode(65 + modulo) + name;
    number = Math.floor((number - modulo) / 26);
  }
  return name;
}

function createSmokeXlsx(rows) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'findesk-v2-smoke-xlsx-'));
  const xlsxPath = path.join(os.tmpdir(), `findesk-v2-smoke-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
  try {
    fs.mkdirSync(path.join(dir, '_rels'), { recursive: true });
    fs.mkdirSync(path.join(dir, 'xl', '_rels'), { recursive: true });
    fs.mkdirSync(path.join(dir, 'xl', 'worksheets'), { recursive: true });
    fs.writeFileSync(path.join(dir, '[Content_Types].xml'), '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    fs.writeFileSync(path.join(dir, '_rels', '.rels'), '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    fs.writeFileSync(path.join(dir, 'xl', 'workbook.xml'), '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Smoke" sheetId="1" r:id="rId1"/></sheets></workbook>');
    fs.writeFileSync(path.join(dir, 'xl', '_rels', 'workbook.xml.rels'), '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    const rowXml = rows.map((row, rowIndex) => {
      const number = rowIndex + 1;
      const cells = row.map((value, cellIndex) => {
        if (value === null || value === undefined || value === '') return '';
        const ref = `${xlsxColumnName(cellIndex)}${number}`;
        return `<c r="${ref}" t="inlineStr"><is><t>${escapeXml(value)}</t></is></c>`;
      }).join('');
      return `<row r="${number}">${cells}</row>`;
    }).join('');
    fs.writeFileSync(path.join(dir, 'xl', 'worksheets', 'sheet1.xml'), `<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>${rowXml}</sheetData></worksheet>`);
    childProcess.execFileSync('zip', ['-qr', xlsxPath, '.'], { cwd: dir, stdio: 'ignore' });
    return xlsxPath;
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

async function cleanupWorkspaceFixture(database, workspaceId) {
  if (!workspaceId) return;
  const workspaceEntries = await database.collection('v2_entries').find({ workspace_id: workspaceId }).project({ id: 1 }).toArray();
  const workspaceEntryIds = workspaceEntries.map((entry) => entry.id);
  if (workspaceEntryIds.length) {
    await database.collection('v2_attachments').deleteMany({ entry_id: { $in: workspaceEntryIds } });
  }
  fs.rmSync(path.join(__dirname, '..', 'storage', 'v2', 'attachments', workspaceId), { recursive: true, force: true });
  await database.collection('v2_workspace_assistant_settings').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_workspace_invites').deleteMany({ workspace_id: workspaceId });
  const reports = await database.collection('v2_accountable_reports').find({ workspace_id: workspaceId }).project({ id: 1 }).toArray();
  const reportIds = reports.map((report) => report.id);
  if (reportIds.length) {
    await database.collection('v2_accountable_report_entry_links').deleteMany({ report_id: { $in: reportIds } });
    await database.collection('v2_accountable_settlements').deleteMany({ report_id: { $in: reportIds } });
    await database.collection('v2_accountable_report_rows').deleteMany({ report_id: { $in: reportIds } });
  }
  const batches = await database.collection('v2_report_batches').find({ workspace_id: workspaceId }).project({ id: 1 }).toArray();
  const batchIds = batches.map((batch) => batch.id);
  if (batchIds.length) {
    await database.collection('v2_report_batch_entries').deleteMany({ batch_id: { $in: batchIds } });
    await database.collection('v2_report_batch_html_snapshots').deleteMany({ batch_id: { $in: batchIds } });
  }
  const packages = await database.collection('v2_report_packages').find({ workspace_id: workspaceId }).project({ id: 1 }).toArray();
  const packageIds = packages.map((reportPackage) => reportPackage.id);
  if (packageIds.length) {
    await database.collection('v2_report_package_items').deleteMany({ package_id: { $in: packageIds } });
    await database.collection('v2_report_versions').deleteMany({ report_id: { $in: packageIds } });
  }
  await database.collection('v2_report_batches').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_report_batch_html_snapshots').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_report_packages').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_report_snapshots').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_internet_reference_lookups').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_dictionary_training_decisions').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_category_rules').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_accountable_reports').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_accountable_offers').deleteMany({ workspace_id: workspaceId });
  const importSources = await database.collection('v2_import_sources').find({ workspace_id: workspaceId }).project({ id: 1 }).toArray();
  const importSourceIds = importSources.map((source) => source.id);
  if (importSourceIds.length) {
    await database.collection('v2_import_rows').deleteMany({ import_source_id: { $in: importSourceIds } });
  }
  await database.collection('v2_import_sources').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_entries').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_flows').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_monthly_closures').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_workspace_members').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_audit_log').deleteMany({ workspace_id: workspaceId });
  await database.collection('v2_workspaces').deleteMany({ id: workspaceId });
}

async function main() {
  const database = await db();
  const settingsCollection = database.collection('v2_workspace_assistant_settings');
  const beforeRaw = await settingsCollection.findOne({ workspace_id: WRITE_TEST_WORKSPACE_ID });
  const before = await handleApi('GET', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {});
  const auditCountBefore = await database.collection('v2_audit_log').countDocuments({
    workspace_id: WRITE_TEST_WORKSPACE_ID,
    entity_type: 'workspace_assistant_settings',
    entity_id: WRITE_TEST_WORKSPACE_ID,
    action: 'update',
  });
  const financeBefore = await financeSnapshot(database);
  let createdWorkspaceId = null;
  let createdArchiveWorkspaceId = null;
  const inviteUserId = 900000000 + Math.floor(Math.random() * 1000000);
  const readOnlyUserId = inviteUserId + 1000000;
  const inviteEmail = `atlas-invite-smoke-${Date.now()}@example.test`;

  try {
    await database.collection('users').insertOne({
      id: inviteUserId,
      email: inviteEmail,
      display_name: 'Atlas Invite Smoke',
      preferred_language: 'ru',
      timezone: 'Europe/Podgorica',
      status: 'active',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      deleted_at: null,
    });

    const createName = `atlas write smoke ${Date.now()}`;
    const created = await handleApi('POST', '/api/workspaces', {}, {
      name: createName,
      type: 'custom',
      currency: 'eur',
      locale: 'ru',
      opening_cash: '123.45',
    });
    createdWorkspaceId = created.workspace.id;
    assert.strictEqual(created.ok, true, 'Workspace create wrapper mismatch');
    assert.strictEqual(created.workspace.name, createName, 'Workspace create name mismatch');
    assert.strictEqual(created.workspace.type, 'custom', 'Workspace create type mismatch');
    assert.strictEqual(created.workspace.currency, 'EUR', 'Workspace create currency mismatch');
    assert.strictEqual(created.workspace.role, 'owner', 'Workspace create owner membership missing');

    const createdFlows = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/flows`, {});
    assert.strictEqual(createdFlows.flows.length, 2, 'Workspace create default flow count mismatch');
    const cashFlow = createdFlows.flows.find((flow) => flow.type === 'cash');
    const cardFlow = createdFlows.flows.find((flow) => flow.type === 'card');
    assert(cashFlow, 'Workspace create cash flow missing');
    assert(cardFlow, 'Workspace create card flow missing');
    assert.strictEqual(Number(cashFlow.opening_balance).toFixed(2), '123.45', 'Workspace create opening cash mismatch');
    assert.strictEqual(cashFlow.has_live_balance, true, 'Workspace create cash live balance mismatch');
    assert.strictEqual(cardFlow.has_live_balance, false, 'Workspace create card live balance mismatch');

    const patchedWorkspace = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}`, {}, {
      name: `${createName} updated`,
      type: 'business',
      currency: 'usd',
      locale: 'en',
    });
    assert.strictEqual(patchedWorkspace.workspace.name, `${createName} updated`, 'Workspace patch name mismatch');
    assert.strictEqual(patchedWorkspace.workspace.type, 'business', 'Workspace patch type mismatch');
    assert.strictEqual(patchedWorkspace.workspace.currency, 'USD', 'Workspace patch currency mismatch');
    assert.strictEqual(patchedWorkspace.workspace.locale, 'en', 'Workspace patch locale mismatch');
    const archiveWorkspace = await handleApi('POST', '/api/workspaces', {}, {
      name: `${createName} updated Archive Raw History`,
      type: 'custom',
      currency: 'eur',
      locale: 'ru',
      opening_cash: '0.00',
    });
    createdArchiveWorkspaceId = archiveWorkspace.workspace.id;
    assert.strictEqual(archiveWorkspace.workspace.name, `${createName} updated Archive Raw History`, 'Archive workspace fixture name mismatch');

    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}`, {}, { type: 'spaceship' }),
      /invalid_type/,
      'Invalid workspace type must be rejected'
    );

    const workspaceAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'workspace', entity_id: createdWorkspaceId })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(workspaceAuditActions.includes('create'), 'Workspace create audit missing');
    assert(workspaceAuditActions.includes('update'), 'Workspace update audit missing');

    const inviteCreated = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/invites`, {}, {
      email: inviteEmail.toUpperCase(),
      name: 'Invite Smoke Employee',
      expires_days: 3,
    });
    assert.strictEqual(inviteCreated.ok, true, 'Invite create wrapper mismatch');
    assert.strictEqual(inviteCreated.invite.invited_email, inviteEmail, 'Invite email normalization mismatch');
    assert.strictEqual(inviteCreated.invite.role, 'employee', 'Invite role mismatch');
    assert.strictEqual(inviteCreated.invite.access_scope, 'own_entries', 'Invite access scope mismatch');
    assert.strictEqual(inviteCreated.invite.status, 'pending', 'Invite status mismatch');
    assert.strictEqual(typeof inviteCreated.invite.token, 'string', 'Invite token missing on create');
    assert.strictEqual(inviteCreated.invite.token.length, 48, 'Invite token length mismatch');
    assert.strictEqual(inviteCreated.invite.workspace.id, createdWorkspaceId, 'Invite workspace wrapper mismatch');

    const preview = await handleApi('POST', '/api/workspace-invites/preview', {}, { token: inviteCreated.invite.token }, inviteUserId);
    assert.strictEqual(preview.ok, true, 'Invite preview wrapper mismatch');
    assert.strictEqual(preview.email_matches, true, 'Invite preview email match mismatch');
    assert.strictEqual(preview.workspace.id, createdWorkspaceId, 'Invite preview workspace mismatch');

    const accepted = await handleApi('POST', '/api/workspace-invites/accept', {}, { token: inviteCreated.invite.token }, inviteUserId);
    assert.strictEqual(accepted.ok, true, 'Invite accept wrapper mismatch');
    assert.strictEqual(accepted.invite.status, 'accepted', 'Invite accept status mismatch');
    assert.strictEqual(accepted.workspace.role, 'employee', 'Invite accept workspace role mismatch');
    assert.strictEqual(accepted.workspace.access_scope, 'own_entries', 'Invite accept scope mismatch');
    await database.collection('v2_workspace_members').insertOne({
      id: crypto.randomUUID(),
      workspace_id: createdWorkspaceId,
      user_id: readOnlyUserId,
      role: 'viewer',
      access_scope: 'workspace',
      assigned_actor_id: null,
      invited_by: 1,
      joined_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    });

    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/flows`, {}, {
        name: 'Atlas viewer flow',
        type: 'card',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not create flow'
    );
    const extraFlow = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/flows`, {}, {
      name: 'Atlas extra card smoke',
      type: 'card',
      has_live_balance: false,
      is_default: false,
      opening_balance: '0.00',
    });
    assert.strictEqual(extraFlow.ok, true, 'Flow create wrapper mismatch');
    assert.strictEqual(extraFlow.flow.workspace_id, createdWorkspaceId, 'Flow create workspace mismatch');
    assert.strictEqual(extraFlow.flow.name, 'Atlas extra card smoke', 'Flow create name mismatch');
    assert.strictEqual(extraFlow.flow.type, 'card', 'Flow create type mismatch');
    assert.strictEqual(extraFlow.flow.has_live_balance, false, 'Flow create live balance mismatch');
    assert.strictEqual(extraFlow.flow.is_default, false, 'Flow create default flag mismatch');
    assert.strictEqual(Number(extraFlow.flow.opening_balance).toFixed(2), '0.00', 'Flow create opening balance mismatch');
    const flowsAfterExtra = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/flows`, {});
    assert(flowsAfterExtra.flows.some((flow) => flow.id === extraFlow.flow.id), 'Flow create readback missing');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/category-rules`, {}, {
        category_code: 'media_comms',
        pattern: 'atlas viewer dictionary rule',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not create category rule'
    );
    const categoryRule = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/category-rules`, {}, {
      category_code: 'media_comms',
      pattern: 'atlas dictionary smoke',
      pattern_type: 'phrase',
      language: 'ru',
      weight: 20,
      negative_weight: 1,
      requires_any: ['связь', ' связь ', ''],
      excludes_any: ['мой', 'личный', 'мой'],
    });
    assert.strictEqual(categoryRule.ok, true, 'Category rule create wrapper mismatch');
    assert.strictEqual(categoryRule.category_rule.workspace_id, createdWorkspaceId, 'Category rule workspace mismatch');
    assert.strictEqual(categoryRule.category_rule.category_code, 'media_comms', 'Category rule category mismatch');
    assert.strictEqual(categoryRule.category_rule.pattern, 'atlas dictionary smoke', 'Category rule pattern mismatch');
    assert.strictEqual(categoryRule.category_rule.pattern_type, 'phrase', 'Category rule pattern type mismatch');
    assert.strictEqual(categoryRule.category_rule.language, 'ru', 'Category rule language mismatch');
    assert.strictEqual(categoryRule.category_rule.weight, 20, 'Category rule weight mismatch');
    assert.strictEqual(categoryRule.category_rule.negative_weight, 1, 'Category rule negative weight mismatch');
    assert.deepStrictEqual(categoryRule.category_rule.requires_any, ['связь'], 'Category rule requires_any normalization mismatch');
    assert.deepStrictEqual(categoryRule.category_rule.excludes_any, ['мой', 'личный'], 'Category rule excludes_any normalization mismatch');
    assert.strictEqual(categoryRule.category_rule.created_by_user, true, 'Category rule created_by_user mismatch');
    assert.strictEqual(categoryRule.category_rule.is_active, true, 'Category rule active flag mismatch');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/category-rules`, {}, {
        category_code: 'not_a_real_category',
        pattern: 'atlas bad category',
      }),
      /unknown_category/,
      'Unknown category rule category must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/category-rules`, {}, {
        category_code: 'media_comms',
        pattern: 'atlas bad enum',
        pattern_type: 'glob',
      }),
      /invalid_pattern_type/,
      'Invalid category rule pattern type must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/category-rules`, {}, {
        category_code: 'media_comms',
        pattern: 'atlas bad list',
        requires_any: 'связь',
      }),
      /invalid_requires_any/,
      'Invalid category rule list must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/flows`, {}, {
        name: 'Atlas invalid flow',
        type: 'vault',
      }),
      /invalid_type/,
      'Invalid flow type must be rejected'
    );

    const xlsxPath = createSmokeXlsx([
      ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
      ['', 'private topup', '300', '', '', '', '', ''],
      ['', 'fuel marina', '', '200', '', '', '', ''],
      ['2026-07-02', 'charter deposit', '5000', '', '', '', '', ''],
      ['2026-07-03', 'снял с карты', '', '', '', '', '1000', ''],
      ['', 'снял с карты', '1000', '', '', '', '', ''],
      ['2026-07-04', 'Netflix', '', '', '', '', '60', ''],
      ['2026-07-05', 'какая-то штука', '', '50', '', '', '', ''],
      ['2026-07-06', 'card refund', '', '', '', '25', '', ''],
      ['2026-07-07', 'информационная строка', '', '', '', '', '', ''],
      ['2026-07-08', 'ambiguous two money columns', '100', '50', '', '', '', ''],
      ['2026-07-01', 'fuel marina', '', '200', '', '', '', ''],
      ['2026-07-31', 'Сводные данные', '6300', '250', '', '25', '1060', 'summary'],
    ]);
    const importUpload = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/excel`, {}, {
      file_name: 'july-final-2026-07-01.xlsx',
      file_id: 'atlas-legacy-file-001',
      file_url: 'https://example.test/july-final.xlsx',
      content_base64: fs.readFileSync(xlsxPath).toString('base64'),
    });
    fs.rmSync(xlsxPath, { force: true });
    assert.strictEqual(importUpload.ok, true, 'Excel import upload wrapper mismatch');
    assert.strictEqual(importUpload.import.include_decision, 'included', 'Excel import include decision mismatch');
    assert.strictEqual(importUpload.import.sheets_scanned, 1, 'Excel import sheets scanned mismatch');
    assert.strictEqual(importUpload.import.rows_scanned, 12, 'Excel import rows scanned mismatch');
    assert.strictEqual(importUpload.import.rows_parsed, 9, 'Excel import rows parsed mismatch');
    assert.strictEqual(importUpload.import.entries_created, 0, 'Excel import upload must not create entries');
    assert.strictEqual(importUpload.import.summary_rows_ignored, 1, 'Excel import summary row mismatch');
    assert.strictEqual(importUpload.import.rows_ignored, 1, 'Excel import ignored row mismatch');
    assert.strictEqual(importUpload.import.rows_unrecognized, 1, 'Excel import unrecognized row mismatch');
    assert.strictEqual(importUpload.import.duplicate_suspects.length, 1, 'Excel import duplicate suspect mismatch');
    assert.strictEqual(Number(importUpload.import.source_summary_totals.cash_income).toFixed(2), '6300.00', 'Excel import summary cash income mismatch');
    assert.strictEqual(Number(importUpload.import.source_summary_totals.cash_expense).toFixed(2), '250.00', 'Excel import summary cash expense mismatch');
    assert.strictEqual(Number(importUpload.import.source_summary_totals.card_income).toFixed(2), '25.00', 'Excel import summary card income mismatch');
    assert.strictEqual(Number(importUpload.import.source_summary_totals.card_expense).toFixed(2), '1060.00', 'Excel import summary card expense mismatch');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/excel`, {}, {
        file_name: 'viewer.xlsx',
        content_base64: Buffer.from('viewer import').toString('base64'),
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not upload Excel import'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/${importUpload.import.import_id}/accept`, {}, {
        decision: 'accept',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not accept Excel import'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/excel`, {}, {
        file_name: 'not-excel.csv',
        content_base64: Buffer.from('bad').toString('base64'),
      }),
      /xlsx_required/,
      'Excel import must require .xlsx extension'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/excel`, {}, {
        file_name: 'bad.xlsx',
        content_base64: 'not base64!',
      }),
      /invalid_base64/,
      'Excel import must reject invalid base64'
    );
    const reviewBeforeAccept = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/imports/${importUpload.import.import_id}/review`, {});
    assert.strictEqual(reviewBeforeAccept.review.entries_created, 0, 'Excel import review before accept must not have entries');
    assert.strictEqual(reviewBeforeAccept.review.row_traces.length, 12, 'Excel import row trace count before accept mismatch');
    const acceptedImport = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/${importUpload.import.import_id}/accept`, {}, {
      decision: 'accept',
    });
    assert.strictEqual(acceptedImport.review.entries_created, 9, 'Excel import accepted entry count mismatch');
    assert.strictEqual(acceptedImport.review.rows_unrecognized, 1, 'Excel import accepted unrecognized count mismatch');
    assert.strictEqual(Number(acceptedImport.review.normalized_totals.cash_income).toFixed(2), '6300.00', 'Excel import accepted cash income mismatch');
    assert.strictEqual(Number(acceptedImport.review.normalized_totals.cash_expense).toFixed(2), '250.00', 'Excel import accepted cash expense mismatch');
    assert.strictEqual(Number(acceptedImport.review.normalized_totals.card_income).toFixed(2), '25.00', 'Excel import accepted card income mismatch');
    assert.strictEqual(Number(acceptedImport.review.normalized_totals.card_expense).toFixed(2), '1060.00', 'Excel import accepted card expense mismatch');
    assert(acceptedImport.review.months_covered.includes('2026-07'), 'Excel import accepted missing month coverage');
    const dateSources = new Set(acceptedImport.review.row_traces.map((trace) => trace.date_source).filter(Boolean));
    assert(dateSources.has('filename_date'), 'Excel import missing filename date provenance');
    assert(dateSources.has('inherited_previous_row_date'), 'Excel import missing inherited date provenance');
    assert(dateSources.has('row_date'), 'Excel import missing row date provenance');
    const importEntries = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/entries`, { year: '2026', month: '7' });
    const linkedImportEntries = importEntries.entries.filter((entry) => entry.source_id === importUpload.import.import_id);
    assert.strictEqual(linkedImportEntries.length, 9, 'Excel import entries source link count mismatch');
    assert.strictEqual(linkedImportEntries.filter((entry) => entry.status === 'duplicate_suspect').length, 1, 'Excel import duplicate entry count mismatch');
    assert.strictEqual(linkedImportEntries.filter((entry) => entry.entry_type === 'card_income').length, 1, 'Excel import card income entry missing');
    const importMonthly = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/reports/monthly`, { year: '2026', month: '7' });
    assert.strictEqual(Number(importMonthly.report.opening_cash).toFixed(2), '123.45', 'Excel import monthly opening cash mismatch');
    assert.strictEqual(Number(importMonthly.report.external_cash_income).toFixed(2), '300.00', 'Excel import monthly external cash income mismatch');
    assert.strictEqual(Number(importMonthly.report.commercial_income).toFixed(2), '5000.00', 'Excel import monthly commercial income mismatch');
    assert.strictEqual(Number(importMonthly.report.cash_topup_from_card_cash_side).toFixed(2), '1000.00', 'Excel import monthly cash topup mismatch');
    assert.strictEqual(Number(importMonthly.report.cash_expense).toFixed(2), '250.00', 'Excel import monthly cash expense mismatch');
    assert.strictEqual(Number(importMonthly.report.card_expense).toFixed(2), '1060.00', 'Excel import monthly card expense mismatch');
    assert.strictEqual(Number(importMonthly.report.other_expenses).toFixed(2), '50.00', 'Excel import monthly other expenses mismatch');
    assert.strictEqual(Number(importMonthly.report.ending_cash).toFixed(2), '6173.45', 'Excel import monthly ending cash mismatch');
    const excludedImport = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/excel`, {}, {
      file_name: 'draft legacy import.xlsx',
      content_base64: Buffer.from('not parsed because excluded').toString('base64'),
    });
    assert.strictEqual(excludedImport.import.include_decision, 'excluded_by_title_marker', 'Excluded import decision mismatch');
    assert.strictEqual(excludedImport.import.files_excluded, 1, 'Excluded import count mismatch');
    assert.strictEqual(excludedImport.import.rows_scanned, 0, 'Excluded import must not parse rows');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/imports/${excludedImport.import.import_id}/accept`, {}, {
        decision: 'accept',
      }),
      /import_excluded/,
      'Excluded import accept must be blocked'
    );
    const importAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'import_source', entity_id: importUpload.import.import_id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(importAuditActions.includes('create_import'), 'Excel import create audit missing');
    assert(importAuditActions.includes('accept_import'), 'Excel import accept audit missing');

    const rawConversionSourceId = crypto.randomUUID();
    const rawConversionRows = [
      {
        id: crypto.randomUUID(),
        import_source_id: rawConversionSourceId,
        sheet_name: 'RawConvert',
        row_number: 1,
        raw_json: {
          'дата': '2026-08-12',
          'описание платежа': 'netflix raw convert',
          'расход карта': '10',
        },
        parse_status: null,
        parse_notes: null,
        created_at: new Date().toISOString(),
      },
      {
        id: crypto.randomUUID(),
        import_source_id: rawConversionSourceId,
        sheet_name: 'RawConvert',
        row_number: 2,
        raw_json: {
          'описание платежа': 'сводные данные',
          'расход карта': '999',
          'сводные данные': 'summary row',
        },
        parse_status: null,
        parse_notes: null,
        created_at: new Date().toISOString(),
      },
    ];
    await database.collection('v2_import_sources').insertOne({
      id: rawConversionSourceId,
      workspace_id: createdArchiveWorkspaceId,
      source_type: 'excel',
      file_name: 'atlas-raw-history-convert-smoke.xlsx',
      file_url: null,
      file_id: null,
      status: 'accepted',
      include_decision: 'included',
      reason: 'atlas write smoke raw conversion',
      created_at: new Date().toISOString(),
    });
    await database.collection('v2_import_rows').insertMany(rawConversionRows);

    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdArchiveWorkspaceId}/raw-history/convert`, {}, {
        mode: 'preview',
        limit: 1,
      }),
      /raw_history_requires_operational_workspace/,
      'Raw history conversion must require operational workspace'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/raw-history/convert`, {}, {
        mode: 'preview',
        source_id: rawConversionSourceId,
        limit: 1,
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not convert raw history'
    );
    const rawConversionPreview = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/raw-history/convert`, {}, {
      mode: 'preview',
      source_id: rawConversionSourceId,
      limit: 2,
    });
    assert.strictEqual(rawConversionPreview.ok, true, 'Raw history conversion preview wrapper mismatch');
    assert.strictEqual(rawConversionPreview.conversion.mode, 'preview', 'Raw history conversion preview mode mismatch');
    assert.strictEqual(rawConversionPreview.conversion.scanned, 2, 'Raw history conversion preview scanned mismatch');
    assert.strictEqual(rawConversionPreview.conversion.convertible, 1, 'Raw history conversion preview convertible mismatch');
    assert.strictEqual(rawConversionPreview.conversion.skipped, 1, 'Raw history conversion preview skipped mismatch');
    assert.strictEqual(rawConversionPreview.conversion.converted, 0, 'Raw history preview must not create entries');
    const rawConversionCommit = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/raw-history/convert`, {}, {
      mode: 'commit',
      source_id: rawConversionSourceId,
      limit: 2,
    });
    assert.strictEqual(rawConversionCommit.conversion.mode, 'commit', 'Raw history conversion commit mode mismatch');
    assert.strictEqual(rawConversionCommit.conversion.convertible, 1, 'Raw history conversion commit convertible mismatch');
    assert.strictEqual(rawConversionCommit.conversion.converted, 1, 'Raw history conversion commit converted mismatch');
    const rawConvertedEntryId = rawConversionCommit.conversion.rows.find((row) => row.entry_id).entry_id;
    const rawConvertedEntry = await database.collection('v2_entries').findOne({ id: rawConvertedEntryId });
    assert(rawConvertedEntry, 'Raw history converted entry missing');
    assert.strictEqual(rawConvertedEntry.workspace_id, createdWorkspaceId, 'Raw history converted entry workspace mismatch');
    assert.strictEqual(rawConvertedEntry.source_type, 'import', 'Raw history converted entry source type mismatch');
    assert.strictEqual(rawConvertedEntry.source_row_id, rawConversionRows[0].id, 'Raw history converted entry source row mismatch');
    assert.strictEqual(rawConvertedEntry.balance_after, null, 'Raw history card conversion must not mutate cash balance');
    const rawConvertedRow = await database.collection('v2_import_rows').findOne({ id: rawConversionRows[0].id });
    assert.strictEqual(rawConvertedRow.parse_status, 'imported', 'Raw history converted row status mismatch');
    assert.strictEqual(rawConvertedRow.entry_id, rawConvertedEntryId, 'Raw history converted row entry link mismatch');
    const rawSummaryRow = await database.collection('v2_import_rows').findOne({ id: rawConversionRows[1].id });
    assert.strictEqual(rawSummaryRow.parse_status, 'summary_ignored', 'Raw history summary row status mismatch');
    const rawConversionAudit = await database.collection('v2_audit_log').findOne({
      workspace_id: createdWorkspaceId,
      entity_type: 'raw_history',
      entity_id: createdArchiveWorkspaceId,
      action: 'raw_history_batch_convert',
    });
    assert(rawConversionAudit, 'Raw history batch conversion audit missing');

    const trainingImportSourceId = crypto.randomUUID();
    const trainingRows = [
      {
        id: crypto.randomUUID(),
        import_source_id: trainingImportSourceId,
        sheet_name: 'Smoke',
        row_number: 1,
        raw_json: {
          'описание платежа': 'агент лодка',
          'расход кеш': '10',
          _date_context: { filename_date: '2026-08-12' },
        },
        parse_status: 'imported',
        parse_notes: 'atlas dictionary smoke',
        created_at: new Date().toISOString(),
      },
      {
        id: crypto.randomUUID(),
        import_source_id: trainingImportSourceId,
        sheet_name: 'Smoke',
        row_number: 2,
        raw_json: {
          'описание платежа': 'мой кредит',
          'расход кеш': '20',
          _date_context: { filename_date: '2026-08-12' },
        },
        parse_status: 'imported',
        parse_notes: 'atlas dictionary smoke',
        created_at: new Date().toISOString(),
      },
      {
        id: crypto.randomUUID(),
        import_source_id: trainingImportSourceId,
        sheet_name: 'Smoke',
        row_number: 3,
        raw_json: {
          'описание платежа': 'ареда яхты',
          'приход кеш': '30',
          _date_context: { filename_date: '2026-08-12' },
        },
        parse_status: 'imported',
        parse_notes: 'atlas dictionary smoke',
        created_at: new Date().toISOString(),
      },
      {
        id: crypto.randomUUID(),
        import_source_id: trainingImportSourceId,
        sheet_name: 'Smoke',
        row_number: 4,
        raw_json: {
          'описание платежа': 'доставка фильтра',
          'расход кеш': '15',
          _date_context: { filename_date: '2026-08-12' },
        },
        parse_status: 'imported',
        parse_notes: 'atlas dictionary smoke',
        created_at: new Date().toISOString(),
      },
    ];
    await database.collection('v2_import_sources').insertOne({
      id: trainingImportSourceId,
      workspace_id: createdArchiveWorkspaceId,
      source_type: 'excel',
      file_name: 'atlas-dictionary-training-smoke.xlsx',
      file_url: null,
      file_id: null,
      status: 'accepted',
      include_decision: 'included',
      reason: 'atlas write smoke',
      created_at: new Date().toISOString(),
    });
    await database.collection('v2_import_rows').insertMany(trainingRows);

    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
        source_row_id: trainingRows[0].id,
        decision_type: 'reject_training',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not create dictionary training decision'
    );
    const trainingCountsBefore = {
      rules: await database.collection('v2_category_rules').countDocuments({ workspace_id: createdWorkspaceId }),
      decisions: await database.collection('v2_dictionary_training_decisions').countDocuments({ workspace_id: createdWorkspaceId }),
    };
    const trainingDecision = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
      source_row_id: trainingRows[0].id,
      decision_type: 'approve_existing_guess_local',
      category_code: 'current_boat_expenses',
      pattern: 'агент',
      pattern_type: 'keyword',
      language: 'ru',
      weight: 10,
      requires_any: ['лодка'],
      excludes_any: ['мой', 'личный', 'долг', 'кредит'],
      note: 'atlas smoke local approval',
    });
    assert.strictEqual(trainingDecision.ok, true, 'Dictionary training decision wrapper mismatch');
    assert.strictEqual(trainingDecision.decision.decision_type, 'approve_existing_guess_local', 'Dictionary training approve type mismatch');
    assert.strictEqual(trainingDecision.decision.target_category_code, 'current_boat_expenses', 'Dictionary training target category mismatch');
    assert.strictEqual(trainingDecision.decision.pattern, 'агент', 'Dictionary training pattern mismatch');
    assert.strictEqual(trainingDecision.decision.requires_any[0], 'лодка', 'Dictionary training requires_any mismatch');
    assert.strictEqual(Boolean(trainingDecision.decision.category_rule_id), true, 'Dictionary training approve must create local category rule');
    assert.strictEqual(trainingDecision.decision.category_rule.id, trainingDecision.decision.category_rule_id, 'Dictionary training category rule wrapper mismatch');
    const duplicateTrainingDecision = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
      source_row_id: trainingRows[0].id,
      decision_type: 'approve_existing_guess_local',
      category_code: 'current_boat_expenses',
      pattern: 'агент',
      pattern_type: 'keyword',
      language: 'ru',
      weight: 10,
      requires_any: ['лодка'],
      excludes_any: ['мой', 'личный', 'долг', 'кредит'],
    });
    assert.strictEqual(duplicateTrainingDecision.decision.id, trainingDecision.decision.id, 'Dictionary training duplicate must reuse decision id');
    assert.strictEqual(duplicateTrainingDecision.decision.category_rule_id, trainingDecision.decision.category_rule_id, 'Dictionary training duplicate must reuse category rule');
    assert.strictEqual(
      await database.collection('v2_category_rules').countDocuments({ workspace_id: createdWorkspaceId }),
      trainingCountsBefore.rules + 1,
      'Dictionary training duplicate must not create second category rule'
    );
    assert.strictEqual(
      await database.collection('v2_dictionary_training_decisions').countDocuments({ workspace_id: createdWorkspaceId }),
      trainingCountsBefore.decisions + 1,
      'Dictionary training duplicate must not create second decision'
    );

    const rejectedTrainingDecision = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
      source_row_id: trainingRows[3].id,
      decision_type: 'reject_training',
      note: 'atlas smoke reject mixed context',
    });
    assert.strictEqual(rejectedTrainingDecision.decision.decision_type, 'reject_training', 'Dictionary training reject type mismatch');
    assert.strictEqual(rejectedTrainingDecision.decision.category_rule_id, null, 'Dictionary training reject must not create category rule');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
        source_row_id: trainingRows[1].id,
        decision_type: 'approve_existing_guess_local',
        category_code: 'current_boat_expenses',
        pattern: 'кредит',
        blockers: ['debt_or_return'],
      }),
      /dictionary_training_blocked/,
      'Blocked dictionary training approval must be rejected'
    );
    const universalCandidate = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
      source_row_id: trainingRows[2].id,
      decision_type: 'propose_universal_candidate',
      category_code: 'commercial_income',
      pattern: 'ареда яхты',
    });
    assert.strictEqual(universalCandidate.decision.decision_type, 'propose_universal_candidate', 'Dictionary training universal candidate type mismatch');
    assert.strictEqual(universalCandidate.decision.category_rule_id, null, 'Universal candidate must not create local category rule');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-decisions`, {}, {
        source_row_id: trainingRows[2].id,
        decision_type: 'promote_universal',
        category_code: 'commercial_income',
        pattern: 'ареда яхты',
      }),
      /universal_promotion_not_supported/,
      'Universal promotion must stay unsupported'
    );

    const disabledInternetSettings = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/assistant-settings`, {}, {
      mr_smith_enabled: false,
      internet_reference_mode: 'disabled',
      provider_key: 'stub',
      retention_days: 14,
    });
    assert.strictEqual(disabledInternetSettings.settings.internet_reference_mode, 'disabled', 'Internet reference disabled mode mismatch');
    const internetLookupCountBefore = await database.collection('v2_internet_reference_lookups').countDocuments({ workspace_id: createdWorkspaceId });
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
        lookup_consent: true,
        sanitized_query: 'Marina Porto Montenegro',
      }),
      /internet_reference_disabled/,
      'Internet reference disabled mode must block lookup'
    );
    const perRequestInternetSettings = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/assistant-settings`, {}, {
      mr_smith_enabled: false,
      internet_reference_mode: 'per_request',
      provider_key: 'stub',
      retention_days: 30,
    });
    assert.strictEqual(perRequestInternetSettings.settings.internet_reference_mode, 'per_request', 'Internet reference per-request mode mismatch');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
        sanitized_query: 'Marina Porto Montenegro',
      }),
      /internet_reference_consent_required/,
      'Internet reference per-request mode must require consent'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
        lookup_consent: true,
      }),
      /missing_sanitized_query/,
      'Internet reference must require sanitized query'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
        lookup_consent: true,
        sanitized_query: 'Marina Porto Montenegro',
        raw_text: '-250 Marina Porto Montenegro',
      }),
      /unsafe_internet_reference_payload/,
      'Internet reference must reject raw financial payload'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
        lookup_consent: true,
        sanitized_query: 'Marina Porto Montenegro',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not create internet reference lookup'
    );
    assert.strictEqual(
      await database.collection('v2_internet_reference_lookups').countDocuments({ workspace_id: createdWorkspaceId }),
      internetLookupCountBefore,
      'Failed internet reference requests must not write lookup rows'
    );
    const internetReference = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
      lookup_consent: true,
      sanitized_query: 'Marina Porto Montenegro 250',
      source_row_id: trainingRows[0].id,
    });
    assert.strictEqual(internetReference.ok, true, 'Internet reference wrapper mismatch');
    assert.strictEqual(internetReference.reference.workspace_id, createdWorkspaceId, 'Internet reference workspace mismatch');
    assert.strictEqual(internetReference.reference.source_row_id, trainingRows[0].id, 'Internet reference source row mismatch');
    assert.strictEqual(internetReference.reference.sanitized_query, 'Marina Porto Montenegro', 'Internet reference query sanitization mismatch');
    assert.strictEqual(internetReference.reference.provider_key, 'stub', 'Internet reference provider mismatch');
    assert.strictEqual(internetReference.reference.result_status, 'stub', 'Internet reference result status mismatch');
    assert.strictEqual(internetReference.reference.consent_source, 'request', 'Internet reference consent source mismatch');
    assert.strictEqual(internetReference.reference.matches[0].source_type, 'stub', 'Internet reference stub match mismatch');
    assert.strictEqual(internetReference.reference.no_financial_mutation, true, 'Internet reference no financial mutation flag mismatch');
    assert.strictEqual(
      await database.collection('v2_internet_reference_lookups').countDocuments({ workspace_id: createdWorkspaceId }),
      internetLookupCountBefore + 1,
      'Internet reference must write one lookup row'
    );
    const internetLookups = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference/lookups`, { limit: '5' });
    assert.strictEqual(internetLookups.lookups[0].id, internetReference.reference.lookup_id, 'Internet reference lookup readback id mismatch');
    assert.strictEqual(internetLookups.lookups[0].query_hash, internetReference.reference.query_hash, 'Internet reference lookup readback hash mismatch');
    assert.strictEqual(internetLookups.lookups[0].matches[0].source_type, 'stub', 'Internet reference lookup matches readback mismatch');
    assert.strictEqual(internetLookups.lookups[0].no_financial_mutation, true, 'Internet reference lookup no mutation readback mismatch');
    assert.strictEqual(Date.parse(internetLookups.lookups[0].retention_delete_after) > Date.now(), true, 'Internet reference retention date missing');
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference/lookups/${internetReference.reference.lookup_id}`, {}, {}, 1),
      /missing_verdict/,
      'Internet reference feedback must require verdict'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference/lookups/${internetReference.reference.lookup_id}`, {}, {
        verdict: 'approved',
      }),
      /invalid_verdict/,
      'Internet reference feedback invalid verdict must be rejected'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference/lookups/${internetReference.reference.lookup_id}`, {}, {
        verdict: 'useful',
        raw_text: '-250 Marina Porto Montenegro',
      }),
      /unsafe_internet_reference_feedback_payload/,
      'Internet reference feedback must reject raw financial payload'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${createdArchiveWorkspaceId}/dictionary-training-internet-reference/lookups/${internetReference.reference.lookup_id}`, {}, {
        verdict: 'useful',
      }),
      /internet_reference_lookup_not_found/,
      'Internet reference feedback cross-workspace lookup must be blocked'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/dictionary-training-internet-reference`, {}, {
        lookup_consent: true,
        sanitized_query: 'Marina Porto Montenegro',
        source_row_id: trainingRows[0].id,
      }),
      /dictionary_source_row_not_found/,
      'Internet reference cross-workspace source row must be blocked'
    );
    const internetFeedback = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference/lookups/${internetReference.reference.lookup_id}`, {}, {
      verdict: 'useful',
      match_index: 0,
      note: 'atlas smoke evidence feedback',
    });
    assert.strictEqual(internetFeedback.lookup.id, internetReference.reference.lookup_id, 'Internet reference feedback lookup id mismatch');
    assert.strictEqual(internetFeedback.lookup.selected_match.verdict, 'useful', 'Internet reference feedback verdict mismatch');
    assert.strictEqual(internetFeedback.lookup.selected_match.no_financial_mutation, true, 'Internet reference feedback no finance flag mismatch');
    assert.strictEqual(internetFeedback.lookup.selected_match.no_training_mutation, true, 'Internet reference feedback no training flag mismatch');
    assert.strictEqual(
      await database.collection('v2_internet_reference_lookups').countDocuments({ workspace_id: createdWorkspaceId }),
      internetLookupCountBefore + 1,
      'Internet reference feedback must not create lookup rows'
    );
    const otherWorkspaceReference = await handleApi('POST', `/api/workspaces/${createdArchiveWorkspaceId}/dictionary-training-internet-reference`, {}, {
      lookup_consent: true,
      sanitized_query: 'Marina Porto Montenegro 250',
    });
    assert.strictEqual(otherWorkspaceReference.reference.sanitized_query, 'Marina Porto Montenegro', 'Second workspace internet reference sanitization mismatch');
    assert.notStrictEqual(otherWorkspaceReference.reference.query_hash, internetReference.reference.query_hash, 'Internet reference query hash must be workspace scoped');
    const workspaceConsentSettings = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/assistant-settings`, {}, {
      mr_smith_enabled: true,
      internet_reference_mode: 'workspace_enabled',
      provider_key: 'stub',
      retention_days: 30,
    });
    assert.strictEqual(workspaceConsentSettings.settings.mr_smith_enabled, true, 'Internet reference workspace consent enabled mismatch');
    const workspaceConsentReference = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/dictionary-training-internet-reference`, {}, {
      sanitized_query: 'Porto Montenegro',
    });
    assert.strictEqual(workspaceConsentReference.reference.consent_source, 'workspace_setting', 'Internet reference workspace consent source mismatch');

    const offerCreated = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/accountable-offers`, {}, {
      employee_user_id: inviteUserId,
      amount: '500.00',
      currency: 'eur',
      purpose: 'Atlas accountable offer smoke',
    });
    assert.strictEqual(offerCreated.ok, true, 'Accountable offer create wrapper mismatch');
    assert.strictEqual(offerCreated.offer.employee_user_id, inviteUserId, 'Accountable offer employee mismatch');
    assert.strictEqual(offerCreated.offer.employee_email, inviteEmail, 'Accountable offer email mismatch');
    assert.strictEqual(Number(offerCreated.offer.amount).toFixed(2), '500.00', 'Accountable offer amount mismatch');
    assert.strictEqual(offerCreated.offer.currency, 'EUR', 'Accountable offer currency mismatch');
    assert.strictEqual(offerCreated.offer.status, 'pending_offer', 'Accountable offer status mismatch');
    assert.strictEqual(offerCreated.offer.no_financial_mutation, true, 'Accountable offer must not mutate finance');

    const offerAccepted = await handleApi('POST', `/api/accountable-offers/${offerCreated.offer.id}/accept`, {}, {}, inviteUserId);
    assert.strictEqual(offerAccepted.ok, true, 'Accountable offer accept wrapper mismatch');
    assert.strictEqual(offerAccepted.offer.status, 'accepted_by_employee', 'Accountable offer accept status mismatch');
    assert.strictEqual(offerAccepted.offer.accepted_by, inviteUserId, 'Accountable offer accepted_by mismatch');

    await assert.rejects(
      () => handleApi('POST', `/api/accountable-offers/${offerCreated.offer.id}/accept`, {}, {}, inviteUserId),
      /accountable_offer_not_pending/,
      'Accepted accountable offer must reject repeat accept'
    );

    const employeeOffers = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/accountable-offers`, {}, {}, inviteUserId);
    assert(employeeOffers.offers.some((offer) => offer.id === offerCreated.offer.id && offer.status === 'accepted_by_employee'), 'Accepted accountable offer missing from employee list');

    const reportDraft = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/accountable-reports`, {}, {
      offer_id: offerCreated.offer.id,
      title: 'Atlas accountable report smoke',
      rows: [
        {
          expense_date: '2026-08-12',
          description: 'Smoke provisions',
          amount: '120.50',
          category_code: 'provisions',
          notes: 'First smoke row',
        },
        {
          date: '2026-08-12',
          description: 'Smoke cleaning',
          amount: '30.00',
          category_code: 'cleaning',
          receipt_note: 'Second smoke row',
        },
      ],
    }, inviteUserId);
    assert.strictEqual(reportDraft.ok, true, 'Accountable report draft wrapper mismatch');
    assert.strictEqual(reportDraft.report.offer_id, offerCreated.offer.id, 'Accountable report offer mismatch');
    assert.strictEqual(reportDraft.report.employee_user_id, inviteUserId, 'Accountable report employee mismatch');
    assert.strictEqual(reportDraft.report.status, 'draft', 'Accountable report draft status mismatch');
    assert.strictEqual(Number(reportDraft.report.total_amount).toFixed(2), '150.50', 'Accountable report total mismatch');
    assert.strictEqual(reportDraft.report.row_count, 2, 'Accountable report row count mismatch');
    assert.strictEqual(reportDraft.report.rows.length, 2, 'Accountable report rows missing');
    assert.strictEqual(reportDraft.report.no_financial_mutation, true, 'Accountable report must not mutate finance');

    const reportSubmitted = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/submit`, {}, {}, inviteUserId);
    assert.strictEqual(reportSubmitted.ok, true, 'Accountable report submit wrapper mismatch');
    assert.strictEqual(reportSubmitted.report.status, 'submitted', 'Accountable report submit status mismatch');
    assert.strictEqual(reportSubmitted.report.submitted_by, inviteUserId, 'Accountable report submitted_by mismatch');

    const reviewRows = [
      {
        id: reportSubmitted.report.rows[0].id,
        review_status: 'accepted',
        payment_method: 'cash',
        category_code: 'provisions',
      },
      {
        id: reportSubmitted.report.rows[1].id,
        review_status: 'adjusted',
        accepted_amount: '20.00',
        payment_method: 'cash',
        category_code: 'cleaning',
        review_note: 'Accepted partly for smoke',
      },
    ];
    const reviewPreview = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/review-preview`, {}, {
      rows: reviewRows,
      actual_remaining: '359.50',
    });
    assert.strictEqual(reviewPreview.ok, true, 'Accountable report review preview wrapper mismatch');
    assert.strictEqual(Number(reviewPreview.preview.accepted_total_amount).toFixed(2), '140.50', 'Review preview accepted total mismatch');
    assert.strictEqual(Number(reviewPreview.preview.rejected_total_amount).toFixed(2), '10.00', 'Review preview rejected total mismatch');
    assert.strictEqual(reviewPreview.preview.settlement.status, 'return_due', 'Review preview settlement status mismatch');
    assert.strictEqual(Number(reviewPreview.preview.settlement.return_due_amount).toFixed(2), '359.50', 'Review preview return due mismatch');

    const reportAccepted = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/accept`, {}, {
      rows: reviewRows,
      actual_remaining: '359.50',
      review_note: 'Atlas admin review smoke',
    });
    assert.strictEqual(reportAccepted.ok, true, 'Accountable report accept wrapper mismatch');
    assert.strictEqual(reportAccepted.result.report.status, 'accepted_by_admin', 'Accountable report accept status mismatch');
    assert.strictEqual(Number(reportAccepted.result.report.accepted_total_amount).toFixed(2), '140.50', 'Accountable report accepted total mismatch');
    assert.strictEqual(reportAccepted.result.settlement.status, 'return_due', 'Accountable settlement status mismatch');
    assert.strictEqual(reportAccepted.result.settlement.resolution_status, 'open', 'Accountable settlement resolution mismatch');
    assert.deepStrictEqual(reportAccepted.result.materialized_entries, [], 'Accountable report accept must not materialize entries');

    await assert.rejects(
      () => handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/materialization-preview`, {}, {}, inviteUserId),
      /accountable_report_not_found/,
      'Employee must not preview accountable materialization'
    );
    const materializationPreview = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/materialization-preview`, {}, {});
    assert.strictEqual(materializationPreview.ok, true, 'Accountable materialization preview wrapper mismatch');
    assert.strictEqual(materializationPreview.preview.policy, 'cash_effect_none_category_projection', 'Materialization policy mismatch');
    assert.strictEqual(materializationPreview.preview.eligible_row_count, 2, 'Materialization eligible rows mismatch');
    assert.strictEqual(Number(materializationPreview.preview.projected_total_amount).toFixed(2), '140.50', 'Materialization projected total mismatch');
    assert.strictEqual(Number(materializationPreview.preview.cash_delta).toFixed(2), '0.00', 'Materialization cash delta mismatch');
    assert.strictEqual(Number(materializationPreview.preview.card_delta).toFixed(2), '0.00', 'Materialization card delta mismatch');
    assert(materializationPreview.preview.rows.every((row) => row.cash_effect === 'none'), 'Materialization rows must be cash-effect none');
    assert(materializationPreview.preview.rows.every((row) => typeof row.idempotency_key === 'string' && row.idempotency_key.length === 64), 'Materialization idempotency keys missing');

    const materialized = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/materialize`, {}, {});
    assert.strictEqual(materialized.ok, true, 'Accountable materialize wrapper mismatch');
    assert.strictEqual(materialized.result.materialization.status, 'materialized', 'Accountable materialization status mismatch');
    assert.strictEqual(materialized.result.materialization.entry_count, 2, 'Accountable materialization entry count mismatch');
    assert.strictEqual(materialized.result.created_entries.length, 2, 'Accountable materialization created entries mismatch');
    assert(materialized.result.created_entries.every((entry) => entry.flow.type === 'accountable'), 'Materialized entries must use accountable flow');
    assert(materialized.result.created_entries.every((entry) => entry.entry_type === 'accountable_expense'), 'Materialized entry type mismatch');
    assert(materialized.result.created_entries.every((entry) => entry.source_type === 'accountable_report'), 'Materialized source type mismatch');
    assert(materialized.result.created_entries.every((entry) => entry.balance_after === null), 'Materialized entries must not have live balance');
    assert.strictEqual(Number(materialized.result.created_entries.reduce((sum, entry) => sum + Number(entry.amount || 0), 0)).toFixed(2), '140.50', 'Materialized entry total mismatch');

    const materializationReadback = await handleApi('GET', `/api/accountable-reports/${reportDraft.report.id}/materialization`, {}, {});
    assert.strictEqual(materializationReadback.materialization.status, 'materialized', 'Materialization readback status mismatch');
    assert.strictEqual(materializationReadback.materialization.entry_count, 2, 'Materialization readback entry count mismatch');
    const reportAfterMaterialize = await handleApi('GET', `/api/accountable-reports/${reportDraft.report.id}`, {}, {});
    assert(reportAfterMaterialize.report.rows.every((row) => row.operational_entry_id), 'Materialized report rows must reference operational projection entries');

    const materializedAgain = await handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/materialize`, {}, {});
    assert.strictEqual(materializedAgain.result.created_entries.length, 0, 'Repeat materialize must not duplicate entries');
    assert.strictEqual(materializedAgain.result.materialization.entry_count, 2, 'Repeat materialize must keep existing links');

    await assert.rejects(
      () => handleApi('POST', `/api/accountable-settlements/${reportAccepted.result.settlement.id}/cash-resolve`, {}, {
        date: '2026-08-12',
        raw_text: '+359.50 atlas employee returned cash',
      }, inviteUserId),
      /workspace_admin_required|accountable_report_not_found/,
      'Employee must not resolve accountable settlement'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/accountable-settlements/${reportAccepted.result.settlement.id}/cash-resolve`, {}, {
        date: '2026-08-12',
        raw_text: '-359.50 wrong settlement direction',
      }),
      /settlement_entry_direction_mismatch/,
      'Settlement cash resolve must reject wrong direction'
    );
    const settlementResolved = await handleApi('POST', `/api/accountable-settlements/${reportAccepted.result.settlement.id}/cash-resolve`, {}, {
      date: '2026-08-12',
      raw_text: '+359.50 atlas employee returned cash',
      note: 'Atlas settlement cash resolution smoke',
    });
    assert.strictEqual(settlementResolved.ok, true, 'Settlement cash resolve wrapper mismatch');
    assert.strictEqual(settlementResolved.result.settlement.resolution_status, 'resolved', 'Settlement resolution status mismatch');
    assert.strictEqual(Number(settlementResolved.result.settlement.resolved_amount).toFixed(2), '359.50', 'Settlement resolved amount mismatch');
    assert.strictEqual(settlementResolved.result.settlement.resolved_entry_id, settlementResolved.result.entry.id, 'Settlement resolved entry mismatch');
    assert.strictEqual(settlementResolved.result.entry.flow.type, 'cash', 'Settlement entry flow mismatch');
    assert.strictEqual(settlementResolved.result.entry.direction, 'in', 'Settlement entry direction mismatch');
    assert.strictEqual(settlementResolved.result.entry.category_code, null, 'Settlement entry must be balance-only');
    assert.strictEqual(Number(settlementResolved.result.entry.amount).toFixed(2), '359.50', 'Settlement entry amount mismatch');
    assert.strictEqual(Number(settlementResolved.result.entry.balance_after).toFixed(2), '6532.95', 'Settlement entry balance_after mismatch');
    const createdWorkspaceSummary = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/summary`, {});
    assert.strictEqual(Number(createdWorkspaceSummary.summary.cash_now).toFixed(2), '6532.95', 'Settlement cash resolve summary mismatch');
    const settlementResolvedAgain = await handleApi('POST', `/api/accountable-settlements/${reportAccepted.result.settlement.id}/cash-resolve`, {}, {
      date: '2026-08-12',
      raw_text: '+359.50 repeat should not duplicate',
    });
    assert.strictEqual(settlementResolvedAgain.result.entry, null, 'Repeat settlement resolve must not duplicate cash entry');
    assert.strictEqual(settlementResolvedAgain.result.settlement.resolution_status, 'resolved', 'Repeat settlement resolve status mismatch');

    const entryPreview = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/parse-preview`, {}, {
      flow_id: cashFlow.id,
      date: '2026-08-12',
      raw_text: '-10.00 atlas operational entry smoke',
      category_code: 'other',
    });
    assert.strictEqual(entryPreview.ok, true, 'Entry parse preview wrapper mismatch');
    assert.strictEqual(entryPreview.preview.will_save, false, 'Entry preview must be read-only');
    assert.strictEqual(entryPreview.preview.entry_type, 'cash_expense', 'Entry preview type mismatch');
    assert.strictEqual(entryPreview.preview.category_code, 'other', 'Entry preview category mismatch');

    const legacyEntryPreview = await handleApi('POST', '/api/parse-entry-preview', {}, {
      workspace_id: createdWorkspaceId,
      flow_id: cashFlow.id,
      date: '2026-08-12',
      raw_text: '-10.00 atlas operational entry smoke',
      category_code: 'other',
    });
    assert.strictEqual(legacyEntryPreview.preview.entry_type, 'cash_expense', 'Legacy entry preview route mismatch');

    const entryCreated = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/entries`, {}, {
      flow_id: cashFlow.id,
      date: '2026-08-12',
      raw_text: '-10.00 atlas operational entry smoke',
      category_code: 'other',
      amount: '10.00',
    });
    assert.strictEqual(entryCreated.ok, true, 'Entry create wrapper mismatch');
    assert.strictEqual(entryCreated.entry.flow.type, 'cash', 'Entry create flow mismatch');
    assert.strictEqual(entryCreated.entry.entry_type, 'cash_expense', 'Entry create type mismatch');
    assert.strictEqual(entryCreated.entry.category_code, 'other', 'Entry create category mismatch');
    assert.strictEqual(Number(entryCreated.entry.amount).toFixed(2), '10.00', 'Entry create amount mismatch');
    assert.strictEqual(Number(entryCreated.entry.balance_after).toFixed(2), '6522.95', 'Entry create balance mismatch');

    await assert.rejects(
      () => handleApi('POST', `/api/entries/${entryCreated.entry.id}/attachments`, {}, {
        file_name: '../receipt.png',
        content_base64: Buffer.from('bad').toString('base64'),
      }),
      /invalid_file_name/,
      'Attachment traversal filename must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/entries/${entryCreated.entry.id}/attachments`, {}, {
        file_name: 'receipt.png',
        content_base64: 'not base64!',
      }),
      /invalid_content_base64/,
      'Attachment invalid base64 must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/entries/${entryCreated.entry.id}/attachments`, {}, {
        file_name: 'receipt.txt',
        content_base64: Buffer.from('plain text').toString('base64'),
      }),
      /unsupported_attachment_type/,
      'Attachment unsupported MIME must be rejected'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/entries/${entryCreated.entry.id}/attachments`, {}, {
        file_name: 'viewer.png',
        content_base64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not upload attachment'
    );
    const summaryBeforeAttachment = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/summary`, {});
    const attachmentCreated = await handleApi('POST', `/api/entries/${entryCreated.entry.id}/attachments`, {}, {
      file_name: 'receipt.png',
      image_mode: 'original',
      content_base64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    });
    assert.strictEqual(attachmentCreated.ok, true, 'Attachment create wrapper mismatch');
    assert.strictEqual(attachmentCreated.attachment.entry_id, entryCreated.entry.id, 'Attachment entry id mismatch');
    assert.strictEqual(attachmentCreated.attachment.file_name, 'receipt.png', 'Attachment file name mismatch');
    assert.strictEqual(attachmentCreated.attachment.mime_type, 'image/png', 'Attachment MIME mismatch');
    assert.strictEqual(attachmentCreated.attachment.image_mode, 'original', 'Attachment image mode mismatch');
    assert(attachmentCreated.attachment.file_url.startsWith('storage/v2/attachments/'), 'Attachment storage path mismatch');
    const attachmentPath = path.join(__dirname, '..', attachmentCreated.attachment.file_url);
    assert.strictEqual(fs.existsSync(attachmentPath), true, 'Attachment file missing on disk');
    const attachmentList = await handleApi('GET', `/api/entries/${entryCreated.entry.id}/attachments`, {});
    assert.strictEqual(attachmentList.attachments.length, 1, 'Attachment list count mismatch');
    assert.strictEqual(attachmentList.attachments[0].id, attachmentCreated.attachment.id, 'Attachment list id mismatch');
    const attachmentDeleted = await handleApi('DELETE', `/api/attachments/${attachmentCreated.attachment.id}`, {}, {});
    assert.strictEqual(attachmentDeleted.attachment.deleted, true, 'Attachment delete flag mismatch');
    assert.strictEqual(attachmentDeleted.attachment.file_deleted, true, 'Attachment file delete flag mismatch');
    assert.strictEqual(fs.existsSync(attachmentPath), false, 'Attachment file remains after delete');
    const attachmentListAfterDelete = await handleApi('GET', `/api/entries/${entryCreated.entry.id}/attachments`, {});
    assert.strictEqual(attachmentListAfterDelete.attachments.length, 0, 'Attachment list must be empty after delete');
    await assert.rejects(
      () => handleApi('DELETE', `/api/attachments/${attachmentCreated.attachment.id}`, {}, {}),
      /attachment_not_found/,
      'Missing attachment delete must return not found'
    );
    const attachmentAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'attachment', entity_id: attachmentCreated.attachment.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(attachmentAuditActions.includes('create'), 'Attachment create audit missing');
    assert(attachmentAuditActions.includes('delete'), 'Attachment delete audit missing');
    const summaryAfterAttachment = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/summary`, {});
    assert.strictEqual(Number(summaryAfterAttachment.summary.cash_now).toFixed(2), Number(summaryBeforeAttachment.summary.cash_now).toFixed(2), 'Attachment must not change cash summary');

    const entryUpdated = await handleApi('PATCH', `/api/entries/${entryCreated.entry.id}`, {}, {
      date: '2026-08-12',
      raw_text: '-12.00 atlas operational entry updated',
      category_code: 'cleaning',
      amount: '12.00',
    });
    assert.strictEqual(entryUpdated.ok, true, 'Entry update wrapper mismatch');
    assert.strictEqual(entryUpdated.entry.category_code, 'cleaning', 'Entry update category mismatch');
    assert.strictEqual(Number(entryUpdated.entry.amount).toFixed(2), '12.00', 'Entry update amount mismatch');
    assert.strictEqual(Number(entryUpdated.entry.balance_after).toFixed(2), '6520.95', 'Entry update balance mismatch');

    const entryCategoryUpdated = await handleApi('PATCH', `/api/entries/${entryCreated.entry.id}/category`, {}, {
      category_code: 'tech_parts',
    });
    assert.strictEqual(entryCategoryUpdated.ok, true, 'Entry category patch wrapper mismatch');
    assert.strictEqual(entryCategoryUpdated.entry.category_code, 'tech_parts', 'Entry category patch mismatch');
    assert.strictEqual(entryCategoryUpdated.entry.status, 'recognized', 'Entry category patch should resolve review status');
    assert.strictEqual(Number(entryCategoryUpdated.entry.amount).toFixed(2), '12.00', 'Entry category patch must not change amount');
    assert.strictEqual(Number(entryCategoryUpdated.entry.balance_after).toFixed(2), '6520.95', 'Entry category patch must not change balance');

    async function smokeOperationalReports() {
      const fragmentPreview = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments/preview`, {}, {
      entry_ids: [entryCreated.entry.id, settlementResolved.result.entry.id],
    });
    assert.strictEqual(fragmentPreview.ok, true, 'Operational fragment preview wrapper mismatch');
    assert.strictEqual(fragmentPreview.can_create, true, 'Operational fragment preview lock mismatch');
    assert.strictEqual(fragmentPreview.entry_ids.length, 2, 'Operational fragment preview ids mismatch');
    assert.strictEqual(Number(fragmentPreview.report.totals.ending_cash).toFixed(2), '6520.95', 'Operational fragment preview ending cash mismatch');

    const batchPreview = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/batch-preview`, {}, {
      entry_ids: [entryCreated.entry.id],
    });
    assert.strictEqual(batchPreview.ok, true, 'Report batch preview alias wrapper mismatch');
    assert.strictEqual(batchPreview.preview.entry_ids[0], entryCreated.entry.id, 'Report batch preview ids mismatch');

    const operationalFragmentOne = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments`, {}, {
      title: 'Atlas closed fragment expense',
      entry_ids: [entryCreated.entry.id],
      closed_date: '2026-08-12',
    });
    assert.strictEqual(operationalFragmentOne.ok, true, 'Operational fragment create wrapper mismatch');
    assert.strictEqual(operationalFragmentOne.fragment.title, 'Atlas closed fragment expense', 'Operational fragment title mismatch');
    assert.strictEqual(operationalFragmentOne.fragment.entry_count, 1, 'Operational fragment entry count mismatch');
    assert.strictEqual(Boolean(operationalFragmentOne.fragment.closed_at), true, 'Operational fragment closed_at missing');

    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments`, {}, {
        title: 'Atlas duplicate locked fragment',
        entry_ids: [entryCreated.entry.id],
      }),
      /report_fragment_contains_locked_entries/,
      'Operational fragment duplicate active lock must be rejected'
    );

    const operationalFragmentTwo = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/batches`, {}, {
      title: 'Atlas closed fragment income',
      entry_ids: [settlementResolved.result.entry.id],
      closed_date: '2026-08-12',
    });
    assert.strictEqual(operationalFragmentTwo.ok, true, 'Report batch create alias wrapper mismatch');
    assert.strictEqual(operationalFragmentTwo.report.title, 'Atlas closed fragment income', 'Report batch alias title mismatch');
    assert.strictEqual(Boolean(operationalFragmentTwo.report.closed_at), true, 'Report batch alias closed_at missing');

    const operationalOpenFragment = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments`, {}, {
      title: 'Atlas open accountable projection fragment',
      entry_ids: [materialized.result.created_entries[0].id],
    });
    assert.strictEqual(operationalOpenFragment.fragment.closed_at, null, 'Operational open fragment should stay open');

    const explicitSnapshot = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments/${operationalFragmentOne.fragment.id}/html-snapshots`, {}, {
      comment: 'Atlas explicit fragment html snapshot',
    });
    assert.strictEqual(explicitSnapshot.ok, true, 'Operational fragment html snapshot wrapper mismatch');
    assert.strictEqual(explicitSnapshot.snapshot.batch_id, operationalFragmentOne.fragment.id, 'Operational fragment html snapshot batch mismatch');
    assert.strictEqual(explicitSnapshot.snapshot.html_hash.length, 64, 'Operational fragment html snapshot hash mismatch');
    const snapshotReadback = await handleApi(
      'GET',
      `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments/${operationalFragmentOne.fragment.id}/html-snapshots/${explicitSnapshot.snapshot.id}`,
      {}
    );
    assert(snapshotReadback.snapshot.html_content.includes('<!doctype html>'), 'Operational fragment html snapshot content missing html document');
    assert(snapshotReadback.snapshot.html_content.includes('Категории'), 'Operational fragment html snapshot content missing categories');

    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-packages`, {}, {
        fragment_ids: [operationalFragmentOne.fragment.id, operationalOpenFragment.fragment.id],
      }),
      /report_package_requires_closed_fragments/,
      'Operational package should reject open fragments'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-packages`, {}, {
        fragment_ids: [operationalFragmentOne.fragment.id, operationalFragmentTwo.report.id],
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer must not create operational package'
    );

    const operationalPackage = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-packages`, {}, {
      title: 'Atlas operational package smoke',
      fragment_ids: [operationalFragmentOne.fragment.id, operationalFragmentTwo.report.id],
      comment: 'Atlas package smoke',
    });
    assert.strictEqual(operationalPackage.ok, true, 'Operational package wrapper mismatch');
    assert.strictEqual(operationalPackage.package.package_type, 'operational_fragment_package', 'Operational package type mismatch');
    assert.strictEqual(operationalPackage.package.fragment_count, 2, 'Operational package fragment count mismatch');
    assert.strictEqual(operationalPackage.package.items.length, 2, 'Operational package items missing');
    assert.strictEqual(operationalPackage.package.versions.length, 1, 'Operational package version missing');
    assert(operationalPackage.package.source_entry_ids.includes(entryCreated.entry.id), 'Operational package missing expense source id');
    assert(operationalPackage.package.source_entry_ids.includes(settlementResolved.result.entry.id), 'Operational package missing income source id');
    assert(operationalPackage.package.items.every((item) => item.html_snapshot && item.html_snapshot.id), 'Operational package item snapshot missing');

    const overlappingFragment = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments`, {}, {
      title: 'Atlas overlapping fragment',
      entry_ids: [entryCreated.entry.id, settlementResolved.result.entry.id],
      closed_date: '2026-08-12',
      allow_locked_entries: true,
    });
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/operational-packages`, {}, {
        fragment_ids: [operationalFragmentOne.fragment.id, overlappingFragment.fragment.id],
      }),
      /report_package_overlapping_fragments/,
      'Operational package must reject overlapping source entries'
    );

    const fragmentReturned = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments/${operationalOpenFragment.fragment.id}`, {}, {
      status: 'returned_for_revision',
      title: 'Atlas open fragment returned',
    });
    assert.strictEqual(fragmentReturned.fragment.status, 'returned_for_revision', 'Operational fragment status patch mismatch');
    assert.strictEqual(fragmentReturned.fragment.closed_at, null, 'Returned operational fragment must stay open');
    const fragmentRebuilt = await handleApi('PATCH', `/api/workspaces/${createdWorkspaceId}/reports/operational-fragments/${operationalOpenFragment.fragment.id}`, {}, {
      rebuild_from_entries: true,
      title: 'Atlas open fragment rebuilt',
    });
    assert.strictEqual(fragmentRebuilt.fragment.status, 'created', 'Operational fragment rebuild status mismatch');
    assert.strictEqual(fragmentRebuilt.fragment.title, 'Atlas open fragment rebuilt', 'Operational fragment rebuild title mismatch');

    const operationalReportAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: { $in: ['report_batch', 'report_html_snapshot', 'report_package'] } })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(operationalReportAuditActions.includes('operational_fragment_create'), 'Operational fragment create audit missing');
    assert(operationalReportAuditActions.includes('operational_fragment_update'), 'Operational fragment update audit missing');
    assert(operationalReportAuditActions.includes('operational_fragment_rebuild'), 'Operational fragment rebuild audit missing');
    assert(operationalReportAuditActions.includes('operational_fragment_html_snapshot_create'), 'Operational fragment html snapshot audit missing');
    assert(operationalReportAuditActions.includes('operational_fragment_package_create'), 'Operational package audit missing');
    }

    const closedMonth = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/months/2026/8/close`, {}, {
      comment: 'Atlas write smoke closed month',
    });
    assert.strictEqual(closedMonth.ok, true, 'Month close wrapper mismatch');
    assert.strictEqual(closedMonth.closure.is_closed, true, 'Month close flag mismatch');
    assert.strictEqual(closedMonth.report.is_closed, true, 'Month close report flag mismatch');
    assert.strictEqual(Number(closedMonth.closure.closing_balance).toFixed(2), '6520.95', 'Month close closing balance mismatch');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/layer1-snapshots`, {}, {
        year: 2026,
        month: 8,
      }, readOnlyUserId),
      /workspace_read_only/,
      'Viewer create layer1 snapshot must be rejected'
    );
    const layer1Snapshot = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/layer1-snapshots`, {}, {
      year: 2026,
      month: 8,
      comment: 'Atlas immutable layer1 snapshot v1',
    });
    assert.strictEqual(layer1Snapshot.ok, true, 'Layer1 snapshot wrapper mismatch');
    assert.strictEqual(layer1Snapshot.snapshot.report_type, 'layer1_summary', 'Layer1 snapshot report type mismatch');
    assert.strictEqual(layer1Snapshot.snapshot.status, 'closed', 'Layer1 snapshot should inherit closed month status');
    assert.strictEqual(layer1Snapshot.snapshot.version, 1, 'Layer1 snapshot first version mismatch');
    assert.strictEqual(layer1Snapshot.snapshot.summary.header.period.month_key, '2026-08', 'Layer1 snapshot period mismatch');
    assert.strictEqual(Number(layer1Snapshot.snapshot.summary.totals.ending_cash).toFixed(2), '6520.95', 'Layer1 snapshot ending cash mismatch');
    assert.strictEqual(layer1Snapshot.snapshot.content_hash.length, 64, 'Layer1 snapshot hash mismatch');
    assert(layer1Snapshot.snapshot.source_entry_ids.includes(entryCreated.entry.id), 'Layer1 snapshot missing operational entry source id');
    const viewerLayer1Snapshots = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/reports/layer1-snapshots`, { year: '2026', month: '8' }, {}, readOnlyUserId);
    assert.strictEqual(viewerLayer1Snapshots.snapshots.length, 1, 'Viewer layer1 snapshot list mismatch');
    assert.strictEqual(viewerLayer1Snapshots.snapshots[0].id, layer1Snapshot.snapshot.id, 'Viewer layer1 snapshot id mismatch');

    await assert.rejects(
      () => handleApi('PATCH', `/api/entries/${entryCreated.entry.id}/category`, {}, {
        category_code: 'fuel',
      }),
      /closed_month_requires_decision/,
      'Closed month category patch must require explicit decision'
    );
    const correctionDecision = await handleApi('POST', `/api/entries/${entryCreated.entry.id}/category/closed-month-decision`, {}, {
      decision: 'create_correction',
      category_code: 'fuel',
      reason: 'atlas category smoke correction request',
    });
    assert.strictEqual(correctionDecision.ok, true, 'Closed month create_correction wrapper mismatch');
    assert.strictEqual(correctionDecision.recorded, true, 'Closed month create_correction must be audit-recorded');
    assert.strictEqual(correctionDecision.entry.category_code, 'tech_parts', 'Closed month create_correction must not mutate category');
    const recalculateDecision = await handleApi('POST', `/api/entries/${entryCreated.entry.id}/category/closed-month-decision`, {}, {
      decision: 'recalculate_chain',
      category_code: 'fuel',
    });
    assert.strictEqual(recalculateDecision.ok, true, 'Closed month recalculate wrapper mismatch');
    assert.strictEqual(recalculateDecision.entry.category_code, 'fuel', 'Closed month recalculate must update category');
    assert.strictEqual(Number(recalculateDecision.entry.amount).toFixed(2), '12.00', 'Closed month recalculate must not change amount');
    assert.strictEqual(Number(recalculateDecision.entry.balance_after).toFixed(2), '6520.95', 'Closed month recalculate must not change balance');

    const monthCorrection = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/months/2026/8/correction`, {}, {
      flow_id: cashFlow.id,
      date: '2026-08-12',
      raw_text: '+5.00 atlas month correction',
      reason: 'Atlas write smoke correction',
      reference_entry_id: entryCreated.entry.id,
      source_type: 'manual',
      status: 'recognized',
      entry_type: 'cash_income',
    });
    assert.strictEqual(monthCorrection.ok, true, 'Month correction wrapper mismatch');
    assert.strictEqual(monthCorrection.entry.entry_type, 'correction', 'Month correction type mismatch');
    assert.strictEqual(monthCorrection.entry.status, 'corrected', 'Month correction status mismatch');
    assert.strictEqual(monthCorrection.entry.source_type, 'correction', 'Month correction source mismatch');
    assert.strictEqual(Number(monthCorrection.entry.amount).toFixed(2), '5.00', 'Month correction amount mismatch');
    assert.strictEqual(Number(monthCorrection.entry.balance_after).toFixed(2), '6525.95', 'Month correction balance mismatch');
    const monthlyAfterCorrection = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/reports/monthly`, { year: '2026', month: '8' });
    assert.strictEqual(Number(monthlyAfterCorrection.report.corrections).toFixed(2), '5.00', 'Month correction monthly total mismatch');
    assert.strictEqual(Number(monthlyAfterCorrection.report.external_cash_income).toFixed(2), '359.50', 'Month correction must not become external income');
    assert.strictEqual(Number(monthlyAfterCorrection.report.ending_cash).toFixed(2), '6525.95', 'Month correction monthly ending cash mismatch');
    const snapshotsAfterCorrection = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/reports/layer1-snapshots`, { year: '2026', month: '8' });
    assert.strictEqual(snapshotsAfterCorrection.snapshots.length, 1, 'Layer1 snapshot v1 should remain single before v2 create');
    assert.strictEqual(snapshotsAfterCorrection.snapshots[0].id, layer1Snapshot.snapshot.id, 'Layer1 snapshot v1 id changed');
    assert.strictEqual(snapshotsAfterCorrection.snapshots[0].content_hash, layer1Snapshot.snapshot.content_hash, 'Layer1 snapshot v1 hash changed after correction');
    assert.strictEqual(Number(snapshotsAfterCorrection.snapshots[0].summary.totals.ending_cash).toFixed(2), '6520.95', 'Layer1 snapshot v1 ending cash changed');
    const layer1SnapshotV2 = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/reports/layer1-snapshots`, {}, {
      year: 2026,
      month: 8,
      comment: 'Atlas immutable layer1 snapshot v2 after correction',
    });
    assert.strictEqual(layer1SnapshotV2.snapshot.version, 2, 'Layer1 snapshot second version mismatch');
    assert.notStrictEqual(layer1SnapshotV2.snapshot.id, layer1Snapshot.snapshot.id, 'Layer1 snapshot v2 id should differ');
    assert.notStrictEqual(layer1SnapshotV2.snapshot.content_hash, layer1Snapshot.snapshot.content_hash, 'Layer1 snapshot v2 hash should differ');
    assert.strictEqual(Number(layer1SnapshotV2.snapshot.summary.totals.corrections_total).toFixed(2), '5.00', 'Layer1 snapshot v2 corrections total mismatch');
    assert.strictEqual(Number(layer1SnapshotV2.snapshot.summary.totals.ending_cash).toFixed(2), '6525.95', 'Layer1 snapshot v2 ending cash mismatch');
    assert(layer1SnapshotV2.snapshot.correction_ids.includes(monthCorrection.entry.id), 'Layer1 snapshot v2 missing correction id');
    assert(layer1SnapshotV2.snapshot.source_entry_ids.includes(monthCorrection.entry.id), 'Layer1 snapshot v2 missing correction source id');
    const reopenedMonth = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/months/2026/8/reopen`, {}, {
      comment: '',
    });
    assert.strictEqual(reopenedMonth.ok, true, 'Month reopen wrapper mismatch');
    assert.strictEqual(reopenedMonth.closure.is_closed, false, 'Month reopen flag mismatch');
    assert.strictEqual(reopenedMonth.report.is_closed, false, 'Month reopen report flag mismatch');
    assert.strictEqual(reopenedMonth.report.comment, null, 'Month reopen should clear report comment');
    const correctionDeleted = await handleApi('DELETE', `/api/entries/${monthCorrection.entry.id}`, {}, {});
    assert.strictEqual(correctionDeleted.entry.archived, true, 'Month correction cleanup delete mismatch');
    await smokeOperationalReports();

    await assert.rejects(
      () => handleApi('PATCH', `/api/entries/${materialized.result.created_entries[0].id}`, {}, {
        raw_text: '-1.00 should not edit projection',
      }),
      /accountable_projection_entry_immutable/,
      'Accountable projection entries must remain immutable'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/entries/${materialized.result.created_entries[0].id}/category`, {}, {
        category_code: 'fuel',
      }),
      /accountable_projection_entry_immutable/,
      'Accountable projection category route must remain immutable'
    );

    const entryDeleted = await handleApi('DELETE', `/api/entries/${entryCreated.entry.id}`, {}, {
      report_fragment_decision: 'recalculate_fragment',
    });
    assert.strictEqual(entryDeleted.ok, true, 'Entry delete wrapper mismatch');
    assert.strictEqual(entryDeleted.entry.archived, true, 'Entry delete archived mismatch');
    await assert.rejects(
      () => handleApi('PATCH', `/api/entries/${entryCreated.entry.id}`, {}, { raw_text: '-13.00 deleted entry update' }),
      /entry_not_found/,
      'Deleted entry must not be editable'
    );
    const summaryAfterEntryDelete = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/summary`, {});
    assert.strictEqual(Number(summaryAfterEntryDelete.summary.cash_now).toFixed(2), '6532.95', 'Entry delete must restore cash summary');

    await assert.rejects(
      () => handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/submit`, {}, {}, inviteUserId),
      /accountable_report_not_draft/,
      'Submitted accountable report must reject repeat submit'
    );
    await assert.rejects(
      () => handleApi('POST', `/api/accountable-reports/${reportDraft.report.id}/accept`, {}, { rows: reviewRows }),
      /accountable_report_not_submitted/,
      'Accepted accountable report must reject repeat admin accept'
    );

    const employeeReports = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/accountable-reports`, {}, {}, inviteUserId);
    assert(employeeReports.reports.some((report) => report.id === reportDraft.report.id && report.status === 'accepted_by_admin'), 'Accepted accountable report missing from employee list');

    await assert.rejects(
      () => handleApi('POST', '/api/workspace-invites/accept', {}, { token: inviteCreated.invite.token }, inviteUserId),
      /invite_already_accepted/,
      'Accepted invite must reject repeat accept'
    );

    const revokeInvite = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/invites`, {}, {
      invited_email: `revoke-${inviteEmail}`,
      expires_days: 30,
    });
    const revoked = await handleApi('POST', `/api/workspaces/${createdWorkspaceId}/invites/${revokeInvite.invite.id}/revoke`, {}, {});
    assert.strictEqual(revoked.ok, true, 'Invite revoke wrapper mismatch');
    assert.strictEqual(revoked.invite.status, 'revoked', 'Invite revoke status mismatch');
    await assert.rejects(
      () => handleApi('POST', `/api/workspaces/${createdWorkspaceId}/invites/${revokeInvite.invite.id}/revoke`, {}, {}),
      /invite_not_pending/,
      'Revoked invite must reject repeat revoke'
    );

    const invites = await handleApi('GET', `/api/workspaces/${createdWorkspaceId}/invites`, {});
    assert(invites.invites.some((invite) => invite.id === inviteCreated.invite.id && invite.status === 'accepted'), 'Accepted invite missing from list');
    assert(invites.invites.some((invite) => invite.id === revokeInvite.invite.id && invite.status === 'revoked'), 'Revoked invite missing from list');

    const inviteAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'workspace_invite' })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(inviteAuditActions.includes('create'), 'Invite create audit missing');
    assert(inviteAuditActions.includes('accept'), 'Invite accept audit missing');
    assert(inviteAuditActions.includes('revoke'), 'Invite revoke audit missing');

    const flowAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'flow', entity_id: extraFlow.flow.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(flowAuditActions.includes('create'), 'Flow create audit missing');

    const categoryRuleAudit = await database.collection('v2_audit_log').findOne({
      workspace_id: createdWorkspaceId,
      entity_type: 'category_rule',
      entity_id: categoryRule.category_rule.id,
      action: 'create',
    });
    assert(categoryRuleAudit, 'Category rule create audit missing');
    assert.strictEqual(categoryRuleAudit.before_json, null, 'Category rule audit before must be null');
    assert(categoryRuleAudit.after_json.includes('atlas dictionary smoke'), 'Category rule audit after missing rule payload');

    const trainingDecisionAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'dictionary_training_decision', entity_id: trainingDecision.decision.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(trainingDecisionAuditActions.includes('create'), 'Dictionary training decision create audit missing');
    assert(trainingDecisionAuditActions.includes('update'), 'Dictionary training decision duplicate update audit missing');

    const offerAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'accountable_offer', entity_id: offerCreated.offer.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(offerAuditActions.includes('create'), 'Accountable offer create audit missing');
    assert(offerAuditActions.includes('accept_by_employee'), 'Accountable offer accept audit missing');

    const reportAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'accountable_report', entity_id: reportDraft.report.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(reportAuditActions.includes('create_draft'), 'Accountable report draft audit missing');
    assert(reportAuditActions.includes('submit'), 'Accountable report submit audit missing');
    assert(reportAuditActions.includes('accept_by_admin'), 'Accountable report admin accept audit missing');
    assert(reportAuditActions.includes('ledger_project'), 'Accountable report materialization audit missing');

    const settlementAuditActions = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'accountable_settlement', entity_id: reportAccepted.result.settlement.id })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(settlementAuditActions.includes('resolve_physical_cash'), 'Settlement cash resolve audit missing');

    const deleted = await handleApi('DELETE', `/api/workspaces/${createdWorkspaceId}`, {}, {});
    assert.strictEqual(deleted.workspace.archived, true, 'Workspace delete archived flag mismatch');
    assert.strictEqual(deleted.workspace.trash_retention_days, 60, 'Workspace delete retention mismatch');

    await assert.rejects(
      () => handleApi('GET', `/api/workspaces/${createdWorkspaceId}`, {}),
      /workspace_not_found/,
      'Archived workspace must be hidden from direct GET'
    );
    const listedAfterDelete = await handleApi('GET', '/api/workspaces', {});
    assert.strictEqual(
      listedAfterDelete.workspaces.some((workspace) => workspace.id === createdWorkspaceId),
      false,
      'Archived workspace must be hidden from Hall list'
    );

    const workspaceAuditActionsAfterDelete = (await database.collection('v2_audit_log')
      .find({ workspace_id: createdWorkspaceId, entity_type: 'workspace', entity_id: createdWorkspaceId })
      .sort({ created_at: 1, id: 1 })
      .toArray()).map((row) => row.action);
    assert(workspaceAuditActionsAfterDelete.includes('delete_to_trash'), 'Workspace delete audit missing');

    const patched = await handleApi('PATCH', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {}, {
      mr_smith_enabled: true,
      internet_reference_mode: 'disabled',
      provider_key: 'stub',
      retention_days: 7,
    });
    assert.strictEqual(patched.ok, true, 'Assistant settings PATCH wrapper mismatch');
    assert.strictEqual(patched.settings.workspace_id, WRITE_TEST_WORKSPACE_ID, 'Assistant settings workspace mismatch');
    assert.strictEqual(patched.settings.mr_smith_enabled, true, 'Assistant enabled mismatch');
    assert.strictEqual(patched.settings.internet_reference_mode, 'disabled', 'Assistant mode mismatch');
    assert.strictEqual(patched.settings.provider_key, 'stub', 'Assistant provider mismatch');
    assert.strictEqual(patched.settings.retention_days, 7, 'Assistant retention mismatch');
    assert.strictEqual(patched.settings.updated_by, 1, 'Assistant updated_by mismatch');

    const readback = await handleApi('GET', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {});
    assert.deepStrictEqual(readback.settings, patched.settings, 'Assistant settings readback mismatch');

    const auditRow = await database.collection('v2_audit_log').findOne(
      {
        workspace_id: WRITE_TEST_WORKSPACE_ID,
        entity_type: 'workspace_assistant_settings',
        entity_id: WRITE_TEST_WORKSPACE_ID,
        action: 'update',
      },
      { sort: { created_at: -1, id: -1 } }
    );
    assert(auditRow, 'Assistant settings audit row missing');
    assert(auditRow.before_json, 'Assistant settings audit before missing');
    assert(auditRow.after_json, 'Assistant settings audit after missing');
    assert.strictEqual(Number(auditRow.performed_by), 1, 'Assistant settings audit performed_by mismatch');
    assert.strictEqual(
      await database.collection('v2_audit_log').countDocuments({
        workspace_id: WRITE_TEST_WORKSPACE_ID,
        entity_type: 'workspace_assistant_settings',
        entity_id: WRITE_TEST_WORKSPACE_ID,
        action: 'update',
      }) > auditCountBefore,
      true,
      'Assistant settings audit count did not increase'
    );

    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {}, { internet_reference_mode: 'always_on' }),
      /invalid_internet_reference_mode/,
      'Invalid assistant mode must be rejected'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {}, { provider_key: 'internet_anywhere' }),
      /invalid_provider_key/,
      'Invalid assistant provider must be rejected'
    );
    await assert.rejects(
      () => handleApi('PATCH', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {}, { retention_days: 'two' }),
      /invalid_retention_days/,
      'Invalid retention must be rejected'
    );
  } finally {
    if (beforeRaw) {
      await handleApi('PATCH', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {}, before.settings);
    } else {
      await settingsCollection.deleteOne({ workspace_id: WRITE_TEST_WORKSPACE_ID });
    }
    await cleanupWorkspaceFixture(database, createdArchiveWorkspaceId);
    await cleanupWorkspaceFixture(database, createdWorkspaceId);
    await database.collection('users').deleteOne({ id: inviteUserId, email: inviteEmail });
  }

  const restored = await handleApi('GET', `/api/workspaces/${WRITE_TEST_WORKSPACE_ID}/assistant-settings`, {});
  assert.deepStrictEqual(restored.settings, before.settings, 'Assistant settings restore mismatch');
  const financeAfter = await financeSnapshot(database);
  assert.deepStrictEqual(financeAfter, financeBefore, 'Finance snapshot changed during assistant settings write smoke');

  console.log(JSON.stringify({
    ok: true,
    workspace_id: WRITE_TEST_WORKSPACE_ID,
    write_routes: [
      'POST /api/workspaces',
      'PATCH /api/workspaces/:workspaceId',
      'DELETE /api/workspaces/:workspaceId',
      'POST /api/workspaces/:workspaceId/flows',
      'POST /api/workspaces/:workspaceId/category-rules',
      'POST /api/workspaces/:workspaceId/dictionary-training-decisions',
      'POST /api/workspaces/:workspaceId/dictionary-training-internet-reference',
      'PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId',
      'POST /api/workspaces/:workspaceId/imports/excel',
      'POST /api/workspaces/:workspaceId/imports/:importId/accept',
      'POST /api/entries/:entryId/attachments',
      'DELETE /api/attachments/:attachmentId',
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
    ],
    restored: true,
    audit_created: true,
    workspace_fixture_cleaned: true,
    finance_snapshot: financeAfter,
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
