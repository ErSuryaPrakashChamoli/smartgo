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

## Implementation notes (2026-10-28, time to a usable viewer, OD-121)
* Measured stage by stage before changing anything (performance marks `twin:…` in the viewer and the page, plus resource timing; `../operations/scripts/browser/verify-twin-performance.mjs`). On a throttled phone profile, the 2 kB test model needed 5.2 s, 85% of it downloading the 725 kB viewer module uncompressed; the model's request waited for the module, and a Draco model's decoder waited for the model.
* The press now starts the model's download, the viewer module and the module's own imports (`modulepreload`) together. The viewer creates the renderer while the model downloads, and parses the downloaded bytes (`GLTFLoader.parseAsync`).
* The Draco, Meshopt and KTX2 decoders are each a separate file (dynamic `import()`), loaded only for a model that needs them: the upload's checks name them (`data-decoders`), and anything else the file turns out to use is loaded after the download. three.js core became a shared chunk (`three.core-*.js`), fetched with the module.
* The page shows the download's progress (`<progress>`), can stop it ("Stop loading", `AbortController`), and says plainly when the download fails, when 3D is unavailable and when the browser loses the graphics context.
* Still nothing 3D loads before the press (Phase 7 AC 3): the files are requested from the click handler only.

## Implementation notes (2026-10-28, OD-122)
* Meshopt streams are now checked without decoding, against what the Meshopt decoder itself requires first: the header byte of the codec for the stream's mode (attributes 0xA0/0xA1, triangles 0xE0/0xE1, indices 0xD0/0xD1) and its minimum length. A stream that fails cannot be decoded; one that passes may still be wrong inside, which only the viewer finds.
* Uploading through the back-office form is now part of the browser checks: a 14.85 MB model at the limits is stored and opens; a file 4 bytes over 15 MB is refused with the plain-language reason (Livewire's temporary upload, the checks and the media library together).
* The public viewer stays unbuilt and off the website until NFR-PERF-4's conditions are settled (A-13, register OD-122 b).

## Implementation notes (2026-10-28, audit, OD-123)
* `mountTwinViewer` takes the press's abort signal: once aborted, it never touches the stage, so "Stop loading" pressed after the download (while a decoder loads or the model is parsed) leaves nothing on the page. Before, the stopped viewer put its canvas on the page once the decoder arrived.
* The viewer gives its graphics context back (`forceContextLoss`) when it stops, fails or is disposed, after removing its context-lost handler. Before, every press kept a context alive; Chromium keeps 16 and drops the oldest, whose handler then stopped the viewer on screen with a false "ran out of graphics memory". The page's reset also stops any download still running.
* The browser checks now cover stopping while a decoder loads, 20 rounds of "Show" and "Stop loading" (no dropped context), a decoder module or WebAssembly file that cannot load, and a browser without WebGL.

