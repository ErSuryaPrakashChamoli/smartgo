# ADR-0012 — Inventory onboarding via a validated, approved XLSX import

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24

## Context
The client will supply the first real location's inventory as a spreadsheet. Operations staff are not technical. Inventory is the operational source of truth (BR-SOT-1), so a bad import would corrupt availability everywhere.

## Decision
* A versioned XLSX template (`SG-INV-1.x`; current `SG-INV-1.2`, which has buildings, operating model, inventory mode including hybrid zones, and product categories) with dropdowns, help text and an ignored example row (`../requirements/INVENTORY_IMPORT_SPEC.md`).
* Upload → queued validation (40 numbered rules) → plain-language report → preview with availability impact → approval by a second person → **all-or-nothing commit** in one transaction, with affected spaces locked (ADR-0004) and all checks re-run against current data.
* Upserts by permanent natural keys (`property_code`, `building_code`, `space_code`, …). **Never deletes:** missing records are reported, and decommissioning is an explicit action.
* Reading uses `openspout/openspout` (already installed via Filament; to be declared as a direct dependency, D-24). Filament provides the upload, report and approval UI. A domain action performs the import.
* Synthetic fixtures (`SYN-*`) are used for tests. A guard (V-180) rejects synthetic data outside local and testing environments. Real client files are never committed.

## Alternatives considered
* **Filament's built-in CSV importer:** one entity per CSV, row-by-row, no cross-sheet validation or approval. Unsuitable for relational, all-or-nothing inventory.
* **Manual entry through wizards only:** fine for small changes (kept as FR-INV-1), but too slow and error-prone for initial onboarding of dozens of spaces.
* **API sync from an existing system:** not needed. The owner confirmed there is no existing operational software (OD-06). It remains an option for future integrations (`../architecture/EXISTING_SYSTEMS_ASSESSMENT.md`).

## Consequences
* (+) Business users onboard locations without technical help. Errors are caught before they reach customers. Every change is audited and approved.
* (−) The template version must be maintained alongside the schema, and template changes need a version bump and importer support.
