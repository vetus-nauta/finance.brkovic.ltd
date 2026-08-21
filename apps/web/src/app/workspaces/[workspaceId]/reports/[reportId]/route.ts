import { getWorkspaceReportSnapshot, roleLabels } from "@/lib/workspace-data";

type ReportRouteProps = {
  params: Promise<{
    workspaceId: string;
    reportId: string;
  }>;
};

function escapeHtml(value: string) {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatDateOnly(value: string) {
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  }).format(new Date(value));
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(value));
}

function formatMoney(value: number, currency: string) {
  return new Intl.NumberFormat("ru-RU", {
    currency,
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
    style: "currency"
  }).format(value);
}

function directionText(direction: string) {
  switch (direction) {
    case "income":
      return "Приход";
    case "expense":
      return "Расход";
    default:
      return "Учет";
  }
}

function reportStatusText(status: string) {
  switch (status) {
    case "draft":
      return "Черновик";
    case "created":
      return "Создан";
    case "sent":
      return "Отправлен";
    case "accepted":
      return "Принят";
    case "returned_for_revision":
      return "На доработке";
    default:
      return status;
  }
}

function approvalEventText(eventType: string) {
  switch (eventType) {
    case "report_snapshot_created":
      return "Отчет создан";
    case "report_snapshot_sent":
      return "Отчет отправлен";
    case "report_snapshot_accepted":
      return "Отчет принят";
    case "report_snapshot_returned_for_revision":
      return "Возвращен на доработку";
    case "report_locked_correction_created":
      return "Создана корректировка";
    default:
      return eventType;
  }
}

export async function GET(_request: Request, { params }: ReportRouteProps) {
  const { workspaceId, reportId } = await params;
  const document = await getWorkspaceReportSnapshot(workspaceId, reportId);

  if (!document) {
    return new Response("Report not found", { status: 404 });
  }

  const { report } = document;
  const categoryBlocks = report.categories
    .map((category) => {
      const rows = report.entries.filter(
        (entry) => entry.categoryCode === category.code && (entry.direction ?? category.direction) === category.direction
      );
      const sourceRows = rows
        .map(
          (entry) => `
            <tr>
              <td class="num">${entry.rowNo}</td>
              <td>${formatDateOnly(entry.occurredOn)}</td>
              <td>${escapeHtml(entry.rawText)}</td>
              <td class="money ${entry.direction === "income" ? "income" : entry.direction === "expense" ? "expense" : ""}">
                ${entry.amount === null ? "—" : formatMoney(entry.amount, document.currency)}
              </td>
              <td>${entry.reviewStatus === "accepted" ? "Принято" : "Проверка"}</td>
            </tr>
          `
        )
        .join("");

      return `
        <details class="category">
          <summary>
            <span>
              <strong>${escapeHtml(category.label)}</strong>
              <small>${directionText(category.direction)} · ${category.entryCount} строк · проверка ${category.reviewCount}</small>
            </span>
            <b>${formatMoney(category.total, document.currency)}</b>
          </summary>
          <table>
            <thead>
              <tr>
                <th>№</th>
                <th>Дата</th>
                <th>Запись</th>
                <th>Сумма</th>
                <th>Статус</th>
              </tr>
            </thead>
            <tbody>${sourceRows}</tbody>
          </table>
        </details>
      `;
    })
    .join("");

  const accountRows = report.accounts
    .map(
      (account) => `
        <tr>
          <td>${escapeHtml(account.label)}</td>
          <td>${account.entryCount}</td>
          <td class="money income">${formatMoney(account.incomeTotal, document.currency)}</td>
          <td class="money expense">${formatMoney(account.expenseTotal, document.currency)}</td>
        </tr>
      `
    )
    .join("");
  const eventRows = report.events
    .map(
      (event) => `
        <tr>
          <td>${formatDateTime(event.createdAt)}</td>
          <td>${escapeHtml(approvalEventText(event.eventType))}</td>
          <td>${event.note ? escapeHtml(event.note) : "—"}</td>
        </tr>
      `
    )
    .join("");

  const html = `<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${escapeHtml(report.title)} · FinDesk</title>
    <style>
      :root {
        color-scheme: light;
        --bg: #f4f7fb;
        --ink: #061426;
        --muted: #66748a;
        --line: #d7e0eb;
        --panel: #ffffff;
        --soft: #f7fafc;
        --blue: #1677f2;
        --green: #07884f;
        --red: #e03131;
      }
      * { box-sizing: border-box; }
      body {
        background: var(--bg);
        color: var(--ink);
        font-family: Arial, Helvetica, sans-serif;
        line-height: 1.35;
        margin: 0;
        padding: 24px;
      }
      main {
        display: grid;
        gap: 16px;
        margin: 0 auto;
        max-width: 1060px;
      }
      .toolbar {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
      }
      .toolbar a,
      .toolbar button {
        align-items: center;
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 8px;
        color: var(--ink);
        cursor: pointer;
        display: inline-flex;
        font: inherit;
        font-weight: 900;
        justify-content: center;
        min-height: 38px;
        padding: 0 12px;
        text-decoration: none;
      }
      .toolbar button.primary {
        background: var(--blue);
        border-color: var(--blue);
        color: #fff;
      }
      header,
      section {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 8px;
      }
      header {
        display: grid;
        gap: 16px;
        grid-template-columns: 1fr auto;
        padding: 18px;
      }
      h1, h2, p { margin: 0; }
      h1 { font-size: 30px; line-height: 1.08; }
      h2 { font-size: 17px; }
      .muted { color: var(--muted); font-weight: 700; }
      .badge {
        align-self: start;
        border: 1px solid #9ed5cd;
        border-radius: 4px;
        color: #087568;
        font-size: 12px;
        font-weight: 900;
        padding: 4px 7px;
      }
      .totals {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(4, minmax(130px, 1fr));
      }
      .total {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 8px;
        display: grid;
        gap: 4px;
        padding: 14px;
      }
      small {
        color: var(--muted);
        font-size: 12px;
        font-weight: 800;
      }
      strong, b { font-weight: 900; }
      .money { text-align: right; white-space: nowrap; }
      .income { color: var(--green); }
      .expense { color: var(--red); }
      section {
        display: grid;
        gap: 10px;
        padding: 14px;
      }
      table {
        border-collapse: collapse;
        width: 100%;
      }
      th, td {
        border-top: 1px solid var(--line);
        padding: 9px 10px;
        text-align: left;
        vertical-align: top;
      }
      th {
        background: var(--soft);
        color: var(--muted);
        font-size: 12px;
        font-weight: 900;
      }
      .num {
        color: #098b7b;
        font-weight: 900;
        width: 52px;
      }
      .category {
        border: 1px solid var(--line);
        border-radius: 8px;
        overflow: hidden;
      }
      .category + .category { margin-top: 8px; }
      .category summary {
        align-items: center;
        cursor: pointer;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        list-style: none;
        min-height: 48px;
        padding: 10px 12px;
      }
      .category summary::-webkit-details-marker { display: none; }
      .category summary span {
        display: grid;
        gap: 2px;
      }
      .event-table td:first-child { width: 180px; white-space: nowrap; }
      @media print {
        body { background: #fff; padding: 0; }
        main { max-width: none; }
        .toolbar { display: none; }
        header, section, .total, .category { break-inside: avoid; }
      }
      @media (max-width: 720px) {
        body { padding: 10px; }
        header { grid-template-columns: 1fr; }
        .totals { grid-template-columns: 1fr 1fr; }
        h1 { font-size: 24px; }
        th, td { padding: 8px 7px; }
      }
    </style>
  </head>
  <body>
    <main>
      <nav class="toolbar" aria-label="Действия с отчетом">
        <button type="button" onclick="document.querySelectorAll('details').forEach((node) => { node.open = true; });">Раскрыть</button>
        <button type="button" onclick="document.querySelectorAll('details').forEach((node) => { node.open = false; });">Свернуть</button>
        <a href="/workspaces/${encodeURIComponent(workspaceId)}/reports/${encodeURIComponent(report.id)}/excel">Excel</a>
        <button class="primary" type="button" onclick="window.print();">Печать / PDF</button>
      </nav>
      <header>
        <div>
          <p class="muted">FinDesk · ${escapeHtml(document.name)} · ${formatDateOnly(report.periodStart)} — ${formatDateOnly(report.periodEnd)}</p>
          <h1>${escapeHtml(report.title)}</h1>
          <p class="muted">${roleLabels[document.role] ?? document.role} · ${document.currency} · ${report.entryCount} строк</p>
        </div>
        <span class="badge">${reportStatusText(report.status)}</span>
      </header>
      <div class="totals" aria-label="Итоги отчета">
        <div class="total"><small>Приход</small><strong class="income">${formatMoney(report.incomeTotal, document.currency)}</strong></div>
        <div class="total"><small>Расход</small><strong class="expense">${formatMoney(report.expenseTotal, document.currency)}</strong></div>
        <div class="total"><small>Итог</small><strong>${formatMoney(report.netTotal, document.currency)}</strong></div>
        <div class="total"><small>Проверка</small><strong>${report.reviewCount}</strong></div>
      </div>
      ${
        eventRows
          ? `<section>
              <h2>История</h2>
              <table class="event-table">
                <thead>
                  <tr>
                    <th>Дата</th>
                    <th>Действие</th>
                    <th>Заметка</th>
                  </tr>
                </thead>
                <tbody>${eventRows}</tbody>
              </table>
            </section>`
          : ""
      }
      <section>
        <h2>Счета</h2>
        <table>
          <thead>
            <tr>
              <th>Счет</th>
              <th>Строк</th>
              <th>Приход</th>
              <th>Расход</th>
            </tr>
          </thead>
          <tbody>${accountRows}</tbody>
        </table>
      </section>
      <section>
        <h2>Категории</h2>
        ${categoryBlocks || "<p class=\"muted\">Категорий в отчете нет.</p>"}
      </section>
    </main>
  </body>
</html>`;

  return new Response(html, {
    headers: {
      "Content-Type": "text/html; charset=utf-8"
    }
  });
}
