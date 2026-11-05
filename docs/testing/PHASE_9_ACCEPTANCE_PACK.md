# SmartGro — Phase 9 Implementation Record and Acceptance Pack (CRM)

| | |
|---|---|
| Status | **In progress, not accepted** (started 2026-10-31, OD-159). Built locally, uncommitted. Packages P9-A (§3) and P9-B (§3.2, OD-160) done; P9-C and P9-D planned (§2). |
| Authority | OD-159: the owner's "continue to new phase", then the answer "Phase 9 CRM (Recommended)", with Phase 14 kept in progress. OD-160: the owner's P9-B instruction. |
| Scope sources | `../../PROJECT_MASTER_PLAN.md` §2 Phase 9 (AC-9.1 to AC-9.3); `../requirements/PRD.md` FR-CRM-1 to 6; `../requirements/BUSINESS_RULES.md` BR-CRM-1 to 7; `../architecture/DATABASE_ERD.md` §6; `../requirements/INFORMATION_ARCHITECTURE.md` UJ-10; `TRACEABILITY_MATRIX.md` |
| Databases | Test writes on `smartgro_testing` only, after checking the effective connection. `smart_go` and `smartgro_preview` are only read. No request to the owner's server (port 8000 or 8001). |
| Related | `../decisions/DECISION_REGISTER.md` OD-159; `../phases/PHASE_STATUS.md`; earlier CRM work: `PHASE_5_ACCEPTANCE_PACK.md` (inbox, status, reasons, export), OD-129 and OD-130 (tours, one-tap call and email, follow-ups) |

**Tests and automated browser checks show what the code does on the test database. They do not replace the moderated UJ-10 check or the owner's acceptance.**

## 1. Where each requirement stands

| Requirement | Before Phase 9 | Phase 9 |
|---|---|---|
| FR-CRM-1: inbox, filters, ownership, status, SLA timers, duplicate detection | partial: inbox, filters, ownership, status and scope built; contacts matched on email or phone at intake | **duplicates for staff: done (P9-A)**; first-response timers: P9-C |
| FR-CRM-2: assignment rules (round-robin, fixed, least-loaded) | not started (manual assignment only) | **built (P9-B, §3.2)**; least loaded cannot be switched on until A-34; A-33 and A-35 open |
| FR-CRM-3: activities with reminders | partial: call and email recorded in one tap; follow-ups and the "due by today" filter; no WhatsApp (D-10), no messages sent (O-1) | unchanged until D-10 and O-1 |
| FR-CRM-4: pipeline, deals, proposed spaces; won deals create allocations | not started | P9-D |
| FR-CRM-5: managed lost and disqualification reasons | built; the lists are empty (O-7) | unchanged |
| FR-CRM-6: export, Super Admin only, audited | built | unchanged |
| AC-9.2: UJ-10 by a Sales/CRM Executive | blocked: notifications (O-1) and the moderated check | after P9-B to P9-D |
| AC-9.3: scoping proven by tests | `EnquiryScopeTest` | every package adds scope tests |

## 2. Plan

Each package is built locally and uncommitted, with feature tests, mutation checks of its decisions and the browser kit. None invents business values:
- The Super Admin configures assignment rules, the first-response target, and pipeline stage names and probabilities, or they wait for D-09.
- Notifications stay blocked (OD-111 d, O-1): alerts appear only inside the back office.
- New tables are guarded, so the owner's server on `smart_go`, which does not have them, keeps working.

| Package | Content | Business values needed | Order |
|---|---|---|---|
| **P9-A** duplicates (FR-CRM-1, BR-CRM-2) | §3 | none | **done** |
| **P9-B** assignment rules (FR-CRM-2, BR-CRM-3), **done** (§3.2) | `assignment_rules` (ERD §6): criteria (city, location, workspace type, customer segment, team size, source), strategy (round-robin, fixed person, least loaded), priority, active. The first matching active rule assigns a new enquiry; with none, it stays in the unassigned queue (today's behaviour). Deterministic, with a test per strategy; screens for the Super Admin. | which rules exist (D-09); none are created by default; A-33 to A-35 | **done** |
| **P9-C** first-response timers (FR-CRM-1, BR-CRM-4) | time waiting since received, within the location's opening hours; due and overdue once a target is set; an inbox filter and count. The 15-minute default is marked [DEFAULT] and unresolved (D-09), so the target is a setting left empty until the owner sets it. | the target and business hours rule (D-09) | next |
| **P9-D** pipeline and deals (FR-CRM-4, BR-CRM-7) | `pipeline_stages`, `deals`, `deal_space` (ERD §6): a deal from an enquiry, proposed spaces from canonical inventory with their live availability, stage moves, and a won deal creating allocations through the inventory actions. | stage names and probabilities (D-09; the Super Admin configures them, OD-36) | last |

## 3. P9-A: duplicate detection for staff (FR-CRM-1, BR-CRM-2), OD-159

**Already there.** The website reuses an existing contact with the same normalised email or E.164 phone, and never overwrites it (`VisitorIntake::contact`).

**Added:**

| Part | Detail |
|---|---|
| Enquiry page | **"Other enquiries from this contact":** the ones the user may see (BR-CRM-6), each linked, with subject, status and date. Others are counted only ("1 more about location you do not work on."). **"Not on the contact record yet":** details typed on this enquiry that the contact lacks or has differently. **"Possibly the same visitor":** another contact that already has this enquiry's email or phone. |
| Update the contact | Action "Update the contact": the staff member ticks which differing details to copy (BR-CRM-2: never without staff confirmation). `UpdateContactFromLead`: locks the enquiry and the contact; needs permission to work on the enquiry within its location scope (deactivated accounts and Property Managers are refused); copies only chosen fields that differ; refuses an email or phone another contact already has; audits the field names, never the values. |
| Inbox | "Repeat contact: N other enquiries" under the visitor's name. |
| Not built | Merging two contacts: no rule asks for it. Staff see "Possibly the same visitor" and decide. |

**Tests:**
- `tests/Feature/Actions/Crm/UpdateContactFromLeadTest.php`: the differences; copying only chosen fields; an audit without values; refusals for non-differing, empty and unknown fields and for an identity another contact has; refusals for another location's sales, the Property Manager and a deactivated account.
- `tests/Feature/Filament/Clusters/Sales/EnquiryDuplicatesTest.php`: links only within scope; differences and the other contact shown; the action copies a detail, then disappears; hidden for read-only staff and matching details; the repeat marker in the inbox.

Run with the existing `EnquiryScreensTest` and `LeadWorkflowTest`: **28 passed, 200 assertions**, exit 0, `smartgro_testing`.

**Mutation checks**, each caught and restored:
- no identity check;
- no permission check;
- equal values counted as differences;
- other enquiries listed without the user's scope.

Browser: §4 (`CHECKS=admin` exit 0).

## 3.2 P9-B: assignment rules (FR-CRM-2, BR-CRM-3), OD-160

Full record: `P9B_ASSIGNMENT_RULES_REPORT_2026-10-31.md`.

| Part | Detail |
|---|---|
| Rules | Sales → **Assignment rules**, Super Admin only (`leads.assignment.manage`, OD-36). A rule has a name, a unique priority (lowest first), a strategy, criteria (cities, locations, workspace types, segments, team sizes, sources; empty means any) and its people in order. It starts off, is switched on and off explicitly (`SetAssignmentRuleActive`: only when it can assign), is changed only while off (`SaveAssignmentRule`), and is never deleted. None exists by default. |
| Assignment | `AssignNewLead`, after each new enquiry or tour-request enquiry is stored: the first matching active rule decides; eligible means as for a manual assignment (active, `leads.manage` within the enquiry's location scope). Round-robin: the next eligible person after the last turn. Fixed: the one person. Least loaded: fewest enquiries in the workload statuses, a tie to the first in order; **cannot be switched on until A-34**. No match, or nobody eligible, leaves the enquiry unassigned (A-33). One decision per enquiry (`lead_assignments`), shown on the enquiry and on the rule's page. Locks: the enquiry, then the active rules, before any read. Audited without personal data; nothing is sent (O-1). |
| Open | A-33 (falling through to the next rule), A-34 (workload), A-35 (round-robin start and reset); D-09 (which rules); O-1 (the notice for unassigned enquiries); defects D1 (a rule's address on a database without the tables), D2 and D3 (accessibility), awaiting A-36 and A-37 (OD-162) |

## 4. Evidence

| Check | Result |
|---|---|
| Pest, §3 | 28 passed |
| Browser kit `CHECKS=admin` (`evidence/phase9/2026-10-31-od159/admin/`, `smartgro_testing`) | **exit 0**: 74 screen checks and 3 viewer checks, 0 failures; the enquiry list and the enquiry page at 360 and 1440 px, 0 axe violations. The fixture enquiry has no other enquiries from its contact, so the new entries are covered by the Pest screen tests. |
| P9-B tests (`P9B_ASSIGNMENT_RULES_REPORT_2026-10-31.md` §5) | new files 22 + 18 + 7 + 2 concurrency + 1 tour test, all passed; regression `tests/Feature/Actions` 410, Filament/Http/Services/Views 714, the rest 241, Infrastructure 31, each exit 0. The nine mutation checks were recorded as caught, but their console output was not retained (historical; closure report §6) |
| Browser kit `CHECKS=admin` (`evidence/phase9/2026-10-31-od160/admin/`) | **exit 0**: 80 screen checks, 0 failures; the new screens 200 (Super Admin) and 403 (sales), 0 axe violations. A first run hit Filament's stale component cache (404, report §4a) |
| P9-B acceptance audit (`P9B_ACCEPTANCE_AUDIT_REPORT_2026-11-01.md`, OD-161) | read-only. **Conditionally accept** the decided scope (least loaded, A-33 and A-35 excluded); deployment **not ready**; evidence gaps listed |
| P9-B evidence closure (`P9B_ACCEPTANCE_EVIDENCE_CLOSURE_REPORT_2026-11-01.md`, OD-162; `evidence/phase9/2026-11-01-p9b-closure/`) | new tests for direct-address refusal, nothing sent, the missing tables, the lock order and `config/filament.php`: 72 tests, 71 passed and 1 skipped (known defect **D1**); access and notification regression 163, P9-A 14, enquiry and tour 40, all passed. Kit `CHECKS=admin` **exit 1**: a rule's page, form, keyboard switching and refusals pass; axe finds **D2** (target size of multi-select "Remove" buttons) and **D3** (primary-button hover contrast). These are uncached source behaviour, not a target server's runtime |

## 5. Outstanding

| Item | Needs |
|---|---|
| D-09 | which assignment rules exist; the first-response target and its business hours; pipeline stages: values only |
| A-33 to A-35 | assignment rules: falling through, workload, round-robin start and reset (P9-B) |
| A-36, A-37 | authorise the fixes for P9-B's defects D1 and D2/D3 (OD-162) |
| D-10, O-1 | WhatsApp and notifications (FR-CRM-3, BR-CRM-3 and BR-CRM-4 alerts, UJ-10) |
| O-7 | the lists of closing reasons |
| AC-9.2 | a moderated UJ-10 check by a Sales/CRM Executive, after notifications |
| Owner acceptance | of each package and of Phase 9 |
