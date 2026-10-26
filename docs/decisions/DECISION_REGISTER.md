# SmartGro — Decision and Risk Register

| | |
|---|---|
| Status | **Living document**; supersedes the snapshot in `../phases/PHASE_0_DISCOVERY_REPORT.md` §5 |
| Last updated | 2026-10-25 |

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

### 0.9 Phase 1 approval — 2026-10-24

| ID | Decision |
|---|---|
| OD-38 | **Phase 1 approved.** The owner instructed "continue next phase" after the Phase 1 baseline was submitted for approval. This is recorded as approval of the baseline, including its deferrals (AC-1.4 → Phase 5 entry gate; remote push → Phase 3 step 3.1). |

### 0.10 Logo supplied — 2026-10-24

| ID | Decision or question |
|---|---|
| OD-39 | **Logo supplied.** The owner attached the logo in conversation with the instruction "add this logo". It is stored unmodified at `resources/brand/source/smartgro-logo.webp`: WebP, 388 × 218 px, transparent background, SHA-256 `295745308066c2f2187cb82bdec5b5b03c26a8dd210092a7e3dcd3bee203964b`. An identical copy is served at `public/brand/smartgro-logo.webp` and shown in the back office. Interpretation: this is the authoritative logo for screen use. No vector master or reversed version was supplied (D-11b stays partly open). |
| Q-OW-15 | **→ answered: OD-40.** The logo's wordmark reads "SMART GRO" (two words), while OD-01 records the name as "SmartGro". **Recommended:** keep "SmartGro" as the written name in text, page titles, emails and the back office; the logo keeps its own lettering. |

### 0.11 Owner answers — 2026-10-24 (round 8): "ok smart gro ok phase 2"

Interpretation: the reply has the same shape as the two questions asked ("ok" + topic, twice), so each "ok" is read as accepting that question's recommendation. The owner should correct OD-40 if "Smart Gro" (two words) was meant as the written name.

| ID | Decision |
|---|---|
| OD-40 | **Q-OW-15:** the written brand name stays **"SmartGro"** (one word) in text, page titles, emails and the back office. The logo keeps its own "SMART GRO" lettering and is never retyped. |
| OD-41 | **Phase 2 starts now**, in parallel with the Phase 3 steps that wait on owner inputs (3.1, 3.11). |

### 0.12 Phase 2 proposals awaiting the owner (2026-10-24)

Details and evidence: `../design/DESIGN_SYSTEM.md` §7.4; screenshots in `../design/review/2026-10-24/`. Nothing below is treated as approved until the owner says so.

| ID | Proposal | Recommended |
|---|---|---|
| P2-A | Colour tokens: forest `#0B2C1B` sampled from the logo replaces Deep Forest `#073B2C`; new logo-sampled tints (`#84BD53`, `#3F9B24`, `#195625`), neutrals and status colours; Smart Green, Action Green, Charcoal and Warm White unchanged | approve |
| P2-B | Typefaces: Sora (headings) and Inter (text, forms, prices), open-licensed and self-hosted | approve |
| P2-C | Visual direction (AC-2.5) as shown in the home-page sample | approve, or name changes |
| P2-D | Favicon and app icons cropped from the logo's "SG" monogram, unchanged in shape and colour | approve |
| P2-E | Reversed logo for dark backgrounds: only the dark wordmark and buildings recoloured to Warm White, geometry unchanged | approve, or supply one |

### 0.13 Owner decisions — 2026-10-24 (round 9)

Source: the owner's instruction "Proceed with the approved design-system decisions", items 1–5, plus the statement that the home-page design is **not yet signed off**.

| ID | Decision |
|---|---|
| OD-40 (confirmed) | **SmartGro** is the official one-word spelling. |
| OD-42 | **P2-A approved:** forest `#0B2C1B` replaces `#073B2C`; the logo-derived palette is kept, together with the automated WCAG 2.2 AA contrast checks. |
| OD-43 | **P2-B approved:** Sora for headings; Inter for body text and prices. |
| OD-44 | **P2-D approved:** the browser-tab icon is the original "SG" symbol, cropped, unchanged in shape and colour. |
| OD-45 | **P2-E approved:** a dark-background logo that keeps the original green elements and changes only the dark lettering and buildings to white. Interpretation: "dark lettering and buildings" covers every element drawn in the logo's dark ink: the word "SMART", the upper arc of the G (same ink as the buildings), the buildings and the arc beneath them. The tagline is a different, grey colour and was not specified, so it is left unchanged (see Q-OW-16). |
| P2-C | **Not approved** (round 9; **→ approved 2026-10-26, OD-82**). The owner will review the desktop and mobile screenshots first. Phase 2 visual sign-off (AC-2.5) stays open; work that depends on the home-page direction (step 2.6) waits. |
| Q-OW-16 | **→ answered: OD-46.** On dark backgrounds the tagline keeps its grey (`#444444`), which reaches only **1.55 : 1** on forest and is not readable. Options: (a) also change the tagline lettering to Warm White (its thin lines stay as supplied, 3.82 : 1); (b) leave it as supplied and accept it is unreadable on dark backgrounds. **Recommended: (a).** |

### 0.14 Owner decision — 2026-10-24 (round 10)

| ID | Decision |
|---|---|
| OD-46 | **Owner-approved: the tagline lettering is white in the dark-background logo** (Q-OW-16, option a). Conditions set by the owner: only the dark-grey tagline lettering changes to white; the tagline stays exactly "WORK SMART. GROW TOGETHER."; the thin side lines are unchanged; the original green elements, proportions, shapes and alignment are preserved; the original logo is unchanged, and this applies only to the dark-background variant. Implemented 2026-10-24 (`App\Actions\Brand\GenerateBrandRenditions`); evidence in `../design/DESIGN_SYSTEM.md` §7.6. |
| P2-C | **Still not signed off** (round 10). The remaining page layouts (step 2.6) are not started until the owner approves the home page. |

### 0.15 Owner instruction — 2026-10-24 (round 11): "Phase 2 continuation prompt"

Source: the owner's brief "SmartGro — Phase 2 continuation prompt: complete remaining page designs and admin UX". **The brief was cut off** in section 5 after item "7. Mixed zones" (Q-OW-17).

| ID | Decision |
|---|---|
| OD-47 | **Design the remaining Phase 2 pages and the back-office screens now, before the home page is signed off.** This replaces the round-10 instruction to wait. The home page stays **unapproved and frozen**: its layout, styling, content, components and responsive behaviour must not change, and shared components may change only where the home page renders identically (verified pixel-for-pixel). |
| OD-48 | Scope confirmed by the brief: city pages (5 canonical cities; Gurgaon redirects to Gurugram; administrator-controlled visibility; coming-soon page), property listing and detail for the 4 confirmed Noida properties, the 7 workspace types, enterprise, the enquiry, tour, request-to-book, online-booking and payment journeys, and back-office screens 1–7 (dashboard, property create and edit, buildings and floors, workspace products, individual seats, pooled capacity, mixed zones), keeping the light back-office theme. |
| Q-OW-17 | **→ answered: OD-49.** Please resend section 5 of the brief from after "7. Mixed zones", and anything that followed it (further back-office screens, deliverables, reporting). Nothing after the cut is assumed. |
| P2-C | **Still not signed off** (round 11). |

### 0.16 Owner instruction — 2026-10-24 (round 12): "Phase 2 — remaining back-office scope"

| ID | Decision |
|---|---|
| OD-49 | **Back-office items 8 to 14 are designed as previews** (answers Q-OW-17): pricing configuration, enquiry management, booking-request review, tour management, contact and legal details, website content management, permissions and publishing safeguards. Previews keep their safeguards: no real bookings, inventory changes, messages, payments or saved business changes. |
| OD-50 | **Stop after this Phase 2 work and wait for owner review.** No deployment, GitHub setup or package installation. Phase 2 is not complete until AC-2.1 to AC-2.5, including the home-page sign-off, are met. |
| Q-OW-18 | **→ answered: OD-51 to OD-57** (§0.17). Six permission questions for the new areas (SECURITY_MODEL §11): manual enquiry assignment, who approves booking requests, who edits contact details, who edits and verifies legal and GST details, whether the Marketing Manager may publish (D-17), and how many people hold each role. |
| Note | The instruction "do not begin Phase 3" conflicts with the record: Phase 3 steps 3.2 to 3.10 were already built on 2026-10-24 after the owner's "continue next phase" (OD-38). No further Phase 3 work was done in round 12; steps 3.1 and 3.11 still wait for owner inputs (D-31, R-14, D-14). |

### 0.17 Owner decisions — 2026-10-25 (round 13): "Record Q-OW-18 owner decisions"

Source: the owner's seven answers to Q-OW-18. **These decisions approve the intended policy. They do not authorise any change to live permissions or role assignments, and none of them is implemented.** How each is applied, the rules every capability follows, and the implementation and test gaps are in `../security/SECURITY_MODEL.md` §11.

In every decision, a role is authorised only once it has been **explicitly granted** the named capability. A role name alone authorises nothing.

| ID | Decision (owner's words) | Recorded as |
|---|---|---|
| OD-51 | "Enquiry owner assignment: Super Admin and Sales/CRM Manager." | `leads.assign` for the Super Admin and the Sales/CRM Executive role. Interpretation: OD-33 has no "Sales/CRM Manager" role, so the Sales/CRM Executive role is meant (Q-OW-19.1). |
| OD-52 | "Booking request approval: Super Admin and authorised Operations/Property Manager." | `bookings.approve` for the Super Admin, the Operations Manager and the Property Manager (assigned properties only). Sales/CRM Executives, Marketing and Finance cannot approve. Interpretations: rejection uses the same capability (Q-OW-19.3); "authorised" means the role is granted the capability (Q-OW-19.2). Approval always re-checks availability under lock; no role, including the Super Admin, can force a booking (BR-BK-5). |
| OD-53 | "Public contact details editing: Super Admin and Marketing Manager." | `settings.contact.manage` for the Super Admin and the Marketing Manager. |
| OD-54 | "Legal/GST editing: Super Admin and Finance Manager." | `settings.legal.manage` for the Super Admin and the Finance role (interpretation: OD-33's "Finance"; Q-OW-19.1). |
| OD-55 | "Legal/GST verification: A second authorised person distinct from the editor." | `settings.legal.verify` for the Super Admin and Finance. Interpretation: these are the roles OD-54 authorises for legal and GST details. The verifier must be a different person from everyone who edited the version, the Super Admin included. Derived rule: a later edit clears the verification. |
| OD-56 | "Publishing approved content: Marketing Manager may publish only after separate approval by another authorised person." | The Marketing Manager publishes (including scheduling and rollback) only a version that another person holding `content.approve` approved. BR-CMS-2 (no self-approval by authors) is kept. Partly answers D-17. |
| OD-57 | "Role headcount: Undecided; keep role assignments and headcount configurable." | No limit per role; roles stay assignable in the back office (`users.manage`). Four-eyes rules are never relaxed when staff are few. |

**Reconciliation (documentation only, 2026-10-25).** Where the documents disagreed with, or did not cover, these decisions:

| Document | Contradiction or gap found | Change made |
|---|---|---|
| `../security/SECURITY_MODEL.md` §3.1 | No rows for manual enquiry assignment, booking-request approval, contact details or legal and GST details. The combined "A approve/publish" let a Marketing Manager approve someone else's content and then publish it themselves, which OD-56 rules out. Sales/CRM could "create/modify" bookings, which could bypass approval. | Rows added and marked ‡ (not implemented). Approve and publish are now separate rights for content. A booking entered by someone without `bookings.approve` stays a request when the product needs approval. Note added: four-eyes rules are never relaxed for lack of staff. |
| SECURITY_MODEL §11 | Seven rows "Needs owner confirmation". Sales/CRM was proposed for booking approval. The Super Admin was shown as "Yes" on four-eyes approvals, although §10 says the Super Admin has no bypass. | Rows now show OD-51 to OD-56, each marked "not implemented". The Sales/CRM proposal is dropped. The Super Admin is shown as "Not own" for those approvals. New sections: §11.1 decisions, §11.2 rules, §11.3 gaps, §11.4 open points. |
| SECURITY_MODEL §3, §7 | Separation of duties covered only author ≠ approver. The audit list had no booking approval, manual assignment or contact and legal changes. | Separation of duties now also covers verifier ≠ editor and Marketing Manager publisher ≠ approver. New rules: capabilities are checked, never role names; approval never overrides availability. Audit entries added. |
| `../requirements/BUSINESS_RULES.md` | BR-BK-10 said "staff confirm" without saying who. There was no rule for manual lead assignment or for publisher ≠ approver. | BR-BK-10 now names the approvers (OD-52). BR-CRM-3a (OD-51) and BR-CMS-2a (OD-56) added. |
| `../architecture/DATABASE_ERD.md` §4.1 | `approver_role` accepted any role. | It is limited to roles holding `bookings.approve`. It narrows approval but never grants it. No model change. |
| `../requirements/CLIENT_INPUTS_REQUIRED.md` C-5.3; D-01, D-09, D-17, D-33 below | Did not reflect the decisions. | Cross-references added. D-17 is partly resolved. |
| `../design/DESIGN_SYSTEM.md` §10; `../phases/PHASE_STATUS.md` | The permissions review preview was described as current. Q-OW-18 was listed as open. | Preview marked out of date; Q-OW-18 answered. |
| ADR-0004, ADR-0005, ADR-0011 | None found: lock-first booking, approved-revision publishing and capability permissions with separation of duties all agree with the decisions. | Unchanged. |

**Not resolved by this update:** none of OD-51 to OD-57 is implemented or tested, and the permissions, workspace-product and legal-details previews still show the round-12 proposals (SECURITY_MODEL §11.3). Documenting a rule does not close its implementation gap.

| ID | Decision |
|---|---|
| Q-OW-19 | **→ answered: OD-58 to OD-67** (§0.18). Points the seven answers leave open (SECURITY_MODEL §11.4): (1) whether "Sales/CRM Manager" and "Finance Manager" mean the OD-33 roles Sales/CRM Executive and Finance; (2) whether "authorised" means the whole role or named people; (3) whether rejecting a booking request follows approval; (4) whether rescheduling a confirmed booking needs approval again; (5) whether contact-detail changes need a second person before going live; (6) whether the Super Admin may publish a version they approved (recommended: no); (7) whether SEO redirects and brand settings need a second person. |
| Note | Documentation only. No permission, role assignment, application code, database, authentication or MFA setting was changed. No Phase 3 work was started; steps 3.1 and 3.11 still wait for owner inputs (D-31, R-14, D-14). |

### 0.18 Owner decisions — 2026-10-25 (round 14): "Resolve Q-OW-19 and reconcile security decisions"

Source: the owner's ten confirmed decisions answering Q-OW-19, plus the owner's response to R-20. **As in round 13, these decisions approve the intended policy. They do not authorise any change to live permissions, role assignments or code, and none is implemented.** OD-51 to OD-57 are kept as recorded in §0.17. Where a decision below confirms or changes how one of them is applied, SECURITY_MODEL §11.1 says so on that row.

| ID | Decision (owner's words) | Recorded as |
|---|---|---|
| OD-58 | "Interpret Sales/CRM Manager as the existing Sales/CRM Executive role for now." | Confirms the OD-51 interpretation. No new role (answers Q-OW-19.1). |
| OD-59 | "Interpret Finance Manager as the existing Finance role for now." | Confirms OD-54, and the Finance role for OD-55 verification. No new role (Q-OW-19.1). |
| OD-60 | "“Authorised” means the user has been explicitly granted the relevant permission. Role names alone do not grant access." | Authorisation is **per user**. A user acts only when that user has been explicitly granted the permission, within the most their role allows (SECURITY_MODEL §3.1) and their property scope. Grants and removals are audited. **Interpretation:** per-user grants, because Q-OW-19.2 asked "the whole role or named people" (to confirm: Q-OW-20.1). This replaces the round-13 reading "the role is granted the capability" (OD-52 row). **Effect:** the current code grants per role. That is an implementation gap and was not changed. **→ Per-user reading superseded by OD-68 (§0.19).** |
| OD-61 | "The same authorised roles permitted to approve booking requests may reject them." | `bookings.approve` covers approval and rejection (Q-OW-19.3). |
| OD-62 | "Legal/GST verification must be performed by a person different from the editor." | Confirms OD-55, for every role including the Super Admin. |
| OD-63 | "Rescheduling a confirmed booking requires approval again if it changes inventory, price or booking terms. Availability must be rechecked for every reschedule, and existing concurrency safeguards must remain in force." | BR-BK-13 (Q-OW-19.4).<br>• **Every** reschedule locks first and re-checks availability (BR-BK-5, ADR-0004).<br>• Approval is needed again when the space, seat, zone or quantity changes, when the recomputed price changes, or when the product, duration, payment rule or cancellation policy changes.<br>• **Interpretation:** a time-only change with everything else the same needs no new approval.<br>• **Derived:** the booking keeps its current values until the change is approved, and a failed re-check changes nothing. |
| OD-64 | "Changes to public contact details require independent approval before going live." | Author → approver (not the author) → publisher (not the approver), per BR-CMS-9 (Q-OW-19.5). **Interpretation:** the approvers are the Super Admin and the Marketing Manager, each only for other people's changes (to confirm: Q-OW-20.2). **→ Confirmed by OD-69.** |
| OD-65 | "A content approver must not publish the same content version they approved. The author must also remain distinct from the approver." | Applies to every role, including the Super Admin, and covers scheduling and rollback (BR-CMS-2, BR-CMS-2a). Extends OD-56 (Q-OW-19.6). The owner's instruction to distinguish author, approver and publisher is applied the same way to contact details, redirects and brand settings. |
| OD-66 | "SEO redirect changes require independent approval before publication." | Covers redirects changed by hand, including the launch map and the `/pin/*` rules (BR-CMS-9, FR-SEO-4, FR-SEO-6; Q-OW-19.7). **Interpretation:** an automatic slug-change redirect goes live with its approved page (BR-CMS-6). |
| OD-67 | "Changes to public-facing brand settings, including logo and design-system settings, require independent approval before publication." | Covers the logo and renditions, design tokens and design-system settings, and social links (BR-CMS-9, FR-BRAND-1; Q-OW-19.7). **Interpretation:** FR-CMS-8 lists social links as brand settings. **Contradiction:** the approved matrix gives the Super Admin "M" but not "A" for brand settings (Q-OW-20.3). **→ Resolved by OD-70.** |
| R-20 | Owner response: "retain strict separation of duties and explain when a second authorised person is required." | Recorded in R-20 and SECURITY_MODEL §11.5. Four-eyes rules are never relaxed, and the Super Admin cannot override them. §11.5 lists which actions need a second person. Each needs at least two people granted the permissions. |
| Q-OW-19 | → answered by OD-58 to OD-67. | |

**Reconciliation (documentation only, 2026-10-25, round 14):**

| Document | Contradiction or gap found | Change made |
|---|---|---|
| `../security/SECURITY_MODEL.md` §3, §3.1 | Several rules contradicted or did not cover the decisions:<br>• "authorised" was read as a role grant;<br>• only the Marketing Manager was barred from publishing what they approved;<br>• contact details had no approval step;<br>• redirects and brand settings let the Marketing Manager approve their own changes;<br>• reschedules were undecided. | • Per-user grants (OD-60), with the matrix as the maximum.<br>• Approve and publish separated, for every role, for content, contact details, redirects and brand settings.<br>• Every Super Admin "A" is "not own".<br>• Reschedule rule added.<br>• Staffing response recorded. |
| SECURITY_MODEL §7, §11 | The audit list and the capability table did not cover reschedules, contact-detail approval, redirects, brand settings or per-user grants. | • Audit entries added for reschedules, contact, redirect and brand approval and publishing, and per-user grants.<br>• §11 table rows added for contact approve and publish, redirects and brand.<br>• §11.1 adds OD-58 to OD-67; round-13 rows kept, with updates noted.<br>• §11.2 rules, §11.3 gaps, §11.4 (Q-OW-19 answers, Q-OW-20) updated.<br>• New §11.5: when a second person is required. |
| `../requirements/BUSINESS_RULES.md` | • BR-CMS-2 was a [DEFAULT].<br>• BR-CMS-2a covered only the Marketing Manager.<br>• There was no reschedule rule and no approval rule for public settings. | • BR-CMS-2 is now an owner decision (OD-65).<br>• BR-CMS-2a covers every publisher.<br>• BR-CMS-6 note added.<br>• New BR-CMS-9 and BR-BK-13.<br>• BR-BK-10 and BR-CRM-3a cite OD-58, OD-60 and OD-61. |
| `../requirements/PRD.md` FR-CMS-2, FR-CMS-8, FR-SEO-4, FR-SEO-6, FR-BRAND-1 | Acceptance criteria let the approver publish, and let brand, contact and redirect changes go live without approval. | Criteria updated. Status line notes the update. |
| `../requirements/INFORMATION_ARCHITECTURE.md` UJ-8; `../architecture/SYSTEM_ARCHITECTURE.md` 3D publishing flow | Both showed the approver publishing. | The approver approves; a different user publishes. |
| `../architecture/API_CONTRACTS.md` §5 `Content\Publish` | The failure list had no case for the approver publishing. | `ApproverCannotPublish` added. No reschedule action is defined yet (gap). |
| `../architecture/DATABASE_ERD.md` | The model cannot hold what the decisions need:<br>• `revisions` has no publisher;<br>• `redirects` and `site_settings` have no approval state;<br>• `bookings` cannot hold a pending reschedule. | Gaps noted in place. **Model not changed:** it needs owner approval when built. |
| `../design/DESIGN_SYSTEM.md` §1.1, §10; `../requirements/CLIENT_INPUTS_REQUIRED.md` C-5.3; `../phases/PHASE_STATUS.md` | Did not reflect the decisions. | Updated. |
| ADR-0004, ADR-0005, ADR-0011 | • ADR-0004 already locks first for reschedules.<br>• ADR-0005's revisions cover content only, so settings and redirects need a design when built.<br>• ADR-0011 agrees. | Unchanged. |

**Not resolved by this update:** none of OD-51 to OD-67 is implemented or tested. The previews still show the round-12 proposals. The data model cannot yet hold the publisher, approval states for settings and redirects, or pending reschedules (SECURITY_MODEL §11.3). Documenting a rule does not close its implementation gap.

| ID | Decision |
|---|---|
| Q-OW-20 | **→ answered: OD-68 to OD-72** (§0.19). Points the round-14 answers leave open (SECURITY_MODEL §11.4):<br>1. Confirm per-user grants for OD-60. The alternative is that a permission granted to a role counts for its members. **→ OD-68** (role grants count; exceptions optional)<br>2. Confirm the Super Admin and the Marketing Manager as contact-detail approvers. **→ OD-69** (confirmed)<br>3. May the Super Admin approve brand changes (not own) and publish them? Recommended: yes. Without it, a single Marketing Manager's brand changes cannot be approved. **→ OD-70** (approve yes; never publish a version they approved)<br>4. Tracking IDs: keep them without a second person, or require approval? **→ OD-71** (approval required)<br>5. Confirm that one user granted `bookings.approve` may approve a request or reschedule they entered themselves. Availability is always re-checked. **→ OD-72** (no: a different approver is required) |
| Note | Documentation only. No permission, role assignment, application code, database schema, authentication, MFA or production setting was changed. No role was added or changed. No Phase 3 work was started. |

### 0.19 Owner decisions — 2026-10-25 (round 15): "Resolve Q-OW-20 and D-17"

Source: the owner's decisions OD-68 to OD-73, with IDs given by the owner, answering Q-OW-20 and D-17. **These are owner-approved policy decisions for documentation. They are not implemented and do not change live permissions, role assignments or code.** Earlier entries are kept unchanged. SECURITY_MODEL §11.1 notes on each earlier row what a later decision confirms or changes.

| ID | Decision (owner's words, key sentences) | Resolves | Recorded as, and why |
|---|---|---|---|
| OD-68 | "A permission explicitly granted to a role applies to every user assigned to that role. Individual permission grants may be supported as optional exceptions, subject to the role's permitted scope. Role membership alone must not grant permissions that have not been explicitly assigned to that role." | Q-OW-20.1 | The §3.1 matrix is each role's **permitted scope**. Permissions assigned to a role apply to all its members. Individual exceptions are optional and limited to that scope. SECURITY_MODEL §11.2 rule 1 sets out:<br>• **precedence:** scope is the limit; exceptions only add; separation of duties applies on top;<br>• **revocation:** from the next request; an exception stops when the role that permits it is removed;<br>• **audit:** who, when, user, permission, scope and reason.<br>No implementation design was added. **Why:** it supersedes the round-14 per-user reading of OD-60, and the existing role-based code already matches it. |
| OD-69 | "Contact-detail changes require independent approval before becoming public. Super Admin and Marketing Manager may approve changes made by another person, subject to explicit permission grants. No person may approve their own change." | Q-OW-20.2 | Confirms the OD-64 interpretation. `settings.contact.approve` is "not own" for both roles. Publisher ≠ approver (OD-65). |
| OD-70 | "Public-facing brand-setting changes require independent approval before publication. Super Admin may approve brand-setting changes but may not publish a version they approved. The author may publish only if they are not the approver." | Q-OW-20.3 | The Super Admin's brand cell becomes M/A (not own). `brand.approve` is "not own" and `brand.publish` is "after another's approval" for the Super Admin and the Marketing Manager. **Why:** with one Marketing Manager, brand changes could otherwise never be approved. |
| OD-71 | "Changes to tracking IDs, analytics identifiers and advertising pixels require independent approval before they become active on the public website. Apply the same author/approver/publisher separation principles used for other public-facing settings." | Q-OW-20.4 | Tracking is no longer exempt; new `settings.tracking.*` rows. **Interpretation:** the approvers are the Super Admin and the Marketing Manager, "not own", as for contact details and brand settings. Approving an ID never adds a script domain to the CSP allow-list. Tags inside an external tag manager are outside this approval (Q-OW-21.2). |
| OD-72 | "A user who creates or submits a booking request or reschedule must not approve that same request or reschedule. Approval must be performed by another authorised person with explicit approval permission. Every booking or reschedule must re-check availability under the required inventory lock. No Super Admin override or forced approval may bypass availability or separation-of-duties rules." | Q-OW-20.5 | `bookings.approve` is "not own". A customer's request is approved by any authorised staff user. A request or reschedule entered by staff needs a different approver. An outside-hours override never skips approval or the re-check. **Interpretation:** rejection follows the same rule (OD-61), and the creator may still withdraw their own request. **Why:** it reverses the round-14 default that one person could do both. |
| OD-73 | "Resolve D-17 in favour of the existing separation-of-duties policy: the author and approver must be different people, and the approver must not publish the version they approved. The author may publish an approved version if they are not the approver. A two-person workflow is therefore permitted." | **D-17** | One approval by a non-author is enough; no second approval. SECURITY_MODEL §11.6 lists the permitted combinations (author A, approver B, publisher A or C) and the failure cases: self-approval; approver publishes; co-author approves; unapproved or changed version; scheduling or rollback by the approver; missing permission; any Super Admin override. |
| Q-OW-20 | → answered by OD-68 to OD-72. | | |
| D-17 | → resolved by OD-73. | | |

**Contradictions resolved (round 15):**

| Contradiction | Where it was | Resolution |
|---|---|---|
| Per-user grants (round-14 reading of OD-60) versus role-based grants in code | SECURITY_MODEL §3, §3.1, §11.2, §11.3; BUSINESS_RULES BR-BK-10, BR-CRM-3a, BR-CMS-2a; DESIGN_SYSTEM §1.1; D-33 | OD-68: role assignments apply to every member; exceptions are optional. The code-level gap is withdrawn. |
| The Super Admin could manage brand settings but not approve them | SECURITY_MODEL §3.1 brand row; §11 `brand.*` "Open" | OD-70: M/A (not own); publish after another's approval. |
| Tracking IDs changed without approval | SECURITY_MODEL §3.1 (Marketing "tracking IDs: M"); PRD FR-CMS-8 ("not covered"); INTEGRATION_CONTRACTS IC-2 | OD-71: independent approval before going live; rows split; IC-2, FR-CMS-8 and FR-AN-1 updated. |
| One user could approve a booking request or reschedule they entered | SECURITY_MODEL §11.5 ("No"), §3.1 booking row | OD-72: "not own" everywhere. BR-BK-9, BR-BK-10, BR-BK-13, PRD FR-BK-5, ERD §4.1 and the `bookings` gap updated. |
| One approval or two (D-17) | BR-CMS-2 "[UNRESOLVED → D-17]"; CLIENT_INPUTS C-5.3; D-17 | OD-73: one approval by a non-author is enough. |
| Was the round-14 extension of author/approver/publisher to public settings an interpretation? | OD-65 row (§0.18) | Confirmed by OD-71 ("the same … principles used for other public-facing settings") and OD-69. |

**Checked, no change needed:** API_CONTRACTS (`Content\Publish` already refuses an approver publishing; there is still no staff booking-approval or reschedule action, which is a gap) and SYSTEM_ARCHITECTURE (the publishing flow already separates approver and publisher). ADR-0004, ADR-0005 and ADR-0011 agree with the decisions.

**Not resolved by this update:** none of OD-51 to OD-73 is implemented or tested. The previews still show the round-12 proposals. The data model still cannot hold the publisher, approval states for settings, redirects and tracking IDs, the staff creator of a booking request, or pending reschedules (SECURITY_MODEL §11.3).

| ID | Decision |
|---|---|
| Q-OW-21 | **→ answered: OD-74, OD-75** (§0.20). (SECURITY_MODEL §11.4):<br>1. **Back-office sign-in** is granted by holding any of the six roles, not by an assigned permission. It is the one access right that comes from role membership itself. Keep it, or require an assigned sign-in permission (OD-68)? **→ OD-74** (explicit `backoffice.access` permission)<br>2. **External tag manager** (if chosen, D-18): tags added inside it bypass the back-office approval required by OD-71. Bring its access controls into D-18, or avoid a tag manager? **→ OD-75** (optional; never a bypass; not enabled for live tracking unless it enforces the controls) |
| Note | Documentation only. No application code, permission, role assignment, authentication, MFA, database schema, migration, test, preview, production setting or deployment configuration was changed. No Phase 3 or other implementation work was started. |

### 0.20 Owner decisions — 2026-10-25 (round 16): "Close current phase, verify acceptance, move to the next phase", section 2

Source: the owner's decisions OD-74 and OD-75, with IDs given by the owner, answering Q-OW-21. **These are approved policy recorded in documentation. They are not implemented and change no live permission, role assignment, sign-in or two-step login behaviour.**

| ID | Decision (owner's words, key points) | Resolves | Recorded as, and why |
|---|---|---|---|
| OD-74 | "Back-office access requires an explicit backoffice.access permission. Role membership alone does not grant access. A role-level permission applies to every member of that role, consistent with OD-68. Individual grants are optional and must remain within the role's permitted scope. The approved role-permission matrix must explicitly represent which roles receive this permission. Do not assume that Super Admin automatically receives every future permission without reviewing it against the approved permission matrix. Apply the policy to protected back-office pages, routes and APIs. Preserve existing authentication and two-step login behaviour." | Q-OW-21.1 | A new matrix row in SECURITY_MODEL §3.1 and §11 assigns `backoffice.access` to all six roles. **Derived:** every role has back-office duties in §3.1, and this keeps today's access. The Super Admin's assignments must be listed and reviewed one by one (SECURITY_MODEL §3.1, §11.2 rule 1). **Contradictions found:**<br>• `User::canAccessPanel()` admits any active user with a role;<br>• `StaffRole::capabilities()` gives the Super Admin every capability automatically.<br>Both are recorded as implementation gaps (SECURITY_MODEL §10, §11.3). The code was not changed, because OD-74 gives no permission to change sign-in code in a documentation task. |
| OD-75 | "External tag managers are optional, not prohibited. They must never provide a bypass around OD-71. Changes to tracking IDs, analytics identifiers, advertising pixels and tag configurations must receive independent approval before becoming active, regardless of whether the change originates in SmartGro's CMS or an external provider. … No person may approve their own change or publish a version they approved. If a future provider cannot enforce the required controls, it must not be enabled for live website tracking." | Q-OW-21.2 | SECURITY_MODEL §11.2 rule 7 sets the access-control, authorisation, approval, publication and audit requirements. A provider is enabled for live tracking only if it is shown to enforce them. No vendor, API, provider capability or integration is assumed: D-18 is still open, and GTM is listed only as a recommended candidate. |
| Q-OW-21 | → answered by OD-74 and OD-75. | | |

**Contradictions resolved (round 16):**

| Contradiction | Where it was | Resolution |
|---|---|---|
| Back-office access came from role membership | SECURITY_MODEL §2, §3, §10, §11.3; SYSTEM_ARCHITECTURE (Filament smoke-test notes); `../operations/LOCAL_DEVELOPMENT.md` | OD-74 policy recorded. The current behaviour is described as current and flagged as a gap; no code was changed. |
| The Super Admin automatically receives every capability | SECURITY_MODEL §10, §11.3 (code: `StaffRole::capabilities()`) | OD-74: each Super Admin assignment is reviewed against §3.1. Implementation gap recorded. |
| Tags inside an external tag manager bypass approval | SECURITY_MODEL §11.4; INTEGRATION_CONTRACTS IC-2; CLIENT_INPUTS C-7.5; PRD FR-AN-1; D-18 | OD-75 requirements recorded; no provider enabled. |
| Test journey J-4 had "a different approver … publishes" | `../testing/TEST_STRATEGY.md` J-4 | Changed to match OD-65 and OD-73: a different person approves, and someone other than the approver publishes. |
| The back-office previews still showed the round-12 proposals | Design previews: permissions review, workspace products, contact and legal details, website content, booking requests | Preview text and choices updated to the decided policy (Phase 2 design work under OD-49). They remain previews and enforce nothing (DESIGN_SYSTEM §10). |

**Not resolved:** none of OD-51 to OD-75 is implemented in production code. See SECURITY_MODEL §11.3 for the gaps.

| ID | Decision |
|---|---|
| Note | No owner question is open after this round. Phase 2 still needs the owner's sign-off of the visual direction (AC-2.5). Phase 3 still needs D-31, R-14 and D-14 (`../phases/PHASE_STATUS.md`, acceptance records). |

### 0.21 Owner instruction — 2026-10-25 (round 20): "Resolve D-31"

| ID | Decision |
|---|---|
| OD-76 | **The intended security model for the GitHub organisation** (owner's words): "A private repository. Company-controlled organisation ownership. Two trusted organisation owners, if available. Mandatory two-factor authentication for members. Least-privilege repository access. No personal account as the sole owner of production-critical code." These are recorded as requirements for Phase 3 step 3.1. The setup checklist for the owner's approval is in `../operations/LOCAL_DEVELOPMENT.md` §5. |
| D-31 | **Awaiting owner input:** the organisation name and its legal or business owner were not supplied. Nothing was created: no organisation, no authentication, no remote, no push. **→ Name and owner confirmed later by OD-78 (§0.23).** |
| Note | Pre-push readiness, checked read-only on 2026-10-25:<br>• 37 commits; no `.env` file was ever committed;<br>• no matches for private keys, application keys, database passwords or common access tokens anywhere in the history;<br>• largest file 0.4 MB;<br>• the CI workflow is valid YAML;<br>• the owner's staged `CLAUDE.md` edit is in no commit, so pushing `main` cannot send it. |

### 0.22 Owner decision — 2026-10-25 (round 21): "D-31 resolution and Phase 3 continuation"

| ID | Decision |
|---|---|
| OD-77 | **The D-31 setup checklist is approved** (`../operations/LOCAL_DEVELOPMENT.md` §5), with two additions:<br>1. re-check the full Git history and repository contents for secrets immediately before pushing;<br>2. keep `CLAUDE.md` out of commits unless its inclusion is explicitly authorised.<br>The approval covers the checklist wording only. |
| D-31 | **Still unresolved.**<br>• Organisation name: `SmartGro` is recommended but has not been selected as the final name.<br>• Legal or business owner: not provided.<br>• Repository creation: not authorised; the owner will create it.<br>• Push credential: set up by the owner.<br>• Push: not authorised.<br>**→ Name and owner confirmed later by OD-78 (§0.23); the other points stay open.** |

### 0.23 Owner decision — 2026-10-25 (round 22): "Owner-confirmed D-31 decisions"

| ID | Decision |
|---|---|
| OD-78 | **D-31 organisation and owner confirmed** (owner's exact values):<br>• GitHub organisation name: `Smartgro`<br>• legal or business owner: `Smart Gro Services Private Limited`<br>The setup checklist is approved under OD-77, including both additions. **Scope:** this names the owner of the GitHub organisation and repository only. It is not recorded as SmartGro's registered legal entity for invoices and legal pages (D-01), which needs separate confirmation together with CIN, GSTIN and registered address. |
| D-31 | **Still open** for the remaining setup steps:<br>• the owner creates the organisation and the private repository;<br>• the owner sets up the push credential;<br>• the first push is **not authorised**.<br>Claude has created no organisation, repository, remote or credential, and has pushed nothing. |

### 0.24 Owner instruction — 2026-10-25 (round 23): read-only pre-push verification (recorded in round 24)

| ID | Finding |
|---|---|
| D-31 | **Destination conflict.** GitHub has no organisation named `Smartgro` (public API: 404). The name `smartgro` (GitHub names ignore capitalisation) is held by a **personal user account created 2017-11-03**. Names are unique across users and organisations, so an organisation called `Smartgro` cannot be created while that account holds it. Pushing to `Smartgro/smartgro` would put the code under a personal account, which conflicts with OD-29, OD-76 and OD-78. **Owner to resolve (R-21).** OD-78 is unchanged: it is the owner's decision. |
| D-31 | **Repository and credential not verifiable from here.**<br>• `Smartgro/smartgro` returns 404 without signing in: not public, but existence cannot be confirmed.<br>• No push credential is visible to this machine's terminal: no SSH key on disk or in the agent, no `~/.ssh/config`, no GitHub CLI, no credential helper, no token variables.<br>• Write access was not tested, because even a dry-run push contacts GitHub's push service. |
| Note | **Checks passed:**<br>• **History:** across all 40 commits, no `.env` file was ever committed (only `.env.example`, whose passwords and key are empty or `null`); `.npmrc` holds settings only; there are no private keys, app keys, cloud or GitHub tokens, Slack or Stripe keys, JWTs, or credentials in URLs (the 16 pattern matches are synthetic test passwords, cast names, design tokens and placeholders).<br>• **Owner edit:** the staged `CLAUDE.md` edit is in no commit.<br>• **Push contents:** a push of `main` would send 40 commits (`4778e70` to `bb911a7`), all under the owner's Git identity.<br>• **GitHub's host key** on this machine matches GitHub's published fingerprint.<br>**Points to note:** the history includes the original `CLAUDE.md` (first commit) and development-tool files (`.claude/`, `.mcp.json`, `AGENTS.md`, `boost.json`), none containing secrets; commit author emails are visible to repository members. Nothing was pushed or changed. |

### 0.25 Owner direction — 2026-10-25 (round 25): "Phase 3 local completion"

| ID | Decision |
|---|---|
| OD-79 | **All GitHub setup is deferred until the application is fully developed and verified locally** (owner's direction). Until the owner authorises otherwise, there is:<br>• no GitHub access, no repository or organisation creation, no remote, no GitHub credentials, no push;<br>• no deployment;<br>• no Redis, Horizon or other packages or infrastructure without explicit approval.<br>GitHub and deployment tasks are recorded as deferred, never as done (PHASE_STATUS, Phase 3 "Deferred GitHub and deployment checklist"). Local test results are not equivalent to GitHub CI or a staging deployment. **Effect:** D-31, D-14, R-21, AC-3.1, AC-3.5 and steps 3.1 (push) and 3.11 are deferred to the final handover. OD-78 is unchanged. |

### 0.26 Owner decisions — 2026-10-26 (round 26): answers to "continue"

| ID | Decision |
|---|---|
| OD-80 | **The GitHub remote `origin` (`ErSuryaPrakashChamoli/smartgo`) is an interim private personal backup** (owner's answer). It was found on 2026-10-26: the owner had committed the working tree, including their `CLAUDE.md` edit, as `427b65f` ("morning pull oct 26") and pushed `main` to that personal-account repository. The owner confirms the repository is **private**. **Recorded as:**<br>• the company organisation plan (OD-29, OD-76, OD-78) and OD-79 stay in force for everything else;<br>• R-11 (single local copy) is reduced, and R-21 is unchanged;<br>• Claude pushes nothing to this remote.<br>Checks before recording, all local only: `origin/main` equals HEAD; no credentials are embedded in the remote URL or stored in Git configuration; the new commit contains no secrets. |
| OD-81 | **OD-74 confirmed and authorised:** all six roles receive `backoffice.access` (the proposed list), and it is implemented now. Sign-in and two-step login are unchanged. **Implemented 2026-10-26:**<br>• `User::canAccessPanel()` needs an active account, a staff role (scope, OD-68) and the `backoffice.access` permission;<br>• `StaffRole::capabilities()` lists every role's capabilities explicitly, the Super Admin's included.<br>Tests are in SECURITY_MODEL §11.3. |

### 0.27 Owner decisions — 2026-10-26 (round 27): phase gates

| ID | Decision |
|---|---|
| OD-82 | **Phase 2 visual direction approved** (owner's words: "Phase 2 visual direction: Approve"). This signs off AC-2.5 (P2-C), the last open Phase 2 criterion. **Recorded as Phase 2 APPROVED** (interpretation, as with Phase 1's "continue next phase"). Not COMPLETE: the vector master (D-11b) and a manual accessibility review are carried forward. |
| OD-83 | **Phase 3 accepted** (owner's words: "Phase 3 acceptance: Accept"), on the terms put to the owner: AC-3.1 (CI) and AC-3.5 (staging) are deferred under OD-79, and R-14 stays an owner action. **Recorded as Phase 3 APPROVED with deferred items.** Not COMPLETE until the deferred items close at the handover. |
| Note | Phase 4 depends on Phases 2 and 3 and is now opened (PHASE_STATUS, Phase 4). Under OD-79, its packages need explicit approval. |
| OD-84 | **Phase 4 packages approved** (owner's answer "Install both"): `spatie/laravel-medialibrary` ^11 and `filament/spatie-laravel-media-library-plugin` ^5.10, both on the approved list (D-24). They are installed when step 4.4 needs them. The S3 adapter stays deferred with hosting (D-14). |
| OD-85 | **Data-model additions approved:** `revisions` gains the publisher and scheduling fields, so author, approver and publisher are recorded separately (OD-65, OD-73). Contact details, brand settings, tracking IDs and manual redirects keep their draft → approved → published history in the same `revisions` table (ADR-0005 extended), so one workflow enforces OD-64 to OD-73. |
| OD-86 | **Claude may run additive migrations on the local development database `smart_go`** after tests pass. Tests keep running on `smartgro_testing`. |
| OD-87 | **Phase 4 work is committed locally, one commit per verified step**, with explicit file paths, and is never pushed (OD-79, OD-80). Interpretation: this also covers the already-verified OD-74 work and the decision records of rounds 26 and 27, because they share documents with the Phase 4 commits. |

### 0.28 Owner directions — 2026-10-26 (round 29): brief for Phase 4 step 4.2

| ID | Decision |
|---|---|
| OD-88 | **Restoring an earlier version creates a new revision that goes through approval and publication** (owner's brief, §9: "Restoration must create a new revision based on the selected historical content; it must not mutate or delete historical revisions … the restored version must go through the required approval and publication workflow"). The person restoring may not be the approver of the version being restored. **Supersedes** BR-CMS-3's "rolling back republishes a previous revision" and ADR-0005's "rollback is republish revision N"; the step 4.1 direct-republish action is removed. **Interpretation:** the restored version's original author and editors count as editors of the new draft, so none of them approves their own words (as for OD-55). |
| OD-89 | **Step 4.2 is not committed in this run** (owner's brief: "Do not commit changes without separate owner authorisation"; "Do not commit, push, deploy, or begin Phase 4.3 during this run"). This overrides OD-87 for step 4.2 until the owner authorises a commit. **Closed by OD-90.** |

### 0.29 Owner directions — 2026-10-26 (round 30)

| ID | Decision |
|---|---|
| OD-90 | **Commit step 4.2** (owner's words: "commit step 4.2"). Committed locally with explicit paths; not pushed (OD-79, OD-80). |
| OD-91 | **Step 4.3 is not committed in its run** (owner's brief for step 4.3: "Do not commit changes without separate authorisation"; "Do not commit, push, deploy, or start Step 4.4 during this run"). |

---

## 1. Decisions

| ID | Decision | Who | Classification | Current state / evidence | Recommended answer | Needed by |
|---|---|---|---|---|---|---|
| D-01 | Company profile, market, legal entity | Owner + Client | **Requires client input** (partly answered) | **Market confirmed:** India; Delhi NCR target markets; operating in Noida (OD-02, OD-03). Still missing: legal entity name, CIN, GSTIN, registered address, positioning statement. Who enters and who verifies them: OD-54, OD-55, confirmed by OD-59 and OD-62 (a second person; not implemented). | supply legal details before public launch | Phase 6 (legal details); market resolved |
| D-02 | Properties, floors and canonical city slugs | Owner | **Requires owner input** (mostly answered) | Four Noida properties, **each its own centre** (OD-13), all **owned** (OD-14); official city names (OD-16). **Open: floors per property and levels (Q-OW-1)**, full addresses (PIN, building names) and inventory. | supply via template SG-INV-1.3 | Phase 1 (AC-1.4) |
| D-03 | Product catalogue | Owner | **Resolved** (2026-10-24, OD-07) | Seven admin-manageable categories. Rule **values** (pricing, payment, cancellation, durations) are not yet confirmed (brief §9 not received). | — | — |
| D-04 | Pricing rules and public price display | Client | **Deferred to Phase 5** | The ERD price model is generic and does not depend on the answer | "from" prices for flexible products, "+ GST" display (CLIENT_INPUTS C-3.4/3.5) | Phase 5 |
| D-05 | Inventory granularity | Owner | **Resolved** (2026-10-24, OD-10) | Individual (per-seat), pooled and hybrid-within-property are all supported, with product-specific rules. A hybrid *zone* is a separate question (Q-OW-7). | — | — |
| D-06 | Existing systems and source of truth | Owner | **Resolved** (2026-10-24, OD-06) | **No existing operational software.** SmartGro is the master for inventory, availability, pricing, bookings and CRM (Scenario A). The assessment is kept for future integrations. | — | — |
| D-07 | Online booking scope at launch | Client | **Deferred to Phase 8** | API contract §2.2/§3.3 is conditional on this | live tour scheduling at launch; instant room booking only with payments | Phase 8 |
| D-08 | Payment gateway; GST invoice ownership | Client | **Deferred to Phase 8** | — | decide with finance; webhooks authoritative | Phase 8 |
| D-09 | Sales process, assignment, SLA | Client | **Deferred to Phase 9** | Who may assign an enquiry owner by hand is decided (OD-51, not implemented); assignment rules and SLA values stay open. | ERD stages; 15-min first response | Phase 9 |
| D-10 | Email / WhatsApp / SMS providers | Client | **Deferred to Phase 8/10** | — | transactional email at launch; WhatsApp once the number is verified | Phase 8 |
| D-11a | Provisional brand palette | Owner | **Resolved** (2026-10-24) | Approved by the owner as **provisional**. Action Green `#287A15` verified: 5.03:1 on Warm White, 5.41:1 on white (WCAG AA). Smart Green `#2E8B18` kept for the logo and decorative/large elements only. To be re-checked when the logo is sampled. | — | — |
| D-11b | Original SmartGro logo files (vector master) | Owner | **Partly resolved** (OD-39) | The raster logo (388 × 218 px WebP) was supplied and stored on 2026-10-24. Still requested: a vector master (SVG preferred; otherwise AI/EPS/PDF) or a high-resolution PNG (at least 2000 px wide), plus a reversed version for dark backgrounds if one exists. Never recreated or redrawn. | place the files at `resources/brand/source/` | Phase 2 |
| D-12 | Photography (existing, rights, shoots) | Client | **Deferred to Phase 6** | Design can proceed with clearly marked placeholders; no AI imagery is ever presented as real | professional shoot per open location | Phase 6 |
| D-13 | Floor plans and 3D capture | Client | **Deferred to Phase 7** | — | pilot one flagship location with a measured scan | Phase 7 |
| D-14 | Hosting, DNS, account ownership | Client + Owner | **Deferred until local completion (OD-79)**; previously deferred to Phase 3 | Staging, proxies and production settings wait for it (PHASE_STATUS, Phase 3 checklist H-1 to H-3) | client-owned accounts; managed Laravel hosting + Cloudflare; India region | Phase 3 (staging) |
| D-15 | Legal policies, DPDP responsibilities, retention | Client (counsel) | **Deferred to Phase 6** | — | counsel to provide | Phase 6 |
| D-16 | Website languages | Owner | **Resolved** (2026-10-24, OD-05) | English only for the initial release | — | — |
| D-17 | Content approval workflow | Client | **Resolved** (2026-10-25, **OD-73**, by the owner; previously partly resolved by OD-56 and OD-65) | **One approval** by a person other than the author is enough. The approver never publishes the version they approved, and the author may publish if they are not the approver, so a two-person workflow is permitted (BR-CMS-2, BR-CMS-2a; cases in SECURITY_MODEL §11.6). Not implemented. | single approver; no self-approval | — |
| D-18 | Analytics, ads, consent, maps | Client | **Deferred to Phase 6** | Tracking-ID, analytics-identifier and pixel changes need independent approval before going live (OD-71, not implemented). An external tag manager is optional, and is enabled for live tracking only if it enforces independent approval, publication and audit (OD-75; SECURITY_MODEL §11.2 rule 7). | GA4 + GTM with consent mode (candidates, not chosen) | Phase 6 |
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
| D-30 | Back-office path and access restriction | Owner | **Deferred to Phase 3** | The path is configurable (`BACKOFFICE_PATH`). Inside the application, access requires the explicit `backoffice.access` permission (OD-74, OD-81; implemented 2026-10-26). The network-level restriction is still open. | non-default path + Cloudflare Access in production | Phase 3 |
| D-31 | GitHub repository owner | Owner | **Partly resolved** (OD-29, OD-34, OD-76, OD-77, OD-78): GitHub organisation `Smartgro`, owned by Smart Gro Services Private Limited (OD-78), under the security model of OD-76; setup checklist approved (OD-77). **Still open:** the owner creates the organisation and the private repository; the owner sets up the push credential; the first push is **not authorised**. **Pre-push check (2026-10-25): no organisation `Smartgro` exists; the GitHub name is held by a personal user account (R-21).** **Deferred until local completion (OD-79).** | `../operations/LOCAL_DEVELOPMENT.md` §5 | name the organisation at Phase 3 start | Phase 3 step 3.1 |
| D-32 | Release slicing (R1) | Owner | **Resolved** (2026-10-24, OD-25) | `PROJECT_MASTER_PLAN.md` §4 | — | — |
| D-33 | Team structure and role/permission matrix | Owner | **Resolved** (2026-10-24, **OD-33**: six owner-defined roles; supersedes OD-28) | `../security/SECURITY_MODEL.md` §3.1. Duties of removed draft roles: Q-OW-13, Q-OW-14 (proposals). Permissions for back-office items 8 to 14 decided (OD-51 to OD-57, approved policy, not implemented); the number of people per role is undecided and stays configurable (OD-57). Role names confirmed for now (OD-58, OD-59). A permission assigned to a role applies to all its members; individual exceptions are optional, within the role's scope (OD-60, OD-68; not implemented). No role added. Back-office access is the explicit `backoffice.access` permission, assigned to all six roles (OD-74; confirmed by OD-81; implemented 2026-10-26). | confirm the † proposals | Phase 3 |
| D-34 | ADR-0003…0014 acceptance | Owner | **Resolved** (2026-10-24, OD-26) | all ADRs accepted | — | — |
| D-35 | Inventory import template SG-INV-1.3 and four-eyes import approval | Owner | **Resolved** (2026-10-24, OD-27) | `../requirements/INVENTORY_IMPORT_SPEC.md` | — | — |
| D-36 | **New:** business values marked [DEFAULT] in the PRD and business rules (hold times, SLAs, windows, horizons) | Owner | **Deferred** to the gate of the phase that implements each rule. They are confirmed there, never silently adopted. | listed in `../requirements/BUSINESS_RULES.md` | confirm at each phase gate | Phases 5–9 |

### Summary

| Classification | Decisions |
|---|---|
| Resolved | D-03, D-05, D-06, D-11a, D-16, D-17 (OD-73), D-19, D-22, D-23, D-24, D-25, D-26 (MySQL part), D-32, D-33, D-34, D-35 |
| Requires owner approval | Deferred until local completion (OD-79): D-31 owner actions (organisation, repository, push credential) and push authorisation, and D-14 hosting for staging; D-30 back-office path for production |
| Requires client input | D-01 (legal details, may defer to Phase 6); D-11b (vector master and reversed version; the raster logo was supplied, OD-39) |
| Deferred to a later phase | D-04 (5), D-07 (8), D-08 (8), D-09 (9), D-10 (8/10), D-12 (6), D-13 (7), D-14 (3), D-15 (6), D-18 (6), D-20 (8), D-21 (13), D-26 Redis (3), D-27 (6), D-28 (8), D-29 (9), D-30 (3) |

---

## 2. Risks

| ID | Risk | Classification | State / mitigation evidence |
|---|---|---|---|
| R-1 | An existing system must stay authoritative, changing the inventory model | **Resolved** (OD-06: no existing software) | SmartGro is the master. `external_*` columns kept for future integrations. |
| R-2 | No version control | **Resolved** | Git initialised, with a reviewed baseline commit (§3). Residual: no off-machine copy yet (R-11). |
| R-3 | No vector logo or real photography | **Requires client input** (D-11b, D-12) | The raster logo is in the project (OD-39), but at 388 × 218 px it cannot serve large hero, print or high-density uses. No real photography yet. BR-MED rules prevent non-genuine imagery. |
| R-4 | 3D capture cost and device performance | **Deferred to Phase 7** | Budgets (NFR-PERF-4) and the fallback (BR-3D-5) are defined |
| R-5 | Scope breadth versus timeline | **Resolved** (OD-25: release slicing approved) | R1 scope as in the master plan |
| R-6 | Regulatory: DPDP, SMS DLT, WhatsApp templates | **Deferred to Phase 6/8/10** | Legal review (D-15). Provider registration should start early. |
| R-7 | Double booking / concurrency bugs | **Deferred to Phase 8** (implementation); design-level mitigation **in place** | MySQL harness proves lock-first serialisation and catches double booking (9/9 passing, 3 runs, MySQL 8.4.11). Booking-domain tests T-BK-01…10 must pass before booking code merges. |
| R-8 | Dev/prod parity (SQLite hid locking bugs) | **Resolved** — owner action A-1 pending | Tests forced to MySQL. A `*_testing` guard is verified to refuse the dev DB. |
| R-9 | Content inconsistency (prices, counts) | **Deferred to Phase 4/5** | Price tokens and inventory-driven blocks designed |
| R-10 | Lead spam and bots | **Deferred to Phase 8** | Form contract §3 in `../architecture/API_CONTRACTS.md` |
| R-11 | The only copy of the repository is on one machine | **Deferred to Phase 3 step 3.1** (OD-34) | Push to the private remote as the first Phase 3 step. Until then, keep a local backup of the project folder. **Update 2026-10-26:** an interim private personal backup exists on GitHub (OD-80); the company-organisation remote is still deferred (OD-79). |
| R-12 | **New:** slow client inputs keep Phase 1 open and delay Phase 2 | **Requires client input** | `../requirements/CLIENT_INPUTS_REQUIRED.md` is ready to send. Owner-approved deferrals are possible for non-structural items. |
| R-13 | **New:** "snapshot before lock" pitfall: a plain read before taking the lock causes double booking even with a lock | **Resolved at design level** | Reproduced by a negative-control test. ADR-0004 now requires the lock to be the first statement in the transaction. |
| R-14 | **New (2026-10-24):** the local `.env` connects as MySQL **`root`** on a server that also hosts other projects' databases, including personal-data tables such as candidates and employees. A misconfiguration or bug in SmartGro could read or damage another project's data. | **Requires owner approval** | Mitigations already in place: tests are forced to `smartgro_testing` and the `*_testing` guard blocks other databases. **Recommended:** switch to a least-privileged `smartgro` user limited to `smart_go` and `smartgro_testing` by running `SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh`. Never use `root` in staging or production. |
| R-15 | Floors per property and full addresses of the four properties are unknown, so the ERD cannot be validated against real data | **Requires client input** (Q-OW-1 re-asked) | Grouping resolved (OD-13). Nothing is created by assumption (BR-PROP-1). Template SG-INV-1.3 is ready. |
| R-16 | **New:** the existing WordPress site at smartgro.space shows signs of compromise: 1,038 injected `/pin/` spam URLs, cloaked to serve Japanese spam to Googlebot | **Requires owner action now** (outside this project) | Evidence and recommended actions in `../research/EXISTING_WEBSITE_AUDIT.md` §2. The new platform must not reuse any files from it, and must return 410 for `/pin/*`. |
| R-17 | Loss of search visibility at cut-over from WordPress | **Deferred to Phase 11** (redirects approved, OD-21) | 57-URL inventory captured; final map from a fresh crawl and Search Console data; `/pin/*` → 410 |
| R-18 | Selling long-term agreements beyond a leased property's lease end | **Resolved for now** (OD-14: all current properties owned) | Lease tracking (BR-PROP-5) is activated when the first leased property is added |
| R-19 | Configuration breadth (7 categories × per-property rules) increases build and test effort | **Resolved** (OD-25, OD-26) | Typed configuration (ADR-0013 accepted); staged release |
| R-21 | **New (2026-10-25):** the D-31 destination conflicts with the decided organisation. GitHub has no organisation `Smartgro`, and the name `smartgro` belongs to a personal user account created 2017-11-03. A push to `Smartgro/smartgro` would place the code under a personal account (contrary to OD-29, OD-76 and OD-78). | **Requires owner action** | Owner to confirm the organisation's exact GitHub name, or whether that account is theirs and how ownership moves to an organisation. No remote, push or history change until resolved (register §0.24). Deferred with D-31 until local completion (OD-79). |
| R-20 | **New (2026-10-25):** with few staff, rules that need a second person block work. Examples: one person holds both the Super Admin and Finance roles, so legal details can never be verified; or only one person can approve content. | **Response approved** (owner, 2026-10-25): keep strict separation of duties. Staffing is still undecided (OD-57). | The rules are never relaxed, and the Super Admin cannot override them (SECURITY_MODEL §3.1, §11.2). SECURITY_MODEL §11.5 lists when a second authorised person is required: content, contact details, redirects, brand settings, tracking IDs, legal and GST verification, prices, imports, campaigns, and booking requests or reschedules entered by staff (OD-72). Until one exists, those actions wait; for example, invoices and legal pages stay blocked. **Recommended:** before launch, at least two people explicitly granted the permissions for each of those areas. |

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
| A-2 | **Deferred until local completion (OD-79).** Create the GitHub organisation `Smartgro` (owner: Smart Gro Services Private Limited, OD-78), the private repository and the push credential yourself (checklist approved, OD-77). Authorise the first push separately. | `../operations/LOCAL_DEVELOPMENT.md` §5 |
| A-3 | Send the client the inputs checklist and the systems questionnaire | `../requirements/CLIENT_INPUTS_REQUIRED.md`; `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` §2 |
| A-4 | Approve or amend D-24, D-25, D-32, D-34, D-35 | this register |
| A-5 | **New (2026-10-24):** enable Filament's AI guidance skill. Filament 5.10.3 ships `filament-development`, but `boost.json` does not enable it, so AI-assisted work lacks Filament-specific rules. Per Filament's docs, `boost:update` alone does not add it. | run `php artisan boost:install` (interactive), choose **Agent Skills** → **filament/filament (skills)**, then review the changes to `CLAUDE.md` and `.claude/skills` before committing |
| A-6 | **New (2026-10-25):** review the visual direction and sign off AC-2.5, or name changes | `../phases/PHASE_STATUS.md`, Phase 2 "AC-2.5 visual review pack" |
| A-7 | **New (2026-10-25):** Phase 3 decisions: D-31 owner actions (organisation, repository, push credential) and push authorisation; R-14 (run the setup script yourself, or decline); D-14 hosting; confirm the OD-74 `backoffice.access` role list; separately, authorise OD-74 implementation | `../phases/PHASE_STATUS.md`, Phase 3 "Owner decision checklist" |
