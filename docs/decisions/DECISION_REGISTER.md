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
| OD-03 | **Geography:** target markets in Delhi NCR are Noida, Gurgaon (now named Gurugram, OD-16), Delhi, Faridabad and Ghaziabad. **Currently operating: Noida only.** No centre is assumed elsewhere. The platform must support adding properties and cities without code. | BR-PROP-4; IA §2; ERD `cities.is_target_market` |
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
| Q-OW-1 | **→ still open (re-asked in §0.3)** How are the **10 floors** split across A-70, A-71, A-23 and H-105, and at which building levels (e.g. ground, 1, 2…)? | floors cannot be created without it (M-7) | provide via the SG-INV-1.1 template, or as a short list |
| Q-OW-2 | **→ answered: OD-13** Is each address a **separate property** customers see as its own centre, or are any of them (e.g. A-70 and A-71, Sector 2) **one centre spanning two buildings**? | property pages, tours, inventory grouping | the model supports both; please state which |
| Q-OW-3 | **→ answered: OD-14** Which properties are **owned and operated**, and which **leased and operated**? Could this differ within a property? Do you want lease end and lock-in dates tracked internally (restricted access)? | BR-PROP-2/5; protects against selling beyond a lease | record per property; track lease end dates |
| Q-OW-4 | **→ answered: OD-16** Use **"Gurgaon"** or **"Gurugram"**, and **"Delhi"** or **"New Delhi"**, in public names and URLs? | permanent URLs and SEO | your preferred customer-facing names; the other spelling redirects |
| Q-OW-5 | **→ answered: OD-18** (follow-up Q-OW-12) Should target cities without a property appear on the website at all (hidden, or "coming soon" with a waitlist)? | avoids implying centres that do not exist | hidden until a property is confirmed; optional waitlist page only with your approval |
| Q-OW-6 | **→ answered: OD-19** (redirects: Q-OW-11) May content from the existing website (copy, photos) be reused after review? Should `/coworking-space/` map to a coworking hub page or to the hot-desk page? | migration and redirects | review first; map `/coworking-space/` to a coworking hub |
| Q-OW-7 | **→ answered: OD-15** Do any desk zones need to be **hybrid within the zone** (some seats individually assigned, the rest a shared pool)? | adds a fourth inventory mode | not needed unless you confirm |
| Q-OW-8 | **→ answered: OD-17** May the system call the entity **"Property"** throughout the back office and code (renaming "location")? | consistent language for administrators | yes; rename before Phase 5 |
| Q-OW-9 | **→ still open (re-asked in §0.3)** Please resend **section 9 onward** of the brief (pricing configuration and anything after it). | pricing, payment and cancellation rules | — |
| Q-OW-10 | **→ acknowledged: OD-20** **Logo file:** please place the original logo in the project at `resources/brand/source/` (SVG preferred; otherwise AI/EPS/PDF, plus a high-resolution PNG). The image referred to in the brief is not available to this session or in the project. | Phase 2 cannot start without it | — |

### 0.2 Owner answers — 2026-10-24 (round 3)

Source: the owner's replies to Q-OW-1…10. Where a reply could be read in more than one way, the interpretation is stated so the owner can correct it.

| ID | Confirmed decision | Implications recorded in |
|---|---|---|
| OD-12 | Floors are recorded **per property** (reply to Q-OW-1: "Separate property"). **The split of the 10 floors and their levels was not given**, so Q-OW-1 stays open, re-asked below. | ERD M-7 |
| OD-13 | **Each address is its own centre/property:** A-70 (Sector 2), A-71 (Sector 2), A-23 (Sector 3) and H-105 (Sector 62) are four separate properties. A-70 and A-71 are **not** one centre. | ERD glossary and M-1; ADR-0014 |
| OD-14 | **All four current properties are owned and operated.** The leased-and-operated model stays supported (OD-09) for future properties. Lease-date tracking is **deferred** until a leased property exists. | ERD `property_leases` (deferred); BR-PROP-5; R-18 |
| OD-15 | **Hybrid zones are required:** a desk zone can have some individually assigned seats while the rest form a shared pool. | ERD §3.2 mode `hybrid`; BR-AV-3d; BR-BK-2c; ADR-0004; SG-INV-1.2 |
| OD-16 | **City names follow the latest official names:** Noida, **Gurugram** (not Gurgaon), **Delhi**, Faridabad, Ghaziabad. Interpretation: Delhi has not been renamed; "New Delhi" is a district within Delhi, so the city is "Delhi". The old name "Gurgaon" is kept only as an alias that redirects. | ERD `cities`; IA §1 |
| OD-17 | **The back office and code call the entity "Property"** (interpretation: the "Yes" on the line after Q-OW-8 answers Q-OW-8). The public site keeps "Locations" in navigation and URLs. | ERD C-13 (rename done in all documents) |
| OD-18 | **Cities without a property stay hidden.** A CMS administrator can add a city and switch its visibility on or off with a toggle. | ERD `cities.is_publicly_visible`; BR-PROP-4; FR-CITY-1 |
| OD-19 | **Nothing is taken from the old website.** It was provided for information only: no copy, images, files or data are reused. | BR-CMS-8; `../research/EXISTING_WEBSITE_AUDIT.md` |
| OD-20 | **Logo:** the owner will add the original file later (Q-OW-10 acknowledged). | D-11b remains open until the file is in `resources/brand/source/` |

### 0.3 Remaining owner questions (after round 3)

| ID | Question | Why it matters |
|---|---|---|
| Q-OW-1 (re-asked) | Please give the number of floors for each property, and which levels they are (they total 10). For example: *A-70: Ground, 1st, 2nd · A-71: … · A-23: … · H-105: …* | floors and inventory cannot be created without it; it is needed to validate the ERD (AC-1.4) |
| Q-OW-9 | Please resend section 9 onward of the brief (pricing configuration and anything after it) | pricing, payment and cancellation rules per category and property |
| Q-OW-11 (new) | **→ answered: OD-21 (yes)** At launch, should the old site's addresses (e.g. `/coworking-space/`, the 6 blog-post URLs) **redirect** to the matching new pages? This reuses **no content**; it only keeps Google rankings and old links working. The spam `/pin/…` URLs would return "Gone" either way. | search visibility at cut-over (R-17) |
| Q-OW-12 (new) | **→ answered: OD-22 (coming-soon page)** When a city is switched **visible** before any property opens there, what should its page show: a "coming soon / register interest" page, or only a listing in the city menu? | what a visible city without a property displays (BR-PROP-4) |
### 0.4 Owner answers — 2026-10-24 (round 4)

| ID | Answer | Recorded as |
|---|---|---|
| OD-21 | **Old website URLs redirect at launch: yes.** Legitimate pages and the 6 blog posts get a 301 to their closest new page; the injected `/pin/*` URLs return 410. No content is copied (OD-19 still applies). | Q-OW-11 answered; R-17 → Phase 11 execution |
| OD-22 | **A city switched visible before any property opens shows a "Coming soon — register interest" page.** Interpretation: the owner answered "yes" to an either/or question, and the first option (coming-soon page) is recorded. The owner should correct this if "menu only" was meant. | Q-OW-12 answered; BR-PROP-4; FR-CITY-1 |

**Not answered in round 4 (still open):**
* **Q-OW-1 floors per property.** The reply was "yes", which does not state how the 10 floors are split. The format example in the question ("A-70: Ground, 1st, 2nd") was **illustrative only** and is **not** recorded as fact.
* **Q-OW-9 pricing (brief section 9 onward).** Not resent.
* **Approvals** D-24, D-25, D-31, D-32, D-34, D-35 and team roles D-33. No answer given; nothing is treated as approved.

### 0.5 Options to unblock Phase 1 (P-1, P-2 and P-3 adopted 2026-10-24; see §0.6)

| Option | Effect | Recommendation |
|---|---|---|
| P-1 | **Defer floors and real inventory to data entry.** The owner already said these "can be entered or completed later" (OD-08). AC-1.4 (ERD validated against real inventory) would move to the **Phase 5 entry gate**: the first property's real data is run through SG-INV-1.3 before any inventory code is merged. | **Recommended.** It removes the main Phase 1 blocker without guessing any floors. |
| P-2 | **Pricing:** either resend section 9, or approve that the project team drafts a pricing-configuration proposal (units, display, GST treatment, deposits, cancellation per category) **as proposed defaults for the owner to edit and approve** | resend if available; otherwise P-2 |
| P-3 | **Approve the pending items as recommended in one reply** ("approve all as recommended"), or list exceptions | saves a round-trip; see `../phases/PHASE_1_PROGRESS_REPORT.md` §5 |
### 0.6 Owner approval — 2026-10-24 (round 5): "Approve all as recommended"

Interpretation: this approves every item for which the project team gave a recommendation in its last request. Items that depend on interpretation are marked; the owner can correct any of them.

| ID | Approved | Notes |
|---|---|---|
| OD-23 | **D-24 packages**, installed **phase by phase** (Phase 3: permissions, activity log, Redis client, Horizon; Phase 4: media library, Filament media plugin, S3 adapter; Phase 5: openspout as direct `^4`; Phase 6/11: schema-org, sitemap; Phase 7: three.js) | Nothing is installed until the relevant phase starts, after Phase 1 approval |
| OD-24 | **D-25 code layout** (domain sub-namespaces inside standard Laravel folders) | |
| OD-25 | **D-32 release slicing** (R1 as in `PROJECT_MASTER_PLAN.md` §4) | |
| OD-26 | **D-34: ADR-0003…0014 accepted** | ADR statuses updated |
| OD-27 | **D-35: import template SG-INV-1.2** and the four-eyes import process | |
| OD-28 | ~~D-33: team roles and permission matrix as drafted~~ (interpretation) **superseded by OD-33** (six owner-defined roles) | — |
| OD-29 | **D-31: the repository will be owned by a GitHub organisation, not a personal account** (interpretation of the recommendation) | **The organisation's name is still needed.** GitHub setup stays deferred, as the owner instructed in round 2. |
| OD-30 | **Option P-1: floors and real inventory are entered later** (interpretation; P-1 was marked "recommended") | AC-1.4 moves to the **Phase 5 entry gate**: the first property's real data is run through SG-INV-1.3 before inventory code is merged. Floors are never assumed. |
| OD-31 | **Option P-2: a pricing-configuration proposal is drafted for the owner to edit and approve** (interpretation; section 9 was not resent) | `../requirements/PRICING_CONFIGURATION_PROPOSAL.md`, drafted 2026-10-24. **No prices, rates or terms are set.** |
### 0.7 Owner answers — 2026-10-24 (round 6)

| ID | Answer | Recorded as / effect |
|---|---|---|
| OD-32 | **Floor allocation stays pending.** The owner wrote "If you know the exact floors, leave the allocation pending". The exact floors are **not** known, so under either reading (the owner may have meant "don't know") the allocation stays pending. Floors are entered later (consistent with OD-30). | Q-OW-1 closed as "pending by owner decision"; no floors recorded |
| OD-33 | **Team roles: Super Admin, Operations Manager, Property Manager, Sales/CRM Executive, Marketing Manager, Finance.** These **supersede** the 13 draft roles approved in OD-28. | `../security/SECURITY_MODEL.md` §3.1 rewritten for six roles, with a mapping of the former roles; ERD `property_manager_id`; template **SG-INV-1.3** (column `centre_manager_email` → `property_manager_email`; the D-35 approval carries over); BR-CRM-3/4/6, PRD, IA journeys and architecture roles updated |

**Duties of draft roles that no longer exist (proposals marked † in the matrix; confirm or adjust):**

| ID | Question | Proposal |
|---|---|---|
| Q-OW-13 | **→ accepted: OD-36** Without a separate sales-manager role, who configures lead assignment rules and pipeline stages, approves lead exports, receives SLA-breach alerts, and which leads can Sales/CRM Executives see? | **Super Admin** configures assignment rules and pipeline stages and approves exports. SLA breaches alert the **Operations Manager and Super Admin**. **Sales/CRM Executives see all leads**, with optional per-user restriction to properties. |
| Q-OW-14 | **→ accepted: OD-37** Who uploads 3D models and maps hotspots (formerly "3D Technician")? | An **external 3D-modelling supplier** prepares the model files. The **Marketing Manager** uploads them and maps hotspots; Super Admin can too. |
### 0.8 Owner acceptance — 2026-10-24 (round 7): "ok"

Interpretation: "ok" accepts the four items in the request "accept S-1, S-2, Q-OW-13, Q-OW-14".

| ID | Accepted | Effect |
|---|---|---|
| OD-34 | **S-1:** the push to the private GitHub remote moves to **Phase 3, step 3.1**, together with naming the organisation (D-31) | AC-1.7 amended; Phase 3 deliverables updated |
| OD-35 | **S-2:** the pricing-proposal **structure** is approved; values PR-1…PR-11 are decided at the **Phase 5 entry gate** | `../requirements/PRICING_CONFIGURATION_PROPOSAL.md` status |
| OD-36 | **Q-OW-13:** the Super Admin configures assignment rules and pipeline stages and approves lead exports; SLA breaches alert the Operations Manager and Super Admin; Sales/CRM Executives see all leads (optional per-user property restriction) | BR-CRM-3/4/6, FR-CRM-1/6, security matrix † cells |
| OD-37 | **Q-OW-14:** an external 3D-modelling supplier prepares models; the Marketing Manager uploads them and maps hotspots (Super Admin can too) | security matrix † cells |

With these, all Phase 1 acceptance criteria except the owner's sign-off (AC-1.8) are met or deferred by owner decision. Phase 1 is **READY FOR REVIEW**.

---

## 1. Decisions

| ID | Decision | Who | Classification | Current state / evidence | Recommended answer | Needed by |
|---|---|---|---|---|---|---|
| D-01 | Company profile, market, legal entity | Owner + Client | **Requires client input** (partly answered) | **Market confirmed:** India; Delhi NCR target markets; operating in Noida (OD-02, OD-03). Still missing: legal entity name, CIN, GSTIN, registered address, positioning statement. | supply legal details before public launch | Phase 6 (legal details); market resolved |
| D-02 | Properties, floors and canonical city slugs | Owner | **Requires owner input** (mostly answered) | Four Noida properties, **each its own centre** (OD-13), all **owned** (OD-14); official city names (OD-16). **Open: floors per property and levels (Q-OW-1)**, full addresses (PIN, building names) and inventory. | supply via template SG-INV-1.3 | Phase 1 (AC-1.4) |
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
| D-24 | Dependencies (`SYSTEM_ARCHITECTURE.md` §9 + `openspout/openspout ^4` as direct) | Owner | **Resolved** (2026-10-24, OD-23) | Installed phase by phase from Phase 3; nothing installed yet | — | — |
| D-25 | Code layout | Owner | **Resolved** (2026-10-24, OD-24) | `SYSTEM_ARCHITECTURE.md` §3.1 | — | — |
| D-26 | Local MySQL for dev/test; Redis locally | Owner | **Resolved** for MySQL (configuration in place and verified); Redis **deferred to Phase 3** | `phpunit.xml` forces MySQL + `smartgro_testing`; guard in `tests/TestCase.php`; setup script prepared. **Owner action A-1 pending:** run the script on this machine. | run A-1; install Redis in Phase 3 | now / Phase 3 |
| D-27 | Partner (landlord/broker) and careers pages | Client | **Deferred to Phase 6** | — | omit until confirmed | Phase 6 |
| D-28 | Tour rules | Client | **Deferred to Phase 8** | — | 30-min slots within operating hours | Phase 8 |
| D-29 | Lead data retention | Client (counsel) | **Deferred to Phase 9** | — | counsel to confirm | Phase 9 |
| D-30 | Back-office path and access restriction | Owner | **Deferred to Phase 3** | — | non-default path + Cloudflare Access in production | Phase 3 |
| D-31 | GitHub repository owner | Owner | **Partly resolved** (OD-29, OD-34): a GitHub **organisation**; creation and push at **Phase 3 step 3.1**. **Name needed then.** | `../operations/LOCAL_DEVELOPMENT.md` §5 | name the organisation at Phase 3 start | Phase 3 step 3.1 |
| D-32 | Release slicing (R1) | Owner | **Resolved** (2026-10-24, OD-25) | `PROJECT_MASTER_PLAN.md` §4 | — | — |
| D-33 | Team structure and role/permission matrix | Owner | **Resolved** (2026-10-24, **OD-33**: six owner-defined roles; supersedes OD-28) | `../security/SECURITY_MODEL.md` §3.1. Duties of removed draft roles: Q-OW-13, Q-OW-14 (proposals). | confirm the † proposals | Phase 3 |
| D-34 | ADR-0003…0014 acceptance | Owner | **Resolved** (2026-10-24, OD-26) | all ADRs accepted | — | — |
| D-35 | Inventory import template SG-INV-1.3 and four-eyes import approval | Owner | **Resolved** (2026-10-24, OD-27) | `../requirements/INVENTORY_IMPORT_SPEC.md` | — | — |
| D-36 | **New:** business values marked [DEFAULT] in the PRD and business rules (hold times, SLAs, windows, horizons) | Owner | **Deferred** to the gate of the phase that implements each rule. They are confirmed there, never silently adopted. | listed in `../requirements/BUSINESS_RULES.md` | confirm at each phase gate | Phases 5–9 |

### Summary

| Classification | Decisions |
|---|---|
| Resolved | D-03, D-05, D-06, D-11a, D-16, D-19, D-22, D-23, D-24, D-25, D-26 (MySQL part), D-32, D-33, D-34, D-35 |
| Requires owner approval | **Phase 1 approval (AC-1.8)**. D-31 organisation name at Phase 3 step 3.1. |
| Requires client input | D-01 (legal details, may defer to Phase 6); D-11b (logo file, owner will add) |
| Deferred to a later phase | D-04 (5), D-07 (8), D-08 (8), D-09 (9), D-10 (8/10), D-12 (6), D-13 (7), D-14 (3), D-15 (6), D-17 (4), D-18 (6), D-20 (8), D-21 (13), D-26 Redis (3), D-27 (6), D-28 (8), D-29 (9), D-30 (3) |

---

## 2. Risks

| ID | Risk | Classification | State / mitigation evidence |
|---|---|---|---|
| R-1 | An existing system must stay authoritative, changing the inventory model | **Resolved** (OD-06: no existing software) | SmartGro is the master. `external_*` columns kept for future integrations. |
| R-2 | No version control | **Resolved** | Git initialised, with a reviewed baseline commit (§3). Residual: no off-machine copy yet (R-11). |
| R-3 | No logo file or real photography | **Requires client input** (D-11b, D-12) | Logo not in the project, and not available to the working session. BR-MED rules prevent non-genuine imagery. |
| R-4 | 3D capture cost and device performance | **Deferred to Phase 7** | Budgets (NFR-PERF-4) and the fallback (BR-3D-5) are defined |
| R-5 | Scope breadth versus timeline | **Resolved** (OD-25: release slicing approved) | R1 scope as in the master plan |
| R-6 | Regulatory: DPDP, SMS DLT, WhatsApp templates | **Deferred to Phase 6/8/10** | Legal review (D-15). Provider registration should start early. |
| R-7 | Double booking / concurrency bugs | **Deferred to Phase 8** (implementation); design-level mitigation **in place** | MySQL harness proves lock-first serialisation and catches double booking (9/9 passing, 3 runs, MySQL 8.4.11). Booking-domain tests T-BK-01…10 must pass before booking code merges. |
| R-8 | Dev/prod parity (SQLite hid locking bugs) | **Resolved** — owner action A-1 pending | Tests forced to MySQL. A `*_testing` guard is verified to refuse the dev DB. |
| R-9 | Content inconsistency (prices, counts) | **Deferred to Phase 4/5** | Price tokens and inventory-driven blocks designed |
| R-10 | Lead spam and bots | **Deferred to Phase 8** | Form contract §3 in `../architecture/API_CONTRACTS.md` |
| R-11 | The only copy of the repository is on one machine | **Deferred to Phase 3 step 3.1** (OD-34) | Push to the private remote as the first Phase 3 step. Until then, keep a local backup of the project folder. |
| R-12 | **New:** slow client inputs keep Phase 1 open and delay Phase 2 | **Requires client input** | `../requirements/CLIENT_INPUTS_REQUIRED.md` is ready to send. Owner-approved deferrals are possible for non-structural items. |
| R-13 | **New:** "snapshot before lock" pitfall: a plain read before taking the lock causes double booking even with a lock | **Resolved at design level** | Reproduced by a negative-control test. ADR-0004 now requires the lock to be the first statement in the transaction. |
| R-14 | **New (2026-10-24):** the local `.env` connects as MySQL **`root`** on a server that also hosts other projects' databases, including personal-data tables such as candidates and employees. A misconfiguration or bug in SmartGro could read or damage another project's data. | **Requires owner approval** | Mitigations already in place: tests are forced to `smartgro_testing` and the `*_testing` guard blocks other databases. **Recommended:** switch to a least-privileged `smartgro` user limited to `smart_go` and `smartgro_testing` by running `SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh`. Never use `root` in staging or production. |
| R-15 | Floors per property and full addresses of the four properties are unknown, so the ERD cannot be validated against real data | **Requires client input** (Q-OW-1 re-asked) | Grouping resolved (OD-13). Nothing is created by assumption (BR-PROP-1). Template SG-INV-1.3 is ready. |
| R-16 | **New:** the existing WordPress site at smartgro.space shows signs of compromise: 1,038 injected `/pin/` spam URLs, cloaked to serve Japanese spam to Googlebot | **Requires owner action now** (outside this project) | Evidence and recommended actions in `../research/EXISTING_WEBSITE_AUDIT.md` §2. The new platform must not reuse any files from it, and must return 410 for `/pin/*`. |
| R-17 | Loss of search visibility at cut-over from WordPress | **Deferred to Phase 11** (redirects approved, OD-21) | 57-URL inventory captured; final map from a fresh crawl and Search Console data; `/pin/*` → 410 |
| R-18 | Selling long-term agreements beyond a leased property's lease end | **Resolved for now** (OD-14: all current properties owned) | Lease tracking (BR-PROP-5) is activated when the first leased property is added |
| R-19 | Configuration breadth (7 categories × per-property rules) increases build and test effort | **Resolved** (OD-25, OD-26) | Typed configuration (ADR-0013 accepted); staged release |

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
