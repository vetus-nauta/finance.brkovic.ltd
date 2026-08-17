const { chromium } = require('playwright-core');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_REPORT_FRAGMENT_BASE || 'http://127.0.0.1:18991';
const email = process.env.FINDESK_V2_REPORT_FRAGMENT_EMAIL || 'vetus.nauta@gmail.com';
const chrome = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || process.env.FINDESK_V2_REPORT_FRAGMENT_CHROME || '/usr/bin/google-chrome';
const marker = process.env.FINDESK_V2_REPORT_FRAGMENT_MARKER || `REPORT_FRAGMENT_${Date.now()}`;
const resultsDir = process.env.FINDESK_V2_REPORT_FRAGMENT_RESULTS || path.join(process.cwd(), 'test-results', 'v2-report-fragment-browser', marker);

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function routeUrl(route) {
  const url = new URL('/v2-api.php', base);
  url.searchParams.set('route', route);
  return url.toString();
}

async function api(page, method, route, body = null, query = {}) {
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

function screenshotPath(name) {
  return path.join(resultsDir, `${name}.png`);
}

function cleanupWorkspace(workspaceId) {
  if (!workspaceId) return;
  const php = `
require 'app/v2/Database.php';
$workspaceId = getenv('WORKSPACE_ID');
$pdo = FinDeskV2Database::pdo();
$stmt = $pdo->prepare('DELETE FROM v2_workspaces WHERE id = ?');
$stmt->execute([$workspaceId]);
$dir = __DIR__ . '/storage/v2/report-batches/' . $workspaceId;
if (is_dir($dir)) {
  foreach (glob($dir . '/*.html') ?: [] as $file) {
    @unlink($file);
  }
  @rmdir($dir);
}
echo 'workspace deleted';
`;
  execFileSync('php', ['-r', php], {
    cwd: process.cwd(),
    env: { ...process.env, WORKSPACE_ID: workspaceId },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

async function signInIfNeeded(page) {
  await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
  const workspaceProbe = await page.evaluate(async () => {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', '/api/workspaces');
    const response = await fetch(url.toString(), { credentials: 'same-origin' });
    return { status: response.status };
  });
  if (workspaceProbe.status === 200) return;
  const auth = await page.evaluate(async (userEmail) => {
    async function authApi(action, body) {
      const url = new URL('/api.php', window.location.origin);
      url.searchParams.set('action', action);
      const response = await fetch(url.toString(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
      });
      return { status: response.status, data: await response.json().catch(() => ({ ok: false, error: 'invalid_json' })) };
    }
    const requested = await authApi('request_code', { email: userEmail });
    const code = requested.data && requested.data.dev_code ? requested.data.dev_code : '';
    if (!/^\d{6}$/.test(code)) return { ok: false, stage: 'request_code', requested };
    const verified = await authApi('verify_code', { email: userEmail, code });
    return { ok: verified.status === 200 && verified.data && verified.data.ok === true, stage: 'verify_code', requested, verified };
  }, email);
  assert(auth.ok, `auth failed: ${JSON.stringify(auth)}`);
  const after = await page.evaluate(async () => {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', '/api/workspaces');
    const response = await fetch(url.toString(), { credentials: 'same-origin' });
    return { status: response.status, data: await response.json().catch(() => null) };
  });
  assert(after.status === 200, `v2 auth probe failed: ${JSON.stringify(after)}`);
}

async function main() {
  fs.mkdirSync(resultsDir, { recursive: true });
  const browser = await chromium.launch({
    executablePath: chrome,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  let workspaceId = '';
  try {
    const context = await browser.newContext({ baseURL: base, viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await signInIfNeeded(page);

    const createdWorkspace = await api(page, 'POST', '/api/workspaces', {
      name: `Report Fragment Smoke ${marker}`,
      type: 'yacht',
      currency: 'EUR',
      locale: 'ru',
      opening_cash: '5000',
    });
    assert(createdWorkspace.status === 200 && createdWorkspace.data.workspace, `workspace create failed: ${JSON.stringify(createdWorkspace)}`);
    workspaceId = createdWorkspace.data.workspace.id;

    const flowsResponse = await api(page, 'GET', `/api/workspaces/${workspaceId}/flows`);
    const cash = (flowsResponse.data.flows || []).find((flow) => flow.type === 'cash');
    assert(cash, 'cash flow missing');
    const rows = [
      { date: '2026-07-31', text: '+1000 пополнение от судовладельца' },
      { date: '2026-08-01', text: '-120 продукты для гостей' },
      { date: '2026-08-02', text: '-40 топливо' },
    ];
    for (let index = 0; index < rows.length; index += 1) {
      const response = await api(page, 'POST', `/api/workspaces/${workspaceId}/entries`, {
        flow_id: cash.id,
        date: rows[index].date,
        raw_text: `${rows[index].text} ${marker}`,
      });
      assert(response.status === 200 && response.data.entry, `entry create failed: ${JSON.stringify(response)}`);
      rows[index].id = response.data.entry.id;
    }

    await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-workspace-select]').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction((needle) => {
      const feed = document.querySelector('[data-v2-feed]');
      return Boolean(feed && feed.textContent.includes(needle));
    }, marker, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('01-operational-ready'), fullPage: false });

    await page.locator('[data-v2-report-selection-toggle]').first().click();
    await page.locator('[data-v2-report-range-from]').fill(rows[0].date);
    await page.locator('[data-v2-report-range-to]').fill(rows[2].date);
    await page.locator('[data-v2-report-range-apply]').click();
    await page.waitForFunction((needle) => {
      const feed = document.querySelector('[data-v2-feed]');
      return Boolean(feed && feed.textContent.includes(needle));
    }, rows[0].text, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('02-report-range-feed'), fullPage: false });

    await page.locator('[data-v2-entry-select]', { hasText: rows[0].text }).click();
    await page.locator('[data-v2-entry-select]', { hasText: rows[2].text }).click();
    await page.locator('[data-v2-report-selection-preview]').click();
    await page.locator('[data-v2-report-fragment-layer]').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.textContent.includes('Предпросмотр готов'));
    }, null, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('03-fragment-preview'), fullPage: false });

    const createResponsePromise = page.waitForResponse((response) => response.url().includes('/v2-api.php') && response.request().method() === 'POST' && response.request().url().includes(encodeURIComponent('/api/workspaces/')));
    await page.locator('[data-v2-report-fragment-create]').click();
    await createResponsePromise.catch(() => null);
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.textContent.includes('Отчет создан') && modal.querySelector('a[href*="v2-report.php"]'));
    }, null, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('04-fragment-created'), fullPage: false });
    const link = await page.locator('a[href*="v2-report.php"]').first().getAttribute('href');
    assert(link, 'report html link missing');
    const downloadLink = await page.locator('[data-v2-report-fragment-download]').getAttribute('href');
    assert(downloadLink && downloadLink.includes('download=1'), 'report html download link missing');
    const printUrl = await page.locator('[data-v2-report-fragment-print]').getAttribute('data-v2-print-url');
    assert(printUrl && printUrl.includes('print=1'), 'report print url missing');
    const downloadProbe = await page.evaluate(async (href) => {
      const response = await fetch(href, { credentials: 'same-origin' });
      return {
        status: response.status,
        type: response.headers.get('content-type') || '',
        disposition: response.headers.get('content-disposition') || '',
      };
    }, downloadLink);
    assert(downloadProbe.status === 200 && downloadProbe.disposition.includes('attachment') && downloadProbe.type.includes('text/html'), `download probe failed: ${JSON.stringify(downloadProbe)}`);
    await page.locator('[data-v2-report-fragment-close-date]').fill(rows[2].date);
    await page.locator('[data-v2-report-fragment-close-date-save]').click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.textContent.includes('дата отчета 2026-08-02'));
    }, null, { timeout: 10000 });
    await page.locator('[data-v2-report-fragment-send]').click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.textContent.includes('Отправлено') && modal.textContent.includes('дата отчета 2026-08-02'));
    }, null, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('05-fragment-controls-sent'), fullPage: false });

    const reportPage = await context.newPage();
    await reportPage.goto(link, { waitUntil: 'domcontentloaded' });
    await reportPage.waitForFunction(() => {
      return document.body
        && document.body.textContent.includes('Категории')
        && document.body.textContent.includes('2026-07-31')
        && document.body.textContent.includes('Отправлен')
        && document.body.textContent.includes('Закрыт: 2026-08-02');
    }, null, { timeout: 10000 });
    await reportPage.screenshot({ path: screenshotPath('06-report-html'), fullPage: false });
    await reportPage.close();

    await page.locator('[data-v2-report-fragment-close]').last().click();
    await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => {
      const feed = document.querySelector('[data-v2-feed]');
      return Boolean(feed && feed.textContent.includes('Отправлен') && feed.querySelector('[data-v2-report-row-open]'));
    }, null, { timeout: 10000 });
    await page.locator('[data-v2-report-context-open]').click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && !modal.hidden && modal.textContent.includes('Отправлено') && modal.textContent.includes('дата отчета 2026-08-02'));
    }, null, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('07-reopened-from-locked-row'), fullPage: false });

    await page.locator('[data-v2-report-fragment-close]').last().click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.hidden);
    }, null, { timeout: 10000 });
    await page.locator('[data-v2-feed] [data-v2-report-row]').first().click();
    try {
      await page.waitForFunction((needle) => {
        const modal = document.querySelector('[data-v2-report-fragment-layer]');
        const feed = document.querySelector('[data-v2-feed]');
        const row = document.querySelector('[data-v2-feed] [data-v2-report-row]');
        return Boolean(
          modal
            && modal.hidden
            && row
            && row.getAttribute('aria-expanded') === 'true'
            && feed
            && feed.textContent.includes(needle)
        );
      }, rows[1].text, { timeout: 10000 });
    } catch (error) {
      const state = await page.evaluate(() => {
        const modal = document.querySelector('[data-v2-report-fragment-layer]');
        const feed = document.querySelector('[data-v2-feed]');
        const row = document.querySelector('[data-v2-feed] [data-v2-report-row]');
        return {
          modalHidden: modal ? modal.hidden : null,
          feedText: feed ? feed.textContent : '',
          rowText: row ? row.textContent : '',
          rowExpanded: row ? row.getAttribute('aria-expanded') : null,
          reportRows: document.querySelectorAll('[data-v2-feed] [data-v2-report-row]').length,
          childRows: document.querySelectorAll('[data-v2-feed] .is-report-child').length,
        };
      });
      await page.screenshot({ path: screenshotPath('08-report-row-expanded-timeout'), fullPage: false });
      throw new Error(`feed report row expand failed: ${JSON.stringify(state)}`);
    }
    await page.screenshot({ path: screenshotPath('08-report-row-expanded'), fullPage: false });
    await page.locator('[data-v2-check-row][data-v2-report-row]').first().click();
    try {
      await page.waitForFunction((needle) => {
        const modal = document.querySelector('[data-v2-report-fragment-layer]');
        const feed = document.querySelector('[data-v2-feed]');
        const row = document.querySelector('[data-v2-check-row][data-v2-report-row]');
        return Boolean(
          modal
            && modal.hidden
            && row
            && row.getAttribute('aria-expanded') === 'false'
            && feed
            && !feed.textContent.includes(needle)
        );
      }, rows[1].text, { timeout: 10000 });
    } catch (error) {
      const state = await page.evaluate(() => {
        const modal = document.querySelector('[data-v2-report-fragment-layer]');
        const check = document.querySelector('[data-v2-check-row][data-v2-report-row]');
        const row = document.querySelector('[data-v2-check-row][data-v2-report-row]');
        const rect = row ? row.getBoundingClientRect() : null;
        return {
          modalHidden: modal ? modal.hidden : null,
          modalText: modal ? modal.textContent : '',
          checkText: check ? check.textContent : '',
          rowTag: row ? row.tagName : '',
          rowClass: row ? row.className : '',
          rowReportId: row ? row.getAttribute('data-v2-report-id') : '',
          rowInCheckTable: row ? Boolean(row.closest('[data-v2-check-table]')) : false,
          rowRect: rect ? { x: rect.x, y: rect.y, width: rect.width, height: rect.height } : null,
        };
      });
      await page.screenshot({ path: screenshotPath('09-report-row-toggle-timeout'), fullPage: false });
      throw new Error(`report row toggle failed: ${JSON.stringify(state)}`);
    }
    await page.screenshot({ path: screenshotPath('09-report-row-collapsed-from-check'), fullPage: false });

    const outdatedProbe = await api(page, 'PATCH', `/api/entries/${rows[1].id}`, {
      raw_text: `${rows[1].text} исправлено ${marker}`,
      report_fragment_decision: 'recalculate_fragment',
    });
    assert(outdatedProbe.status === 200 && outdatedProbe.data.entry, `locked entry update failed: ${JSON.stringify(outdatedProbe)}`);
    const outdatedStatus = await page.evaluate(async ({ workspace, href }) => {
      const id = new URL(href, window.location.origin).searchParams.get('id');
      const url = new URL('/v2-api.php', window.location.origin);
      url.searchParams.set('route', `/api/workspaces/${workspace}/reports/operational-fragments/${id}`);
      const response = await fetch(url.toString(), { credentials: 'same-origin' });
      return { status: response.status, data: await response.json().catch(() => null) };
    }, { workspace: workspaceId, href: link });
    assert(
      outdatedStatus.status === 200
        && outdatedStatus.data
        && outdatedStatus.data.fragment
        && outdatedStatus.data.fragment.status === 'requires_update',
      `requires_update status failed: ${JSON.stringify(outdatedStatus)}`
    );
    await page.screenshot({ path: screenshotPath('09-fragment-requires-update'), fullPage: false });

    await page.locator('[data-v2-report-context-open]').click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && !modal.hidden && modal.textContent.includes('Требует обновления'));
    }, null, { timeout: 10000 });
    await page.locator('[data-v2-report-fragment-cancel]').click();
    await page.waitForFunction(() => {
      const button = document.querySelector('[data-v2-report-fragment-cancel]');
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(button && button.textContent.includes('Точно отменить?') && modal && modal.textContent.includes('Записи не удалятся'));
    }, null, { timeout: 10000 });
    await page.locator('[data-v2-report-fragment-cancel]').click();
    await page.waitForFunction(() => {
      const modal = document.querySelector('[data-v2-report-fragment-layer]');
      return Boolean(modal && modal.textContent.includes('Отчет отменен') && modal.textContent.includes('Заменен'));
    }, null, { timeout: 10000 });
    const cancelledStatus = await page.evaluate(async ({ workspace, href }) => {
      const id = new URL(href, window.location.origin).searchParams.get('id');
      const url = new URL('/v2-api.php', window.location.origin);
      url.searchParams.set('route', `/api/workspaces/${workspace}/reports/operational-fragments/${id}`);
      const response = await fetch(url.toString(), { credentials: 'same-origin' });
      return { status: response.status, data: await response.json().catch(() => null) };
    }, { workspace: workspaceId, href: link });
    assert(cancelledStatus.status === 200 && cancelledStatus.data && cancelledStatus.data.fragment && cancelledStatus.data.fragment.status === 'superseded', `cancel status failed: ${JSON.stringify(cancelledStatus)}`);
    await page.screenshot({ path: screenshotPath('10-fragment-cancelled'), fullPage: false });
    const cancelledPage = await context.newPage();
    await cancelledPage.goto(link, { waitUntil: 'domcontentloaded' });
    await cancelledPage.waitForFunction(() => {
      return document.body
        && document.body.textContent.includes('Заменен')
        && !document.body.textContent.includes('Отправлен');
    }, null, { timeout: 10000 });
    await cancelledPage.screenshot({ path: screenshotPath('11-report-html-cancelled'), fullPage: false });
    await cancelledPage.close();

    await page.locator('[data-v2-report-fragment-close]').last().click();
    await page.waitForFunction((needle) => {
      const feed = document.querySelector('[data-v2-feed]');
      return Boolean(feed && feed.textContent.includes(needle) && !feed.textContent.includes('Отправлен') && !feed.textContent.includes('Требует обновления'));
    }, rows[1].text, { timeout: 10000 });
    await page.screenshot({ path: screenshotPath('12-unlocked-rows'), fullPage: false });

    const reportsCount = await page.evaluate(async (url) => {
      const response = await fetch(url, { credentials: 'same-origin' });
      const html = await response.text();
      return { status: response.status, hasSummary: html.includes('Smoke report') || html.includes('Отчет') || html.includes('Категории') };
    }, routeUrl(`/api/workspaces/${workspaceId}/reports/batches`));
    assert(reportsCount.status === 200, 'reports list fetch failed');
    console.log(JSON.stringify({ ok: true, workspaceId, marker, screenshots: resultsDir, link, reportsCount }, null, 2));
  } finally {
    await browser.close();
    cleanupWorkspace(workspaceId);
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
