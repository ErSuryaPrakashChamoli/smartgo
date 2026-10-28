# ADR-0009 — Three.js viewer, lazy-loaded, with a named-anchor convention

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
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

## Implementation notes (2026-10-28, Phase 7 first part, OD-118)
* `three` 0.186.1. The viewer is the Vite entry `resources/js/twin-viewer.js` (`mountTwinViewer`); `vite.config.js` keeps that entry's exports (`preserveEntrySignatures: 'exports-only'`), which Vite's application build would otherwise drop. Nothing else imports it, so pages without a viewer load no 3D code; the browser checks confirm it (`docs/operations/scripts/browser/three-d-files.mjs`).
* The Draco and KTX2 decoders are separate files that load only when a model needs them; the Meshopt decoder is part of the viewer module.
* A camera anchor (`SG_CAM_<n>`) looks along its node's −Z axis, as glTF cameras do.
* The checks run at upload, not in a queued job: a file that fails is refused with every reason and is not stored (`GlbValidator`, ERD C-29).
* So far the viewer is used only in the back office, to preview a model. The public viewer, scenes, floors, hotspots with live availability and guided-tour editing are not built.

## Implementation notes (2026-10-28, owner's 3D limits, OD-119)
* The limits are configuration (`config/twin.php`, `TwinModelLimits`): the owner-approved 15 MB and 500,000 triangles, and safeguards for pieces drawn, materials, textures (count, side, pixels in all), geometry once decompressed, nodes, the description's size, the model's size in metres and the time the checks may take. CLIENT_INPUTS_REQUIRED C-6.5 is the supplier's specification and must state the same values.
* `GlbValidator` reads the size from the file system first and measures the JSON description before decoding it. It checks every reference and byte range, the node trees and the scene; it reads each texture's format and pixel size from the image's own header (`ImageDimensions`: PNG, JPEG, WebP, KTX2), and the triangles inside each Draco stream from the stream's header (`DracoStream`, bitstream 2.0 to 2.2), because the viewer draws the decoded mesh whatever the description declares. Nothing is decompressed: a Meshopt stream that does not match its description fails only in the viewer.
* The viewer draws once to show a model and once per change of view. Before 2026-10-28 it drew every view twice, and at mount once more at the wrong size; the browser checks found it by counting the triangles drawn (twice those counted at upload) and now fail if showing a model takes other than one render.
* Draco's sequential layout is checked against the real decoder (the browser checks load a Draco test model); the Edgebreaker layout follows the decoder's source and has not met a real Edgebreaker file yet. The first supplier file with Draco confirms it.
