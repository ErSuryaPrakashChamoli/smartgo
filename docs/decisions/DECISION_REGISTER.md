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

## 0. Confirmed owner decisions — 2026-10-24 (round 2)

Source: the owner's brief "Phase 1 — Incorporate Business Owner Decisions", sections 1–8. **Section 9 ("Pricing configuration") onward was cut off and has not been received** (Q-OW-9).

| ID | Confirmed decision | Implications recorded in |
|---|---|---|
| OD-01 | **Brand:** public name **SmartGro**. Tagline **"WORK SMART. GROW TOGETHER."** (confirmed; not to be replaced). The official logo must be used as supplied: no redrawing, reinterpretation or text substitute. | DESIGN_SYSTEM §1; ERD `site_settings` brand group. **The logo file is not in the project (D-11b).** |
| OD-02 | **Market:** India at launch; no international markets without further approval | ERD M-5; PRD §1, §4 |
| OD-03 | **Geography:** target markets in Delhi NCR are Noida, Gurgaon, Delhi, Faridabad and Ghaziabad. **Currently operating: Noida only.** No centre is assumed elsewhere. The platform must support adding properties and cities without code. | BR-PROP-4; IA §2; ERD `cities.is_target_market` |
| OD-04 | **Customer segments (eight):** startups and small businesses; freelancers and independent professionals; SMEs and growing companies; large enterprises and corporates; remote and hybrid employees; consultants and business travellers; companies seeking managed offices; businesses needing virtual offices or registered addresses. **No marketing priority implied.** | ERD `customer_segments`; BR-CRM-1a; PRD §3 |
| OD-05 | **Language:** English only for the initial release | ERD M-6 |
| OD-06 | **Existing operational software: none.** The SmartGro platform is the master for everything operational. | BR-SOT-7; EXISTING_SYSTEMS_ASSESSMENT §7 |
| OD-07 | **Product catalogue (seven categories), admin-manageable:** private cabins / private offices; dedicated desks; hot desks / flexible seating; meeting rooms; conference and training rooms; virtual office / business address; managed offices for enterprises. Each may have its own rules. | ERD §4.1; ADR-0013; BR-PRODCFG |
| OD-08 | **Properties (currently identified, Noida):** A-70, Sector 2 · A-71, Sector 2 · A-23, Sector 3 · H-105, Sector 62. **10 floors in total across the four; allocation not provided.** Full addresses, floor allocation, room-level inventory and capacity are to be entered later. Administrators must be able to add and edit properties, buildings, floors, inventory, status, operating model and property-specific pricing. | ERD §3 and change log; ADR-0014; BR-PROP; Q-OW-1, Q-OW-2 |
| OD-09 | **Operating models:** SmartGro both **owns and operates** and **leases and operates** properties. Recorded explicitly, never inferred. Not a third-party marketplace unless separately confirmed. | ERD `operating_model`; BR-PROP-2; Q-OW-3 |
| OD-10 | **Inventory approaches:** individual tracking (dedicated desks, assigned spaces); pooled capacity (hot desks etc.); hybrid (both within one property); product-specific rules. The lock-before-read invariant is preserved. | ERD §3.2; ADR-0004 lock targets; ADR-0013; BR-AV-3/3a/3b, BR-BK-2a/2b, BR-INV |
| OD-11 | **Existing website:** `https://www.smartgro.space/coworking-space/` (WordPress). URL preservation is needed at launch. | `../research/EXISTING_WEBSITE_AUDIT.md`. **The site shows signs of compromise (R-16).** |

### 0.1 Questions for the owner (concise; each blocks or shapes a specific item)

| ID | Question | Why it matters | Recommendation |
|---|---|---|---|
| Q-OW-1 | How are the **10 floors** split across A-70, A-71, A-23 and H-105, and at which building levels (e.g. ground, 1, 2…)? | floors cannot be created without it (M-7) | provide via the SG-INV-1.1 template, or as a short list |
| Q-OW-2 | Is each address a **separate property** customers see as its own centre, or are any of them (e.g. A-70 and A-71, Sector 2) **one centre spanning two buildings**? | property pages, tours, inventory grouping | the model supports both; please state which |
| Q-OW-3 | Which properties are **owned and operated**, and which **leased and operated**? Could this differ within a property? Do you want lease end and lock-in dates tracked internally (restricted access)? | BR-PROP-2/5; protects against selling beyond a lease | record per property; track lease end dates |
| Q-OW-4 | Use **"Gurgaon"** or **"Gurugram"**, and **"Delhi"** or **"New Delhi"**, in public names and URLs? | permanent URLs and SEO | your preferred customer-facing names; the other spelling redirects |
| Q-OW-5 | Should target cities without a property appear on the website at all (hidden, or "coming soon" with a waitlist)? | avoids implying centres that do not exist | hidden until a property is confirmed; optional waitlist page only with your approval |
| Q-OW-6 | May content from the existing website (copy, photos) be reused after review? Should `/coworking-space/` map to a coworking hub page or to the hot-desk page? | migration and redirects | review first; map `/coworking-space/` to a coworking hub |
| Q-OW-7 | Do any desk zones need to be **hybrid within the zone** (some seats individually assigned, the rest a shared pool)? | adds a fourth inventory mode | not needed unless you confirm |
| Q-OW-8 | May the system call the entity **"Property"** throughout the back office and code (renaming "location")? | consistent language for administrators | yes; rename before Phase 5 |
| Q-OW-9 | Please resend **section 9 onward** of the brief (pricing configuration and anything after it). | pricing, payment and cancellation rules | — |
| Q-OW-10 | **Logo file:** please place the original logo in the project at `resources/brand/source/` (SVG preferred; otherwise AI/EPS/PDF, plus a high-resolution PNG). The image referred to in the brief is not available to this session or in the project. | Phase 2 cannot start without it | — |

---

## 1. Decisions

| ID | Decision | Who | Classification | Current state / evidence | Recommended answer | Needed by |
|---|---|---|---|---|---|---|
| D-01 | Company profile, market, legal entity | Owner + Client | **Requires client input** (partly answered) | **Market confirmed:** India; Delhi NCR target markets; operating in Noida (OD-02, OD-03). Still missing: legal entity name, CIN, GSTIN, registered address, positioning statement. | supply legal details before public launch | Phase 6 (legal details); market resolved |
| D-02 | Properties, floors and canonical city slugs | Owner | **Requires client input** (partly answered) | **Four Noida properties confirmed** (OD-08); 10 floors in total. **Floor allocation (Q-OW-1), property grouping (Q-OW-2), full addresses and inventory outstanding.** City naming: Q-OW-4. | supply via template SG-INV-1.1 | Phase 1 (AC-1.4) |
| D-03 | Product catalogue | Owner | **Resolved** (2026-10-24, OD-07) | Seven admin-manageable categories. Rule **values** (pricing, payment, cancellation, durations) are not yet confirmed (brief §9 not received). | — | — |
| D-04 | Pricing rules and public price display | Client | **Deferred to Phase 5** | The ERD price model is generic and does not depend on the answer | "from" prices for flexible products, "+ GST" display (CLIENT_INPUTS C-3.4/3.5) | Phase 5 |
| D-05 | Inventory granularity | Owner | **Resolved** (2026-10-24, OD-10) | Individual (per-seat), pooled and hybrid-within-property are all supported, with product-specific rules. A hybrid *zone* is a separate question (Q-OW-7). | — | — |
| D-06 | Existing systems and source of truth | Owner | **Resolved** (2026-10-24, OD-06) | **No existing operational software.** SmartGro is the master for inventory, availability, pricing, bookings and CRM (Scenario A). The assessment is kept for future integrations. | — | — |
| D-07 | Online booking scope at launch | Client | **Deferred to Phase 8** | API contract §2.2/§3.3 is conditional on this | live tour scheduling at launch; instant room booking only with payments | Phase 8 |
| D-08 | Payment gateway; GST invoice ownership | Client | **Deferred to Phase 8** | — | decide with finance; webhooks authoritative | Phase 8 |
| D-09 | Sales process, assignment, SLA | Client | **Deferred to Phase 9** | — | ERD stages; 15-min first response | Phase 9 |
| D-10 | Email / WhatsApp / SMS providers | Client | **Deferred to Phase 8/10** | — | transactional email at launch; WhatsApp once the number is verified | Phase 8 |
| D-11a | Provisional brand palette | Owner | **Resolved** (2026-10-24) | Approved by the owner as **provisional**. Action Green `#287A15` verified: 5.03:1 on Warm White, 5.41:1 on white (WCAG AA). Smart Green `#2E8B18` kept for the logo and decorative/large elements only. To be re-checked when the logo is sampled. | — | — |
| D-11b | Original SmartGro logo files (vector master) | Owner | **Requires client input** | The owner states the logo was supplied as an image in the conversation. **That image is not available to this working session, and no logo file exists in the project** (checked 2026-10-24). Never recreated. | place the original at `resources/brand/source/` (Q-OW-10) | Phase 2 |
| D-12 | Photography (existing, rights, shoots) | Client | **Deferred to Phase 6** | Design can proceed with clearly marked placeholders; no AI imagery is ever presented as real | professional shoot per open location | Phase 6 |
| D-13 | Floor plans and 3D capture | Client | **Deferred to Phase 7** | — | pilot one flagship location with a measured scan | Phase 7 |
| D-14 | Hosting, DNS, account ownership | Client + Owner | **Deferred to Phase 3** | — | client-owned accounts; managed Laravel hosting + Cloudflare; India region | Phase 3 (staging) |
| D-15 | Legal policies, DPDP responsibilities, retention | Client (counsel) | **Deferred to Phase 6** | — | counsel to provide | Phase 6 |
| D-16 | Website languages | Owner | **Resolved** (2026-10-24, OD-05) | English only for the initial release | — | — |
| D-17 | Content approval workflow | Client | **Deferred to Phase 4** | — | single approver; no self-approval | Phase 4 |
| D-18 | Analytics, ads, consent, maps | Client | **Deferred to Phase 6** | — | GA4 + GTM with consent mode | Phase 6 |
| D-19 | Existing website and URLs to preserve | Owner | **Resolved** (2026-10-24, OD-11) | Existing WordPress site at smartgro.space. 57 legitimate URLs inventoried (`../research/EXISTING_WEBSITE_AUDIT.md`). The final redirect map is a Phase 11 task. **The site shows signs of compromise (R-16).** | 301 legitimate URLs; 410 `/pin/*` | Phase 11 (map) |
| D-20 | Customer/member portal | Client + Owner | **Deferred to Phase 8** | — | none at launch; signed self-service links | Phase 8 |
| D-21 | AI concierge scope and provider | Owner + Client | **Deferred to Phase 13** | — | defer | Phase 13 |
| D-22 | Brand name and primary domain | Owner | **Resolved** (2026-10-24, OD-01) | **SmartGro**; domain **smartgro.space**; tagline "WORK SMART. GROW TOGETHER." confirmed | — | — |
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
| D-35 | **New:** Inventory import template SG-INV-1.1 (supersedes 1.0) and the four-eyes import approval | Owner (+ client ops review) | **Requires owner approval** | `../requirements/INVENTORY_IMPORT_SPEC.md` | approve; client ops to trial the template | Phase 1 |

### Summary

| Classification | Decisions |
|---|---|
| Resolved | D-03, D-05, D-06, D-11a, D-16, D-19, D-22, D-23, D-26 (MySQL part) |
| Requires owner approval | D-24, D-25, D-31, D-32, D-34 (now ADR-0003…0014), D-35 (now SG-INV-1.1), Q-OW-8 |
| Requires client input | D-01 (legal details), D-02 (floors, grouping, addresses, inventory), D-11b (logo file), D-33 (team/roles), Q-OW-1…7, Q-OW-9, Q-OW-10 |
| Deferred to a later phase | D-04 (5), D-07 (8), D-08 (8), D-09 (9), D-10 (8/10), D-12 (6), D-13 (7), D-14 (3), D-15 (6), D-17 (4), D-18 (6), D-20 (8), D-21 (13), D-26 Redis (3), D-27 (6), D-28 (8), D-29 (9), D-30 (3) |

---

## 2. Risks

| ID | Risk | Classification | State / mitigation evidence |
|---|---|---|---|
| R-1 | An existing system must stay authoritative, changing the inventory model | **Resolved** (OD-06: no existing software) | SmartGro is the master. `external_*` columns kept for future integrations. |
| R-2 | No version control | **Resolved** | Git initialised, with a reviewed baseline commit (§3). Residual: no off-machine copy yet (R-11). |
| R-3 | No logo file or real photography | **Requires client input** (D-11b, D-12) | Logo not in the project, and not available to the working session. BR-MED rules prevent non-genuine imagery. |
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
| R-15 | **New:** floor allocation, grouping and full addresses of the four properties are unknown, so the ERD cannot be validated against real data | **Requires client input** (Q-OW-1, Q-OW-2) | Nothing is created by assumption (BR-PROP-1). Template SG-INV-1.1 is ready. |
| R-16 | **New:** the existing WordPress site at smartgro.space shows signs of compromise: 1,038 injected `/pin/` spam URLs, cloaked to serve Japanese spam to Googlebot | **Requires owner action now** (outside this project) | Evidence and recommended actions in `../research/EXISTING_WEBSITE_AUDIT.md` §2. The new platform must not reuse any files from it, and must return 410 for `/pin/*`. |
| R-17 | **New:** loss of search visibility at cut-over from WordPress | **Deferred to Phase 11** | 57-URL inventory captured; draft redirect map; final map from a fresh crawl and Search Console data |
| R-18 | **New:** selling long-term agreements beyond a leased property's lease end | **Requires client input** (Q-OW-3) | BR-PROP-5 warning, if lease dates are tracked |
| R-19 | **New:** configuration breadth (7 categories × per-property rules) increases build and test effort | **Requires owner approval** (D-32 release slicing) | Typed configuration (ADR-0013); categories can launch in stages |

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
| A-5 | **New (2026-10-24):** enable Filament's AI guidance skill. Filament 5.10.3 ships `filament-development`, but `boost.json` does not enable it, so AI-assisted work lacks Filament-specific rules. Per Filament's docs, `boost:update` alone does not add it. | run `php artisan boost:install` (interactive), choose **Agent Skills** → **filament/filament (skills)**, then review the changes to `CLAUDE.md` and `.claude/skills` before committing |
