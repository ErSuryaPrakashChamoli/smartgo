# SmartGro — Local Development

| | |
|---|---|
| Status | **Active** (Phase 1 setup) |
| Last updated | 2026-10-25 |

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
| Bring staff roles and capabilities in line with the code (changes are audited) | `php artisan access:sync` |
| View the Phase 2 design previews (never served in production) | open `/design` on the local server |
| View the back-office design previews (Super Admin only, never in production) | open `/admin/design-previews` |
| Give an existing user a staff role | `php artisan access:assign-role <email> <role>` |

### Back office (Phase 3)

The staff back office is a Filament panel at `/{BACKOFFICE_PATH}` (default `/admin`). To sign in locally for the first time:

1. `php artisan migrate` and `php artisan access:sync`
2. `php artisan make:filament-user` to create your account. The command does not apply the staff password policy, so choose at least 12 characters with mixed case and a number.
3. `php artisan access:assign-role you@example.com super_admin`
4. Open the back office and sign in. Two-step login is required: set up an authenticator app (save the recovery codes) or email codes. With `MAIL_MAILER=log`, email codes are written to `storage/logs/laravel.log`.

Other staff accounts are then created in **Settings → Staff users**. Only active users with at least one of the six staff roles can enter (current behaviour; OD-74 will require the explicit `backoffice.access` permission instead, not implemented yet). The audit log is at **Settings → Audit log**.

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

**Deferred (OD-79, 2026-10-25):** no GitHub work happens until the application is fully developed and verified locally. The steps below are kept for the final handover.

The GitHub CLI is not installed on this machine and no SSH key exists yet. Local version control works without a remote. Choose **one** option.

**Repository owner (D-31):** a GitHub **organisation**, not a personal account (OD-29): organisation `Smartgro`, owned by **Smart Gro Services Private Limited** (OD-78, 2026-10-25).

### Setup checklist (OD-76; wording approved by the owner on 2026-10-25, OD-77)

**Decided:** organisation `Smartgro`; legal owner `Smart Gro Services Private Limited` (OD-78); this checklist's wording (OD-77).

Pre-push check (2026-10-25, read-only): GitHub has **no organisation `Smartgro`**. The name `smartgro` (GitHub names ignore capitalisation) belongs to a **personal user account created 2017-11-03**, so an organisation cannot use it. `Smartgro/smartgro` is not publicly visible, but whether it exists cannot be confirmed without signing in. No push credential is visible to this machine's terminal. See R-21.

**D-31 stays open until:**
* the owner creates the organisation and the private repository (Claude is not authorised to);
* the owner sets up the push credential (Claude is not authorised to);
* the owner separately authorises the first push (not granted).

Steps 1 to 5 need the owner's own GitHub account. **Never paste passwords, tokens or private keys into chat.**

1. **Create the organisation `Smartgro`** on github.com, owned by Smart Gro Services Private Limited, with a company-controlled contact email. The organisation, not a person, owns the code.
2. **Owners:** add a second trusted owner if one is available. No personal account may be the sole owner of production-critical code. If only one owner exists at first, record who it is as an open risk.
3. **Organisation security settings:**
   * **Authentication security:** require two-factor authentication for everyone in the organisation. Members without it are removed until they enable it.
   * **Member privileges:**
     * base permission "No permission";
     * members cannot create public repositories or change a repository's visibility;
     * only owners may delete or transfer repositories.
   * **Actions:** allow only the actions the CI workflow uses (`actions/checkout`, `actions/setup-node`, `shivammathur/setup-php`). Keep the default workflow token read-only; the workflow already sets `contents: read`.
4. **Repository:** create `smartgro` as **Private** and empty (no README, licence or `.gitignore`). Give access through a team: **Write** for developers, **Admin** for owners only.
5. **Credential for pushing from this machine.** Choose one and set it up yourself:
   * **Least privilege: a deploy key with write access to this repository only.** Run `ssh-keygen -t ed25519 -f ~/.ssh/smartgro_deploy -C "smartgro deploy key"`, then add the **public** key (`~/.ssh/smartgro_deploy.pub`) under the repository's Settings → Deploy keys, with write access allowed. Point Git at it with this `~/.ssh/config` entry:
     ```
     Host github-smartgro
       HostName github.com
       User git
       IdentityFile ~/.ssh/smartgro_deploy
       IdentitiesOnly yes
     ```
     The remote is then `git@github-smartgro:Smartgro/smartgro.git`.
   * **Option A or Option B below:** these give access to everything your own account can reach. Option A also installs a package (`gh`), which needs approval.
6. **First push** (step 3.1). The name and owner are confirmed (OD-78). Only after the owner has created the repository and the credential **and separately authorised the push**:
   * **Immediately before pushing**, re-check the full Git history and the current repository contents for secrets (the checks recorded in `../decisions/DECISION_REGISTER.md` §0.21). Push nothing if anything is found.
   * Keep `CLAUDE.md` out of commits unless the owner explicitly authorises its inclusion.
   * Then:
     ```
     git remote add origin <remote URL>
     git push -u origin main
     ```
   A push sends commits only. Staged or uncommitted changes, such as an owner edit to `CLAUDE.md`, are not sent. The commits carry the local Git name and email. If the GitHub account blocks command-line pushes that expose a private email, the push is refused. Decide this before pushing.
7. **After the first push:**
   * protect `main`: require a pull request and a passing "Lint, test and audit" check; no force pushes or deletion;
   * enable secret scanning and push protection if the plan includes them;
   * record the first CI run's result (AC-3.1).

The options below are kept for reference.

### Option A: GitHub CLI (simplest)

```bash
sudo apt install gh
gh auth login                      # choose GitHub.com, SSH, and generate a new key when asked
gh repo create Smartgro/smartgro --private --source=. --remote=origin --push
```

### Option B: web UI + SSH key

```bash
ssh-keygen -t ed25519 -C "<your-email>"      # accept the default path; set a passphrase
cat ~/.ssh/id_ed25519.pub                    # copy this PUBLIC key
```

1. On github.com, go to **Settings → SSH and GPG keys → New SSH key** and paste the public key.
2. Go to **New repository**: Owner `Smartgro`, Name `smartgro`, **Private**. Do **not** add a README, .gitignore or licence.
3. Then run:

```bash
ssh -T git@github.com                         # confirm authentication
git remote add origin git@github.com:Smartgro/smartgro.git
git push -u origin main
```

### After the first push (recommended)

* Under **Settings → Branches**, add a protection rule for `main`: require a pull request and the passing "Lint, test and audit" check (the CI workflow exists since Phase 3 step 3.10).
* Under **Settings → Code security**, enable secret scanning and push protection, if available for the account's plan.
