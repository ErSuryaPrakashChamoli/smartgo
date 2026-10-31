# SmartGro — OD-153: Spaces completion, security verification and end-to-end checks

Round of 2026-10-31, 12:16–14:25 IST. Evidence: `evidence/phase14/2026-10-31-od153/` (rehearsal, roles, security, tests, browser checks) and `evidence/phase6/2026-10-31-od153/` (repository kit). The brief arrived cut off in its section 6 ("Empty-state"). Sections 1–5 were carried out in full and section 6 as far as it arrived.

`smart_go` is the database of the owner's own server, not a deployed production environment (none exists). Nothing was run against it in this round except read-only checks.

## 1. Summary

- **Spaces: one documented rule was missing, and is now built.**
  - BR-PRODCFG-2 and the approved ERD (§4.1, ADR-0013) say a space is sold as a category only if the category accepts both its **space type** and its inventory mode. Only the mode was checked.
  - Added: the `product_space_type` list, kept on each workspace category's form and audited. It is enforced on every save and bulk add, and in the inventory import (V-117).
  - The reference seeder proposes the documented defaults.
  - Without the new table (`smart_go`, the demo and the site today), behaviour is exactly as before.
- **Reference data and `access:sync` rehearsed** on an exact replica of `smart_go` in `smartgro_testing`. OD-151's figures are confirmed. Both operations are idempotent and have no other effect. Representative roles get exactly the intended screens.
- **Security:**
  - One gap fixed: a deactivated account still passed the property-scope checks that inventory actions call directly.
  - `.env` narrowed from 664 to 600.
  - No secret was found in the git history, the working tree or the logs.
  - Sample content is refused for everyone, the Super Admin included, where the switch is off. A test now covers this.
- **Checks:**
  - Full suite **1,318 passed (6,179 assertions)**.
  - Repository kit: public, enquiry, tour and back office all exit 0.
  - Solutions menu checks and sitemap audits pass on 8080 and 8768.
- **Incidents (not caused by this work, but recorded):**
  - One guest session row was written to `smart_go` at 12:26:45 by a browser request to a server on that database. No such server is running now.
  - The demo and site servers stopped when the session restarted at about 14:12; both were restarted in their own systemd scopes.
- **Nothing committed.** HEAD is still `ee9083c`. No push, deployment or package change.

## 2. State at the start, and inconsistencies found

| Item | Found | Action |
|---|---|---|
| Git | HEAD `ee9083c` (OD-152), 314 uncommitted paths, empty index | preserved |
| `smart_go` | equal to OD-150's recorded after-state (12:16) | read-only only |
| Boost | this project's MCP server uses `CACHE_STORE=array` | unchanged |
| BR-PRODCFG-2 and ERD §4.1 `product_space_type` | documented and approved, **not implemented** (mode-only check) | built (§3) |
| INVENTORY_IMPORT_SPEC V-117 | mode only | now also the space type, where the list exists |
| "A deactivated account can do nothing" (policies) | `User::hasCapabilityForProperty/EveryProperty` ignored `is_active`; `SaveSpace` and `SaveFloor` rely on them | fixed (§5) |
| LOCAL_DEVELOPMENT restart command (OD-152) | `setsid nohup` servers died with the session | changed to systemd user scopes |
| OD-150's "4 nullable columns" | six columns (corrected in OD-151 (a)) | — |

## 3. Spaces and space types

### 3.1 What already existed (OD-127, OD-128)

- **Back office:** Properties → Space types, Spaces, and each property's Floors and Spaces.
- **Actions:**
  - `SaveSpace`: property-scoped `inventory.manage`; the floor must belong to the property; the type must allow the mode; categories must be the property's active offerings whose category allows the mode; permanent codes; row locks; capacity protection; audit.
  - `AddSpaces` (runs of spaces), `ChangeSpaceStatus`, `RemoveSpace`, `SaveFloor`, and allocations.
  - The SG-INV-1.3 import (validator V-001 to V-180, two-person approval).
- **Empty states** already explained themselves for spaces and floors.
- **Public website:** no space is shown publicly. Category pages come from published offerings.

### 3.2 Built in this round

| Part | Change |
|---|---|
| Schema | migration `2026_10_31_064952_create_product_space_type_table` (`product_id`, `space_type_id`, timestamps, both foreign keys cascade). Test database only; ERD C-38. |
| Models | `Product::spaceTypes()`, `SpaceType::products()`, `Product::hasSpaceTypes()` (requires the inventory tables **and** the list; once per request), `Product::acceptsSpaceType()` (always true where the list does not exist, so the old behaviour holds) |
| Rule on save | `SaveSpace` refuses a category that does not accept the space's type: "A meeting room cannot be sold as Private offices and cabins: that category does not accept this space type (BR-PRODCFG-2)". This covers the space editor, the property's Spaces tab and `AddSpaces`. |
| Rule on import | `WorkbookValidator` V-117 refuses the same, row by row, after the mode check |
| Management | each workspace category's form: **"Space types it can be sold with"**, saved through `SetCategorySpaceTypes`: needs `products.manage`; validated; refuses removing a type while a space of that type is sold as the category (it lists the codes); audited as `space_types_changed` (log `properties`, old and new keys) |
| Space types list | new column "Can be sold as" where the list exists; empty state "No space types yet" with what to do |
| Reference data | `ReferenceDataSeeder` proposes the mapping below, only for a type no category accepts yet, so staff choices are never undone |

**Proposed mapping**: proposed defaults, editable on each category.

| Space type | Can be sold as | Source |
|---|---|---|
| Private cabin or office | Private offices and cabins; Managed offices | ERD §3.1 |
| Dedicated desk zone | Dedicated desks | ERD §3.1 |
| Flexible seating zone | Hot desks; **Dedicated desks** | ERD §3.1, plus the approved SG-INV-1.3 synthetic workbook (D-35, row 16): a hybrid zone's assigned seats (OD-15) |
| Meeting room | Meeting rooms | ERD §3.1 |
| Conference or training room | Conference and training rooms; **Meeting rooms** | ERD §3.1 says "if the owner allows"; the approved synthetic workbook (row 11) uses it. **Owner decision A-28.** |
| Office suite or floor plate | Managed offices; Private offices and cabins | ERD §3.1 |
| (none) | Virtual office | BR-PRODCFG-3: never on a space |

With only the ERD's narrower mapping, both approved synthetic workbooks failed validation (2 rows) and seven existing tests failed. The two combinations were already allowed before this round (mode only), so removing them would have been a new restriction that nobody approved.

### 3.3 Requirements of brief §3

| # | Requirement | Status |
|---|---|---|
| 1 | Space types managed in the back office | existing screens (create, edit, delete when unused; `products.manage`), plus the new column and empty state |
| 2 | Spaces belong to the property structure | existing: floor of the same property (server rule), property-scoped codes and actions |
| 3–4 | Approved type-and-category mappings, enforced server-side | **built**: save, bulk add, import |
| 5 | No access outside a user's scope | existing property scope; deactivated accounts now refused even by direct action calls (§5) |
| 6 | Create, edit and status changes follow the authorisation model | existing; per-role matrix verified (§4.3) |
| 7 | Sensitive changes audited | existing inventory audit; new `space_types_changed` |
| 8 | Empty, error, loading and unauthorised states | Space types empty state added; validation messages on the fields; 403 for unauthorised screens; Filament loading states (kit back office: no failures) |
| 9 | Public pages keep working | full suite, kit public, Solutions checks, sitemaps (§6) |
| 10 | No invented facts | no floors, spaces, capacities or prices created anywhere outside the test database; the mapping holds catalogue reference values from documents |

## 4. Reference data and `access:sync` rehearsal (`smartgro_testing` only)

### 4.1 Inspection

- **`db:seed --class=ReferenceDataSeeder`:**
  - Uses `firstOrCreate` throughout: it creates what is missing and never updates or deletes.
  - The space-type mapping is attached only where a type has no category, and only where the list exists.
  - No wrapping transaction. Each statement stands alone, and a rerun completes what is missing.
  - Space types write audit entries (`inventory` log); the mapping does not.
- **`php artisan access:sync`** (`SyncAccessControl`):
  - One transaction: `findOrCreate` on every capability and role, then `syncPermissions` per role whose list differs. That can **remove** a permission a role no longer has in code.
  - One audit entry (`access` / `role_capabilities_changed`) per changed role.
  - Then `forgetCachedPermissions()` deletes the permission cache entry in the configured cache store. Run it with the application's own store (database), or the owner's server keeps the old permissions until the entry expires.
  - Users and their roles are untouched.
- **Hidden side effect:** a plain `php artisan db:seed` (or `migrate --seed`) runs `DatabaseSeeder`, which runs `access:sync` **and** creates the factory user "Test User" (test@example.com). Never run it on `smart_go`. This rehearsal used only `--class=ReferenceDataSeeder`.

### 4.2 Results (fingerprint of every table before and after each step; `rehearsal-deltas.json`)

Replica: the verified OD-150 backup plus the 25 OD-150 migrations, built in the emptied `smartgro_testing` after confirming `SELECT DATABASE()`. It equals live `smart_go` in every table except `security_settings` (its own timestamps) and the 12:26 session row.

| Step | Effect (all other tables unchanged) |
|---|---|
| Reference data, run 1 | `space_types` 0 → **6**; `activity_log` +6 (`inventory` / `created`) |
| Reference data, run 2 | **no change** |
| `access:sync`, run 1 (database cache) | `permissions` 34 → **48** (+14: inventory.manage, allocations.view, allocations.manage, inventory.import, inventory.import.approve, pricing.view, pricing.manage, pricing.approve, offers.manage, leads.reports.view, tours.view, tours.manage, twin_models.view, twin_models.manage); `role_has_permissions` 79 → **125** (+46; nothing removed); `activity_log` +6; `cache` −1 (the permission cache entry) |
| `access:sync`, run 2 | **no change** |
| OD-153 migration | new table `product_space_type`; `migrations` +1 |
| Reference data with the list, run 1 | `product_space_type` 0 → **10** (the mapping above) |
| Reference data with the list, run 2 | **no change** |

Roles after the sync: Super Admin 48, Operations Manager 14, Property Manager 15, Sales/CRM Executive 11, Marketing Manager 27, Finance 10. **OD-151's figures are confirmed.**

### 4.3 Representative role access (temporary test users; page views in a READ ONLY session, `role-access.json`)

| Role | Spaces | Space types | New space type | Categories | Edit a category | 3D models | Price plans | Offers | Inventory imports | Tours | Enquiries report | Pages (CMS) |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Super Admin | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 |
| Operations Manager | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 200 | 403 |
| Property Manager | 200 | 200 | 403 | 200 | 403 | 403 | 200 | 200 | 200 | 200 | 200 | 403 |
| Sales/CRM Executive | 200 | 200 | 403 | 200 | 403 | 403 | 200 | 200 | 403 | 200 | 200 | 403 |
| Marketing Manager | 200 | 200 | 403 | 200 | 403 | 200 | 200 | 200 | 403 | 403 | 200 | 200 |
| Finance | 200 | 200 | 403 | 200 | 403 | 403 | 200 | 200 | 403 | 403 | 403 | 403 |
| Super Admin, **deactivated** | 403 | 403 | 403 | 403 | 403 | 403 | 403 | 403 | 403 | 403 | 403 | 403 |

There were no server errors. Adding spaces at A-70 is allowed only for the Super Admin and Operations Manager; an unassigned Property Manager is refused. In this first run a deactivated account still passed the property-scope helper, which is the gap fixed in §5.

## 5. Security verification

| Area | Result |
|---|---|
| Policies and direct URLs | Server-side: every screen above answers 403 to roles without the capability; Filament re-checks panel access on every request |
| Property and inventory isolation | Property scope on every inventory action; Property Manager limited to assigned properties (existing tests pass) |
| **Deactivated accounts (fixed)** | `User::hasCapabilityForProperty()` and `hasCapabilityForEveryProperty()` now require `is_active`, so actions that check them directly (`SaveSpace`, `SaveFloor` and other property-scoped actions) refuse a deactivated account even if called outside the panel. Narrows access only. Test with both halves mutation-checked. |
| Mass assignment and validation | `Space` is written by `forceFill` in actions only; `SpaceType` and `Product` list `$fillable` for their forms; the new list is kept out of the model update and validated in its action (`exists`, `distinct`) |
| Audit | inventory changes (existing), `space_types_changed` (new), `role_capabilities_changed` (sync) |
| Sample content | Refused at submission, scheduling and publication wherever the switch is off, **for the Super Admin too** (test extended); the owner's server's switch is `false` (OD-152); approval separation unchanged |
| Permission cache | `access:sync` deletes the cache entry (verified on the replica: `cache` −1); role-permission changes happen only through it |
| `.env` | was **664** (group and others could read). Every process using this project runs as `administrator`, the owner; the group has no other member; no web server, and the cron and supervisor jobs belong to another project. **Changed to 600**; the demo, site and artisan still read it (200, settings resolve). Credentials not changed. |
| Secrets in git | none: `APP_KEY` and `DB_PASSWORD` values in no file of the 57 commits or of the working tree; `.env` never committed and ignored; no generic secret patterns |
| Logs | no secret value and no password field in the application, demo, site or preview logs. Logs are mode 664 (readable by other local users) and the test suite writes its expected errors to the shared `storage/logs/laravel.log`: low risk, left as found |
| Debug output | `APP_DEBUG=true` for the owner's server (local, 127.0.0.1); the deployment runbook already requires `false` in production |

## 6. End-to-end checks

| Check | Result |
|---|---|
| Full suite, six parts (`smartgro_testing`) | Http 280 (1,690 assertions); Services and Views 168 (551); Filament 240 (1,603); Actions 360 (1,490); Console to Arch 241 (748); Infrastructure 29 (97): **1,318 passed, 6,179 assertions** |
| New tests | type rule on save, plus who may change a category; category form (audited, refuses removing a type in use); import V-117 type check; seeder defaults that never undo a staff choice; database without the list (behaviour as before, no query to it); deactivated accounts; sample refusal for the Super Admin. The rules were mutation-checked. |
| Kit (`evidence/phase6/2026-10-31-od153/`) | public exit 0, no failures; enquiry 0; tour 0; back office 0 (rerun after the session restart killed the first attempt, which recorded "interrupted") |
| Solutions (`menu-checks.mjs`) on 8080 and 8768 | 0 failures: 13 offerings and destinations, notices, noindex, sitemap exclusion, keyboard, touch and responsive sizes, home hero image and label, Book a tour |
| Sitemaps | 8080: 29 addresses; 8768: 36; 0 problems (well-formed, all 200, no sample page, article or sample-only insights list) |

## 7. `smart_go`, the preview and the servers

- **`smart_go`:** fingerprinted read-only at 12:16, 13:0x and the end of the round. The only change since OD-150 is **one guest session row (12:26:45 IST, `127.0.0.1`, a desktop browser)**, written by a server on `smart_go` that was not running at any check. It was not written by this work. Still 69 migrations, 34 permissions, no space types and no `product_space_type`.
- **Preview:** unchanged (68 migrations, batch 6, last write 2026-10-30 15:53).
- **Demo and site:** stopped by the session restart (last requests 13:51 and 13:19); restarted at 14:16 with their recorded environments and `BACKOFFICE_SAMPLE_CONTENT=true`, now in systemd user scopes `smartgro-8768` and `smartgro-8080`. Their databases were not migrated in this round, so they keep the mode-only rule.

## 8. Remaining work, risks and owner decisions

1. **A-28 (new):** may conference and training rooms also be sold as meeting rooms? The default says yes, as the approved synthetic workbook does. To refuse it, untick "Conference or training room" on the Meeting rooms category.
2. **Bringing Spaces to `smart_go`** needs separate authorisation (A-22, A-23, and now the OD-153 migration). The rehearsed sequence, with the owner's server stopped:
   1. Make a fresh backup and check it.
   2. Run `php artisan migrate --force --path=database/migrations/2026_10_31_064952_create_product_space_type_table.php`.
   3. Run `php artisan db:seed --class=ReferenceDataSeeder --force` (never plain `db:seed`).
   4. Run `php artisan access:sync` with the application's own cache store.
   5. Fingerprint against this rehearsal, then run the read-only smoke test.

   Real floors and spaces still wait for the Phase 5 entry gate (OD-30, OD-128).
3. **The demo and site** can be given the new table the same way (`--path`), if wanted; until then they keep the mode-only rule.
4. **A-27:** the off-machine backup copy is still blocked (no second destination).
5. **Low:** log files are readable by other local users; `APP_DEBUG=true` locally; the demo and site servers do not survive a machine restart.
6. **Commit:** this round's work is uncommitted (§9). The migration file in particular should be committed before it is ever applied to `smart_go`, as OD-152 did for the others.

## 9. Files changed in this round (all uncommitted)

- **New:**
  - `database/migrations/2026_10_31_064952_create_product_space_type_table.php`
  - `app/Actions/Properties/SetCategorySpaceTypes.php`
  - this report
  - `evidence/phase14/2026-10-31-od153/` and `evidence/phase6/2026-10-31-od153/`
- **Changed (code):**
  - `app/Models/Product.php`, `app/Models/SpaceType.php`, `app/Models/User.php`
  - `app/Actions/Inventory/SaveSpace.php`
  - `app/Services/Inventory/Import/WorkbookValidator.php`
  - `app/Filament/Clusters/Properties/Resources/Products/ProductResource.php` and `Pages/CreateProduct.php`, `Pages/EditProduct.php`
  - `app/Filament/Clusters/Properties/Resources/SpaceTypes/SpaceTypeResource.php`
  - `database/seeders/ReferenceDataSeeder.php`
- **Changed (tests):** `InventoryActionsTest`, `InventoryScreensTest`, `InventoryWorkbookTest`, `ReferenceDataSeederTest`, `RevisionWorkflowTest`.
- **Changed (documentation):**
  - `docs/architecture/DATABASE_ERD.md` (v1.24, C-38)
  - `docs/requirements/INVENTORY_IMPORT_SPEC.md` (V-117)
  - `docs/operations/LOCAL_DEVELOPMENT.md` (server start)
  - the decision register and the status log
- **Not in git:** `.env`, mode only (664 → 600; contents unchanged).

## 10. Section 6 completed: empty states (2026-10-31, 14:30–14:55)

The owner declined a clarifying question and said "continue". This was read as finishing the brief's cut-off section 6 ("Empty-state …") without anything that needs authorisation: no commit, and no write to `smart_go`.

- **Where:** a replica of the owner's real state in `smartgro_testing` (the OD-150 backup plus the 25 migrations: reference data, nothing published, 34 permissions), served on 8767 with the owner's `.env` settings and isolated storage. One temporary Super Admin was used to sign in. The server was then stopped, the password file shredded, and the replica wiped by the next test run.
- **Sweep** (`evidence/phase14/2026-10-31-od153/empty-state/`): all 55 back-office screens without parameters at 1440 and 390 px, and the public pages at 1440, 768 and 390 px.
  - No server error, no horizontal overflow, no axe violation, and every internal link answers.
  - The only console errors are the expected 403s for the six screens whose permissions `smart_go` lacks (A-22), and the deliberate `/no-such-page`.
  - All 38 links in the back-office navigation open (200): the forbidden screens are hidden from the menu.
- **Fixed:** seven content lists showed Filament's bare default ("No pages", "No articles", …) on the owner's server, where they are empty. Pages, Insights, FAQs, Customer stories, Testimonials, Media library and Redirects now say what to do next. The copy states only what the CMS already enforces: a second person's approval, consent on file for stories and testimonials, the media library's required fields, and automatic redirects. Copy only; the sweep rerun shows the new states at both widths with no violation, and the Website back-office tests pass (88, 664 assertions).
