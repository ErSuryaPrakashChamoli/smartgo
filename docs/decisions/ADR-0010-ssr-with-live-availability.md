# ADR-0010 — Server-rendered, cacheable pages with live availability fragments

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24

## Context
SEO and performance favour server-rendered, CDN-cached HTML. Availability must never be stale (BR-SOT-4). Market research found operator pages rendered client-side (template placeholders or "Loading…" without JS) and so invisible to non-JS fetchers.

## Decision
* All indexable content is server-rendered Blade.
* Page shells are cacheable at the CDN and purged on `ContentPublished`.
* Availability and prices are rendered in small live fragments (Livewire islands or API calls with ≤ 60 s TTL) that are never cached at the edge.

## Consequences
* (+) Fast first paint, complete HTML for crawlers, and always-fresh availability.
* (−) One extra request on location pages for live state.
