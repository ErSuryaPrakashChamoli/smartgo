# SmartGro — Architecture Decision Records

Statuses: **PROPOSED** (awaiting owner approval) · **ACCEPTED** · **SUPERSEDED** · **REJECTED**.
Only the owner can move an ADR to ACCEPTED. No ADR is accepted yet.

| ADR | Title | Status | Date | Adds dependency? |
|---|---|---|---|---|
| [0001](ADR-0001-modular-monolith.md) | Modular monolith on Laravel 13 | PROPOSED | 2026-10-24 | no |
| [0002](ADR-0002-mysql-system-of-record.md) | MySQL 8.4 as system of record; transactional tests on MySQL | PROPOSED | 2026-10-24 | no |
| [0003](ADR-0003-filament-back-office.md) | Filament 5 back office organised by business job | PROPOSED | 2026-10-24 | no (already installed) |
| [0004](ADR-0004-booking-concurrency.md) | Double-booking prevention via parent-row lock and re-check | PROPOSED | 2026-10-24 | no |
| [0005](ADR-0005-content-revisions-approval.md) | Content revisions and approval workflow | PROPOSED | 2026-10-24 | no |
| [0006](ADR-0006-media-library.md) | Central media library with licensing metadata | PROPOSED | 2026-10-24 | yes: medialibrary, Filament plugin, S3 adapter |
| [0007](ADR-0007-redis-and-local-drivers.md) | Redis in production; database drivers locally until installed | PROPOSED | 2026-10-24 | yes (host extension or predis) |
| [0008](ADR-0008-alpine-via-livewire.md) | Use the Alpine.js bundled with Livewire | PROPOSED | 2026-10-24 | no |
| [0009](ADR-0009-3d-viewer.md) | Three.js viewer, lazy-loaded, named-anchor convention | PROPOSED | 2026-10-24 | yes: `three` (npm), Phase 7 |
| [0010](ADR-0010-ssr-with-live-availability.md) | Server-rendered cacheable pages with live availability fragments | PROPOSED | 2026-10-24 | no |
| [0011](ADR-0011-capability-permissions.md) | Business-capability permissions with location scoping | PROPOSED | 2026-10-24 | yes: `spatie/laravel-permission` |

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
