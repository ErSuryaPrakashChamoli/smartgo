# SmartGro — Phase 5 Implementation Record and Acceptance Pack

| | |
|---|---|
| Status | **Implemented locally for owner review (2026-10-27, rounds 40 and 41, OD-109, OD-110); reviewed and re-verified in round 42 (OD-111); documentation reviewed in round 44. **Partially accepted by the owner on 2026-10-27 (§13); not fully accepted.** Nothing is committed, pushed or deployed. Items that need real inventory, pricing values, legal text or an owner decision are **BLOCKED** or **PARTIAL** below, never marked as passed. |
| Scope | The owner's "Phase 5 complete implementation directive" (OD-109): property and workspace management, profiles and media, workspace offerings, public property and workspace pages, enquiries, Sales property restrictions, permissions, integrity and concurrency. The master plan's Phase 5 (inventory, pricing, availability engine) is mapped too; its entry gate is still not met. |
| Databases | Tests: `smartgro_testing` only (forced by `phpunit.xml` and the `_testing` guard; the effective database was checked before each run). Preview: `smartgro_preview` only (OD-104). **`smart_go` was never written to**: checked read-only before and after the work (23 migrations, batch 9, no Phase 5 tables, no media). |
| Related | `../phases/PHASE_STATUS.md` (Phase 5), `../decisions/DECISION_REGISTER.md` (OD-103 to OD-111), `../security/SECURITY_MODEL.md` §1 and §11, `../architecture/DATABASE_ERD.md` (C-24 to C-27), `../operations/LOCAL_DEVELOPMENT.md` §3 (preview) |

**Tests and browser checks show that the rules are coded and the screens work. They do not replace the owner's review or real content from SmartGro.**

**Round 42 review (OD-111).**
* **Independent re-run:** before any change, the full suite on `smartgro_testing` gave **882 passed, 0 failed, 0 skipped (3,359 assertions, exit code 0)**, confirming the round 41 report.
* **Decisions recorded:** OD-111 (a) to (e). Property Managers never change status or publish; Sales/CRM restricted by default, superseding OD-36's default; the form stays closed until the privacy notice; no personal data in logs and no notifications; nothing invented.
* **Gap found and fixed:** a database error while storing an enquiry would have written the visitor's name, email and phone into `laravel.log`, because Laravel puts query values into the error message. Bound values are now masked, quoted values are removed from database error messages, and traces carry no call arguments. Tests cover each protection, and each test fails when its protection is removed.
* **Coverage added:**
  * Property Managers and Operations staff refused publishing, scheduling and taking down a property's profile;
  * the take-down button shown disabled to a Property Manager;
  * no mail or notification on submission;
  * an enquiry refused closure on screen while no reasons exist, with a hint naming where reasons are added.
* **Owner input template:** `../templates/phase5-owner-inputs/SmartGro_Phase5_Owner_Inputs_SG-INP-1.0.xlsx`, blank apart from the confirmed properties and categories.

---

## 1. Acceptance criteria

Status: **PASS** (implemented and verified), **PARTIAL** (safe local part done; the rest named), **BLOCKED** (needs an input or decision that does not exist yet).

### 1.1 Master plan, Phase 5 (`PROJECT_MASTER_PLAN.md`)

Owner decision of 2026-10-27 (§13): all four criteria are **excluded** from the partial acceptance and stay BLOCKED.

| ID | Criterion | Status | Where / evidence | Remaining |
|---|---|---|---|---|
| AC-5.1 | FR-INV-1 to FR-INV-6 pass | **BLOCKED** | Products (categories) and offerings exist (FR-INV-4 in part). No floors, spaces, allocations, price plans, offers or availability service: the entry gate (floors and real inventory via SG-INV-1.3, pricing PR-1…PR-11) is not met, and nothing may be invented (OD-30, OD-32, OD-35, D-36). | Owner and SmartGro supply floors, inventory and pricing values |
| AC-5.2 | Unit tests for every BR-AV and BR-PR rule | **BLOCKED** (BR-AV-6 done) | BR-AV-6 (planned or pre-opening shown as "Opening {month year}", never bookable): `PropertyStatus::allowsListing`, `LocationControllerTest` | Needs spaces, allocations and price plans |
| AC-5.3 | A real location's inventory is imported | **BLOCKED** | No inventory file supplied | SG-INV-1.3 for one property |
| AC-5.4 | Propagation within 60 s | **BLOCKED** | No availability yet | After AC-5.1 |

### 1.2 Phase 5 directive (OD-109)

Owner decision of 2026-10-27 (§13): the items marked **PASS** below are **accepted**. D8.1 and D8.4 are excluded and stay BLOCKED. D4.1 and D6 (PARTIAL) are not accepted. D8.6 is outside Phase 5.

| ID | Requirement | Status | Implementation | Verification |
|---|---|---|---|---|
| D4.1 | Property records: name, code, slug, address, city, sector, PIN, description, directions, map, contact details, opening hours, photos, status and website visibility, SEO, workflow state, audit | **PARTIAL** | `Property`, `Building`, `PropertyProfile`; `SaveProperty`; Filament Properties cluster. Map: a link built from recorded coordinates (no embedded map, no third-party script). **Round 41:** the location's own phone, WhatsApp and email on the profile, shown only once a version with them is approved and published (otherwise SmartGro's main published details); cities, sectors and categories now save from their back-office forms (defect fixed, see §10); sectors audited. | `SavePropertyTest`, `OpeningHoursTest`, `PropertyProfileWorkflowTest` (contact details, formats), `PropertyScreensTest` (city, sector and category forms); browser. **Not built:** extra buildings and floors (entry gate). |
| D4.2 | Operational status separate from marketing publication; Property Managers neither publish nor change status | **PASS** (decided, OD-111 a) | Status: operations staff (`SaveProperty::OPERATIONS_ONLY_FIELDS`, refused on the server for Property Managers). Publication: the property profile's approval workflow (`content.publish`). | `PropertyManagerScopeTest` (status refused on the server, field disabled, crafted save keeps the status), `PropertyProfileWorkflowTest` (round 42: Property Managers and Operations refused publishing, scheduling and take-down), `OfferingProfileWorkflowTest`, `PropertyProfileScreensTest` (take-down disabled for a Property Manager); browser |
| D4.3 | Lifecycle; non-public states never leak | **PASS** | `PropertyStatus::allowsListing`, `Property::scopePubliclyListed` (state **and** published profile). Closed or temporarily closed: 404; planned or pre-opening: only with an opening date. | `LocationControllerTest`, `PropertyAndEnquiryConcurrencyTest` (close while publishing), `PropertyProfileWorkflowTest` (a scheduled profile is not published once the property closes; the failure is audited) |
| D5 | Property profiles with photos, SEO, preview, submit, approve, reject, resubmit, publish, take down, version history, audit | **PASS** | `PropertyProfile` + `HasWebsiteProfile`; revision workflow (ADR-0005); take-down via `UnpublishContent`; signed preview on the real property page | `PropertyProfileWorkflowTest`, `PropertyProfileScreensTest`; browser: Property Manager draft → second person approves → third publishes |
| D5-M | Property media: assigned-property uploads, correct association, validation, no unpublished exposure, photo limit, approval for replacement or removal, audit | **PASS** | `media_assets.depicts_property_id` (set at upload, fixed); `CreateMediaAsset`, `MediaAssetPolicy`, `MediaAsset::scopeVisibleTo`; photo rule in `HasWebsiteProfile` (SmartGro or commissioned photography **of that property**, valid licence, max 12); `MediaUsage` | `PropertyMediaTest`, `MediaLibraryTest`, `PropertyProfileWorkflowTest`; browser upload by a Property Manager |
| D6 | Workspace offerings per property and category, profiles, workflow, constraints | **PARTIAL** | `Offering` (unique property × category, deactivated not deleted), `OfferingProfile` (headline, description, included, not included, photos, SEO); Filament "Workspace offerings" | `OfferingProfileWorkflowTest`, `WorkspaceControllerTest`. **Not built:** capacity, configuration, prices, availability (inventory and pricing; marketing may never set them, FR-CMS-6). |
| D7 | Public property, sector, category and offering pages from published records; 404 otherwise; canonical; enquiry CTAs; navigation through the CMS | **PASS** | `LocationController` (round 41: sector pages `/locations/{city}/area/{sector}`, linked from the city page and the property breadcrumb), `WorkspaceController`, `LocationPages`, `WorkspaceCatalogue`; menu sections `workspace_types`, `enquire` and location links (FR-CMS-4) | `LocationControllerTest`, `WorkspaceControllerTest`, `PublicShellTest`, `HomeControllerTest`; browser at 1440 and 360 px |
| D8.1 | Public enquiry form and submission (validation, CSRF, rate limits, trap field, timing, duplicates, context re-checked, consent recorded; never a booking) | **BLOCKED** for public use (built and verified; closed by decision OD-111 c until the approved privacy notice, input L-1). It stays blocked until the notice is published **and** the opened form is re-verified on the server: submission accepted, consent and notice version stored, nothing logged | `EnquiryController`, `SubmitEnquiry`, `EnquiryForm::isOpen` (a published page at `legal/privacy`), `x-site.enquiry-form` | `EnquiryControllerTest` (closed: no form fields and every submission refused with nothing stored; open: storage, validation, spam, limits), concurrency; browser |
| D8.2 | Admin inbox: scope, search, filters, view, assign, status, notes, follow-ups, history, export | **PASS** | Sales → Enquiries | `LeadWorkflowTest`, `EnquiryScreensTest`, `EnquiryScopeTest`; browser |
| D8.3 | Closing reasons (BR-CRM-5, FR-CRM-5) | **PASS** (lists empty until the Owner supplies them, input O-7) | Sales → Closing reasons (`leads.settings.manage`, Super Admin); `ChangeLeadStatus` refuses lost or disqualified without an active reason for that status | `LeadWorkflowTest`, `LeadCloseReasonScreensTest`, `EnquiryScreensTest` (round 42: refused on screen while none exist, nothing changed); browser |
| D8.4 | Notifications of new enquiries | **BLOCKED** (decision OD-111 d; input O-1) | None sent. The back office shows a count of new unassigned enquiries. | `EnquiryControllerTest` (round 42: no mail or notification is sent) |
| D8.5 | Personal data never in logs, exports only by the Super Admin, shown only in scope | **PASS** (round 42 fix) | Nothing in the enquiry path logs; database errors masked and redacted (`config/database.php`, `bootstrap/app.php`); traces without arguments (`AppServiceProvider`); keyed hashes for limits and locks | `EnquiryControllerTest` (successful and failed saves leave no name, email or phone in the log), `AppServiceProviderTest`; mutation checks (§6) |
| D8.6 | Assignment rules, SLA alerts, category-specific fields, enterprise form | Not in Phase 5 (Phase 9: FR-CRM-2, BR-CRM-4, D-09; Phase 8: FR-PROD-3, FR-BK-1) | — | — |
| D9 | Sales/CRM property restrictions everywhere | **PASS** (default decided, OD-111 b) | `StaffRole::limitsToAssignedProperties`, `users.has_all_property_access`, `Lead::scopeVisibleTo`, `LeadPolicy`, every action re-checks under lock | `EnquiryScopeTest` (direct URLs, crafted filters, search, removal, reassignment); browser: 404 for another property's enquiry. There are no API endpoints or background jobs that read enquiries. |
| D10 | Permissions and separation of duties | **PASS** | `Capability` (`profiles.*`, `leads.*`, round 41 `leads.settings.manage` for the Super Admin only), `StaffRole`, policies; `RevisionPolicy` with property scope | `SyncAccessControlTest`, `DesignPreviewPagesTest` (review page matches SECURITY_MODEL), scope and workflow tests |
| D11 | Integrity and concurrency | **PASS** | Foreign keys restrict deletion of enquiries, contacts, notes, profiles; unique keys; transactions with row locks; stale-change refusal; MySQL named locks for identical submissions | `PropertyAndEnquiryConcurrencyTest` (3 races, parallel processes), existing publishing races; migrations rolled back and re-applied on the test database |
| D12 | Back office UX | **PASS** | Filament resources with filters, badges, empty states, confirmations, previews, approval queue entries | Screen tests; browser |
| D13 | Automated tests | **PASS** | See §6 | Full suite on `smartgro_testing` |
| D14 | Browser verification | **PASS** | See §7 | Headless Chromium (Playwright) at 1440 and 360 px |
| D15 | Quality and security checks | **PASS** | Pint, build, audits, route review, log review (§6) | No static analyser is configured in the project; none was added |
| D16 | Documentation | **PASS** | This file; PHASE_STATUS, DECISION_REGISTER (OD-109 to OD-111), SECURITY_MODEL (§8 logs), BUSINESS_RULES and PRD notes (OD-111 b), DATABASE_ERD (C-27), LOCAL_DEVELOPMENT; owner input template SG-INP-1.0 | — |

### 1.3 Related PRD requirements touched

| ID | Status | Note |
|---|---|---|
| FR-PROP-1 | PARTIAL | Properties, primary building, address, status, operating model; floors not built |
| FR-CITY-1 / BR-PROP-4 / OD-22 | PASS | Hidden by default; visible without a property → "Coming soon", not indexed, register-interest form (source `city_waitlist`) |
| FR-DISC-1 | PASS (final copy in Phase 6) | City, sector (micro-market, round 41), property and category × city pages, server-rendered; unpublished or empty combinations answer 404 and are not linked |
| FR-DISC-3 | PARTIAL | Property page per IA §3.1 with real sections only; no live availability or price (blocked) |
| FR-DISC-5 | PASS | Every Enquire link carries the property and category; the enquiry stores them after re-checking |
| FR-DISC-7 | PARTIAL | Directions text and a map link from coordinates; structured data waits for Phase 11 |
| FR-CMS-5 (galleries) / FR-CMS-6 | PASS | Concept, render and stock images refused for property photos; profiles never hold capacity, status, availability or price |
| FR-BK-1 | PARTIAL | General, property, category and offering enquiries, at most eight visible fields, spam controls; enterprise seat bands and virtual-office business details not built |
| FR-CRM-1 | PARTIAL | Inbox, filters, ownership, status, scope (restricted by default, OD-111 b), first-response time recorded and shown; SLA timers and alerts (BR-CRM-4, D-09, Phase 9) and full duplicate detection not built (contacts are matched on email or phone, BR-CRM-2) |
| FR-CRM-5 / BR-CRM-5 | PASS | Round 41: managed lists of lost and disqualification reasons, required on those statuses, optional details, cleared on reopening, audited; the lists start empty |
| FR-CRM-6 | PASS | Export: Super Admin only, audited |
| FR-SEG-1 / BR-CRM-1a | PASS | Eight segments seeded (OD-04), optional on the form, shown on the enquiry |

---

## 2. Routes and workflows

| Route | Who | What |
|---|---|---|
| `/locations`, `/locations/{city}` | Public | Cities and their properties on the website by sector; a visible city without one shows "Coming soon" |
| `/locations/{city}/area/{sector}` | Public | A sector's properties on the website (round 41); 404 while it has none |
| `/locations/{city}/{property}` | Public | Property page (published profile, address, hours, published offerings, nearby); 404 otherwise |
| `/locations/{city}/{property}/{workspace}` | Public | One published offering; 404 otherwise |
| `/workspaces`, `/workspaces/{workspace}`, `/workspaces/{workspace}/{city}` | Public | Hub (all types; links only where an offering is published), category and category × city pages; 404 without a published offering |
| `GET /enquire`, `POST /enquire` (throttled), `/enquire/received` | Public | Enquiry form, submission, confirmation (reference only) |
| `/admin/properties/properties` | Super Admin, Operations (edit); Property Manager (assigned, address and hours only); others view | Property records, hours, categories offered (Operations) |
| `/admin/properties/website-profiles`, `/admin/properties/workspace-offerings` | `profiles.view` / `profiles.edit`; approve and publish: `content.approve` / `content.publish` | Profiles through draft → submitted → approved → published, with preview, rejection, scheduling, take-down and version history |
| `/admin/website/media-assets` | `media.manage` (Property Manager: assigned properties only) | Upload with origin, licence and property shown |
| `/admin/sales/enquiries` | `leads.*` within scope | Inbox, view, assign, status (closing reasons from the list), notes and follow-ups, export (Super Admin) |
| `/admin/sales/closing-reasons` | `leads.settings.manage` (Super Admin) | The lists of lost and disqualification reasons; switched off, never deleted |
| `/admin/settings/users` | Super Admin | Roles, assigned properties, "Sales/CRM Executive works on every property" |

**Publication rules.** A property is on the website only while its state allows it and its profile is published; publishing needs a complete address and at least one active offering (BR-PROP-3, floors not checked). An offering is shown only while it is active, its category is active, its profile is published and its property is on the website. The checks run at submission and again under the publishing lock; a profile can be taken down at once by anyone who may publish it.

**Enquiry workflow.** Visitor sends the form → validated (details first, then spam checks) → contact matched on email or phone without overwriting it → enquiry stored as **new** and **unassigned**, with the agreement and notice version → staff within scope see it (a navigation badge counts new unassigned ones) → assign (only to staff who may work on that property) → status: new → contacted / qualified → lost / disqualified (a reason from the managed list for that status, optional details) or reopened as contacted (reason cleared) → notes, contacts made and follow-ups → audit history on the enquiry. Nothing reserves, books or charges anything.

---

## 3. Roles and permissions (implemented)

| Capability | Super Admin | Operations | Property Manager | Sales/CRM | Marketing | Finance |
|---|---|---|---|---|---|---|
| `properties.view` | All | All | Assigned | Assigned (all if set per person) | All | All |
| `properties.manage` | All | All | Assigned: address and hours only | — | — | — |
| `products.manage` (categories offered) | Yes | Yes | — | — | — | — |
| `profiles.view` | All | All | Assigned | Assigned (all if set) | All | — |
| `profiles.edit` | All | — | Assigned | — | All | — |
| `content.approve` / `content.publish` (incl. profile take-down) | Not own | — | — | — | Not own | — |
| `media.manage` | All | — | Assigned properties' photos | — | All | — |
| `leads.view` | All | — | Assigned (read-only) | Assigned (all if set) | — | — |
| `leads.manage`, `leads.assign` | All | — | — | Assigned (all if set) | — | — |
| `leads.export` | Yes | — | — | — | — | — |
| `leads.settings.manage` (closing reasons) | Yes | — | — | — | — | — |

Deactivated accounts can do nothing (every policy's `before`). Role names never grant anything: policies check capabilities and, for property data, assignments.

---

## 4. Database changes

All additive, all run only on `smartgro_testing` (by the tests, plus a rollback and re-apply check) and `smartgro_preview`. **None on `smart_go`.**

| Migration | Change |
|---|---|
| `2026_10_26_123746` … `123759` | cities, micro_markets, properties, buildings, products, offerings (C-24) |
| `2026_10_26_132509` | navigation_items.section (menu links to parts of the site) |
| `2026_10_26_134821` | property_user (assignments) |
| `2026_10_26_134822` | properties.operating_hours |
| `2026_10_26_134824` | property_profiles |
| `2026_10_26_182919` | users.has_all_property_access |
| `2026_10_26_182921` | media_assets.depicts_property_id |
| `2026_10_26_182923` | offering_profiles |
| `2026_10_26_182924` | customer_segments |
| `2026_10_26_182925` | contacts |
| `2026_10_26_182926` | leads |
| `2026_10_26_182927` | lead_activities |
| `2026_10_27_050051` | property_profiles.phone, whatsapp, email (round 41) |
| `2026_10_27_050052` | lead_close_reasons (round 41) |
| `2026_10_27_050053` | leads.close_reason_id (round 41) |

---

## 5. Media handling

Uploads are checked by content (JPEG, PNG or WebP, up to 10 MB and 200–8,000 px, never SVG), stored on the private disk under the media ID, and served only as WebP renditions: to visitors only while a live page, profile or offering on the website uses them; to staff only within their scope. A photo's property is chosen at upload (required for Property Managers, limited to their properties) and never changes. Profiles accept only SmartGro or commissioned photography of their own property with a valid licence. Replacing or removing a photo is a profile change that needs approval; an image used by any content or version cannot be deleted. Uploads, edits and deletions are audited.

---

## 6. Automated checks (2026-10-27)

| Check | Command | Result |
|---|---|---|
| Full suite (MySQL `smartgro_testing`) | `php artisan test --compact` (with the cache overrides in LOCAL_DEVELOPMENT) | Round 42, after the changes: **888 passed, 0 failed, 0 skipped (3,394 assertions, exit code 0)** (6 new tests; another project's test run shared the machine, not the database). Round 42, before any change (independent re-run of round 41): 882 passed, 0 failed, 0 skipped (3,359 assertions, exit code 0). Round 40: 866 passed. |
| Phase 5 tests (new or extended) | the files listed in §1.2 | included above; 0 failed |
| Concurrency and MySQL harness (parallel processes) | `php artisan test --compact tests/Feature/Infrastructure` | 19 passed, including the 3 new Phase 5 races |
| Mutation check | per-email and per-phone locks removed | the duplicate-submission race fails; restored |
| Mutation check (round 41) | the city form's mass-assignable fields removed | the city, sector and category form test fails; restored |
| Mutation checks (round 42) | each log protection removed in turn: value masking, the database-error reporting rule, the trace setting | the matching test fails each time; restored |
| Formatting | `vendor/bin/pint --dirty --format agent` | passed |
| Front-end build | `npm run build` | succeeded |
| Dependencies | `composer audit`; `npm audit` | **Temporary security exception, open; not resolved.** Recorded on the owner's instruction (round 44) until an upstream-compatible fix exists; register item D-1. Round 42: **1 advisory**, published 2026-10-27 03:15 UTC: `phpunit/phpunit` 13.4.3 (test-only, installed through Pest), local code execution through a shared temporary folder in PHPT coverage handling (GHSA-9vw2-f4vp-x7gf). The project has no PHPT tests and runs no coverage. The fix is PHPUnit 13.4.4. **Update approved by the owner but not possible yet (round 43):** the newest stable Pest, v5.3.3, conflicts with PHPUnit above 13.4.3, and every stable Pest 5.3.x release has the same limit. Composer dry runs refused 13.4.4 alone, with `-W`, and with Pest and its Laravel plugin allowed to update; no file was changed. Exposure here: the vulnerable path is the fixed shared folder `<temp>/phpunit-phpt` used only when PHPT tests run with code coverage; the project has no PHPT tests and no coverage driver (Xdebug or PCOV) is loaded. **Re-checked in round 44:** Pest v5.3.3 is still the newest stable release and still conflicts with PHPUnit 13.4.4; `composer install --dry-run` reports nothing to change, so `vendor/` matches the lock file; `composer audit` still reports the advisory. Not allowed as workarounds (owner, round 44): forcing PHPUnit 13.4.4 or later, a Pest development branch, or editing `composer.json`, `composer.lock` or `vendor/`. `npm audit`: 0 vulnerabilities. Round 41: no advisories. |
| Migrations | rollback of the 10 newest and re-apply on `smartgro_testing`; round 41: rollback of the 3 new ones and re-apply | all down and up |
| Routes | `php artisan route:list` | no API routes; only `POST /enquire` writes publicly, throttled |
| Logs | isolated preview and test logs after the browser runs | no errors; round 42: both isolated log folders empty, no 500 responses |
| Static analysis | — | none configured in the project; none added |

---

## 7. Browser verification (headless Chromium, Playwright from the npx cache)

| Check | Database | Result |
|---|---|---|
| Home, locations, property, offering, hub, category, category × city, enquiry pages at 1440 and 360 px | test (labelled fixtures) | 200, no horizontal overflow, no external requests, no console errors |
| Unpublished property and missing category | test | 404 at both widths |
| Enquiry form at 360 px: empty submission, then a valid one | test | errors listed at the top and by each field; confirmation with reference, "This is not a booking" |
| Sales/CRM Executive of H-105 changes an enquiry's status | test | done in the UI; shown as Contacted |
| Sales/CRM Executive of A-70 opens H-105's enquiry by address | test | 404 |
| Property Manager uploads a photo of A-70 (only A-70 offered), writes and submits the profile | test | submitted; no publish button for the Property Manager; the property page stays 404 |
| Second person approves, third publishes | test | live; the property page shows the profile and photo at 1440 and 360 px |
| Home, locations, workspaces, enquiry (closed), admin screens for Property Manager, Operations and Super Admin | preview (reference data only) | 200 or the expected 404, no overflow, no external requests |
| Round 41: city page with sector links, sector page, sector without a listed property, at 1440 and 360 px | test | 200 with links only to listed sectors; 404 for the unlisted one; no overflow, no external requests, no console errors |
| Round 41: a location's own contact details on its property and offering pages | test | "Talk to SmartGro H-105" with call, WhatsApp and email links (fixture values) at both widths |
| Round 41: closing reasons | test | Sales/CRM Executive: 403; Super Admin: empty state, then adds a reason; the H-105 Sales/CRM Executive marks the enquiry lost with it, and the page shows the reason and details |
| Round 41: Property Manager enters "call reception" as the location's phone | test | refused on save, error on the field ("The phone field format is invalid.") |
| Round 41: Operations saves the city form and adds a sector | test | saved; the new sector has no page (404) until a property in it is on the website |
| Round 41: closing reasons and profile screens, and a sector address | preview | 200 at both widths, no overflow, no external requests; the sector answers 404 (nothing published) |
| Round 42: enquiry page without a privacy notice, 1440 and 360 px | preview | "Online enquiries open soon"; no form and no fields; no overflow, no external requests, no console errors |
| Round 42: crafted submission with a valid CSRF token while the form is closed | preview | 302 back to `/enquire` (the CSRF check passed, so the gate refused it); read-only count afterwards: 0 enquiries, 0 contacts |
| Round 42: closing reasons for the Super Admin, 1440 and 360 px | preview | empty state "No closing reasons yet"; no overflow |
| Round 42: Sales/CRM Executive tries to mark an enquiry lost while no reasons exist, 1440 and 360 px | test | hint "No reasons for marking an enquiry lost are set up yet…"; submission blocked; still New |
| Round 42: Property Manager of H-105 and an Operations Manager open H-105's live profile | test | "Take off the website" disabled; no publish button |
| Round 42: Property Manager opens A-70's record | test | Status field greyed out (disabled) |
| Round 42: rerun of every round 40 and round 41 workflow on fresh fixtures | test | same results as before: public pages 200 or the expected 404, no overflow, no external requests; enquiry sent with a reference; Sales scope 404; Property Manager upload, submit, second-person approval, third-person publication, page live with the photo; sector pages; location contact details; closing reasons; phone validation; city and sector forms save |

Fixtures on the test database were labelled "TEST FIXTURE" and are removed by the next test run. Screenshots were kept in the session's scratch folder, not in the repository.

---

## 8. Known limitations and dependencies

* **Nothing is on the public website yet** in the preview: profiles and offerings need SmartGro's real copy and photos, approved and published.
* Enquiry form closed until the approved privacy notice is published at `/legal/privacy` (OD-111 c, D-15).
* No notification of new enquiries (OD-111 d): a channel must be chosen, configured and its delivery verified first. Mail is configured as `log` locally, so no enquiry mail may be sent through it.
* No capacity, configuration, prices, availability, floors, spaces, offers or inventory import (entry gate).
* No assignment rules or SLA alerts (FR-CRM-2, BR-CRM-4: Phase 9, values D-09), category-specific enquiry fields or enterprise seat-band form (FR-PROD-3, FR-BK-1: Phase 8, product configuration is behind the entry gate).
* Closing reasons start empty: until the Super Admin adds them, no enquiry can be marked lost or disqualified (fail-closed, OD-110 b).
* Retention and deletion of enquiry data: undecided (BR-PRV-4, D-29); nothing is deleted.

## 9. Owner action register

Decided in round 42 (OD-111): Property Manager status and publication, the Sales/CRM default, the closed form, no personal data in logs and no notifications, nothing invented. Everything below is still **open**. IDs match the "Legal and operations" sheet of `../templates/phase5-owner-inputs/SmartGro_Phase5_Owner_Inputs_SG-INP-1.0.xlsx`, which is blank apart from the four confirmed properties and seven categories.

| Group | ID | Action | Who | Unblocks |
|---|---|---|---|---|
| Legal | L-1 | Privacy notice approved by counsel, published at `/legal/privacy` through the website workflow (author, second approver, publisher) | Owner, counsel | D8.1, after the open form is re-verified |
| Legal | L-2 | Retention period and deletion process for enquiry and contact data (BR-PRV-4, D-29) | Owner, counsel | Data-protection compliance; nothing is deleted until then |
| Legal | L-3 | Grievance officer name and contact (C-7.4) | Owner | Privacy notice, footer |
| Legal | L-4 | Terms of use, cookie policy, consent approach for analytics (OD-100) | Owner, counsel | Legal pages; tracking stays off |
| Legal | L-5 | Legal entity details (D-01) | Owner | Legal pages, footer |
| Legal | L-6 | Customer-facing cancellation wording (C-4.2, PR-10) | Owner, counsel | Pricing |
| Operational | O-1 | Notification channel, recipients, email provider and sending domain; delivery verified before switching on (C-7.3) | Owner | D8.4 |
| Operational | O-5 | Categories each location offers ("Offerings" sheet) | Operations, Owner | Offering pages |
| Operational | O-6 | Content, photographs with ownership or licence, any own contact details per location ("Location content" sheet, C-6.3) | Marketing, Operations | Pages going live |
| Operational | O-7 | Closing reasons ("Closing reasons" sheet, C-5.4) | Owner (entered by the Super Admin) | Marking enquiries lost or disqualified |
| Operational | O-8 | Which Sales/CRM Executives and Property Managers cover which property; anyone who works on every property | Owner | Back-office access |
| Inventory | O-2 | Floors per property and the first property's inventory in SG-INV-1.3 (Q-OW-1, AC-1.4) | Owner, Operations | AC-5.1, AC-5.3, then AC-5.4 |
| Pricing | O-3 | Pricing decisions PR-1 to PR-11 and price values (OD-35; pricing sheets of SG-INP-1.0; GST and SAC from the tax adviser) | Owner, Finance | AC-5.1, AC-5.2 |
| Business decision | O-4 | Confirm or change the Phase 5 default values BR-SOT-4, BR-AV-2 (inventory) and BR-PR-2, BR-PR-4 (pricing) | Owner | Entry gate |
| Business decision | O-9 | Online booking and payment scope (D-07, D-08) | Owner | Payment rules (PR-9), Phase 8 |
| Security exception | D-1 | PHPUnit 13.4.3 advisory PKSA-qhjm-rpqc-qkn6 / GHSA-9vw2-f4vp-x7gf (test-only). Pending an upstream fix: when a stable Pest release accepts PHPUnit 13.4.4 or later, approve updating Pest and PHPUnit together, then re-run the suite and `composer audit`. Until then, no PHPT tests and no code coverage on shared machines or CI | Owner (approval), developer | A clean `composer audit` |
| Acceptance | A-1 | ~~Review this pack and record an acceptance decision.~~ **Recorded 2026-10-27: partially accepted** (§13) | Owner | Done |
| Acceptance | A-2 | Once the excluded items are resolved (O-2, O-3, O-4, L-1 with re-verification, O-1, D-1), a further owner decision on the rest of Phase 5. Phase 6 does not start until the remaining Phase 5 requirements and this decision are addressed (owner, 2026-10-27). | Owner | Rest of Phase 5; Phase 6 start |

## 10. Security and operational notes

* Personal data: stored only in `contacts` and `leads`; never logged (round 42: also not through database errors or traces, see D8.5); shown only to staff in scope; exported only by the Super Admin with an audit entry; the confirmation page shows only the reference. Password-reset and licence-reminder emails to staff go to the `log` mailer locally; they carry no enquiry data, and a real mail service is needed before go-live (O-1).
* The local preview runs PHP's built-in server; it must be started with `-d variables_order=EGPCS` so its separate storage folder is used (found and fixed during the browser checks: the first runs wrote test-fixture files to the shared `storage/`, which were removed; `smart_go` has no media, so nothing collided).
* **Defect found and fixed in round 41:** the city, sector and workspace category forms could not save (the models allowed no form fields, and Filament does not bypass that). They now list exactly the fields their forms set; every other write still goes through an action. A regression test covers the three forms.
* `bootstrap/cache` holds a route cache written outside the session (17:55 on 2026-10-26) that predates the Phase 5 routes; the documented commands bypass it without deleting it.

## 11. Evidence: what was verified and what was not

**Verified in this project** (commands run on this machine, with the output recorded in this pack):
* test suites, mutation checks, Pint, the front-end build, `composer audit`, `npm audit`, migration rollbacks;
* read-only database checks of `smart_go`, `smartgro_preview` and `smartgro_testing`;
* Composer dry runs and the PHPUnit and Pest version checks;
* headless browser runs.

**Provided by the owner and used as given, not independently verified:**
* the four properties, their codes, addresses and sectors, and that all are owned and operated (OD-08, OD-13, OD-14);
* the seven workspace categories (OD-07);
* the eight customer segments (OD-04);
* city names and aliases (OD-03, OD-16);
* "10 floors in total" (OD-08).

The OD decisions are the owner's instructions, recorded as given.

**Cannot be re-checked from the repository:**
* Screenshots, browser logs and full test output were kept in the session's scratch folder, not in the repository. They will not survive the session; re-running the commands in §6 and §7 reproduces them.
* The advisory details come from `composer audit` (FriendsOfPHP database). GitHub was not consulted, on the owner's instruction.
* Browser checks used one headless Chromium at two widths: no real phones, other browsers or assistive technology.
* Concurrency tests ran on one machine. There is no CI run, because GitHub and CI are deferred (OD-79).

## 12. Readiness for an owner acceptance session

**Session held; decision recorded on 2026-10-27 (A-1, §13): partially accepted.** The next decision (A-2) needs the excluded items resolved first. What follows describes the session as prepared. The preview runs on `smartgro_preview` (`../operations/LOCAL_DEVELOPMENT.md` §3), with reference data only and nothing published.

The session can:
* confirm the PASS items in §1.2;
* walk through the back office with an owner Super Admin account created in the preview;
* record a partial acceptance or a deferral.

The session cannot:
* pass AC-5.1 to AC-5.4;
* open the enquiry form (L-1);
* turn on notifications (O-1);
* clear the PHPUnit exception (D-1).

## 13. Owner decision (2026-10-27)

| | |
|---|---|
| Decision | **Partially accepted.** Not fully accepted. |
| Date | 2026-10-27 |
| Recorded from | The owner's answers in the acceptance questionnaire and review notes of 2026-10-27, in round 45 |
| Scope accepted | **All §1.2 items marked PASS when the decision was made:** D4.2, D4.3, D5, D5-M, D7, D8.2, D8.3, D8.5, D9, D10, D11, D12, D13, D14, D15 and D16, subject to the documented evidence in this pack (§6, §7, §11). D8.3 is accepted as built; its reason lists stay empty until the owner supplies them (O-7). |
| Excluded, still BLOCKED | AC-5.1 to AC-5.4 (inventory and pricing, pending actual business inputs O-2, O-3 and O-4). D8.1 (public enquiry form, until the counsel-approved privacy notice is published, L-1, and the public form is re-verified on the server). D8.4 (notifications, until a delivery channel is verified, O-1). |
| Open security exception | PHPUnit advisory PKSA-qhjm-rpqc-qkn6 / GHSA-9vw2-f4vp-x7gf (D-1), because the current stable Pest release conflicts with the fixed PHPUnit version. Not resolved by this decision. |
| Not accepted (not listed by the owner) | D4.1 and D6 (PARTIAL; floors, capacity, prices and availability not built). The §1.3 PRD rows were not decided separately. D8.6 is outside Phase 5 (Phases 8 and 9). |
| Review details | The owner's notes do not list the individual workflows reviewed. They report no defects and no exclusions beyond those above. |
| Phase 6 | Not started. It must not begin until the remaining Phase 5 requirements and the required owner decision (A-2) are addressed under the project's acceptance process. |
| Outstanding actions | §9 register: L-1 to L-6, O-1 to O-9, D-1, A-2 |

**Owner's review notes (verbatim):**

> Owner decision: Partially accepted on 27 October 2026.
>
> The owner accepts the currently passing Phase 5 criteria selected in the acceptance questionnaire, subject to the documented evidence and acceptance pack.
>
> Explicit exclusions and outstanding blockers:
> - AC-5.1–AC-5.4: Inventory and pricing remain blocked pending actual business inputs.
> - D8.1: The public enquiry form remains blocked until the counsel-approved privacy notice is published and server-side form access is re-verified.
> - D8.4: Notifications remain blocked until a delivery channel is verified.
> - PHPUnit security advisory PKSA-qhjm-rpqc-qkn6 / GHSA-9vw2-f4vp-x7gf: Remains an open temporary security exception because the current stable Pest release conflicts with the fixed PHPUnit version.
>
> No acceptance is implied for blocked or unverified criteria. No unsupported inventory, pricing, legal information, or notification configuration is approved by this decision.
>
> Phase 5 is partially accepted, not fully accepted. Phase 6 must not begin until the remaining Phase 5 requirements and the required owner decision are addressed in accordance with the project acceptance process.

