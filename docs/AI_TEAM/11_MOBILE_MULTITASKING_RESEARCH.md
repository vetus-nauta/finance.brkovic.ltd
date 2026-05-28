# Mobile Multitasking Research

Date: 2026-05-26

Owner: Project Director

Status: research pack for Product Finance Architect and Frontend UX Engineer.

## Why This Exists

FinDesk is a multitasking finance product on a small screen:

- capture money while moving;
- review and submit reports;
- preserve proof;
- check messages;
- manage group money;
- print/export;
- archive;
- keep non-MVP work visible through `Advanced` without turning the phone UI into a desktop menu.

The mobile UI must not be a shrunken desktop.

## Sources Checked

- Live site `https://finance.brkovic.ltd/`: product promise is three layers: `On the Go`, `FinDesk`, `Advanced`.
- Apple Human Interface Guidelines:
  - tab bars;
  - toolbars;
  - menus;
  - sheets;
  - layout.
- Android / Material:
  - adaptive canonical layouts;
  - list-detail;
  - supporting pane;
  - navigation bar / rail / drawer adaptation.
- MDN PWA:
  - offline operation;
  - installable PWA;
  - service worker and local storage patterns.
- W3C WCAG 2.2:
  - target size minimum.
- Baymard mobile navigation benchmark:
  - mobile menus are hard; mobile navigation usually must differ from desktop.

## Patterns That Matter For FinDesk

### 1. Five Or Fewer Primary Mobile Entrances

Small screens cannot expose every module as a permanent top-level button.

FinDesk should preserve every module but make only the most frequent work always reachable.

Recommended mobile primary entrances:

- `На бегу` / capture;
- `FinDesk` / checking and reports;
- `Деньги` / group pot, cash, card, accountable money;
- `Группы` / participants and messages;
- `Advanced` or `Еще` / everything outside MVP, including deeper Business, Travel, analytics, settings.

This does not delete Business, Travel, Archive, or specialist tools. It puts non-MVP work in a stable reachable hub.

### 2. Navigation And Actions Must Be Separate

Navigation changes area.

Actions affect the current item.

For FinDesk:

- bottom/nav area: where the user is;
- sticky action strip: `+`, `-`, `Фото`, `Сдать`, `Печать`, `Экспорт`, `Сообщение`;
- per-card menus: edit, delete, return, accept, archive.

Do not put money actions into the main navigation.

### 3. Capture First, Complete Later

The fastest screen must let a moving person record the fact before the perfect form exists.

Minimum mobile capture:

- amount;
- cash/card/received/accountable type;
- participant/group;
- proof/photo;
- optional note;
- visible status: draft, needs proof, review, accepted.

The app must autosave this open session continuously. A refresh, lock screen, network change, or accidental navigation must not erase current rows, photos, proof, or comments.

### 4. Progressive Disclosure

Only show what is needed now.

Examples for FinDesk:

- tap amount row -> detail sheet;
- tap proof -> proof sheet;
- tap export -> export options sheet;
- tap group pot -> participants/cash/card/accountable breakdown;
- advanced filters stay collapsed until requested.

### 5. List-Detail Transformation

Phone:

- list screen first;
- opening a report/card replaces the list;
- back returns to the same list position and filters.

Tablet/desktop:

- list and detail can sit side by side.

This is directly relevant to Live Report cards, archived reports, group reports, messages, and Business Desk documents.

### 6. Supporting Pane Becomes Bottom Sheet On Phone

On wide screens, proof, messages, notes, and audit trail can be supporting panes.

On phones, they should become bottom sheets or full-screen detail panels.

Recommended phone mappings:

- report detail -> screen;
- proof -> sheet;
- group message thread -> sheet or detail screen;
- audit trail -> collapsed section;
- print/export options -> sheet.

### 7. Task Queue Beats Dashboard Overload

Small screen should show what needs action now:

- drafts needing proof;
- submitted reports waiting for review;
- returned reports;
- missing proof;
- current cash/card imbalance;
- unread group messages tied to money work.

The full dashboard can remain in Advanced.

### 8. Messages Are Context, Not A Separate Social App

Group messages should support money work:

- ask for missing receipt;
- explain a returned row;
- confirm a handoff;
- discuss a report before acceptance.

Messages need unread state and group scope, but business MVP does not require a full social chat product.

### 9. Travel Equalization Is A Separate Workspace

Travel / Trip with Friends should not be mixed with ordinary group cash reports.

It can reuse:

- group participants;
- shared pot;
- expenses;
- proof;
- final archive.

But the settlement logic is its own workspace: who owes whom after the trip.

### 10. Business Desk Is A Separate Workspace

Business Desk must stay outside the fast money flow.

Mobile minimum:

- company profile;
- clients;
- proformas;
- print / save to PDF path;
- clear separation from cash report formulas.

### 11. Field Combat Mode, Offline, And Loss Prevention

A field finance app must protect input when signal is bad.

Minimum direction:

- keep app shell available;
- keep unsent drafts locally;
- show sync state;
- never lose a typed report row because the network changed;
- never lose the current unfinished session because the user switched screen, locked the phone, refreshed, or came back later;
- autosave text, amount, stream, group, participant, photo, scan/proof, and note as separate recoverable units;
- calculate visible totals from the preserved local/server state, not only after final submit;
- make conflict/retry visible.

### 12. Touch And Reach Rules

Mobile controls must be reachable and stable:

- primary actions near thumb zone;
- no tiny adjacent destructive controls;
- minimum hit targets must satisfy accessibility requirements;
- prefer 44px+ practical targets for finance actions even if technical minimum is lower;
- no text overlap or layout shift when labels change language.

## Ready Patterns To Reuse

### Adaptive Navigation

Phone:

- bottom bar or compact top app bar with five or fewer primary entrances;
- `Еще` hub for secondary workspaces.

Tablet:

- navigation rail;
- list-detail screens.

Desktop:

- sidebar;
- multi-pane working views.

### Bottom Sheets

Use for:

- quick proof picker;
- export choice;
- message thread;
- filters;
- amount breakdown;
- report actions.

Do not stack multiple sheets.

### Workspaces Hub

`Еще` can contain:

- Архив;
- Advanced;
- Settings.

This is preservation, not hiding. The hub must be clear and stable.

Inside `Advanced`, group non-MVP work:

- Business Desk;
- Travel / Trip with Friends;
- AI / analytics;
- deeper admin tools;
- future integrations.

### Status Cards

Use card rows for active work:

- draft;
- needs proof;
- submitted;
- returned;
- accepted;
- archived.

Each card must show owner, amount, status, and next action.

### Print / Export Flow

On phone:

1. Open saved report.
2. Tap `Экспорт / Печать`.
3. Choose PDF/Excel/Google/print in a sheet.
4. Show saved/exported result and archive link.

Do not force the user to find printing inside a generic settings menu.

## FinDesk Mobile Product Shape

Recommended first mobile structure:

```text
На бегу
  quick capture
  active live card
  proof
  submit

FinDesk
  review queue
  current period
  closed reports
  save / print / export

Деньги
  group pot
  cash
  card
  accountable money
  carryover

Группы
  participants
  roles
  messages
  invites

Еще
  archive
  Advanced
    Business Desk if not MVP
    Travel / Trip with Friends if not MVP
    AI / analytics
    deeper admin tools
  settings
```

## Next Role Work

Product Finance Architect:

- decide what mobile entries are MVP minimum vs visible skeleton;
- decide whether Travel equalization is launch-critical or staged.
- classify everything outside the first business MVP into `Advanced`.
- define field combat mode acceptance terms.

Frontend UX Engineer:

- convert this research into a small-screen information architecture;
- propose phone/tablet/desktop screen map;
- do not remove modules;
- use `Advanced` / workspace hub only if all non-MVP modules remain findable;
- treat unfinished-session data loss as a blocker.

QA Release Engineer:

- later verify mobile flows on at least 390px width:
  - capture;
  - submit;
  - review;
  - save/print/export;
  - group messages;
  - archive;
  - unfinished-session recovery;
  - Business Desk print path if Business Desk is in MVP minimum.
