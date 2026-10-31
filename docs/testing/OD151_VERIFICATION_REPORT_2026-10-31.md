# SmartGro — OD-151 verification of OD-150

Audit of 2026-10-31, 10:42–10:55 IST. Evidence: `evidence/phase14/2026-10-31-od151/`. The OD-150 record (`../operations/SMART_GO_SCHEMA_UPDATE_2026-10-31.md`), its evidence and its backup were read, not changed.

`smart_go` is the database of the owner's own server. **No deployed production environment exists:** hosting is undecided (D-14) and deployment is on hold (OD-79).

## 1. Executive summary

- **OD-150's database work holds up.** Live `smart_go` is byte-for-byte the state OD-150 recorded straight after its update: nothing has changed since.
  - The backup was **restored independently** in this audit. It equals the recorded pre-update state in every table.
  - The backup plus the 25 migration files reproduce live `smart_go`'s schema exactly.
  - Rolling back batch 11 returns the database exactly to the backup state.
- **Three corrections to OD-150's records.**
  - The update added **six** nullable columns to four existing tables, not "four optional fields" or "4 nullable columns".
  - The rollback instructions need credentials and should name the batch (§5.3).
  - Sample content **is not blocked by configuration on `smart_go`** (`backoffice.sample_content` = true there). Today nothing can be published there only because it has one active staff account (§3.3).
- **One significant risk.** 23 of the 25 migrations now applied to `smart_go` exist only as uncommitted files in this working tree (§5.4).
- **Sitemaps on 8080 and 8768:** both 200, `application/xml`, well-formed sitemaps.org XML, every address published and answering 200, no sample page or article, no error output. One gap, outside OD-150: the insights list and topic pages are listed even when every article on them is a sample (§2.3).
- **Solutions and CMS:** the 13 offerings, their destinations, the sample notices, noindex, sitemap exclusion, editor fields, menu editing and approval separation all match OD-150.
- **Spaces:** enabling them needs `access:sync` (14 permissions, 46 role links, nothing removed) and the space types (6 rows). Neither was run. The sequence is in §6.
- **Tests:** 266 relevant tests pass, run in this audit (§7).
- **No change** was made to `smart_go`, the preview, `.env`, packages or git.

## 2. Sitemaps (8080 site, 8768 demo)

### 2.1 Response

| Check | 8080 (`smartgro_site`) | 8768 (`smartgro_demo`) |
|---|---|---|
| Status | 200 | 200 |
| Content type | `application/xml; charset=UTF-8` | same |
| Other headers | `X-Robots-Tag: noindex, nofollow` (every response outside production), `Cache-Control: no-cache, private` | same |
| XML | Well-formed (Python's XML parser). Root `urlset` in the sitemaps.org 0.9 namespace. Each `url` has a `loc` and nothing unexpected; no duplicates; size limits respected. `xmllint` is not installed, and was not installed for this. | same |
| Error or debug text | none (no exception, SQL, HTML or PHP warning) | none |
| Addresses | 33, all on `http://127.0.0.1:8080`, each answering 200 | 39, all on `http://127.0.0.1:8768`, each answering 200 |

### 2.2 Content against the database (read-only queries)

| Check | 8080 | 8768 |
|---|---|---|
| Published pages / sample pages | 32 / 29 | 34 / 29 |
| Sample pages listed | **none** | **none** |
| Published articles / sample articles | 10 / 10 | 11 / 10 |
| Sample articles listed | **none** | **none** (the one non-sample article is listed) |
| Sample-content topic listed | no | no |
| Non-sample published pages missing from the sitemap | none | none |
| Pages carrying the sample notice, or noindex in the page itself | none | none |
| Solutions sample pages (`/workspace-solutions/…`, `/business-solutions/…`) | not listed | not listed |

The demo also lists five kit test-fixture addresses (`/test-fixture-media`, `/test-fixture-sections`, `/insights/test-fixture-insight`, `/insights/topic/test-fixture-topic`, `/customers/test-fixture-customer-story`). The demo database is a copy of the kit fixtures (OD-143); the site was cleaned of them (OD-147). This is expected, not a defect.

### 2.3 Gap: insights lists made only of sample articles

On 8080 all ten published articles are samples, yet `/insights` and three topic pages (`flexible-work`, `workplace-life`, `workspace-guides`) are listed. Those pages show only sample articles, each marked "Sample content", and the pages carry no noindex of their own.

- **Cause:** `App\Services\Website\Sitemap::insights()` counts every published article, samples included, when deciding which list pages and topic pages to add. Sample articles are dropped only from their own entries.
- **Impact today: none on these servers.** Every response outside production carries `X-Robots-Tag: noindex`. In production, sample articles are refused for publication unless `BACKOFFICE_SAMPLE_CONTENT=true` is set.
- **Minimum safe fix (not made in this audit):** in `insights()`, drop sample articles first (`$posts = $posts->reject(fn (Post $post): bool => $post->isSample())`), return when none remain, and derive the list pages and topic counts from what is left. Add a test that `/insights` and a topic page stay out of the sitemap while their only articles are samples.

## 3. Solutions and CMS

### 3.1 The 13 offerings (browser checks repeated in this audit, GET requests only)

`../design/review/2026-10-31-od150/menu-checks.mjs`, Chromium, 0 failures on 8080 and 8768 (`evidence/…/browser-checks-*.json`):

| Offering | Destination (both servers) |
|---|---|
| Dedicated Desks | `/workspaces/dedicated-desks` (workspace type "Dedicated desks", 200) |
| Flexible Desks | `/workspaces/hot-desks` ("Hot desks", 200) |
| Private Offices | `/workspaces/private-cabins` ("Private offices and cabins", 200) |
| Managed Workspaces | `/workspace-solutions/managed-workspaces` (sample page) |
| Meeting Rooms … Virtual Office (9) | `/business-solutions/{meeting-rooms, day-pass, business-address, gst-registration-support, company-registration-support, workspace-packages, cabin-day-pass, training-rooms, virtual-office}` (sample pages) |

- **Sample pages:** all 13, including the three Workspaces ones kept out of the menu, answer 200. Each carries the "Sample content" notice and `noindex, follow`, and has its seven sections in order and three questions; Book a tour appears only where a visit makes sense. None is in the sitemap.
- **Navigation:** the two categories, hover, click and keyboard switching, Escape, outside click, the panel inside the screen at 1440, 1280 and 1024 px, the phone menu at 768, 390 and 360 px, and axe all pass. No console errors.

### 3.2 Editing (read-only, in-process)

`cms-readonly.php` rendered the back office as the first active Super Admin, with a READ ONLY database session (any write fails), on `smartgro_site` and `smartgro_demo`. It ended still read-only.

- **Virtual Office page editor (200).** It shows:
  - page title, page address, Layout ("Sample content" option);
  - introduction fields, button text, "Goes to" with the new "A workspace type" option;
  - questions;
  - title and description in search results, the index switch, and "Start a new draft".
  - It holds the page's values: title, address, the "(sample)" search title and the first question.
- **Main-menu editor (200).** Label, Goes to, Short description, Icon, Workspace type, the "· sample content" marker in the page picker, and "Start a new draft"; it shows the Solutions categories and the Virtual Office description.
- **Pages list (200).** Paginated at 10 rows. The Layout filter itself is covered by `ListPagesTest`, which passed.

### 3.3 Approval separation, and sample content on `smart_go`

- **Separation in code** (`RevisionPolicy`): whoever edited a version cannot approve it; whoever approved it cannot publish it; a deactivated account can do nothing. There is no Super Admin bypass. Tests pass (§7).
- **The sample-content switch does not protect `smart_go`.** Evaluated with the owner server's own settings, `backoffice.sample_content` is **true**: `APP_ENV=local` and no `BACKOFFICE_SAMPLE_CONTENT` in `.env`. OD-150 recorded this as owner action A-24.
- **What prevents publication on `smart_go` today** (read-only checks):
  - It has **one active staff account** (user 4, Super Admin). The other three accounts are deactivated. Without a second person nothing can be approved, so nothing can be published.
  - It holds no pages, articles or topics, and only 2 revisions (the menus).
  - The sample seed script refuses any database other than the demo, site or test databases.
  - Even if published, a sample page stays noindex, out of the sitemap, and visibly marked.
- **Conclusion:** sample content cannot be published on `smart_go` *today*. That is **not guaranteed** once a second staff account exists. Setting `BACKOFFICE_SAMPLE_CONTENT=false` there (A-24) would make it so.

### 3.4 Mismatches with OD-150

1. **Columns.** OD-150 (c) and the OD-150 record (§2) say "4 nullable columns". The update added **six** nullable columns to **four** existing tables:
   - `faqs.property_id` and `faqs.product_id` (bigint, with foreign keys);
   - `navigation_items.description` (varchar 160) and `navigation_items.icon` (varchar 40);
   - `posts.topic_ids` (JSON);
   - `products.allowed_inventory_modes` (JSON).

   The register's "21 new tables" is correct.
2. **Fallback wording.** OD-150 (b) says a Workspaces item falls back to its sample page when its type has no page, and that "a rerun of the script moves the link". That holds after a rerun. Between reruns, a menu link to a type that has lost its page behaves like any workspace-type link (OD-145):
   - it goes to the enquiry form with the type chosen, tagged "Ask us";
   - or it is hidden while the form is closed.

   The "See locations" buttons and cards on the sample pages are hidden in the same case.
3. **Live type buttons.** Business Solutions sample pages link to their type page only where the type had a page when the script ran; today only Meeting Rooms does. Training Rooms and Virtual Office gain the button only on a rerun after their types have published offerings. OD-150 (b) says "while it exists"; its last sentence covers the rerun.

## 4. Migrations (Workstream 1)

### 4.1 The 25 applied (`smart_go`, batch 11, migration ids 45–69, 09:40:57–09:40:59 IST)

| Id | Migration | Effect on `smart_go` (verified read-only in this audit) | Depends on |
|---|---|---|---|
| 45 | `2026_10_27_185025_create_twin_models_table` | table `twin_models` (0 rows) | properties, users |
| 46 | `2026_10_27_185026_create_twin_anchors_table` | table `twin_anchors` (0) | twin_models (cascade) |
| 47 | `2026_10_28_101824_create_post_topics_table` | table `post_topics` (0) | — |
| 48 | `2026_10_28_101825_add_topic_ids_to_posts_table` | column `posts.topic_ids` JSON NULL | posts |
| 49 | `2026_10_28_123721_create_security_settings_table` | table `security_settings`, **1 row**: id 1, `two_step_login_required` 0, created 2026-10-31 04:10:57 UTC (09:40:57 IST) | — |
| 50 | `2026_10_28_133128_add_scope_to_faqs_table` | `faqs.property_id`, `faqs.product_id` bigint NULL, foreign keys (restrict) | properties, products |
| 51 | `2026_10_28_173942_create_space_types_table` | table `space_types` (0) | — |
| 52 | `2026_10_28_173943_create_floors_table` | `floors` (0) | properties, buildings |
| 53 | `2026_10_28_173945_create_spaces_table` | `spaces` (0) | properties, floors, space_types |
| 54 | `2026_10_28_173946_create_space_seats_table` | `space_seats` (0) | spaces |
| 55 | `2026_10_28_173948_create_offering_space_table` | `offering_space` (0) | offerings, spaces (cascade) |
| 56 | `2026_10_28_173949_create_space_allocations_table` | `space_allocations` (0) | spaces, space_seats, users |
| 57 | `2026_10_28_173951_add_allowed_inventory_modes_to_products_table` | `products.allowed_inventory_modes` JSON NULL, **filled for the 7 categories**: private_office, meeting_room, conference_room, managed_office `["exclusive"]`; dedicated_desk `["per_seat","hybrid"]`; hot_desk `["pooled","per_seat","hybrid"]`; virtual_office `[]`. `updated_at` untouched (the update bypasses the model). | products |
| 58 | `2026_10_28_193821_create_price_plans_table` | `price_plans` (0) | offerings, spaces, users |
| 59 | `2026_10_28_193822_create_offers_table` | `offers` (0) | users |
| 60 | `2026_10_28_193823_create_offer_offering_table` | `offer_offering` (0) | offers (cascade), offerings |
| 61 | `2026_10_28_193825_create_inventory_imports_table` | `inventory_imports` (0) | users |
| 62 | `2026_10_28_193826_create_inventory_import_issues_table` | `inventory_import_issues` (0) | inventory_imports (cascade) |
| 63 | `2026_10_28_195325_create_amenities_table` | `amenities` (0) | — |
| 64 | `2026_10_28_195327_create_amenity_space_table` | `amenity_space` (0) | amenities, spaces (cascade) |
| 65 | `2026_10_29_033934_create_tour_availability_rules_table` | `tour_availability_rules` (0) | properties, users |
| 66 | `2026_10_29_033935_create_property_closures_table` | `property_closures` (0) | properties, users |
| 67 | `2026_10_29_033937_create_tour_requests_table` | `tour_requests` (0) | leads, properties, users |
| 68 | `2026_10_29_072209_create_missing_pages_table` | `missing_pages` (0) | — |
| 69 | `2026_10_30_201616_add_details_to_navigation_items_table` | `navigation_items.description` varchar(160) NULL, `icon` varchar(40) NULL | navigation_items |

**Totals:** 21 new tables, 6 new nullable columns on 4 tables, and 43 new foreign keys (51 before, 94 after). Every foreign key references a table that existed before or was created earlier in the run. All 21 new tables are empty except `security_settings`.

**Data writes, exactly two:**
- id 49 inserts one row into its own new table;
- id 57 fills only the column it adds.

The 43 original tables' data, over their earlier columns, is unchanged (fingerprint).

### 4.2 Running a migration again

No migration is unsafe to run again in the ordinary way:
- Laravel records each in `migrations` and does not repeat it.
- Forced again (for example `--path` after deleting its row), each fails at once ("table exists" or "duplicate column") before changing data.

The risk is in **rolling back and then re-running**, which is lossy once the new structures hold data:

| Id | Lost on rollback |
|---|---|
| 49 | A Super Admin's "two-step login required" choice reverts to optional. This only matters locally: outside local and testing it is always required. |
| 50 | A location- or type-specific FAQ becomes general and appears on `/faqs`. |
| 57 | Staff edits to inventory modes revert to the defaults. |
| 69 | Menu descriptions and icons. |
| every create-table migration | Its table's rows. |

## 5. Backup and rollback

### 5.1 Recorded by OD-150, independently re-checked here

| Item | Recorded in OD-150 | Verified in this audit |
|---|---|---|
| File | `~/smartgro-backups/smart_go-2026-10-31-before-od150.sql` | exists, mode 600, directory mode 700, 327,261 bytes, modified 09:34:42 IST |
| SHA-256 | `91cd4423…0fefb` | `sha256sum -c` **OK**; the evidence copy is identical |
| Content | 43 tables, 21 INSERT | 43 `CREATE TABLE`, 43 `DROP TABLE IF EXISTS`, 21 `INSERT` (one per non-empty table), "Dump completed" line, no `CREATE DATABASE` |
| Restorable | restored into `smartgro_restore_check` (since dropped) | **Restored again in this audit** into the emptied `smartgro_testing`, after confirming the connection. Equal to OD-150's recorded pre-update fingerprint in every table (rows, row hashes, columns, indexes, foreign keys). Against live `smart_go`, only `migrations` differs (the 25 rows). |
| Migration reproducible | rehearsal on the copy | On that restored copy, `migrate --force` produced **the same 64 tables, columns, indexes and foreign keys as live `smart_go`**. Data is identical except the `security_settings` row's timestamps. |
| Rollback | described, not run | **`migrate:rollback --batch=11` run on the copy:** 25 DONE, exit 0, 43 tables left, **identical to the backup state** in every table |

The copy, including its staff accounts, was removed by the test run that followed: `smartgro_testing` now holds a fresh schema and no users. The temporary credentials file was shredded.

### 5.2 Are the instructions sufficient?

They are correct in substance and were proven on the copy. Before relying on them:

1. **Credentials.** The OD-150 commands (`mysql -e …`, `mysql smart_go < …`) assume credentials that are not configured: there is no `~/.my.cnf`, and the account is `root` with a password. Use `mysql -u root -p` or a private option file (`--defaults-extra-file`).
2. **Prefer `--batch=11`.** `migrate:rollback --step=25` undoes the last 25 migrations by order, so it would also undo any later batch. `--batch=11` undoes exactly OD-150's set; both verified.
3. **Stop the owner's server first** (already in OD-150 §7), and remember that restoring the backup discards everything written since 09:34:42, including sessions.
4. **The backup is a single copy on the same disk** as MySQL. An off-machine copy would protect against disk loss (A-27).

### 5.3 Corrected restore procedure (supplements OD-150 §7 without replacing it)

```
sha256sum -c ~/smartgro-backups/smart_go-2026-10-31-before-od150.sql.sha256
mysql -u root -p -e "DROP DATABASE smart_go; CREATE DATABASE smart_go CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql -u root -p smart_go < ~/smartgro-backups/smart_go-2026-10-31-before-od150.sql
```

Or, keeping other data: `php artisan migrate:rollback --batch=11 --force`, with `DB_DATABASE=smart_go` confirmed first.

### 5.4 Uncommitted work and the migration files

- **The applied migration files are mostly uncommitted.** HEAD is still `8a45ae6`. Of the 25 migrations now applied to `smart_go`, only `twin_models` and `twin_anchors` are committed; **23 are untracked files**. None was modified after it was applied (all modified before 09:40:57). If the working tree were lost or reset, `smart_go` would hold 23 migrations with no source: no rollback, and a fresh database could not be rebuilt to match. **Owner action A-26.**
- **The working tree overall:** 698 changed paths (untracked folders expanded).
  - **648 last modified before OD-150 began (09:17):** OD-121 to OD-149 and earlier phases (Oct 28: 137, Oct 29: 260, Oct 30: 219, Oct 31 before 09:17: 32). They are unrelated to OD-150. This includes the deleted `public/robots.txt`, replaced by a route in OD-130.
  - **49 modified during OD-150:** 17 code, test and record files, plus 32 evidence files. Some of the 17 also carry earlier, uncommitted changes from OD-149 and before (for example `NavigationMenuForm.php`, and `Sitemap.php`, which is untracked).
  - **None modified after OD-150.**

## 6. Spaces readiness (inspection only: nothing run, nothing imported)

### 6.1 What the screens need

- **Visibility.** Properties → Space types and Spaces are shown while all eight inventory tables exist (`Space::isAvailable()`), now true on `smart_go`, and to anyone with `properties.view`. They already open on `smart_go`, empty.

| Action | Needs | On `smart_go`? |
|---|---|---|
| Create or edit a space type | `products.manage` | present |
| Create a floor | `properties.manage` | present |
| Create or edit a space | `inventory.manage` | **missing** |

- **Required reference records.**
  - **Space types:** a space cannot exist without one (`spaces.space_type_id` is required).
  - **Floors:** a space cannot exist without one (`spaces.floor_id` is required). Floors are never seeded; they come from SmartGro's facts (OD-30), entered by staff.

### 6.2 `php artisan access:sync` on `smart_go` (computed read-only; not run)

- **What it does** (`SyncAccessControl`, one transaction): creates every missing permission and role (`findOrCreate`), then replaces each role's permission list with the code's (`syncPermissions`), auditing each changed role. It then clears the permission cache (`forgetCachedPermissions`).
- **Exact effect today:**
  - `permissions`: **+14 rows** (`inventory.manage`, `allocations.view`, `allocations.manage`, `inventory.import`, `inventory.import.approve`, `pricing.view`, `pricing.manage`, `pricing.approve`, `offers.manage`, `leads.reports.view`, `tours.view`, `tours.manage`, `twin_models.view`, `twin_models.manage`).
  - `role_has_permissions`: **+46 rows, none removed**:

    | Role | Permissions now → after |
    |---|---|
    | Super Admin | 34 → 48 |
    | Operations Manager | 5 → 14 |
    | Property Manager | 7 → 15 |
    | Sales/CRM Executive | 6 → 11 |
    | Marketing Manager | 22 → 27 |
    | Finance | 5 → 10 |

  - `activity_log`: **+6** `role_capabilities_changed` entries.
  - `roles`: none created.
  - `cache`: the `spatie.permission.cache` row is deleted. Run it with the application's own cache store (database), or the owner's server keeps the old permissions until that row expires.
  - `users` and `model_has_roles`: unchanged.
  - Only one active account (the Owner, Super Admin) would gain anything today.
- **Idempotent:** yes. A second run finds nothing to change and writes no audit entry; only the cache clear repeats. Covered by `SyncAccessControlTest` ("can run repeatedly…", "writes no audit entry when the roles already match the code"), which passed.

### 6.3 Reference data (computed read-only; not run)

- **Command:** `php artisan db:seed --class=ReferenceDataSeeder --force`. It uses `firstOrCreate` throughout: it creates missing records and **never updates existing ones**.
- **Effect on `smart_go` today:** **+6 `space_types` rows**:

  | Key | Name |
  |---|---|
  | `private_cabin` | Private cabin or office |
  | `desk_zone_dedicated` | Dedicated desk zone |
  | `desk_zone_flexible` | Flexible seating zone |
  | `meeting_room` | Meeting room |
  | `conference_training_room` | Conference or training room |
  | `office_suite` | Office suite or floor plate |

  Plus **+6 `activity_log` rows** (the space-type model is audited, log `inventory`). The 5 cities, 7 categories, 8 segments, 3 areas, 4 locations, their primary buildings and their profiles already exist, so nothing else changes.
- **Idempotent:** yes; `ReferenceDataSeederTest` ("…can run again") passed.
- **Never run a plain `php artisan db:seed` (or `migrate --seed`) on `smart_go`.** `DatabaseSeeder` runs `access:sync` and creates a factory user "Test User" (`test@example.com`).

### 6.4 Can it be tested on `smartgro_testing`?

Yes. The test suite already runs both on it (every test that calls `SyncAccessControl`, and `ReferenceDataSeederTest`). An exact rehearsal of `smart_go`'s change, when authorised, would go:
1. Make a fresh read-only dump of `smart_go`.
2. Restore it into an emptied `smartgro_testing` after confirming the connection.
3. Run the two commands, and compare fingerprints with the figures above.
4. Run them again to show nothing changes.
5. Let the next test run wipe the copy.

That rehearsal was not run in this audit (instruction: inspection only).

### 6.5 Minimum safe sequence (no deployment)

1. **Owner decisions:** A-22 (`access:sync`) and A-23 (space types). Whether real spaces may be entered is the **Phase 5 entry gate** (OD-30, OD-128): the first property's real data goes through SG-INV-1.3 first.
2. **Rehearse** on `smartgro_testing` as in §6.4 and record the result.
3. **On `smart_go`, with the owner's server stopped:**
   1. Make a fresh backup and check it (restore test).
   2. Run `php artisan access:sync`, with `DB_DATABASE=smart_go` confirmed and the default cache store.
   3. Run `php artisan db:seed --class=ReferenceDataSeeder --force`.
   4. Fingerprint, and compare with the rehearsal.
   5. Run the read-only smoke test (`../testing/evidence/phase14/2026-10-31-od150-smart-go/http-smoke.php`).
4. Staff then add floors (properties.manage) and spaces (inventory.manage) **only from SmartGro's confirmed facts**.

## 7. Tests and checks run in this audit

| Command | Result |
|---|---|
| `vendor/bin/pest --compact` on SearchEngineTest, PageControllerTest, PublicShellTest, WorkspaceControllerTest, PagePayloadTest, NavigationPayloadTest, NavigationWorkflowTest, RevisionWorkflowTest, ContentTypesWorkflowTest, Website/NavigationMenus, Website/Pages, Website/PostTopics, Actions/Access, Feature/Database, InventoryScreensTest, InventoryActionsTest, SpaceAvailabilityTest, Arch. Run with config, route and event caches bypassed, on `smartgro_testing` (forced by `phpunit.xml`; `TestCase` refuses any database not ending in `_testing`). | **266 passed, 1,414 assertions, exit 0** (10:50:46–10:53:03) |
| `node …/menu-checks.mjs http://127.0.0.1:8080` and `…8768` | exit 0, 0 failures each |
| `python3 -I sitemap_audit.py 8080 smartgro_site` and `8768 smartgro_demo` | 0 problems each |
| `php cms-readonly.php smartgro_site` and `smartgro_demo` (READ ONLY session) | page editor, menu editor and pages list 200, the expected fields present |
| `php access-delta.php` (read-only `smart_go`) | §6.2 figures |
| `fingerprint.php` on `smart_go` at the start (10:42) and end (10:53) | identical to OD-150's recorded after-state |
| Restore, migrate and `migrate:rollback --batch=11` on `smartgro_testing` | §5.1 |
| `smartgro_preview` (read-only) | 68 migrations, batch 6, last write 2026-10-30 15:53 (unchanged) |

Not run in this audit: the full suite. OD-150 recorded 1,311 passing tests; no code has changed since. Nothing was requested on port 8001.

## 8. Risks and blockers

| Risk | Severity | Resolution |
|---|---|---|
| 23 applied migration files exist only in the working tree | **High** | commit, or authorise a commit (A-26) |
| Sample content not blocked by configuration on `smart_go` | Medium (latent while there is one active account) | A-24 |
| Single on-disk copy of each backup | Medium | off-machine copy (A-27) |
| Rollback commands lack credentials; `--step` imprecise | Low | §5.3 |
| Insights lists of only sample articles in the sitemap | Low (noindex outside production; sample articles refused in production by default) | code fix §2.3 |
| Spaces screens open, empty, with no `inventory.manage` | Low (expected) | §6.5 |
| A plain `db:seed` would run `access:sync` and create a test user | Medium if mistyped | always use `--class=ReferenceDataSeeder` |

## 9. Proposed next steps (no deployment)

1. **Secure the source:** commit the working tree, or let the owner commit it (A-26). At the least, keep a copy of the 23 untracked migration files outside the project.
2. **Copy the backups off this machine** (A-27).
3. **Decide A-24:** add `BACKOFFICE_SAMPLE_CONTENT=false` to `smart_go`'s `.env` so its server refuses sample content.
4. **Fix the sitemap gap (§2.3)** with its test, on the test database only.
5. **Spaces:** decide A-22 and A-23, rehearse on `smartgro_testing` (§6.4), then run on `smart_go` (§6.5); real floors and spaces only after the Phase 5 entry gate.
6. **Offerings content (A-25):** real wording, the Flexible Desks ↔ Hot desks naming, and the Business Solutions type pages.
7. **Phase acceptance** stays with the owner. No status changes here.
