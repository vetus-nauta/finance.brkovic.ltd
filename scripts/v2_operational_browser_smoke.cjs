const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_BROWSER_BASE;
const cookieName = process.env.FINDESK_V2_BROWSER_COOKIE;
const token = process.env.FINDESK_V2_BROWSER_TOKEN;
const chrome = process.env.FINDESK_V2_BROWSER_CHROME;
const resultsDir = process.env.FINDESK_V2_BROWSER_RESULTS || path.join(process.cwd(), 'test-results/v2-browser-smoke');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function routeFromRequest(request) {
  const url = new URL(request.url());
  return url.searchParams.get('route') || '';
}

function countMatches(text, needle) {
  return (text.match(new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;
}

async function waitForText(page, selector, text) {
  await page.waitForFunction(
    ({ selector: targetSelector, text: targetText }) => {
      const node = document.querySelector(targetSelector);
      return Boolean(node && node.textContent && node.textContent.includes(targetText));
    },
    { selector, text },
    { timeout: 10000 }
  );
}

async function saveEntry(page, rawText) {
  await page.locator('[data-v2-raw-text]').fill(rawText);
  const responsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/entries');
  });
  await page.locator('[data-v2-submit]').click();
  const response = await responsePromise;
  assert(response.status() === 200, `entry save failed for ${rawText}: HTTP ${response.status()}`);
  await waitForText(page, '[data-v2-feed]', rawText);
  await waitForText(page, '[data-v2-check-table]', rawText);
  await waitForText(page, '[data-v2-status]', 'Saved');
}

async function assertNoPageScroll(page) {
  const metrics = await page.evaluate(() => ({
    bodyOverflow: getComputedStyle(document.body).overflow,
    htmlOverflow: getComputedStyle(document.documentElement).overflow,
    bodyScrollHeight: document.body.scrollHeight,
    htmlScrollHeight: document.documentElement.scrollHeight,
    windowHeight: window.innerHeight,
    shellHeight: document.querySelector('[data-v2-app]')?.getBoundingClientRect().height || 0,
    feedOverflowY: getComputedStyle(document.querySelector('[data-v2-feed]')).overflowY,
    horizontalDisplay: getComputedStyle(document.querySelector('.v2-horizontal')).display,
  }));

  assert(metrics.bodyOverflow === 'hidden', `body overflow must be hidden, got ${metrics.bodyOverflow}`);
  assert(metrics.htmlOverflow === 'hidden', `html overflow must be hidden, got ${metrics.htmlOverflow}`);
  assert(metrics.shellHeight <= metrics.windowHeight + 2, `shell exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.feedOverflowY === 'auto', `feed must own vertical scroll, got ${metrics.feedOverflowY}`);
  assert(metrics.horizontalDisplay === 'grid', `desktop write/check area must be side-by-side grid, got ${metrics.horizontalDisplay}`);
  return metrics;
}

async function run() {
  assert(base, 'Missing FINDESK_V2_BROWSER_BASE');
  assert(cookieName, 'Missing FINDESK_V2_BROWSER_COOKIE');
  assert(token, 'Missing FINDESK_V2_BROWSER_TOKEN');
  assert(chrome, 'Missing FINDESK_V2_BROWSER_CHROME');
  fs.mkdirSync(resultsDir, { recursive: true });

  const browser = await chromium.launch({
    executablePath: chrome,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  try {
    const authless = await browser.newContext({ baseURL: base, viewport: { width: 1280, height: 820 } });
    const authlessPage = await authless.newPage();
    await authlessPage.goto('/v2.php', { waitUntil: 'domcontentloaded' });
    await waitForText(authlessPage, '[data-v2-status]', 'Not authenticated');
    await authless.close();
    console.log('Unauthenticated state: OK');

    const context = await browser.newContext({ baseURL: base, viewport: { width: 1280, height: 820 } });
    await context.addCookies([{ name: cookieName, value: token, url: base }]);
    const page = await context.newPage();

    const entryPostBodies = [];
    await page.route('**/v2-api.php?**', async (route) => {
      const request = route.request();
      if (request.method() === 'POST' && routeFromRequest(request).endsWith('/entries')) {
        entryPostBodies.push(request.postData() || '');
        if ((request.postData() || '').includes('duplicate guard')) {
          await new Promise((resolve) => setTimeout(resolve, 250));
        }
      }
      await route.continue();
    });

    await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-create-form]').waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('[data-v2-create-form] input[name="name"]').fill('Browser Smoke Workspace');
    const workspaceResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()) === '/api/workspaces'
    ));
    await page.locator('[data-v2-create-form] button[type="submit"]').click();
    assert((await workspaceResponse).status() === 200, 'workspace create failed');
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await waitForText(page, '[data-v2-status]', 'Ready');
    console.log('Workspace create/select: OK');

    await saveEntry(page, '+1000 снял с карты');
    await saveEntry(page, '-250 рыба');
    await waitForText(page, '[data-v2-count]', '2 records');

    const checkHeaders = await page.locator('[data-v2-check-table] .v2-check-row').first().innerText();
    for (const field of ['date', 'raw_text', 'flow', 'sign', 'amount', 'direction', 'entry_type', 'category', 'actor', 'status', 'balance_after']) {
      assert(checkHeaders.includes(field), `structured check missing header: ${field}`);
    }
    const checkText = await page.locator('[data-v2-check-table]').innerText();
    assert(checkText.includes('+1000 снял с карты'), 'structured check missing first saved record');
    assert(checkText.includes('-250 рыба'), 'structured check missing second saved record');
    assert(checkText.includes('€1,000.00'), 'structured check missing formatted +1000 amount');
    assert(checkText.includes('€250.00'), 'structured check missing formatted -250 amount');
    console.log('Save records + structured check: OK');

    await page.locator('[data-v2-refresh]').click();
    await waitForText(page, '[data-v2-feed]', '+1000 снял с карты');
    await waitForText(page, '[data-v2-feed]', '-250 рыба');
    await waitForText(page, '[data-v2-check-table]', '-250 рыба');
    console.log('Refresh preserves feed/check: OK');

    const beforeGuardPosts = entryPostBodies.length;
    await page.locator('[data-v2-raw-text]').fill('-10 duplicate guard');
    await Promise.all([
      page.locator('[data-v2-submit]').click(),
      page.locator('[data-v2-submit]').click().catch(() => undefined),
    ]);
    await waitForText(page, '[data-v2-feed]', '-10 duplicate guard');
    const afterGuardPosts = entryPostBodies.length;
    assert(afterGuardPosts - beforeGuardPosts === 1, `double submit sent ${afterGuardPosts - beforeGuardPosts} entry POSTs`);
    const feedAfterGuard = await page.locator('[data-v2-feed]').innerText();
    assert(countMatches(feedAfterGuard, '-10 duplicate guard') === 1, 'double submit rendered duplicate records');
    console.log('Double-submit protection: OK');

    const desktopMetrics = await assertNoPageScroll(page);
    await page.screenshot({ path: path.join(resultsDir, 'desktop-operational-window.png'), fullPage: false });
    console.log(`Desktop scroll metrics: ${JSON.stringify(desktopMetrics)}`);

    const draft = 'offline draft preserved';
    await page.locator('[data-v2-raw-text]').fill(draft);
    await page.waitForFunction((value) => localStorage.getItem('findesk.v2.operational.draft') === value, draft);
    await context.setOffline(true);
    await page.locator('[data-v2-submit]').click();
    await waitForText(page, '[data-v2-status]', 'Offline: draft kept locally');
    assert(await page.locator('[data-v2-raw-text]').inputValue() === draft, 'offline submit cleared draft input');
    await context.setOffline(false);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction((value) => {
      const input = document.querySelector('[data-v2-raw-text]');
      return input && input.value === value;
    }, draft);
    assert(await page.locator('[data-v2-raw-text]').inputValue() === draft, 'draft did not restore after reload');
    console.log('Offline draft preservation: OK');
    await context.close();

    const mobile = await browser.newContext({ baseURL: base, viewport: { width: 390, height: 844 }, isMobile: true });
    await mobile.addCookies([{ name: cookieName, value: token, url: base }]);
    const mobilePage = await mobile.newPage();
    await mobilePage.goto('/v2.php', { waitUntil: 'domcontentloaded' });
    await mobilePage.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    const beforeScrollLeft = await mobilePage.locator('.v2-horizontal').evaluate((node) => node.scrollLeft);
    await mobilePage.locator('[data-v2-view="check"]').click();
    await mobilePage.waitForTimeout(650);
    const mobileMetrics = await mobilePage.evaluate(() => {
      const horizontal = document.querySelector('.v2-horizontal');
      const check = document.querySelector('[data-v2-check]');
      const checkRect = check.getBoundingClientRect();
      return {
        beforeBodyScrollTop: document.scrollingElement.scrollTop,
        horizontalScrollLeft: horizontal.scrollLeft,
        checkLeft: checkRect.left,
        checkRight: checkRect.right,
        windowWidth: window.innerWidth,
        bodyOverflow: getComputedStyle(document.body).overflow,
        feedOverflowY: getComputedStyle(document.querySelector('[data-v2-feed]')).overflowY,
        horizontalOverflowX: getComputedStyle(horizontal).overflowX,
      };
    });
    assert(mobileMetrics.horizontalScrollLeft > beforeScrollLeft + 20, `mobile check view did not move horizontally: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.checkLeft < mobileMetrics.windowWidth && mobileMetrics.checkRight > 0, `mobile check panel not visible: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.bodyOverflow === 'hidden', `mobile body overflow must stay hidden: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.feedOverflowY === 'auto', `mobile feed must own vertical scroll: ${JSON.stringify(mobileMetrics)}`);
    assert(['auto', 'scroll'].includes(mobileMetrics.horizontalOverflowX), `mobile horizontal overflow missing: ${JSON.stringify(mobileMetrics)}`);
    await mobilePage.screenshot({ path: path.join(resultsDir, 'mobile-structured-check.png'), fullPage: false });
    await mobile.close();
    console.log(`Mobile horizontal metrics: ${JSON.stringify(mobileMetrics)}`);

    console.log('FinDesk v2 browser UI smoke: OK');
    console.log(`Screenshots: ${resultsDir}`);
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error('FinDesk v2 browser UI smoke: FAIL');
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
