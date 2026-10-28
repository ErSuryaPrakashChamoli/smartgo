# SmartGro — Phase 6 Implementation Record and Acceptance Pack

| | |
|---|---|
| Status | Implemented locally for owner review (2026-10-27, OD-113), limited to the Phase 6 work that does not depend on the unresolved Phase 5 requirements. **Partially accepted by the owner on 2026-10-27 (§9, OD-114); not fully accepted.** Accepted: AC-6.3 (templates checked, §10.2), AC-6.4, FR-DISC-1 and the "404/410 pages" deliverable. Performance is **NOT VERIFIED**. Phase 7 is not authorised to start. **Closure round (2026-10-27, OD-117, §11):** every code-completable item done, two defects fixed, the browser evidence made repeatable from the repository; no criterion changed status and no further owner decision was recorded. Nothing is committed, pushed or deployed. Phase 5 stays partially accepted (OD-112); owner action A-8 stays open. |
| Authority | OD-113 (owner instruction of 2026-10-27, "SmartGro — Phase 6 master execution prompt"). It narrows OD-112 (d) to the independent work only; OD-112 (d) still governs everything that depends on Phase 5. The brief arrived cut off after its "Dependencies" heading, so scope and criteria come from the project records listed below. |
| Scope sources | `../../PROJECT_MASTER_PLAN.md` §2 Phase 6 (deliverables, AC-6.1 to AC-6.4); `../requirements/PRD.md` FR-DISC-1 to FR-DISC-7, NFR-PERF-1/2, NFR-A11Y-1; `../requirements/INFORMATION_ARCHITECTURE.md` §2 to §3 |
| Earlier Phase 6 work | Public pages built in rounds 38 to 41 under OD-103 and OD-109 (home, locations, sectors, properties, offerings, workspace categories, enquiry page). Reused as built; not rebuilt. |
| Databases | Tests: `smartgro_testing` only (forced by `phpunit.xml`; the effective database was checked before each run). Preview: `smartgro_preview` only (OD-104), migrated once for the new columns after the target was confirmed. **`smart_go` was never written to**: read-only checks before and after the work gave 23 migrations, batch 9, 29 tables, no new column, and no write outside the cache and session tables since 2026-10-26 19:03. |
| Related | `../phases/PHASE_STATUS.md` (Phase 6), `../decisions/DECISION_REGISTER.md` §0.41 (OD-113), `../testing/PHASE_5_ACCEPTANCE_PACK.md`, `../architecture/DATABASE_ERD.md` (C-28), `../requirements/CLIENT_INPUTS_REQUIRED.md` (C-2.9) |

**Tests and browser checks show that the rules are coded and the pages work. They do not replace the owner's review, real content from SmartGro, or measurements on staging.**

---

## 1. Acceptance criteria

Statuses: **PASS** (built and verified), **PARTIAL** (the independent part is built and verified; the rest waits on a named dependency), **BLOCKED** (depends on an unresolved input or decision; nothing invented). Nothing below is accepted until the owner records a decision.

**Owner decision of 2026-10-27 (§9, OD-114): Phase 6 partially accepted.** Accepted: the items marked **PASS** below, which are AC-6.3 (for the templates listed in §10.2), AC-6.4, FR-DISC-1 and the "404/410 pages" deliverable (§1.3). Everything marked **PARTIAL** or **BLOCKED** is not accepted. The statuses below are those of the implementation record, unchanged; §10 records what was re-verified for the decision, and §11.3 links each criterion to the repeatable evidence of the closure round.

### 1.1 Master plan, Phase 6

| AC | Criterion | Status | Evidence or blocker |
|---|---|---|---|
| AC-6.1 | FR-DISC-1 to FR-DISC-7 pass | **PARTIAL** | Row by row in §1.2. FR-DISC-1 passes; FR-DISC-3 is blocked; the rest are partial. |
| AC-6.2 | NFR-PERF-1/2 met on staging (Lighthouse CI, mobile) | **BLOCKED** | No staging exists (OD-79 defers staging, CI and GitHub until local completion). Local proxy only, in §5.3: first-party JavaScript on public pages is 0.02 kB compressed (the budget is 90 kB). Simulated-mobile LCP was 2.4 to 3.0 s on the PHP built-in server, which serves no compression and no cache headers. The 2.5 s target is therefore **not** shown met. |
| AC-6.3 | Axe reports no AA violations | **PASS on the templates checked** | axe-core (WCAG 2.0, 2.1, 2.2 A and AA rules) at 360 and 1440 px: **0 violations** on 19 public addresses on the test database, 5 on the preview database, and the enquiry page with the form open (§5.1). "Needs review" items are colour contrast over the fixed phone bar and dark sections; the new elements' colour pairs compute at 5.41:1 or higher (§5.1). Not done: a manual screen-reader pass (Phase 14, TEST_STRATEGY). |
| AC-6.4 | Every page fully server-rendered | **PASS** | With JavaScript switched off, every checked address returned its heading and main content. The workspace-type filter and the comparison form also worked, and a shared comparison address reopened (§5.1). |

### 1.2 Discovery requirements (PRD FR-DISC)

| Req | Requirement (short) | Status | What is built | What waits, and on what |
|---|---|---|---|---|
| FR-DISC-1 | City, micro-market, centre and product × city pages, server-rendered; unpublished or empty combinations 404 or unlinked | **PASS** (content pending) | `/locations/{city}`, `/locations/{city}/area/{sector}`, `/locations/{city}/{property}`, `/workspaces/{category}/{city}` (rounds 38 to 41), each only from published records. | Real copy and photos from SmartGro (D-12, C-6.3). |
| FR-DISC-2 | City page map/list toggle and filters (product, team size, move-in by, amenities), with and without JS | **PARTIAL** | **New:** a city page narrowed to one workspace type by plain links (`?workspace=`), working without JavaScript, with a count and a link to the category page for that city. Unknown values are ignored and never shown back. The canonical address stays the city page. | Team size and move-in date need inventory (AC-5.1 to AC-5.4). Amenities need the amenity list, part of the property records (D4.1, C-2.7). The map toggle needs a map provider (D-18). |
| FR-DISC-3 | Centre page with live availability and price fragments (60 s, BR-PR-3) | **BLOCKED** | The centre page itself exists (round 38 onward) and says prices and availability are confirmed on request. | Inventory and approved pricing (AC-5.1 to AC-5.4, OD-109 f). |
| FR-DISC-4 | Compare up to 3 centres (price display, commute, amenities, availability); canonical data only; shareable URL | **PARTIAL** | **New:** `/locations/compare`, two or three locations on the website side by side: area, address, opening, workspace types, opening hours, nearest metro and walk, buses, parking, accessibility. Prices and availability read "Confirmed on request". The address can be shared; it is never indexed. A removed location is dropped with a notice; more than three are refused. | Price display and availability (Phase 5 inventory and pricing); amenities (D4.1, C-2.7); commute times beyond the walk from the metro (no data source). |
| FR-DISC-5 | Every "Book a tour"/"Enquire" passes city, centre and product | **PARTIAL** | Property, offering and category pages pass the property and workspace (rounds 39 to 41). **New:** the phone bar's Enquire button carries the same context. | City context from city and sector pages: the enquiry submission is D8.1, excluded by OD-112, so it was **not changed** (OD-113 c 4). Tours are Phase 8. |
| FR-DISC-6 | Phone sticky bar: Book a tour · WhatsApp · Call; each tap tracked | **PARTIAL** | **New:** Call and WhatsApp from the published numbers (a location's own numbers on its pages, otherwise SmartGro's main ones), beside the main button: the location's or category's Enquire, or the published header button. Shown below 1024 px. | Book a tour (Phase 8). Tap tracking: no tracking until the consent approach is agreed (OD-100). |
| FR-DISC-7 | "Getting here": map, metro/bus, walk minutes, parking, accessibility; entered once per location; schema GeoCoordinates | **PARTIAL** (built; content pending) | **New:** nearest metro station, walk in minutes (1 to 60, only with a station), buses, parking and accessibility on the property profile, through draft, approval and publication. Live property pages carry LocalBusiness structured data with GeoCoordinates once the building's coordinates are recorded (never on previews). The map link already existed. | The details themselves (C-2.9). An embedded map needs a provider (D-18). |

### 1.3 Master plan deliverables

| Deliverable | Status | Notes |
|---|---|---|
| All IA templates | **PARTIAL** | Built: home, workspace hub and categories, category × city, locations, city (with coming soon), sector, property, offering, comparison (new), enquiry, insights, customer stories, FAQs, and CMS pages at any free address (`/about`, `/contact`, `/legal/…`). **New for `/contact`:** a "Contact details" page section that reads the published contact setting and, if chosen, lists the locations with their addresses; it stores no details of its own. Not built: `/book-a-tour` and room booking (Phase 8); `/enterprise` with its form (◇, Phase 8, OD-110 e); `/offers` (approved offers); `/solutions/{segment}`, `/partners/*`, `/careers` (◇, D-27); `/booking/{reference}` (Phase 8); `/insights/topic/{slug}` (post topics not built, OD-97); `/sitemap.xml` (Phase 11, FR-SEO-3). |
| Live availability and price fragments | **BLOCKED** | AC-5.1 to AC-5.4. |
| Compare feature | **PARTIAL** | FR-DISC-4 above. |
| Forms (UI) | **PARTIAL** | The enquiry form's UI exists (Phase 5) but stays closed to the public (D8.1, D-15). Tour and enterprise forms are Phase 8. |
| Legal pages | **BLOCKED (texts)** | The legal page template and the footer legal links, hidden until a legal page is published, exist (Phase 4, OD-106). The texts need counsel (D-15, C-7.4). |
| 404/410 pages | **PASS** | **New:** see §2. |

---

## 2. What was built (OD-113)

| Item | Files | Behaviour |
|---|---|---|
| Error pages | `resources/views/errors/{403,404,410,419,429,500,503}.blade.php`, `components/site/error-page.blade.php`; `SiteNavigation::withoutMenus()`; a view composer in `AppServiceProvider` | The website's header, footer and published menus around a plain message and links to the home, locations and workspaces pages. Never indexed. Nothing about the request (address, error) is shown. If the menus cannot be read, the page still renders without them. The 500 and maintenance (503) pages never read the database. Back-office errors use the same pages. |
| "Getting here" details | migration `2026_10_27_105101_add_getting_here_details_to_property_profiles_table`; `PropertyProfile` (fields and rules); `PropertyProfileForm` ("Getting here" section); `EditPropertyProfile`; `WorkspaceCatalogue::profile()`; `pages/locations/property.blade.php` | Plain text only (`<` and `>` refused); walk minutes 1 to 60 and only with a station. Shown only once a version with them is published. Property Managers draft them for assigned properties, like the rest of the profile. |
| Structured data | `LocationPages::structuredData()`; `layouts/site.blade.php` (`head` slot) | LocalBusiness with name, URL, street, city, PIN code and country, plus GeoCoordinates when the building has coordinates. Escaped for a script element (`@json`: `<`, `>`, `&`, `'` and `"` encoded). Live pages only. |
| Workspace-type filter | `LocationController::city()`; `pages/locations/city.blade.php` | Links per workspace type offered in the city (shown when there are two or more), `aria-current` on the active one. |
| Comparison | route `locations.compare` (`/locations/compare`, before `/{city}`); `LocationController::compare()`; `pages/locations/compare.blade.php`; `City::RESERVED_SLUGS` and the city form | Reads only listed properties and published profiles and offerings. Entry points: the locations index, city pages and each property page ("Compare locations"). "compare" can no longer be saved as a city address. |
| Phone action bar | `layouts/site.blade.php` (`mobileActions`, `mobileContact`); `components/site/mobile-action-bar.blade.php`; property, offering and category views | See FR-DISC-6. Design previews keep their sample bar. |
| Contact details section | `App\Services\Content\Blocks\ContactDetailsBlock`, `components/blocks/contact.blade.php`, `BlockLibrary`, `SiteNavigation::mainContact()` | Offered in the page builder only (not in insights, customer stories or legal pages). Stores only title, introduction and the locations choice. |

**Not changed:** the enquiry form and its submission (D8.1), notifications (D8.4), permissions and roles, the home page design, design previews, dependencies, configuration.

---

## 3. Database changes

One additive migration, `property_profiles`: `nearest_metro` varchar(80), `metro_walk_minutes` tinyint unsigned, `buses` varchar(200), `parking` varchar(300), `accessibility` varchar(300), all nullable (ERD C-28). Applied to `smartgro_testing` (by the test runs) and `smartgro_preview` (after a dry run; 44 migrations there now). **Not applied to `smart_go`**: it is pending there together with the other Phase 4 and 5 migrations and must not be run without the owner's authorisation.

---

## 4. Automated checks (2026-10-27)

| Check | Result |
|---|---|
| Full suite on `smartgro_testing` (configuration, route and event caches bypassed; target checked first) | **910 passed, 0 failed, 0 skipped (3,517 assertions), exit code 0**, 452 s. Phase 5's last full run: 888 passed. |
| New tests | `ErrorPagesTest` (4): branded 404 with menus and contact details, noindex, address not echoed; 410; 404 when menus cannot be read; 500 page with no database query. `ContactDetailsBlockTest` (3): stores nothing else, refused on legal pages, shows published details and locations. `LocationControllerTest` (+5): structured data with and without coordinates and with markup in the address; filter; comparison of two; dropping, too many, too few, removed locations; phone bar on property and offering pages. `PropertyProfileWorkflowTest` (+2 tests, 6 data sets): details shown only once published; validation; no structured data in previews. `PublicShellTest` (+1): Call and WhatsApp beside the main header button only. `PropertyScreensTest` (+1): "compare" refused as a city address. `EditPageTest` (+1): the section saves from the editor. `PropertyProfileScreensTest` (extended): the new fields save from the form. |
| Mutation checks (protection removed, test must fail) | Structured data without script escaping: **failed as expected**. Walk minutes without a station: **failed**. Comparison limit raised to 4: **failed**. 500 page reading the menus: **failed** (query log not empty). All changes restored afterwards. |
| Pint | Clean on the changed PHP files. |
| Front-end build | `npm run build` (output in git-ignored `public/build`): app CSS 13.9 kB compressed; public JavaScript 0.02 kB compressed. |

---

## 5. Browser verification (headless Chromium from the Playwright cache; axe-core and Lighthouse from the npx cache, no install)

### 5.1 Test database (labelled "TEST FIXTURE" content: three listed properties, offerings, getting-here details, fixture numbers, a contact page, menus)

| Check | Result |
|---|---|
| 19 public addresses at 360, 768, 1024 and 1440 px: home, locations, city, city filtered, sector, property, offering, hub, category, category × city, comparison chooser, comparison of three, enquiry, `/contact`, `/about`, FAQs, insights, customer stories, an unknown address | 200, or 404 for the unknown address; **no horizontal overflow** after one fix (below); **no external requests**; no console errors other than the browser's own note of the 404 status |
| axe-core at 360 and 1440 px on the same addresses | **0 violations** |
| JavaScript off, 1440 px | every address returned its heading and main content |
| Filter without JavaScript (360 px) | "Hot desks" link → `/locations/noida?workspace=hot-desks`, "2 locations in Noida offer hot desks." |
| Comparison without JavaScript (360 px) | two boxes ticked and submitted → both columns; the address reopened in a new browser with the same columns |
| Phone bar at 360 px | Generic pages: Call, WhatsApp (main published numbers). A-70 and its hot-desk page: the location's own numbers and Enquire with `property` (and `workspace`). Category pages: Enquire with `workspace`. With the form open, the published Enquire header button joins the bar. |
| Enquiry page with a fixture privacy notice published (form open) | 0 axe violations at 360 and 1440 px, no overflow |
| **Defect found and fixed** | The comparison of three overflowed the page by 254 px at 360 px: screen-reader-only text in the last column escaped the scrolling table. The table's scroll region is now `relative`; rechecked: no overflow at any width, 0 violations. |
| Colour contrast left for review by axe | Elements over the fixed phone bar or on dark sections. Computed pairs for the new elements: forest on white 15.1:1, leaf-700 on white 5.41:1, muted on white 6.46:1, paper on forest 14.06:1. |

### 5.2 Preview database (reference data only; nothing published)

| Check | Result |
|---|---|
| Home, locations, comparison, enquiry, an unknown address at four widths | 200 (404 for the unknown address); no overflow; no external requests; 0 axe violations; content without JavaScript. The comparison says there are not enough locations to compare yet. `/contact` answers 404 until Marketing publishes a contact page. |

### 5.3 Local Lighthouse (mobile emulation, simulated throttling; PHP built-in server, no compression, no cache headers)

| Address | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| `/` | 0.91 | 1 | 1 | 1 | 3.0 s | 0 | 0 ms |
| `/locations/noida` | 0.94 | 1 | 1 | 1 | 2.7 s | 0.0002 | 0 ms |
| `/locations/noida/a-70` | 0.92 | 1 | 1 | 1 | 2.9 s | 0.0001 | 0 ms |
| `/locations/noida/a-70/hot-desks` | 0.92 | 1 | 1 | 1 | 2.9 s | 0.0001 | 0 ms |
| comparison of two | 0.92 | 1 | 1 | 0.66 (noindex, by design) | 2.9 s | 0 | 0 ms |
| `/contact` | 0.96 | 1 | 1 | 0.92 (no search description written on the fixture page) | 2.4 s | 0.0008 | 0 ms |

Lighthouse's main findings were "no text compression" (about 120 KiB) and "no cache lifetime", both properties of the local server. These numbers are a proxy only and **do not show NFR-PERF-1 met**; the criterion is measured on staging.

Servers on ports 8767 (test) and 8766 (preview) were started for these checks and stopped afterwards. The owner's server on port 8000 was not touched. Screenshots and logs were kept in the session's scratch folder, not in the repository; that folder was emptied on 2026-10-27 at 17:42 (§10.5, item 5).

---

## 6. Security and privacy notes

* Error pages never echo the requested address or the error; the 500 page cannot fail on a database outage.
* Structured data is encoded so stored text cannot close the script element (tested with `</script>` in an address).
* The comparison accepts only string keys matching listed properties; anything else is ignored and nothing from the address is shown back. It is never indexed.
* The contact section holds no personal data and no contact details; it reads the published, approved setting.
* No new package, external service, script, map, font or tracker. Pages make no external requests.
* The PHPUnit advisory stays an **open temporary security exception** (OD-112 c); nothing changed.

---

## 7. Known issues and dependencies

| Item | Detail |
|---|---|
| Stale route cache on the owner's server | `bootstrap/cache/routes-v7.php` (2026-10-26 17:55, written by an outside `artisan optimize`) does not contain the public routes added since: the workspace pages, offering pages, enquiry routes, sector pages and `/locations/compare` (checked: none of them is in the file). The server on port 8000 serves from it, so the owner will not see those pages until the caches are rebuilt or cleared, which the owner should do. They were not deleted (standing rule). **Update (2026-10-27, §11.6):** the server on port 8000 is another project, not SmartGro; the owner's SmartGro server ran on port 8001. The route cache was cleared that evening by another working session at the owner's instruction (register OD-115). |
| Pending migrations on `smart_go` | The Phase 4 and 5 migrations and C-28 are not applied there. Applying them needs the owner's authorisation. |
| City context in enquiries | Not carried from city and sector pages (OD-113 c 4). Recommended as part of the D8.1 re-verification. |
| Map | A link to OpenStreetMap from recorded coordinates; no embedded map until a provider is decided (D-18). |
| Content | All visible copy beyond labels and empty states, all photography, the getting-here details, contact details, legal texts and the about page come from SmartGro (C-1.3, C-2.9, C-6.3, C-7.4). |

---

## 8. Owner and client actions

| Ref | Action | Who | Unblocks |
|---|---|---|---|
| P6-1 | ~~Review this record and decide on Phase 6 acceptance (accept, partially accept or reject), knowing that Phase 5 is only partially accepted and A-8 is open (register owner action A-9)~~ **Done 2026-10-27: partially accepted (§9, OD-114).** | Owner | Phase 6 status |
| P6-2 | Supply the getting-here details and building coordinates per location (C-2.9) | Client | FR-DISC-7 content, comparison rows |
| P6-3 | Decide the map provider and consent approach (D-18, OD-100) | Owner, counsel | embedded map, map/list toggle, tap tracking |
| P6-4 | Legal texts through counsel (D-15, C-7.4) | Client, counsel | legal pages, D8.1 |
| P6-5 | Decide whether enquiries from city and sector pages should carry the city, to be built at the D8.1 re-verification | Owner | FR-DISC-5 in full |
| P6-6 | Staging environment for NFR-PERF-1/2 (OD-79), configured and measured as in §11.5 | Owner, infrastructure operator | AC-6.2 |
| P6-7 | Publish a contact page (`/contact`) with the new section once SmartGro's main contact details are approved and published | Marketing | IA `/contact` |
| A-8 | The further Phase 5 decision (unchanged) | Owner | everything in OD-112 (d) |
| P6-8 | **New (2026-10-27, OD-114):** the further Phase 6 decision on the criteria not accepted, once their dependencies are resolved and they are verified, including performance on staging (register owner action A-10) | Owner | the rest of Phase 6; the start of Phase 7 |
| P6-9 | **New (2026-10-27, OD-117):** decide how the field targets are judged before launch: INP ≤ 200 ms and the p75 figures of NFR-PERF-1 need real-user data, so either accept lab measurements on staging as the interim proof or wait for field data after launch (§11.5) | Owner | AC-6.2 |

---

## 9. Owner decision (2026-10-27)

Until this decision, this section read "Not recorded. Phase 6 is not accepted."

The owner's instruction, quoted:

> **Phase 6 is PARTIALLY ACCEPTED on 27 October 2026. It is not fully accepted.**
>
> The owner authorises acceptance only of the Phase 6 criteria that are explicitly marked PASS in the acceptance pack and supported by the recorded verification evidence.

| | |
|---|---|
| Decision | **Partially accepted. Not fully accepted.** |
| Date | 2026-10-27 |
| Decided by | The project owner |
| Register | `../decisions/DECISION_REGISTER.md` §0.42, OD-114; records owner action A-9 as done |
| Rule applied | Accepted only where §1 marks the item **PASS** and the evidence was re-checked for this decision (§10). |
| **Accepted** | **AC-6.3** (axe reports no AA violations), for the templates listed in §10.2: 0 violations in 48 page checks on 24 public addresses, 8 on the 403, 419, 429 and 503 pages, 2 on a page with every text-only section, and 10 on the preview database. **AC-6.4** (every page fully server-rendered): all 25 public addresses re-checked with JavaScript off; the filter and the comparison form work without it. **FR-DISC-1** (city, micro-market, centre and product × city pages, server-rendered; unpublished or empty combinations 404 or unlinked): `LocationControllerTest` and `WorkspaceControllerTest` in the passing suite, plus the browser re-check; real copy and photography are still to come. **"404/410 pages"** (§1.3): `ErrorPagesTest` (404 with menus, noindex, address not echoed; 410; 404 when the menus cannot be read) in the passing suite, plus 404 and 410 re-checked in the browser at four widths. |
| Not accepted (PARTIAL) | AC-6.1; FR-DISC-2, FR-DISC-4, FR-DISC-5, FR-DISC-6, FR-DISC-7; the "All IA templates", "Compare feature" and "Forms (UI)" deliverables. Their built parts are verified (§4, §5, §10), but the criteria are not met. |
| Not accepted (BLOCKED) | **AC-6.2**: performance is **NOT VERIFIED** until staging measurements show whether NFR-PERF-1/2 are met; the local figures in §5.3 are a proxy only (no staging, OD-79). **FR-DISC-3** and the "Live availability and price fragments" deliverable: inventory and pricing (AC-5.1 to AC-5.4). "Legal pages" deliverable: the texts (D-15). |
| Not tested | See §10.4: a manual screen-reader pass, browsers other than Chromium, the image and testimonial sections, the enquiry confirmation page, staff previews, back-office error pages, real maintenance mode, automated tests for the 403, 419, 429 and 503 pages, real content. |
| Kept open | **P6-5**: city context for enquiries; implementation waits until D8.1 is re-verified. **D-18**: the embedded-map provider. **Phase 5 inputs** for prices, availability, location content and filters (AC-5.1 to AC-5.4, C-2.7, C-2.9, D-12). **Performance** on staging (AC-6.2, P6-6). Legal texts (D-15, P6-4). Tours and the enterprise form (Phase 8). Tap tracking (OD-100). The items in §10.4. |
| Not enabled | The public enquiry form stays closed until its prerequisites are met (D8.1, OD-111 c). No tap tracking (OD-100). |
| Phase 5 | **Remains partially accepted under OD-112.** A-8 stays open. |
| Phase 6 | **Not fully accepted.** Further decision: P6-8 (register A-10). |
| Phase 7 | **Not authorised to start** until the outstanding Phase 6 requirements are addressed and the owner's further Phase 6 decision (A-10) is recorded. Not started. **Update (2026-10-28, OD-118):** the owner let Phase 7 start; A-10 now covers only the Phase 6 criteria not accepted. |

---

## 10. Verification for the owner decision (2026-10-27)

Re-run in the same working session that built Phase 6, on the isolated databases only. It is a re-run of the recorded checks on the final code, **not an independent review by another person**. No application code, schema or configuration was changed for it.

### 10.1 Re-run for this decision

| Check | Result |
|---|---|
| Effective database before the tests | `smartgro_testing`, configuration not cached (the route, configuration and event caches were bypassed) |
| Full suite on the final code (the implementation run came before the last view change, §5.1) | **910 passed, 0 failed, 0 skipped (3,517 assertions), exit code 0**, 379 s |
| Browser, test database: the 19 addresses of §5.1 plus an insight article, a customer story, a legal page (`/legal/terms`, fixture), a coming-soon city and a 410 address, each at 360, 768, 1024 and 1440 px | 96 page loads: 200, or 404 and 410 where expected; **no horizontal overflow**; **no external requests**; no console errors other than the browser's note of the 404 and 410 statuses; **48 axe runs, 0 violations**; with JavaScript off, 24 of 24 addresses carried one heading and their main content |
| Without JavaScript | the "Hot desks" link opened `/locations/noida?workspace=hot-desks` ("2 locations in Noida offer hot desks."); the comparison form showed both locations, and the shared address reopened with the same columns |
| Phone bar at 360 px | Call and WhatsApp on every page; on A-70 and its hot-desk page the location's own numbers and Enquire with the property (and workspace); Enquire with the workspace on category pages |
| 403, 419, 429 and 503 pages (rendered from the views on the test database, loaded in Chromium at 360 and 1440 px) | each with its heading and "noindex, nofollow", no overflow, **0 axe violations**; a real form post without a CSRF token answered 419 with the website's page |
| A page with every text-only section (hero, text, features, figures, comparison, FAQ, call to action) | 200 at four widths, no overflow, 0 axe violations, content without JavaScript |
| Preview database (`smartgro_preview`): home, locations, comparison, enquiry, an unknown address at four widths | 20 page loads, the expected statuses, no overflow, no external requests, 10 axe runs with 0 violations, 5 of 5 without JavaScript. Migrations: 44 ran, 0 pending |
| `smart_go`, read-only | 23 migrations, batch 9, 29 tables, no Phase 5 or 6 column; no write outside the cache and session tables since 2026-10-26 19:03 |

Servers on ports 8767 and 8766 were started for these checks and stopped afterwards; the owner's server on port 8000 was not touched.

### 10.2 Templates covered by axe-core (the accepted scope of AC-6.3)

Home; locations; city; city filtered by workspace type; coming-soon city; sector; property; offering; workspace hub; category; category × city; comparison chooser and comparison of three; enquiry (closed; the open form was checked in the implementation run only, §10.3); CMS page with the contact section; CMS page with text; CMS page with every text-only section; legal page; FAQs; insights and an insight article; customer stories and a customer story; error pages 403, 404, 410, 419, 429 and 503.

### 10.3 Carried over from the implementation record, not re-run

* The four mutation checks (§4): they need temporary changes to application code, which this documentation-only task does not allow.
* Lighthouse figures (§5.3).
* axe on the enquiry page with the form open (fixture privacy notice, §5.1).
* Pint (§4); no PHP file changed since.

### 10.4 Not tested

* **NFR-PERF-1/2 on staging (AC-6.2): NOT VERIFIED.**
* A manual screen-reader and keyboard pass (NFR-A11Y-1; TEST_STRATEGY; Phase 14).
* Browsers other than Chromium, and real phones.
* axe on the image and testimonial sections (they need real media and consented testimonials), the enquiry confirmation page (D8.1), and staff preview pages.
* Error pages inside the back office: §2 says back-office errors use the same pages; nothing checked it. **Resolved in §11.1.**
* The 503 page during a real maintenance window (only the rendered page was checked).
* Automated tests for the content of the 403, 419, 429 and 503 pages: none exist. 403 and 429 are rendered by other tests, which check the status only; 419 and 503 are rendered by none. They were checked in the browser in §10.1. **Resolved in §11.1.**
* Real content: every page was checked with labelled fixtures; copy, photos, getting-here details and contact numbers from SmartGro are still to come.

### 10.5 Differences between the implementation report and the repository evidence

1. **Full suite.** The reported 910 passing tests came from a run made before the comparison page's last fix (§5.1). The re-run on the final code gave the same result.
2. **Error pages.** The report lists seven error pages as built. Automated tests cover the content of 404, 410 and 500 only. The other four were browser-checked for this decision (§10.1), and no automated test covers them (§10.4).
3. **Back-office error pages.** §2 says back-office errors use the same pages. Nothing verified this; it is listed as not tested.
4. **AC-6.3 coverage.** The implementation run did not cover the insight article, customer story, legal page, coming-soon city, 410, 403, 419, 429 and 503 pages, or the text-only sections. They were covered for this decision, with 0 violations.
5. **Reproducibility.** The browser scripts, logs and screenshots of §5 and §10 were kept in the session's scratch folder, not in the repository. That folder was emptied when the working session restarted at 17:42 on 2026-10-27, after the results above had been read and recorded. **The raw browser evidence no longer exists:** the browser results in §5 and §10 stand as recorded here and cannot be re-inspected. The automated tests (§4, §10.1) remain reproducible from the repository. This affects AC-6.3 most, because its evidence is browser evidence only. Recommended: re-run the axe and no-JavaScript checks before release (Phase 14), with the scripts kept in the repository if the owner approves adding them. **Resolved in §11:** the owner asked for repository-owned checks (OD-117); they now live in `../operations/scripts/browser/` with their results in `evidence/phase6/2026-10-27/`.
6. **Register path.** The owner's brief names `docs/DECISION_REGISTER.md`; the register is `docs/decisions/DECISION_REGISTER.md`. No second register was created.

None of these differences changes a PASS marking in §1. Item 5 means the browser evidence for AC-6.3 and AC-6.4 now exists only as recorded in this pack.

---

## 11. Closure round (2026-10-27, OD-117)

The owner asked for every code-completable Phase 6 item to be finished and the evidence made repeatable before anything else (register OD-117). No acceptance decision was recorded in this round: **OD-114 stands and Phase 6 stays partially accepted.**

### 11.1 Work done

| Item | Detail |
|---|---|
| Error pages, automated | `ErrorPagesTest`: 403, 419, 429 and 503 go through the real exception handler and answer with their real status, the website's page, "noindex" and no internal message. A server error (500) shows no exception class, message, credentials or file path. Maintenance mode answers 503 on public pages and in the back office without a database query; it is simulated inside the test only, because the test environment's file driver would also take down any local server sharing the storage folder. A normal page carries no error heading. Back office: a guest is sent to sign in, a refused screen answers 403, a missing record 404, all with the same pages. `ErrorPagesWithoutDatabaseTest`: the 500 and 503 pages render while the database is unreachable. |
| Edge cases | The comparison ignores nested query values. A property with no published getting-here details shows no such section, and its phone bar shows no call or WhatsApp link. The contact section never shows contact details that are saved but not published. |
| **Defect fixed: keyboard focus hidden behind the phone bar** (WCAG 2.2, 2.4.11 Focus Not Obscured) | On phones, tabbing to links near the bottom of the screen let the browser scroll them under the fixed action bar. Measured on the A-70 page at 360 × 740 px: "Open map" and "SmartGro H-105" fully hidden, "Enquire about this location" partly. The page now reserves scroll space at the bottom while the bar is shown (`resources/views/layouts/site.blade.php`, `scroll-pb-24 lg:scroll-pb-0`). After the fix: 44 focus stops, none hidden. Regression test in `PublicShellTest`. |
| **Defect fixed: no search description on a contact page** | A page made only of the contact section had no automatic description (local Lighthouse SEO 0.92). The section's introduction is now a source for it (`App\Services\Content\PageRenderer`). Test in `ContactDetailsBlockTest`; Lighthouse SEO for `/contact` is now 1. |
| Repeatable browser checks | `../operations/scripts/browser/`: `run-checks.sh` runs everything in one command, with `check-target.php`, `seed-test-fixtures.php`, `verify-public.mjs`, `verify-admin.mjs` and `lighthouse-local.sh`. Usage is in `../operations/LOCAL_DEVELOPMENT.md` §3, "Browser checks". The scripts use Playwright, axe-core and Lighthouse from the local npx cache; nothing is added to `package.json` or `composer.json`. Three faults in the scripts themselves were found and fixed before the evidence was taken. |

### 11.2 Evidence (repeatable)

| Check | Command, time and environment | Result |
|---|---|---|
| Full suite | `php artisan test --compact`, 2026-10-27 18:15 to 18:23 IST; `smartgro_testing`, target checked first; configuration, route and event caches bypassed | **923 passed, 0 failed, 0 skipped (3,593 assertions), exit code 0.** No application or test code changed after it started. |
| Browser checks | `bash docs/operations/scripts/browser/run-checks.sh docs/testing/evidence/phase6/2026-10-27 8767`, 2026-10-27 19:46 to 19:48 IST; Chromium 151.0.7922.34 (Playwright), axe-core 4.13.0, PHP 8.5.4, Node 22.22.1; database `smartgro_testing` (`run.json`); exit code 0 | `public.json`: 26 addresses at 360, 768, 1024 and 1440 px, 104 page loads, no overflow, no external or failed requests, no console errors; 62 axe runs (52 page checks plus 10 on the rendered 403, 419, 429, 500 and 503 pages), **0 violations**; 26 of 26 addresses with one heading and their content without JavaScript; without JavaScript the phone menu opens, the filter narrows the city page and the comparison form compares and its address reopens; keyboard on a phone: 44 stops, each with a focus indicator, none hidden; 404, 410 and 419 answered with the website's pages, "noindex", and the query string not shown. `admin.json`: 7 back-office screens at 360 and 1440 px (profile editor with "Getting here", page editor with the contact section, new page, city editor, dashboard, a 403 and a 404), each at its own address with the expected status, no overflow, no external requests, **0 axe violations**. |
| Local Lighthouse (diagnostics only) | `LIGHTHOUSE=1` with the same script, 19:49 to 19:53 IST, Lighthouse 12.8.2, load average 2.8 to 4.1 | `lighthouse.json`: performance 0.91 to 0.96, accessibility 1, best practices 1, largest contentful paint 2.4 to 3.0 s, CLS at most 0.0008, blocking time at most 1 ms, 154 bytes of script. The comparison scores 0.66 for SEO because it is never indexed, by design. A run started at 18:35, while other projects' test suites loaded the machine, gave 0.31 to 0.46 and one missing report; it was discarded. These figures do not show NFR-PERF-1 met. |

The machine restarted at about 19:44 IST; the evidence above was produced after the restart. The checks rebuild `smartgro_testing`, so its fixtures are gone after the next test run.

### 11.3 Criteria after the closure round (statuses unchanged)

| Item | Status | Evidence | What it waits on |
|---|---|---|---|
| AC-6.1 | PARTIAL | the FR-DISC rows below | — |
| AC-6.2 | BLOCKED; performance **NOT VERIFIED** | `lighthouse.json` (diagnostics only) | staging (P6-6, OD-79) and the measuring method (P6-9) |
| AC-6.3 | PASS, accepted (OD-114) | `public.json` pages and error pages; `admin.json` | — (not tested: §11.4) |
| AC-6.4 | PASS, accepted (OD-114) | `public.json` "noJs" and "flows" | — |
| FR-DISC-1 | PASS, accepted (OD-114) | `LocationControllerTest`, `WorkspaceControllerTest`; `public.json` | real copy and photos (O-6, D-12) |
| FR-DISC-2 | PARTIAL | `LocationControllerTest` (filter); `public.json` flows | team size and move-in (O-2, O-3), amenities (C-2.7), map toggle (D-18) |
| FR-DISC-3 | BLOCKED | — | inventory and pricing (AC-5.1 to AC-5.4; O-2, O-3, O-4) |
| FR-DISC-4 | PARTIAL | `LocationControllerTest` (comparison); `public.json` flows | prices and availability (O-2, O-3), amenities (C-2.7) |
| FR-DISC-5 | PARTIAL | `LocationControllerTest` (phone bar); `public.json` phone bars | P6-5 city context, after D8.1 is re-verified (L-1); tours (Phase 8) |
| FR-DISC-6 | PARTIAL | `PublicShellTest`, `LocationControllerTest`; `public.json` keyboard and phone bars | "Book a tour" (Phase 8); tap tracking (OD-100, L-4) |
| FR-DISC-7 | PARTIAL | `PropertyProfileWorkflowTest`, `LocationControllerTest` | the details themselves (C-2.9, O-6); embedded map (D-18) |
| "404/410 pages" | PASS, accepted (OD-114) | `ErrorPagesTest`; `public.json` statuses | — |
| Other deliverables (§1.3) | unchanged | — | as listed in §1.3 |

**Phase 6 cannot be fully accepted yet:** AC-6.1 (FR-DISC-2 to FR-DISC-7) and AC-6.2 cannot pass without owner, legal, content, Phase 5 or staging input. The next step is the owner's further decision (P6-8, register A-10).

### 11.4 Still not tested

* **Performance (AC-6.2): NOT VERIFIED.**
* A manual screen-reader pass: no screen reader (NVDA, VoiceOver, TalkBack) is available on this machine. The keyboard check is automated, not manual.
* Real phones, and browsers other than Chromium.
* axe on the image and testimonial sections (they need real media and consented testimonials), the enquiry confirmation page (D8.1) and staff previews.
* A real maintenance window: it is simulated in the tests only, so that no shared server is taken down.
* Real content: every page was checked with labelled fixtures.

### 11.5 What staging needs for AC-6.2 (P6-6, P6-9)

* **Environment** (OD-79 defers it; the owner and an infrastructure operator act): HTTPS and "noindex", as in `../operations/DEPLOYMENT_RUNBOOK.md`; PHP OPcache; configuration, route and view caches built at deploy; gzip or Brotli for HTML, CSS and JavaScript; long, immutable cache headers on the hashed files in `/build/`; a MySQL database with synthetic, production-sized data.
* **Lab measurement:** Lighthouse, mobile preset with simulated throttling, median of three runs for each public template (home, locations, city, sector, property, offering, category, category × city, comparison, enquiry, contact). Thresholds: LCP ≤ 2.5 s and CLS ≤ 0.1 (NFR-PERF-1); first-party JavaScript ≤ 90 KB compressed (now 0.15 KB) and the hero image ≤ 200 KB at phone size (NFR-PERF-2; no real photos yet). Server response: TTFB p95 ≤ 600 ms (NFR-PERF-3), which needs a load-testing tool the owner approves.
* **Field measurement:** INP ≤ 200 ms and the 75th-percentile figures of NFR-PERF-1 describe real visitors. They need real-user data after launch, and any analytics script waits for the consent decision (OD-100). Until then the owner decides whether lab figures on staging count as the interim proof (P6-9).

### 11.6 Other changes in the same period (not part of this work)

* **Another working session wrote to `smart_go`** at the owner's instruction "i want it to be run on my local server as well, fix it" (register OD-115 and OD-116). It made a backup, cleared the route cache, applied the 21 pending migrations as batch 10, seeded reference data, synced permissions and published the preview's menus. A read-only check afterwards found 44 migrations, 43 tables (the C-28 columns included) and 3 inactive helper users. **This closure work did not write to `smart_go`:** its database operations used `smartgro_testing` only, and `smart_go` was only queried read-only.
* **Correction:** the server on port 8000 belongs to another project (HRMS). The owner's SmartGro server ran on port 8001, as seen at 17:42 IST. Earlier reports and §5.1 and §10.1 of this pack called port 8000 the owner's server; that was wrong. No owner server was touched.
* The other session's OD-115 entry says no code had changed since the last 910-test pass. The layout (scroll padding, 18:00 IST) and `PageRenderer` (18:15 IST) changed in this round. The 923-test run (18:15 to 18:23 IST) covers both changes.

### 11.7 Status

Phase 6: **partially accepted (OD-114)**. All code-completable work is done; the remaining items need the inputs in §8 and register A-8 and A-10. Phase 7: **not started**; its gate is not met (register OD-117 b). **Update (2026-10-28):** the owner let Phase 7 start (OD-118); Phase 6 stays partially accepted.
