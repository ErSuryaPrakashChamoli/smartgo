# SmartGro — Existing Systems Assessment (D-06)

| | |
|---|---|
| Status | **RESOLVED 2026-10-24 (OD-06): the owner confirmed SmartGro has no existing operational software.** Scenario A applies: the SmartGro platform is the master for properties, inventory, availability, pricing, bookings and CRM. This assessment is retained as the playbook for any **future** integration (e.g. accounting, access control). |
| Last updated | 2026-10-24 |
| Decision owner | Client (SmartGro), with a recommendation from the project team |
| Needed by | **Phase 5 design freeze** (inventory, pricing, availability). It also shapes Phase 8 (bookings) and Phase 9 (CRM). |
| Related | `INTEGRATION_CONTRACTS.md` §3.3 · `DATABASE_ERD.md` M-3 · `../requirements/BUSINESS_RULES.md` BR-SOT-5 · risk R-1 |

---

## 1. Why this matters

The platform's core promise is **one source of truth** for each physical workspace, its availability, its price and its bookings (brief §5). If another system already holds any of these and must stay in use, then for each kind of data we must decide which system is the master and how the other stays in sync. Getting this wrong causes:
* the inconsistencies seen across competitors (`../research/MARKET_BENCHMARK.md` §2.4)
* in the worst case, double bookings, because two systems each think they own a room

---

## 2. Information required from the client

Please answer for **every** system in use today, including spreadsheets that act as the master record.

| # | Question | Why we ask |
|---|---|---|
| Q1 | Which software is used for: member/contract management, meeting-room or desk booking, billing and invoicing, accounting/GST, CRM or lead tracking, door access, visitor management, the website, email/WhatsApp messaging? (Product name and edition, or "spreadsheet", or "none".) | identify every potential master |
| Q2 | For each: which data does it hold? (locations, floors, spaces, capacities, members, agreements, prices, bookings, invoices, leads) | map overlaps with SmartGro's entities |
| Q3 | For each data type, which system does the team treat as **the truth** today when two disagree? | the current de-facto master |
| Q4 | Does each system have an API? Read only, or read/write? Webhooks? Who holds the admin account and API credentials? | feasibility of sync; credential ownership |
| Q5 | Can each system export all its data (CSV/XLSX/API)? | migration and reconciliation |
| Q6 | Contract term, renewal date and cost of each system | keep, integrate or replace timing |
| Q7 | Which systems must **remain** in use after launch, and which could be replaced by the SmartGro platform? | scope of Phases 5, 8 and 9 |
| Q8 | Roughly how much data: locations, spaces, active members/agreements, bookings per month, leads per month? | sizing of sync and import |
| Q9 | Where is each system's data hosted (country or region)? Any contractual data-residency terms? | privacy and legal review (D-15) |
| Q10 | Biggest pain points with the current tools | where the platform adds value |

---

## 3. Scenarios and how the design adapts

| Scenario | Description | Effect on SmartGro design |
|---|---|---|
| **A. No operational system** (spreadsheets only) | Inventory and occupancy live in spreadsheets. Bookings by phone or email. | SmartGro is master for everything in §4. The first load uses the SG-INV-1.1 import (`../requirements/INVENTORY_IMPORT_SPEC.md`). **This is the current ERD default.** |
| **B. Coworking-management platform in use** (members, agreements, billing, room bookings, possibly door access) | A specialised platform already runs operations. | Choose **B1** (platform stays master for agreements and bookings; SmartGro mirrors inventory and availability) or **B2** (SmartGro becomes master for inventory, availability, pricing and bookings; the platform consumes them for billing and access). In both cases **one system only** accepts bookings for a given room. |
| **C. Separate CRM in use** | Leads and pipeline in an external CRM | Choose between keeping that CRM as master (SmartGro pushes leads with attribution; Phase 9 shrinks to an integration) and migrating to the built-in CRM (Phase 9 as planned plus data migration). |
| **D. Accounting/ERP in use** | Invoices, GST and ledgers | It stays master for invoices and payments accounting. SmartGro sends booking and payment records (D-08). SmartGro does not replicate accounting. |

These can combine (e.g. B + C + D).

---

## 4. Recommended single source of truth (pending confirmation)

| Data | Recommended master | Holds unless… | If the exception applies |
|---|---|---|---|
| Physical inventory: locations, buildings, floors, spaces, capacity, dimensions, operational status | **SmartGro platform** | an existing platform drives door access or billing using its own space IDs and cannot accept writes | that platform is master; SmartGro mirrors with `external_ref`, and those fields are read-only in the SmartGro UI |
| Long-term occupancy (agreements / allocations) | **The system that manages agreements and billing** (SmartGro if none exists) | — | SmartGro mirrors allocations to compute availability |
| Availability | **Derived** in SmartGro from the authoritative occupancy and bookings (never typed in) | the booking authority is external and offers live availability by API | SmartGro queries or mirrors it, with a freshness target ≤ 60 s for bookable rooms |
| Prices | **SmartGro price plans** (with approval, BR-PR) | a billing system's price list drives invoices | billing system is master; SmartGro mirrors for display only |
| Bookings (meeting rooms, day passes) | **Exactly one booking engine.** SmartGro is recommended if online booking is in scope (D-07). | the existing platform's booking is embedded in access control or member apps that must stay | the existing engine is master; the SmartGro website books through its API; SmartGro never takes a parallel booking |
| Tours (site visits) | **SmartGro** (new capability; no competitor offers confirmed slots) | — | — |
| Leads, contacts, pipeline | **SmartGro CRM** | the sales team must stay on an existing CRM | SmartGro pushes leads in real time with attribution; the CRM is master |
| Invoices, GST, payments ledger | **Accounting/billing system** | — | — |
| Marketing content, SEO, media | **SmartGro CMS** | — | — |

**Principle:** for every field there is exactly one master. Mirrored fields are visibly read-only in the back office ("Managed in <system>"), so staff cannot create conflicts.

---

## 5. Sync design (applies to any mirrored data)

| Concern | Design |
|---|---|
| Identity | every mirrored record stores `external_system` + `external_ref`; unique per system |
| Inbound changes | provider webhooks where available (signature-verified, receipt stored first, IC-4), plus periodic polling as a fallback |
| Reconciliation | a nightly full comparison producing a **drift report** for operations; no silent auto-fixes of master data |
| Conflicts | the master always wins; local edits of mirrored fields are disabled |
| Freshness | a sync-lag metric per system; alert if lag exceeds 15 min for availability-relevant data [DEFAULT] |
| Outbound | queued, idempotent jobs (IC-3); failures visible in the back office |
| Cut-over | one-time migration with checksum reports, dry run on staging, then sign-off before switching masters |

---

## 6. Integrate or replace: assessment criteria

Each system will be scored once Q1–Q10 are answered:
1. API completeness: read/write for spaces, availability, bookings, agreements; webhooks
2. reliability and rate limits
3. data residency and privacy terms
4. cost and contract timing
5. team adoption and training cost
6. migration effort and risk
7. lock-in

The output is a short recommendation per system: **keep as master / keep and mirror / replace by SmartGro / retire**. It will be recorded as an ADR.

---

## 7. Decision status

| Item | Status |
|---|---|
| D-06 answer | **Resolved (OD-06, 2026-10-24):** no existing operational software |
| Applied design | Scenario A: SmartGro is master per §4. Accounting/invoicing has no current system; how GST invoices are produced remains D-08 (Phase 8). |
| Future integrations | the ERD keeps `external_system`/`external_ref`; §5 sync design applies if a system is added later |
| Gate | the Phase 5 gate on D-06 is **cleared** |
