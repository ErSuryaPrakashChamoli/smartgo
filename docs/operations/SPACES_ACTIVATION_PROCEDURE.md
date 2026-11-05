# SmartGro — activating Spaces on `smart_go` (prepared, not executed)

Prepared 2026-10-31 (OD-155) and revised the same day (OD-156) after a review against the code, the schema and the current state of `smart_go`. Every step was rehearsed in this order on a replica of `smart_go` in `smartgro_testing`. Evidence: `../testing/evidence/phase14/2026-10-31-od155/` and `../testing/evidence/phase14/2026-10-31-od156/`.

**Activation has not been carried out and needs the owner's separate, explicit authorisation** (A-22, A-23, and the A-28 decisions). `smart_go` is the database of the owner's own server, not a deployed production environment.

**One step is already done outside this procedure:** the OD-153 migration (step 4) ran on `smart_go` at 2026-10-31 15:24:51 IST, 15 seconds after commit `8325493`. It ran outside this session, and without this procedure's fresh backup. It created the empty `product_space_type` table (batch 12) and nothing else.

## Revision note (OD-156)

| Was | Now | Evidence |
|---|---|---|
| "Nothing here has been run on `smart_go`" | step 4 already applied (15:24:51) | `information_schema` create time; `migrations` row 70, batch 12 |
| step 1 expects exactly one pending migration and no cached configuration | none pending; a configuration, route and event cache exists (made 15:25) and every command here bypasses it on purpose | `migrate:status`; `bootstrap/cache` |
| step 2 expects 64 tables, 69 migrations (batch 11) | 65 tables, 70 migrations (batch 12), `product_space_type` empty | read-only post-check |
| "the OD-153 code is not committed (OD-128 a 3)" | committed in `8325493` (with the whole working tree) | `git show 8325493` |
| A-28 covered only conference rooms as meeting rooms, handled after activation | A-28 covers **both** proposed combinations; anything not explicitly approved is removed in **step 5a**, right after the seeder adds it and before permissions or the server | rehearsal: the seeder enables both; step 5a removes them |
| recovery said rolling back the migration "drops only this table" | it also does **not** undo the space types, permissions, role links or audit entries; only the backup does | rehearsal |
| full recovery reused a credentials file deleted at step 3 | it is re-created explicitly | step 3 shreds it |
| no explicit pre-checks or post-checks | `postcheck.php`, read-only, before and after | rehearsal output |

## What activation does, and does not do

- **Does:**
  - loads the six proposed space types;
  - links each to the categories that may sell it, keeping only combinations the owner has approved;
  - adds the 14 permissions the code defines and `smart_go` lacks.

  Spaces, Space types, 3D models, pricing, offers, inventory import, tours and the enquiries report then open for the roles that hold those permissions.
- **Does not:**
  - create floors, spaces, seats, allocations, prices or any other inventory (Phase 5 entry gate, OD-30, OD-128);
  - change content, publish anything or change `.env` (`BACKOFFICE_SAMPLE_CONTENT=false` stays);
  - run any other seeder.

  **Never run a plain `php artisan db:seed` or `migrate --seed`:** `DatabaseSeeder` runs `access:sync` and creates a factory "Test User".

## Gates (all required before step 0)

| Gate | Check |
|---|---|
| Authorisation | the owner has explicitly authorised A-22 (`access:sync`) and A-23 (reference data) on `smart_go` |
| **A-28 decided, both parts** | (1) may a **flexible seating zone** be sold as **dedicated desks**? (2) may a **conference or training room** be sold as **meeting rooms**? The seeder adds both automatically (step 5). **Each one not explicitly approved, including "undecided", is removed in step 5a.** Test fixtures, the example workbook, the seeder and rehearsals are not approvals. |
| Inventory commit gate | the owner has recorded a decision on commit `8325493`, which committed the inventory module that OD-128 (a)(3) said waits for the entry gate (A-29). Activation does not depend on it technically, but it should not proceed while this owner decision is open. |
| Backup copy | A-27: a second copy of backups off this machine, recommended first |
| Configuration cache secrets | recommended: `chmod 600 bootstrap/cache/config.php` (it holds the database password and app key; found at mode 664 in OD-156, A-30) |
| Machine quiet | no test suite or browser kit running (they use `smartgro_testing`, which step 3 uses too) |

## Environment for every command

Run all steps in **one** terminal, from the project root:

```bash
cd /home/administrator/Documents/website/smartGrow
export DB_DATABASE=smart_go APP_CONFIG_CACHE=/nonexist/c.php APP_ROUTES_CACHE=/nonexist/r.php APP_EVENTS_CACHE=/nonexist/e.php \
  SESSION_DRIVER=array QUEUE_CONNECTION=sync LOG_CHANNEL=stderr
F=docs/testing/evidence/phase14/2026-10-31-od150-smart-go      # fingerprint.php, http-smoke.php
PC=docs/testing/evidence/phase14/2026-10-31-od156/postcheck.php
DELTA=docs/testing/evidence/phase14/2026-10-31-od155/delta.py
START=$(date -u '+%F %T')                                        # audit entries are counted from here (UTC)
```

- **`DB_DATABASE=smart_go`** names the target instead of trusting `.env`.
- **The three `APP_*_CACHE` paths** never exist, so the cached configuration in `bootstrap/cache` is bypassed. Do not point `APP_PACKAGES_CACHE` or `APP_SERVICES_CACHE` anywhere: artisan must write those.
- **`SESSION_DRIVER`, `QUEUE_CONNECTION` and `LOG_CHANNEL`** keep the commands from writing sessions, jobs or the shared log.
- **`CACHE_STORE` is deliberately not overridden.** `access:sync` must clear `smart_go`'s own permission cache.
- **Credentials are never printed.** They are read from `.env` into a private option file and deleted again.

## Steps

### 0. Stop the owner's server and confirm nothing uses `smart_go`

Stop `php artisan serve` in its terminal (Ctrl+C), then:

```bash
ss -ltnp | grep -E ':(8000|8001|8002|8003)\b'
for p in $(pgrep -f "artisan serve|php -S"); do echo "$p $(readlink /proc/$p/cwd) $(tr '\0' '\n' < /proc/$p/environ | grep ^DB_DATABASE=)"; done
```

- **Expected:** no process whose directory is this project and that has no `DB_DATABASE=` (that is the owner's server on `smart_go`).
- The local site shows `DB_DATABASE=smartgro_site` and can keep running. The demo (`smartgro_demo`, port 8768) is retired (OD-158); if a process shows that database, it was started by hand and is not part of the setup.
- Never stop another project's server.

### 1. Verify the target (read-only)

```bash
php artisan config:show database.connections.mysql.database                              # smart_go
php artisan tinker --execute 'echo DB::selectOne("SELECT DATABASE() d")->d, PHP_EOL;'   # smart_go
php artisan migrate:status --pending                                                      # "No pending migrations"
```

**Stop if** a different database answers, or any migration is pending. (If the OD-153 migration is pending, step 4 applies it.)

### 2. Fingerprint and pre-check (read-only)

```bash
php $F/fingerprint.php smart_go $F/smart_go-before.json > ~/smartgro-backups/smart_go-before-spaces-activation.json
php $PC smart_go "$START" > ~/smartgro-backups/postcheck-before-spaces-activation.json
```

**Expected pre-check:**
- migrations 70, latest batch 12, activation migration ran;
- `product_space_type` exists;
- no space types, no links, both A-28 combinations "not enabled";
- 0 spaces;
- 34 permissions, 0 of the 14;
- role counts: Super Admin 34, Marketing Manager 22, Property Manager 7, Sales/CRM Executive 6, Operations Manager 5, Finance 5;
- no audit entries since `START`;
- permission cache entry present.

**Stop if** any differs.

### 3. Back up `smart_go` and check that the backup restores

```bash
umask 077
php -r '$e=[]; foreach (file(".env", FILE_IGNORE_NEW_LINES) as $l) { if (preg_match("/^([A-Z_]+)=(.*)$/", $l, $m)) { $e[$m[1]] = trim($m[2], "\"'\''"); } } file_put_contents($argv[1], "[client]\nuser={$e["DB_USERNAME"]}\npassword={$e["DB_PASSWORD"]}\nhost={$e["DB_HOST"]}\nport={$e["DB_PORT"]}\n");' ~/smartgro-backups/.my.cnf
B=~/smartgro-backups/smart_go-$(date +%F-%H%M)-before-spaces-activation.sql
mysqldump --defaults-extra-file=$HOME/smartgro-backups/.my.cnf --single-transaction --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF smart_go > "$B"
chmod 600 "$B"; sha256sum "$B" > "$B.sha256"; tail -1 "$B"            # "-- Dump completed on …"
# Restore check in the TEST database, named in the SQL itself (never `artisan db:wipe` here: under the
# export above it would target smart_go).
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf -e "DROP DATABASE smartgro_testing; CREATE DATABASE smartgro_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf smartgro_testing < "$B"
php $F/fingerprint.php smartgro_testing $F/smart_go-before.json > /tmp/restored.json
python3 $DELTA ~/smartgro-backups/smart_go-before-spaces-activation.json /tmp/restored.json   # expect: "changed": {}
shred -u ~/smartgro-backups/.my.cnf
```

- **Target:** reads `smart_go`, writes only the backup file and `smartgro_testing`.
- **Stop if** the dump does not end with "Dump completed", or the restored copy differs in any table.
- **Afterwards:** the copy in `smartgro_testing` holds staff accounts. The next test run replaces it (Pest refuses any database not named `*_testing`).

### 4. The OD-153 migration (already applied; confirm only)

```bash
php artisan migrate --force --path=database/migrations/2026_10_31_064952_create_product_space_type_table.php   # "Nothing to migrate"
```

- **Expected:** "Nothing to migrate", and nothing changes.
- **If it is still pending:**
  - **Changes:** creates `product_space_type`, with two cascading foreign keys and an index; `migrations` +1. No data.
  - **Failure:** MySQL table changes are not transactional. If the table exists without its `migrations` row, remove it with `php artisan tinker --execute 'Schema::dropIfExists("product_space_type");'` (only in that situation) and run the command again.
- **Recovery for this step alone:** `php artisan migrate:rollback --batch=12 --force` drops `product_space_type` and its `migrations` row, after `migrate:status` shows batch 12 holds only this migration.
  - After steps 5–6, that removes the category–type links with the table.
  - It does **not** remove the space types, permissions, role links or any audit entry. Only the step-3 backup undoes those.

### 5. Load the reference data

```bash
php artisan db:seed --class=ReferenceDataSeeder --force
```

- **Expected changes** (rehearsed):
  - `space_types` 0 → **6**;
  - `product_space_type` 0 → **10**, **including both A-28 combinations**;
  - `activity_log` +6 (`inventory` / `created`);
  - nothing else (cities, categories, segments, areas, locations, buildings and profiles already exist and are never updated).
- **Idempotency:** a second run changes nothing. Links are added only to a space type no category accepts yet, so a later run does not re-add a combination removed in step 5a, as long as the type keeps another category (rehearsed).
- **Failure:** there is no wrapping transaction. Running the command again completes what is missing.
- **Recovery:** while no space exists, deleting the six space types removes their links (cascade) but writes six `deleted` audit entries; the `created` entries stay. Only the step-3 backup removes audit entries.

### 5a. Apply the A-28 decisions (immediately after step 5)

For each combination **not explicitly approved by the owner** (undecided counts as not approved), keep its line; delete the line of an approved one:

```bash
php artisan tinker --execute '
$owner = App\Models\User::query()->where("is_active", true)->whereHas("roles", fn ($roles) => $roles->where("name", "super_admin"))->sole();
foreach ([
    "dedicated_desk" => "desk_zone_flexible",          // A-28 (1): flexible seating zone -> dedicated desks
    "meeting_room" => "conference_training_room",      // A-28 (2): conference or training room -> meeting rooms
] as $category => $type) {
    $product = App\Models\Product::query()->where("key", $category)->sole();
    app(App\Actions\Properties\SetCategorySpaceTypes::class)->handle($owner, $product, $product->spaceTypes()->where("key", "!=", $type)->pluck("space_types.id")->all());
}'
```

- **Acts as** the single active Super Admin (`sole()` refuses if there is none or more than one), through the same audited action as the category form.
- **Expected:** `product_space_type` −1 per removed combination (10 → 8 with both); one `properties` / `space_types_changed` audit entry each; the permission cache entry is refreshed (step 6 deletes it).
- **Idempotency:** a second run changes nothing (rehearsed).
- **Approving a combination later:** tick it on the category's form (Properties → Workspace categories), which is audited too.

### 6. Synchronise permissions

```bash
php artisan access:sync
```

- **Expected changes** (rehearsed):
  - `permissions` 34 → **48**;
  - `role_has_permissions` 79 → **125**, none removed:

    | Role | Permissions after |
    |---|---|
    | Super Admin | 48 |
    | Operations Manager | 14 |
    | Property Manager | 15 |
    | Sales/CRM Executive | 11 |
    | Marketing Manager | 27 |
    | Finance | 10 |

  - `activity_log` +6 (`access` / `role_capabilities_changed`);
  - the permission cache entry deleted;
  - users and their roles unchanged.
- **Idempotency:** a second run changes nothing.
- **Failure:** one transaction, so a failure leaves permissions and roles unchanged; the cache entry is cleared only after it commits.
- **Recovery:** the step-3 backup. Removing permissions by hand is not recommended.

### 7. Verify (read-only)

```bash
php $F/fingerprint.php smart_go $F/smart_go-before.json > ~/smartgro-backups/smart_go-after-spaces-activation.json
python3 $DELTA ~/smartgro-backups/smart_go-before-spaces-activation.json ~/smartgro-backups/smart_go-after-spaces-activation.json
php $PC smart_go "$START"
mkdir -p /tmp/smartgro-smoke/framework/{views,cache,sessions} /tmp/smartgro-smoke/logs /tmp/smartgro-smoke/app
CACHE_STORE=array LARAVEL_STORAGE_PATH=/tmp/smartgro-smoke php -d variables_order=EGPCS $F/http-smoke.php smart_go > /tmp/smoke.json
```

**Expected changes, and nothing else** (shown with both A-28 combinations removed):

| Table | Change |
|---|---|
| `space_types` | +6 |
| `product_space_type` | 0 → 8 (10 minus each removed combination) |
| `permissions` | +14 |
| `role_has_permissions` | +46 |
| `activity_log` | +14 (6 + 2 + 6) |
| `cache` | −1 (the permission cache entry) |
| `migrations` | unchanged (+1 only if step 4 applied it) |

**Expected post-check:**
- 6 space types, 8 links, both A-28 combinations "not enabled" (or "ENABLED" exactly where approved);
- 0 spaces;
- 48 permissions, 14 of the 14;
- the role counts of step 6;
- audit since `START`: `inventory / created` 6, `properties / space_types_changed` 2, `access / role_capabilities_changed` 6;
- no permission cache entry.

**Smoke test:** no server error, and every back-office screen listed, including Spaces, Space types, 3D models, price plans, offers, inventory imports, tours and the enquiries report, answering 200 for the Super Admin. It runs in a `READ ONLY` session.

**If anything else changed:** full recovery below.

### 8. Restart the owner's server

Start `php artisan serve` as usual, sign in, and open Properties → Space types: six types, with "Can be sold as" showing only approved combinations.

## Full recovery

Stop the owner's server and restore the step-3 backup into an **emptied** `smart_go` (the dump re-creates only its own tables). Re-create the option file first: step 3 deleted it.

```bash
umask 077
php -r '$e=[]; foreach (file(".env", FILE_IGNORE_NEW_LINES) as $l) { if (preg_match("/^([A-Z_]+)=(.*)$/", $l, $m)) { $e[$m[1]] = trim($m[2], "\"'\''"); } } file_put_contents($argv[1], "[client]\nuser={$e["DB_USERNAME"]}\npassword={$e["DB_PASSWORD"]}\nhost={$e["DB_HOST"]}\nport={$e["DB_PORT"]}\n");' ~/smartgro-backups/.my.cnf
sha256sum -c "$B.sha256"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf -e "DROP DATABASE smart_go; CREATE DATABASE smart_go CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf smart_go < "$B"
shred -u ~/smartgro-backups/.my.cnf
```

This returns `smart_go` exactly to its state at step 3, including every audit entry. The application works either way, because every new table is guarded.
