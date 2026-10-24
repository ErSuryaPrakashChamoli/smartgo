# ADR-0011 — Business-capability permissions with property scoping

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24

## Context
Marketing must not override inventory or booking truth. Centre staff and sales executives must see only their properties or leads.

## Decision
* Permissions are named after business capabilities (e.g. `inventory.update_status`, `pricing.approve`, `content.publish`), assigned to roles per `../security/SECURITY_MODEL.md` §3.1.
* Property scoping is enforced in policies **and** query scopes.
* Separation of duties applies (no self-approval of content or prices).
* `filament-shield` is not used: its per-resource CRUD permissions do not fit this model.

## Consequences
* (+) The permission model matches how the business talks about responsibilities and is auditable.
* (−) Policies are hand-written, and each needs tests (generated from the matrix).
