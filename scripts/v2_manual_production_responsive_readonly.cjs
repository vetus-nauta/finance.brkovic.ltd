const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_PROD_BASE || 'https://finance.brkovic.ltd';
const sessionCookieName = process.env.FINDESK_V2_PROD_COOKIE_NAME || 'ql_session';
const sessionToken = process.env.FINDESK_V2_PROD_SESSION_TOKEN || '';
const chrome = process.env.FINDESK_V2_MANUAL_CHROME || '/usr/bin/google-chrome';
const marker = process.env.FINDESK_V2_MANUAL_MARKER || `PROD_RESPONSIVE_READONLY_${Date.now()}`;
const resultsDir = process.env.FINDESK_V2_MANUAL_RESULTS
  || path.join(process.cwd(), 'test-results', 'v2-production-responsive-readonly', marker);

const viewports = [
  { name: 'mobile-portrait', width: 390, height: 844, isMobile: true },
  { name: 'ipad-portrait', width: 768, height: 1024, isMobile: true },
  { name: 'ipad-landscape', width: 1024, height: 768, isMobile: true },
  { name: 'desktop', width: 1440, height: 900, isMobile: false },
];
const steps = [];

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function record(step, status = 'ok', detail = {}) {
  const row = { step, status, detail, at: new Date().toISOString() };
  steps.push(row);
  console.log(`${status.toUpperCase()} ${step}${Object.keys(detail).length ? ` ${JSON.stringify(detail)}` : ''}`);
}

async function screenshot(page, viewportName, name) {
  const file = path.join(resultsDir, `${viewportName}-${String(steps.length).padStart(2, '0')}-${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  return path.relative(process.cwd(), file);
}

async function activateScreen(page, screen) {
  const selector = `[data-v2-screen="${screen}"]`;
  await activateControl(page, selector);
}

async function activateControl(page, selector) {
  const button = page.locator(selector).first();
  await button.waitFor({ state: 'attached', timeout: 10000 });
  if (await button.isVisible()) {
    await button.click();
    return;
  }
  await page.evaluate((targetSelector) => {
    document.querySelector(targetSelector)?.click();
  }, selector);
}

function textAlternatives(text) {
  return Array.isArray(text) ? text : [text];
}

async function waitForText(page, selector, text, timeout = 12000) {
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

async function selectClaudia(page) {
  await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
  const workspaceId = await page.evaluate(async () => {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', '/api/workspaces');
    const response = await fetch(url.toString(), { credentials: 'same-origin' });
    const data = await response.json();
    const workspace = (data.workspaces || []).find((row) => row.name === 'Claudia Z');
    return workspace ? workspace.id : '';
  });
  assert(workspaceId, 'Claudia Z workspace is not available for production session');
  await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 15000 });
  await waitForText(page, '[data-v2-status]', ['Ready', 'Готово'], 15000);
}

async function layoutMetrics(page) {
  return page.evaluate(() => ({
    innerWidth,
    innerHeight,
    docScrollWidth: document.documentElement.scrollWidth,
    docScrollHeight: document.documentElement.scrollHeight,
    bodyScrollWidth: document.body.scrollWidth,
    bodyScrollHeight: document.body.scrollHeight,
    writingScrollHeight: document.querySelector('[data-v2-feed]')?.scrollHeight || 0,
    writingClientHeight: document.querySelector('[data-v2-feed]')?.clientHeight || 0,
    checkScrollWidth: document.querySelector('[data-v2-check-table]')?.scrollWidth || 0,
    checkClientWidth: document.querySelector('[data-v2-check-table]')?.clientWidth || 0,
  }));
}

async function runViewport(browser, viewport) {
  const context = await browser.newContext({
    baseURL: base,
    viewport: { width: viewport.width, height: viewport.height },
    isMobile: viewport.isMobile,
    hasTouch: viewport.isMobile,
  });
  await context.addCookies([{
    name: sessionCookieName,
    value: sessionToken,
    domain: new URL(base).hostname,
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax',
  }]);
  const page = await context.newPage();
  try {
    await selectClaudia(page);
    await activateScreen(page, 'operational');
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    const opMetrics = await layoutMetrics(page);
    assert(opMetrics.docScrollWidth <= viewport.width + 2, `${viewport.name} has page horizontal overflow: ${opMetrics.docScrollWidth} > ${viewport.width}`);
    record(`${viewport.name} operational`, 'ok', {
      metrics: opMetrics,
      screenshot: await screenshot(page, viewport.name, 'operational'),
    });

    await activateScreen(page, 'summary');
    await page.locator('[data-v2-summary-screen]').waitFor({ state: 'visible', timeout: 10000 });
    for (const tab of ['information', 'sending', 'printing', 'storage']) {
      await page.locator(`[data-v2-summary-tab="${tab}"]`).click();
      await page.locator(`[data-v2-summary-panel="${tab}"]`).waitFor({ state: 'visible', timeout: 10000 });
      if (tab === 'information') {
        await page.waitForFunction(() => {
          const node = document.querySelector('[data-v2-layer1-information]');
          return Boolean(node && node.textContent && node.textContent.trim().length > 20);
        }, null, { timeout: 15000 });
      }
      const metrics = await layoutMetrics(page);
      assert(metrics.docScrollWidth <= viewport.width + 2, `${viewport.name}/${tab} has page horizontal overflow`);
      record(`${viewport.name} summary ${tab}`, 'ok', {
        metrics,
        screenshot: await screenshot(page, viewport.name, `summary-${tab}`),
      });
    }

    await activateScreen(page, 'training');
    await page.locator('[data-v2-training-screen]').waitFor({ state: 'visible', timeout: 10000 });
    const trainingMetrics = await layoutMetrics(page);
    assert(trainingMetrics.docScrollWidth <= viewport.width + 2, `${viewport.name}/training has page horizontal overflow`);
    record(`${viewport.name} training`, 'ok', {
      metrics: trainingMetrics,
      screenshot: await screenshot(page, viewport.name, 'training'),
    });

    await activateScreen(page, 'operational');
    await activateControl(page, '[data-v2-archive-open]');
    await page.locator('[data-v2-archive-layer]').waitFor({ state: 'visible', timeout: 10000 });
    record(`${viewport.name} archive modal`, 'ok', {
      screenshot: await screenshot(page, viewport.name, 'archive-modal'),
    });
    await page.keyboard.press('Escape');
  } finally {
    await context.close();
  }
}

async function main() {
  assert(sessionToken, 'FINDESK_V2_PROD_SESSION_TOKEN is required');
  fs.mkdirSync(resultsDir, { recursive: true });
  const browser = await chromium.launch({
    executablePath: chrome,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    for (const viewport of viewports) {
      await runViewport(browser, viewport);
    }
  } finally {
    await browser.close();
    fs.writeFileSync(path.join(resultsDir, 'responsive-readonly-report.json'), JSON.stringify({
      marker,
      base,
      viewports,
      steps,
    }, null, 2));
  }
}

main().catch((error) => {
  record('responsive readonly failed', 'fail', { error: error.message });
  console.error(error.stack || error.message);
  process.exit(1);
});
