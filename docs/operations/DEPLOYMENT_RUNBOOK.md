# SmartGro — Deployment Runbook

| | |
|---|---|
| Status | **DRAFT — hosting provider not chosen (D-14).** No environment has been deployed. Completed in Phase 15. |
| Last updated | 2026-10-24 |

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
| PHP extensions | `pdo_mysql`, `mbstring`, `intl`, `bcmath` (recommended for money maths; **not loaded locally**, needs checking), `gd` or `imagick`, `exif`, `fileinfo`, `zip`, `pcntl`, `posix`, `redis` (phpredis), `sodium`, `openssl` | all loaded locally except `redis` and `bcmath` |
| Composer | 2.x | local: 2.9.5 |
| Node.js | 22 LTS for builds only | local: 22.22.1, npm 9.2.0 |
| MySQL | 8.4 LTS, InnoDB, `utf8mb4` | local server: 8.4.11 |
| Redis | 7.x or managed equivalent | cache, sessions, queues, locks |
| Object storage | S3-compatible bucket(s): public-read media + private documents | |
| CDN / WAF | Cloudflare (requested) | |
| Processes | web (PHP-FPM or equivalent), queue workers (Horizon), scheduler (`schedule:run` every minute) | |

---

## 3. Environment variables (names only; values live in the host secret store)

Present in the current `.env.example`: `APP_*`, `DB_*`, `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`, `MAIL_*`, `AWS_*`, `FILESYSTEM_DISK`, `VITE_APP_NAME`.

To be added as integrations are chosen: payment gateway keys and webhook secret, WhatsApp/SMS provider credentials, Turnstile site/secret keys, error-monitoring DSN, CDN purge token. **Never commit real values.**

---

## 4. Standard release procedure (to be adapted to the chosen host)

1. CI green: tests (MySQL), Pint, `composer audit`, `npm audit`, build.
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan down --render="errors::503" --retry=60` (only for migrations that need it; prefer zero-downtime)
5. `php artisan migrate --force` (backups taken first; destructive migrations need a separately approved plan)
6. `php artisan optimize` and `php artisan filament:optimize`
7. `php artisan horizon:terminate` (or `queue:restart`) so workers load the new code
8. `php artisan up`
9. Smoke test: `/up` health route, home, one location page, back-office login, a test enquiry in staging.
10. Purge the CDN for changed static paths, if not fingerprinted.

**Rollback:** redeploy the previous release artefact. Migrations must be backward-compatible for one release (expand → migrate → contract) so a code rollback never needs a schema rollback.

---

## 5. Backups and recovery (proposed targets, to be confirmed in D-14)

| Item | Target |
|---|---|
| Database | daily full + point-in-time recovery (binlog); RPO ≤ 1 h, RTO ≤ 4 h |
| Object storage | versioning enabled; lifecycle for old versions |
| Restore drill | before launch, then quarterly |

---

## 6. Open items

* D-14: host, accounts and ownership, region (data residency), domain/DNS access.
* D-19: existing website and URLs to redirect at cut-over.
* D-23: Git hosting and CI provider.
