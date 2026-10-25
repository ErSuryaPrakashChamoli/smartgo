# SmartGro — Phase Status Register

| | |
|---|---|
| Last updated | 2026-10-25 |
| Rule | Only the owner can set APPROVED. COMPLETE requires APPROVED plus handover with no open issues for that phase. |

## Summary

| # | Phase | Status | Blocked by / waiting on |
|---|---|---|---|
| 0 | Discovery, audit, requirements clarification | **APPROVED in principle** (project owner, 2026-10-24) | — (open items carried into Phase 1) |
| 1 | PRD, IA, architecture, database design, acceptance criteria | **APPROVED** (owner, 2026-10-24: "continue next phase") | — (deferred items carried to the Phase 3 and Phase 5 gates) |
| 2 | Brand system, UX/UI, design system | **READY FOR REVIEW** (2026-10-25): AC-2.1 to AC-2.4 met with evidence (acceptance record below). **Not approved, not complete.** | **owner sign-off of the visual direction (AC-2.5, P2-C)**; vector master still requested (D-11b, not an acceptance criterion) |
| 3 | Foundation, auth, permissions, security | **IN PROGRESS** (started 2026-10-24) | step 3.1 needs the GitHub organisation name (D-31) and a dedicated MySQL user (R-14); staging needs the hosting decision (D-14); owner authorisation to implement OD-74 (`backoffice.access`) and an explicit Super Admin permission list (acceptance record below) |
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
| Status | **IN PROGRESS** (started 2026-10-24). Acceptance record 2026-10-25 below: AC-3.1 and AC-3.5 blocked on owner inputs; AC-3.2 open for `backoffice.access` (OD-74). |
| Scope | Back-office panel with custom theme and required MFA; the six owner-defined roles and the permission catalogue; user management; audit log; security headers and password policy; architecture tests; CI workflow; GitHub remote and staging (owner inputs) |
| Dependencies | Phase 1 approved. Packages approved under D-24 (installed now: `spatie/laravel-permission`, `spatie/laravel-activitylog`). Redis client and Horizon wait until Redis is installed locally (ADR-0007). |
| Steps | **3.1** GitHub organisation, private repository, push; dedicated MySQL user. *Owner inputs needed: organisation name; run the setup script.* · **3.2** install approved packages · **3.3** Filament panel (configurable path, D-30), brand colours, custom theme · **3.4** users: active flag, panel access by role, required MFA · **3.5** roles and permission catalogue (six roles, OD-33) · **3.6** user management for the Super Admin · **3.7** audit log (logins, failures, user and role changes) and viewer · **3.8** security headers, password policy · **3.9** architecture tests · **3.10** CI workflow · **3.11** staging environment (needs D-14) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 3. AC-3.2 (policy tests) applies to everything that exists in Phase 3; later modules add their own as they are built. |
| Progress (2026-10-24) | **Done locally, not yet committed to a remote:** 3.2 packages (`spatie/laravel-permission` 8.3.2, `spatie/laravel-activitylog` 5.1.3) · 3.3 panel at `BACKOFFICE_PATH` (default `admin`), SmartGro name, primary `#2E8B18`, custom Vite theme, strict authorisation, database transactions · 3.4 `is_active`, `last_login_at`, panel access only for active users holding a staff role, MFA **required** (authenticator app with 8 recovery codes, or email code) · 3.5 six roles and the capability catalogue (`users.manage`, `audit_log.view`), synced by `php artisan access:sync`; first Super Admin via `php artisan access:assign-role` · 3.6 Settings → Staff users (create, edit roles, deactivate, reset two-step login; no delete) · 3.7 audit log for sign-in, failed sign-in, sign-out, role changes, activation changes, two-step login changes and resets, and (since 2026-10-25) role capability changes made by `access:sync`; read-only Settings → Audit log viewer · 3.8 baseline security headers, HSTS in production, password policy (12+ characters, mixed case, numbers; breached-password check in production) · 3.9 Pest architecture tests (`tests/Arch`) · 3.10 CI workflow `.github/workflows/ci.yml` (runs once the remote exists) · back-office logo from the supplied file (OD-39), larger on sign-in pages; button colour shade 600 `#297D15` so button text is white at AA contrast. **Waiting on owner inputs:** 3.1 (D-31 organisation name; R-14 dedicated MySQL user) and 3.11 (D-14 hosting). |
| Acceptance criteria status | AC-3.1: the same checks pass locally (Pint, MySQL tests, `composer audit` 0 advisories, `npm audit` 0 vulnerabilities); a green CI run needs the remote (D-31). AC-3.2: policy tests cover every role for both capabilities that exist so far, allowed and denied; later modules add their own rows. AC-3.3: met (tests prove staff without two-step login are sent to set-up). AC-3.4: met for every §7 action that exists in Phase 3 (sign-in success and failure, MFA changes, role changes, activation changes); the other §7 actions are audited by the modules that introduce them. Property scoping (`property_user`) arrives with properties in Phase 5. |
| Tests and results | 2026-10-24, local MySQL 8.4.11 (`smartgro_testing`): the Phase 3 test files (`tests/Feature/{Actions,Console,Filament,Http,Listeners,Models,Policies,Providers}`, `tests/Arch`) **100 passed, 0 failed**. Pint clean across the repository. Full suite (`php artisan test --compact`, including the concurrency harness): 2026-10-25, **307 passed, 0 failed (758 assertions, exit code 0)**. |
| Known issues | Redis not installed locally (Horizon and the Redis client wait, ADR-0007). No hosting decision yet (D-14). Login throttling is Filament's 5 attempts per minute per IP address, which depends on correct trusted-proxy settings behind Cloudflare (open item in `../operations/DEPLOYMENT_RUNBOOK.md` §7). Content Security Policy is deferred to Phase 14. Back-office dark mode is off: the approved back-office theme is light (OD-48). Back-office access is by role membership until OD-74 is implemented; the Super Admin receives every capability automatically (SECURITY_MODEL §11.3). |

### Phase 3 acceptance record (2026-10-25)

**Result: IN PROGRESS, blocked on owner inputs. Not READY FOR REVIEW.**

| # | Criterion (source) | Evidence examined | Method | Result | Remaining action (owner) |
|---|---|---|---|---|---|
| AC-3.1 | CI runs Pint, MySQL tests, `composer audit` and `npm audit`, all green (master plan) | `.github/workflows/ci.yml` exists. Local runs on 2026-10-25: Pint passed; 307 tests passed; `composer audit` no advisories; `npm audit` 0 vulnerabilities. | executed locally | **BLOCKED**: there is no remote repository, so CI has never run | **Owner:** name the GitHub organisation (D-31). |
| AC-3.2 | A policy test for every role × capability cell, allowed and denied (master plan) | `tests/Feature/Policies/*` cover all six roles for `users.manage` and `audit_log.view` | automated tests | **PASS** for the implemented capabilities. **OPEN** for `backoffice.access`: it is in the matrix (OD-74) but not implemented, and sign-in is still by role membership. | **Owner:** authorise implementing OD-74 and an explicit Super Admin permission list. This changes the sign-in path, so it needs explicit authorisation. |
| AC-3.3 | MFA is enforced (master plan) | `tests/Feature/Providers/Filament/AdminPanelProviderTest.php`, `tests/Feature/Models/UserTest.php` | automated tests | **PASS** | — |
| AC-3.4 | Audit entries for every sensitive action in SECURITY_MODEL §7 (master plan) | `tests/Feature/Listeners/RecordAuthenticationActivityTest.php`; user and role change tests; `tests/Feature/Actions/Access/SyncAccessControlTest.php` (role capability changes, added 2026-10-25) | automated tests | **PASS** for the §7 actions that exist in Phase 3. Until 2026-10-25, a role's capability changes made by `access:sync` were not audited; they are now. The other entries are added by the modules that introduce those actions. | — |
| AC-3.5 | Staging deployed, reachable only by the team (master plan) | none | — | **BLOCKED** | **Owner:** hosting decision (D-14). |
| 3.1 | Push to a private remote; dedicated MySQL user (master plan step 3.1) | none | — | **BLOCKED** | **Owner:** D-31; run the R-14 setup script. |

### Phase 3 owner decision checklist (prepared 2026-10-25)

Nothing below has been done on the owner's behalf. No organisation is named, no script has been run, no host is chosen, and the access matrix is unchanged.

**1. D-31: GitHub organisation (unblocks step 3.1, AC-3.1 and R-11)**
* **Decided:** the repository belongs to a GitHub organisation, not a personal account (OD-29), and the first push is step 3.1 (OD-34).
* **Needed from the owner:**
  * the organisation name;
  * who owns it: your company, the client, or a new organisation (this decides who legally controls the code);
  * at least one other organisation owner, with two-step login required for members (recommended).
* **Current state:** no remote is configured; the GitHub CLI is not installed; there is no SSH key. CI (`.github/workflows/ci.yml`) runs once the remote exists.
* **Steps after the answer:** `docs/operations/LOCAL_DEVELOPMENT.md` §5 (create a private `smartgro` repository, push `main`, protect `main`, enable secret scanning).

**2. R-14: dedicated MySQL user (part of step 3.1)**
* **Today:** SmartGro's local `.env` connects as MySQL `root` to `smart_go`, on a server that also holds other projects' databases.
* **Script:** `docs/operations/scripts/setup-local-mysql.sh`, run by the owner as `SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh` from the project root.
* **Target:** this machine's local MySQL only. It never touches staging or production.
* **Privilege needed:** `sudo` on this machine, which gives MySQL `root` through `auth_socket`.
* **What it changes:**
  1. Creates `smart_go` and `smartgro_testing` if they are missing (both exist today). Nothing is dropped.
  2. Creates or updates the MySQL user `smartgro`@`localhost` and @`127.0.0.1` with a **new random password**, and grants it `ALL PRIVILEGES` on those **two databases only**: no other database, no global privileges, no `GRANT OPTION`. Full rights inside them are needed for migrations and for the test suite rebuilding `smartgro_testing`.
  3. Copies `.env` to `.env.backup`, then replaces the `DB_*` lines in `.env` with the new user. The password is written only to `.env` and never printed.
  4. Runs `php artisan config:clear` and `php artisan migrate` on `smart_go`. On 2026-10-25 all 6 migrations had already run (0 pending), so no schema change is expected.
* **What it does not change:** the `root` account, other projects' databases, and any staging or production setting.
* **After running:**
  * `php artisan test --compact` should pass;
  * your `php artisan serve` will use the new settings, and may restart by itself when `.env` changes;
  * to undo, restore `.env.backup`;
  * re-running the script rotates the password.

**3. D-14: hosting and staging (unblocks step 3.11 and AC-3.5)**
* **Decide:**
  * the hosting approach (candidates in `docs/operations/DEPLOYMENT_RUNBOOK.md` §6: managed Laravel hosting, a managed VPS with a deployment tool, or a cloud provider's managed services; **none selected**);
  * the region (India preferred, pending legal advice on data residency, D-15);
  * account ownership (the client or the contracting entity; no personal accounts for production);
  * who controls the domain and DNS for smartgro.space (Cloudflare is recommended);
  * how staging is restricted to the team (password or Cloudflare Access, D-30);
  * the monthly budget for staging and production.
* **Runtime needs:** PHP 8.5, MySQL 8.4, Redis, queue workers and a scheduler (runbook §2).
* **Order:** staging deploys from `main`, so D-31 comes first.

**4. OD-74: `backoffice.access` by role (needed before AC-3.2 can be completed)**

Proposed (derived, not yet confirmed by the owner): **all six roles** receive `backoffice.access` as a role assignment, because each has back-office duties in SECURITY_MODEL §3.1. This keeps today's access. Today, access comes from holding any of the six roles. Not implemented.

| Role | Proposed | If access is removed from the role, its members cannot use the back office to: | Effect on separation of duties |
|---|---|---|---|
| Super Admin | ✓ | manage staff, roles and two-step login resets; read the audit log; act as second approver | Staff could only be managed from the command line (`access:assign-role`). Many four-eyes rules would lose their fallback approver. **Not recommended.** |
| Operations Manager | ✓ | manage properties, floors, spaces, allocations and imports; approve booking requests and imports (not own); make booking overrides | Booking and import approvals fall to the Super Admin and Property Managers. |
| Property Manager | ✓ | work on their assigned properties (spaces, allocations, bookings, tours, profile drafts); approve booking requests for them (not own) | On-site work moves to the Operations Manager and the Super Admin. |
| Sales/CRM Executive | ✓ | handle enquiries, assign owners, manage tours, enter booking requests, prepare quotations | Sales work falls to the Super Admin alone. |
| Marketing Manager | ✓ | draft, approve (not own) and publish website content, contact details, redirects, brand settings and tracking IDs | With only Super Admins left, publishing needs at least two of them (author and approver). |
| Finance | ✓ | manage and activate prices (not own); edit and verify legal and GST details; approve quotations | Price activation and legal verification need two Super Admins; invoices stay blocked otherwise. |

Removing access from a role does not delete the role's other permissions, but they cannot be used without back-office access. Named members can still be given access as individual exceptions within the role's scope (OD-68).

**Tests required before OD-74 is implemented** (implementation is not authorised yet):
1. Each role assigned `backoffice.access` can enter. Sign-in, the password policy, throttling and required two-step login are unchanged: the existing tests stay green.
2. A user holding a role without the assignment, and without an exception, is refused on every back-office page, route, Livewire action and back-office API.
3. An individual exception within the role's scope grants access. Revoking it, or removing the role, blocks access from the next request.
4. Inactive users stay refused even with the permission.
5. The Super Admin holds exactly the permissions listed for it. Adding a new capability does not grant it to the Super Admin automatically.
6. Policy tests for every role × `backoffice.access`, allowed and denied (AC-3.2).
7. Granting and removing access is audited.
8. Rollout: after `php artisan access:sync`, every current staff user keeps access, so the change cannot lock everyone out. The first Super Admin created with `access:assign-role` can enter.
9. The design-preview access tests (Super Admin only, refused in production) still pass.

**Owner replies needed:**
* the D-31 organisation name and owner;
* run the R-14 script yourself, or decline;
* the D-14 hosting decision;
* confirm or change the OD-74 role list above;
* separately, authorise implementing OD-74 when you want it built.

## Phase 2: Brand system, UX/UI design, responsive design system

| Field | Detail |
|---|---|
| Status | **READY FOR REVIEW** (2026-10-25): AC-2.1 to AC-2.4 are met with evidence (acceptance record below). AC-2.5, the owner's sign-off of the visual direction, is outstanding. **Not APPROVED and not COMPLETE.** Started 2026-10-24 (OD-41). |
| Scope | `PROJECT_MASTER_PLAN.md` §2, Phase 2: tokens sampled from the real logo, type scale, component library, high-fidelity designs (home, city, property, workspace category, enterprise, tour and booking flows, back-office dashboard and key wizards), mobile-first responsive specification |
| Dependencies | Phase 1 approved (OD-38). Logo supplied (OD-39). Photography plan D-12 is deferred, so designs use clearly labelled photo placeholders, never stock or AI images presented as real. |
| Steps | **2.1** colour tokens from the logo, contrast-verified · **2.2** typefaces and fluid type scale · **2.3** logo renditions (favicon, mark, reversed: *need owner approval*) · **2.4** component library with all states · **2.5** visual-direction sample (home page, 360 px and 1440 px) → **owner sign-off (AC-2.5)** · **2.6** remaining high-fidelity templates and flows · **2.7** responsive specification · **2.8** accessibility and state-coverage verification |
| Content rule | Design previews use only confirmed facts (OD-01, OD-03, OD-04, OD-07, OD-08). Anything else (prices, hours, amenities, photography, copy) is a visibly labelled placeholder. Previews are never served in production and carry `noindex`. |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 2, AC-2.1 to AC-2.5 |
| Progress (2026-10-24) | **Built for review:** 2.1 colour tokens sampled from the logo, all 24 pairings in use pass WCAG 2.2 AA · 2.2 Sora + Inter, self-hosted, fluid type scale · 2.4 component library (buttons, form controls, availability badges, prices, photo placeholders, alerts, empty and loading states, cards, breadcrumb, FAQ, header with menus, footer, mobile action bar, call-to-action band) · 2.5 home-page direction sample. Previews at `/design` (local and staging only). Screenshots at 360 px and 1440 px in `../design/review/2026-10-24/`. **Round 9 (OD-42…OD-45):** P2-A, P2-B, P2-D and P2-E approved; **P2-C (visual direction) not signed off**. Since then: 2.3 logo renditions built (tab icon, home-screen icon, reversed logo; `php artisan brand:renditions`) · 2.7 responsive specification written (`../design/DESIGN_SYSTEM.md` §8) · 2.8 started (page-structure and alt-text checks on every preview; no sideways scrolling at 320–1440 px). **Round 10 (OD-46):** tagline lettering white in the dark-background logo, built and verified. **Round 11 (OD-47, OD-48):** step 2.6 designed without waiting for the home-page sign-off, as the owner instructed: locations index; Noida city page with area filter; coming-soon example (Gurugram; "Gurgaon" redirects; hidden cities return 404); property template for A-70, A-71, A-23 and H-105 with placeholders only; a fictional sample property showing the populated template; workspace hub and the template for all seven types; enterprise; enquiry, tour, request-to-book and online-booking journeys with every status state. Back office (Filament, Super Admin only, never in production): dashboard, property create and edit wizard, buildings and floors, workspace products, individual seats, pooled capacity, mixed zones, and cities with visibility. **The home page is unchanged** (pixel-identical at 360 and 1440 px). **Round 12 (OD-49):** back-office items 8–14 designed as previews: pricing, enquiries, booking requests, tours, contact and legal details, website content, permissions review (SECURITY_MODEL §11). **Stopped for owner review (OD-50).** **Outstanding:** AC-2.5 home-page sign-off; owner review of the permission and publishing record (answered 2026-10-25 as OD-51…OD-75, not implemented; no owner question open); vector logo (D-11b); real content, photos, prices and inventory from SmartGro (later phases). |
| Acceptance criteria status | AC-2.1: met for everything built so far (automated test over every pairing in use). AC-2.2: home, components and foundations captured at 360 px and 1440 px; the other templates follow in 2.6. AC-2.3: buttons, form controls and cards have default, hover, focus, disabled, error, empty and loading states; templates in 2.6 reuse them. AC-2.4: nothing taken from competitors or the old site; all graphics are original (photo placeholders, isometric floor illustration). AC-2.5: **not signed off**; the owner is reviewing the screenshots. AC-2.2 now also covers the 12 new public pages (360 and 1440 px) and the back office (1440 px, dashboard also at 360 px). AC-2.3: new components (status, stepper, time picker, choice cards, timeline, placeholder, offering card) have their states in the component gallery. |
| Tests and results | **Full suite, 2026-10-25 (round 16):** `php artisan test --compact` → **307 passed, 0 failed (758 assertions, 38.9 s, exit code 0)**, including the 7 MySQL concurrency tests (also run alone: 7 passed). New tests:<br>• the permissions review lists the same capabilities and role values as SECURITY_MODEL §11;<br>• the category approver choice refuses roles that may not approve booking requests.<br>Pint passed; `composer audit` no advisories; `npm audit` 0 vulnerabilities. Visual: back office 60 of 60 overflow checks pass (360, 768, 1024, 1440 px); new screenshots in `../design/review/2026-10-25/`. **Full suite, 2026-10-24 (after round 12):** `php artisan test --compact` → **305 passed, 0 failed (743 assertions, 38.0 s, exit code 0)**, including the 7 MySQL concurrency tests. New: back-office items 8–14 render for the Super Admin only and never in production; no preview action writes to the database; tour confirmation and enquiry follow-ups send no mail or notification; approving a conflicting booking request is refused; ₹0 refused unless marked free; GSTIN format; authors cannot approve or publish their own content; old-website media refused; the permissions review lists as implemented exactly the capabilities in code. Visual: 75 of 75 public (320–1440 px) and 60 of 60 back-office (360–1440 px) overflow checks pass; home page pixel-identical. **After round 11:** `php artisan test --compact` → **271 passed, 0 failed (634 assertions, 34.1 s, exit code 0)**, including the 7 MySQL concurrency tests. New: preview pages, redirects and 404s, content rules (no invented prices or availability on real properties; requests never shown as confirmed), component states, and the back-office previews (Super Admin only, refused to other roles and in production, forms validated, instant booking refused). Visual: no sideways scrolling on 15 public previews at 320, 360, 768, 1024 and 1440 px (75 of 75); home page pixel-identical to the screenshots under review. **After round 10:** `php artisan test --compact` → **188 passed, 0 failed (429 assertions, 31.8 s, exit code 0)**; of these, logo renditions 17 passed, contrast checks 28 passed, design previews/page structure/components 33 passed. Visual checks: no sideways scrolling at 320, 360, 768, 1024 or 1440 px on any preview; the home page renders pixel-identical to the screenshots under review (1440 and 360 px). **After round 9:** `php artisan test --compact` on local MySQL 8.4 → **183 passed, 0 failed (416 assertions, 29.1 s, exit code 0)**, including the 7 MySQL concurrency-harness tests. Pint clean. Earlier: Phase 2 tests (`tests/Feature/{Services/Design,Http/Middleware/EnsureDesignPreviewIsAvailableTest.php,Http/Controllers,Views}`) **56 passed**; together with the Phase 3 tests and architecture rules **157 passed, 0 failed**. Pint clean. Build: CSS 10.7 KB gzip, first-party JS 0 KB on marketing pages. |
| Known issues | Raster logo only (D-11b): large and print uses wait for a vector master. The reversed logo exists (OD-45, OD-46). Design previews use labelled placeholders for photos, prices, availability and FAQ answers. The back-office previews show the decided permission policy, but enforce nothing (SECURITY_MODEL §11.3). |

### Phase 2 acceptance record (2026-10-25)

Criteria from `PROJECT_MASTER_PLAN.md` §2 (Phase 2) and the checks the owner asked for on 2026-10-25. **Result: READY FOR REVIEW. Not APPROVED and not COMPLETE: AC-2.5 needs the owner's sign-off.**

| # | Criterion (source) | Evidence examined | Method | Result | Remaining action (owner) |
|---|---|---|---|---|---|
| AC-2.1 | Every colour pairing in use passes WCAG 2.2 AA (master plan) | `tests/Feature/Services/Design/DesignTokensTest.php`; `../design/DESIGN_SYSTEM.md` §7.2 | automated test, run in the full suite on 2026-10-25 | **PASS** | — |
| AC-2.2 | Designs cover mobile (360 px) and desktop (1440 px) (master plan) | Screenshots and overflow checks:<br>• `../design/review/2026-10-24/`: public pages at 360 and 1440 px; back office at 1440 px;<br>• `../design/review/2026-10-25/`: all 15 back-office previews at 360 px, and the 6 updated ones at 1440 px;<br>• overflow checks: back office 60 of 60 (360, 768, 1024, 1440 px, 2026-10-25); public 75 of 75 (2026-10-24; public pages unchanged since). | screenshots and width measurement in headless Chromium | **PASS** | — |
| AC-2.3 | Every component has hover, focus, disabled, error, empty and loading states (master plan) | component gallery `/design/components`; `tests/Feature/Views/Components/*` | automated tests and gallery screenshots | **PASS** for the Phase 2 component library. Back-office screens use Filament's own component states. | — |
| AC-2.4 | No competitor layout or asset copied (master plan) | Assets: only the owner's logo and renditions made from it (`public/brand/`, `resources/brand/source/`), self-hosted open-licensed fonts, and placeholders drawn in code. Nothing from the old site (OD-19). | file inventory and review | **PASS** by inventory and review. Layout similarity cannot be proven by a test. | The owner may confirm while reviewing AC-2.5. |
| AC-2.5 | The owner signs off the visual direction (master plan; P2-C) | Register §0.13 to §0.16: "P2-C not signed off". No later sign-off is recorded. | register search | **BLOCKED** | **Owner:** review the home page and templates, then sign off or name changes. |
| D-1 | Deliverables exist and agree (owner check 1) | Deliverables:<br>• tokens and type scale (`DesignTokens`, Sora and Inter);<br>• component library;<br>• high-fidelity previews (`/design`, `/admin/design-previews`);<br>• responsive specification (`DESIGN_SYSTEM.md` §8).<br>The back-office previews were updated to the decided policy on 2026-10-25, and a new test checks the permissions review against SECURITY_MODEL §11. | inspection and automated test | **PASS** | — |
| D-2 | Owner decisions recorded; nothing open treated as approved (owner checks 2, 3) | Q-OW-16 to Q-OW-21 are answered (OD-46 to OD-75). P2-C is open and not treated as approved. D-11b is open. | register review | **PASS** | — |
| D-3 | Contradictions searched and resolved or recorded (owner check 4) | register §0.17 to §0.20 reconciliation tables; code gaps in SECURITY_MODEL §11.3 | repository-wide text search | **PASS** (the code-level gaps are recorded, not hidden) | — |
| D-4 | Tests and checks run; results reported as output (owner checks 5, 6) | Runs on 2026-10-25:<br>• `php artisan test --compact`: 307 passed, 0 failed, 758 assertions, exit code 0;<br>• `tests/Feature/Infrastructure/MySqlConcurrencyHarnessTest.php`: 7 passed;<br>• `vendor/bin/pint --format agent`: passed;<br>• `composer audit`: no advisories;<br>• `npm audit`: 0 vulnerabilities. | executed | **PASS** | — |
| D-5 | No user work overwritten (owner check 7) | The staged `CLAUDE.md` is unchanged and was not committed. | `git diff --cached` | **PASS** | — |
| D-6 | Security and privacy for Phase 2 (owner check 9) | The previews:<br>• are Super Admin only and refused in production;<br>• are `noindex`;<br>• never write to the database;<br>• never send a message.<br>Tests prove each point. | automated tests | **PASS** | — |
| D-7 | Home page frozen (OD-47) | Round 16 changed no public view, route, stylesheet or service (`git diff --name-only`). | change inspection | **PASS** (unchanged by construction) | — |
| D-8 | Step 2.8 accessibility verification (phase steps; not an acceptance criterion) | automated contrast, page-structure, alt-text and overflow checks | automated | **PARTIAL**: no manual assistive-technology review yet; full accessibility testing is Phase 14 | — |
| D-9 | Next phase understood (owner check 10) | master plan §3 dependency map | review | **PASS**. Phase 4 needs Phases 2 **and** 3; Phase 5 needs Phase 3 done. Neither can start. | — |

### AC-2.5 visual review pack (prepared 2026-10-25)

The screenshots are committed and unchanged. On 2026-10-25 each page below was captured again from the current code and compared pixel by pixel with its screenshot: **0 differing pixels for all seven**, so they show the current implementation. The 2026-10-24 folder is still current for the public pages and the property editor. The 2026-10-25 folder holds only the back-office captures updated in round 16.

| Screen | Width | File | What it shows |
|---|---|---|---|
| Home page: the direction sample to sign off (P2-C) | 1440 px | `docs/design/review/2026-10-24/home-1440.webp` | The overall visual direction: approved palette (OD-42), Sora and Inter (OD-43), logo, workspace finder, the seven workspace types, the four Noida properties with "Price on request" and labelled photo placeholders, 3D teaser, the eight customer groups, tour steps, managed-office band, FAQs and footer. |
| Home page | 360 px | `docs/design/review/2026-10-24/home-360.webp` | The same page on a phone: stacked layout, menu button, and the sticky Enquire / Book a tour bar. |
| Property A-70 | 1440 px | `docs/design/review/2026-10-24/property-a-70-1440.webp` | The property template using confirmed facts only. Address A-70, Sector 2, Noida; hours, floors and amenities read "to be confirmed"; photos are placeholders; workspaces are "not listed yet". Also the tour-request form and nearby properties. |
| Request-to-book journey | 360 px | `docs/design/review/2026-10-24/journey-request-to-book-360.webp` | The four steps on a phone (room and time, details, review, request sent) and both outcomes: confirmed, and "could not be confirmed". It uses the fictional Sample property, and a request is never shown as confirmed. |
| Property editor (back office) | 1440 px | `docs/design/review/2026-10-24/admin-property-editor-1440.webp` | Step 1 of the five-step property wizard, pre-filled with A-70's confirmed facts, in the light back-office theme. |
| Permissions review (back office) | 1440 px | `docs/design/review/2026-10-25/admin-permissions-review-1440.webp` | The decided permission matrix (OD-51 to OD-75) by role, including `backoffice.access`, with each row's basis (implemented / approved matrix / owner decision, not built). |
| Permissions review (back office) | 360 px | `docs/design/review/2026-10-25/admin-permissions-review-360.webp` | The same table at phone width. |

**How to view:**
* **Files:** open the paths above in VS Code, or run `xdg-open <path>` from the project root.
* **Live, on your own running server (port 8000):**
  * `http://localhost:8000/design/home`
  * `/design/locations/noida/a-70`
  * `/design/flows/request-to-book`
  * the back-office previews at `/admin/design-previews/property-editor` and `/admin/design-previews/permissions-review`. These need a Super Admin sign-in with two-step login, and are never available in production.
* **Full sets:** `docs/design/review/2026-10-24/` (51 files) and `docs/design/review/2026-10-25/` (21 files).

**Owner reply needed for AC-2.5:** approve the visual direction, or name the changes wanted. Until then Phase 2 stays READY FOR REVIEW: not approved, not complete.

## Phases 4–15
Scope, deliverables and acceptance criteria are defined in `PROJECT_MASTER_PLAN.md` §2. Each phase gets a full entry here when it starts.

---

## Change log

| Date | Change |
|---|---|
| 2026-10-25 | Round 18 (Phase 3, within approved scope):<br>• `access:sync` now writes an audit entry whenever it changes a role's capabilities (role, old and new; no causer). This closes an AC-3.4 gap for "role and permission changes" (SECURITY_MODEL §7).<br>• Three new tests: grant recorded, removal recorded, unchanged sync records nothing.<br>• Full suite 310 passed (763 assertions, exit code 0); concurrency 7 passed; Pint passed.<br>• Skipped because they need owner decisions: 3.1 (D-31, R-14), 3.11 (D-14), OD-74 (back-office access and explicit Super Admin list), trusted proxies and production session settings (D-14).<br>• No permission, role assignment, sign-in, two-step login, schema or `.env` change. Nothing pushed. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 17:<br>• AC-2.5 visual review pack added. The seven requested screenshots were re-captured from the current code: 0 differing pixels. No screenshot regenerated or changed.<br>• Phase 3 owner decision checklist added (D-31, R-14, D-14, OD-74 by role, required tests).<br>• No code, permission, schema or setting changed; no script run; nothing pushed.<br>• Phase 2 stays READY FOR REVIEW; Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 16:<br>• Q-OW-21 answered by owner decisions OD-74 (explicit `backoffice.access` permission) and OD-75 (external tag managers optional, never a bypass of OD-71), recorded as documentation.<br>• Documents reconciled, including the test-strategy journey J-4.<br>• Back-office previews updated to the decided policy (Phase 2, OD-49), with two new tests.<br>• Full suite 307 passed; back office 60 of 60 overflow checks.<br>• Acceptance records added. **Phase 2 → READY FOR REVIEW** (AC-2.5 owner sign-off outstanding; not approved). **Phase 3 stays IN PROGRESS** (D-31, R-14, D-14; OD-74 implementation needs authorisation).<br>• No next phase started: Phase 4 needs Phases 2 and 3, and Phase 5 needs Phase 3. |
| 2026-10-25 | Round 15: Q-OW-20 and D-17 resolved by owner decisions OD-68…OD-73:<br>• permissions assigned to a role apply to all members, with optional individual exceptions within the role's scope (precedence, revocation and audit documented);<br>• contact-detail approvers confirmed;<br>• the Super Admin may approve brand changes but never publishes what they approved;<br>• tracking IDs need independent approval;<br>• the creator or submitter of a booking request or reschedule never approves it;<br>• one content approval by a non-author is enough.<br>Reconciled SECURITY_MODEL (§3, §3.1, §5, §7, §11; new §11.6 permitted combinations and failure cases), BUSINESS_RULES, PRD, INFORMATION_ARCHITECTURE, INTEGRATION_CONTRACTS, DATABASE_ERD (gap notes only), DESIGN_SYSTEM, CLIENT_INPUTS and the decision register. **Documentation only:** not implemented; no code, permission, role, schema, test, preview or setting changed. Remaining points: Q-OW-21. No implementation phase started. Phase 2 stays IN PROGRESS, waiting for owner review. |
| 2026-10-25 | Round 14: the owner's answers to Q-OW-19 recorded as OD-58…OD-67. They cover role names for now, per-user explicit grants, rejection, legal verification, reschedule approval with an availability re-check every time, and independent approval of contact details, redirects and brand settings. Approver ≠ publisher and author ≠ approver now apply to every role, including the Super Admin. Owner response to R-20 recorded: strict separation of duties is kept, and SECURITY_MODEL §11.5 explains when a second person is needed. Reconciled SECURITY_MODEL §3, §3.1, §7 and §11, BUSINESS_RULES (BR-BK-13, BR-CMS-9), PRD, INFORMATION_ARCHITECTURE, SYSTEM_ARCHITECTURE, API_CONTRACTS, DATABASE_ERD (gap notes only), DESIGN_SYSTEM and CLIENT_INPUTS. **Documentation only:** no permission, role, code, schema or setting changed. Remaining points: Q-OW-20. No Phase 3 work started. Phase 2 stays IN PROGRESS, waiting for owner review. |
| 2026-10-25 | Round 13: the owner's answers to Q-OW-18 recorded as OD-51…OD-57 (permissions for enquiry assignment, booking-request approval, contact details, legal and GST editing and verification, publishing, headcount). SECURITY_MODEL §3, §3.1, §7 and §11, BUSINESS_RULES, DATABASE_ERD §4.1, CLIENT_INPUTS C-5.3 and DESIGN_SYSTEM §10 reconciled. **Documentation only:** the decisions are approved policy, not implemented; no permission, role assignment, code or database change. Implementation and test gaps listed in SECURITY_MODEL §11.3; open points Q-OW-19. No Phase 3 work started. Phase 2 stays IN PROGRESS, waiting for owner review. |
| 2026-10-24 | Round 12 (OD-49, OD-50): back-office items 8–14 designed as previews with safety tests; full suite 305 passed (no database writes, no messages, approval cannot override availability). Visual: 75 of 75 public and 60 of 60 back-office overflow checks pass; home page pixel-identical. Phase 2 stays IN PROGRESS, waiting for owner review. |
| 2026-10-24 | Round 11 (OD-47, OD-48): remaining public page designs, journeys and back-office screens 1–7 plus cities built as previews; home page unchanged (pixel-identical). Brief truncated after "7. Mixed zones" (Q-OW-17). |
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
