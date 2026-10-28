# SmartGro — Client Inputs Required

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval.** Answered items are marked. Open items are deferred with their phase gates (`../decisions/DECISION_REGISTER.md`). |
| Last updated | 2026-10-28 |
| Audience | SmartGro (client) leadership, operations, sales, marketing, finance, legal |
| How to use | Answer each item, or reply "use the recommended default". A default is **not** a fact until the client confirms it. |

The platform must not invent company information, locations, products, prices, occupancy or operating rules. These inputs replace the placeholders and defaults in the requirements.

Legend: **Blocks** = the phase that cannot finish without it.

---

## 1. Company and market (D-01, D-22)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-1.1 | Legal entity name, registered address, CIN/registration number, GSTIN | footer, legal pages, invoices, `Organization` structured data | none (must be provided) | Phase 6 |
| C-1.2 | ~~Countries and cities~~ **Answered 2026-10-24 (OD-02, OD-03):** India; target markets Noida, Gurugram (official name, OD-16), Delhi, Faridabad, Ghaziabad; operating in Noida | — | — | resolved |
| C-1.3 | Positioning statement, brand story, founding year, leadership bios (if public) | About page, tone of voice, trust | none | Phase 6 |
| C-1.4 | Target customers by priority (startups, SMEs, enterprises/GCCs, freelancers) | IA emphasis, journeys, sales routing | premium SMEs + enterprise, with flexible products as entry points | Phase 2 |
| C-1.5 | ~~Brand spelling and domain~~ **Answered (OD-01):** SmartGro · smartgro.space · tagline "WORK SMART. GROW TOGETHER." | — | — | resolved |
| C-1.6 | Verifiable claims SmartGro may make (member counts, client logos with permission, awards, certifications) | trust signals must be true | publish no claims until documented | Phase 6 |

## 2. Locations and inventory (D-02, D-05)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-2.1 | **Partly answered (OD-08):** four Noida properties (A-70, Sector 2 · A-71, Sector 2 · A-23, Sector 3 · H-105, Sector 62); 10 floors in total. **Still needed:** floor allocation per property and building levels (Q-OW-1); full addresses (PIN code, building names); status and opening dates per property | property pages and inventory are built around real properties; nothing is created by assumption | none (must be provided) | Phase 1 (ERD validation), Phase 5 |
| C-2.2 | **First property's full inventory** in the SG-INV-1.3 template, including floors per property. **Deferred to the Phase 5 entry gate** (option P-1, OD-30). | validates the data model against reality before inventory code is merged | none | Phase 5 entry |
| C-2.3 | ~~Seat tracking~~ **Answered (OD-10, OD-15):** individual, pooled, mixed within a property, and **hybrid zones** | — | — | resolved |
| C-2.4 | ~~Property grouping~~ **Answered (OD-13):** each address is its own centre | — | — | resolved |
| C-2.5 | ~~City names and visibility~~ **Answered (OD-16, OD-18):** official current names (Gurugram, Delhi …); cities hidden by default with an admin toggle. A visible city without a property shows a "coming soon / register interest" page (OD-22). | — | — | resolved |
| C-2.6 | Operating hours per location, holidays, after-hours access rules | availability, tour slots, booking windows | none | Phase 5 |
| C-2.7 | Amenities per location and space (only real ones) | listings and filters | none | Phase 5 |
| C-2.9 | **New (2026-10-27, OD-113):** "Getting here" details per location, only as they actually are: nearest metro station and the walk in minutes, buses, parking, accessibility (step-free entrance, lifts, accessible toilets), and each building's map coordinates | the location page's "Getting here" block, the comparison page and the map link (FR-DISC-7). The fields exist; they stay empty until supplied | none (nothing is shown until supplied) | Phase 6 |

## 2A. New items from the owner decisions of 2026-10-24

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-2.8 | ~~Operating model~~ **Answered (OD-14):** all four current properties are owned and operated. Lease tracking is deferred until a leased property exists. | — | — | resolved |
| C-3.8 | **Pricing configuration:** section 9 was not received. A proposal was drafted under option P-2 (`PRICING_CONFIGURATION_PROPOSAL.md`). **Owner to review and fill in decisions PR-1…PR-11.** | pricing, payment and cancellation rules per category and property | proposal values are not approved | Phase 5 |
| C-3.9 | Marketing priority among the eight customer segments (optional) | homepage emphasis, segment landing pages | no priority until set | Phase 2/6 |

## 3. Products and pricing (D-03, D-04)

Round 42: the pricing decisions PR-1 to PR-11, price values, the categories each location offers, location content and the legal and operational inputs for Phase 5 are collected with `../templates/phase5-owner-inputs/SmartGro_Phase5_Owner_Inputs_SG-INP-1.0.xlsx`, alongside the inventory template SG-INV-1.3.

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-3.1 | ~~Products actually sold~~ **Answered (OD-07):** seven categories. **Still needed:** each category's rules (durations, workflow, approval, payment, cancellation, required details) | category configuration (ERD §4.1) | proposed defaults in ERD §4.1, not approved | Phase 5/8 |
| C-3.2 | Pricing units per product (per seat/month, per office/month, per hour, per day) | price-plan model | as listed in `../architecture/DATABASE_ERD.md` §4 | Phase 5 |
| C-3.3 | Minimum terms, lock-in, security deposit, notice periods | business rules, proposal content, FAQs | none | Phase 5 |
| C-3.4 | **Public price display policy:** show "from" prices, exact prices or none, per product | conversion, and differentiation (no Indian competitor showed centre prices) | "from" prices for flexible products; private offices "from ₹ per seat" if approved, otherwise "request a quote" | Phase 5/6 |
| C-3.5 | GST treatment and display (inclusive or "+ GST") | legal display, invoices | store and show tax-exclusive with "+ GST" | Phase 5 |
| C-3.6 | Who may create, approve and change prices and discounts; any price floors | four-eyes approval (BR-PR-2, BR-PR-5) | Finance creates, a second Finance or Management user approves | Phase 5 |
| C-3.7 | Current offers and their terms | offers module | none | Phase 5 |

## 4. Operating policies (D-07, D-15, D-28)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-4.1 | Online booking scope at launch: meeting rooms, day passes, or enquiry only? | Phase 8 scope; payment integration | live tour scheduling at launch; instant room booking only with payments approved | Phase 8 |
| C-4.2 | Cancellation and refund policy per product | checkout, terms, automation | none (legal/finance) | Phase 8 |
| C-4.3 | Tour rules: days and times, slot length, how many tours at once, who hosts | tour scheduler (BR-TR) | 30-minute slots within operating hours, one tour per host | Phase 8 |
| C-4.4 | Booking windows and buffers for rooms (how far ahead; minimum notice; cleaning time) | BR-BK-7 | 30 min notice, 60 days ahead | Phase 8 |
| C-4.5 | Virtual-office documentation and compliance process (if sold) | assisted checkout, document collection | assisted enquiry only | Phase 8 |

## 5. Sales and team (D-09, D-17)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-5.1 | ~~Team structure~~ **Approved as drafted (D-33, OD-28):** role matrix in `../security/SECURITY_MODEL.md` §3.1 | — | — | resolved (adjust in Phase 3) |
| C-5.2 | Sales stages, lead assignment rules (by city, location, product, seat band), response-time target | CRM configuration, SLA alerts | stages in the ERD; 15-minute first response in business hours | Phase 9 |
| C-5.3 | ~~Who approves website content; one approver or two~~ **Answered (OD-56, OD-65, OD-73):** one approval by a person other than the author is enough. Nobody, including the Super Admin, publishes a version they approved. The author may publish if they are not the approver (D-17 resolved). | publishing workflow | — | resolved |
| C-5.4 | Lost-reason and disqualification lists | CRM reporting | draft lists provided in Phase 9 | Phase 9 **Round 42 (OD-111 e):** no draft list is proposed; the Owner supplies the reasons on the "Closing reasons" sheet of `../templates/phase5-owner-inputs/SmartGro_Phase5_Owner_Inputs_SG-INP-1.0.xlsx`, and the Super Admin enters them in Sales → Closing reasons. |

## 6. Brand and assets (D-11, D-12, D-13)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-6.1 | **Original SmartGro logo files.** **Raster logo received 2026-10-24** (OD-39; 388 × 218 px WebP, now at `resources/brand/source/smartgro-logo.webp`). Still needed: the vector master (SVG preferred; otherwise AI/EPS/PDF) or a high-resolution PNG (at least 2000 px wide), plus reversed/mono versions and guidelines if they exist. | the logo must be used as supplied, never recreated | none; required | **Phase 2** |
| C-6.2 | Brand typefaces and licences (if any) | typography system | a licensed web font chosen in Phase 2 if none exists | Phase 2 |
| C-6.3 | Existing professional photography per location, with ownership/licence; plans for new shoots | real imagery only (BR-MED) | professional shoot per open location before launch | Phase 6 |
| C-6.4 | Floor plans (CAD, PDF or image) per floor | interactive floor plans, 3D fallback | none | Phase 7 |
| C-6.5 | 3D scans or architectural models, if any (provider, format, date); budget for new scans. **Supplier file specification: authoritative (owner, OD-119 b).** The back office checks every file against it at upload and refuses a file that breaks any rule, listing every reason; nothing is stored until a file passes. Any change to a limit changes `config/twin.php` and this item together.<br>**Format:** one binary glTF 2.0 file (`.glb`) per floor, self-contained: textures and data embedded, nothing in a separate file or at an outside address. The file may require only features the viewer can show: Draco or Meshopt compression, mesh quantization, KTX2 (Basis Universal) or WebP textures, texture transforms and unlit materials.<br>**Hard limits (owner-approved, OD-119 a):** at most **15 MB** per file and **500,000 triangles** per model, each mesh counted once for every node that shows it, and Draco meshes counted by the triangles their compressed data holds.<br>**Safeguards (chosen under OD-119; OD-119 d):** every surface compressed with Draco or Meshopt; at most **500 pieces drawn separately** (one for each part of each mesh shown); at most **100 materials**; at most **64 textures**, each a PNG, JPEG, WebP or KTX2 image of at most **4,096 pixels** on its longest side, and **33,554,432 pixels** in all (for example eight textures of 2048 × 2048); at most **128 MB** of geometry once decompressed; at most **10,000 nodes** and a **4 MB** description (the file's JSON part); modelled in metres, measuring **1 m to 1,000 m** along the longest side; a file whose checks take more than **10 seconds** is refused as too complex.<br>**Structure:** a scene to show; nodes in trees (each node with one parent at most); every reference and data range complete; positions state their bounds (min and max), as glTF requires.<br>**Anchors:** named nodes `SG_SPACE_<space code>` (the space code from the inventory: capitals, digits and inner hyphens), `SG_AMENITY_<name>` (lowercase, words joined by hyphens) and `SG_CAM_<number>` (from 1; each camera position looks along the node's −Z axis, as glTF cameras do); each name used once; at least one anchor.<br>**Capture type:** state whether the file is a measured scan of the location, an architectural model or a conceptual model: only a measured scan may be called a scan, and the others always carry a notice.<br>**Not a performance promise (OD-119 c):** a file within these limits can still be slow to load on a phone over 4G (measurements: `../testing/evidence/phase7/2026-10-28-od119/performance.json`); NFR-PERF-4 is judged on the pilot scene. | 3D twin scope | pilot one flagship location with a measured scan | Phase 7 |
| C-6.6 | Testimonials and case studies, with written consent | trust content (BR-CMS-7) | publish none without consent | Phase 6 |

## 7. Digital, legal and accounts (D-10, D-14, D-15, D-16, D-18, D-19, D-29)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-7.1 | ~~Existing website~~ **Answered (OD-11):** smartgro.space (WordPress); 57 URLs inventoried. **Owner action:** the site shows signs of compromise (`../research/EXISTING_WEBSITE_AUDIT.md` §2). **Still asked:** may existing content be reused? (Q-OW-6) | SEO equity; security | 301 legitimate URLs, 410 spam | Phase 11 |
| C-7.2 | Hosting, domain registrar, DNS (Cloudflare) and account ownership | deployment and legal control | client-owned accounts; managed Laravel hosting + Cloudflare | Phase 3 (staging) |
| C-7.3 | Email sending domain and provider; WhatsApp Business number and provider; SMS provider with DLT registration | confirmations and follow-up | transactional email at launch; WhatsApp when the number is verified | Phase 8/10 |
| C-7.4 | Privacy notice, terms, cookie policy, grievance officer contact, data retention periods | legal compliance (DPDP Act 2023 and Rules to be confirmed by counsel) | counsel to provide | Phase 6 |
| C-7.5 | Analytics and advertising accounts (GA4, Google Ads, Meta, LinkedIn); consent approach. Tracking changes need independent approval before going live (OD-71). A tag manager is optional. If one is used, it must enforce the same approval, publication and audit before it is enabled for live tracking (OD-75). | measurement and attribution | GA4 + GTM with consent mode | Phase 6/12 |
| C-7.6 | ~~Languages~~ **Answered (OD-05):** English only | — | — | resolved |

## 8. Existing systems (D-06)

**Answered 2026-10-24 (OD-06): no existing operational software.** The SmartGro platform will be the master. The questionnaire in `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` §2 is only needed if a system is introduced later.

---

## Summary: items blocking Phase 1 completion

C-2.1 (floor allocation, addresses) · C-2.2 (first property's inventory) · C-2.4 (property grouping) · C-5.1 (team structure) · C-6.1 (logo file, also blocks Phase 2), or an explicit owner-approved deferral with the recommended default. C-1.2, C-1.5, C-2.3, C-3.1, C-7.1, C-7.6 and D-06 were answered on 2026-10-24.
