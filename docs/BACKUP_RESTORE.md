# Backup And Restore

Date: 2026-08-20

## Current Legacy Backup Needs

Before any migration:

- current MySQL/MariaDB schema dump
- current MySQL/MariaDB data dump
- current `storage/` documents and report files
- current production PHP runtime files
- current config inventory without printing secrets

## Target Backup Needs

PostgreSQL:

- schema migrations in Git
- scheduled logical exports when free tier lacks automatic backups
- manual pre-migration export
- restore test procedure

Storage:

- object metadata export
- bucket object list
- checksum manifest
- restore procedure for private objects

## Restore Test

A backup is not accepted until it can be restored into a clean environment and pass:

- migration/schema checks
- user/workspace counts
- ledger totals
- report totals
- document metadata count
- sample file access through authorized path
