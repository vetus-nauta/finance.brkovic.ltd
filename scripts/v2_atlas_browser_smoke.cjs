const { spawn } = require('node:child_process');
const fs = require('node:fs');
const http = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright-core');
const atlasRuntime = require('../server/findesk-v2-atlas-read-server');

const ROOT = path.resolve(__dirname, '..');
const WORKSPACE_ID = process.env.FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID || '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
const CASH_FLOW_ID = process.env.FINDESK_V2_CLAUDIA_Z_CASH_FLOW_ID || 'c5c895ad-8f4a-4503-8ef7-6676ccc76d32';
const RESULTS_DIR = process.env.FINDESK_V2_ATLAS_BROWSER_RESULTS || path.join(ROOT, 'test-results', 'v2-atlas-browser-smoke');
const TMP_DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'findesk-v2-atlas-browser.'));
const processes = [];
let failed = false;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function freePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(0, '127.0.0.1', () => {
      const address = server.address();
      const port = address && typeof address === 'object' ? address.port : 0;
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

function requestJson(url, options = {}) {
  return new Promise((resolve, reject) => {
    const target = new URL(url);
    const body = options.body || '';
    const req = http.request({
      hostname: target.hostname,
      port: target.port,
      path: `${target.pathname}${target.search}`,
      method: options.method || 'GET',
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) } : {}),
        ...(options.headers || {}),
      },
    }, (res) => {
      let raw = '';
      res.setEncoding('utf8');
      res.on('data', (chunk) => { raw += chunk; });
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode || 0, data: raw ? JSON.parse(raw) : null });
        } catch (error) {
          reject(error);
        }
      });
    });
    req.on('error', reject);
    if (body) req.write(body);
    req.end();
  });
}

function apiRouteFromResponse(response) {
  try {
    return new URL(response.url()).searchParams.get('route') || '';
  } catch {
    return '';
  }
}

async function inputStateSnapshot(page) {
  return page.evaluate(() => {
    const form = document.querySelector('[data-v2-entry-form]');
    const input = document.querySelector('[data-v2-raw-text]');
    const submit = document.querySelector('[data-v2-submit]');
    const edit = document.querySelector('[data-v2-entry-edit-save]');
    const del = document.querySelector('[data-v2-entry-delete]');
    const selected = document.querySelector('[data-v2-entry-select].is-active, [data-v2-entry-select][aria-selected="true"]');
    return {
      formClass: form ? form.className : '',
      inputValue: input ? input.value : '',
      inputReadOnly: input ? input.readOnly : null,
      submitText: submit ? submit.textContent.trim() : '',
      submitHidden: submit ? submit.hidden : null,
      submitDisabled: submit ? submit.disabled : null,
      editText: edit ? edit.textContent.trim() : '',
      editHidden: edit ? edit.hidden : null,
      deleteText: del ? del.textContent.trim() : '',
      deleteHidden: del ? del.hidden : null,
      status: document.querySelector('[data-v2-status]')?.textContent || '',
      selectedEntryId: selected ? selected.getAttribute('data-v2-entry-id') : '',
      body: document.body.innerText.slice(0, 1200),
    };
  });
}

async function waitForCreateReady(page, label) {
  try {
    await page.waitForFunction(() => {
      const form = document.querySelector('[data-v2-entry-form]');
      const input = document.querySelector('[data-v2-raw-text]');
      const submit = document.querySelector('[data-v2-submit]');
      return form
        && input
        && submit
        && !form.classList.contains('is-editing')
        && !form.classList.contains('is-previewing')
        && !input.readOnly
        && submit.textContent.trim() === 'Сохранить';
    }, null, { timeout: 15000 });
  } catch (error) {
    const snapshot = await inputStateSnapshot(page);
    await page.screenshot({ path: path.join(RESULTS_DIR, `${label}.png`), fullPage: true });
    throw new Error(`${label}: create input did not become ready: ${JSON.stringify(snapshot).slice(0, 2000)}`);
  }
}

async function waitForPreviewReady(page, label) {
  try {
    await page.waitForFunction(() => {
      const form = document.querySelector('[data-v2-entry-form]');
      const button = document.querySelector('[data-v2-entry-edit-save]');
      return form
        && button
        && form.classList.contains('is-previewing')
        && !button.disabled
        && button.textContent.trim() === 'Править';
    }, null, { timeout: 15000 });
  } catch (error) {
    const snapshot = await inputStateSnapshot(page);
    await page.screenshot({ path: path.join(RESULTS_DIR, `${label}.png`), fullPage: true });
    throw new Error(`${label}: row preview did not become ready: ${JSON.stringify(snapshot).slice(0, 2000)}`);
  }
}

async function waitForEditReady(page, label) {
  try {
    await page.waitForFunction(() => {
      const form = document.querySelector('[data-v2-entry-form]');
      const input = document.querySelector('[data-v2-raw-text]');
      return form && input && form.classList.contains('is-editing') && !input.readOnly;
    }, null, { timeout: 15000 });
  } catch (error) {
    const snapshot = await inputStateSnapshot(page);
    await page.screenshot({ path: path.join(RESULTS_DIR, `${label}.png`), fullPage: true });
    throw new Error(`${label}: row edit did not become ready: ${JSON.stringify(snapshot).slice(0, 2000)}`);
  }
}

async function waitForJson(url, label) {
  for (let index = 0; index < 150; index += 1) {
    try {
      const response = await requestJson(url);
      if (response.status >= 200 && response.status < 500) return response;
    } catch {
      // keep waiting
    }
    await sleep(200);
  }
  throw new Error(`${label} did not become ready`);
}

function findChrome() {
  const candidates = [
    process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH,
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate));
}

function writeHarness() {
  const publicDir = path.join(TMP_DIR, 'public');
  const appDir = path.join(TMP_DIR, 'app');
  fs.mkdirSync(path.join(publicDir, 'assets', 'v2'), { recursive: true });
  fs.mkdirSync(path.join(appDir, 'v2'), { recursive: true });
  fs.mkdirSync(path.join(TMP_DIR, 'storage', 'logs'), { recursive: true });

  fs.copyFileSync(path.join(ROOT, 'public', 'v2.php'), path.join(publicDir, 'v2.php'));
  fs.copyFileSync(path.join(ROOT, 'public', 'v2-api.php'), path.join(publicDir, 'v2-api.php'));
  fs.cpSync(path.join(ROOT, 'public', 'assets', 'v2'), path.join(publicDir, 'assets', 'v2'), { recursive: true });
  fs.copyFileSync(path.join(ROOT, 'app', 'auth.php'), path.join(appDir, 'auth.php'));
  fs.cpSync(path.join(ROOT, 'app', 'v2'), path.join(appDir, 'v2'), { recursive: true });

  fs.writeFileSync(path.join(appDir, 'db.php'), `<?php
function ql_config(): array { return ['session_cookie_name' => 'findesk_v2_atlas_browser']; }
function ql_db(): PDO { throw new RuntimeException('Atlas browser smoke should not touch MySQL'); }
`);
  fs.writeFileSync(path.join(publicDir, 'api.php'), `<?php
require_once __DIR__ . '/../app/auth.php';
$action = $_GET['action'] ?? '';
if ($action === 'current_user') {
    ql_json(['ok' => true, 'user' => ['id' => 1, 'email' => 'vetus.nauta@gmail.com', 'display_name' => 'Atlas Browser Smoke']]);
}
if ($action === 'logout') {
    ql_json(['ok' => true]);
}
ql_json(['ok' => false, 'error' => 'unsupported_auth_action'], 404);
`);
  return publicDir;
}

function startProcess(command, args, options, name) {
  const child = spawn(command, args, { stdio: ['ignore', 'pipe', 'pipe'], ...options });
  const logFile = path.join(TMP_DIR, `${name}.log`);
  const stream = fs.createWriteStream(logFile);
  child.stdout.pipe(stream);
  child.stderr.pipe(stream);
  processes.push(child);
  child.on('exit', (code, signal) => {
    if (code && code !== 0) {
      fs.appendFileSync(logFile, `\n${name} exited with ${code || signal}\n`);
    }
  });
  return child;
}

async function cleanupEntry(baseUrl, entryId) {
  if (!entryId) return false;
  const response = await atlasRuntime.handleApi(
    'DELETE',
    `/api/entries/${entryId}`,
    {},
    { closed_month_decision: 'recalculate_chain' }
  );
  return response.ok === true;
}

async function main() {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
  const chrome = findChrome();
  assert(chrome, 'Missing Chrome/Chromium executable');

  const atlasPort = await freePort();
  startProcess(process.execPath, [path.join(ROOT, 'server', 'findesk-v2-atlas-read-server.js')], {
    cwd: ROOT,
    env: { ...process.env, FINDESK_V2_ATLAS_READ_PORT: String(atlasPort) },
  }, 'atlas-sidecar');
  await waitForJson(`http://127.0.0.1:${atlasPort}/api?route=/api/workspaces`, 'Atlas sidecar');

  const publicDir = writeHarness();
  const phpPort = await freePort();
  const baseUrl = `http://127.0.0.1:${phpPort}`;
  startProcess('php', ['-S', `127.0.0.1:${phpPort}`, '-t', publicDir], {
    cwd: TMP_DIR,
    env: {
      ...process.env,
      FINDESK_V2_RUNTIME: 'atlas_write',
      FINDESK_V2_ATLAS_READ_BASE_URL: `http://127.0.0.1:${atlasPort}`,
      FINDESK_V2_ATLAS_PROXY_TIMEOUT_MS: '90000',
    },
  }, 'php-ui');
  await waitForJson(`${baseUrl}/api.php?action=current_user`, 'PHP UI');
  const proxyPreflight = await requestJson(`${baseUrl}/v2-api.php?route=/api/workspaces`);
  assert(proxyPreflight.status === 200 && proxyPreflight.data && proxyPreflight.data.ok === true, 'PHP Atlas proxy preflight failed');
  assert(Array.isArray(proxyPreflight.data.workspaces) && proxyPreflight.data.workspaces.length >= 1, 'PHP Atlas proxy returned no workspaces');

  let createdEntryId = '';
  const browser = await chromium.launch({ executablePath: chrome, headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1365, height: 900 } });
    page.setDefaultTimeout(120000);
    const consoleErrors = [];
    page.on('console', (message) => {
      if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => consoleErrors.push(error.message));

    const screenUrl = (screen) => `${baseUrl}/v2.php?screen=${screen}&workspace=${WORKSPACE_ID}&fresh=atlas-browser-smoke`;

    await page.goto(screenUrl('operational'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-workspace-select]');
    await page.waitForFunction(() => {
      const select = document.querySelector('[data-v2-workspace-select]');
      return select && select.options && select.options.length > 0;
    }, null, { timeout: 15000 }).catch(async () => {
      await page.screenshot({ path: path.join(RESULTS_DIR, 'workspace-select-empty.png'), fullPage: true });
      const debug = await page.evaluate(() => ({
        status: document.querySelector('[data-v2-status]')?.textContent || '',
        body: document.body.innerText.slice(0, 1000),
        options: document.querySelector('[data-v2-workspace-select]')?.innerHTML || '',
      }));
      throw new Error(`Workspace selector stayed empty: ${JSON.stringify(debug)}`);
    });
    await page.waitForSelector('[data-v2-entry-select]');
    await page.screenshot({ path: path.join(RESULTS_DIR, 'desktop-operational.png'), fullPage: true });

    const desktopOverflow = await page.evaluate(() => ({
      width: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
      bodyWidth: document.body.scrollWidth,
    }));
    assert(desktopOverflow.width <= desktopOverflow.viewport + 2, `Desktop horizontal overflow: ${JSON.stringify(desktopOverflow)}`);

    await page.goto(screenUrl('summary'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-summary-screen]:not([hidden])');
    await page.screenshot({ path: path.join(RESULTS_DIR, 'desktop-summary.png'), fullPage: true });

    await page.goto(screenUrl('training'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-training-screen]:not([hidden])');
    await page.screenshot({ path: path.join(RESULTS_DIR, 'desktop-training.png'), fullPage: true });

    await page.goto(screenUrl('hall'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-hall]');
    await page.screenshot({ path: path.join(RESULTS_DIR, 'desktop-hall.png'), fullPage: true });

    await page.goto(screenUrl('operational'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-entry-select]');
    await page.fill('[data-v2-date]', '2026-08-31');
    const raw = `-1 продукты atlas browser smoke ${Date.now()}`;
    const edited = `${raw} edited`;
    const createResponsePromise = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && /\/api\/workspaces\/[a-f0-9-]{36}\/entries$/i.test(apiRouteFromResponse(response))
    ));
    await page.fill('[data-v2-raw-text]', raw);
    await page.click('[data-v2-submit]');
    const createResponse = await createResponsePromise;
    const createData = await createResponse.json();
    assert(
      createData.ok === true && createData.entry && createData.entry.id,
      `UI create did not return entry: ${JSON.stringify(createData).slice(0, 1000)}`
    );
    createdEntryId = createData.entry.id;

    await page.locator(`[data-v2-entry-select][data-v2-entry-id="${createdEntryId}"]`).click();
    await page.click('[data-v2-entry-edit-save]');
    await page.waitForFunction(() => {
      const input = document.querySelector('[data-v2-raw-text]');
      return input && !input.readOnly;
    });
    await page.fill('[data-v2-raw-text]', edited);
    const patchResponsePromise = page.waitForResponse((response) => (
      response.request().method() === 'PATCH'
      && apiRouteFromResponse(response) === `/api/entries/${createdEntryId}`
    ));
    await page.click('[data-v2-entry-edit-save]');
    const patchResponse = await patchResponsePromise;
    const patchData = await patchResponse.json();
    assert(
      patchData.ok === true && patchData.entry && patchData.entry.raw_text === edited,
      `UI edit did not persist edited text: ${JSON.stringify(patchData).slice(0, 1000)}`
    );
    await waitForCreateReady(page, 'post-edit-create-ready');
    await page.waitForFunction(() => {
      return (document.querySelector('[data-v2-status]')?.textContent || '').includes('Запись обновлена');
    }, null, { timeout: 30000 });
    await waitForCreateReady(page, 'post-edit-final-create-ready');
    await sleep(450);

    const editedRow = page.locator(`[data-v2-entry-select][data-v2-entry-id="${createdEntryId}"]`);
    await editedRow.scrollIntoViewIfNeeded();
    await editedRow.click();
    await waitForPreviewReady(page, 'delete-preview-ready');
    await page.click('[data-v2-entry-edit-save]');
    await waitForEditReady(page, 'delete-edit-ready');
    await page.click('[data-v2-entry-delete]');
    const deleteResponsePromise = page.waitForResponse((response) => (
      response.request().method() === 'DELETE'
      && apiRouteFromResponse(response) === `/api/entries/${createdEntryId}`
    ));
    await page.click('[data-v2-entry-delete]');
    const deleteResponse = await deleteResponsePromise;
    const deleteData = await deleteResponse.json();
    assert(deleteData.ok === true, `UI delete failed: ${JSON.stringify(deleteData).slice(0, 1000)}`);
    createdEntryId = '';

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(screenUrl('operational'), { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-v2-entry-select]');
    await page.screenshot({ path: path.join(RESULTS_DIR, 'mobile-operational.png'), fullPage: true });
    const mobileOverflow = await page.evaluate(() => ({
      width: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
      bodyWidth: document.body.scrollWidth,
    }));
    assert(mobileOverflow.width <= mobileOverflow.viewport + 2, `Mobile horizontal overflow: ${JSON.stringify(mobileOverflow)}`);
    assert(consoleErrors.length === 0, `Browser console errors: ${consoleErrors.join(' | ')}`);

    const result = {
      ok: true,
      checked_at: new Date().toISOString(),
      base_url: baseUrl,
      workspace_id: WORKSPACE_ID,
      screenshots: RESULTS_DIR,
      desktop_overflow: desktopOverflow,
      mobile_overflow: mobileOverflow,
      disposable_entry_cleaned: true,
      ui_delete_covered: true,
    };
    fs.writeFileSync(path.join(RESULTS_DIR, 'result.json'), `${JSON.stringify(result, null, 2)}\n`);
    console.log(JSON.stringify(result, null, 2));
  } finally {
    await browser.close();
    if (createdEntryId) {
      await cleanupEntry(baseUrl, createdEntryId);
    }
  }
}

main()
  .catch((error) => {
    failed = true;
    console.error(JSON.stringify({ ok: false, error: error.message }, null, 2));
    process.exitCode = 1;
  })
  .finally(async () => {
    for (const child of processes.reverse()) {
      if (!child.killed) child.kill();
    }
    if (failed || process.env.FINDESK_V2_ATLAS_BROWSER_KEEP_TMP === '1') {
      console.error(`Atlas browser smoke temp kept: ${TMP_DIR}`);
    } else {
      fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    await atlasRuntime.closeDb();
  });
