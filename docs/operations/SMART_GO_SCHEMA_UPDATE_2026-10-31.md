# SmartGro — `smart_go` schema update, 2026-10-31 (OD-150)

Record of the owner-authorised database update (register OD-150, decision 4). Evidence: `../testing/evidence/phase14/2026-10-31-od150-smart-go/`.

## 1. Target

| Check | Result (verified) |
|---|---|
| Deployed production | **None exists.** Hosting is open (D-14) and deployment is deferred until local completion (OD-79). |
| Database the authorisation covers | `smart_go`, on the local MySQL server. It is the only database that had the 25 pending migrations named in the OD-149 report. It is also the `.env` default (`APP_ENV=local`) and the database of the owner's own `artisan serve`. |
| Server | `administrator-Latitude-7490:3306`, MySQL 8.4.11, InnoDB, `utf8mb4_0900_ai_ci`, strict SQL mode. A shared workstation server that also holds other projects' databases. |
| Account | `root@localhost`, with all privileges (R-14 is still open: no dedicated account). Passwords were never printed; mysqldump read them from a private option file, deleted afterwards. |
| Size before | 43 tables, 299 rows, about 2.2 MB; 44 migrations, batch 10. |
| Application during the update | The owner's server was **not running** (nothing on 8001 or 8003; port 8000 belongs to another project). No configuration or route cache in `bootstrap/cache`. No queued jobs. |

## 2. The 25 pending migrations

All are additive: 21 new tables and 4 new nullable columns. None drops, renames or narrows anything. Only two write data: (5) inserts one row into its own new table, and (13) fills the new column it adds. Dependencies are satisfied by file order; every foreign key references a table that existed or was created earlier in the run. Each table and column was already guarded in the application (OD-120 pattern), so the working tree ran on `smart_go` both before and after.

| # | Migration | Purpose | Tables / columns | Depends on | Kind | Needed by | Compatibility and impact | Risk |
|---|---|---|---|---|---|---|---|---|
| 1 | `2026_10_27_185025_create_twin_models_table` | 3D models of a property (Phase 7, OD-118/119) | new `twin_models` | `properties`, `users` | schema | Website → 3D models | Screen stays hidden: `twin_models.*` permissions are not on `smart_go` (§6) | Low |
| 2 | `2026_10_27_185026_create_twin_anchors_table` | Named nodes found in a model | new `twin_anchors` | #1 | schema | 3D models | as #1 | Low |
| 3 | `2026_10_28_101824_create_post_topics_table` | Insight topics (OD-122, C-31) | new `post_topics` | — | schema | Website → Insight topics, `/insights/topic/…` | Topics screen appears, empty; no topic is preset | Low |
| 4 | `2026_10_28_101825_add_topic_ids_to_posts_table` | An article's topics | `posts.topic_ids` JSON, nullable | `posts` | schema | article editor, insights, sitemap | Existing articles get none (`smart_go` has 0 articles) | Low |
| 5 | `2026_10_28_123721_create_security_settings_table` | Two-step login switch (OD-124) | new `security_settings` + **1 row** (`two_step_login_required = 0`) | — | schema + data | Settings → Security | Same behaviour: optional locally before and after; always required outside local/testing | Low |
| 6 | `2026_10_28_133128_add_scope_to_faqs_table` | FAQ scope by location or workspace type (FR-CMS-7) | `faqs.property_id`, `faqs.product_id`, nullable, foreign keys (restrict) | `properties`, `products` | schema | FAQ editor, location and type pages | Existing FAQs stay general (`smart_go` has 0 FAQs) | Low |
| 7 | `2026_10_28_173942_create_space_types_table` | Physical space classification (Phase 5 inventory) | new `space_types` | — | schema | Properties → Space types | Screen appears, **empty** until the reference data is seeded (§6) | Low |
| 8 | `2026_10_28_173943_create_floors_table` | Floors of a building | new `floors` | `properties`, `buildings` | schema | inventory | none created by assumption (OD-30) | Low |
| 9 | `2026_10_28_173945_create_spaces_table` | Each physical workspace | new `spaces` (soft deletes) | `properties`, #8, #7 | schema | Properties → Spaces | Screen appears, empty | Low |
| 10 | `2026_10_28_173946_create_space_seats_table` | Seats in a per-seat zone | new `space_seats` | #9 | schema | inventory | — | Low |
| 11 | `2026_10_28_173948_create_offering_space_table` | Categories a space is sold as | new `offering_space` | `offerings`, #9 | schema | inventory | — | Low |
| 12 | `2026_10_28_173949_create_space_allocations_table` | Long-term occupancy | new `space_allocations` | #9, #10, `users` | schema | allocations | Hidden: `allocations.*` permissions absent | Low |
| 13 | `2026_10_28_173951_add_allowed_inventory_modes_to_products_table` | Inventory modes per category (BR-PRODCFG-2) | `products.allowed_inventory_modes` JSON, nullable + **fills it for the 7 categories** where empty | `products` | schema + data | category form ("Spaces it can be sold with") | Only the new column is written; the approved values in the migration | Low |
| 14 | `2026_10_28_193821_create_price_plans_table` | Price plans (OD-35) | new `price_plans` | `offerings`, #9, `users` | schema | pricing | Hidden: `pricing.*` permissions absent; no price seeded | Low |
| 15 | `2026_10_28_193822_create_offers_table` | Promotional offers | new `offers` | `users` | schema | offers | Hidden: `offers.manage` absent | Low |
| 16 | `2026_10_28_193823_create_offer_offering_table` | Offerings an offer applies to | new `offer_offering` | #15, `offerings` | schema | offers | — | Low |
| 17 | `2026_10_28_193825_create_inventory_imports_table` | Inventory workbook imports (SG-INV-1.3) | new `inventory_imports` | `users` | schema | inventory import | Hidden: `inventory.import*` absent | Low |
| 18 | `2026_10_28_193826_create_inventory_import_issues_table` | Import validation findings | new `inventory_import_issues` | #17 | schema | inventory import | — | Low |
| 19 | `2026_10_28_195325_create_amenities_table` | Amenity vocabulary | new `amenities` | — | schema | inventory import | none seeded | Low |
| 20 | `2026_10_28_195327_create_amenity_space_table` | Amenities of a space | new `amenity_space` | #19, #9 | schema | inventory | — | Low |
| 21 | `2026_10_29_033934_create_tour_availability_rules_table` | Tour hours per location (BR-TR-1) | new `tour_availability_rules` | `properties`, `users` | schema | tours | Hidden: `tours.*` absent; public tour form stays closed (no rules) | Low |
| 22 | `2026_10_29_033935_create_property_closures_table` | Location closures | new `property_closures` | `properties`, `users` | schema | tours, bookings | — | Low |
| 23 | `2026_10_29_033937_create_tour_requests_table` | Site visits | new `tour_requests` | `leads`, `properties`, `users` | schema | tours | — | Low |
| 24 | `2026_10_29_072209_create_missing_pages_table` | 404 monitor (FR-SEO-4) | new `missing_pages` | — | schema | Website → Missing pages | From now on the owner's server records the path of each "not found" page (path, count, first and last time; nothing about the visitor) | Low |
| 25 | `2026_10_30_201616_add_details_to_navigation_items_table` | Menu descriptions and icons (OD-149) | `navigation_items.description` varchar(160), `navigation_items.icon` varchar(40), nullable | `navigation_items` | schema | the Solutions mega menu, menu editor | Existing 9 items get none, so the header looks as before | Low |

The SQL each migration runs was captured with `php artisan migrate --pretend` on the restored copy (`pretend.txt`). Data statements: one `insert` (#5) and seven `update … where allowed_inventory_modes is null` (#13); no `delete`.

## 3. Backup

| | |
|---|---|
| File | `~/smartgro-backups/smart_go-2026-10-31-before-od150.sql` (directory mode 700, file mode 600; outside the repository) |
| Made | 2026-10-31 09:34:42 IST, `mysqldump --single-transaction --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF`, exit code 0 |
| Content | 327,261 bytes; 43 `CREATE TABLE`, 21 `INSERT` (one per non-empty table); "Dump completed" line present; no `CREATE DATABASE`, so it restores into any database name |
| SHA-256 | `91cd442381256b0b8aaa30735098abed6eb0085fc960e544309acf9c16d0fefb` (also in `backup.sha256` next to the file and in the evidence) |
| Restore test | Restored into a new, isolated database `smartgro_restore_check`. All 43 tables equal `smart_go` in rows, a hash over every row, columns, indexes and foreign keys (`restore-check.json`) |

Earlier backups (2026-10-30, OD-142/143) remain beside it.

## 4. Rehearsal, then the real run

1. **Rehearsal** on the restored copy: `migrate --pretend`, then `migrate --force`. All 25 ran in 2.44 s as batch 11 (`rehearsal-migrate.txt`). The copy's fingerprint matched expectations (`restore-after.json`): 21 new tables, 4 tables with new nullable columns, and **no existing value changed in any of the 43 original tables**.
2. **Application checks on the copy:**
   - `copy-smoke.php` published a main-menu version with descriptions, icons and new entries through the real workflow (author, a second approver, publisher). The values were stored in the new columns, carried into the next draft and rendered in the header.
   - `http-smoke.php` sent 36 public and back-office requests in-process, with a READ ONLY database session and array session and cache. None failed with a server error, and none tried to write.
3. **Baseline on `smart_go` before the change** (the same read-only test):
   - It found an existing defect: `/sitemap.xml` answered 500, selecting `posts.topic_ids` without the topics guard.
   - Fixed in the code (`Sitemap::insights()`). The OD-120 guard test now covers the sitemap and failed before the fix.
4. **Real run on `smart_go`**, with the effective database confirmed as `smart_go` and `CACHE_STORE=array`, `SESSION_DRIVER=array`, `LOG_CHANNEL=stderr`, so only the migrations wrote:
   - `php artisan migrate --force` ran from **09:40:57 to 09:40:59 IST**, exit code 0, all 25 `DONE`, batch 11 (`smart_go-migrate.txt`).

## 5. Verification on `smart_go` after the change

| Check | Result |
|---|---|
| Migrations | 69, latest batch 11 (25 in it), 0 pending |
| Tables | 64 (43 + 21 new); `security_settings` has 1 row (`two_step_login_required = 0`); every other new table is empty |
| Existing data | **Unchanged** in all 43 original tables (row counts and a hash over every earlier column), except the 25 new `migrations` rows (`smart_go-after.json`) |
| New columns | `navigation_items.description` varchar(160) NULL default NULL, `icon` varchar(40) NULL default NULL; `posts.topic_ids`, `products.allowed_inventory_modes` JSON NULL; `faqs.property_id`, `faqs.product_id` bigint unsigned NULL with foreign keys |
| Navigation | the 9 existing items are intact, with no description or icon |
| Products | `allowed_inventory_modes` set for the 7 categories exactly as in the migration (`virtual_office` = none) |
| Same as rehearsal | identical tables, columns, indexes and foreign keys |
| Application (read-only, in-process) | Every public and back-office request answers as on the copy, with no server errors. The menu editor shows "Short description"; the category form shows "Spaces it can be sold with"; Settings → Security says "Two-step login is optional"; the menu rules keep a description and an icon. The screens whose permissions are absent answer 403, not 500 (`http-smoke-smartgo-after.json`) |
| Smoke tests wrote nothing | fingerprint unchanged afterwards (`smart_go-after-smoke.json`) |
| Sample content | none: 0 pages, 0 articles, 0 topics, 0 sample versions; the sample seed script refuses `smart_go` |
| Port 8001 | never requested |

The CMS's create-and-edit path was proven on the copy (same schema and data), not on `smart_go`: creating a menu version there would change the owner's data, and with one active staff member it could not be approved anyway (BR-CMS-2a).

## 6. Not done (needs a separate owner decision)

- **`php artisan access:sync`:** never run on `smart_go` (forbidden). 14 permissions in the code are absent there: `allocations.manage`, `allocations.view`, `inventory.import`, `inventory.import.approve`, `inventory.manage`, `leads.reports.view`, `offers.manage`, `pricing.approve`, `pricing.manage`, `pricing.view`, `tours.manage`, `tours.view`, `twin_models.manage`, `twin_models.view`. Until they exist, the 3D models, allocation, inventory import, pricing, offers, tours and enquiries-report screens stay hidden (403), as before.
- **Reference data:** not seeded, because the authorisation covers schema updates only. `ReferenceDataSeeder` would now add the space types; the Spaces and Space types screens show empty lists until then.
- **`.env`:** unchanged. With `APP_ENV=local`, `BACKOFFICE_SAMPLE_CONTENT` defaults to *allowed* on this server (OD-149). To make `smart_go` refuse sample pages and sample articles as production does, the owner can add `BACKOFFICE_SAMPLE_CONTENT=false`.
- **Application rollout:** nothing to deploy. The owner's server runs this working tree, which already expects the new schema. No cache needs clearing; there is no configuration or route cache.

## 7. Rollback

1. **Preferred:** stop the owner's server, check the SHA-256, empty the database, then load the backup. This returns `smart_go` exactly to its state at 09:34:42, losing anything written since. The dump re-creates only its own 43 tables, so restoring without emptying the database would leave the 21 new tables behind, and the next `migrate` would fail on them.
   ```
   sha256sum -c ~/smartgro-backups/smart_go-2026-10-31-before-od150.sql.sha256
   mysql -e "DROP DATABASE smart_go; CREATE DATABASE smart_go CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
   mysql smart_go < ~/smartgro-backups/smart_go-2026-10-31-before-od150.sql
   ```
2. **By steps:** `php artisan migrate:rollback --step=25` (all 25 have a `down()` that drops their table or column). This keeps other data, but discards anything entered in the new tables and columns. Never run `migrate:reset`, `migrate:refresh` or `migrate:fresh` (`DEPLOYMENT_RUNBOOK.md`).

The application works either way: every new table and column is guarded.
