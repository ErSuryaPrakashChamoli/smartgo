# Phase 0 — Discovery Report

| | |
|---|---|
| Phase | 0: Discovery, repository audit and requirements clarification |
| Status | **READY FOR REVIEW** (not approved; only the owner can approve) |
| Date | 2026-10-24 |
| Prepared for | SmartGro project owner |

This report separates **confirmed facts** (verified by a command or a fetched source), **assumptions**, **recommendations** and **unresolved questions**. Nothing in it invents client facts.

---

## 1. Repository and environment audit

All checks were run on 2026-10-24 in `/home/administrator/Documents/website/smartGrow`. **No files were deleted or modified during the audit. No database or destructive command was run. No secret values were read into this report** (`.env` keys were listed with values masked).

### 1.1 Confirmed facts

| Area | Finding | Evidence |
|---|---|---|
| Repository | **Not a Git repository** (no `.git` in this or parent directories). No branch, history or uncommitted-change tracking. | `git status` → "not a git repository" |
| Application | Fresh **Laravel 13.38.0** skeleton. App code is the default `User` model, one controller base class, `AppServiceProvider`, and a `/` route returning `welcome`. No SmartGro features exist. | file listing; `php artisan about`; `php artisan route:list` |
| `APP_NAME` | `Laravel` (not yet branded) | `.env` (non-secret value) |
| PHP | **8.5.4** CLI, OPcache; extensions include pdo_mysql, mbstring, intl, gd, imagick, exif, fileinfo, zip, pcntl, posix, sodium | `php -v`, `php -m` |
| PHP extensions missing | **redis (phpredis)**, **bcmath** | `php -m` |
| Composer | 2.9.5; platform requirements of installed packages all satisfied | `composer check-platform-reqs` |
| Direct PHP packages | laravel/framework 13.38.0, **filament/filament 5.10.3**, laravel/tinker 3.0.2; dev: laravel/boost 2.10.3, pestphp/pest 5.3.3, pest-plugin-laravel 5.0.1, laravel/pint 1.32.1, laravel/pail 1.2.7, laravel/pao 1.1.5, mockery 1.6.15, collision 8.9.5, faker 1.24.1 | `composer show --direct` |
| Key transitive | livewire/livewire 4.4.8 (bundles Alpine; `window.Alpine` in its dist), blade-heroicons, league/flysystem 3.36.0, pest-plugin-arch | `composer show`; vendor grep |
| Filament | Installed but **no panel provider registered**, no resources, views not published | `bootstrap/providers.php`; `php artisan about` |
| Node / npm | Node 22.22.1, npm 9.2.0 | `node -v`, `npm -v` |
| JS packages | vite 8.3.5, tailwindcss 4.3.3, @tailwindcss/vite 4.3.3, laravel-vite-plugin 3.2.0, concurrently 10.0.6, @laravel/multiplex 0.4.5. **No Three.js. No separate Alpine.** | `npm ls --depth=0` |
| Frontend | Tailwind v4 `@theme` with Instrument Sans (Bunny Fonts, Laravel default); `resources/js/app.js` is empty | `resources/css/app.css`, `vite.config.js` |
| Database (app) | **SQLite** (`database/database.sqlite`); 3 default migrations ran (users, cache, jobs) | `php artisan migrate:status` |
| Database (server) | **MySQL 8.4.11** service active, listening on 127.0.0.1:3306. **No app credentials configured; no login was attempted.** | `systemctl is-active mysql`; `ss -ltn`; `mysql --version` |
| Redis | **Not installed** (`redis-server` service inactive; no `redis-cli`) | `systemctl`, `which` |
| Drivers | cache, queue and session = `database`; mail = `log`; filesystem = `local`; `public/storage` not linked | `php artisan about` |
| Object storage | S3 adapter not installed; AWS keys empty | `composer show`; masked `.env` |
| Tests | Pest; 2 example tests **pass**; `phpunit.xml` uses SQLite `:memory:` | `php artisan test --compact` |
| AI tooling | Laravel Boost (MCP) configured; `CLAUDE.md`/`AGENTS.md` present; project skills in `.claude/skills`; no `.ai/rules` directory | file listing |
| Brand assets | **No SmartGro logo, imagery, floor plans or 3D files** in the repository. A name search of the home directory found none. Sibling folders (`fynnedge`, `markedge`) belong to other projects and were not inspected beyond file names. | `find` searches |
| Documentation | Only the Laravel default `README.md` before this phase | file listing |
| Deployment config | None (no CI, Docker, Forge/Cloud config) | file listing |
| Laravel locking on SQLite | `SQLiteGrammar::compileLock()` returns `''`, so `lockForUpdate()` is a no-op on SQLite | vendor source |

### 1.2 Compatibility of the requested stack (verified 2026-10-24)

| Requested | Result |
|---|---|
| Laravel 13 / PHP 8.5 / Filament 5 | Installed and working together |
| MySQL 8.4 | Server present; app must be switched from SQLite (needs credentials) |
| Tailwind / Alpine / Livewire | Present (Alpine via Livewire) |
| Three.js | Available on npm (`three@0.186.1`); not installed |
| Redis | Missing locally; environment task, not an architecture change |
| Proposed packages | All publish Laravel 13 / PHP 8.5-compatible constraints on Packagist (`SYSTEM_ARCHITECTURE.md` §9) |

**No downgrade, upgrade or replacement of the requested stack is needed.**

---

## 2. Market benchmark findings

Full report with sources: [`../research/MARKET_BENCHMARK.md`](../research/MARKET_BENCHMARK.md). It covers 12 named operators plus WeWork India and one aggregator, researched 2026-10-24. **IWG/Regus/Spaces blocked automated access**, so those findings are labelled as search snippets.

Headline findings:
1. **No operator offers live, confirmed tour slots.** Tours are request forms, phone links or OTP gates.
2. **Price and availability inconsistencies are common**, both within operator sites and between operator and broker. A single source of truth is a visible differentiator.
3. **Indian operators' centre pages are thin or client-rendered.** Awfis ships unrendered placeholders (re-verified). Indian centre pages lacked metro distances, maps and prices.
4. **3D/360 tours exist but are hidden** on sub-pages or external sites, and floor plans are rare. Inline 3D linked to live availability would be distinctive.
5. **WhatsApp is absent** from Indian operator pages fetched, and only WeWork India's privacy policy cites the DPDP Act (re-verified).
6. Conventions worth adopting: city → micro-market → centre hierarchy, product × city pages, "from" prices for flexible products, a separate enterprise track with seat bands, and context-carrying CTAs.

---

## 3. Initial product scope

See [`../requirements/PRD.md`](../requirements/PRD.md) §4. Proposed **Release 1:**
* brand and design system
* foundation and security
* CMS with approvals
* inventory and availability
* full public site
* enquiries and **live tour scheduling**
* CRM core
* technical SEO
* QA and deployment

The 3D twin is in Release 1 only where real scans or models exist; the fallback ships regardless. Online booking and payment depend on D-07/D-08. Automation, advanced analytics, AI concierge and a member portal come later.

---

## 4. Functional and non-functional requirements

* Functional: [`PRD.md`](../requirements/PRD.md) §5. There are 48 numbered requirements across discovery, CMS, inventory, 3D, booking, CRM, marketing, SEO, analytics and concierge, each with acceptance criteria.
* Business rules: [`BUSINESS_RULES.md`](../requirements/BUSINESS_RULES.md), with rule IDs referenced by tests.
* Non-functional: [`PRD.md`](../requirements/PRD.md) §6, covering Core Web Vitals, payload budgets, WCAG 2.2 AA, security, reliability, scale and locale.
* Sitemap and journeys: [`INFORMATION_ARCHITECTURE.md`](../requirements/INFORMATION_ARCHITECTURE.md).

---

## 5. Missing inputs, decisions and risks

### 5.1 Fact classification

| Type | Items |
|---|---|
| **Confirmed** | brand name "SmartGro"; tagline; provisional palette (from brief); target stack; current environment (§1) |
| **Assumed** (PRD §7) | India / INR / English; no authoritative legacy system; no member portal at launch; no existing site to migrate; free tours |
| **Recommended** | everything marked [DEFAULT] in the business rules and the PROPOSED ADRs |
| **Unresolved** | the decision register below |

### 5.2 Decision register

Priority **P1** means it is needed to approve Phase 1 or to start Phases 2–3. **P2** means it is needed before the phase shown.

| ID | Decision / input needed from owner | Priority | Needed by | Recommended default |
|---|---|---|---|---|
| D-01 | Company profile: legal entity, GSTIN, HQ, country/market, positioning statement, founding story | P1 | Phase 1 | India, INR, premium hospitality-led positioning |
| D-02 | Actual locations: cities, buildings, addresses, floors, status/opening dates; canonical city slugs (e.g. `bengaluru` vs `bangalore`) | P1 | Phase 1 (validate ERD) | official city names as slugs; common aliases as 301s |
| D-03 | Product catalogue (private office, managed office, hot desk, dedicated desk, meeting room, day pass, virtual office, event space) | P1 | Phase 1 | include only what SmartGro actually sells |
| D-04 | Pricing rules: units, minimum terms, deposits, GST display, **public price display policy**, discount authority | P1 | Phase 5 | "from" prices for flexible products; private offices "from ₹/seat" if approved, else enquiry; prices shown "+ GST" |
| D-05 | Inventory granularity: are dedicated desks tracked per seat? Hot desks pooled? | P1 | Phase 1 (ERD) | per-seat dedicated desks; pooled hot desks |
| D-06 | **Existing systems** (coworking/member management, CRM, accounting/ERP) and which is authoritative per field | **P1 (critical)** | Phase 1 | SmartGro platform authoritative for everything except invoices |
| D-07 | Launch booking scope: instant online booking (meeting rooms, day passes) vs request-only | P2 | Phase 8 | live tour scheduling at launch; instant room booking if payments approved |
| D-08 | Payment gateway and GST invoice generation ownership | P2 | Phase 8 | decide with finance; gateway webhooks authoritative |
| D-09 | Sales process: team, stages, assignment, SLA targets, discount approvals | P2 | Phase 9 | stages in `DATABASE_ERD.md` §6; 15-min SLA |
| D-10 | Email, WhatsApp (verified business number, BSP or Cloud API) and SMS (DLT registration) providers | P2 | Phases 8 and 10 | transactional email at launch; WhatsApp in Release 1 if ready |
| D-11 | **Logo** (vector master + variants), brand guidelines, licensed fonts | **P1 (blocks Phase 2)** | Phase 2 | none: the logo must come from the owner |
| D-12 | Photography: existing assets, ownership/licences, planned shoots | P1 | Phase 2/6 | professional shoot per open location before launch |
| D-13 | Floor plans (CAD/PDF) and 3D capture availability (scans, BIM/SketchUp models), budget, pilot location | P2 | Phase 7 | pilot one flagship location with a measured scan |
| D-14 | Hosting and deployment (e.g. Laravel Cloud, AWS, VPS), accounts ownership, region, domain/DNS (Cloudflare), staging | P2 | Phase 3 (staging) / 15 | managed Laravel hosting + Cloudflare; India region |
| D-15 | Legal: privacy notice, terms, cookies, cancellation/refund, DPDP responsibilities, grievance officer, retention | P2 | Phase 6/8 | owner's legal counsel to provide |
| D-16 | Languages | P2 | Phase 4 | English only |
| D-17 | Approval workflow: who approves what; single or two-step | P1 | Phase 4 | single approver, no self-approval |
| D-18 | Analytics/ads stack, consent banner, maps provider | P2 | Phase 6/12 | GA4 + GTM with consent mode; Google Maps or OSM |
| D-19 | Existing website/domain and URLs that must be preserved | P1 | Phase 1 (IA) | if a site exists: crawl and map 301s before launch |
| D-20 | Customer/member portal scope | P2 | Phase 8 | none at launch; signed self-service links |
| D-21 | AI concierge scope and provider | P2 | Phase 13 | defer |
| D-22 | Canonical brand spelling and primary domain ("SmartGro" vs folder name "smartGrow") | P1 | Phase 2 | "SmartGro" per brief |
| D-23 | **Initialise Git** and choose a remote (GitHub/GitLab/Bitbucket) | **P1 (immediate)** | before any code | GitHub private repo; protected `main` |
| D-24 | Approve new dependencies (`SYSTEM_ARCHITECTURE.md` §9) | P1 | Phase 3 | approve as listed |
| D-25 | Approve code layout (`SYSTEM_ARCHITECTURE.md` §3.1) | P1 | Phase 3 | approve as proposed |
| D-26 | Local MySQL database + user for dev/test; install Redis + `php8.5-redis` (and `php8.5-bcmath`) | P1 | Phase 3 | create `smartgro` and `smartgro_testing` DBs |
| D-27 | Partner programmes (landlords, brokers) and careers pages | P2 | Phase 6 | omit until confirmed |
| D-28 | Tour rules: hours, slot length, concurrent tours, hosts, calendar system | P2 | Phase 8 | 30-min slots in operating hours; ICS invites |
| D-29 | Lead data retention period and deletion process | P2 | Phase 9 | legal to confirm |
| D-30 | Back-office URL path and whether to restrict it by IP / Cloudflare Access | P2 | Phase 3 | non-default path + Cloudflare Access in production |

### 5.3 Risk register

| ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-1 | An existing system (D-06) must remain authoritative, invalidating parts of the inventory model | Medium | High | answer D-06 before Phase 5; ERD already allows `external_ref` and sync |
| R-2 | **No version control**: work can be lost and there is no review trail | High (current) | High | D-23 immediately |
| R-3 | No logo or real photography delays Phase 2/6, or creates pressure to use non-genuine imagery | High | High | D-11/D-12 early; BR-MED rules prevent misrepresentation |
| R-4 | 3D capture cost/time and device performance | Medium | Medium | pilot one location; budgets (NFR-PERF-4); fallback always available |
| R-5 | Scope breadth (16 phases) versus launch date | High | High | release slicing (PRD §4); dependency map |
| R-6 | Regulatory: DPDP, SMS DLT, WhatsApp template approval timelines | Medium | Medium | legal review (D-15); start provider registration early |
| R-7 | Concurrency bugs (double booking) | Low (with ADR-0004) | High | parent-row locking; MySQL concurrency tests |
| R-8 | Dev/prod parity: SQLite locally hides locking bugs | High (current) | Medium | ADR-0002; MySQL test DB (D-26) |
| R-9 | Content inconsistency (prices, counts) as seen at competitors | Medium | Medium | price tokens, inventory-driven blocks, validators |
| R-10 | Lead spam and bots | Medium | Low–Med | rate limits, Turnstile, honeypot; OTP only if needed |

---

## 6. Proposed architecture

See [`../architecture/SYSTEM_ARCHITECTURE.md`](../architecture/SYSTEM_ARCHITECTURE.md) and [`../decisions/ADR_INDEX.md`](../decisions/ADR_INDEX.md) (11 PROPOSED ADRs). In summary:
* a modular monolith on Laravel 13 + MySQL 8.4
* server-rendered public site with live availability fragments
* Filament 5 back office organised by business job, with required MFA
* parent-row locking for bookings
* polymorphic revisions for approvals
* a central media library with licensing
* a lazy-loaded Three.js viewer using a named-anchor convention, so editors never handle coordinates
* Redis in production

---

## 7. Phase dependency map

See [`../../PROJECT_MASTER_PLAN.md`](../../PROJECT_MASTER_PLAN.md) §3.

---

## 8. Preliminary database entities

See [`../architecture/DATABASE_ERD.md`](../architecture/DATABASE_ERD.md). It defines about 65 tables (including package-provided permission and activity-log tables) across identity, portfolio/inventory, commerce, booking, CRM, content/media/SEO, 3D twin, marketing and integrations, plus six open modelling questions (M-1…M-6).

---

## 9. Integration inventory

See [`../architecture/INTEGRATION_CONTRACTS.md`](../architecture/INTEGRATION_CONTRACTS.md). It lists 16 integration capabilities, universal rules (secrets, idempotency, webhook verification) and internal domain events. **No provider is selected.**

---

## 10. Proposed acceptance criteria

### Phase 0 (this phase)
| # | Criterion | Status |
|---|---|---|
| 0.1 | Environment and repository audited with evidence; nothing destructive done | Met (§1) |
| 0.2 | Requested stack compatibility verified; deviations reported | Met (§1.2) |
| 0.3 | Market research documented with dates, sources, access status and limits | Met (benchmark; 4 claims re-verified) |
| 0.4 | Missing inputs listed; facts, assumptions, recommendations and unresolved items separated | Met (§5) |
| 0.5 | Proposed architecture, dependency map, entities, integrations and Phase 1 plan documented | Met |
| 0.6 | Owner has reviewed and approved | **Pending owner** |

Phase-level acceptance criteria for all phases are in `PROJECT_MASTER_PLAN.md` §2.

---

## 11. Phase 1 implementation plan

Phase 1 (product requirements, IA, architecture, database design, acceptance criteria) has been **drafted in parallel** with this report. It cannot be finalised until P1 decisions are answered.

| Step | Activity | Owner | Output |
|---|---|---|---|
| 1.1 | Review this report and the Phase 1 drafts | Owner | comments / approval of Phase 0 |
| 1.2 | Answer P1 decisions (D-01…D-06, D-11, D-12, D-17, D-19, D-22…D-26) | Owner | decision log |
| 1.3 | Provide one real location's inventory (floors, spaces, capacities, sizes, current occupancy) as a spreadsheet | Owner / Ops | ERD validation sample |
| 1.4 | Update PRD, business rules and ERD with answers; resolve M-1…M-6; mark ADRs ACCEPTED or REJECTED | Engineering | PRD v1.0, ERD v1.0 |
| 1.5 | Walk through journeys UJ-1…UJ-12 and the role matrix with marketing, ops and sales leads | Owner + team | signed-off journeys and roles |
| 1.6 | Owner approval of Phase 1 | Owner | Phase 1 APPROVED |

**Exact next implementation step after approval:** Phase 3, step 3.1. Initialise Git (D-23) and commit the current baseline. Then switch dev and test to MySQL (D-26) and verify the existing tests pass on MySQL, before installing Filament's panel or any approved package (D-24). Phase 2 (brand system) can run in parallel as soon as the logo arrives (D-11).
