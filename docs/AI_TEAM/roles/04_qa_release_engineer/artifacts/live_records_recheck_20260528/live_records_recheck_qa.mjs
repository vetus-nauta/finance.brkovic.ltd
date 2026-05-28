import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { chromium } from '/tmp/findesk-pw/node_modules/playwright/index.mjs';

const prodUrl = 'https://finance.brkovic.ltd';
const artifactDir = new URL('.', import.meta.url).pathname;
const repoRoot = path.resolve(artifactDir, '../../../../../..');
const publicRoot = path.join(repoRoot, 'public');
const runId = process.env.FINDESK_QA_RUN_ID || '20260528RECORDSRECHECK01';
const inbox = 'vetus.nauta@gmail.com';
const codes = JSON.parse(process.env.FINDESK_QA_CODES_JSON || '{}');
const emails = {
  admin: inbox.replace('@', `+qa-records-recheck-admin-${runId}@`),
  member: inbox.replace('@', `+qa-records-recheck-member-${runId}@`),
  base: inbox.replace('@', `+qa-records-recheck-base-${runId}@`),
};

const cookies = {};
const ids = { run_id: runId, target: 'local frontend assets + production API proxy' };
const checks = [];
const blockers = [];
const defects = [];
const screenshots = [];
const apiCalls = [];
const browserDiagnostics = [];

function redact(value) {
  if (Array.isArray(value)) return value.map(redact);
  if (!value || typeof value !== 'object') return value;
  const out = {};
  for (const [key, val] of Object.entries(value)) {
    if (/cookie|token|code|password|pass/i.test(key)) out[key] = '[redacted]';
    else if (/email/i.test(key)) out[key] = '[email]';
    else out[key] = redact(val);
  }
  return out;
}

function assertOk(condition, message, context = {}) {
  if (!condition) {
    const error = new Error(message);
    error.context = redact(context);
    throw error;
  }
  checks.push({ status: 'PASS', message });
}

function recordBlocker(owner, title, evidence = {}) {
  blockers.push({ owner, title, evidence: redact(evidence) });
}

function recordDefect(owner, title, evidence = {}) {
  defects.push({ owner, title, evidence: redact(evidence) });
}

function cookieHeader(role) {
  return cookies[role] ? Object.entries(cookies[role]).map(([k, v]) => `${k}=${v}`).join('; ') : '';
}

function rememberCookies(role, headers) {
  const rows = typeof headers.getSetCookie === 'function'
    ? headers.getSetCookie()
    : (headers.get('set-cookie') ? [headers.get('set-cookie')] : []);
  if (!cookies[role]) cookies[role] = {};
  for (const row of rows) {
    const first = row.split(';')[0] || '';
    const eq = first.indexOf('=');
    if (eq > 0) cookies[role][first.slice(0, eq)] = first.slice(eq + 1);
  }
}

async function api(role, action, payload = {}) {
  const headers = { 'Content-Type': 'application/json' };
  const cookie = role ? cookieHeader(role) : '';
  if (cookie) headers.Cookie = cookie;
  const response = await fetch(`${prodUrl}/api.php?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload),
  });
  if (role) rememberCookies(role, response.headers);
  const text = await response.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`Bad JSON from ${action}: HTTP ${response.status}: ${text.slice(0, 300)}`);
  }
  apiCalls.push({ role, action, payload: redact(payload), status: response.status, ok: json.ok === true, error: json.error || '' });
  return json;
}

async function uploadFile(role, captureId, fileName, mimeType, buffer, fields = {}) {
  const form = new FormData();
  form.append('capture_id', String(captureId));
  for (const [key, value] of Object.entries(fields)) form.append(key, String(value));
  form.append('file', new File([buffer], fileName, { type: mimeType }));
  const response = await fetch(`${prodUrl}/api.php?action=on_the_go_upload_file`, {
    method: 'POST',
    headers: { Cookie: cookieHeader(role) },
    body: form,
  });
  const json = await response.json();
  apiCalls.push({ role, action: 'on_the_go_upload_file', payload: { capture_id: captureId, fileName, mimeType }, status: response.status, ok: json.ok === true, error: json.error || '' });
  return json;
}

async function login(role) {
  const code = codes[role];
  assertOk(/^\d{6}$/.test(code || ''), `auth code supplied for ${role}`);
  const verified = await api(role, 'verify_code', { email: emails[role], code });
  assertOk(verified.ok === true && verified.user?.id > 0, `verify_code ${role}`, verified);
  ids[`${role}_user_id`] = Number(verified.user.id);
}

async function setupFixture() {
  await login('admin');
  await login('member');
  await login('base');

  const group = await api('admin', 'group_create', { name: `QA records recheck ${runId}` });
  assertOk(group.ok === true && group.group?.id > 0, 'admin created group', group);
  ids.group_id = Number(group.group.id);

  for (const role of ['member', 'base']) {
    const invite = await api('admin', 'group_invite_create', {
      group_id: ids.group_id,
      channel: 'copy',
      invited_email: emails[role],
      access_level: 'base',
    });
    assertOk(invite.ok === true && invite.invite?.url, `admin invited ${role}`, invite);
    const token = new URL(invite.invite.url).searchParams.get('invite');
    const join = await api(role, 'group_join', { token });
    assertOk(join.ok === true && Number(join.group?.id) === ids.group_id, `${role} joined as base`, join);
  }

  const tape = await api('member', 'on_the_go_tape_create', {
    group_id: ids.group_id,
    title: `QA records recheck long proof card ${runId}`,
    cash_received: '100',
    stream_type: 'cash',
  });
  assertOk(tape.ok === true && tape.tape?.id > 0, 'member created Live Report card', tape);
  ids.tape_id = Number(tape.tape.id);

  const capture = await api('member', 'on_the_go_create', {
    tape_id: ids.tape_id,
    capture_type: 'cash_out',
    amount: '12.34',
    currency: 'EUR',
    description: `QA recheck image and PDF proof with long description ${runId}`,
  });
  assertOk(capture.ok === true && capture.capture?.id > 0, 'member created record', capture);
  ids.capture_id = Number(capture.capture.id);

  const bundleId = `qa-recheck-bundle-${runId}`;
  ids.proof_bundle_id = bundleId;
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
    'base64'
  );
  const pdf = Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >> endobj\n4 0 obj << /Length 47 >> stream\nBT /F1 12 Tf 30 100 Td (QA recheck PDF) Tj ET\nendstream endobj\nxref\n0 5\n0000000000 65535 f \ntrailer << /Root 1 0 R /Size 5 >>\nstartxref\n298\n%%EOF\n');

  const imageUpload = await uploadFile('member', ids.capture_id, `qa-recheck-photo-${runId}-long-file-name.png`, 'image/png', png, {
    proof_role: 'scanner_original',
    proof_bundle_id: bundleId,
    client_upload_id: `qa-recheck-image-${runId}`,
  });
  assertOk(imageUpload.ok === true && imageUpload.file?.id > 0, 'member uploaded image proof', imageUpload);
  ids.image_file_id = Number(imageUpload.file.id);

  const pdfUpload = await uploadFile('member', ids.capture_id, `qa-recheck-scan-${runId}-long-file-name.pdf`, 'application/pdf', pdf, {
    proof_role: 'scanner_cleaned_pdf',
    proof_bundle_id: bundleId,
    source_file_id: ids.image_file_id,
    client_upload_id: `qa-recheck-pdf-${runId}`,
  });
  assertOk(pdfUpload.ok === true && pdfUpload.file?.id > 0, 'member uploaded PDF proof', pdfUpload);
  ids.pdf_file_id = Number(pdfUpload.file.id);

  const submitted = await api('member', 'on_the_go_card_submit', { id: ids.tape_id, group_id: ids.group_id });
  assertOk(submitted.ok === true, 'member submitted card to FinDesk', submitted);
}

async function apiIsolationChecks() {
  const adminCards = await api('admin', 'on_the_go_card_list', { group_id: ids.group_id, stream_type: 'cash', limit: 80 });
  assertOk(adminCards.ok === true && (adminCards.cards || []).some((card) => Number(card.id) === ids.tape_id), 'admin API card_list with group_id sees employee card', adminCards);

  const baseCards = await api('base', 'on_the_go_card_list', { group_id: ids.group_id, stream_type: 'cash', limit: 80 });
  assertOk(baseCards.ok === true && !(baseCards.cards || []).some((card) => Number(card.id) === ids.tape_id), 'base employee API card_list does not expose other employee card', baseCards);

  const baseDetail = await api('base', 'on_the_go_card_detail', { id: ids.tape_id });
  assertOk(baseDetail.ok === false && baseDetail.error === 'card_not_found', 'base employee cannot open other employee card detail', baseDetail);

  const baseFiles = await api('base', 'on_the_go_file_list', { capture_id: ids.capture_id });
  assertOk(baseFiles.ok === false && baseFiles.error === 'capture_not_found', 'base employee cannot list other employee proof files', baseFiles);
}

function contentType(filePath) {
  if (filePath.endsWith('.js')) return 'application/javascript; charset=utf-8';
  if (filePath.endsWith('.css')) return 'text/css; charset=utf-8';
  if (filePath.endsWith('.webmanifest')) return 'application/manifest+json; charset=utf-8';
  if (filePath.endsWith('.png')) return 'image/png';
  if (filePath.endsWith('.ico')) return 'image/x-icon';
  return 'text/html; charset=utf-8';
}

function startLocalFrontendProxy(role) {
  const server = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url || '/', 'http://127.0.0.1');
      if (url.pathname === '/api.php') {
        const body = await new Promise((resolve) => {
          const chunks = [];
          req.on('data', (chunk) => chunks.push(chunk));
          req.on('end', () => resolve(Buffer.concat(chunks)));
        });
        const headers = {};
        const cookie = cookieHeader(role);
        if (cookie) headers.Cookie = cookie;
        const type = req.headers['content-type'];
        if (type) headers['Content-Type'] = type;
        const upstream = await fetch(`${prodUrl}${url.pathname}${url.search}`, {
          method: req.method,
          headers,
          body: req.method === 'GET' || req.method === 'HEAD' ? undefined : body,
        });
        const out = Buffer.from(await upstream.arrayBuffer());
        res.writeHead(upstream.status, {
          'Content-Type': upstream.headers.get('content-type') || 'application/octet-stream',
          'Content-Disposition': upstream.headers.get('content-disposition') || '',
          'Cache-Control': 'no-store',
        });
        res.end(out);
        return;
      }

      let filePath = url.pathname === '/' ? '/app.php' : url.pathname;
      filePath = path.normalize(filePath).replace(/^(\.\.[/\\])+/, '');
      const abs = path.join(publicRoot, filePath);
      if (!abs.startsWith(publicRoot) || !fs.existsSync(abs) || !fs.statSync(abs).isFile()) {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        res.end('not found');
        return;
      }
      let body = fs.readFileSync(abs);
      if (abs.endsWith('app.php')) {
        body = Buffer.from(String(body).replace(/^<\?php[\s\S]*?\?>\s*/m, ''), 'utf8');
      }
      res.writeHead(200, { 'Content-Type': contentType(abs), 'Cache-Control': 'no-store' });
      res.end(body);
    } catch (error) {
      res.writeHead(500, { 'Content-Type': 'text/plain' });
      res.end(error.message);
    }
  });
  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => {
      const address = server.address();
      resolve({ server, url: `http://127.0.0.1:${address.port}` });
    });
  });
}

async function browserChecks() {
  const browser = await chromium.launch({ headless: true });
  const viewports = [
    ['mobile390x844', 390, 844],
    ['tablet820x1180', 820, 1180],
    ['desktop1440x900', 1440, 900],
  ];

  for (const role of ['admin', 'base']) {
    const proxy = await startLocalFrontendProxy(role);
    try {
      for (const [label, width, height] of viewports) {
        const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 1 });
        const page = await context.newPage();
        const diag = { role, label, console: [], pageerror: [], requestfailed: [] };
        browserDiagnostics.push(diag);
        page.on('console', (msg) => diag.console.push({ type: msg.type(), text: msg.text().slice(0, 500) }));
        page.on('pageerror', (error) => {
          diag.pageerror.push(error.message);
          recordDefect('Frontend/UX', `${role} ${label} JS pageerror`, { message: error.message });
        });
        page.on('requestfailed', (request) => diag.requestfailed.push({ url: request.url(), failure: request.failure()?.errorText || '' }));
        await page.goto(`${proxy.url}/app.php`, { waitUntil: 'networkidle' });
        try {
          await page.waitForFunction(() => typeof window.qlSetModule === 'function' && typeof window.qlOpenOtrReportCards === 'function', null, { timeout: 15000 });
        } catch (error) {
          const initState = await page.evaluate(() => ({
            title: document.title,
            scripts: Array.from(document.scripts).map((s) => ({ src: s.src, loaded: true })),
            hasQlApi: typeof window.qlApi,
            hasQlSetModule: typeof window.qlSetModule,
            hasOpenCards: typeof window.qlOpenOtrReportCards,
            bodyText: document.body.innerText.slice(0, 1000),
          })).catch((evalError) => ({ evalError: evalError.message }));
          diag.initState = initState;
          await page.screenshot({ path: path.join(artifactDir, `${role}-${label}-init-failure.png`), fullPage: true }).catch(() => {});
          screenshots.push(`${role}-${label}-init-failure.png`);
          throw error;
        }
        const session = await page.evaluate(async () => {
          const response = await fetch('/api.php?action=current_user', { credentials: 'same-origin' });
          return response.json();
        });
        assertOk(session.ok === true && session.user?.id > 0, `${role} ${label} local frontend proxy authenticated`, session);

        await page.evaluate(async ({ groupId }) => {
          if (typeof window.qlSetModule === 'function') window.qlSetModule('captain');
          await new Promise((resolve) => setTimeout(resolve, 900));
          const select = document.getElementById('captainGroupSelect');
          if (select) {
            select.value = String(groupId);
            select.dispatchEvent(new Event('change', { bubbles: true }));
          }
          await new Promise((resolve) => setTimeout(resolve, 900));
          if (typeof window.qlOtrSimpleChooseStream === 'function') window.qlOtrSimpleChooseStream('cash', { chosen: true });
          if (typeof window.qlOpenOtrReportCards === 'function') await window.qlOpenOtrReportCards({ forceCards: true });
          await new Promise((resolve) => setTimeout(resolve, 900));
        }, { groupId: ids.group_id });

        const cardsState = await page.evaluate((tapeId) => {
          const candidates = Array.from(document.querySelectorAll(`[data-otr-card-open="${tapeId}"], [data-captain-open-otr-card="${tapeId}"]`));
          const visibleRow = candidates.find((el) => {
            const rect = el.getBoundingClientRect();
            const style = window.getComputedStyle(el);
            return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
          }) || null;
          const gate = document.getElementById('otrStreamGate');
          const list = document.getElementById('otrReportCardsList');
          return {
            hasTargetCard: !!visibleRow,
            targetText: visibleRow ? visibleRow.textContent.trim() : '',
            listText: list ? list.textContent.trim().slice(0, 500) : '',
            streamGateOpen: !!gate && !gate.classList.contains('hidden') && gate.getAttribute('aria-hidden') !== 'true',
            bodyWidth: document.body.scrollWidth,
            viewportWidth: window.innerWidth,
          };
        }, ids.tape_id);

        await page.screenshot({ path: path.join(artifactDir, `${role}-${label}-records-list.png`), fullPage: true });
        screenshots.push(`${role}-${label}-records-list.png`);

        if (role === 'base') {
          assertOk(!cardsState.hasTargetCard, `base ${label} records list does not expose employee card`, cardsState);
          await context.close();
          continue;
        }

        assertOk(cardsState.hasTargetCard, `admin ${label} ordinary records list shows employee card without direct-open fallback`, cardsState);
        assertOk(!cardsState.streamGateOpen, `admin ${label} records list is not covered by stream gate`, cardsState);
        if (cardsState.bodyWidth > cardsState.viewportWidth + 4) {
          recordDefect('Frontend/UX', `admin ${label} horizontal overflow on records list`, cardsState);
        }

        await page.locator(`[data-captain-open-otr-card="${ids.tape_id}"], [data-otr-card-open="${ids.tape_id}"]`).filter({ visible: true }).first().click();
        await page.waitForSelector(`[data-otr-card-record-files="${ids.capture_id}"]`, { timeout: 15000 });
        await page.waitForFunction((captureId) => {
          const box = document.querySelector(`[data-otr-card-record-files="${captureId}"]`);
          return box && box.querySelectorAll('[data-otr-proof-view]').length >= 2;
        }, ids.capture_id, { timeout: 15000 });

        const detail = await page.evaluate((captureId) => {
          const box = document.querySelector(`[data-otr-card-record-files="${captureId}"]`);
          const buttons = Array.from(box?.querySelectorAll('[data-otr-proof-view]') || []);
          const modal = document.getElementById('otrCardModal');
          const clipped = [];
          document.querySelectorAll('#otrCardModal h3, #otrCardModal button, #otrCardModal .otr-card-record-file').forEach((el) => {
            if (el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2) {
              clipped.push({ text: el.textContent.trim().slice(0, 120), sw: el.scrollWidth, cw: el.clientWidth, sh: el.scrollHeight, ch: el.clientHeight });
            }
          });
          return {
            proofButtons: buttons.length,
            labels: buttons.map((button) => button.textContent.trim()),
            modalOpen: !!modal && !modal.classList.contains('hidden'),
            clipped,
            bodyWidth: document.body.scrollWidth,
            viewportWidth: window.innerWidth,
          };
        }, ids.capture_id);
        assertOk(detail.modalOpen && detail.proofButtons === 2, `admin ${label} opened card from list and sees two proof buttons`, detail);
        if (detail.clipped.length) {
          recordDefect('Frontend/UX', `admin ${label} clipping/overflow remains in card detail`, detail);
        }
        await page.screenshot({ path: path.join(artifactDir, `admin-${label}-card-detail.png`), fullPage: true });
        screenshots.push(`admin-${label}-card-detail.png`);

        for (const kind of ['image', 'pdf']) {
          const selector = kind === 'pdf'
            ? '[data-otr-proof-view]:has-text("PDF")'
            : '[data-otr-proof-view]:has-text("Оригинал"), [data-otr-proof-view]:has-text("PNG")';
          await page.locator(selector).first().click();
          await page.waitForSelector('#proofViewerModal:not(.hidden)', { timeout: 10000 });
          const viewer = await page.evaluate(() => {
            const modal = document.getElementById('proofViewerModal');
            const img = modal?.querySelector('img.proof-viewer-image');
            const frame = modal?.querySelector('iframe.proof-viewer-frame');
            const link = document.getElementById('proofViewerOpenLink');
            return {
              img: !!img,
              frame: !!frame,
              target: link?.getAttribute('target') || '',
              rel: link?.getAttribute('rel') || '',
              hasDownload: link?.hasAttribute('download') || false,
            };
          });
          if (kind === 'image') assertOk(viewer.img, `admin ${label} image opens inline proof viewer`, viewer);
          if (kind === 'pdf') assertOk(viewer.frame, `admin ${label} PDF opens inline proof viewer`, viewer);
          assertOk(viewer.target === '_blank' && /noopener/.test(viewer.rel) && viewer.hasDownload === false, `admin ${label} ${kind} open link is new-tab without download`, viewer);
          await page.screenshot({ path: path.join(artifactDir, `admin-${label}-${kind}-viewer.png`), fullPage: true });
          screenshots.push(`admin-${label}-${kind}-viewer.png`);
          await page.click('[data-close-proof-viewer]');
          await page.waitForFunction(() => document.getElementById('proofViewerModal')?.classList.contains('hidden'), null, { timeout: 10000 });
          assertOk(true, `admin ${label} ${kind} proof viewer closes`);
        }

        await context.close();
      }
    } finally {
      await new Promise((resolve) => proxy.server.close(resolve));
    }
  }

  await browser.close();
}

async function main() {
  await setupFixture();
  await apiIsolationChecks();
  await browserChecks();

  const result = {
    ok: blockers.length === 0,
    run_id: runId,
    ids,
    checks,
    blockers,
    defects,
    screenshots,
    api_calls: apiCalls,
    browser_diagnostics: browserDiagnostics,
    generated_at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(artifactDir, 'live_records_recheck_result.json'), JSON.stringify(redact(result), null, 2));
  console.log(JSON.stringify({ ok: result.ok, run_id: runId, ids: redact(ids), checks: checks.length, blockers: blockers.length, defects: defects.length }, null, 2));
  if (blockers.length) process.exit(2);
}

main().catch((error) => {
  const failure = {
    ok: false,
    run_id: runId,
    ids: redact(ids),
    checks,
    blockers,
    defects,
    browser_diagnostics: browserDiagnostics,
    error: error.message,
    context: redact(error.context || {}),
    generated_at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(artifactDir, 'live_records_recheck_failure.json'), JSON.stringify(failure, null, 2));
  console.error(JSON.stringify(failure, null, 2));
  process.exit(1);
});
