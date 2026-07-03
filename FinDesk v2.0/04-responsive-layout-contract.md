# 04 — Responsive Layout Contract

## Core rule

One screen = one logic.

The page itself must not scroll. Scroll is allowed only inside controlled internal areas.

Allowed scroll containers:

```text
EventFeed
DetailPanelBody
ReportBody
AttachmentList
WorkspaceList
```

Forbidden:

```text
body scroll
page scroll
long landing layout
forms below the fold
hidden input after keyboard opens
```

## Device classes

FinDesk v2.0 must not be a desktop screen simply squeezed into a phone.

There are two main interaction systems:

1. Mobile financial-notes system — phone and iPad mini.
2. Full workspace system — desktop and iPad 11+.

## Mobile financial-notes system

Applies to:

```text
iPhone portrait
iPhone landscape
iPad mini portrait
iPad mini landscape
small Android phones/tablets with similar usable width
```

Main idea:

- user writes financial notes;
- vertical movement scrolls the current-month note feed;
- horizontal movement reveals the structured/report-ready view of those same notes.

## Full workspace system

Applies to:

```text
desktop/laptop monitors
iPad 11+ portrait
iPad 11+ landscape
iPad Pro portrait
iPad Pro landscape
large Android tablets with similar usable width
```

Main idea:

- use the full available screen;
- do not shrink desktop into a mobile column;
- show more context at once;
- keep reading and writing comfortable.

## Main journal screen

The main screen is a current-month notes-style financial feed.

The user sees existing records while entering new ones.

Desktop/full workspace structure:

```text
AppShell
 ├─ TopBar
 ├─ WorkspaceSummary
 ├─ MainArea
 │   ├─ LeftRail
 │   ├─ EventFeed
 │   └─ DetailPanel
 └─ InputBar
```

## WorkspaceSummary

Shows cash now, card expense, commercial income, other expenses review amount/count, and active month total.

On mobile it collapses to one or two lines.

## InputBar

Always accessible inside the screen.

Desktop / iPad 11+:

```text
[Cash v] +1000 снял с карты [attach] [enter]
```

Phone / iPad mini:

```text
+1000 снял с карты
```

## Mobile horizontal-swipe model

Vertical movement:

```text
scroll current-month financial notes feed
```

Horizontal movement:

```text
switch between note view and structured/report-ready view
```

## Mobile view A — Note feed

The user writes and reads financial notes.

## Mobile view B — Structured rows

The same records are shown as report-ready structured rows.

```text
03.07 | Cash | -250 | provisions | рыба | 16 012
03.07 | Card | -60 | media_comms | Netflix | —
```

## Full workspace structured view

On desktop and iPad 11+, show structured information through panels or split sections.

The large screen should feel richer, not like a stretched phone.

## Acceptance notes

A valid implementation must pass:

1. Desktop uses full monitor width.
2. iPad 11+ landscape behaves close to desktop.
3. iPad 11+ portrait remains tablet workspace.
4. iPad mini uses mobile financial-notes system.
5. Phone vertical scrolls notes.
6. Phone horizontal swipe reveals structured/report-ready rows.
7. Input remains reachable with keyboard open.
8. No body/page scroll is introduced.
