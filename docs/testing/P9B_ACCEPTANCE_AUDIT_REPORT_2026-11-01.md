# SmartGro — P9-B Assignment Rules: Acceptance Audit

Audit of 2026-11-01, Phase 9 (CRM), package P9-B (OD-160). Evidence-based, read-only. This report is the only file created or changed by the audit.

**Verdict:**
- Acceptance: **CONDITIONALLY ACCEPT** (§6).
- Deployment readiness: **NOT READY** (§7).

---

## 1. Executive summary

**What passes.** P9-B meets the documented requirements for the parts the owner has already decided:
- the round-robin and fixed-person strategies;
- the six criteria;
- first-match precedence by priority;
- unassigned when nothing matches (BR-CRM-3);
- Super Admin-only management;
- eligibility identical to manual assignment;
- one decision per enquiry, idempotency, rollback and retry, and the round-robin turn under real parallel load;
- audit without visitor details;
- the screens and their explanations.

The implementation, the migrations and the tests support these claims, and so do the surviving run summaries (§4).

**What it cannot pass yet.** These are blocked by owner decisions, not by defects:
- **least loaded** (A-34: no workload definition; the strategy is correctly impossible to switch on);
- **behaviour when the first matching rule has nobody eligible** (A-33; interim: unassigned);
- **round-robin start and reset** (A-35; interim behaviour documented);
- the **BR-CRM-3 notification** (O-1);
- **which rules exist** (D-09; none are created).

FR-CRM-2 requires "tests per strategy" for all three strategies, so least loaded keeps P9-B from full acceptance.

**No code gap was found** that requires changing the implementation for the decided scope.

**Evidence gaps (NOT VERIFIED):**
- the nine mutation checks left no durable artefact;
- `config/filament.php` was added after the last Pest run;
- no test renders the screens on a database without the new tables, or opens a rule's view or edit page directly as a non-admin;
- no P9-B test asserts that nothing is sent;
- the per-file test runs and the regression summaries are kept only in the session's temporary folder. This report preserves the summaries in Appendix A.

**Deployment** needs authorisations that do not exist:
- the three migrations;
- `access:sync`;
- a rebuild of the configuration, route and Filament component caches on any server that caches them;
- D-09 rules and the owner decisions.

On the owner's local server today, the cached routes and Filament component list (both from 2026-10-31 15:25) do not include the new screens.

---

## 2. Initial state and boundaries

| Item | Fact (captured before inspection) |
|---|---|
| Branch | `main` |
| HEAD | `832549382a367d686d9c0981471f2ce66f5270d4` ("update oct 31") |
| Staged | none |
| Working tree | 65 entries: 32 tracked files modified (+927/−151 across all uncommitted work), 33 untracked. They include P9-B, P9-A and the OD-156 to OD-158 work. |
| Records located | decision register `docs/decisions/DECISION_REGISTER.md`; status log `docs/phases/PHASE_STATUS.md` |

**Boundaries kept during this audit:**
- No database connection of any kind (not `smart_go`, `smartgro_preview`, `smartgro_testing` or `smartgro_site`).
- No Pest run: every P9-B test uses `RefreshDatabase` or the concurrency harness, which would write to `smartgro_testing`.
- No migration, seeder, `access:sync`, cache rebuild or cache clearing.
- No service restart or process termination.
- No HTTP request to any port: a page request writes a session row.
- No Git operation other than read-only inspection (`status`, `diff`, `log`, `rev-parse`).
- No package change.

**Checks actually run by this audit (all read-only):**
- `git status`, `diff`, `rev-parse` and `branch`;
- file reads and `grep` of the source, tests, migrations, configuration, kit scripts and documents;
- modification times of the P9-B files;
- the JSON in the existing kit evidence and in the surviving Pest summaries;
- `grep` of two cache files (`bootstrap/cache/routes-v7.php` and `bootstrap/cache/config.php`), read only;
- a static count of P9-B tests and dataset cases.

Everything else is **inferred from existing records**, and is marked as such.

---

## 3. Inspected sources

| Kind | Files |
|---|---|
| Records | `docs/testing/PHASE_9_ACCEPTANCE_PACK.md`; `docs/testing/P9B_ASSIGNMENT_RULES_REPORT_2026-10-31.md`; register OD-159, OD-160 (a)–(e), A-33, A-34, A-35, D-09, OD-111 (d); O-1 (`PHASE_5_ACCEPTANCE_PACK.md` §9, owner action register); status log rows OD-159 (b) and OD-160; `PROJECT_MASTER_PLAN.md` §2 Phase 9 (AC-9.1 to AC-9.3); `docs/requirements/PRD.md` FR-CRM-2; `docs/requirements/BUSINESS_RULES.md` BR-CRM-3, BR-CRM-3a, BR-CRM-6; `docs/security/SECURITY_MODEL.md` §3.1 and §11; `docs/architecture/DATABASE_ERD.md` §6, v1.25; `docs/operations/DEPLOYMENT_RUNBOOK.md` §4; `docs/operations/LOCAL_DEVELOPMENT.md` (cache section) |
| Engine and actions | `app/Actions/Crm/AssignNewLead.php`, `SaveAssignmentRule.php`, `SetAssignmentRuleActive.php`; the hand-offs in `app/Actions/Crm/SubmitEnquiry.php` (line 94) and `app/Actions/Booking/RequestTour.php` (line 158); unchanged `app/Actions/Crm/AssignLead.php` and `app/Policies/LeadPolicy.php` (no diff from HEAD) |
| Model, enums, policy, configuration | `app/Models/AssignmentRule.php`, `LeadAssignment.php`, `Lead.php` (`SOURCES`, `assignment()`); `app/Enums/Crm/AssignmentStrategy.php`, `AssignmentOutcome.php`; `app/Enums/Access/Capability.php` (`ManageAssignmentRules`), `StaffRole.php` (Super Admin list only); `app/Policies/AssignmentRulePolicy.php`; `config/crm.php`; `config/filament.php` |
| Schema | `database/migrations/2026_10_31_163023_create_assignment_rules_table.php`, `…163025_create_assignment_rule_user_table.php`, `…163026_create_lead_assignments_table.php`; `database/factories/AssignmentRuleFactory.php`; no seeder references `AssignmentRule` |
| Screens | `app/Filament/Clusters/Sales/Resources/AssignmentRules/AssignmentRuleResource.php` and `Pages/*`; `app/Filament/Clusters/Sales/Resources/Leads/Schemas/LeadInfolist.php` (`assignment_decision`, lines 41–45 and 139); `app/Filament/Clusters/DesignPreviews/Pages/PermissionsReview.php` (line 94) |
| Tests | `tests/Feature/Actions/Crm/AssignNewLeadTest.php` (22), `AssignmentRuleManagementTest.php` (18), `tests/Feature/Filament/Clusters/Sales/AssignmentRuleScreensTest.php` (7), `tests/Feature/Infrastructure/AssignmentRuleConcurrencyTest.php` (2), `tests/Feature/Http/Controllers/Web/TourControllerTest.php` (1 new test), `tests/Feature/Actions/Access/SyncAccessControlTest.php` (catalogue updated); harness `tests/Support/Concurrency/PublishingHarness.php` and `publish-worker.php` (`auto_assign`) |
| Kit | `docs/operations/scripts/browser/run-checks.sh` (line 175, `FILAMENT_CACHE_PATH`), `verify-admin.mjs` (three new screen checks) |
| Evidence | `docs/testing/evidence/phase9/2026-10-31-od160/admin/` (`run.json`, `admin.json`) and `admin-run1-stale-filament-cache-404/` (`run.json`, `admin.json`, `logs/server.log`); the session's Pest summaries `p9b-part{A,B,B2,C,D}.out` in the Claude scratchpad (temporary, outside the repository; preserved in Appendix A) |

---

## 4. Evidence assessment

| Evidence | Where | What it shows | Limits |
|---|---|---|---|
| Regression A, `tests/Feature/Actions` | scratch `p9b-partA.out`, 22:20:20–22:25:52 on 2026-10-31 | passed, 410 tests, 1,668 assertions, 0 failed or skipped | summary only, no per-test names; includes all 22 + 18 new action tests, `UpdateContactFromLeadTest` and `SyncAccessControlTest` |
| Regression B, Filament, Http, Services and Views | `p9b-partB.out`, 22:25:52–22:33:10 | failed: 712 of 714; both failures in `DesignPreviewPagesTest` (the catalogue and SECURITY_MODEL comparisons) | as above |
| Correction | `PermissionsReview.php` modified 22:38:30 (row at line 94) | the new capability was added to the preview | — |
| Regression B2, the same paths | `p9b-partB2.out`, 22:40:04–22:47:43 | passed, 714 tests, 4,013 assertions; includes the 7 screen tests, the tour test and the P9-A screen tests | summary only |
| Regression C, the rest | `p9b-partC.out`, 22:33:10–22:34:58 | passed, 241 | — |
| Regression D, Infrastructure | `p9b-partD.out`, 22:34:58–22:38:07 | passed, 31: the 29 earlier concurrency tests plus the 2 new P9-B ones | no per-test names; inferred by file inclusion, with 0 skipped |
| The code those runs tested | modification times | all P9-B application code last written 22:17:19–22:19:52 (the restores after the mutation checks, byte for byte by the implementation record); regression A started 22:20:20, so A, C and D ran the final code, and B2 ran the corrected preview | **`config/filament.php` and `run-checks.sh` changed at 22:53:28, after every Pest run** |
| Per-file runs (22, 18, 7, 8, 6) and the nine mutation checks | implementation report §5 only | — | **console output, not preserved**; the per-file results are subsumed by the regression summaries; the mutation checks are NOT VERIFIED |
| Browser run 1 | `admin-run1-stale-filament-cache-404/` | exit 1: the three new screens 404; the other checks passed | axe "0" on a 404 page proves nothing |
| Browser run 2 | `admin/run.json`, `admin.json`, finished 17:28:41 UTC (22:58 IST) | exit 0; `smartgro_testing`, configuration not cached; 80 screen checks and 3 viewer checks, 0 failures; rules list and form 200 at 360 and 1440 px with 0 axe violations; refused to sales (403); enquiry page 0 axe violations | the kit has no rule fixture, so a rule's view page, the edit form and the switch actions were not browser-checked; it ran with the Filament cache bypassed (§5) |

---

## 5. Filament component-cache review

**The finding is confirmed.**
- `bootstrap/cache/filament/panels/admin.php` (2026-10-31 15:25) has no `AssignmentRule` entry.
- `vendor/filament/filament/src/Panel/Concerns/HasComponents.php:725` uses the cached list only when `! app()->runningInConsole()`. That is why the tests passed and the screens returned 404 on a web server reading the cache.
- `bootstrap/cache/routes-v7.php` (same time) has **no `assignment-rules` route** (0 matches, against 17 for `closing-reasons`). A server using the cached routes would also return 404, independently of Filament.

**How the kit bypasses it.**
- `config/filament.php` sets `cache_path` to `env('FILAMENT_CACHE_PATH', base_path('bootstrap/cache/filament'))`: the default is unchanged, and the other Filament keys still merge in.
- `run-checks.sh:175` exports `FILAMENT_CACHE_PATH` as a folder that does not exist, as it already does for the configuration, route and event caches.

**Could it mask a deployment issue?** Yes, by design. The kit verifies the working tree's code, not a server's cached state. A server that serves new code without rebuilding its caches keeps answering 404 for the new screens. Nothing in the kit would show that.

**Is a refresh step documented?**
- **For a normal release, yes.** `DEPLOYMENT_RUNBOOK.md` §4 steps 5–7 run `migrate --force`, `access:sync`, then `php artisan optimize` and `php artisan filament:optimize`. Filament registers `filament:optimize` with Laravel's `optimize` (`vendor/filament/support/src/SupportServiceProvider.php:485`).
- **Not for the owner's local server.** It runs the working tree with caches written by an outside process. No P9-B activation procedure exists there, unlike the Spaces procedure.
- Step 10's smoke test does not mention the new screen.

**Safe without the tables?**
- **Engine:** returns before any query when `AssignmentRule::isAvailable()` is false. PASS, verified (`AssignNewLeadTest`, Schema mock).
- **Resource:** `canAccess()` is `isAvailable() && parent::canAccess()`. PASS by code inspection only.
- **Enquiry page:** the explanation entry is `visible(fn () => AssignmentRule::isAvailable())`. Filament renders only visible components, so its state closure does not run (`vendor/filament/schemas/src/Schema.php:185–197`). PASS by code inspection only.
- No test renders either screen without the tables.

**Does acceptance depend on an unauthorised cache operation?**
- **Code-level acceptance does not:** the behaviour is proven on `smartgro_testing` with fresh discovery.
- **Deployment and visibility on any server with caches do:** a rebuild (`optimize`) is needed after migrating and syncing permissions, and it is not authorised on the owner's server.

**Code-level readiness** means the decided scope works and is tested against the working tree. **Deployment readiness** additionally needs migrated tables, the synced permission, refreshed caches, the owner decisions and real rules (§7).

---

## 6. Acceptance matrix

Classification:
- **PASS-V:** verified, with evidence directly supporting the criterion.
- **PASS-C:** code inspection only.
- **BLOCKED:** needs an owner decision.
- **GAP:** remediation required.
- **NV:** not verified.

"Verified" here means the surviving run summaries cover the test that proves it (§4), plus reading that test.

### A. Strategies

| # | Criterion (source) | Class | Evidence |
|---|---|---|---|
| A1 | Round-robin, deterministic, in turn (FR-CRM-2, pack §2) | PASS-V | `AssignNewLeadTest` "gives enquiries to the rule's people in turn…" (order second → first → third → second) and "passes over people who are deactivated…"; `AssignNewLead::nextInTurn` (lines 125–140); regression A |
| A2 | Fixed person (FR-CRM-2) | PASS-V | `AssignNewLeadTest` "gives every matching enquiry to the fixed person…"; `SetAssignmentRuleActive` lines 40–43 (exactly one person); `AssignmentRuleManagementTest` "needs exactly one person…" |
| A3 | Least loaded (FR-CRM-2 "tests per strategy") | **BLOCKED (A-34)** | the mechanism is tested with statuses set by the test only ("…mechanism only…, A-34"); `config/crm.php:18` `workload_statuses` is null |
| A3a | Least loaded cannot be switched on while A-34 is open | PASS-V | `SetAssignmentRuleActive` lines 52–54; `AssignmentRuleManagementTest` "keeps a least-loaded rule off until the owner defines workload (A-34)"; the engine also refuses (lines 89–91; "does not assign by least loaded while workload is undefined") |
| A4 | No matching active rule leaves the enquiry unassigned (BR-CRM-3; FR-CRM-2 "manager queue") | PASS-V | `AssignNewLeadTest` "leaves an enquiry unassigned, as today, when no rule is active" (`no_rule_matched`, no audit); lines 80–84. "Manager queue" (FR-CRM-2) and "unassigned queue" (BR-CRM-3) are the same queue here: owner null, the inbox's "Unassigned" filter |
| A4a | BR-CRM-3: the Super Admin and Operations Manager are notified | **BLOCKED (O-1)** | OD-111 (d); none sent; the existing unassigned count stays |
| A5 | Only new enquiries, from the enquiry form or a tour request, are assigned | PASS-V | `DB::afterCommit` only after `$lead->save()` of a new lead (`SubmitEnquiry:94`; `RequestTour:158`, inside `enquiry()` after the `$open` return); `AssignNewLeadTest` "gives a new website enquiry an owner once it is stored, and not again for its repeat"; `TourControllerTest` "gives a new tour enquiry an owner…, and not again when a later request joins it". City-waitlist enquiries take the same `SubmitEnquiry` path: PASS-C |
| A6 | No reassignment of existing, reused or repeated enquiries | PASS-V | the `AlreadyOwned` path (lines 76–78) and test "never reassigns…"; the repeat and reuse tests above; rules never run retroactively (no code path) |

### B. Matching and priority

| # | Criterion | Class | Evidence |
|---|---|---|---|
| B1 | City, location, workspace type, segment, team size, source (ERD §6; FR-CRM-2 "city, location, product or seat band") | PASS-V | `AssignmentRule::CRITERIA` and `matches()`; dataset test (6 cases); `SaveAssignmentRule` validates each list against existing records or the form's options. **Interpretation recorded** (OD-160 d): "seats band" is the enquiry's `team_size` (the form's five bands) |
| B2 | An empty list means any | PASS-V | `matches()` line 102 (`$values !== []`); many tests use rules with no criteria that assign |
| B3 | A missing enquiry value does not meet a non-empty list | PASS-V | "does not match a criterion an enquiry has no value for" |
| B4 | Active and inactive rules | PASS-V | the engine loads `is_active = true` only; the precedence test ignores an inactive priority-10 rule; rules start off (E2) |
| B5 | First match, lowest priority number first (BR-CRM-3 "by priority") | PASS-V | `orderBy('priority')` and `first()`; "lets the first matching active rule decide, lowest priority number first…". BR-CRM-3 does not state the direction; "lowest first" is a recorded convention (OD-160 a), stated on the form |
| B6 | Priority unique and validated | PASS-V | migration `priority` unique; `Rule::unique` with message; "a priority already used" case. *Observation:* two Super Admins saving the same priority at the same instant would hit the database constraint, not the friendly message (PASS-C; LOW) |
| B7 | A matching rule with nobody eligible | **BLOCKED (A-33)** | interim behaviour (unassigned, `no_eligible_assignee`, explained on the enquiry) is implemented and tested ("does not try the next rule… interim until A-33"). **The acceptance pack does not permit it as accepted behaviour**: §3.2 and §5 list A-33 as open |
| B8 | Whether a later matching rule is tried | **BLOCKED (A-33)** | not tried; same test |

### C. Authorisation and eligibility

| # | Criterion | Class | Evidence |
|---|---|---|---|
| C1 | Super Admin-only management (OD-36; SECURITY_MODEL §3.1) | PASS-V | `AssignmentRulePolicy` (refuses a deactivated account first; `leads.assignment.manage`; delete false); `StaffRole` grants it to the Super Admin only; `AssignmentRuleManagementTest` refuses sales, operations, marketing, property manager, finance and a deactivated Super Admin for save **and** switch; the screens test returns 403 for three roles; the kit returns 403 for sales |
| C2 | Permission registered and used | PASS-V | `Capability::ManageAssignmentRules = 'leads.assignment.manage'`; `SyncAccessControlTest` (catalogue and Super Admin lists, regression A) |
| C3 | Permissions preview and SECURITY_MODEL consistent | PASS-V | `PermissionsReview.php:94`; `SECURITY_MODEL.md:303`; `DesignPreviewPagesTest` passed in B2 after failing in B |
| C4 | Direct routes and actions cannot bypass | PASS-V for the list and create URLs (403) and for both actions (`Gate::authorize` in `SaveAssignmentRule:36–37` and `SetAssignmentRuleActive:28`); **PASS-C** for a rule's **view** and **edit** URLs as a non-admin (policy `view` and `update`; not tested) | screens test; management test |
| C5 | Eligibility: active, and allowed to work on the enquiry's location | PASS-V | `AssignNewLead:87`; tests for deactivated and other-location people, and for the general enquiry left unassigned when people are location-restricted |
| C6 | Fixed or listed people cannot bypass eligibility | PASS-V | eligibility is applied when assigning, not only when saving; save and switch-on refuse inactive or non-`leads.manage` people (management cases) |
| C7 | Consistent with assignment by hand | PASS-C | the same predicate as `AssignLead:37` and `:66–67` (active, `isWithinScopeOf(…, ManageLeads)`); `AssignLead` unchanged; its stale-owner refusal still protects against overwriting an owner set by a rule |

### D. Integrity, transactions and concurrency

| # | Criterion | Class | Evidence |
|---|---|---|---|
| D1 | Exactly one durable decision per enquiry | PASS-V | `lead_assignments.lead_id` unique; "decides once per enquiry…"; concurrency "decides once when the same new enquiry is assigned twice at the same instant" (regression D) |
| D2 | Repeated runs are idempotent | PASS-V | same tests; the existing decision is returned (lines 70–74) |
| D3 | Round-robin correct under concurrency | PASS-V | `AssignmentRuleConcurrencyTest` (4 parallel workers → 2 and 2, 4 decisions, 4 audits, the turn on the second person), regression D. Mutation M1 (rules unlocked) is recorded as caught: NV (§4) |
| D4 | One transaction for decision, owner and turn | PASS-V | lines 66–116; the failure test shows all three unchanged after a failure |
| D5 | Rollback on failure | PASS-V | "leaves the enquiry and the rule as they were when assignment fails, logs it without personal data…" (log context `lead_id` and exception class only) |
| D6 | Recovery on a later retry | PASS-V | same test, second call assigns |
| D7 | Deterministic least-loaded tie | PASS-V as a mechanism (tie to the first in order); **the strategy stays BLOCKED (A-34)** and is not switchable | `leastLoaded()` strict `<`; mechanism test |
| D8 | Duplicate processing and races | PASS-V | D1; the enquiry is locked, then all active rules, before the first plain read (lines 67–68; ADR-0004's stale-snapshot case) |
| D9 | Constraints and indexes | PASS-C (unique `lead_id` also PASS-V by D1) | migrations: `priority` unique; (`is_active`, `priority`) index; pivot primary (rule, user), unique (rule, position), index `user_id`; `lead_id` unique; restrict on rule and user deletes; (`assignment_rule_id`, `created_at`) index |
| D10 | Audit with identifiers, no visitor details | PASS-V | "audits an assignment by a rule without personal data" (properties: reference, rule id, owner id; the stored row has no visitor email); rule events with field names only (management tests) |
| D11 | No notification side effects | PASS-C | no `Notification::`, `->notify(`, `Mail::` in the engine, actions or models. The only `Notification::make()` calls are Filament's on-screen confirmations (view and edit pages). **No P9-B test asserts that nothing is sent** |

### E. Screens and explanation

| # | Criterion | Class | Evidence |
|---|---|---|---|
| E1 | Sales → Assignment rules, navigation and authorisation | PASS-V | screens test; kit run 2 (200 Super Admin, 403 sales) |
| E2 | Rules start off | PASS-V | `SaveAssignmentRule:100`, migration default false; "creates a rule from the form, switched off…" |
| E3 | Editing only while off | PASS-V | `SaveAssignmentRule:85–89` under lock; management test; `EditAssignmentRule::mount` redirect tested |
| E4 | Switching on only when the rule can assign | PASS-V | management tests (no people, deactivated person, fixed exactly one, least loaded); screens test "explains… why it cannot be switched on" |
| E5 | Rules are not deleted | PASS-C | policy `delete`/`deleteAny` false; no delete or bulk action; restrict on delete from `lead_assignments`. Not tested |
| E6 | Each enquiry explains why it was or was not assigned | PASS-V for "Given to … by the rule …" and "have not run"; **PASS-C** for the other three outcomes' wording (`LeadInfolist:139–150`) | screens test |
| E7 | Each rule lists its last 20 decisions | PASS-V for listing with links; **PASS-C** for the limit of 20 (`ViewAssignmentRule:67`) | screens test |
| E8 | Missing tables: no assignment, no screens | PASS-V for the engine; **PASS-C** for the resource and the enquiry entry; **NV** at runtime on a real database without the tables (no request allowed) | §5 |
| E9 | Accessibility and browser | PASS-V for the list and form (360 and 1440 px, 0 axe violations), the 403 for sales and the enquiry page; **NV** for a rule's view page, the edit form and the switch actions in a browser (no rule fixture) | kit run 2 |

### F. Regression and evidence

| # | Criterion | Class | Evidence |
|---|---|---|---|
| F1 | 50 new tests | PASS-V | static count 22 + 18 + 7 + 2 + 1 = 50; all inside passing summaries (A, B2, D) with 0 failed or skipped |
| F2 | Regression 410, 714, 241, 31 | PASS-V | Appendix A (temporary-folder summaries preserved here) |
| F3 | P9-A duplicate detection, contact update and privacy intact | PASS-V (by inclusion) | `UpdateContactFromLeadTest` (A) and `EnquiryDuplicatesTest` (B2) |
| F4 | Enquiry and tour behaviour intact | PASS-V (by inclusion) | `EnquiryControllerTest`, `TourControllerTest`, `TourWorkflowTest`, `LeadWorkflowTest`, `EnquiryScreensTest`, `EnquiryScopeTest` in A or B2 |
| F5 | Nine deliberate failures (M1 rules unlocked; M2 no once-per-enquiry check; M3 criteria ignored; M4 no eligibility check; M5 tie to the last; M6 active rule editable; M7 least loaded switchable; M8 list open to all staff; M9 saving without permission) | **NV** | recorded in the implementation report and register only; the console output was not preserved |
| F6 | The two initial failures and the correction | PASS-V | B names both `DesignPreviewPagesTest` cases; the preview changed at 22:38:30; B2 passed |
| F7 | Parallel-process tests | PASS-V (by inclusion) | D: 31 = 29 + 2; `publish-worker.php` `auto_assign` mode; the harness refuses non-`*_testing` databases |
| F8 | Tests cover the final tree | **NV** for `config/filament.php` (added after every Pest run; console ignores the component cache, so low risk; covered by kit run 2 only) | modification times |

### Phase-level criteria (for context; not package-level)

| Criterion | Class for P9-B's contribution |
|---|---|
| AC-9.1: FR-CRM-1…6 pass | not assessable from P9-B alone. FR-CRM-2: decided scope PASS-V; least loaded BLOCKED (A-34) |
| AC-9.2: UJ-10 by a Sales/CRM Executive | BLOCKED (O-1, moderated check); outside P9-B |
| AC-9.3: scoping proven by tests | P9-B's part PASS-V (C5, and the general-enquiry test) |

**No row is classified GAP.** The blocked rows need owner decisions. The NV rows need evidence, not code changes.

---

## 7. Recommendation

### 7.1 Acceptance: CONDITIONALLY ACCEPT

P9-B can be accepted **without changing the implementation** for the decided scope:
- round-robin and fixed person;
- the six criteria and first-match precedence;
- unassigned when nothing matches;
- Super Admin management;
- eligibility, integrity and concurrency;
- audit, screens and explanations.

**Conditions:**
1. The owner records the acceptance; tests are not sign-off.
2. **Least loaded is excluded** until A-34 is decided and its workload test is rerun with the decided statuses. If the decision is not "statuses", a code change follows.
3. **A-33 and A-35** are decided or the interim behaviour confirmed. A decision for falling through, or for a different start or reset, means a code change and new tests.
4. The evidence items in §8 (1–4) are completed before the package is closed.

**It is not fully accepted:** FR-CRM-2's least-loaded strategy and BR-CRM-3's notification remain blocked.

### 7.2 Deployment readiness: NOT READY

Before rules can work on any server:
1. **Authorisation** to apply the three migrations: on `smart_go` by the OD-150 procedure (backup, restore check, rehearsal, fingerprints); in a release by `DEPLOYMENT_RUNBOOK` §4 step 5.
2. **`php artisan access:sync`** (step 6) to create `leads.assignment.manage` for the Super Admin, audited.
3. **Cache refresh** wherever caches are used: `php artisan optimize` (includes `filament:optimize`; step 7). On the owner's server today, the routes and the Filament component list from 15:25 lack the new screens. This is unauthorised and must not be done on the owner's behalf. A-30 (mode 664 on the cached configuration) applies to any rebuild.
4. **D-09:** the actual rules (none are created).
5. **A-33, A-34, A-35:** decided, or the interim behaviour accepted.
6. **O-1:** before anyone is notified of unassigned enquiries.
7. **Release control:** P9-B is uncommitted. A-29 (`8325493` and the inventory gate) blocks any push; committing P9-B needs the owner's instruction.
8. A **smoke test** of `/admin/sales/assignment-rules` as the Super Admin on the target server after step 7. The kit cannot show this, because it bypasses the cache.

---

## 8. Remediation, in priority order (not done in this audit)

1. **Preserve the test evidence in the repository:** the Pest summaries (Appendix A) and, on the next authorised run, per-test output in `docs/testing/evidence/phase9/2026-10-31-od160/pest/`.
2. **Rerun the P9-B tests and regression once on `smartgro_testing`** with `config/filament.php` present (F8). This needs the usual authorisation to write to the test database.
3. **Make the mutation checks durable** (F5): rerun the nine with their outputs saved.
4. **Add test-only coverage for the PASS-C rows** that matter at runtime:
   - the screens without the tables (resource refused or hidden; the enquiry page renders without the entry), with a Schema mock;
   - a non-admin opening a rule's view or edit URL directly (403);
   - an explicit "nothing is sent" assertion with an active rule;
   - the other three explanation outcomes.
   No application change is needed.
5. **Browser coverage of a rule's view page, the edit form and switching on and off.** This needs a test-only rule fixture in the kit.
6. *(LOW)* Map a concurrent duplicate priority to the friendly validation message (B6).
7. *(LOW)* Consider warning when switching on a rule whose people cannot work on the locations it matches. Today such enquiries stay unassigned and say why.
8. *(LOW)* When A-34 is decided, refuse an empty `workload_statuses` list. An empty list would currently make least loaded switchable and always pick the first person.
9. A **P9-B activation procedure** for the owner's server, like the Spaces procedure: migrations, `access:sync`, cache refresh, smoke test and rollback.

---

## 9. Owner decisions outstanding (none resolved by this audit)

| ID | Decision | Respected by the implementation? | Recommendation (not a decision) |
|---|---|---|---|
| A-33 | When the first matching rule has nobody eligible: try the next rule, or stay unassigned | yes: unassigned, recorded, explained; no fall-through | keep "stay unassigned": it keeps BR-CRM-3's single-rule meaning, and the enquiry stays visible in the unassigned queue |
| A-34 | Workload for least loaded | yes: `workload_statuses` null; switching on refused; the engine refuses | if wanted, count the person's enquiries in the open statuses (new, contacted, qualified, tour scheduled, proposal sent) |
| A-35 | Round-robin start and reset | yes: interim documented in code (`AssignNewLead` docblock) and register | confirm the interim behaviour; add a manual "reset turn" only if asked |
| D-09 | Which rules exist and their values | yes: no seeder or migration creates rules; none is active by default | the client supplies rules (C-5.2) |
| O-1 | Notification channel | yes: nothing is sent | decide with D-10 |
| P9-B acceptance | owner sign-off | — | conditional acceptance as in §7.1 |

---

## 10. Proposed record updates (not made; only this report was created)

- **`PHASE_9_ACCEPTANCE_PACK.md` §4:** a blank line separates the header from the two P9-B evidence rows, so those rows render outside the table. Remove it. Add a row pointing to this audit and its classification. Note that the mutation checks have no durable artefact.
- **`DECISION_REGISTER.md`:** an OD-161 entry for this audit; optionally an owner action for P9-B's conditional acceptance and for an activation procedure (§8 item 9).
- **`PHASE_STATUS.md`:** a change-log row for this audit.
- **`P9B_ASSIGNMENT_RULES_REPORT_2026-10-31.md` §5:** mark the per-file and mutation rows as console-only evidence.
- **`DEPLOYMENT_RUNBOOK.md` §4 step 10:** add the new back-office screen to the smoke test.

---

## 11. Confirmations

- **No database** was connected to, read or written by this audit, and nothing was migrated, seeded or synchronised.
- **No cache** (configuration, route, event, view or Filament component) was rebuilt, cleared or deleted; two cache files were read with `grep`.
- **No service** was started, restarted or stopped, and no HTTP request was sent.
- **No Git history** was changed: no checkout, reset, clean, stash, commit, push or deployment.
- **All pre-existing changes were preserved.** The only change to the working tree is this new, untracked file.
- **P9-C and P9-D** were not started.

---

## Appendix A — Pest summaries preserved from the session's temporary folder

These are the original single-line JSON summaries, printed at the end of each run in `/tmp/claude-1000/…/scratchpad/`. The folder is outside the repository and is emptied by a machine restart.

| File | Run (IST, 2026-10-31) | result | tests | passed | assertions | failed | skipped |
|---|---|---|---|---|---|---|---|
| `p9b-partA.out` (`tests/Feature/Actions`) | 22:20:20–22:25:52 | passed | 410 | 410 | 1,668 | 0 | 0 |
| `p9b-partB.out` (Filament, Http, Services, Views) | 22:25:52–22:33:10 | failed | 714 | 712 | 4,013 | 2 (`DesignPreviewPagesTest`: "lists as implemented exactly the capabilities that exist in code"; "lists the same capabilities and role grants as the security model") | 0 |
| `p9b-partB2.out` (the same) | 22:40:04–22:47:43 | passed | 714 | 714 | 4,013 | 0 | 0 |
| `p9b-partC.out` (Console, Database, Listeners, Models, Policies, Providers, Security, ExampleTest, Unit, Arch) | 22:33:10–22:34:58 | passed | 241 | 241 | 756 | 0 | 0 |
| `p9b-partD.out` (Infrastructure) | 22:34:58–22:38:07 | passed | 31 | 31 | 106 | 0 | 0 |
