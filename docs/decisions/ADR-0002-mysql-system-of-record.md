# ADR-0002 — MySQL 8.4 as system of record; transactional tests run on MySQL

* **Status:** PROPOSED
* **Date:** 2026-10-24

## Context
The app is currently configured for SQLite (`.env`, `phpunit.xml`). MySQL 8.4.11 is running locally. Laravel 13's `SQLiteGrammar::compileLock()` returns an empty string, so `lockForUpdate()` does nothing on SQLite.

## Decision
* MySQL 8.4 (InnoDB, `utf8mb4`) is the only system of record in every environment beyond quick local experiments.
* Tests that touch booking, allocation or pricing run on MySQL. The whole suite moves to a MySQL test DB once credentials exist (D-26).

## Consequences
* (+) Concurrency tests prove what production does.
* (−) Tests are slightly slower than SQLite in-memory, and every developer needs a MySQL instance.
