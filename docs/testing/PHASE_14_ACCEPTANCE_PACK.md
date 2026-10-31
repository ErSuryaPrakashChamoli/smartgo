# SmartGro — Phase 14 Implementation Record and Acceptance Pack

| | |
|---|---|
| Status | **In progress, not accepted** (2026-10-30). Local hardening and review under OD-132 to OD-137. Nothing here is an independent review, a manual accessibility audit, a staging measurement or owner UAT. |
| Authority | OD-132 (Phases 12 to 14); OD-133 ("continue", Phase 14 only); OD-134 and OD-135 (continue Phases 12 and 14 locally); OD-136 (P14-1 approved: update `symfony/mime`); OD-137 (P14-2 investigated, then approved: update Pest and PHPUnit). D-21 is not approved. |
| Scope sources | `../../PROJECT_MASTER_PLAN.md` §2 Phase 14 (AC-14.1 to AC-14.4); `../security/SECURITY_MODEL.md`; `../operations/DEPLOYMENT_RUNBOOK.md`; `TRACEABILITY_MATRIX.md` |
| Databases | Test writes on `smartgro_testing` only, after checking the effective connection. `smart_go` and `smartgro_preview` are only read. No request to port 8001. |
| Related | `../decisions/DECISION_REGISTER.md` OD-132 (f), OD-133 to OD-137; `../phases/PHASE_STATUS.md`; Phase 12 definitions: `../requirements/KPI_DEFINITIONS.md` |

**Tests and automated browser checks show what the code does on the test database. They do not replace a penetration test, a manual accessibility audit, staging measurements or the owner's acceptance.**

## 1. Where each part stands (OD-135)

| # | Category | Items |
|---|---|---|
| 1 | **Implemented and verified locally** | P14-1 (`symfony/mime` 8.1.10, OD-136), P14-2 (PHPUnit 13.4.5 with Pest 5.3.4, OD-137) and findings P14-5 to P14-15 below, fixed or checked, with regression tests or kit runs; security coverage (§4); query growth and migrations (§5); full suite 1,267 passed on the updated dependencies; `composer audit` clean; browser kit, every part exit code 0, including the tour journey through cancellation (§6, §7) |
| 2 | **Ready for owner review** | The Phase 12 report definitions (`../requirements/KPI_DEFINITIONS.md`); the open choices in §8 |
| 3 | **Blocked by owner decisions** | Content-Security-Policy enforcement (P14-16, needs a reviewed configuration change, SECURITY_MODEL §5); email delivery (D-10, O-1, A-14); response targets (D-09); hosting and staging (D-14); tracking and consent (OD-100, D-18); AI concierge (D-21, Phase 13) |
| 4 | **Needs an independent reviewer** | Penetration test or independent security review (AC-14.1); manual WCAG 2.2 AA audit (AC-14.2) |
| 5 | **Needs staging** | Performance and NFR measurements (AC-14.3, NFR-PERF-1 to 4); the penetration test |
| 6 | **Needs owner UAT** | AC-14.4, on real content, inventory and settings |
| 7 | **Needs future deployment work (Phase 15, not authorised)** | Backup and restore drill; production checks (P14-3, P14-4); Search Console (AC-11.4) |

## 2. Findings

| ID | Severity | Phase, requirement | Finding | Evidence | Status |
|---|---|---|---|---|---|
| P14-1 | Medium | 14, AC-14.1 | `symfony/mime` v8.1.7 was affected by CVE-2026-45070 (header injection through parameter names). See §3. | `composer audit`; Symfony advisory; reproduced on the library before and refused after the update | **Fixed (OD-136):** updated to v8.1.10 with the owner's approval; only that package changed; suite and browser kit pass |
| P14-2 | Medium (GitHub); development only | 14, AC-14.1 | PHPUnit 13.4.3 was affected by PKSA-qhjm-rpqc-qkn6 / GHSA-9vw2-f4vp-x7gf (CVE-2026-119193): another local user could run code as the developer through a shared temporary folder, when PHPT tests run with code coverage. See §3. | `composer audit`; PHPUnit's advisory; Pest's release notes | **Fixed (OD-137):** Pest v5.3.4 and PHPUnit 13.4.5 with the owner's approval; only those two packages changed; `composer audit` clean; suite passes. Was a recorded exception (OD-112 c) |
| P14-3 | Low (configuration) | 14, 15 | The session cookie is `Secure` only with `SESSION_SECURE_COOKIE=true`; `APP_DEBUG` must be false in production | `config/session.php`, `.env.example` | Runbook production checks |
| P14-4 | Low (local only) | 14 | Laravel Boost's `/_boost/browser-logs` accepts unauthenticated posts wherever the application runs with development packages, including the owner's server. Production installs without them. | `php artisan route:list` | Runbook production checks |
| P14-5 | — | 14 | Checked, no defect: the private disk is served only with a valid temporary signature | `PrivateStorageTest` | Verified |
| P14-6 | Low (test hygiene) | 14 | Test files that commit their data leave 404 rows in the test database | `RecordMissingPageTest` clears the table first | Handled |
| P14-7 | Low (by design) | 14, data integrity | A full rollback is not supported: the audit-log migration has no `down()`, so audit history is never dropped | OD-133 (a) | **Kept.** Runbook rule: roll back by steps after a backup |
| P14-8 | Medium | 6, 11, 12, NFR | Query counts grew with content (home page 18 → 36, sitemap 39 → 76, search titles report 54 → 112) | OD-133 (b) | **Fixed**; `QueryGrowthTest` |
| P14-9 | Medium | 11, FR-SEO-1 | The search titles report rendered every section of each CMS page | OD-134; mutation check | **Fixed**: `PageRenderer::searchFields()` |
| P14-10 | Low (test tooling) | 14, evidence | The browser kit never deleted its work folder when `TMPDIR` was set, as the documented command does. 36 folders (2.2 GB) built up since 2026-10-28. `run.json` showed exit code 0 for scripts not selected. | OD-134; OD-135 kit runs | **Fixed and verified** in 8 kit runs (nothing left behind). The 36 folders were deleted under OD-135 (§6). |
| P14-11 | Medium (evidence) | 14 | A stopped kit run (SIGTERM or SIGHUP) left the running check and its browser going after the kit ended, wrote no `run.json` and deleted the server log | OD-135: signal tests on a small script, then on the kit | **Fixed** (§6): stopped runs end the check, its browser and the server; `run.json` records "interrupted"; logs are kept |
| P14-12 | Medium (evidence) | 8, FR-BK-2 | The tour journey depended on the time of day: at 17:25 India time the only free time left was 17:30, so the move step failed (`checks-tour-attempt1/`, exit code 1). It also never cancelled a tour, which FR-BK-2 requires. | OD-135 | **Fixed**: it picks a later day, cancels the tour and waits out the rate limit; exit code 0 |
| P14-13 | Low (test tooling) | 11 | `verify-public.mjs` fetched every sitemap address before checking it was on the test server | Code review | **Fixed**: other addresses are reported and never fetched |
| P14-14 | Medium | 12, AC-12.2 | The enquiry report grouped days with a fixed `+05:30` and let the language choose the week's first day. With another `BACKOFFICE_TIMEZONE`, the daily totals stopped adding up to "received"; with Hindi, weeks started on Sunday. | `EnquiryReportTest`: failed before the fix; a mutation check restores the old week call | **Fixed**: the configured zone's offset; weeks start on Monday |
| P14-15 | Low | 12 | The report screen described weekly totals as "Per day." and headed their column "Day" for periods of 32 to about 217 days | `EnquiryReportTest`: failed before the fix | **Fixed**: the report states its grouping |
| P14-16 | Medium | 14, NFR-SEC-1 | No Content-Security-Policy yet (SECURITY_MODEL §5, planned for Phase 14). Public pages have no inline scripts, so `script-src 'self'` suits them; the back office (Livewire, Filament) needs nonces. Back-office screens are not yet checked for console errors. | Code review; OD-100 test | **Open.** Plan: report-only first, kit extended to back-office console errors, then enforcement as a reviewed change |

Earlier defects fixed in this period: the deadlock when two new visitors sent an enquiry at the same instant (OD-129 e), and a follow-up due time stored in the wrong zone (OD-130 e).

## 3. Dependency advisories: P14-1 `symfony/mime` (OD-136) and P14-2 PHPUnit (OD-137)

### P14-1: `symfony/mime`

**Update (OD-136, 2026-10-29, approved by the owner).**
* **Before:** v8.1.7 (commit `773ac57f20e2`) in `composer.lock` and `vendor/`. A dry run with scripts off (`composer update symfony/mime --dry-run --no-scripts`) showed one operation only: v8.1.7 to v8.1.10.
* **Command:** `composer update symfony/mime`, exit code 0.
  * The project's Composer scripts run `artisan` (`package:discover`, `filament:upgrade`, `vendor:publish`, `boost:update`). Run with `.env`, `boost:update` would cache its project scan in `smart_go`, and the cache clears would empty the caches the owner's server uses. So the command ran with environment overrides: database `smartgro_testing`, array cache, sessions and sync queue, and every cache path (`APP_*_CACHE`, `VIEW_COMPILED_PATH`) in a scratch folder.
* **After:** v8.1.10 (commit `8d1232052c9c`) in `composer.lock`, `vendor/composer/installed.json` and `installed.php`.
  * The lock-file diff is 6 lines in the `symfony/mime` entry (version, source and dist commit, support link, time). No package was added, removed or changed besides it; `content-hash` and `composer.json` are unchanged.
  * A checksum comparison of the 1,228 working-tree files outside `vendor/`, `node_modules/`, `storage/` and `.git` found only `composer.lock` changed. The republished Filament assets and the regenerated Boost guidelines and skills came out identical. `bootstrap/cache` and the 1,419 compiled views were untouched, and `smart_go`'s figures were unchanged.
* **Verified:**
  * The advisory's case (a parameter name containing CR LF) is now refused with `RfcComplianceException`; a normal `filename` parameter still works.
  * `composer audit` lists only P14-2 (PHPUnit, development only) and exits 1 because of it (until OD-137, below).
  * New `tests/Feature/Security/OutgoingEmailTest.php` sends the three emails SmartGro sends (licence reminder, Filament password reset, Filament emailed login code) through the real mail layer: multipart text and HTML in UTF-8, the right recipient, no header from a title containing a line break. The other email tests fake notifications, so they never built a message.
  * Full suite 1,267 passed; every browser kit part exit code 0 (§6, §7).

**Assessment before the update (OD-135)**, unchanged:
* **Installed and locked:** v8.1.7 (released 2026-09-04, commit `773ac57f20e2`), in both `composer.lock` and `vendor/composer/installed.json`.
* **Advisory:** CVE-2026-45070, GHSA-vqc8-7275-q272, Symfony's page `https://symfony.com/cve-2026-45070`. Severity moderate, CVSS 4.0 base 4.9, CWE-93 (CRLF injection).
* **Affected range:** Symfony's page says `>=8, <8.1.10` for 8.x and that the versions it first listed as fixed (8.0.12 and the others) are not. `composer audit` uses the same corrected range. The GitHub advisory still lists the first ranges, under which 8.1.7 would not be flagged; Symfony's correction governs.
* **Does it apply to 8.1.7?** Yes. On the installed library, a parameter name containing CR LF is written into the header unchanged, so an extra `Bcc:` line appears. That test used the library directly, not SmartGro.
* **Patched version:** 8.1.10 (released 2026-10-28). Between 8.1.7 and 8.1.10 there are 21 commits: the fix, plus escaping fixes in quoted parameter values and encoded address names, and serialisation fixes. It needs the same PHP (`>=8.4.1`), polyfills and conflicts as 8.1.7, and fits every runtime constraint (`laravel/framework` `^7.4.0 || ^8.0.0`; `symfony/mailer` `^7.4|^8.0`).
* **Exposure in SmartGro:** the attack needs an application that takes a MIME parameter *name* from untrusted input. SmartGro's code never sets one. Its email (`MediaLicenceExpiring`, Filament's password reset and email login codes) uses names that Symfony and Laravel set (`charset`, `boundary`, `filename`). The `Content-Disposition` header of the tour calendar file is an HTTP response header, which this component does not produce. So the flaw is not reachable through current code paths. That depends on today's code: new mail code or a package that derives a parameter name from input would expose it. The `log` mail driver doesn't remove the flaw; it only means no email leaves the machine yet.
* **Recommendation (approved and carried out under OD-136):** `composer update symfony/mime`, changing only that package in the lock file, then the full suite, the browser kit and `composer audit`.

### P14-2: PHPUnit

**Assessment (OD-137, 2026-10-30, read-only).**
* **Advisory:** PKSA-qhjm-rpqc-qkn6 / GHSA-9vw2-f4vp-x7gf / CVE-2026-119193, published 2026-10-27. GitHub severity medium, CVSS 4.0 base 5.4, CWE-379. Affects PHPUnit 13.4.0 to 13.4.3; fixed in 13.4.4. Installed and locked: 13.4.3.
* **The flaw:** when PHPT tests run with code coverage, PHPUnit writes each test's code to a folder with a fixed name, `<temp>/phpunit-phpt`, and runs it from there, even when another user created that folder. That user can replace the code and run their own as the developer. PHPUnit 13.4.4 also fixed the same weakness in its automatic coverage cache folder (`<temp>/phpunit-code-coverage-cache`), which is used only with code coverage too.
* **Dependency path:** development only. `pestphp/pest` (required as `^5.3`, also through `pestphp/pest-plugin-laravel`) and `brianium/paratest` (through Pest) require PHPUnit; it is in the lock file's development section.
* **Production:** not affected. Production installs with `composer install --no-dev` (`../operations/DEPLOYMENT_RUNBOOK.md` §4), no application code refers to PHPUnit, and `public/` does not expose `vendor/`.
* **Exposure on this machine:** the temporary folder is the shared `/tmp`, and service accounts run processes here. But there are no PHPT tests, no coverage driver (Xdebug or PCOV) is loaded, and CI sets `coverage: none` on a single-user runner. So the vulnerable code was never reached. D-1's interim rule (no PHPT tests and no coverage on shared machines) kept it that way.
* **Why it waited:** Pest v5.3.3 conflicts with any PHPUnit above 13.4.3 (D-1, OD-112 c). Pest v5.3.4 (2026-10-27 11:04 UTC) requires PHPUnit `^13.4.5` and conflicts with anything above it; its release notes name this advisory.
* **Upstream changes reviewed:**
  * PHPUnit 13.4.4 and 13.4.5: TeamCity and TestDox output, signals during process isolation, the two temporary-folder fixes, and the cache folder without the POSIX extension.
  * Pest 5.3.4: `coversNothing()` restored, a reflection fix for static methods, `--init` and test-impact analysis fixes; the only change to how tests load affects `--init` runs only.
  * The project uses none of these features.

**Update (OD-137, 2026-10-30, approved by the owner).**
* **Dry run** with scripts off: `composer update pestphp/pest:5.3.4 phpunit/phpunit:13.4.5 --dry-run --no-scripts`, exit code 0, two operations only.
* **Command:** `composer update pestphp/pest:5.3.4 phpunit/phpunit:13.4.5`, exit code 0, with the same environment overrides as OD-136. The pinned form installs exactly the reviewed versions.
* **After:** Pest v5.3.4 (commit `159514bacefb`) and PHPUnit 13.4.5 (commit `941457a4f147`), locked and installed.
  * The lock-file diff is in those two entries only: versions, commits, support links, times, and their new requirements. Pest's are `brianium/paratest` ^7.27.4, `phpunit/phpunit` ^13.4.5 and `symfony/process` ^8.1.9, with the conflict `>13.4.5`; PHPUnit's is `sebastian/git-state` ^1.1.
  * The locked `brianium/paratest` 7.27.4, `symfony/process` v8.1.9 (a production package) and `sebastian/git-state` 1.1.0 already met those requirements, so no other package changed. `content-hash` and `composer.json` are unchanged.
  * A checksum comparison of the 1,244 working-tree files outside `vendor/`, `node_modules/`, `storage/` and `.git` found only `composer.lock` changed. The 1,457 compiled views were identical and `bootstrap/cache` was untouched.
* **Verified:**
  * `composer audit` reports no advisories and exits 0.
  * Full suite 1,267 passed, the same counts as OD-136 (§7).
  * The browser kit was not run again: no runtime package changed, and the kit does not use PHPUnit or Pest.

## 4. Security coverage reviewed (OD-132 f, OD-134, OD-135)

| Concern | Evidence |
|---|---|
| Sign-in and back-office access | All 106 back-office routes require sign-in; `backoffice.access` tests; 403 checks in the browser kit |
| Authorisation, property scope, direct object access | Policy and scope tests across enquiries, tours, inventory, pricing and reports (refusals and 403s) |
| Public writes | `/enquire`, `/book-a-tour` and a visitor's tour cancel and move are rate limited (3 a minute, 10 an hour per address); the visitor's actions are also signed |
| CSRF | Checked on a real server, because Laravel skips the check in tests: both public forms without a token answer the branded 419 page (`evidence/phase14/2026-10-29-od135/checks-public/`) |
| XSS and injection | Escaping tests on public pages and back-office screens, JSON-LD escaping, plain-text rules; raw SQL uses only column names fixed in code (the report's time-zone offset is a bound value) |
| Mass assignment | `MassAssignmentTest` |
| Uploads and files | GLB, media and workbook upload checks; private disk signed |
| Personal data in logs and exports | Enquiry and tour log-privacy tests; exports Super Admin only, audited and formula-safe; reports show counts only |
| Audit log | Read-only, 403 without the capability (`ManageActivitiesTest`) |
| Concurrency | 29 tests in `tests/Feature/Infrastructure` run real parallel processes on MySQL |
| Queues and retries | SmartGro's own code defines no queued job. Filament's password-reset and emailed login-code notifications are queued (`ShouldQueue`), so production needs the queue workers DEPLOYMENT_RUNBOOK lists; locally (`QUEUE_CONNECTION=database`) they wait for a worker (corrected under OD-136). Events run after commit; the scheduler's two commands do not overlap; health check at `/up` |
| External services and tracking | No outbound HTTP call or tracking script; OD-100 test |
| Headers | Security headers and `X-Robots-Tag` tests; no Content-Security-Policy yet (P14-16) |

## 5. Performance, data integrity and reliability (local)

* **Query growth (P14-8, P14-9):** 18 addresses run the same number of queries with a little content and with much more (`QueryGrowthTest`). The report needs the same queries for 2,000 more enquiries (`EnquiryReportTest`). These are counts on the test database, not staging response times.
* **Migrations (P14-7):** no migration was added in OD-134 or OD-135. The 22 migrations of this period roll back and apply again cleanly (OD-133 a). No database was reset, refreshed or recreated outside `smartgro_testing`, and no historical migration was changed.
* **Browser kit reliability (P14-10, P14-11):** see §6.

## 6. Browser kit verification (OD-135)

Every run used `CHROMIUM_EXECUTABLE=/snap/bin/chromium TMPDIR=$HOME/snap/chromium/common/tmp bash docs/operations/scripts/browser/run-checks.sh <evidence> 8767` with the `CHECKS` shown, in the foreground. Each run checked the target database first (`smartgro_testing`, uncached configuration) and rebuilt it.

| Run | `CHECKS` | Exit code | Result | Evidence (`evidence/phase14/2026-10-29-od135/`) |
|---|---|---|---|---|
| Failure path | `public`, with `PLAYWRIGHT_MODULE` pointing at a missing file | 1 | `run.json`: public 1, others "not run"; `logs/server.log` kept; nothing left behind | `kit-failure-path/` |
| Stopped with SIGTERM | `tour` | 143 | `run.json`: tour "interrupted" (`interrupted_by` TERM); node, 6 browser processes, server and work folder gone; log kept | `kit-interrupted/` |
| Stopped with SIGINT | `tour` | 130 | Same, `interrupted_by` INT | `kit-interrupted-int/` |
| Public | `public` | 0 | 35 sitemap addresses; 116 page loads; 0 axe violations; 404, 410 and two 419s | `checks-public/`, `public-run.log` |
| Enquiry journey | `enquiry` | 0 | 3 enquiries (360 px, 1440 px, no JavaScript); page built, approved, published and used; wrong email refused with the form kept in view | `checks-enquiry/`, `enquiry-run.log` |
| Tour journey, first attempt | `tour` | **1** | Failed at the move step (time of day, P14-12); kept as a record | `checks-tour-attempt1/` |
| Tour journey | `tour` | 0 | Requested; confirmed by the Super Admin; visitor sees it confirmed, calendar file valid without visitor details; moved; cancelled, with no change offered afterwards; requested without JavaScript; 9 page checks, 0 axe violations, 0 overflow | `checks-tour/`, `tour-run.log` |
| Back office | `admin` | 0 | 74 screen checks (34 addresses, including Search titles, both reports, tours and missing pages), 0 axe violations, 0 overflow | `checks-admin/`, `admin-run.log` |
| 3D performance | `performance` | 0 | 11 runs, every model shown; load average 6.8 at the start, so timings are diagnostic only | `checks-performance/`, `performance-run.log` |

**Clean-up.** After these runs the snap temporary folder had exactly the entries it had before, and no browser from a kit run was left. The 36 older folders were then checked one by one:
* owner `administrator`;
* created between 2026-10-28 14:11 and 2026-10-29 16:09;
* 19 to 81 MB each, 2,179 MB in all;
* none open in any process;
* each with the kit's layout, compiled views of this project's `resources/views` and the A-70 test fixtures.

The server log of the OD-134 run the owner stopped was kept as `evidence/phase14/2026-10-29-od134/journeys-stopped-server.log`. It ends at 16:10:48, so the tour journey had sent no request. The 36 folders were then deleted, leaving 3 MB. Four other entries there (`dashboard.png`, `security.png`, `journey/`, `node-compile-cache/`) are not from the kit and were left alone.

## 7. Test evidence

| Check | Result |
|---|---|
| Full suite (OD-135), four foreground parts on `smartgro_testing`, caches bypassed | `tests/Feature/Actions` 354 (1,436 assertions); `tests/Feature/{Console,Database,Listeners,Models,Policies,Providers,Security,ExampleTest.php} tests/Unit tests/Arch` 238 (723); `tests/Feature/Infrastructure` 29 (97); `tests/Feature/{Filament,Http,Services,Views}` 644 (3,446): **1,265 passed, 0 failed, 0 skipped (5,702 assertions)**. Every part exit code 0; none interrupted. The four parts cover all 122 test files. |
| **After the `symfony/mime` update (OD-136)**, same four parts, target checked | 354 (1,436); 240 (744); 29 (97); 644 (3,446): **1,267 passed, 0 failed, 0 skipped (5,723 assertions)**, every part exit code 0, none interrupted. The two new tests are `OutgoingEmailTest`; all 123 test files are in the four parts. Run first: the email-related tests (58 passed) and `OutgoingEmailTest` (2 passed). |
| **Browser kit after the update (OD-136)**, each part on its own (`evidence/phase14/2026-10-29-od136/`) | public 0 (116 page loads, 0 axe violations, 35 sitemap addresses, 404/410/419/419); enquiry 0 (3 enquiries and the built page); tour 0 (request, staff confirmation, calendar file, move, cancel, request without JavaScript; 9 page checks, 0 axe violations); back office 0 (74 screen checks, 0 axe violations, 0 overflow); 3D performance 0 (11 runs, every model shown; load average about 6, timings diagnostic only). Nothing left behind. |
| **After the Pest and PHPUnit update (OD-137)**, same four parts, target checked | 354 (1,436); 240 (744); 29 (97); 644 (3,446): **1,267 passed, 0 failed, 0 skipped (5,723 assertions)**, every part exit code 0, none interrupted; Pest 5.3.4, PHPUnit 13.4.5. `composer audit`: no advisories, exit code 0. |
| Earlier: OD-134 | 1,262 passed (5,682 assertions) |

## 8. Limits and choices still open

* **Accessibility:** axe-core checks only what it can decide. On 9 of the 58 public page loads with JavaScript it could not decide colour contrast (the home page, `/workspaces`, a location page, its filter, the comparison page and the CMS sections page), so a person must check those. Keyboard checks cover the flows the kit drives. No screen reader was used.
* **Performance:** headless Chromium with a software renderer, on a loaded machine; not staging.
* **Content-Security-Policy (P14-16):** not started, for the reasons in §2.

## 9. Outstanding: needs other people, another environment or the owner's approval

| # | Item | Prerequisite | Responsible | Evidence required | Why outstanding |
|---|---|---|---|---|---|
| 1 | Independent penetration test or security review (AC-14.1) | A staging or production-like environment (D-14); a scope and a tester | Owner appoints; tester performs | Report with findings by severity; no open High or Critical | No environment, no tester |
| 2 | Manual accessibility audit, WCAG 2.2 AA (AC-14.2) | A stable build; screen-reader and keyboard testing by a person | Owner appoints an auditor | Audit report per template and journey, including the 9 contrast cases | Only automated checks exist |
| 3 | Performance measurements (AC-14.3, NFR-PERF-1 to 4) | Staging on the chosen host with CDN and caches (D-14); NFR-PERF-4's conditions (A-13) | Owner and infrastructure operator | Lighthouse or field measurements on staging | No staging |
| 4 | Owner UAT (AC-14.4) | Real content, inventory and settings; a test plan | Owner | Signed UAT record | Owner action |
| 5 | Email delivery checks | A mail provider and sending domain (D-10, O-1); A-14 | Owner | Delivery results | No provider chosen |
| 6 | Backup and restore drill | Production hosting (D-14) | Owner and operator | Restore log (Phase 15) | Phase 15 not authorised |
| 7 | Search Console and external structured-data validation (AC-11.4) | Production domain; the owner's account | Owner | Verification and submission records | Production does not exist |
| 8 | Content-Security-Policy (P14-16) | Report-only policy and back-office console checks first | Developer builds; owner approves enforcement | Kit runs with no violations, then the enforced policy | Not started |

Done in OD-135: the enquiry and tour journeys ran again (exit code 0), and the 36 leftover kit folders were deleted. Done in OD-136: the P14-1 update (§3). Done in OD-137: the P14-2 update (§3).

## 10. Phase 13 (AI concierge)

**Blocked; nothing built** (OD-132 e). D-21 (scope and provider) stays open: no option chosen, and no external AI service, cost or data transfer exists.

| Blocked work | Waiting on |
|---|---|
| AC-13.1: FR-AI-1, a conversational finder that answers from canonical inventory, availability and approved content, and hands off to a tour or enquiry | D-21: whether there is a concierge at launch, the provider, where it runs, its cost limit, who may switch it on |
| What it may answer from | Real inventory (entry gate); prices (D-04, PR-1 to PR-11); public availability (A-8) |
| Keeping conversations | Privacy rules (D-15, counsel; privacy notice L-1) |
| AC-13.2: red-team tests (no invented prices or availability) | All of the above |
