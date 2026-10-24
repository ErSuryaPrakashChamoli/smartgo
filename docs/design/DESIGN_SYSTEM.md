# SmartGro — Design System

| | |
|---|---|
| Status | **Phase 2 in progress (started 2026-10-24, OD-41).** Steps 2.1, 2.2, 2.4 and 2.5 are built and waiting for the owner's visual-direction sign-off (AC-2.5); the proposals that need a decision are in §7.4. Phase 1 baseline v1.0 approved (OD-38); provisional palette D-11a. |
| Last updated | 2026-10-24 |
| Logo input | **Raster logo supplied 2026-10-24 (OD-39)** and stored unmodified at `resources/brand/source/smartgro-logo.webp` (WebP, 388 × 218 px, transparent background). Still requested (D-11b, CLIENT_INPUTS C-6.1): a vector master or a PNG at least 2000 px wide, and a reversed version for dark backgrounds. The logo will not be recreated or substituted. The palette below comes from the brief; the colours observed in the logo are listed in §3.1 for the Phase 2 decision. |

This document records what can be decided before Phase 2 (principles, accessibility constraints and a verified contrast analysis) and what is waiting for brand inputs. The full visual design, component library and page designs are Phase 2 deliverables.

---

## 1. Brand inputs status

| Input | Status |
|---|---|
| Brand name | **Confirmed (OD-01, 2026-10-24): "SmartGro"**, the official public-facing name. The repository folder name `smartGrow` is not a brand reference. |
| Domain | **Confirmed: smartgro.space** |
| Tagline | **Confirmed: "WORK SMART. GROW TOGETHER."** Not to be replaced by another slogan. |
| Logo (vector master + raster) | **Official logo confirmed as the authoritative reference (OD-01). Raster supplied 2026-10-24 (OD-39)**: `resources/brand/source/smartgro-logo.webp`, SHA-256 `295745308066c2f2187cb82bdec5b5b03c26a8dd210092a7e3dcd3bee203964b`. Its 388 × 218 px size suits on-screen use up to about 200 px wide (about 100 px tall); large hero, print and high-density uses need the vector master (D-11b). The wordmark reads "SMART GRO"; the written brand name stays "SmartGro" unless the owner decides otherwise (Q-OW-15). It must not be redrawn, reinterpreted or replaced by text, and no AI-generated substitute may be used. |

### 1.1 Logo management (FR-BRAND-1)

| Item | Requirement |
|---|---|
| Source of truth | the original file, kept unmodified: SVG preferred; otherwise AI/EPS/PDF; plus a high-resolution PNG |
| Web delivery | an optimised SVG (metadata stripped, sanitised, geometry unchanged) plus PNG/WebP fallbacks generated from the original, never redrawn |
| Variants | primary, reversed (for Deep Forest backgrounds) and mark-only. **Only** produced if the owner supplies them or approves a variant derived by recolouring; no reshaping. |
| Favicon / app icons | scaled from the official mark only |
| Usage in the site | referenced from brand settings (`site_settings` brand group; ERD §7). Templates never embed a copy, so replacing the file updates every page. |
| Permissions | only brand-permitted roles can replace it, and every replacement is audited |
| Clear space and minimum size | to be taken from the owner's guidelines if they exist. Otherwise proposed in Phase 2 for approval. |
| Palette | **Approved as provisional** (D-11a, 2026-10-24): Smart Green `#2E8B18` (logo and decorative/large elements), Action Green `#287A15` (normal-size text and buttons), Deep Forest `#073B2C`, Warm White `#F7F7F2`, Charcoal `#252D29` |
| Logo usage rules (clear space, minimum size, mono/reversed versions) | Not supplied |
| Brand typefaces and licences | Not supplied. The current scaffold uses Instrument Sans via Bunny Fonts, which is a Laravel default and **not** a brand decision. |
| Photography | Not supplied (D-12) |

---

## 2. Design direction (from the brief)

**Premium corporate + architectural sophistication + hospitality + technology.**

| Do | Avoid |
|---|---|
| Generous whitespace and calm rhythm, like an architectural monograph | Generic coworking-template layouts and copied competitor patterns |
| Real photography of real SmartGro spaces, art-directed with focal points | Over-used stock imagery and AI imagery presented as real |
| Restrained motion that explains (e.g. a floor-to-space transition in 3D) | Decorative parallax, bouncy or "childish" animation |
| Confident typography, with large display sizes for headlines | Excessive gradients and random glassmorphism |
| Information-rich location pages (capacity, commute, inclusions, price where approved) | Thin pages with placeholder content (an observed weakness in the Indian market; see the market benchmark) |
| Hospitality cues: human service, clear next step, a named contact person | Dark patterns, fake urgency, fake scarcity |

---

## 3. Provisional colour tokens and verified contrast

WCAG 2.x contrast ratios were computed on 2026-10-24 with the standard relative-luminance formula.

| Pair | Ratio | WCAG result |
|---|---|---|
| Smart Green `#2E8B18` on Warm White `#F7F7F2` | 4.05 : 1 | **Fails AA for normal text.** Passes for large text (≥ 24 px, or ≥ 18.66 px bold) and UI components. |
| Smart Green `#2E8B18` on White `#FFFFFF` | 4.35 : 1 | **Fails AA for normal text.** White text on a Smart Green button also fails at normal size. |
| Deep Forest `#073B2C` on Warm White | 11.69 : 1 | AAA |
| Charcoal `#252D29` on Warm White | 13.15 : 1 | AAA |
| Warm White on Deep Forest | 11.69 : 1 | AAA |
| Smart Green on Deep Forest | 2.89 : 1 | **Fails** (not even large text) |
| Smart Green on Charcoal | 3.25 : 1 | large text / UI only |

**Consequence:** Smart Green as given cannot be the colour for body links, small labels or white-on-green buttons without failing AA. The resolution below was **approved as provisional by the owner on 2026-10-24**, subject to re-verification once the logo file is sampled in Phase 2:

| Token | Value | Use | Contrast evidence |
|---|---|---|---|
| `--color-brand-green` | `#2E8B18` (to be re-sampled from the logo) | logo, large display type, icons, illustrations, focus rings on light backgrounds | 4.05 on warm white (large/UI only) |
| `--color-action-green` | `#287A15` (provisional) | links, primary buttons (white text), small brand-coloured text | 5.03 on warm white, 5.41 on white (AA) |
| `--color-forest` | `#073B2C` | dark sections, footer, headings | 11.69 on warm white (AAA) |
| `--color-ink` | `#252D29` | body text | 13.15 on warm white (AAA) |
| `--color-paper` | `#F7F7F2` | page background | — |
| `--color-surface` | `#FFFFFF` | cards on paper | — |

On dark Forest backgrounds, brand green must not carry text. Use Warm White text with a lighter green tint for accents, which will be validated in Phase 2.

Semantic availability colours (available, limited, unavailable) must **never rely on colour alone**. They always pair with a text label and an icon, both on the site and in 3D hotspots.

### 3.1 Colours observed in the supplied logo (2026-10-24)

Sampled from the opaque pixels of `resources/brand/source/smartgro-logo.webp` by median-cut clustering. The logo uses gradients, so these are representative values, not official brand colours. **No token has been changed**; adopting any of them is a Phase 2 decision.

| Where in the logo | Observed | Contrast on white | Compared with the provisional palette |
|---|---|---|---|
| Wordmark "SMART GRO", buildings | ≈ `#0B2C1B` | 15.1 : 1 | darker and greener than Deep Forest `#073B2C` |
| Monogram, main green | ≈ `#3F9B24` | 3.5 : 1 | lighter than Smart Green `#2E8B18`; large text and graphics only |
| Monogram, shadow | ≈ `#195625` | 8.8 : 1 | none |
| Monogram, highlight | ≈ `#84BD53` | 2.2 : 1 | decorative only |

**Back office (Phase 3):** Filament's palette is generated so that shade 500 is Smart Green `#2E8B18` and shade 600, used for buttons and links, is `#297D15` (white text 5.2 : 1, AA). This is close to the provisional Action Green `#287A15`; the two are unified when the tokens are shared in Phase 2. **Dark mode is off** in the back office until a reversed logo exists, because the dark wordmark is unreadable on dark backgrounds.

---

## 4. System structure (planned in Phase 1; built as described in §7)

* **Tokens:** defined once in Tailwind v4 `@theme` (CSS variables) and shared by the public site and the Filament custom theme. Filament 5 requires a custom theme (`php artisan make:filament-theme`) for Tailwind classes in custom views, per the Filament 5 docs.
* **CMS-editable brand settings** are limited to validated tokens (e.g. accent colour must pass an automatic contrast check) so editors cannot break accessibility.
* **Type scale:** fluid (`clamp()`), display / heading / body / caption, with at most two families.
* **Spacing and grid:** 4 px base, 12-column desktop and 4-column mobile, with a max content width of about 1280 px.
* **Components to design** (initial inventory): header with city switcher, mega-menu, footer, hero variants, location card, offering card, availability badge, price token ("from ₹… + GST"), gallery and lightbox, floor-plan viewer, 3D viewer frame and poster, hotspot card, tour-slot picker, booking stepper, enquiry form, FAQ accordion, testimonial, case-study card, comparison table, map block, CTA band, breadcrumb, pagination, toast and alert, empty states.
* **Page templates:** home, workspace-type hub, city, location, offering-at-location, meeting room, enterprise, insights index and article, case study, legal, 404/410.
* **Back-office look:** the Filament theme uses the same tokens. Dashboards are organised by job (Today's tours, New leads, Spaces needing attention, Content awaiting approval) rather than by database table.

---

## 5. Accessibility commitments

* WCAG 2.2 AA for the public site and the back office's core flows.
* Visible focus states, logical tab order, skip links, and form errors announced and associated with their fields.
* `prefers-reduced-motion` respected. The 3D viewer opens to a static poster, offers an accessible list alternative, and is never auto-played.
* Minimum touch target 44 × 44 px.

---

## 6. Imagery rules (summary of BR-MED)

* Real SmartGro locations are shown only with real photography or real scans of that location.
* Concept visuals and renders carry a visible "Concept visual" label and never appear in a location's photo gallery.
* Every asset records origin, licence and expiry. Alt text is required unless the image is decorative.

---

## 7. Phase 2 implementation (2026-10-24)

### 7.1 Where things live

| Item | Location |
|---|---|
| Colour and type tokens | `resources/css/app.css` (`@theme`), the single source of truth |
| Contrast pairings in use | `App\Services\Design\DesignTokens::PAIRINGS`, enforced by `tests/Feature/Services/Design/DesignTokensTest.php` |
| Fonts | `vite.config.js` (`laravel-vite-plugin` font provider): downloaded at build time and served from this site |
| Components | `resources/views/components/{ui,form,card,site}`; site layout `resources/views/layouts/site.blade.php` (`<x-layouts::site>`) |
| Design previews | `/design`, `/design/foundations`, `/design/components`, `/design/home`; never served in production, `noindex` |
| Review screenshots | `review/2026-10-24/` (home, components and foundations at 360 px and 1440 px) |

### 7.2 Colour tokens (proposed: P2-A)

Sampled from the supplied logo (§3.1) where a logo colour exists; the rest keep the provisional palette (D-11a). All 24 pairings in use pass WCAG 2.2 AA (see `/design/foundations`).

| Token | Value | Source | Change from provisional |
|---|---|---|---|
| `forest` | `#0B2C1B` | logo wordmark | replaces Deep Forest `#073B2C` |
| `leaf-300` | `#84BD53` | logo highlight | new: accents on dark (6.75 : 1 on forest) |
| `leaf-500` | `#3F9B24` | logo main green | new: graphics and large text only |
| `leaf-600` | `#2E8B18` | brief's Smart Green (within the logo gradient) | unchanged |
| `leaf-700` | `#287A15` | Action Green | unchanged; buttons and links |
| `leaf-800` / `leaf-900` | `#1F6410` / `#195625` | hover; logo shadow | new |
| `ink` | `#252D29` | Charcoal | unchanged |
| `paper` / `surface` | `#F7F7F2` / `#FFFFFF` | Warm White / white | unchanged |
| `mist`, `line`, `muted`, `control` | `#EAF2E4`, `#E3E6DF`, `#56615A`, `#7A847D` | derived | new neutrals; `control` gives form borders 3.6 : 1 or more |
| status colours | available, limited, unavailable, info, danger, each with a soft background | derived | new; always paired with an icon and a text label |

### 7.3 Typography (proposed: P2-B)

* **Sora** (headings, weights 500 and 600): geometric, slightly wide letterforms that echo the logo's wordmark.
* **Inter** (text, forms, prices; weights 400, 500 and 600): highly legible at small sizes, with tabular figures. **Prices always use Inter, because Sora has no ₹ glyph** (verified 2026-10-24).
* Both are SIL Open Font License fonts, downloaded at build time and served from the SmartGro domain: no third-party font requests. Only Sora 600 is preloaded.
* Fluid scale: `display-2xl` 40→72 px, `display-xl` 34→56 px, `display-lg` 28→40 px, `heading` 22→28 px, `subheading` 20 px, `lead` 17→20 px, body 16 px, small 14 px, eyebrow 13 px uppercase.

### 7.4 Proposals for the owner

| ID | Proposal | Recommendation |
|---|---|---|
| P2-A | Colour tokens in §7.2 (forest from the logo, new tints and status colours) | approve |
| P2-B | Sora for headings, Inter for text | approve |
| P2-C | **Visual direction** shown in `review/2026-10-24/` and at `/design/home` (AC-2.5) | approve, or name what to change |
| P2-D | Favicon and app icons made by **cropping the "SG" monogram** from the supplied logo, without reshaping or recolouring | approve (FR-BRAND-1 needs a favicon; the rules require approval for a mark-only variant) |
| P2-E | A **reversed logo** for dark backgrounds made by recolouring only the dark wordmark and buildings to Warm White; geometry unchanged | approve, or supply a reversed version |

### 7.5 Engineering decisions made in Phase 2

* **No framework JavaScript on marketing pages.** Menus use the native Popover API and FAQs use `<details>`, so the public pages ship 0 KB of first-party JavaScript and 10.7 KB (gzip) of CSS. Livewire (which bundles Alpine, about 86 KB gzip) loads only on pages that render a Livewire component (booking, tour scheduler). Those pages sit close to the 90 KB budget in NFR-PERF-2, which is reviewed in Phase 6.
* **Images:** components show real photographs when they exist, otherwise a placeholder labelled "Photography to be supplied by SmartGro". No stock or AI image is ever presented as a SmartGro space.
* **Previews contain only confirmed facts** (OD-01, OD-03, OD-04, OD-07, OD-08). Prices and availability read "on request", and FAQ answers read "Answer to be supplied by SmartGro". A test fails if the home preview ever shows a rupee amount.
* **Logo:** placed only on light backgrounds (the footer is light for this reason) until P2-E is decided.

