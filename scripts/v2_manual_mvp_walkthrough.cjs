const { chromium } = require('playwright-core');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_MANUAL_BASE || 'http://127.0.0.1:18888';
const email = process.env.FINDESK_V2_MANUAL_EMAIL || 'vetus.nauta@gmail.com';
const chrome = process.env.FINDESK_V2_MANUAL_CHROME || '/usr/bin/google-chrome';
const marker = process.env.FINDESK_V2_MANUAL_MARKER || `MANUAL_MVP_WALKTHROUGH_${Date.now()}`;
const resultsDir = process.env.FINDESK_V2_MANUAL_RESULTS || path.join(process.cwd(), 'test-results', 'v2-manual-mvp-walkthrough', marker);
const rawTextInputSelector = '[data-v2-entry-form] [data-v2-raw-text]';

const steps = [];
const createdEntryIds = new Set();
let cleanupUsed = false;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function routeFromRequest(request) {
  const url = new URL(request.url());
  return url.searchParams.get('route') || '';
}

function record(step, status = 'ok', detail = {}) {
  const row = { step, status, detail, at: new Date().toISOString() };
  steps.push(row);
  console.log(`${status.toUpperCase()} ${step}${Object.keys(detail).length ? ` ${JSON.stringify(detail)}` : ''}`);
}

function dbJson(action) {
  const php = `
require 'app/auth.php';
$pdo = ql_db();
$action = getenv('ACTION');
$marker = getenv('MARKER');
if ($action === 'marker_count') {
  $stmt = $pdo->prepare("
    SELECT
      SUM(archived_at IS NULL) AS active_count,
      COUNT(*) AS all_count
    FROM v2_entries
    WHERE raw_text LIKE ?
  ");
  $stmt->execute(['%' . $marker . '%']);
  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['active_count' => 0, 'all_count' => 0];
  echo json_encode([
    'active_count' => (int)$row['active_count'],
    'all_count' => (int)$row['all_count'],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($action === 'purge_marker') {
  $stmt = $pdo->prepare("SELECT id FROM v2_entries WHERE raw_text LIKE ?");
  $stmt->execute(['%' . $marker . '%']);
  $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $auditRows = 0;
  $entryRows = 0;
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $deleteAudit = $pdo->prepare("DELETE FROM v2_audit_log WHERE entity_id IN ($in)");
    $deleteAudit->execute($ids);
    $auditRows += $deleteAudit->rowCount();
  }
  $deleteAuditByText = $pdo->prepare("DELETE FROM v2_audit_log WHERE before_json LIKE ? OR after_json LIKE ?");
  $deleteAuditByText->execute(['%' . $marker . '%', '%' . $marker . '%']);
  $auditRows += $deleteAuditByText->rowCount();
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $deleteEntries = $pdo->prepare("DELETE FROM v2_entries WHERE id IN ($in)");
    $deleteEntries->execute($ids);
    $entryRows += $deleteEntries->rowCount();
  }
  echo json_encode(['entry_rows' => $entryRows, 'audit_rows' => $auditRows, 'ids' => $ids], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($action === 'claudia_workspace') {
  $stmt = $pdo->prepare("SELECT id, name FROM v2_workspaces WHERE name = 'Claudia Z' LIMIT 1");
  $stmt->execute();
  echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: null, JSON_UNESCAPED_UNICODE);
  exit;
}
if ($action === 'month_counts') {
  $stmt = $pdo->prepare("
    SELECT DATE_FORMAT(date, '%Y-%m') AS month_key, COUNT(*) AS entries
    FROM v2_entries
    WHERE workspace_id = (SELECT id FROM v2_workspaces WHERE name = 'Claudia Z' LIMIT 1)
    GROUP BY month_key
    ORDER BY month_key
  ");
  $stmt->execute();
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
  exit;
}
throw new RuntimeException('unknown action');
`;
  const out = execFileSync('php', ['-r', php], {
    cwd: process.cwd(),
    env: { ...process.env, ACTION: action, MARKER: marker },
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
  return JSON.parse(out || 'null');
}

async function waitForText(page, selector, text, timeout = 10000) {
  await page.waitForFunction(
    ({ selector: targetSelector, text: targetText }) => {
      const node = document.querySelector(targetSelector);
      return Boolean(node && node.textContent && node.textContent.includes(targetText));
    },
    { selector, text },
    { timeout }
  );
}

async function screenshot(page, name) {
  const file = path.join(resultsDir, `${String(steps.length).padStart(2, '0')}-${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  return path.relative(process.cwd(), file);
}

async function v2BrowserApi(page, method, route, body = null, query = {}) {
  return page.evaluate(async ({ method, route, body, query }) => {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-FinDesk-V2-Request': 'fetch',
      },
      body: body == null ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    return { status: response.status, data };
  }, { method, route, body, query });
}

async function signIn(page) {
  await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-auth-form]').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('[data-v2-auth-email]').fill(email);
  await page.locator('[data-v2-auth-send]').click();
  await page.locator('[data-v2-auth-code-block]').waitFor({ state: 'visible', timeout: 10000 });
  const code = await page.locator('[data-v2-auth-code]').inputValue();
  assert(/^\d{6}$/.test(code), `local auth code was not available for ${email}`);
  await page.locator('[data-v2-auth-verify]').click();
  await page.locator('[data-v2-workspace-select]').waitFor({ state: 'visible', timeout: 10000 });
  record('email-code auth', 'ok', { email });
}

async function selectClaudiaWorkspace(page) {
  const select = page.locator('[data-v2-workspace-select]');
  await select.selectOption({ label: 'Claudia Z' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-status]', 'Ready');
  const selected = await select.locator('option:checked').innerText();
  assert(selected.trim() === 'Claudia Z', `wrong workspace selected: ${selected}`);
  record('workspace selection', 'ok', { workspace: selected.trim() });
}

async function ensureOperational(page) {
  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
}

async function ensureCreateEntryMode(page) {
  await ensureOperational(page);
  const submit = page.locator('[data-v2-submit]');
  await submit.waitFor({ state: 'attached', timeout: 10000 });
  if (await page.locator('[data-v2-entry-form].is-previewing').count()) {
    await page.keyboard.press('Escape');
  }
  if ((await submit.innerText()).trim() === 'Update') {
    await page.locator(rawTextInputSelector).fill('');
  }
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-submit]');
    return button && button.textContent && button.textContent.trim() === 'Save';
  }, null, { timeout: 5000 });
}

async function saveEntry(page, date, rawText) {
  await ensureCreateEntryMode(page);
  await page.locator('[data-v2-date]').fill(date);
  await page.locator(rawTextInputSelector).fill(rawText);
  const responsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/entries');
  });
  await page.locator('[data-v2-submit]').click();
  const response = await responsePromise;
  assert(response.status() === 200, `entry save failed: HTTP ${response.status()}`);
  const data = await response.json();
  assert(data.entry && data.entry.id, 'entry save response did not return an entry id');
  createdEntryIds.add(data.entry.id);
  await waitForText(page, '[data-v2-feed]', rawText);
  await waitForText(page, '[data-v2-check-table]', rawText);
  return data.entry;
}

async function editEntry(page, oldText, newText) {
  await ensureOperational(page);
  const row = page.locator('[data-v2-entry-select]', { hasText: oldText }).first();
  await row.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator(rawTextInputSelector).fill(newText);
  const responsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  await page.locator('[data-v2-submit]').click();
  const response = await responsePromise;
  assert(response.status() === 200, `entry edit failed: HTTP ${response.status()}`);
  const data = await response.json();
  if (data.entry && data.entry.id) createdEntryIds.add(data.entry.id);
  await waitForText(page, '[data-v2-feed]', newText);
  await waitForText(page, '[data-v2-check-table]', newText);
  return data.entry;
}

async function deleteEntry(page, text) {
  await ensureOperational(page);
  const row = page.locator('[data-v2-entry-select]', { hasText: text }).first();
  await row.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  const del = page.locator('[data-v2-entry-delete]');
  await del.click();
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-entry-delete]');
    return button && button.textContent && button.textContent.trim() === 'Delete?';
  }, null, { timeout: 5000 });
  const responsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'DELETE'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  await del.click();
  const response = await responsePromise;
  assert(response.status() === 200, `entry delete failed: HTTP ${response.status()}`);
  await page.waitForFunction((needle) => {
    const feed = document.querySelector('[data-v2-feed]');
    const check = document.querySelector('[data-v2-check-table]');
    return feed && check && !feed.textContent.includes(needle) && !check.textContent.includes(needle);
  }, text, { timeout: 10000 });
}

async function openMonth(page, year, month) {
  await ensureOperational(page);
  await page.locator('[data-v2-archive-open]').click();
  await page.locator('[data-v2-archive-layer]').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-archive-year]').selectOption(String(year));
  await page.locator('[data-v2-archive-month]').selectOption(String(month));
  await page.locator('[data-v2-archive-load]').click();
  await page.locator('[data-v2-archive-layer]').waitFor({ state: 'hidden', timeout: 5000 });
  await page.waitForFunction(({ year, month }) => {
    const node = document.querySelector('[data-v2-month]');
    if (!node) return false;
    const label = node.textContent || '';
    const expected = new Date(year, month - 1, 1).toLocaleString('en', { month: 'short' });
    return label.includes('Archive') && label.includes(String(year)) && label.includes(expected);
  }, { year, month }, { timeout: 10000 });
  const label = await page.locator('[data-v2-month]').innerText();
  record('open archive month', 'ok', { year, month, label });
}

async function returnCurrentMonth(page) {
  await ensureOperational(page);
  await page.locator('[data-v2-current-month]').click();
  await page.waitForFunction(() => {
    const node = document.querySelector('[data-v2-month]');
    const text = node ? (node.textContent || '') : '';
    return text && !text.includes('Archive');
  }, null, { timeout: 10000 });
  const label = await page.locator('[data-v2-month]').innerText();
  record('return current month', 'ok', { label });
}

async function runSummaryTabs(page) {
  await page.locator('[data-v2-screen="summary"]').click();
  await page.locator('[data-v2-summary-screen]').waitFor({ state: 'visible', timeout: 10000 });
  for (const tab of ['information', 'sending', 'printing', 'storage']) {
    await page.locator(`[data-v2-summary-tab="${tab}"]`).click();
    await page.locator(`[data-v2-summary-panel="${tab}"]`).waitFor({ state: 'visible', timeout: 10000 });
    if (tab === 'information') {
      await waitForText(page, '[data-v2-layer1-information]', 'Period result', 15000);
    }
    if (tab === 'storage') {
      await page.locator('[data-v2-layer1-storage-refresh]').click();
      await page.waitForTimeout(300);
    }
    record(`summary tab ${tab}`, 'ok', { screenshot: await screenshot(page, `summary-${tab}`) });
  }
}

async function runTrainingTransition(page) {
  await page.locator('[data-v2-screen="training"]').click();
  await page.locator('[data-v2-training-screen]').waitFor({ state: 'visible', timeout: 10000 });
  record('training transition', 'ok', { screenshot: await screenshot(page, 'training') });
  await ensureOperational(page);
  record('operational return', 'ok', { screenshot: await screenshot(page, 'operational-return') });
}

async function sourceTraceContains(page, sourceKey, needle) {
  await page.locator('[data-v2-screen="summary"]').click();
  await page.locator('[data-v2-summary-tab="information"]').click();
  await page.locator('[data-v2-layer1-summary-refresh]').click();
  await waitForText(page, '[data-v2-layer1-information]', 'Period result', 15000);
  const button = page.locator(`[data-v2-source-total="${sourceKey}"]`).first();
  if (!(await button.count())) return false;
  await button.click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForTimeout(500);
  const body = await page.locator('[data-v2-source-body]').innerText();
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 5000 });
  return body.includes(needle);
}

async function cleanupCreatedEntries(page) {
  if (!page || createdEntryIds.size === 0) return;
  for (const id of Array.from(createdEntryIds)) {
    const response = await v2BrowserApi(page, 'DELETE', `/api/entries/${id}`, { closed_month_decision: 'recalculate_chain' });
    if (response.status === 200 || response.status === 404) {
      cleanupUsed = true;
    }
  }
}

async function main() {
  fs.mkdirSync(resultsDir, { recursive: true });
  const workspace = dbJson('claudia_workspace');
  assert(workspace && workspace.id, 'Claudia Z workspace was not found in local DB');
  const beforeMarkerCount = dbJson('marker_count');
  assert(beforeMarkerCount.active_count === 0, `active test marker already exists in DB: ${marker}`);
  record('preflight DB', 'ok', { workspace, month_counts: dbJson('month_counts') });

  const browser = await chromium.launch({
    executablePath: chrome,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  let page = null;
  try {
    const context = await browser.newContext({ baseURL: base, viewport: { width: 1440, height: 900 } });
    page = await context.newPage();
    await signIn(page);
    await selectClaudiaWorkspace(page);
    record('desktop opened', 'ok', { screenshot: await screenshot(page, 'desktop-operational') });

    await runSummaryTabs(page);
    await runTrainingTransition(page);

    const currentInitial = `+12 ${marker} current initial`;
    const currentEdited = `+34 ${marker} current edited`;
    await saveEntry(page, '2026-07-09', currentInitial);
    record('current entry create', 'ok', { text: currentInitial, screenshot: await screenshot(page, 'current-created') });
    assert(await sourceTraceContains(page, 'cash_income', currentInitial), 'current entry was not visible in Summary source trace after create');
    record('current entry included in report', 'ok', { source: 'cash_income' });

    await editEntry(page, currentInitial, currentEdited);
    record('current entry edit', 'ok', { text: currentEdited, screenshot: await screenshot(page, 'current-edited') });
    assert(await sourceTraceContains(page, 'cash_income', currentEdited), 'current entry edit was not visible in Summary source trace');
    record('current edit reflected in report', 'ok', { source: 'cash_income' });

    await deleteEntry(page, currentEdited);
    record('current entry delete', 'ok', { screenshot: await screenshot(page, 'current-deleted') });
    assert(!(await sourceTraceContains(page, 'cash_income', marker)), 'deleted current entry still appears in Summary source trace');
    record('current delete removed from report', 'ok');

    await openMonth(page, 2026, 6);
    const archiveInitial = `-7 ${marker} archive june initial`;
    const archiveEdited = `-9 ${marker} archive june edited`;
    await saveEntry(page, '2026-06-30', archiveInitial);
    record('archive entry create', 'ok', { text: archiveInitial, screenshot: await screenshot(page, 'archive-created') });
    assert(await sourceTraceContains(page, 'cash_expense', archiveInitial), 'archive entry was not visible in Summary source trace after create');
    record('archive entry included in report', 'ok', { source: 'cash_expense' });

    await editEntry(page, archiveInitial, archiveEdited);
    record('archive entry edit', 'ok', { text: archiveEdited, screenshot: await screenshot(page, 'archive-edited') });
    assert(await sourceTraceContains(page, 'cash_expense', archiveEdited), 'archive entry edit was not visible in Summary source trace');
    record('archive edit reflected in report', 'ok', { source: 'cash_expense' });

    await deleteEntry(page, archiveEdited);
    record('archive entry delete', 'ok', { screenshot: await screenshot(page, 'archive-deleted') });
    assert(!(await sourceTraceContains(page, 'cash_expense', marker)), 'deleted archive entry still appears in Summary source trace');
    record('archive delete removed from report', 'ok');

    await returnCurrentMonth(page);
    const afterMarkerCount = dbJson('marker_count');
    assert(afterMarkerCount.active_count === 0, `active marker rows left in DB after UI cleanup: ${afterMarkerCount.active_count}`);
    record('postflight DB clean', 'ok', { marker_count: afterMarkerCount, cleanup_used: cleanupUsed });
    const purge = dbJson('purge_marker');
    record('test marker hard purge', 'ok', purge);
  } catch (error) {
    if (page) {
      try {
        await screenshot(page, 'failure');
        await cleanupCreatedEntries(page);
      } catch (cleanupError) {
        record('fallback cleanup failed', 'fail', { error: cleanupError.message });
      }
    }
    throw error;
  } finally {
    await browser.close();
    const purge = dbJson('purge_marker');
    const after = dbJson('marker_count');
    fs.writeFileSync(path.join(resultsDir, 'manual-walkthrough-report.json'), JSON.stringify({
      marker,
      base,
      email,
      db_marker_count_after: after,
      final_purge: purge,
      cleanup_used: cleanupUsed,
      steps,
    }, null, 2));
  }
}

main().catch((error) => {
  record('manual walkthrough failed', 'fail', { error: error.message });
  console.error(error.stack || error.message);
  process.exit(1);
});
