# ADR-0014 — Property → building → floor hierarchy with an explicit operating model

* **Status:** PROPOSED
* **Date:** 2026-10-24
* **Drivers:** owner decisions OD-08 (four Noida properties, 10 floors in total, allocation unknown; administrators add and edit properties, buildings and floors) and OD-09 (owned-and-operated and leased-and-operated)

## Context
The v1.0-rc ERD modelled `buildings` hosting `locations`, with floors attached to the location. The owner describes **properties** (identified by addresses such as "A-70, Sector 2, Noida") to which administrators add buildings "where necessary" and floors. It is not yet known whether neighbouring addresses form one property (Q-OW-2), or how the 10 floors are distributed (Q-OW-1). The operating model must be explicit, not inferred from an address.

## Decision
1. **Hierarchy:** property (`locations`) 1—n `buildings` 1—n `floors` 1—n `spaces`.
   * Every property has at least one building and exactly one primary building.
   * For single-building properties the building is created automatically and hidden in the admin UI.
2. **Addresses live on buildings**, with `locality` (e.g. "Sector 2") and a nullable `postal_code`, so incomplete addresses can be recorded now and completed later.
   * A property is **publishable** only when its primary building's address is complete (BR-PROP-3).
3. **`operating_model`** (`owned_operated` or `leased_operated`) is a required field on the property.
   * Lease facts (start, end, lock-in, notice) are kept in an optional, permission-restricted `property_leases` table, **proposed** pending Q-OW-3.
   * If recorded, long-term allocations ending after a lease's end date raise a warning (BR-PROP-5).
4. **No data is created by assumption.** The four properties, their buildings and their floors are entered by administrators (or via the import template) only when the owner supplies the details. Nothing is distributed evenly.
5. The entity keeps the name `locations` for now. Renaming it to `properties` is proposed (Q-OW-8).

## Alternatives considered
* **Keep building → location:** this cannot represent one property spanning two adjacent buildings, which the A-70/A-71 addresses may require.
* **Floors attached directly to the property:** loses which building a floor belongs to in multi-building properties.
* **Infer the operating model from lease records:** the owner asked for it explicitly; inference fails while lease data is incomplete.

## Consequences
* (+) Supports one-building and multi-building properties, incremental data entry, and both operating models.
* (+) Lease-expiry risk becomes visible to sales and operations (if Q-OW-3 confirms lease tracking).
* (−) One more level in admin navigation, mitigated by auto-hiding single buildings.
* (−) The import template must carry buildings (SG-INV-1.1).
