# SmartGro — Phase Status Register

| | |
|---|---|
| Last updated | 2026-10-24 |
| Rule | Only the owner can set APPROVED. COMPLETE requires APPROVED plus handover with no open issues for that phase. |

## Summary

| # | Phase | Status | Blocked by / waiting on |
|---|---|---|---|
| 0 | Discovery, audit, requirements clarification | **APPROVED in principle** (project owner, 2026-10-24) | — (open items carried into Phase 1) |
| 1 | PRD, IA, architecture, database design, acceptance criteria | **APPROVED** (owner, 2026-10-24: "continue next phase") | — (deferred items carried to the Phase 3 and Phase 5 gates) |
| 2 | Brand system, UX/UI, design system | **IN PROGRESS** (started 2026-10-24, OD-41) | **owner review of the home-page screenshots (AC-2.5, P2-C)**; the remaining layouts wait for that approval; vector master still requested (D-11b) |
| 3 | Foundation, auth, permissions, security | **IN PROGRESS** (started 2026-10-24) | step 3.1 needs the GitHub organisation name (D-31) and a dedicated MySQL user (R-14); staging needs the hosting decision (D-14) |
| 4 | CMS, page builder, media, publishing | NOT STARTED | Phases 2, 3 |
| 5 | Inventory, pricing, availability | NOT STARTED | Phase 3; entry gate: floors and real inventory, pricing values PR-1…PR-11 |
| 6 | Public website | NOT STARTED | Phases 2, 4, 5 |
| 7 | 3D digital twin | NOT STARTED | Phases 4, 5, 6; D-13 |
| 8 | Enquiries, tours, bookings | NOT STARTED | Phases 5, 6; D-07, D-08, D-10, D-28 |
| 9 | CRM | NOT STARTED | Phase 8; D-09 |
| 10 | Marketing automation | NOT STARTED | Phase 9; D-10, D-15 |
| 11 | SEO | NOT STARTED | Phase 6; D-19 |
| 12 | Analytics and BI | NOT STARTED | Phases 6, 8, 9; D-18 |
| 13 | AI concierge | NOT STARTED | Phases 5, 6, 9; D-21 |
| 14 | Testing, security, accessibility, performance | NOT STARTED | release-scoped phases |
| 15 | Deployment, docs, training, handover | NOT STARTED | Phase 14; D-14 |

---

## Phase 0: Discovery, repository audit and requirements clarification

| Field | Detail |
|---|---|
| Status | **APPROVED in principle** by the project owner on 2026-10-24 ("I approve the Phase 0 discovery findings in principle"). Not marked COMPLETE: its open items (decisions and risks) continue in Phase 1 via `../decisions/DECISION_REGISTER.md`. |
| Scope | Environment and repository audit; stack compatibility; market research; missing inputs; preliminary architecture, entities, integrations; Phase 1 plan |
| Dependencies | none |
| Deliverables | `docs/phases/PHASE_0_DISCOVERY_REPORT.md`; `docs/research/MARKET_BENCHMARK.md`; this register; `PROJECT_MASTER_PLAN.md` |
| Acceptance criteria | Report §10: criteria 0.1–0.5 met; 0.6 (owner approval) pending |
| Tests and results | `php artisan test --compact` on 2026-10-24: **2 passed** (Laravel example tests only). No application code was changed in Phase 0. |
| Evidence | Commands and outputs summarised in report §1; 4 research claims independently re-fetched (benchmark §7); WCAG contrast computed for the brief's palette (design system §3) |
| Known issues | Logo file not yet supplied (blocks Phase 2 finalisation). Floors and inventory pending (Phase 5 gate). Pricing values pending (Phase 5 gate). Existing WordPress site compromised (R-16, owner action outside the project). Local `.env` uses MySQL `root` (R-14, fix at Phase 3 step 3.1). No remote repository until Phase 3 step 3.1 (R-11). |
| Decisions requiring approval | **Phase 1 approval.** Everything else is resolved or deferred to a named phase gate (`../decisions/DECISION_REGISTER.md`). |
| Next phase | Phase 1 finalisation |

## Phase 1: Product requirements, IA, system architecture, database design, acceptance criteria

| Field | Detail |
|---|---|
| Status | **APPROVED** by the project owner on 2026-10-24 (instruction: "continue next phase", recorded as approval of the submitted baseline). AC-1.8 met. Deferred items: AC-1.4 → Phase 5 entry gate; the remote push → Phase 3 step 3.1. |
| Scope | PRD, sitemap and journeys, role matrix, business rules, ERD, system architecture, API contracts, integration inventory, existing-systems assessment, security model, test strategy, design system foundation, deployment plan, inventory import, decision register, phase acceptance criteria; safe local setup (Git, MySQL test configuration) |
| Dependencies | Phase 0 (approved in principle); client inputs; owner approvals |
| Deliverables (v1.2-rc, 2026-10-24) | **Requirements:** `PRD.md` · `INFORMATION_ARCHITECTURE.md` · `BUSINESS_RULES.md` · `CLIENT_INPUTS_REQUIRED.md` · `INVENTORY_IMPORT_SPEC.md` (+ template SG-INV-1.3 + synthetic fixtures). **Architecture:** `SYSTEM_ARCHITECTURE.md` · `DATABASE_ERD.md` · `API_CONTRACTS.md` · `INTEGRATION_CONTRACTS.md` · `EXISTING_SYSTEMS_ASSESSMENT.md`. **Other:** `SECURITY_MODEL.md` · `TEST_STRATEGY.md` · `DESIGN_SYSTEM.md` (foundation) · `DEPLOYMENT_RUNBOOK.md` (plan) · `LOCAL_DEVELOPMENT.md` · `DECISION_REGISTER.md` · ADR-0001…0014 (0001/0002 ACCEPTED) · `EXISTING_WEBSITE_AUDIT.md` |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2: AC-1.1, 1.2, 1.3, 1.5, 1.6 met; AC-1.4 deferred (OD-30/32); AC-1.7 met as amended (OD-34); AC-1.8 pending. Evidence table: `PHASE_1_PROGRESS_REPORT.md` §0E. |
| Tests and results | 2026-10-24: 9/9 passed ×4 runs on a throwaway MySQL 8.4.11, then **9/9 on the owner's local MySQL** (`smartgro_testing`, 17.0 s; harness left 0 tables; dev DB `smart_go` unchanged). `*_testing` guard verified; Pint clean. |
| Evidence | Git history (`git log`); test outputs (TEST_STRATEGY §7); workbook structure read-back and LibreOffice render (INVENTORY_IMPORT_SPEC §7); contrast calculations (DESIGN_SYSTEM §3) |
| Known issues | Logo file not in the project (D-11b, Q-OW-10). Pricing brief §9 not received (Q-OW-9). Floor allocation unknown (Q-OW-1). Existing website compromised (R-16, owner action). No remote repository yet (R-11). Local dev `.env` still on SQLite until A-1. M-1…M-6 open. |
| Decisions requiring approval | Owner approvals: D-24, D-25, D-31, D-32, D-34 (ADR-0003…0014), D-35 (SG-INV-1.3). Owner inputs: Q-OW-1, Q-OW-9, Q-OW-10 (logo, later), Q-OW-11, Q-OW-12, D-02 (floors, addresses, inventory), D-33; D-01 legal details (may defer to Phase 6). |
| Next phase | Phase 2 (needs the logo) and Phase 3 (needs D-24, D-25), in parallel, **only after Phase 1 approval** |

## Phase 3: Application foundation, authentication, permissions, security

| Field | Detail |
|---|---|
| Status | **IN PROGRESS** (started 2026-10-24) |
| Scope | Back-office panel with custom theme and required MFA; the six owner-defined roles and the permission catalogue; user management; audit log; security headers and password policy; architecture tests; CI workflow; GitHub remote and staging (owner inputs) |
| Dependencies | Phase 1 approved. Packages approved under D-24 (installed now: `spatie/laravel-permission`, `spatie/laravel-activitylog`). Redis client and Horizon wait until Redis is installed locally (ADR-0007). |
| Steps | **3.1** GitHub organisation, private repository, push; dedicated MySQL user. *Owner inputs needed: organisation name; run the setup script.* · **3.2** install approved packages · **3.3** Filament panel (configurable path, D-30), brand colours, custom theme · **3.4** users: active flag, panel access by role, required MFA · **3.5** roles and permission catalogue (six roles, OD-33) · **3.6** user management for the Super Admin · **3.7** audit log (logins, failures, user and role changes) and viewer · **3.8** security headers, password policy · **3.9** architecture tests · **3.10** CI workflow · **3.11** staging environment (needs D-14) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 3. AC-3.2 (policy tests) applies to everything that exists in Phase 3; later modules add their own as they are built. |
| Progress (2026-10-24) | **Done locally, not yet committed to a remote:** 3.2 packages (`spatie/laravel-permission` 8.3.2, `spatie/laravel-activitylog` 5.1.3) · 3.3 panel at `BACKOFFICE_PATH` (default `admin`), SmartGro name, primary `#2E8B18`, custom Vite theme, strict authorisation, database transactions · 3.4 `is_active`, `last_login_at`, panel access only for active users holding a staff role, MFA **required** (authenticator app with 8 recovery codes, or email code) · 3.5 six roles and the capability catalogue (`users.manage`, `audit_log.view`), synced by `php artisan access:sync`; first Super Admin via `php artisan access:assign-role` · 3.6 Settings → Staff users (create, edit roles, deactivate, reset two-step login; no delete) · 3.7 audit log for sign-in, failed sign-in, sign-out, role changes, activation changes, two-step login changes and resets; read-only Settings → Audit log viewer · 3.8 baseline security headers, HSTS in production, password policy (12+ characters, mixed case, numbers; breached-password check in production) · 3.9 Pest architecture tests (`tests/Arch`) · 3.10 CI workflow `.github/workflows/ci.yml` (runs once the remote exists) · back-office logo from the supplied file (OD-39), larger on sign-in pages; button colour shade 600 `#297D15` so button text is white at AA contrast. **Waiting on owner inputs:** 3.1 (D-31 organisation name; R-14 dedicated MySQL user) and 3.11 (D-14 hosting). |
| Acceptance criteria status | AC-3.1: the same checks pass locally (Pint, MySQL tests, `composer audit` 0 advisories, `npm audit` 0 vulnerabilities); a green CI run needs the remote (D-31). AC-3.2: policy tests cover every role for both capabilities that exist so far, allowed and denied; later modules add their own rows. AC-3.3: met (tests prove staff without two-step login are sent to set-up). AC-3.4: met for every §7 action that exists in Phase 3 (sign-in success and failure, MFA changes, role changes, activation changes); the other §7 actions are audited by the modules that introduce them. Property scoping (`property_user`) arrives with properties in Phase 5. |
| Tests and results | 2026-10-24, local MySQL 8.4.11 (`smartgro_testing`): the Phase 3 test files (`tests/Feature/{Actions,Console,Filament,Http,Listeners,Models,Policies,Providers}`, `tests/Arch`) **100 passed, 0 failed**. Pint clean across the repository. Full suite (`php artisan test --compact`, including the concurrency harness) to be run by the owner. |
| Known issues | Phase 2 blocked on the logo file. Redis not installed locally (Horizon and the Redis client wait, ADR-0007). No hosting decision yet (D-14). Login throttling is Filament's 5 attempts per minute per IP address, which depends on correct trusted-proxy settings behind Cloudflare (open item in `../operations/DEPLOYMENT_RUNBOOK.md` §7). Content Security Policy is deferred to Phase 14. Back-office dark mode is off until a reversed logo exists. |

## Phase 2: Brand system, UX/UI design, responsive design system

| Field | Detail |
|---|---|
| Status | **IN PROGRESS** (started 2026-10-24, OD-41) |
| Scope | `PROJECT_MASTER_PLAN.md` §2, Phase 2: tokens sampled from the real logo, type scale, component library, high-fidelity designs (home, city, property, workspace category, enterprise, tour and booking flows, back-office dashboard and key wizards), mobile-first responsive specification |
| Dependencies | Phase 1 approved (OD-38). Logo supplied (OD-39). Photography plan D-12 is deferred, so designs use clearly labelled photo placeholders, never stock or AI images presented as real. |
| Steps | **2.1** colour tokens from the logo, contrast-verified · **2.2** typefaces and fluid type scale · **2.3** logo renditions (favicon, mark, reversed: *need owner approval*) · **2.4** component library with all states · **2.5** visual-direction sample (home page, 360 px and 1440 px) → **owner sign-off (AC-2.5)** · **2.6** remaining high-fidelity templates and flows · **2.7** responsive specification · **2.8** accessibility and state-coverage verification |
| Content rule | Design previews use only confirmed facts (OD-01, OD-03, OD-04, OD-07, OD-08). Anything else (prices, hours, amenities, photography, copy) is a visibly labelled placeholder. Previews are never served in production and carry `noindex`. |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 2, AC-2.1 to AC-2.5 |
| Progress (2026-10-24) | **Built for review:** 2.1 colour tokens sampled from the logo, all 24 pairings in use pass WCAG 2.2 AA · 2.2 Sora + Inter, self-hosted, fluid type scale · 2.4 component library (buttons, form controls, availability badges, prices, photo placeholders, alerts, empty and loading states, cards, breadcrumb, FAQ, header with menus, footer, mobile action bar, call-to-action band) · 2.5 home-page direction sample. Previews at `/design` (local and staging only). Screenshots at 360 px and 1440 px in `../design/review/2026-10-24/`. **Round 9 (OD-42…OD-45):** P2-A, P2-B, P2-D and P2-E approved; **P2-C (visual direction) not signed off**. Since then: 2.3 logo renditions built (tab icon, home-screen icon, reversed logo; `php artisan brand:renditions`) · 2.7 responsive specification written (`../design/DESIGN_SYSTEM.md` §8) · 2.8 started (page-structure and alt-text checks on every preview; no sideways scrolling at 320–1440 px). **Round 10 (OD-46):** tagline lettering white in the dark-background logo, built and verified. **Waiting:** step 2.6 (remaining page layouts) is **not started** and waits for the owner's approval of the home page (AC-2.5). |
| Acceptance criteria status | AC-2.1: met for everything built so far (automated test over every pairing in use). AC-2.2: home, components and foundations captured at 360 px and 1440 px; the other templates follow in 2.6. AC-2.3: buttons, form controls and cards have default, hover, focus, disabled, error, empty and loading states; templates in 2.6 reuse them. AC-2.4: nothing taken from competitors or the old site; all graphics are original (photo placeholders, isometric floor illustration). AC-2.5: **not signed off**; the owner is reviewing the screenshots (round 9). |
| Tests and results | **Full suite, 2026-10-24 (after round 10):** `php artisan test --compact` → **188 passed, 0 failed (429 assertions, 31.8 s, exit code 0)**; of these, logo renditions 17 passed, contrast checks 28 passed, design previews/page structure/components 33 passed. Visual checks: no sideways scrolling at 320, 360, 768, 1024 or 1440 px on any preview; the home page renders pixel-identical to the screenshots under review (1440 and 360 px). **After round 9:** `php artisan test --compact` on local MySQL 8.4 → **183 passed, 0 failed (416 assertions, 29.1 s, exit code 0)**, including the 7 MySQL concurrency-harness tests. Pint clean. Earlier: Phase 2 tests (`tests/Feature/{Services/Design,Http/Middleware/EnsureDesignPreviewIsAvailableTest.php,Http/Controllers,Views}`) **56 passed**; together with the Phase 3 tests and architecture rules **157 passed, 0 failed**. Pint clean. Build: CSS 10.7 KB gzip, first-party JS 0 KB on marketing pages. |
| Known issues | Raster logo only (D-11b): large and print uses wait for a vector master. No reversed logo, so the logo appears only on light backgrounds (P2-E). Design previews use labelled placeholders for photos, prices, availability and FAQ answers. |

## Phases 4–15
Scope, deliverables and acceptance criteria are defined in `PROJECT_MASTER_PLAN.md` §2. Each phase gets a full entry here when it starts.

---

## Change log

| Date | Change |
|---|---|
| 2026-10-24 | Round 10: OD-46 (tagline lettering white in the dark-background logo) recorded and implemented; regression checks show only the tagline lettering changed. Home page still not signed off; remaining layouts not started. |
| 2026-10-24 | Round 9: P2-A, P2-B, P2-D, P2-E approved (OD-42…OD-45); P2-C not signed off. Logo renditions, responsive specification and preview accessibility checks added. Q-OW-16 raised (tagline on dark backgrounds). |
| 2026-10-24 | Phase 2 steps 2.1, 2.2, 2.4 and 2.5 built: logo-sampled tokens (all pairings AA), Sora + Inter, component library with states, home-page direction sample, previews and screenshots at 360/1440 px. 157 tests pass. Waiting on the owner for AC-2.5 and P2-A…P2-E. |
| 2026-10-24 | Round 8 ("ok smart gro ok phase 2"): written name stays "SmartGro" (OD-40); **Phase 2 started** (OD-41). |
| 2026-10-24 | Logo supplied by the owner (OD-39) and stored unmodified; shown in the back office. Phase 2 unblocked (not started). Open: Q-OW-15 (written name vs "SMART GRO" wordmark); vector master still requested (D-11b). |
| 2026-10-24 | Phase 3 steps 3.2–3.10 implemented locally: back-office panel, required two-step login, six roles and capabilities, staff user management, audit log and viewer, security headers, password policy, architecture tests, CI workflow. 100 Phase 3 tests pass on MySQL. Phase 3 remains IN PROGRESS (3.1 and 3.11 wait on owner inputs). |
| 2026-10-24 | Register created. Phase 0 → READY FOR REVIEW. Phase 1 → IN PROGRESS with drafts. |
| 2026-10-24 | **Phase 1 APPROVED** by the owner ("continue next phase"). Phase 3 started; Phase 2 blocked on the logo file. |
| 2026-10-24 | Round 7: "ok" accepts S-1, S-2, Q-OW-13, Q-OW-14 (OD-34…OD-37). Documents set to Phase 1 baseline v1.0. Tests 9/9 on local MySQL, Pint clean. **Phase 1 → READY FOR REVIEW.** |
| 2026-10-24 | Round 6: floor allocation stays pending (OD-32); six owner-defined roles (OD-33) supersede the drafted roles. Permission matrix rebuilt; template SG-INV-1.3 (column rename). Proposals Q-OW-13/14 raised. Phase 1 remains IN PROGRESS. |
| 2026-10-24 | Round 5: "Approve all as recommended", recorded as OD-23…OD-31. ADR-0003…0014 accepted. Pricing-configuration proposal drafted (no prices). Remaining before READY FOR REVIEW: S-1, S-2. Phase 1 remains IN PROGRESS. |
| 2026-10-24 | Round 4: OD-21 (old URLs redirect at launch) and OD-22 (coming-soon page for visible cities without a property) recorded. Floors, pricing and approvals still unanswered; unblocking options P-1…P-3 offered. Phase 1 remains IN PROGRESS. |
| 2026-10-24 | Round 3: owner answers OD-12…OD-20 incorporated (each address its own property; all owned; official city names; hidden cities with admin toggle; nothing from the old site; hybrid zones; "Property" naming; logo later). ERD v1.2-rc, SG-INV-1.2. Floors per property still open. Phase 1 remains IN PROGRESS. |
| 2026-10-24 | Round 2: owner decisions OD-01…OD-11 incorporated (brand, NCR geography, segments, seven categories, four Noida properties, owned/leased models, inventory modes, no existing software, existing website). ERD v1.1-rc, ADR-0013/0014, SG-INV-1.1, existing-site audit (compromise found). Phase 1 remains IN PROGRESS. |
| 2026-10-24 | Phase 0 → APPROVED in principle (owner). Git initialised (D-23). ADR-0001/0002 accepted. Provisional palette approved (D-11a). MySQL test configuration and concurrency harness added and verified. Phase 1 documents → v1.0-rc. Decision register created. |
