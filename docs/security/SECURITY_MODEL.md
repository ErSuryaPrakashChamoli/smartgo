# SmartGro — Security Model

| | |
|---|---|
| Status | **v1.0-rc — Phase 1, ready for owner review.** Role matrix awaits client confirmation of the team structure (D-33). Implementation begins in Phase 3. |
| Last updated | 2026-10-24 |
| Target baseline | OWASP ASVS 4.0 Level 2 for the back office and booking flows; OWASP Top 10 for the public site |

---

## 1. Assets and threats

| Asset | Why it matters | Main threats |
|---|---|---|
| Lead and contact PII (names, phones, emails, company data) | regulatory and reputational exposure | data leak through over-broad staff access, IDOR on references, form spam, export misuse |
| Inventory, availability and price truth | revenue and customer trust | unauthorised edits by non-operational roles, double booking, price tampering in the client |
| Content and brand | public reputation | defacement via compromised editor account, malicious uploads (SVG/HTML), unlicensed imagery |
| Payments (if enabled) | money | forged payment callbacks, amount tampering, replayed webhooks |
| Staff accounts | gateway to everything | credential stuffing, phishing, session hijack |
| Secrets (API keys) | provider abuse and cost | leakage via repository, logs or frontend bundle |

---

## 2. Authentication

| Control | Decision |
|---|---|
| Staff login | Filament panel login only; no public self-registration. Accounts are created by the Super Admin. |
| MFA | **Required for every staff user** via Filament 5 `multiFactorAuthentication([...], isRequired: true)`, using a TOTP app with email code as fallback (supported per Filament 5 docs). |
| Passwords | Laravel `Password::defaults()`: min 12 chars, mixed case, numbers, `uncompromised()` check, bcrypt (existing `BCRYPT_ROUNDS`). |
| Throttling | login rate limit per email + IP; lockout notice to user; audit-log failed attempts. |
| Sessions | `SESSION_SECURE_COOKIE=true`, `SameSite=Lax`, `http_only`, idle timeout 120 min for staff [DEFAULT], session regenerated on login and on privilege change. |
| Deactivation | `users.is_active = false` blocks panel access immediately (`canAccessPanel`, checked on every request including Livewire requests per Filament 5 docs). |
| Customers | No customer accounts at launch (D-20). Customer self-service (view/cancel booking) uses signed, expiring links. |

---

## 3. Authorisation

* `spatie/laravel-permission` (proposed) holds roles and permissions. **Permissions are business capabilities** (e.g. `inventory.update_status`, `pricing.approve`), not one per CRUD screen.
* Every Filament resource and page, Livewire action and controller checks a **Policy**. Hiding a UI element is never the control.
* **Property scoping:** Property Managers act only on properties in their `property_user` scope (Sales/CRM Executives can optionally be restricted the same way per user). This is enforced in policies **and** in query scopes so lists, exports, global search and relation managers cannot leak other locations' records.
* **Separation of duties:** authors cannot approve their own content (BR-CMS-2) or their own price plans (BR-PR-2).

### 3.1 Role and permission matrix (owner-defined roles, 2026-10-24)

**Roles (OD-33):** Super Admin · Operations Manager · Property Manager · Sales/CRM Executive · Marketing Manager · Finance. These replace the 13 draft roles.

Legend: **M** manage (create/update/delete) · **A** approve/publish · **V** view · **S** scoped to the user's assigned properties (`property_user`) · — no access. Cells marked † are **proposals for duties of draft roles that no longer exist** (Q-OW-13, Q-OW-14).

| Capability | Super Admin | Operations Manager | Property Manager | Sales/CRM Executive | Marketing Manager | Finance |
|---|---|---|---|---|---|---|
| Users, roles, MFA reset | M | — | — | — | — | — |
| Integrations (connection status), tracking IDs | M | — | — | — | V (tracking IDs: M) | — |
| Brand settings, design tokens, **logo** | M | — | — | — | M/A | — |
| Pages, sections, navigation | M/A | — | — | — | M/A (not own) | — |
| Property and offering profiles (copy, media) | M/A | V | M (S) | V | M/A (not own) | — |
| Blog, FAQs, testimonials, case studies | M/A | — | — | — | M/A (not own) | — |
| SEO metadata, redirects | M/A | — | — | — | M/A | — |
| Media library | M | V | M (S) | — | M | — |
| Properties, buildings, floors: create, edit, address, status | M | M | M (S; edit only, no create/delete) | V | V | V |
| Property operating model (owned / leased) | M | M | V (S) | V | V | V |
| Lease facts (deferred, OD-14; restricted, never public) | M | M | — | — | — | M |
| Product categories and non-financial rules (ADR-0013) | M | M | V | V | V | V |
| Category rules affecting money (payment rule, cancellation policy) | M/A | V | — | V | — | M/A (not own) |
| Cities: add, edit, public visibility toggle | M | V | — | — | M | — |
| Customer segments list | M | V | — | V | M | — |
| Spaces and seats: capacity, status, dimensions | M | M | M (S) | V | V | V |
| Space visibility on website | M | M | M (S) | — | M | — |
| Allocations (occupancy) | M | M | M (S) | V | — | V |
| Inventory import: upload, validate, preview | M | M | M (S) | — | — | — |
| Inventory import: approve and commit | A | A (not own) | — | — | — | — |
| Price plans, charges, add-ons | M | V | V (S) | V | V | M |
| Price plan / offer activation | A | — | — | — | — | A (not own) |
| Offers (create) | M | — | — | V | M | M |
| Quotations (managed offices, custom deals) | M/A | — | — | M (approval above the discretion limit) | — | A |
| Bookings (create/modify/cancel) | M | M | M (S) | M | — | V |
| Booking override (outside hours) | M | M | M (S) | — | — | — |
| Tours | M | V | M (S) | M | — | — |
| Leads, contacts, deals | M | V (aggregates) | V (S) | M (all leads †) | V (aggregates) | V (deal values) |
| Lead export (audited) | A † | — | — | — | — | — |
| Assignment rules, pipeline stages | M † | — | — | — | — | — |
| SLA-breach alerts received | ✓ † | ✓ † | — | owner of the lead | — | — |
| Message templates, campaigns, automations | M/A | — | — | V | M/A (not own) | — |
| 3D model upload and anchors (models prepared by a 3D-modelling supplier) | M | V | — | — | M † | — |
| 3D scenes, hotspot ↔ space mapping | M/A | V | M (S) | — | M/A (not own) | — |
| Reports and dashboards | V (all) | V (operations) | V (S) | V (sales) | V (marketing) | V (finance) |
| Audit log | V | — | — | — | — | — |

**Separation of duties with fewer roles:** "not own" means a **different person** holding the approving permission. If only one person holds a role (e.g. one Marketing Manager), the Super Admin acts as the second approver for content, prices and imports. A person may hold more than one role, but can never approve their own submission.

**Mapping from the earlier draft roles:**

| Draft role | Now handled by |
|---|---|
| Platform Admin, Analyst | Super Admin (reports per role) |
| Content Editor, SEO Specialist | Marketing Manager |
| Centre Manager, Front Desk | Property Manager (scoped) |
| Sales Manager | Super Admin for configuration, exports and oversight; Sales/CRM Executives for day-to-day (proposal †, Q-OW-13) |
| Sales Executive | Sales/CRM Executive |
| 3D Technician | an external 3D-modelling supplier prepares models; the Marketing Manager uploads and maps them (proposal †, Q-OW-14) |

---

## 4. Input, output and uploads

| Area | Control |
|---|---|
| Validation | Form Requests (controllers) and validated Livewire/Filament schemas; enums for every status/type field; server-side recomputation of all prices and availability. |
| Output encoding | Blade `{{ }}` escaping by default. Rich text from the CMS is sanitised to an allow-list of tags at save time. `{!! !!}` only on sanitised fields, each reviewed. |
| CSRF | Laravel default on all web and Livewire routes; webhooks are excluded and use signature verification instead. |
| Uploads | MIME sniffed with `fileinfo` plus extension allow-list per collection; size limits per kind; images re-encoded on processing (strips EXIF GPS); SVG allowed only for Brand Admin and sanitised; GLB parsed and validated (no external URIs, budgets); stored with random names in private storage, served through CDN URLs; never executed. |
| Public forms | Rate limiting per IP and per phone/email; honeypot field + Cloudflare Turnstile (D-14); OTP verification only if spam warrants it (it adds friction). |
| IDOR | Customer-facing references are random (`SG-BK-…`); signed URLs for customer self-service; route model binding scoped by policy. |
| Mass assignment | `#[Fillable]` attributes (existing project convention in `app/Models/User.php`) with explicit lists; never `$guarded = []`. |

---

## 5. Transport, headers and platform

* HTTPS only, with HSTS (preload decision at launch).
* Content Security Policy: `script-src 'self'` plus allow-listed analytics/tag domains with nonces; `frame-src` allow-list for map and 360-tour providers; `object-src 'none'`.
* `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` minimal, `frame-ancestors 'none'` for the back office.
* Back-office path is not linked from the public site. Optional Cloudflare Access/IP allow-list is a D-14 decision.
* Production: `APP_DEBUG=false`, `APP_ENV=production`, config/route/view caching, no `.env` in web root.

---

## 6. Secrets management

* Secrets live only in environment variables or the host secret manager (IC-1).
* `.env`, `.env.backup`, `.env.production`, `.env.testing` and `.env.*.local` are git-ignored. `.env.example` contains placeholders only. Before the first commit (2026-10-24), the files were scanned for key patterns, secret-like assignments and personal emails: none found.
* Local database credentials are generated by `docs/operations/scripts/setup-local-mysql.sh`, sent to MySQL over stdin and written only to the local `.env`. They are never printed or shared in chat, documents or logs.
* Logs: request bodies are not logged by default; phone and email are masked in application logs; provider payloads stored in `webhook_receipts` are restricted to the Super Admin and purged after 90 days [DEFAULT].
* Rotation: documented in `../operations/DEPLOYMENT_RUNBOOK.md` once a host is chosen.

---

## 7. Audit trail

Audited actions (who, when, IP, before/after):

* login success and failure, MFA changes, role and permission changes, user activation and deactivation
* price plan create, update, approve, activate; offer approve
* space status/capacity changes, allocations, booking overrides and cancellations
* inventory imports: upload, warnings acknowledged, approval, commit (with file checksum)
* content submit, approve, reject, publish, rollback; redirect changes
* lead export, lead reassignment, consent changes, data deletion requests
* integration and tracking setting changes

Audit records are append-only for application roles; retention 2 years [DEFAULT, D-15].

---

## 8. Privacy (requires legal confirmation, D-15)

* Data minimisation per form (BR-PRV-1); purpose shown at collection; consent recorded with notice version (BR-PRV-2).
* Data subject requests (access, correction, erasure) handled through a back-office workflow with an audit record.
* Applicable law, including India's Digital Personal Data Protection Act, 2023 and its Rules, plus any other jurisdiction SmartGro serves, is to be confirmed by SmartGro's legal counsel. This document does not constitute legal advice.

---

## 9. Security verification (Phase 14)

* Automated: Pest policy tests per role × capability (generated from §3.1), IDOR tests, upload validation tests, webhook signature tests, `composer audit` and `npm audit` in CI.
* Manual: authenticated penetration test of the back office and booking/payment flows before launch.
* Any finding rated High or above blocks launch.
