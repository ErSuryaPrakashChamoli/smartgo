# SmartGro — Test Strategy

| | |
|---|---|
| Status | **v1.0-rc — Phase 1, ready for owner review** |
| Last updated | 2026-10-24 |
| Current state (verified) | Pest 5.3.3, `pest-plugin-laravel` 5.0.1, `pest-plugin-arch`. **Suite runs on MySQL 8.4** (`phpunit.xml` forces `mysql` + `smartgro_testing`). 9 tests: 2 Laravel examples + 7 MySQL harness tests. **9/9 passed in three consecutive runs** against a throwaway MySQL 8.4.11 instance (2026-10-24). |

---

## 1. Principles

1. **Tests prove business rules.** Each rule ID in `../requirements/BUSINESS_RULES.md` (e.g. BR-BK-1) has at least one test whose name or `describe` block references it.
2. **Feature tests first.** Most coverage is HTTP, Livewire and Filament feature tests. Unit tests are for pure logic (availability maths, price resolution, slot generation).
3. **Production-parity database for anything transactional.** Laravel 13's `SQLiteGrammar::compileLock()` returns an empty string (verified in `vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/SQLiteGrammar.php`), so `lockForUpdate()` is **silently dropped** on SQLite. A double-booking test on SQLite would pass without proving anything. Booking, allocation and pricing tests must run on MySQL 8.4, where the grammar emits `for update`.
4. **No disabled protections.** CSRF, policies, MFA, rate limits and validation stay on in tests. Tests authenticate properly or use the documented test helpers.
5. **Fakes at the boundary only.** External providers are faked (`Http::fake`, `Mail::fake`, `Notification::fake`, `Queue::fake`); our own domain services are not mocked in feature tests.
6. **Factories with meaningful states** (`Space::factory()->meetingRoom()->underMaintenance()`), not ad-hoc arrays.

---

## 2. Test layers

| Layer | Tooling | Scope | Runs |
|---|---|---|---|
| Architecture | Pest `arch()` (plugin already installed) | module boundaries: content/marketing classes may not write inventory/pricing models; no `env()` outside config; no debug functions; controllers are thin | every commit |
| Unit | Pest | availability calculator, slot generator, price resolver, reference generator, GLB anchor-name parser | every commit |
| Feature: HTTP | Pest + Laravel HTTP testing | public pages render (SSR, status, meta, canonical, JSON-LD validity), API v1 resources, webhooks (signature, idempotency) | every commit |
| Feature: back office | Pest + Livewire testing helpers for Filament 5 | each role × capability from `SECURITY_MODEL.md` §3.1 (allowed and **denied**); approval workflow transitions; property scoping on lists, search and exports | every commit |
| Concurrency | Pest on MySQL + parallel processes | BR-BK-1/2/5, BR-TR-2: N simultaneous attempts on one slot → exactly one success | every PR touching booking/inventory; nightly |
| Browser / E2E | Pest browser testing (plugin **not installed**; compatibility to be verified in Phase 3) or Playwright | critical journeys (§4) on mobile and desktop viewports | pre-merge to main, nightly |
| Accessibility | axe-core in E2E runs + manual screen-reader pass | WCAG 2.2 AA on every page template and the back-office core flows | per release |
| Performance | Lighthouse CI on page templates; GLB budget validator | Core Web Vitals budgets (PRD §6) | per release |
| Security | policy matrix tests, upload tests, `composer audit`, `npm audit` | `SECURITY_MODEL.md` §9 | every commit / weekly |

---

## 3. Database strategy for tests

**Implemented 2026-10-24 (ADR-0002 accepted).**

| Suite | Connection | Reset strategy |
|---|---|---|
| All feature and unit tests | **MySQL 8.4**, database `smartgro_testing`. Forced in `phpunit.xml`; credentials from the local `.env` only. | `RefreshDatabase` for domain tests (transaction per test) |
| Concurrency (Pest group `concurrency`) | MySQL 8.4; one OS process per competing request, each with its own connection | the test creates and drops its own tables, or uses `DatabaseTruncation`; transactions cannot wrap multi-process tests |

* **Safety guard:** `tests/TestCase.php` throws before any database trait runs if the configured database does not end in `_testing`. This was verified by pointing a temporary config at `smartgro`, which was refused.
* **No SQLite fallback.** Without the local MySQL setup (`docs/operations/LOCAL_DEVELOPMENT.md` §2) the suite fails loudly rather than passing on a database that ignores locks.
* **Fast loop:** `php artisan test --compact --exclude-group=concurrency`.

### 3.1 Concurrency harness (implemented)

`tests/Feature/Infrastructure/MySqlConcurrencyHarnessTest.php` with `tests/Support/Concurrency/`:
* A worker script boots the app in **separate processes**. Each waits for a shared release instant, then attempts a reservation with a chosen strategy: `locked`, `naive` or `stale_snapshot`.
* Throwaway `harness_*` tables isolate it from the domain schema.
* **Negative controls** prove the harness can detect double booking. A test suite that cannot fail proves nothing.

---

## 4. Critical journeys (E2E acceptance)

| ID | Journey | Pass condition |
|---|---|---|
| J-1 | Visitor finds a private office in a city on a 360 px mobile viewport and books a tour | slot chosen, confirmation shown, lead + tour created, owner notified, confirmation message dispatched |
| J-2 | Visitor books a meeting room (if D-07 enables it) | hold → details → (payment) → confirmed; slot disappears for other visitors immediately |
| J-3 | Enterprise enquiry for a multi-city requirement | lead created with seat band and cities, routed per assignment rules |
| J-4 | Content editor updates a location gallery and submits; approver publishes | change is invisible before approval and visible after; revision retained; alt text enforced |
| J-5 | Ops marks a space `maintenance` | within 60 s: removed from listings, booking blocked, 3D hotspot shows unavailable, CRM proposal flagged |
| J-6 | Marketing user links 3D anchors to spaces and publishes a scene | no coordinate input anywhere; live availability colours shown; disclaimer shown for non-scan models |
| J-7 | Keyboard-only and screen-reader user completes J-1 | all steps operable; the 3D alternative list conveys the same information |

---

## 5. Test naming and organisation

* Feature tests are grouped by module folder (`tests/Feature/Booking/...`).
* Test descriptions state the rule in business language, e.g. `it('rejects an overlapping meeting-room booking [BR-BK-1]')`.
* Every bug fix starts with a failing test.

---

## 6. Definition of done (per feature)

1. Acceptance criteria in the PRD are covered by automated tests, including at least one negative/authorisation case.
2. `vendor/bin/pint --dirty` is clean (project rule).
3. The narrowest relevant tests pass locally; the full suite passes in CI.
4. No new accessibility violations (axe) on touched templates.
5. Performance budget is not regressed on touched templates.
6. Docs are updated where behaviour or rules changed.

---

## 7. Evidence log

| Date | Environment | Command | Result |
|---|---|---|---|
| 2026-10-24 | SQLite (Phase 0 baseline) | `php artisan test --compact` | 2 passed (Laravel examples only) |
| 2026-10-24 | throwaway MySQL 8.4.11 (private socket, scratch data directory) | `php artisan test` ×3 | **9 passed, 18 assertions**, each run about 16.5 s |
| 2026-10-24 | same | temporary config with `DB_DATABASE=smartgro` | refused by the `*_testing` guard, as intended |
| 2026-10-24 | **the owner's local MySQL 8.4.11** (dev DB `smart_go` configured by the owner; `smartgro_testing` created through the configured connection) | `php artisan test --compact` | **9 passed, 18 assertions, 17.0 s.** Afterwards `smartgro_testing` had 0 tables (harness cleaned up) and `smart_go` was unchanged (9 Laravel tables). |
| 2026-10-24 | owner's local MySQL, `error_reporting=-1`, deprecations to stderr | `php artisan test --compact` | 9 passed; **0 PHP 8.5 deprecations** |
| 2026-10-24 | disposable copy with `filament:install --panels` (SQLite) | 5 Filament smoke tests + real `artisan serve` | 5 passed (guest redirect, login page and assets, 403 for a non-`FilamentUser` outside local, dashboard after implementing `FilamentUser`, Livewire login success and failure). HTTP: login 200, 6/6 assets 200, `/up` 200. Copy deleted. |

No SmartGro features exist yet, so there is no feature coverage yet.

---

## 8. Booking-domain test catalogue (must pass **before** booking code is merged)

These are written in Phase 5/8 against the real schema, reusing the harness pattern. Each one runs on MySQL. T-BK-11…17 were added on 2026-10-24 for the owner-confirmed inventory modes (OD-10, OD-15 hybrid zones) and product categories (OD-07). The existing harness and its negative controls stay unchanged. **The lock-before-read invariant is the acceptance gate for every test here.**

| ID | Scenario | Expected |
|---|---|---|
| T-BK-01 | N parallel `HoldSlot` calls for the same meeting-room interval | exactly one `held`; the rest `SlotUnavailable` |
| T-BK-02 | Parallel requests with partially overlapping intervals, plus one disjoint | one per overlapping group, plus the disjoint one |
| T-BK-03 | Back-to-back bookings with and without turnaround | allowed only when `turnaround_minutes` is respected (BR-BK-1) |
| T-BK-04 | Parallel day-pass requests beyond shared capacity | confirmed quantity = capacity, never more (BR-BK-2) |
| T-BK-05 | An expired hold competing with a new request | the new request succeeds; the expired hold does not block (BR-BK-3/4) |
| T-BK-06 | `ConfirmBooking` after the hold expired, slot taken meanwhile | `SlotUnavailable`; no confirmation; refund path triggered if paid |
| T-BK-07 | Failure after a partial write (booking inserted, then exception) | full rollback; no booking, no event emitted |
| T-BK-08 | Same idempotency key submitted twice in parallel | one booking; the second returns the first |
| T-BK-09 | Space set to `maintenance` while a booking is being created | either the booking commits first and status change follows, or the booking is refused; never a booking on a non-bookable space |
| T-BK-10 | Inventory consistency: allocations + bookings vs capacity after a randomised parallel workload | invariant holds: no overlapping exclusive bookings; capacity never exceeded |
| T-BK-11 | **Per-seat zone:** N parallel requests for the same seat, plus parallel requests for different seats in the same zone | exactly one per seat; different seats all succeed; serialised on the **zone** row (BR-BK-2a) |
| T-BK-12 | **Hybrid within a property:** concurrent bookings on a per-seat zone, a pooled zone and an exclusive room in the same property | each space enforces its own rule; no cross-space interference (BR-AV-3b) |
| T-BK-13 | **Multi-space agreement** over 3 spaces racing a single-space booking on one of them; two multi-space requests locking overlapping sets in different orders | all-or-nothing; ascending-id lock order prevents deadlock (or the retry succeeds); no partial writes (BR-BK-2b) |
| T-BK-14 | **One room sold as two categories:** parallel meeting-room and training-room bookings for overlapping times on the same space | exactly one succeeds (BR-AV-3c) |
| T-BK-15 | **Admin supply reduction** (capacity cut, seat deactivation, maintenance) racing a booking | either the booking commits and the change is refused with the conflict listed, or the change commits and the booking is refused. Never both (BR-INV-4). |
| T-BK-16 | **Cancellation release:** cancel, then immediately rebook the same slot or seat in parallel with another request | released inventory is available at once; exactly one rebooking succeeds (BR-BK-11) |
| T-BK-17 | **Hybrid zone (OD-15):** concurrent seat assignments, seat bookings and pooled bookings on one hybrid zone, including an assignment that would over-commit the pool | BR-AV-3d holds at every instant; the over-committing request is refused; all writes serialise on the zone row (BR-BK-2c) |
| T-TR-01 | N parallel `ClaimTourSlot` calls beyond `max_concurrent_tours` | confirmed tours = capacity (BR-TR-2) |
| T-ARCH-01 | Arch test: booking/allocation actions perform `lockForUpdate()` before any read | enforced by test + code-review checklist (ADR-0004 constraint) |

## 9. Inventory import test catalogue (Phase 5)

| ID | Scenario | Expected |
|---|---|---|
| T-IMP-01 | `SYNTHETIC_inventory_valid.xlsx` (SG-INV-1.2) with time frozen at 2026-10-24 | 0 errors, 0 warnings; preview counts match the fixture (2 properties, 3 buildings, 4 floors, 15 spaces including a hybrid zone) |
| T-IMP-02 | `SYNTHETIC_inventory_with_errors.xlsx` (SG-INV-1.2) | issues equal **exactly** the hidden `_expected_issues` sheet (20 issues, 18 rules) |
| T-IMP-03 | Commit of the valid file, then re-import of the same file | second run: "no changes" |
| T-IMP-04 | Import committing while a booking holds the space lock | serialised; no lost update |
| T-IMP-05 | Synthetic file in a `production` environment | rejected by V-180 |
| T-IMP-06 | Uploader attempts to approve their own import | denied (four-eyes) |
