# API Contracts

Date: 2026-08-20

## Contract Strategy

Web and mobile use the same contracts.

Shared contract packages:

- `packages/types`
- `packages/validation`
- `packages/domain`
- `packages/api-client`

Use schema validation, preferably Zod, for request/response shapes.

## Command Boundary

Critical business mutations use server-side commands.

Examples:

- `createWorkspace`
- `inviteMember`
- `acceptInvite`
- `issueCashAdvance`
- `submitExpenseReport`
- `reviewExpenseReport`
- `materializeExpenseReport`
- `createOperationalEntry`
- `closePeriod`
- `reopenPeriod`
- `createCorrection`
- `uploadDocument`
- `startOcrJob`
- `confirmExtraction`

## Versioning

Initial API namespace:

- `/api/v1/...`

Breaking changes require a new version or compatibility adapter.

## Client Rule

Never trust client input. UI validation improves UX; server validation is mandatory.
