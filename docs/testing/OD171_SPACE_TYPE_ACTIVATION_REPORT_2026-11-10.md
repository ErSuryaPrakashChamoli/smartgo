# SmartGro — OD-171: Space-Type Activation on `smart_go` (A-23)

Report of 2026-11-10 (OD-171). It records the preparation and rehearsal of loading the approved space types and their category links on `smart_go`, the database of the owner's own server.

**Status.** Preparation, backup verification and isolated rehearsal: **done**. Production write on `smart_go`: **done**, on the owner's explicit authorisation ("yes", 2026-11-10), committed at 18:51:30 IST (§7a).

Evidence: `evidence/phase14/2026-11-10-od171-space-types/`.

---

## 1. Approved scope

The owner's brief ("SmartGro OD-171 — A-23 Controlled Reference-Data Loading", 2026-11-10) authorises **preparation and rehearsal only**. A separate, explicit authorisation is required before writing to `smart_go`.

The brief approves:
- **six space types** and **eight type-category links**;
- **both A-28 combinations excluded**.

It adds no other type, category or link.

## 2. The six space types and eight links

**Sources.**
- `DATABASE_ERD.md` §3.1 is the design source.
- `ReferenceDataSeeder::SPACE_TYPES` and `::SPACE_TYPE_CATEGORIES` hold the values the code loads.

**Agreement.** The two sources agree on keys, inventory modes and links. The only difference is the wording of names: the ERD writes "Private cabin / office", the seeder "Private cabin or office". The seeder's names are what the database stores. This is not a disagreement about scope.

| # | Key (stable identifier) | Name stored | Default mode | Allowed modes | Bookable by time | Order |
|---|---|---|---|---|---|---|
| 1 | `private_cabin` | Private cabin or office | `exclusive` | `exclusive` | no | 10 |
| 2 | `desk_zone_dedicated` | Dedicated desk zone | `per_seat` | `per_seat`, `hybrid` | no | 20 |
| 3 | `desk_zone_flexible` | Flexible seating zone | `pooled` | `pooled`, `per_seat`, `hybrid` | no | 30 |
| 4 | `meeting_room` | Meeting room | `exclusive` | `exclusive` | yes | 40 |
| 5 | `conference_training_room` | Conference or training room | `exclusive` | `exclusive` | yes | 50 |
| 6 | `office_suite` | Office suite or floor plate | `exclusive` | `exclusive` | no | 60 |

Numeric ids are assigned by the database. On `smart_go` the next id is 1, so the types are expected to get ids 1–6; the keys are the identifiers the code uses.

| # | Space type | Can be sold as (category key) |
|---|---|---|
| 1 | `private_cabin` | `private_office` (Private offices and cabins) |
| 2 | `private_cabin` | `managed_office` (Managed offices) |
| 3 | `desk_zone_dedicated` | `dedicated_desk` (Dedicated desks) |
| 4 | `desk_zone_flexible` | `hot_desk` (Hot desks) |
| 5 | `meeting_room` | `meeting_room` (Meeting rooms) |
| 6 | `conference_training_room` | `conference_room` (Conference and training rooms) |
| 7 | `office_suite` | `private_office` (Private offices and cabins) |
| 8 | `office_suite` | `managed_office` (Managed offices) |

These eight are exactly ERD §3.1's "typically sellable as".

**Excluded (A-28, undecided, so not approved):**
1. `desk_zone_flexible` → `dedicated_desk` (flexible seating zone sold as dedicated desks);
2. `conference_training_room` → `meeting_room` (conference or training room sold as meeting rooms).

The seeder would add both. The operation leaves them out.

## 3. Session cleanup

**Other sessions.** Both other SmartGro sessions were released ("done") and confirmed they hold nothing for this work:
- "Smartgro" (Phase 9, OD-170);
- smartgrow-5e, successor of smartgrow-dd.

smartgro_testing was not used or changed by this work.

**Credentials.** The temporary credentials file `…/scratchpad/od171/my.cnf` was checked by path, mode 600 and section header only, then deleted. Its contents were never displayed or copied. No other scratchpad file was removed. Later commands read the credentials from `.env` in-process and never print them.

## 4. Backup verification

Backup: `~/smartgro-backups/smart_go-2026-11-10-before-od171-space-types.sql`.

| Check | Result |
|---|---|
| Exists, readable, size | yes; 735,641 bytes; mode 600 |
| SHA-256 | matches the `.sha256` beside it |
| Identity | `-- Host: 127.0.0.1    Database: smart_go`; MySQL 8.4.11 |
| Complete | `-- Dump completed on 2026-11-10 18:28:26` |
| Structure | 70 `CREATE TABLE`, 32 `INSERT` statements; `space_types` and `product_space_type` present and empty; `products`, `migrations`, `users`, `activity_log` and `home_banners` present with data |
| Credentials | the database password occurs 0 times in the file |
| Restore check | loaded into a new database `smartgro_od171_rehearsal` (same character set and collation as `smart_go`). It has 70 tables, 75 migrations (last batch 12), and every table's row count equals `smart_go` at 18:28. Done twice, for the two rehearsals. |

The backup was never restored over `smart_go`.

## 5. The operation

**Script.** `evidence/…/activate-space-types.php` (SHA-256 `c8b95a31…6860399`, in `activate-space-types.php.sha256`) does the following:
1. It refuses unless connected to the named database with no cached configuration.
2. It reads the definitions from the seeder's constants, never retyping names.
3. It removes the two A-28 combinations.
4. It refuses unless that gives exactly six types and eight links, and every category exists.

**One transaction:**
- **Types:** creates each type only where missing. An existing type must match its definition exactly, or the script refuses and rolls back. It never updates an existing type.
- **Links:** adds each link only where missing. It never removes or changes a link.
- **Audit:** `SpaceType`'s own audit log writes one `inventory` / `created` entry per new type. The log uses the same connection, unbuffered, so it is inside the transaction too.

**What it does not run:** `db:seed`, `access:sync`, any migration (the three P9-D migrations stay pending on `smart_go`), or anything touching other tables.

**Environment:** `DB_DATABASE` named explicitly; the three cache overrides; array session and cache; sync queue; log to standard error.

## 6. Rehearsal (isolated copy, `smartgro_od171_rehearsal`)

**Method.** Every table was fingerprinted before and after each run: row count plus MySQL `CHECKSUM TABLE`. Audit rows up to id 340 were also fingerprinted by SHA-256, to tell new audit rows from changed ones (`table-checksums.php`, read-only session).

| Run | Result | Tables changed |
|---|---|---|
| 1. With `--simulate-failure` (throws after every insert) | rolled back | none |
| 2. The operation | committed: 6 types created, 8 links added | `space_types` 0 → 6, `product_space_type` 0 → 8, `activity_log` 340 → 346 (6 × `inventory` / `created`); audit rows 1–340 identical |
| 3. Repeat | committed with nothing new: 6 types and 8 links confirmed | none |
| 4. After changing one type's name on the copy, run again | refused ("space type office_suite exists with different name") and rolled back | none: the edited name was kept |

- Neither A-28 combination was present after any run.
- **Read-only smoke on the copy** (OD-164's `http-smoke.php`): public 11 × 200 and 1 × 302 (the finder, as before); back office 38 × 200 and 6 × 302; no error. Space types, Spaces, Workspace categories and a category's form all answered 200.

**A first attempt** (`rehearsal/attempt1-comparison-bug/`) found a defect in the script's repeat check. It compared the stored inventory mode, which the model reads as an enum, with a plain string, so an identical type looked different. The repeat refused and rolled back, changing nothing. The comparison was fixed, and the whole rehearsal was repeated on a fresh copy of the backup with the final script (runs 1–4 above). The write path was unchanged.

The rehearsal copy holds staff accounts, so it was dropped afterwards.

## 7. Production (`smart_go`): the plan as approved

**Command,** from the project root, unchanged from the rehearsal:

```bash
export DB_DATABASE=smart_go APP_CONFIG_CACHE=/tmp/smartgro-nocache/config.php APP_ROUTES_CACHE=/tmp/smartgro-nocache/routes.php \
  APP_EVENTS_CACHE=/tmp/smartgro-nocache/events.php SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync LOG_CHANNEL=stderr
php docs/testing/evidence/phase14/2026-11-10-od171-space-types/activate-space-types.php smart_go
```

**Before it runs:**
1. Confirm the target is `smart_go`.
2. Confirm the backup's SHA-256 again.
3. Confirm the script's SHA-256 matches the rehearsed one.
4. Fingerprint every table.
5. Stop if `smart_go` differs from the backup in anything but sessions and cache rows.

**Expected changes, and nothing else:**
- `space_types` 0 → 6;
- `product_space_type` 0 → 8;
- `activity_log` +6 (`inventory` / `created`, no causer).

**Untouched:**
- all other 67 tables, including categories, cities, locations, pages, menus, banners, users, roles and permissions;
- the existing audit rows;
- no floor, space, seat, price or offer is created;
- the three P9-D migrations stay pending.

**After it runs:** a read-only fingerprint compared against the rehearsal; the read-only smoke; the six types and eight links listed read-only.

## 7a. Production execution

**Authorisation.** The owner answered "yes" to the explicit question, after the summary of §2–§8. Started 18:51:11 IST (`evidence/…/production/authorised-and-started.txt`).

**Pre-checks, all passed:**
- the backup's SHA-256 still matches;
- the script's SHA-256 equals the rehearsed one (`c8b95a31…6860399`);
- the uncached configuration names `smart_go`;
- every table's fingerprint equals the backup's (row counts and `CHECKSUM TABLE`, `production/p-c0.json` against `rehearsal/r-c0.json`): nothing had changed since 18:28, not even sessions or cache;
- audit rows 1–340 identical.

**Run.** Exit code 0, `committed`, finished 18:51:30 IST, nothing on standard error (`production/p-run.json`).
- **Types:** 6 created.
- **Links:** 8 added.
- **Counts:** `space_types` 0 → 6, `product_space_type` 0 → 8, `activity_log` 340 → 346.
- **A-28:** neither combination present.

**Read-only verification:**
- **Tables changed** (`production/p-c1.json`): exactly `space_types`, `product_space_type` and `activity_log`, as in the rehearsal; audit rows 1–340 identical.
- **Rows stored** (`production/p-rows.json`): types 1–6 with the keys, names, modes and order of §2; the eight links of §2; audit entries 341–346, each `inventory` / `created` for types 1–6, no causer.
- **Smoke** (`production/p-http-smoke.json`): public 11 × 200 and 1 × 302, back office 38 × 200 and 6 × 302, no error. Space types, Spaces, Workspace categories and a category's form answered 200. No pipeline or deal screen or error appeared (P9-D tables absent, as expected).
- **The smoke itself** changed no table (`production/p-c2.json`).

Both other SmartGro sessions were told before the write and released after it.

**Evidence integrity note.** At 19:20, after the run, another session's `pint --dirty` reformatted `activate-space-types.php` (whitespace and `use` lines). That session restored it from this session's scratchpad copy. Checked here: its SHA-256 is again `c8b95a31…6860399`, the rehearsed and executed one, and only the file's modification time differs. `table-checksums.php` and `state.php` were not touched.

## 8. Rollback

| Situation | Rollback |
|---|---|
| Any error during the run | automatic: the transaction rolls back (rehearsed, run 1) |
| After it commits, while no space uses these types | delete the six types by key (the eight links go with them through the cascade); this writes 6 `deleted` audit entries, while the `created` ones stay. `spaces.space_type_id` is `restrictOnDelete`, so once staff create a space of a type, that type can no longer be deleted. |
| Last resort | restore the 18:28 backup into an emptied `smart_go` (OD-150 procedure); this also undoes anything else written after 18:28 |

## 9. Boundaries kept

- `smart_go`: read-only during preparation and rehearsal; one authorised write (§7a), exactly as rehearsed.
- `smartgro_preview` and `smartgro_testing`: not accessed.
- No application code changed.
- No commit, push, deployment, package change, cache clearing, rebuild or service restart.
- Port 8001 not contacted.
- Git work preserved: only this report and the evidence folder were added.
- Phase 14 stays in progress; CSP enforcement stays disabled.

## 10. Open

- **A-28, both parts:** undecided. Approving a combination later is one tick on the category's form (Properties → Workspace categories), which is audited.
