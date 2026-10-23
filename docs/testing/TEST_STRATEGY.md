# SmartGro — Test Strategy

| | |
|---|---|
| Status | **DRAFT — Phase 1, ready for review** |
| Last updated | 2026-10-24 |
| Current state (verified) | Pest 5.3.3 with `pest-plugin-laravel` 5.0.1 and `pest-plugin-arch` installed. Suite = 2 example tests, both passing (`php artisan test --compact`, 2026-10-24). `phpunit.xml` uses SQLite `:memory:`. |

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
| Feature: back office | Pest + Livewire testing helpers for Filament 5 | each role × capability from `SECURITY_MODEL.md` §3.1 (allowed and **denied**); approval workflow transitions; location scoping on lists, search and exports | every commit |
| Concurrency | Pest on MySQL + parallel processes | BR-BK-1/2/5, BR-TR-2: N simultaneous attempts on one slot → exactly one success | every PR touching booking/inventory; nightly |
| Browser / E2E | Pest browser testing (plugin **not installed**; compatibility to be verified in Phase 3) or Playwright | critical journeys (§4) on mobile and desktop viewports | pre-merge to main, nightly |
| Accessibility | axe-core in E2E runs + manual screen-reader pass | WCAG 2.2 AA on every page template and the back-office core flows | per release |
| Performance | Lighthouse CI on page templates; GLB budget validator | Core Web Vitals budgets (PRD §6) | per release |
| Security | policy matrix tests, upload tests, `composer audit`, `npm audit` | `SECURITY_MODEL.md` §9 | every commit / weekly |

---

## 3. Database strategy for tests

| Suite | Connection | Reset strategy |
|---|---|---|
| Default (`tests/Feature`, `tests/Unit`) | **MySQL 8.4** test database `smartgro_testing` [DEFAULT, requires D-26 credentials] | `RefreshDatabase` (transaction per test) |
| Concurrency (`tests/Concurrency`, Pest group `concurrency`) | MySQL 8.4, separate connections per worker | `DatabaseTruncation`, because transactions cannot wrap multi-process tests |
| Fast local loop (optional) | SQLite `:memory:` (current `phpunit.xml`) | allowed only for tests not tagged `mysql` |

**Proposal:** change `phpunit.xml` to MySQL once a test database exists. Until then, tests that rely on MySQL locking are tagged `->group('mysql')` and skipped with an explicit message, never silently passed.

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

## 7. Phase 0 evidence

| Date | Command | Result |
|---|---|---|
| 2026-10-24 | `php artisan test --compact` | passed: 2 tests, 2 assertions (Laravel skeleton examples only) |

No SmartGro features exist yet, so no feature coverage exists yet.
