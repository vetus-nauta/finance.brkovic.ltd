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
  admin: inbox.replace('@', `+qa-hotfix-admin-${stamp}@`),
  emp1: inbox.replace('@', `+qa-hotfix-emp1-${stamp}@`),
  emp2: inbox.replace('@', `+qa-hotfix-emp2-${stamp}@`),
  emp3: inbox.replace('@', `+qa-hotfix-emp3-${stamp}@`),
  rightsAdmin: inbox.replace('@', `+qa-rights-admin-${stamp}@`),
  rightsBase: inbox.replace('@', `+qa-rights-base-${stamp}@`),
  rightsOther: inbox.replace('@', `+qa-rights-other-${stamp}@`),
};

const cookies = {};
const ids = {stamp};
const checks = [];
const artifacts = {};

function redactPayload(value) {
  if (Array.isArray(value)) return value.map(redactPayload);
  if (!value || typeof value !== 'object') return value;
  const out = {};
  for (const [key, val] of Object.entries(value)) {
    if (/cookie|token|code|password|pass/i.test(key)) {
      out[key] = '[redacted]';
    } else {
      out[key] = redactPayload(val);
    }
  }
  return out;
}

function assertOk(condition, message, context = {}) {
  if (!condition) {
    const error = new Error(message);
    error.context = redactPayload(context);
    throw error;
  }
  checks.push({status: 'PASS', message});
}

function money(value) {
  return Math.round(Number(value || 0) * 100) / 100;
}

function includesAll(text, needles) {
  return needles.every((needle) => String(text || '').includes(needle));
}

function cookieHeader(role) {
  return cookies[role] ? Object.entries(cookies[role]).map(([k, v]) => `${k}=${v}`).join('; ') : '';
}

function rememberCookies(role, headers) {
  let setCookie = [];
  if (typeof headers.getSetCookie === 'function') {
    setCookie = headers.getSetCookie();
  } else {
    const row = headers.get('set-cookie');
    if (row) setCookie = [row];
  }
  if (!cookies[role]) cookies[role] = {};
  for (const row of setCookie) {
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
  const response = await fetch(`${baseUrl}${urlPath}`, {
    headers: {Cookie: cookieHeader(role)},
  });
  const body = Buffer.from(await response.arrayBuffer());
  const filePath = path.join(artifactDir, fileName);
  fs.writeFileSync(filePath, body);
  return {
    path: filePath,
    status: response.status,
    contentType: response.headers.get('content-type') || '',
    bytes: body.length,
    textStart: body.toString('utf8', 0, Math.min(body.length, 300)),
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
    if (parts.length >= 3 && parts[1] === email && /^\d{6}$/.test(parts[2])) {
      return parts[2];
    }
  }
  return '';
}

async function login(role) {
  const email = emails[role];
  const requested = await api(role, 'request_code', {email});
  assertOk(requested.ok === true || requested.error === 'email_send_failed', `request_code ${role}`, {
    ok: requested.ok,
    error: requested.error || null,
    mail_method: requested.mail_method || null,
  });
  const code = latestCodeFor(email);
  assertOk(/^\d{6}$/.test(code), `auth code available for ${role}`);
  const verified = await api(role, 'verify_code', {email, code});
  assertOk(verified.ok === true && verified.user?.id > 0, `verify_code ${role}`, {verified});
  ids[`${role}_user_id`] = verified.user.id;
}

async function inviteAndJoin(adminRole, memberRole, groupId, withExplicitAccess = false) {
  const payload = {
    group_id: groupId,
    channel: 'copy',
    invited_email: emails[memberRole],
  };
  if (withExplicitAccess) payload.access_level = 'base';
  const invite = await api(adminRole, 'group_invite_create', payload);
  assertOk(invite.ok === true && invite.invite?.url, `invite ${memberRole}`, {invite});
  const token = new URL(invite.invite.url).searchParams.get('invite');
  const join = await api(memberRole, 'group_join', {token});
  assertOk(join.ok === true && Number(join.group?.id) === Number(groupId), `join ${memberRole}`, {join});
  return {invite, join};
}

async function createAdminExpenses(groupId) {
  const tape = await api('admin', 'on_the_go_tape_create', {
    group_id: groupId,
    title: `QA admin expenses ${stamp}`,
    cash_received: '0',
  });
  assertOk(tape.ok === true && tape.tape?.id > 0, 'admin Live Report expense card created', {tape});
  ids.admin_tape_id = tape.tape.id;
  const notes = [20, 45, 17, 4].map((amount, index) => `-${amount} QA admin expense ${index + 1} ${stamp}`).join('\n');
  const saved = await api('admin', 'on_the_go_signed_sync', {
    tape_id: ids.admin_tape_id,
    group_id: groupId,
    cash_received: '0',
    notes,
    replace_tape: 1,
    start_next: 0,
  });
  assertOk(saved.ok === true && Number(saved.synced_count) === 4, 'admin Live Report expenses saved', {saved});
  const included = await api('admin', 'on_the_go_card_include', {
    id: ids.admin_tape_id,
    group_id: groupId,
  });
  assertOk(included.ok === true && included.card?.id > 0, 'admin Live Report expense card included', {included});
}

async function createAdvance(role, amount) {
  const row = await api('admin', 'advance_create', {
    group_id: ids.participant_group_id,
    assigned_to_user_id: ids[`${role}_user_id`],
    amount: String(amount),
    currency: 'EUR',
    title: `QA accountable ${role} ${stamp}`,
  });
  assertOk(row.ok === true && row.advance?.id > 0 && row.advance?.on_the_go_tape_id > 0, `advance issued ${role} ${amount}`, {row});
  ids[`${role}_advance_id`] = row.advance.id;
  ids[`${role}_tape_id`] = row.advance.on_the_go_tape_id;
}

async function spendAdvance(role, amounts) {
  if (!amounts.length) return;
  const notes = amounts.map((amount, index) => `-${amount} QA ${role} expense ${index + 1} ${stamp}`).join('\n');
  const saved = await api(role, 'on_the_go_signed_sync', {
    tape_id: ids[`${role}_tape_id`],
    group_id: ids.participant_group_id,
    cash_received: '0',
    notes,
    replace_tape: 1,
    start_next: 0,
  });
  assertOk(saved.ok === true && Number(saved.synced_count) === amounts.length, `advance expenses saved ${role}`, {saved});
}

async function submitAdvance(role, actualRemaining) {
  const submitted = await api(role, 'advance_submit', {
    id: ids[`${role}_advance_id`],
    actual_remaining: String(actualRemaining),
    note: `QA submitted ${role} ${stamp}`,
  });
  assertOk(submitted.ok === true && ['submitted', 'discrepancy'].includes(submitted.advance?.status), `advance submitted ${role}`, {submitted});
  ids[`${role}_submit_status`] = submitted.advance.status;
  ids[`${role}_difference_amount`] = money(submitted.advance.difference_amount || 0);
}

async function acceptAdvance(role) {
  const accepted = await api('admin', 'advance_accept', {
    id: ids[`${role}_advance_id`],
    note: `QA accepted ${role} ${stamp}`,
  });
  assertOk(accepted.ok === true && accepted.advance?.status === 'accepted', `advance accepted ${role}`, {accepted});
  ids[`${role}_entries_created`] = accepted.entries_created || 0;
  ids[`${role}_rollover_advance_id`] = accepted.rollover_advance_id || 0;
  ids[`${role}_rollover_tape_id`] = accepted.rollover_tape_id || 0;
}

function assertParticipantControlTotals(totals, source) {
  assertOk(money(totals.income ?? totals.received_money) === 1000, `${source}: income/received is 1000`, {totals});
  assertOk(money(totals.expense ?? totals.physical_cash_spent) === 284, `${source}: expense is 284`, {totals});
  assertOk(money(totals.cash_balance) === 716 && money(totals.balance) === 716, `${source}: balance is 716`, {totals});
  assertOk(money(totals.admin_cash_left) === 568, `${source}: admin_cash_left is 568`, {totals});
  assertOk(money(totals.employee_positive_remaining_total) === 184, `${source}: employee positive remaining is 184`, {totals});
  assertOk(money(totals.employee_reimbursement_due_total) === 36, `${source}: employee reimbursement due is 36`, {totals});
  assertOk(money(totals.employee_net_remaining_total ?? totals.employee_cash_left ?? totals.accountable_money_left) === 148, `${source}: employee net remaining is 148`, {totals});
}

function assertSignedParticipantRows(rows, source) {
  const values = rows.map((row) => money(row.cash_left ?? row.participant_control_balance));
  assertOk(values.includes(67), `${source}: employee 1 signed remainder 67 visible`, {rows});
  assertOk(values.includes(-36), `${source}: employee 2 signed remainder -36 visible`, {rows});
  assertOk(values.includes(117), `${source}: employee 3 signed remainder 117 visible`, {rows});
  const overrunRow = rows.find((row) => money(row.cash_left ?? row.participant_control_balance) === -36);
  assertOk(money(overrunRow?.reimbursement_due) === 36, `${source}: employee 2 reimbursement due 36 visible`, {rows});
}

async function runParticipantControlRecheck() {
  for (const role of ['admin', 'emp1', 'emp2', 'emp3']) await login(role);

  const group = await api('admin', 'group_create', {name: `QA production hotfix participant ${stamp}`});
  assertOk(group.ok === true && group.group?.id > 0, 'participant-control group created', {group});
  ids.participant_group_id = group.group.id;

  for (const role of ['emp1', 'emp2', 'emp3']) await inviteAndJoin('admin', role, ids.participant_group_id, true);

  const members = await api('admin', 'group_members', {group_id: ids.participant_group_id});
  assertOk(members.ok === true && (members.members || []).length === 4, 'admin sees four participant-control group members', {members});

  const income = await api('admin', 'ledger_create', {
    group_id: ids.participant_group_id,
    entry_type: 'income',
    money_type: 'cash',
    amount: '1000',
    purpose: `QA admin received EUR 1000 ${stamp}`,
  });
  assertOk(income.ok === true && income.entry?.id > 0, 'admin income EUR 1000', {income});
  ids.admin_income_id = income.entry.id;

  await createAdminExpenses(ids.participant_group_id);
  await createAdvance('emp1', 135);
  await createAdvance('emp2', 94);
  await createAdvance('emp3', 117);
  await spendAdvance('emp1', [6, 9, 43, 10]);
  await spendAdvance('emp2', [12, 23, 41, 54]);
  await submitAdvance('emp1', 67);
  await submitAdvance('emp2', 0);
  await submitAdvance('emp3', 117);

  assertOk(ids.emp1_submit_status === 'submitted' && ids.emp1_difference_amount === 0, 'employee 1 submitted EUR 67 with no discrepancy');
  assertOk(ids.emp2_submit_status === 'discrepancy' && ids.emp2_difference_amount === 36, 'employee 2 overrun is EUR 36 before accept');
  assertOk(ids.emp3_submit_status === 'submitted' && ids.emp3_difference_amount === 0, 'employee 3 no-spend submitted EUR 117');

  await acceptAdvance('emp1');
  await acceptAdvance('emp2');
  await acceptAdvance('emp3');

  const currentSheet = await api('admin', 'ledger_group_google_sheet', {group_id: ids.participant_group_id});
  assertOk(currentSheet.ok === true && includesAll(currentSheet.tsv || '', ['1000.00', '284.00', '568.00', '184.00', '36.00', '148.00', '-36.00', '117.00']), 'current export exposes participant-control values', {tsvStart: String(currentSheet.tsv || '').slice(0, 1000)});
  artifacts.current_group_google_sheet = path.join(artifactDir, 'participant_current_group_google_sheet.tsv');
  fs.writeFileSync(artifacts.current_group_google_sheet, currentSheet.tsv || '');

  const currentExcel = await download('admin', `/api.php?action=ledger_group_excel&group_id=${ids.participant_group_id}`, 'participant_current_group_report.xls');
  assertOk(currentExcel.status === 200 && currentExcel.bytes > 0 && includesAll(currentExcel.textStart + fs.readFileSync(currentExcel.path, 'utf8'), ['568.00', '-36.00', '36.00']), 'current Excel downloaded with participant-control values', currentExcel);
  artifacts.current_group_excel = currentExcel.path;

  const finalized = await api('admin', 'ledger_group_finalize_report', {group_id: ids.participant_group_id});
  assertOk(finalized.ok === true && finalized.report_id > 0 && finalized.finalized >= 1, 'participant-control group finalized', {finalized});
  ids.participant_report_id = finalized.report_id;

  const list = await api('admin', 'ledger_group_final_report_list', {group_id: ids.participant_group_id});
  assertOk(list.ok === true && (list.reports || []).some((r) => Number(r.id) === ids.participant_report_id && r.snapshot_available && r.package_available), 'final report appears in archive list', {list});

  const detail = await api('admin', 'ledger_group_final_report_detail', {report_id: ids.participant_report_id});
  assertOk(detail.ok === true, 'final report detail opens', {detail});
  const detailTotals = detail.snapshot?.totals || {};
  assertParticipantControlTotals(detailTotals, 'final detail');
  assertSignedParticipantRows(detail.snapshot?.accountable_rows || detail.snapshot?.participant_control?.participants || [], 'final detail');
  artifacts.final_report_detail = path.join(artifactDir, 'participant_final_report_detail.json');
  fs.writeFileSync(artifacts.final_report_detail, JSON.stringify(redactPayload(detail), null, 2));

  const packageData = await api('admin', 'ledger_group_final_report_package', {report_id: ids.participant_report_id});
  assertOk(packageData.ok === true && packageData.package_type === 'group_final_report', 'closed group package opens', {packageData});
  const summary = packageData.package?.summary || {};
  assertParticipantControlTotals(summary, 'closed package summary');
  assertSignedParticipantRows(packageData.package?.participant_control?.participants || packageData.package?.accountable?.by_participant || [], 'closed package participant control');
  assertOk(money(packageData.package?.accountable?.totals?.reimbursement_due) === 36, 'closed package accountable totals expose reimbursement due 36', {accountable: packageData.package?.accountable});
  artifacts.closed_group_package = path.join(artifactDir, 'participant_closed_group_package.json');
  fs.writeFileSync(artifacts.closed_group_package, JSON.stringify(redactPayload(packageData), null, 2));

  const finalSheet = await api('admin', 'ledger_group_final_report_google_sheet', {report_id: ids.participant_report_id});
  assertOk(finalSheet.ok === true && includesAll(finalSheet.tsv || '', ['1000.00', '284.00', '568.00', '184.00', '36.00', '148.00', '-36.00', '117.00']), 'final Google/TSV export exposes participant-control values');
  artifacts.final_report_google_sheet = path.join(artifactDir, 'participant_final_report_google_sheet.tsv');
  fs.writeFileSync(artifacts.final_report_google_sheet, finalSheet.tsv || '');

  const finalExcel = await download('admin', `/api.php?action=ledger_group_final_report_excel&report_id=${ids.participant_report_id}`, 'participant_final_group_report.xls');
  const finalExcelText = fs.readFileSync(finalExcel.path, 'utf8');
  assertOk(finalExcel.status === 200 && finalExcel.bytes > 0 && includesAll(finalExcelText, ['568.00', '-36.00', '36.00', '148.00']), 'final Excel downloaded with participant-control values', finalExcel);
  artifacts.final_group_excel = finalExcel.path;

  const packagePrint = `<!doctype html><meta charset="utf-8"><title>FinDesk QA package ${ids.participant_report_id}</title><pre>${JSON.stringify(redactPayload(packageData.package), null, 2)}</pre>`;
  artifacts.package_print = path.join(artifactDir, 'participant_closed_group_package_print.html');
  fs.writeFileSync(artifacts.package_print, packagePrint);

  assertOk(568 + 67 - 36 + 117 === 716, 'participant-control equation 568 + 67 - 36 + 117 = 716');
}

async function expectDenied(role, action, payload, expectedError = 'access_denied') {
  const result = await api(role, action, payload);
  assertOk(result.ok === false && result.error === expectedError, `${role} denied ${action} with ${expectedError}`, {result});
  return result;
}

async function runBaseRightsRecheck() {
  for (const role of ['rightsAdmin', 'rightsBase', 'rightsOther']) await login(role);

  const group = await api('rightsAdmin', 'group_create', {name: `QA production hotfix base rights ${stamp}`});
  assertOk(group.ok === true && group.group?.id > 0, 'base-rights group created', {group});
  ids.rights_group_id = group.group.id;

  const baseJoin = await inviteAndJoin('rightsAdmin', 'rightsBase', ids.rights_group_id, false);
  await inviteAndJoin('rightsAdmin', 'rightsOther', ids.rights_group_id, false);
  assertOk(baseJoin.invite.invite.access_level === 'base', 'default invite creates access_level base', {invite: baseJoin.invite.invite});
  assertOk(baseJoin.join.group.access_level === 'base', 'joined base employee has access_level base', {group: baseJoin.join.group});

  const adminIncome = await api('rightsAdmin', 'ledger_create', {
    group_id: ids.rights_group_id,
    entry_type: 'income',
    money_type: 'cash',
    amount: '1000',
    purpose: `QA rights admin cash EUR 1000 ${stamp}`,
  });
  assertOk(adminIncome.ok === true && adminIncome.entry?.id > 0, 'rights admin group income created', {adminIncome});

  const adminMessage = await api('rightsAdmin', 'message_send', {
    group_id: ids.rights_group_id,
    message_text: `QA admin-only group message ${stamp}`,
  });
  assertOk(adminMessage.ok === true && adminMessage.message?.id > 0, 'admin group message send still works', {adminMessage});

  const otherAdvance = await api('rightsAdmin', 'advance_create', {
    group_id: ids.rights_group_id,
    assigned_to_user_id: ids.rightsOther_user_id,
    amount: '50',
    currency: 'EUR',
    title: `QA other employee advance ${stamp}`,
  });
  assertOk(otherAdvance.ok === true && otherAdvance.advance?.id > 0, 'admin money management still works for setup', {otherAdvance});
  ids.rights_other_advance_id = otherAdvance.advance.id;

  const rightsAdminTape = await api('rightsAdmin', 'on_the_go_tape_create', {
    group_id: ids.rights_group_id,
    title: `QA rights admin included card ${stamp}`,
    cash_received: '0',
  });
  assertOk(rightsAdminTape.ok === true && rightsAdminTape.tape?.id > 0, 'rights admin included card created for archive setup', {rightsAdminTape});
  ids.rights_admin_tape_id = rightsAdminTape.tape.id;
  const rightsAdminSave = await api('rightsAdmin', 'on_the_go_signed_sync', {
    tape_id: ids.rights_admin_tape_id,
    group_id: ids.rights_group_id,
    cash_received: '0',
    notes: `-1 QA rights finalization card ${stamp}`,
    replace_tape: 1,
    start_next: 0,
  });
  assertOk(rightsAdminSave.ok === true && Number(rightsAdminSave.synced_count) === 1, 'rights admin included card saved for archive setup', {rightsAdminSave});
  const rightsAdminInclude = await api('rightsAdmin', 'on_the_go_card_include', {
    id: ids.rights_admin_tape_id,
    group_id: ids.rights_group_id,
  });
  assertOk(rightsAdminInclude.ok === true && rightsAdminInclude.card?.id > 0, 'rights admin included card accepted for archive setup', {rightsAdminInclude});

  const adminFinalize = await api('rightsAdmin', 'ledger_group_finalize_report', {group_id: ids.rights_group_id});
  assertOk(adminFinalize.ok === true && adminFinalize.report_id > 0, 'admin final report/archive still works for setup', {adminFinalize});
  ids.rights_report_id = adminFinalize.report_id;

  const adminMembers = await api('rightsAdmin', 'group_members', {group_id: ids.rights_group_id});
  assertOk(adminMembers.ok === true && (adminMembers.members || []).length === 3, 'admin can still see all members', {adminMembers});
  const adminFinalList = await api('rightsAdmin', 'ledger_group_final_report_list', {group_id: ids.rights_group_id});
  assertOk(adminFinalList.ok === true && (adminFinalList.reports || []).some((r) => Number(r.id) === ids.rights_report_id), 'admin can still list final reports', {adminFinalList});

  const baseGroups = await api('rightsBase', 'group_list', {});
  const baseGroup = (baseGroups.groups || []).find((row) => Number(row.id) === Number(ids.rights_group_id));
  assertOk(baseGroups.ok === true && baseGroup?.access_level === 'base', 'base employee group list shows base access', {baseGroups});
  assertOk(baseGroup.permissions?.can_view_group_reports === false && baseGroup.permissions?.can_write_group_ledger === false && baseGroup.permissions?.can_manage_money === false, 'base permissions deny reports, ledger write, money management', {baseGroup});

  const baseMembers = await api('rightsBase', 'group_members', {group_id: ids.rights_group_id});
  assertOk(baseMembers.ok === true && (baseMembers.members || []).length === 1 && Number(baseMembers.members[0].user_id) === Number(ids.rightsBase_user_id), 'base employee sees only self in group_members', {baseMembers});

  await expectDenied('rightsBase', 'ledger_group_google_sheet', {group_id: ids.rights_group_id});
  await expectDenied('rightsBase', 'ledger_group_final_report_list', {group_id: ids.rights_group_id});
  await expectDenied('rightsBase', 'ledger_group_final_report_detail', {report_id: ids.rights_report_id});
  await expectDenied('rightsBase', 'ledger_group_final_report_package', {report_id: ids.rights_report_id});
  await expectDenied('rightsBase', 'ledger_group_final_report_google_sheet', {report_id: ids.rights_report_id});
  await expectDenied('rightsBase', 'message_list', {group_id: ids.rights_group_id});
  await expectDenied('rightsBase', 'message_send', {group_id: ids.rights_group_id, message_text: `base denied ${stamp}`});
  await expectDenied('rightsBase', 'advance_create', {group_id: ids.rights_group_id, assigned_to_user_id: ids.rightsBase_user_id, amount: '10', title: `denied ${stamp}`});
  await expectDenied('rightsBase', 'group_member_access_update', {group_id: ids.rights_group_id, user_id: ids.rightsBase_user_id, access_level: 'manager'}, 'admin_required');

  const baseUnread = await api('rightsBase', 'message_unread', {});
  assertOk(baseUnread.ok === true && Number(baseUnread.unread_count || 0) === 0 && (baseUnread.messages || []).every((msg) => Number(msg.group_id) !== ids.rights_group_id), 'base employee unread feed does not expose group messages', {baseUnread});

  const baseCurrentExcel = await download('rightsBase', `/api.php?action=ledger_group_excel&group_id=${ids.rights_group_id}`, 'base_denied_current_group_report.xls');
  assertOk(baseCurrentExcel.status === 403 && /Access denied/i.test(baseCurrentExcel.textStart), 'base employee cannot download current group Excel', baseCurrentExcel);
  artifacts.base_denied_current_excel = baseCurrentExcel.path;

  const baseFinalExcel = await download('rightsBase', `/api.php?action=ledger_group_final_report_excel&report_id=${ids.rights_report_id}`, 'base_denied_final_group_report.xls');
  assertOk(baseFinalExcel.status === 403 && /access_denied|Access denied/i.test(baseFinalExcel.textStart), 'base employee cannot download final group Excel', baseFinalExcel);
  artifacts.base_denied_final_excel = baseFinalExcel.path;

  const baseTapeListBefore = await api('rightsBase', 'on_the_go_tape_list', {group_id: ids.rights_group_id, stream_type: 'cash'});
  assertOk(baseTapeListBefore.ok === true && money(baseTapeListBefore.tapes?.[0]?.cash_received) === 0, 'base employee active field tape starts from own cash base 0', {baseTapeListBefore});

  const baseDraft = await api('rightsBase', 'on_the_go_field_draft_save', {
    client_draft_id: `draft-rights-${stamp}`,
    client_operation_id: `op-draft-rights-${stamp}`,
    group_id: ids.rights_group_id,
    participant_user_id: ids.rightsAdmin_user_id,
    stream_type: 'cash',
    raw_notes: `-5 QA base own operational row ${stamp}`,
    cash_received: '0',
    sync_state: 'saved',
  });
  assertOk(baseDraft.ok === true && Number(baseDraft.draft?.participant_user_id) === ids.rightsBase_user_id && money(baseDraft.tape?.cash_received) === 0, 'base Field Combat draft is saved and participant forced to self', {baseDraft});

  const baseSave = await api('rightsBase', 'on_the_go_signed_sync', {
    tape_id: baseDraft.tape_id,
    group_id: ids.rights_group_id,
    cash_received: '0',
    notes: `-5 QA base own operational row ${stamp}`,
    replace_tape: 1,
    start_next: 0,
    client_operation_id: `op-sync-rights-${stamp}`,
  });
  assertOk(baseSave.ok === true && Number(baseSave.synced_count) === 1 && money(baseSave.tape?.cash_received) === 0 && money(baseSave.tape?.summary?.cash_out) === 5, 'base employee can save own operational row without admin cash', {baseSave});

  const baseTapeListAfter = await api('rightsBase', 'on_the_go_tape_list', {group_id: ids.rights_group_id, stream_type: 'cash'});
  assertOk(baseTapeListAfter.ok === true && (baseTapeListAfter.tapes || []).length >= 1 && (baseTapeListAfter.tapes || []).every((tape) => Number(tape.user_id) === ids.rightsBase_user_id), 'base employee sees only own operational cards', {baseTapeListAfter});

  const baseAdvanceList = await api('rightsBase', 'advance_list', {group_id: ids.rights_group_id});
  assertOk(baseAdvanceList.ok === true && (baseAdvanceList.advances || []).every((advance) => Number(advance.assigned_to_user_id) === ids.rightsBase_user_id), 'base employee does not see other member accountable data', {baseAdvanceList});

  const baseReport = await api('rightsBase', 'ledger_report', {group_id: ids.rights_group_id, period: 'custom', from: '2026-05-27', to: '2026-05-27'});
  const baseTotalIncome = money(baseReport.summary?.income || 0);
  assertOk(baseReport.ok === true && baseTotalIncome === 0, 'base self-control report does not include administrator group cash', {baseReport});

  artifacts.base_rights_evidence = path.join(artifactDir, 'base_rights_evidence.json');
  fs.writeFileSync(artifacts.base_rights_evidence, JSON.stringify(redactPayload({
    group_id: ids.rights_group_id,
    report_id: ids.rights_report_id,
    base_user_id: ids.rightsBase_user_id,
    base_group: baseGroup,
    base_members: baseMembers,
    base_tape_before: baseTapeListBefore,
    base_draft: baseDraft,
    base_save: baseSave,
    base_tape_after: baseTapeListAfter,
    base_advance_list: baseAdvanceList,
    base_report: baseReport,
  }), null, 2));
}

async function main() {
  await runParticipantControlRecheck();
  await runBaseRightsRecheck();

  const result = {
    ok: true,
    stamp,
    ids,
    checks_count: checks.length,
    checks,
    artifacts,
  };
  artifacts.result = path.join(artifactDir, 'production_hotfix_recheck_result.json');
  fs.writeFileSync(artifacts.result, JSON.stringify(redactPayload(result), null, 2));
  console.log(JSON.stringify(redactPayload(result), null, 2));
}

main().catch((error) => {
  const failure = {
    ok: false,
    stamp,
    message: error.message,
    context: redactPayload(error.context || null),
    ids,
    checks_count: checks.length,
    checks,
    artifacts,
  };
  fs.writeFileSync(path.join(artifactDir, 'production_hotfix_recheck_failure.json'), JSON.stringify(failure, null, 2));
  console.error(JSON.stringify(failure, null, 2));
  process.exit(1);
});
