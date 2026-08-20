"use client";

import { useState } from "react";
import type { OperationalEntry } from "@/lib/workspace-data";

type ActiveZone = "journal" | "structure";

type SyncedLedgerTableProps = {
  entries: OperationalEntry[];
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

function zoneClassName(activeZone: ActiveZone, entryCount: number) {
  return [
    "synced-table",
    entryCount === 0 ? "is-empty" : "",
    activeZone === "structure" ? "structure-active" : "journal-active"
  ]
    .filter(Boolean)
    .join(" ");
}

export function SyncedLedgerTable({ entries }: SyncedLedgerTableProps) {
  const [activeZone, setActiveZone] = useState<ActiveZone>("journal");

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
        <span>{activeZone === "journal" ? `${entries.length} записей` : "та же строка"}</span>
      </div>
      <div className={zoneClassName(activeZone, entries.length)} role="table">
        <div className="synced-row zone-head" role="row">
          <button
            aria-pressed={activeZone === "journal"}
            className="zone-title"
            onClick={() => setActiveZone("journal")}
            type="button"
          >
            <span className="zone-title-main">Оперативный журнал</span>
            <small>{entries.length} записей</small>
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
        {entries.length > 0 ? (
          entries.map((entry) => (
            <div className="synced-row" role="row" key={entry.id}>
              <span onClick={() => setActiveZone("journal")}>{entry.rowNo}</span>
              <strong onClick={() => setActiveZone("journal")}>{entry.rawText}</strong>
              <span
                className={entry.direction === "income" ? "amount-income" : "amount-expense"}
                onClick={() => setActiveZone("journal")}
              >
                {formatAmount(entry.amount, entry.direction)}
              </span>
              <span onClick={() => setActiveZone("structure")}>{entry.rowNo}</span>
              <span onClick={() => setActiveZone("structure")}>{entry.occurredOn}</span>
              <span
                className={entry.reviewStatus === "review" || entry.status === "needs_review" ? "status-pill attention" : "status-pill"}
                onClick={() => setActiveZone("structure")}
              >
                {reviewStatusText(entry.reviewStatus, entry.status)}
              </span>
            </div>
          ))
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
