# ADR-0004 — Double-booking prevention via parent-row lock and re-check

* **Status:** PROPOSED
* **Date:** 2026-10-24

## Context
MySQL has no exclusion constraints for time ranges, so a unique index alone cannot prevent overlapping bookings. Check-then-insert without locking is racy.

## Decision
Every create, extend, reschedule or confirm of a booking runs as `DB::transaction(fn, attempts: 3)`:
1. `Space::whereKey($id)->lockForUpdate()->first()` serialises all writers for that space.
2. Re-validate operational status, closures, booking window and overlap/capacity (BR-BK-1/2/3) **inside the lock**.
3. Insert/update the booking (status `held` with `hold_expires_at`, or `confirmed`).
4. Emit `SpaceAvailabilityChanged` after commit.

Idempotency keys (unique index) protect against client retries. Tour slots use the same pattern on a per-location capacity row.

## Alternatives considered
* **Slot table with unique (`space_id`, `slot_start`)**: simple for fixed 30-min grids, but it breaks for variable durations, turnaround buffers and shared capacity. It may be added later as defence in depth for meeting rooms.
* **Redis distributed lock**: adds a dependency to correctness; the DB lock is sufficient and transactional.

## Consequences
* (+) Correct under concurrency; easy to test on MySQL with parallel processes.
* (−) Writers on the same space serialise. This is acceptable at expected volumes.
