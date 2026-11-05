# SmartGro — P9-B Assignment Rules: Acceptance Evidence Closure

Report of 2026-11-01 (OD-162). It closes the evidence gaps listed in `P9B_ACCEPTANCE_AUDIT_REPORT_2026-11-01.md`.

**Scope.** Tests, browser checks and documentation only: no application code was changed. **Three production defects** were found (D1–D3, §4). They are reported, not fixed, as instructed. P9-B stays **conditionally recommended, not accepted**; acceptance and deployment remain the owner's.

Evidence: `evidence/phase9/2026-11-01-p9b-closure/`.

---

## 1. Initial state

| Item | Fact (captured before any change, `evidence/…/git-start.txt`, `git-start-diffstat.txt`) |
|---|---|
| Branch, HEAD | `main`, `832549382a367d686d9c0981471f2ce66f5270d4` |
| Staged | none |
| Working tree | 67 entries: 32 tracked files modified (+927/−151 across all uncommitted work), 35 untracked. The untracked entries include the P9-A and P9-B files, the acceptance audit, the OD-156 to OD-158 work, and this task's empty evidence folder, created just before the capture. |
| Records | decision register `docs/decisions/DECISION_REGISTER.md`; status log `docs/phases/PHASE_STATUS.md` (verified paths) |

**Test isolation was verified before any test ran:**
- `phpunit.xml` forces `DB_DATABASE=smartgro_testing` (with `force`), `APP_ENV=testing`, and array cache, sessions and mail, with the sync queue.
- `tests/TestCase.php` refuses any database not ending in `_testing`.
- This project's Laravel Boost process (PID 82946) runs with `CACHE_STORE=array` and the never-created config-cache path. A second match was this audit's own shell, already gone.
- The browser kit's `check-target.php` reported `connected_database: smartgro_testing`, `config_cached: false` on every run.

**Other facts:**
- Port 8000 is the HRMS project. No SmartGro server runs on `smart_go`.
- `.env` still has `BACKOFFICE_SAMPLE_CONTENT=false`.

---

## 2. Gaps from the audit, and what this task did

| Audit item | Done here |
|---|---|
| Screens and enquiry creation on a database without the tables (E8) | new tests (§3), **which found defect D1** |
| A non-admin opening a rule's view or edit address directly (C4) | new tests (§3) |
| No "nothing is sent" assertion (D11) | new tests (§3) |
| No browser check of a rule's page, edit form and switches (E9) | kit extended (§5), **which found D2 and D3** |
| Mutation checks with no durable artefact (F5) | reconstructed (§6); the M1 invariant is now also a direct test |
| `config/filament.php` after the last Pest run (F8) | new configuration tests, run (§3, §4) |
| Pest outputs only in a temporary folder | every run's output is saved under `evidence/…/pest/` |
| Pack table rendering; records | corrected (§7) |

---

## 3. New and updated tests

All are test or kit files; no application file was changed.

| File | Tests | Invariant protected |
|---|---|---|
| `tests/Feature/Filament/Clusters/Sales/AssignmentRuleAccessTest.php` (new) | 6 + 6 dataset cases, 1 + 1 + 1 | **Direct addresses:** the list, the new-rule form, a rule's page and its edit form answer 403 to sales, operations, marketing, the Property Manager, finance and a deactivated Super Admin, through HTTP and through the Livewire page mount. **Actions:** changing, switching on and switching off a rule throw `AuthorizationException` for the same six, and nothing changes. **Visitors not signed in** are sent to the sign-in page. **Without the tables** (Schema mock, the established pattern; no table dropped): the resource refuses, the enquiry page and inbox render without "Assignment rules", and **no query reaches the three tables**. **A rule's own address without the tables** must refuse without querying: **skipped as known defect D1** (§4); it fails as written. |
| `tests/Feature/Actions/Crm/AssignmentSideEffectsTest.php` (new) | 3 dataset cases + 3 | **Nothing is sent (O-1)** when a rule assigns, when no rule matches, when assignment fails, or when a website enquiry is stored and assigned. `Notification::fake()` covers mail and database notifications, plus `Mail`, `Queue` and `Http` fakes, each asserting nothing. **Enquiry creation without the tables:** the enquiry is stored, left unassigned, and no query reaches the three tables. **M1 invariant:** the first two queries inside the assignment are `select * from leads … for update` and `select * from assignment_rules where is_active … for update`. |
| `tests/Feature/Providers/FilamentConfigTest.php` (new) | 2 | `filament.cache_path` defaults to `bootstrap/cache/filament`, the admin panel's component cache path follows it, every other Filament setting equals the package's, and `FILAMENT_CACHE_PATH` overrides it (as the kit sets it) |
| `docs/operations/scripts/browser/seed-test-fixtures.php` (kit) | — | Two test-only rules on `smartgro_testing`, both left off: "TEST FIXTURE rule (not a real rule)" (A-70 and 2 to 5 people, the Super Admin as its person, switched on only to record one decision for a test-only enquiry) and "TEST FIXTURE rule without people". Enquiries the kit sends later are not assigned by either. |
| `docs/operations/scripts/browser/verify-admin.mjs` (kit) | 6 new screen checks × 2 widths; one interaction block | §5 |

**Limitations of the no-notification test.** It observes Laravel notifications (every channel, including Filament's database notifications), mail, queued jobs (so a queued notification or mail would show) and Laravel's HTTP client. A direct socket or `curl` call outside these would not be observed. The application has no SMS or WhatsApp adapter (D-10).

---

## 4. Test runs (exact commands and results; all on `smartgro_testing`)

Every Pest command ran from the project root with `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE` and `APP_EVENTS_CACHE` set to files that do not exist, as `php -d memory_limit=3G vendor/bin/pest --compact <paths>`. Outputs are in `evidence/…/pest/`.

| # | Paths | Exit | Result | Output |
|---|---|---|---|---|
| — | `tests/Feature/Filament/Clusters/Sales/AssignmentRuleAccessTest.php` (first version) | 1 | 13 of 14: the missing-table test listed `select * from assignment_rules where id = ? limit 1` → **D1** | `defect-D1-rule-record-url-without-tables.out` |
| — | the D1 test with its skip removed, then restored byte for byte | 1 | fails as written: 3 queries to the hidden tables before the refusal (view and edit) | `defect-D1-unskipped-run.out` |
| F | `tests/Feature/Providers/FilamentConfigTest.php` | 0 | **2 passed**, 5 assertions | `filament-config.out` |
| 1 | the three new files plus `AssignNewLeadTest`, `AssignmentRuleManagementTest`, `AssignmentRuleScreensTest`, `Infrastructure/AssignmentRuleConcurrencyTest` | 0 | **72 tests: 71 passed, 1 skipped (D1)**, 330 assertions | `1-p9b.out` |
| 2 | `SyncAccessControlTest`, `DesignPreviewPagesTest`, `EnquiryControllerTest` (including "no mail or notification is sent"), `tests/Feature/Policies` (including `EnquiryScopeTest`), `Pages/Auth/LoginTest` | 0 | **163 passed**, 517 assertions. A first attempt named a wrong path and stopped before running anything (`2-access-notifications-wrong-path.out`). | `2-access-notifications.out` |
| 3 | `UpdateContactFromLeadTest`, `EnquiryDuplicatesTest` (P9-A) | 0 | **14 passed**, 60 assertions | `3-p9a.out` |
| 4 | `EnquiryScreensTest`, `EnquiryContactToolsTest`, `LeadWorkflowTest`, `TourControllerTest`, `TourWorkflowTest`, `TourScreensTest`, `Infrastructure/PropertyAndEnquiryConcurrencyTest` | 0 | **40 passed**, 459 assertions | `4-enquiry-tour.out` |
| 6A | `tests/Feature/Actions` | 0 | **416 passed**, 1,700 assertions (410 before, plus the 6 new) | `6A.out` |
| 6B | `tests/Feature/Filament tests/Feature/Http tests/Feature/Services tests/Feature/Views` | 0 | **729 tests: 728 passed, 1 skipped (D1)**, 4,097 assertions (714 before, plus the 15 new) | `6B.out` |
| 6C | `tests/Feature/{Console,Database,Listeners,Models,Policies,Providers,Security} tests/Feature/ExampleTest.php tests/Unit tests/Arch` | 0 | **243 passed**, 761 assertions (241 before, plus the 2 configuration tests) | `6C.out` |
| 6D | `tests/Feature/Infrastructure` (real parallel workers) | 0 | **31 passed**, 106 assertions | `6D.out` |

`vendor/bin/pint --dirty --format agent`: passed.

### Defects found (reported, not fixed)

| ID | Defect | Evidence | Effect | Proposed minimal fix (needs authorisation) |
|---|---|---|---|---|
| **D1** | On a database without the assignment tables, a rule's own address (`/admin/sales/assignment-rules/{id}` and `/{id}/edit`) queries `assignment_rules` before refusing. Filament's `ViewRecord::mount()` and `EditRecord::mount()` resolve the record before `authorizeAccess()` (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:69–71`, `EditRecord.php:89–91`). | `defect-D1-*.out` | On such a database (the owner's server, the site) the request would end in a query error (HTTP 500) instead of a refusal, for anyone who types the address. No data is exposed. The list, the new-rule form and the enquiry pages are not affected (tested). Today those servers' cached routes lack these addresses anyway (404). | In `AssignmentRuleResource`, refuse record binding while the tables are missing, e.g. override `resolveRecordRouteBinding()` to `abort_unless(AssignmentRule::isAvailable(), 404)` before the parent call. Then remove the skip from the D1 test. |
| **D2** | The "Remove" buttons on selected values in multi-select fields (Filament's `fi-badge-delete-btn`) are **20 × 20 px**; WCAG 2.2 AA (2.5.8, axe `target-size`) asks for 24 × 24 or enough spacing. | `admin/admin.json`: the edit form at 360 and 1440 px, and with its error | Fails the project's WCAG 2.2 AA standard on the rule's edit form whenever its multi-selects have values. Filament's badge is shared, so other multi-selects with values (the article and property profile forms) probably have it too; not checked here. | Enlarge the button to at least 24 × 24 CSS px in the admin theme (`resources/css/filament/admin/theme.css`), or use checkbox lists for these criteria |
| **D3** | With the pointer over the primary "Save changes" button, its hover colour `#2e8b18` with white 14 px text gives **4.35:1** (WCAG 1.4.3 needs 4.5:1). The same state with the pointer moved away passes. | `admin/admin.json` `editAxe` versus `editAxePointerAway` | Every primary button in the back office, on hover. Earlier kit runs never hovered. | Darken the primary hover shade in the admin theme until white text reaches 4.5:1 |

---

## 5. Browser checks

**Command:**

```
CHECKS=admin CHROMIUM_EXECUTABLE=/snap/bin/chromium TMPDIR=$HOME/snap/chromium/common/tmp \
  bash docs/operations/scripts/browser/run-checks.sh docs/testing/evidence/phase9/2026-11-01-p9b-closure/admin 8767
```

Headless Chromium 156, `smartgro_testing`, configuration not cached.

| Run | Exit | Result | Kept in |
|---|---|---|---|
| 1 | 1 | the new block ran after `browser.close()` (a kit ordering mistake); it was moved, a test-only correction | `admin-run1-browser-closed-early/` |
| 2 | 1 | everything functional passed; axe found D2 and D3 | `admin-run2-axe-on-edit-form/` |
| 3 (final) | **1** | 92 screen checks and 3 viewer checks. **4 failures, all D2 and D3**, recorded with axe's explanation and the element's HTML. Everything else passed. | `admin/` |

**Covered in run 3:**

| Scenario | Result |
|---|---|
| A rule's page as the Super Admin (360 and 1440 px) | 200, 0 axe violations; shows the name, "Locations: SmartGro A-70", "Team sizes: 2 to 5 people", the person "Admin (fixture)", the status "Off", the decision "Assigned by a rule" with a link to the test-only enquiry, and Edit (the rule is off) |
| Its edit form (360 and 1440 px) | 200; **axe `target-size` (D2)** |
| A validation error | an empty name gives "The name field is required." on the field; **axe `target-size` (D2)**, and `color-contrast` with the pointer still on the button (D3) |
| Keyboard | Tab reaches "Switch on"; Enter opens the confirmation; 0 axe violations in the confirmation |
| Switching on | "Rule switched on"; Edit is hidden; the edit address opens the rule's page instead ("Switch the rule off to change it") |
| Switching off | "Rule switched off" |
| Validation when switching on | the rule without people is refused: "Not switched on: Choose the one person this rule gives enquiries to before switching it on." |
| Direct addresses | 403 to sales (page and form), to operations (list) and to marketing (form), at 360 and 1440 px |
| The list, new-rule form and sales refusal (from OD-160) | 200 and 403, 0 axe violations |
| Console errors | none |

**What the kit does not show.** Its server reads the working tree, with Filament's component cache bypassed (`FILAMENT_CACHE_PATH`, OD-160). **These results are uncached source behaviour, not a target server's cached runtime.** On a server whose route cache and Filament component list predate P9-B (the owner's server, cached at 2026-10-31 15:25), the screens answer 404 until `php artisan optimize` runs (`DEPLOYMENT_RUNBOOK.md` §4 step 7; step 10 now says to check new screens). That was not done or checked here.

---

## 6. The nine deliberate-failure checks

**Historical:** each was run once in the implementation session on 2026-10-31 (22:00–22:20 IST). The application code was changed temporarily and restored byte for byte. The implementation report §5 and register OD-160 (e) record each as caught. **The console output was not retained, and none is reproduced here.** Rerunning them would mean editing application code, which this task does not authorise.

**Newly executed:** the tests that guard each invariant passed in run 1 (§4).

| # | Change made then | Invariant | Tests that guard it (run 1, passed) | Historical record |
|---|---|---|---|---|
| M1 | rules read without `FOR UPDATE` | the enquiry, then the active rules, are locked before any plain read, so concurrent turns are consistent | `AssignmentRuleConcurrencyTest` "takes round-robin turns…"; **new:** `AssignmentSideEffectsTest` "locks the enquiry, then the active rules…", which asserts the `FOR UPDATE` that M1 removed | caught, 1 failure (unretained) |
| M2 | the existing-decision check bypassed | one decision per enquiry; a second run changes nothing | `AssignNewLeadTest` "decides once per enquiry…"; the concurrency test "decides once when the same new enquiry is assigned twice…" | caught, 1 (unretained) |
| M3 | criteria ignored in `matches()` | a rule matches only on its criteria | `AssignNewLeadTest` criteria dataset (6) and "does not match a criterion an enquiry has no value for" | caught, 7 (unretained) |
| M4 | eligibility always true | active, and allowed to work on the enquiry's location | "passes over people who are deactivated…", "gives every matching enquiry to the fixed person…", "leaves a general enquiry unassigned…", "does not try the next rule…" | caught, 4 (unretained) |
| M5 | least-loaded tie to the last | a tie goes to the first in the rule's order | least-loaded mechanism test | caught, 1 (unretained) |
| M6 | active rule editable | a rule changes only while off | `AssignmentRuleManagementTest` "changes a rule only while it is switched off…" | caught, 1 (unretained) |
| M7 | least loaded switchable without A-34 | no least-loaded rule can be switched on until workload is defined | "keeps a least-loaded rule off until the owner defines workload (A-34)" | caught, 1 (unretained) |
| M8 | policy `viewAny` true for everyone | only the Super Admin sees the rules | `AssignmentRuleScreensTest` "shows the rules to the Super Admin only…"; **new:** `AssignmentRuleAccessTest` direct addresses (6 cases) | caught, 1 (unretained) |
| M9 | `SaveAssignmentRule` without its `Gate` check | only the Super Admin can save a rule | `AssignmentRuleManagementTest` "refuses everyone else…" (6); **new:** `AssignmentRuleAccessTest` actions (6 cases) | caught, 6 (unretained) |

---

## 7. Documents changed (narrow, factual)

| File | Change |
|---|---|
| `docs/testing/PHASE_9_ACCEPTANCE_PACK.md` | §4: the blank line that pushed two P9-B rows out of the table is removed; rows added for the audit and this closure; the mutation checks marked historical; §3.2 "Open" lists D1–D3. The status is unchanged (not accepted). |
| `docs/testing/P9B_ASSIGNMENT_RULES_REPORT_2026-10-31.md` | §5: an evidence-status note (the per-file and mutation rows were console-only and not retained; where the summaries and fresh runs are) |
| `docs/decisions/DECISION_REGISTER.md` | sections for OD-161 (the audit) and OD-162 (this closure); owner actions A-36 (authorise the D1 fix) and A-37 (authorise the D2 and D3 theme fixes). Nothing is marked approved. |
| `docs/phases/PHASE_STATUS.md` | change-log rows for OD-161 and OD-162 |
| `docs/operations/DEPLOYMENT_RUNBOOK.md` | §4 step 10: open any new back-office screen as the Super Admin after step 7; until the caches are refreshed it answers 404, and the kit cannot show this |

---

## 8. Owner decisions still open (none resolved here)

| ID | Question | Interim behaviour (unchanged, re-verified in run 1) |
|---|---|---|
| A-33 | falling through to the next rule when the first matching one has nobody eligible | stays unassigned, recorded and explained |
| A-34 | workload for least loaded | `workload_statuses` null; switching on refused; the engine refuses |
| A-35 | round-robin start and reset | first in order, then after the last turn |
| D-09 | which rules exist | none; only the two test-only rules on `smartgro_testing` |
| O-1 | notification channel | nothing is sent (run 1) |
| **A-36** (new) | authorise the D1 fix (§4) | — |
| **A-37** (new) | authorise the D2 and D3 fixes in the admin theme | — |

---

## 9. Remaining acceptance gaps

- **GAP D1:** a rule's own address on a database without the tables ends in a query error.
- **GAP D2 and D3:** WCAG 2.2 AA failures on the rule's edit form (target size) and on primary-button hover (contrast). Both are shared back-office components, found through P9-B's screens.
- **BLOCKED:** least loaded (A-34); the A-33 and A-35 confirmations; the BR-CRM-3 notification (O-1); D-09.
- **NOT VERIFIED:**
  - the historical mutation outputs;
  - target-server behaviour (the cached runtime);
  - the article and property profile multi-selects with values (D2 there);
  - Firefox and Safari.

---

## 10. Deployment blockers (unchanged; none done)

1. **Migrations:** the three P9-B migrations need authorisation for the target database (`smart_go` only by the OD-150 procedure; a release by `DEPLOYMENT_RUNBOOK` §4 step 5).
2. **`php artisan access:sync`:** `leads.assignment.manage` (step 6).
3. **Cache rebuild:** `php artisan optimize`, including `filament:optimize` (step 7). On the owner's server, the routes and the component list date from 2026-10-31 15:25 and lack the new screens. A-30 applies to the rebuilt `config.php`.
4. **Rule configuration (D-09)** and the decisions A-33 to A-35. Least loaded stays off until A-34.
5. **D1 to D3** fixed or accepted (A-36, A-37).
6. **A target-server smoke test** of `/admin/sales/assignment-rules` as the Super Admin after step 7 (step 10).
7. **Release control:** P9-B is uncommitted; A-29 blocks any push.

---

## 11. Confirmations

- **`smart_go`** was not connected to, read or written. No database other than `smartgro_testing` was used. No migration, seeder or `access:sync` was run outside the test harness (Pest's `RefreshDatabase` and the kit rebuild `smartgro_testing` only).
- **No cache** was rebuilt or cleared, and no service was started, restarted or stopped, other than the kit's own temporary server on port 8767, which `run-checks.sh` starts and stops. No request went to port 8000 or 8001.
- **No notification, mail or message** was sent: the test fakes recorded nothing, and nothing has a delivery channel.
- **No assignment rule exists or is active outside `smartgro_testing`.** The two test-only rules there are labelled TEST FIXTURE and left off.
- **No Git history** was changed: no commit, push, reset, stash, clean or branch change.
- **All pre-existing work is preserved.** The tracked files changed by this task are listed in §7, plus the test-only kit files `seed-test-fixtures.php` and `verify-admin.mjs`. At the end (`evidence/…/git-end.txt`, `git-end-diffstat.txt`): still on `main` at `8325493`, nothing staged, 72 entries (33 modified, 39 untracked). The only new entries are `DEPLOYMENT_RUNBOOK.md` (modified), this report and the three new test files; the other files changed here were already modified before. No file under `app/`, `config/`, `database/`, `resources/`, `routes/` or `bootstrap/` changed during this task: their tracked diffs are identical, and none is newer than the start capture.
- **P9-C and P9-D** were not started. CSP enforcement was not touched.

---

## 12. Updated acceptance matrix

Statuses:
- **PASS-N:** verified by a check executed in this task.
- **PASS-R:** supported by retained earlier evidence.
- **CODE:** code inspection only.
- **BLOCKED:** needs an owner decision.
- **GAP:** remediation required.
- **NV:** not verified.

| # | Criterion | Status | Evidence |
|---|---|---|---|
| A1 | Round-robin | PASS-N | run 1 (`AssignNewLeadTest`, concurrency test); 6D |
| A2 | Fixed person | PASS-N | run 1 |
| A3 | Least loaded as a strategy | BLOCKED (A-34) | mechanism test passes (run 1); not switchable |
| A3a | Least loaded cannot be switched on | PASS-N | run 1 |
| A4 | Unassigned when nothing matches | PASS-N | run 1 |
| A4a | BR-CRM-3 notification | BLOCKED (O-1) | nothing sent (run 1) |
| A5 | Only new enquiries, from the form or a tour request | PASS-N | runs 1 and 4 |
| A6 | No reassignment of existing, reused or repeated enquiries | PASS-N | runs 1 and 4 |
| B1–B4 | Criteria, empty means any, missing values, active and inactive | PASS-N | run 1 |
| B5 | First match, lowest priority number | PASS-N | run 1 |
| B6 | Unique and validated priority | PASS-N; concurrent duplicate: CODE (LOW) | run 1 |
| B7, B8 | Nobody eligible, and falling through | BLOCKED (A-33) | interim behaviour passes (run 1) |
| C1 | Super Admin-only management | PASS-N | runs 1 and 2; kit run 3 |
| C2, C3 | Permission registered; preview consistent | PASS-N | run 2 |
| C4 | Direct addresses and actions refused | **PASS-N** (was partly CODE) | `AssignmentRuleAccessTest` (run 1); kit run 3 (403 page, form, list) |
| C5, C6 | Eligibility; lists cannot bypass it | PASS-N | run 1 |
| C7 | Consistent with assignment by hand | CODE (`AssignLead` unchanged, same check); `LeadWorkflowTest` passes (run 4) | — |
| D1, D2, D8 | One decision; idempotent; races | PASS-N | runs 1 and 6D |
| D3 | Round-robin under concurrency | PASS-N | concurrency test in runs 1 and 6D |
| D4–D6 | Transaction; rollback; retry | PASS-N | run 1 |
| D7 | Least-loaded tie | PASS-N as a mechanism; the strategy BLOCKED (A-34) | run 1 |
| D9 | Constraints and indexes | CODE (the unique `lead_id` is PASS-N through row "one decision" above) | migrations |
| D10 | Audit without visitor details | PASS-N | run 1 |
| D11 | Nothing sent | **PASS-N** (was CODE) | `AssignmentSideEffectsTest` (run 1), with the §3 limitation |
| E1–E4 | Navigation; starts off; edit only while off; switch only when it can assign | PASS-N | run 1; kit run 3 (keyboard, switches, refusal) |
| E5 | Rules not deleted | CODE | policy `delete` false; no delete action |
| E6, E7 | Explanation on the enquiry; last 20 decisions | PASS-N for the outcomes and the listing tested; CODE for the other wording and the limit | run 1; kit run 3 (decision shown) |
| E8 | Without the tables: engine, enquiry creation, list, enquiry page | **PASS-N** | runs 1 (`AssignmentRuleAccessTest`, `AssignmentSideEffectsTest`) |
| E8a | Without the tables: a rule's own address | **GAP (D1)** | `defect-D1-*.out` |
| E9 | Accessibility: list, new form, rule page, confirmation, keyboard | PASS-N | kit run 3 |
| E9a | Accessibility: rule edit form with values | **GAP (D2)** | kit run 3 |
| E9b | Accessibility: primary button on hover | **GAP (D3)** | kit run 3 |
| F1–F4 | New tests; regression; P9-A; enquiry and tour | PASS-N | runs 1–4, 6A–6D |
| F5 | Nine mutation checks | NV for the historical output; the invariants PASS-N through their guarding tests (§6) | — |
| F6 | The earlier preview correction | PASS-R | audit Appendix A |
| F7 | Parallel workers | PASS-N | 6D, and the concurrency file in run 1 |
| F8 | `config/filament.php` | **PASS-N** | `FilamentConfigTest`; kit run 3 |
| — | Target-server cached runtime | NV | needs an authorised deployment and smoke test |

**Recommendation (unchanged in kind).** P9-B is **not ready for final acceptance**:
- GAP: D1, D2 and D3.
- BLOCKED: A-33, A-34, A-35, O-1 and D-09.

The audit's conditional recommendation for the decided scope still stands. It is now strengthened by fresh evidence, and conditioned additionally on A-36 and A-37. Deployment: **NOT READY** (§10).
