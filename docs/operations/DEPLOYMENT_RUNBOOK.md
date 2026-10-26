# SmartGro — Deployment Runbook

| | |
|---|---|
| Status | **Phase 1 baseline v1.0 (2026-10-24), submitted for owner approval. Deployment plan.** Hosting provider to be chosen in Phase 3 (D-14). The provider-specific runbook is completed in Phase 15. |
| Last updated | 2026-10-25 |
| Local development | [`LOCAL_DEVELOPMENT.md`](LOCAL_DEVELOPMENT.md) |

---

## 0. Source control and CI (plan)

| Item | State / plan |
|---|---|
| Repository | Git, branch `main`, initialised 2026-10-24 with a reviewed baseline. **GitHub setup and deployment are deferred until the application is fully developed and verified locally (OD-79).** The only remote is the owner's interim private backup `ErSuryaPrakashChamoli/smartgo` (OD-80). D-31 decided the organisation `Smartgro`, owned by Smart Gro Services Private Limited (OD-78). The owner creates the organisation, repository and push credential, and the first push needs separate authorisation. The pre-push check found the GitHub name `smartgro` held by a personal user account (R-21). Steps: `LOCAL_DEVELOPMENT.md` §5. |
| Branching | short-lived feature branches → pull request → `main`. `main` is protected once the remote exists. |
| CI (Phase 3, `.github/workflows/ci.yml`) | on every push to `main` and every PR: PHP 8.5 + MySQL 8.4 service container → `composer install` → `npm ci && npm run build` → `vendor/bin/pint --test` → `php artisan test` (full suite, including the `concurrency` group) → `composer audit` and `npm audit --audit-level=high`. A green CI is required to merge. The workflow is committed and starts running once the remote exists (D-31). |
| Secrets in CI | the CI MySQL uses throwaway credentials defined in the workflow (test-only). Deployment secrets live only in the host's secret store or encrypted CI secrets. |
| Deploy flow | `main` → automatic deploy to **staging** → owner/client acceptance → manual promotion to **production** (§4) |

---

## 1. Environments (proposed)

| Environment | Purpose | Data | Access |
|---|---|---|---|
| Local | development | factories/seeders only, **no real customer data** | developers |
| Staging | owner review, UAT, training | anonymised or synthetic | owner, team; `noindex`, password or Cloudflare Access |
| Production | live | real | restricted; MFA everywhere |

---

## 2. Runtime requirements (derived from the verified stack, 2026-10-24)

| Component | Requirement | Notes |
|---|---|---|
| PHP | 8.5.x with OPcache | local: 8.5.4 |
| PHP extensions | `pdo_mysql`, `mbstring`, `intl`, `bcmath` (recommended for money maths), `gd` or `imagick`, `exif`, `fileinfo`, `zip`, `pcntl`, `posix`, `redis` (phpredis), `sodium`, `openssl` | all loaded locally except `redis` and `bcmath`; apt candidates `php8.5-redis` 6.2.0 and `php8.5-bcmath` are available |
| Composer | 2.x | local: 2.9.5 |
| Node.js | 22 LTS for builds only | local: 22.22.1, npm 9.2.0 |
| MySQL | 8.4 LTS, InnoDB, `utf8mb4` | local server: 8.4.11 |
| Redis | 7.x or later, or a managed equivalent (local apt candidate: 8.0.5) | cache, sessions, queues, locks |
| Object storage | S3-compatible bucket(s): public-read media + private documents | |
| CDN / WAF | Cloudflare (requested) | |
| Processes | web (PHP-FPM or equivalent), queue workers (Horizon), scheduler (`schedule:run` every minute) | |

---

## 3. Environment variables (names only; values live in the host secret store)

Present in the current `.env.example`: `APP_*`, `BACKOFFICE_PATH` (use a non-default value in production, D-30), `DB_*`, `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`, `MAIL_*`, `AWS_*`, `FILESYSTEM_DISK`, `VITE_APP_NAME`.

**`APP_URL` must be the production address over HTTPS** (for example `https://www.example.com`, no trailing slash) in every public environment. Canonical URLs (`<link rel="canonical">`), menu and media links are built from it, never from the request's host header (FR-SEO-1, OD-93). A wrong or `http://` value publishes wrong canonical URLs; check it before every first release to a new environment. `BACKOFFICE_TIMEZONE` defaults to `Asia/Kolkata`.

To be added as integrations are chosen: payment gateway keys and webhook secret, WhatsApp/SMS provider credentials, Turnstile site/secret keys, error-monitoring DSN, CDN purge token. **Never commit real values.**

---

## 4. Standard release procedure (to be adapted to the chosen host)

1. CI green: tests (MySQL), Pint, `composer audit`, `npm audit`, build.
2. `composer install --no-dev --optimize-autoloader`. Once the Filament panel exists, this also runs `filament:upgrade`, which republishes Filament's git-ignored `public/{css,js,fonts}/filament` assets. Never skip it.
3. `npm ci && npm run build`
4. `php artisan down --render="errors::503" --retry=60` (only for migrations that need it; prefer zero-downtime)
5. `php artisan migrate --force` (backups taken first; destructive migrations need a separately approved plan)
6. `php artisan access:sync` (brings staff roles and capabilities in line with the code; safe on every release; any change to a role's capabilities is written to the audit log). **It must run before new code serves requests:** back-office access needs the `backoffice.access` permission it assigns (OD-74).
7. `php artisan optimize` and `php artisan filament:optimize`
8. `php artisan horizon:terminate` (or `queue:restart`) so workers load the new code
9. `php artisan up`
10. Smoke test: `/up` health route, home, one location page, back-office login, a test enquiry in staging.
11. Purge the CDN for changed static paths, if not fingerprinted.

**First deployment only:** create the first back-office account with `php artisan make:filament-user` (this command does not apply the staff password policy, so choose a password of at least 12 characters with mixed case and a number), then `php artisan access:assign-role <email> super_admin`. The Super Admin then signs in, sets up two-step login and creates the other staff accounts in Settings → Staff users.

**Rollback:** redeploy the previous release artefact. Migrations must be backward-compatible for one release (expand → migrate → contract) so a code rollback never needs a schema rollback.

---

## 5. Backups and recovery (proposed targets, to be confirmed in D-14)

| Item | Target |
|---|---|
| Database | daily full + point-in-time recovery (binlog); RPO ≤ 1 h, RTO ≤ 4 h |
| Object storage | versioning enabled; lifecycle for old versions |
| Restore drill | before launch, then quarterly |

---

## 6. Hosting decision criteria (for D-14, Phase 3)

| Criterion | Requirement |
|---|---|
| Runtime | PHP 8.5 with the extensions in §2; MySQL 8.4; Redis; long-running queue workers (Horizon) and a scheduler |
| Region | India region preferred (latency, and residency pending legal advice, D-15) |
| Ownership | accounts owned by the client or by the contracting entity, as agreed. No personal accounts for production. |
| Operations | managed backups with point-in-time recovery, zero-downtime deploys, staging environment, log access, autoscaling optional |
| Edge | works behind Cloudflare (DNS, WAF, Turnstile, CDN cache purge by URL/tag) |
| Cost | monthly estimate for staging + production presented to the client before selection |

Candidates (to be assessed against the criteria, none selected): managed Laravel hosting (e.g. Laravel Cloud), a managed VPS with a deployment tool, or a cloud provider's managed services.

## 7. Open items

* D-14: host, accounts and ownership, region, domain/DNS access (deferred until local completion, OD-79)
* D-19: existing website and URLs to redirect at cut-over
* D-31: organisation `Smartgro` decided (OD-78); the destination conflict (R-21), repository and credential setup, and push authorisation are open. The CI workflow is ready and runs once the remote exists.
* Trusted proxies: behind Cloudflare, configure Laravel to trust Cloudflare's proxy IP ranges so that `request()->ip()` is the visitor's address. Login throttling and the IP addresses in the audit log depend on it.
* Production session settings: `SESSION_SECURE_COOKIE=true`; confirm the staff idle timeout (`SESSION_LIFETIME`, 120 minutes proposed)
