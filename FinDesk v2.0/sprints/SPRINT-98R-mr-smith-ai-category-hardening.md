# SPRINT-98R — Mr. Smith AI Category Hardening

Date: 2026-08-14

## Objective

Improve Mr. Smith classification using Claudia Z beta history without turning yacht-specific accidents into universal product truth.

## Director Rule

Financial chain remains the source of truth. Safe category changes may improve reporting labels, but must not change cash arithmetic.

## Agents

### Financial QA Inspector

Scope: arithmetic, lower-accounting leakage, report totals, category-impact risk.

Findings:
- Corrections without category must not appear as review debt.
- Accountable/debt wording is a semantic risk marker, not enough by itself to rewrite operational entries.
- `+500 мой долг` / `+500 долг за август` needs owner decision before moving out of non-commercial income.
- `+500 снял кеш с карты` and `+350 оплатил свои нужды с карты, положил кеш` are safe money-movement corrections.
- Evgenia accountable transition remains arithmetically clean.

### Linguistic Classification Inspector

Scope: Russian human-entry variants, noisy boat language, false category triggers.

Findings:
- Object noun must dominate weak action words: `доставка запчастей` is parts, not transport.
- Guest-trip support includes guest iPhone, hotels, masks/fins, water toys, musicians, scooters, paragliding.
- Crew category is for wages, tips, and people payments; `еда экипаж` is provisions.
- Generic `ЛВ` means guest cash issued only when it is the whole line or an explicit handoff.
- `цоги мар` is not a dictionary rule; keep as manual review unless rewritten by the user.

## Applied Safe Changes

Database:
- `еда экипаж` rows moved from crew to provisions.
- `надувные игрушки`, `маски ласты`, `оплата отеля хвар` moved to guest-trip support.
- `доставка запчасти`, `доставка запчастей` moved to tech parts.
- `доставка посуды лв` moved to transport expenses.
- `заказ одежда экипаж` moved to current boat expenses.
- Safe card/cash movements marked as `cash_topup_from_card`.

Code:
- Live preview and import dictionaries now share the same priority logic.
- `guest_cash_issued` no longer catches every text containing `ЛВ`; it requires an exact guest marker or explicit handoff.
- `correction` entries are excluded from category-review counts.
- Regression test added: `доставка запчастей` must classify as `tech_parts`.

## Evidence

- `bash scripts/v2_fixture_runner.sh` — PASS.
- `bash scripts/v2_http_api_smoke.sh` — PASS.
- `php scripts/v2_claudia_z_reconciliation_audit.php` — PASS, cash diff `0`.
- `php scripts/v2_smith_category_audit.php` — PASS, safe moves `0`, discuss `8` after applied cleanup.

Latest Claudia Z audit:
- Entries: `663`.
- Cash opening: `18196.87`.
- Cash computed/current: `3893.00`.
- Latest month: `2026-08`.
- Review count: `0`.

## Not Auto-Applied

These need owner confirmation:
- Seven debt/credit wording rows, including `-1000 последний кредит июль`, `+500 долг за август`, and five `+500 мой долг` rows.
- `+500 кеш с карты Александр`.
- Any future shop-name-only phrases such as `цоги мар` unless the user rewrites or confirms the meaning.

## Decision

SPRINT-98R is accepted locally as a Smith classification hardening step. It is not a product-final AI assistant; it is a safer dictionary and review engine increment.
