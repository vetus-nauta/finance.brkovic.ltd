"use client";

import { useMemo, useState } from "react";
import { calculateQuickNoteTotal } from "@/lib/quick-note-totals";

type QuickNoteAction = (formData: FormData) => void | Promise<void>;

type QuickNoteComposerProps = {
  accountCode: string;
  currency: string;
  defaultBody: string;
  noteId: string;
  saveAction: QuickNoteAction;
  submitAction: QuickNoteAction;
  today: string;
};

function formatSignedMoney(value: number, currency: string) {
  return new Intl.NumberFormat("ru-RU", {
    currency,
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
    signDisplay: "exceptZero",
    style: "currency"
  }).format(value);
}

export function QuickNoteComposer({
  accountCode,
  currency,
  defaultBody,
  noteId,
  saveAction,
  submitAction,
  today
}: QuickNoteComposerProps) {
  const [body, setBody] = useState(defaultBody);
  const total = useMemo(() => calculateQuickNoteTotal(body), [body]);

  return (
    <form className="quick-note-form" action={saveAction}>
      <input type="hidden" name="account" value={accountCode} />
      <input type="hidden" name="noteId" value={noteId} />
      <label>
        <span>Дата для строк</span>
        <input type="date" name="occurredOn" defaultValue={today} required />
      </label>
      <label>
        <span>Текущая заметка</span>
        <textarea
          name="body"
          onChange={(event) => setBody(event.target.value)}
          placeholder={"+1000 поступило от судовладельца\n-350 продукты\n-100 стоянка в марине"}
          required
          value={body}
        />
      </label>
      <div className="quick-note-total" aria-live="polite">
        <span>Итог по текущим строкам</span>
        <strong>{formatSignedMoney(total, currency)}</strong>
      </div>
      <div className="notes-intake-grid" aria-label="Дополнительные способы ввода">
        <div>
          <strong>Документы</strong>
          <span>Фото чека, PDF, Excel, Word</span>
        </div>
        <div>
          <strong>Telegram и голос</strong>
          <span>Бот и голосовой ввод будут работать как такие же заметки</span>
        </div>
      </div>
      <div className="mode-actions">
        <button type="submit">Сохранить</button>
        <button className="primary-action" formAction={submitAction} type="submit">
          Отправить в журнал
        </button>
      </div>
    </form>
  );
}
