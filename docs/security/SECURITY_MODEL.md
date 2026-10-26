# SmartGro — Security Model

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval.** Six owner-defined roles (OD-33); duties of removed draft roles confirmed (OD-36, OD-37). Phase 3 implementation status: §10. Owner permission and publishing decisions (OD-51 to OD-75, **approved policy, not implemented**): §11. |
| Last updated | 2026-10-25 |
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
| Back-office access | **Requires the explicit `backoffice.access` permission** (OD-74). It applies to every protected back-office page, route, Livewire action and API. Role membership alone never grants access. Sign-in and two-step login are unchanged by OD-74. **Implemented 2026-10-26** (OD-81): entry needs an active account, a staff role (the scope, OD-68) and the permission, assigned to the role or granted as an exception (§10). |
| Deactivation | `users.is_active = false` blocks panel access immediately (`canAccessPanel`, checked on every request including Livewire requests per Filament 5 docs). |
| Customers | No customer accounts at launch (D-20). Customer self-service (view/cancel booking) uses signed, expiring links. |

---

## 3. Authorisation

* `spatie/laravel-permission` (proposed) holds roles and permissions. **Permissions are business capabilities** (e.g. `inventory.update_status`, `pricing.approve`), not one per CRUD screen.
* Every Filament resource and page, Livewire action and controller checks a **Policy**. Hiding a UI element is never the control.
* **Property scoping:** Property Managers act only on properties in their `property_user` scope (Sales/CRM Executives can optionally be restricted the same way per user). This is enforced in policies **and** in query scopes so lists, exports, global search and relation managers cannot leak other locations' records.
* **Separation of duties: the author, the approver and the publisher of a change are kept apart.**
  * Authors cannot approve their own work: content (BR-CMS-2, OD-65, OD-73), price plans (BR-PR-2), imports.
  * One approval by a person other than the author is enough (OD-73, resolves D-17).
  * Nobody, including the Super Admin, publishes a version they approved (OD-65; it was OD-56 for the Marketing Manager only).
  * The author may publish a version that someone else approved.
  * The same author → approver → publisher rule covers:
    * public contact details (OD-64, OD-69);
    * redirect changes (OD-66);
    * public-facing brand settings (OD-67, OD-70);
    * tracking IDs, analytics identifiers and advertising pixels (OD-71);
    * tag configurations in an external tag manager, if one is used (OD-75).
  * The user who created or submitted a booking request or reschedule never approves it (OD-72).
  * Legal entity and GST details are verified by a **different person** from anyone who edited them (OD-55, OD-62).
  * There is no self-verification, and no Super Admin override, emergency publish or forced approval (§11.2). Permitted combinations and failure cases: §11.6.
* **Authorised means explicitly granted (OD-60, OD-68):** a user may act only when the permission has been explicitly given to them in one of two ways, and, where scoped, only on their assigned properties:
  * **assigned to a role they hold**, which then applies to every member of that role; or
  * **granted to them individually**, as an optional exception within their role's permitted scope.

  Role membership never grants a permission that has not been explicitly assigned to that role, and a role name such as "Property Manager" grants nothing by itself. Policies check the permission, never a role name. Precedence, revocation and audit: §11.2.
* **Back-office access is a permission (OD-74):** entering the back office requires `backoffice.access`. It is assigned to roles in §3.1, or granted as an individual exception within a role's scope (OD-68). Holding a role is not enough without that assignment.
* **Approval never overrides availability:** approving a booking request or a reschedule, and any staff override, runs the same lock-and-recheck as a booking (BR-BK-5, BR-BK-9, BR-BK-13, ADR-0004). **Every booking and every reschedule re-checks availability under lock**, whether or not it needs approval (OD-63, OD-72). No role, including the Super Admin, can force a booking that fails the availability rules or the separation-of-duties rules.

### 3.1 Role and permission matrix (owner-defined roles, 2026-10-24; rows marked ‡ added or changed 2026-10-25)

**Roles (OD-33):** Super Admin · Operations Manager · Property Manager · Sales/CRM Executive · Marketing Manager · Finance. These replace the 13 draft roles. The owner's answers name a "Sales/CRM Manager" and a "Finance Manager". **For now these mean the existing Sales/CRM Executive and Finance roles** (owner, OD-58, OD-59). No role was added or changed.

Legend: **M** manage (create/update/delete) · **A** approve/publish · **V** view · **S** scoped to the user's assigned properties (`property_user`) · — no access. Cells marked † cover **duties of draft roles that no longer exist**. They were proposed and then **confirmed by the owner on 2026-10-24** (OD-36, OD-37). Rows marked ‡ are **owner decisions of 2026-10-25 (OD-51 to OD-75). They are approved policy, not yet implemented** (§11.3). Wherever an **A** approves or verifies someone's work, it is "not own" for the Super Admin too. The Super Admin holds no bypass (§10). **The Super Admin column is reviewed row by row like every other column.** The Super Admin does not automatically receive a new permission because it exists (OD-74).

**Approve and publish are separate rights.** For publishable changes, **A** means two permissions: approve (never one's own work) and publish (only a version **someone else** approved). This applies to every role, including the Super Admin (OD-65). It covers scheduled publishing and rollback, which run the same publish action (BR-CMS-3, BR-CMS-5). Publishable changes are:
* content (BR-CMS-1);
* public contact details (OD-64);
* redirect changes (OD-66);
* public-facing brand settings (OD-67, OD-70);
* tracking IDs, analytics identifiers and advertising pixels (OD-71);
* tag configurations in an external tag manager, if one is used (OD-75).

One approval by someone other than the author is enough. The author may publish if they are not the approver (OD-73).

**Each cell is the role's permitted scope (OD-68).**
* A permission in the cell applies to every member of the role once it has been **explicitly assigned to the role**.
* A permission in the cell that is not assigned to the role may be granted to one member as an **individual exception**.
* Nothing outside the cell can be granted to members of that role (§11.2).

| Capability | Super Admin | Operations Manager | Property Manager | Sales/CRM Executive | Marketing Manager | Finance |
|---|---|---|---|---|---|---|
| Back-office access (`backoffice.access`) ‡ (OD-74; all six roles confirmed by the owner, OD-81; **implemented 2026-10-26**) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Users, roles, MFA reset | M | — | — | — | — | — |
| Integrations (connection status) | M | — | — | — | V | — |
| Tracking IDs, analytics identifiers, advertising pixels ‡ (OD-71: independent approval before they become active; approvers as for other public settings) | M/A (not own) | — | — | — | M/A (not own) | — |
| Brand settings, design tokens, **logo**, social links ‡ (OD-67, OD-70: independent approval before publication; the Super Admin may approve but never publishes a version they approved) | M/A (not own) | — | — | — | M/A (not own) | — |
| Pages, sections, navigation | M/A | — | — | — | M/A (not own) | — |
| Property and offering profiles (copy, media) | M/A | V | M (S) | V | M/A (not own) | — |
| Blog, FAQs, testimonials, case studies | M/A | — | — | — | M/A (not own) | — |
| SEO metadata, redirects ‡ (page metadata follows its page's approval; redirect changes need independent approval, OD-66) | M/A | — | — | — | M/A (not own) | — |
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
| Booking requests, and reschedules that change inventory, price or terms: approve or reject ‡ (OD-52, OD-61, OD-63, OD-72; never the user who created or submitted it; every approval re-checks availability under lock, BR-BK-5) | A (not own) | A (not own) | A (S, not own) | — | — | — |
| Booking override (outside hours) | M | M | M (S) | — | — | — |
| Tours | M | V | M (S) | M | — | — |
| Leads, contacts, deals | M | V (aggregates) | V (S) | M (all leads †) | V (aggregates) | V (deal values) |
| Enquiry owner: assign or reassign by hand ‡ (OD-51) | M | — | — | M | — | — |
| Lead export (audited) | A † | — | — | — | — | — |
| Assignment rules, pipeline stages | M † | — | — | — | — | — |
| SLA-breach alerts received | ✓ † | ✓ † | — | owner of the lead | — | — |
| Message templates, campaigns, automations | M/A | — | — | V | M/A (not own) | — |
| 3D model upload and anchors (models prepared by a 3D-modelling supplier) | M | V | — | — | M † | — |
| 3D scenes, hotspot ↔ space mapping | M/A | V | M (S) | — | M/A (not own) | — |
| Public contact details (business email, phone, business address) ‡ (OD-53; independent approval before going live, OD-64, OD-69) | M/A (not own) | — | — | — | M/A (not own) | — |
| Legal entity and GST details: view ‡ (derived from OD-54, OD-55) | V | — | — | — | — | V |
| Legal entity and GST details: edit ‡ (OD-54) | M | — | — | — | — | M |
| Legal entity and GST details: verify ‡ (OD-55, OD-62) | A (not own) | — | — | — | — | A (not own) |
| Reports and dashboards | V (all) | V (operations) | V (S) | V (sales) | V (marketing) | V (finance) |
| Audit log | V | — | — | — | — | — |

**Separation of duties with fewer roles:** "not own" means a **different person** who has been granted the approving permission. If only one person holds a role (e.g. one Marketing Manager), the Super Admin acts as the second approver for content, contact details, redirects, brand settings, tracking IDs, prices and imports, and as the second person for legal verification. For a booking request or reschedule entered by staff, the approver is another user granted `bookings.approve`. A person may hold more than one role, but still counts as one person. They can never:
* approve their own work;
* approve a booking request or reschedule they created or submitted;
* verify legal details they edited;
* publish a version they approved.

**A rule that needs a second person is never relaxed for lack of staff** (owner response to R-20, 2026-10-25): if no second authorised person exists, the action waits. For example, invoices and legal pages stay blocked until another authorised person verifies the legal details. §11.5 lists when a second person is needed. The number of people per role is undecided and stays configurable (OD-57).

**Booking requests (OD-52, OD-61):** Sales/CRM Executives keep "create, modify, cancel" bookings, but they cannot approve or reject a request. For a product that requires staff approval (`requires_staff_approval`), a booking entered by someone without `bookings.approve` is created as a **request**, never as a confirmed booking. This is derived from OD-52 so that the restriction cannot be bypassed. The user who created or submitted a request never approves it; another user granted `bookings.approve` does (OD-72). An outside-hours override (BR-BK-9) never skips approval or the re-check.

**Reschedules (OD-63, BR-BK-13):**
* Every reschedule re-checks availability under the lock-first rule.
* A reschedule that changes inventory, price or booking terms waits for approval by **another** user granted `bookings.approve`, never the one who submitted it (OD-72).
* Until it is approved and confirmed, the booking keeps its current space, time, price and terms.

**Mapping from the earlier draft roles:**

| Draft role | Now handled by |
|---|---|
| Platform Admin, Analyst | Super Admin (reports per role) |
| Content Editor, SEO Specialist | Marketing Manager |
| Centre Manager, Front Desk | Property Manager (scoped) |
| Sales Manager | Super Admin for configuration, exports and oversight; Sales/CRM Executives for day-to-day (confirmed, OD-36) |
| Sales Executive | Sales/CRM Executive |
| 3D Technician | an external 3D-modelling supplier prepares models; the Marketing Manager uploads and maps them (confirmed, OD-37) |

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
* Content Security Policy: `script-src 'self'` plus allow-listed analytics/tag domains with nonces; `frame-src` allow-list for map and 360-tour providers; `object-src 'none'`. Approving a tracking ID in the back office (OD-71) never adds a domain to the allow-list. That stays a reviewed configuration change.
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
* booking request approval and rejection, with who created or submitted the request, the approver and the result of the availability re-check (OD-52, OD-61, OD-72)
* booking reschedules: before and after (space, seat or quantity, time, price, terms), who submitted it, whether approval was needed, the approver, and the result of the availability re-check (OD-63, OD-72)
* inventory imports: upload, warnings acknowledged, approval, commit (with file checksum)
* content draft started, edited, submitted, approved, rejected, published, scheduled, unscheduled, restored (OD-88) and failed scheduled publication, with the author, editors, approver and publisher recorded separately (OD-56, OD-65); redirects created, re-pointed or removed by a publication (BR-CMS-6)
* redirect changes and public-facing brand setting changes (logo, design-system settings, social links): submit, approve, reject, publish, with the author, approver and publisher recorded separately (OD-66, OD-67)
* lead export, lead assignment and reassignment by hand (OD-51), consent changes, data deletion requests
* public contact detail changes: submit, approve, reject, publish, with the author, approver and publisher recorded separately (OD-53, OD-64)
* legal entity and GST detail changes and verification, with the editor and verifier recorded separately (OD-54, OD-55, OD-62)
* permissions assigned to or removed from a role, and individual exceptions granted, revoked or stopped by a role change, with who acted, when, the user, the permission, its scope and the reason (OD-68)
* integration setting changes
* tracking ID, analytics identifier and advertising pixel changes: submit, approve, reject, publish, with the author, approver and publisher recorded separately (OD-71)

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

---

## 10. Implementation status (Phase 3, 2026-10-24)

What exists in code now, and where it differs from the plan above. Each row is covered by Pest tests on MySQL (`../phases/PHASE_STATUS.md`, Phase 3).

| Control | Implemented as | Difference from the plan |
|---|---|---|
| Staff login | Filament panel login at `BACKOFFICE_PATH` (default `admin`); no registration; password reset by email. Since 2026-10-25 the reset-request page gives every request the same answer and an empty form, so it never reveals whether an address belongs to a staff account. | none |
| MFA | required for every staff user; authenticator app (8 recovery codes, regenerable) or email code | none |
| Panel access | only users who are active, hold a staff role, **and** have the `backoffice.access` permission (assigned to all six roles, or granted as an exception within a staff role's scope); checked on every request, including Livewire requests (OD-74, OD-81; since 2026-10-26) | none |
| Passwords | `Password::defaults()`: at least 12 characters, mixed case, numbers | the breached-password check (`uncompromised()`) runs **in production only**, because it calls an external service |
| Throttling | Filament login limit: 5 attempts per minute per IP address (covered by a test since 2026-10-25); two-step codes are rate-limited per user (Filament) | not per email + IP. Behind Cloudflare the app must trust Cloudflare's proxy ranges, otherwise every visitor shares one IP (`../operations/DEPLOYMENT_RUNBOOK.md` §7). A per-email limit is reviewed in Phase 14. |
| Sessions | Laravel defaults; Filament regenerates the session at login | secure-cookie and idle-timeout values are set per environment at deployment. No session regeneration on privilege change: permissions are re-checked on every request, so a role change applies on the next request. |
| Capabilities | `backoffice.access` (all six roles), `users.manage` and `audit_log.view` (Super Admin only; matrix rows "Users, roles, MFA reset" and "Audit log"), and `content.view`, `content.edit`, `content.approve` and `content.publish` (Super Admin and Marketing Manager; since 2026-10-26, see "Content workflow"). Every role's capabilities are an explicit list in `StaffRole::capabilities()`, the Super Admin's included, so a new capability reaches no role until it is reviewed against §3.1 (OD-74, since 2026-10-26). There is no blanket bypass, so "not own" rules will still apply to the Super Admin. | other capabilities are added by the module that needs them; property scoping arrives with properties in Phase 5 |
| Content workflow | Since 2026-10-26 (Phase 4 steps 4.1 to 4.3, OD-85, OD-88). Pages and the four website menus (step 4.3) change only through revisions: draft → submitted → approved → published (BR-CMS-1, ADR-0005); one version in progress at a time. `RevisionPolicy` refuses approval by anyone who edited the version (author or later editor) and publication, scheduling or restoring by its approver (§11.6 cases 1–5). Every transition re-reads the version under a row lock and checks permission and state again, so a version is frozen once submitted even against a request already in flight; a rejection needs a note. The Super Admin follows the same rules, and a deactivated account can do nothing. Publishing locks the page and the version and re-validates the content under the lock. **Restoring** copies an earlier published version into a new draft that needs approval and publication; history is never changed (OD-88). `content:publish-scheduled` runs every minute and publishes due versions as the person who scheduled them, re-checked at that moment; a refused one stays unpublished, its schedule is cleared, the failure is audited with the reason and shown in the editor. **Back office:** Website → Pages (list, editor, version history) for `content.view`; every button calls the same server actions. **Content safety:** a fixed block library with server-side rules for every field; links go to a published page or an https address; rich text is refused if it holds scripts, event handlers, embedded content or unsafe links, and is sanitised against an allowlist when saved and again when shown. **Preview:** a signed link that expires after 30 minutes, inside the back office, so it also needs sign-in, two-step login, back-office access and `content.view`; `noindex`, `no-store` and `no-referrer`, and nothing is written. **Public pages:** a fallback route serves only published pages and live redirects, so existing routes are unaffected; redirect targets must be paths on this site. **Menus (step 4.3):** items link to a page picked from the list (shown only while that page is published, always at its current address) or to an https address on another website; addresses on this website, including back-office and preview links, are refused; labels are plain text. **Search-engine fields (step 4.3):** title, description and indexing are part of the page version and reach the live page only on publication; plain text only; the canonical URL is `APP_URL` plus the published address, never the request's host; previews are always `noindex` without a canonical URL. | The Property Manager's "Own properties" edit scope arrives with property profiles (step 4.7); until then no Property Manager holds `content.view` or `content.edit`. Images (step 4.4), testimonials (4.5), the Open Graph image and structured data (step 4.4, Phase 11) and scheduled unpublishing are not built. Social links and contact details in the footer are brand and public settings (BR-CMS-9) and arrive with step 4.6. Posts, FAQs, settings and manual redirects join in steps 4.5 and 4.6. Workspace and location mega-menus need Phase 5 data. |
| User lifecycle | create, edit, change roles, deactivate, reset two-step login. Accounts are never deleted. Staff cannot deactivate themselves or remove their own Super Admin role. | none |
| Headers | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, `Cross-Origin-Opener-Policy`, `Permissions-Policy`; HSTS over HTTPS in production | Content Security Policy (including `frame-ancestors 'none'` for the back office) is added in Phase 14 |
| Audit trail | sign-in, failed sign-in (email address only, never the password), sign-out, role changes (old and new roles), changes to a role's capabilities made by `access:sync` (role, old and new capabilities; recorded without a causer because the change comes from code, added 2026-10-25), wrong two-step login codes (user, IP, browser; never the code; added 2026-10-25), activation and name/email changes, two-step login changes (which methods are on, never secrets or codes), two-step login resets, and each content workflow step for pages and menus (draft started, edited, submitted, approved, rejected with its note, published, scheduled, unscheduled, restored, failed scheduled publication) with the author, editors, approver and publisher recorded separately, and each redirect a publication creates, re-points or removes (since 2026-10-26). Entries never hold page content, passwords, tokens or preview links. Read-only viewer at Settings → Audit log. | the other §7 actions are audited by the modules that introduce them |

---

## 11. Capabilities for back-office items 8 to 14 and public settings (proposed round 12, 2026-10-24; owner decisions rounds 13 to 16, 2026-10-25)

A review of the role design for pricing, enquiries, booking requests, tours, contact and legal details, website content, redirects and brand settings. **Viewing, editing, approving, verifying and publishing are separate capabilities.** The owner answered the open rows on 2026-10-25: Q-OW-18 → OD-51 to OD-57 (round 13), Q-OW-19 → OD-58 to OD-67 (round 14), Q-OW-20 and D-17 → OD-68 to OD-73 (round 15), Q-OW-21 → OD-74, OD-75 (round 16). See §11.1.

**An approved decision is not an implemented permission.** Only `backoffice.access`, `users.manage`, `audit_log.view` and, since 2026-10-26, `content.view`, `content.edit`, `content.approve` and `content.publish` exist in code (§10). Every other row below is approved policy that the module building the feature must implement and test (§11.3). No live permission or role assignment was changed by this review or by recording the decisions. The same table is shown in the back office at Design previews → Permissions review. It was updated on 2026-10-25, and a test checks that both list the same capabilities and role values (§11.3).

**Each value is the role's permitted scope (OD-68).** A permission applies to every member of the role once it has been explicitly assigned to the role. Otherwise it may be granted to one member as an individual exception within that scope (§11.2).

Values:
* Yes;
* Own properties (only properties assigned to that user);
* Totals (aggregates only);
* Not own (approves or verifies only other people's work; for bookings, never a request or reschedule the user created or submitted);
* After another's approval (publishes only a version that a different authorised person approved).

Basis:
* **Implemented** (in code and tested);
* **Approved matrix** (restates §3.1);
* **OD-xx** (owner decision of 2026-10-25);
* **Derived** (follows from a decision, as explained).

Approved matrix, OD and Derived rows are **not implemented**.

| Area | Capability | Purpose | Super Admin | Operations | Property | Sales/CRM | Marketing | Finance | Basis |
|---|---|---|---|---|---|---|---|---|---|
| Staff and audit | `backoffice.access` | Enter the back office: every protected page, route, Livewire action and API | Yes | Yes | Yes | Yes | Yes | Yes | Implemented (OD-74; roles confirmed by OD-81) |
| Staff and audit | `users.manage` | Manage staff, roles, permission grants and two-step login resets | Yes | — | — | — | — | — | Implemented (role assignments; individual exceptions, OD-68: not implemented) |
| Staff and audit | `audit_log.view` | Read the audit log | Yes | — | — | — | — | — | Implemented |
| Pricing | `pricing.view` | See prices | Yes | Yes | Own properties | Yes | Yes | Yes | Approved matrix |
| Pricing | `pricing.manage` | Create and edit draft prices | Yes | — | — | — | — | Yes | Approved matrix |
| Pricing | `pricing.approve` | Activate prices | Not own | — | — | — | — | Not own | Approved matrix |
| Pricing | `quotations.manage` | Prepare enterprise quotations | Yes | — | — | Up to limit | — | Approves | Approved matrix |
| Enquiries | `leads.view` | See enquiries | Yes | Totals | Own properties | Yes | Totals | Deal values | Approved matrix |
| Enquiries | `leads.manage` | Change status, log follow-ups | Yes | — | — | Yes | — | — | Approved matrix |
| Enquiries | `leads.assign` | Assign or reassign an owner by hand | Yes | — | — | Yes | — | — | OD-51, OD-58 |
| Enquiries | `leads.export` | Export enquiries (audited) | Yes | — | — | — | — | — | Approved matrix |
| Bookings | `bookings.view` | See booking requests and bookings | Yes | Yes | Own properties | Yes | — | Yes | Approved matrix |
| Bookings | `bookings.approve` | Approve or reject booking requests, and reschedules that change inventory, price or terms, never one the user created or submitted; every approval re-checks availability under lock | Not own | Not own | Own properties, not own | — | — | — | OD-52, OD-61, OD-63, OD-72 |
| Bookings | `bookings.cancel` | Cancel bookings | Yes | Yes | Own properties | Yes | — | — | Approved matrix |
| Tours | `tours.view` | See tours | Yes | Yes | Own properties | Yes | — | — | Approved matrix |
| Tours | `tours.manage` | Confirm, reschedule and cancel tours | Yes | — | Own properties | Yes | — | — | Approved matrix |
| Contact and legal | `settings.contact.manage` | Draft changes to public contact details | Yes | — | — | — | Yes | — | OD-53 |
| Contact and legal | `settings.contact.approve` | Approve contact-detail changes | Not own | — | — | — | Not own | — | OD-64, OD-69 |
| Contact and legal | `settings.contact.publish` | Put approved contact details live | After another's approval | — | — | — | After another's approval | — | OD-64, OD-65 |
| Contact and legal | `settings.legal.view` | See legal entity and GST details | Yes | — | — | — | — | Yes | Derived: editors and verifiers (OD-54, OD-55) must see the details; no other role was proposed |
| Contact and legal | `settings.legal.manage` | Edit legal entity and GST details | Yes | — | — | — | — | Yes | OD-54, OD-59 |
| Contact and legal | `settings.legal.verify` | Verify legal details against the registration documents | Not own | — | — | — | — | Not own | OD-55, OD-62 |
| Website content | `content.view` | Read drafts and previews | Yes | Profiles | Own properties | Profiles | Yes | — | Implemented for pages (2026-10-26, Super Admin and Marketing Manager); the profile and own-property scopes come with profiles (step 4.7) |
| Website content | `content.edit` | Edit drafts | Yes | — | Own properties | — | Yes | — | Implemented for pages (2026-10-26); the Property Manager's scope comes with property profiles (step 4.7) |
| Website content | `content.approve` | Approve submitted content (one approval is enough) | Not own | — | — | — | Not own | — | Implemented for pages (2026-10-26); OD-65, OD-73 |
| Website content | `content.publish` | Publish or schedule an approved version | After another's approval | — | — | — | After another's approval | — | Implemented for pages (2026-10-26); OD-56, OD-65, OD-73 |
| Website content | `testimonials.publish` | Publish testimonials and case studies (consent on file) | After another's approval | — | — | — | After another's approval | — | Approved matrix; OD-56, OD-65 |
| Website content | `media.manage` | Upload media with origin and licence | Yes | — | Own properties | — | Yes | — | Approved matrix |
| Redirects | `redirects.manage` | Draft redirect changes | Yes | — | — | — | Yes | — | Approved matrix |
| Redirects | `redirects.approve` | Approve redirect changes | Not own | — | — | — | Not own | — | OD-66 |
| Redirects | `redirects.publish` | Put approved redirects live | After another's approval | — | — | — | After another's approval | — | OD-66, OD-65 |
| Brand | `brand.manage` | Draft changes to the logo, design-system settings and social links | Yes | — | — | — | Yes | — | Approved matrix |
| Brand | `brand.approve` | Approve brand changes | Not own | — | — | — | Not own | — | OD-67, OD-70 |
| Brand | `brand.publish` | Put approved brand changes live | After another's approval | — | — | — | After another's approval | — | OD-67, OD-70, OD-65 |
| Tracking | `settings.tracking.manage` | Draft changes to tracking IDs, analytics identifiers and advertising pixels | Yes | — | — | — | Yes | — | Approved matrix |
| Tracking | `settings.tracking.approve` | Approve tracking changes | Not own | — | — | — | Not own | — | OD-71 (holders as for contact details and brand, OD-69, OD-70) |
| Tracking | `settings.tracking.publish` | Make approved tracking changes active on the public website | After another's approval | — | — | — | After another's approval | — | OD-71, OD-65 |

**Changes from the round-12 table (round 13):**
* The seven rows marked "Needs owner confirmation" now show the owner's decisions.
* The proposal that Sales/CRM Executives approve booking requests was **not** adopted (OD-52).
* The Super Admin is shown as "Not own" for price activation, content approval and legal verification. The existing rule already required this: the Super Admin holds every capability explicitly, without a bypass, so four-eyes rules apply to the Super Admin too.
* The Marketing Manager's publishing changed from a proposal ("Yes?") and from "Not own" to "After another's approval" (OD-56).

**Changes in round 14:**
* The Super Admin's publishing is now "After another's approval" too (OD-65).
* `bookings.approve` also covers reschedules that change inventory, price or terms (OD-63).
* Contact details, redirects and brand settings now have separate approve and publish rights (OD-64, OD-66, OD-67).

**Changes in round 15:**
* The values are the role's permitted scope. Role assignments apply to every member, and individual exceptions are optional (OD-68).
* The Super Admin may approve brand changes (not own) and publish them after another's approval (OD-70). This replaces "Open".
* New tracking rows (OD-71).
* `bookings.approve` is "not own": never the creator or submitter (OD-72).
* One content approval is enough (OD-73).

**Changes in round 16:**
* New `backoffice.access` row (OD-74).
* External tag-manager changes follow the tracking rows (OD-75).

### 11.1 Owner decisions (Q-OW-18 to Q-OW-21 and D-17, 2026-10-25)

Recorded in `../decisions/DECISION_REGISTER.md` §0.17 (OD-51 to OD-57), §0.18 (OD-58 to OD-67), §0.19 (OD-68 to OD-73) and §0.20 (OD-74, OD-75). Each decision approves the **intended policy only**. Rows are kept as first recorded. Where a later decision confirms or changes how one is applied, the row says so.

| ID | Owner's answer | Applied as |
|---|---|---|
| OD-51 | "Enquiry owner assignment: Super Admin and Sales/CRM Manager." | `leads.assign` for the Super Admin and the Sales/CRM Executive role. **Interpretation:** OD-33 has no "Sales/CRM Manager" role. Day-to-day sales work belongs to the Sales/CRM Executive role (OD-36), so that role is meant. Automatic assignment rules stay with the Super Admin (§3.1 †; values D-09). **Round 14:** interpretation confirmed for now (OD-58); only users explicitly granted `leads.assign` may assign (OD-60). |
| OD-52 | "Booking request approval: Super Admin and authorised Operations/Property Manager." | `bookings.approve` for the Super Admin, the Operations Manager and the Property Manager, **Property Managers only for their assigned properties**. Sales/CRM Executives, Marketing and Finance cannot approve. **Interpretation:** rejecting a request uses the same capability. "Authorised" means the role has been explicitly granted the capability (§11.2). A category's `approver_role` (ERD §4.1) may narrow approval to one of these roles, but never grants it. **Round 14:** rejection confirmed (OD-61). "Authorised" now means **the user** has been explicitly granted the permission, not the whole role (OD-60). Reschedules that change inventory, price or terms also need this approval (OD-63). **Round 15:** a permission assigned to a role applies to all its members, so "authorised" covers role assignments as well as individual exceptions (OD-68). The creator or submitter never approves (OD-72). |
| OD-53 | "Public contact details editing: Super Admin and Marketing Manager." | `settings.contact.manage` for the Super Admin and the Marketing Manager. **Round 14:** changes now need independent approval before going live (OD-64). |
| OD-54 | "Legal/GST editing: Super Admin and Finance Manager." | `settings.legal.manage` for the Super Admin and the Finance role. **Interpretation:** OD-33's "Finance" role is the one meant. **Round 14:** confirmed for now (OD-59). |
| OD-55 | "Legal/GST verification: A second authorised person distinct from the editor." | `settings.legal.verify`, held by the roles OD-54 authorises for legal and GST details: the Super Admin and Finance (**interpretation**). The verifier must be a different person from **everyone who edited** the version being verified, including when the editor is the Super Admin. **Derived:** any later edit clears the verification, otherwise an editor could change verified details unchecked. Until verified, invoices and legal pages cannot use the details (D-01). **Round 14:** confirmed (OD-62). |
| OD-56 | "Publishing approved content: Marketing Manager may publish only after separate approval by another authorised person." | The Marketing Manager holds `content.publish` and `testimonials.publish`, but can publish (including schedule and roll back) only a version **approved by another person** who holds `content.approve`. A Marketing Manager never approves and publishes the same version. BR-CMS-2 still applies: authors never approve their own work. **Existing workflow checked:** BR-CMS-1 already required approval before publishing, and BR-CMS-2 required an approver other than the author. Nothing required an approver other than the **publisher**, and the matrix's combined "A approve/publish" allowed it. OD-56 closes that gap for the Marketing Manager. Partly answers D-17; whether one approval is enough stays open. **Round 14:** extended to every publisher, including the Super Admin (OD-65). |
| OD-57 | "Role headcount: Undecided; keep role assignments and headcount configurable." | No limit on the number of people per role. The Super Admin assigns and changes roles in the back office (`users.manage`, implemented, audited). Four-eyes rules are never relaxed when staff are few (§3.1 note). |
| OD-58 | "Interpret Sales/CRM Manager as the existing Sales/CRM Executive role for now." | Confirms the OD-51 interpretation. No new role. "For now" means until the owner changes the role structure (OD-33). |
| OD-59 | "Interpret Finance Manager as the existing Finance role for now." | Confirms the OD-54 interpretation, and that the Finance role is the one for OD-55 verification. No new role. |
| OD-60 | "'Authorised' means the user has been explicitly granted the relevant permission. Role names alone do not grant access." | A user may act only when **that user** has been explicitly granted the permission, and only within their property scope where it applies. The role matrix (§3.1) is the most a user in each role may be granted. Being in a role, or having a role name, grants nothing by itself. Grants are made per user by a holder of `users.manage`, and every grant and removal is audited (§7). **Interpretation:** grants are per user, answering Q-OW-19.2 ("named people"); Q-OW-20.1 asks the owner to confirm. **Effect:** the current code grants the two implemented permissions to every Super Admin through the role. That differs from OD-60 and is listed in §11.3. It is not changed. **Round 15: the per-user reading is superseded by OD-68.** A permission explicitly assigned to a role applies to all its members, and individual grants are optional exceptions. The current role-based code matches this. |
| OD-61 | "The same authorised roles permitted to approve booking requests may reject them." | `bookings.approve` covers approve and reject, for users explicitly granted it (OD-60). The roles are the Super Admin, the Operations Manager, and Property Managers for their assigned properties. **Round 15:** never for a request the user created or submitted (OD-72). **Interpretation:** this applies to rejection too, because OD-61 ties rejection to approval. The creator can still withdraw (cancel) their own request. |
| OD-62 | "Legal/GST verification must be performed by a person different from the editor." | Confirms OD-55: the verifier is never anyone who edited the version, the Super Admin included. A later edit clears the verification. |
| OD-63 | "Rescheduling a confirmed booking requires approval again if it changes inventory, price or booking terms. Availability must be rechecked for every reschedule, and existing concurrency safeguards must remain in force." | **Every reschedule**, by anyone including the Super Admin, runs the BR-BK-5 lock-first transaction: lock, re-check availability for the new time and space, then write. A reschedule needs approval again, by a user granted `bookings.approve` (OD-61), when it changes any of:<br>• **inventory**: the space, seat, zone or quantity;<br>• **price**: the server-recomputed price differs from the booking's price snapshot;<br>• **booking terms**: product, duration, payment rule or cancellation policy.<br>**Interpretation:** a change of time alone that keeps the same space, quantity, duration, price and terms needs no new approval. Otherwise the owner's condition would cover every reschedule. It still re-checks availability under lock. **Round 15:** the user who submitted a reschedule never approves it (OD-72). **Derived:** until the change is approved and confirmed, the booking keeps its current space, time, price and terms. The change is written in one transaction after the lock and re-check. If the re-check fails, nothing changes. See BR-BK-13. |
| OD-64 | "Changes to public contact details require independent approval before going live." | Contact details follow the author → approver → publisher workflow:<br>• a holder of `settings.contact.manage` drafts the change;<br>• a different person holding `settings.contact.approve` approves it;<br>• someone other than the approver publishes it (OD-65).<br>Until then the website keeps showing the previously published details. **Interpretation:** the approvers are the roles OD-53 authorises for contact details (Super Admin and Marketing Manager), each only for other people's changes (Q-OW-20.2). **Round 15:** confirmed (OD-69). |
| OD-65 | "A content approver must not publish the same content version they approved. The author must also remain distinct from the approver." | For **every role, including the Super Admin** (answers Q-OW-19.6):<br>• author ≠ approver;<br>• approver ≠ publisher of that version, including scheduling and rollback.<br>The author may publish a version that someone else approved, so two people are enough. Extends OD-56. Following the owner's instruction to separate author, approver and publisher, the same rule applies to contact details, redirects and brand settings. **Round 15:** OD-71 confirms that public-facing settings use this separation. One approval is enough, which resolves D-17 (OD-73). |
| OD-66 | "SEO redirect changes require independent approval before publication." | Redirects created, changed or removed by hand follow the author → approver → publisher workflow. This includes the launch redirect map and the `/pin/*` 410 rules (OD-21, FR-SEO-6). **Interpretation:** a redirect created automatically by publishing an approved slug change (BR-CMS-6) is covered by that page's approval. Page SEO metadata is part of the page version and follows content approval. |
| OD-67 | "Changes to public-facing brand settings, including logo and design-system settings, require independent approval before publication." | Covers the logo and its renditions (FR-BRAND-1), design tokens and other design-system settings, and social links. **Interpretation:** FR-CMS-8 lists social links as brand settings. All follow the author → approver → publisher workflow. The Marketing Manager approves only other people's changes. **Contradiction found:** the approved matrix gives the Super Admin "M" but not "A" for brand settings. With one Marketing Manager, that person's brand changes could never be approved. The Super Admin's approve and publish rights are left open (Q-OW-20.3) rather than changed. **Round 15:** resolved by OD-70. The Super Admin may approve brand changes (not own) and never publishes a version they approved. |
| OD-68 | "A permission explicitly granted to a role applies to every user assigned to that role. Individual permission grants may be supported as optional exceptions, subject to the role's permitted scope. Role membership alone must not grant permissions that have not been explicitly assigned to that role." | §3.1 shows each role's **permitted scope**. A permission assigned to a role applies to all its members. An individual exception is optional and stays within a scope the user's role permits. Precedence, revocation and audit: §11.2 rule 1. This answers Q-OW-20.1 and supersedes the round-14 per-user reading of OD-60. |
| OD-69 | "Contact-detail changes require independent approval before becoming public. Super Admin and Marketing Manager may approve changes made by another person, subject to explicit permission grants. No person may approve their own change." | Confirms OD-64 and its interpretation (Q-OW-20.2). `settings.contact.approve` is "not own" for both roles. Publishing follows OD-65 (publisher ≠ approver). |
| OD-70 | "Super Admin may approve brand-setting changes but may not publish a version they approved. The author may publish only if they are not the approver." | Resolves Q-OW-20.3 and the matrix contradiction. The Super Admin's brand cell changes from M to M/A (not own). `brand.approve` is "not own" and `brand.publish` is "after another's approval" for the Super Admin and the Marketing Manager. |
| OD-71 | "Changes to tracking IDs, analytics identifiers and advertising pixels require independent approval before they become active on the public website." | Resolves Q-OW-20.4. Tracking is no longer exempt and follows author → approver → publisher (`settings.tracking.*`). **Interpretation:** the approvers are the Super Admin and the Marketing Manager, the roles that may edit tracking IDs (§3.1), "not own". This applies OD-71's instruction to use "the same principles used for other public-facing settings" (OD-69, OD-70). The CSP allow-list stays a separate configuration change (§5). |
| OD-72 | "A user who creates or submits a booking request or reschedule must not approve that same request or reschedule. Approval must be performed by another authorised person with explicit approval permission. Every booking or reschedule must re-check availability under the required inventory lock. No Super Admin override or forced approval may bypass availability or separation-of-duties rules." | Resolves Q-OW-20.5, reversing the round-14 default that one person could do both. A request submitted by a customer is approved by any user granted `bookings.approve`. A request or reschedule entered by staff needs a different approver. An outside-hours override (BR-BK-9) never skips approval or the re-check. **Interpretation:** rejection follows the same rule (OD-61). |
| OD-73 | "The author and approver must be different people, and the approver must not publish the version they approved. The author may publish an approved version if they are not the approver. A two-person workflow is therefore permitted." | **Resolves D-17:** one approval by a person other than the author is enough, and no second approval is required. Permitted combinations and failure cases: §11.6. |
| OD-74 | "Back-office access requires an explicit backoffice.access permission. Role membership alone does not grant access." | Resolves Q-OW-21.1.<br>• `backoffice.access` is required for every protected back-office page, route, Livewire action and API.<br>• Assignment to a role covers all its members; individual grants are optional, within the role's scope (OD-68).<br>• §3.1 shows the permission for all six roles (**derived:** each has back-office duties there, and this keeps today's access).<br>• The Super Admin's assignments are reviewed against §3.1 permission by permission, with no automatic "every permission" rule.<br>• Sign-in and two-step login are unchanged. **Round 26:** the role list was confirmed and implementation authorised (OD-81); implemented on 2026-10-26. |
| OD-75 | "External tag managers are optional, not prohibited. They must never provide a bypass around OD-71." | Resolves Q-OW-21.2.<br>• Changes to tracking IDs, analytics identifiers, advertising pixels and tag configurations need independent approval before they become active, wherever the change is made (SmartGro or an external provider).<br>• No one approves their own change or publishes a version they approved.<br>• Requirements are in §11.2 rule 7.<br>• A provider that cannot enforce them is **not enabled for live website tracking**.<br>• No provider is chosen or integrated (D-18). |

### 11.2 Rules every capability follows

1. **Explicit assignment, never role membership alone (OD-60, OD-68).**
   * **Role assignments.** A permission explicitly assigned to a role applies to every user assigned to that role. A role grants nothing that has not been explicitly assigned to it. Policies and actions check the permission, plus property scope where it applies. They never check a role name. Today role assignments are made in code (`StaffRole::capabilities()`, synchronised by `SyncAccessControl`), so changing one is a reviewed code change.
   * **Individual exceptions (optional).** A permission may also be granted to one user as an exception. It must lie within the permitted scope (§3.1) of a role that user holds, including that role's property scope. Supporting exceptions is optional: role assignments alone are a complete setup. Exceptions are not implemented (§11.3).
   * **Precedence:**
     1. The role's permitted scope is the upper limit. No role assignment and no exception can go beyond it.
     2. Within that limit, a user's permissions are what is assigned to their roles plus any exceptions granted to them.
     3. Exceptions only add permissions. OD-68 does not provide for removing a role-wide permission from one user.
     4. Separation of duties (rule 3), property scope and the booking re-check (rule 4) apply on top of every grant. No grant allows a user to approve their own work, act outside their assigned properties or skip a re-check.
   * **Revocation:**
     * Removing a permission from a role removes it from every member.
     * Revoking an exception removes it from that user.
     * An exception stops having effect once the user no longer holds a role whose permitted scope includes it, for example after a role change.
     * Deactivating an account stops everything.
     * Permissions are checked on every request, so each change applies from the user's next request (§10).
   * **Audit (§7).** Every role assignment and removal, and every exception granted, revoked or stopped, is recorded with who acted, when, the user, the permission, its scope and the reason. A user's exceptions must be visible on their record so they can be reviewed.
   * **Super Admin (OD-74).** The Super Admin receives only the permissions listed for it in §3.1, each reviewed when the permission is introduced. There is no rule that the Super Admin automatically holds every permission that exists.
   * **Back-office access (OD-74).** `backoffice.access` is checked for every protected back-office page, route, Livewire action and API, in addition to being active and signed in with two-step login.
2. **Least privilege.** Grants are only those in §3.1 and the table above. Holding one permission never implies another. Holding both an edit and an approve, verify or publish permission never allows approving, verifying or publishing past one's own involvement.
3. **Separation of duties is checked against people, not roles**, at the moment of the action:
   * approver ≠ author, for content, contact details, redirects, brand settings, tracking IDs, prices and imports (OD-65, OD-69 to OD-71, OD-73);
   * publisher ≠ approver of the version being published, for every role including the Super Admin (OD-65, OD-70, OD-73);
   * booking approver ≠ the user who created or submitted the request or reschedule (OD-72);
   * verifier ≠ any editor of the legal and GST version being verified (OD-55, OD-62).

   One person holding two roles is still one person. **One account per person:** checks compare user accounts, so each account belongs to one named person and is never shared. Creating accounts and granting permissions are audited (§7). There is **no Super Admin override, emergency publish, self-verification or forced approval** anywhere. Combinations and failure cases: §11.6.
4. **No bypass of booking availability or concurrency.**
   * Every path that creates, confirms or changes a booking goes only through the booking service: approving a request, rejecting a request, approving a reschedule (`bookings.approve`), every reschedule with or without approval (OD-63), a staff override (`bookings.override`), and every Super Admin action.
   * The service takes the lock first, then re-checks BR-BK-1 to BR-BK-3, BR-AV rules and booking windows (BR-BK-5, ADR-0004, OD-72).
   * There is no "force approve".
   * A failed re-check leaves the request unconfirmed ("Could not confirm"), or leaves the original booking unchanged, and the customer is offered other times.
5. **Audit.** Every action in §7, including the entries for OD-51 to OD-73, records who acted. The following are each recorded separately: author, approver and publisher; editor and verifier; and the booking creator or submitter and the approver. Role changes are already audited (§10).
6. **Headcount stays configurable** (OD-57). Code never assumes one person per role, and never lets one person satisfy a four-eyes rule alone (§11.5).
7. **External tag managers (OD-75): optional, never a bypass of OD-71.** Changes to tracking IDs, analytics identifiers, advertising pixels and tag configurations become active on the public website only after independent approval, wherever they are made. Before any tag manager is enabled for live website tracking, it must be shown to enforce all of the following. If it cannot, it is **not enabled for live tracking**.
   * **Access control:** only named, individual accounts (no shared logins), limited to people who hold the matching SmartGro tracking permission.
   * **Authorisation:** separate rights to edit, approve and publish, matching `settings.tracking.manage`, `.approve` and `.publish`.
   * **Approval:** every change to tags, triggers, variables or container settings is approved by a person other than its author before it can go live.
   * **Publication:** only an approved version goes live, and never by the person who approved it.
   * **Audit:** who changed, approved and published what, and when, is kept and available for review, in the provider or recorded in SmartGro.

   The tag manager's own container ID is a tracking ID in SmartGro and follows OD-71. Approving a change never adds a script domain to the Content Security Policy, which stays a reviewed configuration change (§5). No provider is chosen, and no provider capability is assumed (D-18).

### 11.3 Implementation and test gaps (not resolved by this documentation update)

**Implementation:** OD-74 (back-office access) and, for pages, OD-56, OD-65 and OD-73 (step 4.1, 2026-10-26) are in code. The rest of OD-51 to OD-75 is not.

* **Missing capabilities.** The following are not in `App\Enums\Access\Capability`:
  * `leads.assign`, `bookings.approve`;
  * `settings.contact.manage`, `settings.contact.approve`, `settings.contact.publish`;
  * `settings.legal.view`, `settings.legal.manage`, `settings.legal.verify`;
  * `testimonials.publish`;
  * `redirects.manage`, `redirects.approve`, `redirects.publish`;
  * `brand.manage`, `brand.approve`, `brand.publish`;
  * `settings.tracking.manage`, `settings.tracking.approve`, `settings.tracking.publish`.

  No role is assigned them, and no policy checks them. Each is added by the module that builds the feature.
* **Grants (OD-68).** The code already assigns permissions to roles (`StaffRole::capabilities()`), which is what OD-68 describes. Round 14 listed this as a mismatch with a per-user reading of OD-60; that reading is superseded, so it is no longer a gap.
  * **Individual exceptions** are not supported. If they are added, they need the scope limit, precedence, revocation and audit in §11.2 rule 1.
  * **Super Admin assignment and back-office access (OD-74):** implemented on 2026-10-26 (OD-81). They are no longer gaps. A test cannot yet tell an explicit Super Admin list from "every capability", because today the Super Admin is permitted every built capability; the difference shows once a capability is added that the Super Admin should not hold.
* **People-level checks.** Implemented for pages on 2026-10-26: author ≠ approver (every editor counts as an author) and approver ≠ publisher, including scheduling and rollback. Still missing:
  * author ≠ approver and approver ≠ publisher for posts, FAQs, testimonials, case studies, contact details, redirects, brand settings, tracking IDs and prices;
  * booking approver ≠ creator or submitter;
  * verifier ≠ editor, and verification cleared on edit.
* **Booking approval and rescheduling (OD-63, OD-72).** The real code does not exist (Phase 8). The lock-first rule is proven only by the MySQL concurrency harness (R-7; booking tests T-BK-01 to T-BK-10 must pass before booking code merges). `../architecture/API_CONTRACTS.md` §5 defines no staff approval or reschedule action yet.
* **Data model:**
  * `revisions` now records the author, every editor, the approver, the publisher and the scheduler (OD-85, built 2026-10-26);
  * `redirects` and `site_settings` (contact details, brand, tracking IDs) will use revisions (OD-85); the tables are not built yet (steps 4.2 and 4.6);
  * `bookings` cannot hold a reschedule waiting for approval, and does not record which staff user created or submitted a request (needed for OD-72).

  ADR-0005's revisions cover pages, posts, profiles, FAQs and scenes; the owner extended them to settings and redirects (OD-85).
* **Audit.** Content workflow steps are audited (since 2026-10-26). No audit events exist yet for the other new §7 entries.

**Design previews** (updated 2026-10-25, round 16, as Phase 2 design work under OD-49; they show the decided policy but enforce nothing):

* **Updated:**
  * the Permissions review lists the same capabilities and role values as the table above, and a test keeps the two in step;
  * the Workspace products "Approved by" choice offers only the Super Admin, Operations Manager and Property Manager (OD-52), and a test refuses Finance;
  * Contact and legal details: contact changes are described as needing independent approval and a separate publish (OD-64, OD-69), and verification as never by an editor (OD-55, OD-62);
  * Website content and Booking requests state approver ≠ publisher (OD-65, OD-73) and creator ≠ approver (OD-72).
* **Still not shown:**
  * the Website content preview is superseded for pages by the real screen (Website → Pages, 2026-10-26), where a second person approves and someone other than the approver publishes;
  * the Booking requests preview has no reschedule and no creator ≠ approver interaction; the samples are customer requests;
  * no preview exists for tracking IDs.
* **External tag manager (OD-75):** none is chosen or integrated, so none of the §11.2 rule 7 controls exist. None may be enabled for live tracking until they do.
* Every preview is Super Admin only, so none of them shows per-role or per-user behaviour.

**Tests:** apart from OD-74 (back-office access) and the page workflow (both tested since 2026-10-26, listed below), no test proves any OD-51 to OD-75 rule. Each needs policy tests per role × capability (§9), including:

* **grants:**
  * a user in a role without the permission assigned, and without an exception, is refused;
  * an exception outside the role's scope cannot be granted;
  * a revoked exception, or one whose role was removed, is refused from the next request;
* **bookings:**
  * Property Manager approval only for assigned properties;
  * Sales/CRM, Marketing and Finance refused for approval and rejection;
  * the creator or submitter of a request or reschedule cannot approve it, including the Super Admin;
  * every booking and reschedule re-checks availability under lock;
  * a reschedule that changes inventory, price or terms waits for approval;
  * a failed re-check leaves the original booking unchanged;
  * concurrent reschedule and booking attempts on the same space never double-book;
* **legal:** the verifier cannot be an editor, including the Super Admin; verification is cleared by an edit;
* **publishing (content, contact details, redirects, brand settings, tracking IDs):**
  * every refused case in §11.6 is refused, and every permitted case succeeds;
  * nothing goes live before approval;
* **redirects:** an automatic slug-change redirect goes live with its approved page;
* **audit:** each action writes its audit entry.

Production tests for OD-74 (since 2026-10-26):

* a Super Admin whose role lacks `backoffice.access` is refused on every back-office page;
* an individual exception within a staff role's scope lets a user in, and revoking it locks them out again;
* a user without a staff role is refused even with the permission granted directly;
* after `access:sync`, every staff role is back in;
* a first Super Admin created from the console can enter;
* each role holds exactly its listed capabilities.

Production tests for the page workflow (since 2026-10-26; `tests/Feature/Actions/Content/RevisionWorkflowTest.php`, `tests/Feature/Console/Commands/Content/PublishScheduledRevisionsCommandTest.php`):

* §11.6 cases 1 and 2 publish; cases 3, 4 and 5 are refused;
* the approver cannot schedule or restore the version they approved; restoring creates a new draft that needs approval, and history never changes (OD-88);
* a submitted or rejected version cannot be published, and an approved version cannot be changed;
* the Super Admin follows the same rules; Operations, Property, Sales/CRM and Finance are refused; a deactivated account is refused;
* a scheduled version is published at its time as the person who scheduled it, and not at all if that person may no longer publish;
* one version in progress at a time; a rejection needs a note; a clashing page address is refused with nothing changed;
* the audit log records the author, approver and publisher of each step.

A mutation check (2026-10-26) removed each of 19 rules in turn; the tests failed every time.

Production tests for step 4.2 (2026-10-26):

* **screens** (`tests/Feature/Filament/Clusters/Website`): the list, editor and version history refuse Operations, Property, Sales/CRM and Finance, a content role that lost `content.view`, and an open editor whose permission is revoked; a submitted version is frozen even against a direct save request; Approve, Publish and Schedule are disabled with the reason for the people the rules refuse; a rejection needs a note that the authors see; scheduling is entered in IST; restore is disabled for the restored version's approver;
* **preview** (`tests/Feature/Http/Controllers/Backoffice`): works for content staff; refused without a valid signature, after expiry, without sign-in, without two-step login, without `content.view`, and for a deactivated account; writes nothing, publishes nothing and leaves the live page unchanged;
* **content safety** (`tests/Feature/Services/Content`, `tests/Unit/Services/Content`): unknown blocks and fields, over-long fields, unsafe links (`javascript:`, `http:`, `//host`, typed paths, missing pages), executable rich text, reserved and malformed addresses are refused; a live page whose stored content holds script still renders none;
* **redirects** (`tests/Feature/Actions/Content/RedirectMovedPageTest.php`, `tests/Feature/Http/Controllers/Web/PageControllerTest.php`): a published address change redirects in one hop through several moves, never loops when a page moves back, creates nothing while the change is only submitted or approved, removes a redirect when a page takes over its address, refuses stored targets on other sites or to itself, and leaves other routes alone;
* **concurrency** (`tests/Feature/Infrastructure/ContentPublishingConcurrencyTest.php`, real parallel processes): an approved version published at the same instant by three people and the scheduler goes live exactly once; two pages claiming the same address at once: one succeeds, the other is refused.

Production tests for step 4.3 (2026-10-26):

* **menus** (`NavigationWorkflowTest`, `NavigationPayloadTest`, `EditNavigationMenuTest`): the §11.6 rules apply to menus, including for the Super Admin; publication replaces the whole live menu; scheduled and restored menus behave as pages do; unsafe destinations, addresses on this website, markup in labels and shapes a menu does not allow are refused; Finance is refused the screens;
* **public shell** (`PublicShellTest`): only published menu versions appear; links to unpublished pages are hidden and page links follow a change of address; nothing, and no placeholder link, is shown while menus are unpublished; the design previews keep their sample menus;
* **search-engine fields** (`PageSeoTest`): published title, description, `noindex` and canonical URL; defaults from the page title and introduction; the canonical URL follows a change of address while the old address redirects, and ignores a spoofed host header; unpublished changes stay off the live page; previews are never indexed; markup is refused.

A mutation check for step 4.3 (2026-10-26) removed 20 rules one at a time. 18 made a test fail at once. For the other two, a test was added and each then failed: an unsafe address stored directly in a live menu is never rendered, and the desktop menu marks the current page.

A mutation check for step 4.2 (2026-10-26) removed 36 rules one at a time. 34 made a test fail. Eight survived at first; for six of them a test was added and each then failed. The other two survivors (the preview's signature and two-step middleware) were hidden by a route cache rebuilt outside this work and failed once it was cleared. Two removals cannot fail a test on their own: each of the two publishing locks (page row, version row) is enough by itself to stop these races; removing both makes the concurrency test fail.

Existing preview tests:

* refusing approval of a booking request whose space is no longer free;
* Approve and Publish disabled for the author;
* the permissions review listing as implemented exactly the capabilities in code;
* the permissions review listing the same capabilities and role values as this section's table;
* the category approver choice refusing roles that may not approve booking requests.

They do not prove production enforcement.

### 11.4 Points raised with the owner

**Q-OW-19 (answered 2026-10-25, OD-58 to OD-67):**

1. **Role names:** "Sales/CRM Manager" and "Finance Manager" are recorded as the Sales/CRM Executive and Finance roles (OD-33). Confirm this, or say that a separate manager-level role is wanted, which would change OD-33. **→ OD-58, OD-59** (for now).
2. **"Authorised":** recorded as "the role is granted the capability", so every holder of that role is authorised. Should only named people within a role be authorised, for example some Property Managers but not others? That needs per-person grants. **→ OD-60** (the user must be explicitly granted the permission), then clarified by **OD-68**: role assignments apply to all members, and individual exceptions are optional.
3. **Rejecting booking requests** is recorded as part of approval (same roles). Confirm. **→ OD-61.**
4. **Rescheduling a confirmed booking** by Sales/CRM (who keep "modify"): does it need approval again? It always re-checks availability under lock (BR-BK-5). **→ OD-63.**
5. **Contact details:** do the Marketing Manager's changes go live when saved, or does a second person check them first? The preview currently says "entered and verified". **→ OD-64.**
6. **Super Admin publishing:** OD-56 names the Marketing Manager. Should the Super Admin also be unable to publish a version they approved? Recommended: yes, the same rule for every publisher. **→ OD-65.**
7. **SEO redirects and brand settings:** §3.1 lets the Marketing Manager approve their own changes. These are not publishable content under BR-CMS-1, so OD-56 does not cover them. Keep this, or require a second person? **→ OD-66, OD-67.**

**Q-OW-20 (answered 2026-10-25, OD-68 to OD-72):**

1. **Per-user grants:** OD-60 is recorded as "each user is granted each permission individually, within their role's limits". Confirm. Alternatively, a permission granted to a role could count as granted to its members; then only §11.2 rule 1 changes, and the current code already matches. **→ OD-68** (role assignments apply to every member; individual exceptions are optional).
2. **Contact-detail approvers:** recorded as the Super Admin and the Marketing Manager, each only for other people's changes (OD-64). Confirm. **→ OD-69** (confirmed).
3. **Brand settings and the Super Admin:** the approved matrix gives the Super Admin "M" but not "A". May the Super Admin approve brand changes (not own) and publish them (after another's approval), as for content? Without this, a single Marketing Manager's brand changes cannot be approved. **Recommended: yes.** **→ OD-70** (yes; the Super Admin never publishes a version they approved).
4. **Tracking IDs:** the Marketing Manager manages them (§3.1) without a second person. They are not public-facing brand settings, so OD-67 does not cover them. Keep this, or require approval? **→ OD-71** (independent approval required).
5. **Booking approval by one person:** OD-52, OD-61 and OD-63 do not ask for a second person. A user granted `bookings.approve` may therefore approve a request or reschedule they entered themselves, and availability is still re-checked under lock. Confirm, or require a different approver. **→ OD-72** (a different approver is required).

**D-17 (one approval or two): → OD-73.** One approval by a person other than the author is enough. The approver never publishes that version.

**Q-OW-21 (answered 2026-10-25, OD-74, OD-75):**

1. **Back-office sign-in:** any active user holding one of the six roles can sign in (§10). This is the only access that comes from role membership itself rather than from an assigned permission. Inside the back office, every screen and action checks an assigned permission. Keep this, or require an assigned sign-in permission (OD-68)? **→ OD-74** (an explicit `backoffice.access` permission is required).
2. **External tag manager:** if a tag manager is chosen (D-18), tags added inside that tool would bypass the back-office approval that OD-71 requires. The back office can enforce OD-71 only for identifiers entered in SmartGro, and the CSP allow-list limits which domains can load (§5). Should the tag manager's own access controls be part of D-18, or should SmartGro avoid a tag manager? **→ OD-75** (optional; enabled for live tracking only if it enforces §11.2 rule 7).

**No owner question is open after round 16.**

**Interpretations still standing** (recorded, not asked again; correct any that are wrong):

* a time-only reschedule needs no new approval (OD-63);
* an automatic slug-change redirect is covered by its page's approval (OD-66);
* social links are brand settings (OD-67);
* the tracking-ID approvers are the Super Admin and the Marketing Manager (OD-71);
* `backoffice.access` is assigned to all six roles, because each has back-office duties in §3.1 (OD-74; **confirmed by the owner, OD-81**);
* the creator of a booking request cannot reject it either, but may withdraw it (OD-72);
* any edit after approval or verification creates a new version that needs its own approval or verification (OD-55, OD-73).

### 11.5 When a second authorised person is required (owner response to R-20)

The owner's response to the staffing risk (2026-10-25): **keep strict separation of duties**. A rule that needs a second person waits until one exists; it is never relaxed, and the Super Admin cannot override it.

| Action | Second person? | Rule |
|---|---|---|
| Publishing website content (pages, navigation, profiles, posts, FAQs, testimonials, case studies, 3D scenes, page SEO metadata) | **Yes** | approver ≠ author; publisher ≠ approver; one approval is enough (BR-CMS-2, OD-65, OD-73) |
| Public contact details | **Yes** | same (OD-64, OD-69) |
| Redirects changed by hand | **Yes** | same (OD-66) |
| Public-facing brand settings | **Yes** | same (OD-67, OD-70) |
| Tracking IDs, analytics identifiers, advertising pixels | **Yes** | same (OD-71) |
| Tag-configuration changes in an external tag manager, if one is used | **Yes** | same (OD-75); a provider that cannot enforce it is not enabled for live tracking |
| Legal entity and GST details | **Yes** | verifier ≠ every editor of the version (OD-55, OD-62) |
| Price plan and offer activation; category rules affecting money | **Yes** | approver ≠ author (BR-PR-2, §3.1) |
| Inventory import commit | **Yes** | approver ≠ uploader (OD-27, §3.1) |
| Message templates, campaigns, automations | **Yes** | approver ≠ author (§3.1) |
| Booking request or reschedule entered by staff (when approval is needed) | **Yes** | approver ≠ creator or submitter (OD-72); availability always re-checked under lock |
| Booking request submitted by a customer | No: one staff approver | any user granted `bookings.approve` (Property Managers: own properties); availability always re-checked under lock |
| Time-only reschedule (OD-63 interpretation) | No | no approval needed; availability always re-checked under lock |
| Manual enquiry assignment, tours, overrides (which never skip approval), exports, role and permission assignments | No | one authorised user; audited |

**Minimum staffing:** each "Yes" row needs **at least two different people** who have been explicitly granted the permissions for that area. Two are enough: one authors, the other approves, and the author publishes (OD-73). A person counts once, however many roles they hold.

Example: one Super Admin and one Marketing Manager.
* Content, contact details, redirects, brand settings and tracking IDs work: either one authors and the other approves.
* Customer booking requests work, because the Super Admin approves them. A request or reschedule the Super Admin enters needs an Operations Manager or Property Manager (or another Super Admin) to approve it; the Marketing Manager cannot.
* Legal and GST details cannot be verified until a second Super Admin or a Finance user exists, so invoices and legal pages stay blocked.

### 11.6 Permitted combinations and failure cases (OD-72, OD-73)

**Publishable changes** (content, contact details, redirects, brand settings, tracking IDs, and tag configurations in an external tag manager). A, B and C are different people. Each has been explicitly granted the permission they use, through a role assignment or an exception (OD-68).

| Case | Author | Approver | Publisher | Result |
|---|---|---|---|---|
| 1 | A | B | A | **Permitted**: the two-person workflow (OD-73) |
| 2 | A | B | C | **Permitted** |
| 3 | A | A | any | **Refused**: the author cannot approve their own version |
| 4 | A | B | B | **Refused**: the approver cannot publish the version they approved |
| 5 | A and C (both edited the version) | C | A | **Refused**: everyone who edited a version is its author and cannot approve it (derived, as for OD-55) |

Other failure cases:
* Publishing a version that is not approved (draft, submitted or rejected) is refused (BR-CMS-1).
* Publishing a version that changed after approval is refused. Any edit makes a new version that needs its own approval (derived: approval covers one exact version).
* Scheduling counts as publishing and is refused for the version's approver; restoring an earlier version is refused for that version's approver (BR-CMS-3, BR-CMS-5). Restoring creates a new draft that goes through approval and publication, and the restored version's authors count as its editors (OD-88).
* A user without the approve or publish permission is refused, whatever their role name (OD-68).
* The Super Admin follows every row above. There is no override (OD-70, OD-72).
* A second approval is never required (OD-73).

**Bookings (OD-72, OD-63):**

| Case | Created or submitted by | Approved by | Result |
|---|---|---|---|
| 6 | a customer (website) | a staff user granted `bookings.approve` (Property Manager: own property) | **Permitted**, if the re-check under lock passes |
| 7 | staff user A | staff user B granted `bookings.approve` | **Permitted**, if the re-check passes |
| 8 | staff user A | A (any role, including the Super Admin) | **Refused** |
| 9 | anyone | anyone, but the time is no longer free | **Refused at the re-check** ("Could not confirm"); nothing changes |
| 10 | A submits a reschedule that changes inventory, price or terms | B | **Permitted**, if the re-check passes; until then the booking keeps its current values |
| 11 | A submits a time-only reschedule | no approval | **Permitted**, if the re-check under lock passes (OD-63 interpretation) |

**Legal and GST details:** A edits and B verifies: **permitted**. A verifies their own edit: **refused**. An edit after verification clears the verification.
