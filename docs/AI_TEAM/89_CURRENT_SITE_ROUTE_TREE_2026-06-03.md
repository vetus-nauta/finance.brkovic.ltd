# Current Site Route Tree - FinDesk - Updated 2026-06-04

## Source

This document describes the current local app route structure as implemented in:

```text
public/app.php
public/assets/app.js
public/api.php
app/findesk_phase2.php
app/yacht_provisioning.php
app/yacht_prices.php
```

Local start:

```text
http://127.0.0.1:18889/app.php
```

Important: Product screens are not separate URL paths. The browser URL stays `/app.php`; Product routing is implemented through JS state, browser history state, and localStorage.

## Public Layer

```text
/
└── public/index.php
    ├── public SEO landing page
    ├── Open Private App -> /app.php
    ├── Install Web App modal
    └── Donate modal
```

Rules:

- public layer is not the working product;
- private work starts in `/app.php`;
- no private finance data is exposed on `/`.

## Private App Entry

```text
/app.php
└── private app shell
    ├── auth loading state
    ├── email/code login flow
    └── authenticated Product Shell
```

Auth APIs:

```text
request_code
verify_code
current_user
logout
```

State:

```text
localStorage:
  ql_module_state_v1
  findesk_phase1_workspace_v1
  findesk_yacht_template_v1

history.state:
  findesk_app=true
  module=product
  phase_screen=<screen>
  stream_type=cash|card
```

## Product Shell

Visible shell:

```text
Product Shell
├── Back
├── FinDesk / current screen title
├── workspace selector
├── primary button: Журнал -> journal-choice
├── Меню
│   ├── Workspace
│   │   ├── Мои пространства -> workspace-hub
│   │   ├── Текущее пространство -> workspace-home
│   │   ├── Журнал -> journal-choice
│   │   └── Создать пространство -> workspace-create
│   ├── Reports
│   │   └── Отчеты -> reports
│   └── Account
│       └── Профиль -> profile
├── account label
├── logout
└── language selector
```

Shell principles:

- menu is service navigation, not a complete feature tree;
- working actions live inside workspaces and operational screens;
- old modules are hidden when Product Shell is active;
- Back uses Product screen stack first, then fallback routing.

## Registered Product Screens

Current `PHASE2_SCREEN_TITLES` registry:

```text
welcome
workspace-hub
workspace-create
workspace-home
solo
templates
yacht-template
yacht
yacht-home
yacht-tools
yacht-bunkering
yacht-fuel
yacht-products
yacht-settings
home-template
home-home
home-tools
home-household
home-shopping
home-budget
journal-choice
journal
team
admin
employee
assembly
reports
protected
profile
```

Normalization:

```text
live-journal -> journal-choice
journal_choice -> journal-choice
journal-choice -> journal-choice
unknown screen -> workspace-hub if workspace exists, otherwise welcome
```

Current route guard:

```text
welcome -> resolveStartScreen if workspace exists, otherwise welcome
templates -> workspace-create
workspace-home -> current workspace home
yacht -> yacht-home only inside Yacht workspace, otherwise workspace-hub/welcome
yacht-* -> allowed only inside Yacht workspace
home-* -> allowed only inside Home workspace
```

## Workspace Model

Workspace state:

```text
findesk_phase1_workspace_v1:
  mode=none|solo|group
  groupId=<id>
```

Workspace kind is now explicit when backend returns `workspace_type`:

```text
solo mode -> solo
group.workspace_type=yacht -> yacht
group.workspace_type=home -> home
group.workspace_type=team -> team
legacy group without workspace_type -> fallback by name
```

Compatibility: old groups still fall back to name-based detection until the `groups.workspace_type` migration is present in the database and old groups are backfilled where needed.

## Primary Route Tree

```text
product
├── welcome
├── workspace-hub
│   ├── workspace-home
│   ├── workspace-create
│   └── select existing workspace
├── workspace-create
│   ├── solo
│   ├── team
│   ├── yacht-template
│   └── home-template
├── workspace-home
│   ├── journal-choice
│   ├── team
│   ├── admin
│   ├── assembly
│   └── reports
├── journal-choice
│   ├── cash -> journal
│   └── card -> journal
├── journal
├── team
│   ├── admin
│   ├── employee
│   └── assembly
├── admin
├── employee
├── assembly
├── reports
├── protected
└── profile
```

## Golden Path - FinDesk Core

```text
Welcome / Workspace Hub
-> choose or create workspace
-> workspace home
-> Cash/Card choice
-> Live Journal
-> fix journal
-> Report Assembly
-> Reports Archive
```

Correct current direction:

- Cash/Card choice exists before Live Journal;
- Live Journal is records-first;
- Team Workspace is people-first;
- Admin and Employee roles are separated;
- Report Assembly is separate from operational work;
- reports preserve Cash / Card / Total structure.

## Yacht Route Tree

```text
workspace-create
└── yacht-template
    └── create Yacht workspace
        └── yacht-home
            ├── journal-choice
            ├── yacht-tools
            │   ├── yacht-bunkering
            │   │   ├── yacht-fuel
            │   │   └── yacht-products
            │   └── yacht-settings
            ├── team          -> crew/people layer
            ├── admin         -> captain money layer
            ├── assembly      -> final calculation
            └── reports
```

Yacht rules:

- Yacht is a workspace/template, not the global FinDesk product;
- `Бункеровка` is internal to Yacht only;
- products and fuel are separate operational screens;
- product and fuel price logic is planning/reference only, not accounting truth;
- price lock blocks automatic reapply before print.

Yacht fuel path:

```text
yacht-fuel
├── region
├── fuel price mode: ordinary / duty-free
├── reference prices toggle
├── approved prices bridge
├── source freshness warning
├── fuel rows
└── print order
```

Yacht product path:

```text
yacht-products
├── people/days/profile/meal plan
├── region / price mode
├── product category accordions
├── selected count and total per category
├── fix selected and collapse
├── shopping list
└── source freshness warning
```

Known Yacht weakness:

- settings are still not discoverable enough from the user's point of view;
- `yacht-tools` is structurally correct, but visually must make settings/products/fuel obvious;
- durable server-side Yacht order archive is not complete.

## Home Route Tree

```text
workspace-create
└── home-template
    └── home-home
        ├── journal-choice / home-shopping
        ├── home-tools
        │   ├── home-household -> team/employee/reports
        │   ├── home-shopping -> journal-choice
        │   └── home-budget -> admin/assembly/reports
        └── reports
```

Current Home status:

- route shell exists;
- template logic is less mature than Yacht;
- Home should stay a template/workspace, not become main product navigation.

## Legacy Module Layer

Legacy modules still physically exist in DOM:

```text
moduleLedger
moduleOnTheGo
moduleCaptain
moduleMoney
modulePremium
moduleGroups
moduleBusiness
moduleSettings
```

Current protection:

```text
qlSetModule(non-product) without {legacy:true}
-> redirects into Product Shell route:
   reports -> reports
   captain/groups -> team
   ontherun -> journal-choice
   money/advances -> admin
   everything else -> workspace-hub
```

Remaining legacy risk:

- old wrappers around `qlSetModule` still exist;
- old buttons with `data-module-tab` still exist inside hidden legacy DOM;
- old localStorage/history states can still request legacy modules, although Product Shell redirects them;
- any future code passing `{legacy:true}` can still open old module surfaces.

Control rule:

- `{legacy:true}` must be treated as an internal engineering escape hatch only;
- normal UI must never pass `{legacy:true}`;
- old modules may support APIs/data, but must not be normal navigation.

## Back Behavior

Current behavior:

```text
Product Back
├── pop phase1ScreenStack if available
├── yacht-products/yacht-fuel -> yacht-bunkering
├── yacht-bunkering/yacht-settings -> yacht-tools
├── yacht-tools -> yacht-home
├── otherwise fallback to workspace/home/root logic
```

Risk:

- Back behavior is better than root-only navigation, but still needs physical QA across desktop, iPad and iPhone;
- browser Back and Product Back both use `/app.php`, so route state must remain consistent.

## API Loading Logic

Product snapshot load:

```text
phase1LoadSnapshot
├── group_list
├── if no workspace: empty workspace-dependent data
└── if workspace ready:
    ├── group_members
    ├── advance_list
    ├── findesk_transfer_list
    ├── on_the_go_card_list
    ├── on_the_go_tape_list
    ├── ledger_balance
    ├── on_the_go_list
    ├── findesk_report_assembly_get
    └── findesk_report_list
```

Yacht-specific APIs:

```text
yacht_provision_calculate
yacht_price_approved_catalog
```

## Main Weak Spots

1. Workspace kind now supports explicit `workspace_type`, but production DB rollout and old-group backfill still need QA.
2. Legacy DOM and legacy wrappers still exist.
3. Old route tree documentation was stale before this update.
4. `templates` route now aliases to `workspace-create`, so naming needs cleanup.
5. Welcome auto-resolves into workspace when workspace exists, which helps returning users but weakens the pure Welcome Hall concept.
6. Yacht settings discoverability is not strong enough.
7. Home template is structurally present but not complete.
8. Protected Actions need a stronger first-class contract across all dangerous backend mutations.
9. Mobile/device behavior is implemented technically but still needs physical QA.
10. PHP CLI is unavailable in this shell, so PHP price worker lint/run remains blocked.

## Correct Next Implementation Order

1. Keep this route tree current while routing changes are made.
2. Close legacy resurrection paths without deleting backend support.
3. Decide whether Welcome should always show first or continue auto-resolving for returning users.
4. Deploy and QA explicit `workspace_type` storage, then backfill old groups where needed.
5. Simplify `templates` vs `workspace-create` naming.
6. Make Yacht settings/products/fuel obvious from `yacht-home`.
7. Finish Home template only after FinDesk/Yacht main paths are stable.
8. Run physical QA on desktop, iPad portrait/landscape, iPhone portrait/landscape.
