# SmartGro — Integration Contracts

| | |
|---|---|
| Status | **v1.0-rc — Phase 1, ready for owner review.** No provider has been selected or connected. Public API and form contracts: [`API_CONTRACTS.md`](API_CONTRACTS.md). |
| Last updated | 2026-10-24 |
| Decisions | D-06 existing systems, D-08 payments, D-10 messaging, D-14 hosting, D-18 analytics |

This document defines **what SmartGro needs from each integration**, independent of vendor, so a provider can be chosen without changing the domain model. Provider names are examples to help the owner decide, not selections.

---

## 1. Universal rules for every integration

| ID | Rule |
|---|---|
| IC-1 | **Secrets** (API keys, tokens, webhook secrets) live only in environment variables or the host's secret manager. They are never stored in the database, CMS, Git, logs, frontend bundles or documentation. The CMS shows only connection *status* and non-secret identifiers. |
| IC-2 | **Non-secret tracking IDs** (GA4 measurement ID, GTM container ID, Meta Pixel ID) may be managed in the CMS, are validated by format, and are changed only by Platform Admin or Marketing Manager. |
| IC-3 | **Outbound calls** run in queued jobs with timeouts, bounded retries with back-off, and an idempotency key. A failed provider never blocks a customer request; the customer sees a success state and staff see the failed dispatch. |
| IC-4 | **Inbound webhooks** verify the provider's signature, persist the receipt with a unique (`provider`, `external_event_id`) **before** processing, process on the queue, and return 2xx quickly. Duplicates are acknowledged and ignored. |
| IC-5 | Every provider is accessed through **one adapter class per capability** (e.g. `WhatsAppSender`), so swapping vendors touches one class. Interfaces are introduced only where two implementations really exist (no speculative abstraction). |
| IC-6 | Personal data sent to a provider is the minimum needed. Provider data-processing terms are a D-15 legal item. |
| IC-7 | Every integration has a sandbox/test mode used in non-production environments, and automated tests fake it (`Http::fake`, `Mail::fake`, `Queue::fake`). |

---

## 2. Integration inventory

| # | Capability | Direction | Phase | Required at launch? | Example providers (unselected) | Decision |
|---|---|---|---|---|---|---|
| 1 | Transactional email (confirmations, notifications) | out + delivery webhooks | 8 | **Yes** | Amazon SES, Postmark, Mailgun, SMTP relay | D-10 |
| 2 | WhatsApp Business messaging | out + inbound/status webhooks | 10 | Recommended | Meta WhatsApp Cloud API directly, or an Indian BSP | D-10 |
| 3 | SMS (OTP, reminders) | out + DLR webhooks | 10 | Optional | DLT-registered Indian SMS provider | D-10 |
| 4 | Payment gateway (meeting rooms, day passes) | out + payment webhooks | 8 | Only if D-07 = instant booking | Razorpay, PayU, Cashfree, Stripe (India) | D-07, D-08 |
| 5 | Invoicing / accounting (GST invoices) | out | 8+ | Depends on D-08 | Existing accounting system (Tally, Zoho Books, …) | D-06 |
| 6 | Existing coworking / member management platform | **bi-directional or inbound** | 5 | Unknown | e.g. Nexudus, OfficeRnD, Spacebring (if any is in use) | **D-06 (critical)** |
| 7 | Existing CRM | sync or migrate | 9 | Unknown | e.g. Zoho CRM, HubSpot, Salesforce (if any is in use) | **D-06** |
| 8 | Calendar (tour host availability, invitations) | out (ICS) / optional bi-directional | 8 | ICS email attachment at launch; API sync later | Google Workspace, Microsoft 365 | D-28 |
| 9 | Maps (location pages, directions) | client embed / static map | 6 | Yes | Google Maps Platform, Mapbox, OpenStreetMap tiles | D-18 |
| 10 | Analytics and tag management | client + server events | 12 | Yes (with consent) | GA4 + GTM; Meta Pixel / Conversions API; LinkedIn Insight; Google Ads | D-18 |
| 11 | Search Console and Bing Webmaster | verification only | 11 | Yes | — | D-19 |
| 12 | Object storage | out | 4 | Yes for production | AWS S3, Cloudflare R2, DigitalOcean Spaces | D-14 |
| 13 | CDN, WAF, bot protection | edge | 15 | Yes | Cloudflare (requested) + Turnstile | D-14 |
| 14 | 360° tour hosting | client embed | 7 | If tours exist | Matterport, Kuula, or self-hosted panoramas | D-13 |
| 15 | Error monitoring / uptime | out | 14 | Yes | Sentry, Nightwatch, Flare, host-native | D-14 |
| 16 | LLM provider for concierge | out | 13 | No | to be chosen at Phase 13 | D-21 |

---

## 3. Contracts by capability

### 3.1 Messaging (email, WhatsApp, SMS)

**Domain contract.** `SendMessage(channel, template_key, contact_id, variables, idempotency_key)` → `message_dispatches` row.

| Field | Rule |
|---|---|
| `template_key` | must map to an `approved_internal` template. WhatsApp/SMS templates must also be `provider_approved`. |
| `variables` | only from the fixed merge-tag list (e.g. `{first_name}`, `{location_name}`, `{tour_time}`) |
| consent | service messages need channel availability; marketing messages need a matching active consent (BR-PRV-2). `suppressions` always wins. |
| delivery status | webhooks update `sent → delivered → read/failed` |

India-specific constraint to confirm with the chosen SMS provider: commercial SMS requires DLT registration of the sender entity, headers and templates (D-10).

### 3.2 Payments (conditional on D-07/D-08)

1. A customer confirms a booking. `bookings.status = pending_payment` while the hold is still valid (BR-BK-4).
2. The server creates a gateway order with the amount from `price_snapshot` (never from the client).
3. The client completes checkout. The **webhook** (not the browser redirect) is authoritative: on `payment.captured`, run `ConfirmBooking` (which re-runs the BR-BK-5 lock and checks).
4. If the hold expired before capture and the slot is now taken, refund automatically and notify staff. This is a rare race that must be handled explicitly.
5. Amounts are integer minor units. Currency comes from the location.

### 3.3 Existing operational systems (D-06), the highest-risk unknown

**Full assessment, client questionnaire (Q1–Q10), scenarios, recommended source of truth and sync design: [`EXISTING_SYSTEMS_ASSESSMENT.md`](EXISTING_SYSTEMS_ASSESSMENT.md).** The decision stays pending until the client confirms its current systems.

If SmartGro already runs a coworking-management, CRM or accounting system, the client must state, **per field group**, which system is authoritative:

| Field group | Authoritative system (to confirm) | Sync direction | Frequency |
|---|---|---|---|
| Locations, floors, spaces, capacity | ? | ? | ? |
| Allocations / memberships | ? | ? | ? |
| Meeting-room bookings | ? | ? | ? |
| Prices | ? | ? | ? |
| Leads / contacts | ? | ? | ? |
| Invoices | ? | ? | ? |

**Recommended default (not approved; applies only if the client confirms no such system exists):** the SmartGro platform is authoritative for every row above except invoices, which stay in the accounting system (D-08).

### 3.4 Analytics and attribution (D-18)

* Client tags load only after consent where consent is required (consent-mode banner), managed by IDs in CMS settings.
* Server-side: each lead stores UTM parameters, click IDs (`gclid`, `fbclid`), landing URL and referrer, captured in a first-party cookie on landing and attached at form submit.
* Conversion events emitted: `enquiry_submitted`, `tour_requested`, `tour_completed`, `booking_confirmed`, `deal_won`. Offline conversions (tour completed, deal won) can be uploaded to ad platforms in Phase 12.

### 3.5 Maps

Location pages display a map and directions link. Coordinates come from `buildings.latitude/longitude` (operational data). An API key, if needed, is domain-restricted and treated as non-secret client config.

---

## 4. Internal domain events (contract between modules)

| Event | Payload (IDs only) | Emitted when | Typical consumers |
|---|---|---|---|
| `SpaceAvailabilityChanged` | `space_id` | allocation, booking, status or closure change commits | availability snapshot, cache/CDN purge, 3D viewer cache |
| `PricePlanActivated` | `price_plan_id`, `offering_id` | approved plan becomes active | cache purge, offering pages |
| `LeadCreated` | `lead_id` | any capture channel | assignment, auto-reply, analytics |
| `LeadAssigned` | `lead_id`, `user_id` | assignment | owner notification, SLA timer |
| `TourScheduled` / `TourRescheduled` / `TourCancelled` | `tour_request_id` | tour lifecycle | confirmations, reminders, calendar ICS |
| `BookingConfirmed` / `BookingCancelled` | `booking_id` | booking lifecycle | confirmations, availability, analytics |
| `ContentPublished` | `type`, `id` | publish action | cache/CDN purge, sitemap regeneration |
| `ConsentWithdrawn` | `contact_id`, `purpose` | withdrawal | suppression, stop automations |

Events carry IDs, not personal data. Listeners re-read current state from the database.
