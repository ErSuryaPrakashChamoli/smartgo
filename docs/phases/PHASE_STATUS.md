# SmartGro — Phase Status Register

| | |
|---|---|
| Last updated | 2026-10-24 |
| Rule | Only the owner can set APPROVED. COMPLETE requires APPROVED plus handover with no open issues for that phase. |

## Summary

| # | Phase | Status | Blocked by / waiting on |
|---|---|---|---|
| 0 | Discovery, audit, requirements clarification | **READY FOR REVIEW** | owner review |
| 1 | PRD, IA, architecture, database design, acceptance criteria | **IN PROGRESS** (drafts ready for review) | P1 decisions (D-01…D-06, D-11, D-12, D-17, D-19, D-22…D-26), inventory sample |
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
| Status | **READY FOR REVIEW** |
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
| Status | **IN PROGRESS** (all drafts written and ready for review; cannot be finalised without owner decisions) |
| Scope | PRD, sitemap and journeys, role matrix, business rules, ERD, API boundaries, integration contracts, ADRs, test strategy, phase-level acceptance criteria |
| Dependencies | Phase 0 review; P1 decisions |
| Deliverables (drafted 2026-10-24) | `docs/requirements/PRD.md` · `INFORMATION_ARCHITECTURE.md` · `BUSINESS_RULES.md` · `docs/architecture/SYSTEM_ARCHITECTURE.md` · `DATABASE_ERD.md` · `INTEGRATION_CONTRACTS.md` · `docs/security/SECURITY_MODEL.md` · `docs/testing/TEST_STRATEGY.md` · `docs/design/DESIGN_SYSTEM.md` (foundation only) · `docs/operations/DEPLOYMENT_RUNBOOK.md` (draft) · `docs/decisions/ADR_INDEX.md` + ADR-0001…0011 (all PROPOSED) |
| Acceptance criteria | `PROJECT_MASTER_PLAN.md` §2, Phase 1 (5 criteria): **none met yet**, as all depend on owner input |
| Tests and results | n/a (design phase) |
| Known issues | Open modelling questions M-1…M-6 (ERD §11); assumptions A-1…A-5 (PRD §7) unconfirmed |
| Decisions requiring approval | D-01…D-06, D-11, D-12, D-17, D-19, D-22…D-26; ADR-0001…0011 |
| Next phase | Phase 2 and Phase 3 (in parallel) |

## Phases 2–15
Scope, deliverables and acceptance criteria are defined in `PROJECT_MASTER_PLAN.md` §2. Each phase gets a full entry here when it starts.

---

## Change log

| Date | Change |
|---|---|
| 2026-10-24 | Register created. Phase 0 → READY FOR REVIEW. Phase 1 → IN PROGRESS with drafts. |
