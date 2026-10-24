# ADR-0005 — Content revisions and approval workflow

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24

## Context
Marketing must create, preview, submit for approval and publish without touching code. History and rollback are needed.

## Decision
* One polymorphic `revisions` table stores draft payloads for publishable types (pages, posts, location/offering profiles, FAQs, scenes).
* Live tables hold only the published state.
* Publishing copies an approved revision's payload into the live record in a transaction, audit-logs it, fires `ContentPublished`, and purges caches.
* Preview renders a revision via a signed, expiring, `noindex` URL.

## Consequences
* (+) Live reads stay simple and fast; drafts never leak; rollback is "republish revision N".
* (−) Every publishable type needs a mapping between its form schema and its payload. A shared trait and tests keep this consistent.
