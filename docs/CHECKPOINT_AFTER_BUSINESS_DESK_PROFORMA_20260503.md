# Quick Ledger — Checkpoint After Business Desk / Proforma

## Project

Domain: http://finance.brkovic.ltd  
Path: /home/brkovic/finance.brkovic.ltd  
Main app: /app.php  
Current asset version: 20260503-20

## Working MVP

### Auth

- Email-code auth in dev mode
- Session persists after reload
- Current user API works

### Ledger

- Personal ledger
- Group ledger
- Income / Expense
- Cash / Non-cash
- Amount / Purpose
- Optional Section
- Create Section
- No default user-facing sections
- File/document attach
- Entry feed
- Daily grouping
- Edit entry
- Soft archive entry

### Reports

- Today / Month / Custom
- Remaining / Adjustment
- By sections
- Group report
- Admin group total
- Admin by members
- Member sees own group result

### Groups

- Create group
- Group list/details
- Rename group
- Members
- Invite link
- Share via Email / WhatsApp / Viber / Telegram / Copy
- Join by invite

### Messages

- group_messages
- group_message_reads
- Send message
- Message list
- Message stays after reload
- Unread modal
- Open group from modal
- Later/close
- Soft beep connected
- Temporary unread test was created and deleted successfully

### Business Desk

Business Desk is currently a working card in the main app flow. Later it must be moved to a separate module/screen/workspace.

Working:
- DB foundation
- Business API
- UI-shell
- Company profile
- Clients
- Proforma create
- Proforma list
- Proforma get
- Proforma printable view
- Browser print / Save to PDF works

Tables:
- company_profiles
- clients
- proformas
- proforma_items

API:
- company_profile_get
- company_profile_save
- client_create
- client_list
- proforma_create
- proforma_list
- proforma_get

## Business Desk UX rules

Business Desk is not part of fast daily ledger input.

Ledger:
- fast money capture
- balance
- sections
- group entries
- messages
- quick reports

Business Desk:
- company profile
- clients
- proformas
- printable documents
- later: PDF download, templates, items/services, simple invoices, acts, exports

Company profile should contain stable company details:
- company name
- email
- phone
- address
- registration number
- VAT number
- default VAT %

Discount does not belong to company profile.  
Discount belongs to a specific proforma/deal.

## Proforma current behavior

- Proforma is not a fiscal invoice.
- Fiscal note is included:
  “This document is a proforma offer and is not a fiscal invoice.”
- Print opens browser/system print dialog.
- Save to PDF works through browser print.

Future improvements:
- Rename Print → Print / Save PDF
- Add hint: choose Save to PDF to export
- Add proper backend Download PDF later
- Make proforma print layout more polished
- Add multiple items
- Add edit/delete/archive proforma
- Add client details edit
- Add services/items library

## UI architecture rule

Functionality is the same on all devices.

Mobile:
- quick capture
- fast correction
- attach receipt/photo
- view last records
- simple group/message access
- Business Desk should become a separate section, not long flow

Desktop:
- financial desk
- post-processing
- reports
- group control
- business documents
- later: multi-column cockpit/workspace

## Section rule

Section = broad accounting area.  
Purpose = specific reason/details.

Examples:
- Section: Yacht / Purpose: fuel for tender
- Section: Work / Purpose: training payment
- Section: Home / Purpose: groceries

No section by default. User creates sections manually.

## Current known UI debt

- Main page is too long.
- Business Desk currently appears as a card in the same vertical flow.
- Need module navigation:
  Ledger / Reports / Groups / Business Desk / Settings
- Desktop needs cockpit layout.
- Mobile needs quick mode.
- Visual polish later.

## Next recommended stage

Short polish before deeper Business Desk:
1. Rename Print button to Print / Save PDF.
2. Add print/save hint.
3. Add clearer “Business Desk is a separate module” entry point.
4. Then create Proforma edit / multi-item support.
