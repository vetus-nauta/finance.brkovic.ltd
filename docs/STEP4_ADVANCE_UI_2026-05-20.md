# Step 4 - Accountable Money UI - 2026-05-20

## Goal

Add the first usable facade for Step 3 accountable money.

Step 3 created the backend flow. Step 4 exposes it in the PWA:

```text
Money tab -> choose group -> issue cash -> employee submits -> manager/admin accepts or returns
```

## UI Changes

New module tab:

```text
Money
```

New markup in:

```text
public/app.php
```

New client controller in:

```text
public/assets/app.js
```

New styles in:

```text
public/assets/app.css
```

## User Modes

`base` user:

- sees only own assigned accountable money in the selected group;
- sees received / spent / expected cash left;
- can enter real cash left and submit for moderation;
- can open own On the Go tape from the Money card.

`manager` user:

- sees group advances;
- can accept submitted/mismatched reports;
- can return reports for correction;
- cannot issue new cash unless group permissions later allow it.

`advanced` user:

- can issue accountable cash to group members;
- can moderate;
- sees group-level red-line cards.

## Visual Model

Pending accountable money uses a left status strip:

```text
red     = issued / submitted waiting work
orange  = returned or mismatch
green   = accepted / closed
```

Each card shows:

- issued amount;
- cash spent;
- card spent;
- expected cash left;
- record count;
- actual cash left and difference after submit.

## Verification

The local smoke test now also checks that Step 4 UI assets are present:

```bash
php scripts/local-smoke.php http://127.0.0.1:18888
```

Additional browser-level check used during this step:

```text
headless Chrome DOM load: OK
headless Chrome runtime exceptions: OK
```

## Known Next Step

The UI is functional foundation, not final polish.

Next layer should connect the Money cards more tightly with:

- group report view;
- notification/message when an employee submits or gets returned;
- final desktop/tablet/mobile refinement;
- later Captain Fin module integration.
