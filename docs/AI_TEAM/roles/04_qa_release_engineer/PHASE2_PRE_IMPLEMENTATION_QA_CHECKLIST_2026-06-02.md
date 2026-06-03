# QA Release Engineer - Phase 2 Pre-Implementation Checklist

Status: open. Physical QA is blocked until these checks pass.

## Foundation Safety

- [ ] Existing auth inspected
- [ ] Email/code flow preserved
- [ ] Sessions preserved
- [ ] Database inspected
- [ ] No destructive DB action planned
- [ ] PWA manifest preserved
- [ ] Service worker preserved
- [ ] Attachment/storage foundation preserved

## Product Logic

- [ ] FinDesk remains money journal, not accounting/ERP
- [ ] Welcome Hall is visible as product entry
- [ ] Old interface shell is removed from normal user path
- [ ] Live Journal is records-feed-first
- [ ] Live Journal has no reports, analytics, categories or dashboard widgets
- [ ] Input uses `+/- amount and note`
- [ ] Fix Journal lifecycle is defined
- [ ] Team Workspace is people-first
- [ ] Admin Card shows `У меня / У сотрудников`
- [ ] Employee Card shows `Выдано / Осталось`
- [ ] Employee live records stay hidden from admin until fixation/submission
- [ ] Ready report state appears after employee fixation

## Cash / Card

- [ ] Cash and Card are separate streams
- [ ] Cash/Card choice exists before Live Journal
- [ ] Card defaults to 0
- [ ] Card balance warning appears only when admin manually enters non-zero card balance
- [ ] Card can be assigned to employee
- [ ] Card reports are separate from Cash reports
- [ ] Final report has Cash, Card and Total sections

## Transfer Offer

- [ ] Admin transfer creates pending offer
- [ ] Employee must confirm before money becomes active
- [ ] Employee journal is blocked while pending transfer exists
- [ ] Admin can edit unresolved pending transfer
- [ ] Admin can delete/cancel unresolved pending transfer
- [ ] Issue, edit, confirmation and cancellation are logged

## Reports

- [ ] Employee fixed journal becomes ready report
- [ ] Admin can attach journal to report
- [ ] Employee cannot edit attached report
- [ ] Admin can detach only through protected action
- [ ] Approved Reports screen exists
- [ ] Report Card has period from/to
- [ ] Participants and movements are shown

## Protected Actions

- [ ] Consequence preview shown
- [ ] Reason required
- [ ] `CONFIRM` required
- [ ] Action logged
- [ ] Rollback restores data and lifecycle behavior

## Navigation / Localization

- [ ] FinDesk menu has no Nav Desk / Ops / other project links
- [ ] Day/Night mode is outside current MVP scope
- [ ] Language list uses shared brkovic.ltd localization where available
- [ ] FinDesk does not duplicate a separate language system
- [ ] Back button uses product navigation stack
- [ ] Root screens may use menu button
- [ ] Inner screens use Back
- [ ] Login/logout state is visible and correct

## Current QA Verdict

Blocked for physical QA:

- old modules are still reachable from visible menu;
- Phase 2 top shell is incomplete;
- Cash/Card choice is inside Live Journal instead of before Live Journal;
- Protected Actions is not a first-class screen;
- Report Assembly is not yet a first-class workflow.
