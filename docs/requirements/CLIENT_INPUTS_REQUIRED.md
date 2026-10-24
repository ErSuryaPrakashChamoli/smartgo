# SmartGro — Client Inputs Required

| | |
|---|---|
| Status | **OPEN, partly answered.** The owner answered C-1.2, C-1.5, C-2.3, C-3.1, C-7.1, C-7.6 and D-06 on 2026-10-24 (`../decisions/DECISION_REGISTER.md` §0); C-2.1 and C-6.1 are partly answered. Everything else is unconfirmed. |
| Last updated | 2026-10-24 |
| Audience | SmartGro (client) leadership, operations, sales, marketing, finance, legal |
| How to use | Answer each item, or reply "use the recommended default". A default is **not** a fact until the client confirms it. |

The platform must not invent company information, locations, products, prices, occupancy or operating rules. These inputs replace the placeholders and defaults in the requirements.

Legend: **Blocks** = the phase that cannot finish without it.

---

## 1. Company and market (D-01, D-22)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-1.1 | Legal entity name, registered address, CIN/registration number, GSTIN | footer, legal pages, invoices, `Organization` structured data | none (must be provided) | Phase 6 |
| C-1.2 | ~~Countries and cities~~ **Answered 2026-10-24 (OD-02, OD-03):** India; target markets Noida, Gurgaon, Delhi, Faridabad, Ghaziabad; operating in Noida | — | — | resolved |
| C-1.3 | Positioning statement, brand story, founding year, leadership bios (if public) | About page, tone of voice, trust | none | Phase 6 |
| C-1.4 | Target customers by priority (startups, SMEs, enterprises/GCCs, freelancers) | IA emphasis, journeys, sales routing | premium SMEs + enterprise, with flexible products as entry points | Phase 2 |
| C-1.5 | ~~Brand spelling and domain~~ **Answered (OD-01):** SmartGro · smartgro.space · tagline "WORK SMART. GROW TOGETHER." | — | — | resolved |
| C-1.6 | Verifiable claims SmartGro may make (member counts, client logos with permission, awards, certifications) | trust signals must be true | publish no claims until documented | Phase 6 |

## 2. Locations and inventory (D-02, D-05)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-2.1 | **Partly answered (OD-08):** four Noida properties (A-70, Sector 2 · A-71, Sector 2 · A-23, Sector 3 · H-105, Sector 62); 10 floors in total. **Still needed:** floor allocation per property and building levels (Q-OW-1); full addresses (PIN code, building names); status and opening dates per property | property pages and inventory are built around real properties; nothing is created by assumption | none (must be provided) | Phase 1 (ERD validation), Phase 5 |
| C-2.2 | **First property's full inventory** in the SG-INV-1.1 template (`../templates/inventory-import/`) | validates the data model against reality before it is built | none | Phase 1 AC-1.4 |
| C-2.3 | ~~Seat tracking~~ **Answered (OD-10):** individual, pooled and hybrid-within-property are all supported. **Still asked:** hybrid inside one zone? (Q-OW-7) | — | not needed unless confirmed | Phase 5 |
| C-2.4 | Is each address a separate property, or do any form one centre spanning buildings (e.g. A-70 and A-71, Sector 2)? (Q-OW-2) | property pages, tours and inventory grouping | the model supports both | Phase 1 (ERD) |
| C-2.5 | Public city names for URLs: Gurgaon or Gurugram; Delhi or New Delhi (Q-OW-4). Show target cities without a property? (Q-OW-5) | permanent URLs; avoid implying non-existent centres | owner's preferred names; hide cities without a property | Phase 6 |
| C-2.6 | Operating hours per location, holidays, after-hours access rules | availability, tour slots, booking windows | none | Phase 5 |
| C-2.7 | Amenities per location and space (only real ones) | listings and filters | none | Phase 5 |

## 2A. New items from the owner decisions of 2026-10-24

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-2.8 | Operating model per property: owned and operated, or leased and operated. Can it differ within a property? Track lease end and lock-in dates internally? (Q-OW-3) | BR-PROP-2/5; avoids selling beyond a lease | record per property; track lease end dates (restricted access) | Phase 5 |
| C-3.8 | **Pricing configuration:** section 9 onward of the owner's brief was not received (Q-OW-9) | pricing, payment and cancellation rules per category and property | — | Phase 5 |
| C-3.9 | Marketing priority among the eight customer segments (optional) | homepage emphasis, segment landing pages | no priority until set | Phase 2/6 |

## 3. Products and pricing (D-03, D-04)

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
| C-5.1 | Team structure and who does what (marketing, content approval, operations, centre managers, finance, sales, front desk) | role and permission matrix (`../security/SECURITY_MODEL.md` §3.1) | matrix as drafted | Phase 1 (role sign-off), Phase 3 |
| C-5.2 | Sales stages, lead assignment rules (by city, location, product, seat band), response-time target | CRM configuration, SLA alerts | stages in the ERD; 15-minute first response in business hours | Phase 9 |
| C-5.3 | Who approves website content; one approver or two | publishing workflow | one approver; nobody approves their own work | Phase 4 |
| C-5.4 | Lost-reason and disqualification lists | CRM reporting | draft lists provided in Phase 9 | Phase 9 |

## 6. Brand and assets (D-11, D-12, D-13)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-6.1 | **Original SmartGro logo files.** The owner states the logo was supplied as an image in conversation, but the file is **not in the project and not available to the working session** (checked 2026-10-24). Please place the original (SVG preferred; otherwise AI/EPS/PDF) plus a high-resolution PNG at `resources/brand/source/`. Include reversed/mono versions and guidelines if they exist. | the logo must be used as supplied, never recreated | none; required | **Phase 2** |
| C-6.2 | Brand typefaces and licences (if any) | typography system | a licensed web font chosen in Phase 2 if none exists | Phase 2 |
| C-6.3 | Existing professional photography per location, with ownership/licence; plans for new shoots | real imagery only (BR-MED) | professional shoot per open location before launch | Phase 6 |
| C-6.4 | Floor plans (CAD, PDF or image) per floor | interactive floor plans, 3D fallback | none | Phase 7 |
| C-6.5 | 3D scans or architectural models, if any (provider, format, date); budget for new scans | 3D twin scope | pilot one flagship location with a measured scan | Phase 7 |
| C-6.6 | Testimonials and case studies, with written consent | trust content (BR-CMS-7) | publish none without consent | Phase 6 |

## 7. Digital, legal and accounts (D-10, D-14, D-15, D-16, D-18, D-19, D-29)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-7.1 | ~~Existing website~~ **Answered (OD-11):** smartgro.space (WordPress); 57 URLs inventoried. **Owner action:** the site shows signs of compromise (`../research/EXISTING_WEBSITE_AUDIT.md` §2). **Still asked:** may existing content be reused? (Q-OW-6) | SEO equity; security | 301 legitimate URLs, 410 spam | Phase 11 |
| C-7.2 | Hosting, domain registrar, DNS (Cloudflare) and account ownership | deployment and legal control | client-owned accounts; managed Laravel hosting + Cloudflare | Phase 3 (staging) |
| C-7.3 | Email sending domain and provider; WhatsApp Business number and provider; SMS provider with DLT registration | confirmations and follow-up | transactional email at launch; WhatsApp when the number is verified | Phase 8/10 |
| C-7.4 | Privacy notice, terms, cookie policy, grievance officer contact, data retention periods | legal compliance (DPDP Act 2023 and Rules to be confirmed by counsel) | counsel to provide | Phase 6 |
| C-7.5 | Analytics and advertising accounts (GA4, Google Ads, Meta, LinkedIn); consent approach | measurement and attribution | GA4 + GTM with consent mode | Phase 6/12 |
| C-7.6 | ~~Languages~~ **Answered (OD-05):** English only | — | — | resolved |

## 8. Existing systems (D-06)

**Answered 2026-10-24 (OD-06): no existing operational software.** The SmartGro platform will be the master. The questionnaire in `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` §2 is only needed if a system is introduced later.

---

## Summary: items blocking Phase 1 completion

C-2.1 (floor allocation, addresses) · C-2.2 (first property's inventory) · C-2.4 (property grouping) · C-5.1 (team structure) · C-6.1 (logo file, also blocks Phase 2), or an explicit owner-approved deferral with the recommended default. C-1.2, C-1.5, C-2.3, C-3.1, C-7.1, C-7.6 and D-06 were answered on 2026-10-24.
