# SmartGro — Product Requirements Document

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval.** Incorporates owner decisions OD-01…OD-37. Pricing values are decided at the Phase 5 entry gate (OD-35). Approval and publishing acceptance criteria updated 2026-10-25 for OD-56 and OD-60 to OD-75 (approved policy, not implemented). |
| Version | 0.3 (2026-10-24) |
| Companion docs | `INFORMATION_ARCHITECTURE.md` (sitemap, journeys) · `BUSINESS_RULES.md` (BR-*) · `CLIENT_INPUTS_REQUIRED.md` · `INVENTORY_IMPORT_SPEC.md` · `../architecture/*` (incl. `API_CONTRACTS.md`, `EXISTING_SYSTEMS_ASSESSMENT.md`) · `../security/SECURITY_MODEL.md` (role matrix) · `../research/MARKET_BENCHMARK.md` |
| Decision references | D-xx items: `../decisions/DECISION_REGISTER.md` (living register) |

> **How to read defaults.** Anything marked [DEFAULT], "recommended" or "assumption" is a proposal from the project team. It is **not** an approved business fact until its decider (owner or client) confirms it in the decision register.

---

## 1. Product summary

SmartGro is a premium coworking and managed-workspace brand ("WORK SMART. GROW TOGETHER."). The platform combines a premium public website with an operating back office. Customers discover and evaluate real workspaces (including interactive 3D twins) and book tours or spaces. SmartGro's team runs inventory, content, sales and marketing from **one source of truth**, without needing technical skills.

**Confirmed business decisions (owner, 2026-10-24; `../decisions/DECISION_REGISTER.md` §0):**
* **Brand:** SmartGro; tagline "WORK SMART. GROW TOGETHER."; domain **smartgro.space**; official logo used as supplied (file still to be placed in the project).
* **Market and language:** India; English only.
* **Geography:** Delhi NCR target markets (Noida, Gurugram, Delhi, Faridabad, Ghaziabad); currently operating in **Noida** only.
* **Properties:** four Noida properties, **each its own centre** (A-70, Sector 2; A-71, Sector 2; A-23, Sector 3; H-105, Sector 62), all **owned and operated**, with 10 floors in total. **Floors per property are unknown.**
* **Operating models:** both owned-and-operated and leased-and-operated.
* **Existing software:** none.
* **Customers:** eight customer segments.
* **Products:** seven product categories.
* **Inventory:** individual, pooled and mixed inventory, including **hybrid zones** (assigned seats plus a shared pool).
* **Cities:** official current names (Noida, Gurugram, Delhi, Faridabad, Ghaziabad). Cities stay hidden until an admin turns them on.
* **Old website:** nothing is taken from it.
* **Back office:** the term "Property" is used throughout.
* **Existing website:** a WordPress site to be replaced (`../research/EXISTING_WEBSITE_AUDIT.md`).

**Not yet known:**
* legal entity details
* floor allocation, full addresses and room-level inventory
* pricing, payment and cancellation rules
* operating policies
* team structure

This PRD defines *how* the platform behaves. It does not invent property data, inventory or commercial policies.

## 2. Goals and success measures

| Goal | Measure | Target |
|---|---|---|
| Convert visitors into qualified conversations | enquiry + tour conversion rate; tour-to-deal rate | baseline after launch; targets set by owner (D-09) |
| Respond fast | median first-response time on new leads | ≤ 15 min in business hours [DEFAULT] |
| Marketing independence | % of routine content changes shipped without a developer | 100% of the routine operations listed in brief §4 |
| Trustworthy inventory | incidents of double booking; inconsistent price/availability between surfaces | zero |
| Discoverability | indexed centre pages; organic sessions to location pages | all published centres indexed within 14 days of launch |
| Performance | Core Web Vitals, p75 mobile | LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 |
| Accessibility | WCAG 2.2 AA conformance on templates | no unresolved AA failures at launch |

## 3. Personas

**Customer segments (all eight confirmed, OD-04; no marketing priority implied):**

| # | Segment | Typical products (indicative, not exclusive) |
|---|---|---|
| S-1 | Startups and small businesses | private cabins, dedicated desks |
| S-2 | Freelancers and independent professionals | hot desks, meeting rooms |
| S-3 | SMEs and growing companies | private cabins, office suites |
| S-4 | Large enterprises and corporates | managed offices, conference/training rooms |
| S-5 | Remote and hybrid employees | hot desks, dedicated desks |
| S-6 | Consultants and business travellers | hot desks (day), meeting rooms |
| S-7 | Companies seeking managed offices | managed offices |
| S-8 | Businesses requiring virtual offices / registered addresses | virtual office / business address |

The segment-to-product mapping is admin-managed (`product_customer_segment`) and is used for discovery and reporting, not for prioritisation.

**Staff roles (owner-defined, OD-33):** Super Admin, Operations Manager, Property Manager, Sales/CRM Executive, Marketing Manager, Finance. Capabilities per `SECURITY_MODEL.md` §3.1. 3D models are prepared by an external 3D-modelling supplier.

## 4. Scope

### 4.1 Proposed Release 1 ("launch"), subject to owner approval
* Brand system and responsive design (Phase 2).
* Foundation, roles, MFA, audit (Phase 3).
* CMS: pages, sections, navigation, profiles, media library, approval workflow (Phase 4).
* Inventory: cities to spaces, offerings, price plans, availability engine (Phase 5).
* Public website: all templates in `INFORMATION_ARCHITECTURE.md` §2 that have real content (Phase 6).
* Enquiries and **live tour scheduling**; meeting-room/day-pass booking **only if D-07 approves** (Phase 8).
* CRM core: leads, contacts, assignment, pipeline, activities, SLA alerts (Phase 9).
* Technical SEO essentials: metadata, schema, sitemaps, redirects (Phase 11).
* Testing, security, accessibility and performance gates; deployment (Phases 14–15).

### 4.2 Release 1 if assets exist, otherwise Release 2
* 3D digital twins for locations with real scans or models (Phase 7, D-13). Fallback (gallery + floor plan) ships in Release 1 regardless.

### 4.3 Later releases
* Marketing automation and campaigns (Phase 10), advanced analytics and attribution (Phase 12), AI concierge (Phase 13), customer/member portal (D-20).

### 4.4 Out of scope (unless re-scoped)
* Member billing and access control (doors, Wi-Fi), visitor management, facility helpdesk, native mobile apps.
* **International markets** (OD-02), multi-currency, and **languages other than English** (OD-05).
* Operating as a **third-party marketplace** for other operators' spaces (not confirmed; OD-09).

### 4.5 Product and geographic coverage
* The platform supports **all seven product categories** (OD-07), configured per category and per property (ADR-0013). Which categories are promoted at launch is part of release slicing (D-32).
* Launch geography is **Noida**. Gurugram, Delhi, Faridabad and Ghaziabad are target markets. They gain public pages only when a property is confirmed there (BR-PROP-4, Q-OW-5). New cities and properties are added through the back office, without code.

## 5. Functional requirements

Format: **FR-ID**: requirement. *Acceptance:* testable criteria. The Phase column shows where it is built.

### 5.1 Discovery and public site (Phase 6)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-DISC-1 | City, micro-market, centre and product × city pages, server-rendered, per IA §2. | Every page returns complete content in HTML without JS. Unpublished or empty combinations return 404 or are not linked. |
| FR-DISC-2 | City page map/list toggle and filters (product, team size, move-in by, amenities). | Filters work with and without JS (query-string fallback). Results match the inventory service. |
| FR-DISC-3 | Centre page per IA §3.1, with live availability and price fragments. | Ops changes show within 60 s (BR-SOT-4). Price shows only per BR-PR-3. |
| FR-DISC-4 | Compare up to 3 SmartGro centres (price display, commute, amenities, availability). | Comparison reads only canonical data. Shareable URL. |
| FR-DISC-5 | Context-carrying CTAs: every "Book a tour"/"Enquire" passes city, centre and product. | The resulting lead has those fields set without the user re-entering them. |
| FR-DISC-6 | Mobile sticky action bar: Book a tour · WhatsApp ◇ · Call. | Visible on ≤ 768 px. Each tap is tracked as an event with page context. |
| FR-DISC-7 | Location "Getting here" block: map, metro/bus, walk minutes, parking, accessibility. | Data entered once per building/location; rendered with schema GeoCoordinates. |

### 5.2 CMS and publishing (Phase 4)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-CMS-1 | Page builder with a fixed library of brand blocks (hero, text, media, cards, FAQ, CTA band, testimonials, location list, stats, comparison table, form embed). | Editors compose pages with no code/JSON/CSS. Every block renders accessibly on mobile and desktop. |
| FR-CMS-2 | Workflow Draft → Submitted → Approved → Published, with rejection notes and scheduling (BR-CMS-1…5). | Author cannot self-approve. The approver cannot publish, schedule or restore the version they approved, for any role including the Super Admin (BR-CMS-2a, OD-65). One approval by a person other than the author is enough, and the author may publish if they are not the approver (OD-73; cases in SECURITY_MODEL §11.6). Published state never changes until a publish action runs. Full revision history; restoring an earlier version makes a new draft that is approved and published like any other (OD-88). |
| FR-CMS-3 | Signed preview links (BR-CMS-4). | Preview shows exactly the draft, carries `noindex`, and expires. |
| FR-CMS-4 | Navigation, header and footer editors; links pick from pages and locations, not typed URLs (external allowed). | Renaming a page's slug never breaks menus. |
| FR-CMS-5 | Media library with upload, alt text, focal point, origin, licence and expiry; responsive renditions (BR-MED-*). | Cannot publish an image without alt text (or a decorative flag). AI/concept assets are blocked from location galleries. Licence expiry unpublishes. |
| FR-CMS-6 | Location and offering profiles (marketing face) separate from operational records. | Marketing users cannot edit capacity, status, availability or price from any screen (policy test per role). |
| FR-CMS-7 | Blog/insights, FAQs (global, location, product scope), testimonials, case studies, legal pages. | Testimonials and case studies require consent-on-file to publish. |
| FR-CMS-8 | Brand settings: contact numbers, social links, tracking IDs, limited design tokens with automatic contrast validation. | A token that fails WCAG AA for its role is rejected with an explanation. Contact details, social links and design tokens go live only after approval by someone other than the author, and are published by someone other than the approver (BR-CMS-9; OD-64, OD-65, OD-67). Tracking IDs, analytics identifiers and advertising pixels become active only after the same approval and publication steps (OD-71). The Super Admin may approve brand changes but never publishes a version they approved (OD-70). |

### 5.3 Inventory, pricing and availability (Phase 5)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-INV-1 | Guided setup: City → Property → Buildings → Floors → Spaces (wizard), plus bulk import with the SG-INV-1.3 template (`INVENTORY_IMPORT_SPEC.md`, ADR-0012). | A non-technical ops user can create a location with 3 floors and 40 spaces in one session. The import validates against rules V-001…V-180 and reports sheet/row/column issues. It is approved by a second person and committed all-or-nothing. Synthetic fixtures pass T-IMP-01…06. |
| FR-INV-2 | Space operational status changes with reason; history kept. | Every change is audit-logged and emits `SpaceAvailabilityChanged`. |
| FR-INV-3 | Allocations (long-term occupancy) with start/end/notice; seat-level for desk zones if D-05. | Availability states follow BR-AV-1…6 exactly (unit tests per rule). |
| FR-INV-4 | Products, offerings and price plans with approval (BR-PR-*). | Four-eyes enforced. Only one active plan per scope and unit at a time. |
| FR-INV-5 | Offers with eligibility, dates and approval. | Expired offers disappear from the site automatically. |
| FR-INV-6 | Availability service used by every surface (site, API, 3D, CRM, booking). | No surface computes availability independently (arch test). |

### 5.3a Properties, product categories and brand assets (Phases 4–5; OD-01, OD-07…OD-10)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-PROP-1 | Authorised administrators can add and edit **properties**, their **buildings** and **floors**, address and location data, operational status and **operating model** (owned/leased). | A property can be created with partial details and completed later. Publishing is blocked until BR-PROP-3 is met. Every change is audited. |
| FR-PROP-2 | Inventory (spaces, seats) can be added to any floor later, and updated at any time, subject to BR-INV-1…5. | Supply reductions that conflict with future commitments are refused with a list of the conflicts (lock-first, ADR-0004). |
| FR-PROP-3 | Each space declares its **inventory mode** (whole space / per seat / pooled / hybrid zone), and the categories it can be **sold as**. | Incompatible combinations are rejected (BR-PRODCFG-2). One availability per space across all categories (BR-AV-3c). |
| FR-PROP-4 | Property-specific pricing through offerings and price plans | Rules **proposed** in `PRICING_CONFIGURATION_PROPOSAL.md` (option P-2, pending owner review). The model supports per-property and per-space prices, quotations, deposits, fees, add-ons, GST per category, offers and cancellation policies. No price is hard-coded. |
| FR-PROP-5 | Restricted lease facts for leased properties: **deferred** (all current properties owned, OD-14) | Built when the first leased property is added (BR-PROP-5) |
| FR-CITY-1 | Administrators can add cities and **toggle each city's public visibility** (default hidden, OD-18) | A hidden city has no public page and no menu entry (404). A visible city without a property shows a **"Coming soon — register interest"** page (OD-22): no properties, prices or inventory; a register-interest form creates a lead (`city_waitlist`, city set); `noindex` by default (proposed). Toggles are audited. |
| FR-SEO-6 | **Migration redirects from the old site (OD-21)** | Every legitimate old URL answers 301 to its mapped new page in one hop; `/pin/*` answers 410; no old content is reused. The map is approved before cut-over, by someone other than its author, and published by someone other than the approver (OD-66, BR-CMS-9). |
| FR-PROD-1 | Product categories are managed in the back office: name, visibility, compatible space types and modes, fulfilment, durations, workflow, approval, payment rule, cancellation policy, capacity limits, booking window, required customer fields, segment relevance (ERD §4.1). | No code change is needed to add a category or change its rules. Changes affecting money require pricing permissions. All changes are audited. |
| FR-PROD-2 | Category rules can be overridden per property (offering). | The effective rule is shown in the admin UI and used by booking and enquiry flows. |
| FR-PROD-3 | Enquiry, quote and booking flows adapt to the category's workflow and required fields. | E.g. a virtual-office enquiry collects business details; a meeting-room request collects time and attendees. |
| FR-SEG-1 | The eight customer segments are admin-managed and optionally captured on forms. | Segment is visible on leads and in reports. No priority until the owner sets one. |
| FR-BRAND-1 | The official logo (original source retained, SVG preferred, plus optimised web renditions; primary, reversed, mark and favicon) is managed once in brand settings and referenced by every template. | Replacing the logo updates every page. Only brand-permitted users can change it. A replacement goes live only after approval by someone other than the author, and is published by someone other than the approver (OD-67, BR-CMS-9). Uploaded SVGs are sanitised. The logo is never recreated or replaced by text. |

### 5.4 3D digital twin (Phase 7)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-3D-1 | GLB upload with automated validation and anchor extraction (BR-3D-4, ADR-0009). | Invalid files are rejected with plain-language reasons. Anchors are listed automatically. |
| FR-3D-2 | Scene builder: choose floors, map anchors to spaces via dropdown with auto-suggestions, order guided-tour stops, preview, submit. | No coordinate or numeric 3D input exists anywhere in the UI. |
| FR-3D-3 | Public viewer: lazy-loaded, floor switcher, hotspots with live availability and CTA, guided tour, reduced-motion support, accessible list alternative. | Pages without a viewer load zero 3D bytes. The viewer page's LCP is not delayed by 3D assets. |
| FR-3D-4 | Labelling of capture type (BR-3D-1/2) and the fallback (BR-3D-5). | Conceptual/architectural disclaimers render automatically and cannot be removed by editors. |
| FR-3D-5 | 360° tour embeds from allow-listed providers. | Non-allow-listed URLs are rejected at save. |

### 5.5 Enquiries, tours and bookings (Phase 8)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-BK-1 | Enquiry forms (general, centre, product, enterprise with seat bands, virtual office ◇), two-step where long. | ≤ 8 visible fields per step. Every submission creates or updates a lead (BR-CRM-1) with attribution. Spam controls active. |
| FR-BK-2 | Live tour scheduler per location (BR-TR-*): pick a slot, confirm instantly, receive confirmation and ICS, reschedule or cancel via signed link. | Concurrent claims of the last slot produce exactly one confirmation (MySQL concurrency test). |
| FR-BK-3 | Meeting-room booking ◇ (D-07): slot picker, hold, details, (payment), confirmation, self-service cancel per policy. | BR-BK-1…10 pass, including concurrency tests. The price shown equals the price snapshot. |
| FR-BK-4 | Day-pass booking ◇ against shared capacity (BR-BK-2). | Capacity never exceeded under concurrency. |
| FR-BK-5 | Staff booking console: calendar per location/space, create/move/cancel with reasons, overrides per permission. | Overrides audit-logged. Overlap rules can never be bypassed. Every create and move re-checks availability under lock. A move that changes inventory, price or terms waits for approval (BR-BK-13). The user who created or submitted a request or move never approves it, and an override never skips approval (OD-63, OD-72). |

### 5.6 CRM (Phase 9)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-CRM-1 | Lead inbox with filters, ownership, status, SLA timers, duplicate detection (BR-CRM-2). | Sales/CRM Executives see all leads unless restricted per user (OD-36); Property Managers see their properties' leads read-only (policy tests). |
| FR-CRM-2 | Assignment rules (round-robin, fixed, least-loaded) by city, location, product or seat band. | Deterministic assignment with tests per strategy. Unmatched leads go to the manager queue. |
| FR-CRM-3 | Activities (call, WhatsApp, email, meeting, note, task) with reminders. | One-tap call/WhatsApp from the lead screen logs an activity. |
| FR-CRM-4 | Pipeline (configurable stages), deals with proposed spaces from canonical inventory. | Proposed spaces show live availability. Won deals create allocations through the inventory service (BR-CRM-7). |
| FR-CRM-5 | Lost reasons and disqualification reasons (managed lists). | Required on status change. |
| FR-CRM-6 | Export with permission and audit. | Super Admin only (confirmed, OD-36). Export events logged. |

### 5.7 Marketing automation (Phase 10)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-MKT-1 | Message templates for email, WhatsApp and SMS with merge tags from a fixed list; provider approval status. | Unapproved templates cannot be sent. |
| FR-MKT-2 | Automation rules built in the UI (trigger → conditions → actions → waits). | Consent and suppression always enforced. Every send idempotent. |
| FR-MKT-3 | Campaigns with UTM generation and attribution to leads. | Leads show campaign and source. |

### 5.8 SEO (Phases 4, 6 and 11)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-SEO-1 | Per-page metadata with sensible automatic defaults and overrides. | Titles and descriptions unique site-wide (validator report). |
| FR-SEO-2 | Structured data: Organization, WebSite, BreadcrumbList, LocalBusiness (+Geo, OpeningHours) and Offer on centre pages, FAQPage where FAQs are visible, Article on posts. | Passes schema validation. Data always matches visible content (no mismatched schema). |
| FR-SEO-3 | XML sitemaps regenerated on publish; robots rules; canonical URLs. | Sitemap contains every published indexable page and nothing unpublished. |
| FR-SEO-4 | Redirect manager with automatic 301 on slug change and a 404 monitor. | Old URLs redirect in one hop. Redirect loops prevented at save. Redirect changes made by hand go live only after independent approval, and are published by someone other than the approver. An automatic slug-change redirect goes live with its approved page (OD-66, BR-CMS-6, BR-CMS-9). |
| FR-SEO-5 | Inventory-driven content blocks (e.g. city FAQs answered from live data). | Numbers always match the inventory service at render time. |

### 5.9 Analytics (Phase 12)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-AN-1 | Consent-aware tag loading; first-party attribution capture onto leads. | No marketing tags fire before consent where consent is required (D-18). A new or changed tracking ID, analytics identifier or advertising pixel becomes active only after independent approval and publication (OD-71, BR-CMS-9). The same applies to tag configurations in an external tag manager. A tag manager that cannot enforce it is not enabled for live tracking (OD-75). |
| FR-AN-2 | Dashboards: funnel (visits → enquiries → tours → deals), lead response SLA, occupancy and availability by location, content freshness. | Figures reconcile with source tables. |

### 5.10 Concierge (Phase 13, future)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-AI-1 | Conversational workspace finder answering from canonical inventory, availability and approved content only; hands off to a tour or enquiry. | Never states prices or availability not returned by the platform's own services. Logs conversations under the privacy rules. |

## 6. Non-functional requirements

| ID | Area | Requirement |
|---|---|---|
| NFR-PERF-1 | Web vitals | p75 mobile LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 on all public templates |
| NFR-PERF-2 | Payload | non-3D pages: ≤ 90 KB gz first-party JS; hero image ≤ 200 KB at mobile size |
| NFR-PERF-3 | Server | p95 TTFB ≤ 600 ms for uncached HTML; availability endpoint p95 ≤ 300 ms |
| NFR-PERF-4 | 3D | default GLB budget per floor ≤ 15 MB compressed, viewer interactive ≤ 5 s on a mid-range phone over 4G [DEFAULT; validated in Phase 7] |
| NFR-A11Y-1 | Accessibility | WCAG 2.2 AA for public site and back-office core flows |
| NFR-SEC-1 | Security | `SECURITY_MODEL.md` controls; zero High/Critical findings at launch |
| NFR-REL-1 | Availability | 99.9% monthly for public site (host dependent, D-14) |
| NFR-REL-2 | Data | RPO ≤ 1 h, RTO ≤ 4 h (runbook §5) |
| NFR-SCALE-1 | Capacity | design for ≥ 100 locations and ≥ 10,000 spaces without architectural change |
| NFR-PRIV-1 | Privacy | data minimisation, consent records, DSR workflow; legal review (D-15) |
| NFR-MAINT-1 | Maintainability | Laravel conventions, Pint-clean, tests per business rule, ADRs for significant decisions |
| NFR-BROWSER-1 | Support | last 2 versions of evergreen browsers, iOS Safari 16+, Android Chrome; graceful degradation without WebGL |
| NFR-LOC-1 | Locale | en-IN formatting (₹, Indian digit grouping), Asia/Kolkata display time [DEFAULT pending D-01, D-16] |

## 7. Working assumptions (unconfirmed; none is treated as fact)

| # | Assumption | Status |
|---|---|---|
| A-1 | SmartGro operates in India, prices in INR, content in English | **Confirmed 2026-10-24** (OD-02, OD-05). INR is implied by India; it is restated when the pricing brief arrives. |
| A-2 | ~~No existing system must remain authoritative.~~ **Withdrawn 2026-10-24** on owner instruction: no assumption is made. See `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md`. **Later confirmed by the owner (OD-06): no existing operational software.** | resolved |
| A-3 | No customer self-registration or member portal at launch | deferred to Phase 8 (D-20) |
| A-4 | ~~No existing website whose URLs must be preserved~~ **Superseded:** an existing WordPress site exists at smartgro.space (OD-11). Its URLs are inventoried and must be redirected at launch (Phase 11). | resolved |
| A-5 | Tours are free and do not need payment | requires client input (CLIENT_INPUTS C-4.3) |

## 8. Open decisions
Listed with classification and recommended answers in `../decisions/DECISION_REGISTER.md`. Client-facing questions are in `CLIENT_INPUTS_REQUIRED.md`.
