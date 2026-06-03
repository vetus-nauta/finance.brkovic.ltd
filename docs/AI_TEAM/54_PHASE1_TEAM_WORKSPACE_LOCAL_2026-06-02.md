# Phase 1 Local Report — Team Workspace / Cards

Date: 2026-06-02
Scope: local only
Status: done for local review, not deployed

## Goal

After the first Live Journal cleanup, rebuild the next user-facing layer:

- Team Workspace
- Admin Card
- Employee Card

without changing backend formulas or API contracts.

## Files changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

## What changed

### 1. Team Workspace

The FinDesk home board inside `#moduleCaptain` now behaves more like a real group workspace:

- when no group is selected, the screen shows `Create Team Workspace`
- group creation is available directly on the FinDesk surface
- when a group is selected, the home screen shows:
  - admin cash
  - employee cash
  - ready report count
  - participant count
  - direct actions:
    - open admin card
    - open live journal
    - open report
    - archive

Participant cards now show:

- name
- state
- remaining cash

### 2. Admin Card

Admin card wording and structure were cleaned:

- `Мой отчет` -> `Мой журнал`
- `Входящие отчеты участников` -> `Готовые журналы сотрудников`
- `Принятые карточки и подотчеты` -> `Прикрепленные журналы и выдачи`
- `Фиксация текущего пакета` -> `Сборка общего отчета`
- primary finalize button now reads `Сохранить общий отчет`

Admin current journal block now shows:

- open live journal
- send to FinDesk
- delete journal
- own fixed journals history

### 3. Employee Card

Employee workspace now emphasizes:

- assigned cash
- current remaining cash
- current state
- current or last journal
- fixed journals history

This works both for:

- self-view
- admin opening an employee card

### 4. Member list inside Admin Card

The member list is now closer to a working roster:

- hides the current admin from the staff roster
- shows member state
- shows current remaining cash
- gives direct `Открыть` access to the employee card
- keeps access-level control for managers/admins

## Backend safety

No new API was introduced.

The local rebuild continues to reuse the existing hooks:

- `group_create`
- `group_members`
- `group_invite_create`
- `advance_create`
- `ledger_balance`
- `on_the_go_tape_list`
- `on_the_go_card_*`
- `advance_*`

## Checks completed

- `node --check public/assets/app.js`
- `git diff --check public/app.php public/assets/app.css public/assets/app.js`
- `curl -I http://127.0.0.1:18889/app.php` -> `200`
- local API smoke by live requests:
  - login by code
  - create group
  - invite member
  - join group
  - create advance
  - read `ledger_balance`
  - read `on_the_go_tape_list`

## Limits in this environment

Could not run full browser runtime audit here because:

- `playwright` is not installed
- no system browser was available
- `php` CLI is not available for the existing PHP smoke script

So this slice is verified by:

- syntax
- HTTP availability
- live API flow

but not yet by local visual browser automation in this shell.

## Next step

Next local slice:

- Group Report Assembly screen cleanup
- tighten Admin Card actions around attach / return / save report
- then visual/runtime review before any deploy
