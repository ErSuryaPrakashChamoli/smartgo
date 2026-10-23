# Phase 1 — Progress Report

| | |
|---|---|
| Date | 2026-10-24 |
| Phase status | **Phase 0: APPROVED in principle · Phase 1: IN PROGRESS** (not ready for approval: client inputs outstanding) |
| Prepared for | Project owner |
| Evidence | Git history, test outputs and file checks cited inline. No result is claimed without a command or file to verify it. |

---

## 1. Phase 0 approval summary

* On 2026-10-24 the project owner **approved the Phase 0 findings in principle**. The status is recorded in `PHASE_STATUS.md` and the Phase 0 report header.
* Phase 0 is **not** marked COMPLETE: its open decisions and risks continue in [`../decisions/DECISION_REGISTER.md`](../decisions/DECISION_REGISTER.md).
* Owner directions applied in this round:
  * Git set up now (D-23)
  * provisional palette approved (D-11a)
  * no assumption about existing systems (D-06; assumption A-2 withdrawn)
  * no invented business facts
  * a MySQL dev/test configuration with no passwords exposed (ADR-0002 accepted)
  * modular monolith retained (ADR-0001 accepted)
  * MySQL concurrency tests before any booking code
  * Phase 1 kept open

---

## 2. Git setup and repository status

| Item | Status | Evidence |
|---|---|---|
| Inspection before init | done | no `.git` existed; Git identity already configured on the machine; GitHub CLI **not installed**; no SSH key |
| Pre-commit review | done | 124 files listed. `.env`, the SQLite DB, `public/build`, `bootstrap/cache`, `vendor` and `node_modules` were confirmed ignored with `git check-ignore`. Scan for key patterns, secret-like assignments and personal emails found **only placeholders**. No customer data exists in the repository. |
| Repository | **initialised**, branch `main` | `git log --oneline` |
| Commits | small and reviewable: baseline as found → Phase 0 documents → ignore-rule hardening → MySQL test configuration and harness → import template → Phase 1 documents | `git log` |
| Ignore hardening | `.env.testing`, `.env.*.local`, `/.claude/settings.local.json` added | `.gitignore` |
| Remote | **not connected.** Needs the owner to choose the repository owner (D-31). Exact steps for both the CLI and the web UI are in `../operations/LOCAL_DEVELOPMENT.md` §5. Local version control is not blocked. | — |

---

## 3. Updated decision register (summary)

Full register: [`../decisions/DECISION_REGISTER.md`](../decisions/DECISION_REGISTER.md). It covers 30 original decisions plus 5 new ones (D-31…D-35) and 10 original risks plus 3 new ones (R-11…R-13).

| Classification | Decisions |
|---|---|
| **Resolved** | D-11a provisional palette · D-23 Git · D-26 MySQL dev/test configuration (owner action A-1 pending) |
| **Requires owner approval** | D-24 dependencies · D-25 code layout · D-31 GitHub repository owner · D-32 release slicing · D-34 ADR-0003…0012 · D-35 import template and process |
| **Requires client input** | D-01 company/market · D-02 locations · D-03 products · D-05 seat granularity · D-06 existing systems · D-11b logo files · D-16 languages · D-19 existing website · D-22 brand spelling/domain · D-33 team/roles |
| **Deferred** | D-04 (Ph 5) · D-07, D-08, D-20, D-28 (Ph 8) · D-09, D-29 (Ph 9) · D-10 (Ph 8/10) · D-12, D-15, D-18, D-27 (Ph 6) · D-13 (Ph 7) · D-14, D-30, Redis (Ph 3) · D-17 (Ph 4) · D-21 (Ph 13) |

| Risks | Classification |
|---|---|
| R-2 no version control · R-8 dev/prod DB parity · R-13 snapshot-before-lock pitfall | **Resolved** (R-8: owner action A-1 pending) |
| R-5 scope breadth · R-11 single local copy | **Requires owner approval** (D-32, D-31) |
| R-1 existing systems · R-3 no logo/photos · R-12 slow client inputs | **Requires client input** |
| R-4 3D (Ph 7) · R-6 regulatory (Ph 6/8/10) · R-7 concurrency implementation (Ph 8) · R-9 content consistency (Ph 4/5) · R-10 spam (Ph 8) | **Deferred** |

---

## 4. Phase 1 documents

### Completed to v1.0-rc (ready for owner review)

| Area | Document | Notes |
|---|---|---|
| Requirements | `../requirements/PRD.md` | v0.2 (v1.0-rc); defaults marked as unapproved; assumption A-2 withdrawn |
| Sitemap and journeys | `../requirements/INFORMATION_ARCHITECTURE.md` | ◇ items depend on client inputs |
| Business rules | `../requirements/BUSINESS_RULES.md` | mechanisms separated from client-owned values; BR-SOT-6 added (one booking engine per space); BR-BK-5 lock-first |
| Client inputs | `../requirements/CLIENT_INPUTS_REQUIRED.md` | **new**; 41 items, each with why it matters, a default and the phase it blocks |
| Inventory import | `../requirements/INVENTORY_IMPORT_SPEC.md` + template + 2 synthetic fixtures | **new**; 37 validation rules; four-eyes approval; all-or-nothing commit |
| System architecture | `../architecture/SYSTEM_ARCHITECTURE.md` | verified state updated; API paths aligned |
| ERD | `../architecture/DATABASE_ERD.md` | import tables and `external_system`/`external_ref` added; M-1…M-6 classified |
| API contracts | `../architecture/API_CONTRACTS.md` | **new**; 4 read endpoints, 3 form contracts, webhooks, domain actions |
| Integration inventory | `../architecture/INTEGRATION_CONTRACTS.md` | linked to the assessment |
| Existing systems | `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` | **new**; questionnaire Q1–Q10, scenarios A–D, recommended source of truth, sync design |
| Role and permission matrix | `../security/SECURITY_MODEL.md` §3.1 | import permissions added; awaits client team confirmation (D-33) |
| Security model | `../security/SECURITY_MODEL.md` | secrets handling updated with the pre-commit scan |
| Test strategy | `../testing/TEST_STRATEGY.md` | MySQL implemented; T-BK-01…10, T-TR-01, T-ARCH-01, T-IMP-01…06 catalogued |
| Design system | `../design/DESIGN_SYSTEM.md` | palette approved as provisional; logo outstanding |
| Deployment plan | `../operations/DEPLOYMENT_RUNBOOK.md` + `../operations/LOCAL_DEVELOPMENT.md` | CI plan, hosting criteria, local setup |
| Decisions | `../decisions/DECISION_REGISTER.md`, ADR-0001…0012 | ADR-0004 amended with harness evidence; ADR-0012 new |
| Phase acceptance criteria | `../../PROJECT_MASTER_PLAN.md` §2 | exact AC-1.1…1.8 (§8 below) |

### Outstanding for v1.0 (cannot be finished without inputs)

* Business values in the PRD and business rules (client)
* ERD validation against a real location (needs the client spreadsheet)
* Role matrix confirmation (D-33)
* Sitemap items marked ◇ (D-03, D-27)
* Owner review comments on all v1.0-rc documents

### Safe local setup completed and verified

| Item | Evidence |
|---|---|
| `phpunit.xml` forces MySQL and `smartgro_testing`; `.env.example` has placeholders only | `git show aa70e23` |
| `tests/TestCase.php` guard refuses non-`*_testing` databases | a temporary config pointing at `smartgro` was refused with the guard message; the temporary file was deleted |
| MySQL concurrency harness: 7 tests (engine, lock wait, rollback, overlap, capacity, 2 negative controls) | **9/9 passed, 3 consecutive runs**, about 16.5 s each, on a throwaway MySQL **8.4.11**. It ran under the owner's user account with a scratch data directory and a private socket, without touching the system MySQL server or any credentials. |
| Setup script writes credentials only to the local `.env` | the `.env` rewrite logic was tested on a scratch copy: only `DB_*` lines changed, all other lines identical; `bash -n` passed |
| Pint | passed |

**Finding worth noting (R-13).** The harness showed that taking a plain read before the row lock causes double booking even though a lock is taken. This is because of how InnoDB fixes a transaction's snapshot. ADR-0004 and BR-BK-5 now require the lock to be the transaction's first statement, and a negative-control test guards it.

---

## 5. Recommended answers for unresolved architectural decisions

| ID | Recommendation | Rationale |
|---|---|---|
| D-24 Dependencies | **Approve**, installed phase by phase, never all at once:<br>• Phase 3: `spatie/laravel-permission` 8.x, `spatie/laravel-activitylog` 5.x, phpredis host extension (fallback `predis/predis` 3.x), `laravel/horizon` 5.x<br>• Phase 4: `spatie/laravel-medialibrary` 11.x, `filament/spatie-laravel-media-library-plugin` 5.x, `league/flysystem-aws-s3-v3` 3.x<br>• Phase 5: `openspout/openspout` 4.x declared as a direct dependency<br>• Phase 6/11: `spatie/schema-org` 5.x, `spatie/laravel-sitemap` 8.x<br>• Phase 7: `three` 0.186.x | all publish Laravel 13 / PHP 8.5-compatible constraints (Packagist and npm, checked 2026-10-24) |
| D-25 Code layout | **Approve** domain sub-namespaces inside standard Laravel folders | keeps Laravel conventions; no new base folders |
| D-31 Repository owner | **Create the private repository under a GitHub organisation** (yours or the client's, per the contract on IP ownership), not a personal account | access control, transfer at handover, branch protection |
| D-32 Release slicing | **Approve R1** as drafted (`PROJECT_MASTER_PLAN.md` §4) | delivers a credible launch without waiting for 3D assets or automation |
| D-34 ADRs | **Accept ADR-0003…0012** | each records alternatives and consequences; ADR-0004 is now backed by MySQL evidence |
| D-35 Import process | **Approve** SG-INV-1.0, and ask client operations to trial the template on the first location | validates both the template and the ERD |
| D-30 (Phase 3) | non-default back-office path + Cloudflare Access in production | defence in depth for staff accounts |
| D-14 (Phase 3) | client-owned accounts on managed Laravel hosting behind Cloudflare, India region; decide using `DEPLOYMENT_RUNBOOK.md` §6 | operational simplicity; residency pending legal advice |

---

## 6. Client information and assets still required

The full checklist, ready to send, is in `../requirements/CLIENT_INPUTS_REQUIRED.md`. Priority order:
1. **Original SmartGro logo files** (vector master + variants). Not found anywhere on the project machine. Blocks Phase 2.
2. **Location list**, and the **first location's inventory** in the SG-INV-1.0 template. Validates the ERD (AC-1.4).
3. **Existing systems questionnaire** (Q1–Q10). Decides the source of truth (D-06).
4. **Products actually sold** (D-03), and **seat tracking** (D-05).
5. Company profile and market (D-01), brand spelling and domain (D-22), languages (D-16), existing website URLs (D-19), team structure (D-33).

Later phases will need: pricing policy (Ph 5), booking scope, cancellation and tour rules (Ph 8), photography (Ph 6), floor plans and 3D (Ph 7), legal policies (Ph 6), messaging providers (Ph 8/10), hosting accounts (Ph 3).

---

## 7. Risks and dependencies

* **Critical path:** client inputs (logo, locations, inventory, systems) lead to Phase 1 approval, then Phase 2 and Phase 3 in parallel, then Phase 4 and Phase 5. Dependency map: `../../PROJECT_MASTER_PLAN.md` §3.
* **Top risks:**
  * R-1: an existing system could change the inventory master
  * R-12: input latency
  * R-3: no real imagery
  * R-11: no off-machine copy of the repository until the remote is pushed

  Mitigations are listed in the register.

---

## 8. Exact acceptance criteria for Phase 1, with current status

| AC | Criterion (summary; full text in `PROJECT_MASTER_PLAN.md` §2) | Status |
|---|---|---|
| AC-1.1 | Documents complete, consistent, all links resolve | **Partly met.** All at v1.0-rc; link check 0 broken (2026-10-24); awaiting owner review. |
| AC-1.2 | Blocking client inputs answered or explicitly deferred | **Not met** |
| AC-1.3 | D-06 answered, or Scenario A deferral recorded with a Phase 5 gate | **Not met.** Assessment ready. |
| AC-1.4 | ERD validated against the first real location via SG-INV-1.0 | **Not met.** Template and rules ready. |
| AC-1.5 | No silent defaults: each confirmed or deferred in the register | **Partly met.** All classified; confirmations pending. |
| AC-1.6 | ADR-0003…0012, D-24, D-25, D-32, D-35 approved | **Partly met.** ADR-0001/0002 accepted. |
| AC-1.7 | Git pushed to private remote; tests pass on local MySQL; Pint clean | **Partly met.** Local Git done; suite verified on throwaway MySQL 8.4.11; remote (A-2) and local DB run (A-1) pending. |
| AC-1.8 | Owner sign-off recorded | **Pending** |

---

## 9. Next recommended action

1. **Owner, about 5 minutes:**
   * Run A-1: `bash docs/operations/scripts/setup-local-mysql.sh`, then `php artisan test --compact`. Expect 9 passed.
   * Choose the repository owner (D-31) and run A-2 (`../operations/LOCAL_DEVELOPMENT.md` §5).
2. **Owner to client:** send `CLIENT_INPUTS_REQUIRED.md` and the systems questionnaire (`EXISTING_SYSTEMS_ASSESSMENT.md` §2). Lead with the logo files, the location list, the first-location spreadsheet and the systems answers.
3. **Owner:** review the v1.0-rc documents and decide D-24, D-25, D-32, D-34 and D-35.
4. **Then (project team):**
   * run the client's inventory through the validation rules
   * close M-1…M-6
   * fold the answers into v1.0
   * present Phase 1 for approval

**No Phase 2 or later implementation will start before Phase 1 is approved.**
