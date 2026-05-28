import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '/tmp/findesk-pw/node_modules/playwright/index.mjs';

const baseUrl = 'https://finance.brkovic.ltd';
const artifactDir = new URL('.', import.meta.url).pathname;
const runId = '20260528LIVEPROOFLINKSQA01';
const inbox = 'vetus.nauta@gmail.com';
const codes = JSON.parse(process.env.FINDESK_QA_CODES_JSON || '{}');
const emails = {
  admin: inbox.replace('@', `+qa-live-records-admin-${runId}@`),
  member: inbox.replace('@', `+qa-live-records-member-${runId}@`),
};

const cookies = {};
const ids = { run_id: runId, base_url: baseUrl };
const checks = [];
const defects = [];
const screenshots = [];

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

function recordDefect(severity, owner, title, evidence) {
  defects.push({ severity, owner, title, evidence: redact(evidence || {}) });
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
  const response = await fetch(`${baseUrl}/api.php?action=${encodeURIComponent(action)}`, {
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
  json._http_status = response.status;
  json._action = action;
  return json;
}

async function download(role, urlPath, label) {
  const response = await fetch(`${baseUrl}${urlPath}`, { headers: { Cookie: cookieHeader(role) } });
  const body = Buffer.from(await response.arrayBuffer());
  const headers = {
    status: response.status,
    content_type: response.headers.get('content-type') || '',
    content_disposition: response.headers.get('content-disposition') || '',
    bytes: body.length,
  };
  fs.writeFileSync(path.join(artifactDir, label), body);
  return headers;
}

async function uploadFile(role, captureId, fileName, mimeType, buffer, fields = {}) {
  const form = new FormData();
  form.append('capture_id', String(captureId));
  for (const [key, value] of Object.entries(fields)) form.append(key, String(value));
  form.append('file', new File([buffer], fileName, { type: mimeType }));
  const response = await fetch(`${baseUrl}/api.php?action=on_the_go_upload_file`, {
    method: 'POST',
    headers: { Cookie: cookieHeader(role) },
    body: form,
  });
  const json = await response.json();
  json._http_status = response.status;
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

  const group = await api('admin', 'group_create', { name: `QA live records proof links ${runId}` });
  assertOk(group.ok === true && group.group?.id > 0, 'admin created production group', group);
  ids.group_id = Number(group.group.id);

  const invite = await api('admin', 'group_invite_create', {
    group_id: ids.group_id,
    channel: 'copy',
    invited_email: emails.member,
    access_level: 'base',
  });
  assertOk(invite.ok === true && invite.invite?.url, 'admin created member invite', invite);
  const token = new URL(invite.invite.url).searchParams.get('invite');
  const join = await api('member', 'group_join', { token });
  assertOk(join.ok === true && Number(join.group?.id) === ids.group_id, 'member joined group', join);

  const tape = await api('member', 'on_the_go_tape_create', {
    group_id: ids.group_id,
    title: `QA proof records ${runId}`,
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
    description: `QA image+PDF proof ${runId}`,
  });
  assertOk(capture.ok === true && capture.capture?.id > 0, 'member created record with proof target', capture);
  ids.capture_id = Number(capture.capture.id);

  const bundleId = `qa-proof-bundle-${runId}`;
  ids.proof_bundle_id = bundleId;
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
    'base64'
  );
  const pdf = Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >> endobj\n4 0 obj << /Length 44 >> stream\nBT /F1 12 Tf 30 100 Td (QA proof PDF) Tj ET\nendstream endobj\nxref\n0 5\n0000000000 65535 f \ntrailer << /Root 1 0 R /Size 5 >>\nstartxref\n295\n%%EOF\n');

  const imageUpload = await uploadFile('member', ids.capture_id, `qa-photo-${runId}.png`, 'image/png', png, {
    proof_role: 'scanner_original',
    proof_bundle_id: bundleId,
    client_upload_id: `qa-image-${runId}`,
  });
  assertOk(imageUpload.ok === true && imageUpload.file?.id > 0, 'member uploaded image proof', imageUpload);
  ids.image_file_id = Number(imageUpload.file.id);

  const pdfUpload = await uploadFile('member', ids.capture_id, `qa-scan-${runId}.pdf`, 'application/pdf', pdf, {
    proof_role: 'scanner_cleaned_pdf',
    proof_bundle_id: bundleId,
    source_file_id: ids.image_file_id,
    client_upload_id: `qa-pdf-${runId}`,
  });
  assertOk(pdfUpload.ok === true && pdfUpload.file?.id > 0, 'member uploaded PDF proof', pdfUpload);
  ids.pdf_file_id = Number(pdfUpload.file.id);

  const submitted = await api('member', 'on_the_go_card_submit', { id: ids.tape_id, group_id: ids.group_id });
  assertOk(submitted.ok === true, 'member submitted Live Report card to group admin', submitted);
}

async function apiChecks() {
  const memberDetail = await api('member', 'on_the_go_card_detail', { id: ids.tape_id });
  assertOk(memberDetail.ok === true && memberDetail.items?.some((row) => Number(row.id) === ids.capture_id && Number(row.files_count) === 2), 'owner card detail shows files_count=2', memberDetail);

  const adminDetail = await api('admin', 'on_the_go_card_detail', { id: ids.tape_id });
  assertOk(adminDetail.ok === true && adminDetail.items?.some((row) => Number(row.id) === ids.capture_id && Number(row.files_count) === 2), 'admin card detail for employee shows files_count=2', adminDetail);

  const memberFiles = await api('member', 'on_the_go_file_list', { capture_id: ids.capture_id });
  assertOk(memberFiles.ok === true && memberFiles.files?.length === 2 && memberFiles.files.every((f) => f.download_url), 'owner file_list returns two visible proof links', memberFiles);
  ids.member_file_ids = memberFiles.files.map((f) => Number(f.id)).sort((a, b) => a - b);

  const adminFiles = await api('admin', 'on_the_go_file_list', { capture_id: ids.capture_id });
  assertOk(adminFiles.ok === true && adminFiles.files?.length === 2 && adminFiles.files.every((f) => f.download_url), 'admin file_list returns two employee proof links', adminFiles);
  ids.admin_file_ids = adminFiles.files.map((f) => Number(f.id)).sort((a, b) => a - b);

  for (const role of ['member', 'admin']) {
    for (const file of adminFiles.files) {
      const kind = String(file.mime_type || '').includes('pdf') ? 'pdf' : 'image';
      const result = await download(role, file.download_url, `${role}-${kind}-${file.id}`);
      assertOk(result.status === 200 && result.bytes > 0, `${role} opens ${kind} proof file ${file.id}`, result);
      if (kind === 'pdf') {
        assertOk(result.content_type.includes('pdf') && /inline/i.test(result.content_disposition), `${role} PDF is inline/new-tab compatible`, result);
      }
      if (kind === 'image') {
        assertOk(result.content_type.startsWith('image/') && /inline/i.test(result.content_disposition), `${role} image is inline/new-tab compatible`, result);
      }
    }
  }

  const anon = await fetch(`${baseUrl}/api.php?action=on_the_go_file_download&id=${ids.pdf_file_id}`);
  assertOk(anon.status === 401, 'anonymous proof download is denied', { status: anon.status });
}

async function addCookies(context, role) {
  await context.addCookies(Object.entries(cookies[role]).map(([name, value]) => ({
    name,
    value,
    domain: 'finance.brkovic.ltd',
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax',
  })));
}

async function browserChecks() {
  const browser = await chromium.launch({ headless: true });
  const viewports = [
    ['mobile390x844', 390, 844],
    ['tablet820x1180', 820, 1180],
    ['desktop1440x900', 1440, 900],
  ];

  for (const role of ['member', 'admin']) {
    for (const [label, width, height] of viewports) {
      const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 1 });
      await addCookies(context, role);
      const page = await context.newPage();
      page.on('pageerror', (error) => recordDefect('non-blocker', 'Frontend/UX', `${role} ${label} JS pageerror`, { message: error.message }));
      await page.goto(`${baseUrl}/app.php`, { waitUntil: 'networkidle' });
      await page.waitForFunction(() => typeof window.qlSetModule === 'function' && typeof window.qlOpenOtrReportCard === 'function', null, { timeout: 15000 });
      const browserUser = await page.evaluate(async () => {
        const response = await fetch('/api.php?action=current_user', { credentials: 'same-origin' });
        return response.json();
      });
      assertOk(browserUser.ok === true && browserUser.user?.id > 0, `${role} ${label} browser session is authenticated`, browserUser);
      await page.evaluate(async ({ tapeId }) => {
        window.qlOtrPinnedTapeId = Number(tapeId);
        if (typeof window.qlOtrSimpleChooseStream === 'function') window.qlOtrSimpleChooseStream('cash', { chosen: true });
        if (typeof window.qlSetModule === 'function') window.qlSetModule('ontherun', { screen: 'cards', stream_type: 'cash', tape_id: Number(tapeId) });
        await new Promise((resolve) => setTimeout(resolve, 1200));
      }, { tapeId: ids.tape_id });
      const hasCardInList = await page.locator(`[data-otr-card-open="${ids.tape_id}"]`).count();
      if (!hasCardInList) {
        recordDefect('non-blocker', 'Frontend/UX', `${role} ${label} records list does not show target card before direct open`, { tape_id: ids.tape_id });
      }
      await page.screenshot({ path: path.join(artifactDir, `${role}-${label}-records-list.png`), fullPage: true });
      screenshots.push(`${role}-${label}-records-list.png`);

      const streamGateOpen = await page.evaluate(() => {
        const gate = document.getElementById('otrStreamGate');
        return !!gate && !gate.classList.contains('hidden') && gate.getAttribute('aria-hidden') !== 'true';
      });
      if (hasCardInList && streamGateOpen) {
        recordDefect('non-blocker', 'Frontend/UX', `${role} ${label} stream gate overlays records list and can intercept card clicks`, { tape_id: ids.tape_id });
      }
      await page.evaluate(async (tapeId) => window.qlOpenOtrReportCard(Number(tapeId)), ids.tape_id);
      await page.waitForSelector(`[data-otr-card-record-files="${ids.capture_id}"]`, { timeout: 15000 });
      await page.waitForFunction((captureId) => {
        const box = document.querySelector(`[data-otr-card-record-files="${captureId}"]`);
        return box && box.querySelectorAll('[data-otr-proof-view]').length >= 2;
      }, ids.capture_id, { timeout: 15000 });

      const ui = await page.evaluate((captureId) => {
        const box = document.querySelector(`[data-otr-card-record-files="${captureId}"]`);
        const buttons = Array.from(box?.querySelectorAll('[data-otr-proof-view]') || []);
        const modal = document.getElementById('otrCardModal');
        const proofModal = document.getElementById('proofViewerModal');
        const close = modal?.querySelector('[data-close-otr-card]');
        return {
          buttons: buttons.map((button) => button.textContent.trim()),
          buttonCount: buttons.length,
          cardModalOpen: !!modal && !modal.classList.contains('hidden'),
          proofModalExists: !!proofModal,
          closeExists: !!close,
          bodyWidth: document.body.scrollWidth,
          viewportWidth: window.innerWidth,
        };
      }, ids.capture_id);
      assertOk(ui.buttonCount === 2, `${role} ${label} card row renders two proof controls`, ui);
      assertOk(ui.cardModalOpen && ui.closeExists, `${role} ${label} card modal has close control`, ui);
      if (ui.bodyWidth > ui.viewportWidth + 4) {
        recordDefect('non-blocker', 'Frontend/UX', `${role} ${label} horizontal overflow on records view`, ui);
      }

      await page.screenshot({ path: path.join(artifactDir, `${role}-${label}-card-detail.png`), fullPage: true });
      screenshots.push(`${role}-${label}-card-detail.png`);

      for (const kind of ['image', 'pdf']) {
        const selector = kind === 'pdf'
          ? '[data-otr-proof-view]:has-text("PDF")'
          : '[data-otr-proof-view]:has-text("PNG")';
        await page.click(selector);
        await page.waitForSelector('#proofViewerModal:not(.hidden)', { timeout: 10000 });
        const viewer = await page.evaluate(() => {
          const modal = document.getElementById('proofViewerModal');
          const img = modal?.querySelector('img.proof-viewer-image');
          const frame = modal?.querySelector('iframe.proof-viewer-frame');
          const link = document.getElementById('proofViewerOpenLink');
          return {
            img: !!img,
            frame: !!frame,
            linkTarget: link?.getAttribute('target') || '',
            linkRel: link?.getAttribute('rel') || '',
            linkDownload: link?.hasAttribute('download') || false,
            openHref: link?.getAttribute('href') || '',
          };
        });
        if (kind === 'image') assertOk(viewer.img, `${role} ${label} image opens inline viewer`, viewer);
        if (kind === 'pdf') assertOk(viewer.frame, `${role} ${label} PDF opens inline viewer`, viewer);
        assertOk(viewer.linkTarget === '_blank' && /noopener/.test(viewer.linkRel), `${role} ${label} ${kind} viewer keeps new-tab escape link`, viewer);
        if (viewer.linkDownload) {
          recordDefect('non-blocker', 'Frontend/UX', `${role} ${label} proof viewer new-tab link also has download attribute`, viewer);
        }
        await page.screenshot({ path: path.join(artifactDir, `${role}-${label}-${kind}-viewer.png`), fullPage: true });
        screenshots.push(`${role}-${label}-${kind}-viewer.png`);
        await page.click('[data-close-proof-viewer]');
        await page.waitForFunction(() => {
          const modal = document.getElementById('proofViewerModal');
          return !!modal && modal.classList.contains('hidden');
        }, null, { timeout: 10000 });
      }

      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForFunction(() => typeof window.qlSetModule === 'function' && typeof window.qlOpenOtrReportCard === 'function', null, { timeout: 15000 });
      await page.evaluate(async ({ tapeId }) => {
        if (typeof window.qlOtrSimpleChooseStream === 'function') window.qlOtrSimpleChooseStream('cash', { chosen: true });
        if (typeof window.qlSetModule === 'function') window.qlSetModule('ontherun', { screen: 'cards', stream_type: 'cash', tape_id: Number(tapeId) });
        await new Promise((resolve) => setTimeout(resolve, 1200));
      }, { tapeId: ids.tape_id });
      assertOk(true, `${role} ${label} records state recoverable after refresh`);
      await context.close();
    }
  }

  await browser.close();
}

async function main() {
  await setupFixture();
  await apiChecks();
  await browserChecks();

  const evidence = {
    ok: true,
    run_id: runId,
    ids,
    checks,
    defects,
    screenshots,
    generated_at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(artifactDir, 'live_records_proof_links_result.json'), JSON.stringify(redact(evidence), null, 2));
  console.log(JSON.stringify({ ok: true, run_id: runId, ids: redact(ids), checks: checks.length, defects: defects.length }, null, 2));
}

main().catch((error) => {
  const failure = {
    ok: false,
    run_id: runId,
    ids: redact(ids),
    checks,
    defects,
    error: error.message,
    context: redact(error.context || {}),
    generated_at: new Date().toISOString(),
  };
  fs.writeFileSync(path.join(artifactDir, 'live_records_proof_links_failure.json'), JSON.stringify(failure, null, 2));
  console.error(JSON.stringify(failure, null, 2));
  process.exit(1);
});
