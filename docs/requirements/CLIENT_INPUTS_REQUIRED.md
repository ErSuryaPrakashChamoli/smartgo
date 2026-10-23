# SmartGro — Client Inputs Required

| | |
|---|---|
| Status | **OPEN.** Nothing below has been confirmed by the client. |
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
| C-1.2 | Countries and cities SmartGro operates in or plans to within 24 months | currency, time zone, language, data residency, URL structure | India, INR, Asia/Kolkata (assumption A-1) | Phase 1 (ERD/IA) |
| C-1.3 | Positioning statement, brand story, founding year, leadership bios (if public) | About page, tone of voice, trust | none | Phase 6 |
| C-1.4 | Target customers by priority (startups, SMEs, enterprises/GCCs, freelancers) | IA emphasis, journeys, sales routing | premium SMEs + enterprise, with flexible products as entry points | Phase 2 |
| C-1.5 | Canonical brand spelling ("SmartGro"), primary domain, any secondary domains | brand consistency, SEO, email sender domains | "SmartGro" per brief | Phase 2 |
| C-1.6 | Verifiable claims SmartGro may make (member counts, client logos with permission, awards, certifications) | trust signals must be true | publish no claims until documented | Phase 6 |

## 2. Locations and inventory (D-02, D-05)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-2.1 | List of locations: name, building, full address, floors operated, status (open / pre-opening / planned), opening dates | the whole public site and inventory model are built around real locations | none (must be provided) | Phase 1 (ERD validation), Phase 5 |
| C-2.2 | **First location's full inventory** in the SG-INV-1.0 template (`../templates/inventory-import/`) | validates the data model against reality before it is built | none | Phase 1 AC-1.3 |
| C-2.3 | Are dedicated desks sold and tracked per individual seat? Are hot desks pooled capacity? | data model for seats (`space_seats`) and availability maths | per-seat dedicated desks; pooled hot desks | Phase 1 (ERD) |
| C-2.4 | Can one location span more than one building? | ERD M-1 | no | Phase 1 (ERD) |
| C-2.5 | Canonical city names for URLs (e.g. Bengaluru vs Bangalore; Gurugram vs Gurgaon) | permanent URLs; aliases become redirects | official current names; common aliases redirect | Phase 6 |
| C-2.6 | Operating hours per location, holidays, after-hours access rules | availability, tour slots, booking windows | none | Phase 5 |
| C-2.7 | Amenities per location and space (only real ones) | listings and filters | none | Phase 5 |

## 3. Products and pricing (D-03, D-04)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-3.1 | Products actually sold: private office, managed office, dedicated desk, hot desk, meeting room, board room, day pass, virtual office (address / GST registration), event space, others | sitemap, filters, space types, templates | sell only what is confirmed; the space-type list is provisional | Phase 1 (IA) |
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
| C-6.1 | **Original SmartGro logo files:** vector master (SVG, AI, EPS or PDF) plus PNG; colour, mono and reversed versions; any brand guidelines | the logo must be used as supplied, never recreated. **Not found in the project environment (searched 2026-10-24).** | none; required | **Phase 2** |
| C-6.2 | Brand typefaces and licences (if any) | typography system | a licensed web font chosen in Phase 2 if none exists | Phase 2 |
| C-6.3 | Existing professional photography per location, with ownership/licence; plans for new shoots | real imagery only (BR-MED) | professional shoot per open location before launch | Phase 6 |
| C-6.4 | Floor plans (CAD, PDF or image) per floor | interactive floor plans, 3D fallback | none | Phase 7 |
| C-6.5 | 3D scans or architectural models, if any (provider, format, date); budget for new scans | 3D twin scope | pilot one flagship location with a measured scan | Phase 7 |
| C-6.6 | Testimonials and case studies, with written consent | trust content (BR-CMS-7) | publish none without consent | Phase 6 |

## 7. Digital, legal and accounts (D-10, D-14, D-15, D-16, D-18, D-19, D-29)

| Ref | Input needed | Why it matters | Recommended default | Blocks |
|---|---|---|---|---|
| C-7.1 | Existing website(s) and URLs that must be preserved or redirected | SEO equity at launch | crawl and map 301s if a site exists | Phase 1 (IA) / 11 |
| C-7.2 | Hosting, domain registrar, DNS (Cloudflare) and account ownership | deployment and legal control | client-owned accounts; managed Laravel hosting + Cloudflare | Phase 3 (staging) |
| C-7.3 | Email sending domain and provider; WhatsApp Business number and provider; SMS provider with DLT registration | confirmations and follow-up | transactional email at launch; WhatsApp when the number is verified | Phase 8/10 |
| C-7.4 | Privacy notice, terms, cookie policy, grievance officer contact, data retention periods | legal compliance (DPDP Act 2023 and Rules to be confirmed by counsel) | counsel to provide | Phase 6 |
| C-7.5 | Analytics and advertising accounts (GA4, Google Ads, Meta, LinkedIn); consent approach | measurement and attribution | GA4 + GTM with consent mode | Phase 6/12 |
| C-7.6 | Languages for the website | content model | English only | Phase 4 |

## 8. Existing systems (D-06)

See `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md` §2 (questions Q1–Q10). **This is the most consequential open input.**

---

## Summary: items blocking Phase 1 completion

C-1.2 · C-2.1 · C-2.2 · C-2.3 · C-2.4 · C-3.1 · C-5.1 · C-7.1 · D-06 (Q1–Q3, Q7), or an explicit owner-approved deferral with the recommended default.
