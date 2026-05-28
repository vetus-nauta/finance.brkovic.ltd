# iPhone Notes UX Algorithms For Live Report

Date: 2026-05-21
Applies to: `Живой отчет` / Live Report
Purpose: capture the interaction model of iPhone Notes separately from FinDesk finance formulas.

## Why This Exists

`Живой отчет` should borrow the operational behavior of iPhone Notes, not its literal brand or every feature.

This document is about UX algorithms:

- list behavior;
- open note behavior;
- edit/view state;
- attachments;
- scan/photo/media flows;
- search/preview mental model;
- mobile-first layout.

It is not about FinDesk accounting math.

## Local References

Use these visual references already stored in the repo:

```text
docs/assets/iphone-notes-reference-list.png
docs/assets/iphone-notes-reference-note.png
```

## External Behavior References

Official Apple references checked:

- Get started with Notes on iPhone: Notes quickly captures text and can add images, sketches, checklists, scans and documents.
- Create and format notes on iPhone: first line can become the note title; notes are created by tapping the new note button, then entering text.
- Scan documents in Notes: attachment button -> scan documents; automatic capture can detect document boundaries; manual capture allows corner adjustment; saved scans become PDFs.
- Scan text/documents with camera: Notes can insert scanned text and scan documents from the camera.
- Search Notes: search covers typed text, handwritten text in supported languages, objects in images and text in scanned documents; locked notes reveal only title in results.
- Work with PDFs in Notes: PDFs/scanned documents can be attached, previewed, renamed, deleted, opened full-screen and annotated.

## Core Notes Algorithm

### 1. List Is The Home Screen

The list is not a dashboard.

It should:

- show a large human title;
- group records by date/period;
- show compact note rows;
- make each row obviously tappable;
- keep actions minimal;
- use familiar symbols for actions;
- avoid technical counters such as `1 карточка` in the hero area.

For `Живой отчет`:

```text
Живые отчеты
Сегодня
21.05.26
21:45  расход €750.00 · строк 3
```

Admin/manager may get a compact FinDesk transition, but the list must not become FinDesk.

### 2. Opened Note Is A Detail Screen

Opening a saved note/report should show the original content.

Default state for an existing saved card:

```text
view mode
```

The user sees the original lines exactly enough to inspect them.

Editing starts only after a clear edit action:

```text
tap pencil -> edit mode -> textarea becomes active
```

For a new note/report:

```text
new -> edit mode immediately
```

### 3. Save Returns To List

Saving should not leave the user in a visually empty new report.

Expected transition:

```text
edit -> save -> list
```

The saved row should appear in the list immediately.

### 4. Delete Returns To List

Deleting an opened card must:

- confirm the action;
- archive/delete the same card, not the next active/empty card;
- clear editor state;
- close the opened detail layer;
- return to the saved reports list.

Expected transition:

```text
opened card -> delete -> list
```

### 5. Attachments Are A Bottom/Tool Action, Not A Form Field

Notes treats attachments as an action attached to the note, not as a standard file form.

For `Живой отчет`, use compact actions:

```text
camera     -> capture receipt/photo
scan       -> scan document/PDF-style flow
media      -> choose existing photo/file
```

Browser limitation:

- a PWA can trigger camera/media/file pickers;
- true Apple Notes-style automatic edge detection and PDF scan cleanup requires native APIs or a custom image-processing/OCR step.

### 6. Search/Preview Mental Model

The list row preview should be derived from content:

- date;
- first meaningful record/time;
- short amount/row count summary;
- attachments indicator if present.

Do not show every financial metric on the list row.

Detailed totals belong in the opened card or FinDesk/Advanced.

### 7. Mobile Is Canonical

`Живой отчет` is a field tool.

Device paths:

- mobile: iPhone Notes-like list and detail screens;
- tablet: wider workspace, can use two internal columns;
- desktop: centered professional work canvas, not stretched full-browser form.

## Implementation Implications For FinDesk

Keep these concepts separate:

- `Живой отчет`: draft/saved/submitted personal report document.
- `FinDesk`: moderation, return, include in report.
- `Advanced`: global money/accountability/audit/AI.

Do not turn the live report list into a mini Advanced dashboard.
