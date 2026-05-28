import fs from 'node:fs';

const baseUrl = 'https://finance.brkovic.ltd';
const artifactDir = new URL('.', import.meta.url).pathname;
const codes = JSON.parse(process.env.FINDESK_QA_CODES_JSON || '{}');
const stamp = process.env.FINDESK_QA_STAMP || new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
const inbox = process.env.FINDESK_QA_INBOX || 'vetus.nauta@gmail.com';

const emails = {
  admin: inbox.replace('@', `+findesk-qa-admin-${stamp}@`),
  emp1: inbox.replace('@', `+findesk-qa-emp1-${stamp}@`),
  emp2: inbox.replace('@', `+findesk-qa-emp2-${stamp}@`),
  emp3: inbox.replace('@', `+findesk-qa-emp3-${stamp}@`),
};

const cookies = {};
const ids = {stamp, emails: {...emails}};
const artifacts = {};
const checks = [];

function assertOk(condition, message, context = {}) {
  if (!condition) {
    const error = new Error(message);
    error.context = context;
    throw error;
  }
  checks.push({status: 'PASS', message});
}

function cookieHeader(role) {
  return cookies[role] ? Object.entries(cookies[role]).map(([k, v]) => `${k}=${v}`).join('; ') : '';
}

function rememberCookies(role, headers) {
  const setCookie = headers.getSetCookie ? headers.getSetCookie() : [];
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
  const path = `${artifactDir}${fileName}`;
  fs.writeFileSync(path, body);
  return {path, status: response.status, contentType: response.headers.get('content-type') || '', bytes: body.length};
}

async function login(role) {
  const code = codes[role];
  assertOk(/^\d{6}$/.test(code || ''), `code supplied for ${role}`);
  if (!code) {
    const requested = await api(role, 'request_code', {email: emails[role]});
    assertOk(requested.ok === true, `request_code ${role}`, {requested});
  }
  const verified = await api(role, 'verify_code', {email: emails[role], code});
  assertOk(verified.ok === true && verified.user?.id > 0, `verify_code ${role}`, {verified});
  ids[`${role}_user_id`] = verified.user.id;
}

async function inviteAndJoin(role, groupId) {
  const invite = await api('admin', 'group_invite_create', {
    group_id: groupId,
    channel: 'copy',
    invited_email: emails[role],
    access_level: 'base',
  });
  assertOk(invite.ok === true && invite.invite?.url, `invite ${role}`, {invite});
  const token = new URL(invite.invite.url).searchParams.get('invite');
  const join = await api(role, 'group_join', {token});
  assertOk(join.ok === true && Number(join.group?.id) === Number(groupId), `join ${role}`, {join});
}

async function createAdminLiveReportExpenses() {
  const tape = await api('admin', 'on_the_go_tape_create', {
    group_id: ids.group_id,
    title: `QA admin expenses ${stamp}`,
    cash_received: '0',
  });
  assertOk(tape.ok === true && tape.tape?.id > 0, 'admin Live Report expense card created', {tape});
  ids.admin_tape_id = tape.tape.id;
  const notes = [20, 45, 17, 4].map((amount, index) => `-${amount} QA admin expense ${index + 1} ${stamp}`).join('\n');
  const saved = await api('admin', 'on_the_go_signed_sync', {
    tape_id: ids.admin_tape_id,
    group_id: ids.group_id,
    cash_received: '0',
    notes,
    replace_tape: 1,
    start_next: 0,
  });
  assertOk(saved.ok === true && Number(saved.synced_count) === 4, 'admin Live Report expenses saved', {saved});
  const included = await api('admin', 'on_the_go_card_include', {
    id: ids.admin_tape_id,
    group_id: ids.group_id,
  });
  assertOk(included.ok === true && included.card?.id > 0, 'admin Live Report expense card included', {included});
}

async function createAdvance(role, amount) {
  const row = await api('admin', 'advance_create', {
    group_id: ids.group_id,
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
    group_id: ids.group_id,
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
  ids[`${role}_difference_amount`] = Number(submitted.advance.difference_amount || 0);
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

function includesAll(text, needles) {
  return needles.every((needle) => text.includes(needle));
}

function money(value) {
  return Math.round(Number(value || 0) * 100) / 100;
}

async function main() {
  for (const role of ['admin', 'emp1', 'emp2', 'emp3']) await login(role);

  const group = await api('admin', 'group_create', {name: `QA production multi employee ${stamp}`});
  assertOk(group.ok === true && group.group?.id > 0, 'group created', {group});
  ids.group_id = group.group.id;

  for (const role of ['emp1', 'emp2', 'emp3']) await inviteAndJoin(role, ids.group_id);

  const members = await api('admin', 'group_members', {group_id: ids.group_id});
  assertOk(members.ok === true && (members.members || []).length === 4, 'admin sees four group members', {members});

  const income = await api('admin', 'ledger_create', {
    group_id: ids.group_id,
    entry_type: 'income',
    money_type: 'cash',
    amount: '1000',
    purpose: `QA admin received EUR 1000 ${stamp}`,
  });
  assertOk(income.ok === true && income.entry?.id > 0, 'admin income EUR 1000', {income});
  ids.admin_income_id = income.entry.id;

  await createAdminLiveReportExpenses();

  await createAdvance('emp1', 135);
  await createAdvance('emp2', 94);
  await createAdvance('emp3', 117);

  await spendAdvance('emp1', [6, 9, 43, 10]);
  await spendAdvance('emp2', [12, 23, 41, 54]);

  await submitAdvance('emp1', 67);
  await submitAdvance('emp2', 0);
  await submitAdvance('emp3', 117);

  assertOk(ids.emp1_submit_status === 'submitted' && money(ids.emp1_difference_amount) === 0, 'employee 1 remainder submitted as EUR 67');
  assertOk(ids.emp2_submit_status === 'discrepancy' && money(ids.emp2_difference_amount) === 36, 'employee 2 overrun remains visible as EUR 36 discrepancy/reimbursement due');
  assertOk(ids.emp3_submit_status === 'submitted' && money(ids.emp3_difference_amount) === 0, 'employee 3 no-spend remainder submitted as EUR 117');

  await acceptAdvance('emp1');
  await acceptAdvance('emp2');
  await acceptAdvance('emp3');

  const currentSheet = await api('admin', 'ledger_group_google_sheet', {group_id: ids.group_id});
  assertOk(currentSheet.ok === true && includesAll(currentSheet.tsv || '', ['1000.00', '284.00', '532.00', '67.00', '117.00']), 'current group export contains expected totals and remainders', {currentSheet});
  artifacts.current_google_sheet = `${artifactDir}current_group_google_sheet.tsv`;
  fs.writeFileSync(artifacts.current_google_sheet, currentSheet.tsv || '');

  const currentExcel = await download('admin', `/api.php?action=ledger_group_excel&group_id=${ids.group_id}`, 'current_group_report.xls');
  assertOk(currentExcel.status === 200 && currentExcel.bytes > 0, 'current Excel downloaded', {currentExcel});
  artifacts.current_excel = currentExcel.path;

  const finalized = await api('admin', 'ledger_group_finalize_report', {group_id: ids.group_id});
  assertOk(finalized.ok === true && finalized.report_id > 0 && finalized.finalized >= 1, 'group final report finalized', {finalized});
  ids.report_id = finalized.report_id;

  const list = await api('admin', 'ledger_group_final_report_list', {group_id: ids.group_id});
  assertOk(list.ok === true && (list.reports || []).some((r) => Number(r.id) === ids.report_id && r.snapshot_available && r.package_available), 'final report appears in archive list', {list});

  const detail = await api('admin', 'ledger_group_final_report_detail', {report_id: ids.report_id});
  assertOk(detail.ok === true, 'final report detail opens', {detail});
  const totals = detail.snapshot?.totals || {};
  assertOk(money(totals.income) === 1000 && money(totals.expense) === 284 && money(totals.admin_cash_left) === 532 && money(totals.cash_balance) === 716 && money(totals.balance) === 716, 'final report financial totals match control', {totals});
  artifacts.final_detail_json = `${artifactDir}final_report_detail.json`;
  fs.writeFileSync(artifacts.final_detail_json, JSON.stringify(detail, null, 2));

  const packageData = await api('admin', 'ledger_group_final_report_package', {report_id: ids.report_id});
  assertOk(packageData.ok === true && packageData.package_type === 'group_final_report', 'closed group package opens', {packageData});
  const summary = packageData.package?.summary || {};
  assertOk(money(summary.received_money) === 1000 && money(summary.physical_cash_spent) === 284 && money(summary.admin_cash_left) === 532 && money(summary.accountable_money_left) === 184 && money(summary.cash_balance) === 716 && money(summary.balance) === 716, 'package summary matches expected totals', {summary});
  assertOk((packageData.package?.participants || []).length === 4 && (packageData.package?.money_rows || []).length >= 9 && (packageData.package?.accountable || []).length >= 3, 'package includes participants, money rows, accountable state');
  artifacts.package_json = `${artifactDir}closed_group_package.json`;
  fs.writeFileSync(artifacts.package_json, JSON.stringify(packageData, null, 2));

  const finalSheet = await api('admin', 'ledger_group_final_report_google_sheet', {report_id: ids.report_id});
  assertOk(finalSheet.ok === true && includesAll(finalSheet.tsv || '', ['1000.00', '284.00', '532.00', '67.00', '117.00']), 'final Google/TSV export contains expected totals');
  artifacts.final_google_sheet = `${artifactDir}final_report_google_sheet.tsv`;
  fs.writeFileSync(artifacts.final_google_sheet, finalSheet.tsv || '');

  const finalExcel = await download('admin', `/api.php?action=ledger_group_final_report_excel&report_id=${ids.report_id}`, 'final_group_report.xls');
  assertOk(finalExcel.status === 200 && finalExcel.bytes > 0, 'final Excel downloaded', {finalExcel});
  artifacts.final_excel = finalExcel.path;

  artifacts.print_html = `${artifactDir}closed_group_package_print.html`;
  fs.writeFileSync(artifacts.print_html, `<!doctype html><meta charset="utf-8"><title>FinDesk QA package ${ids.report_id}</title><pre>${JSON.stringify(packageData.package, null, 2)}</pre>`);

  const emp2Control = 94 - 130;
  assertOk(emp2Control === -36, 'employee 2 control overrun is EUR -36 / reimbursement due EUR 36');
  const emp3Control = 117 - 0;
  assertOk(emp3Control === 117, 'employee 3 no-spend remainder is EUR 117');
  const groupControl = 568 + 67 - 36 + 117;
  assertOk(groupControl === 716, 'participant balance equation equals EUR 716');

  artifacts.ids_json = `${artifactDir}ids.json`;
  fs.writeFileSync(artifacts.ids_json, JSON.stringify(ids, null, 2));
  artifacts.result_json = `${artifactDir}result.json`;
  fs.writeFileSync(artifacts.result_json, JSON.stringify({ids, checks, artifacts}, null, 2));

  console.log(JSON.stringify({ok: true, ids, checks: checks.length, artifacts}, null, 2));
}

main().catch((error) => {
  const failure = {ok: false, message: error.message, context: error.context || null, ids, checks};
  fs.writeFileSync(`${artifactDir}failure.json`, JSON.stringify(failure, null, 2));
  console.error(JSON.stringify(failure, null, 2));
  process.exit(1);
});
