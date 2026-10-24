# SmartGro — Design System (Foundation Draft)

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval. Foundation only.** Provisional palette approved (D-11a); brand name, tagline and domain confirmed (OD-01). The full design system is a Phase 2 deliverable and needs the logo file (OD-20). |
| Last updated | 2026-10-24 |
| Blocking input | **D-11b: the original SmartGro logo files.** The owner directed that the logo already supplied is the authoritative reference. The original file was **not found** in the project environment: searched the repository, its parent folder, and the home directory by name and by recent image files on 2026-10-24. It is recorded as an **outstanding asset requirement** (CLIENT_INPUTS C-6.1). The logo will not be recreated or substituted. The palette below comes from the brief, not from sampling the logo; it will be re-verified once the original file is available. |

This document records what can be decided before Phase 2 (principles, accessibility constraints and a verified contrast analysis) and what is waiting for brand inputs. The full visual design, component library and page designs are Phase 2 deliverables.

---

## 1. Brand inputs status

| Input | Status |
|---|---|
| Brand name | **Confirmed (OD-01, 2026-10-24): "SmartGro"**, the official public-facing name. The repository folder name `smartGrow` is not a brand reference. |
| Domain | **Confirmed: smartgro.space** |
| Tagline | **Confirmed: "WORK SMART. GROW TOGETHER."** Not to be replaced by another slogan. |
| Logo (vector master + raster) | **Official logo confirmed as the authoritative reference (OD-01).** The owner states it was supplied as an image in conversation. That image is **not available to the working session**, and **no logo file exists in the project directory** (re-checked 2026-10-24). **Request:** place the original at `resources/brand/source/` (Q-OW-10). The owner will add it later (OD-20). Phase 2 cannot finalise the brand system until it is there. It must not be redrawn, reinterpreted or replaced by text, and no AI-generated substitute may be used. |

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

---

## 4. System structure (to be built in Phase 2)

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
