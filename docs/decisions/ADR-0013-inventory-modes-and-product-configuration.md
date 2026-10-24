# ADR-0013 — Inventory modes per space and configurable product categories

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24
* **Drivers:** owner decisions OD-07 (seven product categories, admin-manageable), OD-10 (individual, pooled and hybrid inventory; product-specific rules) and OD-15 (hybrid zones required)

## Context
SmartGro sells seven product categories, and each may have different pricing, availability, booking workflow, capacity, approval, payment, cancellation, duration and required customer information. The owner requires that the website is not hard-coded around one or two categories, and that availability is not forced into a single model. The v1.0-rc ERD had a fixed `space_type` enum and a single `booking_mode` per space.

## Decision
1. **Separate the physical from the commercial.**
   * A space's `inventory_mode` (`exclusive`, `per_seat`, `pooled`, `hybrid`) states how its availability is tracked.
   * A product category's configuration states how it is sold: fulfilment pattern, durations, workflow, approval, payment, cancellation, required fields and capacity limits.
2. **Product categories are admin-managed records** (`products`) with typed configuration (ERD §4.1), seeded with the owner's seven categories. Property-specific overrides live on `offerings`, as does property-specific pricing.
3. **A space can be sellable as several categories** (`offering_space`). Availability is held once, on the space, so a booking under one category blocks every other category on that space (single source of truth).
4. **Space types are admin-managed reference data** with allowed inventory modes, so new physical types need no code change.
5. **Hybrid** is supported at two levels:
   * mixing modes across spaces in the same property (OD-10)
   * a **hybrid zone** mode `hybrid`, adopted on 2026-10-24 (OD-15): individually assigned seats plus a shared pool, governed by the BR-AV-3d invariant
6. Concurrency follows ADR-0004's lock targets per mode. The invariant "lock before reading the inventory state" is unchanged.

## Alternatives considered
* **One generic "bookable unit" table for everything:** simpler schema, but it loses the physical truth (seats inside zones, rooms usable for several products) and makes capacity rules opaque.
* **Free-form JSON rules per category:** flexible, but not safely editable by non-technical staff, and hard to validate and test. Typed columns with checkbox-style enum lists are used instead.
* **Hard-coded categories in code:** rejected by the owner's requirement for admin management.

## Consequences
* (+) New categories, space types and per-property variations need configuration, not code.
* (+) One availability engine, with three well-tested modes.
* (−) More reference data and admin screens in Phases 5 and 8. Configuration changes must be permission-controlled and audited.
* (−) Rule *values* (payment, cancellation, windows) stay unconfirmed until the owner's pricing and policy decisions arrive (brief §9 not received).
