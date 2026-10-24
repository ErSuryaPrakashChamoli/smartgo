# SmartGro — Local Development

| | |
|---|---|
| Status | **Active** (Phase 1 setup) |
| Last updated | 2026-10-24 |

---

## 1. Prerequisites (verified on the project machine, 2026-10-24)

| Tool | Version |
|---|---|
| PHP | 8.5.4 |
| Composer | 2.9.5 |
| Node.js / npm | 22.22.1 / 9.2.0 |
| MySQL server | 8.4.11 |
| Git | 2.53.0 |

Missing locally and **optional until Phase 3**:
* `redis-server` (apt candidate 8.0.5)
* `php8.5-redis` (6.2.0)
* `php8.5-bcmath`

```bash
sudo apt install redis-server php8.5-redis php8.5-bcmath
```

Until Redis is installed, cache, queue and session use the `database` drivers (ADR-0007).

---

## 2. MySQL for development and tests (D-26)

The application uses MySQL 8.4 everywhere (ADR-0002):
* **Development** uses database `smartgro`.
* **Tests** use database `smartgro_testing`. `phpunit.xml` forces both `DB_CONNECTION=mysql` and `DB_DATABASE=smartgro_testing`. The same MySQL user and password come from your `.env`.
* `tests/TestCase.php` **refuses to run** database traits against any database whose name does not end in `_testing`. This protects the development database if configuration is ever cached.

### One-time setup

Run this from the project root. It needs `sudo` for the local MySQL server.

```bash
bash docs/operations/scripts/setup-local-mysql.sh
```

To keep an existing development database name (e.g. `smart_go`), run:

```bash
SMARTGRO_DB_NAME=smart_go bash docs/operations/scripts/setup-local-mysql.sh
```

The test database is always `smartgro_testing`.

**Do not connect the application as MySQL `root`.** On a shared development server, `root` can read and change every other project's database. The script creates a `smartgro` user limited to the two SmartGro databases.

The script:
1. creates both databases
2. creates the `smartgro` user with a **randomly generated password**
3. writes the `DB_*` lines into your local `.env`, keeping a copy of the old file in `.env.backup`
4. runs migrations

**The password is never displayed.** Never paste it into chat, tickets, documentation or commits. Re-running the script rotates it.

Until the script has been run, `php artisan test` cannot connect to MySQL and will fail. This is expected; the suite no longer silently falls back to SQLite.

Optional hardening: `chmod 600 .env .env.backup`. Skip this if a separate web-server user must read `.env`.

---

## 3. Everyday commands

| Task | Command |
|---|---|
| Run the app (server, queue, logs, Vite) | `composer run dev` |
| Full test suite (MySQL) | `php artisan test --compact` |
| Fast loop without the multi-process concurrency tests | `php artisan test --compact --exclude-group=concurrency` |
| Only the MySQL concurrency harness | `php artisan test --compact --group=concurrency` |
| Format PHP before committing | `vendor/bin/pint --dirty --format agent` |
| Build frontend assets | `npm run build` |

### MySQL concurrency harness

`tests/Feature/Infrastructure/MySqlConcurrencyHarnessTest.php` proves, on real MySQL, the guarantees the booking engine depends on (ADR-0004). It runs before any booking code exists, using throwaway `harness_*` tables that it creates and drops itself:

1. the suite runs on MySQL 8.4 / InnoDB in a `*_testing` database
2. a row lock makes a competing transaction wait, rather than being ignored as on SQLite
3. a failed transaction rolls back all of its writes
4. concurrent overlapping requests produce exactly one confirmation when the parent row is locked first
5. concurrent requests never exceed shared capacity
6. **negative control:** check-then-insert without a lock double-books, proving the harness can detect the bug
7. **negative control:** a snapshot read *before* taking the lock also double-books, even though a lock is taken (see ADR-0004, "Implementation constraint")

Booking-domain tests (T-BK-*, `../testing/TEST_STRATEGY.md` §8) reuse this harness pattern and must pass before booking code is merged.

---

## 4. Version control

* Repository: local Git, branch `main`, initialised 2026-10-24. Remote: **not yet connected** (see §5).
* Never commit `.env`, `.env.*` (except `.env.example`), credentials, real customer data, database dumps or build output. `.gitignore` covers these. Review `git status` before every commit.
* Use small, reviewable commits with conventional prefixes (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
* Once the remote exists, work on short-lived feature branches and merge to `main` via pull request.

---

## 5. Connecting the private GitHub remote (owner action)

The GitHub CLI is not installed on this machine and no SSH key exists yet. Local version control works without a remote. Choose **one** option.

**First decide the repository owner (D-31):** your personal account, your company organisation, or the client's organisation. This affects who legally controls the code.

### Option A: GitHub CLI (simplest)

```bash
sudo apt install gh
gh auth login                      # choose GitHub.com, SSH, and generate a new key when asked
gh repo create <OWNER>/smartgro --private --source=. --remote=origin --push
```

### Option B: web UI + SSH key

```bash
ssh-keygen -t ed25519 -C "<your-email>"      # accept the default path; set a passphrase
cat ~/.ssh/id_ed25519.pub                    # copy this PUBLIC key
```

1. On github.com, go to **Settings → SSH and GPG keys → New SSH key** and paste the public key.
2. Go to **New repository**: Owner `<OWNER>`, Name `smartgro`, **Private**. Do **not** add a README, .gitignore or licence.
3. Then run:

```bash
ssh -T git@github.com                         # confirm authentication
git remote add origin git@github.com:<OWNER>/smartgro.git
git push -u origin main
```

### After the first push (recommended)

* Under **Settings → Branches**, add a protection rule for `main`: require a pull request and (once CI exists in Phase 3) passing status checks.
* Under **Settings → Code security**, enable secret scanning and push protection, if available for the account's plan.
