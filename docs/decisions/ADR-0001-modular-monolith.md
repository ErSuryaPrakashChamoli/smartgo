# ADR-0001 — Modular monolith on Laravel 13

* **Status:** ACCEPTED (project owner, 2026-10-24: "Retain a modular monolith unless there is evidence that another architecture is needed.")
* **Date:** 2026-10-24

## Context
SmartGro needs a public website, back office, inventory, booking, CRM and marketing automation. The hardest guarantees are a **single source of truth** for workspace inventory and **no double bookings**. Expected scale is tens of locations and moderate traffic. The repository is a fresh Laravel 13.38 skeleton.

## Decision
Build one Laravel application with one MySQL database, organised into domain modules (see `../architecture/SYSTEM_ARCHITECTURE.md` §3). Modules interact through action classes and domain events. Boundaries are enforced by policies and Pest `arch()` tests (`pest-plugin-arch` is already installed).

## Consequences
* (+) A single transaction can span inventory, booking and CRM writes, which keeps availability consistent.
* (+) One deployment, one codebase, low operational cost.
* (−) Boundaries rely on discipline and tests rather than network isolation.
* Revisit only if measured load or team structure demands separation.
