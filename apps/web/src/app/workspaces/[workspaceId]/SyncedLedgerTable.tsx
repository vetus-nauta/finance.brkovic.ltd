"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Fragment, useLayoutEffect, useMemo, useRef, useState } from "react";
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

function buildDisplayRows(entries: OperationalEntry[], reports: ReportSnapshotSummary[]) {
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
  const rows: Array<
    | { type: "entry"; entry: OperationalEntry }
    | { type: "report"; report: ReportSnapshotSummary; visibleEntries: OperationalEntry[] }
  > = [];

  for (const entry of entries) {
    const report = reportByEntryId.get(entry.id);

    if (!report) {
      rows.push({ type: "entry", entry });
      continue;
    }

    if (!emittedReportIds.has(report.id)) {
      emittedReportIds.add(report.id);
      rows.push({
        type: "report",
        report,
        visibleEntries: entries.filter((item) => report.sourceTransactionIds.includes(item.id))
      });
    }
  }

  return rows;
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
  const router = useRouter();
  const [activeZone, setActiveZone] = useState<ActiveZone>("journal");
  const [expandedReportIds, setExpandedReportIds] = useState<Set<string>>(new Set());
  const tableRef = useRef<HTMLDivElement | null>(null);
  const selectedRowRef = useRef<HTMLDivElement | null>(null);
  const ledgerEndRef = useRef<HTMLDivElement | null>(null);
  const didInitialScroll = useRef(false);
  const lastSelectedScrollId = useRef<string | null>(null);
  const displayRows = useMemo(() => buildDisplayRows(entries, reports), [entries, reports]);
  const draftRowNumber = useMemo(
    () => entries.reduce((maxRowNo, entry) => Math.max(maxRowNo, entry.rowNo), 0) + 1,
    [entries]
  );

  useLayoutEffect(() => {
    if (!selectedEntryId || lastSelectedScrollId.current === selectedEntryId) {
      return;
    }

    lastSelectedScrollId.current = selectedEntryId;

    const scrollToSelectedRow = () => {
      selectedRowRef.current?.scrollIntoView({ block: "center" });
    };

    scrollToSelectedRow();
    requestAnimationFrame(scrollToSelectedRow);
    const timers = [80, 260, 700].map((delay) => window.setTimeout(scrollToSelectedRow, delay));

    return () => {
      timers.forEach((timer) => window.clearTimeout(timer));
    };
  }, [displayRows.length, selectedEntryId]);

  useLayoutEffect(() => {
    if (didInitialScroll.current || displayRows.length === 0 || selectedEntryId) {
      return;
    }

    didInitialScroll.current = true;

    const scrollToInitialPosition = () => {
      const table = tableRef.current;

      if (table) {
        table.scrollTo({ top: table.scrollHeight });
        if (table.scrollTop > 0) {
          table.dataset.initialScrolled = "1";
        }
      }

      ledgerEndRef.current?.scrollIntoView({ block: "end" });
    };

    scrollToInitialPosition();
    requestAnimationFrame(scrollToInitialPosition);
    const timers = [80, 260, 700, 1400, 2600].map((delay) => window.setTimeout(scrollToInitialPosition, delay));

    return () => {
      timers.forEach((timer) => window.clearTimeout(timer));
    };
  }, [displayRows.length, selectedEntryId]);

  function entryHref(entryId: string) {
    return `${workspacePath}?account=${encodeURIComponent(accountCode)}&edit=${encodeURIComponent(entryId)}`;
  }

  function reportHref(reportId: string) {
    return `${workspacePath}?mode=reports&account=${encodeURIComponent(accountCode)}&report=${encodeURIComponent(reportId)}`;
  }

  function createEntryHref() {
    return `${workspacePath}?account=${encodeURIComponent(accountCode)}`;
  }

  function activateCreateDraftRow(surface: ActiveZone) {
    setActiveZone(surface);
    router.push(createEntryHref());

    window.requestAnimationFrame(() => {
      const input = document.querySelector<HTMLInputElement>("#operational-entry-form input[name='rawText']");
      input?.focus({ preventScroll: true });
      const end = input?.value.length ?? 0;
      input?.setSelectionRange?.(end, end);
    });
  }

  function toggleReport(reportId: string) {
    setExpandedReportIds((current) => {
      const next = new Set(current);

      if (next.has(reportId)) {
        next.delete(reportId);
      } else {
        next.add(reportId);
      }

      return next;
    });
  }

  function renderEntryRow(entry: OperationalEntry, extraClassName = "") {
    return (
      <div
        className={[
          "synced-row",
          entry.id === selectedEntryId ? "selected-row" : "",
          extraClassName
        ].filter(Boolean).join(" ")}
        role="row"
        key={entry.id}
        ref={entry.id === selectedEntryId ? selectedRowRef : undefined}
      >
        <Link href={entryHref(entry.id)} onClick={() => setActiveZone("journal")}>
          {entry.rowNo}
        </Link>
        <Link className="entry-description-link" href={entryHref(entry.id)} onClick={() => setActiveZone("journal")}>
          <strong>{entry.rawText}</strong>
          {extraClassName ? <small>Строка внутри закрытого отчета</small> : null}
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
        <span>{activeZone === "journal" ? `${entries.length} строк` : "та же строка"}</span>
      </div>
      <div
        className={zoneClassName(activeZone, displayRows.length)}
        data-ledger-scroll="initial"
        ref={tableRef}
        role="table"
        suppressHydrationWarning
      >
        <div className="synced-row zone-head" role="row">
          <button
            aria-pressed={activeZone === "journal"}
            className="zone-title"
            onClick={() => setActiveZone("journal")}
            type="button"
          >
            <span className="zone-title-main">Оперативный журнал</span>
            <small>{entries.length} строк</small>
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
              const expanded = expandedReportIds.has(report.id);
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
                <Fragment key={`report:${report.id}`}>
                  <div
                    className={expanded ? "synced-row report-ledger-row is-expanded" : "synced-row report-ledger-row"}
                    role="row"
                  >
                    <button
                      aria-expanded={expanded}
                      className="report-row-toggle"
                      onClick={() => {
                        setActiveZone("journal");
                        toggleReport(report.id);
                      }}
                      title={expanded ? "Свернуть строки отчета" : "Раскрыть строки отчета"}
                      type="button"
                    >
                      <span aria-hidden="true">{expanded ? "▾" : "▸"}</span>
                      {rowRange}
                    </button>
                    <button
                      aria-expanded={expanded}
                      className="entry-description-link report-row-title-button"
                      onClick={() => {
                        setActiveZone("journal");
                        toggleReport(report.id);
                      }}
                      type="button"
                    >
                      <strong>{report.title}</strong>
                      <small>
                        {formatPeriod(report.periodStart, report.periodEnd)} · {report.entryCount} строк
                        {hiddenCount > 0 ? ` · еще ${hiddenCount} вне счета` : ""}
                      </small>
                    </button>
                    <button
                      aria-expanded={expanded}
                      className="report-row-balance-button"
                      onClick={() => {
                        setActiveZone("journal");
                        toggleReport(report.id);
                      }}
                      type="button"
                    >
                      {balanceLabel}
                    </button>
                    <button
                      aria-expanded={expanded}
                      className="report-row-toggle"
                      onClick={() => {
                        setActiveZone("structure");
                        toggleReport(report.id);
                      }}
                      title={expanded ? "Свернуть строки отчета" : "Раскрыть строки отчета"}
                      type="button"
                    >
                      <span aria-hidden="true">{expanded ? "▾" : "▸"}</span>
                      {rowRange}
                    </button>
                    <button
                      aria-expanded={expanded}
                      className="report-row-title-button"
                      onClick={() => {
                        setActiveZone("structure");
                        toggleReport(report.id);
                      }}
                      type="button"
                    >
                      {formatPeriod(report.periodStart, report.periodEnd)}
                    </button>
                    <span className="report-row-status-cell">
                      <span className="status-pill">{reportStatusText(report.status)}</span>
                      <Link href={reportHref(report.id)}>Открыть</Link>
                    </span>
                  </div>
                  {expanded ? row.visibleEntries.map((entry) => renderEntryRow(entry, "report-child-row")) : null}
                </Fragment>
              );
            }

            const entry = row.entry;
            return renderEntryRow(entry);
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
        <button
          aria-label={`Новая запись, строка ${draftRowNumber}`}
          aria-live="polite"
          className="synced-row new-entry-row"
          data-v2-draft-row
          data-v2-row-number={draftRowNumber}
          onClick={() => activateCreateDraftRow("journal")}
          role="row"
          type="button"
        >
          <span className="draft-row-number" data-v2-row-number-label>
            {draftRowNumber}
          </span>
          <span className="entry-description-link">
            <strong data-v2-draft-text>Новая запись</strong>
          </span>
          <span className="amount-pending" data-v2-draft-amount>
            —
          </span>
          <span className="draft-row-number" data-v2-row-number-label>
            {draftRowNumber}
          </span>
          <span data-v2-check-draft-date>—</span>
          <span className="status-pill muted-pill" data-v2-check-draft-text>
            новая
          </span>
        </button>
        <div className="ledger-end-anchor" ref={ledgerEndRef} aria-hidden="true" />
      </div>
    </div>
  );
}
