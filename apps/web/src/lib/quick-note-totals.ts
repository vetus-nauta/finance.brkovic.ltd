const leadingAmountPattern = /^\s*([+-])\s*(\d[\d\s\u00a0]*(?:[,.]\d+)?)\b/;

function normalizeAmount(value: string) {
  return Number(value.replace(/[\s\u00a0]/g, "").replace(",", "."));
}

export function calculateQuickNoteTotal(body: string) {
  return body
    .split(/\r?\n/)
    .reduce((total, line) => {
      const match = line.match(leadingAmountPattern);

      if (!match) {
        return total;
      }

      const amount = normalizeAmount(match[2]);

      if (!Number.isFinite(amount)) {
        return total;
      }

      return total + (match[1] === "+" ? amount : -amount);
    }, 0);
}
