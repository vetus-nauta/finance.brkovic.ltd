# Step 5 - Premium Shell and Style Pass - 2026-05-20

## Goal

Prepare the app for premium account features before live deployment, without blocking current testing access.

## Product Decision

Premium account features are introduced as a visible product zone, but access is not gated yet.

Current advanced tools stay open for testing.

## UI Changes

New module:

```text
Premium
```

New placeholders:

```text
Advanced Mode
Trip with Friends
Report Studio
```

`Trip with Friends` is a placeholder for:

```text
create a group of people
track the shared pot / contributed money
record trip expenses
equalize who owes whom after the trip
```

No backend tables were added in this step.

## Style Pass

First light premium pass:

- calmer light background;
- stronger card material;
- cleaner button depth;
- premium feature cards;
- navigation supports the extra Premium tab;
- all CSS `letter-spacing` values normalized to `0`;
- removed viewport-scaled hero font sizing.

## Verification

Checked locally:

```text
PHP lint: OK
JS syntax: OK
git diff --check: OK
local smoke 1-4 + Step 5 UI markers: OK
headless Chrome DOM: OK
headless Chrome runtime exceptions: OK
fresh migration: OK, 23 tables
```

## Next Layer

Before live upload:

- manual UI pass on phone/tablet/desktop;
- improve final visual density and spacing where needed;
- add real backend for Trip with Friends only after the shell is approved;
- keep Advanced open until we intentionally enable premium gating.
