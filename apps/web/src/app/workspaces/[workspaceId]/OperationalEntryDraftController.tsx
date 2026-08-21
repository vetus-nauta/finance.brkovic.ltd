"use client";

import { useEffect, useState } from "react";

type OperationalEntryDraftControllerProps = {
  draftKey: string;
  formId: string;
  submitFormIds?: string[];
  successSignal: boolean;
};

type EntryDraft = {
  occurredOn: string;
  rawText: string;
  savedAt: number;
};

const pendingSubmitKey = "findesk:v2:entry-draft:pending-submit";

function formatDraftTime(value: number) {
  return new Intl.DateTimeFormat("ru-RU", {
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(value));
}

function readDraft(key: string): EntryDraft | null {
  try {
    const value = window.localStorage.getItem(key);

    if (!value) {
      return null;
    }

    return JSON.parse(value) as EntryDraft;
  } catch {
    return null;
  }
}

function draftRowText(rawInput: HTMLInputElement) {
  if (rawInput.defaultValue && rawInput.value === rawInput.defaultValue) {
    return "";
  }

  return rawInput.value || "";
}

function updateDraftRows(dateInput: HTMLInputElement, rawInput: HTMLInputElement) {
  const rawText = draftRowText(rawInput);
  const dateText = dateInput.value || "—";

  document.querySelectorAll("[data-v2-draft-text]").forEach((node) => {
    node.textContent = rawText || "Новая запись";
  });

  document.querySelectorAll("[data-v2-check-draft-text]").forEach((node) => {
    node.textContent = rawText || "новая";
  });

  document.querySelectorAll("[data-v2-check-draft-date]").forEach((node) => {
    node.textContent = dateText;
  });
}

export function OperationalEntryDraftController({
  draftKey,
  formId,
  submitFormIds = [formId],
  successSignal
}: OperationalEntryDraftControllerProps) {
  const [note, setNote] = useState("");

  useEffect(() => {
    if (successSignal) {
      const submittedDraftKey = window.sessionStorage.getItem(pendingSubmitKey);

      if (submittedDraftKey) {
        window.localStorage.removeItem(submittedDraftKey);
        window.sessionStorage.removeItem(pendingSubmitKey);
      }
    }
  }, [successSignal]);

  useEffect(() => {
    const form = document.getElementById(formId) as HTMLFormElement | null;

    if (!form) {
      return;
    }

    const dateInput = form.elements.namedItem("occurredOn") as HTMLInputElement | null;
    const rawInput = form.elements.namedItem("rawText") as HTMLInputElement | null;

    if (!dateInput || !rawInput) {
      return;
    }

    const storedDraft = readDraft(draftKey);

    if (storedDraft?.rawText && rawInput.value !== storedDraft.rawText) {
      dateInput.value = storedDraft.occurredOn || dateInput.value;
      rawInput.value = storedDraft.rawText;
      setNote(`Восстановлен черновик ${formatDraftTime(storedDraft.savedAt)}`);
    } else if (storedDraft?.rawText) {
      setNote(`Есть черновик ${formatDraftTime(storedDraft.savedAt)}`);
    }

    let saveTimer = 0;

    const saveDraft = () => {
      updateDraftRows(dateInput, rawInput);
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(() => {
        const rawText = rawInput.value.trim();

        if (!rawText) {
          window.localStorage.removeItem(draftKey);
          setNote("");
          updateDraftRows(dateInput, rawInput);
          return;
        }

        const draft: EntryDraft = {
          occurredOn: dateInput.value,
          rawText,
          savedAt: Date.now()
        };

        window.localStorage.setItem(draftKey, JSON.stringify(draft));
        setNote(`Черновик сохранен ${formatDraftTime(draft.savedAt)}`);
      }, 900);
    };

    const markPendingSubmit = () => {
      window.sessionStorage.setItem(pendingSubmitKey, draftKey);
    };

    dateInput.addEventListener("input", saveDraft);
    rawInput.addEventListener("input", saveDraft);
    updateDraftRows(dateInput, rawInput);
    const submitForms = submitFormIds
      .map((submitFormId) => document.getElementById(submitFormId) as HTMLFormElement | null)
      .filter((submitForm): submitForm is HTMLFormElement => Boolean(submitForm));

    submitForms.forEach((submitForm) => submitForm.addEventListener("submit", markPendingSubmit));

    return () => {
      window.clearTimeout(saveTimer);
      dateInput.removeEventListener("input", saveDraft);
      rawInput.removeEventListener("input", saveDraft);
      submitForms.forEach((submitForm) => submitForm.removeEventListener("submit", markPendingSubmit));
    };
  }, [draftKey, formId, submitFormIds]);

  function clearDraft() {
    window.localStorage.removeItem(draftKey);
    setNote("");

    const form = document.getElementById(formId) as HTMLFormElement | null;
    const dateInput = form?.elements.namedItem("occurredOn") as HTMLInputElement | null;
    const rawInput = form?.elements.namedItem("rawText") as HTMLInputElement | null;

    if (dateInput) {
      dateInput.value = dateInput.defaultValue || dateInput.value;
    }

    if (rawInput) {
      rawInput.value = rawInput.defaultValue;
      rawInput.focus();
    }

    if (dateInput && rawInput) {
      updateDraftRows(dateInput, rawInput);
    }
  }

  if (!note) {
    return null;
  }

  return (
    <p className="entry-draft-note">
      <span>{note}</span>
      <button type="button" onClick={clearDraft}>
        Очистить
      </button>
    </p>
  );
}
