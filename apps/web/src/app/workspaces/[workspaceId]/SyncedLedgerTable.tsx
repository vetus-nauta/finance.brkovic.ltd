"use client";

import Link from "next/link";
import { useState } from "react";
import type { OperationalEntry, ReportSnapshotSummary } from "@/lib/workspace-data";

type ActiveZone = "journal" | "structure";

type SyncedLedgerTableProps = {
  accountCode: string;
  entries: OperationalEntry[];
  reports: ReportSnapshotSummary[];
  selectedEntryId?: string;
  workspacePath: string;
};

function formatAmount(amount: number | null, direction: string | null) {
  if (amount === null) {
    return "—";
  }

  const value = new Intl.NumberFormat("ru-RU", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount);

  return `${direction === "income" ? "+" : "-"}${value} €`;
}

function reviewStatusText(status: string | null, transactionStatus: string) {
  if (transactionStatus === "needs_review" || status === "review") {
    return "проверить";
  }

  return "принято";
}

function reportStatusText(status: string) {
  switch (status) {
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

function formatDate(value: string) {
  return new Intl.DateTimeFormat("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit"
  }).format(new Date(value));
}

function formatPeriod(start: string, end: string) {
  return `${formatDate(start)} — ${formatDate(end)}`;
}

function reportSortValue(report: ReportSnapshotSummary) {
  return `${report.periodStart}-${report.periodEnd}-${report.createdAt}`;
}

function zoneClassName(activeZone: ActiveZone, entryCount: number) {
  return [
    "synced-table",
    entryCount === 0 ? "is-empty" : "",
    activeZone === "structure" ? "structure-active" : "journal-active"
  ]
    .filter(Boolean)
    .join(" ");
}

export function SyncedLedgerTable({ accountCode, entries, reports, selectedEntryId, workspacePath }: SyncedLedgerTableProps) {
  const [activeZone, setActiveZone] = useState<ActiveZone>("journal");
  const sortedReports = [...reports]
    .filter((report) => report.sourceTransactionIds.length > 0)
    .sort((left, right) => reportSortValue(left).localeCompare(reportSortValue(right)));
  const reportByEntryId = new Map<string, ReportSnapshotSummary>();

  for (const report of sortedReports) {
    for (const entryId of report.sourceTransactionIds) {
      if (!reportByEntryId.has(entryId)) {
        reportByEntryId.set(entryId, report);
      }
    }
  }
  const emittedReportIds = new Set<string>();
  const displayRows: Array<
    | { type: "entry"; entry: OperationalEntry }
    | { type: "report"; report: ReportSnapshotSummary; visibleEntries: OperationalEntry[] }
  > = [];

  for (const entry of entries) {
    const report = reportByEntryId.get(entry.id);

    if (!report) {
      displayRows.push({ type: "entry", entry });
      continue;
    }

    if (!emittedReportIds.has(report.id)) {
      emittedReportIds.add(report.id);
      displayRows.push({
        type: "report",
        report,
        visibleEntries: entries.filter((item) => report.sourceTransactionIds.includes(item.id))
      });
    }
  }

  function entryHref(entryId: string) {
    return `${workspacePath}?account=${encodeURIComponent(accountCode)}&edit=${encodeURIComponent(entryId)}`;
  }

  function reportHref(reportId: string) {
    return `${workspacePath}?mode=reports&account=${encodeURIComponent(accountCode)}&report=${encodeURIComponent(reportId)}`;
  }

  return (
    <div className="ledger-panel">
      <div className="mobile-zone-switch" aria-label="Вид журнала">
        <button
          aria-pressed={activeZone === "journal"}
          onClick={() => setActiveZone("journal")}
          type="button"
        >
          Журнал
        </button>
        <button
          aria-pressed={activeZone === "structure"}
          onClick={() => setActiveZone("structure")}
          type="button"
        >
          Проверка
        </button>
        <span>{activeZone === "journal" ? `${displayRows.length} строк` : "та же строка"}</span>
      </div>
      <div className={zoneClassName(activeZone, displayRows.length)} role="table">
        <div className="synced-row zone-head" role="row">
          <button
            aria-pressed={activeZone === "journal"}
            className="zone-title"
            onClick={() => setActiveZone("journal")}
            type="button"
          >
            <span className="zone-title-main">Оперативный журнал</span>
            <small>{displayRows.length} строк</small>
          </button>
          <button
            aria-pressed={activeZone === "structure"}
            className="zone-title"
            onClick={() => setActiveZone("structure")}
            type="button"
          >
            <span className="zone-title-main">Структурная проверка</span>
            <small>та же строка</small>
          </button>
        </div>
        <div className="synced-row synced-head" role="row">
          <span onClick={() => setActiveZone("journal")}>№</span>
          <span onClick={() => setActiveZone("journal")}>Описание</span>
          <span onClick={() => setActiveZone("journal")}>Сумма</span>
          <span onClick={() => setActiveZone("structure")}>№</span>
          <span onClick={() => setActiveZone("structure")}>Дата</span>
          <span onClick={() => setActiveZone("structure")}>Проверка</span>
        </div>
        {displayRows.length > 0 ? (
          displayRows.map((row) => {
            if (row.type === "report") {
              const report = row.report;
              const rowNumbers = row.visibleEntries.map((entry) => entry.rowNo).sort((left, right) => left - right);
              const rowRange =
                rowNumbers.length > 1
                  ? `${rowNumbers[0]}-${rowNumbers[rowNumbers.length - 1]}`
                  : String(rowNumbers[0] ?? "—");
              const hiddenCount = Math.max(report.entryCount - row.visibleEntries.length, 0);
              const balanceLabel =
                report.endingCash === null
                  ? `Итог ${formatAmount(Math.abs(report.netTotal), report.netTotal >= 0 ? "income" : "expense")}`
                  : `Остаток ${formatAmount(report.endingCash, "income").replace("+", "")}`;

              return (
                <div className="synced-row report-ledger-row" role="row" key={`report:${report.id}`}>
                  <Link href={reportHref(report.id)} onClick={() => setActiveZone("journal")}>
                    {rowRange}
                  </Link>
                  <Link className="entry-description-link" href={reportHref(report.id)} onClick={() => setActiveZone("journal")}>
                    <strong>{report.title}</strong>
                    <small>
                      {formatPeriod(report.periodStart, report.periodEnd)} · {report.entryCount} строк
                      {hiddenCount > 0 ? ` · еще ${hiddenCount} вне счета` : ""}
                    </small>
                  </Link>
                  <Link href={reportHref(report.id)} onClick={() => setActiveZone("journal")}>
                    {balanceLabel}
                  </Link>
                  <Link href={reportHref(report.id)} onClick={() => setActiveZone("structure")}>
                    {rowRange}
                  </Link>
                  <Link href={reportHref(report.id)} onClick={() => setActiveZone("structure")}>
                    {formatPeriod(report.periodStart, report.periodEnd)}
                  </Link>
                  <Link className="status-pill" href={reportHref(report.id)} onClick={() => setActiveZone("structure")}>
                    {reportStatusText(report.status)}
                  </Link>
                </div>
              );
            }

            const entry = row.entry;

            return (
              <div
                className={entry.id === selectedEntryId ? "synced-row selected-row" : "synced-row"}
                role="row"
                key={entry.id}
              >
                <Link href={entryHref(entry.id)} onClick={() => setActiveZone("journal")}>
                  {entry.rowNo}
                </Link>
                <Link className="entry-description-link" href={entryHref(entry.id)} onClick={() => setActiveZone("journal")}>
                  <strong>{entry.rawText}</strong>
                </Link>
                <Link
                  className={entry.direction === "income" ? "amount-income" : "amount-expense"}
                  href={entryHref(entry.id)}
                  onClick={() => setActiveZone("journal")}
                >
                  {formatAmount(entry.amount, entry.direction)}
                </Link>
                <Link href={entryHref(entry.id)} onClick={() => setActiveZone("structure")}>
                  {entry.rowNo}
                </Link>
                <Link href={entryHref(entry.id)} onClick={() => setActiveZone("structure")}>
                  {entry.occurredOn}
                </Link>
                <Link
                  className={entry.reviewStatus === "review" || entry.status === "needs_review" ? "status-pill attention" : "status-pill"}
                  href={entryHref(entry.id)}
                  onClick={() => setActiveZone("structure")}
                >
                  {reviewStatusText(entry.reviewStatus, entry.status)}
                </Link>
              </div>
            );
          })
        ) : (
          <div className="synced-row empty-synced-row" role="row">
            <span onClick={() => setActiveZone("journal")} />
            <span className="ledger-empty" onClick={() => setActiveZone("journal")}>
              Нет записей
            </span>
            <span onClick={() => setActiveZone("journal")} />
            <span onClick={() => setActiveZone("structure")} />
            <span onClick={() => setActiveZone("structure")} />
            <span onClick={() => setActiveZone("structure")} />
          </div>
        )}
      </div>
    </div>
  );
}
