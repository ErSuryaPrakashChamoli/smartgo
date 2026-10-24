# SmartGro — Decision and Risk Register

| | |
|---|---|
| Status | **Living document**; supersedes the snapshot in `../phases/PHASE_0_DISCOVERY_REPORT.md` §5 |
| Last updated | 2026-10-24 |

**Who decides**
* **Owner:** the project owner, who approves phases, architecture, process and scope.
* **Client:** SmartGro, which supplies business facts, policies, assets and accounts.

**Classification**
* **Resolved:** decided, with evidence.
* **Requires owner approval:** needed to close Phase 1 or to start the next approved step.
* **Requires client input:** needed to close Phase 1 or to start Phase 2/3.
* **Deferred to Phase N:** not needed until that phase gate; it will be raised then.

A recommended default is **never** treated as an approved business fact. It becomes one only when the decider confirms it here.

---

## 1. Decisions

| ID | Decision | Who | Classification | Current state / evidence | Recommended answer | Needed by |
|---|---|---|---|---|---|---|
| D-01 | Company profile, market, legal entity | Client | **Requires client input** | Unknown. Assumption A-1 (India/INR/English) is unconfirmed. | confirm India/INR/Asia-Kolkata, or state the actual markets | Phase 1 |
| D-02 | Actual locations and canonical city slugs | Client | **Requires client input** | Unknown. Import template SG-INV-1.0 is ready to receive data. | provide the location list; first location via the template | Phase 1 |
| D-03 | Product catalogue | Client | **Requires client input** | Unknown. Space types in the template are marked provisional. | list only products actually sold | Phase 1 |
| D-04 | Pricing rules and public price display | Client | **Deferred to Phase 5** | The ERD price model is generic and does not depend on the answer | "from" prices for flexible products, "+ GST" display (CLIENT_INPUTS C-3.4/3.5) | Phase 5 |
| D-05 | Inventory granularity (per-seat dedicated desks, pooled hot desks) | Client | **Requires client input** | ERD and template support both | per-seat dedicated desks; pooled hot desks | Phase 1 |
| D-06 | Existing systems and source of truth | Client | **Requires client input** (decision pending) | Assessment and questionnaire prepared: `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` | SmartGro master for inventory/availability/pricing/tours; one booking engine only; accounting stays external (§4 there) | Phase 5 gate (Phase 1 closes with an answer or an owner-approved deferral) |
| D-07 | Online booking scope at launch | Client | **Deferred to Phase 8** | API contract §2.2/§3.3 is conditional on this | live tour scheduling at launch; instant room booking only with payments | Phase 8 |
| D-08 | Payment gateway; GST invoice ownership | Client | **Deferred to Phase 8** | — | decide with finance; webhooks authoritative | Phase 8 |
| D-09 | Sales process, assignment, SLA | Client | **Deferred to Phase 9** | — | ERD stages; 15-min first response | Phase 9 |
| D-10 | Email / WhatsApp / SMS providers | Client | **Deferred to Phase 8/10** | — | transactional email at launch; WhatsApp once the number is verified | Phase 8 |
| D-11a | Provisional brand palette | Owner | **Resolved** (2026-10-24) | Approved by the owner as **provisional**. Action Green `#287A15` verified: 5.03:1 on Warm White, 5.41:1 on white (WCAG AA). Smart Green `#2E8B18` kept for the logo and decorative/large elements only. To be re-checked when the logo is sampled. | — | — |
| D-11b | Original SmartGro logo files (vector master) | Client | **Requires client input** | **Not found** in the project environment or home directory (searched 2026-10-24). Never to be recreated. | supply SVG/AI/EPS/PDF + PNG variants and guidelines | Phase 2 |
| D-12 | Photography (existing, rights, shoots) | Client | **Deferred to Phase 6** | Design can proceed with clearly marked placeholders; no AI imagery is ever presented as real | professional shoot per open location | Phase 6 |
| D-13 | Floor plans and 3D capture | Client | **Deferred to Phase 7** | — | pilot one flagship location with a measured scan | Phase 7 |
| D-14 | Hosting, DNS, account ownership | Client + Owner | **Deferred to Phase 3** | — | client-owned accounts; managed Laravel hosting + Cloudflare; India region | Phase 3 (staging) |
| D-15 | Legal policies, DPDP responsibilities, retention | Client (counsel) | **Deferred to Phase 6** | — | counsel to provide | Phase 6 |
| D-16 | Website languages | Client | **Requires client input** | Affects the content model (ERD M-6) | English only | Phase 1 |
| D-17 | Content approval workflow | Client | **Deferred to Phase 4** | — | single approver; no self-approval | Phase 4 |
| D-18 | Analytics, ads, consent, maps | Client | **Deferred to Phase 6** | — | GA4 + GTM with consent mode | Phase 6 |
| D-19 | Existing website and URLs to preserve | Client | **Requires client input** | Affects IA and redirects | if a site exists, crawl and map 301s | Phase 1 |
| D-20 | Customer/member portal | Client + Owner | **Deferred to Phase 8** | — | none at launch; signed self-service links | Phase 8 |
| D-21 | AI concierge scope and provider | Owner + Client | **Deferred to Phase 13** | — | defer | Phase 13 |
| D-22 | Brand spelling and primary domain | Client | **Requires client input** | "SmartGro" per brief; the repository folder is named `smartGrow` | "SmartGro" | Phase 2 |
| D-23 | Git version control | Owner | **Resolved** (2026-10-24) | Repository initialised on `main`. Files reviewed for secrets, credentials, customer data and generated artifacts before the first commit. Commit history in §3. | — | — |
| D-24 | New dependencies (`SYSTEM_ARCHITECTURE.md` §9 + `openspout/openspout` as a direct dependency) | Owner | **Requires owner approval** | Compatibility re-verified 2026-10-24. Nothing installed. | approve the list as proposed | Phase 3 |
| D-25 | Code layout (domain sub-namespaces inside standard Laravel folders) | Owner | **Requires owner approval** | `SYSTEM_ARCHITECTURE.md` §3.1 | approve as proposed | Phase 3 |
| D-26 | Local MySQL for dev/test; Redis locally | Owner | **Resolved** for MySQL (configuration in place and verified); Redis **deferred to Phase 3** | `phpunit.xml` forces MySQL + `smartgro_testing`; guard in `tests/TestCase.php`; setup script prepared. **Owner action A-1 pending:** run the script on this machine. | run A-1; install Redis in Phase 3 | now / Phase 3 |
| D-27 | Partner (landlord/broker) and careers pages | Client | **Deferred to Phase 6** | — | omit until confirmed | Phase 6 |
| D-28 | Tour rules | Client | **Deferred to Phase 8** | — | 30-min slots within operating hours | Phase 8 |
| D-29 | Lead data retention | Client (counsel) | **Deferred to Phase 9** | — | counsel to confirm | Phase 9 |
| D-30 | Back-office path and access restriction | Owner | **Deferred to Phase 3** | — | non-default path + Cloudflare Access in production | Phase 3 |
| D-31 | **New:** GitHub repository owner (personal account, company org or client org) and creation | Owner | **Requires owner approval** | GitHub CLI not installed; no SSH key on the machine. Exact steps in `../operations/LOCAL_DEVELOPMENT.md` §5. | a private repo under the organisation that will own the IP under contract | now |
| D-32 | **New:** Release slicing (R1 scope, `PROJECT_MASTER_PLAN.md` §4) | Owner + Client | **Requires owner approval** | — | approve R1 as drafted | Phase 1 |
| D-33 | **New:** Team structure and role/permission matrix confirmation | Client | **Requires client input** | Matrix drafted (`../security/SECURITY_MODEL.md` §3.1) | confirm or adjust roles | Phase 1 |
| D-34 | **New:** ADR-0003…0012 acceptance (ADR-0001/0002 accepted) | Owner | **Requires owner approval** | `ADR_INDEX.md` | accept all as proposed | Phase 1 |
| D-35 | **New:** Inventory import template SG-INV-1.0 and the four-eyes import approval | Owner (+ client ops review) | **Requires owner approval** | `../requirements/INVENTORY_IMPORT_SPEC.md` | approve; client ops to trial the template | Phase 1 |

### Summary

| Classification | Decisions |
|---|---|
| Resolved | D-11a, D-23, D-26 (MySQL part) |
| Requires owner approval | D-24, D-25, D-31, D-32, D-34, D-35 |
| Requires client input | D-01, D-02, D-03, D-05, D-06, D-11b, D-16, D-19, D-22, D-33 |
| Deferred to a later phase | D-04 (5), D-07 (8), D-08 (8), D-09 (9), D-10 (8/10), D-12 (6), D-13 (7), D-14 (3), D-15 (6), D-17 (4), D-18 (6), D-20 (8), D-21 (13), D-26 Redis (3), D-27 (6), D-28 (8), D-29 (9), D-30 (3) |

---

## 2. Risks

| ID | Risk | Classification | State / mitigation evidence |
|---|---|---|---|
| R-1 | An existing system must stay authoritative, changing the inventory model | **Requires client input** (D-06) | Assessment and questionnaire prepared. The ERD keeps `external_ref` and `source`. Phase 5 gate defined. |
| R-2 | No version control | **Resolved** | Git initialised, with a reviewed baseline commit (§3). Residual: no off-machine copy yet (R-11). |
| R-3 | No logo or real photography | **Requires client input** (D-11b, D-12) | Logo searched for and not found. BR-MED rules prevent non-genuine imagery. |
| R-4 | 3D capture cost and device performance | **Deferred to Phase 7** | Budgets (NFR-PERF-4) and the fallback (BR-3D-5) are defined |
| R-5 | Scope breadth versus timeline | **Requires owner approval** (D-32) | Release slicing drafted |
| R-6 | Regulatory: DPDP, SMS DLT, WhatsApp templates | **Deferred to Phase 6/8/10** | Legal review (D-15). Provider registration should start early. |
| R-7 | Double booking / concurrency bugs | **Deferred to Phase 8** (implementation); design-level mitigation **in place** | MySQL harness proves lock-first serialisation and catches double booking (9/9 passing, 3 runs, MySQL 8.4.11). Booking-domain tests T-BK-01…10 must pass before booking code merges. |
| R-8 | Dev/prod parity (SQLite hid locking bugs) | **Resolved** — owner action A-1 pending | Tests forced to MySQL. A `*_testing` guard is verified to refuse the dev DB. |
| R-9 | Content inconsistency (prices, counts) | **Deferred to Phase 4/5** | Price tokens and inventory-driven blocks designed |
| R-10 | Lead spam and bots | **Deferred to Phase 8** | Form contract §3 in `../architecture/API_CONTRACTS.md` |
| R-11 | **New:** the only copy of the repository is on one machine | **Requires owner approval** (D-31) | Push to a private remote as soon as the owner chooses the repository owner |
| R-12 | **New:** slow client inputs keep Phase 1 open and delay Phase 2 | **Requires client input** | `../requirements/CLIENT_INPUTS_REQUIRED.md` is ready to send. Owner-approved deferrals are possible for non-structural items. |
| R-13 | **New:** "snapshot before lock" pitfall: a plain read before taking the lock causes double booking even with a lock | **Resolved at design level** | Reproduced by a negative-control test. ADR-0004 now requires the lock to be the first statement in the transaction. |
| R-14 | **New (2026-10-24):** the local `.env` connects as MySQL **`root`** on a server that also hosts other projects' databases, including personal-data tables such as candidates and employees. A misconfiguration or bug in SmartGro could read or damage another project's data. | **Requires owner approval** | Mitigations already in place: tests are forced to `smartgro_testing` and the `*_testing` guard blocks other databases. **Recommended:** switch to a least-privileged `smartgro` user limited to `smart_go` and `smartgro_testing` by running `SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh`. Never use `root` in staging or production. |

---

## 3. Evidence: Git history (2026-10-24)

| Commit | Content |
|---|---|
| `4778e70` | baseline Laravel 13 skeleton as found (reviewed: no secrets, credentials or customer data; `.env`, SQLite DB, build output, vendor and node_modules ignored) |
| `70d8375` | Phase 0 report and Phase 1 drafts as approved in principle |
| `f30d8fa` | ignore `.env.testing`, `.env.*.local`, personal Claude settings |
| `aa70e23` | MySQL test configuration, `*_testing` guard, concurrency harness, local setup script |
| `6783a14` | inventory import template SG-INV-1.0, validation process, synthetic fixtures |

Later commits are listed by `git log`.

---

## 4. Owner actions

| ID | Action | Instructions |
|---|---|---|
| A-1 | ~~Create the local MySQL databases and user, then run the tests~~ **Done 2026-10-24.** The owner configured `.env` for MySQL (`smart_go`); `smartgro_testing` was created; **9/9 tests pass**. Follow-up for R-14: switch from `root` to a dedicated user. | `SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh` |
| A-2 | Choose the repository owner (D-31), create the private GitHub repository, push | `../operations/LOCAL_DEVELOPMENT.md` §5 |
| A-3 | Send the client the inputs checklist and the systems questionnaire | `../requirements/CLIENT_INPUTS_REQUIRED.md`; `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` §2 |
| A-4 | Approve or amend D-24, D-25, D-32, D-34, D-35 | this register |
