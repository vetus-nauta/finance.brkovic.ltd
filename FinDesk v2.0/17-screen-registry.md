# 17 — Screen Registry

## Purpose

One screen = one logic.

This registry prevents mixed-purpose screens and dashboard sprawl.

## Hall

Primary logic: choose or create workspace.

Visible data:

- workspace list;
- user profile access;
- create workspace action.

Forbidden:

- reports;
- charts;
- finance entry input.

## Journal

Primary logic: current month financial note feed and entry input.

Visible data:

- workspace;
- month;
- cash now;
- card expense;
- active flow;
- current month feed;
- input bar.

Allowed actions:

- add entry;
- edit entry;
- change category;
- attach file;
- switch Cash/Card;
- open details.

Forbidden:

- full dashboard;
- unrelated reports;
- page scroll.

## Entry Details

Primary logic: inspect and adjust one entry.

Visible data:

- raw text;
- date;
- flow;
- amount;
- category;
- actor;
- attachments;
- audit history.

## Monthly Report

Primary logic: generated monthly summary.

Visible data:

- opening cash;
- external income;
- commercial income;
- cash expense;
- card expense;
- other expenses;
- ending cash;
- comments.

Forbidden:

- manual numeric editing except comments/corrections.

## Other Review

Primary logic: review and reassign `other` category entries.

## Import Review

Primary logic: review imported files and rows.

Visible data:

- files included/excluded;
- rows parsed;
- rows unrecognized;
- duplicate suspects;
- month coverage;
- accept/reject import.

## Settings

Primary logic: workspace settings.

Visible data:

- workspace info;
- members;
- flows;
- categories;
- language;
- rules.

## Device behavior

Mobile/phone/iPad mini:

- Journal vertical = notes feed;
- Journal horizontal = structured rows.

Desktop/iPad 11+:

- use full workspace layout;
- show panels instead of hiding everything.
