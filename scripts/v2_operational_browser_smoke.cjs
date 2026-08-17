const { chromium } = require('playwright-core');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.FINDESK_V2_BROWSER_BASE;
const cookieName = process.env.FINDESK_V2_BROWSER_COOKIE;
const token = process.env.FINDESK_V2_BROWSER_TOKEN;
const chrome = process.env.FINDESK_V2_BROWSER_CHROME;
const dbSocket = process.env.FINDESK_V2_BROWSER_SOCKET;
const dbName = process.env.FINDESK_V2_BROWSER_DB;
const resultsDir = process.env.FINDESK_V2_BROWSER_RESULTS || path.join(process.cwd(), 'test-results/v2-browser-smoke');
const layoutEvidence = [];
const rawTextInputSelector = '[data-v2-entry-form] [data-v2-raw-text]';

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function routeFromRequest(request) {
  const url = new URL(request.url());
  return url.searchParams.get('route') || '';
}

function queryParamFromRequest(request, key) {
  const url = new URL(request.url());
  return url.searchParams.get(key) || '';
}

function countMatches(text, needle) {
  return (text.match(new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;
}

function sqlQuote(value) {
  return String(value).replace(/\\/g, '\\\\').replace(/'/g, "''");
}

function xlsxBase64(rows) {
  const php = `
$rows = json_decode(stream_get_contents(STDIN), true);
if (!is_array($rows)) { fwrite(STDERR, 'invalid rows'); exit(1); }
function x($value) { return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8'); }
function col($index) {
  $name = '';
  $index++;
  while ($index > 0) {
    $mod = ($index - 1) % 26;
    $name = chr(65 + $mod) . $name;
    $index = intdiv($index - $mod, 26);
  }
  return $name;
}
$path = tempnam(sys_get_temp_dir(), 'findesk-v2-browser-import-') . '.xlsx';
$zip = new ZipArchive();
if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, 'zip failed'); exit(1); }
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="July" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
foreach ($rows as $rowIndex => $row) {
  $number = $rowIndex + 1;
  $sheet .= '<row r="' . $number . '">';
  foreach ($row as $columnIndex => $value) {
    if ((string)$value === '') continue;
    $ref = col((int)$columnIndex) . $number;
    $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . x($value) . '</t></is></c>';
  }
  $sheet .= '</row>';
}
$sheet .= '</sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();
echo base64_encode(file_get_contents($path));
@unlink($path);
`;
  return execFileSync('php', ['-r', php], {
    input: JSON.stringify(rows),
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  }).trim();
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

function execSmokeSql(sql) {
  assert(dbSocket, 'Missing FINDESK_V2_BROWSER_SOCKET');
  assert(dbName, 'Missing FINDESK_V2_BROWSER_DB');
  execFileSync('mariadb', ['--no-defaults', `--socket=${dbSocket}`, '-uroot', dbName], {
    input: sql,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
}

function seedHallAccountableReport(workspaceId) {
  const offerId = crypto.randomUUID();
  const reportId = crypto.randomUUID();
  const rowOneId = crypto.randomUUID();
  const rowTwoId = crypto.randomUUID();
  const sql = `
INSERT INTO v2_accountable_offers (
  id, workspace_id, employee_user_id, employee_email, amount, currency, purpose,
  status, created_by, accepted_at, accepted_by
) VALUES (
  '${sqlQuote(offerId)}', '${sqlQuote(workspaceId)}', 19151, 'employee-browser-smoke@example.test', 200.00, 'EUR',
  'Browser smoke accountable UI', 'accepted_by_employee', 19101, NOW(), 19151
);
INSERT INTO v2_accountable_reports (
  id, workspace_id, offer_id, employee_user_id, title, status, currency, total_amount, row_count,
  submitted_at, submitted_by, created_by
) VALUES (
  '${sqlQuote(reportId)}', '${sqlQuote(workspaceId)}', '${sqlQuote(offerId)}', 19151,
  'Browser smoke employee report', 'submitted', 'EUR', 30.00, 2, NOW(), 19151, 19151
);
INSERT INTO v2_accountable_report_rows (
  id, report_id, \`row_number\`, expense_date, description, amount, currency, category_code, notes
) VALUES
  ('${sqlQuote(rowOneId)}', '${sqlQuote(reportId)}', 1, CURDATE(), 'Browser smoke taxi', 12.00, 'EUR', 'transport_expenses', 'ui smoke'),
  ('${sqlQuote(rowTwoId)}', '${sqlQuote(reportId)}', 2, CURDATE(), 'Browser smoke provisions', 18.00, 'EUR', 'provisions', 'ui smoke');
`;
  execSmokeSql(sql);

  return reportId;
}

function monthPartsFromDate(value) {
  const match = /^(\d{4})-(\d{2})-\d{2}$/.exec(value);
  assert(match, `invalid date input value: ${value}`);
  return {
    year: Number(match[1]),
    month: Number(match[2]),
  };
}

const textAliases = new Map([
  ['Attachment saved', ['Attachment saved', 'Файл сохранен']],
  ['Archive', ['Archive', 'Архив']],
  ['Category updated', ['Category updated', 'Категория обновлена']],
  ['Category updated with recalculation', ['Category updated with recalculation', 'Категория обновлена с пересчетом']],
  ['Closed', ['Closed', 'Закрыт']],
  ['closed', ['closed', 'Закрыт']],
  ['partial', ['partial', 'Частично']],
  ['Closed month', ['Closed month', 'Закрытый месяц']],
  ['Closed month change cancelled', ['Closed month change cancelled', 'Изменение закрытого месяца отменено']],
  ['Correction decision recorded', ['Correction decision recorded', 'Корректировка зафиксирована']],
  ['Create a workspace to start writing', ['Create a workspace to start writing', 'Создайте пространство, чтобы начать записи']],
  ['Current month', ['Current month', 'Текущий месяц', 'Готово']],
  ['Deferred', ['Deferred', 'Отложено']],
  ['defer', ['defer', 'Отложено']],
  ['Do not train from this row', ['Do not train from this row', 'Кнопка «Верно, запомнить» здесь недоступна']],
  ['Local rule', ['Local rule', 'Локальное правило', 'Запомнено']],
  ['Lower accounting', ['Lower accounting', 'Нижний учет', 'Деньги под отчет']],
  ['Mixed signal', ['Mixed signal', 'Если смысл смешанный']],
  ['Month closed', ['Month closed', 'Месяц закрыт']],
  ['Month reopened', ['Month reopened', 'Месяц открыт']],
  ['No attachments', ['No attachments', 'Файлов нет']],
  ['Not authenticated', ['Not authenticated', 'Нужен вход']],
  ['Offline: draft kept locally', ['Offline: draft kept locally', 'Офлайн: черновик сохранен локально']],
  ['Open', ['Open', 'Открыт']],
  ['Period result', ['Period result', 'Итог периода', 'Сводка периода', 'Финансовый результат', 'Касса администратора']],
  ['Money position', ['Money position', 'Деньги на дату отчета', 'Всего физически доступно']],
  ['Ready', ['Ready', 'Готово']],
  ['Record deleted', ['Record deleted', 'Запись удалена']],
  ['Saved', ['Saved', 'Сохранено']],
  ['Server values only', ['Server values only', 'Только серверные значения', 'Только значения сервера', 'сервер', 'Цепочка периода']],
  ['Cash flow opening balance', ['Cash flow opening balance', 'Входящий остаток']],
  ['Snapshot v1', ['Snapshot v1', 'Снимок']],
  ['basis_opening', ['basis_opening', 'Основа остатка']],
  ['source_ids', ['source_ids', 'Записей-источников']],
  ['approve_existing_guess_local', ['approve_existing_guess_local', 'Подтверждено локально', 'Запомнено как верно']],
  ['Reference: No external lookup performed', ['Reference: No external lookup performed', 'No external lookup performed']],
  ['no financial or training mutation', ['no financial or training mutation', 'без изменения финансов и обучения']],
  ['Human feedback: useful', ['Human feedback: useful', 'Оценка: useful']],
  ['Weak signal', ['Weak signal', 'Слабый сигнал', 'Слабый признак', 'Если предложение выглядит верным', 'Если категория подходит', 'Решение уже сохранено']],
  ['2 records', ['2 records', '2 записи']],
]);

function textAlternatives(text) {
  if (Array.isArray(text)) return text;
  return textAliases.get(text) || [text];
}

function textMatches(actual, expected) {
  const value = String(actual || '').trim();
  return textAlternatives(expected).some((candidate) => value === candidate);
}

function textIncludes(actual, expected) {
  const value = String(actual || '');
  return textAlternatives(expected).some((candidate) => value.includes(candidate));
}

function compactMoneyText(value) {
  return String(value || '').replace(/[^\d.,+-]/g, '').replace(',', '.');
}

function includesAmountText(actual, amount) {
  const compact = compactMoneyText(actual);
  const normalized = Number(amount).toFixed(2);
  return compact.includes(normalized) || compact.includes(normalized.replace('.00', ''));
}

const expectedEntryDetailFields = [
  ['raw_text', 'Запись'],
  ['date', 'Дата'],
  ['flow', 'Поток'],
  ['sign', 'Знак'],
  ['amount', 'Сумма'],
  ['direction', 'Направление', 'Движение'],
  ['category', 'Категория'],
  ['actor', 'Участник'],
  ['status', 'Статус'],
  ['balance_after', 'Остаток после', 'Остаток'],
];

function detailFieldLabels(label) {
  const match = expectedEntryDetailFields.find((field) => field.includes(label));
  return match || [label];
}

async function waitForText(page, selector, text) {
  const alternatives = textAlternatives(text);
  await page.waitForFunction(
    ({ selector: targetSelector, texts }) => {
      const node = document.querySelector(targetSelector);
      return Boolean(node && node.textContent && texts.some((targetText) => node.textContent.includes(targetText)));
    },
    { selector, texts: alternatives },
    { timeout: 10000 }
  );
}

async function clickViewIfVisible(page, view) {
  const tab = page.locator(`[data-v2-view="${view}"]`);
  await tab.waitFor({ state: 'attached', timeout: 10000 });
  if (await tab.isVisible()) {
    await tab.click();
  }
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

async function ensureCreateEntryMode(page) {
  const submit = page.locator('[data-v2-submit]');
  await submit.waitFor({ state: 'attached', timeout: 10000 });
  const previewing = await page.locator('[data-v2-entry-form].is-previewing').count();
  if (previewing) {
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => {
      const form = document.querySelector('[data-v2-entry-form]');
      const button = document.querySelector('[data-v2-submit]');
      return form && !form.classList.contains('is-previewing') && button && !button.hidden;
    }, null, { timeout: 5000 });
  }
  await submit.waitFor({ state: 'visible', timeout: 10000 });
  if (!textMatches(await submit.innerText(), ['Update', 'Обновить'])) return;
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-submit]');
    return button && button.textContent && ['Save', 'Сохранить'].includes(button.textContent.trim());
  }, null, { timeout: 5000 });
}

async function saveEntry(page, rawText) {
  await ensureCreateEntryMode(page);
  await page.locator('[data-v2-submit]').waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-submit]');
    return button && !button.disabled;
  });
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
  await waitForText(page, '[data-v2-status]', 'Saved');
}

async function v2BrowserApi(page, method, route, body = null, query = {}) {
  return page.evaluate(async ({ method, route, body, query }) => {
    const url = new URL('/v2-api.php', window.location.origin);
    url.searchParams.set('route', route);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-FinDesk-V2-Request': 'fetch',
      },
      body: body == null ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({ ok: false, error: 'invalid_json' }));
    return { status: response.status, data };
  }, { method, route, body, query });
}

function priorMonthDateForBrowserSmoke() {
  const now = new Date();
  const date = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - 1, 15));
  return date.toISOString().slice(0, 10);
}

async function assertHallAccountableMaterializationUi(page, workspaceId) {
  const reportId = seedHallAccountableReport(workspaceId);
  const summaryBefore = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/summary`);
  assert(summaryBefore.status === 200 && summaryBefore.data.ok === true, 'hall materialization summary before failed');

  await page.locator('[data-v2-hall-open]').click();
  await page.locator('[data-v2-hall]').waitFor({ state: 'visible', timeout: 10000 });
  const reportsResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/accountable-reports')
      && queryParamFromRequest(request, 'status') === 'hall_open';
  });
  const dashboardResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/accountable-dashboard');
  });
  await page.locator(`[data-v2-hall-accountable-open][data-v2-workspace-id="${workspaceId}"]`).click();
  assert((await dashboardResponse).status() === 200, 'hall accountable dashboard failed');
  assert((await reportsResponse).status() === 200, 'hall accountable report queue failed');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Под отчет');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Без изменения кассы и карты');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'К возврату');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Browser smoke employee report');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Принять отчет');

  const acceptResponse = page.waitForResponse((response) => (
    response.request().method() === 'POST'
    && response.url().includes('/v2-api.php')
    && routeFromRequest(response.request()) === `/api/accountable-reports/${reportId}/accept`
  ));
  await page.locator(`[data-v2-hall-report-accept="${reportId}"]`).click();
  assert((await acceptResponse).status() === 200, 'hall accountable report accept failed');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Включить в учет');

  const summaryBeforeInclude = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/summary`);
  assert(summaryBeforeInclude.status === 200 && summaryBeforeInclude.data.ok === true, 'hall materialization summary before include failed');
  const dialogPromise = page.waitForEvent('dialog');
  const previewResponse = page.waitForResponse((response) => (
    response.request().method() === 'POST'
    && response.url().includes('/v2-api.php')
    && routeFromRequest(response.request()) === `/api/accountable-reports/${reportId}/materialization-preview`
  ));
  await page.locator(`[data-v2-hall-report-materialize="${reportId}"]`).click();
  assert((await previewResponse).status() === 200, 'hall accountable materialization preview failed');
  const dialog = await dialogPromise;
  assert(dialog.message().includes('Остаток наличных и карта не изменятся'), 'hall accountable materialization confirm missing no cash/card copy');
  const materializeResponse = page.waitForResponse((response) => (
    response.request().method() === 'POST'
    && response.url().includes('/v2-api.php')
    && routeFromRequest(response.request()) === `/api/accountable-reports/${reportId}/materialize`
  ));
  await dialog.accept();
  assert((await materializeResponse).status() === 200, 'hall accountable materialization failed');
  await waitForText(page, '[data-v2-hall-workspace-list]', 'Уже в учете');

  const summaryAfter = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/summary`);
  assert(summaryAfter.status === 200 && summaryAfter.data.ok === true, 'hall materialization summary after failed');
  assert(
    Number(summaryAfter.data.summary.cash_now) === Number(summaryBeforeInclude.data.summary.cash_now),
    'hall materialization changed cash_now'
  );
  assert(
    Number(summaryAfter.data.summary.card_expense_total) === Number(summaryBeforeInclude.data.summary.card_expense_total),
    'hall materialization changed card_expense_total'
  );

  await page.locator(`[data-v2-hall-workspace-open][data-v2-workspace-id="${workspaceId}"]`).click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
}

function nextMonthPartsForBrowserSmoke(parts) {
  return parts.month === 12
    ? { year: parts.year + 1, month: 1 }
    : { year: parts.year, month: parts.month + 1 };
}

async function assertCurrentMonthCloseOpensNextPeriod(browser, workspaceId) {
  const context = await browser.newContext({ baseURL: base, viewport: { width: 1280, height: 820 } });
  await context.addCookies([{ name: cookieName, value: token, url: base }]);
  const page = await context.newPage();
  try {
    await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await waitForText(page, '[data-v2-status]', 'Ready');
    const currentDate = await page.locator('[data-v2-date]').inputValue();
    const current = monthPartsFromDate(currentDate);
    const next = nextMonthPartsForBrowserSmoke(current);
    const closeResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith(`/months/${current.year}/${current.month}/close`)
    ));
    await page.locator('[data-v2-month-toggle]').click();
    assert((await closeResponse).status() === 200, 'current month close failed');
    const expectedPrefix = String(next.year).padStart(4, '0') + '-' + String(next.month).padStart(2, '0') + '-';
    let actual = {};
    for (let attempt = 0; attempt < 50; attempt += 1) {
      actual = await page.evaluate(() => ({
        date: document.querySelector('[data-v2-date]')?.value || '',
        month: document.querySelector('[data-v2-month]')?.textContent || '',
        status: document.querySelector('[data-v2-status]')?.textContent || '',
        state: document.querySelector('[data-v2-month-state]')?.textContent || '',
      }));
      if (
        actual.date.startsWith(expectedPrefix)
        && actual.status.includes('Открыт новый месяц')
        && actual.state === 'Открыт'
      ) break;
      await page.waitForTimeout(200);
    }
    assert(actual.date.startsWith(expectedPrefix), `current close did not open next period: ${JSON.stringify({ current, next, actual })}`);
    assert(!actual.month.includes('Архив') && !actual.month.includes('Archive'), `next operational period was rendered as archive: ${JSON.stringify(actual)}`);
    assert(actual.status.includes('Открыт новый месяц'), `next operational period status missing: ${JSON.stringify(actual)}`);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    for (let attempt = 0; attempt < 50; attempt += 1) {
      actual = await page.evaluate(() => ({
        date: document.querySelector('[data-v2-date]')?.value || '',
        month: document.querySelector('[data-v2-month]')?.textContent || '',
        status: document.querySelector('[data-v2-status]')?.textContent || '',
        state: document.querySelector('[data-v2-month-state]')?.textContent || '',
      }));
      if (actual.date.startsWith(expectedPrefix) && actual.state === 'Открыт') break;
      await page.waitForTimeout(200);
    }
    assert(actual.date.startsWith(expectedPrefix), `reload after current close did not auto-open next period: ${JSON.stringify({ current, next, actual })}`);
    assert(!actual.month.includes('Архив') && !actual.month.includes('Archive'), `reload after current close rendered next period as archive: ${JSON.stringify(actual)}`);
    assert(actual.state === 'Открыт', `reload after current close did not land on open period: ${JSON.stringify(actual)}`);
  } finally {
    await context.close();
  }
}

async function assertEntryEditDelete(page) {
  const rawText = `+123 edit delete probe ${Date.now()}`;
  const editedText = `${rawText} updated`;
  await saveEntry(page, rawText);
  await page.waitForTimeout(300);

  const row = page.locator('[data-v2-entry-select]', { hasText: rawText }).first();
  await row.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  assert(await page.locator('[data-v2-entry-edit-cancel]').count() === 0, 'obsolete edit exit button is still rendered');
  assert(await page.locator('[data-v2-submit]').isHidden(), 'row click should keep Save hidden in preview mode');
  assert(await page.locator(rawTextInputSelector).inputValue() === rawText, 'preview input was not populated from row');
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  assert(textMatches(await page.locator('[data-v2-submit]').innerText(), ['Update', 'Обновить']), 'pencil click did not enter edit mode');

  await page.locator(rawTextInputSelector).fill(editedText);
  const patchResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  await page.locator('[data-v2-submit]').click();
  assert((await patchResponse).status() === 200, 'entry edit update failed');
  await waitForText(page, '[data-v2-feed]', editedText);
  await page.waitForFunction((selector) => {
    const input = document.querySelector(selector);
    const submit = document.querySelector('[data-v2-submit]');
    return input
      && document.activeElement === input
      && input.value === ''
      && submit
      && ['Save', 'Сохранить'].includes(submit.textContent.trim());
  }, rawTextInputSelector, { timeout: 5000 });

  await page.waitForTimeout(300);
  const editedRow = page.locator('[data-v2-entry-select]', { hasText: editedText }).first();
  await editedRow.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  const deleteButton = page.locator('[data-v2-entry-delete]');
  assert(textMatches(await deleteButton.innerText(), ['Del', 'Удал.']), 'delete button missing distinct delete label');
  await deleteButton.click();
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-entry-delete]');
    return button && button.textContent && ['Delete?', 'Удалить?'].includes(button.textContent.trim());
  }, null, { timeout: 5000 });
  assert(textMatches(await deleteButton.innerText(), ['Delete?', 'Удалить?']), 'delete first click did not show inline confirmation');

  const deleteResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'DELETE'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  await deleteButton.click();
  assert((await deleteResponse).status() === 200, 'entry delete failed');
  await page.waitForFunction((text) => {
    const feed = document.querySelector('[data-v2-feed]');
    return feed && !feed.textContent.includes(text);
  }, editedText, { timeout: 5000 });
  await waitForText(page, '[data-v2-status]', 'Record deleted');
}

async function assertEditTargetStableOnHover(page) {
  await ensureCreateEntryMode(page);
  const firstText = '+1000 снял с карты';
  const secondText = '-250 рыба';
  const firstRow = page.locator('[data-v2-entry-select]', { hasText: firstText }).first();
  await firstRow.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  assert(await page.locator('[data-v2-entry-edit-cancel]').count() === 0, 'obsolete edit exit button is still rendered while editing');
  assert(await page.locator(rawTextInputSelector).inputValue() === firstText, 'edit setup did not select the first row');

  await page.locator('[data-v2-entry-select]', { hasText: secondText }).first().hover();
  await page.waitForTimeout(150);
  assert(await page.locator(rawTextInputSelector).inputValue() === firstText, 'journal hover replaced the active edit target');

  await page.locator('[data-v2-check-row][data-v2-entry-id]', { hasText: secondText }).first().hover();
  await page.waitForTimeout(150);
  assert(await page.locator(rawTextInputSelector).inputValue() === firstText, 'structured hover replaced the active edit target');

  await page.locator('[data-v2-entry-select]', { hasText: secondText }).first().click();
  assert(await page.locator(rawTextInputSelector).inputValue() === firstText, 'single click replaced the active edit target');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-submit]');
    const actions = document.querySelector('[data-v2-entry-edit-actions]');
    return button && ['Save', 'Сохранить'].includes(button.textContent.trim()) && actions && actions.hidden;
  }, null, { timeout: 5000 });
}

async function assertHoverPreviewCard(page) {
  await ensureCreateEntryMode(page);
  const rawText = '-250 рыба';
  const row = page.locator('[data-v2-entry-select]', { hasText: rawText }).first();
  await row.hover();
  await page.waitForTimeout(150);
  const hoverState = await page.evaluate(() => {
    const form = document.querySelector('[data-v2-entry-form]');
    const input = document.querySelector('[data-v2-raw-text]');
    return {
      previewing: Boolean(form && form.classList.contains('is-previewing')),
      editing: Boolean(form && form.classList.contains('is-editing')),
      rawValue: input ? input.value : '',
    };
  });
  assert(!hoverState.previewing && !hoverState.editing && hoverState.rawValue === '', `hover should not populate edit input: ${JSON.stringify(hoverState)}`);
  await row.click();
  await page.waitForFunction(
    ({ selector, expected }) => {
      const form = document.querySelector('[data-v2-entry-form]');
      const input = document.querySelector(selector);
      const submit = document.querySelector('[data-v2-submit]');
      const actions = document.querySelector('[data-v2-entry-edit-actions]');
      const del = document.querySelector('[data-v2-entry-delete]');
      return form
        && form.classList.contains('is-previewing')
        && !form.classList.contains('is-editing')
        && input
        && input.value === expected
        && input.readOnly
        && submit
        && submit.hidden
        && actions
        && !actions.hidden
        && del
        && del.hidden;
    },
    { selector: rawTextInputSelector, expected: rawText },
    { timeout: 5000 }
  );
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.waitForFunction(
    ({ selector, expected }) => {
      const form = document.querySelector('[data-v2-entry-form]');
      const input = document.querySelector(selector);
      const submit = document.querySelector('[data-v2-submit]');
      const del = document.querySelector('[data-v2-entry-delete]');
      return form
        && form.classList.contains('is-editing')
        && !form.classList.contains('is-previewing')
        && input
        && input.value === expected
        && !input.readOnly
        && submit
        && !submit.hidden
        && ['Update', 'Обновить'].includes(submit.textContent.trim())
        && del
        && !del.hidden;
    },
    { selector: rawTextInputSelector, expected: rawText },
    { timeout: 5000 }
  );
  await page.keyboard.press('Escape');
  await ensureCreateEntryMode(page);
}

async function assertArchiveUnsavedGuard(page) {
  const originalText = '+1000 снял с карты';
  const editedText = '+1001 снял с карты archive guard saved';
  const row = page.locator('[data-v2-entry-select]', { hasText: originalText }).first();
  await row.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator(rawTextInputSelector).fill(editedText);

  await page.locator('[data-v2-archive-open]').click();
  await page.locator('[data-v2-archive-modal]').waitFor({ state: 'visible', timeout: 10000 });
  const priorMonth = monthPartsFromDate(priorMonthDateForBrowserSmoke());
  await page.locator('[data-v2-archive-year]').selectOption(String(priorMonth.year));
  await page.locator('[data-v2-archive-month]').selectOption(String(priorMonth.month));
  await page.locator('[data-v2-archive-load]').click();
  await page.locator('[data-v2-unsaved-guard]').waitFor({ state: 'visible', timeout: 5000 });
  assert(await page.locator(rawTextInputSelector).inputValue() === editedText, 'unsaved archive guard lost the edited draft before decision');

  await page.locator('[data-v2-unsaved-cancel]').last().click();
  await page.locator('[data-v2-unsaved-guard]').waitFor({ state: 'hidden', timeout: 5000 });
  assert(await page.locator(rawTextInputSelector).inputValue() === editedText, 'unsaved cancel did not keep editing draft');
  assert(!textIncludes(await page.locator('[data-v2-month]').innerText(), ['Archive', 'Архив']), 'unsaved cancel switched period');

  await page.locator('[data-v2-archive-load]').click();
  await page.locator('[data-v2-unsaved-guard]').waitFor({ state: 'visible', timeout: 5000 });
  const patchResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  const archiveEntriesResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/entries')
      && queryParamFromRequest(request, 'year') === String(priorMonth.year)
      && queryParamFromRequest(request, 'month') === String(priorMonth.month);
  });
  await page.locator('[data-v2-unsaved-save]').click();
  assert((await patchResponse).status() === 200, 'unsaved guard save did not persist edit before archive switch');
  assert((await archiveEntriesResponse).status() === 200, 'unsaved guard save did not continue to archive switch');
  await waitForText(page, '[data-v2-status]', 'Archive');
  assert((await page.locator('[data-v2-feed]').innerText()).includes('+42 browser prior opening source'), 'archive switch after unsaved save did not load archive entries');

  await page.locator('[data-v2-current-month]').click();
  await waitForText(page, '[data-v2-status]', 'Current month');
  await waitForText(page, '[data-v2-feed]', editedText);

  const restoredRow = page.locator('[data-v2-entry-select]', { hasText: editedText }).first();
  await restoredRow.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator(rawTextInputSelector).fill(originalText);
  const restoreResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'PATCH'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).startsWith('/api/entries/');
  });
  await page.locator('[data-v2-submit]').click();
  assert((await restoreResponse).status() === 200, 'archive guard restore edit failed');
  await waitForText(page, '[data-v2-status]', 'Запись обновлена');
  await ensureCreateEntryMode(page);
  await waitForText(page, '[data-v2-feed]', originalText);
  await page.waitForTimeout(300);

  const secondEditedText = '-251 рыба archive guard discarded';
  const secondRow = page.locator('[data-v2-entry-select]', { hasText: '-250 рыба' }).first();
  await secondRow.click();
  await page.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-entry-edit-save]').click();
  await page.locator('[data-v2-entry-form].is-editing').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator(rawTextInputSelector).fill(secondEditedText);
  await page.locator('[data-v2-archive-open]').click();
  await page.locator('[data-v2-archive-modal]').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('[data-v2-archive-load]').click();
  await page.locator('[data-v2-unsaved-guard]').waitFor({ state: 'visible', timeout: 5000 });
  await page.locator('[data-v2-unsaved-discard]').click();
  await waitForText(page, '[data-v2-status]', 'Archive');
  await page.locator('[data-v2-current-month]').click();
  await waitForText(page, '[data-v2-status]', 'Current month');
  assert((await page.locator('[data-v2-feed]').innerText()).includes('-250 рыба'), 'unsaved discard lost original current row');
  assert(!(await page.locator('[data-v2-feed]').innerText()).includes(secondEditedText), 'unsaved discard persisted edited draft');
}

async function selectEntryByText(page, rawText) {
  const row = page.locator('[data-v2-entry-select]', { hasText: rawText }).first();
  await row.click();
  const afterFirstClick = await collectLayoutMetrics(page);
  if (!afterFirstClick.detailVisible) await row.click();
  await assertContextualDetailOpen(page, rawText, `journal selection for ${rawText}`);
  return row;
}

async function detailFieldValue(page, label) {
  const labels = detailFieldLabels(label);
  const field = page.locator('[data-v2-detail-fields] div').filter({
    has: page.locator('dt').filter({ hasText: new RegExp(labels.map((item) => item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')) }),
  }).first();
  await field.waitFor({ state: 'visible', timeout: 10000 });
  return field.locator('dd').first().innerText();
}

async function rowEvidence(locator) {
  return locator.evaluate((row) => {
    const hookText = (selector) => {
      const node = row.querySelector(selector);
      if (!node) return '';
      return node.getAttribute(selector.slice(1, -1)) || node.textContent || '';
    };
    return {
      entryId: row.getAttribute('data-v2-entry-id') || '',
      rawText: row.getAttribute('data-v2-raw-text')
        || row.getAttribute('data-v2-entry-raw-text')
        || hookText('[data-v2-row-raw-text]')
        || '',
      rowNumber: row.getAttribute('data-v2-row-number')
        || hookText('[data-v2-row-number]')
        || '',
      text: row.textContent || '',
      className: row.className || '',
    };
  });
}

function checkRowLocator(page, rawText) {
  return page.locator(
    '[data-v2-check-row][data-v2-entry-id], [data-v2-check-table] .v2-check-row[data-v2-entry-id]',
    { hasText: rawText }
  ).first();
}

async function assertLinkedRows(page, rawText) {
  const journalRow = page.locator('[data-v2-entry-select][data-v2-entry-id]', { hasText: rawText }).first();
  const checkRow = checkRowLocator(page, rawText);
  await journalRow.waitFor({ state: 'attached', timeout: 10000 });
  await checkRow.waitFor({ state: 'attached', timeout: 10000 });

  const journal = await rowEvidence(journalRow);
  const check = await rowEvidence(checkRow);

  assert(journal.entryId, `journal row missing data-v2-entry-id for ${rawText}: ${JSON.stringify(journal)}`);
  assert(check.entryId, `structured check row missing data-v2-entry-id for ${rawText}: ${JSON.stringify(check)}`);
  assert(journal.entryId === check.entryId, `journal/check entry ids differ for ${rawText}: ${JSON.stringify({ journal, check })}`);
  assert(
    (journal.rawText && journal.rawText === rawText) || journal.text.includes(rawText),
    `journal row missing raw text evidence for ${rawText}: ${JSON.stringify(journal)}`
  );
  assert(
    (check.rawText && check.rawText === rawText) || check.text.includes(rawText),
    `structured check row missing raw text evidence for ${rawText}: ${JSON.stringify(check)}`
  );
  assert(journal.rowNumber, `journal row missing row number hook for ${rawText}: ${JSON.stringify(journal)}`);
  assert(check.rowNumber, `structured check row missing row number hook for ${rawText}: ${JSON.stringify(check)}`);
  assert(journal.rowNumber === check.rowNumber, `journal/check row numbers differ for ${rawText}: ${JSON.stringify({ journal, check })}`);
  assert(journal.text.includes(String(journal.rowNumber)), `journal row number is not visible for ${rawText}: ${JSON.stringify(journal)}`);
  assert(check.text.includes(String(check.rowNumber)), `structured check row number is not visible for ${rawText}: ${JSON.stringify(check)}`);

  return { journalRow, checkRow, journal, check };
}

async function assertJournalHeader(page) {
  const header = page.locator('[data-v2-journal-header]').first();
  await header.waitFor({ state: 'visible', timeout: 10000 });
  const text = await header.innerText();
  assert(text.includes('№'), `journal header missing №: ${text}`);
  assert(text.includes('Описание'), `journal header missing Описание: ${text}`);
  assert(text.includes('Сумма'), `journal header missing Сумма: ${text}`);

  const geometry = await page.evaluate(() => {
    const rect = (node) => {
      const box = node.getBoundingClientRect();
      return {
        left: box.left,
        right: box.right,
        width: box.width,
      };
    };
    const header = document.querySelector('[data-v2-journal-header]');
    const row = document.querySelector('[data-v2-entry-select][data-v2-entry-id]');
    return {
      header: Array.from(header ? header.children : []).map(rect),
      row: Array.from(row ? row.children : []).map(rect),
    };
  });
  assert(geometry.header.length === 3 && geometry.row.length === 3, `journal header/row geometry missing cells: ${JSON.stringify(geometry)}`);
  const headerNumberCenter = (geometry.header[0].left + geometry.header[0].right) / 2;
  const rowNumberCenter = (geometry.row[0].left + geometry.row[0].right) / 2;
  assert(Math.abs(headerNumberCenter - rowNumberCenter) <= 2, `journal # column misaligned: ${JSON.stringify(geometry)}`);
  assert(Math.abs(geometry.header[1].left - geometry.row[1].left) <= 2, `journal description column misaligned: ${JSON.stringify(geometry)}`);
  assert(Math.abs(geometry.header[2].right - geometry.row[2].right) <= 2, `journal amount column misaligned: ${JSON.stringify(geometry)}`);
  return { text, geometry };
}

async function assertLatestOperationalDraftRow(page, latestRawText, draftRawText, label) {
  await page.locator(rawTextInputSelector).fill(draftRawText);
  await page.waitForTimeout(200);
  const evidence = await page.evaluate(({ latestRawText, draftRawText }) => {
    const feed = document.querySelector('[data-v2-feed]');
    const activeJournal = document.querySelector('[data-v2-entry-select].is-active');
    const activeCheck = document.querySelector('[data-v2-check-row][data-v2-entry-id].is-active');
    const journalDraft = document.querySelector('[data-v2-draft-row]');
    const checkDraft = document.querySelector('[data-v2-check-draft-row]');
    const journalDraftText = document.querySelector('[data-v2-draft-text]');
    const checkDraftText = document.querySelector('[data-v2-check-draft-text]');
    const realRows = document.querySelectorAll('[data-v2-entry-select][data-v2-entry-id]');
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return { top: box.top, bottom: box.bottom, height: box.height };
    };
    return {
      latestRawText,
      draftRawText,
      realRowCount: realRows.length,
      activeJournalRaw: activeJournal ? activeJournal.getAttribute('data-v2-entry-raw-text') : '',
      activeCheckRaw: activeCheck ? activeCheck.getAttribute('data-v2-entry-raw-text') : '',
      journalDraftRowNumber: journalDraft ? journalDraft.getAttribute('data-v2-row-number') : '',
      checkDraftRowNumber: checkDraft ? checkDraft.getAttribute('data-v2-row-number') : '',
      journalDraftText: journalDraftText ? journalDraftText.textContent : '',
      checkDraftText: checkDraftText ? checkDraftText.textContent : '',
      feedScrollTop: feed ? feed.scrollTop : 0,
      feedMaxScrollTop: feed ? feed.scrollHeight - feed.clientHeight : 0,
      journalDraft: rect(journalDraft),
      checkDraft: rect(checkDraft),
      feed: rect(feed),
    };
  }, { latestRawText, draftRawText });

  assert(evidence.activeJournalRaw === latestRawText, `${label} latest journal row is not active: ${JSON.stringify(evidence)}`);
  assert(evidence.activeCheckRaw === latestRawText, `${label} latest structured row is not active: ${JSON.stringify(evidence)}`);
  assert(evidence.journalDraftRowNumber === String(evidence.realRowCount + 1), `${label} journal draft row number is wrong: ${JSON.stringify(evidence)}`);
  assert(evidence.checkDraftRowNumber === String(evidence.realRowCount + 1), `${label} structured draft row number is wrong: ${JSON.stringify(evidence)}`);
  assert(evidence.journalDraftText === draftRawText, `${label} journal draft row does not mirror input: ${JSON.stringify(evidence)}`);
  assert(evidence.checkDraftText === draftRawText, `${label} structured draft row does not mirror input: ${JSON.stringify(evidence)}`);
  assert(evidence.feedScrollTop >= evidence.feedMaxScrollTop - 2, `${label} operational journal did not stay at bottom: ${JSON.stringify(evidence)}`);

  await page.locator(rawTextInputSelector).fill('');
  await page.waitForTimeout(100);
  layoutEvidence.push({ label, kind: 'latest-operational-draft-row', evidence });
  return evidence;
}

async function assertHeaderAndFirstRowAlignment(page, label) {
  const metrics = await page.evaluate(() => {
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return {
        top: box.top,
        bottom: box.bottom,
        height: box.height,
        left: box.left,
        right: box.right,
      };
    };
    const firstVisible = (selector) => Array.from(document.querySelectorAll(selector)).find((node) => {
      const box = node.getBoundingClientRect();
      return box.width > 0 && box.height > 0 && box.bottom > 0 && box.top < window.innerHeight;
    });
    return {
      journalHeader: rect(document.querySelector('[data-v2-journal-header]')),
      checkHeader: rect(document.querySelector('[data-v2-check-header]')),
      journalFirstRow: rect(firstVisible('[data-v2-entry-select][data-v2-entry-id]')),
      checkFirstRow: rect(firstVisible('[data-v2-check-row][data-v2-entry-id]')),
    };
  });

  assert(metrics.journalHeader && metrics.checkHeader, `${label} missing header geometry: ${JSON.stringify(metrics)}`);
  assert(metrics.journalFirstRow && metrics.checkFirstRow, `${label} missing first row geometry: ${JSON.stringify(metrics)}`);
  assert(Math.abs(metrics.journalHeader.top - metrics.checkHeader.top) <= 1, `${label} journal/check header tops differ: ${JSON.stringify(metrics)}`);
  assert(Math.abs(metrics.journalHeader.height - metrics.checkHeader.height) <= 1, `${label} journal/check header heights differ: ${JSON.stringify(metrics)}`);
  assert(Math.abs(metrics.journalFirstRow.top - metrics.checkFirstRow.top) <= 1, `${label} first visible data row tops differ: ${JSON.stringify(metrics)}`);

  layoutEvidence.push({ label, kind: 'header-first-row-alignment', metrics });
  return metrics;
}

async function rowSyncGeometry(page, rawText) {
  const { journalRow, checkRow, journal, check } = await assertLinkedRows(page, rawText);
  return {
    journal,
    check,
    geometry: await page.evaluate(({ journalSelector, checkSelector }) => {
      const rowMetrics = (selector) => {
        const node = document.querySelector(selector);
        const box = node.getBoundingClientRect();
        return {
          entryId: node.getAttribute('data-v2-entry-id') || '',
          rowNumber: node.getAttribute('data-v2-row-number') || '',
          height: box.height,
          top: box.top,
          bottom: box.bottom,
          text: node.textContent || '',
        };
      };
      return {
        journal: rowMetrics(journalSelector),
        check: rowMetrics(checkSelector),
      };
    }, {
      journalSelector: `[data-v2-entry-select][data-v2-entry-id="${journal.entryId}"]`,
      checkSelector: `[data-v2-check-row][data-v2-entry-id="${check.entryId}"]`,
    }),
  };
}

async function assertRowHeightSync(page, rawText, label) {
  const evidence = await rowSyncGeometry(page, rawText);
  const { journal, check } = evidence.geometry;
  assert(journal.entryId === check.entryId, `${label} entry id mismatch: ${JSON.stringify(evidence)}`);
  assert(journal.rowNumber === check.rowNumber, `${label} row number mismatch: ${JSON.stringify(evidence)}`);
  assert(Math.abs(journal.height - check.height) <= 1, `${label} row heights differ: ${JSON.stringify(evidence)}`);
  assert(Math.abs(journal.top - check.top) <= 1, `${label} row tops differ: ${JSON.stringify(evidence)}`);
  layoutEvidence.push({
    label,
    kind: 'row-height-sync',
    rawText,
    evidence,
  });
  return evidence;
}

async function assertContextualDetailOpen(page, rawText, label) {
  await waitForText(page, '[data-v2-detail-raw]', rawText);
  await waitForText(page, '[data-v2-entry-detail-body]', rawText);
  const metrics = await collectLayoutMetrics(page);
  assert(metrics.detail, `${label} detail node missing: ${JSON.stringify(metrics)}`);
  assert(metrics.detailVisible, `${label} detail did not open contextually: ${JSON.stringify(metrics)}`);
  assert(!metrics.detailInsideHorizontal, `${label} detail is still a permanent horizontal panel: ${JSON.stringify(metrics)}`);
  return metrics;
}

async function closeDetailAndAssertPreserved(page, rawText, expectedDraft, label) {
  const close = page.locator(
    '[data-v2-detail-close], [data-v2-entry-detail-close], button:not([data-v2-entry-detail-backdrop])[aria-label="Close entry details"], button:not([data-v2-entry-detail-backdrop])[aria-label="Close details"]'
  ).first();
  assert((await close.count()) > 0, `${label} missing contextual detail close control`);
  await close.click();
  await page.waitForFunction(() => {
    const detail = document.querySelector('[data-v2-entry-detail]');
    if (!detail) return true;
    const style = getComputedStyle(detail);
    const rect = detail.getBoundingClientRect();
    const visible = !detail.hidden
      && detail.getAttribute('aria-hidden') !== 'true'
      && style.display !== 'none'
      && style.visibility !== 'hidden'
      && rect.width > 0
      && rect.height > 0;
    return !visible;
  }, null, { timeout: 10000 });

  const selectedRows = page.locator('[data-v2-entry-select].is-selected', { hasText: rawText });
  assert((await selectedRows.count()) >= 1, `${label} close did not preserve selected journal row for ${rawText}`);
  assert(await page.locator(rawTextInputSelector).inputValue() === expectedDraft, `${label} close did not preserve draft input`);
}

async function closeDetailIfOpen(page, label) {
  const metrics = await collectLayoutMetrics(page);
  if (!metrics.detailVisible) return;
  const close = page.locator(
    '[data-v2-detail-close], [data-v2-entry-detail-close], button:not([data-v2-entry-detail-backdrop])[aria-label="Close entry details"], button:not([data-v2-entry-detail-backdrop])[aria-label="Close details"]'
  ).first();
  assert((await close.count()) > 0, `${label} missing contextual detail close control`);
  await close.click();
  await page.waitForFunction(() => {
    const detail = document.querySelector('[data-v2-entry-detail]');
    if (!detail) return true;
    const style = getComputedStyle(detail);
    const rect = detail.getBoundingClientRect();
    const visible = !detail.hidden
      && detail.getAttribute('aria-hidden') !== 'true'
      && style.display !== 'none'
      && style.visibility !== 'hidden'
      && rect.width > 0
      && rect.height > 0;
    return !visible;
  }, null, { timeout: 10000 });
}

async function selectCheckRowByText(page, rawText) {
  const { checkRow, check } = await assertLinkedRows(page, rawText);
  await checkRow.scrollIntoViewIfNeeded();
  await checkRow.click();
  const afterFirstClick = await collectLayoutMetrics(page);
  if (!afterFirstClick.detailVisible) await checkRow.click();
  await assertContextualDetailOpen(page, rawText, 'structured check row selection');
  const selectedJournal = page.locator(`[data-v2-entry-select][data-v2-entry-id="${check.entryId}"].is-selected`);
  assert((await selectedJournal.count()) === 1, `structured check selection did not highlight matching journal row for ${rawText}`);
  const selectedCheck = page.locator(`[data-v2-check-row][data-v2-entry-id="${check.entryId}"].is-selected, [data-v2-check-table] .v2-check-row[data-v2-entry-id="${check.entryId}"].is-selected`);
  assert((await selectedCheck.count()) === 1, `structured check row missing selected state for ${rawText}`);
  return checkRow;
}

async function assertInactiveSurfaceFirstClickFocusesOnly(page, rawText) {
  await closeDetailIfOpen(page, 'inactive surface first click setup');
  const journalRow = page.locator('[data-v2-entry-select]', { hasText: rawText }).first();
  const checkRow = page.locator('[data-v2-check-row][data-v2-entry-id]', { hasText: rawText }).first();
  await journalRow.focus();
  await page.waitForTimeout(100);
  const before = await collectLayoutMetrics(page);
  assert(before.workspaceJournalFocused, `first click setup did not focus journal: ${JSON.stringify(before)}`);

  await checkRow.click();
  await page.waitForTimeout(150);
  const afterFirstClick = await collectLayoutMetrics(page);
  assert(!afterFirstClick.detailVisible, `first click on inactive structured opened detail: ${JSON.stringify(afterFirstClick)}`);
  assert(afterFirstClick.workspaceCheckFocused, `first click on inactive structured did not focus structured: ${JSON.stringify(afterFirstClick)}`);
  assert(afterFirstClick.journalActiveEntryId === afterFirstClick.checkActiveEntryId, `first click did not keep linked active row: ${JSON.stringify(afterFirstClick)}`);
  assert(
    Math.abs(afterFirstClick.journalHeader.right - afterFirstClick.feed.right) <= 1,
    `inactive journal header does not align with its table body: ${JSON.stringify(afterFirstClick)}`
  );
  const afterFirstClickRowSync = await assertRowHeightSync(page, rawText, 'inactive structured first click row sync');

  await checkRow.click();
  await assertContextualDetailOpen(page, rawText, 'second click on active structured row');
  await closeDetailIfOpen(page, 'inactive surface second click cleanup');

  layoutEvidence.push({
    label: 'inactive surface first click focuses only',
    before,
    afterFirstClick,
    afterFirstClickRowSync,
  });
}

async function assertDesktopCheckFocus(page) {
  const before = await collectLayoutMetrics(page);
  assert(before.horizontalDisplay === 'grid', `desktop check focus requires grid layout before focus: ${JSON.stringify(before)}`);
  assert(before.writing && before.check, `desktop check focus missing writing/check panels before focus: ${JSON.stringify(before)}`);

  await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(300);

  const after = await collectLayoutMetrics(page);
  assert(after.horizontalDisplay === 'grid', `desktop check focus must stay in grid layout: ${JSON.stringify(after)}`);
  assert(after.bodyOverflow === 'hidden' && after.htmlOverflow === 'hidden', `desktop check focus must not create page scroll: ${JSON.stringify(after)}`);
  assert(after.bodyScrollWidth <= after.width + 2, `desktop check focus body overhangs viewport: ${JSON.stringify(after)}`);
  assert(after.htmlScrollWidth <= after.width + 2, `desktop check focus document overhangs viewport: ${JSON.stringify(after)}`);
  assert(after.writing.width < before.writing.width * 0.82, `desktop check focus did not compact journal: ${JSON.stringify({ before, after })}`);
  assert(after.check.width > before.check.width * 1.08, `desktop check focus did not expand structured check: ${JSON.stringify({ before, after })}`);
  return { before, after };
}

async function assertStructuredHorizontalHeaderSync(page) {
  await closeDetailIfOpen(page, 'structured horizontal header sync setup');
  await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(250);

  const evidence = await page.evaluate(() => {
    const table = document.querySelector('[data-v2-check-table]');
    const header = document.querySelector('[data-v2-check-header]');
    const row = document.querySelector('[data-v2-check-row][data-v2-entry-id]');
    if (!table || !header || !row) return { missing: true };

    table.scrollLeft = Math.min(180, Math.max(0, table.scrollWidth - table.clientWidth));
    table.dispatchEvent(new Event('scroll', { bubbles: true }));

    const cellRect = (node, index) => {
      const cell = node.querySelectorAll('span')[index];
      if (!cell) return null;
      const rect = cell.getBoundingClientRect();
      return {
        left: rect.left,
        right: rect.right,
        width: rect.width,
        text: (cell.textContent || '').trim(),
      };
    };

    return {
      scrollLeft: table.scrollLeft,
      scrollWidth: table.scrollWidth,
      clientWidth: table.clientWidth,
      headerTransform: getComputedStyle(header).transform,
      headerRaw: cellRect(header, 2),
      rowRaw: cellRect(row, 2),
      headerAmount: cellRect(header, 5),
      rowAmount: cellRect(row, 5),
    };
  });

  assert(!evidence.missing, `structured horizontal sync missing nodes: ${JSON.stringify(evidence)}`);
  assert(evidence.scrollWidth > evidence.clientWidth, `structured table has no horizontal overflow to validate: ${JSON.stringify(evidence)}`);
  assert(evidence.scrollLeft > 0, `structured horizontal scroll did not move: ${JSON.stringify(evidence)}`);
  assert(evidence.headerTransform !== 'none', `structured header did not follow horizontal scroll: ${JSON.stringify(evidence)}`);
  assert(
    Math.abs(evidence.headerRaw.left - evidence.rowRaw.left) <= 1,
    `structured raw-text header/row X positions diverged: ${JSON.stringify(evidence)}`
  );
  assert(
    Math.abs(evidence.headerAmount.left - evidence.rowAmount.left) <= 1,
    `structured amount header/row X positions diverged: ${JSON.stringify(evidence)}`
  );

  return evidence;
}

async function assertBottomScrollRowSync(page, rawText, label, sourceSurface = 'journal') {
  await closeDetailIfOpen(page, `${label} setup`);
  if (sourceSurface === 'check') {
    await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
  } else {
    await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
  }
  await page.waitForTimeout(150);

  const evidence = await page.evaluate(async ({ text, sourceSurface }) => {
    const feed = document.querySelector('[data-v2-feed]');
    const checkTable = document.querySelector('[data-v2-check-table]');
    if (!feed || !checkTable) return { missing: true };

    const source = sourceSurface === 'check' ? checkTable : feed;
    source.scrollTop = source.scrollHeight;
    source.dispatchEvent(new Event('scroll', { bubbles: true }));
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    const rowByText = (selector) => Array.from(document.querySelectorAll(selector)).find((node) => {
      const rawText = node.getAttribute('data-v2-raw-text')
        || node.getAttribute('data-v2-entry-raw-text')
        || node.textContent
        || '';
      return rawText.includes(text);
    });
    const visibleRows = (selector) => Array.from(document.querySelectorAll(selector))
      .filter((node) => {
        const box = node.getBoundingClientRect();
        return box.height > 0 && box.bottom > 0 && box.top < window.innerHeight;
      })
      .map((node) => node.getAttribute('data-v2-row-number') || '');
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return {
        top: box.top,
        bottom: box.bottom,
        height: box.height,
        rowNumber: node.getAttribute('data-v2-row-number') || '',
        entryId: node.getAttribute('data-v2-entry-id') || '',
        text: node.textContent || '',
      };
    };

    const journalRow = rowByText('[data-v2-entry-select][data-v2-entry-id]');
    const checkRow = rowByText('[data-v2-check-row][data-v2-entry-id]');
    return {
      feedScrollTop: feed.scrollTop,
      checkScrollTop: checkTable.scrollTop,
      feedMaxScrollTop: feed.scrollHeight - feed.clientHeight,
      checkMaxScrollTop: checkTable.scrollHeight - checkTable.clientHeight,
      journalVisibleRows: visibleRows('[data-v2-entry-select][data-v2-entry-id]'),
      checkVisibleRows: visibleRows('[data-v2-check-row][data-v2-entry-id]'),
      journal: rect(journalRow),
      check: rect(checkRow),
    };
  }, { text: rawText, sourceSurface });

  assert(!evidence.missing, `${label} missing scroll containers: ${JSON.stringify(evidence)}`);
  assert(evidence.journal && evidence.check, `${label} missing target rows: ${JSON.stringify(evidence)}`);
  assert(evidence.journal.entryId === evidence.check.entryId, `${label} entry id mismatch: ${JSON.stringify(evidence)}`);
  assert(evidence.journal.rowNumber === evidence.check.rowNumber, `${label} row number mismatch: ${JSON.stringify(evidence)}`);
  assert(Math.abs(evidence.journal.top - evidence.check.top) <= 1, `${label} row tops differ at bottom: ${JSON.stringify(evidence)}`);
  assert(Math.abs(evidence.journal.bottom - evidence.check.bottom) <= 1, `${label} row bottoms differ at bottom: ${JSON.stringify(evidence)}`);
  assert(
    evidence.journalVisibleRows.join(',') === evidence.checkVisibleRows.join(','),
    `${label} visible row windows diverged: ${JSON.stringify(evidence)}`
  );

  layoutEvidence.push({
    label,
    kind: 'bottom-scroll-row-sync',
    rawText,
    sourceSurface,
    evidence,
  });
  return evidence;
}

async function assertDesktopJournalFocus(page) {
  await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(300);
  const before = await collectLayoutMetrics(page);
  assert(before.horizontalDisplay === 'grid', `desktop journal focus requires grid layout before focus: ${JSON.stringify(before)}`);
  assert(before.workspaceCheckFocused, `desktop journal focus setup did not enter check focus first: ${JSON.stringify(before)}`);
  await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(300);
  const after = await collectLayoutMetrics(page);
  assert(after.horizontalDisplay === 'grid', `desktop journal focus must stay in grid layout: ${JSON.stringify(after)}`);
  assert(after.bodyOverflow === 'hidden' && after.htmlOverflow === 'hidden', `desktop journal focus must not create page scroll: ${JSON.stringify(after)}`);
  assert(after.workspaceJournalFocused, `desktop journal focus did not enter journal mode: ${JSON.stringify(after)}`);
  assert(after.check.width < before.check.width * 0.82, `desktop journal focus did not compact structured check: ${JSON.stringify({ before, after })}`);
  assert(after.writing.width > before.writing.width * 1.08, `desktop journal focus did not expand journal: ${JSON.stringify({ before, after })}`);
  return { before, after };
}

async function assertHoverDoesNotOpenDetail(page, rawText) {
  await closeDetailIfOpen(page, 'hover negative precondition');
  await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(150);
  const before = await collectLayoutMetrics(page);
  assert(before.activeElementSurface === 'journal' && before.workspaceJournalFocused, `hover setup did not focus journal: ${JSON.stringify(before)}`);

  await page.locator('[data-v2-entry-select]', { hasText: rawText }).first().hover();
  await page.waitForTimeout(150);
  let metrics = await collectLayoutMetrics(page);
  assert(!metrics.detailVisible, `journal hover opened detail: ${JSON.stringify({ before, metrics })}`);
  assert(metrics.workspaceJournalFocused && !metrics.workspaceCheckFocused, `journal hover changed focused surface: ${JSON.stringify({ before, metrics })}`);
  assert(metrics.activeElementSurface === before.activeElementSurface, `journal hover moved DOM focus: ${JSON.stringify({ before, metrics })}`);
  assert(metrics.journalActiveEntryId === before.journalActiveEntryId, `journal hover changed active row: ${JSON.stringify({ before, metrics })}`);

  await page.locator('[data-v2-check-row][data-v2-entry-id]', { hasText: rawText }).first().hover();
  await page.waitForTimeout(150);
  metrics = await collectLayoutMetrics(page);
  assert(!metrics.detailVisible, `structured check hover opened detail: ${JSON.stringify(metrics)}`);
  assert(metrics.workspaceJournalFocused && !metrics.workspaceCheckFocused, `check hover changed focused surface: ${JSON.stringify({ before, metrics })}`);
  assert(metrics.activeElementSurface === before.activeElementSurface, `check hover moved DOM focus: ${JSON.stringify({ before, metrics })}`);
  assert(metrics.journalActiveEntryId === before.journalActiveEntryId, `check hover changed active row: ${JSON.stringify({ before, metrics })}`);

  await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(150);
  const checkBefore = await collectLayoutMetrics(page);
  assert(checkBefore.activeElementSurface === 'check' && checkBefore.workspaceCheckFocused, `hover setup did not focus check: ${JSON.stringify(checkBefore)}`);

  await page.locator('[data-v2-entry-select]', { hasText: rawText }).first().hover();
  await page.waitForTimeout(150);
  metrics = await collectLayoutMetrics(page);
  assert(!metrics.detailVisible, `journal hover from check focus opened detail: ${JSON.stringify(metrics)}`);
  assert(metrics.workspaceCheckFocused && !metrics.workspaceJournalFocused, `journal hover changed check-focused surface: ${JSON.stringify({ checkBefore, metrics })}`);
  assert(metrics.activeElementSurface === checkBefore.activeElementSurface, `journal hover from check moved DOM focus: ${JSON.stringify({ checkBefore, metrics })}`);
  assert(metrics.checkActiveEntryId === checkBefore.checkActiveEntryId, `journal hover from check changed active row: ${JSON.stringify({ checkBefore, metrics })}`);
  return metrics;
}

async function activeRowRawText(page, surface) {
  const selector = surface === 'check'
    ? '[data-v2-check-row][data-v2-entry-id].is-active'
    : '[data-v2-entry-select].is-active';
  return page.locator(selector).first().getAttribute('data-v2-entry-raw-text');
}

async function assertKeyboardNavigation(page, surface) {
  await closeDetailIfOpen(page, `${surface} keyboard precondition`);
  const firstSelector = surface === 'check'
    ? '[data-v2-check-row][data-v2-entry-id]'
    : '[data-v2-entry-select][data-v2-entry-id]';
  await page.locator(firstSelector).first().focus();
  await page.waitForTimeout(150);
  const before = await collectLayoutMetrics(page);
  assert(before.activeElementSurface === surface, `${surface} keyboard did not focus row surface: ${JSON.stringify(before)}`);
  assert(before.journalActiveCount === 1 && before.checkActiveCount === 1, `${surface} keyboard initial active row counts wrong: ${JSON.stringify(before)}`);
  assert(before.journalActiveEntryId === before.checkActiveEntryId, `${surface} keyboard initial active row not synced: ${JSON.stringify(before)}`);

  await page.keyboard.press('ArrowDown');
  await page.waitForTimeout(150);
  const afterDown = await collectLayoutMetrics(page);
  assert(!afterDown.detailVisible, `${surface} ArrowDown opened details: ${JSON.stringify(afterDown)}`);
  assert(afterDown.activeElementSurface === surface, `${surface} ArrowDown lost row focus: ${JSON.stringify(afterDown)}`);
  assert(afterDown.activeElementEntryId !== before.activeElementEntryId, `${surface} ArrowDown did not move active row: ${JSON.stringify({ before, afterDown })}`);
  assert(afterDown.journalActiveCount === 1 && afterDown.checkActiveCount === 1, `${surface} ArrowDown active row counts wrong: ${JSON.stringify(afterDown)}`);
  assert(afterDown.journalActiveEntryId === afterDown.checkActiveEntryId, `${surface} ArrowDown active row not synced: ${JSON.stringify(afterDown)}`);

  await page.keyboard.press('ArrowUp');
  await page.waitForTimeout(150);
  const afterUp = await collectLayoutMetrics(page);
  assert(afterUp.activeElementSurface === surface, `${surface} ArrowUp lost row focus: ${JSON.stringify(afterUp)}`);
  assert(afterUp.activeElementEntryId === before.activeElementEntryId, `${surface} ArrowUp did not restore active row: ${JSON.stringify({ before, afterUp })}`);
  assert(afterUp.journalActiveEntryId === afterUp.checkActiveEntryId, `${surface} ArrowUp active row not synced: ${JSON.stringify(afterUp)}`);

  await page.keyboard.press('ArrowDown');
  await page.waitForTimeout(150);
  const expectedRawText = await activeRowRawText(page, surface);
  await page.keyboard.press('Enter');
  await assertContextualDetailOpen(page, expectedRawText, `${surface} keyboard Enter`);
  const opened = await collectLayoutMetrics(page);
  assert(opened.journalSelectedEntryId === opened.checkSelectedEntryId, `${surface} keyboard Enter selected rows not synced: ${JSON.stringify(opened)}`);

  await page.keyboard.press('Escape');
  await page.waitForTimeout(150);
  const closed = await collectLayoutMetrics(page);
  assert(!closed.detailVisible, `${surface} Escape did not close detail: ${JSON.stringify(closed)}`);
  assert(closed.activeElementSurface === surface, `${surface} Escape did not restore focus to originating surface: ${JSON.stringify(closed)}`);
  assert(closed.journalActiveEntryId === closed.checkActiveEntryId, `${surface} Escape active row not synced: ${JSON.stringify(closed)}`);
  assert(closed.activeElementEntryId === opened.journalSelectedEntryId, `${surface} Escape restored focus to wrong row: ${JSON.stringify({ opened, closed })}`);
  assert(closed.journalSelectedEntryId === closed.checkSelectedEntryId, `${surface} Escape selected rows not synced: ${JSON.stringify(closed)}`);
  return { before, afterDown, afterUp, opened, closed };
}

async function assertArrowLeftRightSurfaceSwitch(page) {
  await closeDetailIfOpen(page, 'left/right keyboard precondition');

  await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
  await page.waitForTimeout(150);
  const before = await collectLayoutMetrics(page);
  assert(before.activeElementSurface === 'journal', `ArrowRight setup did not focus journal: ${JSON.stringify(before)}`);

  await page.keyboard.press('ArrowRight');
  await page.waitForTimeout(150);
  const afterRight = await collectLayoutMetrics(page);
  assert(!afterRight.detailVisible, `ArrowRight opened detail: ${JSON.stringify(afterRight)}`);
  assert(afterRight.activeElementSurface === 'check', `ArrowRight did not move focus to check: ${JSON.stringify({ before, afterRight })}`);
  assert(afterRight.activeElementEntryId === before.activeElementEntryId, `ArrowRight changed active row: ${JSON.stringify({ before, afterRight })}`);
  assert(afterRight.journalActiveEntryId === afterRight.checkActiveEntryId, `ArrowRight unsynced active rows: ${JSON.stringify(afterRight)}`);

  await page.keyboard.press('ArrowLeft');
  await page.waitForTimeout(150);
  const afterLeft = await collectLayoutMetrics(page);
  assert(!afterLeft.detailVisible, `ArrowLeft opened detail: ${JSON.stringify(afterLeft)}`);
  assert(afterLeft.activeElementSurface === 'journal', `ArrowLeft did not move focus to journal: ${JSON.stringify({ afterRight, afterLeft })}`);
  assert(afterLeft.activeElementEntryId === before.activeElementEntryId, `ArrowLeft changed active row: ${JSON.stringify({ before, afterLeft })}`);
  assert(afterLeft.journalActiveEntryId === afterLeft.checkActiveEntryId, `ArrowLeft unsynced active rows: ${JSON.stringify(afterLeft)}`);

  return { before, afterRight, afterLeft };
}

async function collectLayoutMetrics(page) {
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
    const horizontal = style('.v2-horizontal');
    const firstPanel = style('.v2-panel');
    const feedRect = rect('[data-v2-feed]');
    const workspaceRect = rect('[data-v2-workspace]');
    const summary = document.querySelector('[data-v2-summary]');
    const detail = document.querySelector('[data-v2-entry-detail]');
    const detailStyle = detail ? getComputedStyle(detail) : null;
    const detailRect = detail ? detail.getBoundingClientRect() : null;
    const activeElement = document.activeElement;
    const activeRow = (selector) => document.querySelector(selector + '.is-active');
    const selectedRow = (selector) => document.querySelector(selector + '.is-selected');
    const rowId = (node) => node ? (node.getAttribute('data-v2-entry-id') || '') : '';
    const activeSurface = activeElement && activeElement.matches && activeElement.matches('[data-v2-entry-select]')
      ? 'journal'
      : (activeElement && activeElement.matches && activeElement.matches('[data-v2-check-row][data-v2-entry-id]') ? 'check' : '');
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
      }));
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      shell: rect('[data-v2-app]'),
      topbar: rect('.v2-topbar'),
      brand: rect('.v2-brand'),
      summary: rect('[data-v2-summary]'),
      workspaceControls: rect('.v2-workspace-controls'),
      workspace: workspaceRect,
      rail: rect('.v2-rail'),
      flowFirst: rect('.v2-flow'),
      tabs: rect('.v2-mobile-tabs'),
      horizontal: rect('.v2-horizontal'),
      writing: rect('[data-v2-writing]'),
      journalHeader: rect('[data-v2-journal-header]'),
      detail: rect('[data-v2-entry-detail]'),
      check: rect('[data-v2-check]'),
      checkHeader: rect('[data-v2-check-header]'),
      feed: feedRect,
      inputbar: rect('[data-v2-entry-form]'),
      submit: rect('[data-v2-submit]'),
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyScrollHeight: document.body.scrollHeight,
      htmlScrollHeight: document.documentElement.scrollHeight,
      documentScrollTop: document.scrollingElement ? document.scrollingElement.scrollTop : 0,
      horizontalDisplay: horizontal && horizontal.display,
      horizontalOverflowX: horizontal && horizontal.overflowX,
      horizontalScrollLeft: document.querySelector('.v2-horizontal')?.scrollLeft || 0,
      horizontalGridTemplateAreas: horizontal && horizontal.gridTemplateAreas,
      panelFlexBasis: firstPanel && firstPanel.flexBasis,
      feedOverflowY: style('[data-v2-feed]')?.overflowY || '',
      detailOverflowY: style('[data-v2-entry-detail-body]')?.overflowY || '',
      checkOverflow: style('[data-v2-check-table]')?.overflow || '',
      summaryOverflowX: style('[data-v2-summary]')?.overflowX || '',
      detailTabDisplay: style('[data-v2-view="detail"]')?.display || '',
      visibleViewTabs,
      activeElementTag: activeElement ? activeElement.tagName : '',
      activeElementEntryId: rowId(activeElement),
      activeElementSurface: activeSurface,
      journalActiveEntryId: rowId(activeRow('[data-v2-entry-select]')),
      checkActiveEntryId: rowId(activeRow('[data-v2-check-row][data-v2-entry-id]')),
      journalSelectedEntryId: rowId(selectedRow('[data-v2-entry-select]')),
      checkSelectedEntryId: rowId(selectedRow('[data-v2-check-row][data-v2-entry-id]')),
      journalActiveCount: document.querySelectorAll('[data-v2-entry-select].is-active').length,
      checkActiveCount: document.querySelectorAll('[data-v2-check-row][data-v2-entry-id].is-active').length,
      workspaceCheckFocused: document.querySelector('[data-v2-workspace]')?.classList.contains('is-check-focused') || false,
      workspaceJournalFocused: document.querySelector('[data-v2-workspace]')?.classList.contains('is-journal-focused') || false,
      detailInsideHorizontal: Boolean(detail && detail.closest('.v2-horizontal')),
      detailDisplay: detailStyle?.display || '',
      detailPosition: detailStyle?.position || '',
      detailVisibility: detailStyle?.visibility || '',
      detailAriaHidden: detail ? detail.getAttribute('aria-hidden') : null,
      detailHidden: detail ? detail.hidden : null,
      detailVisible: Boolean(detail && detailRect && !detail.hidden
        && detail.getAttribute('aria-hidden') !== 'true'
        && detailStyle.display !== 'none'
        && detailStyle.visibility !== 'hidden'
        && detailRect.width > 0
        && detailRect.height > 0),
      visibleJournalRatio: workspaceRect && feedRect ? feedRect.height / Math.max(workspaceRect.height, 1) : 0,
      summaryScrollWidth: summary ? summary.scrollWidth : 0,
      summaryClientWidth: summary ? summary.clientWidth : 0,
    };
  });
}

async function assertNoPageScroll(page) {
  const metrics = await collectLayoutMetrics(page);

  assert(metrics.bodyOverflow === 'hidden', `body overflow must be hidden, got ${metrics.bodyOverflow}`);
  assert(metrics.htmlOverflow === 'hidden', `html overflow must be hidden, got ${metrics.htmlOverflow}`);
  assert(metrics.shell.bottom <= metrics.height + 2, `shell exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `body overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `document overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.feedOverflowY === 'auto', `feed must own vertical scroll, got ${metrics.feedOverflowY}`);
  assert(metrics.horizontalDisplay === 'grid', `desktop write/check area must be side-by-side grid, got ${metrics.horizontalDisplay}`);
  assert(!metrics.detailInsideHorizontal, `entry detail must be contextual, not a horizontal workspace panel: ${JSON.stringify(metrics)}`);
  assertTopbarSummaryPlacement(metrics, 'desktop');
  assert(metrics.summaryScrollWidth <= metrics.summaryClientWidth + 2, `desktop summary figures overflow their track: ${JSON.stringify(metrics)}`);
  assert(Math.abs(metrics.flowFirst.top - metrics.journalHeader.top) <= 1, `desktop Cash/Card rail is not aligned to table top: ${JSON.stringify(metrics)}`);
  return metrics;
}

function assertTopbarSummaryPlacement(metrics, label) {
  const summaryIsSecondRow = metrics.summary.top >= metrics.brand.bottom - 2
    && metrics.summary.top >= metrics.workspaceControls.bottom - 2;
  if (!summaryIsSecondRow) {
    assert(metrics.brand.right <= metrics.summary.left + 2, `${label} topbar brand overlaps figures: ${JSON.stringify(metrics)}`);
    assert(metrics.summary.right <= metrics.workspaceControls.left + 2, `${label} topbar figures overlap workspace selector: ${JSON.stringify(metrics)}`);
    return;
  }
  assert(metrics.summary.left >= metrics.topbar.left - 2, `${label} topbar figures escape left edge: ${JSON.stringify(metrics)}`);
  assert(metrics.summary.right <= metrics.topbar.right + 2, `${label} topbar figures escape right edge: ${JSON.stringify(metrics)}`);
}

async function assertViewportLayout(browser, workspaceId, viewport, expected, label, screenshotName = null) {
  const context = await browser.newContext({
    baseURL: base,
    viewport,
    isMobile: expected === 'phone' || expected === 'mini',
    hasTouch: expected === 'phone' || expected === 'mini',
  });
  await context.addCookies([{ name: cookieName, value: token, url: base }]);
  const page = await context.newPage();
  await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-status]', 'Ready');
  if (expected === 'phone') {
    await enableMobileFinanceMode(page);
    await clickViewIfVisible(page, 'write');
    await page.waitForTimeout(200);
  }
  const metrics = await collectLayoutMetrics(page);
  assert(metrics.bodyOverflow === 'hidden', `${label} body overflow must stay hidden: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlOverflow === 'hidden', `${label} html overflow must stay hidden: ${JSON.stringify(metrics)}`);
  assert(metrics.shell.bottom <= metrics.height + 2, `${label} shell exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `${label} body overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `${label} document overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.feedOverflowY === 'auto', `${label} feed must own vertical scroll: ${JSON.stringify(metrics)}`);
  assert(metrics.inputbar.bottom <= metrics.height + 2, `${label} input hidden below viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.submit.bottom <= metrics.height + 2, `${label} submit hidden below viewport: ${JSON.stringify(metrics)}`);
  assert(!metrics.detailInsideHorizontal, `${label} detail must not be a permanent horizontal panel: ${JSON.stringify(metrics)}`);
  if (expected === 'phone' || expected === 'mini') {
    assert(metrics.horizontalDisplay === 'flex', `${label} must use mobile horizontal flex, got ${metrics.horizontalDisplay}: ${JSON.stringify(metrics)}`);
    assert(['auto', 'scroll'].includes(metrics.horizontalOverflowX), `${label} horizontal overflow missing: ${JSON.stringify(metrics)}`);
    assert(metrics.detailTabDisplay === 'none' || metrics.detailTabDisplay === '', `${label} details must not be a primary mobile tab: ${JSON.stringify(metrics)}`);
    assert(
      metrics.visibleViewTabs.map((tab) => tab.view).join(',') === 'write,check,quick-notes',
      `${label} mobile primary modes must be Write/Check/Quick notes: ${JSON.stringify(metrics)}`
    );
    assert(metrics.summaryOverflowX === 'hidden', `${label} summary strip must not steal horizontal scroll: ${JSON.stringify(metrics)}`);
    assert(metrics.visibleJournalRatio >= (expected === 'phone' ? 0.56 : 0.50), `${label} journal is not dominant enough: ${JSON.stringify(metrics)}`);
    if (expected === 'phone') {
      assert(metrics.panelFlexBasis === '100%', `${label} phone panel should snap full width: ${JSON.stringify(metrics)}`);
    } else {
      const panelWidthRatio = metrics.writing.width / metrics.width;
      assert(panelWidthRatio >= 0.68 && panelWidthRatio <= 0.92, `${label} mini panel width is not intentional: ${JSON.stringify(metrics)}`);
    }
  } else {
    assert(metrics.horizontalDisplay === 'grid', `${label} must use full workspace grid, got ${metrics.horizontalDisplay}: ${JSON.stringify(metrics)}`);
    assert(metrics.writing.width >= 260, `${label} writing panel too narrow: ${JSON.stringify(metrics)}`);
    assert(metrics.check.width >= 300, `${label} structured check panel too narrow: ${JSON.stringify(metrics)}`);
    assertTopbarSummaryPlacement(metrics, label);
    assert(metrics.summaryScrollWidth <= metrics.summaryClientWidth + 2, `${label} summary figures overflow their track: ${JSON.stringify(metrics)}`);
    if (expected === 'workspace-portrait') {
      assert(metrics.check.width >= metrics.writing.width, `${label} portrait workspace should keep structured check at least as prominent as journal: ${JSON.stringify(metrics)}`);
    }
  }
  if (screenshotName) {
    await page.screenshot({ path: path.join(resultsDir, screenshotName), fullPage: false });
  }
  layoutEvidence.push({
    label,
    expected,
    viewport,
    screenshot: screenshotName,
    metrics,
  });
  await context.close();
  console.log(`${label} layout metrics: ${JSON.stringify(metrics)}`);
}

async function assertPhoneLandscapeConstrained(browser, workspaceId) {
  const viewport = { width: 844, height: 390 };
  const context = await browser.newContext({
    baseURL: base,
    viewport,
    isMobile: true,
    hasTouch: true,
  });
  await context.addCookies([{ name: cookieName, value: token, url: base }]);
  const page = await context.newPage();
  await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-status]', 'Ready');
  await enableMobileFinanceMode(page);
  await clickViewIfVisible(page, 'write');
  await page.waitForTimeout(200);
  const metrics = await collectLayoutMetrics(page);
  assert(metrics.bodyOverflow === 'hidden', `phone landscape body overflow must stay hidden: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlOverflow === 'hidden', `phone landscape html overflow must stay hidden: ${JSON.stringify(metrics)}`);
  assert(metrics.shell.bottom <= metrics.height + 2, `phone landscape shell exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.inputbar.bottom <= metrics.height + 2, `phone landscape input hidden below viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.submit.bottom <= metrics.height + 2, `phone landscape submit hidden below viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.horizontalDisplay === 'flex', `phone landscape must stay in mobile notes system: ${JSON.stringify(metrics)}`);
  assert(metrics.detailTabDisplay === 'none' || metrics.detailTabDisplay === '', `phone landscape details must not be a primary tab: ${JSON.stringify(metrics)}`);
  assert(
    metrics.visibleViewTabs.map((tab) => tab.view).join(',') === 'write,check,quick-notes',
    `phone landscape primary modes must be Write/Check/Quick notes: ${JSON.stringify(metrics)}`
  );
  assert(!metrics.detailInsideHorizontal, `phone landscape detail must not be a horizontal panel: ${JSON.stringify(metrics)}`);
  assert(metrics.feed.height >= 120, `phone landscape constrained feed still needs usable height: ${JSON.stringify(metrics)}`);
  const screenshotName = 'phone-landscape-constrained-844x390.png';
  await page.screenshot({ path: path.join(resultsDir, screenshotName), fullPage: false });
  layoutEvidence.push({
    label: 'phone landscape constrained',
    expected: 'phone-landscape-constrained',
    viewport,
    screenshot: screenshotName,
    metrics,
  });
  await context.close();
  console.log(`phone landscape constrained metrics: ${JSON.stringify(metrics)}`);
}

async function assertLayer1SummaryScreen(page) {
  const responsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/reports/layer1-summary');
  });
  await page.locator('[data-v2-screen="summary"]').click();
  const response = await responsePromise;
  assert(response.status() === 200, `Layer 1 summary API failed: HTTP ${response.status()}`);
  await page.locator('[data-v2-summary-screen]').waitFor({ state: 'visible', timeout: 10000 });
  try {
    await waitForText(page, '[data-v2-layer1-information]', 'Period result');
  } catch (error) {
    const summaryText = await page.locator('[data-v2-layer1-information]').innerText().catch(() => '');
    throw new Error(`Layer 1 summary period text missing: ${summaryText}`);
  }
  await waitForText(page, '[data-v2-layer1-information]', 'Money position');
  await waitForText(page, '[data-v2-layer1-information]', 'Server values only');
  await waitForText(page, '[data-v2-layer1-information]', 'Lower accounting');
  await waitForText(page, '[data-v2-settlement-workflow]', 'Женя');
  await waitForText(page, '[data-v2-settlement-workflow]', 'Вова');
  await waitForText(page, '[data-v2-settlement-workflow]', 'Данил');

  const tabLabels = await page.locator('[data-v2-summary-tab]').evaluateAll((tabs) => (
    tabs.map((tab) => tab.textContent.trim())
  ));
  assert(
    tabLabels.join('|') === 'Information|Sending|Printing|Storage'
      || tabLabels.join('|') === 'Информация|Отправка|Печать|Хранение',
    `Layer 1 summary tabs mismatch: ${tabLabels.join('|')}`
  );
  assert(await page.locator('[data-v2-workspace]').isHidden(), 'operational workspace should hide on Summary screen');
  assert((await page.locator('[data-v2-entry-form]').evaluate((form) => form.classList.contains('v2-hidden'))), 'money input bar should hide on Summary screen');

  const cashExpense = page.locator('[data-v2-source-total="cash_expense"]').first();
  await cashExpense.waitFor({ state: 'visible', timeout: 10000 });
  await cashExpense.click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-source-body]', '-250 рыба');
  await page.screenshot({ path: path.join(resultsDir, 'desktop-layer1-summary-source-trace.png'), fullPage: false });
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 10000 });

  await page.locator('[data-v2-source-total="lower_accounting_total"]').first().click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-source-body]', '-111 мой кредит browser lower accounting');
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 10000 });

  await page.locator('[data-v2-source-total="settlement:Вова"]').first().click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'visible', timeout: 10000 });
  await waitForText(page, '[data-v2-source-body]', '-200 Вова под отчет');
  const vovaSettlementBody = await page.locator('[data-v2-source-body]').innerText();
  assert(!vovaSettlementBody.includes('-80 Вова купил кабель'), 'Vova settlement source should exclude operational cable row');
  await page.locator('[data-v2-source-close]').click();
  await page.locator('[data-v2-source-layer]').waitFor({ state: 'hidden', timeout: 10000 });

  for (const tab of ['sending', 'printing', 'storage']) {
    await page.locator(`[data-v2-summary-tab="${tab}"]`).click();
    await page.locator(`[data-v2-summary-panel="${tab}"]`).waitFor({ state: 'visible', timeout: 10000 });
  }
  const snapshotCreateResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/reports/layer1-snapshots');
  });
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-layer1-storage-save]');
    return button && !button.disabled;
  }, null, { timeout: 10000 });
  await page.locator('[data-v2-layer1-storage-save]').click();
  assert((await snapshotCreateResponse).status() === 200, 'Layer 1 snapshot save failed');
  await waitForText(page, '[data-v2-layer1-storage]', 'Snapshot v1');
  await waitForText(page, '[data-v2-layer1-storage]', 'basis_opening');
  await waitForText(page, '[data-v2-layer1-storage]', 'source_ids');
  await page.screenshot({ path: path.join(resultsDir, 'desktop-layer1-summary-storage-readback.png'), fullPage: false });

  await page.locator('[data-v2-summary-tab="information"]').click();
  await page.locator('[data-v2-summary-panel="information"]').waitFor({ state: 'visible', timeout: 10000 });

  const metrics = await page.evaluate(() => {
    const shell = document.querySelector('[data-v2-app]');
    const topbar = document.querySelector('.v2-topbar');
    const summaryScreen = document.querySelector('[data-v2-summary-screen]');
    const reportBody = document.querySelector('[data-v2-layer1-information]');
    const tabs = document.querySelector('.v2-summary-tabs');
    const shellRect = shell.getBoundingClientRect();
    const topbarRect = topbar.getBoundingClientRect();
    const summaryRect = summaryScreen.getBoundingClientRect();
    const reportRect = reportBody.getBoundingClientRect();
    const tabsRect = tabs.getBoundingClientRect();
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      shell: { top: shellRect.top, right: shellRect.right, bottom: shellRect.bottom, left: shellRect.left, width: shellRect.width, height: shellRect.height },
      topbar: { top: topbarRect.top, right: topbarRect.right, bottom: topbarRect.bottom, left: topbarRect.left, width: topbarRect.width, height: topbarRect.height },
      summaryScreen: { top: summaryRect.top, right: summaryRect.right, bottom: summaryRect.bottom, left: summaryRect.left, width: summaryRect.width, height: summaryRect.height },
      reportBody: {
        top: reportRect.top,
        right: reportRect.right,
        bottom: reportRect.bottom,
        left: reportRect.left,
        width: reportRect.width,
        height: reportRect.height,
        scrollHeight: reportBody.scrollHeight,
        clientHeight: reportBody.clientHeight,
        overflowY: getComputedStyle(reportBody).overflowY,
      },
      tabs: { top: tabsRect.top, right: tabsRect.right, bottom: tabsRect.bottom, left: tabsRect.left, width: tabsRect.width, height: tabsRect.height },
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyScrollHeight: document.body.scrollHeight,
      htmlScrollHeight: document.documentElement.scrollHeight,
      documentScrollTop: document.scrollingElement.scrollTop,
    };
  });
  assert(metrics.shell.bottom <= metrics.height + 2, `Layer 1 summary shell exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.summaryScreen.bottom <= metrics.height + 2, `Layer 1 summary screen exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyOverflow === 'hidden' && metrics.htmlOverflow === 'hidden', `Layer 1 summary introduced page scroll: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2 && metrics.htmlScrollWidth <= metrics.width + 2, `Layer 1 summary overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(['auto', 'scroll'].includes(metrics.reportBody.overflowY), `Layer 1 summary body must own scroll: ${JSON.stringify(metrics)}`);
  await page.screenshot({ path: path.join(resultsDir, 'desktop-layer1-summary-information.png'), fullPage: false });
  layoutEvidence.push({
    label: 'SPRINT-17R Layer 1 summary first slice',
    screenshot: 'desktop-layer1-summary-information.png',
    source_trace_screenshot: 'desktop-layer1-summary-source-trace.png',
    metrics,
  });
  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  console.log(`Layer 1 summary metrics: ${JSON.stringify(metrics)}`);
}

async function assertDictionaryTrainingScreen(page, workspaceId) {
  const contentBase64 = xlsxBase64([
    ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
    ['2026-07-01', 'агент', '', '50', '', '', '', ''],
    ['2026-07-01', 'доставка фильтра', '', '15', '', '', '', ''],
    ['2026-07-01', 'мой кредит', '', '1000', '', '', '', ''],
    ['2026-07-01', 'ареда яхты', '5525', '', '', '', '', ''],
  ]);
  const upload = await v2BrowserApi(page, 'POST', `/api/workspaces/${workspaceId}/imports/excel`, {
    file_name: 'browser-dictionary-training-2026-07-01.xlsx',
    file_id: 'browser-dictionary-training-001',
    content_base64: contentBase64,
  });
  assert(upload.status === 200 && upload.data.ok === true, `dictionary training import failed: HTTP ${upload.status}`);

  await page.locator('[data-v2-screen="training"]').click();
  await page.locator('[data-v2-training-screen]').waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForFunction(() => {
    const button = document.querySelector('[data-v2-training-refresh]');
    return button && !button.disabled;
  }, null, { timeout: 10000 });
  const queueResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'GET'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/dictionary-review-queue');
  });
  await page.locator('[data-v2-training-refresh]').click();
  assert((await queueResponse).status() === 200, 'dictionary review queue load failed');
  await waitForText(page, '[data-v2-training-queue]', 'агент');
  await waitForText(page, '[data-v2-training-queue]', 'мой кредит');
  await waitForText(page, '[data-v2-training-count]', ['4 / 4', '4 из 4']);
  const filterLabels = await page.locator('[data-v2-training-filter]').evaluateAll((buttons) => (
    buttons.map((button) => button.textContent.trim())
  ));
  const filterLabelSet = filterLabels.join('|');
  assert(
    [
      'All|Weak|Mixed|Blocked|No category|Deferred|Decided',
      'Все|Слабые|Смешанные|Блок|Без категории|Отложено|Решено',
      'Все|Слабый сигнал|Смешанный смысл|Осторожно|Без категории|Отложенные|Готовые',
    ].includes(filterLabelSet),
    `training filter labels mismatch: ${filterLabelSet}`
  );
  await page.locator('[data-v2-training-filter="blocked"]').click();
  await waitForText(page, '[data-v2-training-queue]', 'мой кредит');
  assert((await page.locator('[data-v2-dictionary-row]').count()) === 1, 'blocked filter should show one debt row');
  await page.locator('[data-v2-training-filter="mixed"]').click();
  await waitForText(page, '[data-v2-training-queue]', 'доставка фильтра');
  assert((await page.locator('[data-v2-dictionary-row]').count()) === 1, 'mixed filter should show delivery filter row');
  await page.locator('[data-v2-training-filter="all"]').click();
  await page.locator('[data-v2-training-search]').fill('browser-dictionary-training');
  await waitForText(page, '[data-v2-training-count]', ['4 / 4', '4 из 4']);
  await page.locator('[data-v2-training-search]').fill('ареда');
  await waitForText(page, '[data-v2-training-queue]', 'ареда яхты');
  assert((await page.locator('[data-v2-dictionary-row]').count()) === 1, 'raw-text search should narrow to yacht rental row');
  await page.locator('[data-v2-training-search]').fill('no matching browser smoke row');
  await page.locator('[data-v2-training-empty]').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('[data-v2-training-search]').evaluate((input) => {
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await waitForText(page, '[data-v2-training-count]', ['4 / 4', '4 из 4']);
  assert(await page.locator('[data-v2-workspace]').isHidden(), 'operational workspace should hide on Training screen');
  assert((await page.locator('[data-v2-entry-form]').evaluate((form) => form.classList.contains('v2-hidden'))), 'money input bar should hide on Training screen');

  const agentRow = page.locator('[data-v2-dictionary-row]').filter({ hasText: 'агент' }).first();
  await agentRow.click();
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'weak_only');
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'current_boat_expenses');
  await waitForText(page, '[data-v2-training-assistant-readback]', 'Как поступить');
  await waitForText(page, '[data-v2-mr-smith]', 'Mr. Smith beta');
  await page.locator('summary', { hasText: 'Справка Mr. Smith' }).click();
  await page.locator('[data-v2-smith-query]').fill('Marina Porto Montenegro 250');
  assert(await page.locator('[data-v2-smith-reference]').isDisabled(), 'Mr. Smith preview should require URL and consent');
  await page.locator('[data-v2-smith-candidate-url]').fill('https://example.com/porto');
  await page.locator('[data-v2-smith-consent]').check();
  assert(!(await page.locator('[data-v2-smith-reference]').isDisabled()), 'Mr. Smith preview should enable after URL and consent');
  const smithResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/dictionary-training-internet-reference');
  });
  await page.locator('[data-v2-smith-reference]').click();
  const smithResult = await smithResponse;
  assert(smithResult.status() === 200, 'Mr. Smith reference preview failed');
  await waitForText(page, '[data-v2-smith-result]', 'Reference: No external lookup performed');
  await waitForText(page, '[data-v2-smith-result]', 'lookup');
  await waitForText(page, '[data-v2-smith-result]', 'no financial or training mutation');
  await page.locator('summary', { hasText: 'Справка Mr. Smith' }).click();
  const usefulFeedback = page.locator('[data-v2-smith-feedback-action="useful"]').first();
  await usefulFeedback.waitFor({ state: 'visible', timeout: 10000 });
  const [smithFeedbackResult] = await Promise.all([
    page.waitForResponse((response) => {
      const request = response.request();
      return request.method() === 'PATCH'
        && response.url().includes('/v2-api.php')
        && routeFromRequest(request).includes('/dictionary-training-internet-reference/lookups/');
    }),
    usefulFeedback.click(),
  ]);
  assert(smithFeedbackResult.status() === 200, 'Mr. Smith evidence feedback failed');
  await waitForText(page, '[data-v2-smith-feedback]', 'Human feedback: useful');
  await page.locator('summary', { hasText: /Тонкая настройка правила|Дополнительные условия правила/ }).click();
  await page.locator('[data-v2-dictionary-requires-any]').fill('лодка');
  await page.locator('[data-v2-dictionary-excludes-any]').fill('мой, личный, долг, кредит');
  await page.locator('[data-v2-dictionary-note]').fill('browser smoke local approval');
  const decisionResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/dictionary-training-decisions');
  });
  await page.locator('[data-v2-dictionary-decision-action="approve_existing_guess_local"]').click();
  const decisionResult = await decisionResponse;
  assert(decisionResult.status() === 200, 'dictionary training approve failed');
  const decisionPayload = decisionResult.request().postDataJSON();
  assert(JSON.stringify(decisionPayload.requires_any) === JSON.stringify(['лодка']), `dictionary training requires_any payload mismatch: ${JSON.stringify(decisionPayload)}`);
  assert(JSON.stringify(decisionPayload.excludes_any) === JSON.stringify(['мой', 'личный', 'долг', 'кредит']), `dictionary training excludes_any payload mismatch: ${JSON.stringify(decisionPayload)}`);
  await waitForText(page, '[data-v2-dictionary-decision-panel]', ['Saved decision', 'Сохраненное решение']);
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'approve_existing_guess_local');
  await waitForText(page, '[data-v2-training-queue]', 'Local rule');

  await page.locator('[data-v2-training-search]').fill('Текущие лодочные');
  await waitForText(page, '[data-v2-training-queue]', 'агент');
  await page.locator('[data-v2-training-search]').fill('');

  const deliveryRow = page.locator('[data-v2-dictionary-row]').filter({ hasText: 'доставка фильтра' }).first();
  await deliveryRow.click();
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'mixed_context');
  const deferResponse = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/dictionary-training-decisions');
  });
  await page.locator('[data-v2-dictionary-decision-action="defer"]').click();
  assert((await deferResponse).status() === 200, 'dictionary training defer failed');
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'defer');
  await page.locator('[data-v2-training-filter="deferred"]').click();
  await waitForText(page, '[data-v2-training-queue]', 'Deferred');
  assert((await page.locator('[data-v2-dictionary-row]').count()) === 1, 'deferred filter should show one deferred row');
  await page.locator('[data-v2-training-filter="decided"]').click();
  await waitForText(page, '[data-v2-training-queue]', 'Local rule');
  await page.locator('[data-v2-training-filter="decided"].is-active').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('[data-v2-training-filter="all"]').click();

  const creditRow = page.locator('[data-v2-dictionary-row]').filter({ hasText: 'мой кредит' }).first();
  await creditRow.click();
  await waitForText(page, '[data-v2-dictionary-decision-panel]', 'blocked_by_debt');
  await waitForText(page, '[data-v2-training-assistant-readback]', ['Do not train from this row', 'Здесь есть риск']);
  assert(await page.locator('[data-v2-dictionary-decision-action="approve_existing_guess_local"]').count() === 0, 'blocked training row should hide local approval');
  await waitForText(page, '[data-v2-dictionary-decision-panel]', ['Оставить как нижний учет', 'Оставить как особый учет']);
  await waitForText(page, '[data-v2-dictionary-decision-panel]', ['Сохранить ручной разбор', 'Сохранить только эту запись']);

  const metrics = await page.evaluate(() => {
    const shell = document.querySelector('[data-v2-app]');
    const training = document.querySelector('[data-v2-training-screen]');
    const queue = document.querySelector('[data-v2-training-queue]');
    const detail = document.querySelector('[data-v2-training-detail]');
    const shellRect = shell.getBoundingClientRect();
    const trainingRect = training.getBoundingClientRect();
    const queueRect = queue.getBoundingClientRect();
    const detailRect = detail.getBoundingClientRect();
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      shell: { right: shellRect.right, bottom: shellRect.bottom, width: shellRect.width, height: shellRect.height },
      training: { right: trainingRect.right, bottom: trainingRect.bottom, width: trainingRect.width, height: trainingRect.height },
      queue: { right: queueRect.right, bottom: queueRect.bottom, width: queueRect.width, height: queueRect.height, overflowY: getComputedStyle(queue).overflowY },
      detail: { right: detailRect.right, bottom: detailRect.bottom, width: detailRect.width, height: detailRect.height, overflowY: getComputedStyle(detail).overflowY },
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyScrollHeight: document.body.scrollHeight,
      htmlScrollHeight: document.documentElement.scrollHeight,
    };
  });
  assert(metrics.training.bottom <= metrics.height + 2, `Training screen exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyOverflow === 'hidden' && metrics.htmlOverflow === 'hidden', `Training introduced page scroll: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2 && metrics.htmlScrollWidth <= metrics.width + 2, `Training overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(['auto', 'scroll'].includes(metrics.queue.overflowY), `Training queue must own scroll: ${JSON.stringify(metrics)}`);
  assert(['auto', 'scroll'].includes(metrics.detail.overflowY), `Training detail must own scroll: ${JSON.stringify(metrics)}`);
  await page.screenshot({ path: path.join(resultsDir, 'desktop-dictionary-training-decision.png'), fullPage: false });
  layoutEvidence.push({
    label: 'SPRINT-32R dictionary training decision console',
    screenshot: 'desktop-dictionary-training-decision.png',
    metrics,
  });
  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  console.log(`Dictionary training metrics: ${JSON.stringify(metrics)}`);
}

async function assertQuickNotesScreen(page, workspaceId) {
  await page.locator('[data-v2-screen="quick-notes"]').click();
  await page.locator('[data-v2-quick-notes-screen]').waitFor({ state: 'visible', timeout: 10000 });
  const metrics = await page.evaluate(() => {
    const screen = document.querySelector('[data-v2-quick-notes-screen]');
    const editor = document.querySelector('.v2-quick-note-editor');
    const shareButton = document.querySelector('[data-v2-quick-note-parse]');
    const summaryStrip = document.querySelector('.v2-summary-strip');
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return { top: box.top, right: box.right, bottom: box.bottom, left: box.left, width: box.width, height: box.height };
    };
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      screen: rect(screen),
      editor: rect(editor),
      shareButton: rect(shareButton),
      summaryStrip: rect(summaryStrip),
      summaryStripDisplay: summaryStrip ? getComputedStyle(summaryStrip).display : '',
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
    };
  });
  assert(metrics.screen && metrics.screen.bottom <= metrics.height + 2, `quick notes screen exceeds viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.shareButton && metrics.shareButton.bottom <= metrics.height + 2, `quick notes Share button is not visible: ${JSON.stringify(metrics)}`);
  assert(metrics.summaryStripDisplay === 'none', `quick notes must hide finance summary strip: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `quick notes body overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `quick notes document overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyOverflow === 'hidden' && metrics.htmlOverflow === 'hidden', `quick notes introduced page scroll: ${JSON.stringify(metrics)}`);

  await page.locator('[data-v2-quick-note-new]').click();
  await page.locator('[data-v2-quick-note-date]').fill('2026-08-13');
  await page.locator('[data-v2-quick-note-text]').fill('Заметка от 13.08.26\n-89 browser quick starlink\n-27 browser quick products');
  const initialPreviewResponsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/preview');
  });
  await page.locator('[data-v2-quick-note-parse]').click();
  await page.locator('[data-v2-quick-note-layer]').waitFor({ state: 'visible', timeout: 10000 });
  const initialPreviewResponse = await initialPreviewResponsePromise;
  assert(initialPreviewResponse.status() === 200, `initial quick note preview failed: HTTP ${initialPreviewResponse.status()}`);
  await waitForText(page, '[data-v2-quick-note-preview]', 'browser quick starlink');
  await page.locator('.v2-detail-close[data-v2-quick-note-modal-close]').click();
  try {
    await page.locator('.v2-quick-note-card.is-active', { hasText: 'browser quick starlink' }).waitFor({ state: 'visible', timeout: 10000 });
  } catch (error) {
    const notesReadback = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/quick-notes`);
    const listHtml = await page.locator('[data-v2-quick-notes-list]').evaluate((node) => node.innerHTML).catch((innerError) => String(innerError));
    throw new Error(`quick note card did not render after save: ${JSON.stringify({ notesReadback, listHtml })}`);
  }
  const editedSavedNoteText = 'Заметка от 13.08.26\n-89 browser quick starlink edited\n-27 browser quick products';
  await page.locator('[data-v2-quick-note-text]').fill(editedSavedNoteText);

  const previewResponsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/preview');
  });
  await page.locator('[data-v2-quick-note-parse]').click();
  assert(
    await page.locator('[data-v2-quick-note-text]').inputValue() === editedSavedNoteText,
    'quick notes Check must not revert edited saved note text immediately'
  );
  await page.locator('[data-v2-quick-note-layer]').waitFor({ state: 'visible', timeout: 10000 });
  const previewResponse = await previewResponsePromise;
  assert(previewResponse.status() === 200, `quick note preview failed: HTTP ${previewResponse.status()}`);
  await waitForText(page, '[data-v2-quick-note-preview]', 'browser quick starlink edited');
  assert(
    await page.locator('[data-v2-quick-note-text]').inputValue() === editedSavedNoteText,
    'quick notes Check must not revert edited saved note text after preview'
  );
  await page.locator('[data-v2-quick-note-proposal-enabled="1"]').uncheck();
  await page.screenshot({ path: path.join(resultsDir, 'desktop-quick-notes-smith-preview.png'), fullPage: false });

  const convertResponsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/convert');
  });
  await page.locator('[data-v2-quick-note-convert]').click();
  const convertResponse = await convertResponsePromise;
  assert(convertResponse.status() === 200, `quick note convert failed: HTTP ${convertResponse.status()}`);
  const converted = await convertResponse.json();
  assert(converted.ok === true && converted.entries && converted.entries.length === 1, `quick note convert count mismatch: ${JSON.stringify(converted)}`);

  await page.locator('[data-v2-screen="operational"]').click();
  await waitForText(page, '[data-v2-feed]', 'browser quick starlink edited');
  const entriesAfterConvert = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/entries`);
  assert(entriesAfterConvert.status === 200 && entriesAfterConvert.data.ok === true, 'quick note entries readback failed');
  const entryRows = entriesAfterConvert.data.entries || [];
  assert(entryRows.filter((entry) => entry.raw_text === '-89 browser quick starlink edited').length === 1, 'converted quick note entry missing');
  assert(entryRows.filter((entry) => entry.raw_text === '-27 browser quick products').length === 0, 'disabled quick note line was converted');

  await page.locator('[data-v2-screen="quick-notes"]').click();
  await page.locator('[data-v2-quick-note-new]').click();
  await page.locator('[data-v2-quick-note-date]').fill('2026-08-13');
  await page.locator('[data-v2-quick-note-text]').fill('-89 browser quick starlink');
  const duplicatePreviewResponsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/preview');
  });
  await page.locator('[data-v2-quick-note-parse]').click();
  await page.locator('[data-v2-quick-note-layer]').waitFor({ state: 'visible', timeout: 10000 });
  const duplicatePreviewResponse = await duplicatePreviewResponsePromise;
  assert(duplicatePreviewResponse.status() === 200, `duplicate quick note preview failed: HTTP ${duplicatePreviewResponse.status()}`);
  await waitForText(page, '[data-v2-quick-note-preview]', 'Похожая строка');
  await page.screenshot({ path: path.join(resultsDir, 'desktop-quick-notes-duplicate-warning.png'), fullPage: false });
  await page.locator('.v2-detail-close[data-v2-quick-note-modal-close]').click();
  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  console.log(`Quick notes metrics: ${JSON.stringify(metrics)}`);
}

async function assertQuickNotesMobileScreen(page) {
  await enableMobileFinanceMode(page);
  await page.locator('[data-v2-view="quick-notes"]').click();
  await page.locator('[data-v2-quick-notes-screen]').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('[data-v2-quick-note-new]').click();
  await page.locator('[data-v2-quick-note-text]').fill('-89 mobile quick starlink\n-27 mobile quick products');
  const mobileQuickNoteDraft = await page.locator('[data-v2-quick-note-text]').inputValue();
  const metrics = await page.evaluate(() => {
    const screen = document.querySelector('[data-v2-quick-notes-screen]');
    const editor = document.querySelector('.v2-quick-note-editor-panel');
    const textarea = document.querySelector('[data-v2-quick-note-text]');
    const parseButton = document.querySelector('[data-v2-quick-note-parse]');
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return { top: box.top, right: box.right, bottom: box.bottom, left: box.left, width: box.width, height: box.height };
    };
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      screen: rect(screen),
      editor: rect(editor),
      textarea: rect(textarea),
      parseButton: rect(parseButton),
      bodyOverflow: getComputedStyle(document.body).overflow,
      htmlOverflow: getComputedStyle(document.documentElement).overflow,
      bodyScrollWidth: document.body.scrollWidth,
      htmlScrollWidth: document.documentElement.scrollWidth,
    };
  });
  assert(metrics.screen && metrics.screen.right <= metrics.width + 2, `mobile quick notes screen overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.textarea && metrics.textarea.right <= metrics.width + 2, `mobile quick notes textarea overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.parseButton && metrics.parseButton.bottom <= metrics.height + 2, `mobile quick notes Share button is not visible: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyScrollWidth <= metrics.width + 2, `mobile quick notes body overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.htmlScrollWidth <= metrics.width + 2, `mobile quick notes document overhangs viewport: ${JSON.stringify(metrics)}`);
  assert(metrics.bodyOverflow === 'hidden' && metrics.htmlOverflow === 'hidden', `mobile quick notes introduced page scroll: ${JSON.stringify(metrics)}`);
  const mobilePreviewResponsePromise = page.waitForResponse((response) => {
    const request = response.request();
    return request.method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(request).endsWith('/preview');
  });
  await page.locator('[data-v2-quick-note-parse]').click();
  assert(
    await page.locator('[data-v2-quick-note-text]').inputValue() === mobileQuickNoteDraft,
    'mobile quick notes Check must not clear draft text immediately'
  );
  await page.locator('[data-v2-quick-note-layer]').waitFor({ state: 'visible', timeout: 10000 });
  const mobilePreviewResponse = await mobilePreviewResponsePromise;
  assert(mobilePreviewResponse.status() === 200, `mobile quick note preview failed: HTTP ${mobilePreviewResponse.status()}`);
  await waitForText(page, '[data-v2-quick-note-preview]', 'mobile quick starlink');
  assert(
    await page.locator('[data-v2-quick-note-text]').inputValue() === mobileQuickNoteDraft,
    'mobile quick notes Check must not clear draft text after preview'
  );
  const modalMetrics = await page.evaluate(() => {
    const dialog = document.querySelector('.v2-quick-note-dialog');
    const convertButton = document.querySelector('[data-v2-quick-note-convert]');
    const rect = (node) => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return { top: box.top, right: box.right, bottom: box.bottom, left: box.left, width: box.width, height: box.height };
    };
    return {
      width: window.innerWidth,
      height: window.innerHeight,
      dialog: rect(dialog),
      convertButton: rect(convertButton)
    };
  });
  assert(modalMetrics.dialog && modalMetrics.dialog.right <= modalMetrics.width + 2, `mobile quick notes Smith modal overhangs viewport: ${JSON.stringify(modalMetrics)}`);
  assert(modalMetrics.convertButton && modalMetrics.convertButton.bottom <= modalMetrics.height + 2, `mobile quick notes accept button is not visible: ${JSON.stringify(modalMetrics)}`);
  await page.screenshot({ path: path.join(resultsDir, 'mobile-quick-notes-390x844.png'), fullPage: false });
  await page.locator('.v2-detail-close[data-v2-quick-note-modal-close]').click();
  await page.locator('[data-v2-screen="operational"]').click();
  await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
  console.log(`Mobile quick notes metrics: ${JSON.stringify(metrics)}`);
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
    await authlessPage.locator('[data-v2-auth-form]').waitFor({ state: 'visible', timeout: 10000 });
    await authlessPage.locator('[data-v2-auth-email]').fill('v2-browser-smoke@example.test');
    await authlessPage.locator('[data-v2-auth-send]').click();
    await authlessPage.locator('[data-v2-auth-code-block]').waitFor({ state: 'visible', timeout: 10000 });
    const localCode = await authlessPage.locator('[data-v2-auth-code]').inputValue();
    assert(/^\d{6}$/.test(localCode), `local auth code was not autofilled: ${localCode}`);
    await authlessPage.locator('[data-v2-auth-verify]').click();
    await authlessPage.locator('[data-v2-create-form]').waitFor({ state: 'visible', timeout: 10000 });
    await waitForText(authlessPage, '[data-v2-status]', 'Create a workspace to start writing');
    await authless.close();
    console.log('Unauthenticated and email-code sign-in state: OK');

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

    await assertHallAccountableMaterializationUi(page, workspaceId);
    console.log('Hall accountable materialization UI: OK');

    const browserFlows = await v2BrowserApi(page, 'GET', `/api/workspaces/${workspaceId}/flows`);
    assert(browserFlows.status === 200 && browserFlows.data.ok === true, 'browser flow read failed');
    const browserCashFlow = (browserFlows.data.flows || []).find((flow) => flow.type === 'cash');
    assert(browserCashFlow, 'browser cash flow missing');
    const browserPriorEntry = await v2BrowserApi(page, 'POST', `/api/workspaces/${workspaceId}/entries`, {
      flow_id: browserCashFlow.id,
      date: priorMonthDateForBrowserSmoke(),
      raw_text: '+42 browser prior opening source',
    });
    assert(browserPriorEntry.status === 200 && browserPriorEntry.data.ok === true, 'browser prior source entry create failed');

    const priorMonth = monthPartsFromDate(priorMonthDateForBrowserSmoke());
    await page.locator('[data-v2-archive-open]').click();
    await page.locator('[data-v2-archive-modal]').waitFor({ state: 'visible', timeout: 10000 });
    assert((await page.locator('[data-v2-archive-year]').inputValue()) === String(priorMonth.year), 'archive year default mismatch');
    assert((await page.locator('[data-v2-archive-month]').inputValue()) === String(priorMonth.month), 'archive month default mismatch');
    const archiveEntriesResponse = page.waitForResponse((response) => {
      const request = response.request();
      return request.method() === 'GET'
        && response.url().includes('/v2-api.php')
        && routeFromRequest(request).endsWith('/entries')
        && queryParamFromRequest(request, 'year') === String(priorMonth.year)
        && queryParamFromRequest(request, 'month') === String(priorMonth.month);
    });
    await page.locator('[data-v2-archive-load]').click();
    assert((await archiveEntriesResponse).status() === 200, 'archive entries load failed');
    await waitForText(page, '[data-v2-month]', 'Archive');
    await waitForText(page, '[data-v2-feed]', '+42 browser prior opening source');
    assert(!(await page.locator('[data-v2-current-month]').isHidden()), 'current-month return action should be visible in archive mode');
    const current = monthPartsFromDate(new Date().toISOString().slice(0, 10));
    const currentEntriesResponse = page.waitForResponse((response) => {
      const request = response.request();
      return request.method() === 'GET'
        && response.url().includes('/v2-api.php')
        && routeFromRequest(request).endsWith('/entries')
        && queryParamFromRequest(request, 'year') === String(current.year)
        && queryParamFromRequest(request, 'month') === String(current.month);
    });
    await page.locator('[data-v2-current-month]').click();
    assert((await currentEntriesResponse).status() === 200, 'current entries reload failed');
    await page.waitForFunction((archiveText) => {
      const month = document.querySelector('[data-v2-month]');
      const feed = document.querySelector('[data-v2-feed]');
      return month
        && feed
        && !month.textContent.includes('Archive')
        && !month.textContent.includes('Архив')
        && !feed.textContent.includes(archiveText);
    }, '+42 browser prior opening source', { timeout: 10000 });
    assert(!(await page.locator('[data-v2-feed]').innerText()).includes('+42 browser prior opening source'), 'archive row leaked into current month feed');
    console.log('Archive month switching: OK');

    await saveEntry(page, '+1000 снял с карты');
    await saveEntry(page, '-250 рыба');
    await waitForText(page, '[data-v2-count]', '2 records');
    await assertLatestOperationalDraftRow(page, '-250 рыба', '-77 draft mirror', 'latest operational draft row after save');
    await assertArchiveUnsavedGuard(page);
    console.log('Archive unsaved edit guard: OK');
    await page.locator('[data-v2-check-row][data-v2-entry-id]').first().focus();
    await page.waitForTimeout(150);

    const checkHeaders = await page.locator('[data-v2-check-header]').first().innerText();
    const expectedCheckHeaders = [
      ['date', 'Дата'],
      ['raw_text', 'Запись'],
      ['flow', 'Поток'],
      ['sign', 'Знак'],
      ['amount', 'Сумма'],
      ['direction', 'Направление', 'Движение'],
      ['category', 'Категория'],
      ['accounting', 'Учет'],
      ['actor', 'Участник'],
      ['status', 'Статус'],
      ['balance_after', 'Остаток после', 'Остаток'],
    ];
    for (const field of expectedCheckHeaders) {
      assert(field.some((label) => checkHeaders.includes(label)), `structured check missing header: ${field.join(' / ')}`);
    }
    const checkText = await page.locator('[data-v2-check-table]').innerText();
    assert(checkText.includes('+1000 снял с карты'), 'structured check missing first saved record');
    assert(checkText.includes('-250 рыба'), 'structured check missing second saved record');
    assert(includesAmountText(checkText, 1000), 'structured check missing formatted +1000 amount');
    assert(includesAmountText(checkText, 250), 'structured check missing formatted -250 amount');
    await assertHoverPreviewCard(page);
    await assertEditTargetStableOnHover(page);
    await assertEntryEditDelete(page);
    await waitForText(page, '[data-v2-count]', '2 records');
    await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
    await page.waitForTimeout(150);
    const journalHeader = await assertJournalHeader(page);
    await assertLinkedRows(page, '+1000 снял с карты');
    await assertLinkedRows(page, '-250 рыба');
    const initialRowSync = await assertRowHeightSync(page, '-250 рыба', 'initial desktop row height sync');
    const headerFirstRowAlignment = await assertHeaderAndFirstRowAlignment(page, 'desktop journal/check header and first row alignment');
    await page.screenshot({ path: path.join(resultsDir, 'desktop-journal-header-row-sync.png'), fullPage: false });
    const hoverEvidence = await assertHoverDoesNotOpenDetail(page, '-250 рыба');
    await page.screenshot({ path: path.join(resultsDir, 'desktop-hover-no-detail.png'), fullPage: false });
    const arrowSurfaceEvidence = await assertArrowLeftRightSurfaceSwitch(page);
    await page.screenshot({ path: path.join(resultsDir, 'desktop-arrow-left-right-surface-switch.png'), fullPage: false });
    const journalKeyboardEvidence = await assertKeyboardNavigation(page, 'journal');
    await page.screenshot({ path: path.join(resultsDir, 'desktop-escape-focus-restored-journal.png'), fullPage: false });
    const checkKeyboardEvidence = await assertKeyboardNavigation(page, 'check');
    await page.screenshot({ path: path.join(resultsDir, 'desktop-escape-focus-restored-check.png'), fullPage: false });
    await assertInactiveSurfaceFirstClickFocusesOnly(page, '-250 рыба');
    await page.screenshot({ path: path.join(resultsDir, 'desktop-inactive-window-first-click-focus-only.png'), fullPage: false });
    layoutEvidence.push({
      label: 'SPRINT-16R linked ledger keyboard evidence',
      journalHeader,
      initialRowSync,
      headerFirstRowAlignment,
      hoverEvidence,
      arrowSurfaceEvidence,
      journalKeyboardEvidence,
      checkKeyboardEvidence,
    });
    console.log('Save records + structured check: OK');

    const selectedFish = await selectEntryByText(page, '-250 рыба');
    assert((await selectedFish.getAttribute('class')).includes('is-selected'), 'selected feed row missing active state');
    await assertContextualDetailOpen(page, '-250 рыба', 'journal row selection');
    const fishDetail = await page.locator('[data-v2-entry-detail-body]').innerText();
    for (const field of expectedEntryDetailFields) {
      assert(field.some((label) => fishDetail.includes(label)), `entry detail missing field: ${field.join(' / ')}`);
    }
    const closeDraft = 'contextual close keeps draft';
    await page.locator(rawTextInputSelector).fill(closeDraft);
    await closeDetailAndAssertPreserved(page, '-250 рыба', closeDraft, 'desktop detail');
    await selectCheckRowByText(page, '-250 рыба');
    console.log('Linked rows + contextual detail selection: OK');

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
    await closeDetailAndAssertPreserved(page, '-250 рыба', closeDraft, 'desktop attachment refresh');

    await page.locator('[data-v2-refresh]').click();
    await waitForText(page, '[data-v2-status]', 'Ready');
    await waitForText(page, '[data-v2-feed]', '-250 рыба');
    await selectEntryByText(page, '-250 рыба');
    await waitForText(page, '[data-v2-attachment-list]', 'browser-smoke-attachment.png');

    const attachmentDeleteButton = page.locator('[data-v2-entry-detail]:visible [data-v2-attachment-delete]').first();
    await attachmentDeleteButton.waitFor({ state: 'visible', timeout: 10000 });
    const attachmentDeleteResponse = page.waitForResponse((response) => (
      response.request().method() === 'DELETE'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).startsWith('/api/attachments/')
    ));
    const [, deleteResponse] = await Promise.all([
      attachmentDeleteButton.click(),
      attachmentDeleteResponse,
    ]);
    assert(deleteResponse.status() === 200, 'attachment delete failed');
    assert(attachmentDeletes.length >= 1, 'attachment delete request was not observed');
    await waitForText(page, '[data-v2-attachment-list]', 'No attachments');
    await closeDetailAndAssertPreserved(page, '-250 рыба', closeDraft, 'desktop attachment delete');
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
    await assertContextualDetailOpen(page, '-180 какая-то штука', 'other review category correction');
    const resolvedDetail = await page.locator('[data-v2-entry-detail-body]').innerText();
    assert(
      resolvedDetail.includes('tech_parts') || resolvedDetail.includes('Запчаст') || resolvedDetail.includes('Техчасть'),
      `category correction did not update detail panel: ${resolvedDetail}`
    );
    assert(resolvedDetail.includes('recognized'), 'other_review correction did not mark entry recognized');
    await waitForText(page, '[data-v2-check-table]', 'Техчасть');
    await closeDetailAndAssertPreserved(page, '-180 какая-то штука', '', 'other review category correction');
    console.log('Other review category correction: OK');

    await page.locator('[data-v2-refresh]').click();
    await waitForText(page, '[data-v2-status]', 'Ready');
    await waitForText(page, '[data-v2-feed]', '+1000 снял с карты');
    await waitForText(page, '[data-v2-feed]', '-250 рыба');
    await waitForText(page, '[data-v2-check-table]', '-250 рыба');
    console.log('Refresh preserves feed/check: OK');

    const beforeGuardPosts = entryPostBodies.length;
    await page.locator(rawTextInputSelector).fill('-10 duplicate guard');
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

    await page.locator('[data-v2-archive-open]').click();
    await page.locator('[data-v2-archive-modal]').waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('[data-v2-archive-year]').selectOption(String(priorMonth.year));
    await page.locator('[data-v2-archive-month]').selectOption(String(priorMonth.month));
    await page.locator('[data-v2-archive-load]').click();
    await waitForText(page, '[data-v2-month]', 'Archive');
    await waitForText(page, '[data-v2-feed]', '+42 browser prior opening source');

    const closedMonthRawText = '-33 Netflix closed month';
    const closedMonthEntry = await v2BrowserApi(page, 'POST', `/api/workspaces/${workspaceId}/entries`, {
      flow_id: browserCashFlow.id,
      date: priorMonthDateForBrowserSmoke(),
      raw_text: closedMonthRawText,
    });
    assert(closedMonthEntry.status === 200 && closedMonthEntry.data.ok === true, 'closed month seed entry create failed');
    await page.locator('[data-v2-refresh]').click();
    await waitForText(page, '[data-v2-feed]', closedMonthRawText);

    await selectEntryByText(page, '-33 Netflix closed month');
    await waitForText(page, '[data-v2-entry-detail-body]', 'Мультимедиа');
    const entryMonth = priorMonth;
    await waitForText(page, '[data-v2-month-state]', 'Open');
    await closeDetailAndAssertPreserved(page, '-33 Netflix closed month', '', 'closed month before close toggle');
    const closeMonthResponse = page.waitForResponse((response) => (
      response.request().method() === 'POST'
      && response.url().includes('/v2-api.php')
      && routeFromRequest(response.request()).endsWith(`/months/${entryMonth.year}/${entryMonth.month}/close`)
    ));
    await page.locator('[data-v2-month-toggle]').click();
    assert((await closeMonthResponse).status() === 200, 'month close failed');
    await waitForText(page, '[data-v2-month-state]', 'Closed');
    await waitForText(page, '[data-v2-status]', 'Month closed');
    assert(!(await page.locator('[data-v2-submit]').isDisabled()), 'entry submit should stay guarded, not disabled, while current month is closed');
    const beforeClosedMonthCreatePosts = entryPostBodies.length;
    await page.locator(rawTextInputSelector).fill('-44 closed month guarded save');
    await page.locator('[data-v2-submit]').click();
    await page.locator('[data-v2-closed-edit-layer]').waitFor({ state: 'visible', timeout: 10000 });
    assert(entryPostBodies.length === beforeClosedMonthCreatePosts, 'closed month save should not POST before confirmation');
    await page.locator('[data-v2-closed-edit-layer] [data-v2-closed-edit-cancel]').last().click();
    await page.locator('[data-v2-closed-edit-layer]').waitFor({ state: 'hidden', timeout: 10000 });
    await page.locator(rawTextInputSelector).fill('');
    await selectEntryByText(page, '-33 Netflix closed month');
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
    await waitForText(page, '[data-v2-entry-detail-body]', 'Мультимедиа');
    assert((await detailFieldValue(page, 'category')).includes('Мультимедиа'), 'closed month category mutation appeared optimistic');
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
    assert((await detailFieldValue(page, 'category')).includes('Мультимедиа'), 'create correction mutated original category');

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
    assert((await detailFieldValue(page, 'category')).includes('Топливо'), 'recalculate decision did not update category');
    await closeDetailAndAssertPreserved(page, '-33 Netflix closed month', '', 'closed month before reopen toggle');

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

    const currentEntriesAfterClosedMonthResponse = page.waitForResponse((response) => {
      const request = response.request();
      return request.method() === 'GET'
        && response.url().includes('/v2-api.php')
        && routeFromRequest(request).endsWith('/entries')
        && queryParamFromRequest(request, 'year') === String(current.year)
        && queryParamFromRequest(request, 'month') === String(current.month);
    });
    await page.locator('[data-v2-current-month]').click();
    assert((await currentEntriesAfterClosedMonthResponse).status() === 200, 'current entries reload after closed-month test failed');
    await waitForText(page, '[data-v2-status]', 'Current month');

    await saveEntry(page, '-111 мой кредит browser lower accounting');
    await page.locator('[data-v2-check]').click({ position: { x: 12, y: 12 } });
    await waitForText(page, '[data-v2-check-table]', '-111 мой кредит browser lower accounting');
    const lowerCheckText = await page.locator('[data-v2-check-table]').innerText();
    assert(
      textIncludes(lowerCheckText, ['Под отчет, займы', 'Деньги под отчет', 'Займы, долги, кредиты']),
      `lower accounting marker missing from structured check: ${lowerCheckText}`
    );
    await saveEntry(page, '-300 Женя под отчет');
    await saveEntry(page, '-200 Вова под отчет');
    await saveEntry(page, '+50 Вова вернул остаток');
    await saveEntry(page, '-120 Данил под отчет');
    await saveEntry(page, '+120 Данил вернул остаток');
    await saveEntry(page, '-80 Вова купил кабель');

    await assertLayer1SummaryScreen(page);
    console.log('Layer 1 summary first slice: OK');
    await assertDictionaryTrainingScreen(page, workspaceId);
    console.log('Dictionary training decision console: OK');
    await assertQuickNotesScreen(page, workspaceId);
    console.log('Quick notes Smith migration UI: OK');

    for (let i = 1; i <= 8; i += 1) {
      await saveEntry(page, `-1 scroll filler ${i}`);
    }
    await waitForText(page, '[data-v2-feed]', 'scroll filler 8');
    const fillerRowSync = await assertRowHeightSync(page, 'scroll filler 8', 'scroll filler row height sync');
    layoutEvidence.push({
      label: 'SPRINT-16R scroll filler row sync',
      fillerRowSync,
    });
    await assertBottomScrollRowSync(page, 'scroll filler 8', 'SPRINT-16R bottom scroll row sync');
    await assertBottomScrollRowSync(page, 'scroll filler 8', 'SPRINT-16R bottom scroll row sync from structured', 'check');
    await page.locator('[data-v2-entry-select][data-v2-entry-id]').first().focus();
    await page.waitForTimeout(150);

    const desktopMetrics = await assertNoPageScroll(page);
    await page.screenshot({ path: path.join(resultsDir, 'desktop-operational-window.png'), fullPage: false });
    console.log(`Desktop scroll metrics: ${JSON.stringify(desktopMetrics)}`);
    const desktopJournalFocusMetrics = await assertDesktopJournalFocus(page);
    await page.screenshot({ path: path.join(resultsDir, 'desktop-journal-focus.png'), fullPage: false });
    console.log(`Desktop journal focus metrics: ${JSON.stringify(desktopJournalFocusMetrics)}`);
    const desktopCheckFocusMetrics = await assertDesktopCheckFocus(page);
    await page.screenshot({ path: path.join(resultsDir, 'desktop-check-focus.png'), fullPage: false });
    console.log(`Desktop check focus metrics: ${JSON.stringify(desktopCheckFocusMetrics)}`);
    const structuredHorizontalHeaderSync = await assertStructuredHorizontalHeaderSync(page);
    layoutEvidence.push({
      label: 'SPRINT-16R structured horizontal header sync',
      structuredHorizontalHeaderSync,
    });
    await closeDetailIfOpen(page, 'desktop check focus');

    const draft = 'offline draft preserved';
    await page.locator('[data-v2-screen="operational"]').click();
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await ensureCreateEntryMode(page);
    await page.locator(rawTextInputSelector).fill(draft);
    await page.waitForFunction((value) => localStorage.getItem('findesk.v2.operational.draft') === value, draft);
    await context.setOffline(true);
    await page.locator('[data-v2-submit]').click();
    await waitForText(page, '[data-v2-status]', 'Offline: draft kept locally');
    assert(await page.locator(rawTextInputSelector).inputValue() === draft, 'offline submit cleared draft input');
    await context.setOffline(false);
    await page.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
    await page.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction((value) => {
      const input = document.querySelector('[data-v2-entry-form] [data-v2-raw-text]');
      return input && input.value === value;
    }, draft);
    assert(await page.locator(rawTextInputSelector).inputValue() === draft, 'draft did not restore after reload');
    console.log('Offline draft preservation: OK');
    await context.close();

    const mobile = await browser.newContext({ baseURL: base, viewport: { width: 390, height: 844 }, isMobile: true });
    await mobile.addCookies([{ name: cookieName, value: token, url: base }]);
    const mobilePage = await mobile.newPage();
    await mobilePage.goto(`/v2.php?workspace=${workspaceId}`, { waitUntil: 'domcontentloaded' });
    await mobilePage.locator('[data-v2-workspace]').waitFor({ state: 'visible', timeout: 10000 });
    await waitForText(mobilePage, '[data-v2-status]', 'Ready');
    await mobilePage.waitForTimeout(300);
    await mobilePage.screenshot({ path: path.join(resultsDir, 'phone-portrait-390x844.png'), fullPage: false });
    await assertQuickNotesMobileScreen(mobilePage);
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
    await mobilePage.locator(rawTextInputSelector).focus();
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
    await enableMobileFinanceMode(mobilePage);
    await clickViewIfVisible(mobilePage, 'write');
    await mobilePage.waitForTimeout(300);
    const beforeMobileDetailMetrics = await collectLayoutMetrics(mobilePage);
    const mobileFishRow = mobilePage.locator('[data-v2-entry-select]', { hasText: '-250 рыба' }).first();
    await mobileFishRow.click();
    await mobilePage.locator('[data-v2-entry-form].is-previewing').waitFor({ state: 'visible', timeout: 5000 });
    assert(await mobilePage.locator('[data-v2-submit]').isHidden(), 'mobile first row tap should keep Save hidden in preview mode');
    await mobileFishRow.click();
    const mobileDetailMetrics = await assertContextualDetailOpen(mobilePage, '-250 рыба', 'mobile journal row selection');
    assert(
      mobileDetailMetrics.visibleViewTabs.map((tab) => tab.view).join(',') === 'write,check,quick-notes',
      `mobile detail opened with non Write/Check/Quick notes primary modes: ${JSON.stringify(mobileDetailMetrics)}`
    );
    assert(
      Math.abs(mobileDetailMetrics.documentScrollTop - beforeMobileDetailMetrics.documentScrollTop) <= 2,
      `mobile detail sheet changed page scroll: ${JSON.stringify({ beforeMobileDetailMetrics, mobileDetailMetrics })}`
    );
    assert(!mobileDetailMetrics.detailInsideHorizontal, `mobile detail must not be inside horizontal panel: ${JSON.stringify({ beforeMobileDetailMetrics, mobileDetailMetrics })}`);
    assert(mobileDetailMetrics.bodyScrollWidth <= mobileDetailMetrics.width + 2, `mobile detail created body overhang: ${JSON.stringify(mobileDetailMetrics)}`);
    assert(mobileDetailMetrics.htmlScrollWidth <= mobileDetailMetrics.width + 2, `mobile detail created document overhang: ${JSON.stringify(mobileDetailMetrics)}`);
    const mobileDraft = 'mobile detail close keeps draft';
    await mobilePage.locator(rawTextInputSelector).fill(mobileDraft);
    await closeDetailAndAssertPreserved(mobilePage, '-250 рыба', mobileDraft, 'mobile detail');
    await clickViewIfVisible(mobilePage, 'check');
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
    assert(mobileMetrics.horizontalScrollLeft > beforeMobileDetailMetrics.horizontalScrollLeft + 20, `mobile check view did not move horizontally: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.checkLeft < mobileMetrics.windowWidth && mobileMetrics.checkRight > 0, `mobile check panel not visible: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.bodyOverflow === 'hidden', `mobile body overflow must stay hidden: ${JSON.stringify(mobileMetrics)}`);
    assert(mobileMetrics.feedOverflowY === 'auto', `mobile feed must own vertical scroll: ${JSON.stringify(mobileMetrics)}`);
    assert(['auto', 'scroll'].includes(mobileMetrics.horizontalOverflowX), `mobile horizontal overflow missing: ${JSON.stringify(mobileMetrics)}`);
    await mobilePage.screenshot({ path: path.join(resultsDir, 'mobile-structured-check.png'), fullPage: false });
    await mobile.close();
    console.log(`Mobile horizontal metrics: ${JSON.stringify(mobileMetrics)}`);
    console.log(`Mobile feed/input metrics: ${JSON.stringify({ feedScrollMetrics, inputReachMetrics })}`);

    await assertViewportLayout(browser, workspaceId, { width: 390, height: 844 }, 'phone', 'phone portrait', 'phone-portrait-layout-390x844.png');
    await assertViewportLayout(browser, workspaceId, { width: 360, height: 640 }, 'phone', 'small phone portrait', 'phone-small-360x640.png');
    await assertPhoneLandscapeConstrained(browser, workspaceId);
    await assertViewportLayout(browser, workspaceId, { width: 768, height: 1024 }, 'mini', 'iPad mini portrait', 'ipad-mini-portrait.png');
    await assertViewportLayout(browser, workspaceId, { width: 1024, height: 768 }, 'mini', 'iPad mini landscape', 'ipad-mini-landscape.png');
    await assertViewportLayout(browser, workspaceId, { width: 834, height: 1194 }, 'workspace-portrait', 'iPad 11 portrait', 'ipad-11-portrait.png');
    await assertViewportLayout(browser, workspaceId, { width: 1194, height: 834 }, 'workspace-desktop', 'iPad 11 landscape', 'ipad-11-landscape.png');
    await assertViewportLayout(browser, workspaceId, { width: 1365, height: 820 }, 'workspace-desktop', 'desktop standard 1365x820', 'desktop-standard-1365x820.png');
    await assertViewportLayout(browser, workspaceId, { width: 1440, height: 900 }, 'workspace-desktop', 'desktop standard 1440x900', 'desktop-standard-1440x900.png');
    await assertCurrentMonthCloseOpensNextPeriod(browser, workspaceId);
    console.log('Current month close opens next operational period: OK');

    console.log('FinDesk v2 browser UI smoke: OK');
    fs.writeFileSync(
      path.join(resultsDir, 'layout-metrics.json'),
      JSON.stringify({
        generated_at: new Date().toISOString(),
        evidence: layoutEvidence,
      }, null, 2)
    );
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
