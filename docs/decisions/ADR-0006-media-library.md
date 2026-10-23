# ADR-0006 — Central media library with licensing metadata

* **Status:** PROPOSED (adds dependencies: `spatie/laravel-medialibrary` 11.x, `filament/spatie-laravel-media-library-plugin` 5.x)
* **Date:** 2026-10-24

## Context
Editors need one reusable library (photos, video, floor plans, GLB, documents) with alt text, focal points, origin and licence tracking. AI or concept imagery must never pass as real photography.

## Decision
* A `MediaAsset` model owns exactly one file through medialibrary, which provides renditions, conversions and disk abstraction. Assets attach to content through the `mediables` pivot, with collection and sort order.
* Origin, licence fields and `is_real_location_capture` live on `media_assets` and are enforced by BR-MED rules.
* Storage is S3-compatible in staging and production (`league/flysystem-aws-s3-v3`).

## Consequences
* (+) One asset can be reused everywhere; licence expiry can unpublish it everywhere.
* (−) It is one level of indirection beyond medialibrary's default per-model usage.
