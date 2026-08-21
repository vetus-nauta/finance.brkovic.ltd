import type {
  ReportSnapshotSummary,
  WorkspaceReportDocument,
  WorkspaceReportPackageDocument
} from "@/lib/workspace-data";

type ExcelValue = string | number | null | undefined;

type ExcelCell = {
  value: ExcelValue;
  type?: "String" | "Number";
  style?: string;
};

function escapeXml(value: string) {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");
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

function directionText(direction: string | null) {
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
    case "report_package_created":
      return "Пакет создан";
    case "report_package_sent":
      return "Пакет отправлен";
    case "report_package_accepted":
      return "Пакет принят";
    default:
      return eventType;
  }
}

function cell(input: ExcelValue | ExcelCell): string {
  const normalized =
    typeof input === "object" && input !== null && "value" in input
      ? input
      : {
          value: input
        };
  const value = normalized.value;

  if (value === null || value === undefined || value === "") {
    return `<Cell${normalized.style ? ` ss:StyleID="${normalized.style}"` : ""}/>`;
  }

  const numberValue = typeof value === "number" ? value : Number.NaN;
  const type = normalized.type ?? (Number.isFinite(numberValue) ? "Number" : "String");
  const stringValue = type === "Number" ? String(numberValue) : escapeXml(String(value));

  return `<Cell${normalized.style ? ` ss:StyleID="${normalized.style}"` : ""}><Data ss:Type="${type}">${stringValue}</Data></Cell>`;
}

function row(cells: Array<ExcelValue | ExcelCell>, style = "") {
  const attrs = style ? ` ss:StyleID="${style}"` : "";
  return `<Row${attrs}>${cells.map((value) => cell(value)).join("")}</Row>`;
}

function worksheet(name: string, rows: string[]) {
  return `
    <Worksheet ss:Name="${escapeXml(name.slice(0, 31))}">
      <Table>${rows.join("")}</Table>
    </Worksheet>`;
}

function workbook(sheets: string[]) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook
  xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Styles>
    <Style ss:ID="head"><Font ss:Bold="1"/><Interior ss:Color="#EEF3F8" ss:Pattern="Solid"/></Style>
    <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14"/></Style>
    <Style ss:ID="money"><NumberFormat ss:Format="#,##0.00"/></Style>
  </Styles>
  ${sheets.join("")}
</Workbook>`;
}

function amount(value: number | null): ExcelCell {
  return {
    value: value ?? null,
    style: "money",
    type: value === null ? "String" : "Number"
  };
}

function reportSummaryRows(report: ReportSnapshotSummary, currency: string) {
  return [
    row([{ value: report.title, style: "title" }]),
    row(["Период", `${formatDateOnly(report.periodStart)} - ${formatDateOnly(report.periodEnd)}`]),
    row(["Статус", reportStatusText(report.status)]),
    row(["Валюта", currency]),
    row(["Строк", report.entryCount]),
    row(["Приход", amount(report.incomeTotal)]),
    row(["Расход", amount(report.expenseTotal)]),
    row(["Итог", amount(report.netTotal)]),
    row(["На проверке", report.reviewCount])
  ];
}

function reportAccountsRows(report: ReportSnapshotSummary) {
  return [
    row(["Счет", "Строк", "Приход", "Расход"], "head"),
    ...report.accounts.map((account) =>
      row([account.label, account.entryCount, amount(account.incomeTotal), amount(account.expenseTotal)])
    )
  ];
}

function reportCategoriesRows(report: ReportSnapshotSummary) {
  return [
    row(["Категория", "Тип", "Строк", "Проверка", "Сумма"], "head"),
    ...report.categories.map((category) =>
      row([
        category.label,
        directionText(category.direction),
        category.entryCount,
        category.reviewCount,
        amount(category.total)
      ])
    )
  ];
}

function reportEntriesRows(report: ReportSnapshotSummary, includeReportTitle = false) {
  return [
    row(
      [
        ...(includeReportTitle ? ["Отчет"] : []),
        "№",
        "Дата",
        "Запись",
        "Тип",
        "Категория",
        "Сумма",
        "Проверка"
      ],
      "head"
    ),
    ...report.entries.map((entry) =>
      row([
        ...(includeReportTitle ? [report.title] : []),
        entry.rowNo,
        formatDateOnly(entry.occurredOn),
        entry.rawText,
        directionText(entry.direction),
        entry.categoryLabel,
        amount(entry.amount),
        entry.reviewStatus === "accepted" ? "Принято" : "Проверка"
      ])
    )
  ];
}

function reportEventsRows(report: ReportSnapshotSummary, includeReportTitle = false) {
  return [
    row([...(includeReportTitle ? ["Отчет"] : []), "Дата", "Действие", "Заметка"], "head"),
    ...report.events.map((event) =>
      row([
        ...(includeReportTitle ? [report.title] : []),
        formatDateTime(event.createdAt),
        approvalEventText(event.eventType),
        event.note ?? ""
      ])
    )
  ];
}

export function reportExcelFilename(title: string) {
  const safeTitle = title
    .trim()
    .replace(/[\\/:*?"<>|]+/g, " ")
    .replace(/\s+/g, " ")
    .slice(0, 80)
    .trim();

  return `${safeTitle || "findesk-report"}.xls`;
}

export function reportSnapshotExcelXml(document: WorkspaceReportDocument) {
  const { report } = document;

  return workbook([
    worksheet("Итоги", reportSummaryRows(report, document.currency)),
    worksheet("Счета", reportAccountsRows(report)),
    worksheet("Категории", reportCategoriesRows(report)),
    worksheet("Лента", reportEntriesRows(report)),
    worksheet("История", reportEventsRows(report))
  ]);
}

export function reportPackageExcelXml(document: WorkspaceReportPackageDocument) {
  const incomeTotal = document.reports.reduce((sum, report) => sum + report.incomeTotal, 0);
  const expenseTotal = document.reports.reduce((sum, report) => sum + report.expenseTotal, 0);
  const netTotal = document.reports.reduce((sum, report) => sum + report.netTotal, 0);
  const entryCount = document.reports.reduce((sum, report) => sum + report.entryCount, 0);
  const reviewCount = document.reports.reduce((sum, report) => sum + report.reviewCount, 0);
  const packageEvents = [
    row(["Дата", "Действие", "Заметка"], "head"),
    ...document.reportPackage.events.map((event) =>
      row([formatDateTime(event.createdAt), approvalEventText(event.eventType), event.note ?? ""])
    )
  ];

  return workbook([
    worksheet("Итоги", [
      row([{ value: document.reportPackage.title, style: "title" }]),
      row(["Статус", reportStatusText(document.reportPackage.status)]),
      row(["Валюта", document.currency]),
      row(["Отчетов", document.reportPackage.reportCount]),
      row(["Строк", entryCount]),
      row(["Приход", amount(incomeTotal)]),
      row(["Расход", amount(expenseTotal)]),
      row(["Итог", amount(netTotal)]),
      row(["На проверке", reviewCount])
    ]),
    worksheet("Отчеты", [
      row(["Отчет", "Период", "Строк", "Приход", "Расход", "Итог", "Проверка", "Статус"], "head"),
      ...document.reports.map((report) =>
        row([
          report.title,
          `${formatDateOnly(report.periodStart)} - ${formatDateOnly(report.periodEnd)}`,
          report.entryCount,
          amount(report.incomeTotal),
          amount(report.expenseTotal),
          amount(report.netTotal),
          report.reviewCount,
          reportStatusText(report.status)
        ])
      )
    ]),
    worksheet("Категории", [
      row(["Отчет", "Категория", "Тип", "Строк", "Проверка", "Сумма"], "head"),
      ...document.reports.flatMap((report) =>
        report.categories.map((category) =>
          row([
            report.title,
            category.label,
            directionText(category.direction),
            category.entryCount,
            category.reviewCount,
            amount(category.total)
          ])
        )
      )
    ]),
    worksheet("Лента", document.reports.flatMap((report, index) => reportEntriesRows(report, true).slice(index === 0 ? 0 : 1))),
    worksheet("История", [
      ...packageEvents,
      ...document.reports.flatMap((report, index) => reportEventsRows(report, true).slice(index === 0 ? 0 : 1))
    ])
  ]);
}
