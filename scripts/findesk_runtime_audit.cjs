const fs = require('fs');
const path = require('path');
const { chromium, request } = require('playwright');

const baseUrl = (process.argv[2] || 'http://127.0.0.1:18889').replace(/\/$/, '');
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const root = path.resolve(__dirname, '..');
const logPath = path.join(root, 'storage', 'logs', 'auth_codes.log');
const outDir = path.join(root, 'test-results', `findesk-runtime-audit-${stamp}`);

fs.mkdirSync(outDir, { recursive: true });

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function assertOk(condition, message, context) {
  if (!condition) {
    const error = new Error(message);
    if (context) error.context = context;
    throw error;
  }
}

function latestCode(email) {
  const lines = fs.readFileSync(logPath, 'utf8').trim().split('\n').reverse();
  for (const line of lines) {
    const parts = line.split('|').map(part => part.trim());
    if (parts[1] === email && /^\d{6}$/.test(parts[2] || '')) {
      return parts[2];
    }
  }
  throw new Error(`Auth code not found for ${email}`);
}

async function apiJson(api, action, payload) {
  const res = await api.post(`${baseUrl}/api.php?action=${encodeURIComponent(action)}`, {
    headers: { 'Content-Type': 'application/json' },
    data: payload || {}
  });
  const json = await res.json();
  if (!json || typeof json !== 'object') {
    throw new Error(`Bad JSON for ${action}`);
  }
  json._httpStatus = res.status();
  return json;
}

async function loginApi(email) {
  const api = await request.newContext({ baseURL: baseUrl });
  const requested = await apiJson(api, 'request_code', { email });
  assertOk(requested.ok, `request_code failed for ${email}`, requested);
  await sleep(150);
  const code = latestCode(email);
  const verified = await apiJson(api, 'verify_code', { email, code });
  assertOk(verified.ok && verified.user, `verify_code failed for ${email}`, verified);
  return api;
}

async function openFinDeskPage(browser, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  await page.goto(`${baseUrl}/app.php?fresh=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#loginPanel:not(.hidden)', { timeout: 20000 });
  return { context, page };
}

async function loginPage(page, email) {
  await page.fill('#loginEmail', email);
  await page.click('#sendCodeBtn');
  await page.waitForSelector('#codeBlock:not(.hidden)', { timeout: 10000 });
  await sleep(150);
  const code = latestCode(email);
  await page.fill('#loginCode', code);
  await page.click('#verifyCodeBtn');
  await page.waitForSelector('#userPanel:not(.hidden)', { timeout: 20000 });
  await page.evaluate(() => {
    if (typeof window.qlSetModule === 'function') {
      window.qlSetModule('captain');
      return true;
    }
    return false;
  });
  await page.waitForSelector('#moduleCaptain:not(.hidden)', { timeout: 20000 });
}

async function browserBackClosesCard(page) {
  await page.goBack().catch(() => null);
  await page.waitForTimeout(500);
  return page.evaluate(() => ({
    stillOnApp: window.location.pathname.endsWith('/app.php'),
    boardVisible: !!document.querySelector('#captainBoardHome:not(.hidden)'),
    cardVisible: !!document.querySelector('#captainCardView:not(.hidden)')
  }));
}

async function captureScenario(page, groupId, prefix) {
  await page.selectOption('#captainGroupSelect', String(groupId));
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(outDir, `${prefix}-board.png`), fullPage: true });

  const adminCard = page.locator('[data-captain-open-card="admin"]').first();
  await adminCard.click();
  await page.waitForSelector('#captainAdminWork:not(.hidden)', { timeout: 10000 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, `${prefix}-admin.png`), fullPage: true });

  await page.click('#captainCardBackBtn');
  await page.waitForSelector('#captainBoardHome:not(.hidden)', { timeout: 10000 });
  await page.waitForTimeout(400);

  const participantCard = page.locator('[data-captain-open-card="participant"]').first();
  await participantCard.click();
  await page.waitForSelector('#captainParticipantWork .findesk-workspace-stack', { timeout: 10000 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, `${prefix}-participant.png`), fullPage: true });
  const backCheck = await browserBackClosesCard(page);

  const audit = await page.evaluate((result) => {
    const root = document.documentElement;
    const participantCards = document.querySelectorAll('[data-captain-open-card="participant"]').length;
    const boardOverflow = root.scrollWidth > window.innerWidth;
    const visibleButtons = Array.from(document.querySelectorAll('button'))
      .filter(btn => !!(btn.offsetWidth || btn.offsetHeight || btn.getClientRects().length))
      .map(btn => btn.textContent.trim())
      .filter(Boolean)
      .slice(0, 30);
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      boardOverflow,
      participantCards,
      visibleButtons,
      browserBackClosedCard: result.boardVisible && !result.cardVisible && result.stillOnApp
    };
  }, backCheck);

  fs.writeFileSync(
    path.join(outDir, `${prefix}-audit.json`),
    JSON.stringify(audit, null, 2)
  );
  return audit;
}

async function captureMemberSelfView(page, groupId, prefix) {
  await page.selectOption('#captainGroupSelect', String(groupId));
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(outDir, `${prefix}-board.png`), fullPage: true });

  const ownCard = page.locator('[data-captain-open-card="participant"]').first();
  await ownCard.click();
  await page.waitForSelector('#captainParticipantWork .findesk-workspace-stack', { timeout: 10000 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, `${prefix}-participant.png`), fullPage: true });

  const audit = await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('#captainParticipantWork button'))
      .filter(btn => !!(btn.offsetWidth || btn.offsetHeight || btn.getClientRects().length))
      .map(btn => btn.textContent.trim())
      .filter(Boolean);
    const title = document.querySelector('#captainCardViewTitle')?.textContent?.trim() || '';
    const workspaceTitle = document.querySelector('#captainParticipantWork .captain-session-panel-primary h4')?.textContent?.trim() || '';
    const moderatorButtons = buttons.filter(label => ['Утвердить', 'Вернуть', 'Включить', 'Отменить'].includes(label));
    return {
      title,
      workspaceTitle,
      buttons,
      moderatorButtons,
      boardOverflow: document.documentElement.scrollWidth > window.innerWidth
    };
  });

  const backCheck = await browserBackClosesCard(page);
  audit.browserBackClosedCard = backCheck.boardVisible && !backCheck.cardVisible && backCheck.stillOnApp;

  fs.writeFileSync(
    path.join(outDir, `${prefix}-audit.json`),
    JSON.stringify(audit, null, 2)
  );
  return audit;
}

async function main() {
  const emails = {
    admin: `findesk-admin-${Date.now()}@example.test`,
    member: `findesk-member-${Date.now()}@example.test`
  };
  const groupName = `FinDesk Runtime ${Date.now()}`;
  const summary = { baseUrl, groupName, emails, artifacts: {}, checks: {} };

  const adminApi = await loginApi(emails.admin);
  const memberApi = await loginApi(emails.member);

  const groupRes = await apiJson(adminApi, 'group_create', { name: groupName });
  assertOk(groupRes.ok && groupRes.group && groupRes.group.id, 'group_create failed', groupRes);
  const groupId = Number(groupRes.group.id);
  summary.groupId = groupId;

  const inviteRes = await apiJson(adminApi, 'group_invite_create', {
    group_id: groupId,
    channel: 'copy',
    invited_email: emails.member,
    access_level: 'base'
  });
  assertOk(inviteRes.ok && inviteRes.invite && inviteRes.invite.url, 'group_invite_create failed', inviteRes);
  const inviteUrl = new URL(inviteRes.invite.url);
  const inviteToken = inviteUrl.searchParams.get('invite');
  assertOk(inviteToken, 'invite token missing', inviteRes);

  const joinRes = await apiJson(memberApi, 'group_join', { token: inviteToken });
  assertOk(joinRes.ok, 'group_join failed', joinRes);

  const membersRes = await apiJson(adminApi, 'group_members', { group_id: groupId });
  assertOk(membersRes.ok && Array.isArray(membersRes.members), 'group_members failed', membersRes);
  const memberRow = membersRes.members.find(row => row.email === emails.member);
  assertOk(memberRow && memberRow.user_id, 'member not found in group members', membersRes);
  const memberId = Number(memberRow.user_id);

  const advanceRes = await apiJson(adminApi, 'advance_create', {
    group_id: groupId,
    assigned_to_user_id: memberId,
    amount: '150.00',
    currency: 'EUR',
    title: 'Runtime audit advance'
  });
  assertOk(advanceRes.ok && advanceRes.advance && advanceRes.advance.id, 'advance_create failed', advanceRes);
  const advanceId = Number(advanceRes.advance.id);
  const advanceTapeId = Number(advanceRes.advance.on_the_go_tape_id || 0);
  assertOk(advanceTapeId > 0, 'advance tape id missing', advanceRes);

  const adminSaveRes = await apiJson(adminApi, 'on_the_go_signed_sync', {
    group_id: groupId,
    stream_type: 'cash',
    notes: '+200 received from office\n-15 fuel\n-7 water',
    cash_received: 0,
    replace_tape: 1,
    start_next: 0
  });
  assertOk(adminSaveRes.ok, 'admin signed sync failed', adminSaveRes);

  const memberSaveRes = await apiJson(memberApi, 'on_the_go_signed_sync', {
    group_id: groupId,
    tape_id: advanceTapeId,
    stream_type: 'cash',
    notes: '-20 taxi\n-15 lunch',
    replace_tape: 1,
    start_next: 0
  });
  assertOk(memberSaveRes.ok, 'member signed sync failed', memberSaveRes);

  const memberSubmitRes = await apiJson(memberApi, 'advance_submit', {
    id: advanceId,
    actual_remaining: '115.00',
    note: 'Runtime audit submit'
  });
  assertOk(memberSubmitRes.ok, 'advance_submit failed', memberSubmitRes);

  const browser = await chromium.launch({ headless: true });
  try {
    const desktop = await openFinDeskPage(browser, { width: 1440, height: 1200 });
    await loginPage(desktop.page, emails.admin);
    const desktopAudit = await captureScenario(desktop.page, groupId, 'desktop-1440x1200');
    await desktop.context.close();

    const mobile = await openFinDeskPage(browser, { width: 390, height: 844 });
    await loginPage(mobile.page, emails.admin);
    const mobileAudit = await captureScenario(mobile.page, groupId, 'mobile-390x844');
    await mobile.context.close();

    const memberMobile = await openFinDeskPage(browser, { width: 390, height: 844 });
    await loginPage(memberMobile.page, emails.member);
    const memberMobileAudit = await captureMemberSelfView(memberMobile.page, groupId, 'member-mobile-390x844');
    await memberMobile.context.close();

    assertOk(desktopAudit.browserBackClosedCard, 'desktop browser back did not close card', desktopAudit);
    assertOk(mobileAudit.browserBackClosedCard, 'mobile browser back did not close card', mobileAudit);
    assertOk(memberMobileAudit.browserBackClosedCard, 'member mobile browser back did not close card', memberMobileAudit);
    assertOk(!memberMobileAudit.moderatorButtons.length, 'member mobile sees moderator buttons', memberMobileAudit);

    summary.checks.desktop = desktopAudit;
    summary.checks.mobile = mobileAudit;
    summary.checks.memberMobile = memberMobileAudit;
    summary.artifacts = {
      boardDesktop: path.join(outDir, 'desktop-1440x1200-board.png'),
      adminDesktop: path.join(outDir, 'desktop-1440x1200-admin.png'),
      participantDesktop: path.join(outDir, 'desktop-1440x1200-participant.png'),
      boardMobile: path.join(outDir, 'mobile-390x844-board.png'),
      adminMobile: path.join(outDir, 'mobile-390x844-admin.png'),
      participantMobile: path.join(outDir, 'mobile-390x844-participant.png'),
      memberBoardMobile: path.join(outDir, 'member-mobile-390x844-board.png'),
      memberParticipantMobile: path.join(outDir, 'member-mobile-390x844-participant.png')
    };
  } finally {
    await browser.close();
    await adminApi.dispose();
    await memberApi.dispose();
  }

  fs.writeFileSync(path.join(outDir, 'summary.json'), JSON.stringify(summary, null, 2));
  console.log(JSON.stringify(summary, null, 2));
}

main().catch(error => {
  const payload = {
    ok: false,
    error: error && error.message ? error.message : 'runtime_audit_failed',
    context: error && error.context ? error.context : null
  };
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(path.join(outDir, 'failure.json'), JSON.stringify(payload, null, 2));
  console.error(JSON.stringify(payload, null, 2));
  process.exit(1);
});
