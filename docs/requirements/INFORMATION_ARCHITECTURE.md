# SmartGro — Information Architecture, Sitemap and User Journeys

| | |
|---|---|
| Status | **DRAFT — Phase 1, ready for review** |
| Last updated | 2026-10-24 |
| Evidence | `../research/MARKET_BENCHMARK.md` (research of 2026-10-24) |
| Decisions | D-02 locations, D-03 products, D-16 languages, D-19 existing site/URLs, D-27 partner pages |

---

## 1. IA principles

1. **Three-level discovery, matching how Indian buyers search:** city → micro-market (business district) → centre. This mirrors observed WeWork India and Awfis hierarchies and the locality-led search behaviour in the research.
2. **One canonical page per search intent.** The research found an Indian operator running two parallel city-page sets that compete with each other, and another with truncated or duplicated slugs. SmartGro has exactly one city page per city, one product × city page per pair, and stable, human-readable slugs managed by the CMS with automatic 301s on change (BR-CMS-6).
3. **City-name aliases redirect.** For example `bangalore` → `bengaluru`, if the owner picks `bengaluru` as canonical (D-02). Aliases are stored as redirects, not duplicate pages.
4. **Every indexable page is server-rendered** with complete content in the HTML (ADR-0010).
5. **Product × city pages exist only when there is real inventory** behind them. There are no thin doorway pages.

---

## 2. Public sitemap (proposed)

Items marked ◇ depend on the owner's product scope (D-03) or business model (D-27).

```
/                                         Home
/workspaces                               All workspace solutions (hub)
  /workspaces/private-offices
  /workspaces/managed-offices             ◇ enterprise / custom-built
  /workspaces/coworking                   hot desk + dedicated desk ◇
  /workspaces/meeting-rooms               ◇
  /workspaces/day-pass                    ◇
  /workspaces/virtual-office              ◇ (GST / business address product)
  /workspaces/event-spaces                ◇
  /workspaces/{product}/{city}            product × city landing (only when inventory exists)
/locations                                All cities + map/list
  /locations/{city}                       City: micro-markets, centres, map/list, FAQs
  /locations/{city}/area/{micro-market}   Micro-market (when ≥ 1 centre)
  /locations/{city}/{location}            Centre page (the "sales kit")
    /locations/{city}/{location}/meeting-rooms/{room}   bookable room ◇
/enterprise                               Enterprise and GCC track ◇
/book-a-tour                              Tour scheduler (also inline on centre pages)
/enquire                                  General enquiry (context-aware)
/offers                                   Active approved offers ◇
/customers                                Case studies index
  /customers/{slug}
/insights                                 Articles / guides
  /insights/{slug}
  /insights/topic/{slug}
/about
/contact
/faqs
/partners/landlords                       ◇ (D-27)
/partners/brokers                         ◇ (D-27)
/careers                                  ◇
/legal/privacy  /legal/terms  /legal/cookies  /legal/cancellation-and-refunds ◇
/booking/{reference}?signature=…          customer self-service (noindex, signed)
/sitemap.xml  /robots.txt
```

Back office (not public): `/admin` (path to be confirmed; ADR-0003).

---

## 3. Page anatomy

### 3.1 Centre page (`/locations/{city}/{location}`), the most important template

The research shows the strongest operators treat this page as a complete sales kit. The weakest ship it as empty placeholders. Proposed sections, in order:

1. **Hero:** real photography (or a real-scan poster), name, micro-market, address, status ("Open", "Opening Mar 2027"), primary CTA "Book a tour", secondary "Explore in 3D" (only if a scene is published).
2. **At a glance:** operating hours, floors, workspace types available, live availability badges (live fragment), "from" price per offering where display is approved (live fragment, BR-PR-3).
3. **Workspaces:** one block per offering. Each shows capacity ranges, what's included and not included (an explicit "not included" list, a trust pattern seen in research), availability state and CTA. Offering context is passed into the enquiry form.
4. **Explore:** 3D scene (lazy) **or** fallback (gallery plus interactive 2D floor plan, BR-3D-5), with an accessible list of spaces.
5. **Gallery:** real photography, captioned by floor and room.
6. **Amenities:** grouped as standard, unique to this centre, and not included.
7. **Getting here:** map, nearest metro stations with walking minutes, bus, cab drop-off, parking, accessibility. None of the Indian operator pages fetched gave metro distances, so this is a differentiator.
8. **Meeting rooms** (if any): list with capacity and "Book" CTA.
9. **Reviews / testimonials:** only verified, consented ones (BR-CMS-7).
10. **FAQs:** centre-specific plus global, with matching FAQPage schema.
11. **Nearby SmartGro centres.**
12. **Tour scheduler:** inline slot picker (BR-TR-1) with WhatsApp/phone alternatives.
13. **Downloadables:** centre brochure PDF (generated or uploaded) ◇.

### 3.2 City page (`/locations/{city}`)
H1 such as "Coworking and office spaces in {City}" (final copy in Phase 6), a micro-market selector, map/list toggle, centre cards (photo, micro-market, workspace types, availability state, "from" price where approved), city FAQs, enterprise CTA, and links to product × city pages.

### 3.3 Product × city page (`/workspaces/{product}/{city}`)
Product explanation and inclusions; only the centres offering that product, with offering-level availability and price display; product FAQs; CTA prefilled with the product.

### 3.4 Enterprise page
Requirement form with seat bands (e.g. 20–49, 50–99, 100–249, 250+), cities, timeline and fit-out need; a process explainer; case studies; compliance documents only if SmartGro actually holds the certifications (no unverified claims).

---

## 4. Navigation (proposed)

* **Primary:** Workspaces (mega-menu by product) · Locations (mega-menu by city → micro-market) · Enterprise · Insights · About.
* **Persistent CTA:** "Book a tour". On mobile, a sticky bottom bar with **Book a tour · WhatsApp · Call**. WhatsApp only if D-10 approves it; no Indian operator page in the research showed an active WhatsApp CTA, so this is a differentiation opportunity.
* **Footer:** cities and centres, products, company, legal, grievance/privacy contact (D-15), social.

---

## 5. User journeys

### Customers

| ID | Persona | Journey | Key screens | Success signal |
|---|---|---|---|---|
| UJ-1 | Startup founder, team of 8, mobile | Search "private office {micro-market}" → city/micro-market page → centre page → explores 3D/photos → sees "from ₹…/seat + GST" → picks tour slot → WhatsApp confirmation → tour → proposal | city, centre, tour scheduler | tour booked in ≤ 3 minutes; lead routed with seats and product |
| UJ-2 | Enterprise / GCC real-estate lead | Enterprise page → case study → requirement form (seat band, cities, timeline) → assigned enterprise sales → multi-centre proposal | enterprise, case study, form | lead qualified and assigned within SLA |
| UJ-3 | Consultant needing a meeting room ◇ | Centre page → meeting room → date and slot → details → (pay) → confirmation with calendar invite | room page, slot picker, booking stepper | confirmed without phone call; cannot double-book |
| UJ-4 | Freelancer wanting a day pass ◇ | Day pass page → centre → date → details → (pay) → pass | day pass, booking | web checkout without app download |
| UJ-5 | SME needing a GST registration address ◇ | Virtual office page → centre → plan → assisted enquiry with document checklist | virtual office | enquiry with plan and centre pre-filled |
| UJ-6 | Returning visitor comparing centres | City page → map/list → opens two centres → enquiry with both centres selected | city, centre, enquiry | multi-location interest captured |

### Staff

| ID | Persona | Journey | Success signal |
|---|---|---|---|
| UJ-7 | Content editor | Opens centre profile → uploads photos (alt text, focal point, origin, licence) → reorders gallery → previews → submits | no code or JSON; can't publish without alt text or licence data |
| UJ-8 | Marketing manager (approver) | "Awaiting approval" dashboard → diff/preview → approve/publish or reject with note | change live within 60 s of publish; revision kept |
| UJ-9 | Operations / Centre manager | Marks office "maintenance" or records an allocation with end date | website, 3D and CRM reflect it within 60 s; no marketing role can override |
| UJ-10 | Sales executive | Gets notified of a new lead → WhatsApp/call from the lead screen → logs outcome → schedules tour → moves stage | first response logged; SLA visible |
| UJ-11 | 3D technician + marketing user | Technician uploads GLB → validation passes → marketing links anchors to spaces from dropdowns → preview → submit | scene published without entering any coordinate |
| UJ-12 | Finance | Creates price plan → a second approver activates → website "from" price updates | four-eyes enforced; audit entry |
