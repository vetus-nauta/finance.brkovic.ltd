const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_PROD_BASE || 'https://finance.brkovic.ltd';
const sessionCookieName = process.env.FINDESK_V2_PROD_COOKIE_NAME || 'ql_session';
const sessionToken = process.env.FINDESK_V2_PROD_SESSION_TOKEN || '';
const chrome = process.env.FINDESK_V2_MANUAL_CHROME || '/usr/bin/google-chrome';
const marker = process.env.FINDESK_V2_MANUAL_MARKER || `PROD_MANUAL_WALKTHROUGH_${Date.now()}`;
const resultsDir = process.env.FINDESK_V2_MANUAL_RESULTS
  || path.join(process.cwd(), 'test-results', 'v2-production-manual-walkthrough', marker);
const rawTextInputSelector = '[data-v2-entry-form] [data-v2-raw-text]';

const steps = [];
const createdEntryIds = new Set();

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

async function screenshot(page, name) {
  const file = path.join(resultsDir, `${String(steps.length).padStart(2, '0')}-${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  return path.relative(process.cwd(), file);
}

function textAlternatives(text) {
  return Array.isArray(text) ? text : [text];
}

async function waitForText(page, selector, text, timeout = 10000) {
  await page.waitForFunction(
    ({ selector: targetSelector, texts }) => {
      const node = document.querySelector(targetSelector);
      const content = node && node.textContent ? node.textContent : '';
      return texts.some((targetText) => content.includes(targetText));
    },
    { selector, texts: textAlternatives(text) },
    { timeout }
  );
}

function monthPartsFromOffset(offsetMonths = 0) {
  const date = new Date();
  date.setMonth(date.getMonth() + offsetMonths);
  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  return {
    year,
    month,
    entryDate: `${year}-${String(month).padStart(2, '0')}-${offsetMonths ? '28' : '09'}`,
    ruMonth: new Date(year, month - 1, 1).toLocaleString('ru-RU', { month: 'short' }),
    enMonth: new Date(year, month - 1, 1).toLocaleString('en', { month: 'short' }),
  };
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

async function selectClaudiaWorkspace(page) {
  await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-workspace-select]').waitFor({ state: 'visible', timeout: 15000 });
  await page.locator('[data-v2-workspace-select]').selectOption({ label: 'Claudia Z' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 15000 });
  await waitForText(page, '[data-v2-status]', ['Ready', 'Готово'], 15000);
  const selected = (await page.locator('[data-v2-workspace-select] option:checked').innerText()).trim();
  assert(selected === 'Claudia Z', `wrong workspace selected: ${selected}`);
  record('workspace selection', 'ok', { workspace: selected });
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
  if (['Update', 'Обновить'].includes((await submit.innerText()).trim())) {
    await page.locator(rawTextInputSelector).fill('');
  }
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-submit]');
    const text = button && button.textContent ? button.textContent.trim() : '';
    return ['Save', 'Сохранить'].includes(text);
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
  await row.waitFor({ state: 'visible', timeout: 10000 });
  const entryId = await row.getAttribute('data-v2-entry-id');
  assert(entryId, `entry id missing for delete row: ${text}`);
  const response = await v2BrowserApi(page, 'DELETE', `/api/entries/${entryId}`, { closed_month_decision: 'recalculate_chain' });
  assert(response.status === 200, `entry delete failed: HTTP ${response.status}`);
  createdEntryIds.delete(entryId);
  await page.locator('[data-v2-refresh]').click();
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
    const expectedEn = new Date(year, month - 1, 1).toLocaleString('en', { month: 'short' });
    const expectedRu = new Date(year, month - 1, 1).toLocaleString('ru-RU', { month: 'short' });
    return (label.includes('Archive') || label.includes('Архив'))
      && label.includes(String(year))
      && (label.includes(expectedEn) || label.includes(expectedRu));
  }, { year, month }, { timeout: 10000 });
  record('open archive month', 'ok', { year, month, label: await page.locator('[data-v2-month]').innerText() });
}

async function returnCurrentMonth(page) {
  await ensureOperational(page);
  const currentButton = page.locator('[data-v2-current-month]');
  if (await currentButton.isVisible().catch(() => false)) {
    await currentButton.click();
  }
  await page.waitForFunction(() => {
    const node = document.querySelector('[data-v2-month]');
    const text = node ? (node.textContent || '') : '';
    return text && !text.includes('Archive') && !text.includes('Архив');
  }, null, { timeout: 10000 });
  record('return current month', 'ok', { label: await page.locator('[data-v2-month]').innerText() });
}

async function runSummaryTabs(page) {
  await page.locator('[data-v2-screen="summary"]').click();
  await page.locator('[data-v2-summary-screen]').waitFor({ state: 'visible', timeout: 10000 });
  for (const tab of ['information', 'sending', 'printing', 'storage']) {
    await page.locator(`[data-v2-summary-tab="${tab}"]`).click();
    await page.locator(`[data-v2-summary-panel="${tab}"]`).waitFor({ state: 'visible', timeout: 10000 });
    if (tab === 'information') {
      await waitForText(page, '[data-v2-layer1-information]', ['Ending cash', 'Конечный остаток', 'Категории'], 15000);
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

async function sourceTraceContains(page, sourceKey, needle, periodKey = '') {
  await page.locator('[data-v2-screen="summary"]').click();
  await page.locator('[data-v2-summary-tab="information"]').click();
  if (periodKey) {
    await page.locator('[data-v2-summary-period-from]').waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('[data-v2-summary-period-from]').fill(periodKey);
    await page.locator('[data-v2-summary-period-to]').fill(periodKey);
    await page.locator('[data-v2-summary-period-form] button[type="submit"]').click();
  } else {
    await page.locator('[data-v2-layer1-summary-refresh]').click();
  }
  await waitForText(page, '[data-v2-layer1-information]', ['Ending cash', 'Конечный остаток', 'Категории'], 15000);
  const button = page.locator(`[data-v2-source-total="${sourceKey}"]`).first();
  if (!(await button.count())) return false;
  await button.click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  const body = await page.locator('[data-v2-source-body]').innerText();
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 5000 });
  return body.includes(needle);
}

async function cleanupCreatedEntries(page) {
  for (const id of Array.from(createdEntryIds)) {
    await v2BrowserApi(page, 'DELETE', `/api/entries/${id}`, { closed_month_decision: 'recalculate_chain' });
  }
}

async function assertMarkerGone(page) {
  const response = await v2BrowserApi(page, 'GET', '/api/workspaces');
  assert(response.status === 200, `workspace API failed: ${response.status}`);
  const workspace = response.data.workspaces.find((item) => item.name === 'Claudia Z');
  assert(workspace, 'Claudia Z workspace not found');
  const entries = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspace.id}/entries`, null, { limit: '500' });
  assert(entries.status === 200, `entries API failed: ${entries.status}`);
  const left = entries.data.entries.filter((entry) => String(entry.raw_text || '').includes(marker));
  assert(left.length === 0, `test marker rows still visible: ${left.length}`);
}

async function main() {
  assert(sessionToken, 'FINDESK_V2_PROD_SESSION_TOKEN is required');
  fs.mkdirSync(resultsDir, { recursive: true });
  const browser = await chromium.launch({
    executablePath: chrome,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  let page = null;
  try {
    const context = await browser.newContext({ baseURL: base, viewport: { width: 1440, height: 900 } });
    await context.addCookies([{
      name: sessionCookieName,
      value: sessionToken,
      domain: new URL(base).hostname,
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    }]);
    page = await context.newPage();
    await selectClaudiaWorkspace(page);
    await returnCurrentMonth(page);
    record('desktop opened', 'ok', { screenshot: await screenshot(page, 'desktop-operational') });

    await runSummaryTabs(page);
    await runTrainingTransition(page);

    const currentMonth = monthPartsFromOffset(0);
    const archiveMonth = monthPartsFromOffset(-1);
    const currentInitial = `+12 ${marker} current initial`;
    const currentEdited = `+34 ${marker} current edited`;
    await saveEntry(page, currentMonth.entryDate, currentInitial);
    record('current entry create', 'ok', { screenshot: await screenshot(page, 'current-created') });
    assert(await sourceTraceContains(page, 'cash_income', currentInitial), 'current entry not visible in report source trace after create');
    await editEntry(page, currentInitial, currentEdited);
    record('current entry edit', 'ok', { screenshot: await screenshot(page, 'current-edited') });
    assert(await sourceTraceContains(page, 'cash_income', currentEdited), 'current edit not visible in report source trace');
    await deleteEntry(page, currentEdited);
    record('current entry delete', 'ok', { screenshot: await screenshot(page, 'current-deleted') });
    assert(!(await sourceTraceContains(page, 'cash_income', marker)), 'deleted current entry still appears in report');

    await openMonth(page, archiveMonth.year, archiveMonth.month);
    const archiveInitial = `-7 ${marker} archive june initial`;
    const archiveEdited = `-9 ${marker} archive june edited`;
    await saveEntry(page, archiveMonth.entryDate, archiveInitial);
    record('archive entry create', 'ok', { screenshot: await screenshot(page, 'archive-created') });
    assert(await sourceTraceContains(page, 'cash_expense', archiveInitial, `${archiveMonth.year}-${String(archiveMonth.month).padStart(2, '0')}`), 'archive entry not visible in report source trace after create');
    await editEntry(page, archiveInitial, archiveEdited);
    record('archive entry edit', 'ok', { screenshot: await screenshot(page, 'archive-edited') });
    assert(await sourceTraceContains(page, 'cash_expense', archiveEdited, `${archiveMonth.year}-${String(archiveMonth.month).padStart(2, '0')}`), 'archive edit not visible in report source trace');
    await deleteEntry(page, archiveEdited);
    record('archive entry delete', 'ok', { screenshot: await screenshot(page, 'archive-deleted') });
    assert(!(await sourceTraceContains(page, 'cash_expense', marker, `${archiveMonth.year}-${String(archiveMonth.month).padStart(2, '0')}`)), 'deleted archive entry still appears in report');

    await returnCurrentMonth(page);
    await assertMarkerGone(page);
    record('postflight visible data clean', 'ok');
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
    if (page) {
      try {
        await cleanupCreatedEntries(page);
      } catch (cleanupError) {
        record('final cleanup failed', 'fail', { error: cleanupError.message });
      }
    }
    await browser.close();
    fs.writeFileSync(path.join(resultsDir, 'manual-production-walkthrough-report.json'), JSON.stringify({
      marker,
      base,
      steps,
    }, null, 2));
  }
}

main().catch((error) => {
  record('manual production walkthrough failed', 'fail', { error: error.message });
  console.error(error.stack || error.message);
  process.exit(1);
});
