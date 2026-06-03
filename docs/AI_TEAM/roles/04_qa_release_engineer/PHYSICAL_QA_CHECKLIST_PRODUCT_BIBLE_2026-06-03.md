# Physical QA Checklist — Product Bible FinDesk — 2026-06-03

## Scope

Run on real devices before production release:

```text
iPhone Safari
iPhone installed PWA
Android Chrome
Android installed PWA
Tablet if available
```

## Gate Rule

Physical QA is not passed if the tester cannot answer without explanation:

```text
What is FinDesk?
How do I start?
Where do I record money?
Where do I see people?
Where do I submit journals?
Where do I assemble reports?
Where do I export reports?
```

## Product Route

- [ ] App opens to Product shell, not legacy dashboard.
- [ ] Top line shows FinDesk/product navigation clearly.
- [ ] Browser Back moves one product step back, not to old module start.
- [ ] Menu does not expose obsolete product-generation routes.
- [ ] Legacy screens do not flash during navigation.

## Mobile Keyboard

- [ ] Live Journal input remains visible when keyboard opens.
- [ ] Amount input does not sit under the keyboard.
- [ ] Focused input scrolls into view.
- [ ] Keyboard close returns layout to normal.
- [ ] No horizontal overflow after keyboard open/close.

## Touch / Scroll

- [ ] Records feed scrolls with finger.
- [ ] Page scroll does not freeze after refresh.
- [ ] Buttons react to taps on first try.
- [ ] No accidental double save from one tap.
- [ ] Sticky journal input does not cover records incoherently.

## Live Journal

- [ ] Cash journal accepts `+500 Source`.
- [ ] Cash journal accepts `-120 Fuel`.
- [ ] Card journal accepts `-85 Food`.
- [ ] Card warning appears only for manual card balance entry, not normal Card Journal entry.
- [ ] Submit journal moves it to administrator review.

## Team Workspace

- [ ] Team screen is people-first.
- [ ] Admin card opens.
- [ ] Employee card opens.
- [ ] Pending transfer blocks employee journal.
- [ ] Employee confirms transfer.
- [ ] Confirmed transfer becomes active money.
- [ ] Admin sees signed transfer state.

## Report Assembly

- [ ] Ready journals are visible.
- [ ] Journal can be attached to report.
- [ ] Cash Section remains separate.
- [ ] Card / Non-cash Section remains separate.
- [ ] Total is visible.
- [ ] Finalization requires reason.
- [ ] Finalization requires `УТВЕРДИТЬ`.
- [ ] Finalized report appears in Reports.

## Reports / Export

- [ ] Report opens from archive list.
- [ ] Report detail shows Cash / Card / Total.
- [ ] Report detail shows included journals.
- [ ] Single report JSON export downloads.
- [ ] Archive JSON export downloads.

## PWA / Camera Gate

- [ ] Installed PWA opens without white/blank screen.
- [ ] Service worker updates to current asset version.
- [ ] Camera/scanner permission request appears only when scanner is opened.
- [ ] Scanner modal fits mobile viewport.
- [ ] Cancel/close returns to previous product screen.

## Fail Conditions

Physical QA fails if any are true:

- [ ] old interface layer is visible during normal route;
- [ ] Live Journal cannot be used with mobile keyboard;
- [ ] page freezes after mobile refresh;
- [ ] touch buttons stop responding;
- [ ] employee can record before pending transfer confirmation;
- [ ] report finalizes without protected confirmation;
- [ ] exported report package is empty or missing items.
