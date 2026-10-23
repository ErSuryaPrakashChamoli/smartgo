# SmartGro — Business Rules (Draft)

| | |
|---|---|
| Status | **DRAFT — Phase 1, ready for review** |
| Last updated | 2026-10-24 |
| Owner sign-off required | Yes (Operations, Sales and Marketing leads) |

Each rule has an ID so tests and code can reference it. Rules marked **[DEFAULT]** are recommendations used until the owner confirms or replaces them. Rules marked **[UNRESOLVED → D-xx]** cannot be finalised without the decision listed in `docs/phases/PHASE_0_DISCOVERY_REPORT.md` §5.

---

## 1. Source of truth and ownership

| ID | Rule |
|---|---|
| BR-SOT-1 | Every physical workspace has exactly one `spaces` record. No other table may describe capacity, operational status, availability or price for a space. |
| BR-SOT-2 | Operational data (capacity, dimensions, floor, operational status, allocations, bookings, price plans) can only be changed by roles with the matching **operational** permission: Operations Manager, Centre Manager (own locations) or Finance for pricing. |
| BR-SOT-3 | Marketing roles can change presentation (copy, imagery, SEO, page visibility). They can **hide** an offering or space from the website but can never mark it available, change its capacity or change its price. |
| BR-SOT-4 | When operational status or availability changes, the system emits `SpaceAvailabilityChanged`. Website listings, 3D hotspots, booking widgets and CRM proposals read live state or have their caches invalidated within **60 seconds** [DEFAULT]. |
| BR-SOT-5 | If an external operational system is adopted as the source of truth (D-06), SmartGro becomes a read mirror for those fields. Direction-of-sync rules replace BR-SOT-2 for the mirrored fields. **[UNRESOLVED → D-06]** |

## 2. Availability

| ID | Rule |
|---|---|
| BR-AV-1 | Customer-facing availability states are: **Available now**, **Available from {date}**, **Limited availability** (shared-capacity zones below a threshold), **Join waitlist**, and **Hidden**. |
| BR-AV-2 | A `contract` space (private office, managed suite) is *available now* when it is `active`, publicly listable, and has no `reserved`/`active`/`notice_period` allocation covering today. It is *available from {date}* when the latest covering allocation has an `ends_on` within the publish horizon (90 days [DEFAULT]). |
| BR-AV-3 | A `shared_capacity` zone (hot desk, dedicated desk zone) shows remaining seats on a date as `capacity_seats − Σ allocated seats − Σ confirmed or unexpired-held booking quantity`. The exact count is shown only to staff; customers see the state from BR-AV-1. |
| BR-AV-4 | `maintenance`, `out_of_service` and `decommissioned` spaces are never bookable. They are hidden from customers unless Marketing chooses to show "Coming back soon" (maintenance only). |
| BR-AV-5 | A `location_closures` period blocks all bookings and tours at that location for the period. |
| BR-AV-6 | Locations with `operational_status = planned` or `pre_opening` can be shown as "Opening {month year}" with a waitlist/enquiry CTA. They never show bookable inventory. |

## 3. Pricing and offers

| ID | Rule |
|---|---|
| BR-PR-1 | Prices exist only in `price_plans`. Marketing copy may refer to price only through a price token (e.g. "from {price}") that renders the live price, never a typed number. |
| BR-PR-2 | Creating or changing a price plan requires the `pricing.manage` permission. Activation requires `pricing.approve` held by a **different** user from the author (four-eyes) [DEFAULT]. |
| BR-PR-3 | Website price display per offering is `hidden`, `from_price` (lowest active plan for that unit at that location) or `exact`. Changing this setting requires `pricing.manage`. **[UNRESOLVED → D-04]** whether public pricing is shown at all. |
| BR-PR-4 | Tax display: prices are stored tax-exclusive and displayed with an explicit "+ GST" label [DEFAULT]. **[UNRESOLVED → D-04]** |
| BR-PR-5 | Offers need approval before activation, carry mandatory terms text, and cannot reduce a price below a floor set by Finance (if D-04 defines one). |
| BR-PR-6 | A booking stores an immutable `price_snapshot` at confirmation. Later price changes never alter confirmed bookings. |

## 4. Bookings and double-booking prevention

| ID | Rule |
|---|---|
| BR-BK-1 | **Exclusive spaces** (meeting rooms, board rooms, event spaces): no two blocking bookings may overlap. Overlap uses half-open intervals, `[starts_at, ends_at + turnaround)`, so back-to-back bookings are allowed only when the turnaround time is respected. |
| BR-BK-2 | **Shared-capacity spaces** (day passes): for every instant, the sum of blocking booking quantities plus active allocations must not exceed `capacity_seats`. |
| BR-BK-3 | Blocking statuses are `confirmed`, `pending_payment`, and `held` while `hold_expires_at > now()`. |
| BR-BK-4 | Holds last **10 minutes** [DEFAULT] while the customer completes details or payment. A scheduled job releases expired holds every minute, but correctness never depends on that job, because BR-BK-3 ignores expired holds anyway. |
| BR-BK-5 | **Concurrency control:** every operation that creates, extends, reschedules or confirms a booking runs in a single DB transaction with deadlock retry (`DB::transaction(fn, attempts: 3)`). It acquires `SELECT … FOR UPDATE` on the parent `spaces` row first, then re-validates BR-BK-1/2/3, BR-AV-4/5 and booking-window rules before writing. Validation outside the lock is advisory only. (ADR-0004) |
| BR-BK-6 | Booking requests carry an idempotency key. A retried submission with the same key returns the original booking instead of creating a second one. |
| BR-BK-7 | Booking windows [DEFAULT]: earliest start is 30 minutes from now; latest start is 60 days ahead; minimum and maximum duration come from the space; bookings must fall within location operating hours. |
| BR-BK-8 | Cancellation and refund policy: **[UNRESOLVED → D-07, D-08, D-15]** |
| BR-BK-9 | Staff overrides (e.g. booking outside hours) require `bookings.override` and are always audit-logged with a reason. Overrides can never bypass BR-BK-1/2. |
| BR-BK-10 | Online payment is in scope only if D-07/D-08 approve it. Until then, bookable products produce a **booking request** that staff confirm, and confirmation still runs BR-BK-5. |

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
| BR-CRM-2 | Contact de-duplication: match on normalised email **or** E.164 phone. A match attaches the new lead to the existing contact; it never overwrites contact fields without staff confirmation. |
| BR-CRM-3 | New leads are assigned by the first matching active `assignment_rule` (by priority). If none match, the lead goes to the Sales Manager queue. |
| BR-CRM-4 | First-response SLA target: **15 minutes during business hours** [DEFAULT]. A breach notifies the Sales Manager. **[UNRESOLVED → D-09]** |
| BR-CRM-5 | A lead can only be marked `lost` or `disqualified` with a reason from a managed list. |
| BR-CRM-6 | Sales Executives see only leads they own or leads for their scoped locations. Sales Managers see all. |
| BR-CRM-7 | A won deal with allocated spaces creates `space_allocations` through the inventory service, so the availability rules apply. Sales cannot edit inventory directly. |

## 7. Content and publishing

| ID | Rule |
|---|---|
| BR-CMS-1 | Publishable content (pages, posts, location and offering profiles, scenes, FAQs, testimonials, case studies) follows **Draft → Submitted → Approved → Published**. Content can be **Rejected** with a note at the review step. |
| BR-CMS-2 | Authors cannot approve their own submission [DEFAULT]. Single-approver workflow [DEFAULT]. **[UNRESOLVED → D-17]** |
| BR-CMS-3 | Every published version is retained as a revision. Rolling back republishes a previous revision, which is itself an audited action. |
| BR-CMS-4 | Preview uses a signed, expiring URL that renders the draft exactly as it would publish, and is `noindex`. |
| BR-CMS-5 | Scheduled publishing and unpublishing are supported. The scheduler runs the same publish action as a manual publish. |
| BR-CMS-6 | Slug changes on published content automatically create a 301 redirect from the old path. |
| BR-CMS-7 | Testimonials and case studies cannot be published without `consent_on_file = true`. |

## 8. Media and imagery integrity

| ID | Rule |
|---|---|
| BR-MED-1 | Every asset records an origin: client photography, commissioned, licensed stock, AI-generated concept or render. It also records licence holder, licence reference and expiry where applicable. |
| BR-MED-2 | Assets with origin `ai_generated_concept` or `render` **cannot** be placed in a location's photo gallery. Wherever they appear, the site shows a visible "Concept visual" label. |
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
