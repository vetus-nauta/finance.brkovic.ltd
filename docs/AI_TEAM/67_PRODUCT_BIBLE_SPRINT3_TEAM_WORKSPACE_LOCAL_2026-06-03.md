# FinDesk Product Bible Sprint 3 — Team Workspace / Transfers Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Previous sprint:

```text
docs/AI_TEAM/66_PRODUCT_BIBLE_SPRINT2_SOLO_LIVE_JOURNAL_LOCAL_2026-06-03.md
```

## Goal

Create the first obvious team path:

```text
Welcome Hall
  -> Работаю с людьми
  -> Create / Open Team Workspace
  -> People Screen
  -> Admin Card / Employee Card
  -> Pending Transfer
  -> Employee confirmation
```

This sprint focuses on people and transfer acceptance. It does not complete reports.

## Files Changed

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
```

## Done

### 1. Team Workspace Is Now People Screen

Team route no longer behaves as an old finance dashboard.

If no group is selected, the screen shows:

```text
Рабочие среды
Новая группа
Создать группу
```

If a group is selected, the screen shows:

```text
Admin card
Employee cards
```

Employee cards show:

```text
Name
Position
State
Remaining
```

Public states stay human:

```text
Нет записей
Живой журнал
Готов отчет
```

### 2. Group Creation Inside Product Route

Added group creation through the Product shell:

```text
group_create
findesk_workspace_set
```

The user no longer needs to open the legacy `groups` module to start a team workspace.

### 3. Admin Card Skeleton Rebuilt

Admin Card now shows:

```text
У меня
У сотрудников
Ожидают проверки
```

Admin Card includes first-class team actions:

```text
Пригласить сотрудника
Выдать деньги
Открыть свой журнал
Собрать отчет
```

### 4. Invite Member

Admin can create an invite link inside Admin Card:

```text
group_invite_create
```

This keeps onboarding inside the new FinDesk route instead of sending the user to the old group screen.

### 5. Issue Money As Pending Transfer

Admin can issue money through:

```text
findesk_transfer_create
```

Created transfer appears as:

```text
Ожидает подтверждения
```

Money is not active yet.

### 6. Employee Card Pending State

Employee Card shows pending transfer clearly:

```text
Ожидает подтверждения
Amount
Stream
Description
Подтвердить
```

If the current user is not that employee, the card shows that the transfer waits for the employee signature.

### 7. Employee Journal Gate

If employee has pending transfer:

```text
Журнал откроется после подтверждения
```

The UI matches the backend rule.

### 8. Employee Confirmation

Employee can confirm transfer through:

```text
findesk_transfer_confirm
```

After confirmation:

- transfer state becomes `active`;
- active Live Journal tape is created;
- cash transfer starts with issued amount;
- Admin Card shows it as signed/active.

### 9. Snapshot Uses First-Class Transfers

Product shell snapshot now loads:

```text
findesk_transfer_list
```

Transfer amounts are included in employee issued/remaining calculations.

### 10. Asset Version Updated

```text
20260603-team-workspace1
```

Service worker cache:

```text
findesk-20260603-team-workspace1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-team-workspace1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-team-workspace1
```

Authenticated API smoke passed:

```text
admin creates group
admin creates invite
employee joins group
admin creates pending cash transfer
admin sees pending transfer
employee confirms transfer
transfer becomes active
employee active tape starts with issued cash amount
```

Observed result:

```text
joined group=265
admin pending=1
confirmed state=active tape=439
employee active start=250.00 stream=cash
```

Pending transfer journal block passed:

```text
pending write ok=false
error=findesk_transfer_pending_confirmation_required
```

## Not Done Yet

Sprint 3 does not complete:

- full visual browser QA;
- mobile QA;
- physical iPhone/Android/iPad QA;
- admin add-money flow;
- admin edit/cancel pending transfer UI;
- employee record editing/deleting;
- attachment viewer on new Live Journal surface;
- Report Assembly;
- Reports archive;
- Protected Actions UI for transfer cancellation/rollback;
- production deployment.

## Next Sprint

Sprint 4 target:

```text
Admin Card completion
Employee Card completion
Add Money
Edit / Cancel Pending Transfer through Protected Action pattern
Team Live Journal path after confirmed transfer
```

Definition of done:

```text
Admin can add money
Admin can issue money
Employee can confirm
Employee can open Live Journal after confirmation
Admin sees signed transfer
Pending transfer can be safely edited/cancelled
```
