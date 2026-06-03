# FinDesk Product Bible Sprint 0 Route Map — 2026-06-03

## Purpose

Sprint 0 converts Product Bible V1 into an implementation map.

This is not a design sprint and not a code styling task. It defines the visible product route, the hidden engine layer, and the legacy UI isolation rules before Sprint 1 starts.

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

## Sprint 0 Decision

FinDesk must stop behaving like a patched legacy application.

The next implementation must expose the approved product path:

```text
Welcome Hall
  -> Work alone
  -> Solo Workspace
  -> Cash / Card Choice
  -> Live Journal
  -> Fixed Journal
  -> Reports

Welcome Hall
  -> Work with people
  -> Team Workspace
  -> Admin Card / Employee Card
  -> Cash / Card Choice
  -> Live Journal
  -> Fixed Journal
  -> Report Assembly
  -> Reports
```

Legacy modules may remain as engine support only. They must not be normal user navigation.

## Current Visible Product Screens

Current Phase/Product shell exists in:

```text
public/app.php
public/assets/app.js
public/assets/app.css
```

Current `data-phase-screen` screens:

| Screen key | Product name | Current status |
|---|---|---|
| `welcome` | Welcome Hall | Exists, but currently too dashboard-like and does not expose Product Bible start paths correctly |
| `solo` | Solo Workspace | Exists, shallow |
| `journal-choice` | Cash / Card Choice | Exists |
| `journal` | Live Journal | Exists, but still too form/report-like; records feed is not dominant enough |
| `team` | Team Workspace | Exists, but still uses money/remaining-first patterns |
| `admin` | Admin Card | Exists, but incomplete product behavior |
| `employee` | Employee Card | Exists, but pending transfer/confirmation is not first-class visually |
| `assembly` | Report Assembly | Exists, but too placeholder-level |
| `reports` | Reports | Exists, but not yet first-class saved report archive UX |
| `protected` | Protected Actions | Exists, but placeholder-level |

## Legacy UI Still Present In DOM

These modules are still physically present in `public/app.php`:

| Legacy module | DOM id | Current risk |
|---|---|---|
| Ledger | `moduleLedger` | Can revive old accounting/table behavior |
| On The Go | `moduleOnTheGo` | Can revive old live records screen and stream gate |
| Captain | `moduleCaptain` | Can revive old FinDesk board/card-heavy interface |
| Money | `moduleMoney` | Can revive old advances/dashboard interface |
| Premium | `modulePremium` | Non-MVP distraction |
| Groups | `moduleGroups` | Old group admin surface can bypass Team Workspace |
| Business | `moduleBusiness` | Non-MVP / ecosystem distraction |
| Settings | `moduleSettings` | Can become a second operational workspace |

These modules may stay in the codebase for compatibility during rebuild, but the new user path must not expose them as product screens.

## Legacy Route / State System Still Present

Current legacy module state is still supported by:

```text
QL_MODULE_STATE_ALLOWED = [
  'ontherun',
  'ledger',
  'reports',
  'captain',
  'money',
  'groups',
  'business',
  'premium',
  'settings'
]
```

Current risk:

- old `localStorage` state can restore legacy modules;
- old `data-module-tab` buttons can jump out of the new product path;
- multiple wrappers override `window.qlSetModule`;
- old `popstate` listeners still exist;
- browser Back can conflict between legacy module state and new Phase stack.

Sprint 1 must make the new Product Bible route the only normal visible route after login.

## Target Product Routes

Product route names for implementation:

| Product route | Required object | Main action |
|---|---|---|
| `welcome` | Product start | Choose path |
| `solo` | Solo workspace | Choose Cash/Card |
| `team` | People screen | Open person |
| `admin-card` | Administrator | Add money / issue money / review |
| `employee-card` | Employee | Confirm transfer / open journal |
| `stream-choice` | Money stream | Cash or Card |
| `live-journal` | Records feed | Add record / fix journal |
| `fixed-journal` | Fixed journal | View handoff result |
| `report-assembly` | Ready journals | Attach / return / save |
| `reports` | Saved reports | Open report |
| `protected-action` | Consequences | Reason + CONFIRM |
| `profile` | Service account | Language / install / logout |

Implementation can keep existing keys temporarily, but labels and behavior must follow these product routes.

## Hidden Engine Support

Allowed backend/API support:

| Engine area | Keep? | Reason |
|---|---|---|
| Auth email/code | Yes | Product Bible requires preserving existing auth |
| Groups/members | Yes | Team Workspace depends on it |
| `on_the_go_*` journal APIs | Yes | Current journal engine |
| attachments/proofs | Yes | Required for Live Journal attachments |
| `findesk_transfer_*` | Yes | First-class transfer offer/acceptance |
| `findesk_report_*` | Yes | First-class report assembly |
| `findesk_protected_action_*` | Yes | Protected actions |
| old `advance_*` | Compatibility only | Must not be visible as "Подотчеты" product path |
| old `ledger_group_*` exports | Compatibility only | Use behind Reports/export if still needed |

## Product Bible Route Rules

1. Welcome is not login wall and not dashboard.
2. Auth intercept returns to interrupted action.
3. Menu after login is limited to:

```text
Workspace
Reports
Language
Profile / Account
Logout
```

4. Team Workspace is people-first.
5. Admin Card is the only place for adding money and issuing money.
6. Employee Card must show pending transfer before journal access.
7. Cash/Card choice is mandatory before Live Journal.
8. Live Journal is records-feed-first.
9. Reports are not inside Live Journal.
10. Dangerous changes go through Protected Action.
11. Back uses real navigation stack.
12. Old modules must not be reachable from visible product navigation.

## Sprint 1 Implementation Brief

Sprint 1 target: New Welcome + Product Shell.

Files expected:

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
```

Required outcome:

- replace the current Welcome with Product Bible Welcome Hall;
- show three start paths:
  - `Работаю один`;
  - `Работаю с людьми`;
  - `Готовые шаблоны`;
- remove dashboard-like money strips from Welcome;
- keep auth panel available, but do not make auth the first product screen;
- implement or preserve interrupted-action auth return;
- restrict visible menu to Product Bible items;
- block legacy module restoration from becoming the normal first screen;
- keep old modules hidden as compatibility support.

Sprint 1 is complete only when:

- opening `/app.php` shows Welcome Hall, not old FinDesk board;
- old modules are not visible from the main menu;
- Back from inner product screens follows stack;
- user can choose Solo or Team without seeing legacy labels;
- no `Nav Desk`, `Ops`, `Подотчеты`, `Детали`, old `Сотрудники и группы`, or old `Настройки` are visible in the normal product path.

## QA Checklist For Sprint 0 Closure

- [x] Product Bible V1 accepted as highest source.
- [x] Current product screens identified.
- [x] Legacy DOM modules identified.
- [x] Legacy route/state risks identified.
- [x] Target product routes defined.
- [x] Hidden engine support boundary defined.
- [x] Sprint 1 implementation brief written.

## Director Conclusion

Sprint 0 is closed when this document is accepted.

Next action: implement Sprint 1 locally. Do not start by styling the old screens. Start by making the product route impossible to confuse with legacy FinDesk.
