# SmartGro — Phase Status Register

| | |
|---|---|
| Last updated | 2026-10-24 |
| Rule | Only the owner can set APPROVED. COMPLETE requires APPROVED plus handover with no open issues for that phase. |

## Summary

| # | Phase | Status | Blocked by / waiting on |
|---|---|---|---|
| 0 | Discovery, audit, requirements clarification | **APPROVED in principle** (project owner, 2026-10-24) | — (open items carried into Phase 1) |
| 1 | PRD, IA, architecture, database design, acceptance criteria | **IN PROGRESS.** Documents at v1.0-rc, ready for owner review. | client inputs D-01, D-02, D-03, D-05, D-06, D-16, D-19, D-22, D-33 and the first-location inventory; owner approvals D-24, D-25, D-31, D-32, D-34, D-35; owner action A-1 |
| 2 | Brand system, UX/UI, design system | NOT STARTED | Phase 1 approval; **logo (D-11)** |
| 3 | Foundation, auth, permissions, security | NOT STARTED | Phase 1 approval; D-23, D-24, D-25, D-26 |
| 4 | CMS, page builder, media, publishing | NOT STARTED | Phases 2, 3 |
| 5 | Inventory, pricing, availability | NOT STARTED | Phase 3; D-02…D-06 |
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
| Known issues | Not a Git repository (R-2); app on SQLite while MySQL is available (R-8); Redis, `phpredis` and `bcmath` missing; no brand assets found; IWG sites blocked automated research (snippet-only findings) |
| Decisions requiring approval | Approve Phase 0; then the P1 items in the decision register (report §5.2) |
| Next phase | Phase 1 finalisation |

## Phase 1: Product requirements, IA, system architecture, database design, acceptance criteria

| Field | Detail |
|---|---|
| Status | **IN PROGRESS.** All documents at v1.0-rc and ready for owner review. Cannot close until client inputs arrive (owner instruction: keep Phase 1 open until business decisions and architecture documents are reviewed and finalised). |
| Scope | PRD, sitemap and journeys, role matrix, business rules, ERD, system architecture, API contracts, integration inventory, existing-systems assessment, security model, test strategy, design system foundation, deployment plan, inventory import, decision register, phase acceptance criteria; safe local setup (Git, MySQL test configuration) |
| Dependencies | Phase 0 (approved in principle); client inputs; owner approvals |
| Deliverables (v1.0-rc, 2026-10-24) | **Requirements:** `PRD.md` · `INFORMATION_ARCHITECTURE.md` · `BUSINESS_RULES.md` · `CLIENT_INPUTS_REQUIRED.md` · `INVENTORY_IMPORT_SPEC.md` (+ template SG-INV-1.0 + synthetic fixtures). **Architecture:** `SYSTEM_ARCHITECTURE.md` · `DATABASE_ERD.md` · `API_CONTRACTS.md` · `INTEGRATION_CONTRACTS.md` · `EXISTING_SYSTEMS_ASSESSMENT.md`. **Other:** `SECURITY_MODEL.md` · `TEST_STRATEGY.md` · `DESIGN_SYSTEM.md` (foundation) · `DEPLOYMENT_RUNBOOK.md` (plan) · `LOCAL_DEVELOPMENT.md` · `DECISION_REGISTER.md` · ADR-0001…0012 (0001/0002 ACCEPTED) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, AC-1.1…AC-1.8. Status in `PHASE_1_PROGRESS_REPORT.md` §8. |
| Tests and results | 2026-10-24: 9/9 passed ×3 runs on a throwaway MySQL 8.4.11 (2 examples + 7 concurrency-harness tests); `*_testing` guard verified; Pint clean. Pending: the same run on the local DB created by owner action A-1. |
| Evidence | Git history (`git log`); test outputs (TEST_STRATEGY §7); workbook structure read-back and LibreOffice render (INVENTORY_IMPORT_SPEC §7); contrast calculations (DESIGN_SYSTEM §3) |
| Known issues | Logo file not found (D-11b). No remote repository yet (R-11). Local dev `.env` still on SQLite until A-1. M-1…M-6 open. |
| Decisions requiring approval | Owner: D-24, D-25, D-31, D-32, D-34, D-35. Client: D-01, D-02, D-03, D-05, D-06, D-11b, D-16, D-19, D-22, D-33. |
| Next phase | Phase 2 (needs the logo) and Phase 3 (needs D-24, D-25), in parallel, **only after Phase 1 approval** |

## Phases 2–15
Scope, deliverables and acceptance criteria are defined in `PROJECT_MASTER_PLAN.md` §2. Each phase gets a full entry here when it starts.

---

## Change log

| Date | Change |
|---|---|
| 2026-10-24 | Register created. Phase 0 → READY FOR REVIEW. Phase 1 → IN PROGRESS with drafts. |
| 2026-10-24 | Phase 0 → APPROVED in principle (owner). Git initialised (D-23). ADR-0001/0002 accepted. Provisional palette approved (D-11a). MySQL test configuration and concurrency harness added and verified. Phase 1 documents → v1.0-rc. Decision register created. |
