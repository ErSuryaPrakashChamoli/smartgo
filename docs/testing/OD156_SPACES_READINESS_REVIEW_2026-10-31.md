# SmartGro — OD-156: Spaces readiness review

Review of 2026-10-31, 15:27–15:50 IST. Evidence: `evidence/phase14/2026-10-31-od156/`. Verification and documentation only: no commit, no push, and no write to `smart_go`. The only document changed beyond the records is `../operations/SPACES_ACTIVATION_PROCEDURE.md`, where the evidence showed mismatches.

## 1. Summary

**Not ready for live activation.** Open:
- A-22 and A-23 (authorisation);
- **A-28** (both proposed combinations undecided);
- **A-29** (the inventory commit-gate conflict below);
- recommended: A-27 (off-machine backup) and A-30 (cached configuration permissions).

The reviewed procedure is ready for when those are settled.

**Three changes since OD-155, none made by this session:**
1. Commit **`8325493` "update oct 31"** (15:24:36) committed the whole working tree, 770 files, **including the inventory module**, which OD-128 (a)(3) says waits for the entry gate.
2. At **15:24:51** the OD-153 migration **ran on `smart_go`**: an empty `product_space_type` table, batch 12, nothing else.
3. At **15:25** a configuration, route and event cache was built. Its `config.php` holds the database password and app key and is readable by other local users (mode 664).

**The Spaces rule** holds for creating and editing a space, runs of spaces, the inventory import, mapping changes (including refusing to remove a type in use) and audit, and refuses deactivated accounts. 117 relevant tests pass on `smartgro_testing`.

**A-28:** the seeder would enable both unapproved combinations automatically. The revised procedure removes every combination not explicitly approved immediately after seeding (step 5a, rehearsed). On `smart_go` today neither is enabled: the table is empty.

## 2. Repository state (verified)

| Item | Fact |
|---|---|
| Branch, HEAD | `main`, `832549382a367d686d9c0981471f2ce66f5270d4` |
| History above `8a45ae6` | `ee9083c` (OD-152, 23 applied migrations) → `5ef20f4` (OD-155, `product_space_type` migration) → `8325493` ("update oct 31") |
| `5ef20f4` | unchanged: parent `ee9083c`, one file (`database/migrations/2026_10_31_064952_create_product_space_type_table.php`, 35 lines); its blob `996b4d2…` equals the working file. Not amended or rewritten. |
| `8325493` | made 15:24:36 IST with this machine's git identity, **without** the Claude attribution trailer this session's commits carry, in the style of the owner's earlier commits; **not made by this session**. 770 files (620 added, 149 modified, 1 deleted: `public/robots.txt`), +231,736/−880. Includes: the inventory module (`app/Actions/Inventory/*`, `Space`, `SpaceType`, `Floor`, the Spaces, Space types and inventory-import screens, the import services), OD-153's Spaces code, `SetCategorySpaceTypes`, `User.php`, 62 test files, 408 documentation and evidence files. |
| Secrets in `8325493` | none: `APP_KEY` and `DB_PASSWORD` values absent; no `.env`, dump, key or option file; no generic secret pattern; no file over 2 MB. The only personal addresses outside `composer.lock` are the owner's login email already in the register and status log (OD-138), and the git author address in OD-152's evidence. |
| Push | none: `main` is 3 commits ahead of the last-known `origin/main` (local tracking reference; the remote was not contacted) |
| Working tree | **clean**: nothing staged, nothing modified, no untracked file. Everything that was uncommitted at the end of OD-155 is in `8325493`. |

**Committed versus uncommitted.** All Spaces work is now committed: the migration in `5ef20f4`, everything else in `8325493`. Nothing is uncommitted except this review's own files (§9).

## 3. The OD-128 inventory commit gate (A-29)

OD-128 (a)(3), owner direction: "The inventory implementation is not committed before the first property's real inventory has passed the documented import-template entry gate". It confirms OD-30's "before inventory code is merged". The gate has not been passed: no real inventory has been imported.

`8325493` nevertheless contains the inventory implementation. This session did not make it, and did not amend, revert or rewrite anything. OD-127 (b) foresaw this case: "If the owner commits the working tree, this inventory code would be merged before the gate; that needs the owner's own decision on OD-30." No such decision is recorded.

**Owner decision needed (A-29):**
- **(a)** record that the inventory code may stay committed before the gate, which amends OD-128 (a)(3) and OD-30 for history while real inventory still waits for the gate; or
- **(b)** take it out of `8325493` before any push. That means rewriting local history or a revert commit, which only the owner should choose. A revert would also undo the rest of that commit.

**Until decided:** do not push.

## 4. `smart_go`

| When (IST) | What | By |
|---|---|---|
| 12:26:45 | one guest session row (OD-153 d) | a browser request to a server on `smart_go`, not this session |
| 15:24:51 | `product_space_type` created (empty); `migrations` row 70, batch 12 | outside this session, 15 s after `8325493`; no backup was taken for it, the latest is OD-150's (09:34) |
| 15:25:11–22 | `bootstrap/cache` rebuilt (configuration, routes, events, packages, services, Blade icons) | outside this session |
| during OD-156 | read-only only: fingerprints, the post-check script and catalogue queries, each in a `READ ONLY` session | this session |

**This session made no change to `smart_go`.** Its fingerprint at the end of this review equals the one taken at its start (§8). The cached configuration still names `smart_go`, with `BACKOFFICE_SAMPLE_CONTENT` = false, `APP_ENV=local` and debug on.

**A-30 (recommended):** `bootstrap/cache/config.php` holds every configuration value in plain text, including the database password and app key, at mode **664**. It is ignored by git and was not committed. `chmod 600 bootstrap/cache/config.php` matches OD-153's `.env` change. A future `optimize` re-creates the file with the default mode. Not changed in this review.

## 5. Spaces rule (committed code at HEAD)

| Path | Where enforced | Evidence |
|---|---|---|
| Create a space | `SaveSpace::validate()`: the type must allow the mode; each category must accept the mode and, where the list exists, the type | `InventoryActionsTest`: "sells a space only as categories that accept its space type…" |
| Edit a space | same `validate()` (`$space` given) | "applies the space-type rule when a space is edited, to a run of spaces…" (OD-155) |
| Runs of spaces | `AddSpaces` → `SaveSpace` per code, in one transaction (all or none) | same test: nothing created |
| Import | `WorkbookValidator` V-117 (type check after the mode check) | `InventoryWorkbookTest`: "refuses a space sold as a category that does not accept its space type (V-117…)" |
| Change a mapping | `SetCategorySpaceTypes`: `products.manage` through `ProductPolicy`; validated; audited `space_types_changed` (old, new) | `InventoryScreensTest` (form) and `InventoryActionsTest` (action); audit asserted in both |
| Remove a mapping in use | form rule and action check (lists the space codes) | form test and OD-155 action test |
| Deactivated accounts | `ProductPolicy::before()` (mapping changes); `User::hasCapabilityForProperty/EveryProperty` require `is_active` (spaces, floors) | OD-153 test (mutation-checked); this review on the replica: a deactivated Super Admin is refused by `SetCategorySpaceTypes` (`AuthorizationException`), links unchanged, and may not add spaces |
| Without the table | `Product::hasSpaceTypes()` false: mode-only, no query to the table | OD-153 guard test. Not the case on `smart_go` any more: since 15:24:51 the rule is active there (the table is empty and there are no space types or spaces, so nothing is affected). |

## 6. A-28 (open)

**No owner decision exists** (OD-155 c):
- OD-07 and OD-109 (h) only keep the two categories separate;
- ERD §3.1 allows conference rooms as meeting rooms "if the owner allows";
- the synthetic workbook, the seeder and the rehearsals are not approvals.

It covers:

| # | Combination | Basis of the proposal |
|---|---|---|
| (1) | flexible seating zone → **dedicated desks** | hybrid zones (OD-15) and the synthetic workbook |
| (2) | conference or training room → **meeting rooms** | ERD §3.1 "if the owner allows" and the workbook |

- **Current mapping:** on `smart_go`, none (the table is empty). The proposed seeder default contains both.
- **Would activation enable them silently?** With the OD-155 procedure, yes: step 5 adds both, and it only removed (2), only after activation and only on a "refuse". **Corrected:** step 5a now removes every combination not explicitly approved, both parts and undecided included, right after the seeder and before permissions or the server.
- **Rehearsed on a replica of today's `smart_go`:**
  - the seeder enables both;
  - step 5a removes both (10 → 8 links, two audit entries);
  - running step 5a again changes nothing;
  - running the seeder again later does not re-add them, because each type keeps another category.

## 7. Activation procedure review

Checked against the brief:

| Requirement | Status |
|---|---|
| Target identified before any write | yes: step 1 (`config:show`, `SELECT DATABASE()`), explicit `DB_DATABASE=smart_go`, cached configuration bypassed |
| Verified backup and fingerprint | yes: step 2 (fingerprint and pre-check), step 3 (dump, SHA-256, restore check) |
| Explicit names for destructive test operations | yes: `DROP`/`CREATE DATABASE smartgro_testing` named in SQL; no `db:wipe` |
| Migration, reference data and `access:sync` as separate steps | yes: steps 4, 5 and 6 (plus 5a) |
| Expected changes and postconditions per step | yes, rehearsed figures |
| Partial-failure recovery without implying the rollback reverses data | **corrected:** step 4 now states the rollback leaves space types, permissions, role links and audit entries; step 5 states deleting space types adds `deleted` audit entries; only the backup restores audit history |
| Pre- and post-checks for permissions, reference data, mappings, audit entries and screens | **added:** `postcheck.php` (read-only) with expected output before and after; fingerprint delta; smoke test |
| A-28 before activating mappings | **corrected:** both parts in the gates; step 5a |
| No plain `db:seed` | yes: `--class=ReferenceDataSeeder` only, and stated |

**Other corrections:**
- the statements made false by `8325493`, the 15:24:51 migration and the cache rebuild (now "already applied; confirm only"; expected counts 65 tables, 70 migrations, batch 12);
- one shell with every path variable defined;
- step 0 explains how to recognise the owner's server;
- full recovery re-creates the deleted credentials file.

## 8. Tests and checks run

| Command | Database | Result |
|---|---|---|
| `vendor/bin/pest --compact tests/Feature/Actions/Inventory tests/Feature/Filament/Clusters/Properties tests/Feature/Services/Inventory tests/Feature/Database tests/Feature/Actions/Access tests/Feature/Actions/Content/RevisionWorkflowTest.php` with the three config/route/event cache overrides (essential now that a cached configuration exists) | `smartgro_testing`: forced by `phpunit.xml` and `TestCase` (`*_testing` only); afterwards 70 migrations in batch 1, 0 users | **117 passed, 705 assertions, exit 0** (15:31:24–15:33:18) |
| Replica rehearsal: OD-150 backup + 25 migrations + OD-153 migration, then reference data, step 5a twice, `access:sync`, reference data again, with `postcheck.php` and a fingerprint after each step | `smartgro_testing`, emptied by name in SQL, `SELECT DATABASE()` checked | as §6 and the procedure; `rehearsal-*.json` |
| Deactivated-account check | `smartgro_testing` | refused, links unchanged |
| `postcheck.php smart_go` and fingerprints | `smart_go`, READ ONLY | baseline in §4; start and end of this review equal |

**Boost:** this project's MCP server runs with `CACHE_STORE=array`. Not used against `smart_go`.

## 9. Files changed by this review (uncommitted)

- `docs/operations/SPACES_ACTIVATION_PROCEDURE.md` (revised)
- this report
- `docs/testing/evidence/phase14/2026-10-31-od156/` (the post-check script and outputs, rehearsal deltas, deactivated check)
- the decision register and the status log

## 10. Owner decisions and the next safe action

1. **A-29:** decide on `8325493` versus the inventory commit gate (§3). Do not push until then.
2. **A-28:** approve or refuse each of the two combinations (§6). Anything left undecided is removed at activation.
3. **A-27** (recommended): name a second backup destination off this machine. **A-30** (recommended): `chmod 600 bootstrap/cache/config.php`.
4. **Authorise A-22 and A-23** for `smart_go` when ready. Then, and only then, run `../operations/SPACES_ACTIVATION_PROCEDURE.md` from step 0. Its step 3 takes the fresh backup that the 15:24:51 migration did not have.

**Next safe action:** the owner's decisions above. Nothing further will run on `smart_go` without that separate, explicit authorisation.
