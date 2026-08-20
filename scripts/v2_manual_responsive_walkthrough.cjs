const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_BROWSER_BASE;
const cookieName = process.env.FINDESK_V2_BROWSER_COOKIE;
const token = process.env.FINDESK_V2_BROWSER_TOKEN;
const chrome = process.env.FINDESK_V2_BROWSER_CHROME;
const resultsDir = process.env.FINDESK_V2_BROWSER_RESULTS || path.join(process.cwd(), 'test-results/v2-manual-responsive');
const rawTextInputSelector = '[data-v2-entry-form] [data-v2-raw-text]';
const report = {
  startedAt: new Date().toISOString(),
  base,
  screenshots: [],
  devices: [],
  findings: [],
};

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function routeFromRequest(request) {
  const url = new URL(request.url());
  return url.searchParams.get('route') || '';
}

function priorMonthEndDate() {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), 0).toISOString().slice(0, 10);
}

async function waitForText(page, selector, text) {
  const alternatives = text === 'Ready' ? ['Ready', 'Готово'] : [text];
  await page.waitForFunction(
    ({ selector: targetSelector, texts }) => {
      const node = document.querySelector(targetSelector);
      return Boolean(node && node.textContent && texts.some((targetText) => node.textContent.includes(targetText)));
    },
    { selector, texts: alternatives },
    { timeout: 10000 }
  );
}

async function waitForAnyVisible(page, selectors, timeout = 10000) {
  const started = Date.now();
  while (Date.now() - started < timeout) {
    for (const selector of selectors) {
      if (await page.locator(selector).isVisible().catch(() => false)) return selector;
    }
    await page.waitForTimeout(100);
  }
  throw new Error('None of these selectors became visible: ' + selectors.join(', '));
}

async function openWorkspaceInDeviceContext(page, workspaceId) {
  await waitForAnyVisible(page, [
    '[data-v2-workspace]',
    '[data-v2-hall]',
    '[data-v2-auth]',
    '[data-v2-create]',
  ]);
  if (await page.locator('[data-v2-workspace]').isVisible().catch(() => false)) return;
  const navButton = page.locator('[data-v2-screen="operational"]');
  if (await navButton.isVisible().catch(() => false)) {
    await navButton.click();
    if (await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 5000 }).then(() => true).catch(() => false)) return;
  }
  const hallButton = page.locator('[data-v2-hall-workspace-open][data-v2-workspace-id="' + workspaceId + '"]');
  if (await hallButton.isVisible().catch(() => false)) {
    await hallButton.click();
    if (await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 5000 }).then(() => true).catch(() => false)) return;
  }
  const firstHallButton = page.locator('[data-v2-hall-workspace-open]').first();
  if (await firstHallButton.isVisible().catch(() => false)) {
    await firstHallButton.click();
  }
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
}

async function enableMobileFinanceMode(page) {
  const toggle = page.locator('[data-v2-mobile-finance-toggle]');
  await toggle.waitFor({ state: 'visible', timeout: 10000 });
  const enabled = await page.evaluate(() => document.body.classList.contains('v2-mobile-finance-mode'));
  if (!enabled) {
    await toggle.click();
    await page.waitForFunction(() => document.body.classList.contains('v2-mobile-finance-mode'));
  }
}

async function saveEntry(page, rawText) {
  await page.locator(rawTextInputSelector).fill(rawText);
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
}

async function createEntryViaApi(page, workspaceId, payload) {
  const response = await page.request.post('/v2-api.php?route=' + encodeURIComponent('/api/workspaces/' + workspaceId + '/entries'), {
    headers: { 'X-FinDesk-V2-Request': 'fetch' },
    data: payload,
  });
  assert(response.status() === 200, `entry API save failed for ${payload.raw_text}: HTTP ${response.status()}`);
  const body = await response.json();
  assert(body && body.ok === true, `entry API save returned non-ok for ${payload.raw_text}`);
  return body.entry || null;
}

async function collectMetrics(page) {
  return page.evaluate(() => {
    const rect = (selector) => {
      const node = document.querySelector(selector);
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return {
        top: box.top,
        right: box.right,
        bottom: box.bottom,
        left: box.left,
        width: box.width,
        height: box.height,
      };
    };
    const style = (selector) => {
      const node = document.querySelector(selector);
      return node ? getComputedStyle(node) : null;
    };
    const detail = document.querySelector('[data-v2-entry-detail]');
    const detailStyle = detail ? getComputedStyle(detail) : null;
    const detailRect = detail ? detail.getBoundingClientRect() : null;
    const visibleViewTabs = Array.from(document.querySelectorAll('[data-v2-view]'))
      .filter((node) => {
        const nodeStyle = getComputedStyle(node);
        const nodeRect = node.getBoundingClientRect();
        return nodeStyle.display !== 'none'
          && nodeStyle.visibility !== 'hidden'
          && nodeRect.width > 0
          && nodeRect.height > 0;
      })
      .map((node) => ({
        view: node.getAttribute('data-v2-view') || '',
        text: (node.textContent || '').trim(),
        active: node.classList.contains('is-active'),
      }));
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      shell: rect('[data-v2-app]'),
      workspace: rect('[data-v2-workspace]'),
      tabs: rect('.v2-mobile-tabs'),
      horizontal: rect('.v2-horizontal'),
      writing: rect('[data-v2-writing]'),
      feed: rect('[data-v2-feed]'),
      check: rect('[data-v2-check]'),
      detail: rect('[data-v2-entry-detail]'),
      inputbar: rect('[data-v2-entry-form]'),
      submit: rect('[data-v2-submit]'),
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyScrollHeight: document.body.scrollHeight,
      htmlScrollHeight: document.documentElement.scrollHeight,
      documentScrollTop: document.scrollingElement ? document.scrollingElement.scrollTop : 0,
      horizontalDisplay: style('.v2-horizontal')?.display || '',
      horizontalOverflowX: style('.v2-horizontal')?.overflowX || '',
      horizontalScrollLeft: document.querySelector('.v2-horizontal')?.scrollLeft || 0,
      feedOverflowY: style('[data-v2-feed]')?.overflowY || '',
      feedScrollTop: document.querySelector('[data-v2-feed]')?.scrollTop || 0,
      feedScrollHeight: document.querySelector('[data-v2-feed]')?.scrollHeight || 0,
      feedClientHeight: document.querySelector('[data-v2-feed]')?.clientHeight || 0,
      checkOverflow: style('[data-v2-check-table]')?.overflow || '',
      checkScrollLeft: document.querySelector('[data-v2-check-table]')?.scrollLeft || 0,
      checkScrollWidth: document.querySelector('[data-v2-check-table]')?.scrollWidth || 0,
      checkClientWidth: document.querySelector('[data-v2-check-table]')?.clientWidth || 0,
      detailInsideHorizontal: Boolean(detail && detail.closest('.v2-horizontal')),
      detailVisible: Boolean(detail && detailRect && !detail.hidden
        && detail.getAttribute('aria-hidden') !== 'true'
        && detailStyle.display !== 'none'
        && detailStyle.visibility !== 'hidden'
        && detailRect.width > 0
        && detailRect.height > 0),
      visibleViewTabs,
    };
  });
}

async function collectSummaryMetrics(page) {
  return page.evaluate(() => {
    const rect = (selector) => {
      const node = document.querySelector(selector);
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return {
        top: box.top,
        right: box.right,
        bottom: box.bottom,
        left: box.left,
        width: box.width,
        height: box.height,
      };
    };
    const activePanel = document.querySelector('[data-v2-summary-panel]:not([hidden])');
    const activeScroll = activePanel ? activePanel.querySelector('.v2-summary-scroll') : null;
    const sourceLayer = document.querySelector('[data-v2-source-layer]');
    const sourceDetail = document.querySelector('[data-v2-source-detail]');
    const sourceBody = document.querySelector('[data-v2-source-body]');
    const sourceClose = document.querySelector('[data-v2-source-close]');
    const sourceStyle = sourceLayer ? getComputedStyle(sourceLayer) : null;
    const sourceRect = sourceDetail ? sourceDetail.getBoundingClientRect() : null;
    const sourceBodyRect = sourceBody ? sourceBody.getBoundingClientRect() : null;
    const sourceCloseRect = sourceClose ? sourceClose.getBoundingClientRect() : null;
    const scrollRect = activeScroll ? activeScroll.getBoundingClientRect() : null;
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      shell: rect('[data-v2-app]'),
      summaryScreen: rect('[data-v2-summary-screen]'),
      tabs: rect('.v2-summary-tabs'),
      activePanel: activePanel ? activePanel.getAttribute('data-v2-summary-panel') : '',
      activeScroll: scrollRect ? {
        top: scrollRect.top,
        right: scrollRect.right,
        bottom: scrollRect.bottom,
        left: scrollRect.left,
        width: scrollRect.width,
        height: scrollRect.height,
        scrollHeight: activeScroll.scrollHeight,
        clientHeight: activeScroll.clientHeight,
        scrollTop: activeScroll.scrollTop,
        overflowY: getComputedStyle(activeScroll).overflowY,
      } : null,
      sourceVisible: Boolean(sourceLayer && !sourceLayer.hidden && sourceStyle.display !== 'none' && sourceStyle.visibility !== 'hidden'),
      sourceDetail: sourceRect ? {
        top: sourceRect.top,
        right: sourceRect.right,
        bottom: sourceRect.bottom,
        left: sourceRect.left,
        width: sourceRect.width,
        height: sourceRect.height,
      } : null,
      sourceBody: sourceBodyRect ? {
        top: sourceBodyRect.top,
        right: sourceBodyRect.right,
        bottom: sourceBodyRect.bottom,
        left: sourceBodyRect.left,
        width: sourceBodyRect.width,
        height: sourceBodyRect.height,
        scrollHeight: sourceBody.scrollHeight,
        clientHeight: sourceBody.clientHeight,
        overflowY: getComputedStyle(sourceBody).overflowY,
      } : null,
      sourceClose: sourceCloseRect ? {
        top: sourceCloseRect.top,
        right: sourceCloseRect.right,
        bottom: sourceCloseRect.bottom,
        left: sourceCloseRect.left,
        width: sourceCloseRect.width,
        height: sourceCloseRect.height,
      } : null,
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyScrollHeight: document.body.scrollHeight,
      htmlScrollHeight: document.documentElement.scrollHeight,
      documentScrollTop: document.scrollingElement ? document.scrollingElement.scrollTop : 0,
    };
  });
}

function validateLayout(label, device, metrics, phase) {
  const prefix = `${label} ${phase}`;
  assert(metrics.bodyOverflow === 'hidden', `${prefix}: body scroll is not locked`);
  assert(metrics.htmlOverflow === 'hidden', `${prefix}: html scroll is not locked`);
  assert(metrics.shell.bottom <= metrics.height + 2, `${prefix}: shell exceeds viewport bottom`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `${prefix}: body overhangs viewport width`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `${prefix}: html overhangs viewport width`);
  assert(metrics.inputbar.bottom <= metrics.height + 2, `${prefix}: inputbar is below viewport`);
  assert(metrics.submit.bottom <= metrics.height + 2, `${prefix}: Save button is below viewport`);
  assert(!metrics.detailInsideHorizontal, `${prefix}: details is still inside horizontal workspace`);
  const activeMobileTab = metrics.visibleViewTabs.find((tab) => tab.active)?.view || '';
  const structuredActive = phase.includes('structured') || activeMobileTab === 'check';
  if (structuredActive) {
    assert(['auto', 'scroll'].includes(metrics.checkOverflow), `${prefix}: active structured check is not the scroll container`);
  } else {
    assert(metrics.feedOverflowY === 'auto', `${prefix}: journal feed is not the vertical scroll container`);
  }

  if (device.kind === 'mobile') {
    assert(metrics.horizontalDisplay === 'flex', `${prefix}: mobile/tablet compact view must use horizontal flex`);
    assert(['auto', 'scroll'].includes(metrics.horizontalOverflowX), `${prefix}: mobile/tablet compact view has no horizontal check scroll`);
    assert(metrics.visibleViewTabs.map((tab) => tab.view).join(',') === 'write,check,quick-notes', `${prefix}: primary tabs are not Write/Check/Quick notes`);
  } else {
    assert(metrics.horizontalDisplay === 'grid', `${prefix}: workspace view must use desktop/tablet grid`);
    assert(metrics.visibleViewTabs.length === 0, `${prefix}: workspace view should not show mobile tabs`);
    assert(metrics.writing.width >= 160, `${prefix}: journal panel is too narrow`);
    assert(metrics.check.width >= 300, `${prefix}: structured check panel is too narrow`);
  }
}

function validateMobileLightLayout(label, metrics, phase) {
  const prefix = `${label} ${phase}`;
  assert(metrics.bodyOverflow === 'hidden', `${prefix}: body scroll is not locked`);
  assert(metrics.htmlOverflow === 'hidden', `${prefix}: html scroll is not locked`);
  assert(metrics.shell.bottom <= metrics.height + 2, `${prefix}: shell exceeds viewport bottom`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `${prefix}: body overhangs viewport width`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `${prefix}: html overhangs viewport width`);
  assert(metrics.inputbar.bottom <= metrics.height + 2, `${prefix}: inputbar is below viewport`);
  assert(metrics.submit.bottom <= metrics.height + 2, `${prefix}: Save button is below viewport`);
  assert(metrics.feedOverflowY === 'auto', `${prefix}: journal feed is not the vertical scroll container`);
  assert(metrics.horizontalDisplay === 'grid', `${prefix}: light mobile view should show one-column journal`);
}

function validateSummaryLayout(label, metrics, phase, options = {}) {
  const prefix = `${label} summary ${phase}`;
  assert(metrics.bodyOverflow === 'hidden', `${prefix}: body scroll is not locked`);
  assert(metrics.htmlOverflow === 'hidden', `${prefix}: html scroll is not locked`);
  assert(metrics.documentScrollTop === 0, `${prefix}: document scrolled outside controlled containers`);
  assert(metrics.shell && metrics.shell.bottom <= metrics.height + 2, `${prefix}: shell exceeds viewport bottom`);
  assert(metrics.summaryScreen && metrics.summaryScreen.bottom <= metrics.height + 2, `${prefix}: summary screen exceeds viewport bottom`);
  assert(metrics.summaryScreen.right <= metrics.width + 2, `${prefix}: summary screen overhangs viewport width`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `${prefix}: body overhangs viewport width`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `${prefix}: html overhangs viewport width`);
  assert(metrics.tabs && metrics.tabs.right <= metrics.width + 2, `${prefix}: summary tabs overhang viewport`);
  assert(metrics.activeScroll, `${prefix}: active summary scroll container missing`);
  assert(metrics.activeScroll.bottom <= metrics.height + 2, `${prefix}: summary scroll container exceeds viewport`);
  assert(['auto', 'scroll'].includes(metrics.activeScroll.overflowY), `${prefix}: active summary panel does not own vertical scroll`);
  if (options.sourceVisible) {
    assert(metrics.sourceVisible, `${prefix}: source overlay is not visible`);
    assert(metrics.sourceDetail && metrics.sourceDetail.right <= metrics.width + 2, `${prefix}: source overlay overhangs viewport width`);
    assert(metrics.sourceDetail.bottom <= metrics.height + 2, `${prefix}: source overlay exceeds viewport bottom`);
    assert(metrics.sourceClose && metrics.sourceClose.top >= -2 && metrics.sourceClose.right <= metrics.width + 2, `${prefix}: source close button is not reachable`);
    assert(metrics.sourceBody && ['auto', 'scroll'].includes(metrics.sourceBody.overflowY), `${prefix}: source overlay body does not own vertical scroll`);
    assert(metrics.sourceBody.bottom <= metrics.height + 2, `${prefix}: source overlay body exceeds viewport`);
  }
}

async function screenshot(page, name, label, phase) {
  const file = path.join(resultsDir, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  report.screenshots.push({ label, phase, file });
}

async function closeDetailsIfVisible(page) {
  const metrics = await collectMetrics(page);
  if (!metrics.detailVisible) return;
  await page.locator('[data-v2-entry-detail-close]').click();
  await page.waitForFunction(() => {
    const detail = document.querySelector('[data-v2-entry-detail]');
    if (!detail) return true;
    const style = getComputedStyle(detail);
    const rect = detail.getBoundingClientRect();
    return detail.hidden
      || detail.getAttribute('aria-hidden') === 'true'
      || style.display === 'none'
      || style.visibility === 'hidden'
      || rect.width === 0
      || rect.height === 0;
  }, null, { timeout: 10000 });
}

async function openDetailsFromRow(page, row, expectedText) {
  await row.scrollIntoViewIfNeeded();
  await row.click();
  await row.focus();
  await page.keyboard.press('Enter');
  await waitForText(page, '[data-v2-entry-detail-body]', expectedText);
}

async function walkthroughLayer1Summary(page, device, result) {
  const summaryResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/reports/layer1-summary');
  });
  await page.locator('[data-v2-screen="summary"]').click();
  assert((await summaryResponse).status() === 200, `${device.label}: Layer 1 summary API failed`);
  await page.locator('[data-v2-summary-screen]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-layer1-information]', 'Категории');

  const summaryPhases = [];
  const captureSummary = async (phase, suffix, options = {}) => {
    const metrics = await collectSummaryMetrics(page);
    validateSummaryLayout(device.label, metrics, phase, options);
    summaryPhases.push({ phase, metrics });
    await screenshot(page, `${device.slug}-summary-${suffix}`, device.label, `summary ${phase}`);
    return metrics;
  };

  await captureSummary('information', '01-information');

  await page.locator('[data-v2-source-total="cash_expense"]').first().click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-source-body]', '-250 рыба');
  await captureSummary('cash expense source trace', '02-cash-expense-source-trace', { sourceVisible: true });
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 10000 });

  await page.locator('[data-v2-summary-tab="storage"]').click();
  await page.locator('[data-v2-summary-panel="storage"]').waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-layer1-storage-save]');
    return button && !button.disabled;
  }, null, { timeout: 10000 });
  const snapshotCreateResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/reports/layer1-snapshots');
  });
  await page.locator('[data-v2-layer1-storage-save]').click();
  assert((await snapshotCreateResponse).status() === 200, `${device.label}: Layer 1 snapshot save failed`);
  await waitForText(page, '[data-v2-layer1-storage]', 'Снимок v');
  await waitForText(page, '[data-v2-layer1-storage]', 'Основа остатка');
  await waitForText(page, '[data-v2-layer1-storage]', 'Записей-источников');
  await captureSummary('storage readback', '03-storage-readback');

  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  result.summary = summaryPhases;
}

async function walkthroughDevice(browser, device, workspaceId) {
  const context = await browser.newContext({
    baseURL: base,
    viewport: device.viewport,
    isMobile: device.kind === 'mobile',
    hasTouch: device.kind === 'mobile',
  });
  await context.addCookies([{ name: cookieName, value: token, url: base }]);
  const page = await context.newPage();
  await page.goto('/v2.php?workspace=' + encodeURIComponent(workspaceId), { waitUntil: 'domcontentloaded' });
  await openWorkspaceInDeviceContext(page, workspaceId);
  await waitForText(page, '[data-v2-feed]', '-250 рыба');

  const result = {
    label: device.label,
    viewport: device.viewport,
    kind: device.kind,
    phases: [],
  };

  const capturePhase = async (phase, suffix) => {
    const metrics = await collectMetrics(page);
    validateLayout(device.label, device, metrics, phase);
    result.phases.push({ phase, metrics });
    await screenshot(page, `${device.slug}-${suffix}`, device.label, phase);
    return metrics;
  };

  const phoneLightMode = device.kind === 'mobile'
    && (device.viewport.width <= 600 || device.viewport.height <= 430);
  if (phoneLightMode) {
    const lightMetrics = await collectMetrics(page);
    validateMobileLightLayout(device.label, lightMetrics, 'light journal view');
    result.phases.push({ phase: 'light journal view', metrics: lightMetrics });
    await screenshot(page, `${device.slug}-00-light`, device.label, 'light journal view');
    await enableMobileFinanceMode(page);
    await page.waitForTimeout(250);
  }

  await capturePhase('initial write/journal view', '01-write');

  await page.locator('[data-v2-feed]').evaluate((feed) => {
    feed.scrollTop = feed.scrollHeight;
  });
  await page.waitForTimeout(150);
  const feedMetrics = await capturePhase('journal vertical scroll bottom', '02-journal-scroll-bottom');
  assert(
    feedMetrics.feedScrollHeight > feedMetrics.feedClientHeight,
    `${device.label}: journal has no vertical scroll room (${feedMetrics.feedScrollHeight}/${feedMetrics.feedClientHeight})`
  );
  assert(
    feedMetrics.feedScrollTop > 0,
    `${device.label}: journal did not scroll vertically (${feedMetrics.feedScrollTop}/${feedMetrics.feedScrollHeight}/${feedMetrics.feedClientHeight})`
  );

  if (device.kind === 'mobile') {
    await page.locator('[data-v2-view="check"]').click();
    await page.waitForTimeout(250);
    await capturePhase('check tab selected', '03-check-tab');

    await page.locator('.v2-horizontal').evaluate((node) => {
      node.scrollLeft = node.scrollWidth;
    });
    await page.locator('[data-v2-check-table]').evaluate((node) => {
      node.scrollLeft = node.scrollWidth;
    });
    await page.waitForTimeout(250);
    const checkScrollMetrics = await capturePhase('horizontal structured check scroll', '04-check-horizontal-scroll');
    assert(checkScrollMetrics.horizontalScrollLeft > 0 || checkScrollMetrics.checkScrollLeft > 0, `${device.label}: structured check did not scroll horizontally`);

    await page.locator('[data-v2-view="write"]').click();
    await page.waitForTimeout(250);
  } else {
    if (device.focusCheck) {
      await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
      await page.waitForTimeout(250);
      const focusMetrics = await capturePhase('desktop structured check focus', '03-check-focus');
      await page.locator('[data-v2-check-table]').evaluate((node) => {
        node.scrollLeft = node.scrollWidth;
      });
      await page.waitForTimeout(150);
      const checkMetrics = await capturePhase('structured check internal horizontal scroll', '04-check-scroll');
      assert(checkMetrics.check.width >= focusMetrics.check.width - 2, `${device.label}: check width changed unexpectedly during internal scroll`);
      assert(checkMetrics.checkScrollWidth > checkMetrics.checkClientWidth, `${device.label}: active structured check has no internal horizontal scroll`);
      assert(checkMetrics.checkScrollLeft > 0, `${device.label}: active structured check did not scroll internally`);
    }
  }

  await closeDetailsIfVisible(page);
  const journalRow = page.locator('[data-v2-entry-select]', { hasText: '-250 рыба' }).first();
  await openDetailsFromRow(page, journalRow, '-250 рыба');
  const detailMetrics = await capturePhase('entry details from journal row', '05-details-from-journal');
  assert(detailMetrics.detailVisible, `${device.label}: details did not open from journal`);
  await closeDetailsIfVisible(page);

  if (device.kind === 'mobile') {
    await page.locator('[data-v2-view="check"]').click();
    await page.waitForTimeout(200);
    await page.locator('.v2-horizontal').evaluate((node) => {
      node.scrollLeft = node.scrollWidth;
    });
  }
  const checkRow = page.locator('[data-v2-check-row][data-v2-entry-id]', { hasText: '-250 рыба' }).first();
  await openDetailsFromRow(page, checkRow, '-250 рыба');
  const detailFromCheckMetrics = await capturePhase('entry details from structured row', '06-details-from-check');
  assert(detailFromCheckMetrics.detailVisible, `${device.label}: details did not open from structured check`);
  await closeDetailsIfVisible(page);

  await walkthroughLayer1Summary(page, device, result);

  report.devices.push(result);
  await context.close();
  console.log(`${device.label}: manual walkthrough OK`);
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
    const context = await browser.newContext({ baseURL: base, viewport: { width: 1365, height: 820 } });
    await context.addCookies([{ name: cookieName, value: token, url: base }]);
    const page = await context.newPage();
    const workspaceResponse = await page.request.post('/v2-api.php?route=' + encodeURIComponent('/api/workspaces'), {
      headers: { 'X-FinDesk-V2-Request': 'fetch' },
      data: {
        name: 'Manual Responsive Walkthrough',
        type: 'yacht',
        opening_cash: 0,
      },
    });
    assert(workspaceResponse.status() === 200, 'workspace API create failed');
    const createdWorkspaceBody = await workspaceResponse.json();
    const workspaceId = createdWorkspaceBody && createdWorkspaceBody.workspace && createdWorkspaceBody.workspace.id;
    assert(workspaceId, 'workspace create response did not include id');
    await page.goto('/v2.php?workspace=' + encodeURIComponent(workspaceId), { waitUntil: 'domcontentloaded' });
    await openWorkspaceInDeviceContext(page, workspaceId);
    await waitForText(page, '[data-v2-status]', 'Ready');
    const flowsResponse = await page.request.get('/v2-api.php?route=' + encodeURIComponent('/api/workspaces/' + workspaceId + '/flows'));
    assert(flowsResponse.status() === 200, 'flows API failed for manual walkthrough seed');
    const flowsBody = await flowsResponse.json();
    const cashFlow = (flowsBody.flows || []).find((flow) => flow.type === 'cash');
    assert(cashFlow && cashFlow.id, 'cash flow missing for manual walkthrough seed');
    await createEntryViaApi(page, workspaceId, {
      flow_id: cashFlow.id,
      date: priorMonthEndDate(),
      raw_text: '+42 manual responsive prior opening source',
    });

    const records = [
      '+1000 снял с карты',
      '-250 рыба',
      '-180 какая-то штука',
      '-35 taxi',
      '-42 fuel',
      '-19 cafe',
      '+500 cash replenish',
      '-60 marina fees',
      '-85 groceries',
      '-70 office supplies',
      '-25 parking',
      '+220 client cash',
    ];
    for (let index = 13; index <= 42; index += 1) {
      const sign = index % 7 === 0 ? '+' : '-';
      const amount = sign === '+' ? 100 + index : 10 + index;
      records.push(`${sign}${amount} responsive audit row ${index}`);
    }
    for (const record of records) {
      await createEntryViaApi(page, workspaceId, {
        flow_id: cashFlow.id,
        date: new Date().toISOString().slice(0, 10),
        raw_text: record,
      });
    }
    await page.goto('/v2.php?workspace=' + encodeURIComponent(workspaceId), { waitUntil: 'domcontentloaded' });
    await openWorkspaceInDeviceContext(page, workspaceId);
    await waitForText(page, '[data-v2-feed]', '-250 рыба');
    await page.screenshot({ path: path.join(resultsDir, 'seed-desktop-created.png'), fullPage: false });
    await context.close();

    const devices = [
      { slug: 'desktop-1365x820', label: 'Desktop 1365x820', viewport: { width: 1365, height: 820 }, kind: 'workspace', focusCheck: true },
      { slug: 'desktop-1440x900', label: 'Desktop 1440x900', viewport: { width: 1440, height: 900 }, kind: 'workspace', focusCheck: true },
      { slug: 'ipad-11-portrait-834x1194', label: 'iPad 11 portrait 834x1194', viewport: { width: 834, height: 1194 }, kind: 'workspace', focusCheck: true },
      { slug: 'ipad-11-landscape-1194x834', label: 'iPad 11 landscape 1194x834', viewport: { width: 1194, height: 834 }, kind: 'workspace', focusCheck: true },
      { slug: 'ipad-mini-portrait-768x1024', label: 'iPad mini portrait 768x1024', viewport: { width: 768, height: 1024 }, kind: 'mobile' },
      { slug: 'ipad-mini-landscape-1024x768', label: 'iPad mini landscape 1024x768', viewport: { width: 1024, height: 768 }, kind: 'mobile' },
      { slug: 'phone-portrait-390x844', label: 'Phone portrait 390x844', viewport: { width: 390, height: 844 }, kind: 'mobile' },
      { slug: 'phone-reduced-360x640', label: 'Phone reduced 360x640', viewport: { width: 360, height: 640 }, kind: 'mobile' },
      { slug: 'phone-landscape-844x390', label: 'Phone landscape 844x390', viewport: { width: 844, height: 390 }, kind: 'mobile' },
    ];

    for (const device of devices) {
      await walkthroughDevice(browser, device, workspaceId);
    }
  } finally {
    await browser.close();
  }

  report.finishedAt = new Date().toISOString();
  fs.writeFileSync(path.join(resultsDir, 'manual-responsive-report.json'), JSON.stringify(report, null, 2));
  console.log(`Manual responsive walkthrough: OK`);
  console.log(`Report: ${path.join(resultsDir, 'manual-responsive-report.json')}`);
  console.log(`Screenshots: ${resultsDir}`);
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
