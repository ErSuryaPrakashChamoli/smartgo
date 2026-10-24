# SmartGro — API and Form Contracts

| | |
|---|---|
| Status | **v1.1-rc — Phase 1, ready for owner review.** Updated 2026-10-24 for product categories (OD-07), inventory modes (OD-10) and customer segments (OD-04). |
| Last updated | 2026-10-24 |
| Related | `SYSTEM_ARCHITECTURE.md` §4 · `INTEGRATION_CONTRACTS.md` · `../requirements/BUSINESS_RULES.md` · ADR-0010 |

Examples use **synthetic** identifiers (`synthville`, `SYN-…`). They are not real SmartGro data.

---

## 1. Conventions

| Topic | Contract |
|---|---|
| Base path | `/api/v1`. URI versioning; additive changes only within v1. A breaking change becomes v2, with `Deprecation` and `Sunset` headers on v1 for at least 90 days. |
| Format | JSON (UTF-8), produced by Eloquent API Resources. The response body is `{ "data": … }`, plus `meta` when needed. |
| Identifiers | No database IDs are exposed. Locations by `city` + `slug`; spaces by permanent `code` (ERD `spaces.code`); customer records by `reference` (e.g. `SG-TR-7F3K9Q`). |
| Time | ISO 8601 with the location's UTC offset (e.g. `2026-10-24T10:00:00+05:30`). `timezone` is included where slots are listed. Dates are `YYYY-MM-DD`. |
| Money | `{ "amount_minor": 1499900, "currency": "INR", "tax": "exclusive", "display": "₹14,999 + GST" }`. Only returned where the price display policy allows (BR-PR-3). |
| Visibility | Only published properties in **publicly visible cities** (OD-18), and publicly listable, non-decommissioned spaces, are returned. Hidden records answer **404**, never 403, so they cannot be discovered. |
| Personal data | **Never returned** by public endpoints |
| Auth | Public read endpoints: none. No public write endpoints at launch (forms go through Livewire, §3). A future write API would use Sanctum tokens. |
| CORS | same-origin only |
| Rate limits | named limiter `public-api`: 60 requests/min per IP; `slots`: 30/min per IP. Over the limit → **429** with `Retry-After`. |
| Errors | Laravel's standard JSON shapes: `404 {"message": …}`; `422 {"message": …, "errors": {field: [..]}}`; `429 {"message": …}`; `500 {"message": "Server Error"}` (no details in production) |
| Caching | each endpoint states its `Cache-Control`. Availability is never cached at the CDN for longer than its `max-age`. |

---

## 2. Public read endpoints

### 2.1 `GET /api/v1/locations/{city}/{location}/availability?date=YYYY-MM-DD`

Live availability states for the location's listable spaces (BR-AV-1). Used by the centre page's live fragment and the 3D viewer.

| Param | Rule |
|---|---|
| `date` | optional, default today in the location's time zone. From today to +90 days, else 422. |

```json
{
  "data": {
    "location": { "city": "synthville", "slug": "alpha" },
    "date": "2026-10-24",
    "as_of": "2026-10-24T10:15:03+05:30",
    "spaces": [
      { "code": "SYN-ALPHA-03-PC-01", "type": "private_cabin", "inventory_mode": "exclusive", "capacity_seats": 4,
        "state": "available_from", "available_from": "2027-02-01", "cta": "book_tour" },
      { "code": "SYN-ALPHA-04-MR-01", "type": "meeting_room", "inventory_mode": "exclusive", "capacity_seats": 6,
        "state": "available_now", "available_from": null, "cta": "book" },
      { "code": "SYN-ALPHA-03-FS-01", "type": "desk_zone_flexible", "inventory_mode": "pooled", "capacity_seats": 30,
        "state": "limited", "available_from": null, "cta": "enquire" }
    ]
  }
}
```

* `inventory_mode` ∈ `exclusive`, `per_seat`, `pooled`, `hybrid` (ADR-0013). Hybrid zones report the zone state; dedicated and flexible availability are summarised separately for staff. For `per_seat` zones the state summarises the zone ("available" while any active seat is free). Seat-level detail is staff-only.
* `state` ∈ `available_now`, `available_from`, `limited`, `waitlist`. Remaining-seat **counts are never exposed** (BR-AV-3).
* `cta` ∈ `book` (only if online booking is enabled, D-07), `book_tour`, `enquire`, `waitlist`.
* Headers: `Cache-Control: public, max-age=30, stale-while-revalidate=30`.

### 2.2 `GET /api/v1/spaces/{code}/slots?date=YYYY-MM-DD`

Open booking slots for an exclusive-slot space. **Enabled only if D-07 approves online booking.** Otherwise it returns 404.

```json
{
  "data": {
    "space": "SYN-ALPHA-04-MR-01",
    "date": "2026-10-26",
    "timezone": "Asia/Kolkata",
    "slot_minutes": 30,
    "min_booking_minutes": 30,
    "max_booking_minutes": 480,
    "slots": [
      { "starts_at": "2026-10-26T08:00:00+05:30", "ends_at": "2026-10-26T08:30:00+05:30", "state": "open" },
      { "starts_at": "2026-10-26T08:30:00+05:30", "ends_at": "2026-10-26T09:00:00+05:30", "state": "unavailable" }
    ]
  }
}
```

* Slots respect operating hours, closures, turnaround and the booking window (BR-BK-7). A slot shown as open is **advisory**: the booking action re-checks under lock (BR-BK-5).
* Headers: `Cache-Control: private, max-age=10`.

### 2.3 `GET /api/v1/locations/{city}/{location}/tour-slots?from=YYYY-MM-DD&to=YYYY-MM-DD`

Bookable tour slots (BR-TR-1). Range at most 14 days; default the next 7 days.

```json
{
  "data": {
    "location": { "city": "synthville", "slug": "alpha" },
    "timezone": "Asia/Kolkata",
    "slots": [
      { "starts_at": "2026-10-27T11:00:00+05:30", "ends_at": "2026-10-27T11:30:00+05:30" }
    ]
  }
}
```

Headers: `Cache-Control: private, max-age=15`.

### 2.4 `GET /api/v1/locations/{city}/{location}/scene`

The published 3D scene manifest (ADR-0009). Returns 404 if no scene is published; the page then shows the fallback (BR-3D-5).

```json
{
  "data": {
    "version": "2026-10-24T09:00:00Z",
    "capture": {
      "type": "measured_scan",
      "captured_on": "2026-09-30",
      "disclaimer": null
    },
    "poster_url": "https://cdn.example.com/scenes/alpha/poster-3f2a.avif",
    "floors": [
      { "level": 3, "label": "Level 3", "sort": 1,
        "model": { "url": "https://cdn.example.com/scenes/alpha/l3-9c1e.glb", "bytes": 8123456, "sha256": "…" } }
    ],
    "hotspots": [
      { "anchor": "SG_SPACE_SYN-ALPHA-03-PC-01", "space": "SYN-ALPHA-03-PC-01", "label": "Cabin 3.01" }
    ],
    "tour_stops": [
      { "anchor": "SG_CAM_1", "title": "Arrival lounge", "narration": "…" }
    ]
  }
}
```

* `capture.disclaimer` is set automatically for `architectural_model` and `conceptual_model` (BR-3D-2). Clients must display it.
* The manifest carries **no availability**; the viewer calls §2.1 and colours hotspots by `space`.
* Headers: `Cache-Control: public, max-age=300` with an `ETag` (scene version). Model and poster URLs are content-hashed and immutable (`max-age=31536000, immutable`).

---

## 3. Form contracts (Livewire actions; no public write API)

Every form applies all of the following:
* CSRF protection
* the `forms` rate limiter: 5 submissions per 10 minutes per IP, and per phone or email
* a honeypot field and a Cloudflare Turnstile token (D-14)
* an **idempotency key** generated when the form renders, so a repeat submit returns the same result (BR-BK-6)
* attribution captured from a first-party cookie set on landing (UTM, gclid, fbclid, landing URL, referrer)
* the privacy notice shown at the point of collection, with an optional, **unticked** marketing-consent checkbox (BR-PRV-1/2)

### 3.1 Enquiry (general, centre, product, enterprise)

| Field | Rule |
|---|---|
| `name` | required, 2–100 chars |
| `email` | required, RFC email, ≤ 190 |
| `phone` | required, normalised to E.164 (default region IN, assumption A-1) |
| `company` | optional ≤ 120 (required on the enterprise form) |
| `product` | optional; one of the active product-category slugs (`private-cabins`, `dedicated-desks`, `hot-desks`, `meeting-rooms`, `conference-training-rooms`, `virtual-office`, `managed-offices`; admin-managed, OD-07), **pre-filled from page context**. The category's required fields are added to the form (FR-PROD-3). |
| `customer_segment` | optional; one of the active customer-segment slugs (OD-04), asked as "Which best describes you?" (BR-CRM-1a) |
| `locations[]` | optional list of location slugs, pre-filled from context |
| `seats_band` | optional enum: `1-4`, `5-19`, `20-49`, `50-99`, `100-249`, `250+` (enterprise: required) |
| `move_in_by` | optional month (`YYYY-MM`), not in the past |
| `message` | optional ≤ 2,000 chars |
| `marketing_consent` | optional boolean, default false |

**Outcome:**
1. A lead is created or updated (BR-CRM-1/2), and `LeadCreated` fires.
2. The confirmation screen shows the reference `SG-LD-…` and the next step.
3. Validation errors are shown inline next to each field.

### 3.2 Tour request

| Field | Rule |
|---|---|
| `location` | required, a published location |
| `starts_at` | required, one of the open slots from §2.3 |
| `visitors` | required, 1–10 |
| contact fields | as in §3.1 (`name`, `email`, `phone`, `company`) |
| `seats_band`, `product`, `notes` | optional, as in §3.1 |

**Outcome:**
1. The slot is claimed under lock (BR-TR-2).
2. A tour is created with status `confirmed`, `TourScheduled` fires, and the lead is created or updated.
3. A confirmation with an ICS invite and a signed reschedule/cancel link is sent.

**If the slot was taken meanwhile:** the visitor sees "That time was just booked. Here are the next three available times", and no lead is lost.

### 3.3 Booking (only if D-07 enables online booking)

| Step | Contract |
|---|---|
| 1. Select | `space` code, `starts_at`, `ends_at`, `attendees` ≤ capacity → `HoldSlot` creates a `held` booking with `hold_expires_at` = now + 10 min (BR-BK-4) |
| 2. Details | contact fields (§3.1), optional `gstin` (format-validated), `company` |
| 3. Pay (if enabled, D-08) | gateway order for the amount in `price_snapshot`. **The webhook confirms; the browser redirect does not** (`INTEGRATION_CONTRACTS.md` §3.2). |
| 4. Confirm | `ConfirmBooking` re-checks under lock; status `confirmed`; reference `SG-BK-…`; confirmation + ICS |

Errors shown to the customer: `slot_unavailable` (offers alternatives), `hold_expired` (restart, keeping the details), `payment_failed` (retry while the hold is valid).

### 3.4 Register interest (coming-soon city page, OD-22)

| Field | Rule |
|---|---|
| `city` | required; a **visible** city without a published property (otherwise 404) |
| `name`, `email`, `phone` | as in §3.1 |
| `product` | optional product-category slug |
| `customer_segment` | optional segment slug |
| `marketing_consent` | optional boolean, default false |

**Outcome:** a lead with source `city_waitlist` and the city set, plus a confirmation screen. When a property in that city is published, staff can notify these leads, but only those whose consent allows it (BR-PRV-2).

---

## 4. Webhooks

`POST /webhooks/{provider}`, with `provider` ∈ an allow-list (payment gateway, WhatsApp, SMS, email delivery) configured per environment.

| Step | Contract |
|---|---|
| 1 | Verify the provider signature over the raw body. If invalid → **401**, logged, no processing. |
| 2 | Store a `webhook_receipts` row, unique on (`provider`, `external_event_id`). On a duplicate → **200**, ignored. |
| 3 | Dispatch the processing job and return **200** within 2 s. |
| 4 | The job is idempotent and updates domain state through actions (e.g. `ConfirmBooking`). |

CSRF is not applied to webhooks; the signature replaces it.

---

## 5. Internal domain actions (module boundaries)

These signatures are what other modules may call (`SYSTEM_ARCHITECTURE.md` §3). Each runs its own transaction and authorisation.

| Action | Input | Output | Failure |
|---|---|---|---|
| `Availability\ForProperty` | location, date | collection of `{space, state, available_from}` | — |
| `Booking\HoldSlot` | space, interval, quantity, idempotency key, channel | booking (`held`) | `SlotUnavailable`, `OutsideBookingWindow`, `SpaceNotBookable` |
| `Booking\ConfirmBooking` | booking, payment reference (optional) | booking (`confirmed`) | `HoldExpired`, `SlotUnavailable` |
| `Booking\CancelBooking` | booking, actor, reason | booking (`cancelled`) | `CancellationNotAllowed` |
| `Booking\ClaimTourSlot` | location, start, visitors, lead data, idempotency key | tour request (`confirmed`) | `TourSlotUnavailable` |
| `Inventory\SetSpaceStatus` | space, status, reason, actor | space | `InvalidTransition` |
| `Inventory\AllocateSpace` | space or seat, agreement data, actor | allocation | `OverAllocation` |
| `Inventory\ImportInventoryWorkbook` | import record (approved) | commit summary | `ImportStale`, validation issues |
| `Crm\CaptureLead` | contact data, context, attribution, consent | lead (created or updated) | — |
| `Content\Publish` | revision, actor | published model | `NotApproved`, `SelfApprovalNotAllowed` |

Domain events emitted after commit are listed in `INTEGRATION_CONTRACTS.md` §4.
