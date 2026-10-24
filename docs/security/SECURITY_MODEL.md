# SmartGro — Security Model

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval.** Six owner-defined roles (OD-33); duties of removed draft roles confirmed (OD-36, OD-37). Phase 3 implementation status: §10. Owner permission decisions for back-office items 8 to 14 (OD-51 to OD-57, **approved policy, not implemented**): §11. |
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
| Deactivation | `users.is_active = false` blocks panel access immediately (`canAccessPanel`, checked on every request including Livewire requests per Filament 5 docs). |
| Customers | No customer accounts at launch (D-20). Customer self-service (view/cancel booking) uses signed, expiring links. |

---

## 3. Authorisation

* `spatie/laravel-permission` (proposed) holds roles and permissions. **Permissions are business capabilities** (e.g. `inventory.update_status`, `pricing.approve`), not one per CRUD screen.
* Every Filament resource and page, Livewire action and controller checks a **Policy**. Hiding a UI element is never the control.
* **Property scoping:** Property Managers act only on properties in their `property_user` scope (Sales/CRM Executives can optionally be restricted the same way per user). This is enforced in policies **and** in query scopes so lists, exports, global search and relation managers cannot leak other locations' records.
* **Separation of duties:** authors cannot approve their own content (BR-CMS-2) or their own price plans (BR-PR-2). A Marketing Manager publishes only a version that **another** authorised person approved (OD-56, BR-CMS-2a). Legal entity and GST details are verified by a **different person** from the one who edited them (OD-55).
* **Capabilities, not role names:** a person may act only when a role they hold has been **explicitly granted** the capability, and, where scoped, only on their assigned properties. Policies check the capability, never a role name, so holding a role such as "Property Manager" authorises nothing by itself (§11.2).
* **Approval never overrides availability:** approving a booking request, or any staff override, runs the same lock-and-recheck as a booking (BR-BK-5, BR-BK-9, ADR-0004). No role, including the Super Admin, can force a booking that fails the availability rules.

### 3.1 Role and permission matrix (owner-defined roles, 2026-10-24; rows marked ‡ added 2026-10-25)

**Roles (OD-33):** Super Admin · Operations Manager · Property Manager · Sales/CRM Executive · Marketing Manager · Finance. These replace the 13 draft roles. The owner's answers name a "Sales/CRM Manager" and a "Finance Manager"; they are recorded as the Sales/CRM Executive and Finance roles (§11.1, to be confirmed in Q-OW-19).

Legend: **M** manage (create/update/delete) · **A** approve/publish · **V** view · **S** scoped to the user's assigned properties (`property_user`) · — no access. Cells marked † cover **duties of draft roles that no longer exist**. They were proposed and then **confirmed by the owner on 2026-10-24** (OD-36, OD-37). Rows marked ‡ are **owner decisions of 2026-10-25 (OD-51 to OD-57). They are approved policy, not yet implemented** (§11.3).

**Approve and publish are separate rights.** For publishable content (BR-CMS-1), **A** means two capabilities: `content.approve` (never for one's own work, BR-CMS-2) and `content.publish` (only an approved version). A Marketing Manager publishes **only a version approved by another authorised person**, never one they approved themselves (OD-56). This covers scheduled publishing and rollback, which run the same publish action (BR-CMS-3, BR-CMS-5).

Every cell is a grant that must be **explicitly given** to the role (§11.2).

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
| Booking requests: approve or reject ‡ (OD-52; approval re-checks availability under lock, BR-BK-5) | A | A | A (S) | — | — | — |
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
| Public contact details (business email, phone, business address) ‡ (OD-53) | M | — | — | — | M | — |
| Legal entity and GST details: view ‡ (derived from OD-54, OD-55) | V | — | — | — | — | V |
| Legal entity and GST details: edit ‡ (OD-54) | M | — | — | — | — | M |
| Legal entity and GST details: verify ‡ (OD-55) | A (not own) | — | — | — | — | A (not own) |
| Reports and dashboards | V (all) | V (operations) | V (S) | V (sales) | V (marketing) | V (finance) |
| Audit log | V | — | — | — | — | — |

**Separation of duties with fewer roles:** "not own" means a **different person** holding the approving permission. If only one person holds a role (e.g. one Marketing Manager), the Super Admin acts as the second approver for content, prices and imports, and as the second person for legal verification. A person may hold more than one role, but can never approve their own submission, verify legal details they edited, or (as Marketing Manager) publish content they approved. **A rule that needs a second person is never relaxed for lack of staff:** if no second authorised person exists, the action waits. For example, invoices and legal pages stay blocked until another authorised person verifies the legal details. The number of people per role is undecided and stays configurable (OD-57).

**Booking requests (OD-52):** Sales/CRM Executives keep "create, modify, cancel" bookings, but they cannot approve or reject a request. For a product that requires staff approval (`requires_staff_approval`), a booking entered by someone without `bookings.approve` is created as a **request**, never as a confirmed booking. This is derived from OD-52 so that the restriction cannot be bypassed. Whether rescheduling a confirmed booking needs approval again is open (Q-OW-19).

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
* booking request approval and rejection, with the approver and the result of the availability re-check (OD-52)
* inventory imports: upload, warnings acknowledged, approval, commit (with file checksum)
* content submit, approve, reject, publish, schedule, rollback, with the author, approver and publisher recorded separately (OD-56); redirect changes
* lead export, lead assignment and reassignment by hand (OD-51), consent changes, data deletion requests
* public contact detail changes (OD-53); legal entity and GST detail changes and verification, with the editor and verifier recorded separately (OD-54, OD-55)
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

---

## 10. Implementation status (Phase 3, 2026-10-24)

What exists in code now, and where it differs from the plan above. Each row is covered by Pest tests on MySQL (`../phases/PHASE_STATUS.md`, Phase 3).

| Control | Implemented as | Difference from the plan |
|---|---|---|
| Staff login | Filament panel login at `BACKOFFICE_PATH` (default `admin`); no registration; password reset by email | none |
| MFA | required for every staff user; authenticator app (8 recovery codes, regenerable) or email code | none |
| Panel access | only users who are active **and** hold at least one of the six roles; checked on every request | none |
| Passwords | `Password::defaults()`: at least 12 characters, mixed case, numbers | the breached-password check (`uncompromised()`) runs **in production only**, because it calls an external service |
| Throttling | Filament login limit: 5 attempts per minute per IP address | not per email + IP. Behind Cloudflare the app must trust Cloudflare's proxy ranges, otherwise every visitor shares one IP (`../operations/DEPLOYMENT_RUNBOOK.md` §7). A per-email limit is reviewed in Phase 14. |
| Sessions | Laravel defaults; Filament regenerates the session at login | secure-cookie and idle-timeout values are set per environment at deployment. No session regeneration on privilege change: permissions are re-checked on every request, so a role change applies on the next request. |
| Capabilities | `users.manage` and `audit_log.view`, both Super Admin only (matrix rows "Users, roles, MFA reset" and "Audit log"). Super Admin receives each capability explicitly; there is no blanket bypass, so "not own" rules will still apply to the Super Admin. | other capabilities are added by the module that needs them; property scoping arrives with properties in Phase 5 |
| User lifecycle | create, edit, change roles, deactivate, reset two-step login. Accounts are never deleted. Staff cannot deactivate themselves or remove their own Super Admin role. | none |
| Headers | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, `Cross-Origin-Opener-Policy`, `Permissions-Policy`; HSTS over HTTPS in production | Content Security Policy (including `frame-ancestors 'none'` for the back office) is added in Phase 14 |
| Audit trail | sign-in, failed sign-in (email address only, never the password), sign-out, role changes (old and new roles), activation and name/email changes, two-step login changes (which methods are on, never secrets or codes), two-step login resets. Read-only viewer at Settings → Audit log. | the other §7 actions are audited by the modules that introduce them |

---

## 11. Capabilities for back-office items 8 to 14 (proposed round 12, 2026-10-24; owner decisions round 13, 2026-10-25)

A review of the role design for pricing, enquiries, booking requests, tours, contact and legal details, and website content. **Viewing, editing, approving, verifying and publishing are separate capabilities.** The owner answered the open rows on 2026-10-25 (Q-OW-18 → OD-51 to OD-57, §11.1).

**An approved decision is not an implemented permission.** Only `users.manage` and `audit_log.view` exist in code (§10). Every other row below is approved policy that the module building the feature must implement and test (§11.3). No live permission or role assignment was changed by this review or by recording the decisions. The same table is shown in the back office at Design previews → Permissions review, which **still shows the round-12 proposals** (§11.3).

Values: Yes · Own properties (only properties assigned to that user) · Totals (aggregates only) · Not own (approves or verifies only other people's work) · After another's approval (publishes only a version approved by a different authorised person). Basis: **Implemented** (in code and tested) · **Approved matrix** (restates §3.1) · **OD-xx** (owner decision of 2026-10-25) · **Derived** (follows from a decision, as explained). Approved matrix, OD and Derived rows are **not implemented**.

| Area | Capability | Purpose | Super Admin | Operations | Property | Sales/CRM | Marketing | Finance | Basis |
|---|---|---|---|---|---|---|---|---|---|
| Staff and audit | `users.manage` | Manage staff, roles and two-step login resets | Yes | — | — | — | — | — | Implemented |
| Staff and audit | `audit_log.view` | Read the audit log | Yes | — | — | — | — | — | Implemented |
| Pricing | `pricing.view` | See prices | Yes | Yes | Own properties | Yes | Yes | Yes | Approved matrix |
| Pricing | `pricing.manage` | Create and edit draft prices | Yes | — | — | — | — | Yes | Approved matrix |
| Pricing | `pricing.approve` | Activate prices | Not own | — | — | — | — | Not own | Approved matrix |
| Pricing | `quotations.manage` | Prepare enterprise quotations | Yes | — | — | Up to limit | — | Approves | Approved matrix |
| Enquiries | `leads.view` | See enquiries | Yes | Totals | Own properties | Yes | Totals | Deal values | Approved matrix |
| Enquiries | `leads.manage` | Change status, log follow-ups | Yes | — | — | Yes | — | — | Approved matrix |
| Enquiries | `leads.assign` | Assign or reassign an owner by hand | Yes | — | — | Yes | — | — | OD-51 |
| Enquiries | `leads.export` | Export enquiries (audited) | Yes | — | — | — | — | — | Approved matrix |
| Bookings | `bookings.view` | See booking requests and bookings | Yes | Yes | Own properties | Yes | — | Yes | Approved matrix |
| Bookings | `bookings.approve` | Approve or reject booking requests; approval re-checks availability under lock | Yes | Yes | Own properties | — | — | — | OD-52 |
| Bookings | `bookings.cancel` | Cancel bookings | Yes | Yes | Own properties | Yes | — | — | Approved matrix |
| Tours | `tours.view` | See tours | Yes | Yes | Own properties | Yes | — | — | Approved matrix |
| Tours | `tours.manage` | Confirm, reschedule and cancel tours | Yes | — | Own properties | Yes | — | — | Approved matrix |
| Contact and legal | `settings.contact.manage` | Edit public contact details | Yes | — | — | — | Yes | — | OD-53 |
| Contact and legal | `settings.legal.view` | See legal entity and GST details | Yes | — | — | — | — | Yes | Derived: editors and verifiers (OD-54, OD-55) must see the details; no other role was proposed |
| Contact and legal | `settings.legal.manage` | Edit legal entity and GST details | Yes | — | — | — | — | Yes | OD-54 |
| Contact and legal | `settings.legal.verify` | Verify legal details against the registration documents | Not own | — | — | — | — | Not own | OD-55 |
| Website content | `content.view` | Read drafts and previews | Yes | Profiles | Own properties | Profiles | Yes | — | Approved matrix |
| Website content | `content.edit` | Edit drafts | Yes | — | Own properties | — | Yes | — | Approved matrix |
| Website content | `content.approve` | Approve submitted content | Not own | — | — | — | Not own | — | Approved matrix |
| Website content | `content.publish` | Publish, schedule or roll back an approved version | Yes | — | — | — | After another's approval | — | OD-56 |
| Website content | `testimonials.publish` | Publish testimonials and case studies (consent on file) | Yes | — | — | — | After another's approval | — | Approved matrix; OD-56 |
| Website content | `media.manage` | Upload media with origin and licence | Yes | — | Own properties | — | Yes | — | Approved matrix |

Changes from the round-12 table: the seven rows marked "Needs owner confirmation" now show the owner's decisions. The proposal that Sales/CRM Executives approve booking requests was **not** adopted (OD-52). The Super Admin is shown as "Not own" for price activation, content approval and legal verification, as the existing rule already required: the Super Admin holds every capability explicitly, without a bypass, so four-eyes rules apply to the Super Admin too. The Marketing Manager's publishing changed from a proposal ("Yes?") and from "Not own" to "After another's approval" (OD-56).

### 11.1 Owner decisions (Q-OW-18, 2026-10-25)

Recorded in `../decisions/DECISION_REGISTER.md` §0.17. Each decision approves the **intended policy only**.

| ID | Owner's answer | Applied as |
|---|---|---|
| OD-51 | "Enquiry owner assignment: Super Admin and Sales/CRM Manager." | `leads.assign` for the Super Admin and the Sales/CRM Executive role. **Interpretation:** OD-33 has no "Sales/CRM Manager" role. Day-to-day sales work belongs to the Sales/CRM Executive role (OD-36), so that role is meant. Automatic assignment rules stay with the Super Admin (§3.1 †; values D-09). |
| OD-52 | "Booking request approval: Super Admin and authorised Operations/Property Manager." | `bookings.approve` for the Super Admin, the Operations Manager and the Property Manager, **Property Managers only for their assigned properties**. Sales/CRM Executives, Marketing and Finance cannot approve. **Interpretation:** rejecting a request uses the same capability. "Authorised" means the role has been explicitly granted the capability (§11.2). A category's `approver_role` (ERD §4.1) may narrow approval to one of these roles, but never grants it. |
| OD-53 | "Public contact details editing: Super Admin and Marketing Manager." | `settings.contact.manage` for the Super Admin and the Marketing Manager. |
| OD-54 | "Legal/GST editing: Super Admin and Finance Manager." | `settings.legal.manage` for the Super Admin and the Finance role. **Interpretation:** OD-33's "Finance" role is the one meant. |
| OD-55 | "Legal/GST verification: A second authorised person distinct from the editor." | `settings.legal.verify`, held by the roles OD-54 authorises for legal and GST details: the Super Admin and Finance (**interpretation**). The verifier must be a different person from **everyone who edited** the version being verified, including when the editor is the Super Admin. **Derived:** any later edit clears the verification, otherwise an editor could change verified details unchecked. Until verified, invoices and legal pages cannot use the details (D-01). |
| OD-56 | "Publishing approved content: Marketing Manager may publish only after separate approval by another authorised person." | The Marketing Manager holds `content.publish` and `testimonials.publish`, but can publish (including schedule and roll back) only a version **approved by another person** who holds `content.approve`. A Marketing Manager never approves and publishes the same version. BR-CMS-2 still applies: authors never approve their own work. **Existing workflow checked:** BR-CMS-1 already required approval before publishing, and BR-CMS-2 required an approver other than the author. Nothing required an approver other than the **publisher**, and the matrix's combined "A approve/publish" allowed it. OD-56 closes that gap for the Marketing Manager. Partly answers D-17; whether one approval is enough stays open. |
| OD-57 | "Role headcount: Undecided; keep role assignments and headcount configurable." | No limit on the number of people per role. The Super Admin assigns and changes roles in the back office (`users.manage`, implemented, audited). Four-eyes rules are never relaxed when staff are few (§3.1 note). |

### 11.2 Rules every capability follows

1. **Explicit grant, never a role name.** A person can use a capability only through a role that has been explicitly granted it. Today that grant is made in code (`StaffRole::capabilities()`, synchronised by `SyncAccessControl`), so changing it is a reviewed code change. Policies and actions check the capability, plus property scope where it applies. They never check a role name. Holding the Operations Manager, Property Manager, Sales/CRM Executive, Marketing Manager or Finance role grants nothing until that role is granted the capability.
2. **Least privilege.** Grants are only those in §3.1 and the table above. Holding one capability never implies another. Holding both an edit and an approve or verify capability never allows approving or verifying one's own work.
3. **Separation of duties is checked against people, not roles**, at the moment of the action:
   * approver ≠ author for content, prices and imports;
   * verifier ≠ any editor of the legal and GST version being verified;
   * for the Marketing Manager, publisher ≠ approver of the version being published.

   One person holding two roles is still one person.
4. **No bypass of booking availability or concurrency.** Approving a request (`bookings.approve`), a staff override (`bookings.override`) and every Super Admin action confirm a booking only through the booking service. The service takes the lock first, then re-checks BR-BK-1 to BR-BK-3, BR-AV rules and booking windows (BR-BK-5, ADR-0004). There is no "force approve". A failed re-check leaves the request unconfirmed ("Could not confirm"), and the customer is offered other times.
5. **Audit.** Every action in §7, including the new entries for OD-51 to OD-56, records who acted. Role changes are already audited (§10).
6. **Headcount stays configurable** (OD-57). Code never assumes one person per role, and never lets one person satisfy a four-eyes rule alone.

### 11.3 Implementation and test gaps (not resolved by this documentation update)

**Implementation:** none of OD-51 to OD-57 is in code.

* The capabilities `leads.assign`, `bookings.approve`, `settings.contact.manage`, `settings.legal.view`, `settings.legal.manage`, `settings.legal.verify`, `content.approve`, `content.publish` and `testimonials.publish` are not in `App\Enums\Access\Capability`. No role holds them, and no policy checks them. Each is added by the module that builds the feature.
* When they are added, `StaffRole::capabilities()` gives the Super Admin every capability in the enum. This matches OD-51 to OD-56, which all include the Super Admin. The other five roles must be given their grants one by one.
* No people-level checks exist yet:
  * verifier ≠ editor, and verification cleared on edit;
  * publisher ≠ approver for the Marketing Manager;
  * approver ≠ author for real content and prices.
* The real booking-approval code (Phase 8) does not exist. The lock-first rule is proven only by the MySQL concurrency harness (R-7; booking tests T-BK-01 to T-BK-10 must pass before booking code merges).
* No audit events exist for the new §7 entries.

**Design previews out of date** (code changes, not made because this round is documentation only):

* `PermissionsReview::catalogue()` still shows the round-12 "Yes?" proposals and the Q-OW-18 questions.
* The Workspace products preview offers all six roles under "Approved by" (`approver_role`). OD-52 allows only the Super Admin, Operations Manager and Property Manager.
* The Contact and legal details preview says verification is "proposed: Finance or the Super Admin; needs owner confirmation".
* The Website content preview disables Approve and Publish for the author, but has no approver ≠ publisher case.
* Every preview is Super Admin only, so none of them shows per-role behaviour.

**Tests:** no test proves any OD-51 to OD-57 rule. Each needs policy tests per role × capability (§9), including:

* a role without the grant is refused;
* Property Manager approval only for assigned properties;
* Sales/CRM, Marketing and Finance refused for booking approval;
* the verifier cannot be an editor, including the Super Admin;
* verification is cleared by an edit;
* a Marketing Manager cannot publish a version they approved, including by scheduling or rollback;
* approval of a request whose time is taken is refused under concurrent load;
* each action writes its audit entry.

Existing tests cover only the previews:

* refusing approval of a booking request whose space is no longer free;
* Approve and Publish disabled for the author;
* the permissions review listing as implemented exactly the capabilities in code.

They do not prove production enforcement.

### 11.4 Open points for the owner (Q-OW-19)

1. **Role names:** "Sales/CRM Manager" and "Finance Manager" are recorded as the Sales/CRM Executive and Finance roles (OD-33). Confirm this, or say that a separate manager-level role is wanted, which would change OD-33.
2. **"Authorised":** recorded as "the role is granted the capability", so every holder of that role is authorised. Should only named people within a role be authorised, for example some Property Managers but not others? That needs per-person grants.
3. **Rejecting booking requests** is recorded as part of approval (same roles). Confirm.
4. **Rescheduling a confirmed booking** by Sales/CRM (who keep "modify"): does it need approval again? It always re-checks availability under lock (BR-BK-5).
5. **Contact details:** do the Marketing Manager's changes go live when saved, or does a second person check them first? The preview currently says "entered and verified".
6. **Super Admin publishing:** OD-56 names the Marketing Manager. Should the Super Admin also be unable to publish a version they approved? Recommended: yes, the same rule for every publisher.
7. **SEO redirects and brand settings:** §3.1 lets the Marketing Manager approve their own changes. These are not publishable content under BR-CMS-1, so OD-56 does not cover them. Keep this, or require a second person?

Single or double content approval remains D-17 (Phase 4 gate).
