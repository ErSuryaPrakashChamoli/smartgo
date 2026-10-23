# ADR-0002 — MySQL 8.4 as system of record; transactional tests run on MySQL

* **Status:** ACCEPTED (project owner, 2026-10-24: continue with the MySQL architecture; prepare local MySQL dev/test configuration; MySQL concurrency tests before booking functionality)
* **Date:** 2026-10-24

## Context
At Phase 0 the app was configured for SQLite (`.env`, `phpunit.xml`). MySQL 8.4.11 is running locally. Laravel 13's `SQLiteGrammar::compileLock()` returns an empty string, so `lockForUpdate()` does nothing on SQLite.

## Decision
* MySQL 8.4 (InnoDB, `utf8mb4`) is the only system of record in every environment beyond quick local experiments.
* **The whole test suite runs on MySQL.** `phpunit.xml` forces `DB_CONNECTION=mysql` and `DB_DATABASE=smartgro_testing`. `tests/TestCase.php` refuses to run database traits against any database not ending in `_testing`.
* Credentials exist only in each developer's local `.env`, created by `docs/operations/scripts/setup-local-mysql.sh`. They are never in chat, documentation, logs or Git.

## Evidence (2026-10-24)
* Full suite: 9/9 passing, three consecutive runs, against a throwaway MySQL 8.4.11 instance (same version as the system server).
* The guard was verified to refuse a configuration pointing at `smartgro`.

## Consequences
* (+) Concurrency tests prove what production does.
* (−) Tests are slightly slower than SQLite in-memory, and every developer needs a MySQL instance.
