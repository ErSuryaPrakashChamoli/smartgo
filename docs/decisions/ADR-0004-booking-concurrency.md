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

### Implementation constraint (added 2026-10-24 from harness evidence)
Under InnoDB's default REPEATABLE READ isolation, a transaction's consistent-read snapshot is fixed by its **first plain (non-locking) read**. If any plain read happens **before** the parent-row lock, the post-lock availability check reads that stale snapshot. A competing booking committed in the meantime is then invisible, and **double booking occurs despite the lock**.

Therefore:
1. **The parent-row `lockForUpdate()` must be the first statement in the transaction.** Do not eager-load, check, or read anything first.
2. Alternatively, perform the availability check itself with locking reads (`sharedLock()`/`lockForUpdate()`), which always read the latest committed data.
3. Code review checklist: booking and allocation actions are reviewed specifically for reads before the lock.

### Lock targets per inventory mode (added 2026-10-24 for OD-10; the invariant is unchanged)

**Invariant (owner-confirmed): the booking process acquires the necessary lock before reading the inventory state used to make the booking decision.**

| Situation | Rows locked first (ascending `id` when several) | Then validated under the lock |
|---|---|---|
| `exclusive` space (cabin, room, suite) | that space | no overlapping blocking booking or allocation, including turnaround; space status; closures |
| `per_seat` zone (dedicated desks) | the **zone's** space row, never only the seat row | the chosen seat is active and free for the interval; zone status |
| `pooled` zone (hot desks) | the zone's space row | Σ blocking quantities + allocations + requested ≤ `capacity_seats` at every instant of the interval |
| Multi-space agreement or combined booking (e.g. managed office over several spaces) | every involved space, **ascending `id`** (prevents deadlocks; retried on deadlock) | each space per its own mode |
| Cancellation, extension, reschedule | the same rows as creation | status transition rules; released inventory is immediately available, because availability is derived |
| Admin inventory change that reduces supply (capacity reduction, seat deactivation, status → maintenance or decommissioned, closure) | the affected spaces, ascending `id` | future committed usage still fits; otherwise the change is refused with a list of conflicts (BR-INV-4) |
| Virtual office (no physical inventory) | the offering row, **only** if a per-property cap is configured | cap not exceeded |

Seat rows are never the only lock. A seat booking and a pool booking in the same zone must serialise on the same row, or two writers could each see "free".

### Evidence (MySQL 8.4.11, `tests/Feature/Infrastructure/MySqlConcurrencyHarnessTest.php`)
| Strategy | Concurrent requests | Result |
|---|---|---|
| Lock first, then check | 5 overlapping + 1 separate | exactly 2 confirmed, 0 overlapping pairs |
| Lock first, shared capacity 3 | 8 × 1 seat | exactly 3 confirmed, total seats 3 |
| No lock (negative control) | 4 identical | overlapping pairs > 0: the harness detects double booking |
| Plain read before lock (negative control) | 4 identical | overlapping pairs > 0: confirms the constraint above |

The results held in three consecutive runs.

## Alternatives considered
* **Slot table with unique (`space_id`, `slot_start`)**: simple for fixed 30-min grids, but it breaks for variable durations, turnaround buffers and shared capacity. It may be added later as defence in depth for meeting rooms.
* **Redis distributed lock**: adds a dependency to correctness; the DB lock is sufficient and transactional.

## Consequences
* (+) Correct under concurrency; easy to test on MySQL with parallel processes.
* (−) Writers on the same space serialise. This is acceptable at expected volumes.
