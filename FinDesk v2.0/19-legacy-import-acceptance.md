# 19 — Legacy Import Acceptance

## Purpose

Legacy import must be auditable and gradual.

Do not import all history at once before one-file import is proven.

## One-file import acceptance

A one-file import is accepted only if report shows:

```text
source file name
source file id/url if available
sheets scanned
rows scanned
rows parsed
entries created
rows ignored
rows unrecognized
summary rows ignored
cash income total
cash expense total
card income total
card expense total
source total comparison
```

## Include/exclude report

Importer must report:

```text
files detected
files included
files excluded
reason for exclusion
duplicate suspects
final-version decisions
```

## Exclusion markers

Exclude by default:

```text
не отправлял
не отправлено
не готово
не закончен
не закончено
не полный
неполный
черновик
draft
test
```

Admin can manually override.

## Date acceptance

Importer must show how date was chosen:

```text
row date
inherited previous row date
filename date
file updated date fallback
```

Row date has priority.

## Source traceability

Each imported entry must preserve:

```text
import_source_id
sheet_name
row_number
raw row data
entry_id
parse status
```

## Duplicate suspicion

Importer must mark suspected duplicates, not delete them silently.

## Full archive import acceptance

Full archive import can start only after:

- one-file import passes;
- category mapping has acceptable accuracy;
- duplicate detection exists;
- import review screen exists;
- Director approves full archive scan.
