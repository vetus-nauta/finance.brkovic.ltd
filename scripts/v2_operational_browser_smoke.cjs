const { chromium } = require('playwright-core');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_BROWSER_BASE;
const cookieName = process.env.FINDESK_V2_BROWSER_COOKIE;
const token = process.env.FINDESK_V2_BROWSER_TOKEN;
const chrome = process.env.FINDESK_V2_BROWSER_CHROME;
const dbSocket = process.env.FINDESK_V2_BROWSER_SOCKET;
const dbName = process.env.FINDESK_V2_BROWSER_DB;
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

function sqlQuote(value) {
  return String(value).replace(/\\/g, '\\\\').replace(/'/g, "''");
}

function markClosedMonth(workspaceId, year, month) {
  assert(dbSocket, 'Missing FINDESK_V2_BROWSER_SOCKET');
  assert(dbName, 'Missing FINDESK_V2_BROWSER_DB');
  const sql = `
INSERT INTO v2_monthly_closures (id, workspace_id, year, month, is_closed, closed_by, closed_at)
VALUES ('00000000-0000-4000-8000-000000000404', '${sqlQuote(workspaceId)}', ${year}, ${month}, 1, 19101, NOW())
ON DUPLICATE KEY UPDATE is_closed = 1, closed_by = VALUES(closed_by), closed_at = VALUES(closed_at);
`;
  execFileSync('mariadb', ['--no-defaults', `--socket=${dbSocket}`, '-uroot', dbName], {
    input: sql,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
}

function monthPartsFromDate(value) {
  const match = /^(\d{4})-(\d{2})-\d{2}$/.exec(value);
  assert(match, `invalid date input value: ${value}`);
  return {
    year: Number(match[1]),
    month: Number(match[2]),
  };
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

async function selectEntryByText(page, rawText) {
  const row = page.locator('[data-v2-entry-select]', { hasText: rawText }).first();
  await row.click();
  await waitForText(page, '[data-v2-detail-raw]', rawText);
  await waitForText(page, '[data-v2-entry-detail-body]', rawText);
  return row;
}

async function detailFieldValue(page, label) {
  return page.locator('[data-v2-detail-fields] div', {
    has: page.locator('dt', { hasText: label }),
  }).locator('dd').first().innerText();
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

async function assertViewportLayout(browser, viewport, expected, label, screenshotName = null) {
  const context = await browser.newContext({
    baseURL: base,
    viewport,
    isMobile: expected === 'mobile',
    hasTouch: expected === 'mobile',
  });
  await context.addCookies([{ name: cookieName, value: token, url: base }]);
  const page = await context.newPage();
  await page.goto('/v2.php', { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  const metrics = await page.evaluate(() => {
    const horizontal = document.querySelector('.v2-horizontal');
    const firstPanel = document.querySelector('.v2-panel');
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      horizontalDisplay: getComputedStyle(horizontal).display,
      horizontalOverflowX: getComputedStyle(horizontal).overflowX,
      panelFlexBasis: getComputedStyle(firstPanel).flexBasis,
      bodyOverflow: getComputedStyle(document.body).overflow,
      feedOverflowY: getComputedStyle(document.querySelector('[data-v2-feed]')).overflowY,
    };
  });
  assert(metrics.bodyOverflow === 'hidden', `${label} body overflow must stay hidden: ${JSON.stringify(metrics)}`);
  assert(metrics.feedOverflowY === 'auto', `${label} feed must own vertical scroll: ${JSON.stringify(metrics)}`);
  if (expected === 'mobile') {
    assert(metrics.horizontalDisplay === 'flex', `${label} must use mobile horizontal flex, got ${metrics.horizontalDisplay}: ${JSON.stringify(metrics)}`);
    assert(['auto', 'scroll'].includes(metrics.horizontalOverflowX), `${label} horizontal overflow missing: ${JSON.stringify(metrics)}`);
    assert(metrics.panelFlexBasis === '100%', `${label} panel should snap full width: ${JSON.stringify(metrics)}`);
  } else {
    assert(metrics.horizontalDisplay === 'grid', `${label} must use full workspace grid, got ${metrics.horizontalDisplay}: ${JSON.stringify(metrics)}`);
  }
  if (screenshotName) {
    await page.screenshot({ path: path.join(resultsDir, screenshotName), fullPage: false });
  }
  await context.close();
  console.log(`${label} layout metrics: ${JSON.stringify(metrics)}`);
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
    const categoryPatchBodies = [];
    const closedDecisionBodies = [];
    const attachmentPosts = [];
    const attachmentDeletes = [];
    await page.route('**/v2-api.php?**', async (route) => {
      const request = route.request();
      if (request.method() === 'POST' && routeFromRequest(request).endsWith('/entries')) {
        entryPostBodies.push(request.postData() || '');
        if ((request.postData() || '').includes('duplicate guard')) {
          await new Promise((resolve) => setTimeout(resolve, 250));
        }
      }
      if (request.method() === 'PATCH' && routeFromRequest(request).endsWith('/category')) {
        categoryPatchBodies.push(request.postData() || '');
      }
      if (request.method() === 'POST' && routeFromRequest(request).endsWith('/category/closed-month-decision')) {
        closedDecisionBodies.push(request.postData() || '');
      }
      if (request.method() === 'POST' && routeFromRequest(request).endsWith('/attachments')) {
        attachmentPosts.push(routeFromRequest(request));
      }
      if (request.method() === 'DELETE' && routeFromRequest(request).startsWith('/api/attachments/')) {
        attachmentDeletes.push(routeFromRequest(request));
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
    const workspaceId = await page.locator('[data-v2-workspace-select]').inputValue();
    assert(workspaceId, 'workspace id missing from selector');
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

    const selectedFish = await selectEntryByText(page, '-250 рыба');
    assert((await selectedFish.getAttribute('class')).includes('is-selected'), 'selected feed row missing active state');
    const fishDetail = await page.locator('[data-v2-entry-detail-body]').innerText();
    for (const field of ['raw_text', 'date', 'flow', 'sign', 'amount', 'direction', 'entry_type', 'category', 'actor', 'status', 'balance_after']) {
      assert(fishDetail.includes(field), `entry detail missing field: ${field}`);
    }
    console.log('Entry detail selection: OK');

    const attachmentPath = path.join(resultsDir, 'browser-smoke-attachment.png');
    fs.writeFileSync(
      attachmentPath,
      Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64')
    );
    await page.locator('[data-v2-attachment-input]').setInputFiles(attachmentPath);
    const attachmentUploadResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/attachments')
    ));
    await page.locator('[data-v2-attachment-upload]').click();
    assert((await attachmentUploadResponse).status() === 200, 'attachment upload failed');
    assert(attachmentPosts.length >= 1, 'attachment upload request was not observed');
    await waitForText(page, '[data-v2-attachment-list]', 'browser-smoke-attachment.png');
    await waitForText(page, '[data-v2-status]', 'Attachment saved');

    await page.locator('[data-v2-refresh]').click();
    await waitForText(page, '[data-v2-feed]', '-250 рыба');
    await selectEntryByText(page, '-250 рыба');
    await waitForText(page, '[data-v2-attachment-list]', 'browser-smoke-attachment.png');

    const attachmentDeleteResponse = page.waitForResponse((response) => (
      response.request().method() === 'DELETE'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).startsWith('/api/attachments/')
    ));
    await page.locator('[data-v2-attachment-delete]').first().click();
    assert((await attachmentDeleteResponse).status() === 200, 'attachment delete failed');
    assert(attachmentDeletes.length >= 1, 'attachment delete request was not observed');
    await waitForText(page, '[data-v2-attachment-list]', 'No attachments');
    await waitForText(page, '[data-v2-status]', 'Attachment deleted');
    console.log('Entry attachments upload/list/delete: OK');

    await saveEntry(page, '-180 какая-то штука');
    await waitForText(page, '[data-v2-other-count]', '1');
    await page.locator('[data-v2-other-review-jump]').click();
    await waitForText(page, '[data-v2-detail-raw]', '-180 какая-то штука');
    assert((await page.locator('[data-v2-entry-select].is-review').count()) >= 1, 'other_review row is not highlighted');
    const beforeCategoryPatches = categoryPatchBodies.length;
    await page.locator('[data-v2-category-select]').selectOption('tech_parts');
    const categoryResponse = page.waitForResponse((response) => (
      response.request().method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category')
    ));
    await page.locator('[data-v2-category-save]').click();
    assert((await categoryResponse).status() === 200, 'category patch failed');
    assert(categoryPatchBodies.length - beforeCategoryPatches === 1, 'category save did not send exactly one PATCH');
    await waitForText(page, '[data-v2-status]', 'Category updated');
    await waitForText(page, '[data-v2-other-count]', '0');
    await selectEntryByText(page, '-180 какая-то штука');
    const resolvedDetail = await page.locator('[data-v2-entry-detail-body]').innerText();
    assert(resolvedDetail.includes('tech_parts'), 'category correction did not update detail panel');
    assert(resolvedDetail.includes('recognized'), 'other_review correction did not mark entry recognized');
    await waitForText(page, '[data-v2-check-table]', 'tech_parts');
    console.log('Other review category correction: OK');

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

    await saveEntry(page, '-33 Netflix closed month');
    await selectEntryByText(page, '-33 Netflix closed month');
    await waitForText(page, '[data-v2-entry-detail-body]', 'media_comms');
    const entryMonth = monthPartsFromDate(await page.locator('[data-v2-date]').inputValue());
    await waitForText(page, '[data-v2-month-state]', 'Open');
    const closeMonthResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith(`/months/${entryMonth.year}/${entryMonth.month}/close`)
    ));
    await page.locator('[data-v2-month-toggle]').click();
    assert((await closeMonthResponse).status() === 200, 'month close failed');
    await waitForText(page, '[data-v2-month-state]', 'Closed');
    await waitForText(page, '[data-v2-status]', 'Month closed');
    assert(await page.locator('[data-v2-submit]').isDisabled(), 'entry submit should be disabled while current month is closed');
    await page.locator('[data-v2-category-select]').selectOption('fuel');
    const closedResponse = page.waitForResponse((response) => (
      response.request().method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category')
    ));
    await page.locator('[data-v2-category-save]').click();
    assert((await closedResponse).status() === 409, 'closed month category patch should return 409');
    await waitForText(page, '[data-v2-category-error]', 'Closed month');
    await page.locator('[data-v2-closed-month-decision]').waitFor({ state: 'visible', timeout: 10000 });
    await waitForText(page, '[data-v2-closed-month-decision-to]', 'fuel');
    await waitForText(page, '[data-v2-entry-detail-body]', 'media_comms');
    assert((await detailFieldValue(page, 'category')).includes('media_comms'), 'closed month category mutation appeared optimistic');
    const beforeCancelDecisions = closedDecisionBodies.length;
    await page.locator('[data-v2-closed-month-decision-action="cancel"]').click();
    await page.locator('[data-v2-closed-month-decision]').waitFor({ state: 'hidden', timeout: 10000 });
    assert(closedDecisionBodies.length === beforeCancelDecisions, 'cancel should not send a decision request from UI');
    assert((await page.locator('[data-v2-category-select]').inputValue()) === 'media_comms', 'cancel did not reset category selector');
    await waitForText(page, '[data-v2-status]', 'Closed month change cancelled');

    await page.locator('[data-v2-category-select]').selectOption('fuel');
    const closedResponseForCorrection = page.waitForResponse((response) => (
      response.request().method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category')
    ));
    await page.locator('[data-v2-category-save]').click();
    assert((await closedResponseForCorrection).status() === 409, 'closed month category patch before create correction should return 409');
    await page.locator('[data-v2-closed-month-decision]').waitFor({ state: 'visible', timeout: 10000 });
    const correctionDecision = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category/closed-month-decision')
    ));
    await page.locator('[data-v2-closed-month-decision-action="create_correction"]').click();
    assert((await correctionDecision).status() === 200, 'create correction decision failed');
    assert(closedDecisionBodies.some((body) => body.includes('"decision":"create_correction"')), 'create correction decision body missing');
    await waitForText(page, '[data-v2-status]', 'Correction decision recorded');
    assert((await detailFieldValue(page, 'category')).includes('media_comms'), 'create correction mutated original category');

    await page.locator('[data-v2-category-select]').selectOption('fuel');
    const closedResponseForRecalculate = page.waitForResponse((response) => (
      response.request().method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category')
    ));
    await page.locator('[data-v2-category-save]').click();
    assert((await closedResponseForRecalculate).status() === 409, 'closed month category patch before recalculate should return 409');
    await page.locator('[data-v2-closed-month-decision]').waitFor({ state: 'visible', timeout: 10000 });
    const recalculateDecision = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith('/category/closed-month-decision')
    ));
    await page.locator('[data-v2-closed-month-decision-action="recalculate_chain"]').click();
    assert((await recalculateDecision).status() === 200, 'recalculate decision failed');
    assert(closedDecisionBodies.some((body) => body.includes('"decision":"recalculate_chain"')), 'recalculate decision body missing');
    await waitForText(page, '[data-v2-status]', 'Category updated with recalculation');
    assert((await detailFieldValue(page, 'category')).includes('fuel'), 'recalculate decision did not update category');

    const reopenMonthResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith(`/months/${entryMonth.year}/${entryMonth.month}/reopen`)
    ));
    await page.locator('[data-v2-month-toggle]').click();
    assert((await reopenMonthResponse).status() === 200, 'month reopen failed');
    await waitForText(page, '[data-v2-month-state]', 'Open');
    await waitForText(page, '[data-v2-status]', 'Month reopened');
    assert(!(await page.locator('[data-v2-submit]').isDisabled()), 'entry submit should be enabled after month reopen');
    console.log('Closed-month category decisions: OK');

    for (let i = 1; i <= 8; i += 1) {
      await saveEntry(page, `-1 scroll filler ${i}`);
    }
    await waitForText(page, '[data-v2-feed]', 'scroll filler 8');

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
    await mobilePage.locator('[data-v2-view="write"]').click();
    await mobilePage.waitForTimeout(300);
    const feedScrollMetrics = await mobilePage.locator('[data-v2-feed]').evaluate((feed) => {
      feed.scrollTop = feed.scrollHeight;
      return {
        scrollTop: feed.scrollTop,
        scrollHeight: feed.scrollHeight,
        clientHeight: feed.clientHeight,
      };
    });
    assert(feedScrollMetrics.scrollHeight > feedScrollMetrics.clientHeight, `phone feed should own vertical history scroll: ${JSON.stringify(feedScrollMetrics)}`);
    assert(feedScrollMetrics.scrollTop > 0, `phone feed did not scroll vertically: ${JSON.stringify(feedScrollMetrics)}`);
    await mobilePage.setViewportSize({ width: 390, height: 520 });
    await mobilePage.locator('[data-v2-raw-text]').focus();
    const inputReachMetrics = await mobilePage.evaluate(() => {
      const shell = document.querySelector('[data-v2-app]');
      const inputbar = document.querySelector('[data-v2-entry-form]');
      const workspace = document.querySelector('[data-v2-workspace]');
      const submit = document.querySelector('[data-v2-submit]');
      const shellRect = shell.getBoundingClientRect();
      const inputRect = inputbar.getBoundingClientRect();
      const workspaceRect = workspace.getBoundingClientRect();
      const submitRect = submit.getBoundingClientRect();
      return {
        shellTop: shellRect.top,
        shellBottom: shellRect.bottom,
        shellWidth: shellRect.width,
        inputTop: inputRect.top,
        inputBottom: inputRect.bottom,
        inputHeight: inputRect.height,
        workspaceHeight: workspaceRect.height,
        submitTop: submitRect.top,
        submitBottom: submitRect.bottom,
        bodyScrollWidth: document.body.scrollWidth,
        htmlScrollWidth: document.documentElement.scrollWidth,
        windowWidth: window.innerWidth,
        windowHeight: window.innerHeight,
        bodyOverflow: getComputedStyle(document.body).overflow,
      };
    });
    await mobilePage.locator('[data-v2-submit]').click({ trial: true });
    await mobilePage.screenshot({ path: path.join(resultsDir, 'mobile-reduced-viewport-fit.png'), fullPage: false });
    assert(inputReachMetrics.shellTop >= -2, `phone shell starts outside viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.shellBottom <= inputReachMetrics.windowHeight + 2, `phone shell exceeds viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.bodyScrollWidth <= inputReachMetrics.windowWidth + 2, `phone body overhangs viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.htmlScrollWidth <= inputReachMetrics.windowWidth + 2, `phone document overhangs viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.inputBottom <= inputReachMetrics.windowHeight + 2, `phone input hidden in reduced viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.inputHeight <= 64, `phone input bar consumes too much viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.workspaceHeight >= 240, `phone workspace collapsed in reduced viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.submitBottom <= inputReachMetrics.windowHeight + 2, `phone submit hidden in reduced viewport: ${JSON.stringify(inputReachMetrics)}`);
    assert(inputReachMetrics.bodyOverflow === 'hidden', `phone keyboard check changed body overflow: ${JSON.stringify(inputReachMetrics)}`);
    await mobilePage.setViewportSize({ width: 390, height: 844 });
    await mobilePage.locator('[data-v2-entry-select]', { hasText: '-250 рыба' }).first().click();
    await mobilePage.waitForTimeout(650);
    await waitForText(mobilePage, '[data-v2-detail-raw]', '-250 рыба');
    const detailScrollLeft = await mobilePage.locator('.v2-horizontal').evaluate((node) => node.scrollLeft);
    assert(detailScrollLeft > 20, `mobile details view did not move horizontally: ${detailScrollLeft}`);
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
    assert(mobileMetrics.horizontalScrollLeft > detailScrollLeft + 20, `mobile check view did not move horizontally: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.checkLeft < mobileMetrics.windowWidth && mobileMetrics.checkRight > 0, `mobile check panel not visible: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.bodyOverflow === 'hidden', `mobile body overflow must stay hidden: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.feedOverflowY === 'auto', `mobile feed must own vertical scroll: ${JSON.stringify(mobileMetrics)}`);
    assert(['auto', 'scroll'].includes(mobileMetrics.horizontalOverflowX), `mobile horizontal overflow missing: ${JSON.stringify(mobileMetrics)}`);
    await mobilePage.screenshot({ path: path.join(resultsDir, 'mobile-structured-check.png'), fullPage: false });
    await mobile.close();
    console.log(`Mobile horizontal metrics: ${JSON.stringify(mobileMetrics)}`);
    console.log(`Mobile feed/input metrics: ${JSON.stringify({ feedScrollMetrics, inputReachMetrics })}`);

    await assertViewportLayout(browser, { width: 768, height: 1024 }, 'mobile', 'iPad mini portrait', 'ipad-mini-portrait.png');
    await assertViewportLayout(browser, { width: 1024, height: 768 }, 'mobile', 'iPad mini landscape', 'ipad-mini-landscape.png');
    await assertViewportLayout(browser, { width: 834, height: 1194 }, 'desktop', 'iPad 11 portrait', 'ipad-11-portrait.png');
    await assertViewportLayout(browser, { width: 1194, height: 834 }, 'desktop', 'iPad 11 landscape', 'ipad-11-landscape.png');

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
