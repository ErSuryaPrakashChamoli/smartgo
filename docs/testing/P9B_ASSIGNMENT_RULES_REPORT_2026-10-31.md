# SmartGro — Phase 9 package P9-B: assignment rules (OD-160)

Report of 2026-10-31. FR-CRM-2 and BR-CRM-3. Built locally, uncommitted, **not accepted**: acceptance needs the documented criteria (AC-9.1 to AC-9.3) and the owner's sign-off.

**Boundaries kept:**
- No commit, push or deployment.
- `smart_go` was not read or written for this package.
- The preview was not touched.
- No notification was sent.
- No CSP change.
- P9-C and P9-D were not started.

## 1. Starting state (verified before any change)

| Item | Fact |
|---|---|
| Git | `main`, HEAD `8325493`, nothing staged. The uncommitted P9-A files (`UpdateContactFromLead`, `Lead`, `LeadInfolist`, `ViewLead`, `LeadsTable`, two test files) and every OD-156, OD-157 and OD-158 file were present and were preserved. |
| P9-A mutation restores | Each restored line is present: the enquiry-location permission check `Gate::forUser($actor)->authorize('update', $lead)` in `UpdateContactFromLead`, the identity check, the difference rule, and the scoped list `Lead::query()->visibleTo($user)`. `UpdateContactFromLeadTest` passed again: 9 tests. |
| Port 8000 | Now the **HRMS project** (`php artisan serve` from `~/Documents/hrms/hcms`). No SmartGro server ran on 8000 or 8001, so nothing served this working tree on `smart_go`. The site on 8080 (`smartgro_site`) runs the working tree but has none of the new tables: the guard keeps its enquiries unassigned, as before, and hides the new screen. |
| Test environment | `phpunit.xml` forces `DB_DATABASE=smartgro_testing`, `APP_ENV=testing`, and array cache and sessions. `tests/TestCase.php` refuses any other database. The concurrency worker refuses any database not ending in `_testing`. Every run used the three cache-bypass variables. Laravel Boost's process for this project has `CACHE_STORE=array` and the never-created config-cache path. |

## 2. What the documentation already decided, and what it did not

| Question | Source | Applied |
|---|---|---|
| Who manages rules | OD-36; SECURITY_MODEL §3.1 "Assignment rules, pipeline stages: Super Admin" (owner-confirmed) | new capability `leads.assignment.manage`, Super Admin only |
| Which rule wins | BR-CRM-3 "the first matching active `assignment_rule` (by priority)" | lowest number first; each priority used once (a unique column), so no tie |
| No match | BR-CRM-3, FR-CRM-2: the unassigned queue | the enquiry stays unassigned, as today |
| Who can be given an enquiry | the established manual-assignment check (`AssignLead`: active, `leads.manage` within the enquiry's location scope; BR-CRM-6, OD-111 b) | the same check |
| Criteria | ERD §6, PRD FR-CRM-2: city, location, workspace type, customer segment, seats band, source | all six; "seats band" is the enquiry's team size |
| Default rules | none documented | none created |
| Notification when unassigned (BR-CRM-3) | blocked by O-1 | not sent; the back office keeps its unassigned count |
| **Falling through** to the next rule when the first matching one has nobody eligible | **not decided** | interim: unassigned, recorded as "matched, but nobody could take it" → **A-33** |
| **Workload** for least loaded | **not decided** | a least-loaded rule cannot be switched on → **A-34** |
| **Round-robin start and reset** | **not decided** | interim: first person in the Super Admin's order; then after whoever had the last turn (from the first again if that person was removed); no reset on edit → **A-35** |

## 3. What was built

| Area | Files |
|---|---|
| Schema (ERD v1.25, C-39; test database only) | `database/migrations/2026_10_31_163023_create_assignment_rules_table.php` (`priority` unique, `criteria` JSON, `is_active` default false, `last_assigned_user_id`); `…163025_create_assignment_rule_user_table.php` (people in order: primary (rule, user), unique (rule, position)); `…163026_create_lead_assignments_table.php` (one decision per enquiry: `lead_id` unique) |
| Models and enums | `app/Models/AssignmentRule.php` (`TABLES`, `CRITERIA`, `isAvailable()`, `matches()`); `app/Models/LeadAssignment.php`; `app/Enums/Crm/AssignmentStrategy.php`; `app/Enums/Crm/AssignmentOutcome.php`; `Lead::SOURCES` and `Lead::assignment()`; `config/crm.php` (`assignment.workload_statuses` = null) |
| Assignment | `app/Actions/Crm/AssignNewLead.php`, run with `DB::afterCommit` after each new enquiry (`SubmitEnquiry`) and each new tour-request enquiry (`RequestTour`); a reused open enquiry or a repeat never runs it |
| Management | `app/Actions/Crm/SaveAssignmentRule.php`; `app/Actions/Crm/SetAssignmentRuleActive.php`; `app/Policies/AssignmentRulePolicy.php`; `Capability::ManageAssignmentRules` granted to the Super Admin in `StaffRole` |
| Screens | `app/Filament/Clusters/Sales/Resources/AssignmentRules/` (resource, list, create, edit, view, `SavesAssignmentRules`); the "Assignment rules" explanation on the enquiry page (`LeadInfolist`); the permissions preview (`PermissionsReview`) |
| Tests | `tests/Feature/Actions/Crm/AssignNewLeadTest.php` (22); `tests/Feature/Actions/Crm/AssignmentRuleManagementTest.php` (18); `tests/Feature/Filament/Clusters/Sales/AssignmentRuleScreensTest.php` (7); `tests/Feature/Infrastructure/AssignmentRuleConcurrencyTest.php` (2); one new test in `tests/Feature/Http/Controllers/Web/TourControllerTest.php`. Updated for the new capability: `SyncAccessControlTest` (the catalogue and Super Admin lists). Harness: `tests/Support/Concurrency/PublishingHarness.php` and `publish-worker.php` (mode `auto_assign`; the new tables in the reset list). |
| Browser kit | `docs/operations/scripts/browser/verify-admin.mjs`: the rules list and form (Super Admin), and the list refused to sales; `run-checks.sh`: `FILAMENT_CACHE_PATH` bypassed (§4a); `config/filament.php` (new, `cache_path` only) |
| Documents | `docs/security/SECURITY_MODEL.md` §11; `docs/architecture/DATABASE_ERD.md` §6 and v1.25; `PHASE_9_ACCEPTANCE_PACK.md` §3.2; `TRACEABILITY_MATRIX.md` FR-CRM-2; the decision register (OD-160, A-33 to A-35); the status log |

**Strategies:**
- **Round-robin:** the next eligible person after the last turn, going round. People who cannot take this enquiry are passed over without using their turn.
- **Fixed person:** the one person, if eligible.
- **Least loaded:** the eligible person with the fewest enquiries in the configured statuses; a tie goes to whoever is higher in the rule's order. It cannot be switched on until A-34.

**Criteria (each an empty list for "any"):**
- cities: `city_id`;
- locations: `property_id`;
- workspace types: `product_id`;
- segments: `customer_segment_id`;
- team sizes: `team_size`, the enquiry form's five bands;
- sources: website form, city waitlist, tour form.

An enquiry must meet every list that is filled in. An enquiry without the value (no city, say) does not meet that list.

## 4. Authorisation, integrity and concurrency

**Who.**
- Only the Super Admin (`leads.assignment.manage`) can see, create, change, or switch rules on and off. `AssignmentRulePolicy` refuses a deactivated account first.
- Deleting is refused; rules are switched off instead.
- Every action checks the permission again (`Gate`), not just the screen.

**Separation.**
- A new rule is saved off.
- It is switched on explicitly, and only when it can assign: it has people, they are active with `leads.manage`, a fixed-person rule has exactly one, and a least-loaded rule needs A-34.
- An active rule must be switched off before it is changed, so no enquiry is assigned by a half-edited rule.

**Eligibility.** The same check as a manual assignment is applied at the moment of assigning, so a person deactivated or moved off a location since the rule was set is passed over.

**Concurrency.**
- `AssignNewLead` locks the enquiry, then every active rule, before its first plain read. MySQL takes a transaction's snapshot at its first plain read, so each assignment sees the turns and workloads the previous one committed (ADR-0004; the harness's stale-snapshot case).
- Concurrent new enquiries therefore take round-robin turns one after another, and least loaded counts a consistent workload.
- **Proven with real parallel processes:**
  - four enquiries at the same instant → 2 and 2, four decisions, four audit entries, the turn on the second person;
  - the same enquiry twice at the same instant → one decision, one assignment, one audit entry.

**Failure and retry.**
- The assignment runs after the enquiry is committed, in its own transaction.
- A failure rolls back the decision, the owner and the turn together, and is logged as `lead_id` and the exception class only (no personal data). The visitor's submission is not affected.
- A later run assigns normally.
- A second run for a decided enquiry returns the first decision and changes nothing. An enquiry that already has an owner is never reassigned.

**Audit.**
- Each assignment is audited as `lead_auto_assigned` (reference, rule id, owner id; no visitor details).
- Rule changes are audited as `assignment_rule_created`, `_updated` (field names only), `_activated` and `_deactivated`.

**Explainable.**
- Each enquiry shows why it has, or has not, an owner: the rule's name and strategy, never its other people.
- Each rule's page lists its last 20 decisions.

**Guarded.** Without the three tables (the owner's server, the site), nothing runs, nothing is queried, and the screens are hidden. This is tested with a Schema mock.

## 4a. Found: Filament's component cache hides new screens on servers

**Finding.**
- `bootstrap/cache/filament/panels/admin.php` was written at 15:25 on 2026-10-31 by the run outside the development session that also cached the configuration. Filament uses it for **web requests only**; the console and tests discover components themselves.
- It does not list the new resource, so **Sales → Assignment rules answers 404 on any server that reads it**: the owner's server and the site. Tests passed.

**Fix for the kit only.** A minimal `config/filament.php` exposes `cache_path` as `FILAMENT_CACHE_PATH`. The default is the same folder, and every other Filament setting still merges in. `run-checks.sh` points it at a folder that does not exist, as it already does for the configuration, route and event caches.

**Not done.** The cache was not rebuilt or deleted: it is part of what the owner's server serves. Rebuilding it (`php artisan filament:cache-components`) is the owner's choice. On the site and the owner's server the screens would stay hidden anyway, because their databases lack the tables.

## 5. Tests and checks (all on `smartgro_testing`)

**Evidence status (added 2026-11-01, OD-162):**
- The five per-file rows and the mutation row below were console output in the implementation session; **no output was retained**.
- The regression summaries were kept only in a temporary folder; they are copied into `P9B_ACCEPTANCE_AUDIT_REPORT_2026-11-01.md` Appendix A.
- Fresh runs, with their outputs saved, are in `P9B_ACCEPTANCE_EVIDENCE_CLOSURE_REPORT_2026-11-01.md` and `evidence/phase9/2026-11-01-p9b-closure/`.

| Command | Exit | Result |
|---|---|---|
| `pest tests/Feature/Actions/Crm/AssignNewLeadTest.php` | 0 | 22 passed, 74 assertions |
| `pest tests/Feature/Actions/Crm/AssignmentRuleManagementTest.php` | 0 | 18 passed, 70 assertions |
| `pest tests/Feature/Filament/Clusters/Sales/AssignmentRuleScreensTest.php` | 0 | 7 passed, 56 assertions |
| `pest tests/Feature/Http/Controllers/Web/TourControllerTest.php` | 0 | 8 passed, 121 assertions |
| `pest tests/Feature/Infrastructure/AssignmentRuleConcurrencyTest.php tests/Feature/Infrastructure/PropertyAndEnquiryConcurrencyTest.php` (real parallel workers) | 0 | 6 passed |
| Mutation checks: rules not locked; no once-per-enquiry check; criteria ignored; no eligibility check; ties to the last; an active rule editable; least loaded switchable without A-34; the list open to all staff; saving without the permission check | — | **each caught** (historical: console output not retained; mapped to the tests that guard each invariant in the closure report §6); files restored byte for byte |
| **Regression A** `pest tests/Feature/Actions` | 0 | **410 passed**, 1,668 assertions |
| **Regression B** `pest tests/Feature/Filament tests/Feature/Http tests/Feature/Services tests/Feature/Views` | first run 1 | 712 of 714: both failures in `DesignPreviewPagesTest`, which keeps the permissions preview equal to the code and SECURITY_MODEL §11. The new capability was missing from the preview; it was added, and the file then passed (63). **Rerun in full: exit 0, 714 passed**, 4,013 assertions, 0 failed, 0 skipped. |
| **Regression C** `pest tests/Feature/{Console,Database,Listeners,Models,Policies,Providers,Security} tests/Feature/ExampleTest.php tests/Unit tests/Arch` | 0 | **241 passed**, 756 assertions |
| **Regression D** `pest tests/Feature/Infrastructure` | 0 | **31 passed**, 106 assertions |
| `vendor/bin/pint --dirty --format agent` | 0 | passed |
| Browser kit `CHECKS=admin`, first run (`evidence/phase9/2026-10-31-od160/admin-run1-stale-filament-cache-404/`) | 1 | the three new screens answered **404**: the kit's server read Filament's component cache from 15:25, built by an outside `optimize` (§4a). The other 74 checks passed. |
| Browser kit `CHECKS=admin` with `FILAMENT_CACHE_PATH` bypassed (`evidence/phase9/2026-10-31-od160/admin/`) | **0** | 80 screen checks and 3 viewer checks, 0 failures. Rules list and form at 360 and 1440 px: 200, 0 axe violations; refused to sales: 403; the enquiry page with its assignment explanation: 0 axe violations |

**P9-A and earlier behaviour still pass inside these runs:**
- duplicates, the contact update and its privacy: `UpdateContactFromLeadTest`, `EnquiryDuplicatesTest`;
- enquiries, inbox, assignment by hand and export: `LeadWorkflowTest`, `EnquiryScreensTest`, `EnquiryScopeTest`, `EnquiryControllerTest`;
- tours: `TourControllerTest`, `TourWorkflowTest`.

**Blocked expectations.** These are **not** asserted as approved behaviour:
- least loaded with a workload definition: tested as a mechanism only, with statuses set by the test (A-34);
- "no falling through": tested and named as the interim behaviour until A-33;
- round-robin start: tested as the interim behaviour (A-35).

## 6. Decisions needed (owner actions)

| ID | Question |
|---|---|
| A-33 | When the first matching rule has nobody who can take the enquiry, try the next matching rule, or leave it unassigned (today)? |
| A-34 | What counts as workload for least loaded: enquiries in which statuses, or another measure? Until set, a least-loaded rule cannot be switched on. |
| A-35 | Round-robin start and reset: confirm today's behaviour, or say when the turn should reset. |
| D-09 | Which rules SmartGro wants (none is created by default). |
| O-1 | A delivery channel, before anyone is notified of unassigned enquiries (BR-CRM-3). |

## 7. Deferred on purpose

- Rules apply to **new** enquiries only, never to those already received.
- There is no button to run the rules again.
- A rule cannot be deleted.
- The enterprise seat-band form (FR-PROD-3) and sources other than today's three wait for their own features.
- **Before rules can work on any server**, its database needs the three migrations and `php artisan access:sync` (for `leads.assignment.manage`). On `smart_go` that needs separate authorisation and the OD-150 procedure. Nothing was applied anywhere but `smartgro_testing`.
- P9-C (first-response timers) and P9-D (pipeline and deals) were not started.

## 8. Git state

Nothing committed, pushed or deployed; HEAD is still `8325493`. No staged changes. 65 entries in `git status`: 32 tracked files modified (+925/−151 across all uncommitted work) and 33 untracked. They include P9-B's new files (§3), the P9-A files, and every OD-156 to OD-158 file, all preserved. Nothing was reset, stashed, cleaned or overwritten.
