const fs = require('fs');
const path = require('path');
const { test, expect, request } = require('@playwright/test');

const baseUrl = process.env.FINDESK_LOCAL_BASE_URL || 'http://127.0.0.1:18889';
const logPath = path.resolve(__dirname, '..', 'storage', 'logs', 'auth_codes.log');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
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
  const response = await api.post(`${baseUrl}/api.php?action=${encodeURIComponent(action)}`, {
    headers: { 'Content-Type': 'application/json' },
    data: payload || {}
  });
  const json = await response.json();
  return { response, json };
}

async function loginApi(email) {
  const api = await request.newContext({ baseURL: baseUrl });
  let result = await apiJson(api, 'request_code', { email });
  expect(result.json.ok).toBeTruthy();
  await sleep(150);
  const code = latestCode(email);
  result = await apiJson(api, 'verify_code', { email, code });
  expect(result.json.ok).toBeTruthy();
  expect(result.json.user).toBeTruthy();
  return api;
}

async function openFinDesk(page, groupId) {
  await page.goto(`${baseUrl}/app.php?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#userPanel:not(.hidden)', { timeout: 20000 });
  await page.click('[data-module-tab="captain"]');
  await page.waitForSelector('#moduleCaptain:not(.hidden)', { timeout: 20000 });
  await page.selectOption('#captainGroupSelect', String(groupId));
  await page.waitForTimeout(900);
}

test('FinDesk runtime audit', async ({ browser }, testInfo) => {
  test.slow();

  const stamp = Date.now();
  const emails = {
    admin: `findesk-admin-${stamp}@example.test`,
    member: `findesk-member-${stamp}@example.test`
  };
  const groupName = `FinDesk Runtime ${stamp}`;
  const summary = {
    baseUrl,
    groupName,
    emails,
    checks: {}
  };

  const adminApi = await loginApi(emails.admin);
  const memberApi = await loginApi(emails.member);

  try {
    let result = await apiJson(adminApi, 'group_create', { name: groupName });
    expect(result.json.ok).toBeTruthy();
    const groupId = Number(result.json.group.id);
    summary.groupId = groupId;

    result = await apiJson(adminApi, 'group_invite_create', {
      group_id: groupId,
      channel: 'copy',
      invited_email: emails.member,
      access_level: 'base'
    });
    expect(result.json.ok).toBeTruthy();
    const inviteToken = new URL(result.json.invite.url).searchParams.get('invite');
    expect(inviteToken).toBeTruthy();

    result = await apiJson(memberApi, 'group_join', { token: inviteToken });
    expect(result.json.ok).toBeTruthy();

    result = await apiJson(adminApi, 'group_members', { group_id: groupId });
    expect(result.json.ok).toBeTruthy();
    const memberRow = (result.json.members || []).find(row => row.email === emails.member);
    expect(memberRow).toBeTruthy();
    const memberId = Number(memberRow.user_id);

    result = await apiJson(adminApi, 'advance_create', {
      group_id: groupId,
      assigned_to_user_id: memberId,
      amount: '150.00',
      currency: 'EUR',
      title: 'Runtime audit advance'
    });
    expect(result.json.ok).toBeTruthy();
    const advanceId = Number(result.json.advance.id);
    const advanceTapeId = Number(result.json.advance.on_the_go_tape_id || 0);
    expect(advanceTapeId).toBeGreaterThan(0);

    result = await apiJson(adminApi, 'on_the_go_signed_sync', {
      group_id: groupId,
      stream_type: 'cash',
      notes: '+200 received from office\n-15 fuel\n-7 water',
      cash_received: 0,
      replace_tape: 1,
      start_next: 0
    });
    expect(result.json.ok).toBeTruthy();

    result = await apiJson(memberApi, 'on_the_go_signed_sync', {
      group_id: groupId,
      tape_id: advanceTapeId,
      stream_type: 'cash',
      notes: '-20 taxi\n-15 lunch',
      replace_tape: 1,
      start_next: 0
    });
    expect(result.json.ok).toBeTruthy();

    result = await apiJson(memberApi, 'advance_submit', {
      id: advanceId,
      actual_remaining: '115.00',
      note: 'Runtime audit submit'
    });
    expect(result.json.ok).toBeTruthy();

    const storageState = testInfo.outputPath('admin-storage-state.json');
    await adminApi.storageState({ path: storageState });

    const desktopContext = await browser.newContext({
      storageState,
      viewport: { width: 1440, height: 1200 }
    });
    const desktopPage = await desktopContext.newPage();
    await openFinDesk(desktopPage, groupId);
    await expect(desktopPage.locator('[data-captain-open-card="participant"]').first()).toBeVisible();

    const desktopBoard = testInfo.outputPath('desktop-board.png');
    await desktopPage.screenshot({ path: desktopBoard, fullPage: true });

    await desktopPage.click('[data-captain-open-card="admin"]');
    await expect(desktopPage.locator('#captainAdminWork:not(.hidden)')).toBeVisible();
    await desktopPage.waitForTimeout(500);
    const desktopAdmin = testInfo.outputPath('desktop-admin.png');
    await desktopPage.screenshot({ path: desktopAdmin, fullPage: true });
    await desktopPage.click('#captainCardBackBtn');
    await desktopPage.waitForTimeout(400);

    await desktopPage.click('[data-captain-open-card="participant"]');
    await expect(desktopPage.locator('#captainParticipantWork .findesk-workspace-stack')).toBeVisible();
    await desktopPage.waitForTimeout(500);
    const desktopParticipant = testInfo.outputPath('desktop-participant.png');
    await desktopPage.screenshot({ path: desktopParticipant, fullPage: true });

    summary.checks.desktop = await desktopPage.evaluate(() => ({
      width: window.innerWidth,
      overflowX: document.documentElement.scrollWidth > window.innerWidth,
      boardButtons: document.querySelectorAll('[data-captain-open-card="participant"]').length
    }));
    await desktopContext.close();

    const mobileContext = await browser.newContext({
      storageState,
      viewport: { width: 390, height: 844 },
      isMobile: true,
      hasTouch: true
    });
    const mobilePage = await mobileContext.newPage();
    await openFinDesk(mobilePage, groupId);

    const mobileBoard = testInfo.outputPath('mobile-board.png');
    await mobilePage.screenshot({ path: mobileBoard, fullPage: true });

    await mobilePage.click('[data-captain-open-card="admin"]');
    await expect(mobilePage.locator('#captainAdminWork:not(.hidden)')).toBeVisible();
    await mobilePage.waitForTimeout(500);
    const mobileAdmin = testInfo.outputPath('mobile-admin.png');
    await mobilePage.screenshot({ path: mobileAdmin, fullPage: true });
    await mobilePage.click('#captainCardBackBtn');
    await mobilePage.waitForTimeout(400);

    await mobilePage.click('[data-captain-open-card="participant"]');
    await expect(mobilePage.locator('#captainParticipantWork .findesk-workspace-stack')).toBeVisible();
    await mobilePage.waitForTimeout(500);
    const mobileParticipant = testInfo.outputPath('mobile-participant.png');
    await mobilePage.screenshot({ path: mobileParticipant, fullPage: true });

    summary.checks.mobile = await mobilePage.evaluate(() => ({
      width: window.innerWidth,
      overflowX: document.documentElement.scrollWidth > window.innerWidth,
      boardButtons: document.querySelectorAll('[data-captain-open-card="participant"]').length
    }));
    await mobileContext.close();

    summary.artifacts = {
      desktopBoard,
      desktopAdmin,
      desktopParticipant,
      mobileBoard,
      mobileAdmin,
      mobileParticipant
    };
    fs.writeFileSync(testInfo.outputPath('summary.json'), JSON.stringify(summary, null, 2));
  } finally {
    await adminApi.dispose();
    await memberApi.dispose();
  }
});
