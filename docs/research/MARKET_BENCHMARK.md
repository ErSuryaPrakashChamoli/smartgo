# SmartGro — Market Benchmark (Phase 0)

| | |
|---|---|
| Research date | **2026-10-24** |
| Method | Official websites read through automated fetches: text extraction plus raw-HTML inspection for JSON-LD, form fields and embeds. Three parallel research passes (global, APAC/UK, India). Four key claims were independently re-verified by a second fetch (§7). |
| Limits | **Rendered visuals and photography were not viewed. Mobile layouts were not assessed** beyond markup clues. Sites rendered client-side exposed little content. Requests originated from India, so geo-redirects affected some global pages. Operator-stated figures are reported as the operator's claims, not verified facts. |
| Use | Positioning, customer journeys and industry conventions only. **No layouts, copy, code, assets or visual identity are to be copied.** |

---

## 1. Coverage

| Operator | Entity covered | Access |
|---|---|---|
| WeWork (global) | wework.com | Partially accessible. Several pages geo-redirected to WeWork India; raw HTML blocked by Cloudflare. |
| WeWork India | wework.co.in (separately operated company) | Accessible |
| IWG / Regus / Spaces | regus.com, spacesworks.com, iwgplc.com | **Blocked** (Incapsula bot protection). Findings come **from search snippets only, not page inspection.** |
| Industrious | industriousoffice.com | Partially: location pages are client-rendered; template strings read from HTML |
| Convene | convene.com | Accessible via raw HTML (WebFetch 403) |
| The Executive Centre (TEC) | executivecentre.com (global site, with Indian city pages) | Accessible |
| Servcorp | servcorp.com/en (US market). The India domain did not respond; India is not in its country list. | Accessible (US) |
| Common Ground | commonground.work (Malaysia-headquartered operator) | Accessible |
| Uncommon | uncommon.co.uk (London operator) | Accessible |
| Hubble | hubblehq.com (UK marketplace/broker, not an operator; footer © Yardi Systems) | Accessible except JS-rendered booking form |
| Awfis | awfis.com | HTML accessible, but centre/city content is client-rendered (AngularJS placeholders) |
| Smartworks | smartworksoffice.com (confirmed official domain) | Accessible |
| Mindspace | Two entities. **Mindspace Business Parks REIT** (India office-park landlord, not a flex operator) and **mindspace.me** (international boutique flex operator, **no India presence**). The latter was benchmarked. | Accessible |
| Supplementary | Deskpass India (aggregator) | Accessible |

---

## 2. Key findings by dimension

### 2.1 Product breadth
* The full premium set is private office, managed/enterprise office, coworking (hot or dedicated desk), meeting rooms, event space, day pass and virtual office. TEC has the broadest set (it also sells concierge and server colocation). Smartworks is effectively single-product (enterprise managed offices).
* **In India, the virtual office is a GST/company-registration address product.** Awfis and WeWork India sell it that way. Awfis states a 12-month tenure and a document checklist, with address-only plans checked out online and GST/company plans routed to assisted sales.
* Enterprise starts at about 50 seats at several operators (TEC, Common Ground; Awfis seat bands start at 50–100). The GCC (Global Capability Centre) theme is prominent in India: WeWork India, Smartworks, and TEC's blog.

### 2.2 Discovery
* The dominant hierarchy is **city → neighbourhood/micro-market → centre**, plus **product × city** landing pages (WeWork, WeWork India, TEC, Servcorp, Hubble; Regus per snippets).
* Map/list toggle and product/amenity filters appear at WeWork, WeWork India, Convene, Industrious and Hubble. Hubble (a broker) is the discovery benchmark: filters (size, price, facilities, minimum term), sorting, a map with "search as I move", a shortlist, and an "instant book" filter.
* **No operator offers side-by-side comparison of its own centres.** WeWork global shows a "Compare" button on city cards; Hubble has only a shortlist.

### 2.3 Location pages
* The strongest pages work as a **sales kit**:
  * WeWork global shows a gallery, a "Tour in 3D" link, amenities split into unique / standard / **not included**, prices per product, a transit and commute calculator, reviews and nearby buildings.
  * Convene adds room-by-room capacities, floor plans, 360° tours, transit and parking, and downloadable one-sheets.
* **Floor plans are rare.** Convene shows them; Industrious has them in its template. None of the APAC/UK operators showed them, and none of the Indian operators did either.
* **3D/360 tours are present but secondary:**
  * TEC and Uncommon host theirs on a sub-page or an external site.
  * WeWork India's HTML contains per-centre Matterport links that are **not surfaced** on the page that was read.
  * No Indian operator's page puts a tour in the main experience.
* **Indian operators' centre pages are thin or broken for non-JS clients.** Awfis serves unrendered template placeholders (re-verified, §7). Smartworks centre pages lack capacity, map, prices and FAQs.
* Transit detail varies:
  * Mindspace and Uncommon give walk minutes to stations.
  * WeWork global has a commute calculator.
  * **None of the Indian centre pages that were fetched gave metro distances.**

### 2.4 Pricing visibility
* Low-ticket products (day pass, meeting rooms, memberships, virtual office) commonly show "from" prices: WeWork global, WeWork India day pass, TEC, Servcorp, Uncommon, and Regus per snippets.
* **Private offices are almost always quote-only.** Only the broker (Hubble) shows exact per-office prices; WeWork global shows "starting at" for small private offices.
* Indian conventions: "₹… /month + GST", en-IN digit grouping (1,96,000), and lakh/crore notation (Awfis "1.75L+ Seats").
* **Price inconsistencies are common:**
  * Uncommon's menu prices differ from its location-page prices.
  * Servcorp shows two price sets on one page.
  * Awfis's prices differ across subdomains (per a search snippet).
  * Uncommon's site and Hubble's listing disagreed on availability.

### 2.5 Tour and booking journeys
* **No operator offers live, confirmed tour slots.**
  * Most collect a preferred date and time for staff to confirm: TEC, Servcorp, Common Ground.
  * Some just route to a phone number or OTP form: Awfis's "Book a Tour" is a `tel:` link; Smartworks's form submit is "Get OTP".
  * Mindspace (not India) uses per-location Calendly. Regus has per-centre "online-scheduler" URLs (snippet only).
* Forms are short (5–8 fields). Context is passed through query parameters (TEC pre-fills city, centre and product) or hidden fields (Convene sets "interest" by the active tab). Common Ground uses a two-step form and asks whether the visitor came via an "AI Suggested" referral.
* **Self-serve purchase is often pushed to an app or a separate portal:**
  * WeWork global day passes and meeting rooms deep-link to the app.
  * Uncommon uses a Nexudus portal subdomain.
  * Servcorp uses a separate checkout domain.
* TEC's day pass is instantly confirmed on the main site. Industrious shows a meeting-room "Reserve Now" with a 24-hour free cancellation.
* **Phone OTP verification is common in India** (Smartworks, Awfis).

### 2.6 Trust signals and objection handling
* The standard stack:
  * Scale statistics.
  * Client logo walls: Awfis, Smartworks, WeWork, Industrious, TEC, Common Ground, Hubble.
  * Testimonials linked to case studies: WeWork global.
  * Press and awards; GPTW at Awfis and WeWork India.
  * Listed-company and investor pages: Awfis, Smartworks (NSE/BSE as stated on its site), WeWork India investor relations.
* Per-location reviews are uncommon: Servcorp (Google, with AggregateRating schema), WeWork global, Mindspace, Hubble.
* Certifications: Industrious claims SOC 2, ISO and HIPAA; Uncommon shows B Corp, BREEAM and Cyber Essentials.
* Flexibility claims: "no hidden fees", "1–36 month" terms (Industrious), explicit notice and deposit terms (Servcorp).
* **Privacy posture in India.** Of the Indian sites whose privacy policies were checked, only WeWork India's cites the DPDP Act 2023 (re-verified, §7). Awfis and Smartworks name a Grievance Officer; Smartworks cites the IT Act/SPDI Rules. Consent checkboxes on forms were seen only occasionally (Awfis enterprise form; TEC tour form).

### 2.7 Enterprise
* Common pitch: zero or low capex, speed to occupancy, design-build-manage, portfolio access, and seat-band qualification:
  * TEC: open-book cost-plus pricing with fit-out amortised into one monthly fee, 4–6 months to occupancy, anonymised Indian case studies.
  * Awfis: Acquire → Design → Deliver → Manage, "Zero Upfront CapEx".
  * Smartworks: "Design. Build. Manage.", "delivered in 60 days" as stated.

### 2.8 Mobile (limited evidence)
* App-style bottom navigation on WeWork India (Home / Quick book / Ask Gia); sticky bottom container at Industrious; member apps at most operators.
* **Floating WhatsApp:** Common Ground only. Hubble's advisor card offers WhatsApp. **No active WhatsApp CTA was found on any Indian operator page fetched.**

### 2.9 Content and SEO
* JSON-LD quality varies widely:
  * **Strong:** TEC centre pages (LocalBusiness + Geo + OpeningHours + OfferCatalog + Breadcrumb); Hubble (RealEstateListing with per-office Offers; city FAQs generated from live inventory).
  * **Gaps:** Industrious location pages have Organization only; Common Ground and Uncommon have no LocalBusiness; Mindspace's London page carried FAQ/LocalBusiness data **for a New York location** (schema mismatch).
* Hygiene problems seen at Indian operators:
  * Awfis: stale sitemap (215 URLs, all lastmod 2024-04-10, no centre pages); truncated and triple-hyphen slugs; duplicate singular/plural paths.
  * Smartworks: two parallel city-page sets that each canonicalise to themselves.
* Several operators show visible quality lapses: Lorem Ipsum text and staging links (Common Ground); FAQ text contradicting listings (TEC).

---

## 3. Industry conventions SmartGro should adopt

1. City → micro-market → centre hierarchy, plus product × city pages, with breadcrumbs and BreadcrumbList schema.
2. Map/list toggle; filters for product, team size, move-in date and amenities.
3. "From" prices on flexible products, with GST treatment explicit.
4. Centre page as a sales kit: gallery, amenities (including **not included**), transit, nearby centres, FAQs, repeated tour CTA, downloadable brochure.
5. Context-carrying CTAs: product, centre and city pre-filled in every form.
6. A separate enterprise track with seat bands, a process explainer and case studies.
7. Short forms (≤ 8 fields), with a two-step layout for tours.
8. LocalBusiness + GeoCoordinates + OpeningHours + Offer + FAQPage + BreadcrumbList on centre pages.

## 4. Differentiation opportunities (ranked by expected impact and feasibility)

| # | Opportunity | Evidence of gap | Where it lands in SmartGro |
|---|---|---|---|
| 1 | **Live, confirmed tour slots** with WhatsApp/email confirmation and reschedule link | no operator offers confirmed slots | BR-TR, FR-BK (Phase 8) |
| 2 | **One source of truth for price and availability across every page** | price and availability inconsistencies at four operators and one broker | BR-SOT, BR-PR, ADR-0010 |
| 3 | **Complete, server-rendered centre pages** | Indian operators' placeholders and thin pages | ADR-0010, IA §3.1 |
| 4 | **3D twin and interactive floor plan inline on the centre page, linked to live availability and booking** | tours hidden on sub-pages or external sites; floor plans rare | Phase 7, BR-3D |
| 5 | **Transparent INR pricing**: "from ₹… + GST" per offering, owner-approved | almost no Indian centre-level prices | BR-PR-3 (decision D-04) |
| 6 | **India commute data**: metro stations, walk minutes, cab drop-off, parking | absent on Indian pages fetched | IA §3.1 "Getting here" |
| 7 | **WhatsApp-first follow-up with explicit, granular consent** | no Indian operator page had active WhatsApp; consent rarely shown | BR-PRV, INTEGRATION_CONTRACTS §3.1 |
| 8 | **Visible privacy posture** (notice at every form, consent log, grievance contact) | only WeWork India cites the DPDP Act | SECURITY_MODEL §8 (legal D-15) |
| 9 | **Compare SmartGro centres** (price, commute, amenities, availability) | no operator does it | FR-DISC (Phase 6) |
| 10 | **Web checkout without an app** for meeting rooms and day passes | app- and portal-gated at several operators | Phase 8 (decision D-07) |
| 11 | **Exemplary structured data and sitemaps** (fresh, complete, correct per page) | stale sitemaps and schema mismatches | Phase 11 |
| 12 | **Hospitality- and design-led premium story** in India, versus incumbents' scale messaging | Indian incumbents lead with scale and logo walls | Phase 2 brand system |
| 13 | **Inventory-driven city FAQs** (Hubble pattern) | only the broker does it | Phase 11 content SEO |
| 14 | **Lead routing using context and seat bands, plus non-buyer filtering** (a Smartworks pattern) | partial at competitors | Phase 9 assignment rules |

Positioning caution: anything SmartGro claims (certifications, client logos, reviews, statistics) must be documented and true. The research found that the competitors' errors themselves damage trust.

---

## 5. Implications for requirements

* Add **operating policies** to business rules: tour hours, cancellation windows (Industrious shows 24 h, Uncommon 48 h / 7 days), virtual-office documentation. These are owner decisions (D-07, D-15, D-28).
* Build **GST-aware** pricing and invoicing data (GSTIN capture, "+ GST" display), subject to D-04 and D-08.
* Phone **OTP verification is optional, not default**: it adds friction. Use rate limiting, Turnstile and honeypots first; enable OTP per form if spam data justifies it.
* Plan **city-name aliases** (bangalore/bengaluru, gurgaon/gurugram) as 301 redirects.

---

## 6. Source log (condensed)

All fetches were made on 2026-10-24. "Raw" means the raw HTML was inspected.

| Operator | URLs read (status) |
|---|---|
| WeWork global | `/` (OK), `/l/coworking-space/new-york-city--NY` (OK), `/buildings/250-broadway--new-york-city--NY` (OK), `/join-now` (OK), `/enterprise` and `/solutions/wework-on-demand` (geo-redirected to India; not used for global) |
| WeWork India | `/` (OK, raw), `/enterprise/` (OK), `/workspaces/on-demand/day-pass/` (OK), `/mumbai/bkc/` (OK, raw), `/coworking-space/mumbai/` (raw), `/bangalore/bannerghatta-main-rd` (OK, raw), `/policies/privacy/` (raw) |
| IWG / Regus / Spaces | regus.com (`/`, `/en-gb`, `/en-in`, `/en-gb/india`, `/en-gb/india/coworking`, robots, sitemap), spacesworks.com, iwgplc.com: **all BLOCKED**. Eight web searches used as labelled snippets. |
| Industrious | `/` (OK, raw), `/locations` (partial, JS-only), `/m/new-york-city` (OK, raw), two location pages (one partial, one raw template strings), `/enterprise` (OK), `/solutions/coworking-memberships-and-day-passes` (OK) |
| Convene | `/`, `/locations/`, `/locations/new-york-city/`, `/locations/new-york-city/530-fifth-avenue/`, `/workplace/`, `/meetings/` (all raw; WebFetch 403) |
| The Executive Centre | `/` (OK, raw), `/locations/mumbai/serviced-offices-in-mumbai/` (OK, raw), `/office-space/sydney-400-george/` (OK, raw) and `/virtual-tour/` (raw), `/office-space/mumbai-one-bkc/` (raw), `/schedule-a-tour/` (OK), `/day-pass/` (OK), `/managed-office/` (OK); one guessed URL 404 |
| Servcorp | `/` (302 → `/en/`), `/en/` (OK, raw), One World Trade Center page (OK, raw), `/en/arrange-to-visit/`, `/en/virtual-offices/`, `/en/book-a-meeting-room/` (OK); servcorp.co.in **FAILED** |
| Common Ground | `/` (OK, raw), `/my-en/locations/damansara-heights/` (OK, raw), `/book-a-tour/`, `/day-pass/`, `/my-en/enterprise-solutions/` (OK) |
| Uncommon | `/` (OK, raw), `/location/holborn/` (OK, raw), `/membership/private-office/`, `/meeting-rooms/` (OK); Holborn booking portal (raw: Nexudus identified); HubSpot form JSON **FAILED** |
| Hubble | `/` (OK, raw), `/office-space-london` (OK, raw), `/office-space/11211/uncommon-holborn` (OK, raw), `/meeting-rooms/london` (OK); book-viewing page JS-only |
| Awfis | `/` (OK, raw), `/office-space/bengaluru` (placeholders), Koramangala centre page (placeholders), `/virtual-office`, `/managed-office`, `/day-pass` (OK), `/meeting-rooms/bengaluru` (HTTP 200, grep only), `/privacy-policy` (raw), `/robots.txt`, `/sitemap.xml` (raw) |
| Smartworks | `/` (OK, raw), `/office-space/bangalore/`, `/office-space/bangalore/koramangala/`, `/office-space-in-bangalore/`, `/investors/`, `/faqs/` (OK), `/contact-us/` (partial, client-rendered), `/privacy-policy/`, robots, sitemap (raw) |
| Mindspace | mindspace.me `/`, `/london/`, `/london/shoreditch-liverpool-street/` (OK, raw); mindspacereit.com `/` (OK) |
| Supplementary | deskpass.com Bangalore (OK); office-hub.com (**FAILED**, HTTP 429) |

---

## 7. Independent re-verification (2026-10-24)

| Claim | Check | Result |
|---|---|---|
| TEC embeds a Matterport tour on a centre's `/virtual-tour/` sub-page | fetched page, counted `my.matterport.com/show` | confirmed (2 occurrences) |
| Awfis centre pages serve unrendered template placeholders | fetched Koramangala centre page, grepped `{{space.` | confirmed (`{{space.address`, `{{space.city_name`, …) |
| WeWork India's privacy policy cites the DPDP Act | fetched policy, counted "Digital Personal Data Protection" | confirmed (14 occurrences) |
| Regus blocks automated access | fetched `/en-gb` | confirmed (Incapsula response) |
