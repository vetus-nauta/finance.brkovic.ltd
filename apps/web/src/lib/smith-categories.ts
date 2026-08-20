export const smithCategoryOptions = [
  { code: "crew", label: "Экипаж" },
  { code: "commercial_income", label: "Коммерческий приход" },
  { code: "non_commercial_income", label: "Некоммерческое поступление" },
  { code: "dry_dock", label: "Сухой док" },
  { code: "berth", label: "Стоянка" },
  { code: "marina_ports", label: "Марины и портовые" },
  { code: "tech_parts", label: "Запчасти и сервис" },
  { code: "tender", label: "Тендер / тузик" },
  { code: "fuel", label: "Топливо" },
  { code: "provisions", label: "Продукты и гости" },
  { code: "guest_trip_support", label: "Обеспечение гостей в походе" },
  { code: "guest_cash_issued", label: "Выданные наличные гостям" },
  { code: "representation_expenses", label: "Представительские расходы" },
  { code: "interior", label: "Интерьер и быт" },
  { code: "cleaning", label: "Клининг и химия" },
  { code: "media_comms", label: "Мультимедиа и связь" },
  { code: "transport_expenses", label: "Транспортные расходы" },
  { code: "admin_legal", label: "Админка / документы" },
  { code: "current_boat_expenses", label: "Текущие лодочные расходы" },
  { code: "cash_topup_from_card", label: "Пополнение кеша с карты" },
  { code: "admin_debt", label: "Долг администратора" },
  { code: "lower_accounting", label: "Подотчет / долг" },
  { code: "other", label: "Разобрать вручную" }
] as const;

export type SmithCategoryCode = (typeof smithCategoryOptions)[number]["code"];

const smithCategoryLabels = new Map<string, string>(smithCategoryOptions.map((category) => [category.code, category.label]));
const smithCategoryCodes = new Set<string>(smithCategoryOptions.map((category) => category.code));

export function smithCategoryLabel(code: string | null) {
  if (!code) {
    return "Категория не выбрана";
  }

  return smithCategoryLabels.get(code) ?? "Категория не выбрана";
}

export function isSmithCategoryCode(code: string): code is SmithCategoryCode {
  return smithCategoryCodes.has(code);
}

export function smithReviewReasonForCategory(code: SmithCategoryCode) {
  if (code === "admin_debt") {
    return "admin_debt";
  }

  if (code === "lower_accounting" || code === "guest_cash_issued") {
    return "lower_accounting";
  }

  if (code === "other") {
    return "other_review";
  }

  return "accepted";
}
