# SmartGro — activating Spaces on `smart_go` (prepared, not executed)

Prepared 2026-10-31 (OD-155) and rehearsed in exactly this order on a replica of `smart_go` in `smartgro_testing`. Evidence: `../testing/evidence/phase14/2026-10-31-od155/`.

**Nothing here has been run on `smart_go`.** Running it needs the owner's separate authorisation (A-22, A-23 and the OD-153 migration). `smart_go` is the database of the owner's own server, not a deployed production environment.

## What activation does, and does not do

- **Does:**
  - adds the table that says which space types each workspace category accepts;
  - loads the six proposed space types and the proposed type-category links;
  - adds the 14 permissions the code defines and `smart_go` lacks.

  The Spaces, Space types, 3D models, pricing, offers, inventory import, tours and enquiries-report screens then open for the roles that hold those permissions.
- **Does not:**
  - create floors, spaces, seats, allocations, prices or any other inventory (Phase 5 entry gate, OD-30, OD-128);
  - change content, publish anything or touch `.env` (`BACKOFFICE_SAMPLE_CONTENT=false` stays);
  - run any other seeder.

  **Never run a plain `php artisan db:seed` or `migrate --seed`:** `DatabaseSeeder` runs `access:sync` and creates a factory "Test User".

## Before starting (gates)

| Gate | Check |
|---|---|
| Authorisation | the owner has authorised A-22 (`access:sync`), A-23 (reference data) and the OD-153 migration on `smart_go` |
| A-28 decided | whether conference and training rooms may be sold as meeting rooms. The proposed data allows it; if the decision is "refuse", step 9 removes it. Do not treat the default as approved. |
| Migration committed | `database/migrations/2026_10_31_064952_create_product_space_type_table.php` is in commit `5ef20f4` (`git log --oneline -- <file>`) |
| Code present | the working tree holds the OD-153 code (it is not committed, OD-128 a 3). The owner's server runs this working tree. |
| Backup copy | recommended: A-27 (a second copy of backups off this machine) resolved first |
| Machine quiet | no test suite or browser kit running (they use `smartgro_testing`, which step 3 uses too) |

## Environment for every command

Run from the project root. Every `php artisan` command below assumes:

```bash
cd /home/administrator/Documents/website/smartGrow
export DB_DATABASE=smart_go APP_CONFIG_CACHE=/nonexist/c.php APP_ROUTES_CACHE=/nonexist/r.php APP_EVENTS_CACHE=/nonexist/e.php \
  SESSION_DRIVER=array QUEUE_CONNECTION=sync LOG_CHANNEL=stderr
```

- **`DB_DATABASE=smart_go`** names the target explicitly instead of trusting `.env`.
- **The three `APP_*_CACHE` paths** never exist, so a cached configuration cannot redirect the commands.
- **`SESSION_DRIVER`, `QUEUE_CONNECTION` and `LOG_CHANNEL`** keep the commands from writing sessions, jobs or the shared log.
- **`CACHE_STORE` is deliberately not overridden.** `access:sync` must clear `smart_go`'s own permission cache.
- **Credentials are never printed.** The backup step reads them from `.env` into a private option file and deletes it afterwards.

## Steps

### 0. Stop the owner's server and confirm nothing uses `smart_go`

- **Command:** stop `php artisan serve` in its terminal (Ctrl+C), then:

  ```bash
  ss -ltnp | grep -E ':(8000|8001|8002|8003)\b'
  for p in $(pgrep -f "artisan serve|php -S"); do echo "$p $(readlink /proc/$p/cwd) $(tr '\0' '\n' < /proc/$p/environ | grep ^DB_DATABASE=)"; done
  ```

- **Expected:** no process with this project's directory that uses `smart_go` (the demo and site use `smartgro_demo` and `smartgro_site`).
- **If not:** stop there. Never stop another project's server.

### 1. Verify the target (read-only)

```bash
php artisan config:show database.connections.mysql.database          # smart_go
php artisan tinker --execute 'echo DB::selectOne("SELECT DATABASE() d")->d, PHP_EOL;'   # smart_go
ls bootstrap/cache                                                     # no config.php, routes-*.php or events.php
php artisan migrate:status --pending                                   # exactly one: 2026_10_31_064952_create_product_space_type_table
```

**Stop if** any answer differs, for example a different database, more than one pending migration, or a cached configuration.

### 2. Fingerprint `smart_go` (read-only)

```bash
F=docs/testing/evidence/phase14/2026-10-31-od150-smart-go
php $F/fingerprint.php smart_go $F/smart_go-before.json > ~/smartgro-backups/smart_go-before-spaces-activation.json
```

This records every table's rows, a hash of every row and the schema, in a `READ ONLY` session. **Expected now:** 64 tables, 69 migrations (batch 11), 34 permissions, 0 space types.

### 3. Back up `smart_go` and check that the backup restores

```bash
umask 077
php -r '$e=[]; foreach (file(".env", FILE_IGNORE_NEW_LINES) as $l) { if (preg_match("/^([A-Z_]+)=(.*)$/", $l, $m)) { $e[$m[1]] = trim($m[2], "\"'\''"); } } file_put_contents($argv[1], "[client]\nuser={$e["DB_USERNAME"]}\npassword={$e["DB_PASSWORD"]}\nhost={$e["DB_HOST"]}\nport={$e["DB_PORT"]}\n");' ~/smartgro-backups/.my.cnf
B=~/smartgro-backups/smart_go-$(date +%F-%H%M)-before-spaces-activation.sql
mysqldump --defaults-extra-file=$HOME/smartgro-backups/.my.cnf --single-transaction --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF smart_go > "$B"
chmod 600 "$B"; sha256sum "$B" > "$B.sha256"; tail -1 "$B"            # "-- Dump completed on …"
# Restore check, in the TEST database only. The database is named in the SQL itself, never taken from the
# environment, so this line cannot empty smart_go (do not use `artisan db:wipe` here: under the export above
# it would target smart_go).
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf -e "DROP DATABASE smartgro_testing; CREATE DATABASE smartgro_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf smartgro_testing < "$B"
php $F/fingerprint.php smartgro_testing $F/smart_go-before.json > /tmp/restored.json
python3 docs/testing/evidence/phase14/2026-10-31-od155/delta.py ~/smartgro-backups/smart_go-before-spaces-activation.json /tmp/restored.json   # expect: no changed table
shred -u ~/smartgro-backups/.my.cnf
```

- **Target:** reads `smart_go`, writes only the backup file and `smartgro_testing`.
- **Stop if** the dump does not end with "Dump completed", or the restored copy differs from step 2 in any table.
- **Afterwards:** the copy in `smartgro_testing` holds staff accounts. The next test run removes it (Pest refuses any database not named `*_testing`).

### 4. Add the table (OD-153 migration)

```bash
php artisan migrate --pretend --force --path=database/migrations/2026_10_31_064952_create_product_space_type_table.php
php artisan migrate --force --path=database/migrations/2026_10_31_064952_create_product_space_type_table.php
```

- **Target:** `smart_go`.
- **Expected SQL:** `create table product_space_type` (product_id, space_type_id, timestamps, primary key on both), two foreign keys (cascade on delete) and an index on `space_type_id`. No data.
- **Expected changes:** new table `product_space_type`; `migrations` +1 (batch 12); nothing else.
- **Idempotency:** a second run says "Nothing to migrate" and changes nothing (rehearsed).
- **Failure:** MySQL table changes are not transactional. If it fails, check whether `product_space_type` exists without a `migrations` row. If so, remove it with `php artisan tinker --execute 'Schema::dropIfExists("product_space_type");'` (only in that situation), then run the command again.
- **Recovery:** `php artisan migrate:rollback --batch=12 --force`. Check first that batch 12 holds only this migration (`migrate:status`). It drops only this table (rehearsed).

### 5. Load the reference data (space types and proposed links)

```bash
php artisan db:seed --class=ReferenceDataSeeder --force
```

- **Target:** `smart_go`.
- **Expected changes** (rehearsed):
  - `space_types` 0 → **6**: private cabin or office, dedicated desk zone, flexible seating zone, meeting room, conference or training room, office suite or floor plate.
  - `product_space_type` 0 → **10**:

    | Space type | Can be sold as |
    |---|---|
    | private cabin | private offices, managed offices |
    | dedicated zone | dedicated desks |
    | flexible zone | hot desks, dedicated desks |
    | meeting room | meeting rooms |
    | conference or training room | conference and training rooms, **meeting rooms (A-28)** |
    | office suite | managed offices, private offices |

  - `activity_log` +6 (`inventory` / `created`).
  - Nothing else: cities, categories, segments, areas, locations, buildings and profiles already exist and are never updated.
- **Idempotency:** a second run changes nothing. Links are added only to a space type that no category accepts yet, so staff choices are never undone.
- **Failure:** there is no wrapping transaction, so a failure can leave part of the data. Running the command again completes what is missing.
- **Recovery:** while no space exists, removing the six space types removes their links too (cascade). Otherwise restore the step-3 backup.

### 6. Synchronise permissions

```bash
php artisan access:sync
```

- **Target:** `smart_go`, with its own (database) cache store.
- **Expected changes** (rehearsed):
  - `permissions` 34 → **48**: inventory.manage, allocations.view, allocations.manage, inventory.import, inventory.import.approve, pricing.view, pricing.manage, pricing.approve, offers.manage, leads.reports.view, tours.view, tours.manage, twin_models.view, twin_models.manage.
  - `role_has_permissions` 79 → **125**, **none removed**:

    | Role | Permissions after |
    |---|---|
    | Super Admin | 48 |
    | Operations Manager | 14 |
    | Property Manager | 15 |
    | Sales/CRM Executive | 11 |
    | Marketing Manager | 27 |
    | Finance | 10 |

  - `activity_log` +6 (`access` / `role_capabilities_changed`, one per role).
  - The `spatie.permission.cache` entry deleted from `cache`.
  - Users and their roles unchanged.
- **Idempotency:** a second run changes nothing.
- **Failure:** the permission and role changes run in one transaction, so a failure leaves them unchanged. The cache entry is cleared only after the transaction commits.
- **Recovery:** restore the step-3 backup. Removing permissions by hand is not recommended.

### 7. Verify

```bash
php $F/fingerprint.php smart_go $F/smart_go-before.json > ~/smartgro-backups/smart_go-after-spaces-activation.json
python3 docs/testing/evidence/phase14/2026-10-31-od155/delta.py ~/smartgro-backups/smart_go-before-spaces-activation.json ~/smartgro-backups/smart_go-after-spaces-activation.json
mkdir -p /tmp/smartgro-smoke/framework/{views,cache,sessions} /tmp/smartgro-smoke/logs /tmp/smartgro-smoke/app
CACHE_STORE=array LARAVEL_STORAGE_PATH=/tmp/smartgro-smoke php -d variables_order=EGPCS $F/http-smoke.php smart_go > /tmp/smoke.json
```

**Expected:** exactly these changes, and nothing else:

| Table | Change |
|---|---|
| `product_space_type` | new; 10 rows (9 after step 9 if A-28 is refused) |
| `migrations` | +1 |
| `space_types` | +6 |
| `permissions` | +14 |
| `role_has_permissions` | +46 |
| `activity_log` | +12 |
| `cache` | −1, if the entry existed |

The read-only smoke test should report no server error, and every back-office screen listed, including Spaces, Space types, 3D models, price plans, offers, inventory imports, tours and the enquiries report, answering 200 for the Super Admin. It runs in a `READ ONLY` session, so it cannot write.

**If anything else changed:** restore the step-3 backup (recovery below).

### 8. Restart the owner's server

Start `php artisan serve` as usual, sign in, and open Properties → Space types: there should be six types, with "Can be sold as".

### 9. Only if A-28 is decided "refuse"

Properties → Workspace categories → **Meeting rooms** → untick "Conference or training room" → Save.

- **Expected:** `product_space_type` −1, and one audit entry `space_types_changed` with old `[conference_training_room, meeting_room]` and new `[meeting_room]` (rehearsed through the same action).
- **Not possible** while a conference or training room is sold as a meeting room. Today none exists.

## Full recovery

Stop the owner's server and restore the step-3 backup into an **emptied** `smart_go` (the dump re-creates only its own tables). Use the credentials file as in step 3:

```bash
sha256sum -c "$B.sha256"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf -e "DROP DATABASE smart_go; CREATE DATABASE smart_go CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql --defaults-extra-file=$HOME/smartgro-backups/.my.cnf smart_go < "$B"
```

This returns `smart_go` exactly to its state at step 3. The application works either way, because every new table is guarded.
