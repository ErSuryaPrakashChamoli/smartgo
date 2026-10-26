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
| 2 | Brand system, UX/UI, design system | **APPROVED** (owner, 2026-10-26: "Phase 2 visual direction: Approve", OD-82). All of AC-2.1 to AC-2.5 met. Not COMPLETE: the open items below are carried forward. | — (open, not gating: vector master D-11b; manual accessibility review, Phase 14) |
| 3 | Foundation, auth, permissions, security | **APPROVED with deferred items** (owner, 2026-10-26: "Phase 3 acceptance: Accept", OD-83). Not COMPLETE. | Deferred to the handover (OD-79): AC-3.1 CI, AC-3.5 staging, step 3.1 push, step 3.11; R-21 and D-14. Owner action: R-14. An interim private backup remote exists (OD-80). |
| 4 | CMS, page builder, media, publishing | **IN PROGRESS** (started 2026-10-26) | profiles (step 4.7) wait for the Phase 5 tables; the moderated usability test (AC-4.2) needs the owner to arrange it |
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
| Status | **APPROVED with deferred items** (owner, 2026-10-26, OD-83). AC-3.2, AC-3.3 and AC-3.4 pass. AC-3.1 and AC-3.5 are deferred until local completion (OD-79). Step 3.1's MySQL user waits on R-14. **Not COMPLETE**: the deferred items stay open until the handover. |
| Scope | Back-office panel with custom theme and required MFA; the six owner-defined roles and the permission catalogue; user management; audit log; security headers and password policy; architecture tests; CI workflow; GitHub remote and staging (owner inputs) |
| Dependencies | Phase 1 approved. Packages approved under D-24 (installed now: `spatie/laravel-permission`, `spatie/laravel-activitylog`). Redis client and Horizon wait until Redis is installed locally (ADR-0007). |
| Steps | **3.1** GitHub organisation, private repository, push; dedicated MySQL user. *Owner actions needed: create the `Smartgro` organisation, repository and push credential, then authorise the push (D-31); run the setup script (R-14).* · **3.2** install approved packages · **3.3** Filament panel (configurable path, D-30), brand colours, custom theme · **3.4** users: active flag, panel access by role, required MFA · **3.5** roles and permission catalogue (six roles, OD-33) · **3.6** user management for the Super Admin · **3.7** audit log (logins, failures, user and role changes) and viewer · **3.8** security headers, password policy · **3.9** architecture tests · **3.10** CI workflow · **3.11** staging environment (needs D-14) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 3. AC-3.2 (policy tests) applies to everything that exists in Phase 3; later modules add their own as they are built. |
| Progress (2026-10-24) | **Done locally, not yet committed to a remote:** 3.2 packages (`spatie/laravel-permission` 8.3.2, `spatie/laravel-activitylog` 5.1.3) · 3.3 panel at `BACKOFFICE_PATH` (default `admin`), SmartGro name, primary `#2E8B18`, custom Vite theme, strict authorisation, database transactions · 3.4 `is_active`, `last_login_at`, panel access only for active users holding a staff role, MFA **required** (authenticator app with 8 recovery codes, or email code) · 3.5 six roles and the capability catalogue (`users.manage`, `audit_log.view`), synced by `php artisan access:sync`; first Super Admin via `php artisan access:assign-role` · 3.6 Settings → Staff users (create, edit roles, deactivate, reset two-step login; no delete) · 3.7 audit log for sign-in, failed sign-in, sign-out, role changes, activation changes, two-step login changes and resets, and (since 2026-10-25) role capability changes made by `access:sync`; read-only Settings → Audit log viewer · 3.8 baseline security headers, HSTS in production, password policy (12+ characters, mixed case, numbers; breached-password check in production) · 3.9 Pest architecture tests (`tests/Arch`) · 3.10 CI workflow `.github/workflows/ci.yml` (runs once the remote exists) · back-office logo from the supplied file (OD-39), larger on sign-in pages; button colour shade 600 `#297D15` so button text is white at AA contrast. **Waiting on owner inputs:** 3.1 (D-31 owner actions and push authorisation; R-14 dedicated MySQL user) and 3.11 (D-14 hosting). |
| Acceptance criteria status | AC-3.1: the same checks pass locally (Pint, MySQL tests, `composer audit` 0 advisories, `npm audit` 0 vulnerabilities); a green CI run needs the remote (D-31). AC-3.2: policy tests cover every role for both capabilities that exist so far, allowed and denied; later modules add their own rows. AC-3.3: met (tests prove staff without two-step login are sent to set-up). AC-3.4: met for every §7 action that exists in Phase 3 (sign-in success and failure, MFA changes, role changes, activation changes); the other §7 actions are audited by the modules that introduce them. Property scoping (`property_user`) arrives with properties in Phase 5. |
| Tests and results | 2026-10-24, local MySQL 8.4.11 (`smartgro_testing`): the Phase 3 test files (`tests/Feature/{Actions,Console,Filament,Http,Listeners,Models,Policies,Providers}`, `tests/Arch`) **100 passed, 0 failed**. Pint clean across the repository. Full suite (`php artisan test --compact`, including the concurrency harness): latest run 2026-10-25 (round 24), **310 passed, 0 failed (763 assertions, exit code 0)**. |
| Known issues | Redis not installed locally (Horizon and the Redis client wait, ADR-0007). No hosting decision yet (D-14). Login throttling is Filament's 5 attempts per minute per IP address, which depends on correct trusted-proxy settings behind Cloudflare (open item in `../operations/DEPLOYMENT_RUNBOOK.md` §7). Content Security Policy is deferred to Phase 14. Back-office dark mode is off: the approved back-office theme is light (OD-48). |

### Phase 3 acceptance record (updated 2026-10-25, round 25)

**Result: APPROVED with deferred items (owner acceptance, 2026-10-26, OD-83). Not COMPLETE.** All locally actionable Phase 3 work is done. Everything that remains is one of:
* deferred by the owner (OD-79: GitHub and deployment wait until the application is fully developed and verified locally);
* an owner action (R-14).

Local checks passing are **not** the same as a green GitHub CI run or a staging deployment.

| # | Criterion (source) | Evidence examined | Method | Result | Remaining action (owner) |
|---|---|---|---|---|---|
| AC-3.1 | CI runs Pint, MySQL tests, `composer audit` and `npm audit`, all green (master plan) | `.github/workflows/ci.yml` (valid; same steps). Local runs, round 25:<br>• Pint: exit code 0;<br>• tests: 320 passed, exit code 0;<br>• `composer audit`: no advisories, exit code 0;<br>• `npm audit --audit-level=high`: 0 vulnerabilities, exit code 0;<br>• `npm run build`: exit code 0. | executed locally | **DEFERRED** (OD-79): no remote, so CI has never run. Local results do not count as CI. | At handover: checklist G-1 to G-7 below. |
| AC-3.2 | A policy test for every role × capability cell, allowed and denied (master plan) | `tests/Feature/Policies/*`, `ListUsersTest`, `ManageActivitiesTest`: all six roles, allowed and denied, for `users.manage` and `audit_log.view`. `AdminPanelProviderTest` and `SyncAccessControlTest` cover `backoffice.access`:<br>• every role is let in;<br>• every back-office page refuses a role without it;<br>• exceptions and revocation;<br>• no entry without a staff role;<br>• recovery after `access:sync`.<br>The policies and panel access check permissions, never role names alone. | code review and automated tests | **PASS** (`backoffice.access` implemented 2026-10-26, OD-81) | — |
| AC-3.3 | MFA is enforced (master plan) | `AdminPanelProvider` (two-step login required: authenticator app with recovery codes, or email code); `AdminPanelProviderTest`; `LoginTest` (full sign-in through the password step and the emailed code) | code review and automated tests | **PASS** | — |
| AC-3.4 | Audit entries for every sensitive action in SECURITY_MODEL §7 (master plan) | `RecordAuthenticationActivityTest`; tests for user, role, two-step and role-capability changes; `LoginTest` (wrong two-step code, added in round 25) | code review and automated tests | **PASS** for the §7 actions that exist in Phase 3 | — |
| AC-3.5 | Staging deployed, reachable only by the team (master plan) | none | — | **DEFERRED** (OD-79); also needs D-14 | At handover: D-14. |
| 3.1 | Push to a private remote (step 3.1) | no remote; destination conflict R-21 | read-only check | **DEFERRED** (OD-79); R-21 open | At handover. |
| 3.1 | Dedicated MySQL user (R-14, step 3.1) | the local `.env` still connects as MySQL `root` | read-only check | **BLOCKED: owner action** | Run the setup script yourself, or decline (R-14). |
| 3.11 | Staging environment (step 3.11) | — | — | **DEFERRED** (OD-79); needs D-14 | — |
| — | Breached-password check (production only) | `AppServiceProvider` adds `uncompromised()` in production | code review | **NOT VERIFIED**: it runs only in production and calls an external service, so local tests do not exercise it | Verify on staging. |
| — | Trusted proxies, production session settings, back-office network restriction (D-30) | `../operations/DEPLOYMENT_RUNBOOK.md` §7 | — | **DEFERRED** (OD-79); depend on D-14 | — |

### Phase 3 local completion work (round 25, 2026-10-25)

**Inspected:**
* the panel provider;
* `User` (panel access, hidden attributes, audit options);
* the policies, access actions and console commands;
* the authentication audit listener, security headers and password policy;
* Filament's own login, two-step challenge and password-reset pages (vendor code);
* every Phase 3 test.

**Found and fixed:**
1. **The "forgot password" page revealed which email addresses are staff accounts.**
   * Before: an unknown address got "We can't find a user with that email address." and a recently used one "Please wait before retrying.". Only a success cleared the form.
   * Now: `App\Filament\Pages\Auth\RequestPasswordReset` gives every request the same answer and an empty form. Reset links still go only to active staff.
2. **A wrong two-step login code was not audited**, although §7 requires "login success and failure". Filament fires no event for it, unlike a wrong password.
   * Now: `App\Filament\Pages\Auth\Login` records it as `two_factor_failed` through `App\Actions\Access\RecordFailedTwoStepLogin`. The entry holds the user, IP address and browser, never the code.
   * In every other way the page behaves exactly as Filament's.

Both pages are registered in `AdminPanelProvider`.

**Verified correct, unchanged:**
* two-step login is required, with recovery codes, and staff without it are sent to set it up;
* "Signed in" is recorded only after the second step, and wrong passwords are audited without the password;
* the password, remember token, authenticator secret and recovery codes are hidden from serialisation;
* the policies are permission-based;
* staff accounts cannot be deleted, and nobody can deactivate themselves or remove their own Super Admin role;
* non-staff and deactivated staff get 403;
* the login limit is 5 attempts a minute (now tested).

**Tests added:** 10, in `tests/Feature/Filament/Pages/Auth/LoginTest.php` (5) and `RequestPasswordResetTest.php` (5). Each was checked by temporarily removing the fix it covers; the matching test failed every time.

**Role-name checks remaining:**
* `User::canAccessPanel()` checks for a staff role only as the scope limit; entry needs the `backoffice.access` permission (OD-74, implemented 2026-10-26);
* the Super Admin-only design-preview gate (Phase 2 tooling, never in production).

### Phase 3 work in round 26 (2026-10-26): OD-74 implemented

Authorised by the owner (OD-81: all six roles; implement now).

**Changes:**
* `App\Enums\Access\Capability::AccessBackoffice` (`backoffice.access`);
* `StaffRole::capabilities()` now lists every role explicitly: the Super Admin gets `backoffice.access`, `users.manage` and `audit_log.view`; the other roles get `backoffice.access`;
* `User::canAccessPanel()` now needs an active account, a staff role (scope, OD-68) and the permission;
* the permissions-review preview marks the row implemented.

Sign-in, password policy, throttling and two-step login are unchanged.

**Tests:**
* 5 new tests: every page refused without the permission; an exception granted then revoked; a direct grant without a role refused; recovery after `access:sync`; a console-created first Super Admin can enter;
* `SyncAccessControlTest` updated for the explicit lists and their audit entries.

Three deliberate breaks (permission check, role scope, role assignment) were each caught. Rollout: run `php artisan access:sync` before the new code serves requests. Until then no role holds `backoffice.access`, and staff are refused.

### Deferred GitHub and deployment checklist (OD-79; for the final handover)

**Interim backup (OD-80):** `main` is on the owner's private personal repository `ErSuryaPrakashChamoli/smartgo` (pushed by the owner on 2026-10-26, commit `427b65f`). It is a backup only; the rows below still apply to the company organisation, and Claude pushes nothing.

| # | Task | Status | Blocker | Owner action |
|---|---|---|---|---|
| G-1 | Company-controlled GitHub organisation (D-31, OD-76, OD-78) | Deferred | R-21: the GitHub name `smartgro` is held by a personal user account, and no organisation `Smartgro` exists | Confirm the organisation's exact name and ownership |
| G-2 | Private repository `smartgro` | Deferred | G-1 | Create it yourself (§5 checklist, OD-77) |
| G-3 | Secure access: two owners if available, required two-factor authentication, least-privilege access, a repository-only push credential | Deferred | G-1, G-2 | Configure it yourself; never share credentials in chat |
| G-4 | Final full-history and repository-content secret review | Deferred (last run 2026-10-25: clean) | — | Re-run immediately before the push (OD-77) |
| G-5 | Remote configuration | Deferred | G-2, G-3 | Authorise |
| G-6 | First push of `main` | Deferred | G-1 to G-5 | Explicit authorisation |
| G-7 | GitHub Actions CI run and branch protection (AC-3.1) | Deferred | G-6 | — |
| H-1 | Hosting provider, region, accounts, DNS and budget (D-14) | Deferred | owner decision | Decide |
| H-2 | Staging environment with team-only access (AC-3.5, step 3.11, D-30) | Deferred | H-1, G-6 | — |
| H-3 | Trusted proxies, production session settings, breached-password check verification | Deferred | H-1 | — |

### Phase 3 owner decision checklist (prepared 2026-10-25)

Nothing below has been done on the owner's behalf. No organisation is named, no script has been run, no host is chosen, and the access matrix is unchanged.

**1. D-31: GitHub organisation (unblocks step 3.1, AC-3.1 and R-11)**
* **Decided:** the repository belongs to a GitHub organisation, not a personal account (OD-29); the first push is step 3.1 (OD-34); the security model is OD-76 (private repository, company-controlled ownership, two owners if available, required two-factor authentication, least-privilege access). Setup checklist: `../operations/LOCAL_DEVELOPMENT.md` §5, approved (OD-77). **Organisation `Smartgro`, owned by Smart Gro Services Private Limited (OD-78).**
* **Status (2026-10-25): name and owner confirmed (OD-78); D-31 stays open.** Pre-push check (2026-10-25, read-only): GitHub has **no organisation `Smartgro`**. The name `smartgro` (GitHub names ignore capitalisation) belongs to a **personal user account created 2017-11-03**, so an organisation cannot use it. `Smartgro/smartgro` is not publicly visible, but whether it exists cannot be confirmed without signing in. No push credential is visible to this machine's terminal. See R-21. Still to happen: the owner creates the organisation and the private repository, and sets up the push credential. The first push is **not authorised**. Pre-push readiness checked read-only: no secrets or `.env` files in the 37 commits, largest file 0.4 MB, CI workflow valid, and the staged `CLAUDE.md` edit is in no commit.
* **Needed from the owner:**
  * create the organisation and the private repository;
  * set up the push credential;
  * authorise the first push separately;
  * add at least one other organisation owner if available, with two-step login required for members (OD-76).
* **Current state (2026-10-26):** the only remote is the owner's interim private backup (OD-80); the GitHub CLI is not installed; there is no SSH key. CI (`.github/workflows/ci.yml`) runs once the remote exists.
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

**4. OD-74: `backoffice.access` by role. Resolved: confirmed and implemented on 2026-10-26 (OD-81)**

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
* D-31: create the organisation, repository and push credential, then authorise the first push;
* run the R-14 script yourself, or decline;
* the D-14 hosting decision;

## Phase 2: Brand system, UX/UI design, responsive design system

| Field | Detail |
|---|---|
| Status | **APPROVED** (owner, 2026-10-26: "Phase 2 visual direction: Approve", OD-82). AC-2.1 to AC-2.4 were met with evidence; AC-2.5, the owner's sign-off of the visual direction, is now given. **Not COMPLETE**: the vector master (D-11b) and a manual accessibility review are carried forward. Started 2026-10-24 (OD-41). |
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

Criteria from `PROJECT_MASTER_PLAN.md` §2 (Phase 2) and the checks the owner asked for on 2026-10-25. **Result: APPROVED (owner, 2026-10-26, OD-82). Not COMPLETE: the vector master (D-11b) and a manual accessibility review are carried forward.**

| # | Criterion (source) | Evidence examined | Method | Result | Remaining action (owner) |
|---|---|---|---|---|---|
| AC-2.1 | Every colour pairing in use passes WCAG 2.2 AA (master plan) | `tests/Feature/Services/Design/DesignTokensTest.php`; `../design/DESIGN_SYSTEM.md` §7.2 | automated test, run in the full suite on 2026-10-25 | **PASS** | — |
| AC-2.2 | Designs cover mobile (360 px) and desktop (1440 px) (master plan) | Screenshots and overflow checks:<br>• `../design/review/2026-10-24/`: public pages at 360 and 1440 px; back office at 1440 px;<br>• `../design/review/2026-10-25/`: all 15 back-office previews at 360 px, and the 6 updated ones at 1440 px;<br>• overflow checks: back office 60 of 60 (360, 768, 1024, 1440 px, 2026-10-25); public 75 of 75 (2026-10-24; public pages unchanged since). | screenshots and width measurement in headless Chromium | **PASS** | — |
| AC-2.3 | Every component has hover, focus, disabled, error, empty and loading states (master plan) | component gallery `/design/components`; `tests/Feature/Views/Components/*` | automated tests and gallery screenshots | **PASS** for the Phase 2 component library. Back-office screens use Filament's own component states. | — |
| AC-2.4 | No competitor layout or asset copied (master plan) | Assets: only the owner's logo and renditions made from it (`public/brand/`, `resources/brand/source/`), self-hosted open-licensed fonts, and placeholders drawn in code. Nothing from the old site (OD-19). | file inventory and review | **PASS** by inventory and review. Layout similarity cannot be proven by a test. | The owner may confirm while reviewing AC-2.5. |
| AC-2.5 | The owner signs off the visual direction (master plan; P2-C) | the owner's message of 2026-10-26: "Phase 2 visual direction: Approve" (register §0.27, OD-82) | owner sign-off | **PASS** | — |
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

## Phase 4: Visual CMS, page builder, media library, publishing workflow

| Field | Detail |
|---|---|
| Status | **IN PROGRESS** (started 2026-10-26, after Phase 2 approval OD-82 and Phase 3 acceptance OD-83) |
| Scope | `PROJECT_MASTER_PLAN.md` §2, Phase 4: block library, pages and sections, navigation, profiles, media library with licensing and renditions, revisions and approvals, previews, scheduling, redirects on slug change |
| Dependencies | Phases 2 and 3 approved (OD-82, OD-83).<br>• **Packages:** `spatie/laravel-medialibrary` and `filament/spatie-laravel-media-library-plugin` are on the approved list (D-24) for Phase 4, but under OD-79 each install needs the owner's explicit approval. The S3 adapter waits for hosting (D-14).<br>• **Profiles:** property and offering profiles (FR-CMS-6) attach to the `properties` and `offerings` tables, which Phase 5 builds.<br>• **Content:** legal-page wording needs counsel (D-15); real photography needs D-12; real copy comes from SmartGro. |
| Rules carried in | The publishing rules:<br>• Draft → Submitted → Approved → Published (BR-CMS-1);<br>• the author never approves, the approver never publishes that version, one approval is enough (BR-CMS-2, BR-CMS-2a, OD-65, OD-73; cases in SECURITY_MODEL §11.6);<br>• contact details, redirects, brand settings and tracking IDs follow the same separation (BR-CMS-9, OD-64 to OD-71).<br>Nothing is taken from the old site (BR-CMS-8), and nothing is invented. |
| Steps | **4.1** content permissions per SECURITY_MODEL §3.1 and §11, the `revisions` model (author, approver and publisher recorded separately) and the approve and publish actions with the §11.6 rules and audit · **4.2** pages: the block library (Filament Builder, already installed), page editor, signed `noindex` preview (BR-CMS-4), scheduling (BR-CMS-5), restore as a new draft (BR-CMS-3, OD-88), redirect on slug change (BR-CMS-6) · **4.3** navigation, header and footer editors (FR-CMS-4) and page search-engine fields (FR-SEO-1, added by the owner's brief) · **4.4** media library with origin, licence, alt text and renditions (FR-CMS-5, BR-MED-*; *needs package approval*) · **4.5** posts, FAQs, testimonials (consent on file), case studies, legal pages (FR-CMS-7) · **4.6** public settings with approval: contact details, brand settings, tracking IDs, manual redirects (FR-CMS-8, OD-64 to OD-71) · **4.7** property and offering profiles (FR-CMS-6; *after Phase 5 tables*) · **4.8** moderated test of UJ-7 and UJ-8 (*owner arranges*) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 4: AC-4.1 FR-CMS-1 to FR-CMS-8 pass; AC-4.2 a Marketing Manager completes UJ-7, and a second approver UJ-8, unaided in a moderated test; AC-4.3 no routine CMS task needs code, JSON or SQL |
| Approvals (2026-10-26) | Packages approved (OD-84; installed at step 4.4). Model additions approved (OD-85). Claude runs additive migrations on `smart_go` after tests pass (OD-86). One local commit per verified step, never pushed (OD-87). |
| Progress | Opened 2026-10-26.<br>**Step 4.1 done (2026-10-26, local):**<br>• `content.edit`, `content.approve` and `content.publish` for the Super Admin and Marketing Manager (SECURITY_MODEL §3.1, §11);<br>• `pages` and `revisions` tables (OD-85): author, editors, approver, publisher and scheduler recorded separately;<br>• `RevisionPolicy` plus actions to create, edit, submit, approve, reject (with a note), publish, schedule and roll back. Publishing locks the page and the version and re-checks the permission under the lock;<br>• `content:publish-scheduled` runs every minute and publishes as the scheduler;<br>• every step audited (log `content`); `ContentPublished` is dispatched after commit.<br>Tests: the suite grew from 325 to 351 (§11.6 cases 1–5, the other failure cases, scheduling, audit, role grants). Full suite **351 passed, 0 failed (993 assertions, exit code 0)**; concurrency 7 passed; Pint passed; `composer audit` no advisories; `npm audit` 0 vulnerabilities. Mutation check: 19 of 19 rule removals caught.<br>Migrations run on `smart_go` (additive, OD-86). `access:sync` was **not** run on `smart_go`: no staff users exist yet, and it runs when the first Super Admin is created.<br>Architecture rule "models extend Eloquent" now applies to classes only, so the `HasRevisions` trait and `Revisionable` interface can live in `App\Models`; a stray class there still fails it.<br>**Step 4.2 implemented and verified locally (2026-10-26) and committed on the owner's instruction (OD-90):** see “Step 4.2 acceptance” below.<br>**Step 4.3 implemented, verified and committed locally (2026-10-26, OD-92 to OD-95):** see “Step 4.3 acceptance” below.<br>**Step 4.4 done (2026-10-26, local, OD-96):** media library (Website → Media library) with origin, licence, alt text, credit and focal point; uploads checked by content; private originals and access-checked WebP renditions; image block (alt text required unless decorative, "Concept visual" label, credit); share image with Open Graph tags; licence expiry hides images and a daily reminder warns 30 days ahead; images in use cannot be deleted; `media.manage`. Tests: 20 new (domain, serving, rendering, screens); full suite **529 passed**. Migrations `media`, `media_assets`, `seo_metadata.og_asset_id` applied to `smart_go`. Not built: video, documents, floor plans and 3D files (Phase 7), property galleries and the property link (Phase 5), SVG (BR-MED-5). **Next:** step 4.5. |

### Step 4.3 acceptance (2026-10-26, local evidence; committed locally, OD-95)

Built: Website → Navigation in the back office for the four fixed menus (main menu, header buttons, footer columns, legal links), each changed only through revisions with the same workflow and rules as pages; the public header, phone menu, phone action bar and footer render the published menus; page search-engine fields (title, description, indexing) published with their page, and a canonical URL derived from `APP_URL` and the published address. Tables `navigation_menus`, `navigation_items` and `seo_metadata` added (C-19). The page and menu editors share one workflow trait.

| # | Criterion (owner's brief for step 4.3) | Evidence | Result |
|---|---|---|---|
| 1 | Approved visual direction kept: forest `#0B2C1B` (OD-42), Sora headings, Inter body, original logo files, tagline unchanged | Existing tokens and `x-site.brand-logo`; no new colours, fonts or logo files; design previews unchanged (`PublicShellTest`) | PASS |
| 2 | Navigation management with approved structures (limits confirmed by OD-92), ordering and server-side validation | `NavigationPayloadTest` (13), `NavigationWorkflowTest` (7), `EditNavigationMenuTest` (4) | PASS |
| 3 | No unpublished pages, preview URLs, redirect loops, bypasses or fabricated properties, prices or availability | Page links shown only while published, at the current address; typed addresses on this website refused; unsafe stored addresses never rendered; mega-menus that need Phase 5 data are not offered | PASS |
| 4 | Header and footer from configured, validated destinations; placeholders removed; optional links handled | `PublicShellTest` (7): nothing and no `#` link is shown until menus are published; the phone action bar and its reserved space appear only with header buttons | PASS. Social links and contact details: step 4.6 (BR-CMS-9) |
| 5 | Keyboard, focus and labels on desktop and mobile menus | Keyboard check: Menu button reached and opened with Enter, focus moves into the sheet, Escape closes; desktop drop-down opens with Enter, Tab enters it, Escape closes; 3 px focus outline; first stop "Skip to content"; `aria-current` on the current page | PASS (automated); manual screen-reader review open |
| 6 | SEO title, description, indexing and canonical URL; validated and escaped | `PageSeoTest` (11), `EditPageTest`; plain text only (`<` and `>` refused), Blade-escaped output; the automatic description never exceeds 160 characters including the ellipsis | PASS. No canonical override (OD-93); `APP_URL` requirement in DEPLOYMENT_RUNBOOK §3; Open Graph image (4.4) and structured data (Phase 11) later |
| 7 | Drafts and previews not indexed; canonical consistent with the published URL; no exposure through metadata; no loops with redirects | Previews `noindex, nofollow` without a canonical; metadata written only on publication; canonical follows a change of address while the old address redirects; spoofed host header ignored | PASS |
| 8 | Permissions and publishing preserved; no Super Admin bypass | Menus use `content.*` and the §11.6 rules (tests include the Super Admin); page SEO follows its page's approval (SECURITY_MODEL §3.1) | PASS |
| 9 | Tests, architecture, concurrency, Pint, audits, build | Before the commit (after OD-93's description fix): full suite **509 passed, 0 failed (1,551 assertions)**. Earlier run: full suite **506 passed, 0 failed, 0 skipped (1,542 assertions)**; concurrency 7 passed; architecture 6 passed; Pint passed; `composer audit` no advisories; `npm audit` 0 vulnerabilities; `npm run build` succeeded. Mutation check: 20 rules removed one at a time; 18 caught at once, 2 after tests were added (SECURITY_MODEL §11.3) | PASS |
| 10 | Responsive layouts at 360, 768, 1024 and 1440 px; one H1; landmarks; contrast | 30 captures (public pages, design preview, menu preview, back office): no sideways scrolling, one `h1` each; header, `nav` (labelled), `main`, `footer` landmarks; only existing token pairings (contrast-tested in `DesignTokensTest`); screenshots `../design/review/2026-10-26/nav-*` | PASS (automated) |
| 11 | Database safety | Three additive migrations; environment `local` and database `smart_go` confirmed, SQL inspected, then applied (OD-86) | PASS |

**Not built in step 4.3 (open):** social links and contact or grievance details in the footer (brand and public settings, step 4.6; D-15); Workspaces and Locations mega-menus (need Phase 5 inventory and Phase 6 templates); Book a tour and Enquire destinations (Phases 6 and 8, so the header buttons link to published pages until then); a manual canonical override; the home page `/` is still the framework welcome page (Phase 6).

**Incident found during this step (2026-10-26):** at 11:26, outside the development session, `composer dump-autoload` ran (triggering `package:discover` and `filament:upgrade`), then `php artisan optimize` and `php artisan migrate` on `smart_go`, and at 11:27 `php artisan route:cache`. It left a Filament component cache that hid the new Navigation screens from running apps until `php artisan filament:optimize-clear` was run on 2026-10-26. LOCAL_DEVELOPMENT now warns against caching in the development copy. The actor could not be identified: no other Claude session or Codex session was active, no cron, supervisor, timer, git hook or IDE task runs these commands, and the open terminals keep their history in memory. Desktop session inhibitor events match each command's duration, which points to an interactive terminal (hypothesis). The same pattern repeated at 12:57 (`composer dump-autoload`, `route:cache`; no database change); that route cache was cleared outside the session before the step 4.3 test rerun.

### Step 4.2 acceptance (2026-10-26, local evidence; committed locally, OD-90)

Built: Website → Pages in the back office (list, editor, version history), a block library with server-side rules, signed staff previews, scheduling and cancelling, restore as a new draft (OD-88), redirects when a published page changes address, and a public handler for published pages and live redirects. `content.view` added for the Super Admin and Marketing Manager (approved matrix). `redirects` table added (C-18).

| # | Criterion (owner's brief for step 4.2) | Evidence | Result |
|---|---|---|---|
| 1 | Server-side enforcement of every 4.1 control on every screen, action, form submission and service | `RevisionWorkflowTest`, `EditPageTest` (direct save of a submitted version refused; permission revoked mid-session gives 403), `ListPagesTest`, `CreatePageTest`; every transition re-checked under a row lock | PASS |
| 2 | Page list: title, address, status, live version, version in progress, scheduled time, last published, author/approver/publisher; search, filters, sorting, pagination | `ListPagesTest` (draft titles and addresses searchable, state filter); screenshots `cms-admin-pages-list-*` | PASS |
| 3 | Structured editor with a controlled, extensible block library; no arbitrary HTML or script | `BlockLibrary` + 5 blocks (introduction, text, feature grid, FAQs, call to action); `PagePayloadTest`, `RichTextTest`, `PageControllerTest` (stored script never rendered) | PASS |
| 4 | Server validation of types, required fields, lengths, URLs, image references; useful messages; invalid content cannot be submitted | `PagePayloadTest` (26 cases), errors shown on the exact field (`EditPageTest`, `CreatePageTest`) | PASS (no image block yet: images need the media library, step 4.4) |
| 5 | Drafting and lifecycle clarity; immutable submitted versions; rejection note shown | Workflow notice on every state; `EditPageTest` | PASS |
| 6 | Revision history of who created, edited, submitted, approved, published, scheduled, rejected or restored | Version history table plus per-version activity from the audit log (`RevisionsRelationManagerTest`) | PASS |
| 7 | Preview: same rendering, unpublished, staff-only, not indexed, changes nothing, clearly marked | `RevisionPreviewControllerTest` (12 cases); signed 30-minute link inside the back office; `noindex`, `no-store`, `no-referrer` | PASS |
| 8 | Scheduling: permission and state, scheduler recorded, time validated, approver refused, re-checked at run time, safe failure, never treated as published | `PublishScheduledRevisionsCommandTest`, `EditPageTest` (IST input, cancel, failure shown with reason) | PASS |
| 9 | Restore creates a new revision through approval; history unchanged; approver refused; audited | `RevisionWorkflowTest`, `RevisionsRelationManagerTest` (OD-88) | PASS |
| 10 | Redirects on address change: 301, no loops, safe paths, conflicts handled, audited, only with the approved page, chains tested, other routes unaffected | `RedirectMovedPageTest`, `PageControllerTest` | PASS for automatic redirects (BR-CMS-6). Manual redirects: step 4.6 (OD-66) |
| 11 | Accessibility and responsive design | 23 captures at 360/768/1024/1440 px: no sideways scrolling and one `h1` each; version history at 360 and 1440 px; labelled fields, native disclosure for FAQs, visible focus from the design system; screenshots in `../design/review/2026-10-26/` | PASS for automated checks; a manual screen-reader review is still open (as for Phase 2) |
| 12 | Tests for permitted and prohibited behaviour, including concurrency | Full suite **466 passed, 0 failed, 0 skipped (1,389 assertions)**; concurrency group 7 passed (3 new: one publication from four simultaneous attempts, the scheduler skipping a version just published, one of two pages winning an address); architecture 6 passed | PASS |
| 13 | Database safety | One additive migration (`redirects`); tests on `smartgro_testing` only. On `smart_go` the migration had **already been applied at 11:26:41 by a process outside this work** (with `php artisan optimize`); its schema matches the migration exactly and holds no rows | PASS (see note) |
| 14 | Documentation | SECURITY_MODEL, DATABASE_ERD (v1.4), BUSINESS_RULES (BR-CMS-3, BR-CMS-5), PRD (FR-CMS-2), ADR-0005, API_CONTRACTS §5.1, LOCAL_DEVELOPMENT, DECISION_REGISTER (OD-88, OD-89) | PASS |

Checks on 2026-10-26: Pint passed; `composer audit` no advisories; `npm audit` 0 vulnerabilities; `npm run build` succeeded. Mutation check: 36 rules removed one at a time; the result is recorded in SECURITY_MODEL §11.3.

**Not built in step 4.2 (open):** image, testimonial, workspace-card and enquiry blocks (steps 4.4, 4.5, Phase 5, Phase 6); search-engine fields from the approved design (FR-SEO-1; not yet assigned to a step); scheduled unpublishing (BR-CMS-5); discarding an unwanted draft; landing and legal templates; the public header and footer are still the Phase 2 shell (step 4.3). GitHub CI and staging stay deferred (OD-79); these are local results, not CI.

## Phases 5–15
Scope, deliverables and acceptance criteria are defined in `PROJECT_MASTER_PLAN.md` §2. Each phase gets a full entry here when it starts.

---

## Change log

| Date | Change |
|---|---|
| 2026-10-26 | Round 30: step 4.2 committed (`afe2fc3`, OD-90). Step 4.3 built and verified: Website → Navigation (four fixed menus through the revision workflow), published menus in the public header, phone menu, action bar and footer, page search-engine fields and canonical URLs; `navigation_menus`, `navigation_items`, `seo_metadata` migrated on `smart_go`. Full suite 506 passed. Investigated the 11:26 `optimize`/`migrate` run and cleared its stale Filament cache. **Not committed** (OD-91); nothing pushed. |
| 2026-10-26 | Round 29 (Phase 4 step 4.2, owner's brief): page list, editor and version history; block library with server-side rules and rich-text sanitising; signed staff preview; scheduling and cancelling; restore as a new draft (OD-88); redirects on address change; public handler for published pages and live redirects; `content.view`; `redirects` table. Full suite 466 passed. Committed locally on the owner's instruction (OD-90); nothing pushed. |
| 2026-10-26 | Round 28: Phase 4 step 4.1 built and verified: content permissions, `pages` and `revisions`, the publishing workflow with the §11.6 rules, scheduled publishing and audit. Migrations run on `smart_go` (OD-86). Committed locally (OD-87); not pushed. |
| 2026-10-26 | Round 27:<br>• **Phase 2 APPROVED** (owner: "Phase 2 visual direction: Approve", OD-82).<br>• **Phase 3 APPROVED with deferred items** (owner: "Phase 3 acceptance: Accept", OD-83). Neither phase is COMPLETE.<br>• **Phase 4 opened** with a step plan; packages and model additions await owner approval. |
| 2026-10-26 | Round 26:<br>• Found the owner's commit `427b65f` ("morning pull oct 26", including the staged `CLAUDE.md` edit) and its push to `ErSuryaPrakashChamoli/smartgo`. The owner confirmed it as an interim private backup (OD-80).<br>• OD-74 confirmed and authorised (OD-81), and implemented: `backoffice.access` for all six roles, explicit role lists, permission-based panel access. 5 tests added, sync tests updated. AC-3.2 now passes.<br>• Not committed. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 25 (Phase 3 local completion):<br>• Owner direction OD-79 recorded: GitHub and deployment are deferred until the application is fully developed and verified locally.<br>• Fixed: the password-reset page revealed which emails are staff accounts; a wrong two-step code was not audited. 10 tests added.<br>• Checks, all exit code 0: 320 tests passed (844 assertions, none skipped); Pint passed; `composer audit` no advisories; `npm audit --audit-level=high` 0 vulnerabilities; build passed.<br>• Acceptance record reclassified (PASS, BLOCKED, DEFERRED, NOT VERIFIED). Deferred GitHub and deployment checklist added.<br>• Not committed. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 24 (Phase 3 finalisation audit):<br>• Re-verified on MySQL, all exit code 0: Pint passed; 310 tests passed (763 assertions); `composer audit` no advisories; `npm audit --audit-level=high` 0 vulnerabilities; `npm run build` passed.<br>• Recorded the round-23 pre-push findings: GitHub has no organisation `Smartgro`; the name is held by a personal user account (R-21); no push credential is visible here.<br>• Updated stale statements in the deployment runbook and system architecture.<br>• No code, permission, sign-in or schema change. Not committed. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 22:<br>• D-31 organisation and owner confirmed by the owner (OD-78): organisation `Smartgro`, legal owner Smart Gro Services Private Limited.<br>• D-31 stays open: the owner creates the organisation, repository and push credential; the first push is not authorised.<br>• Documentation only, not committed. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 21:<br>• The owner approved the D-31 setup checklist wording (OD-77), including two additions: re-check the history and contents for secrets immediately before pushing; keep `CLAUDE.md` out of commits unless authorised.<br>• D-31 is still unresolved: no organisation name selected, no legal owner, repository creation and push not authorised.<br>• Documentation only. Phase 3 stays IN PROGRESS. |
| 2026-10-25 | Round 20:<br>• D-31 prepared for owner approval: OD-76 security model recorded; setup checklist in LOCAL_DEVELOPMENT §5; pre-push readiness checked (read-only).<br>• **D-31 awaits the organisation name and legal owner**, so no organisation, authentication, remote or push was created and CI has not run.<br>• Re-verified: audit-fix tests 12 passed; full suite 310 passed (exit code 0); Pint passed.<br>• Phase 3 stays IN PROGRESS. |
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
