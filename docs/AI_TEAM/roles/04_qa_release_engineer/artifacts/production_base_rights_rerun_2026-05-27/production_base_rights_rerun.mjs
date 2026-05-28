import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {execFileSync} from 'node:child_process';

const baseUrl = 'https://finance.brkovic.ltd';
const artifactDir = new URL('.', import.meta.url).pathname;
const stamp = process.env.FINDESK_QA_STAMP || new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
const inbox = process.env.FINDESK_QA_INBOX || 'vetus.nauta@gmail.com';
const ftp = {
  host: process.env.FINDESK_FTP_HOST || '',
  user: process.env.FINDESK_FTP_USER || '',
  pass: process.env.FINDESK_FTP_PASS || '',
};

const emails = {
  admin: inbox.replace('@', `+qa-base-rerun-admin-${stamp}@`),
  base: inbox.replace('@', `+qa-base-rerun-base-${stamp}@`),
  other: inbox.replace('@', `+qa-base-rerun-other-${stamp}@`),
};

const cookies = {};
const ids = {stamp};
const checks = [];
const artifacts = {};

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
  checks.push({status: 'PASS', message});
}

function money(value) {
  return Math.round(Number(value || 0) * 100) / 100;
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
  const headers = {'Content-Type': 'application/json'};
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

async function download(role, urlPath, fileName) {
  const response = await fetch(`${baseUrl}${urlPath}`, {headers: {Cookie: cookieHeader(role)}});
  const body = Buffer.from(await response.arrayBuffer());
  const filePath = path.join(artifactDir, fileName);
  fs.writeFileSync(filePath, body);
  return {
    path: filePath,
    status: response.status,
    contentType: response.headers.get('content-type') || '',
    bytes: body.length,
    textStart: body.toString('utf8', 0, Math.min(body.length, 500)),
  };
}

function fetchAuthLog() {
  assertOk(ftp.host && ftp.user && ftp.pass, 'FTP credentials supplied through environment');
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'findesk-qa-netrc-'));
  const netrc = path.join(tempDir, 'netrc');
  fs.writeFileSync(netrc, `machine ${ftp.host}\nlogin ${ftp.user}\npassword ${ftp.pass}\n`, {mode: 0o600});
  try {
    return execFileSync('curl', [
      '-sS',
      '--netrc-file',
      netrc,
      `ftp://${ftp.host}/finance.brkovic.ltd/storage/logs/auth_codes.log`,
    ], {encoding: 'utf8', maxBuffer: 20 * 1024 * 1024});
  } finally {
    fs.rmSync(tempDir, {recursive: true, force: true});
  }
}

function latestCodeFor(email) {
  const lines = fetchAuthLog().split(/\r?\n/).reverse();
  for (const line of lines) {
    const parts = line.split('|').map((part) => part.trim());
    if (parts.length >= 3 && parts[1] === email && /^\d{6}$/.test(parts[2])) return parts[2];
  }
  return '';
}

async function login(role) {
  const requested = await api(role, 'request_code', {email: emails[role]});
  assertOk(requested.ok === true || requested.error === 'email_send_failed', `request_code ${role}`, {
    ok: requested.ok,
    error: requested.error || null,
    mail_method: requested.mail_method || null,
  });
  const code = latestCodeFor(emails[role]);
  assertOk(/^\d{6}$/.test(code), `auth code available for ${role}`);
  const verified = await api(role, 'verify_code', {email: emails[role], code});
  assertOk(verified.ok === true && verified.user?.id > 0, `verify_code ${role}`, {verified});
  ids[`${role}_user_id`] = Number(verified.user.id);
}

async function expectDenied(role, action, payload, expectedError = 'access_denied') {
  const result = await api(role, action, payload);
  assertOk(result.ok === false && result.error === expectedError, `${role} denied ${action} with ${expectedError}`, {result});
  return result;
}

async function inviteDefault(memberRole, groupId) {
  const invite = await api('admin', 'group_invite_create', {
    group_id: groupId,
    channel: 'copy',
    invited_email: emails[memberRole],
  });
  assertOk(invite.ok === true && invite.invite?.url && invite.invite?.access_level === 'base', `default invite ${memberRole} creates base`, {invite});
  const token = new URL(invite.invite.url).searchParams.get('invite');
  const join = await api(memberRole, 'group_join', {token});
  assertOk(join.ok === true && Number(join.group?.id) === groupId && join.group?.access_level === 'base', `join ${memberRole} as base`, {join});
  return {invite, join};
}

async function setupFinalReport(groupId) {
  const tape = await api('admin', 'on_the_go_tape_create', {
    group_id: groupId,
    title: `QA base rights admin final card ${stamp}`,
    cash_received: '0',
  });
  assertOk(tape.ok === true && tape.tape?.id > 0, 'admin creates included card for final report', {tape});
  ids.admin_tape_id = Number(tape.tape.id);

  const saved = await api('admin', 'on_the_go_signed_sync', {
    tape_id: ids.admin_tape_id,
    group_id: groupId,
    cash_received: '0',
    notes: `-1 QA base rights finalization row ${stamp}`,
    replace_tape: 1,
    start_next: 0,
  });
  assertOk(saved.ok === true && Number(saved.synced_count) === 1, 'admin saves included card row', {saved});

  const included = await api('admin', 'on_the_go_card_include', {id: ids.admin_tape_id, group_id: groupId});
  assertOk(included.ok === true && included.card?.id > 0, 'admin includes card for final report', {included});

  const finalized = await api('admin', 'ledger_group_finalize_report', {group_id: groupId});
  assertOk(finalized.ok === true && finalized.report_id > 0, 'admin finalizes report for base denial checks', {finalized});
  ids.report_id = Number(finalized.report_id);
}

async function main() {
  for (const role of ['admin', 'base', 'other']) await login(role);

  const group = await api('admin', 'group_create', {name: `QA production base rights rerun ${stamp}`});
  assertOk(group.ok === true && group.group?.id > 0, 'admin creates fresh group', {group});
  ids.group_id = Number(group.group.id);

  await inviteDefault('base', ids.group_id);
  await inviteDefault('other', ids.group_id);

  const income = await api('admin', 'ledger_create', {
    group_id: ids.group_id,
    entry_type: 'income',
    money_type: 'cash',
    amount: '1000',
    purpose: `QA base rights admin cash ${stamp}`,
  });
  assertOk(income.ok === true && income.entry?.id > 0, 'admin creates group cash income', {income});

  const message = await api('admin', 'message_send', {
    group_id: ids.group_id,
    message_text: `QA base rights group message ${stamp}`,
  });
  assertOk(message.ok === true && message.message?.id > 0, 'admin message send still works', {message});
  ids.message_id = Number(message.message.id);

  const otherAdvance = await api('admin', 'advance_create', {
    group_id: ids.group_id,
    assigned_to_user_id: ids.other_user_id,
    amount: '50',
    currency: 'EUR',
    title: `QA other advance ${stamp}`,
  });
  assertOk(otherAdvance.ok === true && otherAdvance.advance?.id > 0, 'admin money management still works for setup', {otherAdvance});
  ids.other_advance_id = Number(otherAdvance.advance.id);

  await setupFinalReport(ids.group_id);

  const adminMembers = await api('admin', 'group_members', {group_id: ids.group_id});
  assertOk(adminMembers.ok === true && (adminMembers.members || []).length === 3, 'admin still sees all group members', {adminMembers});

  const adminFinalList = await api('admin', 'ledger_group_final_report_list', {group_id: ids.group_id});
  assertOk(adminFinalList.ok === true && (adminFinalList.reports || []).some((row) => Number(row.id) === ids.report_id), 'admin still sees final report list', {adminFinalList});

  const personalIncome = await api('base', 'ledger_create', {
    entry_type: 'income',
    money_type: 'cash',
    amount: '11',
    purpose: `QA base personal income ${stamp}`,
  });
  assertOk(personalIncome.ok === true && personalIncome.entry?.id > 0, 'base employee can use personal FinDesk ledger', {personalIncome});

  const basePersonalReport = await api('base', 'ledger_report', {
    period: 'custom',
    from: '2026-05-27',
    to: '2026-05-27',
  });
  assertOk(basePersonalReport.ok === true && money(basePersonalReport.summary?.income) >= 11, 'base employee personal report remains available', {basePersonalReport});

  const baseGroups = await api('base', 'group_list', {});
  const baseGroup = (baseGroups.groups || []).find((row) => Number(row.id) === ids.group_id);
  assertOk(baseGroups.ok === true && baseGroup?.access_level === 'base', 'base employee group list shows base access', {baseGroups});
  assertOk(
    baseGroup.permissions?.can_view_group_reports === false
      && baseGroup.permissions?.can_write_group_ledger === false
      && baseGroup.permissions?.can_manage_money === false
      && baseGroup.permissions?.can_moderate === false
      && baseGroup.permissions?.can_manage_members === false,
    'base permissions deny reports, ledger write, money, moderation, and member management',
    {baseGroup}
  );

  const baseMembers = await api('base', 'group_members', {group_id: ids.group_id});
  assertOk(baseMembers.ok === true && (baseMembers.members || []).length === 1 && Number(baseMembers.members[0].user_id) === ids.base_user_id, 'base employee sees only self in group_members', {baseMembers});

  await expectDenied('base', 'ledger_group_google_sheet', {group_id: ids.group_id});
  await expectDenied('base', 'ledger_group_final_report_list', {group_id: ids.group_id});
  await expectDenied('base', 'ledger_group_final_report_detail', {report_id: ids.report_id});
  await expectDenied('base', 'ledger_group_final_report_package', {report_id: ids.report_id});
  await expectDenied('base', 'ledger_group_final_report_google_sheet', {report_id: ids.report_id});
  await expectDenied('base', 'message_list', {group_id: ids.group_id});
  await expectDenied('base', 'message_send', {group_id: ids.group_id, message_text: `QA denied ${stamp}`});
  await expectDenied('base', 'advance_create', {group_id: ids.group_id, assigned_to_user_id: ids.base_user_id, amount: '10', title: `QA denied ${stamp}`});
  await expectDenied('base', 'group_member_access_update', {group_id: ids.group_id, user_id: ids.base_user_id, access_level: 'manager'}, 'admin_required');

  const baseUnread = await api('base', 'message_unread', {});
  assertOk(baseUnread._http_status === 200 && baseUnread.ok === true && Number(baseUnread.unread_count || 0) === 0 && (baseUnread.messages || []).every((row) => Number(row.group_id) !== ids.group_id), 'base message_unread is safe empty HTTP 200', {baseUnread});

  const baseCurrentExcel = await download('base', `/api.php?action=ledger_group_excel&group_id=${ids.group_id}`, 'base_denied_current_group_report.xls');
  assertOk(baseCurrentExcel.status === 403 && /Access denied/i.test(baseCurrentExcel.textStart), 'base cannot download current group Excel', baseCurrentExcel);

  const baseFinalExcel = await download('base', `/api.php?action=ledger_group_final_report_excel&report_id=${ids.report_id}`, 'base_denied_final_group_report.xls');
  assertOk(baseFinalExcel.status === 403 && /access_denied|Access denied/i.test(baseFinalExcel.textStart), 'base cannot download final group Excel', baseFinalExcel);

  const tapeBefore = await api('base', 'on_the_go_tape_list', {group_id: ids.group_id, stream_type: 'cash'});
  assertOk(tapeBefore.ok === true && money(tapeBefore.tapes?.[0]?.cash_received) === 0, 'base operational tape starts from own cash base 0', {tapeBefore});

  const draft = await api('base', 'on_the_go_field_draft_save', {
    client_draft_id: `draft-base-rights-${stamp}`,
    client_operation_id: `op-draft-base-rights-${stamp}`,
    group_id: ids.group_id,
    participant_user_id: ids.admin_user_id,
    stream_type: 'cash',
    raw_notes: `-5 QA base own operational row ${stamp}`,
    cash_received: '0',
    sync_state: 'saved',
  });
  assertOk(draft.ok === true && Number(draft.draft?.participant_user_id) === ids.base_user_id && money(draft.tape?.cash_received) === 0, 'base Field Combat draft saves and participant is forced to self', {draft});

  const saved = await api('base', 'on_the_go_signed_sync', {
    tape_id: draft.tape_id,
    group_id: ids.group_id,
    cash_received: '0',
    notes: `-5 QA base own operational row ${stamp}`,
    replace_tape: 1,
    start_next: 0,
    client_operation_id: `op-sync-base-rights-${stamp}`,
  });
  assertOk(saved.ok === true && Number(saved.synced_count) === 1 && money(saved.tape?.cash_received) === 0 && money(saved.tape?.summary?.cash_out) === 5, 'base can save own operational row without admin/group cash', {saved});

  const cards = await api('base', 'on_the_go_card_list', {group_id: ids.group_id, include_empty: 1});
  assertOk(cards.ok === true && (cards.cards || []).every((card) => Number(card.user_id) === ids.base_user_id), 'base sees only own operational cards', {cards});

  const advances = await api('base', 'advance_list', {group_id: ids.group_id});
  assertOk(advances.ok === true && (advances.advances || []).every((advance) => Number(advance.assigned_to_user_id) === ids.base_user_id), 'base sees only own accountable data', {advances});

  const groupReport = await api('base', 'ledger_report', {
    group_id: ids.group_id,
    period: 'custom',
    from: '2026-05-27',
    to: '2026-05-27',
  });
  assertOk(groupReport.ok === true && money(groupReport.summary?.income) === 0, 'base group self-control excludes admin group cash', {groupReport});

  artifacts.evidence = path.join(artifactDir, 'production_base_rights_rerun_evidence.json');
  const evidence = redact({
    ids,
    base_group: baseGroup,
    base_members: baseMembers,
    base_unread: baseUnread,
    tape_before: tapeBefore,
    draft,
    saved,
    cards,
    advances,
    group_report: groupReport,
    checks,
  });
  fs.writeFileSync(artifacts.evidence, JSON.stringify(evidence, null, 2));

  const result = {ok: true, stamp, ids, checks_count: checks.length, checks, artifacts};
  artifacts.result = path.join(artifactDir, 'production_base_rights_rerun_result.json');
  fs.writeFileSync(artifacts.result, JSON.stringify(redact(result), null, 2));
  console.log(JSON.stringify(redact(result), null, 2));
}

main().catch((error) => {
  const failure = {
    ok: false,
    stamp,
    message: error.message,
    context: redact(error.context || null),
    ids,
    checks_count: checks.length,
    checks,
    artifacts,
  };
  fs.writeFileSync(path.join(artifactDir, 'production_base_rights_rerun_failure.json'), JSON.stringify(failure, null, 2));
  console.error(JSON.stringify(failure, null, 2));
  process.exit(1);
});
