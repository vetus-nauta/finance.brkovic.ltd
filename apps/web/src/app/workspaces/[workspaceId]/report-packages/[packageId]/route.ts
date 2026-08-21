import { getWorkspaceReportPackage, roleLabels } from "@/lib/workspace-data";

type ReportPackageRouteProps = {
  params: Promise<{
    workspaceId: string;
    packageId: string;
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

function formatMoney(value: number, currency: string) {
  return new Intl.NumberFormat("ru-RU", {
    currency,
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
    style: "currency"
  }).format(value);
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

export async function GET(_request: Request, { params }: ReportPackageRouteProps) {
  const { workspaceId, packageId } = await params;
  const document = await getWorkspaceReportPackage(workspaceId, packageId);

  if (!document) {
    return new Response("Report package not found", { status: 404 });
  }

  const incomeTotal = document.reports.reduce((sum, report) => sum + report.incomeTotal, 0);
  const expenseTotal = document.reports.reduce((sum, report) => sum + report.expenseTotal, 0);
  const netTotal = document.reports.reduce((sum, report) => sum + report.netTotal, 0);
  const entryCount = document.reports.reduce((sum, report) => sum + report.entryCount, 0);
  const reviewCount = document.reports.reduce((sum, report) => sum + report.reviewCount, 0);
  const reportsHtml = document.reports
    .map((report) => {
      const categoriesHtml = report.categories
        .map((category) => {
          const rows = report.entries.filter(
            (entry) =>
              entry.categoryCode === category.code && (entry.direction ?? category.direction) === category.direction
          );
          const rowsHtml = rows
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
                <tbody>${rowsHtml}</tbody>
              </table>
            </details>
          `;
        })
        .join("");

      return `
        <section>
          <div class="section-head">
            <div>
              <h2>${escapeHtml(report.title)}</h2>
              <p class="muted">${formatDateOnly(report.periodStart)} — ${formatDateOnly(report.periodEnd)} · ${report.entryCount} строк</p>
            </div>
            <b>${formatMoney(report.netTotal, document.currency)}</b>
          </div>
          <div class="mini-totals">
            <span><small>Приход</small><strong class="income">${formatMoney(report.incomeTotal, document.currency)}</strong></span>
            <span><small>Расход</small><strong class="expense">${formatMoney(report.expenseTotal, document.currency)}</strong></span>
            <span><small>Проверка</small><strong>${report.reviewCount}</strong></span>
          </div>
          ${categoriesHtml || "<p class=\"muted\">Категорий в отчете нет.</p>"}
        </section>
      `;
    })
    .join("");

  const html = `<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${escapeHtml(document.reportPackage.title)} · FinDesk</title>
    <style>
      :root {
        color-scheme: light;
        --bg: #f4f7fb;
        --ink: #061426;
        --muted: #66748a;
        --line: #d7e0eb;
        --panel: #ffffff;
        --soft: #f7fafc;
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
        max-width: 1080px;
      }
      .toolbar {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
      }
      .toolbar button {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 8px;
        color: var(--ink);
        cursor: pointer;
        font: inherit;
        font-weight: 900;
        min-height: 38px;
        padding: 0 12px;
      }
      .toolbar button.primary {
        background: #1677f2;
        border-color: #1677f2;
        color: #fff;
      }
      header,
      section,
      .total {
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
      h2 { font-size: 18px; }
      .muted, small { color: var(--muted); font-weight: 800; }
      small { font-size: 12px; }
      .badge {
        align-self: start;
        border: 1px solid #9ed5cd;
        border-radius: 4px;
        color: #087568;
        font-size: 12px;
        font-weight: 900;
        padding: 4px 7px;
      }
      .totals,
      .mini-totals {
        display: grid;
        gap: 10px;
      }
      .totals { grid-template-columns: repeat(5, minmax(120px, 1fr)); }
      .mini-totals { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
      .total,
      .mini-totals span {
        border: 1px solid var(--line);
        border-radius: 8px;
        display: grid;
        gap: 4px;
        padding: 12px;
      }
      .mini-totals span { background: var(--soft); }
      .income { color: var(--green); }
      .expense { color: var(--red); }
      .money { text-align: right; white-space: nowrap; }
      section {
        display: grid;
        gap: 10px;
        padding: 14px;
      }
      .section-head {
        align-items: baseline;
        display: flex;
        gap: 12px;
        justify-content: space-between;
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
      @media print {
        body { background: #fff; padding: 0; }
        main { max-width: none; }
        .toolbar { display: none; }
        header, section, .total, .category { break-inside: avoid; }
      }
      @media (max-width: 760px) {
        body { padding: 10px; }
        header, .section-head { grid-template-columns: 1fr; }
        header, .section-head, .category summary { align-items: stretch; flex-direction: column; }
        .totals, .mini-totals { grid-template-columns: 1fr 1fr; }
        h1 { font-size: 24px; }
        th, td { padding: 8px 7px; }
      }
    </style>
  </head>
  <body>
    <main>
      <nav class="toolbar" aria-label="Действия с пакетом отчетов">
        <button type="button" onclick="document.querySelectorAll('details').forEach((node) => { node.open = true; });">Раскрыть</button>
        <button type="button" onclick="document.querySelectorAll('details').forEach((node) => { node.open = false; });">Свернуть</button>
        <button class="primary" type="button" onclick="window.print();">Печать / PDF</button>
      </nav>
      <header>
        <div>
          <p class="muted">FinDesk · ${escapeHtml(document.name)} · ${document.reportPackage.reportCount} отчетов</p>
          <h1>${escapeHtml(document.reportPackage.title)}</h1>
          <p class="muted">${roleLabels[document.role] ?? document.role} · ${document.currency}</p>
        </div>
        <span class="badge">${reportStatusText(document.reportPackage.status)}</span>
      </header>
      <div class="totals" aria-label="Итоги пакета отчетов">
        <div class="total"><small>Отчетов</small><strong>${document.reportPackage.reportCount}</strong></div>
        <div class="total"><small>Строк</small><strong>${entryCount}</strong></div>
        <div class="total"><small>Приход</small><strong class="income">${formatMoney(incomeTotal, document.currency)}</strong></div>
        <div class="total"><small>Расход</small><strong class="expense">${formatMoney(expenseTotal, document.currency)}</strong></div>
        <div class="total"><small>Итог</small><strong>${formatMoney(netTotal, document.currency)}</strong></div>
      </div>
      <div class="totals" aria-label="Проверка пакета">
        <div class="total"><small>Проверка</small><strong>${reviewCount}</strong></div>
      </div>
      ${reportsHtml || "<section><p class=\"muted\">В пакете пока нет отчетов.</p></section>"}
    </main>
  </body>
</html>`;

  return new Response(html, {
    headers: {
      "Content-Type": "text/html; charset=utf-8"
    }
  });
}
