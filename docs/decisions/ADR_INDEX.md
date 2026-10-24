# SmartGro — Architecture Decision Records

Statuses: **PROPOSED** (awaiting owner approval) · **ACCEPTED** · **SUPERSEDED** · **REJECTED**.
Only the project owner can move an ADR to ACCEPTED. Open approvals are tracked as D-34 in [`DECISION_REGISTER.md`](DECISION_REGISTER.md).

| ADR | Title | Status | Date | Adds dependency? |
|---|---|---|---|---|
| [0001](ADR-0001-modular-monolith.md) | Modular monolith on Laravel 13 | **ACCEPTED** (2026-10-24) | 2026-10-24 | no |
| [0002](ADR-0002-mysql-system-of-record.md) | MySQL 8.4 as system of record; the whole suite runs on MySQL | **ACCEPTED** (2026-10-24) | 2026-10-24 | no |
| [0003](ADR-0003-filament-back-office.md) | Filament 5 back office organised by business job | PROPOSED | 2026-10-24 | no (already installed) |
| [0004](ADR-0004-booking-concurrency.md) | Double-booking prevention via parent-row lock and re-check (+ lock-first constraint, harness evidence) | PROPOSED | 2026-10-24 | no |
| [0005](ADR-0005-content-revisions-approval.md) | Content revisions and approval workflow | PROPOSED | 2026-10-24 | no |
| [0006](ADR-0006-media-library.md) | Central media library with licensing metadata | PROPOSED | 2026-10-24 | yes: medialibrary, Filament plugin, S3 adapter |
| [0007](ADR-0007-redis-and-local-drivers.md) | Redis in production; database drivers locally until installed | PROPOSED | 2026-10-24 | yes (host extension or predis) |
| [0008](ADR-0008-alpine-via-livewire.md) | Use the Alpine.js bundled with Livewire | PROPOSED | 2026-10-24 | no |
| [0009](ADR-0009-3d-viewer.md) | Three.js viewer, lazy-loaded, named-anchor convention | PROPOSED | 2026-10-24 | yes: `three` (npm), Phase 7 |
| [0010](ADR-0010-ssr-with-live-availability.md) | Server-rendered cacheable pages with live availability fragments | PROPOSED | 2026-10-24 | no |
| [0011](ADR-0011-capability-permissions.md) | Business-capability permissions with property scoping | PROPOSED | 2026-10-24 | yes: `spatie/laravel-permission` |
| [0012](ADR-0012-inventory-import.md) | Inventory onboarding via a validated, approved XLSX import | PROPOSED | 2026-10-24 | declares `openspout/openspout` (already installed) as direct |
| [0013](ADR-0013-inventory-modes-and-product-configuration.md) | Inventory modes per space and configurable product categories | PROPOSED | 2026-10-24 | no |
| [0014](ADR-0014-property-hierarchy-and-operating-model.md) | Property → building → floor hierarchy with an explicit operating model | PROPOSED | 2026-10-24 | no |

## Template for new ADRs

```
# ADR-NNNN — Title
* Status: PROPOSED
* Date: YYYY-MM-DD
## Context
## Decision
## Alternatives considered
## Consequences
```
