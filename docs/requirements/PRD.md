# SmartGro — Product Requirements Document

| | |
|---|---|
| Status | **DRAFT — Phase 1, ready for review.** Not approved. |
| Version | 0.1 (2026-10-24) |
| Companion docs | `INFORMATION_ARCHITECTURE.md` (sitemap, journeys) · `BUSINESS_RULES.md` (BR-*) · `../architecture/*` · `../security/SECURITY_MODEL.md` (role matrix) · `../research/MARKET_BENCHMARK.md` |
| Decision references | D-xx items are listed in `../phases/PHASE_0_DISCOVERY_REPORT.md` §5 |

---

## 1. Product summary

SmartGro is a premium coworking and managed-workspace brand ("WORK SMART. GROW TOGETHER."). The platform combines a premium public website with an operating back office. Customers discover and evaluate real workspaces (including interactive 3D twins) and book tours or spaces. SmartGro's team runs inventory, content, sales and marketing from **one source of truth**, without needing technical skills.

**Confirmed facts:** brand name, tagline, provisional palette, and the target technology stack (from the brief).
**Not yet known:** company profile, locations, products, prices, operating policies, team structure and existing systems (see §8). This PRD defines *how* the platform behaves. It does not invent *what* SmartGro sells.

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

**Customers:** startup founder or SME owner (2–25 seats), enterprise/GCC real-estate or facilities lead (50+ seats), freelancer or consultant (day pass, meeting room), SME needing a registered business/GST address (virtual office ◇), event organiser ◇.

**Staff:** Marketing Manager (approver), Content Editor, SEO Specialist, Operations Manager, Centre Manager, Finance, Sales Manager, Sales Executive, Front Desk, 3D Technician, Analyst, Platform Admin. Capabilities per `SECURITY_MODEL.md` §3.1.

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
Member billing and access control (doors, Wi-Fi), visitor management, facility helpdesk, native mobile apps, multi-country/multi-currency, and multiple languages (D-16).

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
| FR-CMS-2 | Workflow Draft → Submitted → Approved → Published, with rejection notes and scheduling (BR-CMS-1…5). | Author cannot self-approve. Published state never changes until a publish action runs. Full revision history with rollback. |
| FR-CMS-3 | Signed preview links (BR-CMS-4). | Preview shows exactly the draft, carries `noindex`, and expires. |
| FR-CMS-4 | Navigation, header and footer editors; links pick from pages and locations, not typed URLs (external allowed). | Renaming a page's slug never breaks menus. |
| FR-CMS-5 | Media library with upload, alt text, focal point, origin, licence and expiry; responsive renditions (BR-MED-*). | Cannot publish an image without alt text (or a decorative flag). AI/concept assets are blocked from location galleries. Licence expiry unpublishes. |
| FR-CMS-6 | Location and offering profiles (marketing face) separate from operational records. | Marketing users cannot edit capacity, status, availability or price from any screen (policy test per role). |
| FR-CMS-7 | Blog/insights, FAQs (global, location, product scope), testimonials, case studies, legal pages. | Testimonials and case studies require consent-on-file to publish. |
| FR-CMS-8 | Brand settings: contact numbers, social links, tracking IDs, limited design tokens with automatic contrast validation. | A token that fails WCAG AA for its role is rejected with an explanation. |

### 5.3 Inventory, pricing and availability (Phase 5)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-INV-1 | Guided setup: City → Building → Location → Floors → Spaces (wizard), plus bulk import from a spreadsheet template. | A non-technical ops user can create a location with 3 floors and 40 spaces in one session. Import validates and reports row errors without partial writes. |
| FR-INV-2 | Space operational status changes with reason; history kept. | Every change is audit-logged and emits `SpaceAvailabilityChanged`. |
| FR-INV-3 | Allocations (long-term occupancy) with start/end/notice; seat-level for desk zones if D-05. | Availability states follow BR-AV-1…6 exactly (unit tests per rule). |
| FR-INV-4 | Products, offerings and price plans with approval (BR-PR-*). | Four-eyes enforced. Only one active plan per scope and unit at a time. |
| FR-INV-5 | Offers with eligibility, dates and approval. | Expired offers disappear from the site automatically. |
| FR-INV-6 | Availability service used by every surface (site, API, 3D, CRM, booking). | No surface computes availability independently (arch test). |

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
| FR-BK-5 | Staff booking console: calendar per location/space, create/move/cancel with reasons, overrides per permission. | Overrides audit-logged. Overlap rules can never be bypassed. |

### 5.6 CRM (Phase 9)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-CRM-1 | Lead inbox with filters, ownership, status, SLA timers, duplicate detection (BR-CRM-2). | Sales Exec sees only own or scoped leads (policy test). |
| FR-CRM-2 | Assignment rules (round-robin, fixed, least-loaded) by city, location, product or seat band. | Deterministic assignment with tests per strategy. Unmatched leads go to the manager queue. |
| FR-CRM-3 | Activities (call, WhatsApp, email, meeting, note, task) with reminders. | One-tap call/WhatsApp from the lead screen logs an activity. |
| FR-CRM-4 | Pipeline (configurable stages), deals with proposed spaces from canonical inventory. | Proposed spaces show live availability. Won deals create allocations through the inventory service (BR-CRM-7). |
| FR-CRM-5 | Lost reasons and disqualification reasons (managed lists). | Required on status change. |
| FR-CRM-6 | Export with permission and audit. | Only Sales Manager+. Export events logged. |

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
| FR-SEO-4 | Redirect manager with automatic 301 on slug change and a 404 monitor. | Old URLs redirect in one hop. Redirect loops prevented at save. |
| FR-SEO-5 | Inventory-driven content blocks (e.g. city FAQs answered from live data). | Numbers always match the inventory service at render time. |

### 5.9 Analytics (Phase 12)

| ID | Requirement | Acceptance |
|---|---|---|
| FR-AN-1 | Consent-aware tag loading; first-party attribution capture onto leads. | No marketing tags fire before consent where consent is required (D-18). |
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

## 7. Assumptions (to be confirmed)

| # | Assumption |
|---|---|
| A-1 | SmartGro operates in India, prices in INR, and content is in English. Indian operators were explicitly listed for research, but the country is not confirmed. |
| A-2 | No existing operational, CRM or member-management system must remain authoritative (D-06). |
| A-3 | No customer self-registration or member portal at launch (D-20). |
| A-4 | No existing website whose URLs must be preserved (D-19). |
| A-5 | Tours are free and do not need payment. |

## 8. Open decisions
Listed in full, with recommended defaults, in `../phases/PHASE_0_DISCOVERY_REPORT.md` §5.
