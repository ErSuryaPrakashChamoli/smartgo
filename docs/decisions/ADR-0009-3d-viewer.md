# ADR-0009 — Three.js viewer, lazy-loaded, with a named-anchor convention

* **Status:** PROPOSED
* **Date:** 2026-10-24

## Context
3D digital twins need floor switching, hotspots coloured by live availability, guided camera tours and links to canonical spaces. Business users must not handle coordinates. 3D must not slow down normal pages.

## Decision
* Use **Three.js** (npm `three`, latest 0.186.1 checked 2026-10-24) with GLTFLoader and Draco/Meshopt/KTX2 decoders, as a separate Vite entry loaded by dynamic `import()` only on demand.
* GLB files use the node-naming convention `SG_SPACE_<code>`, `SG_AMENITY_<slug>`, `SG_CAM_<n>`. A validation job extracts anchors. Users map anchors to spaces from dropdowns, with auto-suggestions by code.
* Hotspot state comes live from the availability API; nothing about availability is baked into the scene.
* Every viewer has an accessible list and 2D floor-plan equivalent.

## Alternatives considered
* `@google/model-viewer` 4.4.0: faster to start and supports hotspots, but gives less control over multi-floor scenes, camera paths and per-mesh availability styling. It is a candidate for simple single-model previews.
* Third-party 360 tours (e.g. Matterport): supported as embeds for real captures, but they cannot link to SmartGro's live inventory.

## Consequences
* (+) Full control of UX and data linking; zero cost on pages without 3D.
* (−) A custom viewer needs specialist effort in Phase 7, plus a technician workflow for naming nodes.
