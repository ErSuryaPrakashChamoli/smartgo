# SmartGro — Business Rules (Draft)

| | |
|---|---|
| Status | **v1.2-rc — Phase 1, ready for owner review.** Incorporates the owner decisions of 2026-10-24 (OD-01…OD-20). Values marked [DEFAULT] are still not approved. |
| Last updated | 2026-10-24 |
| Sign-off required | Project owner (structure); client operations, sales, marketing and finance leads (values) |

Each rule has an ID so tests and code can reference it.
* **[DEFAULT]** marks a *recommended* value. It is **not** an approved business fact. It is used only to design and test the mechanism until the client confirms or replaces it.
* **[UNRESOLVED → D-xx]** marks a rule that cannot be finalised without that decision (see `../decisions/DECISION_REGISTER.md`).

The *mechanisms* (locking, approval, propagation, labelling) are architecture. The *values* (durations, thresholds, windows) are business policy and belong to the client.

---

## 1. Source of truth and ownership

| ID | Rule |
|---|---|
| BR-SOT-1 | Every physical workspace has exactly one `spaces` record. No other table may describe capacity, operational status, availability or price for a space. |
| BR-SOT-2 | Operational data (capacity, dimensions, floor, operational status, allocations, bookings, price plans) can only be changed by roles with the matching **operational** permission: Operations Manager, Centre Manager (own properties) or Finance for pricing. |
| BR-SOT-3 | Marketing roles can change presentation (copy, imagery, SEO, page visibility). They can **hide** an offering or space from the website but can never mark it available, change its capacity or change its price. |
| BR-SOT-4 | When operational status or availability changes, the system emits `SpaceAvailabilityChanged`. Website listings, 3D hotspots, booking widgets and CRM proposals read live state or have their caches invalidated within **60 seconds** [DEFAULT]. |
| BR-SOT-5 | If an existing system is confirmed as the source of truth for any field group (D-06), SmartGro becomes a read mirror for those fields. They are shown read-only ("Managed in <system>"). Direction-of-sync rules replace BR-SOT-2 for those fields. **[UNRESOLVED → D-06]**; options and recommendation in `../architecture/EXISTING_SYSTEMS_ASSESSMENT.md`. |
| BR-SOT-6 | Exactly **one** booking engine accepts bookings for a given space. SmartGro never takes a parallel booking for a space whose bookings are mastered elsewhere. |
| BR-SOT-7 | **Confirmed 2026-10-24 (OD-06):** SmartGro has no existing operational software. The SmartGro platform is the master for properties, inventory, availability, pricing, bookings and CRM. BR-SOT-5 applies only if a system is integrated in future. |

## 1A. Properties and portfolio (OD-03, OD-08, OD-09)

| ID | Rule |
|---|---|
| BR-PROP-1 | Properties, buildings and floors are created and edited only by authorised administrators (`portfolio.manage`). The four Noida properties are entered from owner-supplied facts only. **No floor, room, capacity or inventory is created by assumption or to make a demo look complete.** |
| BR-PROP-2 | Every property has an explicit `operating_model`: **owned and operated**, or **leased and operated**. It is never inferred from the address. |
| BR-PROP-3 | A property can be saved with incomplete details (address, floors, inventory to follow). It can be **published** on the website only when its primary building's address is complete, it has at least one floor, and it has at least one publicly listable space or an offering marked enquiry-only. |
| BR-PROP-4 | **Cities are hidden by default (OD-18).** A CMS administrator with `cities.manage` can add a city and switch its public visibility on or off with a toggle (`is_publicly_visible`). A visible city with a published property shows its properties. A visible city **without** a published property must not imply that SmartGro operates there: its content is decided by Q-OW-12 (proposed: a "coming soon / register interest" page). Toggling is audited. Current state: Noida has properties; Gurugram, Delhi, Faridabad and Ghaziabad are hidden. |
| BR-PROP-5 | **Deferred (OD-14):** all four current properties are owned and operated. When the first **leased** property is added, its lease facts are recorded (restricted access), and agreements, proposals or offers extending beyond the lease end raise a warning for Sales and Operations. |
| BR-PROP-6 | Each floor belongs to exactly one building of the property. Building levels are unique within a building. Floors are only those SmartGro operates. |

## 1B. Product categories (OD-07)

| ID | Rule |
|---|---|
| BR-PRODCFG-1 | Product categories and their rules are managed in the back office (`catalogue.manage`). Changes are audited. Rules that affect money (payment, cancellation, pricing) need the pricing permissions in §3. |
| BR-PRODCFG-2 | A space can be sold only under categories compatible with its space type and inventory mode: Dedicated desks need `per_seat` or `hybrid`; Hot desks need `pooled`, `per_seat` or `hybrid`; cabins, rooms and managed offices need `exclusive`. |
| BR-PRODCFG-3 | Categories with no physical inventory (virtual office / business address) never consume space availability. A per-property cap is optional. |
| BR-PRODCFG-4 | The website shows only active, publicly listed categories that have at least one active offering at a published property. No page is built for a category with nothing to sell. |
| BR-PRODCFG-5 | `instant_booking` can be enabled for a category only after the online-booking and payment decisions (D-07, D-08) are confirmed. Until then the strongest workflow is request then staff approval. |

## 2. Availability

| ID | Rule |
|---|---|
| BR-AV-1 | Customer-facing availability states are: **Available now**, **Available from {date}**, **Limited availability** (shared-capacity zones below a threshold), **Join waitlist**, and **Hidden**. |
| BR-AV-2 | **`exclusive` spaces sold on term agreements** (private cabins, office suites, managed offices) are *available now* when `active`, publicly listable, and with no `reserved`/`active`/`notice_period` allocation covering today. They are *available from {date}* when the latest covering allocation has an `ends_on` within the publish horizon (90 days [DEFAULT]). |
| BR-AV-3 | **`pooled` zones** (hot desks / flexible seating): free capacity on a date = `capacity_seats − Σ allocated seats − Σ blocking booking quantity`. Staff see the exact count; customers see the state from BR-AV-1. |
| BR-AV-3a | **`per_seat` zones** (dedicated desks): each active seat is free or committed for a date (allocation or blocking booking on that seat). The zone shows "available" while at least one active seat is free. Staff see seat-level status. |
| BR-AV-3b | **Mixed inventory within a property (OD-10 C):** a property may contain `exclusive`, `per_seat`, `pooled` and `hybrid` spaces at the same time. Each space applies its own rule; property-level summaries combine them. |
| BR-AV-3c | A space sellable under several categories (e.g. meeting room and training room) has **one** availability. Any booking or allocation under any category blocks the space for all categories. |
| BR-AV-3d | **Hybrid zones (OD-15):** in a `hybrid` zone, at every instant, *seats individually committed* (seat allocations + seat bookings) + *pooled usage* (pooled allocations + pooled booking quantities) ≤ *active seats*. Individual assignment of a seat is refused if it would leave the pool over-committed. Pooled bookings never take a seat that is individually committed. Customers see the zone as available while either a free seat (for dedicated use) or free pool capacity (for flexible use) exists. |
| BR-AV-4 | `maintenance`, `out_of_service` and `decommissioned` spaces are never bookable. They are hidden from customers unless Marketing chooses to show "Coming back soon" (maintenance only). |
| BR-AV-5 | A `property_closures` period blocks all bookings and tours at that location for the period. |
| BR-AV-6 | Properties with `operational_status = planned` or `pre_opening` can be shown as "Opening {month year}" with a waitlist/enquiry CTA. They never show bookable inventory. |

## 2A. Inventory administration (OD-08, OD-10)

| ID | Rule |
|---|---|
| BR-INV-1 | Authorised administrators (`inventory.manage`, scoped to their properties) can add or edit spaces and seats, update status and capacity, and add inventory later. All changes are audited with a reason. |
| BR-INV-2 | Space `code`s are permanent and unique. A space is never deleted once it has had an allocation or booking. It is decommissioned instead. |
| BR-INV-3 | A change of `inventory_mode` is allowed only when the space has no current or future allocations or blocking bookings. |
| BR-INV-4 | **Reducing supply** (lower capacity, deactivating a seat, status → maintenance/out_of_service/decommissioned, adding a closure) takes the inventory locks first (ADR-0004). It then checks future committed usage. If anything no longer fits, the change is **refused** with a list of the affected bookings and allocations, which must be moved or cancelled first. A permitted override (`bookings.override`) can force it, but only together with explicit resolution of each conflict, never silently. |
| BR-INV-5 | Bulk inventory loads use the approved import process (`INVENTORY_IMPORT_SPEC.md`), including four-eyes approval and an all-or-nothing commit. |

## 3. Pricing and offers

| ID | Rule |
|---|---|
| BR-PR-1 | Prices exist only in `price_plans`. Marketing copy may refer to price only through a price token (e.g. "from {price}") that renders the live price, never a typed number. |
| BR-PR-2 | Creating or changing a price plan requires the `pricing.manage` permission. Activation requires `pricing.approve` held by a **different** user from the author (four-eyes) [DEFAULT]. |
| BR-PR-3 | Website price display per offering is `hidden`, `from_price` (lowest active plan for that unit at that property) or `exact`. Changing this setting requires `pricing.manage`. **[UNRESOLVED → D-04]** whether public pricing is shown at all. |
| BR-PR-4 | Tax display: prices are stored tax-exclusive and displayed with an explicit "+ GST" label [DEFAULT]. **[UNRESOLVED → D-04]** |
| BR-PR-5 | Offers need approval before activation, carry mandatory terms text, and cannot reduce a price below a floor set by Finance (if D-04 defines one). |
| BR-PR-6 | A booking stores an immutable `price_snapshot` at confirmation. Later price changes never alter confirmed bookings. |

## 4. Bookings and double-booking prevention

| ID | Rule |
|---|---|
| BR-BK-1 | **`exclusive` spaces** (meeting rooms, conference/training rooms, cabins and suites when booked by time): no two blocking bookings or allocations may overlap. Overlap uses half-open intervals, `[starts_at, ends_at + turnaround)`, so back-to-back bookings are allowed only when the turnaround time is respected. |
| BR-BK-2 | **`pooled` spaces** (hot desks / day passes): for every instant, the sum of blocking booking quantities plus allocated seats must not exceed `capacity_seats`. |
| BR-BK-2a | **`per_seat` spaces** (dedicated desks): a seat can hold at most one blocking booking or allocation for any instant. The lock is taken on the **zone** row, not the seat row (ADR-0004). |
| BR-BK-2b | An operation involving several spaces (a managed-office agreement over several suites, or combined rooms) locks all of them in ascending `id` order before reading. It succeeds only if **every** space passes its own rule. Otherwise nothing is written. |
| BR-BK-2c | **`hybrid` zones:** seat-level and pool-level requests both lock the **zone** row first, then validate BR-AV-3d for the whole requested interval, plus the chosen seat for seat-level requests. Nothing is read before the lock. |
| BR-BK-3 | Blocking statuses are `confirmed`, `pending_payment`, and `held` while `hold_expires_at > now()`. |
| BR-BK-4 | Holds last **10 minutes** [DEFAULT] while the customer completes details or payment. A scheduled job releases expired holds every minute, but correctness never depends on that job, because BR-BK-3 ignores expired holds anyway. |
| BR-BK-5 | **Concurrency control:** every operation that creates, extends, reschedules or confirms a booking runs in a single DB transaction with deadlock retry (`DB::transaction(fn, attempts: 3)`). Its **first statements** are `SELECT … FOR UPDATE` on the inventory lock rows defined in ADR-0004: the space row for `exclusive`; the **zone's** space row for `per_seat` and `pooled`; all involved spaces in ascending `id` order for multi-space operations. **Nothing is read before the lock** (owner-confirmed invariant, 2026-10-24). It then re-validates BR-BK-1/2/3, BR-AV-4/5 and booking-window rules before writing. Validation outside the lock is advisory only. (ADR-0004; the "read before lock" failure is reproduced by a negative-control test on MySQL 8.4.) |
| BR-BK-6 | Booking requests carry an idempotency key. A retried submission with the same key returns the original booking instead of creating a second one. |
| BR-BK-7 | Booking windows [DEFAULT]: earliest start is 30 minutes from now; latest start is 60 days ahead; minimum and maximum duration come from the space; bookings must fall within location operating hours. |
| BR-BK-8 | Cancellation and refund policy: **[UNRESOLVED → D-07, D-08, D-15]** |
| BR-BK-9 | Staff overrides (e.g. booking outside hours) require `bookings.override` and are always audit-logged with a reason. Overrides can never bypass BR-BK-1/2. |
| BR-BK-10 | Online payment is in scope only if D-07/D-08 approve it. Until then, bookable products produce a **booking request** that staff confirm, and confirmation still runs BR-BK-5. |
| BR-BK-11 | **Cancellation and release:** cancelling a booking or ending an allocation runs under the same locks as creation. Inventory is released immediately, because availability is derived. No job is needed to "free" a seat or room. Expired holds stop blocking automatically (BR-BK-3). |
| BR-BK-12 | Booking rules per product category (durations, notice, approval, payment, cancellation) come from the category configuration (`DATABASE_ERD.md` §4.1). Their **values** are unconfirmed until the owner's pricing and policy decisions arrive (brief §9 not received). |

## 5. Tours (site visits)

| ID | Rule |
|---|---|
| BR-TR-1 | Tour slots are generated from `tour_availability_rules` minus closures, minus slots at `max_concurrent_tours`. |
| BR-TR-2 | Slot claiming uses the same lock-and-recheck pattern as BR-BK-5, locking the location's tour-capacity row. |
| BR-TR-3 | Every tour request creates or updates a lead (BR-CRM-1) and notifies the assigned owner. The visitor receives confirmation on their preferred channel (subject to consent, BR-PRV-2). |
| BR-TR-4 | Default tour duration is 30 minutes, with tours available during operating hours [DEFAULT]. **[UNRESOLVED → D-28]** |

## 6. Leads and CRM

| ID | Rule |
|---|---|
| BR-CRM-1 | Every enquiry, tour request, booking request, call-back request and WhatsApp click-to-chat (where trackable) creates or updates a lead with source and attribution data. |
| BR-CRM-1a | Forms may ask "Which best describes you?" with the eight customer segments (OD-04) as optional answers. The segment is used for routing and reporting. Segments carry **no** priority until the owner sets one. |
| BR-CRM-2 | Contact de-duplication: match on normalised email **or** E.164 phone. A match attaches the new lead to the existing contact; it never overwrites contact fields without staff confirmation. |
| BR-CRM-3 | New leads are assigned by the first matching active `assignment_rule` (by priority). If none match, the lead goes to the Sales Manager queue. |
| BR-CRM-4 | First-response SLA target: **15 minutes during business hours** [DEFAULT]. A breach notifies the Sales Manager. **[UNRESOLVED → D-09]** |
| BR-CRM-5 | A lead can only be marked `lost` or `disqualified` with a reason from a managed list. |
| BR-CRM-6 | Sales Executives see only leads they own or leads for their scoped properties. Sales Managers see all. |
| BR-CRM-7 | A won deal with allocated spaces creates `space_allocations` through the inventory service, so the availability rules apply. Sales cannot edit inventory directly. |

## 7. Content and publishing

| ID | Rule |
|---|---|
| BR-CMS-1 | Publishable content (pages, posts, property and offering profiles, scenes, FAQs, testimonials, case studies) follows **Draft → Submitted → Approved → Published**. Content can be **Rejected** with a note at the review step. |
| BR-CMS-2 | Authors cannot approve their own submission [DEFAULT]. Single-approver workflow [DEFAULT]. **[UNRESOLVED → D-17]** |
| BR-CMS-3 | Every published version is retained as a revision. Rolling back republishes a previous revision, which is itself an audited action. |
| BR-CMS-4 | Preview uses a signed, expiring URL that renders the draft exactly as it would publish, and is `noindex`. |
| BR-CMS-5 | Scheduled publishing and unpublishing are supported. The scheduler runs the same publish action as a manual publish. |
| BR-CMS-6 | Slug changes on published content automatically create a 301 redirect from the old path. |
| BR-CMS-7 | Testimonials and case studies cannot be published without `consent_on_file = true`. |
| BR-CMS-8 | **Nothing is taken from the old smartgro.space website (OD-19):** no copy, images, files, plugins or data. All content is newly created or supplied by the owner. Whether old URLs redirect (which reuses no content) is Q-OW-11. |

## 8. Media and imagery integrity

| ID | Rule |
|---|---|
| BR-MED-1 | Every asset records an origin: client photography, commissioned, licensed stock, AI-generated concept or render. It also records licence holder, licence reference and expiry where applicable. |
| BR-MED-2 | Assets with origin `ai_generated_concept` or `render` **cannot** be placed in a property's photo gallery. Wherever they appear, the site shows a visible "Concept visual" label. |
| BR-MED-3 | Licensed assets past `licence_expires_at` are automatically unpublished from the site, and the Brand Manager is notified 30 days before expiry. |
| BR-MED-4 | Images cannot be published without alt text unless they are flagged decorative. |
| BR-MED-5 | Accepted upload types are validated by content (MIME sniffing), not only by extension. SVG uploads are restricted to the Brand Admin and sanitised. |

## 9. 3D digital twin

| ID | Rule |
|---|---|
| BR-3D-1 | A scene may only be labelled "Virtual tour of {location}" or "3D scan" if every layer is `measured_scan` of that location. |
| BR-3D-2 | If any layer is `architectural_model`, the scene shows "Architectural model — final fit-out may differ". If any layer is `conceptual_model`, it shows "Conceptual illustration — not a scan of this location". |
| BR-3D-3 | A hotspot linked to a space shows that space's live availability and a CTA. If the space is hidden or decommissioned, the hotspot is suppressed automatically. |
| BR-3D-4 | GLB uploads must pass automated validation before they can be used in a scene: size budget, triangle budget, compression, required anchor naming, and no external URIs. |
| BR-3D-5 | A location with no 3D model shows the fallback: photo gallery plus the interactive 2D floor plan (if one is uploaded) plus a "Book a tour" CTA. A conceptual model is never shown as a stand-in for a real space without the BR-3D-2 label. |
| BR-3D-6 | 3D assets load only on pages that contain a viewer, and only after the user requests it or the viewer scrolls into view. |

## 10. Privacy and communications

| ID | Rule |
|---|---|
| BR-PRV-1 | Forms collect only the fields needed for the purpose stated next to the form. |
| BR-PRV-2 | Service messages (e.g. tour confirmations) may be sent to the channel the user provided. Marketing messages need a recorded, purpose-specific consent and must honour withdrawal immediately. |
| BR-PRV-3 | WhatsApp business-initiated messages use provider-approved templates only. SMS in India must use DLT-registered headers and templates. **[UNRESOLVED → D-10]** |
| BR-PRV-4 | Lead and contact data retention period and deletion process: **[UNRESOLVED → D-15, D-29]** Applicable obligations (e.g. India's Digital Personal Data Protection Act, 2023 and its Rules) are to be confirmed by SmartGro's legal counsel. |
